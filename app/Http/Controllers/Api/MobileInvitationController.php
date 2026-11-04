<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ReservationController;
use App\Jobs\SendInvitationSms;
use App\Models\Reservation;
use App\Models\ReservationInvitation;
use App\Models\SmsMessage;
use App\Models\User;
use App\Services\TextBeeSmsGateway;
use App\Support\ReservationInvitationPresenter;
use App\Support\SmsPhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class MobileInvitationController extends ReservationController
{
    public function createInvitation(Request $request, Reservation $reservation): JsonResponse
    {
        $this->authorizeReservationAccess($request, $reservation);

        if (! in_array($reservation->status, ['pending_review', 'confirmed', 'rescheduled'], true)) {
            return response()->json([
                'message' => 'Guests can only be invited to an active upcoming booking.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:191'],
        ]);
        $phone = SmsPhoneNumber::normalize($validated['phone']);

        if (! $phone) {
            throw ValidationException::withMessages([
                'phone' => ['Enter a valid Philippine mobile number.'],
            ]);
        }

        $email = isset($validated['email']) ? Str::lower(trim($validated['email'])) : null;
        $invitee = $this->findCustomer($email, $phone);
        $inviter = $request->user();

        if (($invitee && (int) $invitee->id === (int) $inviter->id)
            || SmsPhoneNumber::normalize($inviter->phone) === $phone
            || ($email && Str::lower($inviter->email) === $email)) {
            throw ValidationException::withMessages([
                'phone' => ['You cannot invite your own account to your event.'],
            ]);
        }

        $duplicate = ReservationInvitation::query()
            ->where('reservation_id', $reservation->id)
            ->whereIn('status', ['pending', 'accepted'])
            ->where(function ($query) use ($phone, $email) {
                $query->where('invitee_phone', $phone);

                if ($email) {
                    $query->orWhere('invitee_email', $email);
                }
            })
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'phone' => ['This customer already has an active invitation for the event.'],
            ]);
        }

        $shouldSendSms = app(TextBeeSmsGateway::class)->configured()
            && (! $invitee || $invitee->sms_notifications_enabled);

        $invitation = ReservationInvitation::create([
            'reservation_id' => $reservation->id,
            'inviter_id' => $inviter->id,
            'invitee_user_id' => $invitee?->id,
            'invitee_name' => trim($validated['name']),
            'invitee_phone' => $phone,
            'invitee_email' => $email,
            'token' => (string) Str::uuid(),
            'status' => 'pending',
        ]);
        $smsQueued = false;

        if ($shouldSendSms) {
            try {
                DB::transaction(function () use ($invitation, $reservation, $inviter, $phone) {
                    $sms = SmsMessage::create([
                        'user_id' => $inviter->id,
                        'reservation_id' => $reservation->id,
                        'recipient' => $phone,
                        'event' => 'invitation',
                        'message' => $this->invitationSms($invitation, $reservation, $inviter),
                    ]);

                    Bus::dispatch(new SendInvitationSms($sms->id, $invitation->id));
                });
                $smsQueued = true;
            } catch (Throwable $exception) {
                Log::warning('Invitation saved, but its SMS could not be queued.', [
                    'invitation_id' => $invitation->id,
                    'error_type' => get_class($exception),
                ]);
            }
        }

        $invitation->load(['reservation', 'inviter']);

        return response()->json([
            'message' => $smsQueued
                ? 'Invitation saved in the app and queued for SMS delivery.'
                : 'Invitation saved in the app. SMS is unavailable or the customer disabled SMS notifications.',
            'sms_queued' => $smsQueued,
            'invitation' => ReservationInvitationPresenter::forMobile($invitation),
        ], Response::HTTP_CREATED);
    }

    public function respondToInvitation(Request $request, ReservationInvitation $invitation): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['accepted', 'declined'])],
        ]);
        $user = $request->user();

        if (! $this->belongsToInvitee($invitation, $user)) {
            abort(403);
        }

        if ($invitation->status !== 'pending') {
            return response()->json([
                'message' => 'This invitation has already been answered.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $invitation->update([
            'invitee_user_id' => $user->id,
            'status' => $validated['status'],
            'responded_at' => now(),
        ]);
        $invitation->load(['reservation', 'inviter']);

        return response()->json([
            'message' => $validated['status'] === 'accepted'
                ? 'Invitation accepted. The event is now in your invitation list.'
                : 'Invitation declined.',
            'invitation' => ReservationInvitationPresenter::forMobile($invitation),
        ]);
    }

    public function revokeInvitation(Request $request, Reservation $reservation, ReservationInvitation $invitation): JsonResponse
    {
        $this->authorizeReservationAccess($request, $reservation);
        abort_unless((int) $invitation->reservation_id === (int) $reservation->id, 404);

        if ($invitation->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending invitations can be cancelled.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $invitation->update([
            'status' => 'revoked',
            'responded_at' => now(),
        ]);

        return response()->json(['message' => 'Invitation cancelled.']);
    }

    private function findCustomer(?string $email, string $phone): ?User
    {
        if ($email) {
            $customer = User::query()->where('role', 'customer')->whereRaw('LOWER(email) = ?', [$email])->first();

            if ($customer) {
                return $customer;
            }
        }

        return User::query()
            ->where('role', 'customer')
            ->whereNotNull('phone')
            ->get()
            ->first(fn (User $user) => SmsPhoneNumber::normalize($user->phone) === $phone);
    }

    private function belongsToInvitee(ReservationInvitation $invitation, User $user): bool
    {
        if ((int) $invitation->invitee_user_id === (int) $user->id) {
            return true;
        }

        if ($invitation->invitee_user_id) {
            return false;
        }

        $matchesEmail = $invitation->invitee_email
            && Str::lower($invitation->invitee_email) === Str::lower($user->email);
        $matchesPhone = SmsPhoneNumber::normalize($user->phone) === $invitation->invitee_phone;

        return $user->role === 'customer' && ($matchesEmail || $matchesPhone);
    }

    private function invitationSms(ReservationInvitation $invitation, Reservation $reservation, User $inviter): string
    {
        $sender = Str::limit($this->plainText(Str::before($inviter->name, ' ')), 20, '');
        $event = Str::limit($this->plainText($reservation->package_name ?: $reservation->reservation_type), 28, '');
        $branch = Str::limit($this->plainText($reservation->branch), 24, '');
        $date = $reservation->event_date?->format('d M Y');
        $time = \Carbon\Carbon::parse($reservation->event_time)->format('g:i A');
        $reference = Str::limit($this->plainText($reservation->booking_reference), 24, '');

        return Str::limit("McD invite from {$sender}: {$event}, {$date} {$time} at {$branch}. Open McD Reservations to reply. Ref {$reference}", 160, '');
    }

    private function plainText(string $value): string
    {
        return trim(preg_replace('/[^\x20-\x7E]/', '', Str::ascii($value)));
    }
}

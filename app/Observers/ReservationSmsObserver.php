<?php

namespace App\Observers;

use App\Jobs\SendBookingSms;
use App\Models\Reservation;
use App\Models\SmsMessage;
use App\Services\TextBeeSmsGateway;
use App\Support\SmsPhoneNumber;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ReservationSmsObserver
{
    public function created(Reservation $reservation): void
    {
        $this->enqueue($reservation);
    }

    public function updated(Reservation $reservation): void
    {
        if ($reservation->wasChanged(['status', 'event_date', 'event_time', 'branch'])) {
            $this->enqueue($reservation);
        }
    }

    private function enqueue(Reservation $reservation): void
    {
        if (! app(TextBeeSmsGateway::class)->configured()) {
            return;
        }

        try {
            $customer = $reservation->user()->first();
            $recipient = SmsPhoneNumber::normalize($customer?->phone);
            $labels = [
                'pending_review' => 'received (awaiting review)',
                'confirmed' => 'confirmed',
                'rescheduled' => 'rescheduled',
                'checked_in' => 'checked in',
                'completed' => 'completed',
                'cancelled' => 'cancelled',
            ];

            if (! $customer || $customer->role !== 'customer' || ! $customer->sms_notifications_enabled
                || ! $recipient || ! isset($labels[$reservation->status])) {
                return;
            }

            $reference = Str::limit($this->plainText($reservation->booking_reference ?: '#'.$reservation->id), 30, '');
            $branch = Str::limit($this->plainText($reservation->branch), 28, '...');
            $date = $reservation->event_date->format('d M Y');
            $time = \Carbon\Carbon::parse($reservation->event_time)->format('g:i A');
            $label = $labels[$reservation->status];
            $message = Str::limit("McDonald's: Booking {$reference} {$label}. {$date} {$time} at {$branch}. Details in My Account.", 160, '');

            // The outbox and database queue share the booking database, so rollbacks discard both.
            DB::transaction(function () use ($reservation, $customer, $recipient, $message) {
                $sms = SmsMessage::create([
                    'user_id' => $customer->id,
                    'reservation_id' => $reservation->id,
                    'recipient' => $recipient,
                    'event' => $reservation->status,
                    'message' => $message,
                ]);

                Bus::dispatch(new SendBookingSms($sms->id));
            });
        } catch (Throwable $exception) {
            Log::warning('Booking saved, but its SMS notification could not be queued.', [
                'reservation_id' => $reservation->id,
                'error_type' => get_class($exception),
            ]);
        }
    }

    private function plainText(string $value): string
    {
        return trim(preg_replace('/[^\x20-\x7E]/', '', Str::ascii($value)));
    }
}

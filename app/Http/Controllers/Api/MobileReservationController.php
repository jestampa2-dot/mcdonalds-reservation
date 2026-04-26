<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ReservationController;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class MobileReservationController extends ReservationController
{
    public function mobileHome(): JsonResponse
    {
        $catalog = $this->catalog();
        $reservationCount = $this->hasTableSafely('reservations')
            ? $this->runDatabaseCheck(fn () => Reservation::count(), 0)
            : 0;

        return response()->json([
            'eventTypes' => array_values($catalog['eventTypes']),
            'branches' => array_values($catalog['branches']),
            'featuredPackages' => collect($catalog['packages'])->flatten(1)->take(3)->values(),
            'stats' => [
                ['label' => 'Live bookings', 'value' => $reservationCount ?: 24],
                ['label' => 'Party-ready branches', 'value' => collect($catalog['branches'])->filter(fn ($branch) => $branch['supports']['birthday'])->count()],
                ['label' => 'Average response time', 'value' => '15 min'],
            ],
        ]);
    }

    public function mobileBookingOptions(Request $request): JsonResponse
    {
        $catalog = $this->catalog();
        $days = (int) $request->integer('days', 60);

        return response()->json([
            'catalog' => $catalog,
            'roomChoices' => $catalog['roomChoices'],
            'availability' => $this->availabilityPayload($catalog, max(28, min($days, 120))),
            'defaults' => [
                'event_date' => now()->addDays(3)->toDateString(),
                'event_time' => '10:00',
                'duration_hours' => (int) ($catalog['bookingWindow']['default_duration_hours'] ?? 4),
                'room_choice' => $this->defaultRoomChoice('birthday'),
            ],
        ]);
    }

    public function mobileAvailability(Request $request): JsonResponse
    {
        $catalog = $this->catalog();
        $days = (int) $request->integer('days', 60);

        return response()->json(
            $this->availabilityPayload($catalog, max(28, min($days, 120)))
        );
    }

    public function mobileDashboard(Request $request): JsonResponse
    {
        $catalog = $this->catalog();
        $bookings = Reservation::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('event_date')
            ->orderBy('event_time')
            ->get();

        return response()->json([
            'bookings' => $bookings->map(fn (Reservation $reservation) => $this->serializeReservation($reservation))->values(),
            'slotOptions' => $catalog['slotOptions'],
            'stats' => [
                ['label' => 'Upcoming', 'value' => $bookings->whereIn('status', ['pending_review', 'confirmed', 'rescheduled', 'checked_in'])->count()],
                ['label' => 'Confirmed spend', 'value' => $this->currencySymbol().number_format($bookings->whereIn('status', ['confirmed', 'checked_in'])->sum('total_amount'), 2)],
                ['label' => 'Pending approvals', 'value' => $bookings->where('status', 'pending_review')->count()],
            ],
        ]);
    }

    public function mobileStore(Request $request): JsonResponse
    {
        $catalog = $this->catalog();
        $customer = $request->user();

        $validated = $request->validate([
            'event_type' => ['required', Rule::in(array_keys($catalog['eventTypes']))],
            'branch_code' => ['required', Rule::in(array_keys($catalog['branches']))],
            'event_date' => ['required', 'date', 'after_or_equal:today'],
            'event_time' => ['required', Rule::in($catalog['slotOptions'])],
            'event_end_time' => ['nullable', 'date_format:H:i'],
            'duration_hours' => ['required', 'integer', 'min:1'],
            'room_choice' => ['required', Rule::in(collect($catalog['roomChoices'])->pluck('code')->all())],
            'guests' => ['required', 'integer', 'min:2', 'max:60'],
            'package_code' => ['required', 'string'],
            'menu_bundles' => ['array'],
            'menu_bundles.*' => ['string'],
            'manual_menu_items' => ['array'],
            'manual_menu_items.*.option_code' => ['required', 'string'],
            'manual_menu_items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'add_ons' => ['array'],
            'add_ons.*' => ['string'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'payment_proof' => ['required', 'image', 'max:4096'],
        ]);

        $branch = $catalog['branches'][$validated['branch_code']];

        if (! empty($validated['event_end_time'])) {
            $selectedDuration = $this->durationBetweenTimes($validated['event_time'], $validated['event_end_time']);

            if (! is_int($selectedDuration) || $selectedDuration < 1) {
                return response()->json([
                    'message' => 'Choose an end time that comes after the selected start time.',
                    'errors' => [
                        'event_end_time' => ['Choose an end time that comes after the selected start time.'],
                    ],
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $validated['duration_hours'] = $selectedDuration;
        }

        if (! ($branch['supports'][$validated['event_type']] ?? false)) {
            return response()->json([
                'message' => 'The selected branch does not support that event type.',
                'errors' => [
                    'branch_code' => ['The selected branch does not support that event type.'],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $package = collect($catalog['packages'][$validated['event_type']])->firstWhere('code', $validated['package_code']);

        if (! $package) {
            return response()->json([
                'message' => 'Please choose a valid package.',
                'errors' => [
                    'package_code' => ['Please choose a valid package.'],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (! $this->isDurationWindowValid($validated['event_time'], (int) $validated['duration_hours'])) {
            return response()->json([
                'message' => 'Choose a start and end time that stays between '.$this->bookingWindowLabel().'.',
                'errors' => [
                    'event_time' => ['Reservations must fit within the event booking window of '.$this->bookingWindowLabel().'.'],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (! $this->isSlotAvailable($validated['branch_code'], $validated['event_date'], $validated['event_time'], (int) $validated['duration_hours'])) {
            return response()->json([
                'message' => 'The chosen date and time are unavailable or already reserved.',
                'errors' => [
                    'event_time' => ['The chosen date and time are unavailable or already reserved.'],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $menuBundles = collect($catalog['menuBundles'])
            ->whereIn('code', $validated['menu_bundles'] ?? [])
            ->values()
            ->all();

        $addOns = collect($catalog['addOns'])
            ->whereIn('code', $validated['add_ons'] ?? [])
            ->values()
            ->all();

        $manualMenuItems = $this->resolveManualMenuSelections($catalog, $validated['manual_menu_items'] ?? []);
        $proofPath = $request->file('payment_proof')->store('payment-proofs', 'public');
        $bookingReference = strtoupper('MCR-'.Str::random(8));
        $receipt = $this->buildReceipt(
            $validated['event_type'],
            $validated['event_date'],
            $package,
            $menuBundles,
            $manualMenuItems,
            $addOns,
            (int) $validated['duration_hours']
        );

        $reservation = Reservation::create([
            'user_id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'phone' => $customer->phone ?: 'Not provided',
            'booking_reference' => $bookingReference,
            'reservation_type' => $validated['event_type'],
            'package_name' => $package['name'],
            'package_code' => $package['code'],
            'room_choice' => collect($catalog['roomChoices'])->firstWhere('code', $validated['room_choice'])['label'] ?? $validated['room_choice'],
            'food_package' => $this->foodPackageSummary($menuBundles, $manualMenuItems),
            'beverage_package' => $this->beveragePackageSummary($menuBundles, $manualMenuItems),
            'event_materials' => collect($addOns)->pluck('name')->implode(', '),
            'branch' => $branch['name'],
            'branch_code' => $branch['code'],
            'event_date' => $validated['event_date'],
            'event_time' => $validated['event_time'],
            'duration_hours' => (int) $validated['duration_hours'],
            'menu_bundles' => Arr::pluck($menuBundles, 'code'),
            'add_ons' => Arr::pluck($addOns, 'code'),
            'manual_menu_items' => $manualMenuItems,
            'service_adjustments' => [
                'extra_menu_bundles' => [],
                'extra_add_ons' => [],
                'extra_manual_menu_items' => [],
            ],
            'payment_proof_path' => $proofPath,
            'guests' => $validated['guests'],
            'total_amount' => $receipt['total_raw'],
            'check_in_code' => substr(hash('sha256', $bookingReference), 0, 10),
            'notes' => $validated['notes'] ?? null,
            'status' => 'pending_review',
            'service_status' => 'available',
        ]);

        return response()->json([
            'message' => 'Reservation submitted. Your payment proof is queued for review.',
            'reservation' => $this->serializeReservation($reservation),
        ], Response::HTTP_CREATED);
    }

    public function mobileCancel(Request $request, Reservation $reservation): JsonResponse
    {
        $this->authorizeReservationAccess($request, $reservation);

        if ($reservation->checked_in_at) {
            return response()->json([
                'message' => 'Checked-in bookings cannot be cancelled.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $reservation->update([
            'status' => 'cancelled',
            'service_status' => 'available',
        ]);

        return response()->json([
            'message' => 'Booking cancelled.',
            'reservation' => $this->serializeReservation($reservation->fresh()),
        ]);
    }

    public function mobileReschedule(Request $request, Reservation $reservation): JsonResponse
    {
        $this->authorizeReservationAccess($request, $reservation);

        $validated = $request->validate([
            'event_date' => ['required', 'date', 'after_or_equal:today'],
            'event_time' => ['required', Rule::in($this->catalog()['slotOptions'])],
        ]);

        $durationHours = (int) ($reservation->duration_hours ?? 4);

        if (! $this->isDurationWindowValid($validated['event_time'], $durationHours)) {
            return response()->json([
                'message' => 'Choose a start and end time that stays between '.$this->bookingWindowLabel().'.',
                'errors' => [
                    'event_time' => ['Reservations must fit within the event booking window of '.$this->bookingWindowLabel().'.'],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (! $this->isSlotAvailable($reservation->branch_code, $validated['event_date'], $validated['event_time'], $durationHours, $reservation->id)) {
            return response()->json([
                'message' => 'The chosen date and time are unavailable or already reserved. Please choose another schedule.',
                'errors' => [
                    'event_time' => ['The chosen date and time are unavailable or already reserved.'],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $reservation->update([
            'event_date' => $validated['event_date'],
            'event_time' => $validated['event_time'],
            'status' => 'rescheduled',
            'checked_in_at' => null,
            'checked_in_by' => null,
            'service_status' => 'available',
        ]);

        return response()->json([
            'message' => 'Booking rescheduled.',
            'reservation' => $this->serializeReservation($reservation->fresh()),
        ]);
    }
}

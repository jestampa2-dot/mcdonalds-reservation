<?php

namespace App\Support\Timeline;

use App\Models\Reservation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Throwable;

class ReservationTimelineBuilder
{
    /**
     * Build the upcoming notifications payload from reservations.
     */
    public function notifications($bookings, int $limit = 8): array
    {
        $today = now()->startOfDay();

        return $this->normalize($bookings)
            ->filter(function (Reservation $reservation) use ($today) {
                if (! $reservation->event_date) {
                    return false;
                }

                return $reservation->event_date->copy()->startOfDay()->greaterThanOrEqualTo($today)
                    && in_array($reservation->status, ['pending_review', 'confirmed', 'rescheduled', 'checked_in'], true);
            })
            ->sortBy(fn (Reservation $reservation) => $this->sortKey($reservation, '9999-12-31', '23:59:59'))
            ->take($limit)
            ->values()
            ->map(fn (Reservation $reservation) => [
                'id' => $reservation->id,
                'booking_reference' => $reservation->booking_reference,
                'package_name' => $reservation->package_name,
                'branch' => $reservation->branch,
                'event_type' => ucfirst((string) $reservation->reservation_type),
                'event_date' => $reservation->event_date?->format('M d, Y'),
                'event_time' => $this->formatTimeRange($reservation->event_time, (int) ($reservation->duration_hours ?? 4)),
                'status' => $reservation->status,
                'assigned_staff_name' => $reservation->assignedStaff?->name,
                'message' => $this->notificationMessage($reservation),
            ])
            ->all();
    }

    /**
     * Build the event history payload from reservations.
     */
    public function history($bookings, int $limit = 10): array
    {
        $today = now()->startOfDay();

        return $this->normalize($bookings)
            ->filter(function (Reservation $reservation) use ($today) {
                if (! $reservation->event_date) {
                    return false;
                }

                return $reservation->event_date->copy()->startOfDay()->lessThan($today)
                    || $reservation->status === 'completed';
            })
            ->sortByDesc(fn (Reservation $reservation) => $this->sortKey($reservation, '0000-00-00', '00:00:00'))
            ->take($limit)
            ->values()
            ->map(fn (Reservation $reservation) => [
                'id' => $reservation->id,
                'booking_reference' => $reservation->booking_reference,
                'package_name' => $reservation->package_name,
                'branch' => $reservation->branch,
                'event_type' => ucfirst((string) $reservation->reservation_type),
                'event_date' => $reservation->event_date?->format('M d, Y'),
                'event_time' => $this->formatTimeRange($reservation->event_time, (int) ($reservation->duration_hours ?? 4)),
                'status' => $reservation->status,
                'service_status' => $reservation->service_status,
                'assigned_staff_name' => $reservation->assignedStaff?->name,
                'checked_in_by' => $reservation->checked_in_by,
            ])
            ->all();
    }

    /**
     * Build the cancelled events payload from reservations.
     */
    public function cancelled($bookings, int $limit = 10): array
    {
        return $this->normalize($bookings)
            ->where('status', 'cancelled')
            ->sortByDesc(fn (Reservation $reservation) => $this->sortKey($reservation, '0000-00-00', '00:00:00'))
            ->take($limit)
            ->values()
            ->map(fn (Reservation $reservation) => [
                'id' => $reservation->id,
                'booking_reference' => $reservation->booking_reference,
                'package_name' => $reservation->package_name,
                'branch' => $reservation->branch,
                'event_type' => ucfirst((string) $reservation->reservation_type),
                'event_date' => $reservation->event_date?->format('M d, Y'),
                'event_time' => $this->formatTimeRange($reservation->event_time, (int) ($reservation->duration_hours ?? 4)),
                'status' => $reservation->status,
                'service_status' => $reservation->service_status,
                'customer_name' => $reservation->name,
                'cancelled_note' => $reservation->notes,
            ])
            ->all();
    }

    protected function normalize($bookings): Collection
    {
        return $bookings instanceof Collection ? $bookings : collect($bookings);
    }

    protected function sortKey(Reservation $reservation, string $fallbackDate, string $fallbackTime): string
    {
        return sprintf(
            '%s %s',
            $reservation->event_date?->toDateString() ?? $fallbackDate,
            $reservation->event_time ?? $fallbackTime
        );
    }

    protected function notificationMessage(Reservation $reservation): string
    {
        if ($reservation->status === 'pending_review') {
            return 'Needs approval before the guest arrives.';
        }

        if ($reservation->status === 'rescheduled') {
            return 'Recently moved to a new schedule and should be re-checked.';
        }

        if ($reservation->status === 'checked_in') {
            return 'Guest is already on site and service is active.';
        }

        return 'Confirmed event coming up soon.';
    }

    protected function formatTimeRange(?string $time, int $durationHours): string
    {
        $startTime = $this->normalizedTime($time);

        if (! $startTime) {
            return 'Time unavailable';
        }

        try {
            $start = Carbon::createFromFormat('H:i', $startTime, config('app.timezone'));

            return $start->format('g:i A').' to '.$start->copy()->addHours($durationHours)->format('g:i A');
        } catch (Throwable) {
            return 'Time unavailable';
        }
    }

    protected function normalizedTime(?string $time): ?string
    {
        $value = substr((string) $time, 0, 5);

        return preg_match('/^\d{2}:\d{2}$/', $value) ? $value : null;
    }
}

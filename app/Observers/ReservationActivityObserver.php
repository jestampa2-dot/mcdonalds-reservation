<?php

namespace App\Observers;

use App\Models\Reservation;
use App\Services\ActivityLogger;

class ReservationActivityObserver
{
    public function __construct(protected ActivityLogger $logger)
    {
    }

    public function created(Reservation $reservation): void
    {
        $this->logger->record(
            'booking.created',
            $reservation,
            "Reservation {$reservation->booking_reference} was created for {$reservation->branch}.",
            $reservation->branch_code,
            ['status' => $reservation->status]
        );
    }

    public function updated(Reservation $reservation): void
    {
        if ($reservation->wasChanged('status')) {
            $this->logger->record(
                'booking.status_changed',
                $reservation,
                "Reservation {$reservation->booking_reference} changed from {$reservation->getOriginal('status')} to {$reservation->status}.",
                $reservation->branch_code,
                ['from' => $reservation->getOriginal('status'), 'to' => $reservation->status]
            );
        }

        if ($reservation->wasChanged('assigned_staff_id')) {
            $this->logger->record(
                'booking.crew_assigned',
                $reservation,
                "Crew assignment changed for reservation {$reservation->booking_reference}.",
                $reservation->branch_code,
                ['assigned_staff_id' => $reservation->assigned_staff_id]
            );
        }

        if ($reservation->wasChanged(['event_date', 'event_time', 'branch_code'])) {
            $this->logger->record(
                'booking.schedule_changed',
                $reservation,
                "The schedule or branch changed for reservation {$reservation->booking_reference}.",
                $reservation->branch_code,
                [
                    'event_date' => $reservation->event_date?->toDateString(),
                    'event_time' => $reservation->event_time,
                ]
            );
        }

        if ($reservation->wasChanged('service_status')) {
            $this->logger->record(
                'booking.service_status_changed',
                $reservation,
                "Service status for {$reservation->booking_reference} changed to {$reservation->service_status}.",
                $reservation->branch_code,
                ['service_status' => $reservation->service_status]
            );
        }
    }

    public function deleted(Reservation $reservation): void
    {
        $this->logger->record(
            'booking.deleted',
            $reservation,
            "Reservation {$reservation->booking_reference} was deleted.",
            $reservation->branch_code
        );
    }
}

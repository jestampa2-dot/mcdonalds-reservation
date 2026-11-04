<?php

namespace App\Support;

use App\Models\ReservationInvitation;
use Carbon\Carbon;

class ReservationInvitationPresenter
{
    public static function forMobile(ReservationInvitation $invitation): array
    {
        $reservation = $invitation->reservation;
        $eventTime = $reservation ? substr((string) $reservation->event_time, 0, 5) : null;

        return [
            'id' => $invitation->id,
            'reservation_id' => $invitation->reservation_id,
            'booking_reference' => $reservation?->booking_reference,
            'inviter_name' => $invitation->inviter?->name,
            'invitee_name' => $invitation->invitee_name,
            'invitee_phone' => $invitation->invitee_phone,
            'invitee_email' => $invitation->invitee_email,
            'event_type' => $reservation?->reservation_type,
            'package_name' => $reservation?->package_name,
            'branch' => $reservation?->branch,
            'event_date' => $reservation?->event_date?->toDateString(),
            'event_time' => $eventTime,
            'event_time_label' => $eventTime ? Carbon::createFromFormat('H:i', $eventTime)->format('g:i A') : null,
            'status' => $invitation->status,
            'responded_at' => $invitation->responded_at?->toDateTimeString(),
            'created_at' => $invitation->created_at?->toDateTimeString(),
        ];
    }
}

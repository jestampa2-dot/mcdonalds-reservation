<?php

namespace App\Mail;

use App\Models\ReservationInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class ReservationInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ReservationInvitation $invitation)
    {
    }

    public function build(): self
    {
        $this->invitation->loadMissing(['reservation', 'inviter']);
        $event = $this->invitation->reservation?->package_name
            ?: $this->invitation->reservation?->reservation_type
            ?: "McDonald's event";

        return $this
            ->subject(Str::limit("You're invited: {$event}", 120, ''))
            ->markdown('emails.reservation-invitation', [
                'dashboardUrl' => route('dashboard'),
            ]);
    }
}

<?php

namespace App\Services;

use App\Mail\ReservationInvitationMail;
use App\Models\ReservationInvitation;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class InvitationEmailGateway
{
    public function configured(): bool
    {
        $mailer = (string) config('mail.default');
        $mailers = (array) config('mail.mailers', []);

        if ($mailer === '' || ! array_key_exists($mailer, $mailers)) {
            return false;
        }

        if ($mailer !== 'smtp') {
            return true;
        }

        return trim((string) config('mail.mailers.smtp.host')) !== ''
            && trim((string) config('mail.mailers.smtp.username')) !== ''
            && trim((string) config('mail.mailers.smtp.password')) !== ''
            && filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL) !== false;
    }

    public function send(ReservationInvitation $invitation): void
    {
        if (! $this->configured()) {
            throw new RuntimeException('Automatic invitation email is not configured.');
        }

        if (! filter_var($invitation->invitee_email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('The invitation does not have a valid recipient email address.');
        }

        Mail::to($invitation->invitee_email, $invitation->invitee_name)
            ->send(new ReservationInvitationMail($invitation));
    }
}

<?php

namespace App\Jobs;

use App\Models\ReservationInvitation;
use App\Services\InvitationEmailGateway;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendInvitationEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 30;
    public $backoff = [30, 120];

    public function __construct(public int $invitationId)
    {
        // Reuse the existing durable notification queue table. The public
        // queue name is email, so the production worker can prioritize it.
        $this->onConnection('sms')->onQueue('email');
    }

    public function handle(InvitationEmailGateway $gateway): void
    {
        $invitation = ReservationInvitation::with(['reservation', 'inviter'])->find($this->invitationId);

        if (! $invitation || $invitation->status !== 'pending' || ! $invitation->reservation) {
            return;
        }

        $gateway->send($invitation);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('An event invitation email could not be delivered.', [
            'invitation_id' => $this->invitationId,
            'error_type' => get_class($exception),
        ]);
    }
}

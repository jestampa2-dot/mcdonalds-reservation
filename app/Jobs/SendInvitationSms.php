<?php

namespace App\Jobs;

use App\Models\ReservationInvitation;
use App\Models\SmsMessage;
use App\Services\TextBeeSmsGateway;
use App\Support\SmsPhoneNumber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;

class SendInvitationSms implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;
    public $timeout = 20;

    public function __construct(public int $smsMessageId, public int $invitationId)
    {
        $this->onConnection('sms')->onQueue('sms');
    }

    public function handle(TextBeeSmsGateway $gateway): void
    {
        $sms = SmsMessage::find($this->smsMessageId);
        $invitation = ReservationInvitation::with(['reservation', 'invitee'])->find($this->invitationId);

        if (! $sms || $sms->status !== 'queued') {
            return;
        }

        if (! $gateway->configured()) {
            $sms->update(['status' => 'skipped', 'error' => 'SMS is disabled or not configured.']);

            return;
        }

        if (! $invitation || $invitation->status !== 'pending' || ! $invitation->reservation
            || (int) $invitation->reservation_id !== (int) $sms->reservation_id
            || (int) $invitation->inviter_id !== (int) $sms->user_id
            || SmsPhoneNumber::normalize($invitation->invitee_phone) !== $sms->recipient
            || ($invitation->invitee && ! $invitation->invitee->sms_notifications_enabled)) {
            $sms->update(['status' => 'skipped', 'error' => 'The invitation, recipient, or SMS preference changed.']);

            return;
        }

        if (! SmsMessage::whereKey($sms->id)->where('status', 'queued')->update(['status' => 'sending'])) {
            return;
        }

        try {
            $batchId = $gateway->send($sms->recipient, $sms->message);
            $sms->update([
                'status' => 'accepted',
                'provider_batch_id' => $batchId,
                'accepted_at' => now(),
                'error' => null,
            ]);
        } catch (ConnectionException $exception) {
            $sms->update([
                'status' => 'unknown',
                'error' => 'The provider request timed out or lost its connection. Check TextBee before sending again.',
            ]);
        } catch (Throwable $exception) {
            $sms->update([
                'status' => 'failed',
                'error' => $exception instanceof \RuntimeException
                    ? Str::limit($exception->getMessage(), 200)
                    : 'SMS could not be submitted. Check the provider configuration.',
            ]);
        }
    }

    public function failed(Throwable $exception): void
    {
        SmsMessage::whereKey($this->smsMessageId)->whereIn('status', ['queued', 'sending'])->update([
            'status' => 'unknown',
            'error' => 'The SMS worker stopped unexpectedly. Check TextBee before sending again.',
        ]);
    }
}

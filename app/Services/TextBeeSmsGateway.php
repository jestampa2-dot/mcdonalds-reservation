<?php

namespace App\Services;

use App\Support\SmsPhoneNumber;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class TextBeeSmsGateway
{
    public function configured(): bool
    {
        return (bool) config('sms.enabled') && trim((string) config('sms.textbee.api_key')) !== '';
    }

    public function send(string $recipient, string $message): string
    {
        if (! $this->configured()) {
            throw new RuntimeException('SMS is disabled or the TextBee API key is missing.');
        }

        $recipient = SmsPhoneNumber::normalize($recipient);

        if (! $recipient || $message === '' || strlen($message) > 160 || preg_match('/[^\x20-\x7E]/', $message)) {
            throw new InvalidArgumentException('Use a Philippine mobile number and an SMS of 1-160 plain text characters.');
        }

        $payload = ['recipients' => [$recipient], 'message' => $message];
        $deviceId = trim((string) config('sms.textbee.device_id'));

        if ($deviceId !== '') {
            $payload['deviceId'] = $deviceId;
        }

        $response = Http::acceptJson()
            ->withHeaders(['x-api-key' => config('sms.textbee.api_key')])
            ->connectTimeout(3)
            ->timeout(8)
            ->post(rtrim(config('sms.textbee.base_url'), '/').'/gateway/send-sms', $payload);

        if (! $response->successful()) {
            throw new RuntimeException('TextBee rejected the SMS request (HTTP '.$response->status().').');
        }

        $batchId = $response->json('data.smsBatchId');

        if ($response->json('data.success') !== true || ! is_string($batchId) || $batchId === '') {
            throw new RuntimeException('TextBee did not confirm that the SMS was accepted.');
        }

        return $batchId;
    }
}

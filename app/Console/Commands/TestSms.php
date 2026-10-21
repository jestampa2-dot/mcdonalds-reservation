<?php

namespace App\Console\Commands;

use App\Jobs\SendBookingSms;
use App\Models\SmsMessage;
use App\Services\TextBeeSmsGateway;
use App\Support\SmsPhoneNumber;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

class TestSms extends Command
{
    protected $signature = 'sms:test {phone : Your own Philippine mobile number}';
    protected $description = 'Queue one test SMS to check the TextBee gateway';

    public function handle(TextBeeSmsGateway $gateway): int
    {
        $recipient = SmsPhoneNumber::normalize($this->argument('phone'));

        if (! $recipient) {
            $this->error('Enter a Philippine mobile number, for example 09171234567 or +639171234567.');

            return self::FAILURE;
        }

        if (! $gateway->configured()) {
            $this->error('Set SMS_ENABLED=true and TEXTBEE_API_KEY in the Laravel .env, then run php artisan config:clear.');

            return self::FAILURE;
        }

        $sms = DB::transaction(function () use ($recipient) {
            $sms = SmsMessage::create([
                'recipient' => $recipient,
                'event' => 'test',
                'message' => "McDonald's reservation system: Your SMS connection is working. This is a test message.",
            ]);

            Bus::dispatch(new SendBookingSms($sms->id));

            return $sms;
        });

        $this->info('Test SMS #'.$sms->id.' queued. Keep the TextBee phone online and run:');
        $this->line('php artisan queue:work sms --queue=sms --tries=1 --timeout=20 --sleep=1');

        return self::SUCCESS;
    }
}

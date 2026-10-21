<?php

namespace Tests\Feature;

use App\Jobs\SendBookingSms;
use App\Models\Reservation;
use App\Models\SmsMessage;
use App\Models\User;
use App\Services\TextBeeSmsGateway;
use App\Support\SmsPhoneNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingSmsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'sms.enabled' => true,
            'sms.textbee.api_key' => 'fake-test-key',
            'sms.textbee.device_id' => 'fake-device-id',
            'sms.textbee.base_url' => 'https://api.textbee.dev/api/v1',
        ]);
        Http::preventStrayRequests();
    }

    public function test_booking_queues_sms_without_waiting_for_the_provider(): void
    {
        $reservation = $this->booking();

        $this->assertSame('sync', config('queue.default'));
        $this->assertDatabaseCount('sms_jobs', 1);
        $this->assertDatabaseHas('sms_messages', [
            'reservation_id' => $reservation->id,
            'recipient' => '+639171234567',
            'event' => 'pending_review',
            'status' => 'queued',
        ]);
        $this->assertStringContainsString($reservation->booking_reference, SmsMessage::first()->message);
        $this->assertLessThanOrEqual(160, strlen(SmsMessage::first()->message));
        Http::assertNothingSent();
    }

    public function test_opt_out_disabled_sms_and_missing_key_do_not_queue_messages(): void
    {
        $customer = $this->customer(['sms_notifications_enabled' => false]);
        $this->booking($customer);

        config(['sms.enabled' => false]);
        $this->booking();

        config(['sms.enabled' => true, 'sms.textbee.api_key' => '']);
        $this->booking();

        $this->assertDatabaseCount('sms_messages', 0);
        $this->assertDatabaseCount('sms_jobs', 0);
        Http::assertNothingSent();
    }

    public function test_invalid_legacy_phone_number_is_skipped_without_breaking_booking(): void
    {
        $this->booking($this->customer(['phone' => 'Not provided']));

        $this->assertDatabaseCount('reservations', 1);
        $this->assertDatabaseCount('sms_messages', 0);
    }

    /** @dataProvider bookingStatuses */
    public function test_status_changes_queue_alerts_once(string $status): void
    {
        $reservation = $this->booking();
        $reservation->update(['status' => $status]);
        $reservation->update(['status' => $status]);
        $reservation->update(['service_status' => 'cleaning', 'notes' => 'Updated staff note']);

        $this->assertDatabaseCount('sms_messages', 2);
        $this->assertDatabaseCount('sms_jobs', 2);
        $this->assertDatabaseHas('sms_messages', ['event' => $status, 'reservation_id' => $reservation->id]);
        Http::assertNothingSent();
    }

    public static function bookingStatuses(): array
    {
        return array_map(fn ($status) => [$status], ['confirmed', 'rescheduled', 'checked_in', 'completed', 'cancelled']);
    }

    public function test_web_and_mobile_admin_updates_both_queue_alerts(): void
    {
        $reservation = $this->booking();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post('/admin/reservations/'.$reservation->id.'/status', [
            'status' => 'confirmed',
        ])->assertRedirect();

        Sanctum::actingAs($admin);
        $this->postJson('/api/mobile/admin/reservations/'.$reservation->id.'/status', [
            'status' => 'completed',
        ])->assertOk();

        $this->assertSame(['pending_review', 'confirmed', 'completed'], SmsMessage::orderBy('id')->pluck('event')->all());
        Http::assertNothingSent();
    }

    public function test_booking_transaction_rollback_discards_sms_and_queue_job(): void
    {
        $customer = $this->customer();
        DB::beginTransaction();
        $reservation = $this->booking($customer);
        $this->assertDatabaseCount('sms_jobs', 1);
        DB::rollBack();

        $this->assertDatabaseMissing('reservations', ['id' => $reservation->id]);
        $this->assertDatabaseCount('sms_messages', 0);
        $this->assertDatabaseCount('sms_jobs', 0);
    }

    public function test_broken_sms_queue_does_not_prevent_a_booking_from_being_saved(): void
    {
        Schema::drop('sms_jobs');
        $reservation = $this->booking();

        $this->assertDatabaseHas('reservations', ['id' => $reservation->id]);
        $this->assertDatabaseCount('sms_messages', 0);
        Http::assertNothingSent();
    }

    public function test_worker_submits_one_sms_and_records_acceptance_without_claiming_delivery(): void
    {
        Http::fake([
            'https://api.textbee.dev/api/v1/gateway/send-sms' => Http::response([
                'data' => ['success' => true, 'smsBatchId' => 'fake-batch-id'],
            ]),
        ]);
        $this->booking();

        Artisan::call('queue:work', ['connection' => 'sms', '--queue' => 'sms', '--once' => true]);
        $sms = SmsMessage::first();

        $this->assertSame('accepted', $sms->status);
        $this->assertSame('fake-batch-id', $sms->provider_batch_id);
        $this->assertNotNull($sms->accepted_at);
        $this->assertDatabaseCount('sms_jobs', 0);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.textbee.dev/api/v1/gateway/send-sms'
            && $request->hasHeader('x-api-key', 'fake-test-key')
            && $request['recipients'] === ['+639171234567']
            && $request['deviceId'] === 'fake-device-id'
            && $request['message'] === $sms->message);

        (new SendBookingSms($sms->id))->handle(app(TextBeeSmsGateway::class));
        Http::assertSentCount(1);
    }

    /** @dataProvider providerFailures */
    public function test_provider_rejections_are_recorded_without_repeating_the_request(int $code, array $body): void
    {
        Http::fake(['*' => Http::response($body, $code)]);
        $reservation = $this->booking();
        $sms = SmsMessage::first();
        $job = new SendBookingSms($sms->id);
        $job->handle(app(TextBeeSmsGateway::class));
        $job->handle(app(TextBeeSmsGateway::class));

        $this->assertSame('failed', $sms->fresh()->status);
        $this->assertSame('pending_review', $reservation->fresh()->status);
        Http::assertSentCount(1);
    }

    public static function providerFailures(): array
    {
        return [
            'bad key' => [401, ['message' => 'Unauthorized']],
            'free limit reached' => [429, ['message' => 'Limit reached']],
            'provider unavailable' => [503, []],
            'false success' => [200, ['data' => ['success' => false]]],
            'no batch id' => [200, ['data' => ['success' => true]]],
        ];
    }

    public function test_timeout_is_marked_unknown_and_not_automatically_retried(): void
    {
        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            $attempts++;
            throw new ConnectionException('Simulated timeout');
        });
        $this->booking();
        $sms = SmsMessage::first();
        $job = new SendBookingSms($sms->id);
        $job->handle(app(TextBeeSmsGateway::class));
        $job->handle(app(TextBeeSmsGateway::class));

        $this->assertSame('unknown', $sms->fresh()->status);
        $this->assertSame(1, $attempts);
    }

    /** @dataProvider changedCustomerDetails */
    public function test_waiting_sms_is_skipped_if_customer_opts_out_or_changes_phone(array $changes): void
    {
        $customer = $this->customer();
        $this->booking($customer);
        $sms = SmsMessage::first();
        $customer->update($changes);
        (new SendBookingSms($sms->id))->handle(app(TextBeeSmsGateway::class));

        $this->assertSame('skipped', $sms->fresh()->status);
        Http::assertNothingSent();
    }

    public static function changedCustomerDetails(): array
    {
        return [
            'opt out' => [['sms_notifications_enabled' => false]],
            'new phone' => [['phone' => '09181234567']],
        ];
    }

    public function test_waiting_sms_is_skipped_after_account_deletion(): void
    {
        $customer = $this->customer();
        $this->booking($customer);
        $sms = SmsMessage::first();
        $customer->delete();
        (new SendBookingSms($sms->id))->handle(app(TextBeeSmsGateway::class));

        $this->assertSame('skipped', $sms->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_web_and_mobile_profile_preferences_are_saved_in_the_same_database(): void
    {
        $customer = $this->customer(['sms_notifications_enabled' => false]);
        $data = ['name' => $customer->name, 'email' => $customer->email, 'sms_notifications_enabled' => true];
        $this->actingAs($customer)->patch('/profile', $data)->assertSessionHasNoErrors()->assertRedirect('/profile');
        $this->assertTrue($customer->fresh()->sms_notifications_enabled);

        Sanctum::actingAs($customer);
        $this->getJson('/api/mobile/profile')->assertOk()->assertJsonPath('profile.sms_notifications_enabled', true);

        $data['sms_notifications_enabled'] = false;
        $this->putJson('/api/mobile/profile', $data)->assertOk()->assertJsonPath('profile.sms_notifications_enabled', false);
        $this->assertFalse($customer->fresh()->sms_notifications_enabled);

        $data['sms_notifications_enabled'] = '1';
        $this->post('/api/mobile/profile', $data, ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('profile.sms_notifications_enabled', true);
    }

    public function test_sms_preference_survives_profile_updates_from_older_clients(): void
    {
        $customer = $this->customer();
        Sanctum::actingAs($customer);
        $this->putJson('/api/mobile/profile', ['name' => $customer->name, 'email' => $customer->email])->assertOk();

        $this->assertTrue($customer->fresh()->sms_notifications_enabled);
    }

    public function test_sms_opt_in_rejects_invalid_phone_numbers(): void
    {
        $customer = $this->customer(['sms_notifications_enabled' => false]);
        Sanctum::actingAs($customer);
        $this->putJson('/api/mobile/profile', [
            'name' => $customer->name,
            'email' => $customer->email,
            'phone' => '12345',
            'sms_notifications_enabled' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('sms_notifications_enabled');

        $this->assertFalse($customer->fresh()->sms_notifications_enabled);
    }

    public function test_test_command_checks_configuration_and_queues_only_one_message(): void
    {
        config(['sms.enabled' => false]);
        $this->artisan('sms:test', ['phone' => '09171234567'])->assertFailed();
        $this->assertDatabaseCount('sms_messages', 0);

        config(['sms.enabled' => true]);
        $this->artisan('sms:test', ['phone' => 'bad number'])->assertFailed();
        $this->artisan('sms:test', ['phone' => '09171234567'])->assertSuccessful();
        $this->assertDatabaseCount('sms_messages', 1);
        $this->assertDatabaseCount('sms_jobs', 1);
        Http::assertNothingSent();
    }

    /** @dataProvider phoneNumbers */
    public function test_phone_normalization(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, SmsPhoneNumber::normalize($input));
    }

    public static function phoneNumbers(): array
    {
        return [
            ['09171234567', '+639171234567'],
            ['9171234567', '+639171234567'],
            ['639171234567', '+639171234567'],
            ['+63 917-123-4567', '+639171234567'],
            ['(0917) 123 4567', '+639171234567'],
            ['12345', null],
            ['+639171234567999', null],
            ['++639171234567', null],
            ['0917abcdefg', null],
            ['', null],
            [null, null],
        ];
    }

    private function customer(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => 'customer',
            'phone' => '09171234567',
            'sms_notifications_enabled' => true,
        ], $attributes));
    }

    private function booking(?User $customer = null): Reservation
    {
        $customer ??= $this->customer();

        return Reservation::create([
            'user_id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'booking_reference' => 'SMS-'.Str::upper(Str::random(8)),
            'reservation_type' => 'birthday',
            'branch' => "McDonald's Surigao City",
            'event_date' => now()->addDays(5)->toDateString(),
            'event_time' => '10:00:00',
            'guests' => 15,
            'status' => 'pending_review',
        ]);
    }
}

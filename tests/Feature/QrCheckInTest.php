<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class QrCheckInTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_signed_qr_scan_marks_confirmed_event_as_ongoing(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 8, 10, 0, 0, config('app.timezone')));
        $reservation = $this->reservation([
            'event_date' => '2026-10-08',
            'event_time' => '10:30:00',
            'status' => 'confirmed',
        ]);
        $url = $this->signedCheckInUrl($reservation);

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reservations/CheckInResult')
                ->where('result', 'active')
                ->where('success', true)
                ->where('booking.reference', $reservation->booking_reference)
                ->where('booking.status', 'checked_in')
                ->where('booking.service_status', 'in_progress')
            );

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => 'checked_in',
            'service_status' => 'in_progress',
            'checked_in_by' => 'Customer QR scan',
        ]);
        $this->assertNotNull($reservation->fresh()->checked_in_at);
    }

    public function test_repeat_scan_is_idempotent(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 8, 10, 0, 0, config('app.timezone')));
        $reservation = $this->reservation([
            'event_date' => '2026-10-08',
            'event_time' => '10:30:00',
            'status' => 'confirmed',
        ]);
        $url = $this->signedCheckInUrl($reservation);

        $this->get($url)->assertOk();
        $firstCheckIn = $reservation->fresh()->checked_in_at;
        Carbon::setTestNow(now()->addMinutes(5));

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('result', 'already_active')
                ->where('success', true)
            );

        $this->assertTrue($firstCheckIn->equalTo($reservation->fresh()->checked_in_at));
    }

    public function test_qr_scan_before_check_in_window_does_not_start_event(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 8, 8, 0, 0, config('app.timezone')));
        $reservation = $this->reservation([
            'event_date' => '2026-10-08',
            'event_time' => '12:00:00',
            'status' => 'confirmed',
        ]);

        $this->get($this->signedCheckInUrl($reservation))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('result', 'too_early')
                ->where('success', false)
            );

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => 'confirmed',
            'service_status' => 'available',
        ]);
    }

    public function test_tampered_qr_link_is_rejected(): void
    {
        $reservation = $this->reservation(['status' => 'confirmed']);
        $url = $this->signedCheckInUrl($reservation);
        $tamperedUrl = str_replace('code='.$reservation->check_in_code, 'code=invalid', $url);

        $this->get($tamperedUrl)->assertForbidden();
        $this->assertSame('confirmed', $reservation->fresh()->status);
    }

    public function test_customer_pass_contains_a_real_qr_image_and_supports_download(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $reservation = $this->reservation([
            'user_id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'status' => 'confirmed',
        ]);

        $preview = $this->actingAs($customer)
            ->get(route('reservations.pass', $reservation))
            ->assertOk()
            ->assertHeader('content-type', 'image/png')
            ->assertHeader('content-disposition', 'inline; filename="'.$reservation->booking_reference.'-qr-pass.png"');

        $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $preview->getContent());

        $this->actingAs($customer)
            ->get(route('reservations.pass', [$reservation, 'download' => 1]))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename="'.$reservation->booking_reference.'-qr-pass.png"');
    }

    public function test_staff_can_check_in_a_confirmed_booking_with_its_qr_code(): void
    {
        $staff = User::factory()->create([
            'name' => 'Check-in Staff',
            'role' => 'staff',
        ]);
        $reservation = $this->reservation(['status' => 'confirmed']);

        $this->actingAs($staff)
            ->post(route('staff.check-in'), ['code' => $reservation->check_in_code])
            ->assertRedirect()
            ->assertSessionHas('success', 'Guest checked in successfully.');

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => 'checked_in',
            'service_status' => 'in_progress',
            'checked_in_by' => $staff->name,
        ]);
    }

    private function reservation(array $attributes = []): Reservation
    {
        return Reservation::create(array_merge([
            'name' => 'QR Test Customer',
            'email' => 'qr-test@example.com',
            'phone' => '09171234567',
            'booking_reference' => 'MCR-QRTEST01',
            'reservation_type' => 'birthday',
            'package_name' => 'Birthday Package',
            'package_code' => 'birthday-package',
            'room_choice' => 'Party Room',
            'branch' => "McDonald's Test Branch",
            'branch_code' => 'test-branch',
            'event_date' => now()->toDateString(),
            'event_time' => '10:30:00',
            'duration_hours' => 4,
            'guests' => 15,
            'total_amount' => 5000,
            'check_in_code' => 'qrtestcode',
            'status' => 'confirmed',
            'service_status' => 'available',
        ], $attributes));
    }

    private function signedCheckInUrl(Reservation $reservation): string
    {
        return URL::signedRoute('reservations.qr-check-in', [
            'reservation' => $reservation,
            'code' => $reservation->check_in_code,
        ]);
    }
}

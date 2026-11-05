<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class ReservationSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_submit_a_reservation_with_a_payment_proof(): void
    {
        Storage::fake('public');

        $this->get('/')->assertOk();
        $user = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($user)->post(route('reservations.store'), $this->validPayload());

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('success');

        $reservation = Reservation::query()->where('user_id', $user->id)->firstOrFail();
        Storage::disk('public')->assertExists($reservation->payment_proof_path);
    }

    public function test_payment_storage_failure_returns_a_form_error_instead_of_a_server_error(): void
    {
        $this->get('/')->assertOk();
        $user = User::factory()->create(['role' => 'customer']);

        Storage::shouldReceive('disk')
            ->once()
            ->with('public')
            ->andThrow(new RuntimeException('Upload volume is read-only.'));

        $response = $this
            ->from(route('reservations.create'))
            ->actingAs($user)
            ->post(route('reservations.store'), $this->validPayload());

        $response->assertRedirect(route('reservations.create'));
        $response->assertSessionHasErrors([
            'payment_proof' => 'We could not save your payment proof. Please select the image again and retry.',
        ]);
        $this->assertDatabaseCount('reservations', 0);
    }

    private function validPayload(): array
    {
        return [
            'event_type' => 'birthday',
            'branch_code' => 'mnl-bgc',
            'event_date' => now()->addDays(10)->toDateString(),
            'event_time' => '10:00',
            'duration_hours' => 4,
            'room_choice' => 'birthday-party-room',
            'guests' => 10,
            'package_code' => 'playplace-blast',
            'menu_bundles' => [],
            'manual_menu_items' => [],
            'add_ons' => [],
            'payment_proof' => UploadedFile::fake()->image('payment-proof.jpg'),
        ];
    }
}

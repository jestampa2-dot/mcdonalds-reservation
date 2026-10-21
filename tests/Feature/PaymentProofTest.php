<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentProofTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_preview_and_download_their_payment_proof(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['role' => 'customer']);
        $proofPath = UploadedFile::fake()
            ->image('payment-proof.png')
            ->store('payment-proofs', 'public');

        $reservation = Reservation::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'booking_reference' => 'MCD-IMAGE-001',
            'reservation_type' => 'birthday',
            'branch' => "McDonald's Test Branch",
            'event_date' => now()->addWeek()->toDateString(),
            'event_time' => '10:00',
            'guests' => 10,
            'payment_proof_path' => $proofPath,
        ]);

        $this->actingAs($user)
            ->get(route('reservations.payment-proof', $reservation))
            ->assertOk()
            ->assertHeader('content-type', 'image/png')
            ->assertHeader('content-disposition', 'inline; filename="MCD-IMAGE-001-payment-proof.png"');

        $this->actingAs($user)
            ->get(route('reservations.payment-proof', [$reservation, 'download' => 1]))
            ->assertDownload('MCD-IMAGE-001-payment-proof.png');
    }
}

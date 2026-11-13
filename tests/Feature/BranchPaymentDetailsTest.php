<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BranchPaymentDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_upload_payment_details_for_their_assigned_branch(): void
    {
        Storage::fake('public');

        $branch = $this->branch('assigned-branch', 'Assigned Branch');
        $staff = User::factory()->create(['role' => 'staff', 'branch_code' => $branch->code]);

        $response = $this->actingAs($staff)->post("/staff/branches/{$branch->id}/payment-details", [
            'payment_number' => 'GCash 0917 123 4567',
            'payment_qr' => UploadedFile::fake()->image('payment-qr.png', 600, 600),
        ]);

        $response->assertRedirect()->assertSessionHasNoErrors();

        $branch->refresh();
        $this->assertSame('GCash 0917 123 4567', $branch->payment_number);
        $this->assertNotNull($branch->payment_qr_path);
        Storage::disk('public')->assertExists($branch->payment_qr_path);
    }

    public function test_staff_cannot_change_another_branches_payment_details(): void
    {
        $assigned = $this->branch('assigned-branch', 'Assigned Branch');
        $other = $this->branch('other-branch', 'Other Branch');
        $staff = User::factory()->create(['role' => 'staff', 'branch_code' => $assigned->code]);

        $this->actingAs($staff)
            ->post("/staff/branches/{$other->id}/payment-details", [
                'payment_number' => 'GCash 0999 000 0000',
            ])
            ->assertForbidden();

        $this->assertNull($other->fresh()->payment_number);
    }

    public function test_mobile_staff_can_upload_branch_payment_details(): void
    {
        Storage::fake('public');

        $branch = $this->branch('mobile-branch', 'Mobile Branch');
        $staff = User::factory()->create(['role' => 'staff', 'branch_code' => $branch->code]);
        Sanctum::actingAs($staff);

        $response = $this->post("/api/mobile/staff/branches/{$branch->id}/payment-details", [
            'payment_number' => 'Maya 0918 222 3333',
            'payment_qr' => UploadedFile::fake()->image('mobile-payment-qr.jpg', 500, 500),
        ], ['Accept' => 'application/json']);

        $response->assertOk()
            ->assertJsonPath('branch.payment_number', 'Maya 0918 222 3333');

        $branch->refresh();
        Storage::disk('public')->assertExists($branch->payment_qr_path);
    }

    public function test_customer_booking_options_include_the_selected_branches_payment_details(): void
    {
        Storage::fake('public');

        $branch = $this->branch('customer-branch', 'Customer Branch');
        $branch->update([
            'payment_number' => 'GCash 0900 111 2233',
            'payment_qr_path' => 'branch-payment-qrs/customer-branch.png',
        ]);
        Storage::disk('public')->put($branch->payment_qr_path, 'qr-image');

        $this->getJson('/api/mobile/booking-options')
            ->assertOk()
            ->assertJsonPath('catalog.branches.customer-branch.payment_number', 'GCash 0900 111 2233')
            ->assertJsonPath('catalog.branches.customer-branch.payment_qr_url', url('/storage/branch-payment-qrs/customer-branch.png'));
    }

    private function branch(string $code, string $name): Branch
    {
        return Branch::create([
            'code' => $code,
            'name' => $name,
            'city' => 'Surigao City',
            'supports' => ['birthday' => true, 'business' => true, 'table' => true],
            'concurrent_limit' => 2,
            'max_guests' => 80,
            'inventory' => [],
            'hosts' => [],
            'is_active' => true,
        ]);
    }
}

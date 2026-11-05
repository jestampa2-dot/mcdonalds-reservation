<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchAccessRequest;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BranchAccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_only_read_and_update_their_assigned_branch(): void
    {
        $assigned = $this->branch('assigned', 'Assigned Branch');
        $other = $this->branch('other', 'Other Branch');
        $admin = User::factory()->create(['role' => 'admin', 'branch_code' => $assigned->code]);
        $ownBooking = $this->reservation($assigned, 'OWN-1001');
        $otherBooking = $this->reservation($other, 'OTHER-1001');

        $report = $this->actingAs($admin)->get('/admin/reports/export');

        $report->assertOk();
        $csv = $report->streamedContent();
        $this->assertStringContainsString($ownBooking->booking_reference, $csv);
        $this->assertStringNotContainsString($otherBooking->booking_reference, $csv);

        $this->actingAs($admin)
            ->post("/admin/reservations/{$otherBooking->id}/status", ['status' => 'confirmed'])
            ->assertForbidden();

        $this->actingAs($admin)
            ->get("/admin/availability/{$other->code}/".now()->addWeek()->toDateString())
            ->assertForbidden();
    }

    public function test_admin_can_request_access_and_master_admin_can_approve_it(): void
    {
        $assigned = $this->branch('assigned', 'Assigned Branch');
        $requested = $this->branch('requested', 'Requested Branch');
        $admin = User::factory()->create(['role' => 'admin', 'branch_code' => $assigned->code]);
        $master = User::factory()->create(['role' => 'master_admin']);
        $booking = $this->reservation($requested, 'REQUESTED-1001');

        $this->actingAs($admin)
            ->post('/admin/branch-access-requests', [
                'branch_code' => $requested->code,
                'request_message' => 'Temporary event coverage.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $accessRequest = BranchAccessRequest::query()->firstOrFail();
        $this->assertSame('pending', $accessRequest->status);

        $this->actingAs($admin)
            ->post("/admin/reservations/{$booking->id}/status", ['status' => 'confirmed'])
            ->assertForbidden();

        $this->actingAs($master)
            ->post("/admin/branch-access-requests/{$accessRequest->id}/review", ['status' => 'approved'])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('branch_access_requests', [
            'id' => $accessRequest->id,
            'status' => 'approved',
            'reviewed_by' => $master->id,
        ]);

        $this->actingAs($admin)
            ->post("/admin/reservations/{$booking->id}/status", ['status' => 'confirmed'])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('reservations', ['id' => $booking->id, 'status' => 'confirmed']);
    }

    public function test_regular_admin_cannot_review_access_requests(): void
    {
        $assigned = $this->branch('assigned', 'Assigned Branch');
        $requested = $this->branch('requested', 'Requested Branch');
        $admin = User::factory()->create(['role' => 'admin', 'branch_code' => $assigned->code]);
        $otherAdmin = User::factory()->create(['role' => 'admin', 'branch_code' => $assigned->code]);
        $accessRequest = BranchAccessRequest::create([
            'user_id' => $admin->id,
            'branch_code' => $requested->code,
            'status' => 'pending',
        ]);

        $this->actingAs($otherAdmin)
            ->post("/admin/branch-access-requests/{$accessRequest->id}/review", ['status' => 'approved'])
            ->assertForbidden();

        $this->assertDatabaseHas('branch_access_requests', ['id' => $accessRequest->id, 'status' => 'pending']);
    }

    public function test_mobile_admin_is_also_restricted_to_assigned_and_approved_branches(): void
    {
        $assigned = $this->branch('assigned', 'Assigned Branch');
        $other = $this->branch('other', 'Other Branch');
        $admin = User::factory()->create(['role' => 'admin', 'branch_code' => $assigned->code]);
        $booking = $this->reservation($other, 'MOBILE-OTHER-1001');

        Sanctum::actingAs($admin);

        $this->getJson('/api/mobile/operations')
            ->assertOk()
            ->assertJsonPath('admin.accounts.users.0.id', $admin->id)
            ->assertJsonCount(1, 'admin.accounts.branches');

        $this->postJson("/api/mobile/admin/reservations/{$booking->id}/status", ['status' => 'confirmed'])
            ->assertForbidden();

        BranchAccessRequest::create([
            'user_id' => $admin->id,
            'branch_code' => $other->code,
            'status' => 'approved',
        ]);

        $this->postJson("/api/mobile/admin/reservations/{$booking->id}/status", ['status' => 'confirmed'])
            ->assertOk();
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

    private function reservation(Branch $branch, string $reference): Reservation
    {
        $customer = User::factory()->create(['role' => 'customer']);

        return Reservation::create([
            'user_id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'booking_reference' => $reference,
            'reservation_type' => 'birthday',
            'package_name' => 'Birthday Package',
            'branch' => $branch->name,
            'branch_code' => $branch->code,
            'event_date' => now()->addWeek()->toDateString(),
            'event_time' => '14:00',
            'guests' => 20,
            'total_amount' => 5000,
            'status' => 'pending_review',
            'service_status' => 'available',
        ]);
    }
}

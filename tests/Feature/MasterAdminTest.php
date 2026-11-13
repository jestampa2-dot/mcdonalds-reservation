<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MasterAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_admin_can_open_cross_branch_dashboard_and_activity_log(): void
    {
        $master = User::factory()->create(['role' => 'master_admin']);

        $this->actingAs($master)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->where('isMasterAdmin', true)
                ->has('masterOverview'));

        $this->actingAs($master)
            ->get('/admin/activity')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Activity')
                ->has('activities'));
    }

    public function test_regular_admin_cannot_open_master_activity_log(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get('/admin/activity')
            ->assertForbidden();
    }

    public function test_master_admin_can_create_a_branch_admin_with_branch_assignment(): void
    {
        $master = User::factory()->create(['role' => 'master_admin']);
        $branch = $this->branch();

        $this->actingAs($master)
            ->post('/admin/users', [
                'name' => 'Surigao Branch Admin',
                'email' => 'surigao.admin@example.com',
                'phone' => '+63 917 555 1010',
                'role' => 'admin',
                'branch_code' => $branch->code,
                'password' => 'StrongPassword123!',
                'password_confirmation' => 'StrongPassword123!',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'surigao.admin@example.com',
            'role' => 'admin',
            'branch_code' => $branch->code,
        ]);
    }

    public function test_only_master_admin_can_promote_another_master_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post('/admin/users', [
                'name' => 'Unauthorized Master',
                'email' => 'blocked.master@example.com',
                'role' => 'master_admin',
                'password' => 'StrongPassword123!',
                'password_confirmation' => 'StrongPassword123!',
            ])
            ->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'blocked.master@example.com']);
    }

    public function test_booking_decisions_are_recorded_for_master_admin_monitoring(): void
    {
        $master = User::factory()->create(['role' => 'master_admin']);
        $customer = User::factory()->create(['role' => 'customer']);
        $reservation = $this->reservation($customer);

        $this->actingAs($master)
            ->post("/admin/reservations/{$reservation->id}/status", ['status' => 'confirmed'])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $master->id,
            'action' => 'booking.status_changed',
            'subject_id' => $reservation->id,
            'branch_code' => 'surigao-city',
        ]);
    }

    public function test_master_admin_can_generate_a_reservation_csv_report(): void
    {
        $master = User::factory()->create(['role' => 'master_admin']);
        $reservation = $this->reservation(User::factory()->create(['role' => 'customer']));

        $response = $this->actingAs($master)->get('/admin/reports/export');

        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString($reservation->booking_reference, $content);
        $this->assertStringContainsString('Payment method', $content);
        $this->assertStringContainsString('Remaining balance', $content);
    }

    public function test_completed_event_report_includes_the_final_bill_and_payment_plan(): void
    {
        $master = User::factory()->create(['role' => 'master_admin']);
        $reservation = $this->reservation(User::factory()->create(['role' => 'customer']));
        $reservation->update([
            'status' => 'completed',
            'payment_type' => 'down_payment',
            'payment_amount_due' => 2500,
        ]);

        $this->actingAs($master)
            ->get(route('admin.reports'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Reports')
                ->has('completedEvents', 1)
                ->where('completedEvents.0.booking_reference', $reservation->booking_reference)
                ->where('completedEvents.0.payment_type', 'down_payment')
                ->where('completedEvents.0.payment_amount_due', 2500)
                ->where('completedEvents.0.payment_balance', 2500)
            );
    }

    private function branch(): Branch
    {
        return Branch::create([
            'code' => 'surigao-city',
            'name' => "McDonald's Surigao City",
            'city' => 'Surigao City',
            'supports' => ['birthday' => true, 'business' => true, 'table' => true],
            'concurrent_limit' => 2,
            'max_guests' => 80,
            'inventory' => [],
            'hosts' => [],
            'is_active' => true,
        ]);
    }

    private function reservation(User $customer): Reservation
    {
        return Reservation::create([
            'user_id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'booking_reference' => 'MASTER-1001',
            'reservation_type' => 'birthday',
            'package_name' => 'Birthday Package',
            'branch' => "McDonald's Surigao City",
            'branch_code' => 'surigao-city',
            'event_date' => now()->addWeek()->toDateString(),
            'event_time' => '14:00',
            'guests' => 20,
            'total_amount' => 5000,
            'status' => 'pending_review',
            'service_status' => 'available',
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_an_account(): void
    {
        $branch = $this->branch();
        $admin = User::factory()->create(['role' => 'admin', 'branch_code' => $branch->code]);

        $response = $this
            ->actingAs($admin)
            ->post('/admin/users', [
                'name' => 'Crew Member',
                'email' => 'crew@example.com',
                'phone' => '+63 917 200 0001',
                'role' => 'staff',
                'branch_code' => $branch->code,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'name' => 'Crew Member',
            'email' => 'crew@example.com',
            'phone' => '+63 917 200 0001',
            'role' => 'staff',
        ]);
    }

    public function test_admin_can_update_an_account(): void
    {
        $branch = $this->branch();
        $admin = User::factory()->create(['role' => 'admin', 'branch_code' => $branch->code]);
        $user = User::factory()->create(['role' => 'customer', 'branch_code' => $branch->code]);

        $response = $this
            ->actingAs($admin)
            ->post("/admin/users/{$user->id}", [
                'name' => 'Updated User',
                'email' => 'updated@example.com',
                'phone' => '+63 917 300 0001',
                'role' => 'manager',
                'branch_code' => $branch->code,
                'password' => '',
                'password_confirmation' => '',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $user->refresh();

        $this->assertSame('Updated User', $user->name);
        $this->assertSame('updated@example.com', $user->email);
        $this->assertSame('+63 917 300 0001', $user->phone);
        $this->assertSame('manager', $user->role);
    }

    public function test_admin_can_delete_an_account(): void
    {
        $branch = $this->branch();
        $admin = User::factory()->create(['role' => 'admin', 'branch_code' => $branch->code]);
        $user = User::factory()->create(['role' => 'customer', 'branch_code' => $branch->code]);

        $response = $this
            ->actingAs($admin)
            ->delete("/admin/users/{$user->id}");

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
        ]);
    }

    public function test_admin_cannot_delete_own_account(): void
    {
        $branch = $this->branch();
        $admin = User::factory()->create(['role' => 'admin', 'branch_code' => $branch->code]);

        $response = $this
            ->actingAs($admin)
            ->from('/admin/accounts')
            ->delete("/admin/users/{$admin->id}");

        $response
            ->assertSessionHas('error', 'You cannot delete your own account.')
            ->assertRedirect('/admin/accounts');

        $this->assertNotNull($admin->fresh());
    }

    private function branch(): Branch
    {
        return Branch::create([
            'code' => 'test-branch',
            'name' => "McDonald's Test Branch",
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

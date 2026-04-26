<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_fetch_mobile_home_and_booking_options(): void
    {
        $this->getJson('/api/mobile/home')
            ->assertOk()
            ->assertJsonStructure([
                'eventTypes',
                'branches',
                'featuredPackages',
                'stats',
            ]);

        $this->getJson('/api/mobile/booking-options')
            ->assertOk()
            ->assertJsonStructure([
                'catalog' => [
                    'eventTypes',
                    'branches',
                    'packages',
                    'menuBundles',
                    'addOns',
                    'roomChoices',
                    'bookingWindow',
                    'slotOptions',
                    'pricing',
                ],
                'roomChoices',
                'availability' => [
                    'generated_at',
                    'slotOptions',
                    'branches',
                ],
                'defaults' => [
                    'event_date',
                    'event_time',
                    'duration_hours',
                    'room_choice',
                ],
            ]);
    }

    public function test_guest_can_register_and_receive_a_mobile_token(): void
    {
        $response = $this->postJson('/api/mobile/register', [
            'name' => 'Mobile Customer',
            'email' => 'mobile@example.com',
            'phone' => '+63 912 345 6789',
            'birth_date' => '2000-01-15',
            'gender' => 'prefer_not_to_say',
            'address_line' => '123 Sample Street',
            'city' => 'Manila',
            'province' => 'Metro Manila',
            'postal_code' => '1000',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'message',
                'token',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'phone',
                    'role',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'mobile@example.com',
        ]);
    }

    public function test_authenticated_user_can_fetch_mobile_dashboard(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/mobile/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'bookings',
                'slotOptions',
                'stats',
            ]);
    }

    public function test_admin_can_fetch_mobile_operations_payload(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/mobile/operations')
            ->assertOk()
            ->assertJsonStructure([
                'role',
                'admin' => [
                    'dashboard',
                    'bookings',
                    'confirmedEvents',
                    'availability',
                    'branches',
                    'accounts',
                    'catalog',
                    'reports',
                    'timeline',
                ],
                'staff' => [
                    'prepList',
                    'todayBookings',
                    'notifications',
                    'history',
                    'statusOptions',
                ],
            ]);
    }

    public function test_authenticated_user_can_update_mobile_profile(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->putJson('/api/mobile/profile', [
            'name' => 'Updated Mobile Name',
            'email' => $user->email,
            'phone' => '+63 955 111 2222',
            'birth_date' => '1999-02-15',
            'gender' => 'prefer_not_to_say',
            'address_line' => '456 Updated Street',
            'city' => 'Quezon City',
            'province' => 'Metro Manila',
            'postal_code' => '1100',
        ])
            ->assertOk()
            ->assertJsonPath('profile.name', 'Updated Mobile Name')
            ->assertJsonPath('profile.city', 'Quezon City');
    }
}

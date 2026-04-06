<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '+63 917 555 0199',
            'birth_date' => '2000-05-20',
            'gender' => 'prefer_not_to_say',
            'address_line' => '123 Test Street',
            'city' => 'Quezon City',
            'province' => 'Metro Manila',
            'postal_code' => '1100',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(RouteServiceProvider::HOME);
        $this->assertDatabaseHas('users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '+63 917 555 0199',
            'gender' => 'prefer_not_to_say',
            'address_line' => '123 Test Street',
            'city' => 'Quezon City',
            'province' => 'Metro Manila',
            'postal_code' => '1100',
            'role' => 'customer',
        ]);
        $user = User::where('email', 'test@example.com')->firstOrFail();

        $this->assertSame('customer', $user->role);
        $this->assertSame('2000-05-20', optional($user->birth_date)->format('Y-m-d'));
    }
}

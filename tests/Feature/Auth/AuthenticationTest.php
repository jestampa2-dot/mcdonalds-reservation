<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PDOException;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(RouteServiceProvider::HOME);
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_see_a_friendly_error_when_the_database_is_unavailable(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\RedirectIfAuthenticated::class);

        Auth::shouldReceive('attempt')
            ->once()
            ->andThrow(new QueryException(
                'select * from `users` where `email` = ? limit 1',
                ['demo@example.com'],
                new PDOException(
                    'SQLSTATE[HY000] [2002] No connection could be made because the target machine actively refused it'
                )
            ));

        $response = $this->from('/login')->post('/login', [
            'email' => 'demo@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors([
            'email' => trans('auth.unavailable'),
        ]);
    }
}

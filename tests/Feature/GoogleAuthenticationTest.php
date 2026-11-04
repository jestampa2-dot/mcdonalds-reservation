<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GoogleIdTokenVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class GoogleAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.google', [
            'client_id' => 'web-client.apps.googleusercontent.com',
            'client_secret' => 'test-secret',
            'redirect' => 'http://127.0.0.1:8000/auth/google/callback',
        ]);
    }

    public function test_mobile_google_sign_in_creates_a_verified_customer(): void
    {
        $this->fakeGoogleToken([
            'sub' => 'google-123',
            'email' => 'new.customer@gmail.com',
            'email_verified' => true,
            'name' => 'New Customer',
            'picture' => 'https://example.com/avatar.jpg',
        ]);

        $response = $this->postJson('/api/mobile/google', ['id_token' => 'valid-token']);

        $response->assertOk()
            ->assertJsonPath('user.email', 'new.customer@gmail.com')
            ->assertJsonPath('user.role', 'customer')
            ->assertJsonPath('needs_profile_completion', true)
            ->assertJsonStructure(['token']);

        $this->assertDatabaseHas('users', [
            'email' => 'new.customer@gmail.com',
            'google_id' => 'google-123',
            'role' => 'customer',
        ]);

        $this->assertNotNull(User::where('email', 'new.customer@gmail.com')->firstOrFail()->email_verified_at);
    }

    public function test_mobile_google_sign_in_links_an_existing_customer_by_verified_email(): void
    {
        $customer = User::factory()->create([
            'email' => 'existing@gmail.com',
            'google_id' => null,
        ]);

        $this->fakeGoogleToken([
            'sub' => 'google-existing',
            'email' => 'EXISTING@gmail.com',
            'email_verified' => true,
            'name' => 'Existing Customer',
        ]);

        $this->postJson('/api/mobile/google', ['id_token' => 'valid-token'])
            ->assertOk()
            ->assertJsonPath('user.id', $customer->id)
            ->assertJsonPath('needs_profile_completion', false);

        $this->assertSame(1, User::whereRaw('LOWER(email) = ?', ['existing@gmail.com'])->count());
        $this->assertDatabaseHas('users', [
            'id' => $customer->id,
            'google_id' => 'google-existing',
        ]);
    }

    public function test_mobile_google_sign_in_rejects_an_unverified_email(): void
    {
        $this->fakeGoogleToken([
            'sub' => 'google-unverified',
            'email' => 'unverified@gmail.com',
            'email_verified' => false,
        ]);

        $this->postJson('/api/mobile/google', ['id_token' => 'valid-token'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('google');

        $this->assertDatabaseMissing('users', ['email' => 'unverified@gmail.com']);
    }

    public function test_google_sign_in_cannot_link_a_privileged_account(): void
    {
        User::factory()->create([
            'email' => 'admin@gmail.com',
            'role' => 'admin',
        ]);

        $this->fakeGoogleToken([
            'sub' => 'google-admin',
            'email' => 'admin@gmail.com',
            'email_verified' => true,
        ]);

        $this->postJson('/api/mobile/google', ['id_token' => 'valid-token'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('google');

        $this->assertDatabaseMissing('users', ['google_id' => 'google-admin']);
    }

    public function test_an_already_linked_google_account_keeps_access_after_master_admin_promotion(): void
    {
        $master = User::factory()->create([
            'email' => 'linked.master@gmail.com',
            'google_id' => 'google-linked-master',
            'role' => 'master_admin',
        ]);

        $this->fakeGoogleToken([
            'sub' => 'google-linked-master',
            'email' => 'linked.master@gmail.com',
            'email_verified' => true,
        ]);

        $this->postJson('/api/mobile/google', ['id_token' => 'valid-token'])
            ->assertOk()
            ->assertJsonPath('user.id', $master->id)
            ->assertJsonPath('user.role', 'master_admin');
    }

    public function test_an_explicitly_authorized_master_admin_can_link_google_once(): void
    {
        $master = User::factory()->create([
            'email' => 'pending.master@gmail.com',
            'google_id' => null,
            'google_link_pending' => true,
            'role' => 'master_admin',
        ]);

        $this->fakeGoogleToken([
            'sub' => 'google-pending-master',
            'email' => 'pending.master@gmail.com',
            'email_verified' => true,
        ]);

        $this->postJson('/api/mobile/google', ['id_token' => 'valid-token'])
            ->assertOk()
            ->assertJsonPath('user.id', $master->id)
            ->assertJsonPath('user.role', 'master_admin');

        $this->assertDatabaseHas('users', [
            'id' => $master->id,
            'google_id' => 'google-pending-master',
            'google_link_pending' => false,
        ]);
    }

    public function test_web_google_callback_signs_in_and_sends_new_customer_to_profile(): void
    {
        $googleUser = (new SocialiteUser())->setRaw([
            'sub' => 'google-web-123',
            'email' => 'web.customer@gmail.com',
            'email_verified' => true,
            'name' => 'Web Customer',
            'picture' => 'https://example.com/web-avatar.jpg',
        ])->map([
            'id' => 'google-web-123',
            'email' => 'web.customer@gmail.com',
            'name' => 'Web Customer',
            'avatar' => 'https://example.com/web-avatar.jpg',
        ]);

        $provider = Mockery::mock();
        $provider->shouldReceive('user')->once()->andReturn($googleUser);
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

        $response = $this->get('/auth/google/callback');

        $user = User::where('google_id', 'google-web-123')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('profile.edit'));
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function fakeGoogleToken(array $claims): void
    {
        $verifier = Mockery::mock(GoogleIdTokenVerifier::class);
        $verifier->shouldReceive('verify')
            ->once()
            ->with('valid-token')
            ->andReturn($claims);

        $this->app->instance(GoogleIdTokenVerifier::class, $verifier);
    }
}

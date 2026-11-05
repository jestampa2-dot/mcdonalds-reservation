<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\EnsureMasterAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionMasterAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_seeder_promotes_the_configured_google_account(): void
    {
        putenv('MASTER_ADMIN_EMAIL=jestampa2@ssct.edu.ph');
        putenv('MASTER_ADMIN_NAME=Jay Christian Estampa');
        $_ENV['MASTER_ADMIN_EMAIL'] = 'jestampa2@ssct.edu.ph';
        $_ENV['MASTER_ADMIN_NAME'] = 'Jay Christian Estampa';
        $_SERVER['MASTER_ADMIN_EMAIL'] = 'jestampa2@ssct.edu.ph';
        $_SERVER['MASTER_ADMIN_NAME'] = 'Jay Christian Estampa';

        try {
            $user = User::factory()->create([
                'email' => 'jestampa2@ssct.edu.ph',
                'email_verified_at' => null,
                'google_id' => 'google-account-subject',
                'google_link_pending' => true,
                'role' => 'customer',
                'branch_code' => 'mnl-bgc',
            ]);

            $this->seed(EnsureMasterAdminSeeder::class);

            $user->refresh();

            $this->assertSame('master_admin', $user->role);
            $this->assertNull($user->branch_code);
            $this->assertSame('google-account-subject', $user->google_id);
            $this->assertFalse($user->google_link_pending);
            $this->assertNotNull($user->email_verified_at);
        } finally {
            putenv('MASTER_ADMIN_EMAIL');
            putenv('MASTER_ADMIN_NAME');
            unset(
                $_ENV['MASTER_ADMIN_EMAIL'],
                $_ENV['MASTER_ADMIN_NAME'],
                $_SERVER['MASTER_ADMIN_EMAIL'],
                $_SERVER['MASTER_ADMIN_NAME']
            );
        }
    }
}

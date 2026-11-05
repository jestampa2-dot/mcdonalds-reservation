<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class EnsureMasterAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = Str::lower(trim((string) env('MASTER_ADMIN_EMAIL')));

        if ($email === '') {
            return;
        }

        $masterAdmin = User::query()
            ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
            ->first();

        if (! $masterAdmin) {
            $masterAdmin = User::query()->create([
                'email' => $email,
                'name' => env('MASTER_ADMIN_NAME', 'Master Admin'),
                'role' => 'master_admin',
                'email_verified_at' => now(),
                'google_link_pending' => true,
                'password' => Hash::make(Str::random(64)),
            ]);
        }

        $masterAdmin->forceFill([
            'email' => $email,
            'role' => 'master_admin',
            'branch_code' => null,
            'email_verified_at' => $masterAdmin->email_verified_at ?: now(),
            'google_link_pending' => blank($masterAdmin->google_id),
        ])->save();
    }
}

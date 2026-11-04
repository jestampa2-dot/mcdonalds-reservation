<?php

namespace Database\Seeders;

use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DatabaseSeeder::class);

        Reservation::query()
            ->whereIn('booking_reference', [
                'MCR-DEMO100',
                'MCR-DEMO200',
                'MCR-DEMO300',
            ])
            ->delete();

        User::query()
            ->whereIn('email', [
                'admin@mcdbooker.test',
                'manager@mcdbooker.test',
                'staff@mcdbooker.test',
                'crewlead@mcdbooker.test',
                'guest@mcdbooker.test',
            ])
            ->delete();

        $masterAdminEmail = Str::lower(trim((string) env('MASTER_ADMIN_EMAIL')));

        if ($masterAdminEmail === '') {
            return;
        }

        $masterAdmin = User::query()->firstOrCreate(
            ['email' => $masterAdminEmail],
            [
                'name' => env('MASTER_ADMIN_NAME', 'Master Admin'),
                'role' => 'master_admin',
                'email_verified_at' => now(),
                'google_link_pending' => true,
                'password' => Hash::make(Str::random(64)),
            ]
        );

        $masterAdmin->forceFill([
            'role' => 'master_admin',
            'email_verified_at' => $masterAdmin->email_verified_at ?: now(),
            'google_link_pending' => blank($masterAdmin->google_id),
        ])->save();
    }
}

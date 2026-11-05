<?php

namespace Database\Seeders;

use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Seeder;

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

        $this->call(EnsureMasterAdminSeeder::class);
    }
}

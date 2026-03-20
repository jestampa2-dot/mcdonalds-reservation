<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@mcdbooker.test'],
            ['name' => 'Ava Admin', 'role' => 'admin', 'password' => Hash::make('password')]
        );

        $manager = User::updateOrCreate(
            ['email' => 'manager@mcdbooker.test'],
            ['name' => 'Milo Manager', 'role' => 'manager', 'password' => Hash::make('password')]
        );

        $staff = User::updateOrCreate(
            ['email' => 'staff@mcdbooker.test'],
            ['name' => 'Sky Staff', 'role' => 'staff', 'password' => Hash::make('password')]
        );

        $staffTwo = User::updateOrCreate(
            ['email' => 'crewlead@mcdbooker.test'],
            ['name' => 'Riley Crew Lead', 'role' => 'staff', 'password' => Hash::make('password')]
        );

        $customer = User::updateOrCreate(
            ['email' => 'guest@mcdbooker.test'],
            ['name' => 'Casey Customer', 'role' => 'customer', 'password' => Hash::make('password')]
        );

        foreach (config('booking.branches') as $branch) {
            Branch::updateOrCreate(
                ['code' => $branch['code']],
                [
                    'name' => $branch['name'],
                    'city' => $branch['city'],
                    'supports' => $branch['supports'],
                    'concurrent_limit' => $branch['concurrent_limit'],
                    'max_guests' => $branch['max_guests'],
                    'map_url' => $branch['map_url'],
                    'inventory' => $branch['inventory'],
                    'hosts' => $branch['hosts'],
                    'is_active' => true,
                ]
            );
        }

        $samples = [
            [
                'user_id' => $customer->id,
                'assigned_staff_id' => $staff->id,
                'name' => 'Casey Customer',
                'email' => 'guest@mcdbooker.test',
                'phone' => '+63 917 555 0101',
                'booking_reference' => 'MCR-DEMO100',
                'reservation_type' => 'birthday',
                'package_name' => 'The Ultimate Birthday PlayPlace Bash',
                'package_code' => 'playplace-blast',
                'room_choice' => 'Celebration area',
                'food_package' => '10 Cheeseburger Meals, 5 McNugget Share Boxes',
                'beverage_package' => 'McFloat Refreshment Round',
                'event_materials' => 'Dedicated Party Host, Cake Service',
                'branch' => 'McDonald\'s BGC High Street',
                'branch_code' => 'mnl-bgc',
                'event_date' => now()->addDays(4)->toDateString(),
                'event_time' => '14:30',
                'menu_bundles' => ['burger-10', 'nugget-share-5'],
                'add_ons' => ['party-host', 'cake-service'],
                'guests' => 22,
                'total_amount' => 13191.50,
                'check_in_code' => 'demo100abc',
                'notes' => 'Birthday celebrant loves sports-themed decor.',
                'status' => 'confirmed',
                'service_status' => 'available',
            ],
            [
                'user_id' => $customer->id,
                'assigned_staff_id' => $staffTwo->id,
                'name' => 'Casey Customer',
                'email' => 'guest@mcdbooker.test',
                'phone' => '+63 917 555 0101',
                'booking_reference' => 'MCR-DEMO200',
                'reservation_type' => 'table',
                'package_name' => 'Family Feast Reservation',
                'package_code' => 'family-feast',
                'room_choice' => 'Family dining zone',
                'food_package' => '12 McSpaghetti Party Plates',
                'beverage_package' => 'McFloat Refreshment Round',
                'event_materials' => 'Cake Service',
                'branch' => 'McDonald\'s Mall of Asia',
                'branch_code' => 'mnl-moa',
                'event_date' => now()->addDays(2)->toDateString(),
                'event_time' => '17:30',
                'menu_bundles' => ['mcspaghetti-12', 'mcfloat-round'],
                'add_ons' => ['cake-service'],
                'guests' => 8,
                'total_amount' => 5350.50,
                'check_in_code' => 'demo200xyz',
                'notes' => 'Need a stroller-friendly table.',
                'status' => 'pending_review',
                'service_status' => 'available',
            ],
            [
                'user_id' => $manager->id,
                'assigned_staff_id' => $staff->id,
                'name' => 'Nadia Ops',
                'email' => 'ops@example.com',
                'phone' => '+63 917 555 0125',
                'booking_reference' => 'MCR-DEMO300',
                'reservation_type' => 'business',
                'package_name' => 'Boardroom Bites',
                'package_code' => 'boardroom-bites',
                'room_choice' => 'McCafe meeting zone',
                'food_package' => '10 Cheeseburger Meals',
                'beverage_package' => 'Coffee station',
                'event_materials' => 'Presentation Kit Upgrade',
                'branch' => 'McDonald\'s Ortigas Center',
                'branch_code' => 'mnl-ortigas',
                'event_date' => now()->addDay()->toDateString(),
                'event_time' => '11:30',
                'menu_bundles' => ['burger-10'],
                'add_ons' => ['meeting-upgrade'],
                'guests' => 14,
                'total_amount' => 9040.00,
                'check_in_code' => 'demo300ops',
                'notes' => 'Need extension cords for laptop setup.',
                'status' => 'confirmed',
                'service_status' => 'available',
            ],
        ];

        foreach ($samples as $sample) {
            Reservation::updateOrCreate(
                ['booking_reference' => $sample['booking_reference']],
                $sample
            );
        }
    }
}

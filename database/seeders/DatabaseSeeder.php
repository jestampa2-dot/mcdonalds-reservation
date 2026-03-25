<?php

namespace Database\Seeders;

use App\Models\AddOn;
use App\Models\BookingPackage;
use App\Models\Branch;
use App\Models\BranchHost;
use App\Models\BranchInventoryItem;
use App\Models\EventType;
use App\Models\MenuBundle;
use App\Models\PricingSetting;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $eventTypes = collect(config('booking.event_types'));
        $packages = collect(config('booking.packages'));
        $menuBundles = collect(config('booking.menu_bundles'));
        $addOns = collect(config('booking.add_ons'));
        $pricing = config('booking.pricing');

        $eventTypeModels = $eventTypes->values()->mapWithKeys(function (array $eventType, int $index) use ($eventTypes) {
            $code = $eventTypes->keys()->get($index);
            $model = EventType::updateOrCreate(
                ['code' => $code],
                [
                    'label' => $eventType['label'],
                    'description' => $eventType['description'],
                    'icon' => $eventType['icon'] ?? null,
                    'sort_order' => $index,
                    'is_active' => true,
                ]
            );

            return [$code => $model];
        });

        $packages->each(function (array $items, string $eventTypeCode) use ($eventTypeModels) {
            collect($items)->each(function (array $package, int $index) use ($eventTypeModels, $eventTypeCode) {
                BookingPackage::updateOrCreate(
                    ['code' => $package['code']],
                    [
                        'event_type_id' => $eventTypeModels[$eventTypeCode]->id,
                        'name' => $package['name'],
                        'price' => $package['price'],
                        'guest_range' => $package['guest_range'] ?? null,
                        'features' => $package['features'] ?? [],
                        'sort_order' => $index,
                        'is_active' => true,
                    ]
                );
            });
        });

        $menuBundles->each(function (array $bundle, int $index) {
            MenuBundle::updateOrCreate(
                ['code' => $bundle['code']],
                [
                    'name' => $bundle['name'],
                    'price' => $bundle['price'],
                    'prep_label' => $bundle['prep_label'] ?? null,
                    'sort_order' => $index,
                    'is_active' => true,
                ]
            );
        });

        $addOns->each(function (array $addOn, int $index) {
            AddOn::updateOrCreate(
                ['code' => $addOn['code']],
                [
                    'name' => $addOn['name'],
                    'price' => $addOn['price'],
                    'sort_order' => $index,
                    'is_active' => true,
                ]
            );
        });

        PricingSetting::updateOrCreate(
            ['id' => 1],
            [
                'weekend_multiplier' => $pricing['weekend_multiplier'],
                'holiday_multiplier' => $pricing['holiday_multiplier'],
                'extension_hourly_rate' => $pricing['extension_hourly_rate'],
                'holidays' => $pricing['holidays'] ?? [],
                'is_active' => true,
            ]
        );

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
            $branchModel = Branch::updateOrCreate(
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

            $branchModel->supportedEventTypes()->sync(
                $eventTypeModels
                    ->filter(fn ($eventTypeModel, $code) => $branch['supports'][$code] ?? false)
                    ->pluck('id')
                    ->all()
            );

            $inventoryItems = collect($branch['inventory'] ?? []);
            BranchInventoryItem::query()
                ->where('branch_id', $branchModel->id)
                ->whereNotIn('item', $inventoryItems->pluck('item')->all())
                ->delete();

            $inventoryItems->each(function (array $item, int $index) use ($branchModel) {
                BranchInventoryItem::updateOrCreate(
                    [
                        'branch_id' => $branchModel->id,
                        'item' => $item['item'],
                    ],
                    [
                        'stock' => $item['stock'] ?? 0,
                        'threshold' => $item['threshold'] ?? 0,
                        'sort_order' => $index,
                    ]
                );
            });

            $hosts = collect($branch['hosts'] ?? []);
            BranchHost::query()
                ->where('branch_id', $branchModel->id)
                ->whereNotIn('name', $hosts->all())
                ->delete();

            $hosts->each(function (string $host, int $index) use ($branchModel) {
                BranchHost::updateOrCreate(
                    [
                        'branch_id' => $branchModel->id,
                        'name' => $host,
                    ],
                    [
                        'sort_order' => $index,
                    ]
                );
            });
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

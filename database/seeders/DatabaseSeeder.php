<?php

namespace Database\Seeders;

use App\Models\AddOn;
use App\Models\BookingPackage;
use App\Models\Branch;
use App\Models\BranchHost;
use App\Models\BranchInventoryItem;
use App\Models\EventType;
use App\Models\MenuCategory;
use App\Models\MenuBundle;
use App\Models\MenuItem;
use App\Models\MenuItemOption;
use App\Models\PricingSetting;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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

        $this->seedMenuCatalog();

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
                'event_time' => '08:00',
                'menu_bundles' => ['burger-10', 'nugget-share-5'],
                'add_ons' => ['party-host', 'cake-service'],
                'manual_menu_items' => [
                    [
                        'option_code' => 'big-mac-solo',
                        'item_code' => 'big-mac',
                        'item_name' => 'Big Mac',
                        'option_label' => 'Solo',
                        'quantity' => 4,
                        'unit_price' => 154,
                        'line_total' => 616,
                        'prep_label' => '4 x Big Mac Solo',
                        'category_code' => 'burgers',
                    ],
                ],
                'guests' => 22,
                'total_amount' => 13807.50,
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
                'event_time' => '09:00',
                'menu_bundles' => ['mcspaghetti-12', 'mcfloat-round'],
                'add_ons' => ['cake-service'],
                'manual_menu_items' => [
                    [
                        'option_code' => 'fries-bff',
                        'item_code' => 'fries',
                        'item_name' => 'Fries',
                        'option_label' => 'BFF',
                        'quantity' => 1,
                        'unit_price' => 135,
                        'line_total' => 135,
                        'prep_label' => '1 x Fries BFF',
                        'category_code' => 'fries',
                    ],
                ],
                'guests' => 8,
                'total_amount' => 5485.50,
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
                'manual_menu_items' => [
                    [
                        'option_code' => 'coke-regular',
                        'item_code' => 'coke',
                        'item_name' => 'Coke',
                        'option_label' => 'Regular',
                        'quantity' => 10,
                        'unit_price' => 77,
                        'line_total' => 770,
                        'prep_label' => '10 x Coke Regular',
                        'category_code' => 'desserts-drinks',
                    ],
                ],
                'guests' => 14,
                'total_amount' => 9810.00,
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

    protected function seedMenuCatalog(): void
    {
        collect($this->menuCatalogSeed())->each(function (array $categoryData, int $categoryIndex) {
            $category = MenuCategory::updateOrCreate(
                ['code' => $categoryData['code']],
                [
                    'name' => $categoryData['name'],
                    'icon' => $categoryData['icon'] ?? null,
                    'description' => $categoryData['description'] ?? null,
                    'sort_order' => $categoryIndex,
                    'is_active' => $categoryData['is_active'] ?? true,
                ]
            );

            collect($categoryData['items'])->each(function (array $itemData, int $itemIndex) use ($category) {
                $item = MenuItem::updateOrCreate(
                    ['code' => $itemData['code']],
                    [
                        'menu_category_id' => $category->id,
                        'name' => $itemData['name'],
                        'description' => $itemData['description'] ?? null,
                        'badge' => $itemData['badge'] ?? null,
                        'artwork' => $itemData['artwork'] ?? null,
                        'sort_order' => $itemIndex,
                        'is_active' => $itemData['is_active'] ?? true,
                    ]
                );

                collect($itemData['options'] ?? [])->each(function (array $optionData, int $optionIndex) use ($item) {
                    MenuItemOption::updateOrCreate(
                        ['code' => $optionData['code'] ?? $this->menuOptionCode($item->code, $optionData['label'])],
                        [
                            'menu_item_id' => $item->id,
                            'label' => $optionData['label'],
                            'price' => $optionData['price'],
                            'prep_label' => $optionData['prep_label'] ?? $item->name.' '.$optionData['label'],
                            'sort_order' => $optionIndex,
                            'is_active' => $optionData['is_active'] ?? true,
                        ]
                    );
                });
            });
        });
    }

    protected function menuOptionCode(string $itemCode, string $label): string
    {
        return $itemCode.'-'.Str::slug($label);
    }

    protected function menuCatalogSeed(): array
    {
        return [
            [
                'code' => 'burgers',
                'name' => 'Burgers',
                'icon' => '🍔',
                'description' => 'Classic McDonald’s burger picks with solo, medium meal, and large meal options.',
                'items' => [
                    ['code' => 'big-mac', 'name' => 'Big Mac', 'description' => 'Signature layered burger with the classic Big Mac taste.', 'badge' => 'Bestseller', 'artwork' => 'burger', 'options' => [['label' => 'Solo', 'price' => 154], ['label' => 'Medium Meal', 'price' => 206], ['label' => 'Large Meal', 'price' => 226]]],
                    ['code' => 'burger-mcdo', 'name' => 'Burger McDo', 'description' => 'The everyday burger option for quick group add-ons.', 'artwork' => 'burger', 'options' => [['label' => 'Solo', 'price' => 37], ['label' => 'Medium Meal', 'price' => 111], ['label' => 'Large Meal', 'price' => 131]]],
                    ['code' => 'cheeseburger', 'name' => 'Cheeseburger', 'description' => 'Simple cheeseburger favorite for party trays and meal sets.', 'artwork' => 'burger', 'options' => [['label' => 'Solo', 'price' => 66], ['label' => 'Medium Meal', 'price' => 134], ['label' => 'Large Meal', 'price' => 154]]],
                    ['code' => 'cheesy-burger-mcdo', 'name' => 'Cheesy Burger McDo', 'description' => 'A budget-friendly burger with a cheesy finish.', 'artwork' => 'burger', 'options' => [['label' => 'Solo', 'price' => 50], ['label' => 'Medium Meal', 'price' => 121], ['label' => 'Large Meal', 'price' => 141]]],
                    ['code' => 'cheeseburger-deluxe', 'name' => 'Cheeseburger Deluxe', 'description' => 'An upgraded cheeseburger for fuller event meal picks.', 'artwork' => 'burger', 'options' => [['label' => 'Solo', 'price' => 95], ['label' => 'Medium Meal', 'price' => 152], ['label' => 'Large Meal', 'price' => 172]]],
                    ['code' => 'double-cheeseburger', 'name' => 'Double Cheeseburger', 'description' => 'Double patty cheeseburger for heavier meal selections.', 'artwork' => 'burger', 'options' => [['label' => 'Solo', 'price' => 117], ['label' => 'Medium Meal', 'price' => 170], ['label' => 'Large Meal', 'price' => 190]]],
                    ['code' => 'mcchicken', 'name' => 'McChicken', 'description' => 'Crispy chicken burger option with meal upgrades.', 'artwork' => 'chicken-burger', 'options' => [['label' => 'Solo', 'price' => 122], ['label' => 'Medium Meal', 'price' => 175], ['label' => 'Large Meal', 'price' => 195]]],
                    ['code' => 'quarter-pounder-with-cheese', 'name' => 'Quarter Pounder with Cheese', 'description' => 'Premium burger choice for bigger appetites.', 'badge' => 'Premium', 'artwork' => 'burger', 'options' => [['label' => 'Solo', 'price' => 158], ['label' => 'Medium Meal', 'price' => 206], ['label' => 'Large Meal', 'price' => 226]]],
                    ['code' => 'ebi-burger', 'name' => 'Ebi Burger', 'description' => 'Seafood burger pick with full meal options.', 'artwork' => 'shrimp-burger', 'options' => [['label' => 'Solo', 'price' => 160], ['label' => 'Medium Meal', 'price' => 217], ['label' => 'Large Meal', 'price' => 237]]],
                    ['code' => 'k-chicken-burger', 'name' => 'K-Chicken Burger', 'description' => 'K-style chicken burger for bold-flavor event orders.', 'artwork' => 'chicken-burger', 'options' => [['label' => 'Solo', 'price' => 160], ['label' => 'Medium Meal', 'price' => 217], ['label' => 'Large Meal', 'price' => 237]]],
                    ['code' => 'mccrispy-chicken-sandwich', 'name' => 'McCrispy Chicken Sandwich', 'description' => 'Value sandwich option for add-on burger orders.', 'artwork' => 'sandwich', 'options' => [['label' => 'Solo', 'price' => 49], ['label' => 'Medium Meal', 'price' => 122], ['label' => 'Large Meal', 'price' => 142]]],
                ],
            ],
            [
                'code' => 'chicken',
                'name' => 'Chicken',
                'icon' => '🍗',
                'description' => 'Chicken meals, McNuggets, and fillet choices for event-day favorites.',
                'items' => [
                    ['code' => '1pc-chicken-mcdo-with-fries', 'name' => '1-pc Chicken McDo with Fries', 'description' => 'Chicken McDo with fries in solo or meal sizing.', 'artwork' => 'chicken', 'options' => [['label' => 'Solo', 'price' => 80], ['label' => 'Medium Meal', 'price' => 132], ['label' => 'Large Meal', 'price' => 152]]],
                    ['code' => '2pc-chicken-mcdo', 'name' => '2-pc Chicken McDo', 'description' => 'Two-piece Chicken McDo meal set for heavier appetites.', 'badge' => 'Sharing favorite', 'artwork' => 'chicken', 'options' => [['label' => 'Solo', 'price' => 158], ['label' => 'Medium Meal', 'price' => 189], ['label' => 'Large Meal', 'price' => 194]]],
                    ['code' => '6pc-chicken-mcnuggets-with-fries', 'name' => '6-pc Chicken McNuggets with Fries', 'description' => 'McNuggets with fries for snack-style event trays.', 'artwork' => 'nuggets', 'options' => [['label' => 'Solo', 'price' => 123], ['label' => 'Medium Meal', 'price' => 178], ['label' => 'Large Meal', 'price' => 198]]],
                    ['code' => '6pc-chicken-mcnuggets-with-rice', 'name' => '6-pc Chicken McNuggets with Rice', 'description' => 'Rice-based McNuggets meal configuration.', 'artwork' => 'nuggets', 'options' => [['label' => 'Medium Meal', 'price' => 165], ['label' => 'Large Meal', 'price' => 170]]],
                    ['code' => 'mccrispy-chicken-fillet-with-fries', 'name' => 'McCrispy Chicken Fillet with Fries', 'description' => 'Fillet meal option with fries for event add-ons.', 'artwork' => 'fillet', 'options' => [['label' => 'Solo', 'price' => 60], ['label' => 'Medium Meal', 'price' => 115], ['label' => 'Large Meal', 'price' => 135]]],
                    ['code' => 'mccrispy-chicken-fillet-ala-king-with-fries', 'name' => 'McCrispy Chicken Fillet Ala King with Fries', 'description' => 'Ala King chicken fillet option for richer party meals.', 'artwork' => 'fillet', 'options' => [['label' => 'Solo', 'price' => 62], ['label' => 'Medium Meal', 'price' => 117], ['label' => 'Large Meal', 'price' => 137]]],
                ],
            ],
            [
                'code' => 'rice-bowls',
                'name' => 'Rice Bowls',
                'icon' => '🍚',
                'description' => 'Rice bowl options for practical reservation meal add-ons.',
                'items' => [
                    ['code' => '1pc-mushroom-pepper-steak-rice-bowl', 'name' => '1-pc Mushroom Pepper Steak Rice Bowl', 'description' => 'Single-piece pepper steak bowl for solo or meal ordering.', 'artwork' => 'rice-bowl', 'options' => [['label' => 'Solo', 'price' => 63], ['label' => 'Medium Meal', 'price' => 86], ['label' => 'Large Meal', 'price' => 91]]],
                    ['code' => '2pc-mushroom-pepper-steak-rice-bowl', 'name' => '2-pc Mushroom Pepper Steak Rice Bowl', 'description' => 'Double-piece rice bowl for fuller rice meal trays.', 'artwork' => 'rice-bowl', 'options' => [['label' => 'Solo', 'price' => 84], ['label' => 'Medium Meal', 'price' => 107], ['label' => 'Large Meal', 'price' => 112]]],
                    ['code' => '1pc-mushroom-pepper-steak-and-egg-rice-bowl', 'name' => '1-pc Mushroom Pepper Steak & Egg Rice Bowl', 'description' => 'Rice bowl with egg topping in meal configurations.', 'artwork' => 'rice-bowl', 'options' => [['label' => 'Medium Meal', 'price' => 107], ['label' => 'Large Meal', 'price' => 112]]],
                    ['code' => '2pc-mushroom-pepper-steak-and-egg-rice-bowl', 'name' => '2-pc Mushroom Pepper Steak & Egg Rice Bowl', 'description' => 'Double-piece rice bowl with egg for premium rice servings.', 'artwork' => 'rice-bowl', 'options' => [['label' => 'Medium Meal', 'price' => 129], ['label' => 'Large Meal', 'price' => 134]]],
                ],
            ],
            [
                'code' => 'pasta',
                'name' => 'McSpaghetti / Pasta Combos',
                'icon' => '🍝',
                'description' => 'McSpaghetti and pasta-style combos for kids and group events.',
                'items' => [
                    ['code' => 'mcspaghetti', 'name' => 'McSpaghetti', 'description' => 'Classic McSpaghetti solo serving.', 'artwork' => 'pasta', 'options' => [['label' => 'Solo', 'price' => 59]]],
                    ['code' => '1pc-chicken-mcdo-and-mcspaghetti', 'name' => '1-pc Chicken McDo & McSpaghetti', 'description' => 'A combined chicken and pasta meal for party guests.', 'artwork' => 'pasta', 'options' => [['label' => 'Solo', 'price' => 118], ['label' => 'Medium Meal', 'price' => 144], ['label' => 'Large Meal', 'price' => 149]]],
                    ['code' => 'mcspaghetti-with-burger-mcdo', 'name' => 'McSpaghetti with Burger McDo', 'description' => 'Pasta and burger pair for combo ordering.', 'artwork' => 'pasta', 'options' => [['label' => 'Medium Meal', 'price' => 112], ['label' => 'Large Meal', 'price' => 117]]],
                    ['code' => 'mcspaghetti-with-fries', 'name' => 'McSpaghetti with Fries', 'description' => 'Pasta with fries in medium and large meal options.', 'artwork' => 'pasta', 'options' => [['label' => 'Medium Meal', 'price' => 118], ['label' => 'Large Meal', 'price' => 138]]],
                ],
            ],
            [
                'code' => 'fries',
                'name' => 'Fries',
                'icon' => '🍟',
                'description' => 'Fries and Shake Shake Fries in regular, large, and BFF formats.',
                'items' => [
                    ['code' => 'fries', 'name' => 'Fries', 'description' => 'Classic fries available from medium up to BFF sharing size.', 'artwork' => 'fries', 'options' => [['label' => 'Solo / Medium', 'price' => 60], ['label' => 'Large', 'price' => 80], ['label' => 'BFF', 'price' => 135]]],
                    ['code' => 'shake-shake-fries-bbq', 'name' => 'Shake Shake Fries BBQ', 'description' => 'BBQ-flavored Shake Shake Fries for snack stations.', 'artwork' => 'fries', 'options' => [['label' => 'Solo / Medium', 'price' => 70], ['label' => 'Large', 'price' => 90], ['label' => 'BFF', 'price' => 150]]],
                    ['code' => 'shake-shake-fries-cheese', 'name' => 'Shake Shake Fries Cheese', 'description' => 'Cheese-flavored Shake Shake Fries for party trays.', 'artwork' => 'fries', 'options' => [['label' => 'Solo / Medium', 'price' => 70], ['label' => 'Large', 'price' => 90], ['label' => 'BFF', 'price' => 150]]],
                    ['code' => 'shake-shake-fries-nori', 'name' => 'Shake Shake Fries Nori', 'description' => 'Nori-flavored Shake Shake Fries with multiple serving sizes.', 'artwork' => 'fries', 'options' => [['label' => 'Solo / Medium', 'price' => 75], ['label' => 'Large', 'price' => 95], ['label' => 'BFF', 'price' => 155]]],
                ],
            ],
            [
                'code' => 'fries-float-combos',
                'name' => 'Fries + McFloat Combos',
                'icon' => '🥤',
                'description' => 'Snack combo options pairing fries with McFloat drinks.',
                'items' => [
                    ['code' => 'fries-mcfloat-combo', 'name' => 'Fries + McFloat Combo', 'description' => 'Classic fries and float combo in multiple sizes.', 'artwork' => 'combo', 'options' => [['label' => 'Medium', 'price' => 84], ['label' => 'Large', 'price' => 109], ['label' => 'BFF', 'price' => 222]]],
                    ['code' => 'shake-shake-fries-mcfloat-combo-bbq', 'name' => 'Shake Shake Fries + McFloat Combo BBQ', 'description' => 'BBQ Shake Shake Fries plus McFloat combo.', 'artwork' => 'combo', 'options' => [['label' => 'Medium', 'price' => 94], ['label' => 'Large', 'price' => 119], ['label' => 'BFF', 'price' => 237]]],
                    ['code' => 'shake-shake-fries-mcfloat-combo-cheese', 'name' => 'Shake Shake Fries + McFloat Combo Cheese', 'description' => 'Cheese Shake Shake Fries plus McFloat combo.', 'artwork' => 'combo', 'options' => [['label' => 'Medium', 'price' => 94], ['label' => 'Large', 'price' => 119], ['label' => 'BFF', 'price' => 237]]],
                    ['code' => 'shake-shake-fries-mcfloat-combo-nori', 'name' => 'Shake Shake Fries + McFloat Combo Nori', 'description' => 'Nori Shake Shake Fries plus McFloat combo.', 'artwork' => 'combo', 'options' => [['label' => 'Medium', 'price' => 99], ['label' => 'Large', 'price' => 124], ['label' => 'BFF', 'price' => 247]]],
                ],
            ],
            [
                'code' => 'mcfloat',
                'name' => 'McFloat',
                'icon' => '🥤',
                'description' => 'McFloat drinks in medium and large serving sizes.',
                'items' => [
                    ['code' => 'coke-mcfloat', 'name' => 'Coke McFloat', 'description' => 'Refreshing Coke McFloat for individual event add-ons.', 'artwork' => 'drink', 'options' => [['label' => 'Medium', 'price' => 32], ['label' => 'Large', 'price' => 53]]],
                ],
            ],
            [
                'code' => 'breakfast',
                'name' => 'Breakfast',
                'icon' => '🍳',
                'description' => 'Breakfast menu items with solo, meal, and hash brown variants.',
                'items' => [
                    ['code' => 'sausage-mcmuffin', 'name' => 'Sausage McMuffin', 'description' => 'Breakfast sandwich available as solo or meal.', 'artwork' => 'breakfast', 'options' => [['label' => 'Solo', 'price' => 95], ['label' => 'Meal', 'price' => 115], ['label' => 'With Hash Browns Meal', 'price' => 150]]],
                    ['code' => 'sausage-mcmuffin-with-egg', 'name' => 'Sausage McMuffin with Egg', 'description' => 'Sausage McMuffin upgraded with egg and breakfast meal options.', 'artwork' => 'breakfast', 'options' => [['label' => 'Solo', 'price' => 115], ['label' => 'Meal', 'price' => 135], ['label' => 'With Hash Browns Meal', 'price' => 170]]],
                    ['code' => 'egg-muffin', 'name' => 'Egg Muffin', 'description' => 'Straightforward breakfast muffin meal pick.', 'artwork' => 'breakfast', 'options' => [['label' => 'Meal', 'price' => 116]]],
                    ['code' => 'cheesy-eggdesal', 'name' => 'Cheesy Eggdesal', 'description' => 'Cheesy Eggdesal in solo, meal, and hash brown meal options.', 'artwork' => 'breakfast', 'options' => [['label' => 'Solo', 'price' => 77], ['label' => 'Meal', 'price' => 99], ['label' => 'With Hash Browns Meal', 'price' => 134]]],
                    ['code' => 'cheesy-eggdesal-with-sausage', 'name' => 'Cheesy Eggdesal with Sausage', 'description' => 'Cheesy Eggdesal with sausage for a heavier breakfast order.', 'artwork' => 'breakfast', 'options' => [['label' => 'Solo', 'price' => 108], ['label' => 'Meal', 'price' => 127], ['label' => 'With Hash Browns Meal', 'price' => 162]]],
                    ['code' => 'cheesy-eggdesal-with-ham', 'name' => 'Cheesy Eggdesal with Ham', 'description' => 'Ham version of Cheesy Eggdesal in multiple breakfast configurations.', 'artwork' => 'breakfast', 'options' => [['label' => 'Solo', 'price' => 97], ['label' => 'Meal', 'price' => 119], ['label' => 'With Hash Browns Meal', 'price' => 154]]],
                ],
            ],
            [
                'code' => 'desserts-drinks',
                'name' => 'Desserts and Drinks',
                'icon' => '🍦',
                'description' => 'Single-price dessert and beverage selections.',
                'items' => [
                    ['code' => 'apple-pie', 'name' => 'Apple Pie', 'description' => 'Classic McDonald’s dessert add-on.', 'artwork' => 'dessert', 'options' => [['label' => 'Regular', 'price' => 45]]],
                    ['code' => 'coke', 'name' => 'Coke', 'description' => 'Standard Coke drink selection for manual ordering.', 'artwork' => 'drink', 'options' => [['label' => 'Regular', 'price' => 77]]],
                    ['code' => 'coke-zero-sugar', 'name' => 'Coke Zero Sugar', 'description' => 'Zero sugar Coke drink option.', 'artwork' => 'drink', 'options' => [['label' => 'Regular', 'price' => 77]]],
                    ['code' => 'sprite', 'name' => 'Sprite', 'description' => 'Refreshing lemon-lime soft drink.', 'artwork' => 'drink', 'options' => [['label' => 'Regular', 'price' => 77]]],
                    ['code' => 'royal', 'name' => 'Royal', 'description' => 'Royal soft drink option.', 'artwork' => 'drink', 'options' => [['label' => 'Regular', 'price' => 77]]],
                    ['code' => 'iced-tea', 'name' => 'Iced Tea', 'description' => 'Iced tea beverage add-on.', 'artwork' => 'drink', 'options' => [['label' => 'Regular', 'price' => 85]]],
                    ['code' => 'orange-juice', 'name' => 'Orange Juice', 'description' => 'Orange juice beverage option.', 'artwork' => 'drink', 'options' => [['label' => 'Regular', 'price' => 85]]],
                    ['code' => 'apple-juice', 'name' => 'Apple Juice', 'description' => 'Apple juice beverage option.', 'artwork' => 'drink', 'options' => [['label' => 'Regular', 'price' => 85]]],
                    ['code' => 'mcflurry-with-oreo', 'name' => 'McFlurry with Oreo', 'description' => 'Sweet Oreo McFlurry dessert cup.', 'artwork' => 'dessert', 'options' => [['label' => 'Regular', 'price' => 70]]],
                    ['code' => 'cotton-candy-mcfloat', 'name' => 'Cotton Candy McFloat', 'description' => 'Sweet float option for snack add-ons.', 'artwork' => 'drink', 'options' => [['label' => 'Regular', 'price' => 61]]],
                    ['code' => 'raspberry-mcfloat', 'name' => 'Raspberry McFloat', 'description' => 'Raspberry float variation.', 'artwork' => 'drink', 'options' => [['label' => 'Regular', 'price' => 61]]],
                    ['code' => 'grape-mcfloat', 'name' => 'Grape McFloat', 'description' => 'Grape float variation.', 'artwork' => 'drink', 'options' => [['label' => 'Regular', 'price' => 61]]],
                    ['code' => 'royal-mcfloat', 'name' => 'Royal McFloat', 'description' => 'Royal-based McFloat selection.', 'artwork' => 'drink', 'options' => [['label' => 'Regular', 'price' => 60]]],
                ],
            ],
            [
                'code' => 'sharing',
                'name' => 'Group / Sharing Items',
                'icon' => '🎉',
                'description' => 'Stored in the catalog for future pricing and branch-level activation.',
                'items' => [
                    ['code' => '6pc-chicken-mcshare-box', 'name' => '6-pc Chicken McShare Box', 'description' => 'Group-sharing item pending branch pricing setup.', 'badge' => 'Branch quote', 'artwork' => 'sharing', 'is_active' => false, 'options' => []],
                    ['code' => '8pc-chicken-mcshare-box', 'name' => '8-pc Chicken McShare Box', 'description' => 'Group-sharing item pending branch pricing setup.', 'badge' => 'Branch quote', 'artwork' => 'sharing', 'is_active' => false, 'options' => []],
                    ['code' => '20pc-chicken-mcnuggets', 'name' => '20-pc Chicken McNuggets', 'description' => 'Group-sharing item pending branch pricing setup.', 'badge' => 'Branch quote', 'artwork' => 'sharing', 'is_active' => false, 'options' => []],
                    ['code' => 'bff-fries-items', 'name' => 'BFF Fries Items', 'description' => 'Stored for later branch-level bundle pricing.', 'badge' => 'Branch quote', 'artwork' => 'sharing', 'is_active' => false, 'options' => []],
                    ['code' => 'mcshare-bundles', 'name' => 'McShare Bundles', 'description' => 'Stored for later branch-level bundle pricing.', 'badge' => 'Branch quote', 'artwork' => 'sharing', 'is_active' => false, 'options' => []],
                ],
            ],
        ];
    }
}

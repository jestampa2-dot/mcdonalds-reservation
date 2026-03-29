<?php

return [
    'event_types' => [
        'birthday' => [
            'label' => 'Birthday Bash',
            'description' => 'PlayPlace-ready parties with themed setups, party meals, and host support.',
            'icon' => 'Celebration',
        ],
        'business' => [
            'label' => 'Business Meeting',
            'description' => 'Quick-serve meetings with presentation kits, coffee service, and private seating.',
            'icon' => 'Briefcase',
        ],
        'table' => [
            'label' => 'Table Reservation',
            'description' => 'Fast family meals with guaranteed seating and pre-ordered bundles.',
            'icon' => 'UtensilsCrossed',
        ],
    ],
    'slot_options' => array_map(
        fn ($hour) => str_pad((string) $hour, 2, '0', STR_PAD_LEFT).':00',
        range(7, 12)
    ),
    'pricing' => [
        'weekend_multiplier' => 1.15,
        'holiday_multiplier' => 1.25,
        'extension_hourly_rate' => 450,
        'holidays' => [
            '2026-04-03',
            '2026-05-01',
            '2026-12-25',
        ],
    ],
    'branches' => [
        'mnl-bgc' => [
            'code' => 'mnl-bgc',
            'name' => 'McDonald\'s BGC High Street',
            'city' => 'Taguig',
            'supports' => [
                'birthday' => true,
                'business' => true,
                'table' => true,
            ],
            'concurrent_limit' => 3,
            'max_guests' => 48,
            'coords' => ['lat' => 14.5495, 'lng' => 121.0510],
            'map_url' => 'https://maps.google.com/?q=McDonald%27s+BGC+High+Street',
            'inventory' => [
                ['item' => 'Party balloons', 'stock' => 68, 'threshold' => 24],
                ['item' => 'Birthday hats', 'stock' => 58, 'threshold' => 20],
                ['item' => 'Happy Meal toys', 'stock' => 92, 'threshold' => 40],
                ['item' => 'Meeting kits', 'stock' => 18, 'threshold' => 8],
            ],
            'hosts' => ['Alyssa', 'Marco', 'Trixie', 'Paolo'],
        ],
        'mnl-moa' => [
            'code' => 'mnl-moa',
            'name' => 'McDonald\'s Mall of Asia',
            'city' => 'Pasay',
            'supports' => [
                'birthday' => true,
                'business' => false,
                'table' => true,
            ],
            'concurrent_limit' => 2,
            'max_guests' => 36,
            'coords' => ['lat' => 14.5350, 'lng' => 120.9822],
            'map_url' => 'https://maps.google.com/?q=McDonald%27s+Mall+of+Asia',
            'inventory' => [
                ['item' => 'Party balloons', 'stock' => 26, 'threshold' => 24],
                ['item' => 'Birthday hats', 'stock' => 22, 'threshold' => 20],
                ['item' => 'Happy Meal toys', 'stock' => 44, 'threshold' => 40],
                ['item' => 'Meeting kits', 'stock' => 0, 'threshold' => 8],
            ],
            'hosts' => ['Nina', 'Chad', 'Vince'],
        ],
        'mnl-ortigas' => [
            'code' => 'mnl-ortigas',
            'name' => 'McDonald\'s Ortigas Center',
            'city' => 'Pasig',
            'supports' => [
                'birthday' => false,
                'business' => true,
                'table' => true,
            ],
            'concurrent_limit' => 2,
            'max_guests' => 32,
            'coords' => ['lat' => 14.5869, 'lng' => 121.0619],
            'map_url' => 'https://maps.google.com/?q=McDonald%27s+Ortigas+Center',
            'inventory' => [
                ['item' => 'Party balloons', 'stock' => 0, 'threshold' => 24],
                ['item' => 'Birthday hats', 'stock' => 0, 'threshold' => 20],
                ['item' => 'Happy Meal toys', 'stock' => 38, 'threshold' => 40],
                ['item' => 'Meeting kits', 'stock' => 11, 'threshold' => 8],
            ],
            'hosts' => ['Jamie', 'Carlo', 'Mika'],
        ],
    ],
    'packages' => [
        'birthday' => [
            [
                'code' => 'playplace-blast',
                'name' => 'The Ultimate Birthday PlayPlace Bash',
                'price' => 7990,
                'guest_range' => '15-30 guests',
                'features' => ['Private party nook', 'Host-led games', 'Themed table setup'],
            ],
            [
                'code' => 'happy-meal-jam',
                'name' => 'Happy Meal Jam',
                'price' => 5590,
                'guest_range' => '10-18 guests',
                'features' => ['Happy Meal line-up', 'Birthday song moment', 'Party hats included'],
            ],
        ],
        'business' => [
            [
                'code' => 'boardroom-bites',
                'name' => 'Boardroom Bites',
                'price' => 6490,
                'guest_range' => '10-20 guests',
                'features' => ['Projector-ready zone', 'Coffee and fries station', 'Meeting kits'],
            ],
            [
                'code' => 'coffee-huddle',
                'name' => 'Coffee Huddle',
                'price' => 3990,
                'guest_range' => '6-12 guests',
                'features' => ['Breakfast platters', 'Coffee carafes', 'Express setup'],
            ],
        ],
        'table' => [
            [
                'code' => 'family-feast',
                'name' => 'Family Feast Reservation',
                'price' => 2490,
                'guest_range' => '4-10 guests',
                'features' => ['Reserved table', 'Priority meal prep', 'QR check-in pass'],
            ],
            [
                'code' => 'crew-catchup',
                'name' => 'Crew Catch-Up',
                'price' => 3290,
                'guest_range' => '8-14 guests',
                'features' => ['Long-table setup', 'Bundle pre-order', 'Dessert add-on ready'],
            ],
        ],
    ],
    'menu_bundles' => [
        [
            'code' => 'burger-10',
            'name' => '10 Cheeseburger Meals',
            'price' => 1850,
            'prep_label' => '10x cheeseburger trays',
        ],
        [
            'code' => 'nugget-share-5',
            'name' => '5 McNugget Share Boxes',
            'price' => 1490,
            'prep_label' => '5x McNugget boxes',
        ],
        [
            'code' => 'mcspaghetti-12',
            'name' => '12 McSpaghetti Party Plates',
            'price' => 1320,
            'prep_label' => '12x spaghetti plates',
        ],
        [
            'code' => 'mcfloat-round',
            'name' => 'McFloat Refreshment Round',
            'price' => 690,
            'prep_label' => '12x float drinks',
        ],
    ],
    'add_ons' => [
        [
            'code' => 'party-host',
            'name' => 'Dedicated Party Host',
            'price' => 1200,
        ],
        [
            'code' => 'cake-service',
            'name' => 'Cake Service',
            'price' => 650,
        ],
        [
            'code' => 'sports-theme',
            'name' => 'Sports Theme Decor',
            'price' => 900,
        ],
        [
            'code' => 'meeting-upgrade',
            'name' => 'Presentation Kit Upgrade',
            'price' => 700,
        ],
    ],
];

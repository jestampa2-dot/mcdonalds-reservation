<?php

return [
    'enabled' => env('SMS_ENABLED', false),
    'textbee' => [
        'base_url' => env('TEXTBEE_BASE_URL', 'https://api.textbee.dev/api/v1'),
        'api_key' => env('TEXTBEE_API_KEY', ''),
        'device_id' => env('TEXTBEE_DEVICE_ID', ''),
    ],
];

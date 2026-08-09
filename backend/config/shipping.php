<?php

return [
    'pricing_policy' => [
        'version' => 'compatibility-v1',
        'currency' => 'VND',
        'free_shipping_threshold' => 200000,
        'base_fee_per_physical_vendor' => 15000,
    ],

    'demo_carrier' => [
        'name' => env('DEMO_SHIPPING_CARRIER', 'KomiBook Express (mô phỏng)'),
        'tracking_prefix' => env('DEMO_SHIPPING_TRACKING_PREFIX', 'KBX'),
    ],
];

<?php

return [
    /*
     * External calls remain opt-in. Keeping this false guarantees that local,
     * test and newly deployed environments cannot create billable requests.
     */
    'external_calls_enabled' => (bool) env('PAYMENT_EXTERNAL_CALLS_ENABLED', false),

    'providers' => [
        'vnpay' => [
            'label' => 'VNPAY Sandbox',
            'mode' => env('VNPAY_MODE', 'sandbox'),
            'supports_qr' => true,
            'supports_refund' => true,
            'supports_reconciliation' => true,
        ],
        'demo_wallet' => [
            'label' => 'Ví KomiBook',
            'mode' => env('DEMO_WALLET_MODE', 'disabled'),
            'supports_qr' => false,
            'supports_refund' => true,
            'supports_reconciliation' => false,
        ],
    ],
];

<?php

return [
    'expected_database' => env('PRODUCTION_DATABASE_NAME', 'komibook'),
    'expected_host' => env('PRODUCTION_HOST', 'komibook.id.vn'),
    'shared_root' => env('PRODUCTION_SHARED_ROOT', 'C:/komibook_shared'),

    'minimum_counts' => [
        'users' => (int) env('PRODUCTION_MIN_USERS', 1),
        'books' => (int) env('PRODUCTION_MIN_BOOKS', 1),
        'vendors' => (int) env('PRODUCTION_MIN_VENDORS', 1),
        'organizations' => (int) env('PRODUCTION_MIN_ORGANIZATIONS', 1),
    ],

    // Canonical runtime schema contracts. Legacy compatibility columns are
    // intentionally excluded so releases cannot silently depend on them.
    'required_columns' => [
        'vendors' => [
            'onboarding_status',
            'business_model',
            'is_demo',
            'submitted_at',
            'last_review_reason',
        ],
    ],

    'forbidden_commands' => [
        'db:wipe',
        'migrate:fresh',
        'migrate:refresh',
        'migrate:reset',
        'migrate:rollback',
        'schema:dump',
    ],
];

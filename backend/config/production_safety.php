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

    // Every database path managed by the public disk must resolve inside the
    // shared storage before a release can receive production traffic.
    'public_media_references' => [
        ['table' => 'users', 'columns' => ['avatar']],
        ['table' => 'vendors', 'columns' => ['logo']],
        ['table' => 'organizations', 'columns' => ['logo']],
        ['table' => 'books', 'columns' => ['cover_image'], 'json_columns' => ['gallery_images']],
        ['table' => 'articles', 'columns' => ['cover_image', 'social_image']],
        ['table' => 'article_media', 'columns' => ['path'], 'where' => ['disk' => 'public']],
        ['table' => 'used_book_listings', 'json_columns' => ['actual_photos']],
        ['table' => 'notification_campaigns', 'columns' => ['image_url']],
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

<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'vnpay' => [
        'tmn_code' => env('VNPAY_TMN_CODE'),
        'hash_secret' => env('VNPAY_HASH_SECRET'),
        'url' => env('VNPAY_URL', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html'),
        'return_url' => env('VNPAY_RETURN_URL'),
        'refund_url' => env('VNPAY_REFUND_URL', 'https://sandbox.vnpayment.vn/merchant_webapi/api/transaction'),
        'confirm_on_return' => env('VNPAY_CONFIRM_ON_RETURN', false),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
    ],

    'facebook' => [
        'app_id' => env('FACEBOOK_APP_ID'),
        'app_secret' => env('FACEBOOK_APP_SECRET'),
        'graph_version' => env('FACEBOOK_GRAPH_VERSION'),
    ],

    'sms' => [
        'provider' => env('SMS_PROVIDER'),
    ],

    'gemini' => [
        'enabled' => env('GEMINI_ENABLED', false),
        'api_key' => env('GEMINI_API_KEY', ''),
        'model' => env('GEMINI_MODEL', ''),
        'fallback_model' => env('GEMINI_FALLBACK_MODEL', ''),
        'allowed_models' => array_values(array_unique(array_filter(array_map('trim', explode(',', (string) env('GEMINI_ALLOWED_MODELS', '')))))),
        'max_attempts' => env('GEMINI_MAX_ATTEMPTS', 1),
        'connect_timeout' => env('GEMINI_CONNECT_TIMEOUT', 3),
        'timeout' => env('GEMINI_TIMEOUT', 12),
        'max_output_tokens' => env('GEMINI_MAX_OUTPUT_TOKENS', 1200),
    ],

];

<?php

return [

    'session_token_pepper' => env('SESSION_TOKEN_PEPPER'),
    'verification_code_pepper' => env('VERIFICATION_CODE_PEPPER'),
    'session_idle_ttl' => (int) env('SESSION_IDLE_TTL', 1209600),
    'webhook_payload_retention_days' => (int) env('WEBHOOK_PAYLOAD_RETENTION_DAYS', 30),
    'demo_webhook_secret' => env('DEMO_WEBHOOK_SECRET'),
    'turnstile' => [
        'secret' => env('TURNSTILE_SECRET'),
    ],
    'hcaptcha' => [
        'secret' => env('HCAPTCHA_SECRET'),
    ],

    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'publishable' => env('STRIPE_PUBLISHABLE_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'defaults' => [
        'payment_driver' => env('DEFAULT_PAYMENT_DRIVER', 'demo'),
        'verification_driver' => env('DEFAULT_VERIFICATION_DRIVER', 'demo'),
        'human_check_driver' => env('DEFAULT_HUMAN_CHECK_DRIVER', 'demo'),
        'email_provider' => env('DEFAULT_EMAIL_PROVIDER', 'log'),
        'sms_provider' => env('DEFAULT_SMS_PROVIDER', 'log'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
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

];

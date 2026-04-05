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

    /*
    | Column-style mock bank (Next.js app in /mock-bank). Used for sandbox HTTP + webhooks.
    */
    'mock_bank' => [
        'base_url' => env('MOCK_BANK_BASE_URL'),
        'secret' => env('MOCK_BANK_SECRET'),
        'webhook_secret' => env('MOCK_BANK_WEBHOOK_SECRET'),
        /*
        | When true, KYC submission and account creation use in-process responses instead of HTTP
        | to the mock-bank service. If MOCK_BANK_INLINE is unset, defaults to true only in the
        | local environment so `php artisan serve` works without mock-bank on :3000.
        */
        'inline' => (function (): bool {
            $raw = env('MOCK_BANK_INLINE');

            if ($raw === null) {
                return app()->environment('local');
            }

            return filter_var($raw, FILTER_VALIDATE_BOOLEAN);
        })(),
    ],

];

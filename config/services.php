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

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI'),
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET_KEY', env('STRIPE_SECRET')),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'currency' => env('STRIPE_CURRENCY', 'AUD'),
        'success_url' => env(
            'STRIPE_SUCCESS_URL',
            rtrim(env('FRONTEND_APP_URL', env('FRONTEND_URL', env('APP_URL'))), '/') . '/admin/billing/success?session_id={CHECKOUT_SESSION_ID}'
        ),
        'cancel_url' => env(
            'STRIPE_CANCEL_URL',
            rtrim(env('FRONTEND_APP_URL', env('FRONTEND_URL', env('APP_URL'))), '/') . '/admin/billing/cancel'
        ),
    ],

    'abn_lookup' => [
        'guid' => env('ABN_LOOKUP_GUID'),
        'endpoint' => env('ABN_LOOKUP_ENDPOINT', 'https://abr.business.gov.au/ABRXMLSearch/AbrXmlSearch.asmx'),
        'timeout' => env('ABN_LOOKUP_TIMEOUT', 15),
        'retry_attempts' => env('ABN_LOOKUP_RETRY_ATTEMPTS', 2),
        'retry_sleep_ms' => env('ABN_LOOKUP_RETRY_SLEEP_MS', 300),
    ],

];

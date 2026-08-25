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

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'stripe' => [
        'mode' => env('STRIPE_MODE', 'test'),
        'key' => env('STRIPE_PUBLISHABLE_KEY'),
        'secret' => env('STRIPE_SECRET_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'test_key' => env('STRIPE_TEST_PUBLISHABLE_KEY'),
        'test_secret' => env('STRIPE_TEST_SECRET_KEY'),
        'test_webhook_secret' => env('STRIPE_TEST_WEBHOOK_SECRET'),
        'live_key' => env('STRIPE_LIVE_PUBLISHABLE_KEY'),
        'live_secret' => env('STRIPE_LIVE_SECRET_KEY'),
        'live_webhook_secret' => env('STRIPE_LIVE_WEBHOOK_SECRET'),
    ],

    /*
    | OpenStreetMap Nominatim (free): forward-geocode typed addresses to lat/lng when the
    | client does not send coordinates, then match operational areas by GPS radius.
    | https://operations.osmfoundation.org/policies/nominatim/ — use a stable User-Agent.
    */
    'nominatim' => [
        'forward_geocode_enabled' => filter_var(env('NOMINATIM_FORWARD_GEOCODE_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'base_url' => rtrim((string) env('NOMINATIM_BASE_URL', 'https://nominatim.openstreetmap.org'), '/'),
        // Keep below mobile client timeouts (often 15s) — geocode is a last resort only.
        'timeout' => (int) env('NOMINATIM_TIMEOUT', 4),
        'connect_timeout' => (int) env('NOMINATIM_CONNECT_TIMEOUT', 2),
        'user_agent' => env('NOMINATIM_USER_AGENT'),
    ],

    /*
    | Mobile social sign-in (POST /api/auth/google, POST /api/auth/apple).
    | Comma-separated client IDs if you have separate iOS/Android OAuth clients.
    */
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID', env('PUBLIC_GOOGLE_CLIENT_ID')),
        'client_ids' => env('GOOGLE_CLIENT_IDS', env('PUBLIC_GOOGLE_CLIENT_IDS')),
    ],

    'apple' => [
        // iOS React Native: identity token `aud` = Bundle ID (e.g. com.company.tandil).
        // Web Sign in with Apple: `aud` = Services ID — add both to APPLE_CLIENT_IDS if needed.
        'client_id' => env('APPLE_CLIENT_ID', env('PUBLIC_APPLE_BUNDLE_ID', env('APPLE_BUNDLE_ID'))),
        'client_ids' => env('APPLE_CLIENT_IDS', env('PUBLIC_APPLE_BUNDLE_IDS')),
    ],

    /*
    | Delivery OTP SMS. Default driver `log` records outbound messages.
    | Set SMS_DRIVER=http and SMS_HTTP_URL to POST JSON {to,message,context} to a gateway.
    */
    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
        'http_url' => env('SMS_HTTP_URL'),
        'http_token' => env('SMS_HTTP_TOKEN'),
        'sender_id' => env('SMS_SENDER_ID', 'Tandil'),
    ],

];

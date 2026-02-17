<?php

return [

    /*
    |--------------------------------------------------------------------------
    | IMAP Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the IMAP server used to detect email bounces.
    |
    */

    'imap' => [
        'host' => env('NEWSLETTER_IMAP_HOST'),
        'port' => env('NEWSLETTER_IMAP_PORT', 993),
        'username' => env('NEWSLETTER_IMAP_USERNAME'),
        'password' => env('NEWSLETTER_IMAP_PASSWORD'),
        'encryption' => env('NEWSLETTER_IMAP_ENCRYPTION', 'ssl'),
        'folder' => env('NEWSLETTER_IMAP_FOLDER', 'INBOX'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tracking Configuration
    |--------------------------------------------------------------------------
    |
    | Enable or disable email tracking (opens and clicks).
    |
    */

    'tracking' => [
        'enabled' => env('NEWSLETTER_TRACKING_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure rate limits for sending emails. Set to 0 to disable a specific limit.
    | Limits are progressive: daily limit takes precedence over hourly,
    | and hourly takes precedence over per-minute.
    |
    */

    'rate_limits' => [
        'per_minute' => env('NEWSLETTER_RATE_LIMIT_PER_MINUTE', 0),
        'per_hour' => env('NEWSLETTER_RATE_LIMIT_PER_HOUR', 0),
        'per_day' => env('NEWSLETTER_RATE_LIMIT_PER_DAY', 0),
    ],

];

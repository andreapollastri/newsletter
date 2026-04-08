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
    | All limits use rolling windows in cache (minute / hour / day). Set any value
    | to 0 to disable that window; the others still apply.
    |
    | Sending checks limits in this order: daily → hourly → per-minute. A send is
    | allowed only if it fits under every enabled cap. The tightest cap determines
    | throughput in practice.
    |
    | When a cap is exceeded, SendNewsletterEmail calls release() with a delay
    | (retry_after); no email is sent and counters are not incremented for that
    | attempt. A low per-minute cap causes frequent short delays; for large blasts,
    | many deployments set per-minute to 0 and rely on per-hour / per-day only.
    |
    | Estimated completion time for messages in "Sending" status (see Message::
    | getEstimatedSendTime) uses the same three values.
    |
    | Env keys: NEWSLETTER_RATE_LIMIT_PER_MINUTE, _PER_HOUR, _PER_DAY
    |
    */

    'rate_limits' => [
        'per_minute' => (int) env('NEWSLETTER_RATE_LIMIT_PER_MINUTE', 0),
        'per_hour' => (int) env('NEWSLETTER_RATE_LIMIT_PER_HOUR', 0),
        'per_day' => (int) env('NEWSLETTER_RATE_LIMIT_PER_DAY', 0),
    ],

];

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

];

<?php

return [

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

    'hsm_attendance' => [
        'enabled' => (bool) env('HSM_ATTENDANCE_ENABLED', true),
        'connection' => env('HSM_ATTENDANCE_DB_CONNECTION', 'hsm_attendance'),
        'portal_url' => rtrim((string) env('HSM_ATTENDANCE_PORTAL_URL', 'https://tikismd.id'), '/'),
        'storage_path' => rtrim((string) env('HSM_ATTENDANCE_STORAGE_PATH', '/home/u432375434/domains/tikismd.id/storage/private'), '/'),
    ],

];

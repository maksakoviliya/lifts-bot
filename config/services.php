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

    'worldweatheronline' => [
        'url' => env('WORLDWEATHERONONLINE_API_URL'),
        'key' => env('WORLDWEATHERONONLINE_API_KEY'),
        'latlng' => env('WORLDWEATHERONONLINE_LAT_LNG'),
    ],

    'telegram' => [
        'required_channel' => env('TELEGRAM_REQUIRED_CHANNEL', '@sheregeshafisha'),
        'channel_url' =>  env('TELEGRAM_CHANNEL_URL', 'https://t.me/sheregeshafisha')
    ]
];

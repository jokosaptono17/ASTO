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

    // Asisten Mentari — kontrak sama dengan MekanikPintar (docs/mentari.md)
    'mentari' => [
        'endpoint' => env('MENTARI_ENDPOINT'),
        'token' => env('MENTARI_TOKEN'),
        'timeout' => (int) env('MENTARI_TIMEOUT', 25),
    ],

    // "Masuk dengan AstoOne" untuk MekanikPintar. Rahasia sama persis dengan ASTOONE_SSO_SECRET di .env LMS.
    'mekanikpintar' => [
        'secret' => env('MEKANIKPINTAR_SSO_SECRET'),
        'callback' => env('MEKANIKPINTAR_SSO_CALLBACK', 'https://mekanikpintar.com/login/astoone/kembali'),
    ],

];

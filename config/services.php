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

        // AI gratis (kuota free tier). Dicoba berurutan; kuota habis / gangguan → penyedia berikutnya → otak bawaan.
        // "personal" = boleh menerima data karyawan (penyedia tidak memakai data untuk melatih model).
        'ai' => [
            'order' => env('MENTARI_AI_ORDER', 'groq,gemini,openrouter'),
            'timeout' => (int) env('MENTARI_AI_TIMEOUT', 20),
            'per_user_daily' => (int) env('MENTARI_AI_PER_USER', 25),
            'providers' => [
                'groq' => [
                    'label' => 'Groq', 'base' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'), 'key' => env('GROQ_API_KEY'),
                    'model' => env('GROQ_MODEL', 'openai/gpt-oss-120b'), 'daily' => (int) env('GROQ_DAILY_LIMIT', 900),
                    'personal' => true, 'reasoning' => 'low',
                ],
                'gemini' => [
                    'label' => 'Gemini', 'base' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta/openai'), 'key' => env('GEMINI_API_KEY'),
                    'model' => env('GEMINI_MODEL', 'gemini-flash-latest'), 'daily' => (int) env('GEMINI_DAILY_LIMIT', 200),
                    'personal' => false, 'reasoning' => 'low',
                ],
                'openrouter' => [
                    'label' => 'OpenRouter', 'base' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'), 'key' => env('OPENROUTER_API_KEY'),
                    'model' => env('OPENROUTER_MODEL', 'openrouter/free'), 'daily' => (int) env('OPENROUTER_DAILY_LIMIT', 45),
                    'personal' => false, 'reasoning' => null,
                ],
            ],
        ],
    ],

    // "Masuk dengan AstoOne" untuk MekanikPintar. Rahasia sama persis dengan ASTOONE_SSO_SECRET di .env LMS.
    'mekanikpintar' => [
        'secret' => env('MEKANIKPINTAR_SSO_SECRET'),
        'callback' => env('MEKANIKPINTAR_SSO_CALLBACK', 'https://mekanikpintar.com/login/astoone/kembali'),
    ],

];

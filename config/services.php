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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'brevo' => [
        'api_key' => env('BREVO_API_KEY'),
    ],

    // AI chat. Read through config() rather than env() so these survive
    // `php artisan config:cache`, which makes env() return null in production.
    'openrouter' => [
        'key' => env('OPENROUTER_API_KEY', env('HF_TOKEN', env('GROQ_API_KEY'))),
        'url' => env('OPENROUTER_URL', 'https://openrouter.ai/api/v1/chat/completions'),
        'model' => env('OPENROUTER_DEFAULT_MODEL', 'deepseek/deepseek-chat-v3.1'),
    ],

];

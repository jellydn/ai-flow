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

    'github' => ['token' => env('GITHUB_TOKEN')],
    'openai' => [
        'key' => env('OPENAI_API_KEY') ?: env('OPENROUTER_API_KEY'),
        'base_url' => env('AI_BASE_URL', 'https://api.openai.com/v1'),
        'model' => env('AI_MODEL') ?: env('OPENAI_MODEL', 'gpt-4o-mini'),
        'timeout' => env('OPENAI_TIMEOUT', 30),
        'providers' => ['openai', 'openrouter', 'anthropic', 'gemini'],
        'openai_base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'openrouter_key' => env('OPENROUTER_API_KEY'),
        'openrouter_base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
        'openrouter_model' => env('OPENROUTER_MODEL', env('AI_MODEL', 'openrouter/free')),
        'referer' => env('AI_SITE_URL', env('APP_URL')),
    ],

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-20250514'),
    ],

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
    ],

];

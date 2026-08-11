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

    'order_ai' => [
        'api_key' => env('ORDER_AI_API_KEY'),
        'base_url' => env('ORDER_AI_BASE_URL', 'https://api.openai.com/v1'),
        'model' => env('ORDER_AI_MODEL', 'gpt-4.1-mini'),
        'timeout' => (int) env('ORDER_AI_TIMEOUT', 60),
        'verify_ssl' => filter_var(env('ORDER_AI_VERIFY_SSL', true), FILTER_VALIDATE_BOOL),
    ],

];

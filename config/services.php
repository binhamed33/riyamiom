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

    'discord' => [
        'log_webhook' => env('DISCORD_LOG_WEBHOOK'),
        'monitor_webhook' => env('DISCORD_MONITOR_WEBHOOK'),
        'webhook_url' => env('DISCORD_WEBHOOK_URL', ''),
        'bot_token' => env('DISCORD_BOT_TOKEN', ''),
        'channel_id' => env('DISCORD_CHANNEL_ID', ''),
        'staff_webhook' => env('DISCORD_STAFF_WEBHOOK', ''),
        'staff_events_webhook' => env('DISCORD_STAFF_EVENTS_WEBHOOK', ''),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-flash-latest'),
    ],

    'whatsapp' => [
        'url' => env('WHATSAPP_API_URL', ''),
        'token' => env('WHATSAPP_API_TOKEN', ''),
        'meta_token' => env('WHATSAPP_META_TOKEN', ''),
        'meta_phone_id' => env('WHATSAPP_META_PHONE_ID', ''),
        'template' => env('WHATSAPP_TEMPLATE_NAME', ''),
    ],

];

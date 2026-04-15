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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'microsoft_graph' => [
        'tenant_id'     => env('MS_TENANT_ID'),
        'client_id'     => env('MS_CLIENT_ID'),
        'client_secret' => env('MS_CLIENT_SECRET'),
        'sender_email'  => env('MS_SENDER_EMAIL'),
        'base_url'      => env('GRAPH_BASE_URL', 'https://graph.microsoft.com/v1.0'),
    ],

    'jarvies' => [
        'url'     => env('JARVIES_URL', 'http://127.0.0.1:8001'),
        'api_key' => env('JARVIES_API_KEY'),
    ],

    'external_ticket' => [
        'api_key' => env('EXTERNAL_TICKET_API_KEY'),
    ],

];

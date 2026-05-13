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
        'tenant_id'             => env('MS_TENANT_ID'),
        'client_id'             => env('MS_CLIENT_ID'),
        'client_secret'         => env('MS_CLIENT_SECRET'),
        'sender_email'          => env('MS_SENDER_EMAIL'),
        'base_url'              => env('GRAPH_BASE_URL', 'https://graph.microsoft.com/v1.0'),
        'ticket_parent_folder'            => env('ONEDRIVE_TICKET_PARENT_FOLDER', 'TICKETING'),
        'customer_deliverable_path'       => env('ONEDRIVE_CUSTOMER_DELIVERABLE_PATH', 'DELIVERY SUPPORT/CUSTOMER DELIVERABLE'),
    ],

    // Set JARVIES_URL in .env to the public JARVIES base URL in production
    // (e.g. https://jarvies.example.com). Localhost fallback is dev-only.
    'jarvies' => [
        'url'     => env('JARVIES_URL', ''),
        'api_key' => env('JARVIES_API_KEY'),
    ],

    'external_ticket' => [
        'api_key' => env('EXTERNAL_TICKET_API_KEY'),
    ],

];

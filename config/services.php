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
        'sharepoint_site_id'    => env('SHAREPOINT_SITE_ID'),
        'base_url'              => env('GRAPH_BASE_URL', 'https://graph.microsoft.com/v1.0'),
        'ticket_parent_folder'            => env('ONEDRIVE_TICKET_PARENT_FOLDER', 'TICKETING'),
        'customer_deliverable_path'       => env('ONEDRIVE_CUSTOMER_DELIVERABLE_PATH', 'DELIVERY SUPPORT/CUSTOMER DELIVERABLE'),
    ],

    'jarvies' => [
        'url'        => env('JARVIES_URL', ''),        // internal Docker URL (server-to-server API)
        'public_url' => env('JARVIES_PUBLIC_URL', ''), // public URL (browser redirect)
        'api_key'    => env('JARVIES_API_KEY'),
    ],

    'external_ticket' => [
        'api_key' => env('EXTERNAL_TICKET_API_KEY'),
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        // Skill ID Agent Skills custom "sap-ticket-analyzer" (dibuat di Anthropic
        // Console) — dipakai AiTicketAnalyzerService untuk fitur Analisa AI di
        // validasi Staging Ticket.
        'ticket_analyzer_skill_id' => env('ANTHROPIC_TICKET_ANALYZER_SKILL_ID'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
    ],

    'ai' => [
        /*
         * Retensi arsip percakapan AI (tabel ai_conversations), dalam hari,
         * dihitung dari pesan terakhir. 0 = simpan selamanya.
         *
         * Angkanya dibuat konfigurasi karena ini keputusan kebijakan, bukan
         * teknis: isinya bisa memuat tangkapan layar sistem customer, dan
         * seberapa lama itu layak disimpan bisa berubah tanpa perlu ubah kode.
         */
        'retention_days' => (int) env('AI_HISTORY_RETENTION_DAYS', 90),
    ],

];

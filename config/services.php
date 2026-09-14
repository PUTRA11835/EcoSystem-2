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

        // Skill ID Agent Skills custom "laravel-word-report-generator" — di-upload
        // lewat `php artisan claude:upload-skill` (lihat .claude/skills/laravel-word-report-generator/SKILL.md).
        // Dipakai ClaudeReportService untuk fitur generate laporan .docx/.pdf dari template.
        'word_report_skill_id' => env('ANTHROPIC_WORD_REPORT_SKILL_ID'),

        // Model dipakai ClaudeReportService — bisa dioverride tanpa deploy kalau perlu.
        'word_report_model' => env('ANTHROPIC_WORD_REPORT_MODEL', 'claude-opus-5'),
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

    /*
    |--------------------------------------------------------------------------
    | Power Automate (otomasi Microsoft Teams)
    |--------------------------------------------------------------------------
    |
    | EcoSystem yang MEMANGGIL Power Automate, bukan sebaliknya: tiap flow dibuat
    | dengan trigger "When an HTTP request is received" (konektor standard, tidak
    | butuh lisensi premium), lalu URL trigger-nya ditempel ke env di bawah.
    |
    | Setiap request membawa header X-EcoSystem-Secret berisi POWER_AUTOMATE_SECRET
    | supaya flow bisa menolak panggilan dari pihak lain. URL trigger Power Automate
    | memang sudah mengandung tanda tangan SAS, tetapi siapa pun yang pernah melihat
    | URL itu bisa memanggilnya lagi — secret ini lapis kedua.
    |
    | Flow yang URL-nya dikosongkan otomatis dilewati (fitur mati untuk flow itu
    | saja), sehingga integrasi bisa dinyalakan bertahap tanpa ubah kode.
    |
    */
    'power_automate' => [
        'enabled' => (bool) env('POWER_AUTOMATE_ENABLED', false),
        'secret'  => env('POWER_AUTOMATE_SECRET'),
        'timeout' => (int) env('POWER_AUTOMATE_TIMEOUT', 10),

        // Email yang SELALU ikut dimasukkan ke group chat tiket (pisah koma).
        // Dua gunanya: (1) memastikan chat selalu berisi >= 3 orang sehingga
        // Teams mengizinkan pemberian nama grup, (2) tetap ada penerima saat
        // modul tiket belum punya Module Lead.
        'teams_extra_members' => env('POWER_AUTOMATE_TEAMS_EXTRA_MEMBERS'),

        // Ikutkan email validator sebagai anggota group chat tiket. Default
        // mati: akun validator bisa akun sistem yang bukan mailbox M365.
        'teams_include_validator' => (bool) env('POWER_AUTOMATE_TEAMS_INCLUDE_VALIDATOR', true),

        // Alamat yang TIDAK PERNAH boleh masuk Teams (pisah koma): akun sistem
        // yang punya email di database tapi tidak punya mailbox Microsoft 365.
        'teams_exclude_members' => env('POWER_AUTOMATE_TEAMS_EXCLUDE_MEMBERS'),

        // role_id yang pemegangnya SELALU ditarik ke channel tiket baru (pisah
        // koma), di samping Module Lead. Default 5 = Delivery Support Head,
        // 6 = Delivery Support Service Helpdesk. Keanggotaan role dibaca dari
        // tabel pivot employee_role_assignment, bukan kolom di `employee`.
        'teams_member_role_ids' => env('POWER_AUTOMATE_TEAMS_MEMBER_ROLE_IDS', '5,6'),

        'flows' => [
            // Email customer baru masuk -> Power Automate balas greeting otomatis.
            'email_received'       => env('POWER_AUTOMATE_FLOW_EMAIL_RECEIVED'),
            // Staging ticket di-approve -> kartu ke channel Teams + chat lead modul.
            'ticket_validated'     => env('POWER_AUTOMATE_FLOW_TICKET_VALIDATED'),
            // Tiket masih berstatus open -> reminder berulang ke lead modul.
            'ticket_open_reminder' => env('POWER_AUTOMATE_FLOW_TICKET_OPEN_REMINDER'),
            // Consultant di-assign ke tiket -> ditambahkan ke channel tiket itu.
            'ticket_member_added'  => env('POWER_AUTOMATE_FLOW_TICKET_MEMBER_ADDED'),
        ],

        'reminder' => [
            // Jarak antar reminder untuk satu tiket yang sama (menit).
            'interval_minutes'   => (int) env('POWER_AUTOMATE_REMINDER_INTERVAL_MINUTES', 1),

            // Batas jumlah reminder per tiket. 0 = tanpa batas (kirim terus selama open).
            'max_count'          => (int) env('POWER_AUTOMATE_REMINDER_MAX_COUNT', 0),

            // Banyak tiket yang diproses per satu kali scheduler jalan — pagar ledakan
            // supaya backlog tiket open lama tidak mengirim ratusan pesan Teams
            // sekaligus pada menit pertama fitur dinyalakan.
            'batch_limit'        => (int) env('POWER_AUTOMATE_REMINDER_BATCH_LIMIT', 50),

            // Hanya tiket yang dibuat pada/setelah waktu ini yang direminder. Isi
            // dengan waktu go-live fitur (format "2026-09-02 08:00:00") agar tiket
            // open lama tidak ikut dibombardir. Kosong = semua tiket open.
            'since'              => env('POWER_AUTOMATE_REMINDER_SINCE'),

            // true = berhenti mengingatkan begitu tiket sudah punya PIC atau member,
            // walaupun statusnya masih open.
            'stop_when_assigned' => (bool) env('POWER_AUTOMATE_REMINDER_STOP_WHEN_ASSIGNED', false),
        ],
    ],

];

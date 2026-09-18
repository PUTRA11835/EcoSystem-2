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

    /*
    |--------------------------------------------------------------------------
    | Microsoft Graph — chat Teams (sinkron chat <-> internal note)
    |--------------------------------------------------------------------------
    |
    | Kredensialnya SENGAJA jatuh balik ke app registration email (MS_*), sesuai
    | keputusan 17 Sep 2026: dua app berarti dua client secret dengan dua tanggal
    | kedaluwarsa, dan secret kedaluwarsa adalah penyebab kegagalan integrasi yang
    | paling sering. Isi MS_TEAMS_* hanya kalau Teams nanti dipindah ke app sendiri
    | — pemisahannya cukup lewat .env, tanpa ubah kode.
    |
    | Rincian: docs/teams-chat-sync-design.md §2.
    |
    | Ditulis dengan `?:`, BUKAN sebagai argumen default `env($k, $fallback)`.
    | Bedanya menentukan: variabel yang ADA TAPI KOSONG di .env (`MS_TEAMS_TENANT_ID=`)
    | membuat env() mengembalikan string kosong, bukan null — sehingga nilai default
    | tidak pernah dipakai dan integrasi Teams mati diam-diam. Padahal "ada tapi
    | kosong" justru keadaan yang kita anjurkan di .env.example.
    |
    */
    'microsoft_graph_teams' => [
        'tenant_id'     => env('MS_TEAMS_TENANT_ID') ?: env('MS_TENANT_ID'),
        'client_id'     => env('MS_TEAMS_CLIENT_ID') ?: env('MS_CLIENT_ID'),
        'client_secret' => env('MS_TEAMS_CLIENT_SECRET') ?: env('MS_CLIENT_SECRET'),
        'base_url'      => env('GRAPH_BASE_URL', 'https://graph.microsoft.com/v1.0'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sinkronisasi chat Teams <-> internal note
    |--------------------------------------------------------------------------
    |
    | Semua default MATI. Dua arahnya bisa dinyalakan terpisah: kalau ada yang
    | aneh, matikan satu arah tanpa mengganggu yang lain, dan mematikan keduanya
    | mengembalikan sistem ke keadaan sebelum fitur ini ada — tanpa deploy ulang.
    |
    */
    'teams_sync' => [
        'enabled'  => (bool) env('TEAMS_SYNC_ENABLED', false),
        'inbound'  => (bool) env('TEAMS_SYNC_INBOUND', false),   // Teams -> EcoSystem
        'outbound' => (bool) env('TEAMS_SYNC_OUTBOUND', false),  // EcoSystem -> Teams

        // Pagar ledakan: maksimal pesan yang diserap per chat per putaran polling.
        // Menahan chat yang lama tidak tersinkron agar tidak membanjiri thread
        // tiket dalam satu menit.
        'max_per_poll' => (int) env('TEAMS_SYNC_MAX_PER_POLL', 20),

        // Jumlah chat yang dipoll per putaran.
        'chat_batch' => (int) env('TEAMS_SYNC_CHAT_BATCH', 50),

        // Polling gagal beruntun sebanyak ini -> sync_enabled chat itu dimatikan
        // sendiri dan dicatat Log::warning. Tanpa ambang, chat yang chat_id-nya
        // sudah tidak valid akan dipanggil selamanya tiap menit.
        'max_poll_failures' => (int) env('TEAMS_SYNC_MAX_POLL_FAILURES', 10),

        'timeout' => (int) env('TEAMS_SYNC_TIMEOUT', 15),

        // Akun pemilik connection Teams di Power Automate. Dua perannya, dan
        // keduanya muncul BERSAMAAN begitu chat dibuat lewat Graph:
        //
        //  1. WAJIB jadi anggota tiap group chat buatan Graph. Waktu konektor
        //     Teams yang membuat grup, Teams menambahkan pemilik koneksi sendiri
        //     — itu sebabnya alamat ini justru ditaruh di _EXCLUDE_MEMBERS dulu
        //     (kalau ikut lagi, jadi "Duplicate chat members"). Graph TIDAK
        //     menambahkan siapa pun otomatis, jadi tanpa baris ini flow 5 dan
        //     flow 7 gagal memposting: akun koneksinya bukan anggota grup.
        //  2. Pesannya DIABAIKAN saat sinkron masuk. Semua yang diposting flow
        //     (kartu tiket, mention lead, dan internal note yang baru saja kita
        //     kirim sendiri) tiba atas nama akun ini; tanpa aturan ini, tiap
        //     internal note yang dikirim ke Teams akan terbaca balik sebagai
        //     pesan baru dan tersimpan dobel di tiketnya.
        //
        // Konsekuensi yang diterima: kalau ada MANUSIA yang mengetik di group
        // chat tiket memakai akun ini, pesannya tidak ikut tersinkron. Akun ini
        // akun layanan, jadi itu pertukaran yang wajar.
        // `?:` bukan argumen default env() — lihat catatan di microsoft_graph_teams:
        // variabel yang ada tapi kosong mengembalikan '' dan membuat nilai
        // cadangannya tidak pernah terpakai.
        'connection_email' => env('POWER_AUTOMATE_TEAMS_CONNECTION_OWNER') ?: env('MS_SENDER_EMAIL'),

        // Batas ukuran satu gambar Teams yang diunduh ke storage tiket (MB).
        // Gambar melebihi ini tidak diunduh; pesannya tetap masuk dengan penanda
        // bahwa gambarnya ada di Teams. Menjaga disk tiket dari kiriman video/
        // screenshot raksasa yang tidak ada hubungannya dengan tiket.
        'max_image_mb' => (int) env('TEAMS_SYNC_MAX_IMAGE_MB', 10),

        // Batas ukuran berkas SharePoint yang diproksi AttachmentController.
        // Graph mengembalikan isinya sekaligus, jadi berkas besar dimuat penuh ke
        // memori PHP hanya untuk diteruskan. Yang melebihi batas dialihkan ke
        // SharePoint-nya (perlu login Microsoft, tapi jalan).
        'max_proxy_file_mb' => (int) env('TEAMS_SYNC_MAX_PROXY_FILE_MB', 25),

        // Antrean keluar.
        'outbox_batch'        => (int) env('TEAMS_SYNC_OUTBOX_BATCH', 25),
        'outbox_max_attempts' => (int) env('TEAMS_SYNC_OUTBOX_MAX_ATTEMPTS', 5),
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

        // PAGAR STAGING. Email pelapor yang boleh memicu Power Automate (pisah
        // koma). KOSONG = tanpa batas, dan itu keadaan PRODUKSI — default-nya
        // sengaja longgar supaya lupa mengisi tidak pernah membisukan notifikasi
        // customer sungguhan.
        //
        // Diisi hanya di server dev/staging, yang membaca mailbox
        // support@eclectic.co.id yang SAMA dengan produksi: tanpa pagar ini,
        // email customer sungguhan yang kebetulan masuk saat demo akan memicu
        // group chat Teams dan notifikasi ke employee sungguhan, dari server yang
        // datanya belum tentu benar.
        'allowed_submitters' => env('POWER_AUTOMATE_ALLOWED_SUBMITTERS'),

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

        // role_id yang pemegangnya SELALU ikut jadi peserta group chat tiket
        // baru (pisah koma), di samping Module Lead. Default 5 = Delivery
        // Support Head,
        // 6 = Delivery Support Service Helpdesk. Keanggotaan role dibaca dari
        // tabel pivot employee_role_assignment, bukan kolom di `employee`.
        'teams_member_role_ids' => env('POWER_AUTOMATE_TEAMS_MEMBER_ROLE_IDS', '5,6'),

        // Ikutkan tim Delivery Support tiket (Delivery Owner, Support Manager,
        // CO PM, Support Admin) sebagai peserta group chat. Orangnya diambil
        // dari delivery support yang dipilih helpdesk saat validasi, jadi
        // berbeda per tiket — tidak seperti teams_member_role_ids di atas.
        'teams_include_support_team' => (bool) env('POWER_AUTOMATE_TEAMS_INCLUDE_SUPPORT_TEAM', true),

        // Batas peserta satu group chat (batas konektor Teams = 20). Kelebihan
        // peserta membuat aksi "Create a chat" menolak SELURUH permintaan, jadi
        // daftar dipotong lebih dulu di sisi EcoSystem: yang dibuang adalah
        // anggota tetap dan role penjaga, bukan lead modul / tim Delivery
        // Support. Turunkan angkanya kalau ingin menyisakan slot lebih banyak
        // untuk consultant yang ditambahkan flow 6. 0 = tanpa batas.
        'teams_max_members' => (int) env('POWER_AUTOMATE_TEAMS_MAX_MEMBERS', 20),

        'flows' => [
            // Email customer baru masuk -> Power Automate balas greeting otomatis.
            'email_received'       => env('POWER_AUTOMATE_FLOW_EMAIL_RECEIVED'),
            // Staging ticket di-approve -> group chat Teams per tiket + kartu.
            'ticket_validated'     => env('POWER_AUTOMATE_FLOW_TICKET_VALIDATED'),
            // Tiket masih berstatus open -> reminder berulang ke lead modul.
            'ticket_open_reminder' => env('POWER_AUTOMATE_FLOW_TICKET_OPEN_REMINDER'),
            // Consultant di-assign ke tiket -> ditambahkan ke group chat tiket itu.
            'ticket_member_added'  => env('POWER_AUTOMATE_FLOW_TICKET_MEMBER_ADDED'),

            // Internal note EcoSystem -> pesan di group chat tiket (flow 7).
            // Ini satu-satunya jalan keluar: POST /chats/{id}/messages TIDAK bisa
            // app-only (hanya Teamwork.Migrate.All, untuk migrasi), jadi arah
            // keluar wajib lewat Power Automate. Lihat design §3.
            'teams_post_message'   => env('POWER_AUTOMATE_FLOW_TEAMS_POST_MESSAGE'),
        ],

        'reminder' => [
            // Jarak antar reminder untuk satu tiket yang sama (menit).
            // Jarak minimum antar reminder UNTUK TIKET YANG SAMA, dalam menit.
            // Default 60 (bukan 1): scheduler memanggil command ini tiap menit,
            // jadi nilai 1 berarti tiap tiket open mengirim kartu Teams setiap
            // menit — banjir notifikasi yang membuat orang mematikan seluruh
            // integrasinya. Diturunkan hanya untuk pengujian.
            'interval_minutes'   => (int) env('POWER_AUTOMATE_REMINDER_INTERVAL_MINUTES', 60),

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

            // ticket_type yang boleh diingatkan (pisah koma). Kosong = semua tipe,
            // yaitu perilaku sebelum knob ini ada. Nilai yang sah: Incident,
            // Change Request, Service Request, EWA, RISE, Consult, Internal.
            // Gunanya menyaring tipe yang memang tidak menuntut lead segera
            // meng-assign orang (EWA laporan berkala, Consult konsultatif,
            // Internal tanpa customer yang menunggu) - menagihnya berulang cuma
            // membuat lead kebal terhadap notifikasi yang sungguhan mendesak.
            'types'              => env('POWER_AUTOMATE_REMINDER_TYPES'),

            // true = berhenti mengingatkan begitu tiket sudah punya PIC atau member,
            // walaupun statusnya masih open.
            'stop_when_assigned' => (bool) env('POWER_AUTOMATE_REMINDER_STOP_WHEN_ASSIGNED', false),
        ],
    ],

];

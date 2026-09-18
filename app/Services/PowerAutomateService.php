<?php

namespace App\Services;

use App\Models\StagingTicket;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Jembatan keluar EcoSystem -> Power Automate.
 *
 * Arah panggilannya sengaja satu arah (EcoSystem yang POST ke Power Automate):
 * trigger "When an HTTP request is received" adalah konektor STANDARD, sedangkan
 * arah sebaliknya (Power Automate menarik data lewat action HTTP) butuh lisensi
 * PREMIUM. Selain itu EcoSystem-lah yang tahu kejadian bisnisnya (email baru yang
 * sudah lolos filter NDR, staging yang di-approve, tiket yang masih open), jadi
 * flow tidak perlu menebak lewat polling.
 *
 * Semua kegagalan dicatat ke log lalu DITELAN — integrasi notifikasi tidak boleh
 * menggagalkan approve tiket atau pemrosesan inbox.
 */
class PowerAutomateService
{
    public const FLOW_EMAIL_RECEIVED       = 'email_received';
    public const FLOW_TICKET_VALIDATED     = 'ticket_validated';
    public const FLOW_TICKET_OPEN_REMINDER = 'ticket_open_reminder';
    public const FLOW_TICKET_MEMBER_ADDED  = 'ticket_member_added';
    public const FLOW_TEAMS_POST_MESSAGE   = 'teams_post_message';

    // ─── Konfigurasi ─────────────────────────────────────────────────────────

    public function isEnabled(): bool
    {
        return (bool) config('services.power_automate.enabled');
    }

    /** URL trigger flow, atau null kalau flow itu belum/tidak dikonfigurasi. */
    public function flowUrl(string $flow): ?string
    {
        $url = trim((string) config("services.power_automate.flows.{$flow}"));

        return $url !== '' ? $url : null;
    }

    /** Flow siap dipakai (integrasi aktif DAN URL flow terisi). */
    public function isFlowReady(string $flow): bool
    {
        return $this->isEnabled() && $this->flowUrl($flow) !== null;
    }

    // ─── Pagar staging ───────────────────────────────────────────────────────

    /**
     * Daftar email pelapor yang BOLEH memicu Power Automate (pisah koma).
     *
     * KOSONG = tanpa batas; itu keadaan produksi, dan sengaja jadi default supaya
     * lupa mengisinya tidak pernah membisukan notifikasi customer sungguhan.
     *
     * Diisi HANYA di server dev/staging. Masalah yang dipecahkannya: staging
     * membaca mailbox support@eclectic.co.id yang SAMA dengan produksi, jadi
     * tanpa pagar ini email customer sungguhan yang masuk saat demo akan memicu
     * group chat Teams dan notifikasi ke employee sungguhan — dari server yang
     * datanya belum tentu benar.
     *
     * @return list<string> huruf kecil semua
     */
    public function allowedSubmitters(): array
    {
        $raw = trim((string) config('services.power_automate.allowed_submitters'));

        if ($raw === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($e) => mb_strtolower(trim($e)),
            explode(',', $raw)
        )));
    }

    /**
     * Email pelapor di dalam payload, dari dua bentuk yang dipakai semua flow:
     * tiket (`ticket.submitted_by.email`) dan email masuk (`sender_email`).
     *
     * Bentuk tiket juga dicek di akar payload karena sebagian pemanggil mengirim
     * ticketPayload() apa adanya, tanpa membungkusnya dalam kunci 'ticket'.
     */
    private function submitterEmail(array $payload): ?string
    {
        $candidates = [
            $payload['ticket']['submitted_by']['email'] ?? null,
            $payload['submitted_by']['email'] ?? null,
            $payload['sender_email'] ?? null,
            $payload['staging']['sender_email'] ?? null,
        ];

        foreach ($candidates as $email) {
            if (is_string($email) && trim($email) !== '') {
                return mb_strtolower(trim($email));
            }
        }

        return null;
    }

    /**
     * Boleh berangkat? Selalu true di produksi (daftar kosong).
     *
     * Saat daftarnya terisi, payload yang email pelapornya TIDAK DIKENALI ikut
     * DIHADANG — bukan diloloskan. Pagar ini memang dipasang untuk mencegah
     * kebocoran ke orang sungguhan, dan meloloskan yang tak teridentifikasi akan
     * membolongi tepat pada kasus yang paling tidak kita pahami.
     */
    private function passesSubmitterGate(string $flow, array $payload): bool
    {
        $allowed = $this->allowedSubmitters();

        if ($allowed === []) {
            return true;
        }

        $email = $this->submitterEmail($payload);

        if ($email !== null && in_array($email, $allowed, true)) {
            return true;
        }

        Log::info('PowerAutomate: flow DIHADANG pagar staging', [
            'flow'      => $flow,
            'submitter' => $email ?? '(tidak teridentifikasi di payload)',
            'ticket'    => $payload['ticket']['number'] ?? null,
            'allowed'   => $allowed,
        ]);

        return false;
    }

    // ─── Pengiriman ──────────────────────────────────────────────────────────

    /**
     * POST payload JSON ke satu flow.
     *
     * @return bool true kalau flow menerima (2xx); false untuk semua kondisi
     *              lain, termasuk saat flow itu memang sengaja dimatikan.
     */
    public function dispatch(string $flow, array $payload): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $url = $this->flowUrl($flow);
        if (!$url) {
            Log::debug('PowerAutomate: flow dilewati karena URL kosong', ['flow' => $flow]);
            return false;
        }

        // Pagar staging. Ditaruh di sini — satu-satunya pintu keluar ke Power
        // Automate — supaya SETIAP flow ikut terjaga, termasuk flow yang belum
        // ditulis. Menaruhnya di tiap pemanggil berarti flow berikutnya harus
        // ingat memasangnya sendiri, dan yang lupa baru ketahuan setelah bocor.
        if (!$this->passesSubmitterGate($flow, $payload)) {
            return false;
        }

        $payload = array_merge([
            'event'   => $flow,
            'sent_at' => now()->toIso8601String(),
            'source'  => config('app.name', 'EcoSystem'),
        ], $payload);

        try {
            // retry 2x: Power Automate kadang membalas 429 saat tenant di-throttle.
            // throw:false supaya kegagalan tetap berupa response, bukan exception
            // yang menyelinap ke pemanggil.
            $response = Http::withHeaders([
                    'Content-Type'       => 'application/json',
                    'X-EcoSystem-Secret' => (string) config('services.power_automate.secret'),
                ])
                ->timeout((int) config('services.power_automate.timeout', 10))
                ->retry(2, 500, throw: false)
                ->post($url, $payload);

            if ($response->successful()) {
                // Dicatat juga saat BERHASIL: Power Automate membalas 202 sebelum
                // flow-nya jalan, jadi tanpa baris ini tidak ada cara membedakan
                // "panggilan tidak pernah berangkat" dari "flow gagal di dalam".
                Log::info('PowerAutomate: flow dipanggil', [
                    'flow'   => $flow,
                    'status' => $response->status(),
                    'ticket' => $payload['ticket']['number'] ?? null,
                    'chat'   => $payload['chat']['topic'] ?? null,
                ]);

                return true;
            }

            Log::warning('PowerAutomate: flow menolak request', [
                'flow'   => $flow,
                'status' => $response->status(),
                'body'   => mb_substr($response->body(), 0, 500),
            ]);
        } catch (\Throwable $e) {
            Log::error('PowerAutomate: gagal memanggil flow', [
                'flow'  => $flow,
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }

    /**
     * Kirim setelah response HTTP selesai supaya user tidak ikut menunggu Power
     * Automate. Di konteks console (scheduler) callback ini baru jalan saat
     * command selesai, jadi pemanggil dari command pakai {@see dispatch()}.
     */
    public function dispatchAfterResponse(string $flow, array $payload): void
    {
        if (!$this->isFlowReady($flow)) {
            return;
        }

        app()->terminating(function () use ($flow, $payload) {
            $this->dispatch($flow, $payload);
        });
    }

    // ─── Pembangun payload ───────────────────────────────────────────────────

    /**
     * Bentuk baku satu tiket untuk semua flow. Field sengaja DATAR dan sudah
     * berupa teks siap tampil (bukan ID mentah), karena di Power Automate setiap
     * transformasi harus ditulis sebagai ekspresi manual yang rapuh.
     */
    public function ticketPayload(Ticket $ticket): array
    {
        $pic     = $this->employeeName($ticket->ticket_lead_id ? (int) $ticket->ticket_lead_id : null) ?? $ticket->pic;
        $members = $this->ticketMemberNames((int) $ticket->ticket_id);

        return [
            'id'           => (int) $ticket->ticket_id,
            'number'       => $ticket->ticket_number,
            'subject'      => $ticket->description,
            'status'       => $ticket->status,
            'status_label' => $ticket->status_label,
            'priority'     => $ticket->ticket_priority,
            'type'         => $ticket->ticket_type,
            'scale'        => $ticket->scale,
            'channel'      => $ticket->channel,
            'customer'     => $this->customerName($ticket->customer_id),
            'end_customer' => $this->customerName($ticket->end_customer_id),
            'module'       => $ticket->module,
            'module_id'    => $ticket->module_id ? (int) $ticket->module_id : null,
            'submitted_by' => [
                'name'  => $ticket->submitted_by_name ?? $ticket->name,
                'email' => $ticket->submitted_by_email,
                'phone' => $ticket->no_hp,
            ],
            'pic'          => $pic,
            'members'      => $members,
            'is_assigned'  => $pic !== null || $members !== [],
            'created_at'   => optional($ticket->created_at)->toIso8601String(),
            'age_minutes'  => $ticket->created_at ? (int) $ticket->created_at->diffInMinutes(now()) : 0,
            'url'          => $this->ticketUrl((int) $ticket->ticket_id),
        ];
    }

    /** Bentuk baku satu staging ticket — dipakai flow greeting email. */
    public function stagingPayload(StagingTicket $staging): array
    {
        return [
            'id'                  => (int) $staging->id,
            'subject'             => $staging->description,
            'status'              => $staging->status,
            'channel'             => $staging->channel,
            'customer'            => $this->customerName($staging->customer_id),
            'customer_id'         => $staging->customer_id ? (int) $staging->customer_id : null,
            'sender_name'         => $staging->sender_name,
            'sender_email'        => $staging->submitted_by_email,
            'cc_emails'           => $staging->cc_emails ?: [],
            // ID pesan di mailbox Graph — dipakai action "Reply to email" di Power
            // Automate supaya greeting menyambung ke thread yang sama, bukan email
            // baru yang nanti dibaca EcoSystem sebagai tiket lain.
            'graph_message_id'    => $staging->graph_message_id,
            'internet_message_id' => $staging->email_message_id,
            'conversation_id'     => $staging->email_thread_id,
            'has_attachments'     => (bool) $staging->has_attachments,
            'received_at'         => optional($staging->created_at)->toIso8601String(),
        ];
    }

    /**
     * Lead modul penerima notifikasi, lengkap dengan email kerja (@eclectic.co.id)
     * yang dipakai konektor Teams untuk menemukan orangnya.
     *
     * Tiket menyimpan module_id DAN nama modul sebagai teks; keduanya dicoba
     * karena tiket lama (dan tiket dari form Jarvies) bisa hanya punya namanya.
     *
     * @return list<array{employee_id:int,name:?string,email:?string}>
     */
    public function moduleLeads(?int $moduleId, ?string $moduleName = null): array
    {
        if (!$moduleId && $moduleName) {
            $moduleId = DB::table('modules')
                ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($moduleName))])
                ->value('id');
        }

        if (!$moduleId) {
            return [];
        }

        $employeeIds = DB::table('module_leads')
            ->where('module_id', $moduleId)
            ->pluck('employee_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($employeeIds === []) {
            return [];
        }

        $names  = $this->employeeNames($employeeIds);
        $emails = $this->workEmails($employeeIds);

        $leads = [];
        foreach ($employeeIds as $id) {
            $leads[] = [
                'employee_id' => $id,
                'name'        => $names[$id] ?? null,
                'email'       => $emails[$id] ?? null,
            ];
        }

        return $leads;
    }

    /**
     * Email lead yang bisa langsung dipakai konektor Teams. Lead tanpa email
     * kerja dibuang di sini supaya flow tidak berhenti di tengah jalan karena
     * penerimanya kosong.
     *
     * @param  list<array{employee_id:int,name:?string,email:?string}>  $leads
     * @return list<string>
     */
    public function leadEmails(array $leads): array
    {
        return $this->rejectExcluded(array_values(array_unique(array_filter(
            array_column($leads, 'email')
        ))));
    }

    /**
     * Nama (topic) group chat tiket.
     *
     * Dipisah dari {@see ticketChatPayload()} karena dipakai DUA flow: flow yang
     * MEMBUAT chatnya saat tiket divalidasi, dan flow yang mencari chat itu lagi
     * saat ada orang baru ditambahkan ke tiket. Konektor Teams tidak punya cara
     * menyimpan chat id tanpa lisensi Premium, jadi flow kedua mencocokkan
     * `topic` hasil aksi "List chats" dengan string ini — cocoknya harus
     * exact-match, dan itu hanya terjamin selama kedua jalur memakai fungsi yang
     * sama persis.
     *
     * CATATAN: kalau subject tiket diubah SETELAH chatnya terbentuk, string ini
     * tidak lagi sama dengan topic yang terlanjur dipakai. Karena itu nomor tiket
     * ikut dikirim terpisah sebagai kunci cadangan (lihat `chat.number`).
     *
     * @param  array<string,mixed>  $ticketPayload  hasil ticketPayload()
     */
    public function ticketChatTopic(array $ticketPayload): string
    {
        $number  = trim((string) ($ticketPayload['number'] ?? ''));
        $subject = trim((string) ($ticketPayload['subject'] ?? ''));

        // Batas nama group chat di Teams 250 karakter; potong subjeknya, bukan
        // nomornya, supaya tiket tetap bisa dikenali saat subjeknya panjang.
        $topic = trim($number . ' - ' . $subject);
        if (mb_strlen($topic) > 250) {
            // Penanda potongnya '...' ASCII, bukan elipsis satu karakter: topic
            // ini dibandingkan huruf-per-huruf oleh ekspresi di Power Automate,
            // dan karakter non-ASCII rawan berubah saat diketik ulang di
            // designer (lihat catatan lapangan 4 soal apostrof melengkung).
            $topic = rtrim(mb_substr($topic, 0, 247)) . '...';
        }

        return $topic;
    }

    /**
     * Bahan group chat Teams per tiket.
     *
     * Tim support sudah terbiasa membuat group chat manual bernama
     * "26080149 - Standard Cost Type S salah harga" untuk tiap tiket; flow 2
     * mengotomasi kebiasaan itu. Nama grup dan daftar anggotanya dirakit DI SINI,
     * bukan di Power Automate, supaya ekspresi di designer tetap sesedikit
     * mungkin (lihat catatan lapangan 4: ekspresi harus diketik tangan).
     *
     * `members_csv` memakai pemisah titik-koma karena itu yang diterima field
     * *Members* pada aksi Teams "Create a chat".
     *
     * Urutan penggabungan anggota BERARTI: kalau jumlahnya melewati batas 20
     * peserta milik konektor Teams, yang dipotong adalah yang paling belakang
     * (lihat {@see capChatMembers()}). Lead modul dan orang Delivery Support yang
     * memang menangani tiket ini didahulukan atas anggota tetap.
     *
     * @param  array<string,mixed>  $ticketPayload  hasil ticketPayload()
     * @param  list<string>         $leadEmails
     * @return array{topic:string,members:list<string>,members_csv:string,has_lead:bool,support_team:list<array{employee_id:int,name:?string,email:string,role:string,role_label:string}>}
     */
    public function ticketChatPayload(array $ticketPayload, array $leadEmails, ?string $validatorEmail = null): array
    {
        $topic = $this->ticketChatTopic($ticketPayload);

        // Tim Delivery Support yang menangani tiket ini (Delivery Owner, Support
        // Manager, CO PM, Support Admin). Dibaca dari delivery support yang sudah
        // dipilih helpdesk saat validasi, jadi orangnya spesifik per tiket —
        // berbeda dari role penjaga di bawah yang sama untuk semua tiket.
        $supportTeam = $this->deliverySupportTeam(
            $this->deliverySupportIdForTicket((int) ($ticketPayload['id'] ?? 0))
        );

        // Role penjaga (Delivery Support Head / Helpdesk) ikut di jalur group chat
        // juga, supaya kedua bentuk flow 2 memuat orang yang sama.
        $members = array_merge(
            $leadEmails,
            array_column($supportTeam, 'email'),
            array_column($this->roleMembers(), 'email'),
            $this->extraChatMembers()
        );

        // Validator ikut secara default (POWER_AUTOMATE_TEAMS_INCLUDE_VALIDATOR
        // = true): yang menekan Validate umumnya helpdesk manusia yang memang
        // perlu hadir di grup. Pengamannya ada di rejectExcluded() di bawah —
        // akun sistem seperti ECI_ADMIN (admin@eclectic.co.id) punya email di
        // database tapi bukan mailbox Microsoft 365, dan SATU alamat asing saja
        // membuat aksi Teams "Create a chat" menolak seluruh permintaan dengan
        // BadRequest. Jadi knob ini hanya aman selama
        // POWER_AUTOMATE_TEAMS_EXCLUDE_MEMBERS memuat akun-akun sistem itu.
        if (config('services.power_automate.teams_include_validator')
            && $validatorEmail
            && filter_var($validatorEmail, FILTER_VALIDATE_EMAIL)) {
            $members[] = $validatorEmail;
        }

        // Perbandingan case-insensitive: alamat yang sama dengan beda kapital
        // akan membuat Teams menolak seluruh aksi Create a chat.
        $unique = [];
        foreach ($members as $email) {
            $email = trim((string) $email);
            if ($email === '') {
                continue;
            }
            $unique[mb_strtolower($email)] = $email;
        }
        $members = $this->capChatMembers($this->rejectExcluded(array_values($unique)), $topic);

        return [
            'topic'        => $topic,
            'members'      => $members,
            'members_csv'  => implode(';', $members),
            'has_lead'     => $leadEmails !== [],
            // Nama + peran tim Delivery Support, untuk ditampilkan di Adaptive
            // Card. Email-nya sudah ikut di `members`; blok ini murni tampilan.
            'support_team' => $supportTeam,
        ];
    }

    /**
     * Potong daftar peserta di batas konektor Teams (20 orang untuk satu chat).
     *
     * Melebihi batas membuat aksi "Create a chat" menolak SELURUH permintaan
     * dengan BadRequest — jadi lebih baik grupnya terbentuk dengan peserta yang
     * paling penting daripada tidak terbentuk sama sekali. Yang dibuang adalah
     * yang paling belakang: anggota tetap (`TEAMS_EXTRA_MEMBERS`) dan pemegang
     * role penjaga, bukan lead modul atau tim Delivery Support tiket itu.
     *
     * Sisa slotnya juga dipakai flow 6 untuk menambahkan consultant, jadi
     * batasnya sengaja dibikin bisa diturunkan lewat env.
     *
     * @param  list<string>  $members
     * @return list<string>
     */
    private function capChatMembers(array $members, string $topic): array
    {
        $max = (int) config('services.power_automate.teams_max_members', 20);
        if ($max <= 0 || count($members) <= $max) {
            return $members;
        }

        Log::warning('PowerAutomate: peserta group chat melebihi batas, sisanya dipotong', [
            'topic'   => $topic,
            'total'   => count($members),
            'max'     => $max,
            'dropped' => array_slice($members, $max),
        ]);

        return array_slice($members, 0, $max);
    }

    /**
     * Payload flow "ticket_member_added": satu orang baru pada satu tiket yang
     * sudah punya group chat.
     *
     * Flow di Power Automate TIDAK menyimpan chat id (aksi HTTP untuk memanggil
     * balik EcoSystem butuh lisensi Premium), jadi chatnya dicari ulang tiap
     * kali lewat aksi Teams "List chats" lalu dicocokkan dengan `chat.topic` di
     * bawah. Topic itu dibangun ulang oleh fungsi yang SAMA dengan yang dipakai
     * flow "ticket_validated" saat membuat grupnya, jadi cocoknya exact-match,
     * bukan tebak-tebakan prefix.
     *
     * CATATAN: kalau subject tiket diubah setelah validasi, topic hasil rakitan
     * ini tidak lagi sama dengan nama grup yang terlanjur dibuat. Karena itu
     * `chat.number` ikut dikirim sebagai kunci cadangan — nomor tiket tidak
     * pernah berubah, dan flow bisa memakainya untuk pencocokan awalan.
     *
     * @param  array{employee_id:?int,name:?string,email:?string}  $person
     * @param  'member'|'pic'  $role
     * @param  array<string,mixed>  $actor
     */
    public function ticketMemberPayload(Ticket $ticket, array $person, string $role, array $actor = []): array
    {
        $ticketPayload = $this->ticketPayload($ticket);

        return [
            'ticket'  => $ticketPayload,
            'chat'    => [
                'topic'  => $this->ticketChatTopic($ticketPayload),
                'number' => (string) ($ticketPayload['number'] ?? ''),
            ],
            'person'  => [
                'employee_id' => $person['employee_id'] ?? null,
                'name'        => $person['name'] ?? null,
                'email'       => $person['email'] ?? null,
                // 'member' = baris ticket_member, 'pic' = ticket_lead_id.
                'role'        => $role,
                'role_label'  => $role === 'pic' ? 'PIC' : 'Member',
            ],
            'added_by' => [
                'id'    => $actor['id'] ?? null,
                'name'  => $actor['name'] ?? null,
                'email' => $actor['email'] ?? null,
            ],
        ];
    }

    /**
     * Nama + email kerja satu employee dalam bentuk yang dipakai
     * {@see ticketMemberPayload()}. Mengembalikan null kalau orangnya tidak punya
     * email kerja sama sekali — tanpa email, konektor Teams tidak bisa menemukan
     * orangnya, jadi lebih baik flow tidak dipanggil daripada gagal di tengah.
     *
     * @return array{employee_id:int,name:?string,email:string}|null
     */
    public function employeeContact(int $employeeId): ?array
    {
        $email = trim((string) ($this->workEmails([$employeeId])[$employeeId] ?? ''));
        if ($email === '' || $this->rejectExcluded([$email]) === []) {
            return null;
        }

        return [
            'employee_id' => $employeeId,
            'name'        => $this->employeeName($employeeId),
            'email'       => $email,
        ];
    }
    /**
     * Buang alamat yang tidak boleh masuk Teams — akun sistem seperti
     * `admin@eclectic.co.id` ada di database sebagai email user, tapi bukan
     * mailbox Microsoft 365. Satu alamat asing membuat aksi "Create a chat"
     * DAN "Get an @mention token" menolak seluruh permintaan, jadi
     * penyaringannya dipasang di jalur anggota maupun jalur mention.
     *
     * @param  list<string>  $emails
     * @return list<string>
     */
    private function rejectExcluded(array $emails): array
    {
        $excluded = $this->excludedChatMembers();
        if ($excluded === []) {
            return $emails;
        }

        return array_values(array_filter(
            $emails,
            fn ($email) => !in_array(mb_strtolower(trim((string) $email)), $excluded, true)
        ));
    }

    /** @return list<string> daftar alamat (huruf kecil) yang tidak boleh dipakai */
    private function excludedChatMembers(): array
    {
        $raw = (string) config('services.power_automate.teams_exclude_members');

        return array_values(array_filter(array_map(
            fn ($email) => mb_strtolower(trim($email)),
            explode(',', $raw)
        )));
    }

    // ─── Tim Delivery Support ────────────────────────────────────────────────

    /**
     * Delivery support yang menangani satu tiket.
     *
     * PITFALL: tiket TIDAK punya kolom `delivery_support_id`. Kaitannya lewat
     * tabel `delivery_support_activities` — saat helpdesk memilih delivery
     * support di modal validasi, EcoSystem membuat satu baris activity di sana
     * (lihat StagingTicketController::assignTicketToDeliverySupport()). Karena
     * itu fungsi ini dibaca dari tabel activity, bukan dari request: hasilnya
     * benar juga untuk tiket yang di-assign belakangan, bukan hanya saat
     * validasi.
     *
     * Satu tiket bisa punya lebih dari satu activity (mis. dipindah ke delivery
     * support lain); yang dipakai adalah yang TERBARU.
     */
    public function deliverySupportIdForTicket(int $ticketId): ?int
    {
        if ($ticketId <= 0) {
            return null;
        }

        $id = DB::table('delivery_support_activities')
            ->where('ticket_id', $ticketId)
            ->orderByDesc('id')
            ->value('delivery_support_id');

        return $id ? (int) $id : null;
    }

    /**
     * Orang Delivery Support yang menangani tiket: Delivery Owner, Support
     * Manager, CO PM, dan Support Admin. Mereka ikut jadi peserta group chat
     * tiket (keputusan meeting 11 Sep 2026) — sebelumnya grup hanya berisi lead
     * modul + role penjaga, sehingga pemilik delivery-nya sendiri tidak ada di
     * dalam grup tiket miliknya.
     *
     * Bentuk penyimpanannya TIDAK seragam, dan ini sumber kesalahan yang mudah:
     * Delivery Owner / CO PM / Support Admin adalah kolom tunggal di tabel
     * `delivery_support`, sedangkan Support Manager BISA LEBIH DARI SATU dan
     * tersimpan di tabel pivot `delivery_support_managers`. Kolom lama
     * `delivery_support.support_manager_id` masih ada di skema tapi sudah tidak
     * dipakai (tidak ada di `$fillable`, tidak ikut di-update controller), jadi
     * isinya bisa basi — JANGAN dibaca.
     *
     * `sales_id` sengaja TIDAK diikutkan: Sales bukan pelaksana tiket.
     *
     * Employee non-aktif dibuang, sama seperti {@see roleMembers()} — mantan
     * karyawan tidak punya mailbox lagi, dan satu alamat tanpa mailbox membuat
     * aksi Teams "Create a chat" menolak seluruh permintaan.
     *
     * @return list<array{employee_id:int,name:?string,email:string,role:string,role_label:string}>
     */
    public function deliverySupportTeam(?int $deliverySupportId): array
    {
        if (!$deliverySupportId || !config('services.power_automate.teams_include_support_team', true)) {
            return [];
        }

        $support = DB::table('delivery_support')
            ->where('id', $deliverySupportId)
            ->first(['id', 'delivery_owner_id', 'co_pm_id', 'support_admin_id']);

        if (!$support) {
            return [];
        }

        // role => list<employee_id>. Urutannya jadi urutan tampil di kartu.
        $byRole = [
            'delivery_owner'  => array_filter([$support->delivery_owner_id]),
            'support_manager' => DB::table('delivery_support_managers')
                ->where('delivery_support_id', $deliverySupportId)
                ->pluck('employee_id')
                ->all(),
            'co_pm'           => array_filter([$support->co_pm_id]),
            'support_admin'   => array_filter([$support->support_admin_id]),
        ];

        $employeeIds = array_values(array_unique(array_map(
            'intval',
            array_merge(...array_values($byRole))
        )));

        if ($employeeIds === []) {
            return [];
        }

        $active = DB::table('employee')
            ->whereIn('employee_id', $employeeIds)
            ->where('is_active', true)
            ->pluck('employee_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $names  = $this->employeeNames($active);
        $emails = $this->workEmails($active);

        $labels = [
            'delivery_owner'  => 'Delivery Owner',
            'support_manager' => 'Support Manager',
            'co_pm'           => 'CO PM',
            'support_admin'   => 'Support Admin',
        ];

        $team = [];
        $seen = [];
        foreach ($byRole as $role => $ids) {
            foreach ($ids as $id) {
                $id = (int) $id;
                // Satu orang bisa memegang dua peran sekaligus (mis. Delivery
                // Owner merangkap CO PM); peran PERTAMA yang dipakai supaya
                // alamatnya tidak dobel di members_csv.
                if (isset($seen[$id]) || !in_array($id, $active, true)) {
                    continue;
                }

                $email = trim((string) ($emails[$id] ?? ''));
                if ($email === '') {
                    // Tanpa email kerja, konektor Teams tidak bisa menemukan orangnya.
                    continue;
                }

                $seen[$id] = true;
                $team[] = [
                    'employee_id' => $id,
                    'name'        => $names[$id] ?? null,
                    'email'       => $email,
                    'role'        => $role,
                    'role_label'  => $labels[$role],
                ];
            }
        }

        return $team;
    }

    /**
     * Blok `delivery_support` untuk payload flow: identitas delivery support
     * tiket beserta timnya. Dipakai Adaptive Card supaya penerima tahu grup ini
     * milik delivery support mana; daftar emailnya sendiri sudah masuk lewat
     * `chat.members_csv`.
     *
     * @return array{id:?int,name:?string,type:?string,team:list<array<string,mixed>>,team_csv:string}
     */
    public function deliverySupportPayload(int $ticketId): array
    {
        $id      = $this->deliverySupportIdForTicket($ticketId);
        $team    = $this->deliverySupportTeam($id);
        $support = $id
            ? DB::table('delivery_support')->where('id', $id)->first(['name', 'type'])
            : null;

        return [
            'id'   => $id,
            'name' => $support->name ?? null,
            'type' => $support->type ?? null,
            'team' => $team,
            // Teks siap tampil: "Budi (Delivery Owner), Ani (Support Manager)".
            // Dirakit di sini supaya kartunya tidak perlu Apply to each.
            'team_csv' => implode(', ', array_map(
                fn ($p) => trim(($p['name'] ?? $p['email']) . ' (' . $p['role_label'] . ')'),
                $team
            )),
        ];
    }

    /**
     * Employee pemegang role penjaga tiket (default: 5 Delivery Support Head dan
     * 6 Delivery Support Service Helpdesk) beserta email kerjanya. Mereka ikut
     * jadi peserta group chat tiap tiket, di samping Module Lead.
     *
     * PITFALL: keanggotaan role TIDAK ada sebagai kolom di tabel `employee` —
     * relasinya di tabel pivot `employee_role_assignment` (employee_id, role_id).
     * Employee non-aktif dibuang supaya mantan helpdesk tidak ikut ditarik ke
     * setiap grup tiket baru.
     *
     * @return list<array{employee_id:int,name:?string,email:string}>
     */
    public function roleMembers(): array
    {
        $roleIds = $this->chatMemberRoleIds();
        if ($roleIds === []) {
            return [];
        }

        $employeeIds = DB::table('employee_role_assignment')
            ->join('employee', 'employee.employee_id', '=', 'employee_role_assignment.employee_id')
            ->whereIn('employee_role_assignment.role_id', $roleIds)
            ->where('employee.is_active', true)
            ->distinct()
            ->pluck('employee.employee_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($employeeIds === []) {
            return [];
        }

        $names  = $this->employeeNames($employeeIds);
        $emails = $this->workEmails($employeeIds);

        $people = [];
        foreach ($employeeIds as $id) {
            $email = trim((string) ($emails[$id] ?? ''));
            if ($email === '') {
                // Tanpa email kerja, konektor Teams tidak bisa menemukan orangnya.
                continue;
            }
            $people[] = [
                'employee_id' => $id,
                'name'        => $names[$id] ?? null,
                'email'       => $email,
            ];
        }

        return $people;
    }

    /** @return list<int> role_id yang orangnya selalu diikutkan ke group chat tiket */
    private function chatMemberRoleIds(): array
    {
        $raw = (string) config('services.power_automate.teams_member_role_ids');

        return array_values(array_unique(array_filter(array_map(
            fn ($id) => (int) trim($id),
            explode(',', $raw)
        ))));
    }

    /** @return list<string> */
    private function extraChatMembers(): array
    {
        $raw = (string) config('services.power_automate.teams_extra_members');

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    // ─── Resolusi nama & email ───────────────────────────────────────────────

    /**
     * Nama employee ada di employee_basic_data, BUKAN di tabel `employee` (tabel
     * itu hanya memegang eci + status aktif). Perhatikan: `full_name` di model
     * EmployeeBasicData adalah ACCESSOR, bukan kolom — di query mentah nama harus
     * dirangkai sendiri dari first_name + last_name.
     *
     * @param  list<int>  $employeeIds
     * @return array<int,string>
     */
    private function employeeNames(array $employeeIds): array
    {
        if ($employeeIds === []) {
            return [];
        }

        $names = [];
        $rows  = DB::table('employee_basic_data')
            ->whereIn('employee_id', $employeeIds)
            ->get(['employee_id', 'first_name', 'last_name']);

        foreach ($rows as $row) {
            $name = trim(trim((string) $row->first_name) . ' ' . trim((string) $row->last_name));
            if ($name !== '') {
                $names[(int) $row->employee_id] = $name;
            }
        }

        return $names;
    }

    private function employeeName(?int $employeeId): ?string
    {
        if (!$employeeId) {
            return null;
        }

        return $this->employeeNames([$employeeId])[$employeeId] ?? null;
    }

    /**
     * Email kerja tersimpan di employee_address.email_work (alamat PRIMARY kalau
     * ada). Employee lama bisa belum punya baris alamat sama sekali, jadi ada
     * fallback ke email login di auth_users — itu email @eclectic.co.id yang sama
     * yang dipakai masuk EcoSystem maupun Microsoft 365.
     *
     * @param  list<int>  $employeeIds
     * @return array<int,string>
     */
    private function workEmails(array $employeeIds): array
    {
        $emails = [];

        $addresses = DB::table('employee_address')
            ->whereIn('employee_id', $employeeIds)
            ->whereNotNull('email_work')
            ->where('email_work', '!=', '')
            ->orderBy('is_primary', 'desc')
            ->get(['employee_id', 'email_work']);

        foreach ($addresses as $row) {
            // is_primary desc -> baris primary tiba lebih dulu, jadi baris
            // non-primary berikutnya tidak boleh menimpanya.
            $id = (int) $row->employee_id;
            if (!isset($emails[$id])) {
                $emails[$id] = trim((string) $row->email_work);
            }
        }

        $missing = array_values(array_diff($employeeIds, array_keys($emails)));
        if ($missing !== []) {
            $logins = DB::table('auth_users')
                ->whereIn('employee_id', $missing)
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->pluck('email', 'employee_id');

            foreach ($logins as $employeeId => $email) {
                $emails[(int) $employeeId] = trim((string) $email);
            }
        }

        return $emails;
    }

    private function customerName($customerId): ?string
    {
        if (!$customerId) {
            return null;
        }

        return DB::table('customer_basic_data')
            ->where('customer_id', $customerId)
            ->value('name_1');
    }

    /** @return list<string> */
    private function ticketMemberNames(int $ticketId): array
    {
        $ids = DB::table('ticket_member')
            ->where('ticket_id', $ticketId)
            ->pluck('employee_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($ids === []) {
            return [];
        }

        return array_values($this->employeeNames($ids));
    }

    /**
     * URL absolut halaman tiket. Dibangun dari APP_URL (bukan dari request) agar
     * tetap benar saat dipanggil dari scheduler yang tidak punya request.
     */
    private function ticketUrl(int $ticketId): string
    {
        return rtrim((string) config('app.url'), '/') . '/ticket/' . $ticketId;
    }
}

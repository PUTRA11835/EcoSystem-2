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
     * @param  array<string,mixed>  $ticketPayload  hasil ticketPayload()
     * @param  list<string>         $leadEmails
     * @return array{topic:string,members:list<string>,members_csv:string,has_lead:bool}
     */
    public function ticketChatPayload(array $ticketPayload, array $leadEmails, ?string $validatorEmail = null): array
    {
        $number  = trim((string) ($ticketPayload['number'] ?? ''));
        $subject = trim((string) ($ticketPayload['subject'] ?? ''));

        // Batas nama group chat di Teams 250 karakter; potong subjeknya, bukan
        // nomornya, supaya tiket tetap bisa dikenali saat subjeknya panjang.
        $topic = trim($number . ' - ' . $subject);
        if (mb_strlen($topic) > 250) {
            $topic = mb_substr($topic, 0, 249) . '…';
        }

        // Role penjaga (Delivery Support Head / Helpdesk) ikut di jalur group chat
        // juga, supaya kedua bentuk flow 2 memuat orang yang sama.
        $members = array_merge(
            $leadEmails,
            array_column($this->roleMembers(), 'email'),
            $this->extraChatMembers()
        );

        // Validator TIDAK ikut secara default. Akun yang menekan Validate bisa
        // berupa akun sistem (ECI_ADMIN -> admin@eclectic.co.id) yang bukan
        // mailbox Microsoft 365; satu alamat asing saja membuat aksi Teams
        // "Create a chat" menolak SELURUH permintaan dengan BadRequest.
        // Helpdesk yang memang perlu selalu hadir cukup didaftarkan lewat
        // POWER_AUTOMATE_TEAMS_EXTRA_MEMBERS.
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
        $members = $this->rejectExcluded(array_values($unique));

        return [
            'topic'       => $topic,
            'members'     => $members,
            'members_csv' => implode(';', $members),
            'has_lead'    => $leadEmails !== [],
        ];
    }

    /**
     * Payload flow "ticket_member_added": satu orang baru pada satu tiket yang
     * sudah punya channel.
     *
     * Flow di Power Automate TIDAK menyimpan channel id (aksi HTTP untuk
     * memanggil balik EcoSystem butuh lisensi Premium), jadi channel dicari
     * lewat aksi Teams "List channels" pada team support lalu dicocokkan dengan
     * `channel.name` di bawah. Nama itu dibangun ulang oleh fungsi yang SAMA
     * dengan yang dipakai flow 2 saat membuat channelnya, jadi cocoknya
     * exact-match, bukan tebak-tebakan prefix.
     *
     * CATATAN: kalau subject tiket diubah setelah validasi, nama hasil rakitan
     * ini tidak lagi sama dengan nama channel yang terlanjur dibuat. Karena itu
     * `channel.number` ikut dikirim sebagai kunci cadangan — nomor tiket tidak
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
            'channel' => [
                'name'   => $this->ticketChannelPayload($ticketPayload)['name'],
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

    /**
     * Bahan channel Teams per tiket (aksi "Create a channel").
     *
     * Aturan nama channel jauh lebih ketat daripada nama group chat: maksimal 50
     * karakter dan sederet karakter dilarang. Subject tiket sering melanggar
     * keduanya, dan Teams menolak seluruh aksi kalau namanya tidak sah — jadi
     * pembersihannya dikerjakan di sini, bukan lewat ekspresi di designer.
     *
     * Nomor tiket selalu dipertahankan utuh di depan; yang dipotong subjeknya,
     * supaya channel tetap bisa dicari dari nomor tiketnya.
     *
     * @param  array<string,mixed>  $ticketPayload  hasil ticketPayload()
     * @param  list<string>         $leadEmails     hasil leadEmails()
     * @return array{name:string,description:string,members:list<array{name:?string,email:string,source:'module_lead'|'role'}>,member_emails:list<string>,member_csv:string,member_count:int}
     */
    public function ticketChannelPayload(array $ticketPayload, array $leadEmails = []): array
    {
        $number  = $this->sanitizeChannelName((string) ($ticketPayload['number'] ?? ''));
        $subject = $this->sanitizeChannelName((string) ($ticketPayload['subject'] ?? ''));

        $prefix = $number !== '' ? $number . ' - ' : '';
        $room   = 50 - mb_strlen($prefix);

        if ($room < 1) {
            // Nomor tiket saja sudah memenuhi batas — biarkan tanpa subjek.
            $name = mb_substr($prefix, 0, 50);
        } else {
            $name = $prefix . mb_substr($subject, 0, $room);
        }

        $name = trim($name, " .\t\n\r\0\x0B");
        if ($name === '') {
            $name = 'Tiket ' . ($ticketPayload['id'] ?? 'baru');
        }

        // Deskripsi channel menampung subject utuh, jadi pemotongan nama di atas
        // tidak menghilangkan informasi.
        $description = trim(sprintf(
            '%s | Customer: %s | Modul: %s',
            (string) ($ticketPayload['subject'] ?? '-'),
            (string) ($ticketPayload['customer'] ?? '-'),
            (string) ($ticketPayload['module'] ?? '-')
        ));

        // Anggota tetap channel: lead modul + role penjaga (Delivery Support Head,
        // Delivery Support Service Helpdesk).
        // Standard channel di Teams TIDAK punya daftar anggota sendiri — yang bisa
        // membaca channel adalah anggota TEAM-nya, jadi daftar ini dipakai flow
        // untuk aksi "Add a member to a team", bukan "add to channel".
        $members = $this->channelMembers($leadEmails);

        return [
            'name'          => $name,
            'description'   => mb_substr($description, 0, 1024),
            'members'       => $members,
            'member_emails' => array_column($members, 'email'),
            'member_csv'    => implode(';', array_column($members, 'email')),
            'member_count'  => count($members),
        ];
    }

    /**
     * Daftar orang yang harus bisa membaca channel tiket, sudah dedup dan bersih
     * dari alamat terlarang. Urutan sumbernya sengaja: lead modul lebih dulu
     * supaya kalau suatu saat daftarnya dipotong, lead-lah yang bertahan.
     *
     * Sumbernya HANYA dua: Module Lead tiket ini dan pemegang role penjaga.
     * Lihat catatan di badan fungsi soal kenapa EXTRA_MEMBERS tidak ikut.
     *
     * Sumber `role` di-resolve DI SINI (bukan lewat aksi tambahan di designer)
     * karena pemetaan role -> orang adalah pengetahuan EcoSystem; flow cukup
     * menerima daftar email jadi.
     *
     * @param  list<string>  $leadEmails
     * @return list<array{name:?string,email:string,source:'module_lead'|'role'}>
     */
    public function channelMembers(array $leadEmails = []): array
    {
        $rows = [];

        foreach ($leadEmails as $email) {
            $rows[] = ['name' => null, 'email' => (string) $email, 'source' => 'module_lead'];
        }

        foreach ($this->roleMembers() as $person) {
            $rows[] = $person + ['source' => 'role'];
        }

        // POWER_AUTOMATE_TEAMS_EXTRA_MEMBERS sengaja TIDAK ikut di sini. Dua
        // alasan keberadaannya khusus group chat: menjaga peserta >= 3 orang
        // (syarat Teams agar grup boleh diberi nama) dan jadi penerima cadangan
        // untuk modul tanpa Module Lead. Channel tidak punya syarat jumlah, dan
        // anggota channel tidak menerima notifikasi apa pun — yang memberi
        // notifikasi hanya @mention atas lead_emails — jadi di jalur channel
        // knob itu tidak melakukan apa-apa selain menambah orang diam-diam.
        // Peran "selalu ada yang mengawasi" dipegang role penjaga di atas.

        // Dedup case-insensitive: Teams menolak SELURUH aksi kalau satu alamat
        // muncul dua kali dengan kapitalisasi berbeda.
        $unique = [];
        foreach ($rows as $row) {
            $email = trim((string) $row['email']);
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $key = mb_strtolower($email);
            if (!isset($unique[$key])) {
                $unique[$key] = ['name' => $row['name'], 'email' => $email, 'source' => $row['source']];
            } elseif ($unique[$key]['name'] === null && $row['name'] !== null) {
                // Baris pertama (lead) tidak membawa nama; lengkapi dari baris role.
                $unique[$key]['name'] = $row['name'];
            }
        }

        $excluded = $this->excludedChatMembers();

        return array_values(array_filter(
            $unique,
            fn ($row) => !in_array(mb_strtolower($row['email']), $excluded, true)
        ));
    }

    /**
     * Employee pemegang role penjaga tiket (default: 5 Delivery Support Head dan
     * 6 Delivery Support Service Helpdesk) beserta email kerjanya.
     *
     * PITFALL: keanggotaan role TIDAK ada sebagai kolom di tabel `employee` —
     * relasinya di tabel pivot `employee_role_assignment` (employee_id, role_id).
     * Employee non-aktif dibuang supaya mantan helpdesk tidak ikut ditarik ke
     * setiap channel tiket baru.
     *
     * @return list<array{employee_id:int,name:?string,email:string}>
     */
    public function roleMembers(): array
    {
        $roleIds = $this->channelMemberRoleIds();
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

    /** @return list<int> role_id yang orangnya selalu diikutkan ke channel tiket */
    private function channelMemberRoleIds(): array
    {
        $raw = (string) config('services.power_automate.teams_member_role_ids');

        return array_values(array_unique(array_filter(array_map(
            fn ($id) => (int) trim($id),
            explode(',', $raw)
        ))));
    }

    /**
     * Buang karakter yang ditolak Teams untuk nama channel dan rapatkan spasi
     * ganda yang tersisa setelahnya.
     */
    private function sanitizeChannelName(string $value): string
    {
        $clean = preg_replace('/[#%&*{}\\:<>?\/+|~"\']/u', '', $value) ?? '';
        $clean = preg_replace('/\s+/u', ' ', $clean) ?? '';

        return trim($clean);
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

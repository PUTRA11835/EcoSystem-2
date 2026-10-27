<?php

namespace App\Services\Teams;

use App\Models\TeamsPendingGuestMember;
use App\Models\TicketTeamsChat;
use Illuminate\Support\Facades\Log;

/**
 * Pembuatan dan pembacaan group chat Teams milik tiket, lewat Graph app-only.
 *
 * Menggantikan aksi "Create a chat" di flow 5 Power Automate. Dua alasan
 * pindahnya (docs/teams-chat-sync-design.md §4):
 *
 *  1. Kita jadi MEMEGANG chat_id. Flow 5 tidak bisa mengembalikan id chat ke
 *     EcoSystem (aksi HTTP untuk memanggil balik butuh lisensi Premium), jadi
 *     flow 6 terpaksa mencari chatnya lagi tiap kali lewat "List chats" +
 *     cocokkan `topic` — rapuh, dan langsung meleset begitu subject tiket
 *     diubah. Dengan chat_id tersimpan, pencocokan topic tidak diperlukan lagi.
 *  2. Sinkronisasi pesan masuk MUSTAHIL tanpa chat_id.
 *
 * Yang TIDAK bisa dilakukan di sini: mengirim pesan. POST /chats/{id}/messages
 * tidak tersedia untuk izin aplikasi (hanya Teamwork.Migrate.All, untuk migrasi),
 * jadi arah keluar tetap lewat Power Automate.
 */
class TeamsChatService
{
    /** Hasil addGuestMember(). */
    public const GUEST_ADDED    = 'added';
    public const GUEST_DEFERRED = 'deferred';
    public const GUEST_FAILED   = 'failed';

    public function __construct(
        private readonly TeamsGraphClient $graph,
        private readonly TeamsGuestService $guests,
    ) {
    }

    /**
     * Fitur menyala DAN kredensialnya ada.
     *
     * TEAMS_SYNC_ENABLED mematikan seluruh integrasi ini; saat mati, pembuatan
     * chat kembali sepenuhnya ke flow 5 seperti sebelum fitur ini ada.
     */
    public function isEnabled(): bool
    {
        return (bool) config('services.teams_sync.enabled') && $this->graph->isConfigured();
    }

    public function chatForTicket(int $ticketId): ?TicketTeamsChat
    {
        return TicketTeamsChat::where('ticket_id', $ticketId)->first();
    }

    /**
     * Buat group chat untuk sebuah tiket dan simpan chat_id-nya.
     *
     * Idempoten: tiket yang sudah punya chat mengembalikan baris yang ada tanpa
     * memanggil Graph. Ini bukan kehati-hatian berlebihan — approve yang
     * di-retry, atau dua helpdesk yang menekan Validate hampir bersamaan, akan
     * sampai ke sini dua kali.
     *
     * Mengembalikan null (bukan melempar) untuk SEMUA kegagalan: pembuatan chat
     * adalah langkah tambahan di akhir approve tiket, dan tiket yang sudah
     * tervalidasi tidak boleh gagal gara-gara Teams.
     *
     * Guest yang belum dikenal Teams tidak ikut di POST /chats — satu anggota
     * yang ditolak membuat SELURUH chat ditolak (Graph memakai
     * DisablePartialSuccess). Mereka diantre ke teams_pending_guest_member dan
     * dimasukkan susulan; pendingGuestEmails() menyebut siapa saja.
     *
     * @param  list<string>  $memberEmails  sudah tersaring & terpotong oleh
     *                                      PowerAutomateService::ticketChatPayload()
     */
    public function createForTicket(int $ticketId, string $topic, array $memberEmails): ?TicketTeamsChat
    {
        if (!$this->isEnabled()) {
            return null;
        }

        if ($existing = $this->chatForTicket($ticketId)) {
            return $existing;
        }

        [$members, $deferred] = $this->planMembers($this->withConnectionOwner($memberEmails));

        try {
            $chat = $this->postChat($ticketId, $topic, $members);
        } catch (TeamsGraphException $e) {
            $guests = array_keys(array_filter($members, static fn ($m) => $m['guest']));

            if (!self::isRosterRejection($e) || $guests === []) {
                $this->logChatRejected($ticketId, $topic, $e, $memberEmails);

                return null;
            }

            // Ada guest yang tidak dikenal Teams walau sudah lewat masa
            // propagasi (atau tercatat lama tetapi baru dibuat ulang di Entra).
            // Graph tidak menyebut siapa, jadi SEMUA guest ditunda dan chat
            // dicoba sekali lagi dengan anggota internal saja.
            Log::info('TeamsChat: roster ditolak, chat dicoba ulang tanpa guest', [
                'ticket_id' => $ticketId,
                'guests'    => $guests,
            ]);

            $deferred = array_values(array_unique(array_merge($deferred, $guests)));
            $members  = array_filter($members, static fn ($m) => !$m['guest']);

            try {
                $chat = $this->postChat($ticketId, $topic, $members);
            } catch (TeamsGraphException $e) {
                $this->logChatRejected($ticketId, $topic, $e, $memberEmails);

                return null;
            }
        }

        if ($chat === null) {
            return null;
        }

        $chatId = $chat['id'] ?? null;

        if (!is_string($chatId) || $chatId === '') {
            Log::warning('TeamsChat: Graph membalas sukses tanpa chat id', [
                'ticket_id' => $ticketId,
                'response'  => $chat,
            ]);

            return null;
        }

        $row = TicketTeamsChat::create([
            'ticket_id'   => $ticketId,
            'chat_id'     => $chatId,
            'topic'       => $topic,
            'created_via' => 'graph',
            // Kursor dimulai dari SEKARANG. Pesan sistem "members added" /
            // "chat renamed" yang dibuat Graph barusan sudah lewat titik ini,
            // jadi tidak ikut terserap — dan riwayat sebelum chat ada memang
            // tidak ada. Lihat design §7.
            'last_message_at' => now(),
        ]);

        foreach ($deferred as $email) {
            $this->deferGuest($ticketId, $email);
        }

        // Guest yang sudah masuk diberi tahu sekarang; yang ditunda nanti,
        // saat postGuestMember() berhasil memasukkannya.
        foreach ($members as $email => $m) {
            if ($m['guest']) {
                $this->guests->sendChatInvite($email, $chatId, $topic);
            }
        }

        Log::info('TeamsChat: group chat dibuat lewat Graph', [
            'ticket_id' => $ticketId,
            'chat_id'   => $chatId,
            'topic'     => $topic,
            'members'   => count($members),
            'deferred'  => $deferred,
        ]);

        return $row;
    }

    /**
     * POST /chats. Null = anggotanya kosong (sudah dicatat); kegagalan Graph
     * dilempar supaya pemanggil bisa memutuskan mencoba ulang tanpa guest.
     *
     * @param  array<string,array{guest:bool,member:array}>  $members
     */
    private function postChat(int $ticketId, string $topic, array $members): ?array
    {
        if ($members === []) {
            Log::warning('TeamsChat: chat tidak dibuat, tidak ada anggota yang valid', [
                'ticket_id' => $ticketId,
                'topic'     => $topic,
            ]);

            return null;
        }

        return $this->graph->post('chats', [
            'chatType' => 'group',
            'topic'    => $topic,
            'members'  => array_values(array_column($members, 'member')),
        ]);
    }

    private function logChatRejected(int $ticketId, string $topic, TeamsGraphException $e, array $memberEmails): void
    {
        // Body error Graph dibawa utuh: satu alamat yang bukan mailbox M365
        // membuat SELURUH permintaan ditolak, dan hanya body itu yang
        // menyebut alamat mana yang bermasalah.
        Log::warning('TeamsChat: Graph menolak pembuatan chat', [
            'ticket_id' => $ticketId,
            'topic'     => $topic,
            'status'    => $e->status,
            'code'      => $e->graphCode,
            'body'      => mb_substr((string) $e->body, 0, 1000),
            'members'   => $memberEmails,
        ]);
    }

    /**
     * Penolakan karena ada anggota yang tidak bisa dimasukkan ke roster —
     * bentuk yang dikembalikan Graph untuk guest yang belum dikenal Teams:
     * 403 dengan innerError "DisablePartialSuccess-Create Thread: One or more
     * members cannot be added to the thread roster".
     */
    public static function isRosterRejection(TeamsGraphException $e): bool
    {
        $body = (string) $e->body;

        return $e->status === 403
            && (stripos($body, 'thread roster') !== false || stripos($body, 'DisablePartialSuccess') !== false);
    }

    /**
     * Roster chat: AAD object id -> email + displayName.
     *
     * INI sumber identitas untuk pesan masuk, menggantikan GET /users/{id} yang
     * dipakai rancangan awal — app registration ini tidak punya User.Read.All
     * dan panggilan itu dijawab 403 (terbukti di spike 17 Sep 2026).
     *
     * Rosternya juga lebih tepat sasaran: isinya persis orang di chat tiket ini,
     * bukan seluruh direktori tenant.
     *
     * @return array<string,array{email:?string,display_name:?string}>
     */
    public function roster(string $chatId): array
    {
        try {
            $response = $this->graph->get(TeamsGraphClient::chatPath($chatId, 'members'));
        } catch (TeamsGraphException $e) {
            Log::warning('TeamsChat: gagal membaca roster chat', [
                'chat_id' => $chatId,
                'status'  => $e->status,
                'code'    => $e->graphCode,
            ]);

            return [];
        }

        $out = [];

        foreach ($response['value'] ?? [] as $member) {
            $aadId = $member['userId'] ?? null;

            if (!is_string($aadId) || $aadId === '') {
                continue;
            }

            $out[$aadId] = [
                'email'        => $member['email'] ?? null,
                'display_name' => $member['displayName'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * Pastikan akun pemilik connection Power Automate ikut jadi anggota.
     *
     * Ini BUKAN sekadar kehati-hatian. Alamat itu sengaja ada di
     * POWER_AUTOMATE_TEAMS_EXCLUDE_MEMBERS, jadi ia PASTI tidak ada di daftar
     * yang datang dari ticketChatPayload(). Pengecualian itu benar untuk jalur
     * lama — konektor Teams menambahkan pemilik koneksi sendiri saat ia yang
     * membuat grup, dan alamat yang ikut dua kali membuat seluruh permintaan
     * ditolak sebagai "Duplicate chat members".
     *
     * Graph tidak menambahkan siapa pun otomatis. Tanpa baris ini, grup terbentuk
     * tanpa akun koneksi di dalamnya dan flow 5 (Post card) maupun flow 7
     * (Post message) gagal — keduanya memposting *Post as User* atas nama akun
     * itu, dan Teams menolak posting dari yang bukan anggota.
     *
     * @param  list<string>  $emails
     * @return list<string>
     */
    private function withConnectionOwner(array $emails): array
    {
        $owner = trim((string) config('services.teams_sync.connection_email'));

        if ($owner === '') {
            Log::warning('TeamsChat: services.teams_sync.connection_email kosong — '
                . 'grup akan dibuat tanpa akun koneksi Power Automate, dan flow tidak akan bisa memposting ke dalamnya.');

            return $emails;
        }

        $seen = array_map(static fn ($e) => mb_strtolower(trim((string) $e)), $emails);

        if (in_array(mb_strtolower($owner), $seen, true)) {
            return $emails;
        }

        $emails[] = $owner;

        return $emails;
    }

    /**
     * Ubah daftar email jadi anggota Graph, dan pisahkan guest yang harus
     * menunggu.
     *
     * Anggota internal di-bind dengan UPN langsung, bukan AAD object id: spike
     * membuktikan Graph menerimanya, dan resolusi ke object id lewat
     * GET /users/{id} justru TIDAK tersedia untuk app ini (403, tanpa
     * User.Read.All). Jadi kolom email di `employee` sudah cukup.
     *
     * Consultant eksternal (di luar TEAMS_INTERNAL_DOMAINS) TIDAK bisa begitu —
     * UPN guest bukan alamat emailnya. Mereka di-bind dengan object id dari
     * TeamsGuestService (diundang dulu kalau belum pernah). Yang tidak bisa
     * di-resolve DIBUANG; yang baru diundang DITUNDA (isPropagating) — dua-duanya
     * karena satu anggota yang ditolak membuat Graph menolak seluruh chat.
     *
     * Semua anggota diberi peran `owner` supaya siapa pun di grup bisa menamai
     * ulang grup dan menambah orang — chat tiket adalah ruang kerja bersama,
     * bukan milik satu orang.
     *
     * @param  list<string>  $emails
     * @return array{0: array<string,array{guest:bool,member:array}>, 1: list<string>}
     *         [anggota per email huruf kecil, email guest yang ditunda]
     */
    private function planMembers(array $emails): array
    {
        $members  = [];
        $deferred = [];

        foreach ($emails as $email) {
            $email = mb_strtolower(trim((string) $email));

            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || isset($members[$email])) {
                continue;
            }

            $ref = $this->guests->teamsUserRef($email);

            if ($ref === null) {
                Log::warning('TeamsChat: consultant eksternal dilewati, guest tidak bisa di-resolve', [
                    'email' => $email,
                ]);

                continue;
            }

            $guest = $this->guests->isExternal($email);

            if ($guest && $this->guests->isPropagating($email)) {
                $deferred[] = $email;

                continue;
            }

            $members[$email] = ['guest' => $guest, 'member' => $this->member($ref)];
        }

        return [$members, array_values(array_unique($deferred))];
    }

    /**
     * Tambahkan consultant EKSTERNAL ke group chat tiket yang sudah ada.
     *
     * Jalur susulan untuk guest saja. Anggota internal tetap ditambahkan flow 6
     * lewat aksi "Add a user to a chat" — aksi itu mencari orangnya dari email,
     * dan untuk guest pencarian itu gagal. Di sini kita memegang chat_id dan
     * object id guest, jadi Graph bisa langsung dipanggil (terbukti di spike
     * 20 Okt 2026: POST /chats/{id}/members dengan role owner).
     *
     * Guest yang baru diundang, atau yang ditolak Graph, DITUNDA ke antrean
     * teams:add-pending-guests alih-alih dibuang. $flowPayload (flow 6) ikut
     * disimpan dan baru dikirim begitu guest-nya benar-benar masuk grup.
     *
     * @return string  GUEST_ADDED = sudah masuk; pemanggil boleh langsung
     *                 mengirim flow 6. GUEST_DEFERRED = jangan kirim — command
     *                 yang akan mengirimnya. GUEST_FAILED = tidak ada yang
     *                 bisa dilakukan.
     */
    public function addGuestMember(int $ticketId, string $email, ?array $flowPayload = null): string
    {
        if (!$this->isEnabled() || !$this->guests->isExternal($email) || !$this->chatForTicket($ticketId)) {
            return self::GUEST_FAILED;
        }

        $guestId = $this->guests->resolve($email);

        if ($guestId === null) {
            return self::GUEST_FAILED;
        }

        if ($this->guests->isPropagating($email)) {
            $this->deferGuest($ticketId, $email, $flowPayload);

            return self::GUEST_DEFERRED;
        }

        try {
            $this->postGuestMember($ticketId, $guestId, $email);
        } catch (TeamsGraphException $e) {
            Log::warning('TeamsChat: gagal menambahkan guest ke group chat, diantre ulang', [
                'ticket_id' => $ticketId,
                'email'     => $email,
                'status'    => $e->status,
                'code'      => $e->graphCode,
                'body'      => mb_substr((string) $e->body, 0, 1000),
            ]);

            $this->deferGuest($ticketId, $email, $flowPayload);

            return self::GUEST_DEFERRED;
        }

        return self::GUEST_ADDED;
    }

    /**
     * Satu percobaan untuk satu baris antrean. True = guest sudah masuk grup;
     * kalau baris membawa flow_payload, pemanggil yang mengirim flow 6.
     */
    public function addPendingGuest(TeamsPendingGuestMember $row): bool
    {
        $max = max(1, (int) config('services.teams_sync.guest_add_max_attempts', 10));

        if (!$this->chatForTicket((int) $row->ticket_id)) {
            // Tidak akan pulih dengan menunggu — langsung berhenti.
            $row->markAttemptFailed('Tiket tidak punya group chat Teams.', 1);

            return false;
        }

        $guestId = $this->guests->resolve($row->email);

        if ($guestId === null) {
            $row->markAttemptFailed('Object id guest tidak bisa di-resolve (lihat log TeamsGuest).', $max);

            return false;
        }

        try {
            $this->postGuestMember((int) $row->ticket_id, $guestId, $row->email);
        } catch (TeamsGraphException $e) {
            $row->markAttemptFailed($e->getMessage(), $max);

            if ($row->status === TeamsPendingGuestMember::STATUS_FAILED) {
                Log::warning('TeamsChat: guest tetap gagal dimasukkan ke group chat, berhenti mencoba', [
                    'ticket_id' => $row->ticket_id,
                    'email'     => $row->email,
                    'attempts'  => $row->attempts,
                    'status'    => $e->status,
                    'body'      => mb_substr((string) $e->body, 0, 1000),
                ]);
            }

            return false;
        }

        $row->markAdded();

        return true;
    }

    /**
     * Guest tiket ini yang masih menunggu dimasukkan ke grup.
     *
     * @return list<string>
     */
    public function pendingGuestEmails(int $ticketId): array
    {
        return TeamsPendingGuestMember::where('ticket_id', $ticketId)
            ->where('status', TeamsPendingGuestMember::STATUS_PENDING)
            ->pluck('email')
            ->all();
    }

    /**
     * Antre (atau antre ulang) satu guest. Baris yang sudah ada dimulai lagi
     * dari awal: penundaan baru berarti ada alasan baru untuk mencoba.
     */
    private function deferGuest(int $ticketId, string $email, ?array $flowPayload = null): void
    {
        $email = mb_strtolower(trim($email));

        TeamsPendingGuestMember::updateOrCreate(
            ['ticket_id' => $ticketId, 'email' => $email],
            [
                'status'          => TeamsPendingGuestMember::STATUS_PENDING,
                'attempts'        => 0,
                'next_attempt_at' => now()->addMinutes(TeamsPendingGuestMember::backoffMinutes(0)),
                'last_error'      => null,
                'added_at'        => null,
                'flow_payload'    => $flowPayload,
            ]
        );

        Log::info('TeamsChat: guest diantre untuk dimasukkan susulan', [
            'ticket_id' => $ticketId,
            'email'     => $email,
        ]);
    }

    /**
     * Riwayat chat dibuka penuh: consultant yang masuk belakangan perlu membaca
     * konteks tiketnya dari awal — termasuk kartu flow 5 yang terkirim sebelum
     * guest yang ditunda sempat masuk.
     *
     * Setelah berhasil, guest-nya diberi tahu lewat email berisi tautan chat.
     *
     * @throws TeamsGraphException
     */
    private function postGuestMember(int $ticketId, string $guestId, string $email): void
    {
        $chat = $this->chatForTicket($ticketId);

        $this->graph->post(
            TeamsGraphClient::chatPath($chat->chat_id, 'members'),
            $this->member($guestId) + ['visibleHistoryStartDateTime' => '0001-01-01T00:00:00Z']
        );

        Log::info('TeamsChat: guest ditambahkan ke group chat', [
            'ticket_id' => $ticketId,
            'chat_id'   => $chat->chat_id,
            'email'     => $email,
        ]);

        $this->guests->sendChatInvite($email, $chat->chat_id, (string) $chat->topic);
    }

    /** @param  string  $userRef  UPN (internal) atau object id (guest) */
    private function member(string $userRef): array
    {
        return [
            '@odata.type'     => '#microsoft.graph.aadUserConversationMember',
            'roles'           => ['owner'],
            'user@odata.bind' => "https://graph.microsoft.com/v1.0/users('{$userRef}')",
        ];
    }
}

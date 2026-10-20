<?php

namespace App\Services\Teams;

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

        $members = $this->buildMembers($this->withConnectionOwner($memberEmails));

        if ($members === []) {
            Log::warning('TeamsChat: chat tidak dibuat, tidak ada anggota yang valid', [
                'ticket_id' => $ticketId,
                'topic'     => $topic,
            ]);

            return null;
        }

        try {
            $chat = $this->graph->post('chats', [
                'chatType' => 'group',
                'topic'    => $topic,
                'members'  => $members,
            ]);
        } catch (TeamsGraphException $e) {
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

        Log::info('TeamsChat: group chat dibuat lewat Graph', [
            'ticket_id' => $ticketId,
            'chat_id'   => $chatId,
            'topic'     => $topic,
            'members'   => count($members),
        ]);

        return $row;
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
     * Ubah daftar email jadi anggota Graph.
     *
     * Anggota internal di-bind dengan UPN langsung, bukan AAD object id: spike
     * membuktikan Graph menerimanya, dan resolusi ke object id lewat
     * GET /users/{id} justru TIDAK tersedia untuk app ini (403, tanpa
     * User.Read.All). Jadi kolom email di `employee` sudah cukup.
     *
     * Consultant eksternal (di luar TEAMS_INTERNAL_DOMAINS) TIDAK bisa begitu —
     * UPN guest bukan alamat emailnya. Mereka di-bind dengan object id dari
     * TeamsGuestService (diundang dulu kalau belum pernah). Yang tidak bisa
     * di-resolve DIBUANG: satu alamat asing membuat Graph menolak seluruh chat.
     *
     * Semua anggota diberi peran `owner` supaya siapa pun di grup bisa menamai
     * ulang grup dan menambah orang — chat tiket adalah ruang kerja bersama,
     * bukan milik satu orang.
     */
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

    private function buildMembers(array $emails): array
    {
        $members = [];

        foreach ($emails as $email) {
            $email = trim((string) $email);

            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $ref = $this->guests->teamsUserRef($email);

            if ($ref === null) {
                Log::warning('TeamsChat: consultant eksternal dilewati, guest tidak bisa di-resolve', [
                    'email' => $email,
                ]);

                continue;
            }

            $members[] = $this->member($ref);
        }

        return $members;
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
     * Riwayat chat dibuka penuh: consultant yang masuk belakangan perlu membaca
     * konteks tiketnya dari awal.
     */
    public function addGuestMember(int $ticketId, string $email): bool
    {
        if (!$this->isEnabled() || !$this->guests->isExternal($email)) {
            return false;
        }

        $chat = $this->chatForTicket($ticketId);

        if (!$chat) {
            return false;
        }

        $guestId = $this->guests->resolve($email);

        if ($guestId === null) {
            return false;
        }

        try {
            $this->graph->post(
                TeamsGraphClient::chatPath($chat->chat_id, 'members'),
                $this->member($guestId) + ['visibleHistoryStartDateTime' => '0001-01-01T00:00:00Z']
            );
        } catch (TeamsGraphException $e) {
            Log::warning('TeamsChat: gagal menambahkan guest ke group chat', [
                'ticket_id' => $ticketId,
                'chat_id'   => $chat->chat_id,
                'email'     => $email,
                'status'    => $e->status,
                'code'      => $e->graphCode,
                'body'      => mb_substr((string) $e->body, 0, 1000),
            ]);

            return false;
        }

        Log::info('TeamsChat: guest ditambahkan ke group chat', [
            'ticket_id' => $ticketId,
            'chat_id'   => $chat->chat_id,
            'email'     => $email,
        ]);

        return true;
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

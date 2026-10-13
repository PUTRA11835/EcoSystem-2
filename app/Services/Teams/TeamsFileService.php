<?php

namespace App\Services\Teams;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketTeamsChat;
use App\Models\TicketTeamsFile;
use App\Services\OneDriveService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Lampiran internal note -> kartu berkas asli di group chat Teams.
 *
 * Lihat docs/teams-sync-parity-design.md §6c.
 *
 * Teams hanya menampilkan kartu berkas (ikon, nama, pratinjau) untuk berkas
 * yang TINGGAL di OneDrive/SharePoint dan dilampirkan sebagai `reference`.
 * Tautan ke proxy EcoSystem tidak akan pernah jadi kartu. Jadi berkasnya
 * disalin ke OneDrive akun pengirim, dibagikan ke anggota grup, dan pesannya
 * dikirim flow 7 lewat Graph (bukan lewat aksi "Post message", yang tidak punya
 * parameter lampiran).
 *
 * Tiga keputusan yang membentuk kelas ini:
 *
 *  1. **Izin diberikan di FOLDER tiket, bukan per berkas.** Izin folder diwarisi
 *     isinya, jadi anggota yang ditambahkan belakangan cukup diberi izin sekali
 *     dan langsung bisa membuka berkas lama maupun baru.
 *  2. **Folder sendiri, terpisah dari folder TICKETING.** Folder tiket yang itu
 *     dibagikan ke customer; lampiran internal note tidak boleh ikut terlihat.
 *  3. **Gagal = kembali ke tautan, bukan gagal kirim.** Semua kegagalan di sini
 *     membuat pemanggil memakai bentuk tautan yang lama. Kartu yang tampil tapi
 *     tidak bisa dibuka lebih buruk daripada tautan yang jalan — karena itu
 *     kegagalan MEMBAGIKAN folder juga membatalkan seluruh penyalinan.
 */
class TeamsFileService
{
    public function __construct(
        private readonly OneDriveService $drive,
        private readonly TeamsChatService $chats,
    ) {
    }

    public function isEnabled(): bool
    {
        return (bool) config('services.teams_sync.enabled')
            && (bool) config('services.teams_sync.outbound')
            && (bool) config('services.teams_sync.native_files');
    }

    public function maxBytes(): int
    {
        return max(1, (int) config('services.teams_sync.native_file_max_mb', 30)) * 1024 * 1024;
    }

    /**
     * Baris `ticket_attachment` ini bisa disalin ke OneDrive?
     *
     * Hanya berkas yang byte-nya ada di disk EcoSystem. Lampiran email (hidup
     * di Graph) dan berkas Teams (sudah di SharePoint) tidak disalin: internal
     * note yang ditulis di EcoSystem selalu menyimpan lampirannya di disk, jadi
     * dua jenis itu tidak pernah lewat jalur ini.
     */
    public function canCopy(object $row): bool
    {
        if (empty($row->file_path) || !empty($row->graph_message_id)) {
            return false;
        }

        // file_size bisa NULL di baris lama; ukuran sebenarnya diperiksa lagi
        // di copy() dari disk.
        return empty($row->file_size) || (int) $row->file_size <= $this->maxBytes();
    }

    /**
     * Salin lampiran ke OneDrive dan kembalikan bentuk lampiran Graph-nya.
     *
     * Lampiran yang gagal disalin tidak ada di hasil — pemanggil mengirimnya
     * sebagai tautan. Kegagalan menyiapkan atau membagikan FOLDER dilempar,
     * karena tanpa itu tidak satu pun berkas bisa dibuka anggota grup.
     *
     * @param  list<int>  $attachmentIds
     * @return array<int,array{id:string,contentType:string,contentUrl:string,name:string}>
     *         dikunci id lampiran
     *
     * @throws \Throwable
     */
    public function publish(TicketTeamsChat $chat, array $attachmentIds): array
    {
        $attachmentIds = array_values(array_unique(array_map('intval', $attachmentIds)));

        if ($attachmentIds === []) {
            return [];
        }

        $folderId = $this->folderFor($chat);

        $this->shareWithRoster($chat, $folderId);

        $out = [];

        foreach ($attachmentIds as $id) {
            try {
                $out[$id] = $this->copy($chat, $folderId, $id)->toGraphAttachment();
            } catch (\Throwable $e) {
                Log::warning('TeamsFile: lampiran gagal disalin ke OneDrive, dikirim sebagai tautan', [
                    'ticket_id'     => $chat->ticket_id,
                    'attachment_id' => $id,
                    'error'         => mb_substr($e->getMessage(), 0, 500),
                ]);
            }
        }

        return $out;
    }

    /**
     * Beri hak baca folder berkas tiket kepada orang yang baru masuk grup.
     *
     * Dipanggil saat member/PIC ditambahkan ke tiket. Tiket yang belum pernah
     * mengirim berkas belum punya folder, dan tidak ada yang perlu dilakukan —
     * orangnya akan ikut dibagikan lewat roster pada kiriman berkas pertama.
     *
     * Semua kegagalan ditelan: ini langkah tambahan di ujung penambahan member.
     *
     * @param  list<string>  $emails
     */
    public function grantForTicket(int $ticketId, array $emails): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        try {
            $folderId = TicketTeamsChat::where('ticket_id', $ticketId)->value('files_folder_id');

            if (!$folderId) {
                return;
            }

            $this->drive->grantRead($folderId, $this->withoutDriveOwner($emails));
        } catch (\Throwable $e) {
            Log::warning('TeamsFile: gagal memberi akses folder berkas ke anggota baru', [
                'ticket_id' => $ticketId,
                'emails'    => $emails,
                'error'     => mb_substr($e->getMessage(), 0, 500),
            ]);
        }
    }

    /**
     * GUID berkas yang dipakai Teams sebagai `id` lampiran `reference`.
     *
     * Sumber resminya eTag driveItem, bentuknya `"{GUID},versi"`. Kalau eTag
     * tidak berbentuk begitu, `sharepointIds.listItemUniqueId` memuat GUID yang
     * sama (terbukti pada probe 13 Okt 2026).
     */
    public static function attachmentGuid(array $item): ?string
    {
        if (preg_match('/\{([0-9a-fA-F-]{36})\}/', (string) ($item['eTag'] ?? ''), $m)) {
            return $m[1];
        }

        $fallback = (string) ($item['sharepointIds']['listItemUniqueId'] ?? '');

        return preg_match('/^[0-9a-fA-F-]{36}$/', $fallback) ? $fallback : null;
    }

    // ─── Internal ────────────────────────────────────────────────────────────

    /** Folder OneDrive berkas chat tiket ini; dibuat pada pemakaian pertama. */
    private function folderFor(TicketTeamsChat $chat): string
    {
        if ($chat->files_folder_id) {
            return $chat->files_folder_id;
        }

        // Susunannya meniru folder deliverable supaya mudah ditelusuri:
        //   {root}/{id customer} {NAMA}/TICKETING/{nomor tiket}
        // tetapi di bawah ROOT SENDIRI. Folder tiket deliverable dibagikan ke
        // customer lewat link edit publik yang menurun ke semua isinya, jadi
        // lampiran internal note tidak boleh ditaruh di sana (13 Okt 2026).
        $ticket   = Ticket::find($chat->ticket_id);
        $customer = $ticket?->customerDeliverableFolderName() ?: 'TANPA CUSTOMER';
        $number   = $ticket?->ticket_number ?: ('Ticket-' . $chat->ticket_id);

        $rootId      = $this->drive->ensureFolderPath((string) config('services.teams_sync.files_path'));
        $customerId  = $this->drive->ensureSubFolder($rootId, $customer);
        $ticketingId = $this->drive->ensureSubFolder($customerId, 'TICKETING');
        $folderId    = $this->drive->ensureSubFolder($ticketingId, (string) $number);

        $chat->files_folder_id = $folderId;
        $chat->save();

        return $folderId;
    }

    /**
     * Bagikan folder ke seluruh anggota grup saat ini.
     *
     * Dipanggil pada SETIAP kiriman, bukan hanya saat folder dibuat: roster
     * berubah (flow 6 menambah orang), dan memberi izin yang sudah ada tidak
     * berakibat apa-apa di sisi Graph.
     */
    private function shareWithRoster(TicketTeamsChat $chat, string $folderId): void
    {
        $emails = [];

        foreach ($this->chats->roster($chat->chat_id) as $member) {
            if (!empty($member['email'])) {
                $emails[] = (string) $member['email'];
            }
        }

        $emails = $this->withoutDriveOwner($emails);

        if ($emails === []) {
            throw new RuntimeException('Roster group chat kosong atau tidak terbaca; folder berkas tidak bisa dibagikan.');
        }

        try {
            $this->drive->grantRead($folderId, $emails);

            return;
        } catch (\Throwable $e) {
            // Satu alamat yang ditolak (mis. tamu dari luar organisasi)
            // menggagalkan seluruh permintaan. Ulangi satu per satu supaya
            // sisanya tetap dapat akses.
            Log::warning('TeamsFile: pembagian folder sekaligus ditolak, dicoba per anggota', [
                'ticket_id' => $chat->ticket_id,
                'error'     => mb_substr($e->getMessage(), 0, 500),
            ]);
        }

        $granted = 0;

        foreach ($emails as $email) {
            try {
                $this->drive->grantRead($folderId, [$email]);
                $granted++;
            } catch (\Throwable $e) {
                Log::warning('TeamsFile: anggota grup tidak bisa diberi akses folder berkas', [
                    'ticket_id' => $chat->ticket_id,
                    'email'     => $email,
                    'error'     => mb_substr($e->getMessage(), 0, 300),
                ]);
            }
        }

        if ($granted === 0) {
            throw new RuntimeException('Folder berkas tidak bisa dibagikan ke satu pun anggota group chat.');
        }
    }

    /**
     * Pemilik drive tidak perlu (dan tidak bisa) diundang ke berkasnya sendiri.
     *
     * @param  list<string>  $emails
     * @return list<string>
     */
    private function withoutDriveOwner(array $emails): array
    {
        $owner = mb_strtolower(trim((string) config('services.microsoft_graph.sender_email')));

        return array_values(array_unique(array_filter(
            array_map(static fn ($e) => mb_strtolower(trim((string) $e)), $emails),
            static fn ($e) => $e !== '' && $e !== $owner
        )));
    }

    private function copy(TicketTeamsChat $chat, string $folderId, int $attachmentId): TicketTeamsFile
    {
        if ($existing = TicketTeamsFile::where('ticket_attachment_id', $attachmentId)->first()) {
            return $existing;
        }

        $attachment = TicketAttachment::find($attachmentId);

        // Cek kepemilikan: id lampiran datang dari payload antrean, dan berkas
        // tiket lain tidak boleh pernah mendarat di folder tiket ini.
        if (!$attachment || (int) $attachment->ticket_id !== (int) $chat->ticket_id) {
            throw new RuntimeException('Lampiran tidak ditemukan pada tiket ini.');
        }

        $path = (string) $attachment->file_path;

        if ($path === '' || $attachment->graph_message_id
            || str_contains($path, '..') || str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            throw new RuntimeException('Lampiran bukan berkas lokal yang bisa disalin.');
        }

        $disk = Storage::disk('public');

        if (!$disk->exists($path)) {
            throw new RuntimeException('Berkas lampiran tidak ada di disk.');
        }

        $size = (int) $disk->size($path);

        if ($size > $this->maxBytes()) {
            throw new RuntimeException('Berkas melebihi batas TEAMS_SYNC_NATIVE_FILE_MAX_MB.');
        }

        $stream = $disk->readStream($path);

        try {
            $uploaded = $this->drive->uploadStream(
                $folderId,
                (string) ($attachment->file_name ?: basename($path)),
                $stream,
                $size
            );
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        // webDavUrl, bukan webUrl: untuk berkas Office, webUrl berbentuk
        // `_layouts/15/Doc.aspx?sourcedoc=…` dan Teams menolaknya sebagai
        // contentUrl. webDavUrl selalu path langsung ke berkasnya.
        $item = $this->drive->getItem(
            (string) $uploaded['id'],
            'id,name,eTag,webUrl,webDavUrl,sharepointIds'
        );

        $guid = self::attachmentGuid($item) ?? self::attachmentGuid($uploaded);
        $url  = (string) ($item['webDavUrl'] ?? $item['webUrl'] ?? '');

        if (!$guid || $url === '') {
            throw new RuntimeException('OneDrive tidak mengembalikan GUID atau URL berkas.');
        }

        return TicketTeamsFile::create([
            'ticket_id'            => $chat->ticket_id,
            'ticket_attachment_id' => $attachmentId,
            'drive_item_id'        => (string) $uploaded['id'],
            'attachment_guid'      => $guid,
            'content_url'          => $url,
            'name'                 => (string) ($item['name'] ?? $uploaded['name'] ?? $attachment->file_name),
        ]);
    }
}

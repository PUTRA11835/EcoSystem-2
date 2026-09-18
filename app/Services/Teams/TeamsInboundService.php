<?php

namespace App\Services\Teams;

use App\Models\TicketAttachment;
use App\Models\TicketMessage;
use App\Models\TicketMessageTeams;
use App\Models\TicketTeamsChat;
use App\Services\MessageHtmlSanitizerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Pesan di group chat Teams -> internal note EcoSystem.
 *
 * Lihat docs/teams-chat-sync-design.md §7.
 *
 * DUA KOREKSI TERHADAP §7, ditemukan 17 Sep 2026 saat menulis kelas ini:
 *
 *  1. **`channel` TIDAK diisi `'teams'`.** Kolom itu `enum('email','web')` di
 *     `ticket_message` — tabel yang dibagi dengan repo Jarvies dan yang oleh §2
 *     dilarang diubah strukturnya. Menambah nilai enum = ALTER pada tabel bersama.
 *     Jadi `channel` tetap `'web'`, dan penanda asal Teams ada di tabel pendamping
 *     `ticket_message_teams` — persis prinsip §2 "semua metadata Teams ditaruh di
 *     tabel pendamping".
 *  2. **`message_type` tetap `'internal_note'`.** UI tiket menentukan bentuk
 *     gelembung MURNI dari `message_type === 'internal_note'`
 *     (show.blade.php:4179, 4520). Mengisinya `'teams'` membuat internal note
 *     tampil sebagai balasan biasa ke customer — salah secara visual, dan justru
 *     pada pesan yang sifatnya internal.
 */
class TeamsInboundService
{
    /** Pesan Teams yang kita serap hanyalah yang bertipe ini. */
    private const HUMAN_MESSAGE_TYPE = 'message';

    /** Cache roster per chat, hidup selama satu putaran sinkron. */
    private array $rosterCache = [];

    /** Cache email -> employee_id, hidup selama satu putaran sinkron. */
    private array $employeeCache = [];

    /**
     * Jumlah gambar yang gagal diunduh pada pesan yang SEDANG diproses.
     * Direset oleh attachmentNotice() setelah dibaca.
     */
    private int $failedImages = 0;

    public function __construct(
        private readonly TeamsGraphClient $graph,
        private readonly TeamsChatService $chats,
    ) {
    }

    public function isEnabled(): bool
    {
        return (bool) config('services.teams_sync.enabled')
            && (bool) config('services.teams_sync.inbound')
            && $this->graph->isConfigured();
    }

    /**
     * Chat yang layak dipoll putaran ini.
     *
     * Tiket yang sudah Closed/Cancelled dilewati: diskusinya selesai, dan menarik
     * pesan baru ke tiket tertutup hanya menghidupkan kembali sesuatu yang sudah
     * ditutup. Barisnya tidak dimatikan — tiket bisa dibuka lagi.
     */
    public function dueChats(): iterable
    {
        $closed = ['closed', 'cancelled'];

        return TicketTeamsChat::query()
            ->where('sync_enabled', true)
            ->whereIn('ticket_id', function ($q) use ($closed) {
                $q->select('ticket_id')->from('ticket')->whereNotIn('status', $closed);
            })
            ->orderByRaw('last_polled_at is null desc')
            ->orderBy('last_polled_at')
            ->limit(max(1, (int) config('services.teams_sync.chat_batch', 50)))
            ->get();
    }

    /**
     * Satu putaran untuk satu chat.
     *
     * @return array{imported:int,skipped:int,failed:bool}
     */
    public function syncChat(TicketTeamsChat $chat): array
    {
        $cursor = $chat->cursorIso();

        if ($cursor === null) {
            // Kursor kosong berarti baris ini dibuat tanpa titik mulai. JANGAN
            // tarik semua — itu akan menumpahkan seluruh riwayat chat ke thread
            // tiket. Tetapkan sekarang sebagai titik mulai dan tunggu putaran
            // berikutnya (design §7: riwayat lama sengaja tidak diimpor).
            $chat->markPolled(now());

            return ['imported' => 0, 'skipped' => 0, 'failed' => false];
        }

        try {
            $response = $this->graph->get(
                TeamsGraphClient::chatPath($chat->chat_id, 'messages'),
                [
                    '$top'     => 50,
                    '$orderby' => 'lastModifiedDateTime desc',
                    '$filter'  => "lastModifiedDateTime gt {$cursor}",
                ]
            );
        } catch (TeamsGraphException $e) {
            if ($e->isThrottled()) {
                // Dipanggil terlalu sering. Bukan kegagalan chat ini — jangan
                // dihitung sebagai poll_failures, cukup coba lagi semenit lagi.
                Log::info('TeamsInbound: Graph throttling, putaran ini dilewati', [
                    'ticket_id' => $chat->ticket_id,
                ]);

                return ['imported' => 0, 'skipped' => 0, 'failed' => false];
            }

            $disabled = $chat->markPollFailed(
                (int) config('services.teams_sync.max_poll_failures', 10)
            );

            Log::warning('TeamsInbound: polling gagal', [
                'ticket_id'     => $chat->ticket_id,
                'chat_id'       => $chat->chat_id,
                'status'        => $e->status,
                'code'          => $e->graphCode,
                'poll_failures' => $chat->poll_failures,
                'disabled'      => $disabled,
            ]);

            if ($disabled) {
                Log::warning('TeamsInbound: sinkron chat DIMATIKAN otomatis setelah gagal beruntun', [
                    'ticket_id' => $chat->ticket_id,
                    'chat_id'   => $chat->chat_id,
                ]);
            }

            return ['imported' => 0, 'skipped' => 0, 'failed' => true];
        }

        $all      = $response['value'] ?? [];
        $messages = $this->humanMessages($all, $chat->chat_id);

        // Graph mengembalikan terbaru dulu; serap dari yang paling lama supaya
        // urutan di thread tiket sama dengan urutan di Teams.
        $messages = array_reverse($messages);

        $max = max(1, (int) config('services.teams_sync.max_per_poll', 20));
        $skipped = 0;

        if (count($messages) > $max) {
            // Pagar ledakan: sisanya diambil putaran berikutnya, karena kursor
            // hanya maju sampai pesan terakhir yang benar-benar diserap.
            $skipped  = count($messages) - $max;
            $messages = array_slice($messages, 0, $max);
        }

        // Kursor berarti "semua yang sampai titik ini SUDAH diperiksa", bukan
        // "sudah diserap". Kalau ia hanya maju mengikuti pesan yang diserap,
        // chat yang isinya cuma pesan terbuang (kartu flow, pesan akun koneksi,
        // event sistem) tidak akan pernah memajukan kursornya dan dipindai ulang
        // tiap menit selamanya.
        //
        // KECUALI saat batch terpotong pagar ledakan: di situ kursor hanya boleh
        // maju sejauh yang benar-benar ditangani, kalau tidak sisanya hilang.
        $newest   = $skipped === 0 ? $this->newestOf($all) : null;
        $imported = 0;

        if ($messages !== []) {
            $already = TicketMessageTeams::existingIds(
                $chat->chat_id,
                array_column($messages, 'id')
            );

            foreach ($messages as $message) {
                $modified = $this->parseDate($message['lastModifiedDateTime'] ?? null);

                if (in_array($message['id'], $already, true)) {
                    // Sudah pernah diserap. Ini JALUR NORMAL, bukan anomali:
                    // pesan bergambar dimodifikasi Teams beberapa detik setelah
                    // dibuat, jadi ia muncul lagi di jendela polling berikutnya.
                    // Kursor tetap dimajukan supaya ia tidak muncul selamanya.
                    if ($modified && (!$newest || $modified > $newest)) {
                        $newest = $modified;
                    }
                    continue;
                }

                if ($this->store($chat, $message)) {
                    $imported++;
                }

                if ($modified && (!$newest || $modified > $newest)) {
                    $newest = $modified;
                }
            }
        }

        $chat->markPolled($newest);

        return ['imported' => $imported, 'skipped' => $skipped, 'failed' => false];
    }

    /**
     * Saring pesan manusia.
     *
     * Syaratnya ditulis POSITIF (`=== 'message'`), bukan membuang yang
     * `!= 'message'`. Pesan sistem ternyata datang sebagai
     * `messageType = 'unknownFutureValue'` — nama yang secara harfiah berarti
     * Microsoft akan menambah jenis baru. Syarat negatif akan meloloskan jenis
     * baru itu jadi internal note; syarat positif tidak.
     */
    private function humanMessages(array $values, string $chatId): array
    {
        return array_values(array_filter($values, function ($m) use ($chatId) {
            if (($m['messageType'] ?? null) !== self::HUMAN_MESSAGE_TYPE) {
                return false;
            }

            if (!empty($m['deletedDateTime'])) {
                return false;
            }

            if (self::isCardOnly($m)) {
                return false;
            }

            // Pesan tanpa penulis manusia — bot, aplikasi, atau konektor.
            //
            // Pagar KEDUA untuk gema arah keluar: kalau flow 7 kelak diubah jadi
            // "Post as: Flow bot", pesannya datang dengan `from.user` kosong dan
            // `from.application` terisi, sehingga pencocokan akun koneksi di
            // bawah tidak kena dan tiap note keluar tersimpan dobel lagi.
            // Aturan ini membuat perilakunya tidak bergantung pada pilihan di
            // designer Power Automate.
            //
            // Kehilangan yang disengaja: pesan dari bot Teams lain di grup tiket
            // juga tidak ikut tersinkron. Itu memang bukan diskusi manusia.
            if (empty($m['from']['user']['id'])) {
                return false;
            }

            if ($this->isFromConnectionAccount($chatId, $m)) {
                return false;
            }

            return !empty($m['id']);
        }));
    }

    /**
     * Pesan yang ditulis akun pemilik connection Power Automate — buang.
     *
     * **Ini pagar anti-gema arah keluar, dan tanpa ia setiap internal note yang
     * kita kirim ke Teams akan tersimpan DOBEL di tiketnya.**
     *
     * Alasannya: flow 7 memposting note kita ke Teams atas nama akun koneksi,
     * tapi TIDAK mengembalikan id pesan yang ia buat. Jadi kita tidak pernah tahu
     * id pesan kiriman kita sendiri, dan dedupe (`chat_id`,`teams_message_id`)
     * — yang menangani semua kasus lain — tidak bisa mengenalinya saat pesan itu
     * terbaca balik pada polling berikutnya.
     *
     * Aturan yang sama sekaligus membuang dua sumber derau lain yang juga
     * diposting flow atas nama akun ini: kartu "Tiket baru" flow 5 dan pesan
     * mention lead modulnya.
     *
     * Harga yang dibayar: manusia yang mengetik di group chat tiket memakai akun
     * itu tidak ikut tersinkron. Akun layanan, jadi wajar — tapi sengaja dicatat
     * di sini supaya tidak jadi misteri di kemudian hari.
     */
    private function isFromConnectionAccount(string $chatId, array $message): bool
    {
        $owner = mb_strtolower(trim((string) config('services.teams_sync.connection_email')));

        if ($owner === '') {
            return false;
        }

        $aadId = $message['from']['user']['id'] ?? null;

        if (!is_string($aadId) || $aadId === '') {
            return false;
        }

        if (!isset($this->rosterCache[$chatId])) {
            $this->rosterCache[$chatId] = $this->chats->roster($chatId);
        }

        $email = $this->rosterCache[$chatId][$aadId]['email'] ?? null;

        return is_string($email) && mb_strtolower(trim($email)) === $owner;
    }

    /** Stempel waktu termuda dari sekumpulan pesan Graph mentah. */
    private function newestOf(array $values): ?\DateTimeInterface
    {
        $newest = null;

        foreach ($values as $m) {
            $at = $this->parseDate($m['lastModifiedDateTime'] ?? null);

            if ($at && (!$newest || $at > $newest)) {
                $newest = $at;
            }
        }

        return $newest;
    }

    /**
     * Pesan yang isinya HANYA Adaptive Card — buang.
     *
     * Flow 5 memposting kartu "Tiket baru" ke grup tepat setelah grupnya dibuat,
     * dan kartu itu datang sebagai `messageType: 'message'` biasa dengan
     * `body.content` cuma `<attachment id="..."></attachment>`. Tanpa saringan
     * ini, SETIAP tiket baru langsung mendapat internal note kosong berisi
     * "(pesan Teams tanpa teks)" — sampah yang muncul di setiap tiket, bukan
     * kasus pinggiran.
     *
     * Saringannya sempit dengan sengaja: hanya pesan yang teksnya kosong DAN
     * seluruh lampirannya bertipe kartu. Orang yang mengetik komentar lalu
     * menempelkan kartu tetap terserap, karena teksnya tidak kosong.
     */
    private static function isCardOnly(array $message): bool
    {
        $attachments = $message['attachments'] ?? [];

        if ($attachments === []) {
            return false;
        }

        $body = (string) ($message['body']['content'] ?? '');
        $body = preg_replace('/<attachment\b[^>]*>.*?<\/attachment>/is', '', $body);
        $body = preg_replace('/<attachment\b[^>]*\/?>/i', '', (string) $body);

        if (trim(strip_tags((string) $body)) !== '') {
            return false;
        }

        foreach ($attachments as $attachment) {
            if (!str_starts_with((string) ($attachment['contentType'] ?? ''), 'application/vnd.microsoft.card')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Simpan satu pesan Teams sebagai internal note.
     *
     * TicketMessage dan TicketMessageTeams ditulis dalam SATU transaksi: baris
     * pendamping itulah satu-satunya pagar anti-loop, dan note yang tersimpan
     * tanpa pendampingnya akan diserap ulang tiap menit selamanya.
     */
    private function store(TicketTeamsChat $chat, array $message): bool
    {
        $aadId   = $message['from']['user']['id'] ?? null;
        $name    = $message['from']['user']['displayName'] ?? null;
        $html    = (string) ($message['body']['content'] ?? '');
        $isHtml  = ($message['body']['contentType'] ?? 'text') === 'html';

        [$employeeId, $email] = $this->resolveSender($chat->chat_id, is_string($aadId) ? $aadId : null);

        // Gambar Teams diunduh ke storage tiket dan <img>-nya diarahkan ulang ke
        // /storage/... Tanpa ini, src-nya tetap URL Graph yang butuh bearer token
        // dan pasti tampil rusak di UI tiket.
        $pendingImages = [];
        $html = $this->persistInlineImages($html, $chat, $message, $pendingImages);

        // Lampiran berkas (bukan gambar inline) disimpan sebagai TAUTAN, bukan
        // diunduh — lihat catatan di fileAttachments().
        $fileLinks = $this->fileAttachments($message);

        $text = $this->toPlainText($html, $isHtml);
        $notice = $this->attachmentNotice($pendingImages, $fileLinks);

        if (trim($text) === '') {
            // Gelembungnya nanti menampilkan message_html, tapi kolom `message`
            // tetap harus berisi sesuatu: ia yang dipakai preview daftar tiket
            // dan teks notifikasi, dan keduanya akan tampil blanko kalau kosong.
            $text = match (true) {
                $pendingImages !== [] => count($pendingImages) === 1
                    ? '(gambar dari Teams)'
                    : '(' . count($pendingImages) . ' gambar dari Teams)',
                $fileLinks !== []     => '(' . count($fileLinks) . ' berkas dari Teams)',
                // Pesan tanpa isi sama sekali (mis. stiker yang lolos saringan).
                // Tetap dicatat di tabel pendamping supaya tidak dicoba ulang
                // tiap menit sampai kiamat.
                default               => '(pesan Teams tanpa teks)',
            };
        }

        if ($notice !== null) {
            $text = trim($text . "\n\n" . $notice);
            $html .= '<p>' . e($notice) . '</p>';
        }

        // HTML disimpan hanya kalau memang ada yang perlu dipertahankan bentuknya
        // (gambar, atau format dari Teams). Pesan teks polos cukup kolom `message`
        // — konsisten dengan internal note biasa yang ditulis di EcoSystem.
        $storedHtml = ($isHtml && trim(strip_tags($html, '<img>')) !== '')
            ? MessageHtmlSanitizerService::sanitize($html)
            : null;

        try {
            DB::transaction(function () use ($chat, $message, $text, $storedHtml, $employeeId, $email, $name, $pendingImages, $fileLinks) {
                $note = TicketMessage::create([
                    'ticket_id'   => $chat->ticket_id,
                    'sender_type' => 'employee',
                    'sender_id'   => $employeeId,
                    'sender_email' => $email,
                    'sender_name' => $name ?: 'Teams',
                    'message'     => $text,
                    'message_html' => $storedHtml,
                    'is_internal_note' => true,
                    // Lihat catatan kelas: 'web' karena enum tidak punya 'teams',
                    // dan 'internal_note' karena UI menentukan bentuk gelembung
                    // dari nilai ini.
                    'channel'      => 'web',
                    'message_type' => 'internal_note',
                    'is_read_by_customer' => false,
                    'is_read_by_agent'    => false,
                ]);

                TicketMessageTeams::create([
                    'ticket_message_id' => $note->id,
                    'chat_id'           => $chat->chat_id,
                    'teams_message_id'  => (string) $message['id'],
                    'direction'         => TicketMessageTeams::DIRECTION_IN,
                ]);

                // Baris metadata gambar — byte-nya sudah di disk, DB hanya
                // menyimpan path. Ini invariant yang sama yang ditegakkan
                // InlineImageService untuk gambar dari editor dan dari email.
                foreach ($pendingImages as $img) {
                    TicketAttachment::create([
                        'ticket_id'        => $chat->ticket_id,
                        'message_id'       => $note->id,
                        'uploaded_by_type' => 'system',
                        'attachment_type'  => 'image',
                        'file_path'        => $img['file_path'],
                        'file_name'        => $img['file_name'],
                        'file_size'        => $img['file_size'],
                        'mime_type'        => $img['mime_type'],
                        'is_inline'        => true,
                    ]);
                }

                foreach ($fileLinks as $file) {
                    TicketAttachment::create([
                        'ticket_id'        => $chat->ticket_id,
                        'message_id'       => $note->id,
                        'uploaded_by_type' => 'system',
                        'attachment_type'  => 'link',
                        'link_url'         => $file['url'],
                        'link_title'       => $file['name'],
                        // file_name WAJIB ikut diisi walau ini lampiran tautan:
                        // kartu lampiran di UI menampilkan `file_name` sebagai
                        // judulnya (show.blade.php:4059), jadi membiarkannya NULL
                        // membuat kartunya bertuliskan "null".
                        'file_name'        => $file['name'],
                        'description'      => 'Lampiran dari Teams',
                        'is_inline'        => false,
                    ]);
                }

                DB::table('ticket')
                    ->where('ticket_id', $chat->ticket_id)
                    ->update([
                        'last_message_at'              => now(),
                        'last_internal_note_at'        => now(),
                        'last_internal_note_sender_id' => $employeeId,
                    ]);
            });
        } catch (\Throwable $e) {
            // Termasuk pelanggaran unique (chat_id, teams_message_id) kalau dua
            // putaran berjalan bersamaan — itu justru pagar yang bekerja.
            Log::warning('TeamsInbound: gagal menyimpan pesan Teams', [
                'ticket_id'        => $chat->ticket_id,
                'teams_message_id' => $message['id'] ?? null,
                'error'            => $e->getMessage(),
            ]);

            return false;
        }

        return true;
    }

    /**
     * AAD object id -> [employee_id, email].
     *
     * TIDAK memakai `GET /users/{id}`: app registration ini tidak punya
     * `User.Read.All` dan panggilan itu dijawab 403 (spike 17 Sep 2026).
     * Sumbernya roster chat, yang justru lebih sempit — hanya orang di chat
     * tiket ini, bukan seluruh direktori tenant.
     *
     * @return array{0: ?int, 1: ?string}
     */
    private function resolveSender(string $chatId, ?string $aadId): array
    {
        if ($aadId === null) {
            return [null, null];
        }

        if (!isset($this->rosterCache[$chatId])) {
            $this->rosterCache[$chatId] = $this->chats->roster($chatId);
        }

        $email = $this->rosterCache[$chatId][$aadId]['email'] ?? null;

        if (!is_string($email) || trim($email) === '') {
            // Orang yang sudah keluar dari chat, guest, atau akun eksternal.
            // Pesannya tetap masuk; hanya tidak tertaut ke employee.
            return [null, null];
        }

        return [$this->employeeIdByEmail($email), $email];
    }

    /**
     * Email kerja -> employee_id, memakai dua sumber yang sama dengan
     * PowerAutomateService::workEmails(): `employee_address.email_work` lebih
     * dulu, lalu `auth_users.email` sebagai cadangan.
     */
    private function employeeIdByEmail(string $email): ?int
    {
        $key = mb_strtolower(trim($email));

        if (array_key_exists($key, $this->employeeCache)) {
            return $this->employeeCache[$key];
        }

        $id = DB::table('employee_address')
            ->whereRaw('LOWER(email_work) = ?', [$key])
            ->orderBy('is_primary', 'desc')
            ->value('employee_id');

        if (!$id) {
            $id = DB::table('auth_users')
                ->whereRaw('LOWER(email) = ?', [$key])
                ->whereNotNull('employee_id')
                ->value('employee_id');
        }

        return $this->employeeCache[$key] = $id ? (int) $id : null;
    }

    /** HTML Teams -> teks polos yang enak dibaca di gelembung internal note. */
    private function toPlainText(string $body, bool $isHtml): string
    {
        // Tag <attachment id="..."></attachment> dibuang LEBIH DULU, sebelum
        // percabangan html/teks.
        //
        // Pesan berlampiran berkas datang dengan contentType "text" padahal
        // isinya memuat tag itu (terbukti 17 Sep 2026 dengan kiriman .xlsx), jadi
        // jalur strip_tags di bawah tidak pernah menyentuhnya dan tagnya bocor
        // mentah-mentah ke gelembung tiket. Lampirannya sendiri sudah ditangani
        // lewat fileAttachments(); tag ini cuma penanda posisi di badan pesan.
        $body = preg_replace('/<attachment\b[^>]*>.*?<\/attachment>/is', '', $body);
        $body = preg_replace('/<attachment\b[^>]*\/?>/i', '', (string) $body);
        $body = (string) $body;

        if (!$isHtml) {
            return trim($body);
        }

        // <br> dan </p> jadi baris baru dulu, supaya strip_tags tidak
        // menggabungkan paragraf jadi satu baris panjang.
        $text = preg_replace('/<br\s*\/?>/i', "\n", $body);
        $text = preg_replace('/<\/(p|div|li)>/i', "\n", (string) $text);
        $text = strip_tags((string) $text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Rapikan: maksimal dua baris kosong beruntun.
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return trim((string) $text);
    }

    /**
     * Unduh gambar Teams ke storage tiket dan arahkan ulang `<img src>`-nya.
     *
     * Gambar inline TIDAK muncul di `attachments` (array itu tetap kosong) — ia
     * ada sebagai `<img src=".../hostedContents/{id}/$value">` di dalam body HTML.
     * Terbukti di spike 17 Sep 2026.
     *
     * **Invariant yang ditegakkan di sini: byte gambar tidak pernah masuk DB.**
     * Byte ditulis ke public disk dengan konvensi path yang SAMA dengan
     * InlineImageService (`ticket-inline-images/{ticket_id}/{uuid}.{ext}`), dan
     * yang tersimpan di `message_html` hanya URL `/storage/...` yang pendek.
     *
     * URL Graph aslinya TIDAK boleh dibiarkan di HTML: ia butuh bearer token,
     * jadi browser yang membuka tiket pasti mendapat gambar rusak.
     *
     * @param  array<int,array{file_path:string,file_name:string,file_size:int,mime_type:string}>  $stored
     *         diisi oleh metode ini; caller memakainya untuk membuat baris TicketAttachment.
     */
    private function persistInlineImages(string $html, TicketTeamsChat $chat, array $message, array &$stored): string
    {
        if (stripos($html, 'hostedContents') === false) {
            return $html;
        }

        $maxBytes = max(1, (int) config('services.teams_sync.max_image_mb', 10)) * 1024 * 1024;
        $failed   = 0;

        $result = preg_replace_callback(
            '/<img\b([^>]*?)\ssrc="([^"]*hostedContents[^"]*)"([^>]*)>/i',
            function (array $m) use ($chat, $message, &$stored, $maxBytes, &$failed): string {
                $path = $this->graphPathOf($m[2]);

                if ($path === null) {
                    $failed++;
                    return '';
                }

                try {
                    $response = $this->graph->getRaw($path);
                } catch (TeamsGraphException $e) {
                    Log::warning('TeamsInbound: gagal mengunduh gambar Teams', [
                        'ticket_id'        => $chat->ticket_id,
                        'teams_message_id' => $message['id'] ?? null,
                        'status'           => $e->status,
                        'code'             => $e->graphCode,
                    ]);
                    $failed++;

                    // Buang <img>-nya. Membiarkannya berarti gambar rusak
                    // permanen di tiket; penandanya ditambahkan caller.
                    return '';
                }

                $binary = $response->body();
                // MIME HANYA ada di header respons — di daftar hostedContents,
                // contentBytes dan contentType keduanya null (terbukti di spike).
                $mime   = strtok((string) $response->header('Content-Type'), ';') ?: 'image/png';

                if ($binary === '' || strlen($binary) > $maxBytes) {
                    $failed++;
                    return '';
                }

                $ext      = $this->extForMime($mime);
                $filePath = 'ticket-inline-images/' . $chat->ticket_id . '/' . Str::uuid() . '.' . $ext;

                try {
                    Storage::disk('public')->put($filePath, $binary);
                } catch (\Throwable $e) {
                    Log::warning('TeamsInbound: gagal menyimpan gambar Teams ke storage', [
                        'ticket_id' => $chat->ticket_id,
                        'error'     => $e->getMessage(),
                    ]);
                    $failed++;

                    return '';
                }

                $stored[] = [
                    'file_path' => $filePath,
                    'file_name' => 'image.' . $ext,
                    'file_size' => strlen($binary),
                    'mime_type' => $mime,
                ];

                return '<img' . $m[1] . ' src="/storage/' . $filePath . '"' . $m[3] . '>';
            },
            $html
        );

        if ($failed > 0) {
            // Disimpan supaya attachmentNotice() bisa menyebut jumlahnya.
            $this->failedImages = $failed;
        }

        // preg_replace_callback mengembalikan null bila gagal internal — jangan
        // sampai isi pesannya hilang gara-gara itu.
        return $result ?? $html;
    }

    /**
     * Ubah URL Graph absolut jadi path relatif untuk TeamsGraphClient.
     *
     * Dilakukan lewat perbandingan prefix, bukan regex terhadap isi URL: id
     * hostedContents adalah base64 yang bisa memuat karakter apa saja, dan URL
     * yang bukan milik Graph tidak boleh ikut diambilkan token kita.
     */
    private function graphPathOf(string $url): ?string
    {
        $base = rtrim((string) config('services.microsoft_graph_teams.base_url'), '/') . '/';

        if (!str_starts_with($url, $base)) {
            return null;
        }

        return substr($url, strlen($base));
    }

    private function extForMime(string $mime): string
    {
        return match (strtolower(explode('/', $mime)[1] ?? 'png')) {
            'jpeg', 'jpg'    => 'jpg',
            'gif'            => 'gif',
            'webp'           => 'webp',
            'bmp'            => 'bmp',
            'svg+xml', 'svg' => 'svg',
            default          => 'png',
        };
    }

    /**
     * Lampiran BERKAS pada pesan Teams, sebagai tautan.
     *
     * **`attachments` TIDAK sama dengan "ada lampiran".** Data nyata dari grup
     * tiket (17 Sep 2026) memperlihatkan array itu justru paling sering berisi
     * `messageReference` (balasan yang mengutip pesan lain) dan
     * `forwardedMessageReference` (pesan diteruskan) — keduanya bukan lampiran
     * sama sekali. Menghitungnya sebagai lampiran membuat setiap balasan-mengutip
     * mendapat penanda "1 lampiran" yang menyesatkan.
     *
     * Berkas disimpan sebagai TAUTAN, bukan diunduh: file yang dibagikan di chat
     * Teams tinggal di OneDrive/SharePoint pengirimnya, sudah punya kendali akses
     * sendiri, dan menyalin byte-nya ke storage tiket berarti menduplikasi
     * dokumen sekaligus melepaskannya dari kendali itu.
     *
     * @return list<array{name:string,url:string}>
     */
    private function fileAttachments(array $message): array
    {
        $out = [];

        foreach ($message['attachments'] ?? [] as $attachment) {
            $type = (string) ($attachment['contentType'] ?? '');

            // Bukan lampiran: kutipan, penerusan, dan kartu.
            if (in_array($type, ['messageReference', 'forwardedMessageReference'], true)
                || str_starts_with($type, 'application/vnd.microsoft.card')) {
                continue;
            }

            $url = $attachment['contentUrl'] ?? null;

            if (!is_string($url) || $url === '') {
                continue;
            }

            $out[] = [
                'name' => (string) ($attachment['name'] ?: 'Berkas Teams'),
                'url'  => $url,
            ];
        }

        return $out;
    }

    /**
     * Penanda untuk hal-hal yang TIDAK ikut tersalin.
     *
     * Hanya menyebut yang benar-benar gagal dibawa. Gambar yang berhasil diunduh
     * tidak disebut — ia sudah tampil di gelembungnya, dan penanda "1 lampiran"
     * di bawah gambar yang jelas-jelas terlihat hanya membingungkan.
     */
    private function attachmentNotice(array $images, array $files): ?string
    {
        $parts = [];

        if ($this->failedImages > 0) {
            $parts[] = $this->failedImages . ' gambar gagal diambil dari Teams';
        }

        $this->failedImages = 0;

        if ($parts === []) {
            return null;
        }

        return '(' . implode(', ', $parts) . ')';
    }

    private function parseDate(?string $iso): ?\DateTimeInterface
    {
        if (!$iso) {
            return null;
        }

        try {
            return new \DateTimeImmutable($iso);
        } catch (\Throwable) {
            return null;
        }
    }
}

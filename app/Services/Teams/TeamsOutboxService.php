<?php

namespace App\Services\Teams;

use App\Models\TeamsOutboxItem;
use App\Models\TicketMessage;
use App\Models\TicketMessageTeams;
use App\Models\TicketTeamsChat;
use App\Services\PowerAutomateService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

/**
 * Internal note EcoSystem -> group chat Teams, lewat antrean + flow 7.
 *
 * Kenapa antrean dan bukan kirim langsung (design §5): user tidak ikut menunggu
 * Power Automate, kegagalan bisa di-retry, dan kegagalan TERLIHAT SEBAGAI DATA
 * (`status='failed'` + `last_error`) alih-alih menghilang jadi satu baris log —
 * pelajaran langsung dari kasus email jadi draft diam-diam.
 *
 * Kenapa lewat Power Automate dan bukan Graph seperti arah masuknya:
 * `POST /chats/{id}/messages` TIDAK tersedia untuk izin aplikasi (hanya
 * `Teamwork.Migrate.All`, untuk migrasi). Asimetri itu bukan pilihan gaya.
 *
 * Lihat docs/teams-chat-sync-design.md §8.
 */
class TeamsOutboxService
{
    public function __construct(private readonly PowerAutomateService $powerAutomate)
    {
    }

    public function isEnabled(): bool
    {
        return (bool) config('services.teams_sync.enabled')
            && (bool) config('services.teams_sync.outbound');
    }

    // ─── Mengantre ───────────────────────────────────────────────────────────

    /**
     * Masukkan satu internal note ke antrean kirim.
     *
     * Mengembalikan null untuk semua kondisi "tidak perlu dikirim" — itu jalur
     * normal, bukan kegagalan.
     */
    public function queue(TicketMessage $message): ?TeamsOutboxItem
    {
        if (!$this->isEnabled()) {
            return null;
        }

        if (!$this->shouldSend($message)) {
            return null;
        }

        $chat = TicketTeamsChat::where('ticket_id', $message->ticket_id)->first();

        if (!$chat || !$chat->sync_enabled) {
            return null;
        }

        // unique(ticket_message_id) di DB adalah penjaga sesungguhnya; cek ini
        // hanya supaya jalur normal tidak melempar exception.
        if (TeamsOutboxItem::where('ticket_message_id', $message->id)->exists()) {
            return null;
        }

        return TeamsOutboxItem::create([
            'ticket_id'         => $message->ticket_id,
            'chat_id'           => $chat->chat_id,
            'ticket_message_id' => $message->id,
            'payload'           => $this->buildPayload($message, $chat),
        ]);
    }

    /**
     * Masukkan pengumuman jadwal meeting ke antrean kirim.
     *
     * Jalur masuk KEDUA ke outbox, terpisah dari queue(). Sengaja tidak menumpang
     * shouldSend(): pesan meeting berjenis `sender_type='system'` atau balasan ke
     * customer, dan keduanya ada di daftar §8 "TIDAK dikirim ke Teams". Aturan itu
     * tetap benar untuk jalur note — meeting adalah pengecualian yang diminta
     * secara sadar, jadi ia lewat pintu sendiri, bukan dengan melonggarkan pintu
     * yang lain.
     *
     * Yang dikirim adalah pengumuman untuk TIM di group chat tiket — bukan
     * salinan undangan email ke customer. Isinya yang dibutuhkan orang yang mau
     * ikut: kapan, tautannya mana, dan konteks tiketnya.
     *
     * @param  array{notes:?string, link:?string, start:?\DateTimeInterface, end:?\DateTimeInterface}  $meeting
     */
    public function queueMeeting(TicketMessage $message, array $meeting): ?TeamsOutboxItem
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $chat = TicketTeamsChat::where('ticket_id', $message->ticket_id)->first();

        if (!$chat || !$chat->sync_enabled) {
            return null;
        }

        if (TeamsOutboxItem::where('ticket_message_id', $message->id)->exists()) {
            return null;
        }

        return TeamsOutboxItem::create([
            'ticket_id'         => $message->ticket_id,
            'chat_id'           => $chat->chat_id,
            'ticket_message_id' => $message->id,
            // Blok `card` membuat flow 7 mengambil cabang "Post card"; jalur note
            // yang tidak membawanya tetap lewat "Post message" (keputusan meeting
            // 18 Sep 2026 — jadwal meeting perlu tampil menonjol dan punya tombol
            // gabung, sementara note justru hidup dari quote-reply).
            //
            // `message_html` hasil wrap() tetap ikut: cadangan kalau kartunya
            // ditolak konektor, dan membuat payload meeting tetap sebentuk dengan
            // payload note.
            'payload'           => $this->wrap($this->meetingText($message, $meeting), $message, $chat)
                + ['card' => $this->meetingCard($message, $meeting)],
        ]);
    }

    /**
     * Adaptive Card pengumuman meeting, sudah dalam bentuk JSON string.
     *
     * String, bukan array: field *Adaptive Card* pada aksi Teams menerima teks
     * JSON, jadi designer cukup menempelkan satu ekspresi
     * `triggerBody()?['card']` tanpa perlu `string()` atau `json()`.
     *
     * Tautan meeting jadi TOMBOL, bukan baris teks — itu inti permintaannya:
     * URL mentah di badan pesan panjang dan tidak terbaca.
     */
    private function meetingCard(TicketMessage $message, array $meeting): string
    {
        $facts = [];

        if ($when = $this->meetingWhen($meeting)) {
            $facts[] = ['title' => 'Waktu', 'value' => $when . ' WIB'];
        }

        if ($message->sender_name) {
            $facts[] = ['title' => 'Oleh', 'value' => (string) $message->sender_name];
        }

        $body = [
            [
                'type'   => 'TextBlock',
                'text'   => 'Meeting dijadwalkan',
                'weight' => 'Bolder',
                'size'   => 'Medium',
            ],
        ];

        if ($facts !== []) {
            $body[] = ['type' => 'FactSet', 'facts' => $facts];
        }

        if (!empty($meeting['notes'])) {
            $body[] = [
                'type' => 'TextBlock',
                'text' => trim((string) $meeting['notes']),
                'wrap' => true,
            ];
        }

        $card = [
            'type'    => 'AdaptiveCard',
            '$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json',
            'version' => '1.4',
            'body'    => $body,
        ];

        if (!empty($meeting['link'])) {
            $card['actions'] = [[
                'type'  => 'Action.OpenUrl',
                'title' => 'Gabung Meeting',
                'url'   => (string) $meeting['link'],
            ]];
        }

        return json_encode($card, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Rentang waktu meeting dalam zona waktu aplikasi, atau null kalau jadwalnya
     * tidak diketahui. Dipakai kartu maupun teks cadangannya.
     */
    private function meetingWhen(array $meeting): ?string
    {
        $start = $meeting['start'] ?? null;

        if (!$start) {
            return null;
        }

        $tz = config('app.timezone');
        $s  = Carbon::parse($start)->setTimezone($tz);
        $when = $s->format('D, d M Y H:i');

        if ($end = ($meeting['end'] ?? null)) {
            $e = Carbon::parse($end)->setTimezone($tz);
            // Tanggal tidak diulang kalau meetingnya selesai di hari yang sama.
            $when .= '–' . $e->format($e->isSameDay($s) ? 'H:i' : 'D, d M Y H:i');
        }

        return $when;
    }

    /**
     * Teks pengumuman meeting — sejak 18 Sep 2026 hanya CADANGAN: yang tampil di
     * grup adalah {@see meetingCard()}. Tetap dikirim di `message_html` supaya
     * payload meeting sebentuk dengan payload note, dan supaya ada yang bisa
     * diposting kalau kartunya suatu saat ditolak konektor.
     *
     * Jam ditampilkan dalam zona waktu aplikasi, bukan UTC: yang membacanya orang
     * di grup, dan "14:00" yang ternyata UTC adalah jenis kesalahan yang baru
     * ketahuan saat ada yang telat meeting.
     */
    private function meetingText(TicketMessage $message, array $meeting): string
    {
        $when = $this->meetingWhen($meeting);

        $lines = array_filter([
            'Meeting dijadwalkan · EcoSystem',
            $message->sender_name ? 'Oleh: ' . $message->sender_name : null,
            '',
            $when ? 'Waktu: ' . $when . ' WIB' : null,
            !empty($meeting['link']) ? 'Link: ' . $meeting['link'] : null,
            !empty($meeting['notes']) ? 'Catatan: ' . trim((string) $meeting['notes']) : null,
        ], static fn ($l) => $l !== null);

        return implode("\n", $lines);
    }

    /**
     * Note ini layak dikirim ke Teams?
     *
     * Daftar penolakannya mengikuti §8 "Yang TIDAK dikirim ke Teams", dan yang
     * pertama adalah pagar anti-loop: note yang ASALNYA dari Teams tidak boleh
     * dikirim balik ke Teams.
     */
    private function shouldSend(TicketMessage $message): bool
    {
        // Balasan ke customer, pesan masuk dari customer, pesan sistem/SLA.
        if (!$message->is_internal_note) {
            return false;
        }

        if ($message->sender_type !== 'employee') {
            return false;
        }

        if ($message->is_deleted) {
            return false;
        }

        // Berasal dari Teams -> jangan dikirim balik. Ini pagar anti-loop yang
        // tidak bergantung pada siapa pemanggilnya.
        $bridge = TicketMessageTeams::where('ticket_message_id', $message->id)->first();

        if ($bridge && $bridge->direction === TicketMessageTeams::DIRECTION_IN) {
            return false;
        }

        return trim((string) ($message->message ?: strip_tags((string) $message->message_html))) !== '';
    }

    /**
     * Bentuk pesan di Teams — teks, bukan Adaptive Card.
     *
     * Kartu tampil buruk di HP dan tidak bisa di-quote-reply; diskusi tiket di
     * group chat justru hidup dari balas-membalas. Teksnya dirakit SEKARANG dan
     * disimpan, bukan dirakit ulang saat flush, supaya yang terkirim persis isi
     * note saat ditulis walau note-nya kemudian diedit.
     */
    private function buildPayload(TicketMessage $message, TicketTeamsChat $chat): array
    {
        // html_entity_decode WAJIB. Kolom `message` menyimpan hasil strip_tags,
        // dan strip_tags TIDAK menyentuh entitas: note berisi "->" tersimpan
        // sebagai "-&gt;". Tanpa decode, e() di wrap() meng-escape "&"-nya lagi
        // dan yang sampai di Teams jadi "-&amp;gt;". Terbukti 23 Sep 2026.
        $body = trim(html_entity_decode(
            (string) ($message->message ?: strip_tags((string) $message->message_html)),
            ENT_QUOTES | ENT_HTML5
        ));
        $text = ($message->sender_name ?: 'EcoSystem') . " · EcoSystem\n" . $body;

        $links = $this->attachmentLinks($message);

        return $this->wrap(
            $text,
            $message,
            $chat,
            $this->attachmentHtml($links),
            $this->attachmentText($links)
        );
    }

    /**
     * Lampiran satu note, lengkap dengan tautan bertanda tangan yang bisa dibuka
     * dari Teams TANPA login EcoSystem.
     *
     * Sebelum 23 Sep 2026 yang dikirim hanya penanda "(2 lampiran — lihat
     * tiket)"; sejak itu lampirannya benar-benar ikut. Byte-nya tetap TIDAK
     * dikirim ke Teams — yang dikirim tautan ke proxy EcoSystem, cerminan dari
     * arah masuk yang juga menyimpan berkas Teams sebagai tautan, bukan salinan.
     *
     * Gambar inline ikut (keputusan 23 Sep 2026). Konsekuensinya note dengan
     * banyak tangkapan layar jadi banyak gambar di grup — itu memang yang
     * diinginkan: yang dilihat orang di grup sama dengan yang dilihat di tiket.
     *
     * Lampiran yang tidak bisa dilayani proxy (mis. tautan luar) dilewati
     * daripada mengirim tautan yang pasti gagal dibuka.
     *
     * @return list<array{name:string,url:string,is_image:bool}>
     */
    private function attachmentLinks(TicketMessage $message): array
    {
        $rows = DB::table('ticket_attachment')
            ->where('message_id', $message->id)
            ->orderBy('id')
            ->get(['id', 'file_name', 'link_title', 'file_path', 'mime_type', 'attachment_type',
                   'graph_message_id', 'graph_attachment_id']);

        $days = max(0, (int) config('services.teams_sync.attachment_link_days', 0));
        $out  = [];

        foreach ($rows as $row) {
            // Hanya yang benar-benar bisa dilayani AttachmentController.
            $servable = $row->file_path
                || ($row->graph_message_id && $row->graph_attachment_id);

            if (!$servable) {
                continue;
            }

            $out[] = [
                'name'     => (string) ($row->file_name ?: $row->link_title ?: 'Lampiran'),
                'url'      => $days > 0
                    ? URL::temporarySignedRoute('attachments.teams', now()->addDays($days), ['id' => $row->id])
                    : URL::signedRoute('attachments.teams', ['id' => $row->id]),
                'is_image' => $row->attachment_type === 'image'
                    || str_starts_with((string) $row->mime_type, 'image/'),
            ];
        }

        return $out;
    }

    /**
     * Blok HTML lampiran: gambar ditampilkan langsung, sisanya jadi tautan.
     *
     * Gambar dibungkus `<a>` supaya diklik membuka versi penuhnya — di grup ia
     * tampil kecil, dan tangkapan layar error justru perlu dizoom.
     *
     * **Apakah Teams merender `<img>` ber-URL eksternal di pesan chat belum
     * terbukti** (23 Sep 2026): konektor kadang membuangnya. Kalau ternyata
     * dibuang, tautan berkasnya tetap ada di `message`, dan jalur penggantinya
     * adalah mengirim note bergambar sebagai Adaptive Card lewat cabang yang
     * sudah ada di flow 7 — lihat docs/teams-sync-parity-design.md §6.
     *
     * @param  list<array{name:string,url:string,is_image:bool}>  $links
     */
    private function attachmentHtml(array $links): string
    {
        $parts = [];

        foreach ($links as $link) {
            $line = '';

            // Gambar tampil langsung di badan pesan. TERBUKTI DIRENDER Teams
            // (uji 23 Sep 2026), dengan satu syarat yang mahal dipelajari:
            // JANGAN dibungkus <a href>. Percobaan pertama memakai
            // <a href><img></a> dan Teams membuang SELURUH bloknya — bukan
            // menampilkan gambar rusak, hilang sama sekali. Tanpa anchor, <img>
            // lolos. Karena itu "klik untuk membuka penuh" diwakili baris URL di
            // bawah, bukan dengan membungkus gambarnya.
            if ($link['is_image']) {
                $line .= '<img src="' . e($link['url']) . '" alt="' . e($link['name'])
                    . '" width="400" style="max-width:400px"><br>';
            }

            // URL TELANJANG, bukan <a href> — lihat catatan di atas. Teams
            // mengubahnya sendiri jadi tautan yang bisa diklik. Nama berkas ikut
            // supaya orang tahu apa yang akan dibuka sebelum mengkliknya, dan
            // baris ini juga yang menjadi jalan membuka gambar ukuran penuh.
            $line .= e($link['name']) . ': ' . e($link['url']);

            $parts[] = $line;
        }

        return implode('<br>', $parts);
    }

    /**
     * Versi teks polos daftar lampiran — cadangan kalau field Message suatu saat
     * berhenti diperlakukan sebagai HTML, dan isi kolom `note.text` di payload.
     *
     * @param  list<array{name:string,url:string,is_image:bool}>  $links
     */
    private function attachmentText(array $links): string
    {
        return implode("\n", array_map(
            static fn ($l) => $l['name'] . ' → ' . $l['url'],
            $links
        ));
    }

    /**
     * Bungkus teks jadi payload flow 7.
     *
     * Dipakai KEDUA jalur (internal note dan pengumuman meeting) supaya bentuk
     * payloadnya tidak pernah menyimpang satu sama lain — flow 7 hanya tahu satu
     * bentuk, dan bentuk itu didefinisikan di sini saja.
     *
     * **Tidak ada baris "nomor tiket → tautan" di badan pesan** (keputusan
     * meeting 18 Sep 2026). Pesan ini diposting di dalam group chat tiket yang
     * bersangkutan, jadi nomornya hanya mengulang nama grup, dan tautannya
     * tampil sebagai URL mentah yang panjang. Blok `ticket` di payload TETAP
     * membawa `number` dan `url` — keduanya dipakai pagar staging dan berguna
     * kalau suatu saat flow ingin menampilkannya sendiri.
     */
    private function wrap(
        string $text,
        TicketMessage $message,
        TicketTeamsChat $chat,
        string $htmlExtra = '',
        string $textExtra = ''
    ): array {
        $ticket = DB::table('ticket')
            ->where('ticket_id', $message->ticket_id)
            ->first(['ticket_number', 'description', 'submitted_by_email', 'submitted_by_name']);

        $number = $ticket->ticket_number ?? (string) $message->ticket_id;
        $url    = rtrim((string) config('app.url'), '/') . '/tickets/' . $message->ticket_id;

        // Dua tambahan terpisah, bukan satu: badan note DI-ESCAPE (teks ketikan
        // manusia), sedangkan blok lampiran adalah HTML yang memang harus lolos
        // apa adanya. Menggabungkannya lebih dulu akan membuat <img> ikut
        // ter-escape dan tampil sebagai teks mentah.
        $body = rtrim($text);
        $html = nl2br(e($body), false);

        if ($htmlExtra !== '') {
            $html .= '<br><br>' . $htmlExtra;
        }

        if ($textExtra !== '') {
            $body = rtrim($body . "\n\n" . $textExtra);
        }

        return [
            'chat'    => ['id' => $chat->chat_id],
            // Field "Message" pada aksi Teams "Post message in a chat or channel"
            // adalah HTML: "\n" di dalamnya diabaikan dan pesan tiga baris jadi
            // gepeng satu baris. Versi HTML dirakit di sini supaya flow tidak
            // perlu ekspresi replace() yang rapuh di designer.
            //
            // Nama penulis dan isi note di-escape: keduanya teks yang diketik
            // manusia, dan tanda < atau & di dalamnya akan merusak HTML-nya.
            'message_html' => $html,
            'ticket'  => [
                'id'      => (int) $message->ticket_id,
                'number'  => $number,
                'subject' => $ticket->description ?? null,
                'url'     => $url,
                // WAJIB ikut. Pagar staging
                // (POWER_AUTOMATE_ALLOWED_SUBMITTERS) membaca email pelapor dari
                // sini, dan ia fail-closed: payload yang pelapornya tidak
                // teridentifikasi ikut dihadang. Tanpa blok ini, SELURUH note
                // keluar akan diblokir diam-diam di server staging.
                'submitted_by' => [
                    'name'  => $ticket->submitted_by_name ?? null,
                    'email' => $ticket->submitted_by_email ?? null,
                ],
            ],
            'note'    => [
                'id'     => (int) $message->id,
                'author' => $message->sender_name,
                'text'   => $body,
            ],
            'message' => $body,
        ];
    }

    // ─── Mengirim ────────────────────────────────────────────────────────────

    /** @return iterable<TeamsOutboxItem> */
    public function due(): iterable
    {
        return TeamsOutboxItem::query()
            ->pending()
            ->orderBy('created_at')
            ->limit(max(1, (int) config('services.teams_sync.outbox_batch', 25)))
            ->get();
    }

    /**
     * Kirim satu item ke flow 7.
     *
     * Baris `ticket_message_teams` arah `out` ditulis HANYA setelah flow
     * menerima. Ditulis lebih awal, note yang gagal terkirim akan terlihat
     * seolah sudah ada di Teams.
     */
    public function send(TeamsOutboxItem $item): bool
    {
        $maxAttempts = max(1, (int) config('services.teams_sync.outbox_max_attempts', 5));

        if (!$this->powerAutomate->isFlowReady(PowerAutomateService::FLOW_TEAMS_POST_MESSAGE)) {
            $item->markAttemptFailed('Flow 7 (teams_post_message) belum dikonfigurasi.', $maxAttempts);

            return false;
        }

        $item->status = TeamsOutboxItem::STATUS_SENDING;
        $item->save();

        $ok = $this->powerAutomate->dispatch(
            PowerAutomateService::FLOW_TEAMS_POST_MESSAGE,
            $item->payload
        );

        if (!$ok) {
            // dispatch() sudah mencatat status + body errornya ke log; di sini
            // yang penting kegagalannya tersimpan sebagai DATA supaya terlihat
            // tanpa membaca log.
            $item->markAttemptFailed('Power Automate menolak atau tidak dapat dihubungi.', $maxAttempts);

            Log::warning('TeamsOutbox: gagal mengirim note ke Teams', [
                'outbox_id'  => $item->id,
                'ticket_id'  => $item->ticket_id,
                'attempts'   => $item->attempts,
                'status'     => $item->status,
            ]);

            return false;
        }

        DB::transaction(function () use ($item) {
            $item->markSent();

            // teams_message_id sengaja NULL: flow 7 tidak mengembalikan id pesan
            // yang ia buat. Barisnya tetap ditulis sebagai jejak arah keluar —
            // yang menahan gema pesan ini saat terbaca balik adalah aturan
            // "abaikan pesan dari akun koneksi" di TeamsInboundService, bukan
            // baris ini.
            TicketMessageTeams::firstOrCreate(
                ['ticket_message_id' => $item->ticket_message_id],
                [
                    'chat_id'          => $item->chat_id,
                    'teams_message_id' => null,
                    'direction'        => TicketMessageTeams::DIRECTION_OUT,
                ]
            );
        });

        return true;
    }
}

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
            'payload'           => $this->wrap($this->meetingText($message, $meeting), $message, $chat),
        ]);
    }

    /**
     * Teks pengumuman meeting.
     *
     * Jam ditampilkan dalam zona waktu aplikasi, bukan UTC: yang membacanya orang
     * di grup, dan "14:00" yang ternyata UTC adalah jenis kesalahan yang baru
     * ketahuan saat ada yang telat meeting.
     */
    private function meetingText(TicketMessage $message, array $meeting): string
    {
        $tz    = config('app.timezone');
        $start = $meeting['start'] ?? null;
        $end   = $meeting['end'] ?? null;

        $when = null;
        if ($start) {
            $s    = Carbon::parse($start)->setTimezone($tz);
            $when = $s->format('D, d M Y H:i');

            if ($end) {
                $e = Carbon::parse($end)->setTimezone($tz);
                // Tanggal tidak diulang kalau meetingnya selesai di hari yang sama.
                $when .= '–' . $e->format($e->isSameDay($s) ? 'H:i' : 'D, d M Y H:i');
            }
        }

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
        $body = trim((string) ($message->message ?: strip_tags((string) $message->message_html)));

        $attachments = DB::table('ticket_attachment')
            ->where('message_id', $message->id)
            ->where('is_inline', false)
            ->count();

        if ($attachments > 0) {
            // Tidak ada byte yang dikirim ke Teams pada fase ini (design §9) —
            // cukup beri tahu bahwa lampirannya ada di tiket.
            $body .= "\n\n({$attachments} lampiran — lihat tiket)";
        }

        $text = ($message->sender_name ?: 'EcoSystem') . " · EcoSystem\n" . $body;

        return $this->wrap($text, $message, $chat);
    }

    /**
     * Bungkus teks jadi payload flow 7, lengkap dengan baris tautan tiket.
     *
     * Dipakai KEDUA jalur (internal note dan pengumuman meeting) supaya bentuk
     * payloadnya tidak pernah menyimpang satu sama lain — flow 7 hanya tahu satu
     * bentuk, dan bentuk itu didefinisikan di sini saja.
     */
    private function wrap(string $text, TicketMessage $message, TicketTeamsChat $chat): array
    {
        $ticket = DB::table('ticket')
            ->where('ticket_id', $message->ticket_id)
            ->first(['ticket_number', 'description', 'submitted_by_email', 'submitted_by_name']);

        $number = $ticket->ticket_number ?? (string) $message->ticket_id;
        $url    = rtrim((string) config('app.url'), '/') . '/tickets/' . $message->ticket_id;

        // Isi tanpa baris tautan — dipakai di blok `note` supaya konsumen payload
        // yang ingin teksnya saja tidak perlu memotong baris terakhir sendiri.
        $body = rtrim($text);
        $text = $body . "\n\n" . $number . ' → ' . $url;

        return [
            'chat'    => ['id' => $chat->chat_id],
            // Field "Message" pada aksi Teams "Post message in a chat or channel"
            // adalah HTML: "\n" di dalamnya diabaikan dan pesan tiga baris jadi
            // gepeng satu baris. Versi HTML dirakit di sini supaya flow tidak
            // perlu ekspresi replace() yang rapuh di designer.
            //
            // Nama penulis dan isi note di-escape: keduanya teks yang diketik
            // manusia, dan tanda < atau & di dalamnya akan merusak HTML-nya.
            'message_html' => nl2br(e($text), false),
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
            'message' => $text,
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

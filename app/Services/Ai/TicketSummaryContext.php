<?php

namespace App\Services\Ai;

use App\Models\Ticket;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Merakit konteks satu tiket untuk fitur "AI Summarize" di halaman detail tiket.
 *
 * Dua tanggung jawab, dan keduanya harus dilihat berpasangan:
 *
 *   build()       — teks yang dikirim ke model.
 *   fingerprint() — sidik jari ISI tiket, dipakai sebagai bagian kunci cache.
 *
 * SIDIK JARI, BUKAN last_message_at. Ringkasan wajib ikut basi setiap kali ada
 * pembaruan apa pun pada tiket — bukan cuma saat ada pesan baru. Karena itu
 * fingerprint() mengambil MAX(updated_at) DAN COUNT(*) dari setiap sumber yang
 * ikut masuk ke build(). COUNT ikut dihitung sebab penghapusan baris menurunkan
 * jumlah tanpa menaikkan MAX(updated_at) — tanpa itu, menghapus sebuah activity
 * log akan menyisakan ringkasan lama yang masih menyebutnya.
 *
 * KONSEKUENSINYA: setiap sumber baru yang ditambahkan ke build() WAJIB
 * ditambahkan juga ke fingerprint(), kalau tidak perubahannya tak akan pernah
 * memicu regenerasi.
 */
class TicketSummaryContext
{
    /** Ambil kepala + ekor thread bila pesannya lebih banyak dari ini. */
    private const MAX_MESSAGES = 60;
    private const HEAD_MESSAGES = 20;

    private const MAX_ACTIVITY_LOGS = 100;
    private const MAX_DELIVERABLES = 20;

    /** Plafon karakter per potong teks, supaya satu email raksasa tidak menelan seluruh jendela konteks. */
    private const MAX_MESSAGE_CHARS = 2000;
    private const MAX_DELIVERABLE_CHARS = 1500;

    /**
     * Hash isi tiket berikut seluruh sumber yang ikut diringkas. Berubah begitu
     * ada baris ditambah, diubah, atau dihapus di salah satunya.
     */
    public function fingerprint(Ticket $ticket): string
    {
        $parts = [
            'ticket:' . optional($ticket->updated_at)->getTimestamp(),
            'status:' . $ticket->status,
        ];

        $sources = [
            'message'     => 'ticket_message',
            'activity'    => 'ticket_activity_logs',
            'deliverable' => 'ticket_deliverables',
            'member'      => 'ticket_member',
        ];

        foreach ($sources as $label => $table) {
            $row = DB::table($table)
                ->where('ticket_id', $ticket->ticket_id)
                ->selectRaw('COUNT(*) AS c, MAX(updated_at) AS m')
                ->first();

            $parts[] = $label . ':' . ($row->c ?? 0) . ':' . ($row->m ?? '-');
        }

        return substr(hash('sha256', implode('|', $parts)), 0, 32);
    }

    /**
     * Teks konteks lengkap untuk model. Labelnya bahasa Inggris, isinya apa
     * adanya (thread tiket di sini campur Indonesia/Inggris).
     */
    public function build(Ticket $ticket): string
    {
        $ticket->loadMissing(['customer.basicData', 'ticketLead.basicData', 'members.basicData']);

        // Diambil SEKALI di sini dan dibagi ke technicalSignalsSection() +
        // messagesSection(): yang pertama butuh SELURUH pesan (sebelum
        // head/tail truncation) supaya TCODE/SAP Note yang kebetulan ada di
        // pesan yang nanti terpotong tetap terdeteksi, yang kedua tetap
        // memotongnya untuk ditampilkan seperti biasa.
        $messages = $this->fetchMessages($ticket);

        $sections = [
            $this->headerSection($ticket),
            $this->technicalSignalsSection($ticket, $messages),
            $this->messagesSection($messages),
            $this->activitySection($ticket),
            $this->deliverablesSection($ticket),
        ];

        return implode("\n\n", array_filter($sections));
    }

    private function headerSection(Ticket $ticket): string
    {
        $members = $ticket->members
            ->map(fn($m) => $this->employeeName($m))
            ->filter(fn($name) => '-' !== $name)
            ->implode(', ');

        $leadName = $this->employeeName($ticket->ticketLead);

        $lines = [
            'Ticket number: ' . ($ticket->ticket_number ?: '-'),
            'Subject: ' . ($ticket->name ?: '-'),
            // display_name = basicData->name_1, dengan fallback ke email; nama
            // customer bukan kolom di tabel `customer`.
            'Customer: ' . ($ticket->customer?->display_name ?? '-'),
            'Module: ' . ($ticket->module_names ?: ($ticket->module ?: '-')),
            'Type: ' . ($ticket->ticket_type ?: '-'),
            'Priority: ' . ($ticket->ticket_priority ?: '-'),
            'Scale: ' . ($ticket->scale ?: '-'),
            'Status: ' . ($ticket->status ?: '-'),
            'Opened: ' . (optional($ticket->created_at)->format('d M Y') ?: '-'),
            'Start date: ' . (optional($ticket->start_date)->format('d M Y') ?: '-'),
            'End date: ' . (optional($ticket->end_date)->format('d M Y') ?: '-'),
            'Man days: ' . ($ticket->man_days ?: '-'),
            'Progress: ' . ($ticket->progress_percentage ?: 0) . '%'
                . ($ticket->progress_note ? ' - ' . $ticket->progress_note : ''),
            'Ticket lead: ' . $leadName,
            'Team members: ' . ($members ?: '-'),
        ];

        $header = "## TICKET\n" . implode("\n", $lines);

        if ($ticket->description) {
            $header .= "\n\nReported problem (description field):\n"
                . $this->clean($ticket->description, self::MAX_MESSAGE_CHARS);
        }

        return $header;
    }

    /**
     * Nama pegawai untuk dibaca model.
     *
     * Tabel `employee` hanya menyimpan eci dan kapasitas — namanya ada di
     * relasi basicData. Urutan fallback mengikuti yang sudah dipakai
     * TicketController: nama lengkap, lalu nick_name, baru menyerah.
     */
    private function employeeName(?object $employee): string
    {
        $basic = $employee?->basicData;
        if (!$basic) {
            return '-';
        }

        $full = trim(($basic->first_name ?? '') . ' ' . ($basic->last_name ?? ''));

        return $full ?: ($basic->nick_name ?: '-');
    }

    /**
     * Dipanggil SEKALI dari build() — lihat komentar di sana untuk kenapa
     * dibagi ke technicalSignalsSection() DAN messagesSection().
     */
    private function fetchMessages(Ticket $ticket): Collection
    {
        return $ticket->messages()
            ->where('is_deleted', false)
            ->where(function ($q) {
                $q->whereNull('email_status')->orWhere('email_status', '!=', 'failed');
            })
            ->orderBy('created_at')
            ->get(['sender_type', 'sender_name', 'message', 'is_internal_note', 'channel', 'created_at']);
    }

    /**
     * Sorotan TCODE & nomor SAP Note/KBA yang disebut di tiket ini, ditaruh
     * di PALING ATAS context — dihitung dari SELURUH pesan (bukan yang sudah
     * dipotong messagesSection()), supaya sinyal teknis yang kebetulan ada di
     * pesan yang nanti terpotong tetap sampai ke model.
     *
     * Presisi disengaja lebih diutamakan daripada recall: dua pola di bawah
     * (extractTcodes/extractSapNotes) HANYA menangkap penyebutan yang
     * eksplisit berlabel ("tcode SE38", "SAP Note 1234567") — menebak dari
     * kata uppercase acak akan menangkap singkatan lain (PIC, CEO, dst) dan
     * section ini jadi lebih menyesatkan daripada membantu. Karena itu
     * daftarnya BOLEH kosong/tidak lengkap; thread penuh di bawah tetap jadi
     * sumber utama, ini cuma sorotan tambahan.
     */
    private function technicalSignalsSection(Ticket $ticket, Collection $messages): string
    {
        $haystack = implode("\n", array_filter([
            (string) $ticket->description,
            $messages->pluck('message')->implode("\n"),
        ]));

        $tcodes = $this->extractTcodes($haystack);
        $notes = $this->extractSapNotes($haystack);

        if (empty($tcodes) && empty($notes)) {
            return '';
        }

        $lines = [];
        if (!empty($tcodes)) {
            $lines[] = 'TCODE mentioned in this ticket: ' . implode(', ', $tcodes);
        }
        if (!empty($notes)) {
            $lines[] = 'SAP Note/KBA number mentioned in this ticket: ' . implode(', ', $notes);
        }

        return "## DETECTED TECHNICAL REFERENCES (auto-extracted, verify against the full thread below)\n"
            . implode("\n", $lines);
    }

    /** @return string[] kode unik, huruf besar, urutan kemunculan */
    private function extractTcodes(string $text): array
    {
        preg_match_all('/\b(?:t-?code|transaction(?:\s+code)?)\s*:?\s*([A-Z][A-Z0-9]{1,9})\b/i', $text, $matches);

        return array_values(array_unique(array_map('strtoupper', $matches[1] ?? [])));
    }

    /** @return string[] nomor unik, urutan kemunculan */
    private function extractSapNotes(string $text): array
    {
        preg_match_all('/\b(?:SAP\s+)?(?:Note|KBA)\s*#?\s*(\d{6,8})\b/i', $text, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    /**
     * Thread percakapan, TERMASUK internal note (fitur ini employee-side; catatan
     * internal justru bagian paling informatif soal cara penyelesaian).
     *
     * Yang dibuang: pesan terhapus, dan pesan keluar yang gagal terkirim -
     * bounce/NDR bukan bagian dari cerita penyelesaian dan membuat model
     * menyimpulkan hal yang salah.
     */
    private function messagesSection(Collection $messages): string
    {
        if ($messages->isEmpty()) {
            return '';
        }

        $total = $messages->count();
        $omitted = 0;

        if ($total > self::MAX_MESSAGES) {
            $tailCount = self::MAX_MESSAGES - self::HEAD_MESSAGES;
            $omitted = $total - self::MAX_MESSAGES;
            $messages = $messages->take(self::HEAD_MESSAGES)
                ->concat($messages->slice($total - $tailCount))
                ->values();
        }

        $lines = [];
        $index = 0;

        foreach ($messages as $m) {
            if ($omitted > 0 && self::HEAD_MESSAGES === $index) {
                $lines[] = "[... {$omitted} messages in the middle of the thread omitted ...]";
            }
            ++$index;

            $body = $this->clean((string) $m->message, self::MAX_MESSAGE_CHARS);
            if ('' === $body) {
                continue;
            }

            $lines[] = sprintf(
                "[%s] %s (%s)%s:\n%s",
                optional($m->created_at)->format('d M Y H:i'),
                $m->sender_name ?: ucfirst((string) $m->sender_type),
                $m->sender_type,
                $m->is_internal_note ? ' [INTERNAL NOTE]' : '',
                $body
            );
        }

        return "## CONVERSATION THREAD ({$total} messages)\n" . implode("\n\n", $lines);
    }

    private function activitySection(Ticket $ticket): string
    {
        // Nama pegawai TIDAK ada di tabel `employee` (isinya cuma eci/kapasitas);
        // first_name/last_name tinggal di employee_basic_data.
        $logs = DB::table('ticket_activity_logs as l')
            ->leftJoin('employee_basic_data as e', 'e.employee_id', '=', 'l.employee_id')
            ->where('l.ticket_id', $ticket->ticket_id)
            ->orderBy('l.activity_date')
            ->orderBy('l.id')
            ->limit(self::MAX_ACTIVITY_LOGS)
            ->get(['l.activity_date', 'l.activity', 'e.first_name', 'e.last_name']);

        if ($logs->isEmpty()) {
            return '';
        }

        $lines = $logs->map(function ($l) {
            $who = trim(($l->first_name ?? '') . ' ' . ($l->last_name ?? '')) ?: 'Unknown';

            return '- ' . $l->activity_date . ' - ' . $who . ': ' . $this->clean((string) $l->activity, 800);
        })->implode("\n");

        return "## WORK LOG (what the team actually did)\n" . $lines;
    }

    private function deliverablesSection(Ticket $ticket): string
    {
        $docs = DB::table('ticket_deliverables')
            ->where('ticket_id', $ticket->ticket_id)
            ->orderBy('upload_date')
            ->limit(self::MAX_DELIVERABLES)
            ->get(['doc_type', 'body_text', 'file_name', 'status', 'upload_date']);

        if ($docs->isEmpty()) {
            return '';
        }

        $lines = $docs->map(function ($d) {
            $head = '- [' . ($d->doc_type ?: 'Document') . '] '
                . ($d->file_name ?: '(no file)')
                . ' - status: ' . ($d->status ?: '-')
                . ($d->upload_date ? ', ' . $d->upload_date : '');

            $body = $this->clean((string) ($d->body_text ?? ''), self::MAX_DELIVERABLE_CHARS);

            return $body ? $head . "\n  " . str_replace("\n", "\n  ", $body) : $head;
        })->implode("\n");

        return "## DELIVERABLES\n" . $lines;
    }

    /**
     * HTML jadi teks polos, lalu buang kutipan balasan email.
     *
     * Thread di sistem ini lewat Outlook, jadi hampir setiap balasan membawa
     * salinan seluruh percakapan sebelumnya. Tanpa dipotong, konteks membengkak
     * berlipat-lipat dan model membaca keluhan yang sama belasan kali.
     */
    private function clean(string $text, int $maxChars): string
    {
        $text = preg_replace('#<br\s*/?>#i', "\n", $text) ?? $text;
        $text = preg_replace('#</(p|div|li|tr|h[1-6])>#i', "\n", $text) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // NBSP jadi spasi biasa dulu - kalau tidak, baris yang "kelihatan kosong"
        // tidak pernah cocok dengan pola batas kutipan di bawah.
        $text = str_replace(["\xC2\xA0", "\r\n", "\r"], [' ', "\n", "\n"], $text);

        $text = $this->cutAtQuoteBoundary($text);

        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
        $text = trim($text);

        if (mb_strlen($text) > $maxChars) {
            $text = mb_substr($text, 0, $maxChars) . ' [...]';
        }

        return $text;
    }

    private function cutAtQuoteBoundary(string $text): string
    {
        $boundaries = [
            '/^-{2,}\s*Original Message\s*-{2,}/im',
            '/^_{5,}$/m',
            '/^From:\s.+$/im',
            '/^Dari:\s.+$/im',
            '/^On\s.+\swrote:\s*$/im',
            '/^Pada\s.+\smenulis:\s*$/im',
        ];

        $cut = mb_strlen($text);

        foreach ($boundaries as $pattern) {
            if (preg_match($pattern, $text, $m, PREG_OFFSET_CAPTURE)) {
                // Offset dari preg dihitung dalam byte; ubah ke offset karakter
                // supaya mb_substr memotong di tempat yang benar.
                $charOffset = mb_strlen(substr($text, 0, $m[0][1]));
                $cut = min($cut, $charOffset);
            }
        }

        return mb_substr($text, 0, $cut);
    }
}

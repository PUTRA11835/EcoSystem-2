<?php

namespace App\Console\Commands;

use App\Models\TicketAttachment;
use App\Models\TicketMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Bersihkan laporan NDR / bounce ("Undeliverable" dari postmaster/Outlook) yang
 * TERLANJUR tersimpan sebagai bubble chat sebelum intercept di EmailController aktif.
 *
 * Laporan tsb ter-match ke thread ticket via conversationId/References lalu tersimpan
 * sebagai TicketMessage dengan pengirim "Microsoft Outlook" / MicrosoftExchange... —
 * body-nya berupa tabel/HTML laporan yang merusak tampilan room chat.
 *
 * Untuk tiap NDR yang ditemukan, command ini:
 *   1. Menandai pesan email KELUAR terakhir sebelum NDR sebagai 'failed' + alasan
 *      (agar helpdesk tetap dapat sinyal "Tidak terkirim"). Bisa dimatikan --no-mark.
 *   2. Menghapus bubble NDR beserta attachment/inline file-nya.
 *
 * Default = DRY-RUN (hanya menampilkan rencana). Tambahkan --apply untuk menerapkan.
 */
class CleanupNdrMessages extends Command
{
    protected $signature = 'tickets:cleanup-ndr
        {--apply : Terapkan perubahan (tanpa flag ini hanya dry-run/preview)}
        {--no-mark : Jangan tandai pesan keluar terkait sebagai "Tidak terkirim"}';

    protected $description = 'Bersihkan laporan NDR/bounce (Undeliverable) yang terlanjur masuk sebagai bubble chat ticket';

    public function handle(): int
    {
        $apply  = (bool) $this->option('apply');
        $doMark = ! $this->option('no-mark');

        $ndrs = TicketMessage::where('channel', 'email')
            ->where(function ($q) {
                $q->where('sender_email', 'like', 'MicrosoftExchange%')
                  ->orWhere('sender_email', 'like', 'postmaster@%')
                  ->orWhere('sender_email', 'like', 'mailer-daemon@%')
                  ->orWhere('sender_email', 'like', 'mailerdaemon@%')
                  ->orWhereIn('sender_name', ['Microsoft Outlook', 'Mail Delivery Subsystem', 'Postmaster']);
            })
            ->orderBy('ticket_id')
            ->orderBy('created_at')
            ->get();

        if ($ndrs->isEmpty()) {
            $this->info('Tidak ada bubble NDR/bounce yang perlu dibersihkan.');
            return self::SUCCESS;
        }

        $this->info(($apply ? '[APPLY] ' : '[DRY-RUN] ') . "Ditemukan {$ndrs->count()} bubble NDR/bounce:");
        $this->newLine();

        $marked  = 0;
        $deleted = 0;

        foreach ($ndrs as $ndr) {
            $recipient = $this->extractRecipientFromBody($ndr->message_html ?: (string) $ndr->message);
            $reason    = $recipient
                ? "Email could not be delivered to {$recipient} — the address was not found or rejected by the destination server. Check the recipient's spelling and resend."
                : 'Email could not be delivered — the destination address was not found or rejected by the server. Check the recipient list and resend.';

            // Pesan email keluar terakhir sebelum NDR ini → yang paling mungkin bounce.
            $outgoing = TicketMessage::where('ticket_id', $ndr->ticket_id)
                ->where('channel', 'email')
                ->where('sender_type', 'employee')
                ->where('is_internal_note', false)
                ->where('created_at', '<=', $ndr->created_at)
                ->orderBy('created_at', 'desc')
                ->first();

            $markInfo = '—';
            if ($doMark && $outgoing) {
                if ($outgoing->email_status === 'failed') {
                    $markInfo = "msg #{$outgoing->id} (sudah failed)";
                } else {
                    $markInfo = "msg #{$outgoing->id} → failed";
                    if ($apply) {
                        $outgoing->update(['email_status' => 'failed', 'email_error' => $reason]);
                    }
                    $marked++;
                }
            }

            $this->line("  NDR #{$ndr->id} (ticket {$ndr->ticket_id}, {$ndr->created_at})"
                . " | recipient=" . ($recipient ?: '?')
                . " | tandai keluar: {$markInfo}");

            if ($apply) {
                $this->deleteNdrMessage($ndr);
            }
            $deleted++;
        }

        $this->newLine();
        if ($apply) {
            $this->info("Selesai. Bubble NDR dihapus: {$deleted}. Pesan keluar ditandai 'Tidak terkirim': {$marked}.");
        } else {
            $this->warn("DRY-RUN — belum ada perubahan. Jalankan ulang dengan --apply untuk menerapkan.");
            $this->line("Akan menghapus {$deleted} bubble NDR" . ($doMark ? " & menandai {$marked} pesan keluar." : '.'));
        }

        return self::SUCCESS;
    }

    /**
     * Hapus bubble NDR beserta attachment (metadata + file inline di public disk).
     */
    private function deleteNdrMessage(TicketMessage $ndr): void
    {
        $attachments = TicketAttachment::where('message_id', $ndr->id)->get();
        foreach ($attachments as $att) {
            if ($att->file_path) {
                Storage::disk('public')->delete($att->file_path);
            }
            $att->delete();
        }
        $ndr->delete();
    }

    /**
     * Ambil alamat tujuan yang gagal dari body laporan NDR (best effort) — alamat email
     * pertama yang BUKAN milik akun sendiri / postmaster / domain sendiri.
     */
    private function extractRecipientFromBody(string $body): ?string
    {
        if ($body === '') {
            return null;
        }

        $ownSender = strtolower((string) config('services.microsoft_graph.sender_email'));
        $ownDomain = str_contains($ownSender, '@') ? substr(strrchr($ownSender, '@'), 1) : '';

        $text = html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (preg_match_all('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i', $text, $m)) {
            foreach ($m[0] as $addr) {
                $addrL = strtolower($addr);
                if ($addrL === $ownSender)                                 continue;
                if (str_starts_with($addrL, 'postmaster@'))                continue;
                if (str_contains($addrL, 'microsoftexchange'))             continue;
                if ($ownDomain && str_ends_with($addrL, '@' . $ownDomain)) continue;
                return $addr;
            }
        }
        return null;
    }
}

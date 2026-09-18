<?php

namespace App\Console\Commands;

use App\Models\TeamsOutboxItem;
use App\Services\Teams\TeamsOutboxService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Kirim internal note yang mengantre di `teams_outbox` ke group chat Teams
 * lewat flow 7 Power Automate.
 *
 * Dijadwalkan tiap menit. Item yang gagal kembali ke `pending` sampai batas
 * percobaan habis, lalu BERHENTI di `failed` dengan `last_error` terisi — supaya
 * kegagalannya terlihat sebagai data di tabel, bukan hanya sebagai baris log
 * yang tidak ada yang membaca.
 *
 * Lihat docs/teams-chat-sync-design.md §8.
 */
class FlushTeamsOutbox extends Command
{
    protected $signature = 'teams:flush-outbox
                            {--retry-failed : Kembalikan item berstatus failed ke pending lalu coba lagi}
                            {--dry-run : Tampilkan antrean tanpa memanggil Power Automate}';

    protected $description = 'Kirim internal note yang mengantre ke group chat Teams (flow 7)';

    public function handle(TeamsOutboxService $outbox): int
    {
        if ($this->option('retry-failed')) {
            // Dipakai setelah penyebabnya diperbaiki (flow mati, koneksi Teams
            // putus). Sengaja manual: mencoba ulang otomatis selamanya hanya
            // menyembunyikan masalah yang butuh tangan manusia.
            $n = TeamsOutboxItem::where('status', TeamsOutboxItem::STATUS_FAILED)
                ->update(['status' => TeamsOutboxItem::STATUS_PENDING, 'attempts' => 0]);

            $this->info("{$n} item failed dikembalikan ke pending.");
        }

        if (!$outbox->isEnabled()) {
            // Bukan error: begini caranya arah keluar dimatikan
            // (TEAMS_SYNC_ENABLED / TEAMS_SYNC_OUTBOUND = false).
            $this->line('Sinkron keluar Teams tidak aktif — dilewati.');

            return self::SUCCESS;
        }

        $items = collect($outbox->due());

        if ($items->isEmpty()) {
            $this->line('Antrean kosong.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->table(
                ['id', 'ticket', 'message', 'status', 'attempts', 'error'],
                $items->map(fn (TeamsOutboxItem $i) => [
                    $i->id,
                    $i->ticket_id,
                    $i->ticket_message_id,
                    $i->status,
                    $i->attempts,
                    mb_substr((string) $i->last_error, 0, 40),
                ])->all()
            );

            return self::SUCCESS;
        }

        $sent = 0;
        $failed = 0;

        foreach ($items as $item) {
            if ($outbox->send($item)) {
                $sent++;
                continue;
            }

            $failed++;

            if ($item->status === TeamsOutboxItem::STATUS_FAILED) {
                $this->error("  item {$item->id} (tiket {$item->ticket_id}) MENYERAH setelah {$item->attempts} percobaan");
            }
        }

        $this->info("Selesai: {$sent} terkirim, {$failed} gagal dari {$items->count()} item.");

        if ($failed > 0) {
            Log::warning('teams:flush-outbox menyisakan kegagalan', [
                'sent'   => $sent,
                'failed' => $failed,
            ]);
        }

        return self::SUCCESS;
    }
}

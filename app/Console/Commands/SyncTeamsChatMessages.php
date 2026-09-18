<?php

namespace App\Console\Commands;

use App\Models\TicketTeamsChat;
use App\Services\Teams\TeamsInboundService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Tarik pesan baru dari group chat Teams tiket menjadi internal note.
 *
 * Dijadwalkan tiap menit. Tidak menyimpan daftar pekerjaan di mana pun — tiap
 * run menghitung ulang chat mana yang layak dipoll dari `ticket_teams_chat`
 * dan status tiketnya saat ini, jadi tiket yang ditutup hilang sendiri dari
 * antrean tanpa perlu kode pembatal.
 *
 * Kursornya (`last_message_at`) hanya maju untuk pesan yang benar-benar sudah
 * ditangani, sehingga pesan yang tersisa karena pagar `max_per_poll` pasti
 * terambil putaran berikutnya.
 *
 * Lihat docs/teams-chat-sync-design.md §7.
 */
class SyncTeamsChatMessages extends Command
{
    protected $signature = 'teams:sync-chat-messages
                            {--ticket= : Hanya sinkronkan chat milik satu ticket_id (untuk pengujian)}
                            {--dry-run : Tampilkan chat yang akan dipoll tanpa memanggil Graph}';

    protected $description = 'Tarik pesan group chat Teams menjadi internal note tiket';

    public function handle(TeamsInboundService $inbound): int
    {
        if (!$inbound->isEnabled()) {
            // Bukan error: begini caranya fitur ini dimatikan
            // (TEAMS_SYNC_ENABLED / TEAMS_SYNC_INBOUND = false). Scheduler tetap
            // memanggilnya tiap menit dan ia keluar seketika.
            $this->line('Sinkron masuk Teams tidak aktif — dilewati.');

            return self::SUCCESS;
        }

        $chats = $this->option('ticket')
            ? TicketTeamsChat::where('ticket_id', (int) $this->option('ticket'))->get()
            : collect($inbound->dueChats());

        if ($chats->isEmpty()) {
            $this->line('Tidak ada chat yang perlu disinkronkan.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->table(
                ['ticket_id', 'chat_id', 'sync', 'kursor', 'gagal'],
                $chats->map(fn (TicketTeamsChat $c) => [
                    $c->ticket_id,
                    mb_substr($c->chat_id, 0, 30) . '…',
                    $c->sync_enabled ? 'on' : 'off',
                    $c->cursorIso() ?? '(belum ada)',
                    $c->poll_failures,
                ])->all()
            );

            return self::SUCCESS;
        }

        $imported = 0;
        $skipped  = 0;
        $failed   = 0;

        foreach ($chats as $chat) {
            $result = $inbound->syncChat($chat);

            $imported += $result['imported'];
            $skipped  += $result['skipped'];
            $failed   += $result['failed'] ? 1 : 0;

            if ($result['imported'] > 0) {
                $this->line("  tiket {$chat->ticket_id}: {$result['imported']} pesan diserap");
            }

            if ($result['skipped'] > 0) {
                // Bukan kehilangan data — sisanya diambil putaran berikutnya
                // karena kursor hanya maju sejauh yang sudah ditangani.
                $this->warn("  tiket {$chat->ticket_id}: {$result['skipped']} pesan ditunda (batas per putaran)");
            }
        }

        $this->info(sprintf(
            'Selesai: %d chat dipoll, %d pesan diserap, %d ditunda, %d gagal.',
            $chats->count(),
            $imported,
            $skipped,
            $failed
        ));

        if ($imported > 0 || $failed > 0) {
            Log::info('teams:sync-chat-messages selesai', [
                'chats'    => $chats->count(),
                'imported' => $imported,
                'skipped'  => $skipped,
                'failed'   => $failed,
            ]);
        }

        return self::SUCCESS;
    }
}

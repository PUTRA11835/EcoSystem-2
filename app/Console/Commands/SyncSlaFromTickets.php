<?php

namespace App\Console\Commands;

use App\Models\StagingTicket;
use App\Models\Ticket;
use App\Models\TicketSla;
use App\Services\SlaService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Sync ticket_sla records dari semua tiket yang ada di database.
 *
 * Aman dijalankan berkali-kali: hanya memproses tiket yang belum punya SLA record.
 * Gunakan --force untuk re-generate semua (hapus SLA lama lalu buat ulang).
 *
 * Usage:
 *   php artisan sla:sync              # Hanya tiket yang belum punya SLA
 *   php artisan sla:sync --force      # Re-generate semua SLA dari awal
 *   php artisan sla:sync --no-events  # Buat SLA record saja, skip backfill events
 */
class SyncSlaFromTickets extends Command
{
    protected $signature = 'sla:sync
                            {--force       : Hapus SLA lama dan generate ulang untuk semua tiket}
                            {--skip-events : Lewati backfill event dari ticket_message}';

    protected $description = 'Sync ticket_sla dari semua tiket yang ada. Aman dijalankan di database manapun.';

    public function handle(SlaService $slaService): int
    {
        $force      = $this->option('force');
        $noEvents   = $this->option('skip-events');

        // ── 1. Tentukan tiket yang perlu diproses ────────────────────────────
        if ($force) {
            $this->warn('Mode --force: SLA record lama akan dihapus dan dibuat ulang.');

            if (!$this->confirm('Lanjutkan? Semua data ticket_sla, ticket_sla_events, dan ticket_sla_pauses akan dihapus.')) {
                $this->info('Dibatalkan.');
                return self::FAILURE;
            }

            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            DB::table('ticket_sla_events')->truncate();
            DB::table('ticket_sla_pauses')->truncate();
            DB::table('ticket_sla')->truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');

            $this->info('Data SLA lama dihapus.');
        }

        // Ambil semua ticket yang belum punya SLA record
        $existingSlaTicketIds = DB::table('ticket_sla')
            ->whereNotNull('ticket_id')
            ->pluck('ticket_id')
            ->flip(); // flip untuk O(1) lookup

        $tickets = Ticket::query()
            ->when(!$force, fn($q) => $q->whereNotIn('ticket_id', $existingSlaTicketIds->keys()->all()))
            ->whereNotNull('ticket_type')
            ->whereNotNull('ticket_priority')
            ->orderBy('ticket_id')
            ->get();

        if ($tickets->isEmpty()) {
            $this->info('Semua tiket sudah memiliki SLA record. Tidak ada yang perlu diproses.');
            return self::SUCCESS;
        }

        $this->info("Memproses {$tickets->count()} tiket...");

        // ── 2. Load staging tickets sekali (keyed by ticket_id) ─────────────
        $stagingMap = StagingTicket::whereNotNull('ticket_id')
            ->whereIn('ticket_id', $tickets->pluck('ticket_id'))
            ->get()
            ->keyBy('ticket_id');

        // ── 3. Buat SLA record untuk setiap tiket ───────────────────────────
        $created  = 0;
        $skipped  = 0;
        $noPolicy = 0;

        foreach ($tickets as $ticket) {
            $staging = $stagingMap->get($ticket->ticket_id);

            // Cek apakah ada SLA policy yang cocok sebelum mencoba attach
            $scale    = $ticket->scale ?: 'Simple';
            $priority = $ticket->ticket_priority;
            $custId   = $ticket->customer_id;

            $hasPolicy = DB::table('sla_policies')
                ->where('is_active', true)
                ->where('priority', $priority)
                ->where('scale', $scale)
                ->where(function ($q) use ($custId) {
                    $custId
                        ? $q->where('customer_id', $custId)->orWhereNull('customer_id')
                        : $q->whereNull('customer_id');
                })
                ->exists();

            if (!$hasPolicy) {
                $this->line("  <comment>SKIP</comment> ticket #{$ticket->ticket_id} ({$ticket->ticket_number}): tidak ada policy untuk priority={$priority} scale={$scale}");
                $noPolicy++;
                continue;
            }

            $countBefore = TicketSla::where('ticket_id', $ticket->ticket_id)->count();

            $slaService->attachToTicket($ticket, $staging);

            $countAfter = TicketSla::where('ticket_id', $ticket->ticket_id)->count();

            if ($countAfter > $countBefore) {
                $this->line("  <info>OK</info>  ticket #{$ticket->ticket_id} ({$ticket->ticket_number}) — staging_id=" . ($staging?->id ?? 'none') . " priority={$priority} scale={$scale}");
                $created++;
            } else {
                $this->line("  <comment>SKIP</comment> ticket #{$ticket->ticket_id}: sudah ada SLA atau gagal attach");
                $skipped++;
            }
        }

        $this->newLine();
        $this->info("SLA record dibuat: {$created} | dilewati: {$skipped} | tanpa policy: {$noPolicy}");

        // ── 4. Backfill events dari ticket_message ───────────────────────────
        if (!$noEvents && $created > 0) {
            $this->newLine();
            $this->info('Backfill event dari ticket_message...');
            $this->call('sla:backfill-events', ['--all' => true]);
        }

        // ── 5. Auto-close tiket yang sudah closed tapi SLA belum ditutup ─────
        $this->syncClosedTickets($slaService);

        $this->newLine();
        $this->info('sla:sync selesai.');

        return self::SUCCESS;
    }

    // Tutup SLA untuk tiket yang statusnya closed/done tapi resolution_status masih pending/paused
    private function syncClosedTickets(SlaService $slaService): void
    {
        $closedStatuses = ['closed', 'done', 'resolved'];

        $openSlas = TicketSla::with(['ticket', 'policy'])
            ->whereIn('resolution_status', ['pending', 'paused'])
            ->whereNotNull('ticket_id')
            ->get();

        $closed = 0;
        foreach ($openSlas as $sla) {
            $ticket = $sla->ticket;
            if (!$ticket || !in_array($ticket->status, $closedStatuses, true)) continue;

            $closedAt = Carbon::parse($ticket->updated_at);
            $slaService->closeTicketSla($sla, $ticket, null, $closedAt);
            $closed++;
        }

        if ($closed > 0) {
            $this->info("Auto-close SLA untuk {$closed} tiket yang sudah closed.");
        }
    }
}

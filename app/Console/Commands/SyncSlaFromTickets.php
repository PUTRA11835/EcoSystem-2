<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Models\TicketSla;
use App\Services\SlaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncSlaFromTickets extends Command
{
    protected $signature = 'sla:sync
                            {--force : Drop all SLA data and regenerate from scratch (DESTRUCTIVE)}
                            {--skip-events : Create SLA records but skip backfilling events}';

    protected $description = 'Sync SLA records for tickets that are missing one';

    public function handle(SlaService $sla): int
    {
        if ($this->option('force')) {
            if (!$this->confirm('WARNING: --force will delete ALL SLA data. Continue?')) {
                return 0;
            }
            $this->info('Deleting all SLA data...');
            DB::table('ticket_sla_events')->delete();
            DB::table('ticket_sla_pauses')->delete();
            DB::table('ticket_sla')->delete();
            $this->info('SLA data deleted.');
        }

        $existing = TicketSla::whereNotNull('ticket_id')->pluck('ticket_id')->toArray();

        $missing = Ticket::whereNotIn('ticket_id', $existing)
            ->whereNotNull('ticket_type')
            ->whereNotNull('ticket_priority')
            ->get();

        if ($missing->isEmpty()) {
            $this->info('All tickets already have an SLA record.');
            return 0;
        }

        $this->info("Found {$missing->count()} ticket(s) without an SLA record. Processing...");
        $bar = $this->output->createProgressBar($missing->count());
        $bar->start();

        $newIds = [];
        foreach ($missing as $ticket) {
            try {
                $staging = \App\Models\StagingTicket::where('ticket_id', $ticket->ticket_id)->first();
                $sla->attachToTicket($ticket, $staging);
                $newIds[] = $ticket->ticket_id;
            } catch (\Throwable $e) {
                $this->newLine();
                $this->warn("  Failed to attach ticket #{$ticket->ticket_id}: {$e->getMessage()}");
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();

        if (!empty($newIds) && !$this->option('skip-events')) {
            $this->info('Backfilling events for new tickets...');
            $sla->backfillEventsForTickets($newIds);
            $this->info('Auto-closing SLA for already-closed tickets...');
            $sla->autoCloseSlaForClosedTickets($newIds);
        }

        $this->info('Done. ' . count($newIds) . ' ticket(s) synced.');
        return 0;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\TicketSla;
use App\Services\SlaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillMissingSlaEvents extends Command
{
    protected $signature = 'sla:backfill-events
                            {--all : Process all messages, not just the last 24 hours}
                            {--recalculate : Delete and regenerate all events from scratch}';

    protected $description = 'Backfill SLA events from ticket_message history';

    public function handle(SlaService $sla): int
    {
        if ($this->option('recalculate')) {
            if (!$this->confirm('WARNING: --recalculate will delete ALL SLA events. Continue?')) {
                return 0;
            }
            $this->info('Deleting all SLA events...');
            DB::table('ticket_sla_events')->delete();
            DB::table('ticket_sla_pauses')->delete();
            DB::table('ticket_sla')->whereNotNull('ticket_id')->update([
                'ball_holder'         => 'helpdesk',
                'sla_paused_at'       => null,
                'total_waiting_hours' => 0,
                'resolution_status'   => 'pending',
                'session_start_at'    => DB::raw('first_responded_at'),
            ]);
            $this->info('Events deleted. Regenerating...');
        }

        $ticketIds = TicketSla::whereNotNull('ticket_id')->pluck('ticket_id')->toArray();

        if (empty($ticketIds)) {
            $this->warn('No SLA records found. Run php artisan sla:sync first.');
            return 0;
        }

        if ($this->option('all') || $this->option('recalculate')) {
            $this->info('Processing all ' . count($ticketIds) . ' ticket(s)...');
        } else {
            $recentTicketIds = DB::table('ticket_message')
                ->where('created_at', '>=', now()->subDay())
                ->whereIn('ticket_id', $ticketIds)
                ->distinct()
                ->pluck('ticket_id')
                ->toArray();
            $ticketIds = array_intersect($ticketIds, $recentTicketIds);
            $this->info('Processing ' . count($ticketIds) . ' ticket(s) with messages in the last 24 hours...');
        }

        if (empty($ticketIds)) {
            $this->info('No tickets to process.');
            return 0;
        }

        $bar = $this->output->createProgressBar(count($ticketIds));
        $bar->start();

        foreach (array_chunk($ticketIds, 50) as $chunk) {
            $sla->backfillEventsForTickets($chunk);
            $bar->advance(count($chunk));
        }

        $bar->finish();
        $this->newLine();
        $this->info('Backfill complete.');
        return 0;
    }
}

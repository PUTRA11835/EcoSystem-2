<?php

namespace App\Console\Commands;

use App\Services\SlaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Backfill / recalculate SLA events dari raw ticket_message timestamps.
 * --all        : proses semua pesan, tidak hanya 24 jam terakhir
 * --recalculate: hapus event lama lalu generate ulang (pakai jika formula berubah)
 */
class BackfillMissingSlaEvents extends Command
{
    protected $signature   = 'sla:backfill-events
                                {--all : Proses semua pesan, tidak hanya 24 jam}
                                {--recalculate : Hapus event lama dan hitung ulang dengan formula baru}';
    protected $description = 'Backfill SLA events untuk pesan yang belum tercatat';

    private const STOP_STATUSES = ['author action', 'proposed solution', 'sent in to SAP', 'wait to close'];
    private const BALL_HOLDER_MAP = [
        'author action'     => 'customer',
        'proposed solution' => 'customer',
        'wait to close'     => 'customer',
        'sent in to SAP'    => 'sap',
    ];

    public function handle(SlaService $slaService): void
    {
        if ($this->option('recalculate')) {
            $this->runRecalculate($slaService);
        } else {
            $this->runBackfill($slaService);
        }
    }

    // ─── Mode recalculate: hapus event lama, generate ulang ─────────────────

    private function runRecalculate(SlaService $slaService): void
    {
        $this->info('Mode recalculate: hapus event lama...');

        DB::table('ticket_sla_events')
            ->whereIn('event_type', ['agent_replied', 'customer_replied', 'ticket_closed'])
            ->delete();

        DB::table('ticket_sla')->update([
            'ball_holder'          => 'helpdesk',
            'sla_paused_at'        => null,
            'total_waiting_hours'  => 0,
            'net_resolution_hours' => null,
            'resolution_status'    => 'pending',
        ]);

        DB::statement('UPDATE ticket_sla SET session_start_at = first_responded_at WHERE first_responded_at IS NOT NULL');

        $this->info('Reset selesai. Mulai generate ulang...');
        $this->runBackfill($slaService);
    }

    // ─── Mode backfill: isi event yang belum ada ─────────────────────────────

    private function runBackfill(SlaService $slaService): void
    {
        $since = $this->option('all') || $this->option('recalculate')
            ? '2000-01-01'
            : now()->subHours(24)->toDateTimeString();

        $slaTickets = DB::table('ticket_sla')
            ->join('sla_policies', 'sla_policies.id', '=', 'ticket_sla.sla_policy_id')
            ->get([
                'ticket_sla.ticket_id',
                'ticket_sla.staging_ticket_id',
                'ticket_sla.sla_start_at',
                'ticket_sla.ball_holder',
                'ticket_sla.sla_paused_at',
                'ticket_sla.session_start_at',
                'ticket_sla.first_responded_at',
                'sla_policies.is_24_hours',
            ]);

        if ($slaTickets->isEmpty()) {
            $this->info('Tidak ada tiket SLA.');
            return;
        }

        $slaMap = $slaTickets->keyBy('ticket_id');

        $missing = DB::table('ticket_message as m')
            ->where('m.is_internal_note', 0)
            ->whereIn('m.ticket_id', $slaMap->keys()->all())
            ->where('m.created_at', '>=', $since)
            ->whereNotExists(function ($q) {
                $q->from('ticket_sla_events as e')
                    ->whereColumn('e.message_id', 'm.id');
            })
            ->orderBy('m.ticket_id')
            ->orderBy('m.created_at')
            ->get(['m.id', 'm.ticket_id', 'm.sender_type', 'm.created_at']);

        if ($missing->isEmpty()) {
            $this->info('Tidak ada pesan yang perlu diproses.');
            return;
        }

        $this->info("Backfill {$missing->count()} pesan...");

        foreach ($missing->groupBy('ticket_id') as $ticketId => $msgs) {
            $sla = $slaMap->get($ticketId);
            if (!$sla) continue;

            $is24h        = (bool) ($sla->is_24_hours ?? true);
            $ballHolder   = $sla->ball_holder ?? 'helpdesk';
            $pausedAt     = $sla->sla_paused_at ? Carbon::parse($sla->sla_paused_at) : null;
            $sessionStart = $sla->session_start_at
                ? Carbon::parse($sla->session_start_at)
                : Carbon::parse($sla->first_responded_at ?? $sla->sla_start_at);

            $lastJarvisStatus  = null;
            $totalWaitingDelta = 0;

            foreach ($msgs as $msg) {
                $eventAt = Carbon::parse($msg->created_at);

                if ($msg->sender_type === 'customer') {
                    // ── Customer burst ──────────────────────────────────────
                    // Chat pertama dalam burst → hitung waiting, reset session
                    // Chat berikutnya dalam burst → diabaikan (ball sudah di helpdesk)
                    $waitingHours = null;
                    if (in_array($ballHolder, ['customer', 'sap'], true) && $pausedAt) {
                        $waitingHours       = $slaService->calcHours($pausedAt, $eventAt, $is24h);
                        $totalWaitingDelta += $waitingHours;
                        $ballHolder       = 'helpdesk';
                        $pausedAt         = null;
                        $sessionStart     = $eventAt;
                        $lastJarvisStatus = null;
                    }

                    DB::table('ticket_sla_events')->insert([
                        'ticket_id'         => $ticketId,
                        'staging_ticket_id' => $sla->staging_ticket_id,
                        'message_id'        => $msg->id,
                        'event_type'        => 'customer_replied',
                        'jarvis_status'     => null,
                        'event_at'          => $msg->created_at,
                        'waiting_hours'     => $waitingHours,
                        'resolution_hours'  => null,
                        'notes'             => 'Customer membalas',
                        'triggered_by_type' => 'system',
                        'created_at'        => now(),
                    ]);

                } else {
                    // ── Employee burst ──────────────────────────────────────
                    // Backfill default 'in process' (status historis tidak tersimpan di pesan)
                    $jarviesStatus   = 'in process';
                    $resolutionHours = $slaService->calcHours($sessionStart, $eventAt, $is24h);
                    $waitingHours    = null;

                    if (in_array($jarviesStatus, self::STOP_STATUSES, true)) {
                        if ($ballHolder === 'helpdesk') {
                            // Pertama kali STOP
                            $ballHolder = self::BALL_HOLDER_MAP[$jarviesStatus];
                            $pausedAt   = $eventAt;
                        } elseif ($lastJarvisStatus !== $jarviesStatus) {
                            // Status beda → reset patokan waiting
                            $ballHolder = self::BALL_HOLDER_MAP[$jarviesStatus];
                            $pausedAt   = $eventAt;
                        }
                        // Status sama → sla_paused_at tetap (hitung dari chat pertama yang beda)
                    } else {
                        // RUN status
                        if ($ballHolder !== 'helpdesk' && $pausedAt) {
                            $waitingHours       = $slaService->calcHours($pausedAt, $eventAt, $is24h);
                            $totalWaitingDelta += $waitingHours;
                            $ballHolder       = 'helpdesk';
                            $pausedAt         = null;
                            $sessionStart     = $eventAt;
                            $resolutionHours  = 0;
                        }
                    }

                    $lastJarvisStatus = $jarviesStatus;

                    DB::table('ticket_sla_events')->insert([
                        'ticket_id'         => $ticketId,
                        'staging_ticket_id' => $sla->staging_ticket_id,
                        'message_id'        => $msg->id,
                        'event_type'        => 'agent_replied',
                        'jarvis_status'     => $jarviesStatus,
                        'event_at'          => $msg->created_at,
                        'waiting_hours'     => $waitingHours,
                        'resolution_hours'  => $resolutionHours,
                        'notes'             => 'Balasan helpdesk dikirim',
                        'triggered_by_type' => 'system',
                        'created_at'        => now(),
                    ]);
                }

                $this->line("  [msg {$msg->id}] ticket={$ticketId} {$msg->sender_type} ball={$ballHolder}");
            }

            // Update state ticket_sla setelah semua pesan diproses
            $updateData = [
                'ball_holder'      => $ballHolder,
                'sla_paused_at'    => $pausedAt,
                'session_start_at' => $sessionStart,
            ];
            if ($totalWaitingDelta > 0) {
                DB::table('ticket_sla')
                    ->where('ticket_id', $ticketId)
                    ->increment('total_waiting_hours', $totalWaitingDelta, $updateData);
            } else {
                DB::table('ticket_sla')->where('ticket_id', $ticketId)->update($updateData);
            }
        }

        $this->info('Done.');
    }
}

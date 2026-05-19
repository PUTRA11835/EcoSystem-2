<?php

namespace App\Services;

use App\Models\SlaPolicy;
use App\Models\StagingTicket;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\TicketSla;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SlaService
{
    private const FULL_SLA_TYPES = ['Incident', 'Service Request'];

    // Jarvis status → efek SLA
    private const STOP_STATUSES = ['author action', 'proposed solution', 'sent in to SAP', 'wait to close', 'in process'];
    private const RUN_STATUSES  = ['sent it to support'];
    private const END_STATUSES  = ['closed'];

    private const BALL_HOLDER_MAP = [
        'author action'     => 'customer',
        'proposed solution' => 'customer',
        'wait to close'     => 'customer',
        'sent in to SAP'    => 'sap',
        'in process'        => 'customer',
    ];

    // ─── Business hours helper ────────────────────────────────────────────────
    // is_24_hours=true  → hitung jam kalender biasa
    // is_24_hours=false → hitung jam bisnis Senin-Jumat 09:00-18:00

    public function calcHours(Carbon $from, Carbon $to, bool $is24h): float
    {
        if ($from->gte($to)) return 0.0;

        if ($is24h) {
            return round($from->diffInMinutes($to) / 60, 2);
        }

        $minutes = 0;
        $day     = $from->copy()->startOfDay();
        $toDay   = $to->copy()->startOfDay();

        while ($day->lte($toDay)) {
            if (!$day->isWeekend()) {
                $open  = $day->copy()->setTime(9, 0, 0);
                $close = $day->copy()->setTime(18, 0, 0);
                $start = $from->gt($open)  ? $from->copy() : $open->copy();
                $end   = $to->lt($close)   ? $to->copy()   : $close->copy();
                if ($start->lt($end)) {
                    $minutes += $start->diffInMinutes($end);
                }
            }
            $day->addDay();
        }

        return round($minutes / 60, 2);
    }

    // ─── 1. Attach SLA saat staging divalidasi ───────────────────────────────
    // $staging boleh null jika tiket dibuat langsung (tanpa email/staging).
    // Dalam kasus itu, ticket.created_at dipakai sebagai sla_start_at.

    public function attachToTicket(Ticket $ticket, ?StagingTicket $staging = null): void
    {
        try {
            if (empty($ticket->ticket_type)) return;
            if ($ticket->sla()->exists()) return;

            $scale    = $ticket->scale ?: 'Simple';
            $priority = $ticket->ticket_priority ?: 'Medium';
            $custId   = $ticket->customer_id;

            if (!$priority) {
                Log::info('SlaService: ticket tidak memiliki priority, skip', ['ticket_id' => $ticket->ticket_id]);
                return;
            }

            $policy = SlaPolicy::where('is_active', true)
                ->where('priority', $priority)
                ->where('scale', $scale)
                ->where(function ($q) use ($custId) {
                    $custId
                        ? $q->where('customer_id', $custId)->orWhereNull('customer_id')
                        : $q->whereNull('customer_id');
                })
                ->orderByRaw('customer_id IS NULL ASC')
                ->first();

            if (!$policy) {
                // Fallback ke global policy jika tidak ada policy spesifik customer
                $policy = SlaPolicy::where('is_active', true)
                    ->where('priority', $priority)
                    ->where('scale', $scale)
                    ->whereNull('customer_id')
                    ->first();
            }

            if (!$policy) {
                Log::info('SlaService: tidak ada policy', [
                    'ticket_id' => $ticket->ticket_id,
                    'priority'  => $priority,
                    'scale'     => $scale,
                ]);
                return;
            }

            $is24h     = (bool) $policy->is_24_hours;
            $slaMode   = in_array($ticket->ticket_type, self::FULL_SLA_TYPES, true) ? 'full' : 'response_only';
            $stagingId = $staging?->id;

            // sla_start_at: staging.created_at jika ada, fallback ke ticket.created_at
            $slaStart  = $staging
                ? Carbon::parse($staging->created_at)
                : Carbon::parse($ticket->created_at);
            $validated = Carbon::parse($ticket->created_at);

            // Pastikan sla_start_at tidak lebih besar dari ticket.created_at
            if ($slaStart->gt($validated)) {
                $slaStart = $validated->copy();
            }

            $validHours = $this->calcHours($slaStart, $validated, $is24h);
            $respStatus = $validHours <= (float) $policy->response_hours ? 'met' : 'breached';

            $ticket->sla()->create([
                'staging_ticket_id'         => $stagingId,
                'sla_policy_id'             => $policy->id,
                'sla_mode'                  => $slaMode,
                'sla_start_at'              => $slaStart,
                'response_due_at'           => $slaStart->copy()->addHours((float) $policy->response_hours),
                'resolution_due_at'         => $slaMode === 'full'
                    ? $slaStart->copy()->addHours((float) $policy->resolution_hours)
                    : null,
                'first_responded_at'        => $validated,
                'validation_duration_hours' => $validHours,
                'response_status'           => $respStatus,
                'resolved_at'               => null,
                'total_waiting_hours'       => 0,
                'net_resolution_hours'      => null,
                'resolution_status'         => 'pending',
                'ball_holder'               => 'helpdesk',
                'sla_paused_at'             => null,
                'session_start_at'          => $validated,
            ]);

            $this->insertEvent($ticket->ticket_id, $stagingId, null, 'email_received', $slaStart, [
                'response_hours' => 0,
                'notes'          => 'Request masuk',
            ]);
            $this->insertEvent($ticket->ticket_id, $stagingId, null, 'ticket_validated', $validated, [
                'response_hours' => $validHours,
                'notes'          => 'Ticket created',
            ]);

        } catch (\Throwable $e) {
            Log::error('SlaService@attachToTicket', [
                'ticket_id' => $ticket->ticket_id,
                'error'     => $e->getMessage(),
                'file'      => $e->getFile() . ':' . $e->getLine(),
            ]);
        }
    }

    // ─── 2. Rekam event saat pesan dikirim/diterima ──────────────────────────

    public function recordMessageEvent(
        Ticket        $ticket,
        TicketMessage $message,
        string        $senderType,
        ?string       $jarviesStatus = null
    ): void {
        try {
            $sla = TicketSla::with('policy')->where('ticket_id', $ticket->ticket_id)->first();
            if (!$sla || $message->is_internal_note) return;
            if ($sla->isClosed()) return; // tiket sudah selesai, tidak dihitung

            $eventAt = Carbon::parse($message->created_at);

            if ($senderType === 'customer') {
                $this->handleCustomerBurst($sla, $ticket, $message, $eventAt);
            } else {
                $this->handleEmployeeBurst($sla, $ticket, $message, $eventAt, $jarviesStatus ?? 'sent it to support');
            }

        } catch (\Exception $e) {
            Log::error('SlaService@recordMessageEvent', [
                'ticket_id'  => $ticket->ticket_id,
                'message_id' => $message->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    // ─── CUSTOMER BURST ───────────────────────────────────────────────────────
    // Aturan: chat pertama dalam burst = patokan session_start_at (waiting selesai)
    // Chat berikutnya dalam burst yang sama → diabaikan (ball sudah di helpdesk)

    private function handleCustomerBurst(TicketSla $sla, Ticket $ticket, TicketMessage $message, Carbon $eventAt): void
    {
        $is24h        = (bool) ($sla->policy?->is_24_hours ?? true);
        $waitingHours = null;

        if (in_array($sla->ball_holder, ['customer', 'sap'], true) && $sla->sla_paused_at) {
            // Chat pertama dari customer dalam burst ini → akhiri waiting
            $pausedAt     = Carbon::parse($sla->sla_paused_at);
            $waitingHours = $this->calcHours($pausedAt, $eventAt, $is24h);

            $sla->increment('total_waiting_hours', $waitingHours);
            $sla->update([
                'ball_holder'      => 'helpdesk',
                'sla_paused_at'    => null,
                'session_start_at' => $eventAt, // session helpdesk baru mulai dari sini
            ]);
        }
        // Ball sudah di helpdesk (customer kirim lagi dalam burst) → tidak ada state change

        $this->insertEvent($ticket->ticket_id, $sla->staging_ticket_id, $message->id, 'customer_replied', $eventAt, [
            'waiting_hours' => $waitingHours,
            'notes'         => 'Customer membalas',
        ]);
    }

    // ─── EMPLOYEE BURST ───────────────────────────────────────────────────────
    // Aturan double-chat helpdesk:
    //   Status SAMA dengan pesan sebelumnya → sla_paused_at tidak berubah (hitung dari chat pertama yang beda)
    //   Status BEDA → sla_paused_at RESET ke chat ini (chat ini jadi patokan baru)
    //
    // Contoh: A, B, B → patokan adalah chat ke-2 (terakhir yang beda)
    // Contoh: A, B, A → patokan adalah chat ke-3 (terakhir yang beda dari sebelumnya)

    private function handleEmployeeBurst(TicketSla $sla, Ticket $ticket, TicketMessage $message, Carbon $eventAt, string $jarviesStatus): void
    {
        $is24h        = (bool) ($sla->policy?->is_24_hours ?? true);
        $sessionStart = Carbon::parse($sla->session_start_at ?? $sla->sla_start_at);

        // Resolution time = jam kerja helpdesk dari session_start → sekarang
        $resolutionHours = $this->calcHours($sessionStart, $eventAt, $is24h);

        if (in_array($jarviesStatus, self::STOP_STATUSES, true)) {
            $this->handleStop($sla, $ticket, $message, $eventAt, $jarviesStatus, $resolutionHours);

        } elseif (in_array($jarviesStatus, self::RUN_STATUSES, true)) {
            $this->handleRun($sla, $ticket, $message, $eventAt, $jarviesStatus, $resolutionHours, $is24h);

        } elseif (in_array($jarviesStatus, self::END_STATUSES, true)) {
            $this->closeTicketSla($sla, $ticket, $message, $eventAt, $resolutionHours);
        }
    }

    // STOP: bola ke customer/SAP, waiting clock mulai
    private function handleStop(TicketSla $sla, Ticket $ticket, TicketMessage $message, Carbon $eventAt, string $jarviesStatus, float $resolutionHours): void
    {
        if ($sla->ball_holder === 'helpdesk') {
            // Pertama kali STOP dalam burst → mulai waiting dari sekarang
            $sla->update([
                'ball_holder'   => self::BALL_HOLDER_MAP[$jarviesStatus],
                'sla_paused_at' => $eventAt,
            ]);
        } else {
            // Sudah dalam status paused — cek apakah status BERUBAH dari chat sebelumnya
            $lastStatus = $this->lastAgentJarvisStatus($ticket->ticket_id);

            if ($lastStatus !== $jarviesStatus) {
                // Status beda → reset patokan waiting ke chat ini
                $sla->update([
                    'ball_holder'   => self::BALL_HOLDER_MAP[$jarviesStatus],
                    'sla_paused_at' => $eventAt,
                ]);
            }
            // Status sama → sla_paused_at tetap (hitung dari chat pertama yang beda)
        }

        $this->insertEvent($ticket->ticket_id, $sla->staging_ticket_id, $message->id, 'agent_replied', $eventAt, [
            'resolution_hours' => $resolutionHours,
            'jarvis_status'    => $jarviesStatus,
            'notes'            => $this->noteForStatus($jarviesStatus),
        ]);
    }

    // RUN: helpdesk aktif bekerja (atau resume dari SAP)
    private function handleRun(TicketSla $sla, Ticket $ticket, TicketMessage $message, Carbon $eventAt, string $jarviesStatus, float $resolutionHours, bool $is24h): void
    {
        $waitingHours = null;

        if ($sla->ball_holder !== 'helpdesk' && $sla->sla_paused_at) {
            // Agent self-resume (kirim RUN saat bola masih di customer/SAP)
            $pausedAt     = Carbon::parse($sla->sla_paused_at);
            $waitingHours = $this->calcHours($pausedAt, $eventAt, $is24h);

            $sla->increment('total_waiting_hours', $waitingHours);
            $sla->update([
                'ball_holder'      => 'helpdesk',
                'sla_paused_at'    => null,
                'session_start_at' => $eventAt, // mini-session baru setelah self-resume
            ]);

            // Recalculate resolution dari session baru
            $resolutionHours = 0;
        }

        $this->insertEvent($ticket->ticket_id, $sla->staging_ticket_id, $message->id, 'agent_replied', $eventAt, [
            'waiting_hours'    => $waitingHours,
            'resolution_hours' => $resolutionHours,
            'jarvis_status'    => $jarviesStatus,
            'notes'            => $this->noteForStatus($jarviesStatus),
        ]);
    }

    // ─── Close SLA saat tiket ditutup ────────────────────────────────────────

    public function closeTicketSla(TicketSla $sla, Ticket $ticket, ?TicketMessage $message, Carbon $closedAt, ?float $lastSessionHours = null): void
    {
        try {
            $is24h = (bool) ($sla->policy?->is_24_hours ?? true);

            if ($sla->ball_holder !== 'helpdesk' && $sla->sla_paused_at) {
                $finalWaiting = $this->calcHours(Carbon::parse($sla->sla_paused_at), $closedAt, $is24h);
                $sla->increment('total_waiting_hours', $finalWaiting);
            }

            $sla->refresh();
            $grossHours = $this->calcHours(Carbon::parse($sla->sla_start_at), $closedAt, $is24h);
            $netHours   = max(0, round($grossHours - (float) $sla->total_waiting_hours, 2));

            $resTarget = $sla->policy ? (float) $sla->policy->resolution_hours : null;
            $resStatus = $resTarget !== null
                ? ($netHours <= $resTarget ? 'met' : 'breached')
                : 'met';

            $sla->update([
                'resolved_at'          => $closedAt,
                'net_resolution_hours' => $netHours,
                'resolution_status'    => $resStatus,
                'ball_holder'          => 'helpdesk',
                'sla_paused_at'        => null,
                'session_start_at'     => null,
            ]);

            $this->insertEvent($ticket->ticket_id, $sla->staging_ticket_id, $message?->id, 'ticket_closed', $closedAt, [
                'resolution_hours' => $lastSessionHours ?? 0,
                'jarvis_status'    => 'closed',
                'notes'            => 'Ticket closed. Net SLA: ' . $netHours . ' hrs',
            ]);

        } catch (\Throwable $e) {
            Log::error('SlaService@closeTicketSla', [
                'ticket_id' => $ticket->ticket_id,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    // ─── Auto-sync: pastikan semua tiket punya SLA record ────────────────────
    // Dipanggil otomatis saat report diakses. Cepat jika semua sudah tersync
    // (hanya satu COUNT query). Lambat hanya saat pertama kali atau database baru.

    public function ensureTicketsHaveSla(): void
    {
        try {
            // Ambil ticket_id yang belum punya SLA record (satu query)
            $ticketIdsWithSla = DB::table('ticket_sla')
                ->whereNotNull('ticket_id')
                ->pluck('ticket_id');

            $unsyncedTickets = Ticket::whereNotIn('ticket_id', $ticketIdsWithSla)
                ->whereNotNull('ticket_type')
                ->whereNotNull('ticket_priority')
                ->orderBy('ticket_id')
                ->get();

            if ($unsyncedTickets->isEmpty()) return;

            Log::info('SlaService@ensureTicketsHaveSla: syncing ' . $unsyncedTickets->count() . ' ticket(s)');

            // Load staging tickets sekaligus
            $stagingMap = StagingTicket::whereNotNull('ticket_id')
                ->whereIn('ticket_id', $unsyncedTickets->pluck('ticket_id'))
                ->get()
                ->keyBy('ticket_id');

            foreach ($unsyncedTickets as $ticket) {
                $staging = $stagingMap->get($ticket->ticket_id);
                $this->attachToTicket($ticket, $staging);
            }

            // Backfill events dari ticket_message untuk tiket yang baru disync
            $newSlaTicketIds = $unsyncedTickets->pluck('ticket_id')->all();
            $this->backfillEventsForTickets($newSlaTicketIds);

            // Auto-close SLA untuk tiket yang sudah closed
            $this->autoCloseSlaForClosedTickets($newSlaTicketIds);

        } catch (\Throwable $e) {
            // Jangan crash report — log saja, tetap lanjut tampilkan data yang ada
            Log::error('SlaService@ensureTicketsHaveSla', ['error' => $e->getMessage()]);
        }
    }

    // Backfill SLA events dari ticket_message untuk ticket_id tertentu
    private function backfillEventsForTickets(array $ticketIds): void
    {
        if (empty($ticketIds)) return;

        $slaRows = DB::table('ticket_sla')
            ->join('sla_policies', 'sla_policies.id', '=', 'ticket_sla.sla_policy_id')
            ->whereIn('ticket_sla.ticket_id', $ticketIds)
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

        if ($slaRows->isEmpty()) return;

        $slaMap = $slaRows->keyBy('ticket_id');

        $messages = DB::table('ticket_message')
            ->whereIn('ticket_id', $ticketIds)
            ->where('is_internal_note', 0)
            ->whereNotExists(function ($q) {
                $q->from('ticket_sla_events as e')
                    ->whereColumn('e.message_id', 'ticket_message.id');
            })
            ->orderBy('ticket_id')
            ->orderBy('created_at')
            ->get(['id', 'ticket_id', 'sender_type', 'created_at']);

        foreach ($messages->groupBy('ticket_id') as $ticketId => $msgs) {
            $sla = $slaMap->get($ticketId);
            if (!$sla) continue;

            $is24h        = (bool) ($sla->is_24_hours ?? true);
            $ballHolder   = $sla->ball_holder ?? 'helpdesk';
            $pausedAt     = $sla->sla_paused_at ? Carbon::parse($sla->sla_paused_at) : null;
            $sessionStart = $sla->session_start_at
                ? Carbon::parse($sla->session_start_at)
                : Carbon::parse($sla->first_responded_at ?? $sla->sla_start_at);

            $totalWaitingDelta = 0;

            foreach ($msgs as $msg) {
                $eventAt = Carbon::parse($msg->created_at);

                if ($msg->sender_type === 'customer') {
                    $waitingHours = null;
                    if (in_array($ballHolder, ['customer', 'sap'], true) && $pausedAt) {
                        $waitingHours       = $this->calcHours($pausedAt, $eventAt, $is24h);
                        $totalWaitingDelta += $waitingHours;
                        $ballHolder         = 'helpdesk';
                        $pausedAt           = null;
                        $sessionStart       = $eventAt;
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
                } elseif ($msg->sender_type !== 'system') {
                    $resolutionHours = $this->calcHours($sessionStart, $eventAt, $is24h);

                    DB::table('ticket_sla_events')->insert([
                        'ticket_id'         => $ticketId,
                        'staging_ticket_id' => $sla->staging_ticket_id,
                        'message_id'        => $msg->id,
                        'event_type'        => 'agent_replied',
                        'jarvis_status'     => 'in process',
                        'event_at'          => $msg->created_at,
                        'waiting_hours'     => null,
                        'resolution_hours'  => $resolutionHours,
                        'notes'             => 'Balasan helpdesk dikirim',
                        'triggered_by_type' => 'system',
                        'created_at'        => now(),
                    ]);
                }
            }

            if ($totalWaitingDelta > 0) {
                DB::table('ticket_sla')
                    ->where('ticket_id', $ticketId)
                    ->increment('total_waiting_hours', $totalWaitingDelta, [
                        'ball_holder'   => $ballHolder,
                        'sla_paused_at' => $pausedAt,
                    ]);
            } else {
                DB::table('ticket_sla')->where('ticket_id', $ticketId)->update([
                    'ball_holder'   => $ballHolder,
                    'sla_paused_at' => $pausedAt,
                ]);
            }
        }
    }

    // Auto-close SLA untuk tiket yang sudah closed tapi resolution_status masih pending
    private function autoCloseSlaForClosedTickets(array $ticketIds): void
    {
        $closedStatuses = ['closed', 'done', 'resolved'];

        TicketSla::with(['ticket', 'policy'])
            ->whereIn('ticket_id', $ticketIds)
            ->whereIn('resolution_status', ['pending', 'paused'])
            ->each(function (TicketSla $sla) use ($closedStatuses) {
                $ticket = $sla->ticket;
                if (!$ticket || !in_array($ticket->status, $closedStatuses, true)) return;

                $this->closeTicketSla($sla, $ticket, null, Carbon::parse($ticket->updated_at));
            });
    }

    // ─── Helper: status jarvis terakhir yang dikirim agent ───────────────────
    // Dipakai untuk deteksi apakah ini burst dengan status yang sama atau beda

    private function lastAgentJarvisStatus(int $ticketId): ?string
    {
        return DB::table('ticket_sla_events')
            ->where('ticket_id', $ticketId)
            ->where('event_type', 'agent_replied')
            ->whereNotNull('jarvis_status')
            ->orderByDesc('event_at')
            ->value('jarvis_status');
    }

    // ─── Helper: live waiting (termasuk yang sedang berjalan) ────────────────

    public function liveWaitingHours(TicketSla $sla): float
    {
        $total  = (float) $sla->total_waiting_hours;
        $is24h  = (bool) ($sla->policy?->is_24_hours ?? true);

        if ($sla->ball_holder !== 'helpdesk' && $sla->sla_paused_at) {
            $total += $this->calcHours(Carbon::parse($sla->sla_paused_at), now(), $is24h);
        }

        return round($total, 2);
    }

    // ─── Helper: catatan per status ──────────────────────────────────────────

    private function noteForStatus(string $status): string
    {
        return match ($status) {
            'author action'      => 'Menunggu tindakan customer',
            'proposed solution'  => 'Solusi diusulkan, menunggu konfirmasi customer',
            'sent in to SAP'     => 'Diteruskan ke SAP',
            'wait to close'      => 'Menunggu persetujuan penutupan',
            'in process'         => 'Customer sedang mengerjakan / konfirmasi',
            'sent it to support' => 'Diteruskan ke support',
            default              => 'Helpdesk reply',
        };
    }

    // ─── Helper: insert ke ticket_sla_events ─────────────────────────────────

    private function insertEvent(
        int                              $ticketId,
        ?int                             $stagingId,
        ?int                             $messageId,
        string                           $eventType,
        Carbon|\DateTimeInterface|string $eventAt,
        array                            $data = []
    ): void {
        DB::table('ticket_sla_events')->insert([
            'ticket_id'          => $ticketId,
            'staging_ticket_id'  => $stagingId,
            'message_id'         => $messageId,
            'event_type'         => $eventType,
            'jarvis_status'      => $data['jarvis_status']    ?? null,
            'event_at'           => $eventAt,
            'waiting_hours'      => $data['waiting_hours']    ?? null,
            'response_hours'     => $data['response_hours']   ?? null,
            'resolution_hours'   => $data['resolution_hours'] ?? null,
            'notes'              => $data['notes']             ?? null,
            'triggered_by'       => null,
            'triggered_by_type'  => 'system',
            'created_at'         => now(),
        ]);
    }
}

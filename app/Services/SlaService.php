<?php

namespace App\Services;

use App\Models\SlaPolicy;
use App\Models\StagingTicket;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\TicketSla;
use App\Models\TicketSlaEvent;
use App\Models\TicketSlaPause;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SlaService
{
    // ── Status-to-SLA effect mapping (ticket.status values) ──────────────────

    const FULL_SLA_TYPES = ['Incident', 'Service Request'];

    // Statuses that stop the SLA clock and move the ball to customer or SAP
    const STOP_STATUSES = [
        'waiting_on_customer',
        'waiting_to_confirmation',
        'waiting_on_3rd_party',
        'hold',
    ];

    // Statuses that return the ball to helpdesk
    const RUN_STATUSES = ['inprocess', 'open'];

    // Statuses that finalise the SLA
    const END_STATUSES = ['closed', 'cancelled'];

    // END_STATUSES subset that is always finalised as 'met'
    const CANCELLED_STATUSES = ['cancelled'];

    // ticket.status → ball_holder
    const BALL_HOLDER_MAP = [
        'waiting_on_customer'     => 'customer',
        'waiting_to_confirmation' => 'customer',
        'waiting_on_3rd_party'    => 'sap',
        'hold'                    => 'customer',
    ];

    // ── 1. calcHours ─────────────────────────────────────────────────────────

    /**
     * Calculate elapsed hours between two timestamps.
     * is24h = true  → full calendar hours (7×24)
     * is24h = false → business hours only (Mon–Fri 09:00–18:00)
     */
    public function calcHours(Carbon $from, Carbon $to, bool $is24h): float
    {
        if ($from->gte($to)) {
            return 0.0;
        }

        if ($is24h) {
            return round($from->floatDiffInHours($to), 2);
        }

        // Business hours: Mon–Fri 09:00–18:00
        $total   = 0.0;
        $current = $from->copy();

        while ($current->lt($to)) {
            $dayOfWeek = $current->dayOfWeek; // 0=Sun, 6=Sat
            if ($dayOfWeek >= 1 && $dayOfWeek <= 5) {
                $dayStart = $current->copy()->setTime(9, 0, 0);
                $dayEnd   = $current->copy()->setTime(18, 0, 0);

                $periodStart = $current->gt($dayStart) ? $current : $dayStart;
                $periodEnd   = $to->lt($dayEnd) ? $to : $dayEnd;

                if ($periodStart->lt($periodEnd)) {
                    $total += $periodStart->floatDiffInHours($periodEnd);
                }
            }
            $current = $current->copy()->addDay()->setTime(9, 0, 0);
        }

        return round($total, 2);
    }

    // ── 2. attachToStaging ───────────────────────────────────────────────────

    /**
     * Buat SLA record saat staging ticket pertama kali masuk (sebelum divalidasi).
     * SLA clock dimulai sejak email/web submission tiba.
     * Idempotent — skip jika sudah ada.
     */
    public function attachToStaging(StagingTicket $staging): void
    {
        if (TicketSla::where('staging_ticket_id', $staging->id)->exists()) {
            return;
        }

        $slaStart = $staging->created_at;

        TicketSla::create([
            'staging_ticket_id'   => $staging->id,
            'ticket_id'           => null,
            'sla_policy_id'       => null,
            'sla_mode'            => 'full',
            'sla_start_at'        => $slaStart,
            'response_due_at'     => null,
            'resolution_due_at'   => null,
            'response_status'     => 'pending',
            'resolution_status'   => 'pending_validation',
            'ball_holder'         => 'helpdesk',
            'total_waiting_hours' => 0,
        ]);

        TicketSlaEvent::create([
            'staging_ticket_id' => $staging->id,
            'event_type'        => 'email_received',
            'event_at'          => $slaStart,
            'triggered_by_type' => 'system',
            'notes'             => 'SLA clock started — awaiting helpdesk validation',
        ]);

        Log::info('SlaService@attachToStaging: staging SLA record created', [
            'staging_id'  => $staging->id,
            'sla_start_at' => $slaStart,
        ]);
    }

    /**
     * Hapus SLA record staging saat ditolak (rejected).
     */
    public function detachFromStaging(StagingTicket $staging): void
    {
        $sla = TicketSla::where('staging_ticket_id', $staging->id)
                        ->whereNull('ticket_id')
                        ->first();
        if (!$sla) {
            return;
        }

        TicketSlaEvent::where('staging_ticket_id', $staging->id)
                      ->whereNull('ticket_id')
                      ->delete();
        $sla->delete();

        Log::info('SlaService@detachFromStaging: staging SLA record removed (rejected)', [
            'staging_id' => $staging->id,
        ]);
    }

    // ── 3. attachToTicket ────────────────────────────────────────────────────

    /**
     * Promosikan SLA record staging → ticket saat divalidasi.
     *
     * Jika ticket_type BUKAN Incident / Service Request → hapus record staging
     * (tidak masuk SLA report sama sekali).
     *
     * Jika ticket_type valid → update record staging yang sudah ada dengan
     * ticket_id, policy, dan semua kalkulasi waktu.
     *
     * Idempotent — skip jika ticket sudah punya SLA record.
     */
    public function attachToTicket(Ticket $ticket, ?StagingTicket $staging = null): void
    {
        if (!$ticket->ticket_type) {
            Log::info('SlaService@attachToTicket: skipped — ticket_type not set', [
                'ticket_id' => $ticket->ticket_id,
            ]);
            return;
        }

        // Jika ticket.sla sudah ada (bukan dari staging), skip
        if (TicketSla::where('ticket_id', $ticket->ticket_id)->exists()) {
            return;
        }

        // Ticket type tidak eligible → hapus staging record dan keluar
        if (!in_array($ticket->ticket_type, self::FULL_SLA_TYPES)) {
            if ($staging) {
                $this->detachFromStaging($staging);
            }
            Log::info('SlaService@attachToTicket: ticket_type not SLA-eligible — staging record removed', [
                'ticket_id'   => $ticket->ticket_id,
                'ticket_type' => $ticket->ticket_type,
            ]);
            return;
        }

        $priority   = $ticket->ticket_priority ?? 'Medium';
        $scale      = $ticket->scale ?? 'Simple';
        $customerId = $ticket->customer_id;

        $policy = SlaPolicy::findFor($customerId, $priority, $scale);

        if (!$policy) {
            Log::info('SlaService@attachToTicket: no matching policy found', [
                'ticket_id'   => $ticket->ticket_id,
                'customer_id' => $customerId,
                'priority'    => $priority,
                'scale'       => $scale,
            ]);
            return;
        }

        $slaStartAt  = $staging?->created_at ?? $ticket->created_at;
        $respondedAt = $ticket->created_at;

        if ($slaStartAt->gt($respondedAt)) {
            $slaStartAt = $respondedAt;
        }

        $validationHours = $this->calcHours($slaStartAt, $respondedAt, $policy->is_24_hours);
        $responseStatus  = $validationHours <= (float) $policy->response_hours ? 'met' : 'breached';

        // Cek apakah ada staging SLA record yang perlu di-update
        $existingSla = $staging
            ? TicketSla::where('staging_ticket_id', $staging->id)->whereNull('ticket_id')->first()
            : null;

        if ($existingSla) {
            // Update record staging → jadikan record tiket resmi
            $existingSla->update([
                'ticket_id'                 => $ticket->ticket_id,
                'sla_policy_id'             => $policy->id,
                'sla_mode'                  => 'full',
                'sla_start_at'              => $slaStartAt,
                // Response deadline: dari email masuk + response_hours
                'response_due_at'           => $slaStartAt->copy()->addHours((float) $policy->response_hours),
                // Resolution deadline: dari VALIDASI + resolution_hours (bukan dari email masuk)
                'resolution_due_at'         => $respondedAt->copy()->addHours((float) $policy->resolution_hours),
                'first_responded_at'        => $respondedAt,
                'validation_duration_hours' => $validationHours,
                'response_status'           => $responseStatus,
                'resolution_status'         => 'pending',
                'ball_holder'               => 'helpdesk',
                'session_start_at'          => $respondedAt,
            ]);

            // Update event email_received agar ticket_id terisi
            TicketSlaEvent::where('staging_ticket_id', $staging->id)
                          ->where('event_type', 'email_received')
                          ->whereNull('ticket_id')
                          ->update(['ticket_id' => $ticket->ticket_id]);
        } else {
            // Tidak ada staging record — buat dari awal (fallback / ticket langsung)
            TicketSla::create([
                'staging_ticket_id'         => $staging?->id,
                'ticket_id'                 => $ticket->ticket_id,
                'sla_policy_id'             => $policy->id,
                'sla_mode'                  => 'full',
                'sla_start_at'              => $slaStartAt,
                // Response deadline: dari email masuk + response_hours
                'response_due_at'           => $slaStartAt->copy()->addHours((float) $policy->response_hours),
                // Resolution deadline: dari VALIDASI + resolution_hours (bukan dari email masuk)
                'resolution_due_at'         => $respondedAt->copy()->addHours((float) $policy->resolution_hours),
                'first_responded_at'        => $respondedAt,
                'validation_duration_hours' => $validationHours,
                'response_status'           => $responseStatus,
                'resolution_status'         => 'pending',
                'ball_holder'               => 'helpdesk',
                'session_start_at'          => $respondedAt,
                'total_waiting_hours'       => 0,
            ]);

            TicketSlaEvent::create([
                'ticket_id'         => $ticket->ticket_id,
                'staging_ticket_id' => $staging?->id,
                'event_type'        => 'email_received',
                'event_at'          => $slaStartAt,
                'triggered_by_type' => 'system',
                'notes'             => 'SLA clock started',
            ]);
        }

        // Event: ticket_validated
        TicketSlaEvent::create([
            'ticket_id'         => $ticket->ticket_id,
            'staging_ticket_id' => $staging?->id,
            'event_type'        => 'ticket_validated',
            'event_at'          => $respondedAt,
            'response_hours'    => $validationHours,
            'triggered_by_type' => 'system',
            'notes'             => 'Response SLA: ' . $validationHours . ' hrs (' . $responseStatus . ')',
        ]);

        Log::info('SlaService@attachToTicket: SLA record promoted/created', [
            'ticket_id'        => $ticket->ticket_id,
            'staging_id'       => $staging?->id,
            'policy_id'        => $policy->id,
            'response_status'  => $responseStatus,
            'validation_hours' => $validationHours,
            'was_staging'      => $existingSla !== null,
        ]);
    }

    // ── 3. recordMessageEvent ────────────────────────────────────────────────

    /**
     * Called every time a non-internal message is created.
     * $ticketStatus = ticket->status at the time the message was sent
     * (replaces the old jarvies_status field on messages).
     */
    public function recordMessageEvent(
        Ticket        $ticket,
        TicketMessage $message,
        string        $senderType,
        ?string       $ticketStatus
    ): void {
        $sla = $ticket->sla;

        if (!$sla || $message->is_internal_note || $sla->isClosed()) {
            return;
        }

        if ($senderType === 'customer') {
            $this->handleCustomerBurst($sla, $ticket, $message);
        } else {
            $status = $ticketStatus ?? 'inprocess';
            $this->handleEmployeeBurst($sla, $ticket, $message, $status);
        }
    }

    // ── 4. handleStatusChange ────────────────────────────────────────────────

    /**
     * Called when ticket.status changes WITHOUT an accompanying message.
     * Updates ball_holder / sla_paused_at state only — no event is inserted.
     */
    public function handleStatusChange(Ticket $ticket, string $newStatus): void
    {
        $sla = $ticket->sla;

        if (!$sla || $sla->isClosed()) {
            return;
        }

        if (in_array($newStatus, self::END_STATUSES)) {
            $isCancelled = in_array($newStatus, self::CANCELLED_STATUSES);
            $this->closeTicketSla($sla, $ticket, null, now(), null, $isCancelled);
            return;
        }

        if (in_array($newStatus, self::STOP_STATUSES)) {
            $this->applyStop($sla, $newStatus);
        } elseif (in_array($newStatus, self::RUN_STATUSES)) {
            $this->applyRun($sla);
        }

        $sla->save();
    }

    // ── 5. liveWaitingHours ──────────────────────────────────────────────────

    public function liveWaitingHours(TicketSla $sla): float
    {
        $total = (float) $sla->total_waiting_hours;
        $is24h = $sla->policy?->is_24_hours ?? true;

        if ($sla->ball_holder !== 'helpdesk' && $sla->sla_paused_at) {
            $total += $this->calcHours($sla->sla_paused_at, now(), $is24h);
        }

        return round($total, 2);
    }

    // ── 6. closeTicketSla ────────────────────────────────────────────────────

    public function closeTicketSla(
        TicketSla      $sla,
        Ticket         $ticket,
        ?TicketMessage $message,
        Carbon         $closedAt,
        ?float         $lastSessionHours,
        bool           $isCancelled = false
    ): void {
        $policy = $sla->policy;
        $is24h  = $policy?->is_24_hours ?? true;

        // Add any remaining waiting time if ball was not with helpdesk at close
        $finalWaiting = 0.0;
        if ($sla->ball_holder !== 'helpdesk' && $sla->sla_paused_at) {
            $finalWaiting = $this->calcHours($sla->sla_paused_at, $closedAt, $is24h);
        }

        $sla->refresh();
        $totalWaiting = (float) $sla->total_waiting_hours + $finalWaiting;

        // Resolution dimulai saat ticket divalidasi (first_responded_at), bukan saat email masuk.
        // Response SLA sudah mengukur email → validasi secara terpisah.
        $resolutionStart = $sla->first_responded_at ?? $sla->sla_start_at;
        $grossHours      = $this->calcHours($resolutionStart, $closedAt, $is24h);
        $netHours        = max(0.0, $grossHours - $totalWaiting);

        if ($isCancelled) {
            $resStatus = 'met';
        } elseif (!$policy || $sla->sla_mode === 'response_only') {
            $resStatus = 'met';
        } else {
            $resStatus = $netHours <= (float) $policy->resolution_hours ? 'met' : 'breached';
        }

        $sla->update([
            'resolved_at'          => $closedAt,
            'net_resolution_hours' => $netHours,
            'total_waiting_hours'  => $totalWaiting,
            'resolution_status'    => $resStatus,
            'ball_holder'          => 'helpdesk',
            'sla_paused_at'        => null,
            'session_start_at'     => null,
        ]);

        TicketSlaEvent::create([
            'ticket_id'         => $ticket->ticket_id,
            'message_id'        => $message?->id,
            'event_type'        => 'ticket_closed',
            'event_at'          => $closedAt,
            'resolution_hours'  => $lastSessionHours,
            'triggered_by_type' => 'system',
            'notes'             => 'Net SLA: ' . round($netHours, 2) . ' hrs (' . $resStatus . ')'
                                   . ($isCancelled ? ' [cancelled]' : ''),
        ]);
    }

    // ── 7. ensureTicketsHaveSla ──────────────────────────────────────────────

    /**
     * Auto-sync: ensures all eligible tickets have an SLA record.
     * Safe to call repeatedly — idempotent.
     */
    public function ensureTicketsHaveSla(): void
    {
        $existingIds = TicketSla::whereNotNull('ticket_id')->pluck('ticket_id')->toArray();

        $missing = Ticket::whereNotIn('ticket_id', $existingIds)
            ->whereNotNull('ticket_type')
            ->whereNotNull('ticket_priority')
            ->get();

        if ($missing->isEmpty()) {
            return;
        }

        $newIds = [];
        foreach ($missing as $ticket) {
            try {
                $staging = $ticket->stagingTicket ?? null;
                $this->attachToTicket($ticket, $staging);
                $newIds[] = $ticket->ticket_id;
            } catch (\Throwable $e) {
                Log::warning('SlaService@ensureTicketsHaveSla: failed to attach', [
                    'ticket_id' => $ticket->ticket_id,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        if (!empty($newIds)) {
            $this->backfillEventsForTickets($newIds);
            $this->autoCloseSlaForClosedTickets($newIds);
        }
    }

    // ── 8. backfillEventsForTickets ──────────────────────────────────────────

    public function backfillEventsForTickets(array $ticketIds): void
    {
        $tickets = Ticket::with(['sla', 'messages' => fn ($q) => $q->orderBy('created_at')])
            ->whereIn('ticket_id', $ticketIds)
            ->get();

        foreach ($tickets as $ticket) {
            $sla = $ticket->sla;
            if (!$sla) {
                continue;
            }

            foreach ($ticket->messages as $msg) {
                if ($msg->is_internal_note || $sla->isClosed()) {
                    break;
                }

                $senderType = $msg->sender_type === 'customer' ? 'customer' : 'employee';
                $this->recordMessageEvent($ticket, $msg, $senderType, null);
                $sla->refresh();
            }
        }
    }

    // ── 9. autoCloseSlaForClosedTickets ──────────────────────────────────────

    public function autoCloseSlaForClosedTickets(array $ticketIds): void
    {
        $tickets = Ticket::with('sla')
            ->whereIn('ticket_id', $ticketIds)
            ->whereIn('status', ['closed', 'cancelled'])
            ->get();

        foreach ($tickets as $ticket) {
            $sla = $ticket->sla;
            if (!$sla || $sla->isClosed()) {
                continue;
            }

            $isCancelled = $ticket->status === 'cancelled';
            $closedAt    = $ticket->updated_at ?? now();
            $this->closeTicketSla($sla, $ticket, null, $closedAt, null, $isCancelled);
        }
    }

    // ── Private: handleCustomerBurst ─────────────────────────────────────────

    private function handleCustomerBurst(TicketSla $sla, Ticket $ticket, TicketMessage $message): void
    {
        $is24h    = $sla->policy?->is_24_hours ?? true;
        $waitingH = null;

        if (in_array($sla->ball_holder, ['customer', 'sap']) && $sla->sla_paused_at) {
            $waitingH = $this->calcHours($sla->sla_paused_at, $message->created_at, $is24h);

            $sla->total_waiting_hours = (float) $sla->total_waiting_hours + $waitingH;
            $sla->ball_holder         = 'helpdesk';
            $sla->sla_paused_at       = null;
            $sla->session_start_at    = $message->created_at;
            $sla->resolution_status   = 'pending';
            $sla->save();

            TicketSlaPause::where('ticket_id', $ticket->ticket_id)
                ->whereNull('ended_at')
                ->update([
                    'ended_at'            => $message->created_at,
                    'duration_hours'      => $waitingH,
                    'ended_by_message_id' => $message->id,
                ]);
        }

        TicketSlaEvent::create([
            'ticket_id'         => $ticket->ticket_id,
            'message_id'        => $message->id,
            'event_type'        => 'customer_replied',
            'event_at'          => $message->created_at,
            'waiting_hours'     => $waitingH,
            'triggered_by'      => $message->sender_id,
            'triggered_by_type' => 'customer',
            'notes'             => $waitingH !== null
                                   ? 'Customer replied after ' . $waitingH . ' hrs'
                                   : 'Customer replied',
        ]);
    }

    // ── Private: handleEmployeeBurst ─────────────────────────────────────────

    private function handleEmployeeBurst(
        TicketSla     $sla,
        Ticket        $ticket,
        TicketMessage $message,
        string        $ticketStatus
    ): void {
        $is24h        = $sla->policy?->is_24_hours ?? true;
        $sessionStart = $sla->session_start_at ?? $sla->sla_start_at;
        $resolutionH  = $this->calcHours($sessionStart, $message->created_at, $is24h);

        if (in_array($ticketStatus, self::STOP_STATUSES)) {
            $this->handleStop($sla, $ticket, $message, $ticketStatus, $resolutionH);
        } elseif (in_array($ticketStatus, self::RUN_STATUSES)) {
            $this->handleRun($sla, $ticket, $message, $ticketStatus, $resolutionH);
        } elseif (in_array($ticketStatus, self::END_STATUSES)) {
            $isCancelled = in_array($ticketStatus, self::CANCELLED_STATUSES);
            $this->closeTicketSla($sla, $ticket, $message, $message->created_at, $resolutionH, $isCancelled);
        } else {
            // Unknown status — log the event without changing state
            TicketSlaEvent::create([
                'ticket_id'         => $ticket->ticket_id,
                'message_id'        => $message->id,
                'event_type'        => 'agent_replied',
                'jarvis_status'     => $ticketStatus,
                'event_at'          => $message->created_at,
                'resolution_hours'  => $resolutionH,
                'triggered_by'      => $message->sender_id,
                'triggered_by_type' => 'employee',
                'notes'             => $this->noteForStatus($ticketStatus),
            ]);
        }
    }

    // ── Private: handleStop ──────────────────────────────────────────────────

    private function handleStop(
        TicketSla     $sla,
        Ticket        $ticket,
        TicketMessage $message,
        string        $ticketStatus,
        float         $resolutionH
    ): void {
        $newBallHolder = self::BALL_HOLDER_MAP[$ticketStatus] ?? 'customer';

        if ($sla->ball_holder === 'helpdesk') {
            // First stop in this burst — start the waiting clock
            $sla->ball_holder       = $newBallHolder;
            $sla->sla_paused_at     = $message->created_at;
            $sla->resolution_status = 'paused';
            $sla->save();

            TicketSlaPause::create([
                'ticket_id'             => $ticket->ticket_id,
                'pause_reason'          => $this->pauseReasonFor($ticketStatus),
                'triggered_by_status'   => $ticketStatus,
                'started_at'            => $message->created_at,
                'started_by_message_id' => $message->id,
            ]);
        } else {
            // Already paused — apply burst coalescing
            $lastStatus = $this->lastAgentStatus($ticket->ticket_id);
            if ($lastStatus !== $ticketStatus) {
                // Status changed — reset the pause baseline
                $sla->ball_holder   = $newBallHolder;
                $sla->sla_paused_at = $message->created_at;
                $sla->save();

                TicketSlaPause::where('ticket_id', $ticket->ticket_id)
                    ->whereNull('ended_at')
                    ->update(['ended_at' => $message->created_at, 'duration_hours' => 0]);

                TicketSlaPause::create([
                    'ticket_id'             => $ticket->ticket_id,
                    'pause_reason'          => $this->pauseReasonFor($ticketStatus),
                    'triggered_by_status'   => $ticketStatus,
                    'started_at'            => $message->created_at,
                    'started_by_message_id' => $message->id,
                ]);
            }
            // Same status as before — no baseline reset (burst coalescing)
        }

        TicketSlaEvent::create([
            'ticket_id'         => $ticket->ticket_id,
            'message_id'        => $message->id,
            'event_type'        => 'agent_replied',
            'jarvis_status'     => $ticketStatus,
            'event_at'          => $message->created_at,
            'resolution_hours'  => $resolutionH,
            'triggered_by'      => $message->sender_id,
            'triggered_by_type' => 'employee',
            'notes'             => $this->noteForStatus($ticketStatus),
        ]);
    }

    // ── Private: handleRun ───────────────────────────────────────────────────

    private function handleRun(
        TicketSla     $sla,
        Ticket        $ticket,
        TicketMessage $message,
        string        $ticketStatus,
        float         $resolutionH
    ): void {
        $is24h    = $sla->policy?->is_24_hours ?? true;
        $waitingH = null;

        if ($sla->ball_holder !== 'helpdesk' && $sla->sla_paused_at) {
            $waitingH = $this->calcHours($sla->sla_paused_at, $message->created_at, $is24h);
            $sla->total_waiting_hours = (float) $sla->total_waiting_hours + $waitingH;
            $sla->ball_holder         = 'helpdesk';
            $sla->sla_paused_at       = null;
            $sla->session_start_at    = $message->created_at;
            $sla->resolution_status   = 'pending';
            $resolutionH              = 0;
            $sla->save();

            TicketSlaPause::where('ticket_id', $ticket->ticket_id)
                ->whereNull('ended_at')
                ->update([
                    'ended_at'            => $message->created_at,
                    'duration_hours'      => $waitingH,
                    'ended_by_message_id' => $message->id,
                ]);
        }

        TicketSlaEvent::create([
            'ticket_id'         => $ticket->ticket_id,
            'message_id'        => $message->id,
            'event_type'        => 'agent_replied',
            'jarvis_status'     => $ticketStatus,
            'event_at'          => $message->created_at,
            'waiting_hours'     => $waitingH,
            'resolution_hours'  => $resolutionH,
            'triggered_by'      => $message->sender_id,
            'triggered_by_type' => 'employee',
            'notes'             => $this->noteForStatus($ticketStatus),
        ]);
    }

    // ── Private: applyStop / applyRun (state-only, no event) ─────────────────

    private function applyStop(TicketSla $sla, string $newStatus): void
    {
        $newBallHolder = self::BALL_HOLDER_MAP[$newStatus] ?? 'customer';

        if ($sla->ball_holder === 'helpdesk') {
            $sla->ball_holder       = $newBallHolder;
            $sla->sla_paused_at     = now();
            $sla->resolution_status = 'paused';
        } elseif ($sla->ball_holder !== $newBallHolder) {
            // Ball holder changed — update without resetting total waiting
            $sla->ball_holder   = $newBallHolder;
            $sla->sla_paused_at = now();
        }
    }

    private function applyRun(TicketSla $sla): void
    {
        if ($sla->ball_holder !== 'helpdesk' && $sla->sla_paused_at) {
            $is24h    = $sla->policy?->is_24_hours ?? true;
            $waitingH = $this->calcHours($sla->sla_paused_at, now(), $is24h);

            $sla->total_waiting_hours = (float) $sla->total_waiting_hours + $waitingH;
            $sla->ball_holder         = 'helpdesk';
            $sla->sla_paused_at       = null;
            $sla->session_start_at    = now();
            $sla->resolution_status   = 'pending';

            TicketSlaPause::where('ticket_id', $sla->ticket_id)
                ->whereNull('ended_at')
                ->update([
                    'ended_at'       => now(),
                    'duration_hours' => $waitingH,
                ]);
        }
    }

    // ── Private: helpers ─────────────────────────────────────────────────────

    private function lastAgentStatus(int $ticketId): ?string
    {
        return TicketSlaEvent::where('ticket_id', $ticketId)
            ->where('event_type', 'agent_replied')
            ->whereNotNull('jarvis_status')
            ->orderByDesc('event_at')
            ->value('jarvis_status');
    }

    private function pauseReasonFor(string $status): string
    {
        return match ($status) {
            'waiting_on_3rd_party' => 'sent_to_sap',
            'hold'                 => 'on_hold',
            default                => 'waiting_customer',
        };
    }

    private function noteForStatus(string $status): string
    {
        return match ($status) {
            'waiting_on_customer'     => 'Waiting for customer response',
            'waiting_to_confirmation' => 'Waiting for customer confirmation',
            'waiting_on_3rd_party'    => 'Escalated to SAP / third party',
            'hold'                    => 'Ticket on hold',
            'inprocess'               => 'Helpdesk actively working',
            'open'                    => 'Ticket reopened',
            'closed'                  => 'Ticket resolved',
            'cancelled'               => 'Ticket cancelled',
            default                   => $status,
        };
    }

    // ── 10. startMeeting ─────────────────────────────────────────────────────

    /**
     * Pause SLA clock saat meeting dimulai — ball berpindah ke customer.
     * Jika ball sudah tidak di helpdesk, hanya catat event tanpa mengubah state.
     */
    public function startMeeting(Ticket $ticket, ?Carbon $startAt = null): void
    {
        $sla = $ticket->sla;
        if (!$sla || $sla->isClosed()) {
            return;
        }

        $at = $startAt ?? now();

        if ($sla->ball_holder === 'helpdesk') {
            $sla->ball_holder       = 'customer';
            $sla->sla_paused_at     = $at;
            $sla->resolution_status = 'paused';
            $sla->save();

            TicketSlaPause::create([
                'ticket_id'           => $ticket->ticket_id,
                'pause_reason'        => 'meeting',
                'triggered_by_status' => 'meeting',
                'started_at'          => $at,
            ]);
        }

        TicketSlaEvent::create([
            'ticket_id'         => $ticket->ticket_id,
            'event_type'        => 'meeting_started',
            'event_at'          => $at,
            'triggered_by_type' => 'employee',
            'notes'             => 'Meeting started — SLA clock paused',
        ]);
    }

    // ── 11. endMeeting ───────────────────────────────────────────────────────

    /**
     * Resume SLA clock saat meeting selesai — ball kembali ke helpdesk.
     * Durasi meeting dihitung sebagai waiting hours dan diakumulasi ke total.
     */
    public function endMeeting(Ticket $ticket, ?Carbon $endAt = null): void
    {
        $sla = $ticket->sla;
        if (!$sla || $sla->isClosed()) {
            return;
        }

        $at       = $endAt ?? now();
        $is24h    = $sla->policy?->is_24_hours ?? true;
        $waitingH = null;

        if ($sla->ball_holder !== 'helpdesk' && $sla->sla_paused_at) {
            $waitingH = $this->calcHours($sla->sla_paused_at, $at, $is24h);

            $sla->total_waiting_hours = (float) $sla->total_waiting_hours + $waitingH;
            $sla->ball_holder         = 'helpdesk';
            $sla->sla_paused_at       = null;
            $sla->session_start_at    = $at;
            $sla->resolution_status   = 'pending';
            $sla->save();

            TicketSlaPause::where('ticket_id', $ticket->ticket_id)
                ->whereNull('ended_at')
                ->update([
                    'ended_at'       => $at,
                    'duration_hours' => $waitingH,
                ]);
        }

        TicketSlaEvent::create([
            'ticket_id'         => $ticket->ticket_id,
            'event_type'        => 'meeting_ended',
            'event_at'          => $at,
            'waiting_hours'     => $waitingH,
            'triggered_by_type' => 'employee',
            'notes'             => $waitingH !== null
                                   ? 'Meeting ended — ' . round($waitingH, 2) . ' hrs counted as waiting'
                                   : 'Meeting ended',
        ]);
    }
}

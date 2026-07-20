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
    public function __construct(protected HolidayService $holidays)
    {
    }

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
     * is24h = false → business hours only (Mon–Fri 08:00–17:00)
     * Result is never negative — $from at/after $to returns 0.0.
     */
    public function calcHours(Carbon $from, Carbon $to, bool $is24h): float
    {
        if ($from->gte($to)) {
            return 0.0;
        }

        if ($is24h) {
            return round($from->floatDiffInHours($to), 2);
        }

        // Business hours: Mon–Fri 08:00–17:00
        $total   = 0.0;
        $current = $from->copy();

        while ($current->lt($to)) {
            $dayOfWeek = $current->dayOfWeek; // 0=Sun, 6=Sat
            if ($dayOfWeek >= 1 && $dayOfWeek <= 5) {
                $dayStart = $current->copy()->setTime(8, 0, 0);
                $dayEnd   = $current->copy()->setTime(17, 0, 0);

                $periodStart = $current->gt($dayStart) ? $current : $dayStart;
                $periodEnd   = $to->lt($dayEnd) ? $to : $dayEnd;

                if ($periodStart->lt($periodEnd)) {
                    $total += $periodStart->floatDiffInHours($periodEnd);
                }
            }
            $current = $current->copy()->addDay()->setTime(8, 0, 0);
        }

        return round($total, 2);
    }

    /**
     * Resolve the SLA clock start time given when the ticket was received and its policy.
     *
     * - No policy, 24/7 policy, or policy without a configured work window → start = received time as-is.
     * - Otherwise: if received within the policy's work window (excluding any break window) on a working
     *   day, start = received time as-is. If received during the break, start = end of break, same day.
     *   If outside the work window entirely (or on a weekend/holiday), roll forward to the next working
     *   day's window start.
     */
    private function resolveSlaStartAt(Carbon $receivedAt, ?SlaPolicy $policy): Carbon
    {
        if (!$policy || $policy->is_24_hours || !$policy->work_start_time || !$policy->work_end_time) {
            return $receivedAt->copy();
        }

        [$startH, $startM] = array_map('intval', explode(':', $policy->work_start_time));
        [$endH, $endM]     = array_map('intval', explode(':', $policy->work_end_time));

        $hasBreak = $policy->break_start_time && $policy->break_end_time;
        if ($hasBreak) {
            [$brkStartH, $brkStartM] = array_map('intval', explode(':', $policy->break_start_time));
            [$brkEndH, $brkEndM]     = array_map('intval', explode(':', $policy->break_end_time));
        }

        $cursor = $receivedAt->copy();

        for ($i = 0; $i < 30; $i++) {
            if ($this->holidays->isNonWorkingDay($cursor)) {
                $cursor = $cursor->copy()->addDay()->setTime($startH, $startM, 0);
                continue;
            }

            $dayStart = $cursor->copy()->setTime($startH, $startM, 0);
            $dayEnd   = $cursor->copy()->setTime($endH, $endM, 0);

            if ($cursor->lt($dayStart)) {
                return $dayStart;
            }

            if ($hasBreak) {
                $breakStart = $cursor->copy()->setTime($brkStartH, $brkStartM, 0);
                $breakEnd   = $cursor->copy()->setTime($brkEndH, $brkEndM, 0);
                if ($cursor->gte($breakStart) && $cursor->lt($breakEnd)) {
                    return $breakEnd;
                }
            }

            if ($cursor->lt($dayEnd)) {
                return $cursor->copy();
            }

            $cursor = $cursor->copy()->addDay()->setTime($startH, $startM, 0);
        }

        Log::warning('SlaService@resolveSlaStartAt: exceeded rollover safety bound', [
            'received_at' => $receivedAt->toDateTimeString(),
            'policy_id'   => $policy->id,
        ]);

        return $cursor;
    }

    /**
     * Add a duration of SLA response hours to a starting timestamp, counting only
     * minutes that fall inside the policy's working window (skipping breaks, non-working
     * days/holidays, and out-of-window time) — mirrors resolveSlaStartAt's working-hours
     * rules so "Response Due On" stays consistent with "Start SLA Response".
     *
     * No policy, a 24/7 policy, or a policy without a configured work window → plain
     * calendar addHours (same fallback used elsewhere for these cases).
     */
    private function addWorkingHours(Carbon $from, float $hours, ?SlaPolicy $policy): Carbon
    {
        if ($hours <= 0) {
            return $from->copy();
        }

        if (!$policy || $policy->is_24_hours || !$policy->work_start_time || !$policy->work_end_time) {
            return $from->copy()->addHours($hours);
        }

        [$startH, $startM] = array_map('intval', explode(':', $policy->work_start_time));
        [$endH, $endM]     = array_map('intval', explode(':', $policy->work_end_time));

        $hasBreak = $policy->break_start_time && $policy->break_end_time;
        if ($hasBreak) {
            [$brkStartH, $brkStartM] = array_map('intval', explode(':', $policy->break_start_time));
            [$brkEndH, $brkEndM]     = array_map('intval', explode(':', $policy->break_end_time));
        }

        $remainingMinutes = (int) round($hours * 60);
        $cursor           = $from->copy();

        for ($i = 0; $i < 400 && $remainingMinutes > 0; $i++) {
            if ($this->holidays->isNonWorkingDay($cursor)) {
                $cursor = $cursor->copy()->addDay()->setTime($startH, $startM, 0);
                continue;
            }

            $dayStart = $cursor->copy()->setTime($startH, $startM, 0);
            $dayEnd   = $cursor->copy()->setTime($endH, $endM, 0);

            if ($cursor->lt($dayStart)) {
                $cursor = $dayStart;
            }
            if ($cursor->gte($dayEnd)) {
                $cursor = $cursor->copy()->addDay()->setTime($startH, $startM, 0);
                continue;
            }

            if ($hasBreak) {
                $breakStart = $cursor->copy()->setTime($brkStartH, $brkStartM, 0);
                $breakEnd   = $cursor->copy()->setTime($brkEndH, $brkEndM, 0);
                if ($cursor->gte($breakStart) && $cursor->lt($breakEnd)) {
                    $cursor = $breakEnd;
                    continue;
                }
            }

            // Usable segment today runs from $cursor to the next break start (if any) or day end
            $segmentEnd = $dayEnd;
            if ($hasBreak) {
                $breakStart = $cursor->copy()->setTime($brkStartH, $brkStartM, 0);
                if ($breakStart->gt($cursor) && $breakStart->lt($segmentEnd)) {
                    $segmentEnd = $breakStart;
                }
            }

            $availableMinutes = $cursor->diffInMinutes($segmentEnd);

            if ($remainingMinutes <= $availableMinutes) {
                return $cursor->copy()->addMinutes($remainingMinutes);
            }

            $remainingMinutes -= $availableMinutes;
            $cursor = $segmentEnd;
        }

        Log::warning('SlaService@addWorkingHours: exceeded rollover safety bound', [
            'from'      => $from->toDateTimeString(),
            'hours'     => $hours,
            'policy_id' => $policy->id,
        ]);

        return $cursor;
    }

    /**
     * Response-due timestamp for display, computed live from sla_start_at + the currently
     * attached policy's response_hours (working-hours-aware). Always reflects the policy's
     * current configuration rather than whatever was frozen into the stored response_due_at
     * column at attach/sync time.
     */
    public function responseDueAt(TicketSla $sla): ?Carbon
    {
        if (!$sla->sla_start_at || !$sla->policy) {
            return null;
        }

        return $this->addWorkingHours($sla->sla_start_at, (float) $sla->policy->response_hours, $sla->policy);
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

        $priority          = $ticket->ticket_priority ?? 'Medium';
        $scale             = $ticket->scale ?? 'Simple';
        $deliverySupportId = \Illuminate\Support\Facades\DB::table('delivery_support_activities')
            ->where('ticket_id', $ticket->ticket_id)
            ->value('delivery_support_id');

        $policy = SlaPolicy::findFor($deliverySupportId, $priority, $scale);

        $receivedAt  = $staging?->created_at ?? $ticket->created_at;
        $respondedAt = $ticket->created_at;

        if ($receivedAt->gt($respondedAt)) {
            $receivedAt = $respondedAt;
        }

        $slaStartAt = $this->resolveSlaStartAt($receivedAt, $policy);

        // Calculate validation hours; use business hours as default when policy not yet known
        $is24h           = $policy ? $policy->is_24_hours : false;
        $validationHours = $this->calcHours($slaStartAt, $respondedAt, $is24h);
        $responseStatus  = $policy
            ? ($validationHours <= (float) $policy->response_hours ? 'met' : 'breached')
            : 'pending';

        // Cek apakah ada staging SLA record yang perlu di-update
        $existingSla = $staging
            ? TicketSla::where('staging_ticket_id', $staging->id)->whereNull('ticket_id')->first()
            : null;

        if ($existingSla) {
            // Update record staging → jadikan record tiket resmi
            $existingSla->update([
                'ticket_id'                 => $ticket->ticket_id,
                'sla_policy_id'             => $policy?->id,
                'sla_mode'                  => 'full',
                'sla_start_at'              => $slaStartAt,
                'response_due_at'           => $policy ? $this->addWorkingHours($slaStartAt, (float) $policy->response_hours, $policy) : null,
                'resolution_due_at'         => $policy ? $respondedAt->copy()->addHours((float) $policy->resolution_hours) : null,
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
                'sla_policy_id'             => $policy?->id,
                'sla_mode'                  => 'full',
                'sla_start_at'              => $slaStartAt,
                'response_due_at'           => $policy ? $this->addWorkingHours($slaStartAt, (float) $policy->response_hours, $policy) : null,
                'resolution_due_at'         => $policy ? $respondedAt->copy()->addHours((float) $policy->resolution_hours) : null,
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
        $notesText = $policy
            ? 'Response SLA: ' . $validationHours . ' hrs (' . $responseStatus . ')'
            : 'SLA clock running — policy will be applied when delivery support is assigned';

        TicketSlaEvent::create([
            'ticket_id'         => $ticket->ticket_id,
            'staging_ticket_id' => $staging?->id,
            'event_type'        => 'ticket_validated',
            'event_at'          => $respondedAt,
            'response_hours'    => $validationHours,
            'triggered_by_type' => 'system',
            'notes'             => $notesText,
        ]);

        Log::info('SlaService@attachToTicket: SLA record promoted/created', [
            'ticket_id'        => $ticket->ticket_id,
            'staging_id'       => $staging?->id,
            'policy_id'        => $policy?->id,
            'response_status'  => $responseStatus,
            'validation_hours' => $validationHours,
            'was_staging'      => $existingSla !== null,
            'policy_deferred'  => $policy === null,
        ]);
    }

    // ── 3b. syncPolicy ───────────────────────────────────────────────────────

    /**
     * Apply (or re-apply) SLA policy to a ticket that already has an SLA record
     * but whose delivery support was not yet known at validation time.
     *
     * Safe to call multiple times; skips if the SLA is already finalised (met/breached).
     */
    public function syncPolicy(Ticket $ticket, ?int $deliverySupportId = null): void
    {
        $sla = TicketSla::where('ticket_id', $ticket->ticket_id)->first();

        if (!$sla) {
            // SLA record never created — try attaching now (delivery support is now known)
            $this->attachToTicket($ticket);
            return;
        }

        // Already has a resolved response status — don't regress
        if (in_array($sla->response_status, ['met', 'breached'], true) && $sla->sla_policy_id) {
            return;
        }

        $priority = $ticket->ticket_priority ?? 'Medium';
        $scale    = $ticket->scale ?? 'Simple';

        // Use explicitly provided ID, fallback to lookup from delivery_support_activities
        if ($deliverySupportId === null) {
            $deliverySupportId = \Illuminate\Support\Facades\DB::table('delivery_support_activities')
                ->where('ticket_id', $ticket->ticket_id)
                ->value('delivery_support_id');
        }

        $policy = SlaPolicy::findFor($deliverySupportId, $priority, $scale);

        if (!$policy) {
            Log::info('SlaService@syncPolicy: still no matching policy after delivery support assignment', [
                'ticket_id'           => $ticket->ticket_id,
                'delivery_support_id' => $deliverySupportId,
            ]);
            return;
        }

        // Recompute from the true original received time (ticket/staging created_at),
        // never from $sla->sla_start_at — that column gets overwritten with the resolved
        // (already rolled-forward) value once a policy is applied, so re-running this after
        // the policy's work-hour window changes must not use it as the "received" input again.
        $staging    = $sla->stagingTicket;
        $receivedAt = $staging?->created_at ?? $ticket->created_at;
        if ($receivedAt->gt($ticket->created_at)) {
            $receivedAt = $ticket->created_at;
        }

        $slaStartAt  = $this->resolveSlaStartAt($receivedAt, $policy);
        $respondedAt = $sla->first_responded_at ?? $ticket->created_at;

        $validationHours = $this->calcHours($slaStartAt, $respondedAt, $policy->is_24_hours);
        $responseStatus  = $validationHours <= (float) $policy->response_hours ? 'met' : 'breached';

        $sla->update([
            'sla_policy_id'             => $policy->id,
            'sla_start_at'              => $slaStartAt,
            'response_due_at'           => $this->addWorkingHours($slaStartAt, (float) $policy->response_hours, $policy),
            'resolution_due_at'         => $respondedAt->copy()->addHours((float) $policy->resolution_hours),
            'validation_duration_hours' => $validationHours,
            'response_status'           => $responseStatus,
        ]);

        // Update the ticket_validated event note to reflect the now-known policy
        TicketSlaEvent::where('ticket_id', $ticket->ticket_id)
            ->where('event_type', 'ticket_validated')
            ->update([
                'response_hours' => $validationHours,
                'notes'          => 'Response SLA: ' . $validationHours . ' hrs (' . $responseStatus . ') — policy applied after delivery support assigned',
            ]);

        Log::info('SlaService@syncPolicy: policy applied retroactively', [
            'ticket_id'       => $ticket->ticket_id,
            'policy_id'       => $policy->id,
            'response_status' => $responseStatus,
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

        // Auto-close the meeting if its scheduled end time has passed before processing this message
        if ($this->autoEndExpiredMeeting($ticket)) {
            $sla->refresh();
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

        // Ticket sudah closed — tidak ada penambahan waktu live
        if ($sla->isClosed()) {
            return round($total, 2);
        }

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

        if ($missing->isNotEmpty()) {
            $newIds = [];
            foreach ($missing as $ticket) {
                try {
                    $staging = StagingTicket::where('ticket_id', $ticket->ticket_id)->first();
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

        $this->syncMissingPolicies();
    }

    /**
     * Retry policy-matching for SLA records still without a policy.
     *
     * A ticket's SLA record can be left with sla_policy_id = null when its delivery
     * support had no matching SlaPolicy at the time it was assigned. If that policy
     * gets configured later (SLA Config is often filled in after tickets already
     * exist), nothing re-triggers the match — so this re-attempts it on every report
     * load. Safe to call repeatedly; syncPolicy() itself is a no-op when still no match.
     */
    private function syncMissingPolicies(): void
    {
        $slas = TicketSla::whereNotNull('ticket_id')
            ->whereNull('sla_policy_id')
            ->with('ticket')
            ->get();

        foreach ($slas as $sla) {
            if (!$sla->ticket) {
                continue;
            }
            try {
                $this->syncPolicy($sla->ticket);
            } catch (\Throwable $e) {
                Log::warning('SlaService@syncMissingPolicies: failed', [
                    'ticket_id' => $sla->ticket_id,
                    'error'     => $e->getMessage(),
                ]);
            }
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
            // Meeting hold tidak boleh diputus oleh chat — hold berlaku sampai meeting selesai
            if (!$this->hasMeetingHold($ticket->ticket_id)) {
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
            // Already paused — apply burst coalescing.
            // Meeting hold mengambil prioritas: jangan sentuh pause baseline saat meeting aktif,
            // karena overwrite sla_paused_at akan memotong window waiting yang sudah berjalan.
            if (!$this->hasMeetingHold($ticket->ticket_id)) {
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
            // Meeting hold aktif — tidak ada perubahan state
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
            // Meeting hold tidak boleh diputus oleh agent reply — hold berlaku sampai meeting selesai
            if (!$this->hasMeetingHold($ticket->ticket_id)) {
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

    /**
     * Auto-close the active meeting pause if its scheduled_end_at has passed.
     * Returns true when a meeting was auto-ended so callers can refresh SLA state.
     */
    public function autoEndExpiredMeeting(Ticket $ticket): bool
    {
        $pause = TicketSlaPause::where('ticket_id', $ticket->ticket_id)
            ->where('pause_reason', 'meeting')
            ->whereNull('ended_at')
            ->whereNotNull('scheduled_end_at')
            ->where('scheduled_end_at', '<=', now())
            ->first();

        if (!$pause) {
            return false;
        }

        $this->endMeeting($ticket, $pause->scheduled_end_at);
        return true;
    }

    private function hasMeetingHold(int $ticketId): bool
    {
        return TicketSlaPause::where('ticket_id', $ticketId)
            ->where('pause_reason', 'meeting')
            ->whereNull('ended_at')
            ->exists();
    }

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
     * Pause SLA clock saat jadwal meeting dibuat — ball berpindah ke customer.
     *
     * $pauseAt       = waktu SLA mulai di-hold (saat jadwal dibuat, untuk kalkulasi)
     * $scheduledAt   = waktu meeting dijadwalkan (untuk tampilan di event log)
     * $scheduledEndAt = waktu meeting selesai (auto-resume SLA saat waktu ini tercapai)
     */
    public function startMeeting(
        Ticket  $ticket,
        ?Carbon $pauseAt        = null,
        ?Carbon $scheduledAt    = null,
        ?Carbon $scheduledEndAt = null
    ): void {
        $sla = $ticket->sla;
        if (!$sla || $sla->isClosed()) {
            return;
        }

        $at      = $pauseAt ?? now();
        $eventAt = $scheduledAt ?? $at;

        $existingMeetingPause = TicketSlaPause::where('ticket_id', $ticket->ticket_id)
            ->where('pause_reason', 'meeting')
            ->whereNull('ended_at')
            ->first();

        if ($existingMeetingPause) {
            // Meeting lain sudah aktif — update scheduled_end_at-nya dengan waktu meeting baru.
            // Ini terjadi ketika user membuat jadwal meeting kedua sebelum meeting pertama auto-end.
            $existingMeetingPause->update(['scheduled_end_at' => $scheduledEndAt]);
        } elseif ($sla->ball_holder === 'helpdesk') {
            // Ball was with helpdesk — meeting moves it to customer
            $sla->ball_holder       = 'customer';
            $sla->sla_paused_at     = $at;
            $sla->resolution_status = 'paused';
            $sla->save();

            TicketSlaPause::create([
                'ticket_id'           => $ticket->ticket_id,
                'pause_reason'        => 'meeting',
                'triggered_by_status' => 'meeting',
                'started_at'          => $at,
                'scheduled_end_at'    => $scheduledEndAt,
            ]);
        } else {
            // Ball is already with customer/SAP (e.g. waiting_on_customer) — create meeting guard
            // without changing SLA state. This prevents customer replies from restarting the
            // SLA clock during the meeting window, and zeroes out resolution hours in the event log.
            TicketSlaPause::create([
                'ticket_id'           => $ticket->ticket_id,
                'pause_reason'        => 'meeting',
                'triggered_by_status' => 'meeting',
                'started_at'          => $at,
                'scheduled_end_at'    => $scheduledEndAt,
            ]);
        }

        TicketSlaEvent::create([
            'ticket_id'         => $ticket->ticket_id,
            'event_type'        => 'meeting_started',
            'event_at'          => $eventAt,
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

        $at    = $endAt ?? now();
        $is24h = $sla->policy?->is_24_hours ?? true;

        $meetingPause = TicketSlaPause::where('ticket_id', $ticket->ticket_id)
            ->where('pause_reason', 'meeting')
            ->whereNull('ended_at')
            ->first();

        // If the scheduled end time is before the meeting's start (user input error),
        // clamp so Meeting Ended never appears before Meeting Started in the log.
        if ($meetingPause && $at->lt($meetingPause->started_at)) {
            $at = $meetingPause->started_at->clone();
        }

        $waitingH   = null;
        $priorPause = false;

        if ($sla->ball_holder !== 'helpdesk' && $sla->sla_paused_at) {
            // Did the SLA get paused before the meeting was scheduled?
            // If sla_paused_at < meetingPause.started_at, a prior customer/SAP wait was
            // already in progress — the meeting was just a guard on top of it.
            $priorPause = (bool) ($meetingPause && $sla->sla_paused_at->lt($meetingPause->started_at));

            if ($priorPause) {
                // Meeting was overlaid on a pre-existing customer wait.
                // Close the meeting guard record; leave the customer wait pause open.
                // The ball stays with the customer — they still need to reply to restart the SLA.
                // $waitingH is set so the meeting_ended event shows the meeting duration in the
                // WAITING column, but it is NOT added to total_waiting_hours (the full customer
                // wait window already includes this period, so adding it would double-count).
                $waitingH = $this->calcHours($meetingPause->started_at, $at, $is24h);
                $meetingPause->update([
                    'ended_at'       => $at,
                    'duration_hours' => $waitingH,
                ]);
            } else {
                // The meeting itself paused the SLA (ball moved from helpdesk to customer BY meeting).
                // Resume the SLA clock now that the meeting is over.
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
        } elseif ($meetingPause) {
            $meetingPause->update(['ended_at' => $at, 'duration_hours' => 0]);
        }

        $notes = $waitingH !== null
            ? 'Meeting ended — ' . round($waitingH, 2) . ' hrs' . ($priorPause ? ' (meeting duration)' : ' counted as waiting')
            : 'Meeting ended';

        TicketSlaEvent::create([
            'ticket_id'         => $ticket->ticket_id,
            'event_type'        => 'meeting_ended',
            'event_at'          => $at,
            'waiting_hours'     => $waitingH,
            'triggered_by_type' => 'employee',
            'notes'             => $notes,
        ]);
    }
}

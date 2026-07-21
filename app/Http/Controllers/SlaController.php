<?php

namespace App\Http\Controllers;

use App\Models\DeliverySupport;
use App\Models\Employee;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\TicketSla;
use App\Models\TicketSlaEvent;
use App\Models\TicketSlaPause;
use App\Services\SlaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SlaController extends Controller
{
    public function __construct(private SlaService $sla) {}

    private function assertAdmin(): bool
    {
        return session('user.role.id') === 1;
    }

    // Diatur lewat Role Management (menu slug: sla.report), bukan role hardcode,
    // supaya role apa pun bisa diberi/dicabut akses SLA Report dari UI Role Management.
    private function assertSlaAccess(): bool
    {
        $employee = Employee::find(session('user.id'));
        return (bool) $employee?->hasPermission('sla.report');
    }

    // Admin + Delivery Support Head (can create/edit/delete policies)
    private function canManagePolicies(): bool
    {
        return in_array(session('user.role.id'), [1, 5], true);
    }

    // Diatur lewat Role Management (menu slug: ticket.meeting), bukan role hardcode,
    // supaya role apa pun bisa diberi/dicabut akses meeting dari UI Role Management.
    private function assertMeetingAccess(): bool
    {
        $employee = Employee::find(session('user.id'));
        return (bool) $employee?->hasPermission('ticket.meeting');
    }

    // ── Web Pages ─────────────────────────────────────────────────────────────

    public function configPage()
    {
        $canManage        = $this->canManagePolicies();
        $deliverySupports = DeliverySupport::orderBy('name')->get(['id', 'name', 'type']);
        return view('admin.sla.config', compact('deliverySupports', 'canManage'));
    }

    public function reportPage()
    {
        $customers = \App\Models\Customer::with('basicData')->where('is_active', true)->get();
        return view('admin.sla.report', compact('customers'));
    }

    // ── API: Policy CRUD ──────────────────────────────────────────────────────

    public function getPolicies(Request $request)
    {
        if (!$this->assertSlaAccess()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $query = SlaPolicy::with('deliverySupport');

        if ($request->filled('delivery_support_id')) {
            $query->where('delivery_support_id', $request->delivery_support_id);
        }

        $policies = $query->orderBy('delivery_support_id')
            ->orderByRaw("FIELD(priority, 'Very High', 'High', 'Medium', 'Low')")
            ->orderByRaw("FIELD(scale, 'Simple', 'Medium', 'Complex')")
            ->get()
            ->map(fn ($p) => $this->formatPolicy($p));

        return response()->json(['success' => true, 'data' => $policies]);
    }

    public function storePolicy(Request $request)
    {
        if (!$this->canManagePolicies()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $v = Validator::make($request->all(), [
            'delivery_support_id' => 'required|exists:delivery_support,id',
            'priority'            => 'required|in:Low,Medium,High,Very High',
            'scale'               => 'required|in:Simple,Medium,Complex',
            'response_hours'      => 'required|numeric|min:0.1|max:999',
            'resolution_hours'    => 'required|numeric|min:0.1|max:999',
            'is_24_hours'         => 'boolean',
            'work_start_time'     => 'nullable|date_format:H:i',
            'work_end_time'       => 'nullable|date_format:H:i|after:work_start_time',
            'break_start_time'    => 'nullable|date_format:H:i|required_with:break_end_time',
            'break_end_time'      => 'nullable|date_format:H:i|after:break_start_time|required_with:break_start_time',
        ]);

        if ($v->fails()) {
            return response()->json(['success' => false, 'errors' => $v->errors()], 422);
        }

        $deliverySupportId = (int) $request->delivery_support_id;
        $exists = SlaPolicy::where('delivery_support_id', $deliverySupportId)
            ->where('priority', $request->priority)
            ->where('scale', $request->scale)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'A policy for this delivery support / priority / scale combination already exists.',
            ], 422);
        }

        $policy = SlaPolicy::create([
            'delivery_support_id' => $deliverySupportId,
            'priority'            => $request->priority,
            'scale'               => $request->scale,
            'response_hours'      => $request->response_hours,
            'resolution_hours'    => $request->resolution_hours,
            'is_24_hours'         => $request->priority === 'Very High' ? true : $request->boolean('is_24_hours', false),
            'work_start_time'     => $request->work_start_time,
            'work_end_time'       => $request->work_end_time,
            'break_start_time'    => $request->break_start_time,
            'break_end_time'      => $request->break_end_time,
            'is_active'           => true,
            'created_by'          => session('user.id'),
        ]);

        return response()->json([
            'success' => true,
            'data'    => $this->formatPolicy($policy->load('deliverySupport')),
        ], 201);
    }

    public function updatePolicy(Request $request, $id)
    {
        if (!$this->canManagePolicies()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $v = Validator::make($request->all(), [
            'response_hours'   => 'sometimes|numeric|min:0.1|max:999',
            'resolution_hours' => 'sometimes|numeric|min:0.1|max:999',
            'is_24_hours'      => 'sometimes|boolean',
            'work_start_time'  => 'nullable|date_format:H:i',
            'work_end_time'    => 'nullable|date_format:H:i|after:work_start_time',
            'break_start_time' => 'nullable|date_format:H:i|required_with:break_end_time',
            'break_end_time'   => 'nullable|date_format:H:i|after:break_start_time|required_with:break_start_time',
            'is_active'        => 'sometimes|boolean',
        ]);

        if ($v->fails()) {
            return response()->json(['success' => false, 'errors' => $v->errors()], 422);
        }

        $policy  = SlaPolicy::findOrFail($id);
        $updates = $request->only([
            'response_hours', 'resolution_hours', 'is_24_hours',
            'work_start_time', 'work_end_time',
            'break_start_time', 'break_end_time', 'is_active',
        ]);
        if ($policy->priority === 'Very High') {
            $updates['is_24_hours'] = true;
        }
        $policy->update($updates);

        return response()->json([
            'success' => true,
            'data'    => $this->formatPolicy($policy->load('deliverySupport')),
        ]);
    }

    public function destroyPolicy($id)
    {
        if (!$this->canManagePolicies()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $policy = SlaPolicy::findOrFail($id);

        if (TicketSla::where('sla_policy_id', $id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This policy is in use by existing tickets. Consider deactivating it instead.',
            ], 422);
        }

        $policy->delete();
        return response()->json(['success' => true, 'message' => 'Policy deleted.']);
    }

    // ── API: Per-ticket SLA detail ────────────────────────────────────────────

    public function getTicketSla($id)
    {
        $ticket = Ticket::with([
            'sla.policy',
            'sla.events' => fn ($q) => $q->orderBy('event_at'),
            'sla.events.message',
            'sla.pauses',
        ])->findOrFail($id);

        $sla = $ticket->sla;

        if (!$sla) {
            return response()->json(['success' => true, 'data' => null]);
        }

        // Auto-end meeting jika waktu meeting_end_time sudah lewat
        try {
            if ($this->sla->autoEndExpiredMeeting($ticket)) {
                $ticket->load(['sla.policy', 'sla.events' => fn ($q) => $q->orderBy('event_at'), 'sla.events.message', 'sla.pauses']);
                $sla = $ticket->sla;
            }
        } catch (\Throwable $e) {
            Log::warning('SlaController@getTicketSla: auto-end meeting failed', [
                'ticket_id' => $id,
                'error'     => $e->getMessage(),
            ]);
        }

        // Jika tiket sudah closed/cancelled tapi SLA belum di-finalize, auto-finalize sekarang
        if (
            $ticket->status &&
            in_array($ticket->status, SlaService::END_STATUSES) &&
            !$sla->isClosed()
        ) {
            try {
                $isCancelled = $ticket->status === 'cancelled';
                $this->sla->closeTicketSla($sla, $ticket, null, $ticket->updated_at ?? now(), null, $isCancelled);
                $sla->refresh();
            } catch (\Throwable $e) {
                Log::warning('SlaController@getTicketSla: auto-finalize gagal', [
                    'ticket_id' => $id,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        $policy   = $sla->policy;
        // Same live-recompute as the admin SLA report (SlaController::formatSlaRow) —
        // derived from raw timestamps/pauses instead of the stored duration/status columns,
        // so this panel and the report never disagree, and both self-correct historical
        // tickets after a calcHours() change without a backfill.
        $liveWait = $this->sla->liveTotalWaitingHours($sla);

        $responseData = [
            'status'       => $this->sla->responseStatusLive($sla),
            'target_hours' => $policy ? (float) $policy->response_hours : null,
            'actual_hours' => $this->sla->responseDurationHours($sla),
            'due_at'       => $sla->response_due_at?->toDateTimeString(),
            'responded_at' => $sla->first_responded_at?->toDateTimeString(),
        ];

        $resolutionData = null;
        if ($sla->sla_mode === 'full') {
            $resolutionMetrics = $this->sla->liveResolutionMetrics($sla);
            $resolutionData = [
                'status'        => $resolutionMetrics['status'],
                'target_hours'  => $policy ? (float) $policy->resolution_hours : null,
                'actual_hours'  => $resolutionMetrics['net_hours'],
                'due_at'        => $sla->resolution_due_at?->toDateTimeString(),
                'resolved_at'   => $sla->resolved_at?->toDateTimeString(),
                'net_hours'     => $resolutionMetrics['net_hours'],
                'waiting_hours' => $liveWait,
            ];
        }

        $events = $this->buildSlaEventLog($sla, $ticket->ticket_id, $ticket->status);

        // Tampilkan 'meeting' sebagai ball_holder saat meeting sedang aktif
        // (DB menyimpan 'customer' tapi label itu membingungkan bagi agen)
        $activeMeeting = TicketSlaPause::where('ticket_id', $ticket->ticket_id)
            ->where('pause_reason', 'meeting')
            ->whereNull('ended_at')
            ->exists();
        $ballHolder = $activeMeeting ? 'meeting' : $sla->ball_holder;

        return response()->json([
            'success' => true,
            'data'    => [
                'sla_mode'    => $sla->sla_mode,
                'ball_holder' => $ballHolder,
                'response'    => $responseData,
                'resolution'  => $resolutionData,
                'events'      => $events,
            ],
        ]);
    }

    /**
     * Build SLA event log dari dua sumber:
     * 1. ticket_sla_events — untuk special events (email_received, ticket_validated,
     *    ticket_closed, meeting_*) yang tidak punya representasi di ticket_message.
     * 2. ticket_message — source of truth untuk semua balasan (agent & customer).
     *
     * Waiting & resolution dihitung ulang dengan menelusuri timeline pesan secara
     * kronologis, mirip SlaService::backfillEventsForTickets — sehingga semua
     * balasan (email langsung, Jarvies web, apapun) punya timing yang benar
     * tanpa bergantung apakah SLA trigger pernah jalan atau tidak.
     */
    private function buildSlaEventLog(TicketSla $sla, int $ticketId, ?string $currentTicketStatus = null): \Illuminate\Support\Collection
    {
        $stopStatuses     = ['waiting_on_customer', 'waiting_to_confirmation', 'waiting_on_3rd_party', 'hold'];
        $runStatuses      = ['inprocess', 'open'];
        $endStatuses      = SlaService::END_STATUSES;
        $messageOnlyTypes = ['agent_replied', 'customer_replied'];
        $is24h            = $sla->policy?->is_24_hours ?? true;
        $ticketIsClosed   = $sla->isClosed() || in_array($currentTicketStatus, $endStatuses);

        // ── 1. Special events ─────────────────────────────────────────────────
        $specialEvents = $sla->events
            ->reject(fn ($e) => in_array($e->event_type, $messageOnlyTypes))
            ->map(fn ($e) => [
                'event_type'       => $e->event_type,
                'event_at'         => $e->event_at->setTimezone('Asia/Jakarta')->toIso8601String(),
                'label'            => $e->event_label,
                'jarvis_status'    => $e->jarvis_status,
                'waiting_hours'    => $e->waiting_hours !== null ? (float) $e->waiting_hours : null,
                'response_hours'   => $e->response_hours !== null ? (float) $e->response_hours : null,
                'resolution_hours' => $e->resolution_hours !== null ? (float) $e->resolution_hours : null,
                'notes'            => $e->notes,
                'ball_after'       => $e->event_type === 'ticket_validated' ? 'helpdesk' : null,
                'sender_name'      => null,
                'message_preview'  => $e->message
                    ? ($e->message->sla_message
                        ? mb_substr($e->message->sla_message, 0, 120)
                        : mb_substr(strip_tags($e->message->message ?? $e->message->message_html ?? ''), 0, 120))
                    : null,
                '_sort'            => $e->event_at->toDateTimeString(),
            ]);

        // ── 2. Stored message events ───────────────────────────────────────────
        $storedByMsgId = $sla->events
            ->filter(fn ($e) => in_array($e->event_type, $messageOnlyTypes))
            ->keyBy('message_id');

        // ── 3. Pauses — dua indeks ───────────────────────────────────────────
        $allPauses = TicketSlaPause::where('ticket_id', $ticketId)
            ->orderBy('started_at')
            ->get();

        $pauseStartedByMsg = $allPauses->whereNotNull('started_by_message_id')->keyBy('started_by_message_id');
        $pauseEndedByMsg   = $allPauses->whereNotNull('ended_by_message_id')->keyBy('ended_by_message_id');

        // ── 4. All non-internal messages ──────────────────────────────────────
        // Meeting messages are tracked via TicketSlaEvent (specialEvents above);
        // excluding them here prevents duplicates in the SLA timeline walk.
        // Use explicit NULL guard: MySQL treats NULL NOT IN (...) as NULL (unknown),
        // so a plain whereNotIn would silently drop all legacy rows with null message_type.
        $messages = TicketMessage::where('ticket_id', $ticketId)
            ->where('is_internal_note', false)
            ->where(function ($q) {
                $q->whereNull('message_type')
                  ->orWhereNotIn('message_type', ['meeting_started', 'meeting_ended']);
            })
            // Setelah tiket di-close, tidak ada pesan yang dihitung dalam SLA timeline
            ->when($sla->resolved_at, fn ($q) => $q->where('created_at', '<=', $sla->resolved_at))
            ->orderBy('created_at')
            ->get();

        // ID agent message terakhir — untuk fallback "status sekarang"
        $lastAgentMsgId = $messages
            ->filter(fn ($m) => $m->sender_type !== 'customer')
            ->last()?->id;

        // ── 5. Stateful timeline walk ─────────────────────────────────────────
        $ballHolder   = 'helpdesk';
        $sessionStart = $sla->first_responded_at ?? $sla->sla_start_at;
        $pauseStart   = null; // Carbon|null — kapan pause dimulai
        $lastAgentAt  = null; // Carbon|null — waktu agent message terakhir (fallback waiting)

        $messageEvents = $messages->map(function ($msg) use (
            $storedByMsgId, $pauseStartedByMsg, $pauseEndedByMsg, $allPauses,
            $stopStatuses, $runStatuses, $is24h,
            $lastAgentMsgId, $currentTicketStatus, $sla, $ticketIsClosed,
            &$ballHolder, &$sessionStart, &$pauseStart, &$lastAgentAt
        ) {
            $stored     = $storedByMsgId->get($msg->id);
            $isCustomer = $msg->sender_type === 'customer';

            $waitingH      = null;
            $resolutionH   = null;
            $ballAfter     = null;
            $jarvisStatus  = null;
            $pausedByMeeting = false;

            if ($isCustomer) {
                // ── Customer reply ─────────────────────────────────────────────
                // Prioritas waiting_hours:
                //   1. Stored customer_replied event
                //   2. Pause record dengan ended_by_message_id = pesan ini
                //   3. Timeline-computed: $pauseStart (formal SLA pause)
                //   4. Waktu sejak agent reply terakhir (informal gap)

                if ($stored?->waiting_hours !== null) {
                    $waitingH = round((float) $stored->waiting_hours, 2);

                } elseif (($endedPause = $pauseEndedByMsg->get($msg->id)) !== null
                    && $endedPause->duration_hours !== null) {
                    $waitingH = round((float) $endedPause->duration_hours, 2);

                } elseif ($ballHolder !== 'helpdesk' && $pauseStart !== null) {
                    $waitingH = round($this->sla->calcHours($pauseStart, $msg->created_at, $is24h, $sla->policy), 2);

                } elseif ($lastAgentAt !== null) {
                    // Tidak ada formal pause — hitung gap sejak agent terakhir balas
                    $waitingH = round($this->sla->calcHours($lastAgentAt, $msg->created_at, $is24h, $sla->policy), 2);
                }

                // Reset state: ball kembali ke helpdesk
                $ballHolder   = 'helpdesk';
                $pauseStart   = null;
                $sessionStart = $msg->created_at;
                $ballAfter    = 'helpdesk';

            } else {
                // ── Agent reply ────────────────────────────────────────────────
                $pauseStartedByThisMsg = $pauseStartedByMsg->get($msg->id);

                // jarvisStatus priority:
                //   1. Stored event
                //   2. Pause record yang di-trigger pesan ini
                //   3. (khusus pesan terakhir) status ticket saat ini — hanya jika tiket BELUM closed
                $jarvisStatus = $stored?->jarvis_status
                    ?? $pauseStartedByThisMsg?->triggered_by_status;

                if (!$jarvisStatus && $msg->id === $lastAgentMsgId && $currentTicketStatus
                    && in_array($currentTicketStatus, $stopStatuses)
                    && !$ticketIsClosed) {
                    $jarvisStatus = $currentTicketStatus;
                }

                // Pause anchor: gunakan pause record atau waktu pesan
                // Untuk pesan terakhir, utamakan sla_paused_at — tapi hanya jika tiket belum closed
                $effectivePauseStart = $pauseStartedByThisMsg?->started_at ?? $msg->created_at;
                if ($msg->id === $lastAgentMsgId && $sla->sla_paused_at !== null && !$ticketIsClosed) {
                    $effectivePauseStart = $sla->sla_paused_at;
                }

                // Resolution = waktu aktif helpdesk sejak session terakhir (selalu dihitung fresh dari timeline)
                if ($sessionStart !== null) {
                    $resolutionH = round($this->sla->calcHours($sessionStart, $msg->created_at, $is24h, $sla->policy), 2);
                }

                // Jika ada meeting pause aktif saat pesan ini dikirim, waktu resolusi tidak dihitung
                $meetingActiveAtMsg = $allPauses
                    ->where('pause_reason', 'meeting')
                    ->first(fn ($p) => $p->started_at <= $msg->created_at
                        && ($p->ended_at === null || $p->ended_at > $msg->created_at));
                $pausedByMeeting = false;
                if ($meetingActiveAtMsg !== null) {
                    $resolutionH     = 0;
                    $pausedByMeeting = true;
                }

                if ($jarvisStatus && in_array($jarvisStatus, $stopStatuses)) {
                    if ($ballHolder === 'helpdesk') {
                        $pauseStart = $effectivePauseStart;
                    }
                    $ballHolder = $jarvisStatus === 'waiting_on_3rd_party' ? 'sap' : 'customer';
                    $ballAfter  = $ballHolder;

                } elseif ($jarvisStatus && in_array($jarvisStatus, $runStatuses)) {
                    if ($ballHolder !== 'helpdesk' && $pauseStart !== null) {
                        $resolutionH  = 0;
                        $pauseStart   = null;
                        $sessionStart = $msg->created_at;
                    }
                    $ballHolder = 'helpdesk';
                    $ballAfter  = 'helpdesk';

                } else {
                    $ballAfter = $ballHolder === 'helpdesk' ? 'helpdesk' : null;
                }

                // Update waktu agent terakhir balas (dipakai sebagai fallback waiting)
                $lastAgentAt = $msg->created_at;
            }

            return [
                'event_type'       => $isCustomer ? 'customer_replied' : 'agent_replied',
                'event_at'         => $msg->created_at->setTimezone('Asia/Jakarta')->toIso8601String(),
                'label'            => $isCustomer ? 'Customer replied' : 'Agent replied',
                'jarvis_status'    => $jarvisStatus,
                'waiting_hours'    => $waitingH,
                'response_hours'   => null,
                'resolution_hours' => $isCustomer ? null : $resolutionH,
                'meeting_paused'   => !$isCustomer && $pausedByMeeting,
                'notes'            => $stored?->notes,
                'ball_after'       => $ballAfter,
                'sender_name'      => $msg->sender_name,
                'message_preview'  => $msg->sla_message
                    ? mb_substr($msg->sla_message, 0, 120)
                    : (mb_substr(strip_tags($msg->message ?? $msg->message_html ?? ''), 0, 120) ?: null),
                '_sort'            => $msg->created_at->toDateTimeString(),
            ];
        });

        return $specialEvents
            ->concat($messageEvents)
            ->sortBy('_sort')
            ->values()
            ->map(fn ($e) => collect($e)->except('_sort')->all());
    }

    // ── API: SLA Report ───────────────────────────────────────────────────────

    public function getReport(Request $request)
    {
        if (!$this->assertSlaAccess()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            $this->sla->ensureTicketsHaveSla();
        } catch (\Throwable $e) {
            Log::warning('SlaController@getReport: ensureTicketsHaveSla failed', [
                'error' => $e->getMessage(),
            ]);
        }

        $query = TicketSla::with([
            'ticket.customer.basicData',
            'ticket.ticketLead.basicData',
            'ticket.moduleMaster',
            'ticket.deliverySupportActivities' => fn ($q) => $q->orderByDesc('delivery_support_id'),
            'ticket.deliverySupportActivities.deliverySupport',
            'stagingTicket',
            'policy',
        ])
            ->whereNotNull('ticket_id');

        if ($request->filled('customer_id')) {
            $query->whereHas('ticket', fn ($q) => $q->where('customer_id', $request->customer_id));
        }

        if ($request->filled('month') && $request->filled('year')) {
            $query->whereMonth('sla_start_at', $request->month)
                  ->whereYear('sla_start_at', $request->year);
        } elseif ($request->filled('year')) {
            $query->whereYear('sla_start_at', $request->year);
        }

        // 'pending'/'paused' are ball-holder-state-driven (set directly by ticket status
        // transitions) and were never affected by the calcHours() formula fix, so filtering
        // on the stored column is always accurate for them — do it at the SQL level as before.
        // 'met'/'breached' are duration-based verdicts that ARE now recomputed live (see
        // SlaService::liveResolutionMetrics()) for closed tickets, so the stored column can
        // occasionally disagree with what's displayed (only for tickets whose closed-duration
        // crossed a holiday/break-window boundary). Filtering those live means fetching a
        // larger bounded candidate pool first, then filtering + capping to 200 in PHP.
        $statusFilter = $request->filled('resolution_status') ? $request->resolution_status : null;
        $filterLive   = in_array($statusFilter, ['met', 'breached'], true);

        if ($statusFilter && !$filterLive) {
            $query->where('resolution_status', $statusFilter);
        }

        $slas = $query->orderBy('sla_start_at', 'desc')->limit($filterLive ? 1000 : 200)->get();

        // Batch-fetch "first waiting_on_customer" reply timestamps for all rows at once
        // (avoids N+1 queries inside formatSlaRow for up to 200 rows).
        $firstResolvedByTicket = TicketSlaEvent::whereIn('ticket_id', $slas->pluck('ticket_id')->filter())
            ->where('jarvis_status', 'waiting_on_customer')
            ->orderBy('event_at')
            ->get()
            ->unique('ticket_id')
            ->keyBy('ticket_id')
            ->map(fn ($e) => $e->event_at);

        // Batch-fetch "first deliverable sent" timestamps for all rows at once (same
        // N+1 avoidance as $firstResolvedByTicket above).
        $docSentByTicket = \App\Models\TicketDeliverable::whereIn('ticket_id', $slas->pluck('ticket_id')->filter())
            ->where('status', 'Sent')
            ->orderBy('updated_at')
            ->get()
            ->unique('ticket_id')
            ->keyBy('ticket_id')
            ->map(fn ($d) => $d->updated_at);

        // Batch-fetch SLA notes (agent-tagged "sla_message" annotations on chat messages,
        // same source as the Log Shifting report) for the Notes column, grouped by ticket.
        $notesByTicket = TicketMessage::whereIn('ticket_id', $slas->pluck('ticket_id')->filter())
            ->whereNotNull('sla_message')
            ->where('sla_message', '!=', '')
            ->where('is_deleted', false)
            ->orderBy('created_at')
            ->get(['ticket_id', 'sla_message', 'created_at'])
            ->groupBy('ticket_id');

        // Batch-fetch pause history for all rows at once — used to live-recompute Waiting
        // Hours / Resolution Duration from raw timestamps (see SlaService::liveTotalWaitingHours),
        // so historical tickets self-correct after a calcHours() formula change without a
        // separate backfill command.
        $pausesByTicket = TicketSlaPause::whereIn('ticket_id', $slas->pluck('ticket_id')->filter())
            ->get()
            ->groupBy('ticket_id');

        $total    = $slas->count();
        $met      = $slas->where('resolution_status', 'met')->count();
        $breached = $slas->where('resolution_status', 'breached')->count();
        $active   = $slas->whereIn('resolution_status', ['pending', 'paused'])->count();
        $compRate = ($met + $breached) > 0
                    ? round($met / ($met + $breached) * 100, 2)
                    : null;

        $avgResponse   = $slas->whereNotNull('validation_duration_hours')->avg('validation_duration_hours');
        $avgResolution = $slas->whereNotNull('net_resolution_hours')->avg('net_resolution_hours');

        $tickets = $slas->map(fn ($s) => $this->formatSlaRow(
            $s,
            $firstResolvedByTicket->get($s->ticket_id),
            $docSentByTicket->get($s->ticket_id),
            $notesByTicket->get($s->ticket_id),
            $pausesByTicket->get($s->ticket_id)
        ));

        // Live-filtered met/breached (see $filterLive above) — filter on the recomputed
        // status now that formatSlaRow() has produced it, then cap to the usual 200.
        if ($filterLive) {
            $tickets = $tickets->filter(fn ($t) => ($t['resolution']['status'] ?? null) === $statusFilter)
                ->take(200)
                ->values();
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'summary' => [
                    'total'                => $total,
                    'active'               => $active,
                    'met'                  => $met,
                    'breached'             => $breached,
                    'compliance_rate'      => $compRate,
                    'avg_response_hours'   => $avgResponse ? round($avgResponse, 2) : null,
                    'avg_resolution_hours' => $avgResolution ? round($avgResolution, 2) : null,
                ],
                'tickets' => $tickets,
            ],
        ]);
    }

    // ── PDF Export ────────────────────────────────────────────────────────────

    public function downloadLogPdf($id)
    {
        if (!$this->assertSlaAccess()) {
            abort(403);
        }

        $ticket = Ticket::with([
            'sla.policy',
            'sla.events'         => fn ($q) => $q->orderBy('event_at'),
            'sla.events.message',
            'sla.pauses'         => fn ($q) => $q->whereNotNull('ended_at')->orderBy('started_at'),
            'customer.basicData',
        ])->findOrFail($id);

        $sla           = $ticket->sla;
        $policy        = $sla?->policy;
        $pauses        = $sla?->pauses ?? collect();
        $responseDueAt = $sla ? $this->sla->responseDueAt($sla) : null;

        [$pauses, $liveMetrics] = $this->liveSlaPdfMetrics($sla, $pauses);

        $events = $sla ? $this->buildSlaEventLog($sla, $ticket->ticket_id, $ticket->status) : collect();

        $docNumber = 'ECL/SLA/' . $ticket->ticket_number . '/' . now()->format('Ym');

        $pdf = Pdf::loadView('admin.sla.log-pdf', array_merge(compact(
            'ticket', 'sla', 'policy', 'events', 'pauses', 'docNumber', 'responseDueAt'
        ), $liveMetrics));
        $pdf->setPaper('A4', 'landscape');

        return $pdf->download('SLA-Log-' . $ticket->ticket_number . '.pdf');
    }

    public function downloadTicketPdf($id)
    {
        if (!$this->assertSlaAccess()) {
            abort(403);
        }

        $ticket = Ticket::with([
            'sla.policy',
            'sla.events' => fn ($q) => $q->orderBy('event_at'),
            'sla.pauses' => fn ($q) => $q->whereNotNull('ended_at'),
            'customer.basicData',
        ])->findOrFail($id);

        $sla           = $ticket->sla;
        $policy        = $sla?->policy;
        $events        = $sla?->events ?? collect();
        $pauses        = $sla?->pauses ?? collect();
        $responseDueAt = $sla ? $this->sla->responseDueAt($sla) : null;

        [$pauses, $liveMetrics] = $this->liveSlaPdfMetrics($sla, $pauses);

        $pdf = Pdf::loadView('admin.sla.ticket-pdf', array_merge(compact(
            'ticket', 'sla', 'policy', 'events', 'pauses', 'responseDueAt'
        ), $liveMetrics));
        $pdf->setPaper('A4', 'portrait');

        return $pdf->download('SLA-Ticket-' . $ticket->ticket_number . '.pdf');
    }

    // ── API: Meeting pause/resume ─────────────────────────────────────────────

    public function startMeeting(Request $request, $id)
    {
        if (!$this->assertMeetingAccess()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $v = Validator::make($request->all(), [
            'started_at'          => 'nullable|date',
            'notes'               => 'nullable|string|max:1000',
            'meeting_link'        => 'nullable|url|max:2048',
            'meeting_start_time'  => 'nullable|date',
            'meeting_end_time'    => 'nullable|date',
            'to_emails'           => 'nullable|array',
            'to_emails.*'         => 'email',
            'cc_emails'           => 'nullable|array',
            'cc_emails.*'         => 'email',
        ]);

        if ($v->fails()) {
            return response()->json(['success' => false, 'errors' => $v->errors()], 422);
        }

        $ticket           = Ticket::with(['sla.policy', 'customer'])->findOrFail($id);
        $startAt          = $request->filled('started_at') ? Carbon::parse($request->started_at) : now();
        $senderName       = session('user.name') ?? 'Helpdesk';
        $senderId         = (int) session('user.id');
        $notes            = $request->input('notes');
        $meetingLink      = $request->input('meeting_link');
        $meetingStartTime = $request->filled('meeting_start_time') ? Carbon::parse($request->meeting_start_time) : null;
        $meetingEndTime   = $request->filled('meeting_end_time')   ? Carbon::parse($request->meeting_end_time)   : null;

        // To/CC: modal mengirim key ini terus-menerus (prefilled dari toEmails/ccEmails
        // di UI), jadi yang menentukan adalah isinya, bukan sekadar ada/tidaknya key.
        // Daftar baru yang tidak kosong disimpan sebagai default ticket (sama seperti
        // reply biasa) supaya konsisten dipakai lagi di reply/meeting berikutnya. Kalau
        // dikirim kosong (agent hapus semua chip), jangan override — biarkan
        // sendSystemReplyEmail jatuh ke fallback lama (to_emails/cc_emails ticket yang
        // sudah tersimpan, atau resolveCustomerEmail() jika tiket belum pernah punya sama sekali).
        $toEmailsInput = array_values(array_filter((array) $request->input('to_emails', [])));
        $ccEmailsInput = array_values(array_filter((array) $request->input('cc_emails', [])));

        if (!empty($toEmailsInput) || !empty($ccEmailsInput)) {
            $ticket->update([
                'to_emails' => $toEmailsInput ?: $ticket->to_emails,
                'cc_emails' => $ccEmailsInput ?: $ticket->cc_emails,
            ]);
        }

        $toEmails = !empty($toEmailsInput) ? $toEmailsInput : (!empty($ticket->to_emails) ? (array) $ticket->to_emails : null);
        $ccEmails = !empty($ccEmailsInput) ? $ccEmailsInput : null;

        if ($meetingStartTime && $meetingEndTime && !$meetingEndTime->gt($meetingStartTime)) {
            return response()->json(['success' => false, 'message' => 'Waktu selesai meeting harus setelah waktu mulai'], 422);
        }

        // SLA hold dimulai sejak jadwal meeting dibuat.
        // Meeting akan auto-end pada meeting_end_time — tidak perlu klik "Selesai Meeting".
        $slaStart = $startAt;

        try {
            // Pause SLA sejak jadwal dibuat ($slaStart), event log tampilkan waktu meeting ($meetingStartTime)
            // scheduled_end_at = meeting_end_time → auto-resume SLA saat waktu ini tercapai
            $this->sla->startMeeting($ticket, $slaStart, $meetingStartTime, $meetingEndTime);

            // Bangun HTML undangan meeting
            $html      = $this->buildMeetingEmailHtml($ticket, $senderName, $notes, $meetingLink, $startAt, $meetingStartTime, $meetingEndTime);
            $msgParts  = array_filter([
                $meetingStartTime ? 'MeetingStart: ' . $meetingStartTime->toIso8601String() : null,
                $meetingEndTime   ? 'MeetingEnd: '   . $meetingEndTime->toIso8601String()   : null,
                $notes,
                $meetingLink ? "Link: {$meetingLink}" : null,
            ]);
            $plainBody = implode("\n", $msgParts) ?: 'Jadwal meeting dibuat';

            // Kirim via infrastruktur email yang SAMA dengan chat reply biasa
            $emailMsg = app(TicketMessageController::class)->sendSystemReplyEmail(
                $ticket,
                $senderId,
                $senderName,
                $html,
                $plainBody,
                'meeting_started',
                $ccEmails,
                $toEmails
            );

            // Fallback: jika tidak ada customer email atau email gagal, simpan sebagai pesan internal
            if (!$emailMsg) {
                TicketMessage::create([
                    'ticket_id'        => $ticket->ticket_id,
                    'sender_type'      => 'system',
                    'sender_id'        => $senderId,
                    'sender_name'      => $senderName,
                    'message'          => $plainBody,
                    'is_internal_note' => true,
                    'message_type'     => 'meeting_started',
                    'channel'          => 'web',
                    'created_at'       => $startAt,
                    'updated_at'       => $startAt,
                ]);
            }

            // Update last_message_at agar list tiket terurutkan ke posisi teratas
            $ticket->update(['last_message_at' => now(), 'last_agent_reply_at' => now()]);

            return response()->json([
                'success'    => true,
                'message'    => 'Jadwal meeting berhasil dibuat.',
                'email_sent' => (bool) $emailMsg,
            ]);
        } catch (\Throwable $e) {
            Log::error('SlaController@startMeeting failed', ['ticket_id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Gagal memulai meeting.'], 500);
        }
    }

    private function buildMeetingEmailHtml(
        Ticket  $ticket,
        string  $senderName,
        ?string $notes,
        ?string $meetingLink,
        Carbon  $startAt,
        ?Carbon $meetingStartTime = null,
        ?Carbon $meetingEndTime   = null
    ): string {
        $ticketNum = e($ticket->ticket_number ?? '');
        $agent     = e($senderName);
        $tz        = 'Asia/Jakarta';

        $notesHtml = $notes
            ? '<p style="margin:0 0 12px 0;">' . nl2br(e($notes)) . '</p>'
            : '';
        $linkBlock = $meetingLink
            ? '<div style="margin:20px 0;padding:16px 20px;background:#f5f3ff;border-radius:10px;border-left:4px solid #7c3aed;">
                 <p style="margin:0 0 8px 0;font-size:13px;font-weight:600;color:#5b21b6;">Link Meeting</p>
                 <a href="' . e($meetingLink) . '" style="color:#7c3aed;font-size:14px;font-weight:600;word-break:break-all;">' . e($meetingLink) . '</a>
               </div>'
            : '';

        // Baris waktu di tabel
        if ($meetingStartTime && $meetingEndTime) {
            $startStr  = $meetingStartTime->timezone($tz)->format('d M Y, H:i') . ' WIB';
            $endStr    = $meetingEndTime->timezone($tz)->format('d M Y, H:i') . ' WIB';
            $timeRows  = <<<HTML
            <tr><td style="padding:4px 0;font-size:13px;color:#6b7280;width:120px;">Mulai</td><td style="padding:4px 0;font-size:13px;font-weight:600;color:#374151;">{$startStr}</td></tr>
            <tr><td style="padding:4px 0;font-size:13px;color:#6b7280;">Selesai</td><td style="padding:4px 0;font-size:13px;font-weight:600;color:#374151;">{$endStr}</td></tr>
            HTML;
        } elseif ($meetingStartTime) {
            $startStr = $meetingStartTime->timezone($tz)->format('d M Y, H:i') . ' WIB';
            $timeRows = "<tr><td style=\"padding:4px 0;font-size:13px;color:#6b7280;width:120px;\">Waktu Mulai</td><td style=\"padding:4px 0;font-size:13px;font-weight:600;color:#374151;\">{$startStr}</td></tr>";
        } else {
            $timeStr  = $startAt->timezone($tz)->format('d M Y, H:i') . ' WIB';
            $timeRows = "<tr><td style=\"padding:4px 0;font-size:13px;color:#6b7280;width:120px;\">Waktu</td><td style=\"padding:4px 0;font-size:13px;font-weight:600;color:#374151;\">{$timeStr}</td></tr>";
        }

        return <<<HTML
        <p style="margin:0 0 12px 0;">Halo,</p>
        <p style="margin:0 0 12px 0;">
            Kami mengundang Anda untuk mengikuti <strong>sesi meeting</strong> terkait tiket
            <strong style="color:#374151;">#{$ticketNum}</strong>.
        </p>
        <table style="width:100%;background:#f9fafb;border-radius:8px;padding:12px 16px;margin-bottom:16px;border-collapse:collapse;">
            {$timeRows}
            <tr><td style="padding:4px 0;font-size:13px;color:#6b7280;">Host</td><td style="padding:4px 0;font-size:13px;font-weight:600;color:#374151;">{$agent} &mdash; PT Eclectic Consulting</td></tr>
        </table>
        {$notesHtml}
        {$linkBlock}
        <p style="margin:12px 0 0 0;font-size:13px;color:#6b7280;">Untuk pertanyaan lebih lanjut, silakan balas email ini.</p>
        HTML;
    }

    public function endMeeting(Request $request, $id)
    {
        if (!$this->assertMeetingAccess()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $v = Validator::make($request->all(), [
            'ended_at' => 'nullable|date',
            'notes'    => 'nullable|string|max:1000',
        ]);

        if ($v->fails()) {
            return response()->json(['success' => false, 'errors' => $v->errors()], 422);
        }

        $ticket     = Ticket::with('sla.policy')->findOrFail($id);
        $endAt      = $request->filled('ended_at') ? Carbon::parse($request->ended_at) : now();
        $senderName = session('user.name') ?? 'Helpdesk';
        $senderId   = session('user.id');
        $notes      = $request->input('notes');

        try {
            $this->sla->endMeeting($ticket, $endAt);

            // Hitung durasi dari pause record meeting yang baru ditutup
            $waitingH  = null;
            $lastPause = TicketSlaPause::where('ticket_id', $ticket->ticket_id)
                ->where('pause_reason', 'meeting')
                ->whereNotNull('ended_at')
                ->latest('ended_at')
                ->first();
            if ($lastPause) {
                $waitingH = $lastPause->duration_hours;
            }

            // Fallback: hitung dari sla_paused_at jika pause record belum closed
            if ($waitingH === null && $ticket->sla) {
                $ticket->sla->refresh();
                // Jika setelah endMeeting sla_paused_at sudah null, berarti durasi sudah dihitung
                // Ambil dari total_waiting_hours delta atau biarkan null
            }

            $durationText = $waitingH !== null ? round((float) $waitingH, 2) . ' jam' : null;
            $msgParts     = array_filter([$notes, $durationText ? "Durasi: {$durationText}" : null]);
            $msgBody      = implode("\n", $msgParts) ?: 'Meeting selesai';

            TicketMessage::create([
                'ticket_id'        => $ticket->ticket_id,
                'sender_type'      => 'system',
                'sender_id'        => $senderId,
                'sender_name'      => $senderName,
                'message'          => $msgBody,
                'is_internal_note' => true,
                'message_type'     => 'meeting_ended',
                'channel'          => 'web',
                'created_at'       => $endAt,
                'updated_at'       => $endAt,
            ]);

            // Update last_message_at agar list tiket terurutkan ke posisi teratas
            $ticket->update(['last_message_at' => now()]);

            return response()->json([
                'success'       => true,
                'message'       => 'Meeting selesai — SLA clock dilanjutkan.',
                'waiting_hours' => $waitingH,
            ]);
        } catch (\Throwable $e) {
            Log::error('SlaController@endMeeting failed', [
                'ticket_id' => $id,
                'error'     => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);
            return response()->json(['success' => false, 'message' => 'Gagal mengakhiri meeting: ' . $e->getMessage()], 500);
        }
    }

    // ── Private Formatters ────────────────────────────────────────────────────

    private function formatPolicy(SlaPolicy $p): array
    {
        $dsName = $p->deliverySupport
            ? trim($p->deliverySupport->name . ($p->deliverySupport->type ? ' (' . $p->deliverySupport->type . ')' : ''))
            : null;

        return [
            'id'                   => $p->id,
            'delivery_support_id'  => $p->delivery_support_id,
            'delivery_support_name'=> $dsName,
            'priority'             => $p->priority,
            'scale'                => $p->scale,
            'response_hours'       => (float) $p->response_hours,
            'resolution_hours'     => (float) $p->resolution_hours,
            'is_24_hours'          => (bool) $p->is_24_hours,
            'work_start_time'      => $p->work_start_time ? substr($p->work_start_time, 0, 5) : null,
            'work_end_time'        => $p->work_end_time ? substr($p->work_end_time, 0, 5) : null,
            'break_start_time'     => $p->break_start_time ? substr($p->break_start_time, 0, 5) : null,
            'break_end_time'       => $p->break_end_time ? substr($p->break_end_time, 0, 5) : null,
            'is_active'            => (bool) $p->is_active,
            'created_at'           => $p->created_at?->toDateTimeString(),
        ];
    }

    private function formatSlaRow(
        TicketSla $s,
        ?Carbon $firstResolvedAt = null,
        ?Carbon $docSentAt = null,
        ?\Illuminate\Support\Collection $notes = null,
        ?\Illuminate\Support\Collection $pauses = null
    ): array {
        $t       = $s->ticket;
        $staging = $s->stagingTicket;
        $policy  = $s->policy;

        // Resolve customer name: ticket > staging
        $customerName = null;
        if ($t?->customer?->basicData) {
            $bd           = $t->customer->basicData;
            $customerName = trim(($bd->title ?? '') . ' ' . ($bd->name_1 ?? ''));
        } elseif ($staging?->customer?->basicData) {
            $bd           = $staging->customer->basicData;
            $customerName = trim(($bd->title ?? '') . ' ' . ($bd->name_1 ?? ''));
        }

        $deliverySupportType = $t?->deliverySupportActivities?->first()?->deliverySupport?->type;

        $isPendingValidation = $s->resolution_status === 'pending_validation';

        $responseTargetHours    = $policy ? (float) $policy->response_hours   : null;
        $resolutionTargetHours  = $policy ? (float) $policy->resolution_hours : null;

        // Response/Resolution Duration + verdict are recomputed live from raw timestamps
        // (sla_start_at, first_responded_at, resolved_at, ticket_sla_pauses) rather than
        // read from the stored validation_duration_hours/net_resolution_hours/response_status/
        // resolution_status columns — those are frozen with whatever calcHours() looked like
        // at the time they were written, so historical tickets would keep showing stale
        // numbers after a calcHours() formula change unless recomputed live like this.
        $responseActualHours   = $this->sla->responseDurationHours($s);
        $responseStatus        = $this->sla->responseStatusLive($s);

        $resolutionMetrics     = $this->sla->liveResolutionMetrics($s, $pauses);
        $resolutionActualHours = $resolutionMetrics['net_hours'];
        $resolutionStatus      = $resolutionMetrics['status'];

        // Convert hours to days (8 working hours per day)
        $toWorkingDays = fn (?float $h) => $h !== null ? round($h / 8, 2) : null;

        $endStatuses = SlaService::END_STATUSES;
        $closedAt    = ($t && in_array($t->status, $endStatuses))
                       ? $t->updated_at?->toDateTimeString()
                       : null;

        return [
            'ticket_id'              => $t?->ticket_id,
            'ticket_number'          => $t?->ticket_number,
            'staging_id'             => $staging?->id,
            'is_pending_validation'  => $isPendingValidation,
            'year'                   => $s->sla_start_at?->year ?? ($t?->created_at?->year),
            'customer_name'          => $customerName,
            'description'            => $t?->description ?? $staging?->description,
            'module'                 => $t?->module_name,
            'ticket_type'            => $t?->ticket_type ?? ($isPendingValidation ? 'Pending Validation' : null),
            'ticket_priority'        => $t?->ticket_priority ?? $staging?->ticket_priority,
            'scale'                  => $t?->scale,
            'cust_pic'               => $t?->submitted_by_name ?? $t?->client,
            'pic'                    => $t?->ticketLead?->basicData?->full_name,
            'ticket_status'          => $t?->status,
            'sla_mode'               => $s->sla_mode,
            'delivery_support_type'  => $deliverySupportType,
            'received_at'            => ($staging?->created_at ?? $t?->created_at)?->toDateTimeString(),
            'sla_start_at'           => $s->sla_start_at?->toDateTimeString(),
            'sla_policy_missing'     => $policy === null,
            'closed_at'              => $closedAt,
            'ball_holder'            => $s->ball_holder,
            'response'               => [
                'status'        => $responseStatus,
                'actual_hours'  => $responseActualHours,
                'target_hours'  => $responseTargetHours,
                'target_days'   => $toWorkingDays($responseTargetHours),
                'due_at'        => $this->sla->responseDueAt($s)?->toDateTimeString(),
                'responded_at'  => $s->first_responded_at?->toDateTimeString(),
                'met'           => $responseStatus === 'met',
            ],
            'resolution'             => [
                'status'        => $resolutionStatus,
                'actual_hours'  => $resolutionActualHours,
                'actual_days'   => $toWorkingDays($resolutionActualHours),
                'target_hours'  => $resolutionTargetHours,
                'target_days'   => $toWorkingDays($resolutionTargetHours),
                'start_at'      => $this->sla->resolutionStartAt($s)?->toDateTimeString(),
                'due_at'        => $this->sla->resolutionDueAt($s)?->toDateTimeString(),
                'resolved_at'   => ($firstResolvedAt ?? $this->sla->firstResolvedAt($s))?->toDateTimeString(),
                'doc_sent_at'   => ($docSentAt ?? $this->sla->resolutionDocSentAt($s))?->toDateTimeString(),
                'met'           => in_array($resolutionStatus, ['met', 'pending_validation']),
            ],
            'waiting_hours'          => $this->sla->liveTotalWaitingHours($s, $pauses),
            // SLA notes: chat messages the agent tagged with a short "sla_message" annotation
            // (same source as the Log Shifting report), chronological, for the Notes column.
            'notes'                  => ($notes ?? collect())->map(fn ($m) => [
                'at'   => $m->created_at->toDateTimeString(),
                'text' => $this->stripLeadingTimestamp($m->sla_message),
            ])->values(),
        ];
    }

    /**
     * The Notes column already prefixes each line with the message's own created_at
     * timestamp — "[DD.MM.YYYY HH:mm] text". Some agents also manually type a leading
     * "[...]" (sometimes preceded by "- ") timestamp of their own into the sla_message
     * text itself (e.g. "- [14.07.2026 22.10] Email received."), which then duplicates
     * the auto timestamp. Strip that leading bracket so only the created_at date shows.
     */
    private function stripLeadingTimestamp(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        return trim(preg_replace('/^-?\s*\[[^\]]*\]\s*/', '', trim($text)));
    }

    /**
     * Live-recomputed duration/status metrics for the SLA PDF exports — same live methods
     * as the admin SLA report / SLA Log popup (SlaService::responseDurationHours() etc.),
     * so the PDF never shows stale figures for historical tickets whose stored
     * validation_duration_hours/net_resolution_hours/total_waiting_hours/*_status columns
     * were frozen with an older calcHours() formula.
     *
     * Also returns $pauses with each row's duration_hours recomputed in-memory (not
     * persisted) so the "Waiting / Pause History" table in the Log PDF matches the live total.
     *
     * @return array{0: \Illuminate\Support\Collection, 1: array}
     */
    private function liveSlaPdfMetrics(?TicketSla $sla, \Illuminate\Support\Collection $pauses): array
    {
        if (!$sla) {
            return [$pauses, [
                'responseActualHours'  => null,
                'responseStatusLive'   => null,
                'resolutionNetHours'   => null,
                'resolutionStatusLive' => null,
                'liveWaitingHours'     => 0.0,
            ]];
        }

        $is24h  = $sla->policy?->is_24_hours ?? true;
        $pauses = $pauses->map(function ($p) use ($sla, $is24h) {
            if ($p->ended_at) {
                $p->duration_hours = $this->sla->calcHours($p->started_at, $p->ended_at, $is24h, $sla->policy);
            }
            return $p;
        });

        // $pauses here was loaded with a whereNotNull('ended_at') constraint (see
        // downloadLogPdf/downloadTicketPdf), i.e. it's already exactly the "ended pauses"
        // set liveTotalWaitingHours()/liveResolutionMetrics() need — pass it through to
        // avoid a redundant query for the same data.
        $resolutionMetrics = $this->sla->liveResolutionMetrics($sla, $pauses);

        return [$pauses, [
            'responseActualHours'  => $this->sla->responseDurationHours($sla),
            'responseStatusLive'   => $this->sla->responseStatusLive($sla),
            'resolutionNetHours'   => $resolutionMetrics['net_hours'],
            'resolutionStatusLive' => $resolutionMetrics['status'],
            'liveWaitingHours'     => $this->sla->liveTotalWaitingHours($sla, $pauses),
        ]];
    }
}

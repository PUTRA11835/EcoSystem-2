<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\TicketSla;
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

    // Admin + Delivery Support Head + Helpdesk
    private function assertSlaAccess(): bool
    {
        return in_array(session('user.role.id'), [1, 5, 6], true);
    }

    // Admin + Delivery Support Head (can create/edit/delete policies)
    private function canManagePolicies(): bool
    {
        return in_array(session('user.role.id'), [1, 5], true);
    }

    // ── Web Pages ─────────────────────────────────────────────────────────────

    public function configPage()
    {
        if (!$this->assertSlaAccess()) {
            abort(403);
        }

        $canManage = $this->canManagePolicies();
        $customers = Customer::with('basicData')->where('is_active', true)->get();
        return view('admin.sla.config', compact('customers', 'canManage'));
    }

    public function reportPage()
    {
        if (!$this->assertSlaAccess()) {
            abort(403);
        }

        $customers = Customer::with('basicData')->where('is_active', true)->get();
        return view('admin.sla.report', compact('customers'));
    }

    // ── API: Policy CRUD ──────────────────────────────────────────────────────

    public function getPolicies(Request $request)
    {
        if (!$this->assertSlaAccess()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $query = SlaPolicy::with('customer.basicData');

        if ($request->filled('customer_id')) {
            if ($request->customer_id === 'global') {
                $query->whereNull('customer_id');
            } else {
                $query->where('customer_id', $request->customer_id);
            }
        }

        $policies = $query->orderByRaw('customer_id IS NULL ASC')
            ->orderBy('customer_id')
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
            'customer_id'      => 'nullable|exists:customer,customer_id',
            'priority'         => 'required|in:Low,Medium,High,Very High',
            'scale'            => 'required|in:Simple,Medium,Complex',
            'response_hours'   => 'required|numeric|min:0.1|max:999',
            'resolution_hours' => 'required|numeric|min:0.1|max:999',
            'is_24_hours'      => 'boolean',
        ]);

        if ($v->fails()) {
            return response()->json(['success' => false, 'errors' => $v->errors()], 422);
        }

        $customerId = $request->customer_id ?: null;
        $exists = SlaPolicy::where('customer_id', $customerId)
            ->where('priority', $request->priority)
            ->where('scale', $request->scale)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'A policy for this customer / priority / scale combination already exists.',
            ], 422);
        }

        $policy = SlaPolicy::create([
            'customer_id'      => $customerId,
            'priority'         => $request->priority,
            'scale'            => $request->scale,
            'response_hours'   => $request->response_hours,
            'resolution_hours' => $request->resolution_hours,
            'is_24_hours'      => $request->priority === 'Very High' ? true : $request->boolean('is_24_hours', false),
            'is_active'        => true,
            'created_by'       => session('user.id'),
        ]);

        return response()->json([
            'success' => true,
            'data'    => $this->formatPolicy($policy->load('customer.basicData')),
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
            'is_active'        => 'sometimes|boolean',
        ]);

        if ($v->fails()) {
            return response()->json(['success' => false, 'errors' => $v->errors()], 422);
        }

        $policy  = SlaPolicy::findOrFail($id);
        $updates = $request->only(['response_hours', 'resolution_hours', 'is_24_hours', 'is_active']);
        if ($policy->priority === 'Very High') {
            $updates['is_24_hours'] = true;
        }
        $policy->update($updates);

        return response()->json([
            'success' => true,
            'data'    => $this->formatPolicy($policy->load('customer.basicData')),
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
        $liveWait = $this->sla->liveWaitingHours($sla);

        $responseData = [
            'status'       => $sla->response_status,
            'target_hours' => $policy ? (float) $policy->response_hours : null,
            'actual_hours' => $sla->validation_duration_hours !== null
                               ? (float) $sla->validation_duration_hours : null,
            'due_at'       => $sla->response_due_at?->toDateTimeString(),
            'responded_at' => $sla->first_responded_at?->toDateTimeString(),
        ];

        $resolutionData = null;
        if ($sla->sla_mode === 'full') {
            $resolutionData = [
                'status'        => $sla->resolution_status,
                'target_hours'  => $policy ? (float) $policy->resolution_hours : null,
                'actual_hours'  => $sla->net_resolution_hours !== null
                                    ? (float) $sla->net_resolution_hours : null,
                'due_at'        => $sla->resolution_due_at?->toDateTimeString(),
                'resolved_at'   => $sla->resolved_at?->toDateTimeString(),
                'net_hours'     => $sla->net_resolution_hours !== null
                                    ? (float) $sla->net_resolution_hours : null,
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
                'event_at'         => $e->event_at->toDateTimeString(),
                'label'            => $e->event_label,
                'jarvis_status'    => $e->jarvis_status,
                'waiting_hours'    => $e->waiting_hours !== null ? (float) $e->waiting_hours : null,
                'response_hours'   => $e->response_hours !== null ? (float) $e->response_hours : null,
                'resolution_hours' => $e->resolution_hours !== null ? (float) $e->resolution_hours : null,
                'notes'            => $e->notes,
                'ball_after'       => $e->event_type === 'ticket_validated' ? 'helpdesk' : null,
                'sender_name'      => null,
                'message_preview'  => $e->message
                    ? mb_substr(strip_tags($e->message->message ?? $e->message->message_html ?? ''), 0, 120)
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
            $storedByMsgId, $pauseStartedByMsg, $pauseEndedByMsg,
            $stopStatuses, $runStatuses, $is24h,
            $lastAgentMsgId, $currentTicketStatus, $sla, $ticketIsClosed,
            &$ballHolder, &$sessionStart, &$pauseStart, &$lastAgentAt
        ) {
            $stored     = $storedByMsgId->get($msg->id);
            $isCustomer = $msg->sender_type === 'customer';

            $waitingH     = null;
            $resolutionH  = null;
            $ballAfter    = null;
            $jarvisStatus = null;

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
                    $waitingH = round($this->sla->calcHours($pauseStart, $msg->created_at, $is24h), 2);

                } elseif ($lastAgentAt !== null) {
                    // Tidak ada formal pause — hitung gap sejak agent terakhir balas
                    $waitingH = round($this->sla->calcHours($lastAgentAt, $msg->created_at, $is24h), 2);
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
                    $resolutionH = round($this->sla->calcHours($sessionStart, $msg->created_at, $is24h), 2);
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
                'event_at'         => $msg->created_at->toDateTimeString(),
                'label'            => $isCustomer ? 'Customer replied' : 'Agent replied',
                'jarvis_status'    => $jarvisStatus,
                'waiting_hours'    => $waitingH,
                'response_hours'   => null,
                'resolution_hours' => $isCustomer ? null : $resolutionH,
                'notes'            => $stored?->notes,
                'ball_after'       => $ballAfter,
                'sender_name'      => $msg->sender_name,
                'message_preview'  => mb_substr(strip_tags($msg->message ?? $msg->message_html ?? ''), 0, 120) ?: null,
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

        $query = TicketSla::with(['ticket.customer.basicData', 'policy', 'stagingTicket.customer.basicData'])
            ->where(function ($q) {
                // Tampilkan tiket yang sudah divalidasi ATAU staging yang masih pending_validation
                $q->whereNotNull('ticket_id')
                  ->orWhere('resolution_status', 'pending_validation');
            });

        if ($request->filled('customer_id')) {
            $query->whereHas('ticket', fn ($q) => $q->where('customer_id', $request->customer_id));
        }

        if ($request->filled('month') && $request->filled('year')) {
            $query->whereMonth('sla_start_at', $request->month)
                  ->whereYear('sla_start_at', $request->year);
        } elseif ($request->filled('year')) {
            $query->whereYear('sla_start_at', $request->year);
        }

        if ($request->filled('resolution_status')) {
            $query->where('resolution_status', $request->resolution_status);
        }

        $slas = $query->orderBy('sla_start_at', 'desc')->limit(200)->get();

        $total    = $slas->count();
        $met      = $slas->where('resolution_status', 'met')->count();
        $breached = $slas->where('resolution_status', 'breached')->count();
        $active   = $slas->whereIn('resolution_status', ['pending', 'paused'])->count();
        $compRate = ($met + $breached) > 0
                    ? round($met / ($met + $breached) * 100, 2)
                    : null;

        $avgResponse   = $slas->whereNotNull('validation_duration_hours')->avg('validation_duration_hours');
        $avgResolution = $slas->whereNotNull('net_resolution_hours')->avg('net_resolution_hours');

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
                'tickets' => $slas->map(fn ($s) => $this->formatSlaRow($s)),
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

        $sla    = $ticket->sla;
        $policy = $sla?->policy;
        $pauses = $sla?->pauses ?? collect();

        $events = $sla ? $this->buildSlaEventLog($sla, $ticket->ticket_id, $ticket->status) : collect();

        $docNumber = 'ECL/SLA/' . $ticket->ticket_number . '/' . now()->format('Ym');

        $pdf = Pdf::loadView('admin.sla.log-pdf', compact(
            'ticket', 'sla', 'policy', 'events', 'pauses', 'docNumber'
        ));
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

        $sla    = $ticket->sla;
        $policy = $sla?->policy;
        $events = $sla?->events ?? collect();
        $pauses = $sla?->pauses ?? collect();

        $pdf = Pdf::loadView('admin.sla.ticket-pdf', compact('ticket', 'sla', 'policy', 'events', 'pauses'));
        $pdf->setPaper('A4', 'portrait');

        return $pdf->download('SLA-Ticket-' . $ticket->ticket_number . '.pdf');
    }

    // ── API: Meeting pause/resume ─────────────────────────────────────────────

    public function startMeeting(Request $request, $id)
    {
        if (!$this->assertSlaAccess()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $v = Validator::make($request->all(), [
            'started_at'   => 'nullable|date',
            'notes'        => 'nullable|string|max:1000',
            'meeting_link' => 'nullable|url|max:2048',
        ]);

        if ($v->fails()) {
            return response()->json(['success' => false, 'errors' => $v->errors()], 422);
        }

        $ticket      = Ticket::with(['sla.policy', 'customer'])->findOrFail($id);
        $startAt     = $request->filled('started_at') ? Carbon::parse($request->started_at) : now();
        $senderName  = session('user.name') ?? 'Helpdesk';
        $senderId    = (int) session('user.id');
        $notes       = $request->input('notes');
        $meetingLink = $request->input('meeting_link');

        try {
            $this->sla->startMeeting($ticket, $startAt);

            // Bangun HTML undangan meeting (tanpa footer — TicketMessageController menambahkannya)
            $html      = $this->buildMeetingEmailHtml($ticket, $senderName, $notes, $meetingLink, $startAt);
            $msgParts  = array_filter([$notes, $meetingLink ? "Link: {$meetingLink}" : null]);
            $plainBody = implode("\n", $msgParts) ?: 'Meeting dimulai';

            // Kirim via infrastruktur email yang SAMA dengan chat reply biasa
            $emailMsg = app(TicketMessageController::class)->sendSystemReplyEmail(
                $ticket,
                $senderId,
                $senderName,
                $html,
                $plainBody,
                'meeting_started'
            );

            // Fallback: jika tidak ada customer email atau email gagal, simpan sebagai
            // pesan internal agar meeting card tetap muncul di room chat
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

            return response()->json([
                'success'    => true,
                'message'    => 'Meeting dimulai — SLA clock dijeda.',
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
        Carbon  $startAt
    ): string {
        $ticketNum  = e($ticket->ticket_number ?? '');
        $agent      = e($senderName);
        $timeStr    = $startAt->timezone('Asia/Jakarta')->format('d M Y, H:i') . ' WIB';
        $notesHtml  = $notes
            ? '<p style="margin:0 0 12px 0;">' . nl2br(e($notes)) . '</p>'
            : '';
        $linkBlock  = $meetingLink
            ? '<div style="margin:20px 0;padding:16px 20px;background:#f5f3ff;border-radius:10px;border-left:4px solid #7c3aed;">
                 <p style="margin:0 0 8px 0;font-size:13px;font-weight:600;color:#5b21b6;">Link Meeting</p>
                 <a href="' . e($meetingLink) . '" style="color:#7c3aed;font-size:14px;font-weight:600;word-break:break-all;">' . e($meetingLink) . '</a>
               </div>'
            : '';

        return <<<HTML
        <p style="margin:0 0 12px 0;">Halo,</p>
        <p style="margin:0 0 12px 0;">
            Kami mengundang Anda untuk mengikuti <strong>sesi meeting</strong> terkait tiket
            <strong style="color:#374151;">#{$ticketNum}</strong>.
        </p>
        <table style="width:100%;background:#f9fafb;border-radius:8px;padding:12px 16px;margin-bottom:16px;border-collapse:collapse;">
            <tr><td style="padding:4px 0;font-size:13px;color:#6b7280;width:120px;">Waktu</td><td style="padding:4px 0;font-size:13px;font-weight:600;color:#374151;">{$timeStr}</td></tr>
            <tr><td style="padding:4px 0;font-size:13px;color:#6b7280;">Host</td><td style="padding:4px 0;font-size:13px;font-weight:600;color:#374151;">{$agent} &mdash; PT Eclectic Consulting</td></tr>
        </table>
        {$notesHtml}
        {$linkBlock}
        <p style="margin:12px 0 0 0;font-size:13px;color:#6b7280;">Untuk pertanyaan lebih lanjut, silakan balas email ini.</p>
        HTML;
    }

    public function endMeeting(Request $request, $id)
    {
        if (!$this->assertSlaAccess()) {
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
        $customerName = null;
        if ($p->customer && $p->customer->basicData) {
            $bd           = $p->customer->basicData;
            $customerName = trim(($bd->title ?? '') . ' ' . ($bd->name_1 ?? ''));
        }

        return [
            'id'               => $p->id,
            'customer_id'      => $p->customer_id,
            'customer_name'    => $customerName,
            'priority'         => $p->priority,
            'scale'            => $p->scale,
            'response_hours'   => (float) $p->response_hours,
            'resolution_hours' => (float) $p->resolution_hours,
            'is_24_hours'      => (bool) $p->is_24_hours,
            'is_active'        => (bool) $p->is_active,
            'created_at'       => $p->created_at?->toDateTimeString(),
        ];
    }

    private function formatSlaRow(TicketSla $s): array
    {
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

        $isPendingValidation = $s->resolution_status === 'pending_validation';

        return [
            'ticket_id'            => $t?->ticket_id,
            'ticket_number'        => $t?->ticket_number,
            'staging_id'           => $staging?->id,
            'is_pending_validation' => $isPendingValidation,
            'description'          => $t?->description ?? $staging?->description,
            'customer_name'        => $customerName,
            'ticket_type'          => $t?->ticket_type ?? ($isPendingValidation ? 'Pending Validation' : null),
            'ticket_priority'      => $t?->ticket_priority ?? $staging?->ticket_priority,
            'scale'                => $t?->scale,
            'sla_mode'             => $s->sla_mode,
            'sla_start_at'         => $s->sla_start_at?->toDateTimeString(),
            'ball_holder'          => $s->ball_holder,
            'response'             => [
                'status'       => $s->response_status,
                'actual_hours' => $s->validation_duration_hours !== null
                                   ? (float) $s->validation_duration_hours : null,
                'target_hours' => $policy ? (float) $policy->response_hours : null,
                'due_at'       => $s->response_due_at?->toDateTimeString(),
            ],
            'resolution'           => [
                'status'       => $s->resolution_status,
                'actual_hours' => $s->net_resolution_hours !== null
                                   ? (float) $s->net_resolution_hours : null,
                'target_hours' => $policy ? (float) $policy->resolution_hours : null,
                'due_at'       => $s->resolution_due_at?->toDateTimeString(),
                'resolved_at'  => $s->resolved_at?->toDateTimeString(),
            ],
            'waiting_hours'        => (float) $s->total_waiting_hours,
        ];
    }
}

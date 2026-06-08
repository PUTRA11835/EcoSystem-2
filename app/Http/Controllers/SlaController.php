<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\TicketSla;
use App\Services\SlaService;
use Barryvdh\DomPDF\Facade\Pdf;
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

        $stopStatuses = ['waiting_on_customer', 'waiting_to_confirmation', 'waiting_on_3rd_party', 'hold'];

        $events = $sla->events->map(fn ($e) => [
            'event_type'       => $e->event_type,
            'event_at'         => $e->event_at->toDateTimeString(),
            'label'            => $e->event_label,
            'jarvis_status'    => $e->jarvis_status,
            'waiting_hours'    => $e->waiting_hours !== null ? (float) $e->waiting_hours : null,
            'response_hours'   => $e->response_hours !== null ? (float) $e->response_hours : null,
            'resolution_hours' => $e->resolution_hours !== null ? (float) $e->resolution_hours : null,
            'notes'            => $e->notes,
            'ball_after'       => match (true) {
                $e->event_type === 'customer_replied'                               => 'helpdesk',
                $e->event_type === 'ticket_validated'                               => 'helpdesk',
                $e->event_type === 'agent_replied' && $e->jarvis_status === 'waiting_on_3rd_party' => 'sap',
                $e->event_type === 'agent_replied' && in_array($e->jarvis_status, $stopStatuses) => 'customer',
                $e->event_type === 'agent_replied'                                  => 'helpdesk',
                default                                                             => null,
            },
            'message_preview'  => $e->message
                ? mb_substr(strip_tags($e->message->message ?? $e->message->message_html ?? ''), 0, 120)
                : null,
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'sla_mode'    => $sla->sla_mode,
                'ball_holder' => $sla->ball_holder,
                'response'    => $responseData,
                'resolution'  => $resolutionData,
                'events'      => $events,
            ],
        ]);
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

        $stopStatuses = ['waiting_on_customer', 'waiting_to_confirmation', 'waiting_on_3rd_party', 'hold'];

        $events = ($sla?->events ?? collect())->map(function ($e) use ($stopStatuses) {
            $e->ball_after = match (true) {
                $e->event_type === 'customer_replied'                                              => 'helpdesk',
                $e->event_type === 'ticket_validated'                                              => 'helpdesk',
                $e->event_type === 'agent_replied' && $e->jarvis_status === 'waiting_on_3rd_party' => 'sap',
                $e->event_type === 'agent_replied' && in_array($e->jarvis_status, $stopStatuses)   => 'customer',
                $e->event_type === 'agent_replied'                                                 => 'helpdesk',
                default                                                                            => null,
            };
            $e->message_preview = $e->message
                ? mb_substr(strip_tags($e->message->message ?? $e->message->message_html ?? ''), 0, 110)
                : null;
            return $e;
        });

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

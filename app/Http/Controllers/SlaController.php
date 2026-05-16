<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\TicketSla;
use App\Services\SlaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SlaController extends Controller
{
    private function assertAdmin(): bool
    {
        return (int) session('user.role.id') === 1;
    }

    // ─── PAGES ───────────────────────────────────────────────────────────────

    public function configPage()
    {
        if (!$this->assertAdmin()) abort(403);

        $customers = Customer::with('basicData')
            ->where('is_active', true)
            ->orderBy('customer_id')
            ->get();

        return view('admin.sla.config', compact('customers'));
    }

    public function reportPage()
    {
        if (!$this->assertAdmin()) abort(403);

        $customers = Customer::with('basicData')
            ->where('is_active', true)
            ->orderBy('customer_id')
            ->get();

        return view('admin.sla.report', compact('customers'));
    }

    // ─── API: SLA POLICIES ───────────────────────────────────────────────────

    public function getPolicies(Request $request)
    {
        if (!$this->assertAdmin()) return response()->json(['success' => false], 403);

        try {
            $query = SlaPolicy::with(['customer.basicData', 'createdBy.basicData'])
                ->orderByRaw('customer_id IS NULL ASC')
                ->orderBy('customer_id')
                ->orderByRaw("FIELD(priority, 'Very High', 'High', 'Medium', 'Low')")
                ->orderByRaw("FIELD(scale, 'Simple', 'Medium', 'Complex')");

            if ($request->filled('customer_id')) {
                if ($request->customer_id === 'global') {
                    $query->whereNull('customer_id');
                } else {
                    $query->where('customer_id', $request->customer_id);
                }
            }

            $policies = $query->get()->map(fn($p) => [
                'id'               => $p->id,
                'customer_id'      => $p->customer_id,
                'customer_name'    => $p->customer
                    ? ($p->customer->basicData->name_1 ?? $p->customer->customer_code ?? 'Customer #' . $p->customer_id)
                    : 'Default Global',
                'priority'         => $p->priority,
                'scale'            => $p->scale,
                'response_hours'   => (float) $p->response_hours,
                'resolution_hours' => (float) $p->resolution_hours,
                'is_24_hours'      => $p->is_24_hours,
                'is_active'        => $p->is_active,
                'created_at'       => $p->created_at?->format('d/m/Y'),
            ]);

            return response()->json(['success' => true, 'data' => $policies]);
        } catch (\Exception $e) {
            Log::error('SlaController@getPolicies', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to load data'], 500);
        }
    }

    public function storePolicy(Request $request)
    {
        if (!$this->assertAdmin()) return response()->json(['success' => false], 403);

        $validated = $request->validate([
            'customer_id'      => 'nullable|exists:customer,customer_id',
            'priority'         => 'required|in:Low,Medium,High,Very High',
            'scale'            => 'required|in:Simple,Medium,Complex',
            'response_hours'   => 'required|numeric|min:0.1|max:999',
            'resolution_hours' => 'required|numeric|min:0.1|max:9999',
            'is_24_hours'      => 'boolean',
        ]);

        // Cek duplikat
        $exists = SlaPolicy::where('priority', $validated['priority'])
            ->where('scale', $validated['scale'])
            ->where(function ($q) use ($validated) {
                if (empty($validated['customer_id'])) {
                    $q->whereNull('customer_id');
                } else {
                    $q->where('customer_id', $validated['customer_id']);
                }
            })->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'A policy for this Customer + Priority + Scale combination already exists.',
            ], 422);
        }

        try {
            $policy = SlaPolicy::create([
                ...$validated,
                'customer_id' => $validated['customer_id'] ?? null,
                'is_24_hours' => $request->boolean('is_24_hours'),
                'is_active'   => true,
                'created_by'  => session('user.employee_id'),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'SLA policy added successfully.',
                'data'    => ['id' => $policy->id],
            ]);
        } catch (\Exception $e) {
            Log::error('SlaController@storePolicy', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to save policy'], 500);
        }
    }

    public function updatePolicy(Request $request, int $id)
    {
        if (!$this->assertAdmin()) return response()->json(['success' => false], 403);

        $policy = SlaPolicy::findOrFail($id);

        $validated = $request->validate([
            'response_hours'   => 'required|numeric|min:0.1|max:999',
            'resolution_hours' => 'required|numeric|min:0.1|max:9999',
            'is_24_hours'      => 'boolean',
            'is_active'        => 'boolean',
        ]);

        try {
            $policy->update([
                'response_hours'   => $validated['response_hours'],
                'resolution_hours' => $validated['resolution_hours'],
                'is_24_hours'      => $request->boolean('is_24_hours'),
                'is_active'        => $request->boolean('is_active', true),
            ]);

            return response()->json(['success' => true, 'message' => 'SLA policy updated successfully.']);
        } catch (\Exception $e) {
            Log::error('SlaController@updatePolicy', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to update policy'], 500);
        }
    }

    public function destroyPolicy(int $id)
    {
        if (!$this->assertAdmin()) return response()->json(['success' => false], 403);

        $policy = SlaPolicy::findOrFail($id);

        // Cek apakah policy sudah digunakan oleh tiket
        $usedCount = TicketSla::where('sla_policy_id', $id)->count();
        if ($usedCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "This policy is used by {$usedCount} ticket(s). Deactivate it instead of deleting.",
            ], 422);
        }

        try {
            $policy->delete();
            return response()->json(['success' => true, 'message' => 'SLA policy deleted successfully.']);
        } catch (\Exception $e) {
            Log::error('SlaController@destroyPolicy', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to delete policy'], 500);
        }
    }

    // ─── API: SLA PER TIKET (event log dihitung live dari ticket_message) ────

    public function getTicketSla(int $id)
    {
        try {
            $sla = TicketSla::with('policy')->where('ticket_id', $id)->first();
            if (!$sla) {
                return response()->json(['success' => false, 'message' => 'No SLA data found for this ticket'], 404);
            }

            $is24h      = (bool) ($sla->policy?->is_24_hours ?? true);
            $slaService = app(SlaService::class);

            // ─── Fetch semua pesan non-internal, terurut waktu ───────────────
            $msgs = DB::table('ticket_message')
                ->where('ticket_id', $id)
                ->where('is_internal_note', 0)
                ->orderBy('created_at')
                ->get(['id', 'sender_type', 'created_at', 'message', 'message_html']);

            $msgIds = $msgs->pluck('id')->all();

            // jarvis_status per message_id (lookup saja, bukan kalkulasi)
            $jarviesMap = DB::table('ticket_sla_events')
                ->where('ticket_id', $id)
                ->where('event_type', 'agent_replied')
                ->whereIn('message_id', $msgIds)
                ->pluck('jarvis_status', 'message_id');

            // attachment flag per message_id
            $attachmentMsgIds = DB::table('ticket_attachment')
                ->whereIn('message_id', $msgIds)
                ->pluck('message_id')
                ->flip(); // flip untuk O(1) lookup

            // system events (email_received, ticket_validated, ticket_closed) — tetap dari tabel
            $systemEvents = DB::table('ticket_sla_events')
                ->where('ticket_id', $id)
                ->whereIn('event_type', ['email_received', 'ticket_validated', 'ticket_closed'])
                ->orderBy('event_at')
                ->get();

            // ─── State machine: hitung live dari pesan ────────────────────────
            // Resolution Time dihitung dari sla_start_at (awal tiket) untuk semua agent reply.
            // Meeting event: waiting berjalan selama meeting, berhenti saat End Meeting diklik.

            $solutionStartedAt = $sla->solution_started_at ? Carbon::parse($sla->solution_started_at) : null;
            $slaStartAt        = Carbon::parse($sla->sla_start_at);

            // Jika ada pesan lebih awal dari sla_start_at, gunakan waktu pesan pertama sebagai baseline
            $firstMsgAt = $msgs->first() ? Carbon::parse($msgs->first()->created_at) : null;
            if ($firstMsgAt && $firstMsgAt->lt($slaStartAt)) {
                $slaStartAt = $firstMsgAt;
            }

            $inResolutionPhase = false;

            $ballHolder       = 'helpdesk';
            $pausedAt         = null;
            $lastJarvisStatus = null;
            $totalWaiting     = 0.0;

            $events = [];

            // System events sebelum pesan (email_received, ticket_validated)
            foreach ($systemEvents->whereIn('event_type', ['email_received', 'ticket_validated']) as $se) {
                $events[] = $this->systemEventRow($se);
            }

            foreach ($msgs as $msg) {
                // Skip pesan sistem (status change, notifikasi otomatis)
                if ($msg->sender_type === 'system') continue;

                $eventAt = Carbon::parse($msg->created_at);
                $preview = $this->buildPreview($msg, $attachmentMsgIds);

                // Detect meeting message
                $meetingData = null;
                $decoded = json_decode($msg->message ?? '', true);
                if (is_array($decoded) && ($decoded['_type'] ?? '') === 'meeting') {
                    $meetingData = $decoded;
                }

                // Insert solution_started event at correct chronological position
                if ($solutionStartedAt && !$inResolutionPhase && $eventAt->gte($solutionStartedAt)) {
                    $inResolutionPhase = true;
                    $events[] = [
                        'event_type'       => 'solution_started',
                        'event_at'         => $solutionStartedAt->format('d/m/Y H:i'),
                        'waiting_hours'    => null,
                        'response_hours'   => $slaService->calcHours($slaStartAt, $solutionStartedAt, $is24h),
                        'resolution_hours' => $slaService->calcHours($slaStartAt, $solutionStartedAt, $is24h),
                        'jarvis_status'    => null,
                        'notes'            => 'Solution phase started',
                        'message_preview'  => null,
                    ];
                }

                if ($meetingData) {
                    // Meeting = seperti agent reply: ball ke customer, waiting mulai dari meeting dibuat.
                    // End Meeting = seperti customer reply: waiting berhenti, ball kembali ke helpdesk.
                    $endedAt      = !empty($meetingData['ended_at']) ? Carbon::parse($meetingData['ended_at']) : null;
                    $meetingEnded = $endedAt !== null;
                    $waitingHours = null;

                    if ($meetingEnded) {
                        // Waiting dihitung dari waktu meeting dibuat sampai End Meeting diklik
                        $waitingHours  = $slaService->calcHours($eventAt, $endedAt, $is24h);
                        $totalWaiting += $waitingHours;
                        $ballHolder    = 'helpdesk';
                        $pausedAt      = null;
                        $lastJarvisStatus = null;
                    } else {
                        // Meeting ongoing: ball ke customer, waiting mulai jalan dari sekarang
                        $ballHolder = 'customer';
                        $pausedAt   = $eventAt;
                    }

                    $events[] = [
                        'event_type'       => 'meeting_scheduled',
                        'event_at'         => $eventAt->format('d/m/Y H:i'),
                        'waiting_hours'    => $waitingHours,
                        'resolution_hours' => null,
                        'jarvis_status'    => null,
                        'meeting_ended'    => $meetingEnded,
                        'notes'            => $meetingData['title'] ?? 'Meeting',
                        'message_preview'  => isset($meetingData['agenda']) && $meetingData['agenda']
                            ? mb_substr($meetingData['agenda'], 0, 100)
                            : null,
                    ];

                } elseif ($msg->sender_type === 'customer') {
                    $waitingHours = null;

                    if (in_array($ballHolder, ['customer', 'sap'], true) && $pausedAt) {
                        // Customer pertama setelah agent reply → hitung waiting dari pausedAt
                        $waitingHours  = $slaService->calcHours($pausedAt, $eventAt, $is24h);
                        $totalWaiting += $waitingHours;
                        $ballHolder    = 'helpdesk';
                        $pausedAt      = null;
                    }

                    $lastJarvisStatus = null;

                    $events[] = [
                        'event_type'       => 'customer_replied',
                        'event_at'         => $eventAt->format('d/m/Y H:i'),
                        'waiting_hours'    => $waitingHours,
                        'response_hours'   => null,
                        'resolution_hours' => null,
                        'jarvis_status'    => null,
                        'notes'            => 'Customer replied',
                        'message_preview'  => $preview,
                    ];

                } else {
                    // Agent reply
                    $jarviesStatus = $jarviesMap->get($msg->id) ?? 'in process';

                    // Keduanya selalu dihitung dari baseline (sla_start_at atau pesan pertama, mana yang lebih awal)
                    $responseHours   = $slaService->calcHours($slaStartAt, $eventAt, $is24h);
                    $resolutionHours = $slaService->calcHours($slaStartAt, $eventAt, $is24h);

                    if ($ballHolder !== 'helpdesk' && $pausedAt) {
                        if ($jarviesStatus !== $lastJarvisStatus) {
                            $pausedAt   = $eventAt;
                            $ballHolder = ($jarviesStatus === 'sent in to SAP') ? 'sap' : 'customer';
                        }
                    } else {
                        $ballHolder = ($jarviesStatus === 'sent in to SAP') ? 'sap' : 'customer';
                        $pausedAt   = $eventAt;
                    }

                    $lastJarvisStatus = $jarviesStatus;

                    $events[] = [
                        'event_type'       => 'agent_replied',
                        'event_at'         => $eventAt->format('d/m/Y H:i'),
                        'waiting_hours'    => null,
                        'response_hours'   => $responseHours,
                        'resolution_hours' => $resolutionHours,
                        'jarvis_status'    => $jarviesStatus,
                        'notes'            => 'Helpdesk reply sent',
                        'message_preview'  => $preview,
                    ];
                }
            }

            // If solution_started_at is after all messages, append it now
            if ($solutionStartedAt && !$inResolutionPhase) {
                $events[] = [
                    'event_type'       => 'solution_started',
                    'event_at'         => $solutionStartedAt->format('d/m/Y H:i'),
                    'waiting_hours'    => null,
                    'response_hours'   => $slaService->calcHours($slaStartAt, $solutionStartedAt, $is24h),
                    'resolution_hours' => $slaService->calcHours($slaStartAt, $solutionStartedAt, $is24h),
                    'jarvis_status'    => null,
                    'notes'            => 'Solution phase started',
                    'message_preview'  => null,
                ];
            }

            // ticket_closed tetap dari tabel (ada net_resolution_hours final)
            $closedEvent = $systemEvents->where('event_type', 'ticket_closed')->first();

            // Auto-backfill: tiket sudah closed di DB tapi event belum tercatat
            if (!$closedEvent && !$sla->isClosed()) {
                $ticket = Ticket::find($id);
                if ($ticket && in_array($ticket->status, ['closed'], true)) {
                    $closedAt = Carbon::parse($ticket->updated_at);
                    $slaService->closeTicketSla($sla, $ticket, null, $closedAt);
                    $sla->refresh();
                    // Reload event yang baru saja di-insert
                    $closedEvent = DB::table('ticket_sla_events')
                        ->where('ticket_id', $id)
                        ->where('event_type', 'ticket_closed')
                        ->first();
                }
            }

            if ($closedEvent) {
                $events[] = $this->systemEventRow($closedEvent);
            }

            // Jika bola masih di customer/SAP, tambahkan waiting yang sedang berjalan ke summary
            $liveWaiting = $totalWaiting;
            if ($ballHolder !== 'helpdesk' && $pausedAt) {
                $liveWaiting += $slaService->calcHours($pausedAt, now(), $is24h);
            }

            $isResponseOnly = ($sla->sla_mode ?? 'full') === 'response_only';

            return response()->json([
                'success'    => true,
                'sla_mode'   => $sla->sla_mode ?? 'full',
                'sla_status' => [
                    'response'   => [
                        'status' => $sla->response_status,
                        'hours'  => $sla->validation_duration_hours ? (float) $sla->validation_duration_hours : null,
                        'target' => $sla->policy ? (float) $sla->policy->response_hours : null,
                    ],
                    'resolution' => $isResponseOnly ? null : [
                        'status' => $sla->resolution_status,
                        'hours'  => $sla->net_resolution_hours ? (float) $sla->net_resolution_hours : null,
                        'target' => $sla->policy ? (float) $sla->policy->resolution_hours : null,
                    ],
                ],
                'summary'    => [
                    'started_at'           => $sla->sla_start_at?->format('d/m/Y H:i'),
                    'closed_at'            => $sla->resolved_at?->format('d/m/Y H:i'),
                    'total_waiting'        => round($liveWaiting, 2),
                    'solution_started_at'  => $sla->solution_started_at?->format('d/m/Y H:i'),
                ],
                'events'     => $events,
            ]);
        } catch (\Exception $e) {
            Log::error('SlaController@getTicketSla', ['error' => $e->getMessage(), 'line' => $e->getFile() . ':' . $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'Failed to load SLA data'], 500);
        }
    }

    private function systemEventRow(object $se): array
    {
        return [
            'event_type'       => $se->event_type,
            'event_at'         => Carbon::parse($se->event_at)->format('d/m/Y H:i'),
            'waiting_hours'    => $se->waiting_hours    ? (float) $se->waiting_hours    : null,
            'response_hours'   => $se->response_hours   ? (float) $se->response_hours   : null,
            'resolution_hours' => $se->resolution_hours ? (float) $se->resolution_hours : null,
            'jarvis_status'    => $se->jarvis_status,
            'notes'            => $se->notes,
            'message_preview'  => null,
        ];
    }

    private function buildPreview(object $msg, \Illuminate\Support\Collection $attachmentIds): ?string
    {
        $hasAtt  = $attachmentIds->has($msg->id);
        $rawText = mb_substr(trim(strip_tags($msg->message_html ?: $msg->message ?? '')), 0, 200);

        if ($rawText) return $rawText . ($hasAtt ? ' [+attachment]' : '');
        if ($hasAtt)  return 'attachment';
        return null;
    }

    // ─── DOWNLOAD PDF PER TIKET ──────────────────────────────────────────────

    public function downloadTicketPdf(int $id)
    {
        if (!$this->assertAdmin()) abort(403);

        $sla = TicketSla::with(['policy', 'events', 'pauses'])
            ->where('ticket_id', $id)
            ->firstOrFail();

        $ticket = Ticket::with(['customer.basicData', 'deliverySupportActivities.deliverySupport'])
            ->where('ticket_id', $id)
            ->firstOrFail();

        $deliveryTypes = $ticket->deliverySupportActivities
            ->pluck('deliverySupport.type')
            ->filter()
            ->unique()
            ->values();

        $pdf = Pdf::loadView('admin.sla.ticket-pdf', [
            'ticket'        => $ticket,
            'sla'           => $sla,
            'events'        => $sla->events->sortBy('event_at'),
            'pauses'        => $sla->pauses,
            'deliveryTypes' => $deliveryTypes,
        ])->setPaper('a4', 'portrait');

        $filename = 'SLA-' . $ticket->ticket_number . '-' . now()->format('Ymd') . '.pdf';

        return $pdf->download($filename);
    }

    // ─── API: SLA REPORT ─────────────────────────────────────────────────────

    public function getReport(Request $request)
    {
        if (!$this->assertAdmin()) return response()->json(['success' => false], 403);

        try {
            $query = TicketSla::with([
                'ticket.customer.basicData',
                'ticket.deliverySupportActivities.deliverySupport',
                'policy',
            ])->whereNotNull('ticket_id');

            // Filter customer
            if ($request->filled('customer_id')) {
                $query->whereHas('ticket', fn($q) => $q->where('customer_id', $request->customer_id));
            }

            // Filter status resolusi
            if ($request->filled('resolution_status')) {
                $query->where('resolution_status', $request->resolution_status);
            }

            // Filter periode (bulan/tahun dari sla_start_at)
            if ($request->filled('month') && $request->filled('year')) {
                $query->whereYear('sla_start_at', $request->year)
                      ->whereMonth('sla_start_at', $request->month);
            } elseif ($request->filled('year')) {
                $query->whereYear('sla_start_at', $request->year);
            }

            $all = $query->latest('sla_start_at')->get();

            // ─── Summary Cards ───────────────────────────────────────────────
            $closed   = $all->whereIn('resolution_status', ['met', 'breached']);
            $active   = $all->whereIn('resolution_status', ['pending', 'paused']);
            $breached = $all->where('resolution_status', 'breached');
            $met      = $all->where('resolution_status', 'met');

            $complianceRate = $closed->count() > 0
                ? round(($met->count() / $closed->count()) * 100, 1)
                : null;

            $avgResponse   = $all->whereNotNull('validation_duration_hours')->avg('validation_duration_hours');
            $avgResolution = $closed->whereNotNull('net_resolution_hours')->avg('net_resolution_hours');

            // ─── Table Rows ──────────────────────────────────────────────────
            $rows = $all->take(200)->map(function ($sla) {
                $ticket   = $sla->ticket;
                $customer = $ticket?->customer;
                $policy   = $sla->policy;

                $deliveryType = $ticket?->deliverySupportActivities
                    ->pluck('deliverySupport.type')
                    ->filter()
                    ->unique()
                    ->implode(', ');

                return [
                    'ticket_id'          => $sla->ticket_id,
                    'ticket_number'      => $ticket?->ticket_number ?? '-',
                    'customer_name'      => $customer?->basicData?->name_1
                        ?? $customer?->customer_code
                        ?? '-',
                    'ticket_type'        => $ticket?->ticket_type ?? '-',
                    'delivery_type'      => $deliveryType ?: '-',
                    'sla_mode'           => $sla->sla_mode ?? 'full',
                    'priority'           => $ticket?->ticket_priority ?? '-',
                    'scale'              => $ticket?->scale ?? '-',
                    'sla_start_at'       => $sla->sla_start_at?->format('d/m/Y H:i'),
                    'resolved_at'        => $sla->resolved_at?->format('d/m/Y H:i'),
                    'response_status'    => $sla->response_status,
                    'resolution_status'  => $sla->resolution_status,
                    'response_target'    => $policy ? (float) $policy->response_hours : null,
                    'resolution_target'  => $policy ? (float) $policy->resolution_hours : null,
                    'response_actual'    => $sla->validation_duration_hours ? (float) $sla->validation_duration_hours : null,
                    'resolution_actual'  => $sla->net_resolution_hours ? (float) $sla->net_resolution_hours : null,
                    'total_waiting'      => (float) $sla->total_waiting_hours,
                    'is_24_hours'        => $policy?->is_24_hours ?? false,
                ];
            });

            return response()->json([
                'success' => true,
                'summary' => [
                    'total'            => $all->count(),
                    'active'           => $active->count(),
                    'met'              => $met->count(),
                    'breached'         => $breached->count(),
                    'compliance_rate'  => $complianceRate,
                    'avg_response'     => $avgResponse ? round($avgResponse, 2) : null,
                    'avg_resolution'   => $avgResolution ? round($avgResolution, 2) : null,
                ],
                'data' => $rows,
            ]);
        } catch (\Exception $e) {
            Log::error('SlaController@getReport', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to load report'], 500);
        }
    }
}

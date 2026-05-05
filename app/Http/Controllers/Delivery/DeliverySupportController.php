<?php

namespace App\Http\Controllers\Delivery;

use App\Enums\RoleId;
use App\Http\Controllers\Controller;
use App\Models\DeliverySupport;
use App\Models\DeliverySupportPhase;
use App\Models\DeliverySupportPlanning;
use App\Models\DeliverySupportActivity;
use App\Models\DeliverySupportViewConfiguration;
use App\Models\DeliveryList;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeliverySupportController extends Controller
{
    /**
     * Display listing of support deliveries
     */
    public function index(Request $request)
    {
        $query = DeliverySupport::with(['client', 'deliveryOwner', 'supportManager']);

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($cq) use ($search) {
                        $cq->whereHas('basicData', function ($bq) use ($search) {
                            $bq->where('name_1', 'like', "%{$search}%");
                        });
                    });
            });
        }

        // Filter by status
        if ($request->filled('status')) {
            if ($request->status === 'completed') {
                $query->where('calculated_progress', '>=', 100);
            } elseif ($request->status === 'in_progress') {
                $query->where('calculated_progress', '>', 0)
                    ->where('calculated_progress', '<', 100);
            } elseif ($request->status === 'not_started') {
                $query->where('calculated_progress', 0);
            }
        }

        // Filter by client
        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        $supports = $query->orderBy('created_at', 'desc')->get();
        $clients = Customer::with('basicData')->get();

        return view('delivery.support.list.index', compact('supports', 'clients'));
    }

    /**
     * Display planning list - all supports with planning progress
     */
    public function planningList(Request $request)
    {
        $query = DeliverySupport::with([
            'client.basicData',
            'deliveryOwner.basicData',
            'supportManager.basicData',
            'phases' => function ($q) {
                $q->where('is_visible', true)->orderBy('order_sequence');
            },
            'activities'
        ]);

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($cq) use ($search) {
                        $cq->whereHas('basicData', function ($bq) use ($search) {
                            $bq->where('name_1', 'like', "%{$search}%");
                        });
                    });
            });
        }

        // Filter by status
        if ($request->filled('status')) {
            if ($request->status === 'completed') {
                $query->where('calculated_progress', '>=', 100);
            } elseif ($request->status === 'in_progress') {
                $query->where('calculated_progress', '>', 0)
                    ->where('calculated_progress', '<', 100);
            } elseif ($request->status === 'not_started') {
                $query->where('calculated_progress', 0);
            }
        }

        $supports = $query->orderBy('created_at', 'desc')->get();

        return view('delivery.support.list.planning-list', compact('supports'));
    }

    /**
     * Show create form
     */
    public function create()
    {
        $clients = Customer::with('basicData')->get();
        $employees = Employee::with('basicData')->where('is_active', true)->get();

        return view('delivery.support.list.create', compact('clients', 'employees'));
    }

    /**
     * Store new support delivery
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'client_id' => 'required|exists:customer,customer_id',
            'type' => 'required|in:AMS,MO,ATS,Project,Internal',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'resolution_estimated' => 'nullable|date',
            'delivery_owner_id' => 'nullable|exists:employee,employee_id',
            'support_manager_id' => 'nullable|exists:employee,employee_id',
            'support_method' => 'nullable|string|max:100',
            'total_mandays' => 'nullable|integer|min:0',
            'approval_date' => 'nullable|date',
            'approval_name' => 'nullable|string|max:255',
        ]);

        DB::beginTransaction();
        try {
            // Create delivery list entry
            $deliveryList = DeliveryList::create([]);

            // Create support
            $support = DeliverySupport::create([
                'id_delivery_list' => $deliveryList->id,
                'client_id' => $validated['client_id'],
                'name' => $validated['name'],
                'type' => $validated['type'],
                'start_date' => $validated['start_date'] ?? null,
                'end_date' => $validated['end_date'] ?? null,
                'resolution_estimated' => $validated['resolution_estimated'] ?? null,
                'delivery_owner_id' => $validated['delivery_owner_id'] ?? null,
                'support_manager_id' => $validated['support_manager_id'] ?? null,
                'support_method' => $validated['support_method'] ?? null,
                'total_mandays' => $validated['total_mandays'] ?? null,
                'approval_date' => $validated['approval_date'] ?? null,
                'approval_name' => $validated['approval_name'] ?? null,
                'created_by_id' => session('user.id'),
            ]);

            // Create default view configuration
            DeliverySupportViewConfiguration::create([
                'delivery_support_id' => $support->id,
                'default_view' => 'table',
            ]);

            // Create default phases (Support phase + Incident group)
            $this->createDefaultPhases($support);

            DB::commit();

            return redirect()
                ->route('delivery.support.show', $support)
                ->with('success', 'Support delivery created successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating support delivery', [
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to create support delivery');
        }
    }

    /**
     * Show support delivery detail
     */
    public function show(DeliverySupport $support)
    {
        $support->load([
            'client.basicData',
            'deliveryOwner.basicData',
            'supportManager.basicData',
            'phases' => function ($q) {
                $q->orderBy('order_sequence');
            },
            'activities.phase',
            'activities.employees.basicData',
            'documents',
            'updates',
            'viewConfiguration'
        ]);

        // Get available employees for assignment
        $employees = Employee::with('basicData')
            ->where('is_active', true)
            ->get();

        // Get clients for modal form
        $clients = Customer::with('basicData')->get();

        return view('delivery.support.list.show', compact('support', 'employees', 'clients'));
    }

    /**
     * Update specific fields via AJAX (section-based editing)
     */
    public function updateField(Request $request, DeliverySupport $support)
    {
        $section = $request->input('section');
        $data = $request->input('data', []);

        try {
            switch ($section) {
                case 'support-info':
                    $sessionUser = session('user');
                    $roleId = $sessionUser['role']['id'] ?? null;
                    $canEditType = in_array($roleId, RoleId::TICKET_MANAGER_GROUP, true);

                    $rules = [
                        'name' => 'required|string|max:255',
                        'client_id' => 'required|exists:customer,customer_id',
                        'support_method' => 'nullable|string|max:100',
                        'start_date' => 'nullable|date',
                        'end_date' => 'nullable|date|after_or_equal:start_date',
                        'resolution_estimated' => 'nullable|date',
                        'total_mandays' => 'nullable|integer|min:0',
                    ];
                    if ($canEditType) {
                        $rules['type'] = 'nullable|in:AMS,MO,ATS,Project,Internal';
                    }

                    $validated = validator($data, $rules)->validate();

                    $updateData = [
                        'name' => $validated['name'],
                        'client_id' => $validated['client_id'],
                        'support_method' => $validated['support_method'] ?? null,
                        'start_date' => $validated['start_date'] ?? null,
                        'end_date' => $validated['end_date'] ?? null,
                        'resolution_estimated' => $validated['resolution_estimated'] ?? null,
                        'total_mandays' => $validated['total_mandays'] ?? null,
                    ];
                    if ($canEditType) {
                        $updateData['type'] = $validated['type'] ?? null;
                    }

                    $support->update($updateData);
                    break;

                case 'approval-info':
                    $validated = validator($data, [
                        'approval_date' => 'nullable|date',
                        'approval_name' => 'nullable|string|max:255',
                    ])->validate();

                    $support->update([
                        'approval_date' => $validated['approval_date'] ?? null,
                        'approval_name' => $validated['approval_name'] ?? null,
                    ]);
                    break;

                case 'team-info':
                    $validated = validator($data, [
                        'delivery_owner_id' => 'nullable|exists:employee,employee_id',
                        'support_manager_id' => 'nullable|exists:employee,employee_id',
                    ])->validate();

                    $support->update([
                        'delivery_owner_id' => $validated['delivery_owner_id'] ?: null,
                        'support_manager_id' => $validated['support_manager_id'] ?: null,
                    ]);
                    break;

                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid section specified'
                    ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Changes saved successfully'
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Delivery support record data is invalid.',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Error updating support field', [
                'support_id' => $support->id,
                'section' => $section,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update'
            ], 500);
        }
    }

    /**
     * Delete support delivery
     */
    public function destroy(DeliverySupport $support)
    {
        try {
            DB::beginTransaction();

            // Delete related records
            $support->phases()->delete();
            $support->activities()->delete();
            $support->documents()->delete();
            $support->updates()->delete();
            $support->viewConfiguration()->delete();

            // Delete support
            $support->delete();

            DB::commit();

            return redirect()
                ->route('delivery.support.index')
                ->with('success', 'Support delivery deleted successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting support delivery', [
                'support_id' => $support->id,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Failed to delete support delivery');
        }
    }

    /**
     * Get support data for planning page (JSON)
     */
    public function getData(DeliverySupport $support)
    {
        $support->load([
            'phases' => function ($q) {
                $q->where('is_active', true)->orderBy('order_sequence');
            },
            'activities.phase',
            'activities.stages',
            'activities.employees.basicData',
        ]);

        return response()->json([
            'success' => true,
            'data' => $support
        ]);
    }

    /**
     * Create default phases for new support
     * Creates "Support" phase with "Incident" group automatically
     */
    protected function createDefaultPhases(DeliverySupport $support)
    {
        try {
            // Create single "Support" phase with 100% weight
            $phase = DeliverySupportPhase::create([
                'delivery_support_id' => $support->id,
                'name' => 'Support',
                'color' => '#3B82F6',
                'weight' => 100,
                'order_sequence' => 1,
                'is_resolution_phase' => true,
                'is_system_default' => true,
                'is_visible' => true,
                'is_active' => true,
                'orientation' => 'vertical',
            ]);

            Log::info('Created Support phase', [
                'support_id' => $support->id,
                'phase_id' => $phase->id,
                'phase_name' => $phase->name
            ]);

            // Create "Incident" group under the phase with 100% weight
            $incidentGroup = DeliverySupportPlanning::create([
                'delivery_support_id' => $support->id,
                'phase_id' => $phase->id,
                'parent_id' => null,
                'name' => 'Incident',
                'group_name' => 'Incident',
                'is_group' => true,
                'level' => 0,
                'order_sequence' => 1,
                'weight' => 100,
                'status' => 'not_started',
                'progress_percentage' => 0,
            ]);

            Log::info('Created Incident group', [
                'support_id' => $support->id,
                'phase_id' => $phase->id,
                'group_id' => $incidentGroup->id,
                'group_name' => $incidentGroup->name
            ]);

            return $phase;

        } catch (\Exception $e) {
            Log::error('Error creating default phases', [
                'support_id' => $support->id,
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);
            throw $e;
        }
    }

    /**
     * Create activity from ticket
     * Activities are created directly under the group (without stages)
     */
    protected function createActivityFromTicket(DeliverySupport $support, Ticket $ticket)
    {
        // Find the default "Support" phase
        $phase = $support->phases()
            ->where('name', 'Support')
            ->where('is_system_default', true)
            ->first();

        if (!$phase) {
            // Fallback: get first active phase
            $phase = $support->phases()->where('is_active', true)->first();
        }

        if (!$phase) {
            Log::warning('No phase found for activity creation', [
                'support_id' => $support->id,
                'ticket_id' => $ticket->ticket_id
            ]);
            return null;
        }

        // Find the "Incident" group
        $group = DeliverySupportPlanning::where('delivery_support_id', $support->id)
            ->where('phase_id', $phase->id)
            ->where('is_group', true)
            ->where('name', 'Incident')
            ->first();

        // Get next order sequence
        $nextOrder = DeliverySupportActivity::where('delivery_support_id', $support->id)
            ->where('delivery_support_phase_id', $phase->id)
            ->max('order_sequence') + 1;

        // Create activity from ticket
        $activity = DeliverySupportActivity::create([
            'delivery_support_id' => $support->id,
            'delivery_support_phase_id' => $phase->id,
            'ticket_id' => $ticket->ticket_id, // Link activity to source ticket
            'stage_id' => null, // No stage - activity directly under group
            'name' => $ticket->description ?? "Ticket #{$ticket->ticket_id}",
            'description' => $ticket->description,
            'order_sequence' => $nextOrder,
            'module' => null,
            'new_issue' => true,
            'object' => null,
            'incident_type' => $ticket->type ?? 'incident',
            'complexity' => $this->mapPriorityToComplexity($ticket->ticket_priority),
            'deliverable' => null,
            'start_date' => $ticket->start_date ?? now(),
            'end_date' => $ticket->end_date,
            'status' => $this->mapTicketStatus($ticket->status),
            'progress_percentage' => 0,
            'weight' => $ticket->man_days ?? 1, // Use man_days as weight or default 1
            'notes' => "Auto-created from Ticket #{$ticket->ticket_id}",
        ]);

        // Also create a planning entry for this activity under the group
        if ($group) {
            DeliverySupportPlanning::create([
                'delivery_support_id' => $support->id,
                'phase_id' => $phase->id,
                'parent_id' => $group->id,
                'activity_id' => $activity->id,
                'name' => $activity->name,
                'is_group' => false,
                'level' => 1,
                'order_sequence' => $nextOrder,
                'start_date' => $activity->start_date,
                'end_date' => $activity->end_date,
                'weight' => $activity->weight,
                'status' => $activity->status,
                'progress_percentage' => 0,
            ]);
        }

        // Assign ticket PIC to activity if exists
        if ($ticket->employee_id) {
            $activity->employees()->attach($ticket->employee_id, [
                'role' => 'lead',
                'allocation_percentage' => 100,
                'is_active' => true,
                'assigned_date' => now(),
            ]);
        }

        Log::info('Activity created from ticket', [
            'support_id' => $support->id,
            'activity_id' => $activity->id,
            'ticket_id' => $ticket->ticket_id
        ]);

        return $activity;
    }

    /**
     * Map ticket priority to complexity
     */
    protected function mapPriorityToComplexity(?string $priority): string
    {
        return match (strtolower($priority ?? '')) {
            'high' => 'complex',
            'medium' => 'medium',
            'low' => 'simple',
            default => 'medium'
        };
    }

    /**
     * Map ticket status to activity status
     */
    protected function mapTicketStatus(?string $status): string
    {
        return match (strtolower($status ?? '')) {
            'open' => 'not_started',
            'in_progress' => 'in_progress',
            'hold' => 'on_hold',
            'closed' => 'completed',
            'cancel' => 'completed',
            default => 'not_started'
        };
    }

    /**
     * Assign ticket to support - creates activity automatically
     */
    public function assignTicket(Request $request, DeliverySupport $support)
    {
        $validated = $request->validate([
            'ticket_id' => 'required|exists:ticket,ticket_id',
        ]);

        $ticket = Ticket::find($validated['ticket_id']);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found'
            ], 404);
        }

        DB::beginTransaction();
        try {
            // Create activity from ticket
            $activity = $this->createActivityFromTicket($support, $ticket);

            if (!$activity) {
                throw new \Exception('Failed to create activity from ticket');
            }

            // Update support progress
            $support->updateCalculatedProgress();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Ticket assigned successfully',
                'data' => [
                    'activity_id' => $activity->id,
                    'activity_name' => $activity->name
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error assigning ticket to support', [
                'support_id' => $support->id,
                'ticket_id' => $validated['ticket_id'],
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to assign ticket'
            ], 500);
        }
    }

    /**
     * Search delivery supports for dropdown/autocomplete
     */
    public function search(Request $request)
    {
        try {
            $query = DeliverySupport::with(['client.basicData']);

            // Filter by client if provided
            if ($request->filled('client_id')) {
                $query->where('client_id', $request->client_id);
            }

            // Search by name
            if ($request->filled('q')) {
                $search = $request->q;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhereHas('client', function ($cq) use ($search) {
                            $cq->whereHas('basicData', function ($bq) use ($search) {
                                $bq->where('name_1', 'like', "%{$search}%");
                            });
                        });
                });
            }

            $supports = $query->orderBy('created_at', 'desc')
                ->limit(50)
                ->get()
                ->map(function ($support) {
                    return [
                        'id' => $support->id,
                        'name' => $support->name,
                        'type' => $support->type,
                        'client_id' => $support->client_id,
                        'client_name' => $support->client?->basicData?->name_1 ?? 'Unknown',
                        'progress' => $support->calculated_progress,
                        'created_at' => $support->created_at?->format('Y-m-d'),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $supports
            ]);

        } catch (\Exception $e) {
            Log::error('Error searching delivery supports', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to search delivery supports'
            ], 500);
        }
    }

    /**
     * Get statistics for delivery supports
     */
    public function getStatistics(Request $request)
    {
        try {
            $query = DeliverySupport::query();

            if ($request->filled('client_id')) {
                $query->where('client_id', $request->client_id);
            }

            $total = $query->count();
            $completed = (clone $query)->where('calculated_progress', '>=', 100)->count();
            $inProgress = (clone $query)->where('calculated_progress', '>', 0)
                ->where('calculated_progress', '<', 100)->count();
            $notStarted = (clone $query)->where('calculated_progress', 0)->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total' => $total,
                    'completed' => $completed,
                    'in_progress' => $inProgress,
                    'not_started' => $notStarted,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get statistics'
            ], 500);
        }
    }
}

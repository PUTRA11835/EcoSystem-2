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
use App\Services\OneDriveService;
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
            'co_pm_id' => 'nullable|exists:employee,employee_id',
            'support_admin_id' => 'nullable|exists:employee,employee_id',
            'sales_id' => 'nullable|exists:employee,employee_id',
            'support_method' => 'nullable|string|max:100',
            'total_mandays' => 'nullable|integer|min:0',
            'approval_date' => 'nullable|date',
            'approval_name' => 'nullable|string|max:255',
            'service_window_start' => 'nullable|date_format:H:i',
            'service_window_end' => 'nullable|date_format:H:i|after_or_equal:service_window_start',
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
                'co_pm_id' => $validated['co_pm_id'] ?? null,
                'support_admin_id' => $validated['support_admin_id'] ?? null,
                'sales_id' => $validated['sales_id'] ?? null,
                'support_method' => $validated['support_method'] ?? null,
                'total_mandays' => $validated['total_mandays'] ?? null,
                'approval_date' => $validated['approval_date'] ?? null,
                'approval_name' => $validated['approval_name'] ?? null,
                'service_window_start' => $validated['service_window_start'] ?? null,
                'service_window_end' => $validated['service_window_end'] ?? null,
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
     * Show edit form (mirror of create, pre-filled).
     */
    public function edit(DeliverySupport $support)
    {
        $support->load([
            'client.basicData',
            'deliveryOwner.basicData',
            'supportManager.basicData',
            'coPm.basicData',
            'supportAdmin.basicData',
            'sales.basicData',
        ]);

        $clients   = Customer::with('basicData')->get();
        $employees = Employee::with('basicData')->where('is_active', true)->get();

        return view('delivery.support.list.edit', compact('support', 'clients', 'employees'));
    }

    /**
     * Full update (PUT). Updates every field that the create form exposes.
     * Type changes still gated to TICKET_MANAGER_GROUP (consistent with updateField).
     */
    public function update(Request $request, DeliverySupport $support)
    {
        $sessionUser = session('user');
        $roleId = $sessionUser['role']['id'] ?? null;
        $canEditType = in_array($roleId, RoleId::TICKET_MANAGER_GROUP, true);

        $rules = [
            'name'                 => 'required|string|max:255',
            'client_id'            => 'required|exists:customer,customer_id',
            'start_date'           => 'nullable|date',
            'end_date'             => 'nullable|date|after_or_equal:start_date',
            'resolution_estimated' => 'nullable|date',
            'delivery_owner_id'    => 'nullable|exists:employee,employee_id',
            'support_manager_id'   => 'nullable|exists:employee,employee_id',
            'co_pm_id'             => 'nullable|exists:employee,employee_id',
            'support_admin_id'     => 'nullable|exists:employee,employee_id',
            'sales_id'             => 'nullable|exists:employee,employee_id',
            'support_method'       => 'nullable|string|max:100',
            'total_mandays'        => 'nullable|integer|min:0',
            'approval_date'        => 'nullable|date',
            'approval_name'        => 'nullable|string|max:255',
            'service_window_start' => 'nullable|date_format:H:i',
            'service_window_end'   => 'nullable|date_format:H:i|after_or_equal:service_window_start',
        ];
        if ($canEditType) {
            $rules['type'] = 'required|in:AMS,MO,ATS,Project,Internal';
        }

        $validated = $request->validate($rules);

        try {
            $updateData = [
                'name'                 => $validated['name'],
                'client_id'            => $validated['client_id'],
                'start_date'           => $validated['start_date']           ?? null,
                'end_date'             => $validated['end_date']             ?? null,
                'resolution_estimated' => $validated['resolution_estimated'] ?? null,
                'delivery_owner_id'    => $validated['delivery_owner_id']    ?: null,
                'support_manager_id'   => $validated['support_manager_id']   ?: null,
                'co_pm_id'             => $validated['co_pm_id']             ?: null,
                'support_admin_id'     => $validated['support_admin_id']     ?: null,
                'sales_id'             => $validated['sales_id']             ?: null,
                'support_method'       => $validated['support_method']       ?? null,
                'total_mandays'        => $validated['total_mandays']        ?? null,
                'approval_date'        => $validated['approval_date']        ?? null,
                'approval_name'        => $validated['approval_name']        ?? null,
                'service_window_start' => $validated['service_window_start'] ?? null,
                'service_window_end'   => $validated['service_window_end']   ?? null,
            ];
            if ($canEditType) {
                $updateData['type'] = $validated['type'];
            }

            $support->update($updateData);

            return redirect()
                ->route('delivery.support.show', $support)
                ->with('success', 'Support delivery updated successfully');

        } catch (\Exception $e) {
            Log::error('Error updating support delivery', [
                'support_id' => $support->id,
                'error'      => $e->getMessage(),
                'error_at'   => $e->getFile() . ':' . $e->getLine(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to update support delivery');
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
            'coPm.basicData',
            'supportAdmin.basicData',
            'sales.basicData',
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
                        'service_window_start' => 'nullable|date_format:H:i',
                        'service_window_end' => 'nullable|date_format:H:i|after_or_equal:service_window_start',
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
                        'service_window_start' => $validated['service_window_start'] ?? null,
                        'service_window_end' => $validated['service_window_end'] ?? null,
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
                        'delivery_owner_id'  => 'nullable|exists:employee,employee_id',
                        'support_manager_id' => 'nullable|exists:employee,employee_id',
                        'co_pm_id'           => 'nullable|exists:employee,employee_id',
                        'support_admin_id'   => 'nullable|exists:employee,employee_id',
                        'sales_id'           => 'nullable|exists:employee,employee_id',
                    ])->validate();

                    $support->update([
                        'delivery_owner_id'  => $validated['delivery_owner_id']  ?: null,
                        'support_manager_id' => $validated['support_manager_id'] ?: null,
                        'co_pm_id'           => $validated['co_pm_id']           ?: null,
                        'support_admin_id'   => $validated['support_admin_id']   ?: null,
                        'sales_id'           => $validated['sales_id']           ?: null,
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
        if ($ticket->ticket_lead_id) {
            $activity->employees()->attach($ticket->ticket_lead_id, [
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

    /**
     * Generate (or re-generate) an OneDrive folder + anonymous edit link for a support delivery.
     * Idempotent: if a folder already exists, only the share link is recreated.
     */
    public function generateFolder(Request $request, DeliverySupport $support)
    {
        $request->validate([
            'folder_name' => ['nullable', 'string', 'max:255', 'not_regex:~[\\\\/:*?"<>|]~'],
        ], [
            'folder_name.not_regex' => 'Folder name cannot contain: \\ / : * ? " < > |',
        ]);

        try {
            $oneDrive   = new OneDriveService();
            $folderName = trim($request->input('folder_name') ?: ($support->name ?? 'Support-' . $support->id));

            if ($support->onedrive_folder_id) {
                // Folder already exists — just recreate the share link (idempotent + upgrades to edit)
                $shareUrl = $oneDrive->createAnonymousLink($support->onedrive_folder_id);
                $support->update(['onedrive_folder_url' => $shareUrl]);
            } else {
                // First time — create folder then share link
                $folderId = $oneDrive->createFolder($folderName);
                $shareUrl = $oneDrive->createAnonymousLink($folderId);
                $support->update([
                    'onedrive_folder_id'  => $folderId,
                    'onedrive_folder_url' => $shareUrl,
                ]);
            }

            return response()->json([
                'success'    => true,
                'message'    => 'OneDrive folder ready.',
                'folder_url' => $shareUrl,
            ]);

        } catch (\Exception $e) {
            Log::error('OneDrive generateFolder failed (support)', [
                'support_id' => $support->id,
                'error'      => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate OneDrive folder: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function deleteFolder(Request $request, DeliverySupport $support)
    {
        if (!$support->onedrive_folder_id) {
            return response()->json(['success' => false, 'message' => 'No folder to delete.'], 400);
        }

        try {
            (new OneDriveService())->deleteFolder($support->onedrive_folder_id);
            $support->update(['onedrive_folder_id' => null, 'onedrive_folder_url' => null]);
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('OneDrive deleteFolder failed (support)', [
                'support_id' => $support->id,
                'error'      => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Generate a Customer Deliverable sub-folder inside:
     *   Delivery Support/Customer Deliverable/{padded_id} {CUSTOMER_NAME}/{subfolder_name}
     *
     * Logic:
     *  1. Build customer folder name from client_id + basicData.name_1
     *  2. Find or create the customer folder inside the root "Customer Deliverable" path
     *  3. Create the sub-folder (user-supplied name) inside the customer folder
     *  4. Generate anonymous share link for the sub-folder
     *  5. Save IDs/URL to delivery_support
     */
    public function generateCustomerDeliverableFolder(Request $request, DeliverySupport $support)
    {
        $request->validate([
            'subfolder_name' => ['required', 'string', 'max:255', 'not_regex:~[\\\\/:*?"<>|]~'],
        ], [
            'subfolder_name.required'  => 'Sub-folder name is required.',
            'subfolder_name.not_regex' => 'Sub-folder name cannot contain: \\ / : * ? " < > |',
        ]);

        $support->load('client.basicData');

        $client = $support->client;
        if (!$client || !$client->basicData) {
            return response()->json([
                'success' => false,
                'message' => 'Client data not found. Please assign a client to this support first.',
            ], 422);
        }

        // "001 PERTAMINA" — padded client_id + uppercase name
        $customerFolderName = str_pad($support->client_id, 3, '0', STR_PAD_LEFT)
            . ' ' . strtoupper($client->basicData->name_1);

        $rootPath    = config('services.microsoft_graph.customer_deliverable_path', 'DELIVERY SUPPORT/CUSTOMER DELIVERABLE');
        $subfolderName = trim($request->input('subfolder_name'));

        try {
            $oneDrive = new OneDriveService();

            // Step 1: find or create the customer folder
            $customerFolderId = $oneDrive->findOrCreateFolderInPath($rootPath, $customerFolderName);

            // Step 2: create the sub-folder inside the customer folder
            $subFolderId = $oneDrive->createSubFolder($customerFolderId, $subfolderName);

            // Step 3: generate anonymous share link for the sub-folder
            $shareUrl = $oneDrive->createAnonymousLink($subFolderId);

            // Step 4: persist
            $support->update([
                'onedrive_deliverable_folder_id'  => $subFolderId,
                'onedrive_deliverable_folder_url' => $shareUrl,
            ]);

            return response()->json([
                'success'         => true,
                'message'         => 'Customer deliverable folder created successfully.',
                'folder_url'      => $shareUrl,
                'customer_folder' => $customerFolderName,
                'subfolder'       => $subfolderName,
            ]);

        } catch (\Exception $e) {
            Log::error('OneDrive generateCustomerDeliverableFolder failed', [
                'support_id'      => $support->id,
                'customer_folder' => $customerFolderName ?? null,
                'subfolder'       => $subfolderName,
                'error'           => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate folder: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Return list of sub-folders inside the customer's deliverable folder from OneDrive.
     */
    public function getDeliverableSubfolders(DeliverySupport $support)
    {
        $support->load('client.basicData');
        $client = $support->client;

        if (!$client || !$client->basicData) {
            return response()->json(['subfolders' => []]);
        }

        $customerFolderName = str_pad($support->client_id, 3, '0', STR_PAD_LEFT)
            . ' ' . strtoupper($client->basicData->name_1);
        $rootPath           = config('services.microsoft_graph.customer_deliverable_path', 'DELIVERY SUPPORT/CUSTOMER DELIVERABLE');
        $customerFolderPath = $rootPath . '/' . $customerFolderName;

        try {
            $oneDrive   = new OneDriveService();
            $subfolders = $oneDrive->listFolderChildrenByPath($customerFolderPath);
            return response()->json(['subfolders' => $subfolders, 'customer_folder' => $customerFolderName]);
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            // Berikan pesan error yang lebih jelas jika akun OneDrive service tidak ditemukan
            if (str_contains($message, 'User not found') || str_contains($message, 'ResourceNotFound')) {
                $message = 'OneDrive service account tidak ditemukan. Hubungi administrator untuk memeriksa konfigurasi MS_SENDER_EMAIL di Azure AD.';
            }
            Log::warning('getDeliverableSubfolders: OneDrive error', [
                'support_id' => $support->id,
                'path'       => $customerFolderPath,
                'error'      => $e->getMessage(),
            ]);
            return response()->json(['subfolders' => [], 'error' => $message]);
        }
    }

    /**
     * Generate (or re-use existing) anonymous share link for a given OneDrive folder ID.
     * Link type 'edit' = anyone with link can view, upload, and edit.
     */
    public function getDeliverableShareLink(Request $request, DeliverySupport $support)
    {
        $request->validate(['folder_id' => ['required', 'string', 'max:500']]);

        try {
            $oneDrive = new OneDriveService();
            $url      = $oneDrive->createAnonymousLink($request->input('folder_id'), 'edit');
            return response()->json(['success' => true, 'url' => $url]);
        } catch (\Throwable $e) {
            Log::error('getDeliverableShareLink failed', [
                'support_id' => $support->id,
                'folder_id'  => $request->input('folder_id'),
                'error'      => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Clear the stored Customer Deliverable folder reference (does NOT delete from OneDrive).
     */
    public function deleteCustomerDeliverableFolder(Request $request, DeliverySupport $support)
    {
        $support->update([
            'onedrive_deliverable_folder_id'  => null,
            'onedrive_deliverable_folder_url' => null,
        ]);
        return response()->json(['success' => true]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\RoleId;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Services\OneDriveService;
use App\Services\StagingTicketService;
use App\Services\TicketNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TicketController extends Controller
{
    public function __construct(private readonly TicketNumberService $ticketNumbers) {}

    /**
     * Get user info for history
     */
    private function getUserInfo($sessionUser)
    {
        $roleName = match($sessionUser['role']['id']) {
            RoleId::ADMIN->value    => 'Admin',
            RoleId::EMPLOYEE->value => 'Employee',
            RoleId::INTERNSHIP->value => 'Customer',
            default                 => 'Unknown'
        };
        
        $userName = $sessionUser['name'] ?? $sessionUser['email'] ?? 'Unknown User';
        
        return [
            'id' => $sessionUser['id'],
            'name' => $userName,
            'role' => strtolower($roleName)
        ];
    }

    /**
     * Check if employee is qualified (DSM)
     */
    private function isEmployeeQualified($employeeId)
    {
        $qualification = DB::table('employee_qualification')
            ->where('employee_id', $employeeId)
            ->first();
        
        return $qualification && $qualification->dsm == 1;
    }

    /**
     * Lightweight endpoint untuk polling — kembalikan timestamp update terakhir dari DB lokal.
     * Tidak menyentuh Graph API, aman dipanggil dari browser setiap 30 detik.
     */
    public function generateFolder(Request $request, $id)
    {
        $ticket = Ticket::where('ticket_id', $id)->firstOrFail();

        $request->validate([
            'folder_name' => ['nullable', 'string', 'max:255', 'not_regex:~[\\\\/:*?"<>|]~'],
        ]);

        $folderName   = trim($request->input('folder_name') ?: ($ticket->ticket_number . ' - ' . $ticket->description));
        $parentFolder = config('services.microsoft_graph.ticket_parent_folder', 'TICKETING');
        $oneDrive     = new OneDriveService();

        try {
            if ($ticket->onedrive_folder_id) {
                $shareUrl = $oneDrive->createAnonymousLink($ticket->onedrive_folder_id);
                $ticket->update(['onedrive_folder_url' => $shareUrl]);
            } else {
                $folderId = $oneDrive->createFolderInPath($folderName, $parentFolder);
                $shareUrl = $oneDrive->createAnonymousLink($folderId);
                $ticket->update([
                    'onedrive_folder_id'  => $folderId,
                    'onedrive_folder_url' => $shareUrl,
                ]);
            }

            return response()->json(['success' => true, 'folder_url' => $shareUrl]);
        } catch (\Throwable $e) {
            Log::error('Ticket generateFolder failed', ['ticket_id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function deleteFolder(Request $request, $id)
    {
        $ticket = Ticket::where('ticket_id', $id)->firstOrFail();

        if (!$ticket->onedrive_folder_id) {
            return response()->json(['success' => false, 'message' => 'No folder to delete.'], 400);
        }

        try {
            (new OneDriveService())->deleteFolder($ticket->onedrive_folder_id);
            $ticket->update(['onedrive_folder_id' => null, 'onedrive_folder_url' => null]);
            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            Log::error('Ticket deleteFolder failed', ['ticket_id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function latestUpdate()
    {
        $latest = DB::table('ticket')
            ->whereNull('deleted_at')
            ->max('last_message_at');

        return response()->json(['latest_update' => $latest]);
    }

    /**
     * Display a listing of tickets
     */
    public function index(Request $request)
    {
        try {
            $sessionUser = session('user');
        
            if (!$sessionUser) {
                Log::error('No user in session');
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized - Please login first'
                ], 401);
            }
            
            Log::info('Session user found', [
                'user_id' => $sessionUser['id'],
                'user_type' => $sessionUser['type'],
                'role_id' => $sessionUser['role']['id']
            ]);

            $filterUnassigned = $request->boolean('unassigned');

            // Admin: bisa lihat semua ticket, atau filter unassigned jika ?unassigned=1
            if ($sessionUser['role']['id'] === RoleId::ADMIN->value) {
                Log::info('Admin viewing tickets', ['unassigned' => $filterUnassigned]);

                $query = Ticket::with(['customer.basicData', 'employee.basicData', 'members.basicData'])
                    ->orderByRaw('COALESCE(last_message_at, created_at) DESC');
                if ($filterUnassigned) {
                    $query->whereNull('employee_id');
                }
                $tickets = $query->get();

            // Employee: tampilkan ticket unassigned (belum ada PIC) — frontend /api/tickets maps to "Unassign" tab
            } elseif ($sessionUser['role']['id'] === RoleId::EMPLOYEE->value) {
                Log::info('Employee viewing unassigned tickets');

                $tickets = Ticket::with(['customer.basicData', 'employee.basicData', 'members.basicData'])
                    ->whereNull('employee_id')
                    ->orderByRaw('COALESCE(last_message_at, created_at) DESC')
                    ->get();

            // Helpdesk, RPMO, Head of Project, Head of Support: lihat semua ticket, atau filter unassigned jika ?unassigned=1
            } elseif (in_array($sessionUser['role']['id'], array_merge(RoleId::HEAD_GROUP, RoleId::HELPDESK_GROUP), true)) {
                Log::info('Staff viewing tickets', ['role_id' => $sessionUser['role']['id'], 'unassigned' => $filterUnassigned]);

                $query = Ticket::with(['customer.basicData', 'employee.basicData', 'members.basicData'])
                    ->orderByRaw('COALESCE(last_message_at, created_at) DESC');
                if ($filterUnassigned) {
                    $query->whereNull('employee_id');
                }
                $tickets = $query->get();

            // Delivery Support Manager (role 20): lihat hanya tiket dari delivery support yang mereka kelola
            } elseif ($sessionUser['role']['id'] === RoleId::SUPPORT_MANAGER->value) {
                $employeeId = DB::table('auth_users')
                    ->where('id', $sessionUser['id'])
                    ->value('employee_id');

                Log::info('Support Manager viewing delivery tickets', ['employee_id' => $employeeId]);

                $ticketIds = $employeeId
                    ? DB::table('delivery_support_activities')
                        ->join('delivery_support', 'delivery_support.id', '=', 'delivery_support_activities.delivery_support_id')
                        ->where('delivery_support.support_manager_id', $employeeId)
                        ->whereNotNull('delivery_support_activities.ticket_id')
                        ->pluck('delivery_support_activities.ticket_id')
                        ->unique()
                    : collect();

                $tickets = Ticket::with(['customer.basicData', 'employee.basicData', 'members.basicData'])
                    ->whereIn('ticket_id', $ticketIds)
                    ->orderByRaw('COALESCE(last_message_at, created_at) DESC')
                    ->get();

            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied'
                ], 403);
            }

            Log::info('Tickets fetched', ['count' => $tickets->count()]);

            // Weighted average progress per tiket dari consultant_mandays_detail
            $ticketIds   = $tickets->pluck('ticket_id')->toArray();
            $progressMap = \App\Http\Controllers\ConsultantWorkloadController::progressMapForTickets($ticketIds);

            // ✅ Transform data untuk frontend
            $ticketsData = $tickets->map(function($ticket) use ($progressMap) {
                $allProgress = $progressMap[$ticket->ticket_id]
                    ?? (float) ($ticket->progress_percentage ?? 0);

                // ✅ Hitung pending confirmations untuk admin
                $pendingCount = DB::table('ticket_confirmation')
                    ->where('ticket_id', $ticket->ticket_id)
                    ->where('status', 'pending')
                    ->count();
                
                // ✅ Get pending confirmation detail (untuk status waiting employee)
                $pendingConfirmation = DB::table('ticket_confirmation')
                    ->where('ticket_id', $ticket->ticket_id)
                    ->where('status', 'pending')
                    ->first();
                
                return [
                    'ticket_id' => $ticket->ticket_id,
                    'ticket_number' => $ticket->ticket_number,
                    'customer_id' => $ticket->customer_id,
                    'employee_id' => $ticket->employee_id,
                    'description' => $ticket->description,
                    'ticket_priority' => $ticket->ticket_priority,
                    'ticket_type' => $ticket->ticket_type,
                    'jarvies_status' => $ticket->jarvies_status,
                    'status' => $ticket->status,
                    'channel' => $ticket->channel,
                    'email_thread_id' => $ticket->email_thread_id,
                    'folder' => $ticket->folder,
                    'file_log' => $ticket->file_log,
                    'start_date' => $ticket->start_date,
                    'end_date' => $ticket->end_date,
                    'man_days' => $ticket->man_days,
                    'progress_percentage' => (float) ($ticket->progress_percentage ?? 0),
                    'all_consultant_progress' => $allProgress,
                    'wait_close' => $ticket->wait_close,
                    'last_message_at' => $ticket->last_message_at,
                    'last_customer_reply_at' => $ticket->last_customer_reply_at,
                    'last_agent_reply_at' => $ticket->last_agent_reply_at,
                    'last_internal_note_at'        => $ticket->last_internal_note_at,
                    'last_internal_note_sender_id' => $ticket->last_internal_note_sender_id,
                    'customer' => $ticket->customer ? [
                        'customer_id' => $ticket->customer->customer_id,
                        'customer_name' => $ticket->customer->basicData->name_1 ?? $ticket->customer->email,
                        'customer_code' => $ticket->customer->customer_code,
                    ] : null,
                    'employee' => $ticket->employee ? [
                        'employee_id' => $ticket->employee->employee_id,
                        'employee_name' => $ticket->employee->basicData->first_name ?? 'Unknown',
                    ] : null,
                    'members' => $ticket->members->map(function($member) {
                        return [
                            'employee_id' => $member->employee_id,
                            'employee_name' => $member->basicData->first_name ?? 'Unknown',
                        ];
                    }),
                    'member_ids' => $ticket->members->pluck('employee_id')->toArray(),
                    'pending_confirmations_count' => $pendingCount,
                    'confirmation' => $pendingConfirmation ? [
                        'confirmation_id' => $pendingConfirmation->confirmation_id,
                        'employee_id' => $pendingConfirmation->employee_id,
                        'status' => $pendingConfirmation->status,
                    ] : null,
                    'created_at' => $ticket->created_at,
                    'updated_at' => $ticket->updated_at,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $ticketsData,
                'message' => 'Tickets retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching tickets:', [
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve tickets',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $user   = session('user');
        $roleId = $user['role']['id'];

        // ── Admin (role 1) → langsung buat ticket (bypass staging) ────────────
        if ($roleId === RoleId::ADMIN->value) {
            $validated = $request->validate([
                'description'     => 'required|string',
                'ticket_priority' => 'required|in:Very High,High,Medium,Low',
                'ticket_type'     => 'nullable|string|in:Incident,Service Request,Change Request,Consult',
                'customer_id'     => 'required|exists:customer,customer_id',
            ]);

            $validated['status']         = 'open';
            $validated['jarvies_status'] = 'in process';

            try {
                $ticket = DB::transaction(function () use ($validated) {
                    $validated['ticket_number'] = $this->ticketNumbers->generate();
                    return Ticket::create($validated);
                });

                return response()->json([
                    'success' => true,
                    'message' => 'Ticket created successfully',
                    'data'    => $ticket,
                ], 201);
            } catch (\Exception $e) {
                Log::error('TicketController@store (admin): gagal', [
                    'error' => $e->getMessage(),
                    'error_at' => $e->getFile() . ':' . $e->getLine(),
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create ticket',
                ], 500);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'Unauthorized to create tickets',
        ], 403);
    }

    /**
     * External API: create ticket via query string
     * URL: /api/external/tickets/create?description=...&ticket_priority=...&customer_code=...&type=...
     */
    public function storeExternalQuery(Request $request)
    {
        $payload = $request->query();

        $validator = Validator::make($payload, [
            'description' => 'required|string',
            'ticket_priority' => 'nullable|in:Very High,High,Medium,Low',
            'customer_id' => 'required_without_all:customer_code,external_number|exists:customer,customer_id',
            'customer_code' => 'required_without_all:customer_id,external_number|exists:customer,customer_code',
            'external_number' => 'nullable|customer_id,customer_code|exists:customer_basic_data,external_number',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket creation data is invalid. Provide a valid customer_id, customer_code, or external_number.',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $customerId = $payload['customer_id'] ?? null;

            if (!$customerId && !empty($payload['customer_code'])) {
                $customerId = DB::table('customer')
                    ->where('customer_code', $payload['customer_code'])
                    ->value('customer_id');
            }

            if (!$customerId && !empty($payload['external_number'])) {
                $customerId = DB::table('customer_basic_data')
                    ->where('external_number', $payload['external_number'])
                    ->value('customer_id');
            }

            if (!$customerId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer not found'
                ], 404);
            }

            $ticket = DB::transaction(function () use ($customerId, $payload) {
                return Ticket::create([
                    'customer_id'     => $customerId,
                    'description'     => $payload['description'] ?? null,
                    'ticket_priority' => null,
                    'status'          => 'open',
                    'jarvies_status'  => 'in process',
                    'ticket_number'   => $this->ticketNumbers->generate(),
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Ticket created successfully',
                'data' => $ticket
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating external ticket (query):', [
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create ticket'
            ], 500);
        }
    }

    /**
     * External API: get all tickets (no auth)
     * URL: /api/external/tickets
     */
    public function externalIndex()
    {
        try {
            $tickets = Ticket::orderBy('created_at', 'desc')
                ->get([
                    'ticket_id',
                    'ticket_number',
                    'customer_id',
                    'employee_id',
                    'description',
                    'ticket_priority',
                    'jarvies_status',
                    'status',
                    'start_date',
                    'end_date',
                    'man_days',
                    'created_at',
                    'updated_at',
                ]);

            return response()->json([
                'success' => true,
                'data' => $tickets,
                'message' => 'Tickets retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching external tickets:', [
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve tickets',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    /**
     * Get my tickets (for customer and employee)
     */
    public function myTickets()
    {
        try {
            $sessionUser = session('user');

            if (!$sessionUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            Log::info('My Tickets - Session User:', $sessionUser);

            // Employee / Helpdesk: tampilkan tiket dimana mereka PIC atau member
            if (in_array($sessionUser['role']['id'], array_merge([RoleId::EMPLOYEE->value], RoleId::HELPDESK_GROUP), true)) {
                $employeeId = $sessionUser['id'];

                Log::info('My Tickets - Filtering for employee/helpdesk', ['employee_id' => $employeeId]);

                // Ticket yang employee handle sebagai PIC atau member
                $tickets = Ticket::with(['customer.basicData', 'employee.basicData', 'members.basicData'])
                    ->where(function($query) use ($employeeId) {
                        $query->where('ticket.employee_id', $employeeId)
                            ->orWhereHas('members', function($inner) use ($employeeId) {
                                $inner->where('ticket_member.employee_id', $employeeId);
                            });
                    })
                    ->orderByRaw('COALESCE(ticket.last_message_at, ticket.created_at) DESC')
                    ->get();

            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid role'
                ], 403);
            }

            Log::info('My Tickets fetched', ['count' => $tickets->count()]);

            // Weighted average progress per tiket dari consultant_mandays_detail
            $myTicketIds   = $tickets->pluck('ticket_id')->toArray();
            $myProgressMap = \App\Http\Controllers\ConsultantWorkloadController::progressMapForTickets($myTicketIds);

            // ✅ Transform data dengan confirmation info
            $ticketsData = $tickets->map(function($ticket) use ($myProgressMap) {
                $myAllProgress = $myProgressMap[$ticket->ticket_id]
                    ?? (float) ($ticket->progress_percentage ?? 0);

                // ✅ Hitung pending confirmations
                $pendingCount = DB::table('ticket_confirmation')
                    ->where('ticket_id', $ticket->ticket_id)
                    ->where('status', 'pending')
                    ->count();

                // ✅ Get pending confirmation detail
                $pendingConfirmation = DB::table('ticket_confirmation')
                    ->where('ticket_id', $ticket->ticket_id)
                    ->where('status', 'pending')
                    ->first();

                return [
                    'ticket_id' => $ticket->ticket_id,
                    'ticket_number' => $ticket->ticket_number,
                    'customer_id' => $ticket->customer_id,
                    'employee_id' => $ticket->employee_id,
                    'description' => $ticket->description,
                    'ticket_priority' => $ticket->ticket_priority,
                    'ticket_type' => $ticket->ticket_type,
                    'jarvies_status' => $ticket->jarvies_status,
                    'status' => $ticket->status,
                    'channel' => $ticket->channel,
                    'email_thread_id' => $ticket->email_thread_id,
                    'folder' => $ticket->folder,
                    'file_log' => $ticket->file_log,
                    'start_date' => $ticket->start_date,
                    'end_date' => $ticket->end_date,
                    'man_days' => $ticket->man_days,
                    'progress_percentage' => (float) ($ticket->progress_percentage ?? 0),
                    'all_consultant_progress' => $myAllProgress,
                    'wait_close' => $ticket->wait_close,
                    'last_message_at' => $ticket->last_message_at,
                    'last_customer_reply_at' => $ticket->last_customer_reply_at,
                    'last_agent_reply_at' => $ticket->last_agent_reply_at,
                    'last_internal_note_at'        => $ticket->last_internal_note_at,
                    'last_internal_note_sender_id' => $ticket->last_internal_note_sender_id,
                    'customer' => $ticket->customer ? [
                        'customer_id' => $ticket->customer->customer_id,
                        'customer_name' => $ticket->customer->basicData->name_1 ?? $ticket->customer->email,
                        'customer_code' => $ticket->customer->customer_code,
                    ] : null,
                    'employee' => $ticket->employee ? [
                        'employee_id' => $ticket->employee->employee_id,
                        'employee_name' => $ticket->employee->basicData->first_name ?? 'Unknown',
                    ] : null,
                    'members' => $ticket->members->map(function($member) {
                        return [
                            'employee_id' => $member->employee_id,
                            'employee_name' => $member->basicData->first_name ?? 'Unknown',
                        ];
                    }),
                    'member_ids' => $ticket->members->pluck('employee_id')->toArray(),
                    'pending_confirmations_count' => $pendingCount,
                    'confirmation' => $pendingConfirmation ? [
                        'confirmation_id' => $pendingConfirmation->confirmation_id,
                        'employee_id' => $pendingConfirmation->employee_id,
                        'status' => $pendingConfirmation->status,
                    ] : null,
                    'created_at' => $ticket->created_at,
                    'updated_at' => $ticket->updated_at,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $ticketsData,
                'message' => 'My tickets retrieved successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error fetching my tickets:', [
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve my tickets',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Take ticket (for employee with DSM qualification)
     */
    public function takeTicket(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'man_days' => 'required|numeric|min:0|max:9999.99',
            'member_ids' => 'nullable|array',
            'member_ids.*' => 'exists:employee,employee_id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket assignment data is invalid. man_days is required and member IDs must be valid.',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $sessionUser = session('user');
            
            if (!$sessionUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }
            
            // Pastikan user adalah employee
            if ($sessionUser['role']['id'] !== RoleId::EMPLOYEE->value) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only employees can take tickets'
                ], 403);
            }

            $employeeId = $sessionUser['id'];
            
            // Cek DSM qualification
            if (!$this->isEmployeeQualified($employeeId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not qualified for this section. DSM qualification required.'
                ], 403);
            }

            $ticket = Ticket::findOrFail($id);
            
            // Cek apakah ticket sudah diambil atau ada pending confirmation
            if ($ticket->employee_id !== null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket has already been taken'
                ], 400);
            }

            $existingConfirmation = DB::table('ticket_confirmation')
                ->where('ticket_id', $id)
                ->where('status', 'pending')
                ->exists();

            if ($existingConfirmation) {
                return response()->json([
                    'success' => false,
                    'message' => 'This ticket already has a pending confirmation request'
                ], 400);
            }

            // Buat confirmation request
            DB::table('ticket_confirmation')->insert([
                'ticket_id' => $id,
                'employee_id' => $employeeId,
                'member_ids' => json_encode($request->member_ids ?? []),
                'man_days' => $request->man_days,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Ticket assignment request sent. Waiting for admin confirmation.'
            ]);
        } catch (\Exception $e) {
            Log::error('Error taking ticket:', [
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to take ticket',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get pending confirmations (Admin only)
     */
    public function pendingConfirmations()
    {
        try {
            $sessionUser = session('user');
            
            if (!$sessionUser || $sessionUser['role']['id'] !== RoleId::ADMIN->value) {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin only'
                ], 403);
            }

            $confirmations = DB::table('ticket_confirmation')
                ->join('ticket', 'ticket_confirmation.ticket_id', '=', 'ticket.ticket_id')
                ->join('employee', 'ticket_confirmation.employee_id', '=', 'employee.employee_id')
                ->join('employee_basic_data', 'employee.employee_id', '=', 'employee_basic_data.employee_id')
                ->join('customer', 'ticket.customer_id', '=', 'customer.customer_id')
                ->leftJoin('customer_basic_data', 'customer.customer_id', '=', 'customer_basic_data.customer_id')
                ->where('ticket_confirmation.status', 'pending')
                ->select(
                    'ticket_confirmation.*',
                    'ticket.description',
                    'ticket.ticket_priority',
                    'employee_basic_data.first_name as employee_name',
                    DB::raw('COALESCE(customer_basic_data.name_1, customer.email) as customer_name')
                )
                ->orderBy('ticket_confirmation.created_at', 'desc')
                ->get();

            // Decode member_ids for each confirmation
            foreach ($confirmations as $confirmation) {
                $confirmation->member_ids = json_decode($confirmation->member_ids, true) ?? [];
                
                // Get member names
                if (!empty($confirmation->member_ids)) {
                    $members = DB::table('employee')
                        ->join('employee_basic_data', 'employee.employee_id', '=', 'employee_basic_data.employee_id')
                        ->whereIn('employee.employee_id', $confirmation->member_ids)
                        ->pluck('employee_basic_data.first_name')
                        ->toArray();
                    
                    $confirmation->member_names = $members;
                } else {
                    $confirmation->member_names = [];
                }
            }

            return response()->json([
                'success' => true,
                'data' => $confirmations
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching confirmations:', [
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch confirmations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Confirm or reject ticket assignment (Admin only)
     */
    public function confirmAssignment(Request $request, $confirmationId)
    {
        $sessionUser = session('user');
        
        if (!$sessionUser || $sessionUser['role']['id'] !== RoleId::ADMIN->value) {
            return response()->json([
                'success' => false,
                'message' => 'Admin only'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'action' => 'required|in:confirm,reject'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $confirmation = DB::table('ticket_confirmation')
                ->where('confirmation_id', $confirmationId)
                ->first();
            
            if (!$confirmation || $confirmation->status != 'pending') {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or already processed confirmation'
                ], 400);
            }

            if ($request->action === 'confirm') {
                // Update ticket
                $ticket = Ticket::findOrFail($confirmation->ticket_id);
                $ticket->update([
                    'employee_id' => $confirmation->employee_id,
                    'man_days' => $confirmation->man_days,
                    'jarvies_status' => 'sent it to support',
                    'start_date' => now()
                ]);

                // Attach members
                $memberIds = json_decode($confirmation->member_ids, true);
                if ($memberIds) {
                    $ticket->members()->sync($memberIds);
                }

                // Update confirmation - GANTI 'jarvies_status' jadi 'status'
                DB::table('ticket_confirmation')
                    ->where('confirmation_id', $confirmationId)
                    ->update([
                        'status' => 'confirmed',
                        'confirmed_by' => $sessionUser['id'],
                        'confirmed_at' => now(),
                        'updated_at' => now()
                    ]);
            } else {
                // Reject - GANTI 'jarvies_status' jadi 'status'
                DB::table('ticket_confirmation')
                    ->where('confirmation_id', $confirmationId)
                    ->update([
                        'status' => 'rejected',
                        'confirmed_by' => $sessionUser['id'],
                        'confirmed_at' => now(),
                        'updated_at' => now()
                    ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $request->action === 'confirm' 
                    ? 'Assignment confirmed successfully' 
                    : 'Assignment rejected'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error confirming assignment:', [
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to process confirmation',
                'error' => $e->getMessage()
            ], 500);
        }
    }   

    /**
     * Get available PICs (employees with DSM qualification) — for admin/helpdesk/head assign
     */
    public function getAvailablePics()
    {
        $sessionUser = session('user');
        if (!$sessionUser) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $roleId = $sessionUser['role']['id'] ?? 0;
        $allowed = array_merge(
            [RoleId::ADMIN->value, RoleId::HEAD_OF_SUPPORT->value],
            RoleId::HELPDESK_GROUP
        );
        if (!in_array($roleId, $allowed, true)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $pics = DB::table('employee')
            ->join('employee_basic_data', 'employee.employee_id', '=', 'employee_basic_data.employee_id')
            ->where('employee.role_id', RoleId::EMPLOYEE->value)
            ->select(
                'employee.employee_id',
                DB::raw("TRIM(CONCAT(COALESCE(employee_basic_data.first_name,''), ' ', COALESCE(employee_basic_data.last_name,''))) as name")
            )
            ->orderBy('employee_basic_data.first_name')
            ->get();

        return response()->json(['success' => true, 'data' => $pics]);
    }

    /**
     * Assign PIC directly (Admin / Helpdesk / Head of Support only) — no confirmation needed
     */
    public function assignPic(Request $request, $id)
    {
        $sessionUser = session('user');
        if (!$sessionUser) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $roleId = $sessionUser['role']['id'] ?? 0;
        $allowed = array_merge(
            [RoleId::ADMIN->value, RoleId::HEAD_OF_SUPPORT->value],
            RoleId::HELPDESK_GROUP
        );
        if (!in_array($roleId, $allowed, true)) {
            return response()->json(['success' => false, 'message' => 'Only Admin, Helpdesk, or Head of Support can assign a PIC'], 403);
        }

        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employee,employee_id',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $ticket = Ticket::findOrFail($id);

            if ($ticket->employee_id !== null) {
                return response()->json(['success' => false, 'message' => 'Ticket already has a PIC assigned'], 400);
            }

            $ticket->update([
                'employee_id'    => $request->employee_id,
                'jarvies_status' => 'in process',
                'start_date'     => now(),
            ]);

            return response()->json(['success' => true, 'message' => 'PIC assigned successfully']);
        } catch (\Exception $e) {
            Log::error('Error assigning PIC:', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to assign PIC'], 500);
        }
    }

    /**
     * Update man days (Customer & Admin only)
     */
    public function updateManDays(Request $request, $id)
    {
        $sessionUser = session('user');
        
        // Only admin can update man days
        if (!$sessionUser || $sessionUser['role']['id'] !== RoleId::ADMIN->value) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only admin can update man days.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'man_days' => 'required|numeric|min:0|max:9999.99',
            'notes' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $ticket = Ticket::findOrFail($id);
            
            // Check if ticket is confirmed (has employee_id)
            if (!$ticket->employee_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket must be assigned and confirmed first'
                ], 400);
            }

            DB::beginTransaction();

            $userInfo = $this->getUserInfo($sessionUser);

            // Save history
            DB::table('mandays_history')->insert([
                'ticket_id' => $id,
                'old_value' => $ticket->man_days ?? 0,
                'new_value' => $request->man_days,
                'changed_by' => $userInfo['id'],
                'changed_by_name' => $userInfo['name'],
                'changed_by_role' => $userInfo['role'],
                'notes' => $request->notes,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Update ticket
            $ticket->update(['man_days' => $request->man_days]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Man days updated successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating man days:', [
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update man days',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get man days history
     */
    public function getMandaysHistory($id)
    {
        try {
            $sessionUser = session('user');
            
            if (!$sessionUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $ticket = Ticket::findOrFail($id);

            $history = DB::table('mandays_history')
                ->where('ticket_id', $id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $history
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching history:', [
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified ticket
     */
    public function show($id)
    {
        try {
            $sessionUser = session('user');
            
            if (!$sessionUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $ticket = Ticket::with(['customer.basicData', 'employee.basicData', 'members.basicData'])
                ->findOrFail($id);

            // Customer can only see their own tickets
            if ($sessionUser['role']['id'] === RoleId::INTERNSHIP->value) {
                if ((int) $ticket->customer_id !== (int) $sessionUser['id']) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Access denied'
                    ], 403);
                }
            }

            // Employee harus punya DSM qualification (kecuali Admin)
            if ($sessionUser['role']['id'] === RoleId::EMPLOYEE->value && !$this->isEmployeeQualified($sessionUser['id'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not qualified for this section. DSM qualification required.'
                ], 403);
            }

            // Transform data
            $ticketData = [
                'ticket_id' => $ticket->ticket_id,
                'ticket_number' => $ticket->ticket_number,
                'customer_id' => $ticket->customer_id,
                'employee_id' => $ticket->employee_id,
                'description' => $ticket->description,
                'ticket_priority' => $ticket->ticket_priority,
                'ticket_type' => $ticket->ticket_type,
                'jarvies_status' => $ticket->jarvies_status,
                'status' => $ticket->status,
                'channel' => $ticket->channel,
                'folder' => $ticket->folder,
                'file_log' => $ticket->file_log,
                'start_date' => $ticket->start_date,
                'end_date' => $ticket->end_date,
                'man_days' => $ticket->man_days,
                'wait_close' => $ticket->wait_close,
                'last_message_at' => $ticket->last_message_at,
                'last_customer_reply_at' => $ticket->last_customer_reply_at,
                'last_agent_reply_at' => $ticket->last_agent_reply_at,
                'last_internal_note_at'        => $ticket->last_internal_note_at,
                'last_internal_note_sender_id' => $ticket->last_internal_note_sender_id,
                'customer' => $ticket->customer ? [
                    'customer_id' => $ticket->customer->customer_id,
                    'customer_name' => $ticket->customer->basicData->name_1 ?? $ticket->customer->email,
                ] : null,
                'employee' => $ticket->employee ? [
                    'employee_id' => $ticket->employee->employee_id,
                    'employee_name' => $ticket->employee->basicData->first_name ?? 'Unknown',
                ] : null,
                'members' => $ticket->members->map(function($member) {
                    return [
                        'employee_id' => $member->employee_id,
                        'employee_name' => $member->basicData->first_name ?? 'Unknown',
                    ];
                }),
                'member_ids' => $ticket->members->pluck('employee_id')->toArray(),
                'created_at' => $ticket->created_at,
                'updated_at' => $ticket->updated_at,
            ];

            return response()->json([
                'success' => true,
                'data' => $ticketData,
                'message' => 'Ticket retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified ticket (Admin only)
     */
    public function update(Request $request, $id)
    {
        $sessionUser = session('user');

        $roleId     = $sessionUser['role']['id'] ?? 0;
        $isAdmin    = $roleId === RoleId::ADMIN->value;
        $isHelpdesk = in_array($roleId, RoleId::HELPDESK_GROUP, true);
        $isEmployee = $roleId !== RoleId::INTERNSHIP->value && $roleId > 0;

        if (!$sessionUser) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        // Employees other than admin/helpdesk may ONLY self-assign PIC on unassigned tickets.
        // All other fields require admin or helpdesk.
        $ticketForCheck = Ticket::find($id);
        $requestKeys    = array_keys($request->except(['_token', '_method']));
        $isSelfAssignOnly = !$isAdmin && !$isHelpdesk
            && $requestKeys === ['employee_id']
            && $ticketForCheck
            && $ticketForCheck->employee_id === null;

        if (!$isAdmin && !$isHelpdesk && !$isSelfAssignOnly) {
            return response()->json([
                'success' => false,
                'message' => 'Only admin or helpdesk can update ticket'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'jarvies_status' => 'sometimes|string|in:in process,author action,proposed solution,closed,sent in to SAP,sent it to support',
            'ticket_priority' => 'sometimes|string|in:Very High,High,Medium,Low',
            'ticket_type'    => 'sometimes|nullable|string|in:Incident,Service Request,Change Request,Consult',
            'scale'          => 'sometimes|nullable|string|in:Simple,Medium,Complex',
            'employee_id'    => 'sometimes|nullable|exists:employee,employee_id',
            'man_days'       => 'sometimes|nullable|numeric|min:0|max:9999.99',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket update data is invalid.',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $ticket = Ticket::findOrFail($id);

            // Build update data from validated fields
            $updateData = [];

            if ($request->has('jarvies_status') && ($isAdmin || $isHelpdesk)) {
                $updateData['jarvies_status'] = $request->jarvies_status;
            }
            if ($request->has('ticket_priority') && ($isAdmin || $isHelpdesk)) {
                $updateData['ticket_priority'] = $request->ticket_priority;
            }
            if ($request->has('ticket_type') && ($isAdmin || $isHelpdesk)) {
                $updateData['ticket_type'] = $request->ticket_type;
            }
            if ($request->has('scale') && ($isAdmin || $isHelpdesk)) {
                $updateData['scale'] = $request->scale;
            }
            if ($request->has('employee_id')) {
                $updateData['employee_id'] = $request->employee_id;
                // Jika sebelumnya unassigned dan sekarang di-assign PIC → otomatis in process
                if ($ticket->employee_id === null && !empty($request->employee_id)) {
                    $updateData['jarvies_status'] = 'in process';
                }
            }
            if ($request->has('man_days') && $isAdmin) {
                $updateData['man_days'] = $request->man_days;
            }

            if (!empty($updateData)) {
                $ticket->update($updateData);
            }

            $ticket->load(['customer.basicData', 'employee.basicData', 'members.basicData']);

            return response()->json([
                'success' => true,
                'data' => $ticket,
                'message' => 'Ticket updated successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating ticket:', [
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update ticket',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get tickets by status
     */
    public function getByStatus($status)
    {
        try {
            $sessionUser = session('user');
            
            if (!$sessionUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            // Employee harus punya DSM qualification (kecuali Admin)
            if ($sessionUser['role']['id'] === RoleId::EMPLOYEE->value && !$this->isEmployeeQualified($sessionUser['id'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not qualified for this section. DSM qualification required.'
                ], 403);
            }

            $query = Ticket::with(['customer.basicData', 'employee.basicData', 'members.basicData'])
                ->where('jarvies_status', $status)
                ->orderBy('created_at', 'desc');

            // Admin (role_id = 1) bisa lihat semua
            // Employee dengan DSM juga bisa lihat semua (sudah dicheck di atas)

            $tickets = $query->get();

            return response()->json([
                'success' => true,
                'data' => $tickets,
                'message' => "Tickets with status '{$status}' retrieved successfully"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve tickets',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get ticket statistics
     */
    public function statistics()
    {
        try {
            $sessionUser = session('user');
            
            if (!$sessionUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            // Employee harus punya DSM qualification (kecuali Admin)
            if ($sessionUser['role']['id'] === RoleId::EMPLOYEE->value && !$this->isEmployeeQualified($sessionUser['id'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not qualified for this section. DSM qualification required.'
                ], 403);
            }

            $query = Ticket::query();

            // Admin (role_id = 1) dan Employee dengan DSM bisa lihat semua statistik

            $stats = [
                'total' => (clone $query)->count(),
                'in_process' => (clone $query)->where('jarvies_status', 'in process')->count(),
                'author_action' => (clone $query)->where('jarvies_status', 'author action')->count(),
                'proposed_solution' => (clone $query)->where('jarvies_status', 'proposed solution')->count(),
                'closed' => (clone $query)->where('jarvies_status', 'closed')->count(),
                'sent_to_sap' => (clone $query)->where('jarvies_status', 'sent in to SAP')->count(),
                'sent_to_support' => (clone $query)->where('jarvies_status', 'sent it to support')->count(),
                'by_priority' => [
                    'high' => (clone $query)->where('ticket_priority', 'High')->count(),
                    'medium' => (clone $query)->where('ticket_priority', 'Medium')->count(),
                    'low' => (clone $query)->where('ticket_priority', 'Low')->count(),
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Statistics retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update ticket status (status field, not jarvies_status)
     * Admin only
     */
    public function updateTicketStatus(Request $request, $id)
    {
        $sessionUser = session('user');
        
        $roleId = $sessionUser['role']['id'] ?? 0;
        if (!$sessionUser || !in_array($roleId, RoleId::TICKET_MANAGER_GROUP, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Only admin or helpdesk can update ticket status'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:open,in_progress,hold,cancel,closed,reply,wait_to_close',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket status is invalid. Allowed values: open, in_progress, hold, cancel, closed, reply, wait_to_close.',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $ticket = Ticket::findOrFail($id);

            $ticket->update([
                'status' => $request->status
            ]);

            // Add system log and send email when ticket is closed or cancelled
            if (in_array($request->status, ['closed', 'cancel'])) {
                $userName  = $sessionUser['name'] ?? $sessionUser['email'] ?? 'Unknown User';
                $label     = $request->status === 'closed' ? 'Closed' : 'Cancelled';
                $timestamp = now()->format('d/m/Y H:i');
                $logMessage = "Status change to \"{$label}\" by {$userName} at {$timestamp}";

                DB::table('ticket_message')->insert([
                    'ticket_id'   => $ticket->ticket_id,
                    'sender_type' => 'system',
                    'message'     => $logMessage,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);

                try {
                    $customerEmail = $ticket->customer?->email
                        ?? Customer::find($ticket->customer_id)?->email;

                    if ($customerEmail) {
                        $ticketNum = $ticket->ticket_number ?? $ticket->ticket_id;
                        $subject   = 'Ticket #' . $ticketNum . ' - ' . $label;
                        $htmlBody  = '<p>Your ticket <strong>#' . htmlspecialchars((string) $ticketNum) . '</strong> has been <strong>' . $label . '</strong>.</p>'
                                   . '<p>' . htmlspecialchars($logMessage) . '</p>';

                        $inReplyTo = TicketMessage::where('ticket_id', $ticket->ticket_id)
                            ->where('channel', 'email')
                            ->whereNotNull('email_message_id')
                            ->orderByDesc('created_at')
                            ->value('email_message_id');

                        $emailController = new EmailController();
                        $emailController->sendTicketReply(
                            $customerEmail,
                            $subject,
                            $htmlBody,
                            $inReplyTo,
                            [],
                            [],
                            true,
                            $ticket->email_thread_id
                        );
                    }
                } catch (\Exception $emailEx) {
                    Log::warning('updateTicketStatus: email notification failed', [
                        'error'     => $emailEx->getMessage(),
                        'ticket_id' => $id,
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Ticket status updated successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating ticket status:', [
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update ticket status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get pending member change requests (Admin only)
     */
    public function pendingMemberChanges()
    {
        try {
            $sessionUser = session('user');
            
            if (!$sessionUser || $sessionUser['role']['id'] !== RoleId::ADMIN->value) {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin only'
                ], 403);
            }

            $memberChanges = DB::table('member_change_requests')
                ->join('ticket', 'member_change_requests.ticket_id', '=', 'ticket.ticket_id')
                ->join('employee', 'member_change_requests.requested_by', '=', 'employee.employee_id')
                ->join('employee_basic_data', 'employee.employee_id', '=', 'employee_basic_data.employee_id')
                ->where('member_change_requests.status', 'pending')
                ->select(
                    'member_change_requests.*',
                    'ticket.description as ticket_description',
                    'employee_basic_data.first_name as requested_by_name'
                )
                ->orderBy('member_change_requests.created_at', 'desc')
                ->get();

            // Decode member_ids and get names
            foreach ($memberChanges as $change) {
                $change->member_ids = json_decode($change->member_ids, true) ?? [];
                
                if (!empty($change->member_ids)) {
                    $members = DB::table('employee')
                        ->join('employee_basic_data', 'employee.employee_id', '=', 'employee_basic_data.employee_id')
                        ->whereIn('employee.employee_id', $change->member_ids)
                        ->pluck('employee_basic_data.first_name')
                        ->toArray();
                    
                    $change->member_names = implode(', ', $members);
                } else {
                    $change->member_names = 'None';
                }
            }

            return response()->json([
                'success' => true,
                'data' => $memberChanges
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching member changes:', [
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch member changes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add a single member to ticket (Admin, PIC, or Helpdesk)
     */
    public function addMember(Request $request, $id)
    {
        $sessionUser = session('user');
        if (!$sessionUser) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $ticket  = Ticket::with('members.basicData')->findOrFail($id);
        $roleId     = $sessionUser['role']['id'];
        $isAdmin    = $roleId === RoleId::ADMIN->value;
        $isHelpdesk = in_array($roleId, RoleId::HELPDESK_GROUP, true);
        $isPic      = $roleId === RoleId::EMPLOYEE->value && $ticket->employee_id == $sessionUser['id'];

        if (!$isAdmin && !$isHelpdesk && !$isPic) {
            return response()->json([
                'success' => false,
                'message' => 'Only Admin, Helpdesk, or the assigned PIC can add members.',
            ], 403);
        }

        $request->validate([
            'employee_id' => 'required|exists:employee,employee_id',
        ]);

        try {
            $empId = (int) $request->employee_id;

            // Prevent adding PIC as member
            if ($ticket->employee_id == $empId) {
                return response()->json([
                    'success' => false,
                    'message' => 'The assigned PIC cannot also be added as a member.',
                ], 422);
            }

            // Avoid duplicate — use wherePivot to avoid ambiguous column with BelongsToMany join
            if (!$ticket->members()->wherePivot('employee_id', $empId)->exists()) {
                $ticket->members()->attach($empId);
            }

            // Return updated members list
            $ticket->load('members.basicData');
            $members = $ticket->members->map(fn ($m) => [
                'employee_id' => $m->employee_id,
                'name'        => trim(($m->basicData->first_name ?? '') . ' ' . ($m->basicData->last_name ?? '')),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Member added successfully',
                'data'    => $members,
            ]);
        } catch (\Exception $e) {
            Log::error('Error adding member:', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to add member'], 500);
        }
    }

    /**
     * Update ticket members directly (Admin, PIC, or Helpdesk)
     */
    public function updateMembers(Request $request, $id)
    {
        $sessionUser = session('user');
        if (!$sessionUser) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $ticket  = Ticket::findOrFail($id);
        $roleId     = $sessionUser['role']['id'];
        $isAdmin    = $roleId === RoleId::ADMIN->value;
        $isHelpdesk = in_array($roleId, RoleId::HELPDESK_GROUP, true);
        $isPic      = $roleId === RoleId::EMPLOYEE->value && $ticket->employee_id == $sessionUser['id'];

        if (!$isAdmin && !$isHelpdesk && !$isPic) {
            return response()->json([
                'success' => false,
                'message' => 'Only Admin, Helpdesk, or the assigned PIC can manage members.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'member_ids' => 'required|array',
            'member_ids.*' => 'exists:employee,employee_id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Sync members (akan replace existing)
            $ticket->members()->sync($request->member_ids);

            return response()->json([
                'success' => true,
                'message' => 'Members updated successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating members:', [
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update members',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Request member change (Employee/PIC only)
     */
    public function requestMemberChange(Request $request, $id)
    {
        $sessionUser = session('user');
        
        $allowedRoles = [RoleId::EMPLOYEE->value, RoleId::HELPDESK->value, RoleId::RPMO->value];
        if (!$sessionUser || !in_array($sessionUser['role']['id'], $allowedRoles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya PIC atau Helpdesk yang dapat mengajukan perubahan member'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'member_ids' => 'required|array',
            'member_ids.*' => 'exists:employee,employee_id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $ticket = Ticket::findOrFail($id);
            
            // Check if employee is the PIC
            if ($ticket->employee_id != $sessionUser['id']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only the assigned PIC can request member changes'
                ], 403);
            }

            // Create change request
            DB::table('member_change_requests')->insert([
                'ticket_id' => $id,
                'requested_by' => $sessionUser['id'],
                'change_type' => 'update',
                'member_ids' => json_encode($request->member_ids),
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Permintaan perubahan member dikirim. Menunggu persetujuan Head of Support.'
            ]);
        } catch (\Exception $e) {
            Log::error('Error requesting member change:', [
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to request member change',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove member (Admin, PIC, or Helpdesk)
     */
    public function removeMember($ticketId, $employeeId)
    {
        $sessionUser = session('user');
        if (!$sessionUser) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $ticket     = Ticket::with('members.basicData')->findOrFail($ticketId);
        $roleId     = $sessionUser['role']['id'];
        $isAdmin    = $roleId === RoleId::ADMIN->value;
        $isHoS      = $roleId === RoleId::HEAD_OF_SUPPORT->value;
        $isHelpdesk = in_array($roleId, RoleId::HELPDESK_GROUP, true);
        $isPic      = $roleId === RoleId::EMPLOYEE->value && $ticket->employee_id == $sessionUser['id'];

        if (!$isAdmin && !$isHoS && !$isHelpdesk && !$isPic) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak memiliki akses untuk menghapus member.'
            ], 403);
        }

        try {
            $member     = $ticket->members->firstWhere('employee_id', $employeeId);
            $memberName = $member
                ? trim(($member->basicData->first_name ?? '') . ' ' . ($member->basicData->last_name ?? ''))
                : null;

            $ticket->members()->detach($employeeId);
            $ticket->load('members.basicData');

            return response()->json([
                'success'       => true,
                'message'       => 'Member removed successfully',
                'employee_name' => $memberName,
                'data'          => $ticket->members->map(fn ($m) => [
                    'employee_id' => $m->employee_id,
                    'name'        => trim(($m->basicData->first_name ?? '') . ' ' . ($m->basicData->last_name ?? '')),
                ]),
            ]);
        } catch (\Exception $e) {
            Log::error('Error removing member:', [
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to remove member',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Request member removal (Employee/PIC only)
     */
    public function requestMemberRemoval($ticketId, $employeeId)
    {
        $sessionUser = session('user');
        
        if (!$sessionUser || $sessionUser['role']['id'] !== RoleId::EMPLOYEE->value) {
            return response()->json([
                'success' => false,
                'message' => 'Only employees can request member removal'
            ], 403);
        }

        try {
            $ticket = Ticket::findOrFail($ticketId);
            
            // Check if employee is the PIC
            if ($ticket->employee_id != $sessionUser['id']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only the assigned PIC can request member removal'
                ], 403);
            }

            // Create removal request
            DB::table('member_change_requests')->insert([
                'ticket_id' => $ticketId,
                'requested_by' => $sessionUser['id'],
                'change_type' => 'remove',
                'member_ids' => json_encode([$employeeId]),
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Permintaan hapus member dikirim. Menunggu persetujuan Head of Support.'
            ]);
        } catch (\Exception $e) {
            Log::error('Error requesting member removal:', [
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to request member removal',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process member change request (Admin only)
     */
    public function processMemberChangeRequest(Request $request, $changeRequestId, $action)
    {
        $sessionUser = session('user');
        
        $allowedRoles = [RoleId::ADMIN->value, RoleId::HEAD_OF_SUPPORT->value];
        if (!$sessionUser || !in_array($sessionUser['role']['id'], $allowedRoles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya Head of Support atau Admin yang dapat memproses permintaan ini'
            ], 403);
        }

        if (!in_array($action, ['approve', 'reject'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid action'
            ], 400);
        }

        try {
            DB::beginTransaction();

            $changeRequest = DB::table('member_change_requests')
                ->where('change_request_id', $changeRequestId)
                ->first();
            
            if (!$changeRequest || $changeRequest->status != 'pending') {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or already processed request'
                ], 400);
            }

            if ($action === 'approve') {
                $ticket = Ticket::findOrFail($changeRequest->ticket_id);
                $memberIds = json_decode($changeRequest->member_ids, true);
                
                if ($changeRequest->change_type === 'update') {
                    // Update members
                    $ticket->members()->sync($memberIds);
                } else if ($changeRequest->change_type === 'remove') {
                    // Remove members
                    $ticket->members()->detach($memberIds);
                }
                
                // Update request status
                DB::table('member_change_requests')
                    ->where('change_request_id', $changeRequestId)
                    ->update([
                        'status' => 'approved',
                        'processed_by' => $sessionUser['id'],
                        'processed_at' => now(),
                        'updated_at' => now()
                    ]);
            } else {
                // Reject
                DB::table('member_change_requests')
                    ->where('change_request_id', $changeRequestId)
                    ->update([
                        'status' => 'rejected',
                        'processed_by' => $sessionUser['id'],
                        'processed_at' => now(),
                        'updated_at' => now()
                    ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $action === 'approve' 
                    ? 'Member change approved successfully'
                    : 'Member change rejected'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error processing member change:', [
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to process member change',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single confirmation by ID (Admin only)
     */
    public function getConfirmation($confirmationId)
    {
        try {
            $sessionUser = session('user');
            
            if (!$sessionUser || $sessionUser['role']['id'] !== RoleId::ADMIN->value) {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin only'
                ], 403);
            }

            $confirmation = DB::table('ticket_confirmation')
                ->join('ticket', 'ticket_confirmation.ticket_id', '=', 'ticket.ticket_id')
                ->join('employee', 'ticket_confirmation.employee_id', '=', 'employee.employee_id')
                ->join('employee_basic_data', 'employee.employee_id', '=', 'employee_basic_data.employee_id')
                ->join('customer', 'ticket.customer_id', '=', 'customer.customer_id')
                ->leftJoin('customer_basic_data', 'customer.customer_id', '=', 'customer_basic_data.customer_id')
                ->where('ticket_confirmation.confirmation_id', $confirmationId)
                ->select(
                    'ticket_confirmation.*',
                    'ticket.description',
                    'ticket.ticket_priority',
                    'employee_basic_data.first_name as employee_name',
                    DB::raw('COALESCE(customer_basic_data.name_1, customer.email) as customer_name')
                )
                ->first();

            if (!$confirmation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Confirmation not found'
                ], 404);
            }

            // Decode member_ids
            $confirmation->member_ids = json_decode($confirmation->member_ids, true) ?? [];
            
            // Get member names
            if (!empty($confirmation->member_ids)) {
                $members = DB::table('employee')
                    ->join('employee_basic_data', 'employee.employee_id', '=', 'employee_basic_data.employee_id')
                    ->whereIn('employee.employee_id', $confirmation->member_ids)
                    ->pluck('employee_basic_data.first_name')
                    ->toArray();
                
                $confirmation->member_names = $members;
            } else {
                $confirmation->member_names = [];
            }

            return response()->json([
                'success' => true,
                'data' => $confirmation
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching confirmation:', [
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch confirmation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available delivery supports for assigning a ticket
     * Returns supports that can accept more tickets
     */
    public function getAvailableSupports($id)
    {
        try {
            $sessionUser = session('user');

            if (!$sessionUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            // Only Admin and Helpdesk can assign tickets to support
            if (!in_array($sessionUser['role']['id'], [RoleId::ADMIN->value, RoleId::HELPDESK->value], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only Admin and Helpdesk can assign tickets to delivery support'
                ], 403);
            }

            $ticket = Ticket::findOrFail($id);

            // Get all active delivery supports
            $supports = DB::table('delivery_support')
                ->join('customer', 'delivery_support.client_id', '=', 'customer.customer_id')
                ->leftJoin('customer_basic_data', 'customer.customer_id', '=', 'customer_basic_data.customer_id')
                ->leftJoin('employee as owner', 'delivery_support.delivery_owner_id', '=', 'owner.employee_id')
                ->leftJoin('employee_basic_data as owner_data', 'owner.employee_id', '=', 'owner_data.employee_id')
                ->where('delivery_support.calculated_progress', '<', 100) // Not completed
                ->select(
                    'delivery_support.id',
                    'delivery_support.name',
                    'delivery_support.ticket_id',
                    'delivery_support.client_id',
                    'delivery_support.calculated_progress',
                    'delivery_support.start_date',
                    'delivery_support.end_date',
                    DB::raw('COALESCE(customer_basic_data.name_1, customer.email) as client_name'),
                    DB::raw('COALESCE(owner_data.first_name, "Unassigned") as owner_name')
                )
                ->orderBy('delivery_support.created_at', 'desc')
                ->get();

            // Count tickets per support
            foreach ($supports as $support) {
                $support->ticket_count = DB::table('delivery_support_activities')
                    ->where('delivery_support_id', $support->id)
                    ->count();
            }

            return response()->json([
                'success' => true,
                'data' => $supports,
                'ticket' => [
                    'ticket_id' => $ticket->ticket_id,
                    'ticket_number' => $ticket->ticket_number,
                    'description' => $ticket->description,
                    'customer_id' => $ticket->customer_id,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching available supports:', [
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch available supports',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign a ticket to a delivery support
     * This creates an activity in the delivery support from the ticket
     */
    public function assignToSupport(Request $request, $id)
    {
        $sessionUser = session('user');

        if (!$sessionUser) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        // Only Admin and Helpdesk can assign tickets
        if (!in_array($sessionUser['role']['id'], [RoleId::ADMIN->value, RoleId::HELPDESK->value], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Only Admin and Helpdesk can assign tickets to delivery support'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'support_id' => 'required|exists:delivery_support,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'The support_id is required and must reference an existing delivery support record.',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $ticket = Ticket::findOrFail($id);
            $supportId = $request->support_id;

            // Get delivery support
            $support = DB::table('delivery_support')->where('id', $supportId)->first();

            if (!$support) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Delivery support not found'
                ], 404);
            }

            // Check if ticket is already assigned to this support (check both ticket_id and notes for backward compatibility)
            $existingActivity = DB::table('delivery_support_activities')
                ->where('delivery_support_id', $supportId)
                ->where(function ($query) use ($ticket) {
                    $query->where('ticket_id', $ticket->ticket_id)
                        ->orWhere('notes', 'like', '%Ticket #' . $ticket->ticket_id . '%');
                })
                ->first();

            if ($existingActivity) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'This ticket is already assigned to this delivery support'
                ], 400);
            }

            // Find the default "Support" phase
            $phase = DB::table('delivery_support_phases')
                ->where('delivery_support_id', $supportId)
                ->where('is_system_default', true)
                ->first();

            if (!$phase) {
                // Fallback: get first active phase
                $phase = DB::table('delivery_support_phases')
                    ->where('delivery_support_id', $supportId)
                    ->where('is_active', true)
                    ->first();
            }

            if (!$phase) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'No active phase found in delivery support'
                ], 400);
            }

            // Find the "Incident" group
            $group = DB::table('delivery_support_planning')
                ->where('delivery_support_id', $supportId)
                ->where('phase_id', $phase->id)
                ->where('is_group', true)
                ->first();

            // Get next order sequence
            $nextOrder = DB::table('delivery_support_activities')
                ->where('delivery_support_id', $supportId)
                ->where('delivery_support_phase_id', $phase->id)
                ->max('order_sequence') + 1;

            // Map priority to complexity
            $complexity = match (strtolower($ticket->ticket_priority ?? '')) {
                'high' => 'complex',
                'medium' => 'medium',
                'low' => 'simple',
                default => 'medium'
            };

            // Map status
            $status = match (strtolower($ticket->status ?? '')) {
                'open' => 'not_started',
                'in_progress' => 'in_progress',
                'hold' => 'on_hold',
                'closed' => 'completed',
                'cancel' => 'completed',
                default => 'not_started'
            };

            // Create activity from ticket
            $activityId = DB::table('delivery_support_activities')->insertGetId([
                'delivery_support_id' => $supportId,
                'delivery_support_phase_id' => $phase->id,
                'ticket_id' => $ticket->ticket_id, // Link activity to source ticket
                'stage_id' => null,
                'name' => $ticket->ticket_number . ' - ' . ($ticket->description ?? "Ticket #{$ticket->ticket_id}"),
                'description' => $ticket->description,
                'order_sequence' => $nextOrder,
                'module' => null,
                'new_issue' => true,
                'object' => null,
                'incident_type' => $ticket->type ?? 'incident',
                'complexity' => $complexity,
                'deliverable' => null,
                'start_date' => $ticket->start_date ?? now(),
                'end_date' => $ticket->end_date,
                'status' => $status,
                'progress_percentage' => 0,
                'weight' => $ticket->man_days ?? 1,
                'notes' => "Auto-created from Ticket #{$ticket->ticket_id}",
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create planning entry if group exists
            if ($group) {
                DB::table('delivery_support_planning')->insert([
                    'delivery_support_id' => $supportId,
                    'phase_id' => $phase->id,
                    'parent_id' => $group->id,
                    'activity_id' => $activityId,
                    'name' => $ticket->ticket_number . ' - ' . ($ticket->description ?? "Ticket #{$ticket->ticket_id}"),
                    'is_group' => false,
                    'level' => 1,
                    'order_sequence' => $nextOrder,
                    'start_date' => $ticket->start_date ?? now(),
                    'end_date' => $ticket->end_date,
                    'weight' => $ticket->man_days ?? 1,
                    'status' => $status,
                    'progress_percentage' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Assign ticket PIC to activity if exists
            if ($ticket->employee_id) {
                DB::table('delivery_support_activity_employee')->insert([
                    'delivery_support_activity_id' => $activityId,
                    'employee_id' => $ticket->employee_id,
                    'role' => 'lead',
                    'allocation_percentage' => 100,
                    'is_active' => true,
                    'assigned_date' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Update ticket to link to delivery support (optional - store reference)
            // Note: The ticket table has a delivery_support relationship via DeliverySupport model

            DB::commit();

            Log::info('Ticket assigned to delivery support', [
                'ticket_id' => $ticket->ticket_id,
                'ticket_number' => $ticket->ticket_number,
                'support_id' => $supportId,
                'activity_id' => $activityId,
                'assigned_by' => $sessionUser['id']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Ticket assigned to delivery support successfully',
                'data' => [
                    'activity_id' => $activityId,
                    'support_id' => $supportId,
                    'support_name' => $support->name,
                    'support_type' => $support->type ?? null,
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error assigning ticket to support:', [
                'ticket_id' => $id,
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to assign ticket to delivery support',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new delivery support and assign the ticket to it
     * Only Admin (1), Helpdesk (6), and RPMO (7) can create delivery supports
     */
    public function createDeliverySupport(Request $request, $id)
    {
        $sessionUser = session('user');

        if (!$sessionUser) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        // Only Admin, Helpdesk, and RPMO can create delivery supports
        if (!in_array($sessionUser['role']['id'], RoleId::TICKET_MANAGER_GROUP, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Only Admin, Helpdesk, and RPMO can create delivery supports'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name'           => 'required|string|max:255',
            'type'           => 'required|in:AMS,MO,ATS,Project,Internal',
            'support_method' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Delivery list entry data is invalid.',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $ticket = Ticket::findOrFail($id);

            // Create delivery list entry
            $deliveryListId = DB::table('delivery_list')->insertGetId([
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create delivery support
            $supportId = DB::table('delivery_support')->insertGetId([
                'id_delivery_list' => $deliveryListId,
                'client_id'        => $ticket->customer_id,
                'name'             => $request->name,
                'type'             => $request->type,
                'support_method'   => $request->support_method,
                'start_date' => $ticket->start_date ?? now(),
                'end_date' => $ticket->end_date,
                'created_by_id' => $sessionUser['id'],
                'calculated_progress' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create default view configuration
            DB::table('delivery_support_view_configurations')->insert([
                'delivery_support_id' => $supportId,
                'default_view' => 'table',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create Support phase
            $phaseId = DB::table('delivery_support_phases')->insertGetId([
                'delivery_support_id' => $supportId,
                'name' => 'Support',
                'color' => '#3B82F6',
                'weight' => 100,
                'order_sequence' => 1,
                'is_resolution_phase' => true,
                'is_system_default' => true,
                'is_visible' => true,
                'is_active' => true,
                'orientation' => 'vertical',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create Incident group
            $groupId = DB::table('delivery_support_planning')->insertGetId([
                'delivery_support_id' => $supportId,
                'phase_id' => $phaseId,
                'parent_id' => null,
                'name' => 'Incident',
                'group_name' => 'Incident',
                'is_group' => true,
                'level' => 0,
                'order_sequence' => 1,
                'weight' => 100,
                'status' => 'not_started',
                'progress_percentage' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Map ticket status to activity status
            $status = match (strtolower($ticket->status ?? '')) {
                'open' => 'not_started',
                'in_progress' => 'in_progress',
                'hold' => 'on_hold',
                'closed' => 'completed',
                'cancel' => 'completed',
                default => 'not_started'
            };

            // Map priority to complexity
            $complexity = match (strtolower($ticket->ticket_priority ?? '')) {
                'high' => 'complex',
                'medium' => 'medium',
                'low' => 'simple',
                default => 'medium'
            };

            // Create activity from ticket
            $activityId = DB::table('delivery_support_activities')->insertGetId([
                'delivery_support_id' => $supportId,
                'delivery_support_phase_id' => $phaseId,
                'ticket_id' => $ticket->ticket_id, // Link activity to ticket
                'stage_id' => null,
                'name' => $ticket->ticket_number . ' - ' . ($ticket->description ?? "Ticket #{$ticket->ticket_id}"),
                'description' => $ticket->description,
                'order_sequence' => 1,
                'module' => null,
                'new_issue' => true,
                'object' => null,
                'incident_type' => $ticket->type ?? 'incident',
                'complexity' => $complexity,
                'deliverable' => null,
                'start_date' => $ticket->start_date ?? now(),
                'end_date' => $ticket->end_date,
                'status' => $status,
                'progress_percentage' => 0,
                'weight' => $ticket->man_days ?? 1,
                'notes' => "Auto-created from Ticket #{$ticket->ticket_id}",
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create planning entry for activity
            DB::table('delivery_support_planning')->insert([
                'delivery_support_id' => $supportId,
                'phase_id' => $phaseId,
                'parent_id' => $groupId,
                'activity_id' => $activityId,
                'name' => $ticket->ticket_number . ' - ' . ($ticket->description ?? "Ticket #{$ticket->ticket_id}"),
                'is_group' => false,
                'level' => 1,
                'order_sequence' => 1,
                'start_date' => $ticket->start_date ?? now(),
                'end_date' => $ticket->end_date,
                'weight' => $ticket->man_days ?? 1,
                'status' => $status,
                'progress_percentage' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Assign ticket PIC to activity if exists
            if ($ticket->employee_id) {
                DB::table('delivery_support_activity_employee')->insert([
                    'delivery_support_activity_id' => $activityId,
                    'employee_id' => $ticket->employee_id,
                    'role' => 'lead',
                    'allocation_percentage' => 100,
                    'is_active' => true,
                    'assigned_date' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();

            Log::info('Created delivery support from ticket', [
                'ticket_id' => $ticket->ticket_id,
                'ticket_number' => $ticket->ticket_number,
                'support_id' => $supportId,
                'support_name' => $request->name,
                'created_by' => $sessionUser['id']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Delivery support created and ticket assigned successfully',
                'data' => [
                    'support_id' => $supportId,
                    'support_name' => $request->name,
                    'support_type' => $request->type,
                    'activity_id' => $activityId
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating delivery support from ticket:', [
                'ticket_id' => $id,
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create delivery support',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

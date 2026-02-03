<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class TicketController extends Controller
{
    /**
     * Get user info for history
     */
    private function getUserInfo($sessionUser)
    {
        $roleName = match($sessionUser['role']['id']) {
            1 => 'Admin',
            2 => 'Employee',
            3 => 'Customer',
            default => 'Unknown'
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
     * Display a listing of tickets
     */
    public function index()
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

            // Admin: bisa lihat semua ticket tanpa pembatasan
            if ($sessionUser['role']['id'] == 1) {
                Log::info('Admin viewing all tickets');
                
                $tickets = Ticket::with(['customer.basicData', 'employee.basicData', 'members.basicData'])
                    ->orderBy('created_at', 'desc')
                    ->get();
                    
            // Employee: cek DSM qualification
            } elseif ($sessionUser['role']['id'] == 2) {
                $employeeId = $sessionUser['id'];
                
                // Cek apakah employee memiliki DSM qualification
                if (!$this->isEmployeeQualified($employeeId)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You are not qualified for this section. DSM qualification required.'
                    ], 403);
                }
                
                Log::info('Employee with DSM qualification viewing all tickets');
                
                // Employee dengan DSM bisa lihat semua ticket
                $tickets = Ticket::with(['customer.basicData', 'employee.basicData', 'members.basicData'])
                    ->orderBy('created_at', 'desc')
                    ->get();
                    
            // Customer: hanya bisa lihat tiket mereka sendiri
            } elseif ($sessionUser['role']['id'] == 3) {
                Log::info('Filtering for customer', ['customer_id' => $sessionUser['id']]);
                
                $tickets = Ticket::with(['customer.basicData', 'employee.basicData', 'members.basicData'])
                    ->where('customer_id', $sessionUser['id'])
                    ->orderBy('created_at', 'desc')
                    ->get();
                    
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid role'
                ], 403);
            }

            Log::info('Tickets fetched', ['count' => $tickets->count()]);

            // ✅ Transform data untuk frontend
            $ticketsData = $tickets->map(function($ticket) {
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
                    'customer_id' => $ticket->customer_id,
                    'employee_id' => $ticket->employee_id,
                    'description' => $ticket->description,
                    'ticket_priority' => $ticket->ticket_priority,
                    'jarvies_status' => $ticket->jarvies_status,
                    'status' => $ticket->status, 
                    'type' => $ticket->type,
                    'folder' => $ticket->folder,
                    'file_log' => $ticket->file_log,
                    'start_date' => $ticket->start_date,
                    'end_date' => $ticket->end_date,
                    'man_days' => $ticket->man_days,
                    'wait_close' => $ticket->wait_close,
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
                'trace' => $e->getTraceAsString()
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
        $user = session('user');
        $roleId = $user['role']['id'];
        
        // Validation rules berbeda untuk customer dan admin
        if ($roleId == 3) {
            // Customer
            $validated = $request->validate([
                'description' => 'required|string',
                'ticket_priority' => 'required|in:Low,Medium,High'
            ]);
            
            $validated['customer_id'] = $user['id']; // Auto set dari session
            $validated['status'] = 'open';
            $validated['jarvies_status'] = 'in process';
            
        } elseif ($roleId == 1) {
            // Admin
            $validated = $request->validate([
                'description' => 'required|string',
                'ticket_priority' => 'required|in:Low,Medium,High',
                'customer_id' => 'required|exists:customers,customer_id',
                'type' => 'nullable|in:AMS,MO,ATS,Project,Internal'
            ]);
            
            $validated['status'] = 'open';
            $validated['jarvies_status'] = 'in process';
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to create tickets'
            ], 403);
        }
        
        $ticket = Ticket::create($validated);
        
        return response()->json([
            'success' => true,
            'message' => 'Ticket created successfully',
            'data' => $ticket
        ]);
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

            // Customer: hanya tiket mereka sendiri
            if ($sessionUser['role']['id'] == 3) {
                $customerId = $sessionUser['id'];
                Log::info('My Tickets - Filtering for customer', ['customer_id' => $customerId]);
                
                $tickets = Ticket::with(['customer.basicData', 'employee.basicData', 'members.basicData'])
                    ->where('customer_id', $customerId)
                    ->orderBy('created_at', 'desc')
                    ->get();

            // Employee: cek DSM dan tampilkan tiket yang mereka handle
            } elseif ($sessionUser['role']['id'] == 2) {
                $employeeId = $sessionUser['id'];
                
                // Cek DSM qualification
                if (!$this->isEmployeeQualified($employeeId)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You are not qualified for this section. DSM qualification required.'
                    ], 403);
                }

                Log::info('My Tickets - Filtering for employee', ['employee_id' => $employeeId]);

                // Cek apakah employee_id valid
                $employeeExists = DB::table('employee')->where('employee_id', $employeeId)->exists();

                if (!$employeeExists) {
                    Log::error('Employee ID not found in database', ['employee_id' => $employeeId]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Employee data not found'
                    ], 404);
                }

                // Ticket yang employee handle sebagai PIC atau member
                $tickets = Ticket::with(['customer.basicData', 'employee.basicData', 'members.basicData'])
                    ->where(function($query) use ($employeeId) {
                        $query->where('ticket.employee_id', $employeeId)
                            ->orWhereHas('members', function($inner) use ($employeeId) {
                                $inner->where('ticket_member.employee_id', $employeeId);
                            });
                    })
                    ->orderBy('ticket.created_at', 'desc')
                    ->get();
                    
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid role'
                ], 403);
            }

            Log::info('My Tickets fetched', ['count' => $tickets->count()]);

            // ✅ Transform data dengan confirmation info
            $ticketsData = $tickets->map(function($ticket) {
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
                    'customer_id' => $ticket->customer_id,
                    'employee_id' => $ticket->employee_id,
                    'description' => $ticket->description,
                    'ticket_priority' => $ticket->ticket_priority,
                    'jarvies_status' => $ticket->jarvies_status,
                    'status' => $ticket->status, 
                    'type' => $ticket->type,
                    'folder' => $ticket->folder,
                    'file_log' => $ticket->file_log,
                    'start_date' => $ticket->start_date,
                    'end_date' => $ticket->end_date,
                    'man_days' => $ticket->man_days,
                    'wait_close' => $ticket->wait_close,
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
                'trace' => $e->getTraceAsString()
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
                'message' => 'Validation failed',
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
            if ($sessionUser['role']['id'] != 2) {
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
                'trace' => $e->getTraceAsString()
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
            
            if (!$sessionUser || $sessionUser['role']['id'] != 1) {
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
                'trace' => $e->getTraceAsString()
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
        
        if (!$sessionUser || $sessionUser['role']['id'] != 1) {
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
                ->where('confirmation_id', $confirmationId)  // GANTI 'id' jadi 'confirmation_id'
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
                    'jarvies_status' => 'in process',
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
                        'status' => 'confirmed',  // GANTI dari 'jarvies_status'
                        'confirmed_by' => $sessionUser['id'],
                        'confirmed_at' => now(),
                        'updated_at' => now()
                    ]);
            } else {
                // Reject - GANTI 'jarvies_status' jadi 'status'
                DB::table('ticket_confirmation')
                    ->where('confirmation_id', $confirmationId)
                    ->update([
                        'status' => 'rejected',  // GANTI dari 'jarvies_status'
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
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to process confirmation',
                'error' => $e->getMessage()
            ], 500);
        }
    }   

    /**
     * Update man days (Customer & Admin only)
     */
    public function updateManDays(Request $request, $id)
    {
        $sessionUser = session('user');
        
        // Only admin and customer can update man days
        if (!$sessionUser || !in_array($sessionUser['role']['id'], [1, 3])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only admin and customer can update man days.'
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
            
            // Customer can only update their own tickets
            if ($sessionUser['role']['id'] == 3 && $ticket->customer_id != $sessionUser['id']) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only update your own tickets'
                ], 403);
            }

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
                'trace' => $e->getTraceAsString()
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

            // Customer can only view their own ticket history
            if ($sessionUser['role']['id'] == 3 && $ticket->customer_id != $sessionUser['id']) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only view your own ticket history'
                ], 403);
            }

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
                'trace' => $e->getTraceAsString()
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

            // Customer hanya bisa lihat tiket mereka sendiri
            if ($sessionUser['role']['id'] == 3 && $ticket->customer_id != $sessionUser['id']) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only view your own tickets'
                ], 403);
            }

            // Employee harus punya DSM qualification (kecuali Admin)
            if ($sessionUser['role']['id'] == 2 && !$this->isEmployeeQualified($sessionUser['id'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not qualified for this section. DSM qualification required.'
                ], 403);
            }

            // Transform data
            $ticketData = [
                'ticket_id' => $ticket->ticket_id,
                'customer_id' => $ticket->customer_id,
                'employee_id' => $ticket->employee_id,
                'description' => $ticket->description,
                'ticket_priority' => $ticket->ticket_priority,
                'jarvies_status' => $ticket->jarvies_status,
                'type' => $ticket->type,
                'folder' => $ticket->folder,
                'file_log' => $ticket->file_log,
                'start_date' => $ticket->start_date,
                'end_date' => $ticket->end_date,
                'man_days' => $ticket->man_days,
                'wait_close' => $ticket->wait_close,
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
     * Update the specified ticket (Admin only - jarvies_status only)
     */
    public function update(Request $request, $id)
    {
        $sessionUser = session('user');
        
        // Only admin can update ticket status
        if (!$sessionUser || $sessionUser['role']['id'] != 1) {
            return response()->json([
                'success' => false,
                'message' => 'Only admin can update ticket status'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'jarvies_status' => 'required|string|in:in process,author action,proposed solution,closed,sent in to SAP,sent it to support',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $ticket = Ticket::findOrFail($id);
            
            // Only update jarvies_status
            $ticket->update([
                'jarvies_status' => $request->jarvies_status
            ]);
            
            $ticket->load(['customer.basicData', 'employee.basicData', 'members.basicData']);

            return response()->json([
                'success' => true,
                'data' => $ticket,
                'message' => 'Ticket status updated successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating ticket:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update ticket',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified ticket
     */
    public function destroy($id)
    {
        try {
            $sessionUser = session('user');
            
            if (!$sessionUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            // Employee harus punya DSM qualification
            if ($sessionUser['role']['id'] == 2 && !$this->isEmployeeQualified($sessionUser['id'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not qualified for this section. DSM qualification required.'
                ], 403);
            }

            $ticket = Ticket::findOrFail($id);
            
            // Delete ticket (members will be automatically deleted due to cascade)
            $ticket->delete();

            return response()->json([
                'success' => true,
                'message' => 'Ticket deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete ticket',
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
            if ($sessionUser['role']['id'] == 2 && !$this->isEmployeeQualified($sessionUser['id'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not qualified for this section. DSM qualification required.'
                ], 403);
            }

            $query = Ticket::with(['customer.basicData', 'employee.basicData', 'members.basicData'])
                ->where('jarvies_status', $status)
                ->orderBy('created_at', 'desc');

            // Customer hanya bisa lihat tiket mereka sendiri
            if ($sessionUser['role']['id'] == 3) {
                $query->where('customer_id', $sessionUser['id']);
            }
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
            if ($sessionUser['role']['id'] == 2 && !$this->isEmployeeQualified($sessionUser['id'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not qualified for this section. DSM qualification required.'
                ], 403);
            }

            $query = Ticket::query();

            // Customer hanya bisa lihat statistik tiket mereka sendiri
            if ($sessionUser['role']['id'] == 3) {
                $query->where('customer_id', $sessionUser['id']);
            }
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
                'by_type' => (clone $query)->selectRaw('type, COUNT(*) as count')
                    ->whereNotNull('type')
                    ->groupBy('type')
                    ->get()
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

    // Send mandays to customer (Admin only)
    public function sendToCustomer(Request $request, $ticketId)
    {
        $ticket = Ticket::findOrFail($ticketId);
        
        // Create negotiation record
        MandaysNegotiation::create([
            'ticket_id' => $ticketId,
            'proposed_mandays' => $ticket->man_days,
            'proposed_by' => 'admin',
            'proposed_by_user_id' => auth()->id(),
            'notes' => $request->notes,
            'status' => 'pending'
        ]);
        
        $ticket->update([
            'current_negotiated_mandays' => $ticket->man_days,
            'negotiation_status' => 'pending_customer'
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Mandays proposal sent to customer successfully'
        ]);
    }

    // Customer response to mandays proposal
    public function customerResponse(Request $request, $ticketId)
    {
        $request->validate([
            'action' => 'required|in:accept,counter,confirm',
            'proposed_mandays' => 'required_if:action,counter|numeric|min:0',
            'notes' => 'nullable|string'
        ]);
        
        $ticket = Ticket::findOrFail($ticketId);
        $lastNegotiation = $ticket->negotiations()->latest()->first();
        
        if ($request->action === 'confirm') {
            // Customer confirm final - bisa dilakukan kapan saja
            $lastNegotiation->update([
                'is_customer_confirmed' => true,
                'customer_confirmed_at' => now()
            ]);
            
            showNotification('Mandays confirmed by customer!', 'success');
        }else if ($request->action === 'accept') {
            // Customer accepts
            $lastNegotiation->update([
                'status' => 'accepted',
                'responded_at' => now()
            ]);
            
            $ticket->update([
                'man_days' => $ticket->current_negotiated_mandays,
                'negotiation_status' => 'accepted'
            ]);
            
            // Record in history
            MandaysHistory::create([
                'ticket_id' => $ticketId,
                'old_mandays' => $ticket->man_days,
                'new_mandays' => $ticket->current_negotiated_mandays,
                'changed_by' => auth()->id(),
                'changed_by_role' => 'Customer',
                'reason' => 'Accepted negotiation: ' . $request->notes
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Mandays accepted successfully'
            ]);
        } else {
            // Customer counters
            $lastNegotiation->update([
                'status' => 'countered',
                'responded_at' => now()
            ]);
            
            MandaysNegotiation::create([
                'ticket_id' => $ticketId,
                'proposed_mandays' => $request->proposed_mandays,
                'proposed_by' => 'customer',
                'proposed_by_user_id' => auth()->id(),
                'notes' => $request->notes,
                'status' => 'pending'
            ]);
            
            $ticket->update([
                'current_negotiated_mandays' => $request->proposed_mandays,
                'negotiation_status' => 'pending_admin'
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Counter proposal sent to admin successfully'
            ]);
        }
    }

    // Admin response to customer counter
    public function adminResponse(Request $request, $ticketId)
    {
        $request->validate([
            'action' => 'required|in:accept,counter',
            'proposed_mandays' => 'required_if:action,counter|numeric|min:0',
            'notes' => 'nullable|string'
        ]);
        
        $ticket = Ticket::findOrFail($ticketId);
        $lastNegotiation = $ticket->negotiations()->latest()->first();
        
        if ($request->action === 'accept') {
            // Admin accepts customer's counter
            $lastNegotiation->update([
                'status' => 'accepted',
                'responded_at' => now()
            ]);
            
            $ticket->update([
                'man_days' => $ticket->current_negotiated_mandays,
                'negotiation_status' => 'accepted'
            ]);
            
            // Record in history
            MandaysHistory::create([
                'ticket_id' => $ticketId,
                'old_mandays' => $ticket->man_days,
                'new_mandays' => $ticket->current_negotiated_mandays,
                'changed_by' => auth()->id(),
                'changed_by_role' => 'Admin',
                'reason' => 'Accepted customer counter: ' . $request->notes
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Customer proposal accepted successfully'
            ]);
        } else {
            // Admin counters again
            $lastNegotiation->update([
                'status' => 'countered',
                'responded_at' => now()
            ]);
            
            MandaysNegotiation::create([
                'ticket_id' => $ticketId,
                'proposed_mandays' => $request->proposed_mandays,
                'proposed_by' => 'admin',
                'proposed_by_user_id' => auth()->id(),
                'notes' => $request->notes,
                'status' => 'pending'
            ]);
            
            $ticket->update([
                'current_negotiated_mandays' => $request->proposed_mandays,
                'negotiation_status' => 'pending_customer'
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Counter proposal sent to customer successfully'
            ]);
        }
    }

    // Get negotiation history
    public function getNegotiationHistory($ticketId)
    {
        $negotiations = MandaysNegotiation::where('ticket_id', $ticketId)
            ->with('proposer')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($nego) {
                return [
                    'negotiation_id' => $nego->negotiation_id,
                    'proposed_mandays' => $nego->proposed_mandays,
                    'proposed_by' => $nego->proposed_by,
                    'proposer_name' => $nego->proposer ? $nego->proposer->name : 'System',
                    'notes' => $nego->notes,
                    'status' => $nego->status,
                    'created_at' => $nego->created_at,
                    'responded_at' => $nego->responded_at
                ];
            });
        
        return response()->json([
            'success' => true,
            'data' => $negotiations
        ]);
        
    } 
    
    /**
     * Update ticket status (status field, not jarvies_status)
     * Admin only
     */
    public function updateTicketStatus(Request $request, $id)
    {
        $sessionUser = session('user');
        
        if (!$sessionUser || $sessionUser['role']['id'] != 1) {
            return response()->json([
                'success' => false,
                'message' => 'Only admin can update ticket status'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:open,in_progress,hold,cancel,closed,reply',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $ticket = Ticket::findOrFail($id);
            
            $ticket->update([
                'status' => $request->status
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Ticket status updated successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating ticket status:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
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
            
            if (!$sessionUser || $sessionUser['role']['id'] != 1) {
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
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch member changes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update ticket members directly (Admin only)
     */
    public function updateMembers(Request $request, $id)
    {
        $sessionUser = session('user');
        
        if (!$sessionUser || $sessionUser['role']['id'] != 1) {
            return response()->json([
                'success' => false,
                'message' => 'Admin only'
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
            
            // Sync members (akan replace existing)
            $ticket->members()->sync($request->member_ids);

            return response()->json([
                'success' => true,
                'message' => 'Members updated successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating members:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
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
        
        // Only employee can request
        if (!$sessionUser || $sessionUser['role']['id'] != 2) {
            return response()->json([
                'success' => false,
                'message' => 'Only employees can request member changes'
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
                'message' => 'Member change request submitted. Waiting for admin approval.'
            ]);
        } catch (\Exception $e) {
            Log::error('Error requesting member change:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to request member change',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove member directly (Admin only)
     */
    public function removeMember($ticketId, $employeeId)
    {
        $sessionUser = session('user');
        
        if (!$sessionUser || $sessionUser['role']['id'] != 1) {
            return response()->json([
                'success' => false,
                'message' => 'Admin only'
            ], 403);
        }

        try {
            $ticket = Ticket::findOrFail($ticketId);
            
            // Detach member
            $ticket->members()->detach($employeeId);

            return response()->json([
                'success' => true,
                'message' => 'Member removed successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error removing member:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove member',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Request member removal (Employee/PIC only)
     */
    public function requestMemberRemoval($ticketId, $employeeId)
    {
        $sessionUser = session('user');
        
        if (!$sessionUser || $sessionUser['role']['id'] != 2) {
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
                'message' => 'Member removal request submitted. Waiting for admin approval.'
            ]);
        } catch (\Exception $e) {
            Log::error('Error requesting member removal:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
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
        
        if (!$sessionUser || $sessionUser['role']['id'] != 1) {
            return response()->json([
                'success' => false,
                'message' => 'Admin only'
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
                'trace' => $e->getTraceAsString()
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
            
            if (!$sessionUser || $sessionUser['role']['id'] != 1) {
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
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch confirmation',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
<?php

namespace App\Http\Controllers;

use App\Enums\RoleId;
use App\Exports\TicketExport;
use App\Http\Controllers\EmailController;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Notification;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketMessage;
use App\Services\SlaService;
use App\Services\StagingTicketService;
use App\Services\TicketNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class TicketController extends Controller
{
    public function __construct(private readonly TicketNumberService $ticketNumbers) {}

    /**
     * Get user info for history
     */
    private function getUserInfo($sessionUser)
    {
        $roleName = match($sessionUser['role']['id']) {
            RoleId::EC_ADMINISTRATOR->value    => 'Admin',
            RoleId::DELIVERY_SUPPORT_USER->value => 'Employee',
            RoleId::EC_USER->value => 'Customer',
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
     * Tidak menyentuh Graph API, aman dipanggil dari browser setiap 10 detik.
     */
    public function latestUpdate()
    {
        $row = DB::table('ticket')
            ->whereNull('deleted_at')
            ->selectRaw('MAX(GREATEST(COALESCE(last_message_at, created_at), updated_at)) AS latest')
            ->first();

        return response()->json(['latest_update' => $row->latest ?? null]);
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

            $filterUnassigned  = $request->boolean('unassigned');
            $isExternalEmployee = strtolower($sessionUser['employee_type'] ?? 'internal') === 'external';

            // External employee: hanya bisa lihat ticket yang dia handle (sebagai lead atau member)
            if ($isExternalEmployee && $sessionUser['role']['id'] !== RoleId::EC_ADMINISTRATOR->value) {
                Log::info('External employee viewing own tickets only', ['employee_id' => $sessionUser['id']]);
                $employeeId = $sessionUser['id'];
                $tickets = Ticket::with(['customer.basicData', 'endCustomer.basicData', 'ticketLead.basicData', 'members.basicData', 'sla.policy'])
                    ->whereNull('is_hidden')
                    ->where(function ($q) use ($employeeId) {
                        $q->where('ticket_lead_id', $employeeId)
                          ->orWhereHas('members', fn ($i) => $i->where('ticket_member.employee_id', $employeeId));
                    })
                    ->orderByRaw('COALESCE(last_message_at, created_at) DESC')
                    ->get();

            // Admin: bisa lihat semua ticket, atau filter unassigned jika ?unassigned=1
            } elseif ($sessionUser['role']['id'] === RoleId::EC_ADMINISTRATOR->value) {
                Log::info('Admin viewing tickets', ['unassigned' => $filterUnassigned]);

                $query = Ticket::with(['customer.basicData', 'endCustomer.basicData', 'ticketLead.basicData', 'members.basicData', 'sla.policy'])
                    ->whereNull('is_hidden')
                    ->orderByRaw('COALESCE(last_message_at, created_at) DESC');
                if ($filterUnassigned) {
                    $query->whereNull('ticket_lead_id');
                }
                $tickets = $query->get();

            // Employee: tampilkan ticket unassigned (belum ada PIC) — frontend /api/tickets maps to "Unassign" tab
            } elseif ($sessionUser['role']['id'] === RoleId::DELIVERY_SUPPORT_USER->value) {
                Log::info('Employee viewing unassigned tickets');
                $tickets = Ticket::with(['customer.basicData', 'endCustomer.basicData', 'ticketLead.basicData', 'members.basicData', 'sla.policy'])
                    ->whereNull('is_hidden')
                    ->whereNull('ticket_lead_id')
                    ->orderByRaw('COALESCE(last_message_at, created_at) DESC')
                    ->get();

            // Helpdesk, RPMO, Head of Project, Head of Support:
            // lihat semua ticket organisasi, atau filter unassigned jika ?unassigned=1
            } elseif (in_array(
                $sessionUser['role']['id'],
                array_merge(RoleId::HEAD_GROUP, RoleId::HELPDESK_GROUP),
                true
            )) {
                Log::info('Staff viewing tickets', ['role_id' => $sessionUser['role']['id'], 'unassigned' => $filterUnassigned]);

                $query = Ticket::with(['customer.basicData', 'endCustomer.basicData', 'ticketLead.basicData', 'members.basicData', 'sla.policy'])
                    ->whereNull('is_hidden')
                    ->orderByRaw('COALESCE(last_message_at, created_at) DESC');
                if ($filterUnassigned) {
                    $query->whereNull('ticket_lead_id');
                }
                $tickets = $query->get();

            // Support Manager: "All Tickets" = semua tiket organisasi (sama seperti Head)
            // "My Tickets" = hanya tiket dari delivery yang dia kelola (/api/tickets/my)
            } elseif ($sessionUser['role']['id'] === RoleId::DELIVERY_SUPPORT_MANAGER->value) {
                Log::info('Support Manager viewing all tickets', ['employee_id' => $sessionUser['id']]);

                $query = Ticket::with(['customer.basicData', 'endCustomer.basicData', 'ticketLead.basicData', 'members.basicData', 'sla.policy'])
                    ->whereNull('is_hidden')
                    ->orderByRaw('COALESCE(last_message_at, created_at) DESC');

                if ($filterUnassigned) {
                    $query->whereNull('ticket_lead_id');
                }
                $tickets = $query->get();

            } else {
                // Fallback: cek apakah role punya izin `tickets.inbox` di tabel role_menu.
                // Ini memungkinkan custom role yang diberi akses lewat UI bisa melihat semua tiket.
                $employee = Employee::find($sessionUser['id']);
                if (!$employee || !$employee->hasPermission('tickets.inbox')) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Access denied'
                    ], 403);
                }

                Log::info('Custom role viewing tickets via role_menu permission', [
                    'role_id' => $sessionUser['role']['id'],
                    'employee_id' => $sessionUser['id'],
                ]);

                $query = Ticket::with(['customer.basicData', 'endCustomer.basicData', 'ticketLead.basicData', 'members.basicData', 'sla.policy'])
                    ->whereNull('is_hidden')
                    ->orderByRaw('COALESCE(last_message_at, created_at) DESC');
                if ($filterUnassigned) {
                    $query->whereNull('ticket_lead_id');
                }
                $tickets = $query->get();
            }

            Log::info('Tickets fetched', ['count' => $tickets->count()]);

            // Weighted average progress per tiket dari consultant_mandays_detail
            $ticketIds   = $tickets->pluck('ticket_id')->toArray();
            $progressMap = \App\Http\Controllers\ConsultantWorkloadController::progressMapForTickets($ticketIds);

            // Tiket yang sudah dibaca oleh employee yang sedang login (hanya jika role punya fungsi istimewa ticket.read)
            $canReadFeature = (bool) \App\Models\Employee::find($sessionUser['id'])?->hasPermission('ticket.read');
            $readAtMap = $canReadFeature
                ? DB::table('ticket_reads')
                    ->where('employee_id', $sessionUser['id'])
                    ->whereIn('ticket_id', $ticketIds)
                    ->pluck('read_at', 'ticket_id')
                : collect();

            // Batch load support manager & admin per ticket via delivery_support_activities
            $deliverySupportMap = \App\Models\DeliverySupportActivity::with([
                'deliverySupport.supportManagers.basicData',
                'deliverySupport.supportAdmin.basicData',
            ])
            ->whereIn('ticket_id', $ticketIds)
            ->whereNotNull('ticket_id')
            ->get()
            ->keyBy('ticket_id')
            ->map(function ($activity) {
                $ds = $activity->deliverySupport;
                return [
                    'support_manager_name' => $ds?->supportManagers->pluck('basicData.first_name')->filter()->implode(', '),
                    'support_admin_name'   => $ds?->supportAdmin?->basicData?->first_name,
                ];
            });

            // Batch load approved customer mandays (latest approved version per ticket)
            $customerMandaysMap = \App\Models\CustomerMandays::whereIn('ticket_id', $ticketIds)
                ->where('status', 'approved')
                ->orderBy('version', 'desc')
                ->get()
                ->groupBy('ticket_id')
                ->map(fn($group) => $group->first()->total_mandays);

            // Batch-load semua pending confirmations sekaligus (hindari N+1)
            $confirmationMap = DB::table('ticket_confirmation')
                ->whereIn('ticket_id', $ticketIds)
                ->where('status', 'pending')
                ->get()
                ->groupBy('ticket_id');

            // ✅ Transform data untuk frontend
            $ticketsData = $tickets->map(function($ticket) use ($progressMap, $customerMandaysMap, $deliverySupportMap, $readAtMap, $canReadFeature, $confirmationMap) {
                $allProgress = $progressMap[$ticket->ticket_id]
                    ?? (float) ($ticket->progress_percentage ?? 0);

                $pendingConfirmations = $confirmationMap->get($ticket->ticket_id, collect());
                $pendingCount         = $pendingConfirmations->count();
                $pendingConfirmation  = $pendingConfirmations->first();

                return [
                    'ticket_id' => $ticket->ticket_id,
                    'ticket_number' => $ticket->ticket_number,
                    'customer_id' => $ticket->customer_id,
                    'ticket_lead_id' => $ticket->ticket_lead_id,
                    'description' => $ticket->description,
                    'ticket_priority' => $ticket->ticket_priority,
                    'ticket_type' => $ticket->ticket_type,
                    'scale' => $ticket->scale,
                    'status' => $ticket->status,
                    'channel' => $ticket->channel,
                    'email_thread_id' => $ticket->email_thread_id,
                    'folder' => $ticket->folder,
                    'file_log' => $ticket->file_log,
                    'start_date' => $ticket->start_date,
                    'end_date' => $ticket->end_date,
                    'man_days' => $ticket->man_days,
                    'customer_mandays' => $customerMandaysMap[$ticket->ticket_id] ?? null,
                    'progress_percentage' => (float) ($ticket->progress_percentage ?? 0),
                    'all_consultant_progress' => $allProgress,
                    'wait_close' => $ticket->wait_close,
                    'last_message_at' => $ticket->last_message_at,
                    'last_customer_reply_at' => $ticket->last_customer_reply_at,
                    'last_agent_reply_at' => $ticket->last_agent_reply_at,
                    'last_internal_note_at'        => $ticket->last_internal_note_at,
                    'last_internal_note_sender_id' => $ticket->last_internal_note_sender_id,
                    'is_read' => !$canReadFeature || (
                        $readAtMap->has($ticket->ticket_id)
                        && (!$ticket->last_message_at || \Carbon\Carbon::parse($readAtMap->get($ticket->ticket_id))->gte($ticket->last_message_at))
                    ),
                    'customer' => $ticket->customer ? [
                        'customer_id' => $ticket->customer->customer_id,
                        'customer_name' => $ticket->customer->basicData->name_1 ?? $ticket->customer->email,
                        'customer_code' => $ticket->customer->customer_code,
                    ] : null,
                    'end_customer_id'   => $ticket->end_customer_id,
                    'end_customer_name' => $ticket->endCustomer?->basicData?->name_1,
                    'employee' => $ticket->ticketLead ? [
                        'employee_id' => $ticket->ticketLead->employee_id,
                        'employee_name' => $ticket->ticketLead->basicData->first_name ?? 'Unknown',
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
                    'sla' => $ticket->sla ? [
                        'target_response_hours'   => $ticket->sla->policy?->response_hours,
                        'response_time_hours'     => $ticket->sla->validation_duration_hours,
                        'response_status'         => $ticket->sla->response_status,
                        'target_resolution_hours' => $ticket->sla->policy?->resolution_hours,
                        'resolution_due_at'       => $ticket->sla->resolution_due_at,
                        'resolution_time_hours'   => $ticket->sla->net_resolution_hours,
                        'resolution_status'       => $ticket->sla->resolution_status,
                    ] : null,
                    'support_manager' => $deliverySupportMap[$ticket->ticket_id]['support_manager_name'] ?? null,
                    'support_admin'   => $deliverySupportMap[$ticket->ticket_id]['support_admin_name'] ?? null,
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

    public function exportToExcel(Request $request)
    {
        $sessionUser = session('user');
        if (!$sessionUser) {
            abort(401);
        }

        $roleId = $sessionUser['role']['id'];
        $allowed = [RoleId::EC_ADMINISTRATOR->value, RoleId::DELIVERY_SUPPORT_HEAD->value, RoleId::DELIVERY_HELPDESK->value];
        if (!in_array($roleId, $allowed, true)) {
            abort(403);
        }

        $query = Ticket::with(['customer.basicData', 'endCustomer.basicData', 'ticketLead.basicData'])
            ->whereNull('is_hidden')
            ->orderBy('ticket_id', 'asc');

        // Status — dari card filter
        if ($request->filled('card_status')) {
            $query->where('status', $request->card_status);
        }
        // Status — dari column filter (bisa bersamaan dengan card_status)
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        // Priority
        if ($request->filled('priority')) {
            $query->where('ticket_priority', $request->priority);
        }
        // Scale
        if ($request->filled('scale')) {
            $query->where('scale', $request->scale);
        }
        // Ticket type
        if ($request->filled('type')) {
            $query->where('ticket_type', $request->type);
        }
        // Ticket number keyword
        if ($request->filled('ticket_number')) {
            $query->where('ticket_number', 'like', '%' . $request->ticket_number . '%');
        }
        // Description keyword
        if ($request->filled('description')) {
            $query->where('description', 'like', '%' . $request->description . '%');
        }
        // Date range — cocokkan start_date, fallback ke created_at
        if ($request->filled('date_from')) {
            $dateFrom = $request->date_from;
            $query->where(function ($q) use ($dateFrom) {
                $q->whereDate('start_date', '>=', $dateFrom)
                  ->orWhere(function ($q2) use ($dateFrom) {
                      $q2->whereNull('start_date')->whereDate('created_at', '>=', $dateFrom);
                  });
            });
        }
        if ($request->filled('date_to')) {
            $dateTo = $request->date_to;
            $query->where(function ($q) use ($dateTo) {
                $q->whereDate('start_date', '<=', $dateTo)
                  ->orWhere(function ($q2) use ($dateTo) {
                      $q2->whereNull('start_date')->whereDate('created_at', '<=', $dateTo);
                  });
            });
        }
        // Customer name (exact match, case-insensitive — sesuai frontend)
        if ($request->filled('customer')) {
            $customerName = $request->customer;
            $query->whereHas('customer.basicData', function ($q) use ($customerName) {
                $q->whereRaw('LOWER(name_1) = LOWER(?)', [$customerName]);
            });
        }
        // Ticket Lead / PIC name (by first_name, exact match — sesuai frontend)
        if ($request->filled('pic')) {
            $picName = $request->pic;
            $query->whereHas('ticketLead.basicData', function ($q) use ($picName) {
                $q->whereRaw('LOWER(first_name) = LOWER(?)', [$picName]);
            });
        }

        $tickets = $query->get();

        $ticketIds   = $tickets->pluck('ticket_id')->toArray();
        $progressMap = \App\Http\Controllers\ConsultantWorkloadController::progressMapForTickets($ticketIds);

        $customerMandaysMap = \App\Models\CustomerMandays::whereIn('ticket_id', $ticketIds)
            ->where('status', 'approved')
            ->orderBy('version', 'desc')
            ->get()
            ->groupBy('ticket_id')
            ->map(fn($g) => $g->first()->total_mandays);

        $rows = $tickets->map(function ($ticket) use ($progressMap, $customerMandaysMap) {
            return [
                'ticket_number'          => $ticket->ticket_number,
                'description'            => $ticket->description,
                'created_at'             => $ticket->created_at,
                'start_date'             => $ticket->start_date,
                'customer'               => ['customer_name' => $ticket->customer?->basicData?->name_1 ?? $ticket->customer?->email],
                'end_customer_name'      => $ticket->endCustomer?->basicData?->name_1,
                'employee'               => $ticket->ticketLead ? ['employee_name' => $ticket->ticketLead->basicData?->first_name ?? 'Unknown'] : null,
                'ticket_priority'        => $ticket->ticket_priority,
                'scale'                  => $ticket->scale,
                'status'                 => $ticket->status,
                'ticket_type'            => $ticket->ticket_type,
                'customer_mandays'       => $customerMandaysMap[$ticket->ticket_id] ?? null,
                'all_consultant_progress'=> $progressMap[$ticket->ticket_id] ?? (float)($ticket->progress_percentage ?? 0),
                'end_date'               => $ticket->end_date,
            ];
        });

        $filename = 'TICKET SUPPORT ' . now()->timezone('Asia/Jakarta')->format('dmY') . '.xlsx';

        return Excel::download(new TicketExport($rows), $filename);
    }

    public function store(Request $request)
    {
        $user   = session('user');
        $roleId = $user['role']['id'];

        // ── Admin (role 1) → langsung buat ticket (bypass staging) ────────────
        if ($roleId === RoleId::EC_ADMINISTRATOR->value) {
            $validated = $request->validate([
                'description'     => 'required|string',
                'ticket_priority' => 'required|in:Very High,High,Medium,Low',
                'ticket_type'     => 'required|string|in:Incident,Change Request,Service Request,EWA,RISE,Consult',
                'customer_id'     => 'required|exists:customer,customer_id',
                'scale'           => 'nullable|string|in:Simple,Medium,Complex',
                'name'            => 'nullable|string|max:255',
                'no_hp'           => 'nullable|string|max:255',
                'module'          => 'nullable|string|max:255',
                'client'          => 'nullable|string|max:255',
                'to_email'        => 'nullable|string|max:2000',
                'cc_emails'       => 'nullable|string|max:2000',
                'body'            => 'nullable|string',
                'attachments'     => 'nullable|array',
                'attachments.*'   => 'file|max:20480',
            ]);

            $body  = $validated['body'] ?? null;
            $files = $request->file('attachments', []);

            // "To" HANYA dari input manual — TIDAK auto-baca company email.
            // Default kosong (mis. EWA): email dikirim hanya ke CC contact.
            // Bisa lebih dari satu penerima, pisah koma (mirror perilaku CC).
            // Primary = elemen pertama; sisanya jadi additional toRecipients.
            $toList = [];
            if (!empty($validated['to_email'])) {
                foreach (array_filter(array_map('trim', explode(',', $validated['to_email']))) as $to) {
                    if (filter_var($to, FILTER_VALIDATE_EMAIL)
                        && !in_array(strtolower($to), array_map('strtolower', $toList), true)) {
                        $toList[] = $to;
                    }
                }
            }
            $toEmail      = $toList[0] ?? '';
            $additionalTo = array_slice($toList, 1);

            // Parse CC emails menjadi array format [{address,name}] untuk disimpan di ticket.
            $ccList = [];
            if (!empty($validated['cc_emails'])) {
                foreach (array_filter(array_map('trim', explode(',', $validated['cc_emails']))) as $cc) {
                    if (filter_var($cc, FILTER_VALIDATE_EMAIL)) {
                        $ccList[] = ['address' => $cc, 'name' => null];
                    }
                }
            }

            // ── Kirim email via Graph bila ada minimal satu penerima (To/CC) ──────
            set_time_limit(120);
            $emailResult    = null;
            $conversationId = null;
            $internetMsgId  = null;
            if ($toEmail !== '' || !empty($ccList)) {
                try {
                    $emailResult = (new EmailController())->sendTicketReply(
                        toEmail:            $toEmail,
                        subject:            '[JARVIES] ' . $validated['description'],
                        body:               $body ?? '',
                        inReplyTo:          null,
                        files:              $files,
                        ccList:             array_column($ccList, 'address'),
                        noRePrefix:         true,
                        additionalToEmails: $additionalTo,
                    );
                    $conversationId = $emailResult['conversation_id'] ?? null;
                    $internetMsgId  = $emailResult['internet_message_id'] ?? null;
                } catch (\Exception $e) {
                    Log::warning('TicketController@store (admin): email gagal (non-fatal)', [
                        'to_email' => $toEmail,
                        'error'    => $e->getMessage(),
                    ]);
                }
            }

            try {
                $ticket = DB::transaction(function () use ($validated, $toEmail, $toList, $ccList, $conversationId, $user) {
                    return Ticket::create([
                        'ticket_number'      => $this->ticketNumbers->generate(),
                        'customer_id'        => $validated['customer_id'],
                        'description'        => $validated['description'],
                        'ticket_priority'    => $validated['ticket_priority'],
                        'ticket_type'        => $validated['ticket_type'] ?: null,
                        'scale'              => $validated['scale'] ?? null,
                        'name'               => $validated['name'] ?? null,
                        'no_hp'              => $validated['no_hp'] ?? null,
                        'module'             => $validated['module'] ?? null,
                        'client'             => $validated['client'] ?? null,
                        'status'             => 'inprocess',
                        // channel 'email' agar composer To/CC selalu tersedia di halaman tiket,
                        // walau To dikosongkan saat create (bisa diisi manual saat balas).
                        'channel'            => 'email',
                        'email_thread_id'    => $conversationId,
                        // Simpan HANYA "To" manual (nullable) — bukan company email.
                        // submitted_by_email = primary; to_emails = seluruh daftar To
                        // (primary + tambahan) agar reply berikutnya tetap ke semua penerima.
                        'submitted_by_email' => $toEmail !== '' ? $toEmail : null,
                        'to_emails'          => !empty($toList) ? $toList : null,
                        'cc_emails'          => !empty($ccList) ? $ccList : null,
                    ]);
                });

                $message = null;
                if (!empty($body)) {
                    $message = TicketMessage::create([
                        'ticket_id'           => $ticket->ticket_id,
                        'sender_type'         => 'employee',
                        'sender_id'           => $user['employee_id'] ?? null,
                        'sender_email'        => null,
                        'sender_name'         => $user['name'] ?? null,
                        'message'             => strip_tags($body),
                        'message_html'        => $body,
                        'is_internal_note'    => false,
                        'channel'             => $emailResult ? 'email' : 'web',
                        'email_message_id'    => $internetMsgId,
                        'is_read_by_customer' => false,
                        'is_read_by_agent'    => true,
                    ]);
                }

                // Attachment: jika email terkirim, simpan metadata Graph; jika tidak,
                // simpan file lokal seperti sebelumnya.
                if ($emailResult && !empty($emailResult['attachments']) && $message) {
                    foreach ($emailResult['attachments'] as $att) {
                        \App\Models\TicketAttachment::create([
                            'ticket_id'           => $ticket->ticket_id,
                            'message_id'          => $message->id,
                            'uploaded_by_type'    => 'employee',
                            'uploaded_by_id'      => $user['employee_id'] ?? null,
                            'attachment_type'     => app(EmailController::class)->resolveAttachmentTypePublic($att['mime'] ?? ''),
                            'file_name'           => $att['name'],
                            'link_title'          => $att['name'],
                            'file_size'           => $att['size'] ?? 0,
                            'mime_type'           => $att['mime'] ?? 'application/octet-stream',
                            'is_inline'           => false,
                            'graph_attachment_id' => $att['graph_att_id'] ?? null,
                            'graph_message_id'    => $emailResult['graph_message_id'] ?? null,
                        ]);
                    }
                } elseif (!$emailResult && $files && $message) {
                    foreach ($files as $file) {
                        try {
                            $path = $file->store("ticket-attachments/{$ticket->ticket_id}", 'public');
                            \App\Models\TicketAttachment::create([
                                'ticket_id'        => $ticket->ticket_id,
                                'message_id'       => $message->id,
                                'uploaded_by_type' => 'employee',
                                'uploaded_by_id'   => $user['employee_id'] ?? null,
                                'attachment_type'  => 'file',
                                'file_name'        => $file->getClientOriginalName(),
                                'file_size'        => $file->getSize(),
                                'mime_type'        => $file->getMimeType(),
                                'is_inline'        => false,
                                'file_path'        => $path,
                            ]);
                        } catch (\Exception $e) {
                            Log::warning('TicketController@store (admin): gagal simpan attachment', [
                                'file' => $file->getClientOriginalName(), 'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }

                return response()->json([
                    'success'    => true,
                    'message'    => 'Ticket created successfully',
                    'data'       => $ticket,
                    'email_sent' => $emailResult !== null,
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
     * POST /api/tickets/helpdesk-create
     * Helpdesk membuat tiket langsung (bypass staging) + kirim email ke customer via Graph.
     */
    public function storeFromHelpdesk(Request $request)
    {
        $user     = session('user');
        $roleId   = $user['role']['id'];
        $employee = \App\Models\Employee::find($user['id'] ?? null);
        $canCreate = $employee && in_array('ui.ticket.btn-create', $employee->allPermissionSlugs());

        if (!$canCreate || $roleId === RoleId::EC_ADMINISTRATOR->value) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'customer_id'     => 'required|exists:customer,customer_id',
            'to_email'        => 'nullable|string|max:2000',
            'cc_emails'       => 'nullable|string|max:2000',
            'description'     => 'required|string|max:1000',
            'ticket_priority' => 'required|in:Very High,High,Medium,Low',
            'ticket_type'     => 'required|string|in:Incident,Change Request,Service Request,EWA,RISE,Consult',
            'scale'           => 'nullable|string|in:Simple,Medium,Complex',
            'name'            => 'nullable|string|max:255',
            'no_hp'           => 'nullable|string|max:255',
            'module'          => 'nullable|string|max:255',
            'client'          => 'nullable|string|max:255',
            'body'            => 'nullable|string',
            'attachments'     => 'nullable|array',
            'attachments.*'   => 'file|max:20480',
        ]);

        $customer = Customer::find($validated['customer_id']);
        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer tidak ditemukan.',
            ], 422);
        }

        // "To" HANYA dari input manual — TIDAK auto-baca company email ($customer->email).
        // Default kosong (mis. EWA): email hanya dikirim ke CC contact terdaftar.
        // Bisa lebih dari satu penerima, pisah koma (mirror perilaku CC).
        // Primary = elemen pertama; sisanya jadi additional toRecipients.
        $toList = [];
        if (!empty($validated['to_email'])) {
            foreach (array_filter(array_map('trim', explode(',', $validated['to_email']))) as $to) {
                if (filter_var($to, FILTER_VALIDATE_EMAIL)
                    && !in_array(strtolower($to), array_map('strtolower', $toList), true)) {
                    $toList[] = $to;
                }
            }
        }
        $toEmail      = $toList[0] ?? '';
        $additionalTo = array_slice($toList, 1);

        $ccList = [];
        if (!empty($validated['cc_emails'])) {
            foreach (array_filter(array_map('trim', explode(',', $validated['cc_emails']))) as $cc) {
                if (filter_var($cc, FILTER_VALIDATE_EMAIL)) {
                    $ccList[] = ['address' => $cc, 'name' => null];
                }
            }
        }
        $files = $request->file('attachments', []);

        // ── Kirim email via Graph ─────────────────────────────────────────────
        // Hanya kirim jika ada minimal satu penerima (To manual ATAU CC contact).
        // Untuk EWA umumnya To kosong → email tetap terkirim ke CC saja.
        set_time_limit(120);
        $emailResult    = null;
        $conversationId = null;
        $internetMsgId  = null;

        if ($toEmail !== '' || !empty($ccList)) {
            try {
                $emailCtrl   = new EmailController();
                $emailResult = $emailCtrl->sendTicketReply(
                    toEmail:            $toEmail,
                    subject:            '[JARVIES] ' . $validated['description'],
                    body:               $validated['body'] ?? '',
                    inReplyTo:          null,
                    files:              $files,
                    ccList:             array_column($ccList, 'address'),
                    noRePrefix:         true,
                    additionalToEmails: $additionalTo,
                );
                $conversationId = $emailResult['conversation_id'] ?? null;
                $internetMsgId  = $emailResult['internet_message_id'] ?? null;
            } catch (\Exception $e) {
                Log::warning('TicketController@storeFromHelpdesk: email gagal (non-fatal)', [
                    'to_email' => $toEmail,
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        // ── Buat ticket langsung (bypass staging) ────────────────────────────
        try {
            $ticket = DB::transaction(function () use ($validated, $customer, $toEmail, $toList, $ccList, $conversationId, $internetMsgId, $emailResult, $user) {
                $ticket = Ticket::create([
                    'ticket_number'      => $this->ticketNumbers->generate(),
                    'customer_id'        => $customer->customer_id,
                    'description'        => $validated['description'],
                    'ticket_priority'    => $validated['ticket_priority'],
                    'ticket_type'        => $validated['ticket_type'] ?: null,
                    'scale'              => $validated['scale'] ?: null,
                    'name'               => $validated['name'] ?? null,
                    'no_hp'              => $validated['no_hp'] ?? null,
                    'module'             => $validated['module'] ?? null,
                    'client'             => $validated['client'] ?? null,
                    'status'             => 'open',
                    // channel 'email' agar composer To/CC selalu tersedia di halaman tiket,
                    // walau To dikosongkan saat create (bisa diisi manual saat balas).
                    'channel'            => 'email',
                    'email_thread_id'    => $conversationId,
                    // Simpan HANYA "To" manual (nullable) — bukan company email — supaya
                    // balasan berikutnya juga tidak otomatis tertuju ke company email.
                    // submitted_by_email = primary; to_emails = seluruh daftar To
                    // (primary + tambahan) agar reply berikutnya tetap ke semua penerima.
                    'submitted_by_email' => $toEmail !== '' ? $toEmail : null,
                    'to_emails'          => !empty($toList) ? $toList : null,
                    'cc_emails'          => !empty($ccList) ? $ccList : null,
                    'last_message_at'    => now(),
                    'last_agent_reply_at'=> now(),
                ]);

                if (!empty($validated['body'])) {
                    TicketMessage::create([
                        'ticket_id'           => $ticket->ticket_id,
                        'sender_type'         => 'employee',
                        'sender_id'           => $user['employee_id'] ?? null,
                        'sender_email'        => null,
                        'sender_name'         => $user['name'] ?? null,
                        'message'             => strip_tags($validated['body']),
                        'message_html'        => $validated['body'],
                        'is_internal_note'    => false,
                        'channel'             => $emailResult ? 'email' : 'web',
                        'email_message_id'    => $internetMsgId,
                        'is_read_by_customer' => false,
                        'is_read_by_agent'    => true,
                    ]);
                }

                return $ticket;
            });

            // Attach SLA jika ticket_type eligible (Incident / Service Request)
            try {
                app(\App\Services\SlaService::class)->attachToTicket($ticket);
            } catch (\Throwable $e) {
                Log::warning('TicketController@storeFromHelpdesk: SLA attach gagal (non-fatal)', [
                    'ticket_id' => $ticket->ticket_id,
                    'error'     => $e->getMessage(),
                ]);
            }

            // Simpan metadata attachment di DB
            if ($emailResult && !empty($emailResult['attachments'])) {
                $msgId = $ticket->messages()->latest()->value('id');
                foreach ($emailResult['attachments'] as $att) {
                    TicketAttachment::create([
                        'ticket_id'           => $ticket->ticket_id,
                        'message_id'          => $msgId,
                        'uploaded_by_type'    => 'employee',
                        'uploaded_by_id'      => $user['employee_id'] ?? null,
                        'attachment_type'     => app(EmailController::class)->resolveAttachmentTypePublic($att['mime'] ?? ''),
                        'file_name'           => $att['name'],
                        'link_title'          => $att['name'],
                        'file_size'           => $att['size'] ?? 0,
                        'mime_type'           => $att['mime'] ?? 'application/octet-stream',
                        'is_inline'           => false,
                        'graph_attachment_id' => $att['graph_att_id'] ?? null,
                        'graph_message_id'    => $emailResult['graph_message_id'] ?? null,
                    ]);
                }
            }

            return response()->json([
                'success'        => true,
                'message'        => 'Ticket created successfully.',
                'ticket_id'      => $ticket->ticket_id,
                'ticket_number'  => $ticket->ticket_number,
                'email_sent'     => $emailResult !== null,
            ], 201);

        } catch (\Exception $e) {
            Log::error('TicketController@storeFromHelpdesk: gagal buat ticket', [
                'error'    => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat tiket: ' . $e->getMessage(),
            ], 500);
        }
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
                    'status'          => 'inprocess',
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
                    'ticket_lead_id',
                    'description',
                    'ticket_priority',
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

            $isExternalEmployee = strtolower($sessionUser['employee_type'] ?? 'internal') === 'external';

            // External employee (non-admin): hanya ticket yang mereka handle
            if ($isExternalEmployee && $sessionUser['role']['id'] !== RoleId::EC_ADMINISTRATOR->value) {
                $employeeId = $sessionUser['id'];
                Log::info('My Tickets - External employee', ['employee_id' => $employeeId]);
                $tickets = Ticket::with(['customer.basicData', 'endCustomer.basicData', 'ticketLead.basicData', 'members.basicData', 'sla.policy'])
                    ->whereNull('is_hidden')
                    ->where(function ($query) use ($employeeId) {
                        $query->where('ticket.ticket_lead_id', $employeeId)
                            ->orWhereHas('members', fn ($inner) => $inner->where('ticket_member.employee_id', $employeeId));
                    })
                    ->orderByRaw('COALESCE(ticket.last_message_at, ticket.created_at) DESC')
                    ->get();

            // Employee / Helpdesk: tampilkan tiket dimana mereka PIC atau member
            } elseif (in_array($sessionUser['role']['id'], array_merge([RoleId::DELIVERY_SUPPORT_USER->value], RoleId::HELPDESK_GROUP), true)) {
                $employeeId = $sessionUser['id'];

                Log::info('My Tickets - Filtering for employee/helpdesk', ['employee_id' => $employeeId]);

                // Ticket yang employee handle sebagai PIC atau member
                $tickets = Ticket::with(['customer.basicData', 'endCustomer.basicData', 'ticketLead.basicData', 'members.basicData', 'sla.policy'])
                    ->whereNull('is_hidden')
                    ->where(function($query) use ($employeeId) {
                        $query->where('ticket.ticket_lead_id', $employeeId)
                            ->orWhereHas('members', function($inner) use ($employeeId) {
                                $inner->where('ticket_member.employee_id', $employeeId);
                            });
                    })
                    ->orderByRaw('COALESCE(ticket.last_message_at, ticket.created_at) DESC')
                    ->get();

            // Support Manager: tampilkan ticket dari delivery yang dia kelola
            } elseif ($sessionUser['role']['id'] === RoleId::DELIVERY_SUPPORT_MANAGER->value) {
                $employeeId = $sessionUser['id'];

                Log::info('My Tickets - Filtering for support manager', ['employee_id' => $employeeId]);

                $managedDeliveryIds = DB::table('delivery_support_managers')
                    ->where('employee_id', $employeeId)
                    ->pluck('delivery_support_id');

                $managedTicketIds = DB::table('delivery_support_activities')
                    ->whereIn('delivery_support_id', $managedDeliveryIds)
                    ->whereNotNull('ticket_id')
                    ->pluck('ticket_id')
                    ->unique()
                    ->values();

                $tickets = Ticket::with(['customer.basicData', 'endCustomer.basicData', 'ticketLead.basicData', 'members.basicData', 'sla.policy'])
                    ->whereNull('is_hidden')
                    ->whereIn('ticket_id', $managedTicketIds)
                    ->orderByRaw('COALESCE(ticket.last_message_at, ticket.created_at) DESC')
                    ->get();

            } else {
                // Fallback for roles configured via web (not hardcoded here):
                // show tickets where the user is PIC or member.
                $employeeId = $sessionUser['id'];
                Log::info('My Tickets - Fallback for role', [
                    'employee_id' => $employeeId,
                    'role_id'     => $sessionUser['role']['id'] ?? null,
                ]);
                $tickets = Ticket::with(['customer.basicData', 'endCustomer.basicData', 'ticketLead.basicData', 'members.basicData', 'sla.policy'])
                    ->whereNull('is_hidden')
                    ->where(function ($query) use ($employeeId) {
                        $query->where('ticket.ticket_lead_id', $employeeId)
                            ->orWhereHas('members', function ($inner) use ($employeeId) {
                                $inner->where('ticket_member.employee_id', $employeeId);
                            });
                    })
                    ->orderByRaw('COALESCE(ticket.last_message_at, ticket.created_at) DESC')
                    ->get();
            }

            Log::info('My Tickets fetched', ['count' => $tickets->count()]);

            // Weighted average progress per tiket dari consultant_mandays_detail
            $myTicketIds   = $tickets->pluck('ticket_id')->toArray();
            $myProgressMap = \App\Http\Controllers\ConsultantWorkloadController::progressMapForTickets($myTicketIds);

            // Tiket yang sudah dibaca oleh employee yang sedang login (hanya jika role punya fungsi istimewa ticket.read)
            $myCanReadFeature = (bool) \App\Models\Employee::find($sessionUser['id'])?->hasPermission('ticket.read');
            $myReadAtMap = $myCanReadFeature
                ? DB::table('ticket_reads')
                    ->where('employee_id', $sessionUser['id'])
                    ->whereIn('ticket_id', $myTicketIds)
                    ->pluck('read_at', 'ticket_id')
                : collect();

            // Batch load approved customer mandays (latest approved version per ticket)
            $myCustomerMandaysMap = \App\Models\CustomerMandays::whereIn('ticket_id', $myTicketIds)
                ->where('status', 'approved')
                ->orderBy('version', 'desc')
                ->get()
                ->groupBy('ticket_id')
                ->map(fn($group) => $group->first()->total_mandays);

            // ✅ Transform data dengan confirmation info
            $ticketsData = $tickets->map(function($ticket) use ($myProgressMap, $myCustomerMandaysMap, $myReadAtMap, $myCanReadFeature) {
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
                    'ticket_lead_id' => $ticket->ticket_lead_id,
                    'description' => $ticket->description,
                    'ticket_priority' => $ticket->ticket_priority,
                    'ticket_type' => $ticket->ticket_type,
                    'scale' => $ticket->scale,
                    'status' => $ticket->status,
                    'channel' => $ticket->channel,
                    'email_thread_id' => $ticket->email_thread_id,
                    'folder' => $ticket->folder,
                    'file_log' => $ticket->file_log,
                    'start_date' => $ticket->start_date,
                    'end_date' => $ticket->end_date,
                    'man_days' => $ticket->man_days,
                    'customer_mandays' => $myCustomerMandaysMap[$ticket->ticket_id] ?? null,
                    'progress_percentage' => (float) ($ticket->progress_percentage ?? 0),
                    'all_consultant_progress' => $myAllProgress,
                    'wait_close' => $ticket->wait_close,
                    'last_message_at' => $ticket->last_message_at,
                    'last_customer_reply_at' => $ticket->last_customer_reply_at,
                    'last_agent_reply_at' => $ticket->last_agent_reply_at,
                    'last_internal_note_at'        => $ticket->last_internal_note_at,
                    'last_internal_note_sender_id' => $ticket->last_internal_note_sender_id,
                    'is_read' => !$myCanReadFeature || (
                        $myReadAtMap->has($ticket->ticket_id)
                        && (!$ticket->last_message_at || \Carbon\Carbon::parse($myReadAtMap->get($ticket->ticket_id))->gte($ticket->last_message_at))
                    ),
                    'customer' => $ticket->customer ? [
                        'customer_id' => $ticket->customer->customer_id,
                        'customer_name' => $ticket->customer->basicData->name_1 ?? $ticket->customer->email,
                        'customer_code' => $ticket->customer->customer_code,
                    ] : null,
                    'end_customer_id'   => $ticket->end_customer_id,
                    'end_customer_name' => $ticket->endCustomer?->basicData?->name_1,
                    'employee' => $ticket->ticketLead ? [
                        'employee_id' => $ticket->ticketLead->employee_id,
                        'employee_name' => $ticket->ticketLead->basicData->first_name ?? 'Unknown',
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
                    'sla' => $ticket->sla ? [
                        'target_response_hours'   => $ticket->sla->policy?->response_hours,
                        'response_time_hours'     => $ticket->sla->validation_duration_hours,
                        'response_status'         => $ticket->sla->response_status,
                        'target_resolution_hours' => $ticket->sla->policy?->resolution_hours,
                        'resolution_due_at'       => $ticket->sla->resolution_due_at,
                        'resolution_time_hours'   => $ticket->sla->net_resolution_hours,
                        'resolution_status'       => $ticket->sla->resolution_status,
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
            if ($sessionUser['role']['id'] !== RoleId::DELIVERY_SUPPORT_USER->value) {
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
            if ($ticket->ticket_lead_id !== null) {
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
            
            if (!$sessionUser || $sessionUser['role']['id'] !== RoleId::EC_ADMINISTRATOR->value) {
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
        
        if (!$sessionUser || $sessionUser['role']['id'] !== RoleId::EC_ADMINISTRATOR->value) {
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
                    'ticket_lead_id' => $confirmation->employee_id,
                    'man_days'       => $confirmation->man_days,
                    'start_date'     => now(),
                ]);

                // Attach members
                $memberIds = json_decode($confirmation->member_ids, true);
                if ($memberIds) {
                    $ticket->members()->sync($memberIds);
                }

                // Update confirmation
                DB::table('ticket_confirmation')
                    ->where('confirmation_id', $confirmationId)
                    ->update([
                        'status' => 'confirmed',
                        'confirmed_by' => $sessionUser['id'],
                        'confirmed_at' => now(),
                        'updated_at' => now()
                    ]);
            } else {
                // Reject
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
     * Get available Ticket Leads (employees with DSM qualification) — for admin/helpdesk/head assign
     */
    public function getAvailableTicketLeads()
    {
        $sessionUser = session('user');
        if (!$sessionUser) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $roleIds = $sessionUser['role_ids'] ?? [$sessionUser['role']['id'] ?? 0];
        $allowed = array_merge(
            [RoleId::EC_ADMINISTRATOR->value, RoleId::DELIVERY_SUPPORT_HEAD->value, RoleId::DELIVERY_SUPPORT_MANAGER->value],
            RoleId::HELPDESK_GROUP
        );
        if (!array_intersect($roleIds, $allowed)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $pics = Employee::withAnyRole([RoleId::DELIVERY_SUPPORT_USER->value])
            ->where('is_active', true)
            ->with('basicData:employee_id,first_name,last_name')
            ->get()
            ->map(fn($e) => [
                'employee_id' => $e->employee_id,
                'name'        => $e->basicData
                                    ? trim(($e->basicData->first_name ?? '') . ' ' . ($e->basicData->last_name ?? ''))
                                    : $e->eci,
            ])
            ->filter(fn($e) => $e['name'] !== '')
            ->sortBy('name')
            ->values();

        return response()->json(['success' => true, 'data' => $pics]);
    }

    /**
     * Assign Ticket Lead directly (Admin / Helpdesk / Head of Support only) — no confirmation needed
     */
    public function assignTicketLead(Request $request, $id)
    {
        $sessionUser = session('user');
        if (!$sessionUser) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $roleId = $sessionUser['role']['id'] ?? 0;
        $allowed = array_merge(
            [RoleId::EC_ADMINISTRATOR->value, RoleId::DELIVERY_SUPPORT_HEAD->value, RoleId::DELIVERY_SUPPORT_MANAGER->value],
            RoleId::HELPDESK_GROUP
        );
        if (!in_array($roleId, $allowed, true)) {
            return response()->json(['success' => false, 'message' => 'Only Admin, Helpdesk, Head of Support, or Support Manager can assign a Ticket Lead'], 403);
        }

        $validator = Validator::make($request->all(), [
            'ticket_lead_id' => 'required|exists:employee,employee_id',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $ticket = Ticket::findOrFail($id);

            $isFirstAssign = $ticket->ticket_lead_id === null;

            $leadName = DB::table('employee')
                ->join('employee_basic_data', 'employee.employee_id', '=', 'employee_basic_data.employee_id')
                ->where('employee.employee_id', $request->ticket_lead_id)
                ->selectRaw("TRIM(CONCAT(COALESCE(employee_basic_data.first_name,''), ' ', COALESCE(employee_basic_data.last_name,''))) as full_name")
                ->value('full_name');

            $updateData = array_filter([
                'ticket_lead_id' => $request->ticket_lead_id,
                'status'         => 'inprocess',
                'start_date'     => $isFirstAssign ? now() : null,
            ], fn ($v) => $v !== null);

            if ($leadName) {
                $updateData['pic'] = trim($leadName);
            }

            $ticket->update($updateData);

            return response()->json(['success' => true, 'message' => $isFirstAssign ? 'Ticket Lead assigned successfully' : 'Ticket Lead updated successfully']);
        } catch (\Exception $e) {
            Log::error('Error assigning Ticket Lead:', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to assign Ticket Lead'], 500);
        }
    }

    /**
     * Update the PIC (in charge) field for a ticket.
     * Accessible by team members: admin, helpdesk, head, ticket lead, active members.
     */
    public function updatePic(Request $request, $id)
    {
        $sessionUser = session('user');
        if (!$sessionUser) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'pic' => 'required|string|max:255',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $ticket = Ticket::with('members')->findOrFail($id);

        $userId = $sessionUser['id'];
        $roleId = $sessionUser['role']['id'] ?? 0;

        $isTeamMember = in_array($roleId, array_merge(
            RoleId::TICKET_MANAGER_GROUP,
            [RoleId::DELIVERY_SUPPORT_HEAD->value]
        ), true)
            || $ticket->ticket_lead_id == $userId
            || $ticket->members->contains('employee_id', $userId);

        if (!$isTeamMember) {
            return response()->json(['success' => false, 'message' => 'Only team members can update PIC'], 403);
        }

        $ticket->update(['pic' => $request->pic]);

        return response()->json(['success' => true, 'message' => 'PIC updated successfully']);
    }

    /**
     * Update man days (Customer & Admin only)
     */
    public function updateManDays(Request $request, $id)
    {
        $sessionUser = session('user');
        
        // Only admin can update man days
        if (!$sessionUser || $sessionUser['role']['id'] !== RoleId::EC_ADMINISTRATOR->value) {
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
            
            // Check if ticket is confirmed (has ticket_lead_id)
            if (!$ticket->ticket_lead_id) {
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

            $ticket = Ticket::with(['customer.basicData', 'endCustomer.basicData', 'ticketLead.basicData', 'members.basicData'])
                ->findOrFail($id);

            // Customer can only see their own tickets
            if ($sessionUser['role']['id'] === RoleId::EC_USER->value) {
                if ((int) $ticket->customer_id !== (int) $sessionUser['id']) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Access denied'
                    ], 403);
                }
            }

            // External employee: hanya bisa lihat ticket yang dia handle
            $isExternalEmployee = strtolower($sessionUser['employee_type'] ?? 'internal') === 'external';
            if ($isExternalEmployee && $sessionUser['role']['id'] !== RoleId::EC_ADMINISTRATOR->value) {
                $employeeId = $sessionUser['id'];
                $isLead     = (int) $ticket->ticket_lead_id === $employeeId;
                $isMember   = $ticket->members->contains('employee_id', $employeeId);
                if (!$isLead && !$isMember) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Access denied'
                    ], 403);
                }
            }

            // Employee harus punya DSM qualification (kecuali Admin)
            if ($sessionUser['role']['id'] === RoleId::DELIVERY_SUPPORT_USER->value && !$this->isEmployeeQualified($sessionUser['id'])) {
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
                'ticket_lead_id' => $ticket->ticket_lead_id,
                'description' => $ticket->description,
                'ticket_priority' => $ticket->ticket_priority,
                'ticket_type' => $ticket->ticket_type,
                'scale' => $ticket->scale,
                'status' => $ticket->status,
                'channel' => $ticket->channel,
                'folder' => $ticket->folder,
                'file_log' => $ticket->file_log,
                // Link folder deliverable OneDrive (scoped ke folder ticket saja).
                // Ini anonymous edit-link yang dibuat pada folder ticket di
                // TicketDeliverableController::store — customer yang membuka link
                // hanya melihat isi folder ticket ini (tidak bisa naik ke folder
                // customer/CUSTOMER DELIVERABLE, sehingga tidak melihat customer lain).
                // Null selama belum ada file deliverable yang diupload (folder lazy-create).
                'deliverable_folder_url' => $ticket->onedrive_folder_url,
                'has_deliverable_folder' => !empty($ticket->onedrive_folder_url),
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
                'end_customer_id'   => $ticket->end_customer_id,
                'end_customer_name' => $ticket->endCustomer?->basicData?->name_1,
                'employee' => $ticket->ticketLead ? [
                    'employee_id' => $ticket->ticketLead->employee_id,
                    'employee_name' => $ticket->ticketLead->basicData->first_name ?? 'Unknown',
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
        $isAdmin    = $roleId === RoleId::EC_ADMINISTRATOR->value;
        $isHelpdesk = in_array($roleId, RoleId::TICKET_MANAGER_GROUP, true);
        $isEmployee = $roleId !== RoleId::EC_USER->value && $roleId > 0;

        if (!$sessionUser) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        // External employee tidak boleh mengambil unassigned ticket maupun update apapun
        $isExternalEmployee = strtolower($sessionUser['employee_type'] ?? 'internal') === 'external';
        if ($isExternalEmployee && !$isAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'External employees cannot update tickets'
            ], 403);
        }

        // Employees other than admin/helpdesk may ONLY self-assign PIC on unassigned tickets.
        // All other fields require admin or helpdesk.
        $ticketForCheck = Ticket::find($id);
        $requestKeys    = array_keys($request->except(['_token', '_method']));
        $isSelfAssignOnly = !$isAdmin && !$isHelpdesk
            && $requestKeys === ['ticket_lead_id']
            && $ticketForCheck
            && $ticketForCheck->ticket_lead_id === null;

        if (!$isAdmin && !$isHelpdesk && !$isSelfAssignOnly) {
            return response()->json([
                'success' => false,
                'message' => 'Only admin or helpdesk can update ticket'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'ticket_priority' => 'sometimes|string|in:Very High,High,Medium,Low',
            'ticket_type'    => 'sometimes|nullable|string|in:Incident,Change Request,Service Request,EWA,RISE,Consult',
            'scale'          => 'sometimes|nullable|string|in:Simple,Medium,Complex',
            'ticket_lead_id' => 'sometimes|nullable|exists:employee,employee_id',
            'man_days'       => 'sometimes|nullable|numeric|min:0|max:9999.99',
            'name'           => 'sometimes|nullable|string|max:255',
            'no_hp'          => 'sometimes|nullable|string|max:255',
            'module'         => 'sometimes|nullable|string|max:255',
            'client'         => 'sometimes|nullable|string|max:255',
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

            if ($request->has('ticket_priority') && ($isAdmin || $isHelpdesk)) {
                $updateData['ticket_priority'] = $request->ticket_priority;
            }
            if ($request->has('ticket_type') && ($isAdmin || $isHelpdesk)) {
                $updateData['ticket_type'] = $request->ticket_type;
            }
            if ($request->has('scale') && ($isAdmin || $isHelpdesk)) {
                $updateData['scale'] = $request->scale;
            }
            if ($request->has('ticket_lead_id')) {
                $updateData['ticket_lead_id'] = $request->ticket_lead_id;
                // Jika sebelumnya unassigned dan sekarang di-assign Ticket Lead → otomatis inprocess
                if ($ticket->ticket_lead_id === null && !empty($request->ticket_lead_id)) {
                    $updateData['status'] = 'inprocess';
                }
            }
            if ($request->has('man_days') && $isAdmin) {
                $updateData['man_days'] = $request->man_days;
            }
            if ($request->has('name') && ($isAdmin || $isHelpdesk)) {
                $updateData['name'] = $request->name ?: null;
            }
            if ($request->has('no_hp') && ($isAdmin || $isHelpdesk)) {
                $updateData['no_hp'] = $request->no_hp ?: null;
            }
            if ($request->has('module') && ($isAdmin || $isHelpdesk)) {
                $updateData['module'] = $request->module ?: null;
            }
            if ($request->has('client') && ($isAdmin || $isHelpdesk)) {
                $updateData['client'] = $request->client ?: null;
            }

            if (!empty($updateData)) {
                $ticket->update($updateData);
            }

            $ticket->load(['customer.basicData', 'endCustomer.basicData', 'ticketLead.basicData', 'members.basicData']);

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
            if ($sessionUser['role']['id'] === RoleId::DELIVERY_SUPPORT_USER->value && !$this->isEmployeeQualified($sessionUser['id'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not qualified for this section. DSM qualification required.'
                ], 403);
            }

            $query = Ticket::with(['customer.basicData', 'endCustomer.basicData', 'ticketLead.basicData', 'members.basicData'])
                ->whereNull('is_hidden')
                ->where('status', $status)
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
            if ($sessionUser['role']['id'] === RoleId::DELIVERY_SUPPORT_USER->value && !$this->isEmployeeQualified($sessionUser['id'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not qualified for this section. DSM qualification required.'
                ], 403);
            }

            $query = Ticket::whereNull('is_hidden');

            // Admin (role_id = 1) dan Employee dengan DSM bisa lihat semua statistik

            $stats = [
                'total'                   => (clone $query)->count(),
                'open'                    => (clone $query)->where('status', 'open')->count(),
                'inprocess'               => (clone $query)->where('status', 'inprocess')->count(),
                'waiting_on_customer'     => (clone $query)->where('status', 'waiting_on_customer')->count(),
                'waiting_on_3rd_party'    => (clone $query)->where('status', 'waiting_on_3rd_party')->count(),
                'waiting_to_confirmation' => (clone $query)->where('status', 'waiting_to_confirmation')->count(),
                'hold'                    => (clone $query)->where('status', 'hold')->count(),
                'cancelled'               => (clone $query)->where('status', 'cancelled')->count(),
                'closed'                  => (clone $query)->where('status', 'closed')->count(),
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
     * Update ticket status — unified single field
     * Admin / Helpdesk only
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

        $allowed = 'open,inprocess,waiting_on_customer,waiting_on_3rd_party,waiting_to_confirmation,hold,cancelled,closed';

        $validator = Validator::make($request->all(), [
            'status' => "required|string|in:{$allowed}",
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => "Ticket status is invalid. Allowed values: {$allowed}.",
                'errors'  => $validator->errors()
            ], 422);
        }

        try {
            $ticket = Ticket::findOrFail($id);

            $ticket->update([
                'status' => $request->status
            ]);

            // Notifikasi bell Jarvies — status berubah
            if ($ticket->customer_id) {
                $statusLabel = match ($request->status) {
                    'closed'      => 'Closed',
                    'cancelled'   => 'Cancelled',
                    'open'        => 'Open',
                    'in_progress' => 'In Progress',
                    'resolved'    => 'Resolved',
                    default       => ucfirst(str_replace('_', ' ', $request->status)),
                };
                \App\Services\CustomerNotificationService::notify(
                    customerId: (int) $ticket->customer_id,
                    type:       in_array($request->status, ['closed', 'cancelled']) ? 'ticket_closed' : 'ticket_status_changed',
                    ticketId:   (int) $ticket->ticket_id,
                    fromName:   'Helpdesk Support',
                    preview:    'Your ticket #' . ($ticket->ticket_number ?? $ticket->ticket_id) . ' status has been updated to ' . $statusLabel . '.',
                    link:       '/tickets/' . $ticket->ticket_id,
                );
            }

            // Trigger SLA state transition (non-fatal)
            try {
                $ticket->load('sla');
                app(SlaService::class)->handleStatusChange($ticket, $request->status);
            } catch (\Throwable $e) {
                Log::warning('TicketController@updateTicketStatus: SLA handleStatusChange gagal (non-fatal)', [
                    'ticket_id' => $id,
                    'status'    => $request->status,
                    'error'     => $e->getMessage(),
                ]);
            }

            // Add system log and send email when ticket is closed or cancelled
            if (in_array($request->status, ['closed', 'cancelled'])) {
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

                $customerEmail = $ticket->customer?->email
                    ?? Customer::find($ticket->customer_id)?->email;

                if ($customerEmail) {
                    $ticketNum    = $ticket->ticket_number ?? $ticket->ticket_id;
                    // Subject HARUS sama dengan subject thread ("[JARVIES] #XXXX : desc") agar
                    // notifikasi status tetap satu thread di Outlook/Exchange (status sudah
                    // dijelaskan di body). Subject berbeda → Exchange reset Thread-Index → pecah.
                    $subject      = '[JARVIES] #' . $ticketNum . ' : ' . mb_substr($ticket->description ?? '', 0, 80);
                    $htmlBody     = '<p>Your ticket <strong>#' . htmlspecialchars((string) $ticketNum) . '</strong> has been <strong>' . $label . '</strong>.</p>'
                                  . '<p>' . htmlspecialchars($logMessage) . '</p>';
                    $inReplyTo    = TicketMessage::where('ticket_id', $ticket->ticket_id)
                        ->where('channel', 'email')
                        ->whereNotNull('email_message_id')
                        ->orderByDesc('created_at')
                        ->value('email_message_id');
                    $threadId     = $ticket->email_thread_id;
                    $ticketId_    = $id;

                    dispatch(function () use ($customerEmail, $subject, $htmlBody, $inReplyTo, $threadId, $ticketId_) {
                        try {
                            (new EmailController())->sendTicketReply(
                                $customerEmail, $subject, $htmlBody, $inReplyTo, [], [], true, $threadId
                            );
                        } catch (\Exception $e) {
                            Log::warning('updateTicketStatus: email notification failed', [
                                'error'     => $e->getMessage(),
                                'ticket_id' => $ticketId_,
                            ]);
                        }
                    })->afterResponse();
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
            
            if (!$sessionUser || $sessionUser['role']['id'] !== RoleId::EC_ADMINISTRATOR->value) {
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
        $roleIds = array_map('intval', $sessionUser['role_ids'] ?? [$sessionUser['role']['id']]);
        $isAdmin    = (bool) array_intersect($roleIds, [RoleId::EC_ADMINISTRATOR->value]);
        $isHelpdesk = (bool) array_intersect($roleIds, RoleId::TICKET_MANAGER_GROUP);
        $isPic      = in_array(RoleId::DELIVERY_SUPPORT_USER->value, $roleIds, true) && $ticket->ticket_lead_id == $sessionUser['id'];

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
            if ($ticket->ticket_lead_id == $empId) {
                return response()->json([
                    'success' => false,
                    'message' => 'The assigned PIC cannot also be added as a member.',
                ], 422);
            }

            // Cek apakah sudah ada record (aktif atau nonaktif)
            $existing = DB::table('ticket_member')
                ->where('ticket_id', $ticket->ticket_id)
                ->where('employee_id', $empId)
                ->first();

            $isReactivation = false;
            if ($existing) {
                if ($existing->is_active) {
                    return response()->json(['success' => false, 'message' => 'Employee is already a member.'], 422);
                }
                // Reaktivasi member yang sebelumnya dinonaktifkan
                DB::table('ticket_member')
                    ->where('ticket_id', $ticket->ticket_id)
                    ->where('employee_id', $empId)
                    ->update(['is_active' => true, 'updated_at' => now()]);
                $isReactivation = true;
            } else {
                $ticket->members()->attach($empId, ['is_active' => true]);
            }

            // Return semua members (aktif + nonaktif) untuk UI
            $ticket->load('allMembers.basicData');
            $members = $this->formatAllMembers($ticket);

            // Notifikasi
            $actorId   = (int) $sessionUser['id'];
            $actorName = $sessionUser['name'] ?? $sessionUser['email'] ?? 'Someone';
            $added     = $ticket->allMembers->firstWhere('employee_id', $empId);
            $addedName = $added
                ? trim(($added->basicData->first_name ?? '') . ' ' . ($added->basicData->last_name ?? ''))
                : "Employee #{$empId}";
            $this->sendMemberNotifications($ticket, $actorId, $actorName, $empId, $addedName, $isReactivation ? 'reactivated' : 'added');

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

    /** Format allMembers collection untuk response JSON */
    private function formatAllMembers(Ticket $ticket): array
    {
        return $ticket->allMembers->map(fn ($m) => [
            'employee_id' => $m->employee_id,
            'name'        => trim(($m->basicData->first_name ?? '') . ' ' . ($m->basicData->last_name ?? '')),
            'is_active'   => (bool) $m->pivot->is_active,
        ])->values()->toArray();
    }

    /**
     * Kirim notifikasi ke member yang terdampak dan ke semua member aktif lain.
     *
     * @param Ticket $ticket         Ticket yang sudah di-load allMembers.basicData
     * @param int    $actorId        employee_id pelaku aksi
     * @param string $actorName      nama pelaku
     * @param int    $targetId       employee_id member yang ditambah/dinonaktifkan/diaktifkan
     * @param string $targetName     nama member yang ditambak/dinonaktifkan/diaktifkan
     * @param string $action         'added' | 'removed' | 'reactivated'
     */
    private function sendMemberNotifications(
        Ticket $ticket,
        int    $actorId,
        string $actorName,
        int    $targetId,
        string $targetName,
        string $action
    ): void {
        $ticketNumber = $ticket->ticket_number ?? "#{$ticket->ticket_id}";
        $link         = "/ticket/{$ticket->ticket_id}";
        $type         = "ticket_member_{$action}";

        $msgToTarget = match ($action) {
            'added'       => "You have been added to ticket {$ticketNumber} by {$actorName}.",
            'removed'     => "You have been removed from ticket {$ticketNumber} by {$actorName}.",
            'reactivated' => "You have been re-added to ticket {$ticketNumber} by {$actorName}.",
            default       => "Your membership on ticket {$ticketNumber} was updated by {$actorName}.",
        };

        $msgToOthers = match ($action) {
            'added'       => "{$targetName} has been added to ticket {$ticketNumber} by {$actorName}.",
            'removed'     => "{$targetName} has been removed from ticket {$ticketNumber} by {$actorName}.",
            'reactivated' => "{$targetName} has been re-added to ticket {$ticketNumber} by {$actorName}.",
            default       => "{$targetName}'s membership on ticket {$ticketNumber} was updated by {$actorName}.",
        };

        // Notif ke member yang terdampak (bukan aktor)
        if ($targetId !== $actorId) {
            Notification::create([
                'employee_id'      => $targetId,
                'type'             => $type,
                'ticket_id'        => $ticket->ticket_id,
                'from_employee_id' => $actorId,
                'from_name'        => $actorName,
                'preview'          => $msgToTarget,
                'link'             => $link,
                'is_read'          => false,
            ]);
        }

        // Notif ke semua member aktif lain (kecuali target dan aktor)
        $ticket->allMembers
            ->filter(fn ($m) => (bool) $m->pivot->is_active
                             && $m->employee_id !== $targetId
                             && $m->employee_id !== $actorId)
            ->each(function ($m) use ($actorId, $actorName, $type, $ticket, $link, $msgToOthers) {
                Notification::create([
                    'employee_id'      => $m->employee_id,
                    'type'             => $type,
                    'ticket_id'        => $ticket->ticket_id,
                    'from_employee_id' => $actorId,
                    'from_name'        => $actorName,
                    'preview'          => $msgToOthers,
                    'link'             => $link,
                    'is_read'          => false,
                ]);
            });
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
        $isAdmin    = $roleId === RoleId::EC_ADMINISTRATOR->value;
        $isHelpdesk = in_array($roleId, RoleId::TICKET_MANAGER_GROUP, true);
        $isPic      = $roleId === RoleId::DELIVERY_SUPPORT_USER->value && $ticket->ticket_lead_id == $sessionUser['id'];

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
        
        $allowedRoles = [RoleId::DELIVERY_SUPPORT_USER->value, RoleId::DELIVERY_HELPDESK->value, RoleId::DELIVERY_RPMO_HEAD->value];
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
            if ($ticket->ticket_lead_id != $sessionUser['id']) {
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

        $ticket  = Ticket::with('members.basicData')->findOrFail($ticketId);
        $roleIds = array_map('intval', $sessionUser['role_ids'] ?? [$sessionUser['role']['id']]);
        $isAdmin    = in_array(RoleId::EC_ADMINISTRATOR->value, $roleIds, true);
        $isHoS      = in_array(RoleId::DELIVERY_SUPPORT_HEAD->value, $roleIds, true);
        $isManager  = in_array(RoleId::DELIVERY_SUPPORT_MANAGER->value, $roleIds, true);
        $isHelpdesk = (bool) array_intersect($roleIds, RoleId::HELPDESK_GROUP);
        $isPic      = in_array(RoleId::DELIVERY_SUPPORT_USER->value, $roleIds, true) && $ticket->ticket_lead_id == $sessionUser['id'];

        if (!$isAdmin && !$isHoS && !$isManager && !$isHelpdesk && !$isPic) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak memiliki akses untuk menghapus member.'
            ], 403);
        }

        try {
            $ticket->load('allMembers.basicData');
            $member     = $ticket->allMembers->firstWhere('employee_id', $employeeId);
            $memberName = $member
                ? trim(($member->basicData->first_name ?? '') . ' ' . ($member->basicData->last_name ?? ''))
                : null;

            // Nonaktifkan — tidak dihapus agar bisa direaktivasi
            DB::table('ticket_member')
                ->where('ticket_id', $ticket->ticket_id)
                ->where('employee_id', $employeeId)
                ->update(['is_active' => false, 'updated_at' => now()]);

            $ticket->load('allMembers.basicData');

            // Notifikasi
            $actorId   = (int) $sessionUser['id'];
            $actorName = $sessionUser['name'] ?? $sessionUser['email'] ?? 'Someone';
            $this->sendMemberNotifications($ticket, $actorId, $actorName, (int) $employeeId, $memberName ?? "Employee #{$employeeId}", 'removed');

            return response()->json([
                'success'       => true,
                'message'       => 'Member deactivated successfully',
                'employee_name' => $memberName,
                'data'          => $this->formatAllMembers($ticket),
            ]);
        } catch (\Exception $e) {
            Log::error('Error deactivating member:', [
                'error'    => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to deactivate member',
            ], 500);
        }
    }

    /**
     * Request member removal (Employee/PIC only)
     */
    public function requestMemberRemoval($ticketId, $employeeId)
    {
        $sessionUser = session('user');
        
        if (!$sessionUser || $sessionUser['role']['id'] !== RoleId::DELIVERY_SUPPORT_USER->value) {
            return response()->json([
                'success' => false,
                'message' => 'Only employees can request member removal'
            ], 403);
        }

        try {
            $ticket = Ticket::findOrFail($ticketId);
            
            // Check if employee is the PIC
            if ($ticket->ticket_lead_id != $sessionUser['id']) {
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
        
        $allowedRoles = [RoleId::EC_ADMINISTRATOR->value, RoleId::DELIVERY_SUPPORT_HEAD->value];
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
                    // Nonaktifkan semua yang tidak ada di list baru, aktifkan yang ada
                    $now = now();
                    DB::table('ticket_member')
                        ->where('ticket_id', $ticket->ticket_id)
                        ->whereNotIn('employee_id', $memberIds)
                        ->update(['is_active' => false, 'updated_at' => $now]);

                    foreach ($memberIds as $empId) {
                        DB::table('ticket_member')->updateOrInsert(
                            ['ticket_id' => $ticket->ticket_id, 'employee_id' => $empId],
                            ['is_active' => true, 'updated_at' => $now]
                        );
                    }
                } else if ($changeRequest->change_type === 'remove') {
                    // Nonaktifkan member yang diminta dihapus
                    DB::table('ticket_member')
                        ->where('ticket_id', $ticket->ticket_id)
                        ->whereIn('employee_id', $memberIds)
                        ->update(['is_active' => false, 'updated_at' => now()]);
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
            
            if (!$sessionUser || $sessionUser['role']['id'] !== RoleId::EC_ADMINISTRATOR->value) {
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

            // Admin, Helpdesk, and Head of Support can assign tickets to support
            if (!in_array($sessionUser['role']['id'], [RoleId::EC_ADMINISTRATOR->value, RoleId::DELIVERY_HELPDESK->value, RoleId::DELIVERY_SUPPORT_HEAD->value], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only Admin, Helpdesk, and Delivery Support Head can assign tickets to delivery support'
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

        // Admin, Helpdesk, and Head of Support can assign tickets
        if (!in_array($sessionUser['role']['id'], [RoleId::EC_ADMINISTRATOR->value, RoleId::DELIVERY_HELPDESK->value, RoleId::DELIVERY_SUPPORT_HEAD->value], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Only Admin, Helpdesk, and Delivery Support Head can assign tickets to delivery support'
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

            // Check if ticket is already assigned to this exact support
            $sameSupport = DB::table('delivery_support_activities')
                ->where('delivery_support_id', $supportId)
                ->where('ticket_id', $ticket->ticket_id)
                ->exists();

            if ($sameSupport) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'This ticket is already assigned to this delivery support.',
                ], 400);
            }

            // If ticket is in a different DS, remove the link there first (one DS per ticket rule)
            DB::table('delivery_support_activities')
                ->where('ticket_id', $ticket->ticket_id)
                ->whereNot('delivery_support_id', $supportId)
                ->update(['ticket_id' => null, 'updated_at' => now()]);

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

            // Map unified ticket status to activity status
            $status = match ($ticket->status ?? '') {
                'inprocess', 'waiting_on_customer', 'waiting_on_3rd_party', 'waiting_to_confirmation' => 'in_progress',
                'hold'      => 'on_hold',
                'closed', 'cancelled' => 'completed',
                default     => 'not_started',
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

            // Assign ticket lead to activity if exists
            if ($ticket->ticket_lead_id) {
                DB::table('delivery_support_activity_employee')->insert([
                    'delivery_support_activity_id' => $activityId,
                    'employee_id' => $ticket->ticket_lead_id,
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

            // Now that the ticket is linked to a delivery support, apply the SLA policy
            // (policy could not be matched at validation time because DS was not yet assigned)
            app(\App\Services\SlaService::class)->syncPolicy($ticket);

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
            'type'           => 'required|in:AMS,MO,ATS,CR,RISE,CLOUD,POSTPAID,Project,Internal',
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
                'open'                    => 'not_started',
                'inprocess'               => 'in_progress',
                'waiting_on_customer',
                'waiting_on_3rd_party',
                'waiting_to_confirmation' => 'in_progress',
                'hold'                    => 'on_hold',
                'closed',
                'cancelled'               => 'completed',
                default                   => 'not_started',
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

            // Assign ticket lead to activity if exists
            if ($ticket->ticket_lead_id) {
                DB::table('delivery_support_activity_employee')->insert([
                    'delivery_support_activity_id' => $activityId,
                    'employee_id' => $ticket->ticket_lead_id,
                    'role' => 'lead',
                    'allocation_percentage' => 100,
                    'is_active' => true,
                    'assigned_date' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();

            // Apply SLA policy now that ticket is linked to a delivery support
            app(\App\Services\SlaService::class)->syncPolicy($ticket);

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

    /**
     * Hide a ticket (set is_hidden = 1).
     * Requires permission: ticket.hide
     */
    public function hide($id)
    {
        $sessionUser = session('user');
        if (!$sessionUser) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $employee = Employee::find($sessionUser['id'] ?? null);
        if (!$employee || !$employee->hasPermission('ticket.hide')) {
            return response()->json(['success' => false, 'message' => 'Access denied'], 403);
        }

        try {
            $ticket = Ticket::findOrFail($id);

            if ($ticket->is_hidden) {
                return response()->json(['success' => false, 'message' => 'Ticket is already hidden'], 422);
            }

            $ticket->update(['is_hidden' => 1]);

            Log::info('Ticket hidden', [
                'ticket_id'   => $id,
                'hidden_by'   => $sessionUser['id'],
                'hidden_by_name' => $sessionUser['name'] ?? null,
            ]);

            return response()->json(['success' => true, 'message' => 'Ticket has been hidden']);
        } catch (\Exception $e) {
            Log::error('TicketController@hide: error', ['ticket_id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to hide ticket'], 500);
        }
    }

    /**
     * Unhide a ticket (set is_hidden = null).
     * Requires permission: ticket.hide
     */
    public function unhide($id)
    {
        $sessionUser = session('user');
        if (!$sessionUser) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $employee = Employee::find($sessionUser['id'] ?? null);
        if (!$employee || !$employee->hasPermission('ticket.hide')) {
            return response()->json(['success' => false, 'message' => 'Access denied'], 403);
        }

        try {
            $ticket = Ticket::findOrFail($id);

            if (!$ticket->is_hidden) {
                return response()->json(['success' => false, 'message' => 'Ticket is not hidden'], 422);
            }

            $ticket->update(['is_hidden' => null]);

            Log::info('Ticket unhidden', [
                'ticket_id'     => $id,
                'unhidden_by'   => $sessionUser['id'],
                'unhidden_by_name' => $sessionUser['name'] ?? null,
            ]);

            return response()->json(['success' => true, 'message' => 'Ticket is now visible again']);
        } catch (\Exception $e) {
            Log::error('TicketController@unhide: error', ['ticket_id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to unhide ticket'], 500);
        }
    }

    /**
     * List all hidden tickets.
     * Requires permission: management.hidden-tickets
     */
    public function hiddenIndex()
    {
        $sessionUser = session('user');
        if (!$sessionUser) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $employee = Employee::find($sessionUser['id'] ?? null);
        if (!$employee || !$employee->hasPermission('management.hidden-tickets')) {
            return response()->json(['success' => false, 'message' => 'Access denied'], 403);
        }

        try {
            $tickets = Ticket::with(['customer.basicData', 'endCustomer.basicData', 'ticketLead.basicData', 'members.basicData'])
                ->where('is_hidden', 1)
                ->orderByRaw('COALESCE(last_message_at, created_at) DESC')
                ->get();

            $data = $tickets->map(function ($ticket) {
                return [
                    'ticket_id'      => $ticket->ticket_id,
                    'ticket_number'  => $ticket->ticket_number,
                    'description'    => $ticket->description,
                    'ticket_priority'=> $ticket->ticket_priority,
                    'ticket_type'    => $ticket->ticket_type,
                    'status'         => $ticket->status,
                    'is_hidden'      => $ticket->is_hidden,
                    'customer'       => $ticket->customer ? [
                        'customer_id'   => $ticket->customer->customer_id,
                        'customer_name' => $ticket->customer->basicData->name_1 ?? $ticket->customer->email,
                        'customer_code' => $ticket->customer->customer_code,
                    ] : null,
                    'end_customer_name' => $ticket->endCustomer?->basicData?->name_1,
                    'employee'       => $ticket->ticketLead ? [
                        'employee_id'   => $ticket->ticketLead->employee_id,
                        'employee_name' => $ticket->ticketLead->basicData->first_name ?? 'Unknown',
                    ] : null,
                    'members'        => $ticket->members->map(fn($m) => [
                        'employee_id'   => $m->employee_id,
                        'employee_name' => $m->basicData->first_name ?? 'Unknown',
                    ]),
                    'start_date'     => $ticket->start_date,
                    'end_date'       => $ticket->end_date,
                    'created_at'     => $ticket->created_at,
                    'updated_at'     => $ticket->updated_at,
                ];
            });

            return response()->json([
                'success' => true,
                'data'    => $data,
                'message' => 'Hidden tickets retrieved successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('TicketController@hiddenIndex: error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve hidden tickets'], 500);
        }
    }
}

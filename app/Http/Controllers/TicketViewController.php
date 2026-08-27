<?php

namespace App\Http\Controllers;

use App\Enums\RoleId;
use App\Models\Employee;
use App\Models\Notification;
use App\Models\Ticket;
use App\Models\Customer;
use App\Models\CustomerMandays;
use App\Models\Module;
use App\Models\TicketSlaPause;
use App\Support\SessionUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketViewController extends Controller
{
    /**
     * Convert session user array to object format for Blade views
     */
    private function getUserObject(): ?SessionUser
    {
        return SessionUser::fromSession(session('user'));
    }

    /**
     * Display ticket index/list view
     */
    public function index()
    {
        $user = $this->getUserObject();

        if (!$user) {
            return redirect()->route('login');
        }

        // Get customers for create ticket dropdown (all users with btn-create permission)
        $customers = [];
        $employee = \App\Models\Employee::find($user->id);
        if ($employee && in_array('ui.ticket.btn-create', $employee->allPermissionSlugs())) {
            $customers = Customer::with('basicData')
                ->customers() // tiket hanya untuk business partner bertipe Customer
                ->where('is_active', true)
                ->get()
                ->map(function ($customer) {
                    return [
                        'customer_id'   => $customer->customer_id,
                        'customer_code' => $customer->customer_code,
                        'name'          => $customer->basicData->name_1 ?? $customer->email ?? 'Unknown'
                    ];
                })
                ->toArray();
        }

        $isExternalEmployee = strtolower($user->employee_type ?? 'internal') === 'external';

        $modules = Module::active()->orderBy('name')->get(['id', 'name'])->toArray();

        // Tab "Ticket Modul" hanya relevan untuk employee yang jadi lead di
        // minimal satu module — dicek di sini (bukan lewat menu permission)
        // karena ini murni data, bukan keputusan role.
        $isModuleLead = $employee
            ? \App\Models\ModuleLead::where('employee_id', $employee->employee_id)->exists()
            : false;

        return view('ticket.index', [
            'user'               => $user,
            'customers'          => $customers,
            'currentEmployeeId'  => $user->id,
            'isExternalEmployee' => $isExternalEmployee,
            'modules'            => $modules,
            'isModuleLead'       => $isModuleLead,
        ]);
    }

    /**
     * Display create ticket form (if needed)
     */
    public function create()
    {
        $user = $this->getUserObject();

        if (!$user) {
            return redirect()->route('login');
        }

        return view('ticket.create', [
            'user' => $user
        ]);
    }

    /**
     * Display single ticket detail view
     */
    public function show($id)
    {
        $user = $this->getUserObject();

        if (!$user) {
            return redirect()->route('login');
        }

        // Load ticket with all relationships
        $ticket = Ticket::with(['customer.basicData', 'endCustomer.basicData', 'ticketLead.basicData', 'allMembers.basicData', 'moduleMaster'])
            ->findOrFail($id);

        // Tandai tiket sudah dibaca oleh employee ini (hanya jika role punya fungsi istimewa ticket.read)
        $employee = \App\Models\Employee::find($user->id);
        if ($employee && $employee->hasPermission('ticket.read')) {
            $now = now();
            DB::table('ticket_reads')->upsert(
                [['ticket_id' => $ticket->ticket_id, 'employee_id' => $user->id, 'read_at' => $now, 'created_at' => $now, 'updated_at' => $now]],
                ['ticket_id', 'employee_id'],
                ['read_at', 'updated_at']
            );
        }

        // Opening the ticket also clears any unread reply/internal-note notifications
        // for it, so the bell badge doesn't stay stuck after the user has seen them here.
        Notification::where('employee_id', $user->id)
            ->where('ticket_id', $ticket->ticket_id)
            ->whereIn('type', ['ticket_reply', 'ticket_internal_note'])
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        // Check if ticket is assigned to a delivery support
        // First try via activities (newer method), then fallback to direct ticket_id on delivery_support (older method)
        $deliverySupport = DB::table('delivery_support_activities')
            ->join('delivery_support', 'delivery_support_activities.delivery_support_id', '=', 'delivery_support.id')
            ->leftJoin('employee as adm', 'delivery_support.support_admin_id', '=', 'adm.employee_id')
            ->leftJoin('employee_basic_data as adm_bd', 'adm.employee_id', '=', 'adm_bd.employee_id')
            ->where('delivery_support_activities.ticket_id', $ticket->ticket_id)
            ->orderByDesc('delivery_support.id')
            ->select(
                'delivery_support.id',
                'delivery_support.name',
                'delivery_support.type',
                'delivery_support.onedrive_deliverable_folder_id',
                DB::raw("(SELECT GROUP_CONCAT(TRIM(CONCAT(mgr_bd.first_name, ' ', COALESCE(mgr_bd.last_name, ''))) SEPARATOR ', ')
                          FROM delivery_support_managers dsm
                          JOIN employee_basic_data mgr_bd ON mgr_bd.employee_id = dsm.employee_id
                          WHERE dsm.delivery_support_id = delivery_support.id) as support_manager_name"),
                DB::raw("TRIM(CONCAT(adm_bd.first_name, ' ', COALESCE(adm_bd.last_name, ''))) as support_admin_name")
            )
            ->first();


        // Get consultants (employees with DSM qualification) for PIC dropdown
        $consultants = DB::table('employee')
            ->join('employee_basic_data', 'employee.employee_id', '=', 'employee_basic_data.employee_id')
            ->join('employee_qualification', 'employee.employee_id', '=', 'employee_qualification.employee_id')
            ->where('employee_qualification.dsm', 1)
            ->select(
                'employee.employee_id',
                DB::raw("CONCAT(employee_basic_data.first_name, ' ', COALESCE(employee_basic_data.last_name, '')) as name")
            )
            ->orderBy('employee_basic_data.first_name')
            ->get()
            ->map(function ($item) {
                return [
                    'employee_id' => $item->employee_id,
                    'name' => trim($item->name)
                ];
            })
            ->toArray();

        // Get employees eligible for the member dropdown (role-based, configurable via
        // Management > Permissions — see ticket.eligible-ticket-member)
        $employees = Employee::withMenuPermission('ticket.eligible-ticket-member')
            ->where('is_active', 1)
            ->with('basicData:employee_id,first_name,last_name')
            ->get()
            ->map(fn ($e) => [
                'employee_id' => $e->employee_id,
                'name'        => trim(($e->basicData->first_name ?? '') . ' ' . ($e->basicData->last_name ?? '')),
            ])
            ->filter(fn ($e) => $e['name'] !== '')
            ->sortBy('name')
            ->values()
            ->toArray();

        // Approved customer mandays total (for Properties panel) — sum of every
        // approved version, same logic as the "Customer Mandays" column in the
        // ticket list (a ticket can have several independently-approved versions,
        // e.g. an addendum proposed after the first one was already approved).
        $approvedMandaysRows = CustomerMandays::where('ticket_id', $ticket->ticket_id)
            ->where('status', 'approved')
            ->get(['total_mandays']);
        $approvedMandays = $approvedMandaysRows->isEmpty() ? null : $approvedMandaysRows->sum('total_mandays');

        // Resolve email tujuan reply (digunakan untuk tampilan "To:" di compose area)
        // Prioritas 1: submitted_by_email dari ticket itu sendiri (diisi saat import CSV)
        $customerEmail = $ticket->submitted_by_email ?? null;

        // Prioritas 2: submitted_by_email dari staging ticket yang diapprove
        if (!$customerEmail) {
            $customerEmail = DB::table('staging_tickets')
                ->where('ticket_id', $ticket->ticket_id)
                ->whereNotNull('submitted_by_email')
                ->value('submitted_by_email');
        }

        if (!$customerEmail) {
            $customerEmail = DB::table('ticket_message')
                ->where('ticket_id', $ticket->ticket_id)
                ->where('sender_type', 'customer')
                ->whereNotNull('sender_email')
                ->orderBy('created_at', 'asc')
                ->value('sender_email');
        }

        // CATATAN: sengaja TIDAK fallback ke company email (customer.email) untuk seed
        // "To". Tiket manual/EWA dibuat dengan To kosong; company email tidak boleh
        // otomatis jadi tujuan reply. To diisi manual bila perlu.

        $inMeeting = TicketSlaPause::where('ticket_id', $ticket->ticket_id)
            ->where('pause_reason', 'meeting')
            ->whereNull('ended_at')
            ->exists();

        $isExternalEmployee = strtolower($user->employee_type ?? 'internal') === 'external';

        $modules = Module::active()->orderBy('name')->get(['id', 'name'])->toArray();

        return view('ticket.show', [
            'user'               => $user,
            'ticket'             => $ticket,
            'consultants'        => $consultants,
            'employees'          => $employees,
            'ticketId'           => $id,
            'deliverySupport'    => $deliverySupport,
            'approvedMandays'    => $approvedMandays,
            'customerEmail'      => $customerEmail,
            'inMeeting'          => $inMeeting,
            'isExternalEmployee' => $isExternalEmployee,
            'modules'            => $modules,
        ]);
    }
}

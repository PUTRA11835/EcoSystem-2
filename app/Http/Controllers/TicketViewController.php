<?php

namespace App\Http\Controllers;

use App\Enums\RoleId;
use App\Models\Ticket;
use App\Models\Customer;
use App\Models\CustomerMandays;
use App\Models\TicketSlaPause;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketViewController extends Controller
{
    /**
     * Convert session user array to object format for Blade views
     */
    private function getUserObject()
    {
        $sessionUser = session('user');

        if (!$sessionUser) {
            return null;
        }

        // Convert array to object for Blade compatibility
        $user = new \stdClass();
        $user->id = $sessionUser['id'] ?? null;
        $user->name = $sessionUser['name'] ?? $sessionUser['email'] ?? 'Unknown';
        $user->email = $sessionUser['email'] ?? null;
        $user->type = $sessionUser['type'] ?? null;

        // Create role object
        $user->role = new \stdClass();
        $user->role->role_id = (int) ($sessionUser['role']['id'] ?? 0);
        $user->role->role_name = $sessionUser['role']['name'] ?? 'Unknown';

        return $user;
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

        // Get customers for Admin create ticket dropdown
        $customers = [];
        if ($user->role->role_id === RoleId::EC_ADMINISTRATOR->value) {
            $customers = Customer::with('basicData')
                ->where('is_active', true)
                ->get()
                ->map(function ($customer) {
                    return [
                        'customer_id' => $customer->customer_id,
                        'customer_code' => $customer->customer_code,
                        'name' => $customer->basicData->name_1 ?? $customer->email ?? 'Unknown'
                    ];
                })
                ->toArray();
        }

        return view('ticket.index', [
            'user'              => $user,
            'customers'         => $customers,
            'currentEmployeeId' => $user->id,
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
        $ticket = Ticket::with(['customer.basicData', 'endCustomer.basicData', 'ticketLead.basicData', 'allMembers.basicData'])
            ->findOrFail($id);

        // Check if ticket is assigned to a delivery support
        // First try via activities (newer method), then fallback to direct ticket_id on delivery_support (older method)
        $deliverySupport = DB::table('delivery_support_activities')
            ->join('delivery_support', 'delivery_support_activities.delivery_support_id', '=', 'delivery_support.id')
            ->where('delivery_support_activities.ticket_id', $ticket->ticket_id)
            ->select('delivery_support.id', 'delivery_support.name', 'delivery_support.type')
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

        // Get all active employees for member dropdown
        $employees = DB::table('employee')
            ->join('employee_basic_data', 'employee.employee_id', '=', 'employee_basic_data.employee_id')
            ->where('employee.is_active', 1)
            ->select(
                'employee.employee_id',
                DB::raw("CONCAT(employee_basic_data.first_name, ' ', COALESCE(employee_basic_data.last_name, '')) as name")
            )
            ->orderBy('employee_basic_data.first_name')
            ->get()
            ->map(function ($item) {
                return [
                    'employee_id' => $item->employee_id,
                    'name'        => trim($item->name),
                ];
            })
            ->toArray();

        // Approved customer mandays total (for Properties panel)
        $approvedMandays = CustomerMandays::where('ticket_id', $ticket->ticket_id)
            ->where('status', 'approved')
            ->orderBy('version', 'desc')
            ->value('total_mandays');

        // Resolve email tujuan reply (digunakan untuk tampilan "To:" di compose area)
        $customerEmail = DB::table('staging_tickets')
            ->where('ticket_id', $ticket->ticket_id)
            ->whereNotNull('submitted_by_email')
            ->value('submitted_by_email');

        if (!$customerEmail) {
            $customerEmail = DB::table('ticket_message')
                ->where('ticket_id', $ticket->ticket_id)
                ->where('sender_type', 'customer')
                ->whereNotNull('sender_email')
                ->orderBy('created_at', 'asc')
                ->value('sender_email');
        }

        if (!$customerEmail && $ticket->customer_id) {
            $customerEmail = Customer::find($ticket->customer_id)?->email;
        }

        $inMeeting = TicketSlaPause::where('ticket_id', $ticket->ticket_id)
            ->where('pause_reason', 'meeting')
            ->whereNull('ended_at')
            ->exists();

        return view('ticket.show', [
            'user'             => $user,
            'ticket'           => $ticket,
            'consultants'      => $consultants,
            'employees'        => $employees,
            'ticketId'         => $id,
            'deliverySupport'  => $deliverySupport,
            'approvedMandays'  => $approvedMandays,
            'customerEmail'    => $customerEmail,
            'inMeeting'        => $inMeeting,
        ]);
    }
}

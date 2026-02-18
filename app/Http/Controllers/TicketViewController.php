<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\Customer;
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
        $user->role->role_id = $sessionUser['role']['id'] ?? 0;
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
        if ($user->role->role_id == 1) {
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
            'user' => $user,
            'customers' => $customers
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
        $ticket = Ticket::with(['customer.basicData', 'employee.basicData', 'members.basicData'])
            ->findOrFail($id);

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

        return view('ticket.show', [
            'user' => $user,
            'ticket' => $ticket,
            'consultants' => $consultants,
            'ticketId' => $id
        ]);
    }
}

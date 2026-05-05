<?php

namespace App\Http\Controllers;

use App\Enums\RoleId;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{

    public function index(Request $request)
    {
        try {
            $user = session('user');
            $token = session('auth_token');

            Log::info('Dashboard access attempt', [
                'has_user' => $user ? 'yes' : 'no',
                'has_token' => $token ? 'yes' : 'no',
                'ip' => $request->ip()
            ]);

            if (!$user || !$token) {
                Log::warning('Dashboard accessed without valid session', [
                    'ip_address' => $request->ip(),
                ]);
                return redirect()->route('login')->withErrors(['message' => 'Please login first']);
            }

            $totalEmployees = DB::table('employee')
                ->where('is_active', true)
                ->count();
            $totalCustomers = DB::table('customer')
                ->where('is_active', true)
                ->count();
            $activeProjects = DB::table('delivery_projects')
                ->whereNotIn('status', ['completed', 'closed', 'cancel'])
                ->count();

            $totalTickets = DB::table('ticket')->whereNull('deleted_at')->count();

            $dashboardData = [
                'employee' => $totalEmployees,
                'customers' => $totalCustomers,
                'active_projects' => $activeProjects,
                'total_tickets' => $totalTickets,
                'recent_activities' => [],
            ];

            // Extra data for Delivery Support Head dashboard
            if (($user['type'] ?? '') === 'employee' && ($user['role']['id'] ?? 0) === RoleId::HEAD_OF_SUPPORT->value) {
                $base = DB::table('ticket')->whereNull('deleted_at');

                $dashboardData['ticket_stats'] = [
                    'total'          => (clone $base)->count(),
                    'open'           => (clone $base)->where('status', 'open')->count(),
                    'in_progress'    => (clone $base)->where('status', 'in_progress')->count(),
                    'hold'           => (clone $base)->where('status', 'hold')->count(),
                    'cancel'         => (clone $base)->where('status', 'cancel')->count(),
                    'closed'         => (clone $base)->where('status', 'closed')->count(),
                    'reply'          => (clone $base)->where('status', 'reply')->count(),
                    'wait_to_close'  => (clone $base)->where('status', 'wait_to_close')->count(),
                ];

                // Chart: all tickets created in last 30 days
                $start30 = now()->subDays(29)->startOfDay();
                $byDay = DB::table('ticket')
                    ->whereNull('deleted_at')
                    ->where('created_at', '>=', $start30)
                    ->select(DB::raw('DATE(created_at) as day'), DB::raw('COUNT(*) as cnt'))
                    ->groupBy('day')
                    ->pluck('cnt', 'day')
                    ->toArray();

                $chartLabels = [];
                $chartData   = [];
                for ($i = 29; $i >= 0; $i--) {
                    $d = now()->subDays($i)->format('Y-m-d');
                    $chartLabels[] = now()->subDays($i)->format('d M');
                    $chartData[]   = $byDay[$d] ?? 0;
                }
                $dashboardData['ticket_chart'] = ['labels' => $chartLabels, 'data' => $chartData];

                // Recent 5 tickets (all)
                $dashboardData['recent_tickets'] = DB::table('ticket as t')
                    ->whereNull('t.deleted_at')
                    ->leftJoin('customer as c', 't.customer_id', '=', 'c.customer_id')
                    ->leftJoin('customer_basic_data as cbd', 'c.customer_id', '=', 'cbd.customer_id')
                    ->leftJoin('employee as e', 't.employee_id', '=', 'e.employee_id')
                    ->leftJoin('employee_basic_data as ebd', 'e.employee_id', '=', 'ebd.employee_id')
                    ->select(
                        't.ticket_id', 't.ticket_number', 't.description',
                        't.status', 't.jarvies_status', 't.created_at',
                        'cbd.name_1 as customer_name',
                        DB::raw("COALESCE(TRIM(CONCAT(COALESCE(ebd.first_name,''),' ',COALESCE(ebd.last_name,''))), 'Unassigned') as pic_name")
                    )
                    ->orderBy('t.created_at', 'desc')
                    ->limit(5)
                    ->get();

                // Team load: top 5 employees by open ticket count
                $dashboardData['team_load'] = DB::table('ticket as t')
                    ->whereNull('t.deleted_at')
                    ->whereNotIn('t.status', ['closed', 'cancel'])
                    ->join('employee as e', 't.employee_id', '=', 'e.employee_id')
                    ->leftJoin('employee_basic_data as ebd', 'e.employee_id', '=', 'ebd.employee_id')
                    ->select('e.employee_id', DB::raw("TRIM(CONCAT(COALESCE(ebd.first_name,''),' ',COALESCE(ebd.last_name,''))) as name"), DB::raw('COUNT(*) as open_count'))
                    ->groupBy('e.employee_id', 'ebd.first_name', 'ebd.last_name')
                    ->orderBy('open_count', 'desc')
                    ->limit(5)
                    ->get();
            }

            // Extra data for Employee dashboard
            if (($user['type'] ?? '') === 'employee' && ($user['role']['id'] ?? 0) === RoleId::EMPLOYEE->value) {
                $employeeId = $user['id'];

                // Collect ticket IDs where this employee is PIC or member
                $picIds    = DB::table('ticket')->where('employee_id', $employeeId)->pluck('ticket_id');
                $memberIds = DB::table('ticket_member')->where('employee_id', $employeeId)->pluck('ticket_id');
                $ticketIds = $picIds->merge($memberIds)->unique()->values();

                $base = DB::table('ticket')->whereIn('ticket_id', $ticketIds);

                $dashboardData['ticket_stats'] = [
                    'total'          => (clone $base)->count(),
                    'open'           => (clone $base)->where('status', 'open')->count(),
                    'in_progress'    => (clone $base)->where('status', 'in_progress')->count(),
                    'hold'           => (clone $base)->where('status', 'hold')->count(),
                    'cancel'         => (clone $base)->where('status', 'cancel')->count(),
                    'closed'         => (clone $base)->where('status', 'closed')->count(),
                    'reply'          => (clone $base)->where('status', 'reply')->count(),
                    'wait_to_close'  => (clone $base)->where('status', 'wait_to_close')->count(),
                ];

                // Chart: tickets created in last 30 days grouped by date
                $start30 = now()->subDays(29)->startOfDay();
                $byDay = DB::table('ticket')
                    ->whereIn('ticket_id', $ticketIds)
                    ->where('created_at', '>=', $start30)
                    ->select(DB::raw('DATE(created_at) as day'), DB::raw('COUNT(*) as cnt'))
                    ->groupBy('day')
                    ->pluck('cnt', 'day')
                    ->toArray();

                $chartLabels = [];
                $chartData   = [];
                for ($i = 29; $i >= 0; $i--) {
                    $d = now()->subDays($i)->format('Y-m-d');
                    $chartLabels[] = now()->subDays($i)->format('d M');
                    $chartData[]   = $byDay[$d] ?? 0;
                }
                $dashboardData['ticket_chart'] = ['labels' => $chartLabels, 'data' => $chartData];

                // Recent tickets
                $dashboardData['recent_tickets'] = DB::table('ticket as t')
                    ->whereIn('t.ticket_id', $ticketIds)
                    ->leftJoin('customer as c', 't.customer_id', '=', 'c.customer_id')
                    ->leftJoin('customer_basic_data as cbd', 'c.customer_id', '=', 'cbd.customer_id')
                    ->select(
                        't.ticket_id', 't.ticket_number', 't.description',
                        't.status', 't.jarvies_status', 't.created_at',
                        'cbd.name_1 as customer_name'
                    )
                    ->orderBy('t.created_at', 'desc')
                    ->limit(5)
                    ->get();
            }

            Log::info('Dashboard accessed successfully', [
                'user_id' => $user['id'],
                'user_name' => $user['name'] ?? $user['company_name'] ?? 'Unknown',
                'user_type' => $user['type'],
                'ip_address' => $request->ip(),
            ]);

            return view('home.home', [
                'user' => $user,
                'data' => $dashboardData,
            ]);

        } catch (\Exception $e) {
            Log::error('Dashboard error', [
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine(),
                'ip_address' => $request->ip(),
            ]);

            return redirect()->route('login')->withErrors([
                'message' => 'An error occurred. Please login again.'
            ]);
        }
    }
}

<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Dashboard ringkasan untuk aplikasi mobile employee.
     *
     * Menggabungkan statistik dari:
     * - Ticket (semua status & prioritas)
     * - Delivery Support (status berdasarkan calculated_progress)
     * - Delivery Project (status & progress)
     * - Timesheet (aktivitas bulan ini)
     * - Data tambahan: tiket per PIC, tiket baru hari ini/bulan ini
     */
    public function index(Request $request)
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'ticket'           => $this->ticketSummary(),
                'delivery_support' => $this->deliverySupportSummary(),
                'delivery_project' => $this->deliveryProjectSummary(),
                'timesheet'        => $this->timesheetSummary(),
                'employee'         => $this->employeeSummary(),
                'customer'         => $this->customerSummary(),
            ],
            'generated_at' => now()->toIso8601String(),
        ], 200);
    }

    // =========================================================================
    // TICKET
    // =========================================================================

    /**
     * Statistik tiket berdasarkan:
     * - Total keseluruhan
     * - Per status (open, in_progress, hold, cancel, closed, reply)
     * - Per prioritas (Low, Medium, High)
     * - Tiket baru hari ini dan bulan ini
     * - Distribusi by type
     */
    private function ticketSummary(): array
    {
        // Hitung semua status sekaligus dalam satu query
        $statusCounts = DB::table('ticket')
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'open'                    THEN 1 ELSE 0 END) as open,
                SUM(CASE WHEN status = 'inprocess'               THEN 1 ELSE 0 END) as inprocess,
                SUM(CASE WHEN status = 'waiting_on_customer'     THEN 1 ELSE 0 END) as waiting_on_customer,
                SUM(CASE WHEN status = 'waiting_on_3rd_party'    THEN 1 ELSE 0 END) as waiting_on_3rd_party,
                SUM(CASE WHEN status = 'waiting_to_confirmation' THEN 1 ELSE 0 END) as waiting_to_confirmation,
                SUM(CASE WHEN status = 'hold'                    THEN 1 ELSE 0 END) as hold,
                SUM(CASE WHEN status = 'cancelled'               THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN status = 'closed'                  THEN 1 ELSE 0 END) as closed
            ")
            ->first();

        // Hitung per prioritas
        $priorityCounts = DB::table('ticket')
            ->selectRaw("
                SUM(CASE WHEN ticket_priority = 'Low'    THEN 1 ELSE 0 END) as low,
                SUM(CASE WHEN ticket_priority = 'Medium' THEN 1 ELSE 0 END) as medium,
                SUM(CASE WHEN ticket_priority = 'High'   THEN 1 ELSE 0 END) as high
            ")
            ->first();

        // Tiket baru hari ini dan bulan ini
        $newToday = DB::table('ticket')
            ->whereDate('created_at', today())
            ->count();

        $newThisMonth = DB::table('ticket')
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();

        // Catatan: kolom `type` sudah dipindahkan ke tabel delivery_support
        // (migration 2026_02_18_move_type_from_ticket_to_delivery_support)

        return [
            'total'        => (int) $statusCounts->total,
            'by_status'    => [
                'open'                    => (int) $statusCounts->open,
                'inprocess'               => (int) $statusCounts->inprocess,
                'waiting_on_customer'     => (int) $statusCounts->waiting_on_customer,
                'waiting_on_3rd_party'    => (int) $statusCounts->waiting_on_3rd_party,
                'waiting_to_confirmation' => (int) $statusCounts->waiting_to_confirmation,
                'hold'                    => (int) $statusCounts->hold,
                'cancelled'               => (int) $statusCounts->cancelled,
                'closed'                  => (int) $statusCounts->closed,
            ],
            'by_priority'  => [
                'low'    => (int) $priorityCounts->low,
                'medium' => (int) $priorityCounts->medium,
                'high'   => (int) $priorityCounts->high,
            ],
            'new_today'      => $newToday,
            'new_this_month' => $newThisMonth,
        ];
    }

    // =========================================================================
    // DELIVERY SUPPORT
    // =========================================================================

    /**
     * Statistik Delivery Support.
     *
     * Status TIDAK ada kolom eksplisit — diturunkan dari calculated_progress:
     *   not_started  = calculated_progress = 0
     *   in_progress  = calculated_progress > 0 AND < 100
     *   completed    = calculated_progress >= 100
     */
    private function deliverySupportSummary(): array
    {
        $row = DB::table('delivery_support')
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN calculated_progress = 0                              THEN 1 ELSE 0 END) as not_started,
                SUM(CASE WHEN calculated_progress > 0 AND calculated_progress < 100 THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN calculated_progress >= 100                            THEN 1 ELSE 0 END) as completed,
                ROUND(AVG(calculated_progress), 2) as avg_progress
            ")
            ->first();

        // Tiket baru bulan ini
        $newThisMonth = DB::table('delivery_support')
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();

        // Distribusi per type (AMS, MO, ATS, Project, Internal, dll)
        $byType = DB::table('delivery_support')
            ->selectRaw('type, COUNT(*) as count')
            ->whereNotNull('type')
            ->groupBy('type')
            ->orderByDesc('count')
            ->get()
            ->map(fn($r) => ['type' => $r->type, 'count' => (int) $r->count]);

        return [
            'total'          => (int) $row->total,
            'by_status'      => [
                'not_started' => (int) $row->not_started,
                'in_progress' => (int) $row->in_progress,
                'completed'   => (int) $row->completed,
            ],
            'avg_progress'    => (float) ($row->avg_progress ?? 0),
            'new_this_month'  => $newThisMonth,
            'by_type'         => $byType,
        ];
    }

    // =========================================================================
    // DELIVERY PROJECT
    // =========================================================================

    /**
     * Statistik Delivery Project.
     *
     * Status adalah string bebas (default'planning').
     * Dari DashboardController lama: completed/closed/cancel = selesai.
     */
    private function deliveryProjectSummary(): array
    {
        $row = DB::table('delivery_projects')
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status NOT IN ('completed','closed','cancel') THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status IN ('completed','closed')              THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'cancel'                             THEN 1 ELSE 0 END) as cancelled,
                ROUND(AVG(calculated_progress), 2) as avg_progress
            ")
            ->first();

        // Semua nilai status yang benar-benar ada di database (group by)
        $byStatus = DB::table('delivery_projects')
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->orderByDesc('count')
            ->get()
            ->map(fn($r) => ['status' => $r->status, 'count' => (int) $r->count]);

        // Distribusi per category
        $byCategory = DB::table('delivery_projects')
            ->selectRaw('category, COUNT(*) as count')
            ->whereNotNull('category')
            ->groupBy('category')
            ->orderByDesc('count')
            ->get()
            ->map(fn($r) => ['category' => $r->category, 'count' => (int) $r->count]);

        // Proyek baru bulan ini
        $newThisMonth = DB::table('delivery_projects')
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();

        return [
            'total'          => (int) $row->total,
            'active'         => (int) $row->active,
            'completed'      => (int) $row->completed,
            'cancelled'      => (int) $row->cancelled,
            'avg_progress'   => (float) ($row->avg_progress ?? 0),
            'by_status'      => $byStatus,
            'by_category'    => $byCategory,
            'new_this_month' => $newThisMonth,
        ];
    }

    // =========================================================================
    // TIMESHEET
    // =========================================================================

    /**
     * Statistik Timesheet bulan ini.
     *
     * Status: draft, submitted, approved, rejected
     * Activity type: development, meeting, documentation, testing,
     *                support, training, other
     */
    private function timesheetSummary(): array
    {
        $thisMonth = DB::table('timesheets')
            ->whereYear('date', now()->year)
            ->whereMonth('date', now()->month)
            ->whereNull('deleted_at')
            ->selectRaw("
                COUNT(*) as total_entries,
                ROUND(SUM(duration_minutes) / 60, 2) as total_hours,
                SUM(CASE WHEN is_billable = 1 THEN duration_minutes ELSE 0 END) / 60 as billable_hours,
                SUM(CASE WHEN status = 'draft'     THEN 1 ELSE 0 END) as draft,
                SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted,
                SUM(CASE WHEN status = 'approved'  THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'rejected'  THEN 1 ELSE 0 END) as rejected
            ")
            ->first();

        // Distribusi per activity_type bulan ini
        $byType = DB::table('timesheets')
            ->whereYear('date', now()->year)
            ->whereMonth('date', now()->month)
            ->whereNull('deleted_at')
            ->selectRaw('activity_type, COUNT(*) as count, ROUND(SUM(duration_minutes)/60,2) as hours')
            ->groupBy('activity_type')
            ->orderByDesc('hours')
            ->get()
            ->map(fn($r) => [
                'type'  => $r->activity_type,
                'count' => (int) $r->count,
                'hours' => (float) $r->hours,
            ]);

        return [
            'this_month' => [
                'total_entries'  => (int) ($thisMonth->total_entries ?? 0),
                'total_hours'    => (float) ($thisMonth->total_hours ?? 0),
                'billable_hours' => (float) ($thisMonth->billable_hours ?? 0),
                'by_status'      => [
                    'draft'     => (int) ($thisMonth->draft ?? 0),
                    'submitted' => (int) ($thisMonth->submitted ?? 0),
                    'approved'  => (int) ($thisMonth->approved ?? 0),
                    'rejected'  => (int) ($thisMonth->rejected ?? 0),
                ],
                'by_activity_type' => $byType,
            ],
        ];
    }

    // =========================================================================
    // EMPLOYEE
    // =========================================================================

    /**
     * Ringkasan data employee.
     */
    private function employeeSummary(): array
    {
        $row = DB::table('employee')
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive
            ")
            ->first();

        // Distribusi per role
        $byRole = DB::table('employee_role_assignment as era')
            ->join('employee as e', 'era.employee_id', '=', 'e.employee_id')
            ->join('employee_role as r', 'era.role_id', '=', 'r.id')
            ->selectRaw('r.name as role_name, COUNT(DISTINCT e.employee_id) as count')
            ->where('e.is_active', true)
            ->groupBy('r.id', 'r.name')
            ->orderByDesc('count')
            ->get()
            ->map(fn($r) => ['role' => $r->role_name, 'count' => (int) $r->count]);

        return [
            'total'   => (int) $row->total,
            'active'  => (int) $row->active,
            'inactive' => (int) $row->inactive,
            'by_role' => $byRole,
        ];
    }

    // =========================================================================
    // CUSTOMER
    // =========================================================================

    /**
     * Ringkasan data customer.
     */
    private function customerSummary(): array
    {
        $row = DB::table('customer')
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive
            ")
            ->first();

        // Distribusi per customer_group (dari customer_basic_data)
        $byGroup = DB::table('customer as c')
            ->join('customer_basic_data as cb', 'c.customer_id', '=', 'cb.customer_id')
            ->selectRaw('cb.customer_group, COUNT(*) as count')
            ->where('c.is_active', true)
            ->whereNotNull('cb.customer_group')
            ->groupBy('cb.customer_group')
            ->orderByDesc('count')
            ->get()
            ->map(fn($r) => ['group' => $r->customer_group, 'count' => (int) $r->count]);

        // Customer baru bulan ini
        $newThisMonth = DB::table('customer')
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();

        return [
            'total'          => (int) $row->total,
            'active'         => (int) $row->active,
            'inactive'       => (int) $row->inactive,
            'new_this_month' => $newThisMonth,
            'by_group'       => $byGroup,
        ];
    }
}

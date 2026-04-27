<?php

namespace App\Http\Controllers;

use App\Models\ConsultantMandaysDetail;
use App\Models\Employee;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConsultantWorkloadController extends Controller
{
    private const ACTIVE_STATUSES = ['open', 'in_progress', 'hold', 'reply', 'wait_to_close'];

    public function index()
    {
        return view('ticket.consultant-workload');
    }

    /**
     * API: Daftar semua konsultan beserta data workload-nya.
     */
    public function list(Request $request)
    {
        try {
            $month = $request->integer('month', now()->month);
            $year  = $request->integer('year', now()->year);

            $consultants = Employee::with(['basicData', 'roles'])
                ->where('is_active', true)
                ->get();

            // Pre-load weighted progress per ticket dari consultant_mandays_detail
            $allTicketIds    = DB::table('ticket')
                ->whereIn('status', self::ACTIVE_STATUSES)
                ->whereNull('deleted_at')
                ->pluck('ticket_id')
                ->toArray();
            $progressMap = self::progressMapForTickets($allTicketIds);

            $result = $consultants->map(function (Employee $emp) use ($month, $year, $progressMap) {
                $name = $emp->basicData
                    ? trim($emp->basicData->first_name . ' ' . ($emp->basicData->last_name ?? ''))
                    : $emp->eci;

                $roles = $emp->roles->pluck('name')->implode(', ');

                $tickets = $this->ticketsByEmployee($emp->employee_id, $progressMap);

                $totalDays   = (float) ($tickets->sum('man_days') ?? 0);
                $capacity    = (float) ($emp->monthly_capacity_md ?? 20);
                $workloadPct = $capacity > 0 ? round(($totalDays / $capacity) * 100, 1) : 0;
                $avgProgress = $tickets->count() > 0
                    ? round($tickets->avg('consultant_progress'), 1)
                    : 0;

                $actualMd = (float) (DB::table('timesheets')
                    ->where('employee_id', $emp->employee_id)
                    ->whereMonth('date', $month)
                    ->whereYear('date', $year)
                    ->whereIn('status', ['submitted', 'approved'])
                    ->whereNull('deleted_at')
                    ->sum('md_consumed') ?? 0);

                return [
                    'employee_id'      => $emp->employee_id,
                    'eci'              => $emp->eci,
                    'name'             => $name,
                    'roles'            => $roles,
                    'modules'          => $emp->modules ?? '-',
                    'monthly_capacity' => $capacity,
                    'ticket_count'     => $tickets->count(),
                    'total_days'       => $totalDays,
                    'actual_md'        => $actualMd,
                    'workload_pct'     => $workloadPct,
                    'avg_progress'     => $avgProgress,
                    'tickets'          => $tickets->values(),
                ];
            });

            $sorted = $result->sortByDesc('workload_pct')->values();

            return response()->json([
                'success' => true,
                'data'    => $sorted,
                'month'   => $month,
                'year'    => $year,
            ]);
        } catch (\Exception $e) {
            Log::error('ConsultantWorkload@list error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat data: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * API: Detail satu konsultan.
     */
    public function detail(int $id)
    {
        try {
            $emp = Employee::with(['basicData', 'roles'])->findOrFail($id);

            $name = $emp->basicData
                ? trim($emp->basicData->first_name . ' ' . ($emp->basicData->last_name ?? ''))
                : $emp->eci;

            $ticketIds   = DB::table('ticket')
                ->whereIn('status', self::ACTIVE_STATUSES)
                ->whereNull('deleted_at')
                ->pluck('ticket_id')
                ->toArray();
            $progressMap = self::progressMapForTickets($ticketIds);

            $statusOrder = ['in_progress' => 0, 'reply' => 1, 'open' => 2, 'hold' => 3, 'wait_to_close' => 4];
            $tickets = $this->ticketsByEmployee($id, $progressMap)
                ->sortBy(fn($t) => $statusOrder[$t->status] ?? 99)
                ->values();

            $totalDays = (float) ($tickets->sum('man_days') ?? 0);
            $capacity  = (float) ($emp->monthly_capacity_md ?? 20);
            $actualMd  = (float) (DB::table('timesheets')
                ->where('employee_id', $id)
                ->whereMonth('date', now()->month)
                ->whereYear('date', now()->year)
                ->whereIn('status', ['submitted', 'approved'])
                ->whereNull('deleted_at')
                ->sum('md_consumed') ?? 0);

            return response()->json([
                'success'          => true,
                'employee_id'      => $emp->employee_id,
                'eci'              => $emp->eci,
                'name'             => $name,
                'roles'            => $emp->roles->pluck('name'),
                'modules'          => $emp->modules ?? '-',
                'monthly_capacity' => $capacity,
                'total_days'       => $totalDays,
                'actual_md'        => $actualMd,
                'remaining_md'     => max(0, $capacity - $totalDays),
                'workload_pct'     => $capacity > 0 ? round(($totalDays / $capacity) * 100, 1) : 0,
                'tickets'          => $tickets->values(),
            ]);
        } catch (\Exception $e) {
            Log::error('ConsultantWorkload@detail error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat detail: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Query tiket aktif untuk satu employee (PIC + member), dilengkapi:
     *  - consultant_progress  : weighted average progress dari consultant_mandays_detail
     *  - consultant_details   : per-konsultan progress rows (untuk sub-tabel di view)
     *
     * $progressMap = [ticket_id => weighted_avg_pct]  (di-pre-load di caller)
     */
    private function ticketsByEmployee(int $empId, array $progressMap = [])
    {
        $picIds    = DB::table('ticket')
            ->where('employee_id', $empId)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->whereNull('deleted_at')
            ->pluck('ticket_id');

        $memberIds = DB::table('ticket_member')
            ->where('employee_id', $empId)
            ->pluck('ticket_id');

        $ticketIds = $picIds->merge($memberIds)->unique()->values();

        $baseSelect = [
            'ticket.ticket_id', 'ticket.ticket_number', 'ticket.subject',
            'ticket.status', 'ticket.ticket_priority', 'ticket.ticket_type',
            'ticket.man_days', 'ticket.progress_percentage', 'ticket.progress_note',
            'ticket.last_progress_at', 'ticket.module', 'ticket.start_date',
            'ticket.end_date', 'customer_basic_data.name_1 as customer_name',
            DB::raw("CASE WHEN ticket.employee_id = {$empId} THEN 'pic' ELSE 'member' END as role_in_ticket"),
        ];

        if ($ticketIds->isEmpty()) {
            return collect();
        }

        $tickets = DB::table('ticket')
            ->leftJoin('customer_basic_data', 'ticket.customer_id', '=', 'customer_basic_data.customer_id')
            ->whereIn('ticket.ticket_id', $ticketIds)
            ->whereIn('ticket.status', self::ACTIVE_STATUSES)
            ->whereNull('ticket.deleted_at')
            ->select($baseSelect)
            ->get();

        // Load per-consultant progress detail untuk semua tiket sekaligus
        $consultantDetails = $this->consultantDetailsForTickets($ticketIds->toArray());

        return $tickets->map(function ($ticket) use ($progressMap, $consultantDetails) {
            $tid = $ticket->ticket_id;

            // Weighted average dari consultant_mandays_detail, fallback ke ticket.progress_percentage
            $ticket->consultant_progress = $progressMap[$tid]
                ?? (float) ($ticket->progress_percentage ?? 0);

            // Per-konsultan progress untuk sub-tabel
            $ticket->consultant_details = $consultantDetails[$tid] ?? [];

            return $ticket;
        });
    }

    /**
     * Load per-konsultan progress dari consultant_mandays_detail untuk sekumpulan ticket_id.
     * Return: [ticket_id => [ [employee_id, name, mandays, approved_additional, progress_percentage, progress_note, detail_id], ... ]]
     */
    private function consultantDetailsForTickets(array $ticketIds): array
    {
        if (empty($ticketIds)) return [];

        $rows = DB::table('consultant_mandays as cm')
            ->join('consultant_mandays_detail as cmd', 'cmd.consultant_mandays_id', '=', 'cm.id')
            ->leftJoin('employee as e', 'e.employee_id', '=', 'cmd.employee_id')
            ->leftJoin('employee_basic_data as ebd', 'ebd.employee_id', '=', 'e.employee_id')
            ->whereIn('cm.ticket_id', $ticketIds)
            ->select(
                'cm.ticket_id',
                'cmd.id as detail_id',
                'cmd.employee_id',
                DB::raw("TRIM(CONCAT(COALESCE(ebd.first_name,''), ' ', COALESCE(ebd.last_name,''))) as emp_name"),
                'e.eci',
                'cmd.module',
                'cmd.mandays',
                'cmd.approved_additional',
                'cmd.progress_percentage',
                'cmd.progress_note',
                'cmd.progress_updated_at'
            )
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $tid = (int) $row->ticket_id;
            $effectiveMd = (float) $row->mandays + (float) $row->approved_additional;
            $map[$tid][] = [
                'detail_id'           => $row->detail_id,
                'employee_id'         => $row->employee_id,
                'emp_name'            => trim($row->emp_name) ?: ($row->eci ?? '—'),
                'eci'                 => $row->eci ?? '—',
                'module'              => $row->module ?? '—',
                'mandays'             => (float) $row->mandays,
                'approved_additional' => (float) $row->approved_additional,
                'effective_md'        => $effectiveMd,
                'progress_percentage' => (float) $row->progress_percentage,
                'progress_note'       => $row->progress_note ?? '',
                'progress_updated_at' => $row->progress_updated_at,
            ];
        }

        return $map;
    }

    /**
     * API: Update progress tiket (legacy — satu field di ticket).
     */
    public function updateProgress(Request $request, int $ticketId)
    {
        try {
            $validated = $request->validate([
                'progress_percentage' => 'required|numeric|min:0|max:100',
                'progress_note'       => 'nullable|string|max:500',
            ]);

            $user  = session('user');
            $empId = $user['id'] ?? null;

            Ticket::where('ticket_id', $ticketId)->update([
                'progress_percentage' => $validated['progress_percentage'],
                'progress_note'       => $validated['progress_note'] ?? null,
                'last_progress_at'    => now(),
                'progress_updated_by' => $empId,
            ]);

            return response()->json(['success' => true, 'message' => 'Progress updated']);
        } catch (\Exception $e) {
            Log::error('ConsultantWorkload@updateProgress error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Update progress per-konsultan di consultant_mandays_detail.
     */
    public function updateConsultantProgress(Request $request, int $detailId)
    {
        try {
            $validated = $request->validate([
                'progress_percentage' => 'required|numeric|min:0|max:100',
                'progress_note'       => 'nullable|string|max:1000',
            ]);

            $detail = ConsultantMandaysDetail::findOrFail($detailId);

            $detail->update([
                'progress_percentage' => $validated['progress_percentage'],
                'progress_note'       => $validated['progress_note'] ?? null,
                'progress_updated_at' => now(),
            ]);

            // Hitung ulang weighted average progress untuk tiket ini
            $ticketId  = DB::table('consultant_mandays')
                ->where('id', $detail->consultant_mandays_id)
                ->value('ticket_id');

            $ticketProgress = $this->calcTicketProgress($detail->consultant_mandays_id);

            // Update ticket.progress_percentage dengan weighted average
            if ($ticketId) {
                Ticket::where('ticket_id', $ticketId)->update([
                    'progress_percentage' => $ticketProgress,
                    'last_progress_at'    => now(),
                ]);
            }

            return response()->json([
                'success'          => true,
                'message'          => 'Progress konsultan diperbarui',
                'ticket_progress'  => $ticketProgress,
            ]);
        } catch (\Exception $e) {
            Log::error('ConsultantWorkload@updateConsultantProgress error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Hitung weighted average progress tiket dari consultant_mandays_detail.
     * Bobot = mandays + approved_additional.
     * Fallback 0 jika tidak ada data.
     */
    public static function calcTicketProgress(int $consultantMandaysId): float
    {
        $details = DB::table('consultant_mandays_detail')
            ->where('consultant_mandays_id', $consultantMandaysId)
            ->select('progress_percentage', 'mandays', 'approved_additional')
            ->get();

        $totalWeight    = 0;
        $weightedSum    = 0;

        foreach ($details as $d) {
            $weight       = (float) $d->mandays + (float) $d->approved_additional;
            $totalWeight += $weight;
            $weightedSum += $weight * (float) $d->progress_percentage;
        }

        return $totalWeight > 0
            ? round($weightedSum / $totalWeight, 1)
            : 0;
    }

    /**
     * Kembalikan map ticket_id → weighted_progress untuk sekumpulan ticket_id.
     * Digunakan oleh TicketController agar tidak duplikasi query.
     *
     * @param  array $ticketIds
     * @return array<int, float>  [ticket_id => progress_pct]
     */
    public static function progressMapForTickets(array $ticketIds): array
    {
        if (empty($ticketIds)) return [];

        $rows = DB::table('consultant_mandays as cm')
            ->join('consultant_mandays_detail as cmd', 'cmd.consultant_mandays_id', '=', 'cm.id')
            ->whereIn('cm.ticket_id', $ticketIds)
            ->select(
                'cm.ticket_id',
                DB::raw('SUM((cmd.mandays + cmd.approved_additional) * cmd.progress_percentage) as weighted_sum'),
                DB::raw('SUM(cmd.mandays + cmd.approved_additional) as total_weight')
            )
            ->groupBy('cm.ticket_id')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->ticket_id] = $row->total_weight > 0
                ? round($row->weighted_sum / $row->total_weight, 1)
                : 0;
        }

        return $map;
    }
}

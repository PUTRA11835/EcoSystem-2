<?php

namespace App\Http\Controllers;

use App\Enums\RoleId;
use App\Models\ConsultantMandays;
use App\Models\ConsultantMandaysDetail;
use App\Models\Employee;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConsultantWorkloadController extends Controller
{
    private const ACTIVE_STATUSES = ['open', 'inprocess', 'waiting_on_customer', 'waiting_on_3rd_party', 'waiting_to_confirmation', 'hold'];

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
                ->withAnyRole([RoleId::DELIVERY_SUPPORT_USER->value])
                ->get();

            // Pre-load weighted progress per ticket dari consultant_mandays_detail
            $allTicketIds = DB::table('ticket')
                ->whereIn('status', self::ACTIVE_STATUSES)
                ->whereNull('deleted_at')
                ->pluck('ticket_id')
                ->toArray();
            $progressMap = self::progressMapForTickets($allTicketIds);

            // Pre-load modules dari employee_qualification per employee
            $empIds     = $consultants->pluck('employee_id')->toArray();
            $modulesMap = self::modulesMapForEmployees($empIds);

            $result = $consultants->map(function (Employee $emp) use ($progressMap, $modulesMap) {
                $name = $emp->basicData
                    ? trim($emp->basicData->first_name . ' ' . ($emp->basicData->last_name ?? ''))
                    : $emp->eci;

                $roles = $emp->roles->pluck('name')->implode(', ');

                $tickets = $this->ticketsByEmployee($emp->employee_id, $progressMap);

                // Aggregate from sub-row consultant_details so main row always matches sub-rows
                $totalAllocMd = 0;
                $totalRemain  = 0;
                foreach ($tickets as $ticket) {
                    $myDetail = collect($ticket->consultant_details)
                        ->firstWhere('employee_id', $emp->employee_id);
                    if ($myDetail) {
                        $totalAllocMd += (float) $myDetail['effective_md'];
                        $totalRemain  += (float) $myDetail['remain_md'];
                    }
                }
                $workloadPct = $totalAllocMd > 0
                    ? round($totalRemain / $totalAllocMd * 100, 1)
                    : 0;

                $ticketCount = $tickets->count();
                // Load Score = Remain × (1 + 0.1 × n)
                $loadScore   = round($totalRemain * (1 + 0.1 * $ticketCount), 2);

                return [
                    'employee_id'  => $emp->employee_id,
                    'eci'          => $emp->eci,
                    'name'         => $name,
                    'roles'        => $roles,
                    'modules'      => $modulesMap[$emp->employee_id] ?? '-',
                    'ticket_count' => $ticketCount,
                    'total_days'   => round($totalAllocMd, 2),
                    'workload_days' => round($totalRemain, 2),
                    'workload_pct'  => $workloadPct,
                    'load_score'   => $loadScore,
                    'tickets'      => $tickets->values(),
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

            $statusOrder = ['inprocess' => 0, 'waiting_on_customer' => 1, 'waiting_on_3rd_party' => 2, 'waiting_to_confirmation' => 3, 'open' => 4, 'hold' => 5];
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

            $modulesMap = self::modulesMapForEmployees([$id]);

            return response()->json([
                'success'          => true,
                'employee_id'      => $emp->employee_id,
                'eci'              => $emp->eci,
                'name'             => $name,
                'roles'            => $emp->roles->pluck('name'),
                'modules'          => $modulesMap[$id] ?? '-',
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
            ->where('ticket_lead_id', $empId)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->whereNull('deleted_at')
            ->pluck('ticket_id');

        $memberIds = DB::table('ticket_member')
            ->where('employee_id', $empId)
            ->pluck('ticket_id');

        // Tiket di mana employee punya alokasi di consultant_mandays_detail
        $mandaysIds = DB::table('consultant_mandays_detail as cmd')
            ->join('consultant_mandays as cm', 'cm.id', '=', 'cmd.consultant_mandays_id')
            ->where('cmd.employee_id', $empId)
            ->pluck('cm.ticket_id');

        $ticketIds = $picIds->merge($memberIds)->merge($mandaysIds)->unique()->values();

        $baseSelect = [
            'ticket.ticket_id', 'ticket.ticket_number',
            DB::raw("COALESCE(NULLIF(ticket.subject, ''), ticket.description) as subject"),
            'ticket.status', 'ticket.ticket_priority', 'ticket.ticket_type',
            'ticket.man_days', 'ticket.progress_percentage', 'ticket.progress_note',
            'ticket.last_progress_at', 'ticket.module', 'ticket.start_date',
            'ticket.end_date', 'ticket.ticket_lead_id',
            'customer_basic_data.name_1 as customer_name',
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

        return $tickets->map(function ($ticket) use ($progressMap, $consultantDetails, $empId) {
            $ticket->role_in_ticket = ((int) $ticket->ticket_lead_id === $empId) ? 'pic' : 'member';
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
     * Return: [ticket_id => [ [...], ... ]]
     * remain_md = effective_md × (1 − progress/100)
     */
    private function consultantDetailsForTickets(array $ticketIds): array
    {
        if (empty($ticketIds)) return [];

        $rows = DB::table('consultant_mandays as cm')
            ->join('consultant_mandays_detail as cmd', 'cmd.consultant_mandays_id', '=', 'cm.id')
            ->leftJoin('employee as e', 'e.employee_id', '=', 'cmd.employee_id')
            ->leftJoin('employee_basic_data as ebd', 'ebd.employee_id', '=', 'e.employee_id')
            ->leftJoinSub(
                DB::table('employee_qualification')
                    ->join('modules', 'modules.id', '=', 'employee_qualification.module_id')
                    ->where('modules.is_active', true)
                    ->select('employee_qualification.employee_id', DB::raw("GROUP_CONCAT(DISTINCT modules.name ORDER BY modules.name SEPARATOR ', ') as qualification_modules"))
                    ->groupBy('employee_qualification.employee_id'),
                'eq',
                'eq.employee_id',
                '=',
                'cmd.employee_id'
            )
            ->whereIn('cm.ticket_id', $ticketIds)
            ->select(
                'cm.ticket_id',
                'cmd.id as detail_id',
                'cmd.employee_id',
                DB::raw("TRIM(CONCAT(COALESCE(ebd.first_name,''), ' ', COALESCE(ebd.last_name,''))) as emp_name"),
                'e.eci',
                'eq.qualification_modules',
                'cmd.mandays',
                'cmd.approved_additional',
                'cmd.progress_percentage as consultant_progress',
                'cmd.progress_note as consultant_progress_note',
                'cmd.progress_updated_at as consultant_progress_updated_at'
            )
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $tid         = (int) $row->ticket_id;
            $mandays     = (float) $row->mandays;
            $additional  = (float) $row->approved_additional;
            $effectiveMd = $mandays + $additional;
            $consultantPct = (float) ($row->consultant_progress ?? 0);
            $remainShare = round($effectiveMd * (1 - $consultantPct / 100), 2);

            $map[$tid][] = [
                'detail_id'                    => $row->detail_id,
                'employee_id'                  => $row->employee_id,
                'emp_name'                     => trim($row->emp_name) ?: ($row->eci ?? '—'),
                'eci'                          => $row->eci ?? '—',
                'module'                       => $row->qualification_modules ?? '—',
                'mandays'                      => $mandays,
                'approved_additional'          => $additional,
                'effective_md'                 => $effectiveMd,
                'remain_md'                    => $remainShare,
                'progress_percentage'          => $consultantPct,
                'progress_note'                => $row->consultant_progress_note,
                'progress_updated_at'          => $row->consultant_progress_updated_at,
            ];
        }

        return $map;
    }

    /**
     * API: Ambil progress per consultant untuk sebuah tiket.
     */
    public function getConsultantProgress(int $ticketId)
    {
        try {
            $cm = ConsultantMandays::where('ticket_id', $ticketId)->latest()->first();

            if (!$cm) {
                return response()->json(['success' => true, 'data' => []]);
            }

            $details = DB::table('consultant_mandays_detail as cmd')
                ->leftJoin('employee as e', 'e.employee_id', '=', 'cmd.employee_id')
                ->leftJoin('employee_basic_data as ebd', 'ebd.employee_id', '=', 'e.employee_id')
                ->where('cmd.consultant_mandays_id', $cm->id)
                ->select(
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
                ->get()
                ->map(fn($d) => [
                    'detail_id'          => $d->detail_id,
                    'employee_id'        => $d->employee_id,
                    'emp_name'           => trim($d->emp_name) ?: ($d->eci ?? '—'),
                    'eci'                => $d->eci ?? '—',
                    'module'             => $d->module ?? '—',
                    'mandays'            => (float) $d->mandays,
                    'progress_percentage' => (float) ($d->progress_percentage ?? 0),
                    'progress_note'      => $d->progress_note,
                    'progress_updated_at' => $d->progress_updated_at,
                ]);

            return response()->json(['success' => true, 'data' => $details]);
        } catch (\Exception $e) {
            Log::error('ConsultantWorkload@getConsultantProgress error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Update progress per consultant, lalu recalculate ticket.progress_percentage
     * sebagai weighted average berdasarkan mandays.
     */
    public function updateConsultantProgress(Request $request, int $ticketId)
    {
        try {
            $validated = $request->validate([
                'progresses'                           => 'required|array|min:1',
                'progresses.*.detail_id'               => 'required|integer|exists:consultant_mandays_detail,id',
                'progresses.*.progress_percentage'     => 'required|numeric|min:0|max:100',
                'progresses.*.progress_note'           => 'nullable|string|max:500',
            ]);

            $now   = now();
            $empId = session('user.id');

            foreach ($validated['progresses'] as $item) {
                ConsultantMandaysDetail::where('id', $item['detail_id'])->update([
                    'progress_percentage' => $item['progress_percentage'],
                    'progress_note'       => $item['progress_note'] ?? null,
                    'progress_updated_at' => $now,
                ]);
            }

            // Recalculate ticket.progress_percentage sebagai weighted average mandays
            $cm = ConsultantMandays::where('ticket_id', $ticketId)->latest()->first();
            $ticketProgress = 0.0;
            $latestNote     = null;

            if ($cm) {
                $allDetails = ConsultantMandaysDetail::where('consultant_mandays_id', $cm->id)->get();
                $totalMd    = $allDetails->sum(fn($d) => (float) $d->mandays);

                if ($totalMd > 0) {
                    $weightedSum    = $allDetails->sum(fn($d) => (float) $d->mandays * (float) $d->progress_percentage);
                    $ticketProgress = round($weightedSum / $totalMd, 2);
                } elseif ($allDetails->count() > 0) {
                    $ticketProgress = round($allDetails->avg('progress_percentage'), 2);
                }

                // Ambil catatan terbaru dari consultant yang baru diupdate
                $updatedIds = collect($validated['progresses'])->pluck('detail_id');
                $latestNote = $allDetails
                    ->whereIn('id', $updatedIds->toArray())
                    ->whereNotNull('progress_note')
                    ->sortByDesc('progress_updated_at')
                    ->first()?->progress_note;
            }

            Ticket::where('ticket_id', $ticketId)->update([
                'progress_percentage' => $ticketProgress,
                'progress_note'       => $latestNote,
                'last_progress_at'    => $now,
                'progress_updated_by' => $empId,
            ]);

            return response()->json([
                'success'          => true,
                'message'          => 'Progress updated',
                'ticket_progress'  => $ticketProgress,
            ]);
        } catch (\Exception $e) {
            Log::error('ConsultantWorkload@updateConsultantProgress error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Kembalikan map ticket_id → progress_percentage langsung dari ticket table.
     */
    public static function progressMapForTickets(array $ticketIds): array
    {
        if (empty($ticketIds)) return [];

        return DB::table('ticket')
            ->whereIn('ticket_id', $ticketIds)
            ->pluck('progress_percentage', 'ticket_id')
            ->map(fn($v) => (float) $v)
            ->toArray();
    }

    /**
     * Load modules per employee dari consultant_mandays_detail (modul tiket aktif),
     * dengan fallback ke employee_qualification jika tidak ada data mandays.
     * Return: [employee_id => "Module1, Module2"]
     */
    public static function modulesMapForEmployees(array $empIds): array
    {
        if (empty($empIds)) return [];

        // Ambil modul dari consultant_mandays_detail (tiket aktif)
        $mandaysRows = DB::table('consultant_mandays_detail as cmd')
            ->join('consultant_mandays as cm', 'cm.id', '=', 'cmd.consultant_mandays_id')
            ->join('ticket as t', 't.ticket_id', '=', 'cm.ticket_id')
            ->whereIn('cmd.employee_id', $empIds)
            ->whereIn('t.status', ['open', 'inprocess', 'waiting_on_customer', 'waiting_on_3rd_party', 'waiting_to_confirmation', 'hold'])
            ->whereNull('t.deleted_at')
            ->whereNotNull('cmd.module')
            ->where('cmd.module', '!=', '')
            ->select('cmd.employee_id', 'cmd.module')
            ->distinct()
            ->get();

        $map = [];
        foreach ($mandaysRows as $row) {
            $map[$row->employee_id][] = $row->module;
        }

        // Fallback ke employee_qualification untuk employee yang belum punya data mandays
        $missingIds = array_diff($empIds, array_keys($map));
        if (!empty($missingIds)) {
            $qualRows = DB::table('employee_qualification')
                ->join('modules', 'modules.id', '=', 'employee_qualification.module_id')
                ->whereIn('employee_qualification.employee_id', $missingIds)
                ->where('modules.is_active', true)
                ->select('employee_qualification.employee_id', 'modules.name as module')
                ->get();

            foreach ($qualRows as $row) {
                $map[$row->employee_id][] = $row->module;
            }
        }

        return array_map(
            fn($modules) => implode(', ', array_unique($modules)),
            $map
        );
    }

    /**
     * Hitung workload per consultant: sisa MD vs alokasi awal.
     * Formula: remain = mandays × (1 − progress/100)  per tiket per konsultan
     *          workload_days = Σ remain  |  workload_pct = workload_days / Σ mandays × 100
     * Return: [employee_id => ['days' => X, 'pct' => Y]]
     */
    public static function workloadByRemainForEmployees(array $empIds, array $activeStatuses): array
    {
        if (empty($empIds)) return [];

        $rows = DB::table('consultant_mandays_detail as cmd')
            ->join('consultant_mandays as cm', 'cm.id', '=', 'cmd.consultant_mandays_id')
            ->join('ticket as t', 't.ticket_id', '=', 'cm.ticket_id')
            ->whereIn('cmd.employee_id', $empIds)
            ->whereIn('t.status', $activeStatuses)
            ->whereNull('t.deleted_at')
            ->select(
                'cmd.employee_id',
                DB::raw('SUM(cmd.mandays) as total_allocated_md'),
                DB::raw('SUM(cmd.mandays * (1 - cmd.progress_percentage / 100)) as total_remain')
            )
            ->groupBy('cmd.employee_id')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $allocatedMd = (float) $row->total_allocated_md;
            $totalRemain = (float) $row->total_remain;
            $map[(int) $row->employee_id] = [
                'allocated_md' => round($allocatedMd, 2),
                'remain_days'  => round($totalRemain, 2),
                'pct'          => $allocatedMd > 0 ? round($totalRemain / $allocatedMd * 100, 1) : 0,
            ];
        }

        return $map;
    }
}

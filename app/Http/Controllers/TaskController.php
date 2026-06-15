<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TaskController extends Controller
{
    private const ACTIVE_STATUSES = ['open', 'inprocess', 'hold', 'waiting_on_customer', 'waiting_on_3rd_party', 'waiting_to_confirmation'];

    public function index()
    {
        return view('ticket.task');
    }

    /**
     * API: Daftar tiket aktif di mana user yang login adalah PIC.
     */
    public function list(Request $request)
    {
        try {
            $sessionUser = session('user');
            if (!$sessionUser) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            $empId = (int) $sessionUser['id'];
            $month = $request->integer('month', now()->month);
            $year  = $request->integer('year', now()->year);

            // Ambil tiket di mana user ini adalah PIC
            $tickets = DB::table('ticket')
                ->leftJoin('customer_basic_data', 'ticket.customer_id', '=', 'customer_basic_data.customer_id')
                ->where('ticket.ticket_lead_id', $empId)
                ->whereIn('ticket.status', self::ACTIVE_STATUSES)
                ->whereNull('ticket.deleted_at')
                ->select([
                    'ticket.ticket_id', 'ticket.ticket_number', 'ticket.subject',
                    'ticket.status', 'ticket.ticket_priority', 'ticket.ticket_type',
                    'ticket.man_days', 'ticket.progress_percentage', 'ticket.progress_note',
                    'ticket.last_progress_at', 'ticket.module', 'ticket.start_date', 'ticket.end_date',
                    'customer_basic_data.name_1 as customer_name',
                ])
                ->orderByRaw("FIELD(ticket.status, 'inprocess', 'open', 'waiting_on_customer', 'waiting_on_3rd_party', 'waiting_to_confirmation', 'hold')")
                ->get();

            if ($tickets->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data'    => [],
                    'month'   => $month,
                    'year'    => $year,
                ]);
            }

            $ticketIds         = $tickets->pluck('ticket_id')->toArray();
            $progressMap       = ConsultantWorkloadController::progressMapForTickets($ticketIds);
            $consultantDetails = $this->consultantDetailsForTickets($ticketIds);
            $modulesMap        = ConsultantWorkloadController::modulesMapForEmployees([$empId]);
            $myModules         = $modulesMap[$empId] ?? '-';

            $ticketsData = $tickets->map(function ($ticket) use ($progressMap, $consultantDetails) {
                $tid = $ticket->ticket_id;
                return [
                    'ticket_id'           => $tid,
                    'ticket_number'       => $ticket->ticket_number,
                    'subject'             => $ticket->subject,
                    'status'              => $ticket->status,
                    'ticket_priority'     => $ticket->ticket_priority,
                    'ticket_type'         => $ticket->ticket_type,
                    'man_days'            => (float) $ticket->man_days,
                    'progress_percentage' => (float) $ticket->progress_percentage,
                    'progress_note'       => $ticket->progress_note,
                    'module'              => $ticket->module,
                    'start_date'          => $ticket->start_date,
                    'end_date'            => $ticket->end_date,
                    'customer_name'       => $ticket->customer_name,
                    'consultant_progress' => $progressMap[$tid] ?? (float) ($ticket->progress_percentage ?? 0),
                    'consultant_details'  => $consultantDetails[$tid] ?? [],
                ];
            });

            // Summary: aggregate dari consultant_details milik user ini
            $totalAllocMd = 0;
            $totalRemain  = 0;
            foreach ($consultantDetails as $details) {
                foreach ($details as $d) {
                    if ((int) $d['employee_id'] === $empId) {
                        $totalAllocMd += $d['effective_md'];
                        $totalRemain  += $d['remain_md'];
                    }
                }
            }
            $ticketCount  = $tickets->count();
            $workloadPct  = $totalAllocMd > 0 ? round($totalRemain / $totalAllocMd * 100, 1) : 0;
            $loadScore    = round($totalRemain * (1 + 0.1 * $ticketCount), 2);

            return response()->json([
                'success'    => true,
                'data'       => $ticketsData->values(),
                'emp_id'     => $empId,
                'my_modules' => $myModules,
                'summary'    => [
                    'ticket_count'  => $ticketCount,
                    'total_alloc_md'=> round($totalAllocMd, 2),
                    'total_remain'  => round($totalRemain, 2),
                    'workload_pct'  => $workloadPct,
                    'load_score'    => $loadScore,
                ],
                'month'      => $month,
                'year'       => $year,
            ]);
        } catch (\Exception $e) {
            Log::error('Task@list error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat data: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function consultantDetailsForTickets(array $ticketIds): array
    {
        if (empty($ticketIds)) return [];

        $ticketProgress = DB::table('ticket')
            ->whereIn('ticket_id', $ticketIds)
            ->pluck('progress_percentage', 'ticket_id');

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
                'cmd.progress_percentage',
                'cmd.progress_note'
            )
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $tid         = (int) $row->ticket_id;
            $mandays     = (float) $row->mandays;
            $additional  = (float) $row->approved_additional;
            $effectiveMd = $mandays + $additional;
            $progress    = (float) ($ticketProgress[$tid] ?? 0);
            $remainShare = round($effectiveMd * (1 - $progress / 100), 2);

            $map[$tid][] = [
                'detail_id'           => $row->detail_id,
                'employee_id'         => $row->employee_id,
                'emp_name'            => trim($row->emp_name) ?: ($row->eci ?? '—'),
                'eci'                 => $row->eci ?? '—',
                'module'              => $row->qualification_modules ?? '—',
                'mandays'             => $mandays,
                'approved_additional'  => $additional,
                'effective_md'         => $effectiveMd,
                'remain_md'            => $remainShare,
                'progress_percentage'  => (float) ($row->progress_percentage ?? 0),
                'progress_note'        => $row->progress_note ?? null,
            ];
        }

        return $map;
    }
}

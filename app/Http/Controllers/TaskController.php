<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TaskController extends Controller
{
    private const ACTIVE_STATUSES = ['open', 'in_progress', 'hold', 'reply', 'wait_to_close'];

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
                ->where('ticket.employee_id', $empId)
                ->whereIn('ticket.status', self::ACTIVE_STATUSES)
                ->whereNull('ticket.deleted_at')
                ->select([
                    'ticket.ticket_id', 'ticket.ticket_number', 'ticket.subject',
                    'ticket.status', 'ticket.ticket_priority', 'ticket.ticket_type',
                    'ticket.man_days', 'ticket.progress_percentage', 'ticket.progress_note',
                    'ticket.last_progress_at', 'ticket.module', 'ticket.start_date', 'ticket.end_date',
                    'customer_basic_data.name_1 as customer_name',
                ])
                ->orderByRaw("FIELD(ticket.status, 'in_progress', 'reply', 'open', 'hold', 'wait_to_close')")
                ->get();

            if ($tickets->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data'    => [],
                    'month'   => $month,
                    'year'    => $year,
                ]);
            }

            $ticketIds   = $tickets->pluck('ticket_id')->toArray();
            $progressMap = ConsultantWorkloadController::progressMapForTickets($ticketIds);

            // Load per-consultant progress detail
            $consultantDetails = $this->consultantDetailsForTickets($ticketIds);

            // Actual md konsultan ini di bulan terpilih
            $actualMd = (float) DB::table('timesheets')
                ->where('employee_id', $empId)
                ->whereMonth('date', $month)
                ->whereYear('date', $year)
                ->whereIn('status', ['submitted', 'approved'])
                ->whereNull('deleted_at')
                ->sum('md_consumed');

            $totalManDays = (float) $tickets->sum('man_days');

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

            return response()->json([
                'success'        => true,
                'data'           => $ticketsData->values(),
                'summary'        => [
                    'ticket_count'  => $tickets->count(),
                    'total_man_days'=> $totalManDays,
                    'actual_md'     => $actualMd,
                ],
                'month'          => $month,
                'year'           => $year,
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
            $tid         = (int) $row->ticket_id;
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
}

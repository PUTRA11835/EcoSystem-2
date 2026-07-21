<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
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
     * API: Daftar tiket aktif di mana user yang login ditugaskan —
     * sebagai Lead, Member, atau punya alokasi mandays.
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

            // Ambil tiket di mana user ini ditugaskan (lead, member, atau alokasi mandays)
            $assignedTicketIds = Ticket::assignedTicketIds($empId);

            $tickets = DB::table('ticket')
                ->leftJoin('customer_basic_data', 'ticket.customer_id', '=', 'customer_basic_data.customer_id')
                ->leftJoin('employee_basic_data as ticket_updater_ebd', 'ticket_updater_ebd.employee_id', '=', 'ticket.progress_updated_by')
                ->whereIn('ticket.ticket_id', $assignedTicketIds)
                ->whereIn('ticket.status', self::ACTIVE_STATUSES)
                ->whereNull('ticket.deleted_at')
                ->whereNull('ticket.is_hidden')
                ->select([
                    'ticket.ticket_id', 'ticket.ticket_number',
                    DB::raw("COALESCE(NULLIF(ticket.subject, ''), ticket.description) as subject"),
                    'ticket.status', 'ticket.ticket_priority', 'ticket.ticket_type',
                    'ticket.man_days', 'ticket.progress_percentage', 'ticket.progress_note',
                    'ticket.last_progress_at', 'ticket.module', 'ticket.start_date', 'ticket.end_date',
                    'ticket.resolution_days_status', 'ticket.mandays_proposal_status',
                    'ticket.ticket_lead_id',
                    'customer_basic_data.name_1 as customer_name',
                    DB::raw("NULLIF(TRIM(CONCAT(COALESCE(ticket_updater_ebd.first_name,''), ' ', COALESCE(ticket_updater_ebd.last_name,''))), '') as last_progress_by_name"),
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
            $consultantDetails = ConsultantWorkloadController::consultantDetailsForTickets($ticketIds);
            $modulesMap        = ConsultantWorkloadController::modulesMapForEmployees([$empId]);
            $myModules         = $modulesMap[$empId] ?? '-';

            // Tiket dengan konfirmasi take-ticket yang sudah confirmed juga dianggap
            // punya man_days asli (bukan cuma placeholder headcount), sama seperti
            // Ticket::hasRealManDays().
            $confirmedTicketIds = DB::table('ticket_confirmation')
                ->whereIn('ticket_id', $ticketIds)
                ->where('status', 'confirmed')
                ->pluck('ticket_id')
                ->flip();

            $ticketsData = $tickets->map(function ($ticket) use ($progressMap, $consultantDetails, $confirmedTicketIds, $empId) {
                $tid = $ticket->ticket_id;
                $hasRealManDays = $ticket->resolution_days_status === 'approved'
                    || $ticket->mandays_proposal_status === 'approved'
                    || $confirmedTicketIds->has($tid);

                return [
                    'ticket_id'           => $tid,
                    'ticket_number'       => $ticket->ticket_number,
                    'subject'             => $ticket->subject,
                    'status'              => $ticket->status,
                    'ticket_priority'     => $ticket->ticket_priority,
                    'ticket_type'         => $ticket->ticket_type,
                    'man_days'            => (float) $ticket->man_days,
                    'has_real_man_days'   => $hasRealManDays,
                    'is_lead'             => (int) $ticket->ticket_lead_id === $empId,
                    'progress_percentage' => (float) $ticket->progress_percentage,
                    'progress_note'       => $ticket->progress_note,
                    'last_progress_at'    => $ticket->last_progress_at,
                    'last_progress_by_name' => $ticket->last_progress_by_name,
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
}

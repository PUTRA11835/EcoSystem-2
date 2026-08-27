<?php

namespace App\Services\Ai\Tools;

use App\Models\Employee;
use App\Models\Ticket;
use App\Models\TicketSla;
use App\Services\SlaService;
use Illuminate\Support\Carbon;

class GetSlaSummaryTool implements AiTool
{
    public function __construct(private SlaService $sla)
    {
    }

    public function definition(): array
    {
        return [
            'name' => 'get_sla_summary',
            'description' => 'Look up SLA (response/resolution due dates, breach status) for one specific ticket, or list '
                . 'tickets that are close to or past their SLA resolution deadline. Use this for any question about SLA '
                . 'due dates, "first resolved" dates, or whether a ticket is breaching/at risk of breaching SLA. All dates '
                . 'and breach calculations here are computed by the same service the official SLA Report page uses — do '
                . 'not attempt to compute SLA timing yourself from raw ticket timestamps.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'ticket_id' => [
                        'type' => 'integer',
                        'description' => 'Look up SLA detail for this one ticket. Omit to list multiple tickets instead.',
                    ],
                    'only_at_risk' => [
                        'type' => 'boolean',
                        'description' => 'When listing multiple tickets (ticket_id omitted), only include tickets that are '
                            . 'already past their resolution due date or due within the next 24 hours. Default false.',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of tickets to include when listing (ticket_id omitted). Default 10, maximum 30.',
                    ],
                ],
                'required' => [],
            ],
        ];
    }

    public function run(Employee $employee, array $input): array
    {
        if (!$employee->hasPermission('sla.report')) {
            return [
                'error' => "The current user doesn't have permission to view SLA report data.",
            ];
        }

        if (!empty($input['ticket_id'])) {
            return $this->singleTicket($employee, (int) $input['ticket_id']);
        }

        return $this->listTickets($employee, (bool) ($input['only_at_risk'] ?? false), (int) ($input['limit'] ?? 10));
    }

    private function singleTicket(Employee $employee, int $ticketId): array
    {
        $ticket = Ticket::find($ticketId, ['ticket_id', 'ticket_number', 'ticket_priority', 'status']);
        if (!$ticket) {
            return ['error' => "No ticket found with id {$ticketId}."];
        }

        $sla = TicketSla::with(['policy', 'ticket'])->where('ticket_id', $ticketId)->first();
        if (!$sla) {
            return ['error' => "Ticket {$ticket->ticket_number} has no SLA record (SLA may not apply to this ticket)."];
        }

        return ['ticket' => $this->formatRow($ticket, $sla)];
    }

    private function listTickets(Employee $employee, bool $onlyAtRisk, int $limit): array
    {
        $limit = max(1, min($limit, 30));

        $slas = TicketSla::with(['policy', 'ticket'])
            ->whereHas('ticket', fn ($q) => $q->whereNotIn('status', ['closed', 'cancelled']))
            ->whereNotIn('resolution_status', ['met', 'breached'])
            ->orderBy('resolution_due_at')
            ->limit(200) // bound the raw scan; final limit applied after live computation
            ->get();

        $rows = [];
        foreach ($slas as $sla) {
            if (!$sla->ticket) {
                continue;
            }

            $dueAt = $this->sla->resolutionDueAt($sla);
            if ($onlyAtRisk && (!$dueAt || $dueAt->isAfter(Carbon::now()->addDay()))) {
                continue;
            }

            $rows[] = $this->formatRow($sla->ticket, $sla);
            if (count($rows) >= $limit) {
                break;
            }
        }

        return [
            'count' => count($rows),
            'tickets' => $rows,
        ];
    }

    private function formatRow(Ticket $ticket, TicketSla $sla): array
    {
        $resolutionDueAt = $this->sla->resolutionDueAt($sla);

        return [
            'ticket_id' => $ticket->ticket_id,
            'ticket_number' => $ticket->ticket_number,
            'priority' => $ticket->ticket_priority,
            'status' => $ticket->status,
            'resolution_status' => $sla->resolution_status,
            'response_due_at' => optional($this->sla->responseDueAt($sla))->toIso8601String(),
            'resolution_due_at' => optional($resolutionDueAt)->toIso8601String(),
            'first_resolved_at' => optional($this->sla->firstResolvedAt($sla))->toIso8601String(),
            'is_overdue' => $resolutionDueAt ? $resolutionDueAt->isPast() : null,
        ];
    }
}

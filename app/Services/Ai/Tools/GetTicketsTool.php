<?php

namespace App\Services\Ai\Tools;

use App\Models\Employee;
use App\Models\Ticket;

class GetTicketsTool implements AiTool
{
    public function definition(): array
    {
        return [
            'name' => 'get_tickets',
            'description' => 'Look up EcoSystem support tickets to answer questions about them or to summarize them. '
                . 'Use this whenever the user asks about "my tickets", ticket status/priority/counts, or wants a summary '
                . 'of open/recent tickets. Do not use this for SLA due-date or breach questions — use get_sla_summary instead. '
                . 'Do not use this for delivery/implementation project questions — use get_delivery_projects instead.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'scope' => [
                        'type' => 'string',
                        'enum' => ['mine', 'all'],
                        'description' => 'Whose tickets to look up. "mine" (default) is the tickets assigned to the '
                            . 'current user (as lead, member, or via mandays allocation). "all" asks for every ticket '
                            . 'in the organization, which only works if the user has that permission — otherwise this '
                            . 'silently falls back to "mine".',
                    ],
                    'status' => [
                        'type' => 'string',
                        'enum' => ['open', 'inprocess', 'waiting_on_customer', 'waiting_on_3rd_party', 'waiting_to_confirmation', 'hold', 'cancelled', 'closed'],
                        'description' => 'Filter to a single ticket status. Omit to include all statuses.',
                    ],
                    'priority' => [
                        'type' => 'string',
                        'enum' => ['Low', 'Medium', 'High', 'Very High'],
                        'description' => 'Filter to a single priority. Omit to include all priorities.',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of tickets to return. Default 20, maximum 50.',
                    ],
                ],
                'required' => [],
            ],
        ];
    }

    public function run(Employee $employee, array $input): array
    {
        $limit = (int) ($input['limit'] ?? 20);
        $limit = max(1, min($limit, 50));

        $scope = $input['scope'] ?? 'mine';
        $note = null;

        if ('all' === $scope && !$employee->hasMenuPermission('ticket.all-tickets')) {
            $scope = 'mine';
            $note = "The current user doesn't have permission to view all organization tickets — showing their own assigned tickets instead.";
        }

        $query = Ticket::query()->with(['ticketLead:employee_id,employee_id']);

        if ('mine' === $scope) {
            $query->whereIn('ticket_id', Ticket::assignedTicketIds($employee->employee_id));
        }

        if (!empty($input['status'])) {
            $query->where('status', $input['status']);
        }

        if (!empty($input['priority'])) {
            $query->where('ticket_priority', $input['priority']);
        }

        $tickets = $query
            ->orderByDesc('last_message_at')
            ->limit($limit)
            ->get([
                'ticket_id', 'ticket_number', 'status', 'ticket_priority', 'ticket_type',
                'description', 'ticket_lead_id', 'progress_percentage', 'start_date',
                'end_date', 'last_message_at', 'created_at',
            ]);

        $rows = $tickets->map(fn (Ticket $t) => [
            'ticket_id' => $t->ticket_id,
            'ticket_number' => $t->ticket_number,
            'status' => $t->status,
            'priority' => $t->ticket_priority,
            'type' => $t->ticket_type,
            'description' => $t->description,
            'progress_percentage' => $t->progress_percentage,
            'start_date' => optional($t->start_date)->toDateString(),
            'end_date' => optional($t->end_date)->toDateString(),
            'last_message_at' => optional($t->last_message_at)->toIso8601String(),
        ])->all();

        return array_filter([
            'scope' => $scope,
            'count' => count($rows),
            'tickets' => $rows,
            'note' => $note,
        ], fn ($v) => null !== $v);
    }
}

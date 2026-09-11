<?php

namespace App\Services\Ai\Tools;

use App\Models\DeliveryProject;
use App\Models\Employee;

class GetDeliveryProjectsTool implements AiTool
{
    public function definition(): array
    {
        return [
            'name' => 'get_delivery_projects',
            'description' => 'Look up delivery (implementation) projects and their schedule status. Use this for questions '
                . 'like "which projects are behind schedule" or "what is the progress of project X for client Y". The '
                . 'status field already reflects the project\'s Schedule Performance Index band (On Track / At Risk / '
                . 'At Critical) — do not try to recompute it. Do not use this for ticket or SLA questions.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'status' => [
                        'type' => 'string',
                        'enum' => ['On Track', 'At Risk', 'At Critical'],
                        'description' => 'Filter to projects in this schedule-status band. Omit to include all statuses. '
                            . '"behind schedule" questions map to "At Risk" and "At Critical".',
                    ],
                    'client_name' => [
                        'type' => 'string',
                        'description' => 'Filter to projects for a client whose name contains this text (partial match).',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of projects to return. Default 20, maximum 50.',
                    ],
                ],
                'required' => [],
            ],
        ];
    }

    public function run(Employee $employee, array $input): array
    {
        if (!$employee->canAccessMenu('delivery.project')) {
            return [
                'error' => "The current user doesn't have access to the Delivery Project module.",
            ];
        }

        $limit = (int) ($input['limit'] ?? 20);
        $limit = max(1, min($limit, 50));

        $query = DeliveryProject::query()->with('client.basicData');

        if (!empty($input['status'])) {
            $query->where('status', $input['status']);
        }

        if (!empty($input['client_name'])) {
            $needle = $input['client_name'];
            $query->whereHas('client.basicData', fn ($q) => $q->where('name_1', 'like', "%{$needle}%"));
        }

        $projects = $query->latest()->limit($limit)->get();

        $rows = $projects->map(fn (DeliveryProject $p) => [
            'id' => $p->id,
            'status' => $p->status,
            'phase' => $p->phase,
            'progress_percent' => $p->calculated_progress,
            'client_name' => $p->client?->basicData?->name_1,
        ])->all();

        return [
            'count' => count($rows),
            'projects' => $rows,
        ];
    }
}

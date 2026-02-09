<?php

namespace App\Exports;

use App\Models\DeliveryProject;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class TableViewExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    protected $project;
    protected $rows = [];

    public function __construct(DeliveryProject $project)
    {
        $this->project = $project;
        $this->prepareData();
    }

    private function prepareData()
    {
        // One-to-Many relationship (phases belong to project)
        $phases = $this->project->phases()
            ->where('is_visible', true)
            ->orderBy('order_sequence')
            ->get();

        foreach ($phases as $phase) {
            // Phase row
            $this->rows[] = [
                'type' => 'phase',
                'level' => 0,
                'name' => strtoupper($phase->name),
                'weight' => $phase->weight,
                'module' => 'Phase Level',
                'object' => '-',
                'start_date' => $this->getPhaseStartDate($phase),
                'end_date' => $this->getPhaseEndDate($phase),
                'duration' => $this->getPhaseDuration($phase),
                'status' => $this->getPhaseStatus($phase),
                'progress' => $this->getPhaseProgress($phase),
            ];

            // Get groups for this phase
            $groups = $this->project->plannings()
                ->where('phase_id', $phase->id)
                ->where('is_group', true)
                ->whereNull('parent_id')
                ->orderBy('order_sequence')
                ->get();

            foreach ($groups as $group) {
                $this->addGroupRecursive($group, 1);
            }
        }
    }

    private function addGroupRecursive($group, $level)
    {
        // Group row
        $this->rows[] = [
            'type' => 'group',
            'level' => $level,
            'name' => str_repeat('  ', $level) . '📁 ' . $group->name,
            'weight' => $group->calculated_weight ?? 0,
            'module' => $group->notes ?? 'Group',
            'object' => '-',
            'start_date' => $this->formatDate($group->calculated_start_date),
            'end_date' => $this->formatDate($group->calculated_end_date),
            'duration' => $this->calculateDuration($group->calculated_start_date, $group->calculated_end_date),
            'status' => $group->status ?? 'not_started',
            'progress' => $group->calculated_progress ?? 0,
        ];

        // Sub-groups
        $subGroups = $group->children()->where('is_group', true)->orderBy('order_sequence')->get();
        foreach ($subGroups as $subGroup) {
            $this->addGroupRecursive($subGroup, $level + 1);
        }

        // Stages
        $stages = $group->stages()->orderBy('order_sequence')->get();
        foreach ($stages as $stage) {
            $this->addStage($stage, $level + 1);
        }
    }

    private function addStage($stage, $level)
    {
        // Stage row
        $this->rows[] = [
            'type' => 'stage',
            'level' => $level,
            'name' => str_repeat('  ', $level) . '⭐ ' . $stage->name,
            'weight' => $stage->weight ?? 0,
            'module' => $stage->description ?? 'Stage',
            'object' => '-',
            'start_date' => $this->formatDate($stage->planned_start_date),
            'end_date' => $this->formatDate($stage->planned_end_date),
            'duration' => $stage->duration_days ?? '-',
            'status' => $stage->status ?? 'not_started',
            'progress' => $stage->progress ?? 0,
        ];

        // Activities - Get project activities (new structure)
        $activities = $stage->projectActivities()->orderBy('order_sequence')->get();
        foreach ($activities as $activity) {
            $this->addActivity($activity, $level + 1);
        }
    }

    private function addActivity($activity, $level)
    {
        // Activity row
        $this->rows[] = [
            'type' => 'activity',
            'level' => $level,
            'name' => str_repeat('  ', $level) . '📄 ' . $activity->name,
            'weight' => $activity->weight ?? 0,
            'module' => $activity->module ?? '-',
            'object' => $activity->object ?? '-',
            'start_date' => $this->formatDate($activity->start_date),
            'end_date' => $this->formatDate($activity->end_date),
            'duration' => $this->calculateDuration($activity->start_date, $activity->end_date),
            'status' => $activity->status ?? 'not_started',
            'progress' => $activity->progress_percentage ?? 0,
        ];

        // Note: In new structure, activities don't have children
        // All activities are direct children of stages
    }

    public function collection()
    {
        return collect($this->rows)->map(function($row) {
            return [
                $row['name'],
                $row['weight'] . '%',
                $row['module'],
                $row['object'],
                $row['start_date'],
                $row['end_date'],
                $row['duration'],
                $this->formatStatus($row['status']),
                $row['progress'] . '%',
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Phase / Group / Stage / Activity',
            'Weight %',
            'Module',
            'Object',
            'Start Date',
            'End Date',
            'Duration (Days)',
            'Status',
            'Progress',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Header styling
        $sheet->getStyle('A1:I1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color' => ['rgb' => '4F46E5']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Apply row styling based on type
        $rowNumber = 2;
        foreach ($this->rows as $row) {
            $style = [];
            
            switch ($row['type']) {
                case 'phase':
                    $style = [
                        'font' => ['bold' => true, 'size' => 12],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'color' => ['rgb' => 'E0E7FF']
                        ],
                    ];
                    break;
                case 'group':
                    $style = [
                        'font' => ['bold' => true],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'color' => ['rgb' => 'F3F4F6']
                        ],
                    ];
                    break;
                case 'stage':
                    $style = [
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'color' => ['rgb' => 'FEF3C7']
                        ],
                    ];
                    break;
            }

            if (!empty($style)) {
                $sheet->getStyle("A{$rowNumber}:I{$rowNumber}")->applyFromArray($style);
            }

            $rowNumber++;
        }

        // Borders for all cells
        $sheet->getStyle("A1:I" . ($rowNumber - 1))->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D1D5DB'],
                ],
            ],
        ]);

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 50,
            'B' => 12,
            'C' => 15,
            'D' => 20,
            'E' => 15,
            'F' => 15,
            'G' => 15,
            'H' => 15,
            'I' => 12,
        ];
    }

    public function title(): string
    {
        return 'Table View';
    }

    // Helper methods
    private function formatDate($date)
    {
        if (!$date) return '-';
        return $date instanceof \Carbon\Carbon ? $date->format('d M Y') : \Carbon\Carbon::parse($date)->format('d M Y');
    }

    private function formatStatus($status)
    {
        $statusMap = [
            'not_started' => 'Not Started',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'delayed' => 'Delayed',
            'on_hold' => 'On Hold',
        ];
        return $statusMap[$status] ?? 'Not Started';
    }

    private function calculateDuration($start, $end)
    {
        if (!$start || !$end) return '-';
        $startDate = $start instanceof \Carbon\Carbon ? $start : \Carbon\Carbon::parse($start);
        $endDate = $end instanceof \Carbon\Carbon ? $end : \Carbon\Carbon::parse($end);
        return $startDate->diffInDays($endDate) + 1;
    }

    private function getPhaseStartDate($phase)
    {
        // Calculate from groups
        return '-';
    }

    private function getPhaseEndDate($phase)
    {
        return '-';
    }

    private function getPhaseDuration($phase)
    {
        return '-';
    }

    private function getPhaseStatus($phase)
    {
        return 'in_progress';
    }

    private function getPhaseProgress($phase)
    {
        return 0;
    }
}
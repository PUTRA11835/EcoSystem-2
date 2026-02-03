<?php

namespace App\Exports;

use App\Models\Project;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithDrawings;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Carbon\Carbon;

class GanttViewExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    protected $project;
    protected $rows = [];
    protected $dates = [];

    public function __construct(Project $project)
    {
        $this->project = $project;
        $this->prepareGanttData();
    }

    private function prepareGanttData()
    {
        $phases = $this->project->phases()
            ->wherePivot('orientation', 'vertical')
            ->wherePivot('is_visible', true)
            ->orderBy('order_sequence')
            ->get();

        // Collect all dates
        $allDates = [];

        foreach ($phases as $phase) {
            $groups = $this->project->plannings()
                ->where('phase_id', $phase->id)
                ->where('is_group', true)
                ->whereNull('parent_id')
                ->orderBy('order_sequence')
                ->get();

            foreach ($groups as $group) {
                $this->collectDatesFromGroup($group, $allDates);
            }
        }

        // Generate date range
        if (!empty($allDates)) {
            sort($allDates);
            $startDate = Carbon::parse($allDates[0])->subWeek();
            $endDate = Carbon::parse($allDates[count($allDates) - 1])->addWeek();
            
            $this->generateDateColumns($startDate, $endDate);
        }

        // Build rows
        foreach ($phases as $phase) {
            $this->addPhaseRow($phase);
        }
    }

    private function collectDatesFromGroup($group, &$dates)
    {
        // Collect from stages
        foreach ($group->stages as $stage) {
            if ($stage->planned_start_date) {
                $dates[] = $stage->planned_start_date->format('Y-m-d');
            }
            if ($stage->planned_end_date) {
                $dates[] = $stage->planned_end_date->format('Y-m-d');
            }
        }

        // Collect from sub-groups recursively
        foreach ($group->children()->where('is_group', true)->get() as $subGroup) {
            $this->collectDatesFromGroup($subGroup, $dates);
        }
    }

    private function generateDateColumns($start, $end)
    {
        $current = $start->copy();
        while ($current <= $end) {
            $this->dates[] = $current->format('Y-m-d');
            $current->addDay();
        }
    }

    private function addPhaseRow($phase)
    {
        $groups = $this->project->plannings()
            ->where('phase_id', $phase->id)
            ->where('is_group', true)
            ->whereNull('parent_id')
            ->orderBy('order_sequence')
            ->get();

        // Phase header
        $phaseRow = [
            'type' => 'phase',
            'name' => strtoupper($phase->name),
            'start' => null,
            'end' => null,
            'progress' => 0,
        ];
        
        // Fill timeline
        foreach ($this->dates as $date) {
            $phaseRow[$date] = '';
        }
        
        $this->rows[] = $phaseRow;

        // Groups
        foreach ($groups as $group) {
            $this->addGroupRowRecursive($group, 1);
        }
    }

    private function addGroupRowRecursive($group, $level)
    {
        $groupRow = [
            'type' => 'group',
            'name' => str_repeat('  ', $level) . $group->name,
            'start' => null,
            'end' => null,
            'progress' => $group->calculated_progress ?? 0,
        ];

        // Calculate group dates
        $groupDates = $this->calculateGroupDates($group);
        $groupRow['start'] = $groupDates['start'];
        $groupRow['end'] = $groupDates['end'];

        // Fill timeline with bars
        foreach ($this->dates as $date) {
            $groupRow[$date] = $this->isDateInRange($date, $groupDates['start'], $groupDates['end']) ? '█' : '';
        }

        $this->rows[] = $groupRow;

        // Sub-groups
        foreach ($group->children()->where('is_group', true)->orderBy('order_sequence')->get() as $subGroup) {
            $this->addGroupRowRecursive($subGroup, $level + 1);
        }

        // Stages
        foreach ($group->stages()->orderBy('order_sequence')->get() as $stage) {
            $this->addStageRow($stage, $level + 1);
        }
    }

    private function addStageRow($stage, $level)
    {
        $stageRow = [
            'type' => 'stage',
            'name' => str_repeat('  ', $level) . $stage->name,
            'start' => $stage->planned_start_date ? $stage->planned_start_date->format('Y-m-d') : null,
            'end' => $stage->planned_end_date ? $stage->planned_end_date->format('Y-m-d') : null,
            'progress' => $stage->progress ?? 0,
        ];

        // Fill timeline
        foreach ($this->dates as $date) {
            $stageRow[$date] = $this->isDateInRange($date, $stageRow['start'], $stageRow['end']) ? '▓' : '';
        }

        $this->rows[] = $stageRow;
    }

    private function calculateGroupDates($group)
    {
        $dates = [];

        foreach ($group->stages as $stage) {
            if ($stage->planned_start_date) $dates[] = $stage->planned_start_date->format('Y-m-d');
            if ($stage->planned_end_date) $dates[] = $stage->planned_end_date->format('Y-m-d');
        }

        foreach ($group->children()->where('is_group', true)->get() as $subGroup) {
            $subDates = $this->calculateGroupDates($subGroup);
            if ($subDates['start']) $dates[] = $subDates['start'];
            if ($subDates['end']) $dates[] = $subDates['end'];
        }

        if (empty($dates)) {
            return ['start' => null, 'end' => null];
        }

        sort($dates);
        return [
            'start' => $dates[0],
            'end' => $dates[count($dates) - 1]
        ];
    }

    private function isDateInRange($date, $start, $end)
    {
        if (!$start || !$end) return false;
        return $date >= $start && $date <= $end;
    }

    public function collection()
    {
        return collect($this->rows)->map(function($row) {
            $data = [
                $row['name'],
                $row['start'] ?? '-',
                $row['end'] ?? '-',
                $row['progress'] . '%',
            ];

            // Add timeline columns
            foreach ($this->dates as $date) {
                $data[] = $row[$date] ?? '';
            }

            return $data;
        });
    }

    public function headings(): array
    {
        $headings = [
            'Task Name',
            'Start Date',
            'End Date',
            'Progress',
        ];

        // Add date headers
        foreach ($this->dates as $date) {
            $headings[] = Carbon::parse($date)->format('d M');
        }

        return $headings;
    }

    public function styles(Worksheet $sheet)
    {
        // Header styling
        $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($this->headings()));
        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
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

        // Row styling
        $rowNumber = 2;
        foreach ($this->rows as $row) {
            $color = match($row['type']) {
                'phase' => 'E0E7FF',
                'group' => 'F3F4F6',
                'stage' => 'FEF3C7',
                default => 'FFFFFF'
            };

            $sheet->getStyle("A{$rowNumber}:{$lastColumn}{$rowNumber}")->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'color' => ['rgb' => $color]
                ],
            ]);

            $rowNumber++;
        }

        return [];
    }

    public function columnWidths(): array
    {
        $widths = [
            'A' => 40,
            'B' => 12,
            'C' => 12,
            'D' => 10,
        ];

        // Date columns
        for ($i = 0; $i < count($this->dates); $i++) {
            $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(5 + $i);
            $widths[$column] = 3;
        }

        return $widths;
    }

    public function title(): string
    {
        return 'Gantt Chart';
    }
}
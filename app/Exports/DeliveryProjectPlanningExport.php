<?php


namespace App\Exports;

use App\Models\DeliveryProject;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class DeliveryProjectPlanningExport implements WithMultipleSheets
{
    protected $project;
    protected $data;

    public function __construct(DeliveryProject $project, array $data)
    {
        $this->project = $project;
        $this->data = $data;
    }

    public function sheets(): array
    {
        $sheets = [];

        // Summary sheet
        $sheets[] = new ProjectSummarySheet($this->project);

        // Sheet for each phase
        foreach ($this->data as $phaseData) {
            $sheets[] = new PhaseSheet($phaseData);
        }

        // Gantt Data sheet
        $sheets[] = new GanttDataSheet($this->project);

        return $sheets;
    }
}

class ProjectSummarySheet implements FromCollection, WithHeadings, WithStyles, WithColumnWidths
{
    protected $project;

    public function __construct(DeliveryProject $project)
    {
        $this->project = $project;
    }

    public function collection()
    {
        $phases = $this->project->phases()
            ->with(['weight', 'is_visible', 'orientation'])
            ->where('is_visible', true)
            ->get();

        $summaryData = collect([
            ['Project Information'],
            ['Project ID', $this->project->id],
            ['Description', $this->project->description],
            ['Client', $this->project->client->company_name ?? 'N/A'],
            ['Status', $this->project->status],
            ['Start Date', $this->project->start_date],
            ['End Date', $this->project->end_date],
            ['Overall Progress', $this->project->overall_progress . '%'],
            [],
            ['Phase Configuration'],
            ['Phase Name', 'Orientation', 'Weight (%)', 'Progress (%)'],
        ]);

        foreach ($phases as $phase) {
            $summaryData->push([
                $phase->name,
                $phase->orientation,
                $phase->weight,
                $phase->calculateProgress($this->project->id)
            ]);
        }

        return $summaryData;
    }

    public function headings(): array
    {
        return [];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            10 => ['font' => ['bold' => true, 'size' => 12]],
            11 => ['font' => ['bold' => true], 'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color' => ['rgb' => 'E7E6E6']
            ]],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 25,
            'B' => 20,
            'C' => 15,
            'D' => 15,
        ];
    }
}

class PhaseSheet implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths
{
    protected $phaseData;

    public function __construct(array $phaseData)
    {
        $this->phaseData = $phaseData;
    }

    public function collection()
    {
        $activities = collect();
        $this->flattenActivities($this->phaseData['activities'], $activities);
        return $activities;
    }

    private function flattenActivities($items, &$collection, $level = 0)
    {
        foreach ($items as $item) {
            $collection->push([
                'level' => $level,
                'task_title' => $item['name'],
                'is_group' => $item['is_group'],
                'module' => $item['module'] ?? '',
                'new_req' => $item['new_requirement'] ?? false,
                'object' => $item['object'] ?? '',
                'receive_type' => $item['receive_type'] ?? '',
                'complexity' => $item['complexity'] ?? '',
                'planned_start' => $item['start_date'],
                'planned_end' => $item['end_date'],
                'planned_days' => $item['planned_days'],
                'actual_start' => $item['actual_start_date'] ?? '',
                'actual_end' => $item['actual_end_date'] ?? '',
                'actual_days' => $item['actual_days'] ?? '',
                'status' => $item['status_text'],
                'progress' => $item['progress_percentage'],
                'deliverable' => $item['deliverable'] ?? '',
                'notes' => $item['notes'] ?? '',
            ]);

            if (isset($item['children']) && !empty($item['children'])) {
                $this->flattenActivities($item['children'], $collection, $level + 1);
            }
        }
    }

    public function map($row): array
    {
        $indent = str_repeat('  ', $row['level']);
        return [
            $indent . $row['task_title'],
            $row['module'],
            $row['new_req'] ? 'Yes' : 'No',
            $row['object'],
            $row['receive_type'],
            $row['complexity'],
            $row['planned_start'],
            $row['planned_end'],
            $row['planned_days'],
            $row['actual_start'],
            $row['actual_end'],
            $row['actual_days'],
            $row['status'],
            $row['progress'] . '%',
            $row['deliverable'],
            $row['notes'],
        ];
    }

    public function headings(): array
    {
        return [
            'Task Title',
            'Module',
            'New Req',
            'Object',
            'Receive Type',
            'Complexity',
            'Planned Start',
            'Planned End',
            'Planned Days',
            'Actual Start',
            'Actual End',
            'Actual Days',
            'Status',
            'Progress',
            'Deliverable',
            'Notes',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:R1')->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color' => ['rgb' => 'D4E6F1']
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
        ]);

        $sheet->setAutoFilter('A1:R1');

        $sheet->freezePane('A2');

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 30,
            'B' => 15,
            'C' => 10,
            'D' => 20,
            'E' => 15,
            'F' => 15,
            'G' => 20,
            'H' => 20,
            'I' => 12,
            'J' => 12,
            'K' => 10,
            'L' => 12,
            'M' => 12,
            'N' => 10,
            'O' => 15,
            'P' => 10,
            'Q' => 25,
            'R' => 30,
        ];
    }
}

namespace App\Imports;

use App\Models\Delivery;
use App\Models\DeliveryPlanning;
use App\Models\DeliveryActivity;
use App\Models\DeliveryCustomActivity;
use App\Models\DeliveryDynamicProjectPhase;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithValidation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeliveryProjectPlanningImport implements WithMultipleSheets
{
    protected $project;

    public function __construct(DeliveryProject $project)
    {
        $this->project = $project;
    }

    public function sheets(): array
    {
        return [
            'Summary' => new SummaryImport($this->project),
            '*' => new PhaseDataImport($this->project),
        ];
    }
}

class PhaseDataImport implements ToCollection, WithHeadingRow, WithValidation
{
    protected $project;
    protected $phaseName;

    public function __construct(DeliveryProject $project, $phaseName = null)
    {
        $this->project = $project;
        $this->phaseName = $phaseName;
    }

    public function collection(Collection $rows)
    {
        DB::transaction(function () use ($rows) {
            $parentMap = [];
            $previousLevel = 0;
            $parentStack = [];

            foreach ($rows as $index => $row) {
                $level = $this->detectIndentLevel($row['task_title']);
                $taskName = trim($row['task_title']);
                
                // Manage parent stack based on level
                if ($level < $previousLevel) {
                    // Pop items from stack
                    $parentStack = array_slice($parentStack, 0, $level);
                }
                
                $parentId = $level > 0 && !empty($parentStack) ? end($parentStack) : null;

                // Determine if this is a group
                $isGroup = $this->isGroupItem($row, $rows, $index);

                // Create planning record
                $planning = $this->createPlanningRecord($row, $parentId, $isGroup);

                if ($isGroup) {
                    $parentStack[] = $planning->id;
                }

                $previousLevel = $level;
            }
        });
    }

    private function createPlanningRecord($row, $parentId, $isGroup)
    {
        $taskName = trim(str_replace('  ', '', $row['task_title']));

        // Find or create activity
        $activity = null;
        if (!$isGroup) {
            $phase = $this->findPhaseByName($this->phaseName);
            if ($phase) {
                $activity = DeliveryProjectActivity::firstOrCreate(
                    [
                        'name' => $taskName,
                        'delivery_project_phase_id' => $phase->id,
                    ],
                    [
                        'description' => $row['notes'] ?? '',
                        'order_sequence' => 999,
                    ]
                );
            }
        }

        // Create planning record
        $planning = DeliveryProjectPlanning::create([
            'delivery_projects_id' => $this->project->id,
            'activity_id' => $activity ? $activity->id : null,
            'parent_id' => $parentId,
            'is_group' => $isGroup,
            'start_date' => $this->parseDate($row['planned_start']),
            'end_date' => $this->parseDate($row['planned_end']),
            'status' => $this->mapStatus($row['status']),
            'progress_percentage' => $this->parseProgress($row['progress']),
            'notes' => $isGroup ? $taskName : ($row['notes'] ?? null),
        ]);

        // Update activity with extended fields if exists
        if (!$isGroup && $activity) {
            $activity->update([
                'module' => $row['module'] ?? null,
                'new_requirement' => ($row['new_req'] ?? '') === 'Yes',
                'object' => $row['object'] ?? null,
                'receive_type' => $row['receive_type'] ?? null,
                'complexity' => $row['complexity'] ?? null,
                'deliverable' => $row['deliverable'] ?? null,
                'actual_start_date' => $this->parseDate($row['actual_start']),
                'actual_end_date' => $this->parseDate($row['actual_end']),
            ]);
        }

        return $planning;
    }

    private function detectIndentLevel($text)
    {
        $leadingSpaces = strlen($text) - strlen(ltrim($text));
        return floor($leadingSpaces / 2);
    }

    private function isGroupItem($row, $allRows, $currentIndex)
    {
        // Check if next item has deeper indent level
        if ($currentIndex + 1 < count($allRows)) {
            $currentLevel = $this->detectIndentLevel($row['task_title']);
            $nextLevel = $this->detectIndentLevel($allRows[$currentIndex + 1]['task_title']);
            return $nextLevel > $currentLevel;
        }
        return false;
    }

    private function findPhaseByName($name)
    {
        if (!$name) return null;
        return DeliveryDynamicProjectPhase::where('name', 'like', '%' . $name . '%')->first();
    }

    private function parseDate($dateString)
    {
        if (empty($dateString)) return null;
        
        try {
            if (is_numeric($dateString)) {
                // Excel date serial number
                return Carbon::createFromFormat('Y-m-d', '1900-01-01')
                    ->addDays($dateString - 2);
            }
            return Carbon::parse($dateString);
        } catch (\Exception $e) {
            Log::warning('Failed to parse date: ' . $dateString);
            return null;
        }
    }

    private function parseProgress($progressString)
    {
        if (empty($progressString)) return 0;
        $progress = str_replace('%', '', $progressString);
        return min(100, max(0, floatval($progress)));
    }

    private function mapStatus($statusText)
    {
        $statusText = strtolower(trim($statusText));
        $statusMap = [
            'not started' => 'not_started',
            'in progress' => 'in_progress',
            'completed'   => 'completed',
            'delayed'     => 'delayed',
        ];
        
        return $statusMap[$statusText] ?? 'not_started';
    }

    public function rules(): array
    {
        return [
            '*.task_title' => 'required|string',
            '*.planned_start' => 'nullable',
            '*.planned_end' => 'nullable',
            '*.status' => 'nullable|string',
            '*.progress' => 'nullable',
        ];
    }
}
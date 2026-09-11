<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Illuminate\Database\Eloquent\Collection;

class TimesheetApprovalExport implements
    FromCollection,
    WithHeadings,
    WithStyles,
    ShouldAutoSize
{
    private const LAST_COL = 'Q';

    protected Collection $rows;

    public function __construct(Collection $rows)
    {
        $this->rows = $rows;
    }

    public function collection(): \Illuminate\Support\Collection
    {
        return $this->rows->map(function ($ts) {
            $employeeName = trim(($ts->employee?->basicData?->first_name ?? '') . ' ' . ($ts->employee?->basicData?->last_name ?? ''));
            $ticket       = $ts->ticket;
            $projectName  = $ts->activity?->delivery_project?->name ?? $ts->delivery_project?->name;
            $approverName = trim(($ts->approver?->basicData?->first_name ?? '') . ' ' . ($ts->approver?->basicData?->last_name ?? ''));

            $type = 'Office';
            if ($ts->ticket_id)            $type = 'Support';
            elseif ($ts->delivery_projects_id) $type = 'Project';

            return [
                'employee'     => $employeeName ?: '-',
                'date'         => $ts->date?->format('d M Y') ?? '-',
                'month'        => $ts->period_month ?? '-',
                'year'         => $ts->period_year  ?? '-',
                'type'         => $type,
                'ticket'       => $ticket?->ticket_number ?? ($projectName ?? '-'),
                'customer'     => $ticket?->customer?->basicData?->name_1 ?? '-',
                'description'  => $ts->description ?? '-',
                'activity_type'=> $ts->activity_type ?? '-',
                'start_time'   => $ts->start_time ?? '-',
                'end_time'     => $ts->end_time   ?? '-',
                'duration'     => $ts->duration_minutes ? round($ts->duration_minutes / 60, 2) : '-',
                'md_consumed'  => $ts->md_consumed  ?? '-',
                'presence'     => $ts->presence     ?? '-',
                'status'       => ucfirst($ts->status ?? '-'),
                'approved_by'  => $approverName ?: '-',
                'approved_at'  => $ts->approved_at?->timezone('Asia/Jakarta')->format('d M Y H:i') ?? '-',
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Employee',
            'Date',
            'Month',
            'Year',
            'Type',
            'Ticket / Project',
            'Customer',
            'Description',
            'Activity Type',
            'Start Time',
            'End Time',
            'Duration (hrs)',
            'MD Consumed',
            'Presence',
            'Status',
            'Approved By',
            'Approved At',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = $this->rows->count() + 1;
        $lastCol = self::LAST_COL;

        $styles = [
            1 => [
                'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFCC0000']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ],
        ];

        for ($i = 2; $i <= $lastRow; $i++) {
            $rowArgb = ($i % 2 === 0) ? 'FFF9FAFB' : 'FFFFFFFF';
            $styles["A{$i}:{$lastCol}{$i}"] = [
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $rowArgb]],
            ];
        }

        return $styles;
    }
}

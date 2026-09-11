<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Export laporan Consultant Assignment — satu baris per penugasan, kolomnya
 * mengikuti tabel di halaman supaya angka di Excel bisa dicocokkan langsung
 * dengan yang dilihat user.
 */
class ConsultantAssignmentExport implements FromArray, WithEvents, ShouldAutoSize
{
    private const LAST_COL = 'V';

    /** Kolom pertama & terakhir yang di-center (tanggal s/d utilization). */
    private const CENTER_FROM = 'O';
    private const CENTER_TO   = 'U';

    protected Collection $rows;

    public function __construct(Collection $rows)
    {
        $this->rows = $rows;
    }

    public function array(): array
    {
        $out = [[
            'Consultant',
            'ECI',
            'Position',
            'Division',
            'Home Base',
            'Project',
            'IO Number',
            'Customer',
            'Project Category',
            'Project Phase',
            'Module',
            'Project Role',
            'Employee Type',
            'Vendor',
            'Start Date',
            'End Date',
            'Duration (Days)',
            'Assignment Status',
            'Planned MD',
            'Actual MD',
            'Utilization (%)',
            'Notes',
        ]];

        foreach ($this->rows as $r) {
            $out[] = [
                $r['consultant_name'],
                $r['eci'] !== '' ? $r['eci'] : '-',
                $r['position'] !== '' ? $r['position'] : '-',
                $r['division'] !== '' ? $r['division'] : '-',
                $r['home_base'] !== '' ? $r['home_base'] : '-',
                $r['project_name'],
                $r['io_number'] !== '' ? $r['io_number'] : '-',
                $r['customer_name'] !== '' ? $r['customer_name'] : '-',
                $r['project_category'] !== '' ? $r['project_category'] : '-',
                $r['project_phase'] !== '' ? $r['project_phase'] : '-',
                $r['module'] !== '' ? $r['module'] : '-',
                $r['role'],
                $r['employee_type'],
                $r['vendor_name'] !== '' ? $r['vendor_name'] : '-',
                $r['start_date'] ?: '-',
                $r['end_date'] ?: '-',
                $r['duration_days'] ?? '-',
                $r['assignment_status'],
                $r['planned_md'],
                $r['actual_md'],
                $r['utilization'] ?? '-',
                $r['notes'] !== '' ? $r['notes'] : '-',
            ];
        }

        return $out;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet   = $event->sheet->getDelegate();
                $lastCol = self::LAST_COL;
                $lastRow = $this->rows->count() + 1;

                $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
                    'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFCC0000']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
                ]);

                $sheet->freezePane('A2');

                if ($lastRow > 1) {
                    $sheet->getStyle(self::CENTER_FROM . '2:' . self::CENTER_TO . $lastRow)
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
            },
        ];
    }
}

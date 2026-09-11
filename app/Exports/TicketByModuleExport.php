<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Illuminate\Support\Collection;

// Menulis satu tiket per baris, dikelompokkan di bawah baris judul modul
// (merged, bold) alih-alih kolom "Modul" yang berulang di setiap baris.
class TicketByModuleExport implements FromArray, WithEvents, ShouldAutoSize
{
    protected Collection $groups;

    /** Nomor baris (1-indexed) yang merupakan judul modul, diisi saat array() dibuat. */
    protected array $titleRows = [];

    /** Nomor baris (1-indexed) berisi data tiket, diisi saat array() dibuat. */
    protected array $dataRows = [];

    private const LAST_COL = 'E';

    public function __construct(Collection $groups)
    {
        $this->groups = $groups;
    }

    public function array(): array
    {
        $rows = [['Ticket Number', 'Description', 'Ticket Lead', 'Created At', 'Day not Close']];
        $rowNumber = 1;

        foreach ($this->groups as $moduleName => $tickets) {
            $rowNumber++;
            $rows[] = [$moduleName, '', '', '', ''];
            $this->titleRows[] = $rowNumber;

            foreach ($tickets as $ticket) {
                $rowNumber++;
                $this->dataRows[] = $rowNumber;
                $rows[] = [
                    $ticket['ticket_number'],
                    $ticket['description'],
                    $ticket['lead_name'],
                    $ticket['created_at'],
                    $ticket['day_on_close'],
                ];
            }
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = self::LAST_COL;

                $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFCC0000']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                foreach ($this->titleRows as $row) {
                    $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
                    $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['argb' => 'FF1F2937']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE5E7EB']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
                    ]);
                }

                foreach ($this->dataRows as $row) {
                    $sheet->getStyle("D{$row}:{$lastCol}{$row}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
            },
        ];
    }
}

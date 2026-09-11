<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Export daftar tiket aktif milik SATU konsultan — isinya persis sub-tabel yang
 * muncul saat baris konsultan di halaman Consultant Workload di-expand
 * (kolom & angka sama supaya bisa dicocokkan langsung).
 *
 * Layout sheet:
 *   Baris 1        : judul "Consultant Workload — Tickets"
 *   Baris 2-5      : info konsultan (nama, ECI, modul, periode generate)
 *   Baris 6        : kosong
 *   Baris 7        : header tabel
 *   Baris 8..n     : satu tiket per baris
 *   Baris n+1      : TOTAL (Alloc / Add. / Remain)
 */
class ConsultantWorkloadTicketsExport implements FromArray, WithEvents, WithTitle, ShouldAutoSize
{
    private const LAST_COL   = 'O';
    private const HEADER_ROW = 15;

    /** status mesin => label yang dipakai di halaman. */
    private const STATUS_LABEL = [
        'open'                    => 'Open',
        'inprocess'              => 'Inprocess',
        'waiting_on_customer'    => 'Waiting Customer',
        'waiting_on_3rd_party'   => 'Waiting 3rd Party',
        'waiting_to_confirmation' => 'Waiting Confirmation',
        'hold'                   => 'Hold',
        'cancelled'              => 'Cancelled',
        'closed'                 => 'Closed',
    ];

    protected Collection $tickets;

    /** @var array{name:string,eci:?string,modules:?string} */
    protected array $consultant;

    protected int $dataRowCount = 0;

    public function __construct(array $consultant, Collection $tickets)
    {
        $this->consultant = $consultant;
        $this->tickets    = $tickets->values();
    }

    public function title(): string
    {
        return 'Tickets';
    }

    public function array(): array
    {
        $c = $this->consultant;

        $allocDays = (float) ($c['alloc_days'] ?? 0);
        $addDays   = (float) ($c['add_days'] ?? 0);
        $effMd     = (float) ($c['effective_md'] ?? 0);
        $remain    = (float) ($c['remain_days'] ?? 0);
        $wPct      = $c['workload_pct'] ?? 0;
        $nTicket   = (int) ($c['ticket_count'] ?? 0);
        $loadScore = $c['load_score'] ?? 0;

        $rows = [
            ['Consultant Workload — Tickets', '', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['Consultant',         $c['name'] ?? '-'],
            ['ECI',                $c['eci'] ?: '-'],
            ['Personnel Sub Area', $c['personnel_subarea'] ?: '-'],
            ['Current Assignment', $c['current_assignment'] ?: '-'],
            ['Module',             $c['modules'] && $c['modules'] !== '-' ? $c['modules'] : '-'],
            ['Tickets (active)',   $nTicket],
            ['Alloc Days',         number_format($allocDays, 2) . ' days'],
            ['Add. Days',          number_format($addDays, 2) . ' days'],
            ['Remain',             number_format($remain, 2) . ' days'],
            ['Workload %',         $wPct . '%  (' . number_format($remain, 2) . ' / ' . number_format($effMd, 2) . ' md)'],
            ['Load Score',         $loadScore . '  (' . number_format($remain, 2) . ' d × (1 + 0.1 × ' . $nTicket . '))'],
            ['Generated',          now()->format('d M Y H:i')],
            [''],
            [
                'No', 'Ticket No.', 'Subject', 'Customer', 'Role', 'Status', 'Priority',
                'Day not Close', 'Alloc Days', 'Add. Days', 'Remain',
                'Progress (%)', 'Progress Note', 'Last Updated', 'Updated By',
            ],
        ];

        $totAlloc = 0.0;
        $totAdd   = 0.0;
        $totRemain = 0.0;
        $no = 0;

        foreach ($this->tickets as $t) {
            $no++;
            $detail = collect($t->consultant_details ?? [])
                ->firstWhere('employee_id', $c['employee_id'] ?? null);

            $hasDetail = (bool) $detail;
            $alloc  = $hasDetail ? (float) ($detail['mandays'] ?? 0) : null;
            $add    = $hasDetail ? (float) ($detail['approved_additional'] ?? 0) : null;
            $remain = $hasDetail ? (float) ($detail['remain_md'] ?? 0) : null;
            $pct    = $hasDetail
                ? (float) ($detail['progress_percentage'] ?? 0)
                : (float) ($t->progress_percentage ?? 0);

            $totAlloc  += $alloc  ?? 0;
            $totAdd    += $add    ?? 0;
            $totRemain += $remain ?? 0;

            $note      = $hasDetail ? ($detail['progress_note'] ?? null) : ($t->progress_note ?? null);
            $updatedAt = $hasDetail ? ($detail['progress_updated_at'] ?? null) : ($t->last_progress_at ?? null);
            $updatedBy = $hasDetail ? ($detail['progress_updated_by_name'] ?? null) : ($t->last_progress_by_name ?? null);

            $rows[] = [
                $no,
                $t->ticket_number ?? '-',
                $t->subject ?: '-',
                $t->customer_name ?: '-',
                ($t->role_in_ticket ?? '') === 'pic' ? 'Ticket Lead' : 'Member',
                self::STATUS_LABEL[$t->status] ?? $t->status,
                $t->ticket_priority ?? '-',
                $this->dayNotClose($t->start_date ?? null) ?? '-',
                $alloc  !== null ? round($alloc, 2)  : 'Not determined',
                $add    !== null && $add > 0 ? round($add, 2) : '-',
                $remain !== null ? round($remain, 2) : '-',
                round($pct, 2),
                $note ?: '-',
                $updatedAt ? \Carbon\Carbon::parse($updatedAt)->format('d M Y H:i') : '-',
                $updatedBy ?: '-',
            ];
        }

        $this->dataRowCount = $no;

        if ($no > 0) {
            $rows[] = [
                '', '', '', '', '', '', '',
                'TOTAL',
                round($totAlloc, 2),
                round($totAdd, 2),
                round($totRemain, 2),
                '', '', '', '',
            ];
        } else {
            $rows[] = ['', 'No active tickets for this consultant.'];
        }

        return $rows;
    }

    /** Umur tiket dalam hari sejak start_date (first-assign), tidak pernah negatif. */
    private function dayNotClose(?string $startDate): ?int
    {
        if (!$startDate) {
            return null;
        }

        $start = \Carbon\Carbon::parse($startDate)->startOfDay();
        $today = now()->startOfDay();

        return (int) max(0, floor(($today->timestamp - $start->timestamp) / 86400));
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet    = $event->sheet->getDelegate();
                $lastCol  = self::LAST_COL;
                $headRow  = self::HEADER_ROW;
                $firstRow = $headRow + 1;
                $lastRow  = $headRow + $this->dataRowCount;
                $totalRow = $lastRow + 1;

                // Judul
                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 13, 'color' => ['argb' => 'FFCC0000']],
                ]);

                // Label info konsultan / ringkasan (kolom A baris 2 s/d header-1)
                $sheet->getStyle('A2:A' . ($headRow - 2))->getFont()->setBold(true);

                // Header tabel
                $sheet->getStyle("A{$headRow}:{$lastCol}{$headRow}")->applyFromArray([
                    'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFCC0000']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
                ]);

                $sheet->freezePane("A{$firstRow}");

                if ($this->dataRowCount > 0) {
                    // Border seluruh area data
                    $sheet->getStyle("A{$headRow}:{$lastCol}{$totalRow}")
                        ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                    // Center: No, Role, Status, Priority, Day not Close, Progress
                    foreach (['A', 'E', 'F', 'G', 'H', 'L'] as $col) {
                        $sheet->getStyle("{$col}{$firstRow}:{$col}{$lastRow}")
                            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    }

                    // Right: Alloc / Add. / Remain
                    foreach (['I', 'J', 'K'] as $col) {
                        $sheet->getStyle("{$col}{$firstRow}:{$col}{$lastRow}")
                            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    }

                    // Baris TOTAL
                    $sheet->getStyle("A{$totalRow}:{$lastCol}{$totalRow}")->applyFromArray([
                        'font' => ['bold' => true],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF3F4F6']],
                    ]);
                    $sheet->getStyle("H{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    foreach (['I', 'J', 'K'] as $col) {
                        $sheet->getStyle("{$col}{$totalRow}")
                            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    }
                }
            },
        ];
    }
}

<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Export daftar Delivery Project — satu baris per project, berisi seluruh data
 * yang tersebar di halaman detail (general, delivery info, financial, progress,
 * dan ringkasan section Issue/Risk/WRICEF/TOP) supaya bisa dianalisis di Excel
 * tanpa membuka project satu per satu.
 *
 * Baris sudah disiapkan lengkap oleh DeliveryProjectController::export();
 * kelas ini hanya memetakannya ke kolom + merapikan format.
 */
class DeliveryProjectExport implements FromArray, WithEvents, WithTitle, WithStrictNullComparison, ShouldAutoSize
{
    // WithStrictNullComparison: tanpa ini Laravel-Excel menulis setiap nilai 0
    // sebagai sel KOSONG — progres 0%, deviasi 0, dan hitungan 0 akan hilang.

    /** Kolom terakhir (58 kolom, lihat urutan header di array()). */
    private const LAST_COL = 'BF';

    /** Kolom angka rupiah — ditulis sebagai number, diformat #,##0. */
    private const MONEY_COLS = ['Y', 'Z', 'AA', 'AB', 'AD', 'AU', 'AV', 'AW'];

    /** Kolom angka desimal (persentase & SPI) — format 0.00. */
    private const DECIMAL_COLS = ['AC', 'AE', 'AF', 'AG', 'AH', 'AI'];

    /** Kolom yang di-center: tanggal, hitungan, dan flag. */
    private const CENTER_COLS = [
        'E', 'F', 'H', 'O', 'S', 'T', 'U', 'V', 'W', 'X',
        'AC', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AL',
        'AM', 'AN', 'AO', 'AP', 'AQ', 'AR', 'AS', 'AT',
        'BB', 'BC', 'BD', 'BE',
    ];

    protected Collection $rows;

    public function __construct(Collection $rows)
    {
        $this->rows = $rows;
    }

    public function title(): string
    {
        return 'Delivery Projects';
    }

    public function array(): array
    {
        $out = [[
            'Project Name',
            'IO Number',
            'Customer',
            'Project Type',
            'Category',
            'Status (SPI)',
            'Current Phase',
            'High Level Risk',
            'Project Owner',
            'Delivery Owner',
            'Delivery Manager',
            'Project Manager',
            'Co Project Manager',
            'Project Admin',
            'AE Type',
            'AE Name',
            'AE Email',
            'AE Phone',
            'Contract Start',
            'Contract End',
            'Go Live (Est.)',
            'Delivery Method',
            'Warranty (Months)',
            'Total Mandays',
            'Revenue',
            'Plan Cost',
            'Actual Cost',
            'Gross Profit (Plan)',
            'GP % (Plan)',
            'Gross Profit (Actual)',
            'GP % (Actual)',
            'Overall Progress (%)',
            'Planned Progress (%)',
            'Deviation (%)',
            'SPI',
            'Activities (Total)',
            'Activities (Done)',
            'Team Members',
            'Issues (Open)',
            'Issues (Total)',
            'Risks (Open)',
            'Risks (Total)',
            'WRICEF (Open)',
            'WRICEF (Total)',
            'Documents',
            'Payment Terms',
            'TOP Amount',
            'TOP Paid',
            'TOP Outstanding',
            'Location Name',
            'Location Type',
            'City',
            'Region',
            'Country',
            'Closed',
            'Closed At',
            'Last Update',
            'Description',
        ]];

        foreach ($this->rows as $r) {
            $out[] = [
                $r['name'],
                $r['io_number'],
                $r['customer'],
                $r['project_type'],
                $r['category'],
                $r['status'],
                $r['phase'],
                $r['high_level_risk'],
                $r['project_owner'],
                $r['delivery_owner'],
                $r['delivery_manager'],
                $r['project_manager'],
                $r['co_pm'],
                $r['project_admin'],
                $r['ae_type'],
                $r['ae_name'],
                $r['ae_email'],
                $r['ae_phone'],
                $r['contract_start_date'],
                $r['contract_end_date'],
                $r['go_live_estimated'],
                $r['delivery_method'],
                $r['warranty_period'],
                $r['total_mandays'],
                $r['revenue'],
                $r['plan_cost'],
                $r['actual_cost'],
                $r['gross_profit'],
                $r['gross_profit_percentage'],
                $r['actual_gross_profit'],
                $r['actual_gross_profit_percentage'],
                $r['overall_progress'],
                $r['planned_progress'],
                $r['progress_deviation'],
                $r['spi'],
                $r['activities_total'],
                $r['activities_done'],
                $r['team_members'],
                $r['issues_open'],
                $r['issues_total'],
                $r['risks_open'],
                $r['risks_total'],
                $r['wricefs_open'],
                $r['wricefs_total'],
                $r['documents'],
                $r['payment_terms'],
                $r['top_amount'],
                $r['top_paid'],
                $r['top_outstanding'],
                $r['location_name'],
                $r['location_type'],
                $r['location_city'],
                $r['location_region'],
                $r['location_country'],
                $r['is_closed'],
                $r['closed_at'],
                $r['updated_at'],
                $r['description'],
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

                // Nama project + IO tetap terlihat saat menggeser ke kolom kanan.
                $sheet->freezePane('C2');

                if ($lastRow <= 1) {
                    return;
                }

                foreach (self::MONEY_COLS as $col) {
                    $sheet->getStyle("{$col}2:{$col}{$lastRow}")->getNumberFormat()->setFormatCode('#,##0');
                }

                foreach (self::DECIMAL_COLS as $col) {
                    $sheet->getStyle("{$col}2:{$col}{$lastRow}")->getNumberFormat()->setFormatCode('0.00');
                }

                foreach (self::CENTER_COLS as $col) {
                    $sheet->getStyle("{$col}2:{$col}{$lastRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                // Deskripsi bisa panjang: batasi lebarnya dan bungkus teksnya
                // supaya autosize tidak melebarkan satu kolom sampai layar penuh.
                $sheet->getColumnDimension('BF')->setAutoSize(false)->setWidth(60);
                $sheet->getStyle("BF2:BF{$lastRow}")->getAlignment()->setWrapText(true);
            },
        ];
    }
}

<?php

namespace App\Exports;

use App\Models\DeliveryProject;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithCharts;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;

class SCurveExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithTitle, WithCharts
{
    protected $project;
    protected $data;

    public function __construct(DeliveryProject $project)
    {
        $this->project = $project;
        $this->loadData();
    }

    private function loadData()
    {
        // Get S-Curve data from ProjectDataController
        $controller = new \App\Http\Controllers\DeliveryProjectDataController();

        $response = $controller->getSCurveData($this->project->id);
        $this->data = json_decode($response->getContent(), true);

        // If the response has success wrapper, unwrap it
        if (isset($this->data['success']) && !$this->data['success']) {
            throw new \Exception('Failed to load S-curve data');
        }
    }

    public function collection()
    {
        $rows = collect();
        
        foreach ($this->data['weekly_data'] as $week) {
            $rows->push([
                $week['week_label'],
                $week['date_label'],
                $week['planned_cumulative'],
                $week['actual_cumulative'],
                $week['variance'],
            ]);
        }
        
        return $rows;
    }

    public function headings(): array
    {
        return [
            'Week',
            'Date Range',
            'Planned %',
            'Actual %',
            'Variance',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Header styling
        $sheet->getStyle('A1:E1')->applyFromArray([
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

        // Data styling
        $lastRow = count($this->data['weekly_data']) + 1;
        
        $sheet->getStyle("C2:C{$lastRow}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'DBEAFE']],
        ]);
        
        $sheet->getStyle("D2:D{$lastRow}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'D1FAE5']],
        ]);
        
        $sheet->getStyle("E2:E{$lastRow}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'FEF3C7']],
        ]);

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12,
            'B' => 15,
            'C' => 12,
            'D' => 12,
            'E' => 12,
        ];
    }

    public function title(): string
    {
        return 'S-Curve Analysis';
    }

    public function charts()
    {
        $lastRow = count($this->data['weekly_data']) + 1;
        
        // Create data series for Planned
        $plannedValues = new DataSeriesValues(
            DataSeriesValues::DATASERIES_TYPE_NUMBER,
            "S-Curve Analysis!C2:C{$lastRow}",
            null,
            $lastRow - 1
        );
        
        // Create data series for Actual
        $actualValues = new DataSeriesValues(
            DataSeriesValues::DATASERIES_TYPE_NUMBER,
            "S-Curve Analysis!D2:D{$lastRow}",
            null,
            $lastRow - 1
        );
        
        // Create categories
        $categories = new DataSeriesValues(
            DataSeriesValues::DATASERIES_TYPE_STRING,
            "S-Curve Analysis!A2:A{$lastRow}",
            null,
            $lastRow - 1
        );
        
        // Create series
        $series = new DataSeries(
            DataSeries::TYPE_LINECHART,
            null,
            range(0, 1),
            [
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, null, null, 1, ['Planned Progress']),
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, null, null, 1, ['Actual Progress']),
            ],
            [$categories, $categories],
            [$plannedValues, $actualValues]
        );
        
        $plotArea = new PlotArea(null, [$series]);
        $legend = new Legend(Legend::POSITION_RIGHT, null, false);
        $title = new Title('S-Curve: Planned vs Actual Progress');
        
        $chart = new Chart(
            'scurve_chart',
            $title,
            $legend,
            $plotArea
        );
        
        $chart->setTopLeftPosition('G2');
        $chart->setBottomRightPosition('O20');
        
        return [$chart];
    }
}
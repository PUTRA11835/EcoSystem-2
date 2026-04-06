<?php

namespace App\Http\Controllers;

use App\Models\DeliveryProject;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\TableViewExport;
use App\Exports\GanttViewExport;
use App\Exports\SCurveExport;

class DeliveryProjectPlanningExportController extends Controller
{
    /**
     * Export Planning to PDF
     */
    public function exportPlanningPDF(DeliveryProject $project)
    {
        try {
            Log::info('📄 Exporting Planning PDF', ['delivery_projects_id' => $project->id]);

            $dataController = new DeliveryProjectDataController();
            $response = $dataController->getTableData($project);
            $data = json_decode($response->getContent(), true);

            $pdf = PDF::loadView('project-planning.exports.table-pdf', [
                'project' => $project,
                'phasesData' => $data,
                'exportDate' => now()->format('d M Y'),
            ]);

            $pdf->setPaper('A4', 'landscape');

            $filename = 'project-planning-' . $project->id . '-' . now()->format('Y-m-d') . '.pdf';

            return $pdf->download($filename);

        } catch (\Exception $e) {
            Log::error('Error exporting planning PDF', [
                'delivery_projects_id' => $project->id,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Failed to export PDF: ' . $e->getMessage());
        }
    }

    /**
     * Export Gantt to PDF
     */
    public function exportGanttPDF(DeliveryProject $project)
    {
        try {
            Log::info('📄 Exporting Gantt PDF', ['delivery_projects_id' => $project->id]);

            $dataController = new DeliveryProjectDataController();
            $response = $dataController->getGanttData($project->id);
            $data = json_decode($response->getContent(), true);

            if (!$data['success']) {
                throw new \Exception('Failed to load gantt data');
            }

            $pdf = PDF::loadView('project-planning.exports.gantt-pdf', [
                'project' => $project,
                'vertical_groups' => $data['vertical_groups'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'exportDate' => now()->format('d M Y'),
            ]);

            $pdf->setPaper('A4', 'landscape');

            $filename = 'gantt-chart-' . $project->id . '-' . now()->format('Y-m-d') . '.pdf';

            return $pdf->download($filename);

        } catch (\Exception $e) {
            Log::error('Error exporting gantt PDF', [
                'delivery_projects_id' => $project->id,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Failed to export Gantt PDF: ' . $e->getMessage());
        }
    }

    /**
     * Export S-Curve to PDF
     */
    public function exportSCurvePDF(DeliveryProject $project)
    {
        try {
            Log::info('📄 Exporting S-Curve PDF', ['delivery_projects_id' => $project->id]);

            $dataController = new DeliveryProjectDataController();
            $response = $dataController->getSCurveData($project->id);
            $data = json_decode($response->getContent(), true);

            if (!$data['success']) {
                throw new \Exception('Failed to load S-curve data');
            }

            $pdf = PDF::loadView('project-planning.exports.scurve-pdf', [
                'project' => $project,
                'sCurveData' => [
                    'weekly_data' => $data['weekly_data'],
                    'statistics' => $data['statistics'],
                    'phases' => $data['phases'],
                    'start_date' => $data['start_date'],
                    'end_date' => $data['end_date'],
                ],
                'exportDate' => now()->format('d M Y'),
            ]);

            $pdf->setPaper('A4', 'landscape');

            $filename = 'scurve-analysis-' . $project->id . '-' . now()->format('Y-m-d') . '.pdf';

            return $pdf->download($filename);

        } catch (\Exception $e) {
            Log::error('Error exporting S-curve PDF', [
                'delivery_projects_id' => $project->id,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Failed to export S-Curve PDF: ' . $e->getMessage());
        }
    }

    /**
     * Export Planning Table to Excel
     */
    public function exportTableExcel(DeliveryProject $project)
    {
        try {
            Log::info('📊 Exporting Table Excel', ['delivery_projects_id' => $project->id]);

            $filename = 'project-planning-table-' . $project->id . '-' . now()->format('Y-m-d') . '.xlsx';

            return Excel::download(new TableViewExport($project), $filename);

        } catch (\Exception $e) {
            Log::error('Error exporting table Excel', [
                'delivery_projects_id' => $project->id,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Failed to export Table Excel: ' . $e->getMessage());
        }
    }

    /**
     * Export Gantt Chart to Excel
     */
    public function exportGanttExcel(DeliveryProject $project)
    {
        try {
            Log::info('📊 Exporting Gantt Excel', ['delivery_projects_id' => $project->id]);

            $filename = 'gantt-chart-' . $project->id . '-' . now()->format('Y-m-d') . '.xlsx';

            return Excel::download(new GanttViewExport($project), $filename);

        } catch (\Exception $e) {
            Log::error('Error exporting gantt Excel', [
                'delivery_projects_id' => $project->id,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Failed to export Gantt Excel: ' . $e->getMessage());
        }
    }

    /**
     * Export S-Curve to Excel
     */
    public function exportSCurveExcel(DeliveryProject $project)
    {
        try {
            Log::info('📊 Exporting S-Curve Excel', ['delivery_projects_id' => $project->id]);

            $filename = 'scurve-analysis-' . $project->id . '-' . now()->format('Y-m-d') . '.xlsx';

            return Excel::download(new SCurveExport($project), $filename);

        } catch (\Exception $e) {
            Log::error('Error exporting S-curve Excel', [
                'delivery_projects_id' => $project->id,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Failed to export S-Curve Excel: ' . $e->getMessage());
        }
    }
}
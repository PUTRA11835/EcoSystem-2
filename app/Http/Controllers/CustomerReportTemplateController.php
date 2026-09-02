<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\ReportTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Manajemen library template Word Report Generator dari halaman detail
 * Customer (tab "Report Templates") — lihat sections/report-templates.blade.php.
 * Digate lewat middleware 'customer.section:report_templates,...'
 * (CheckCustomerSectionAccess), section permission tersendiri yang TIDAK
 * otomatis ikut siapa pun yang boleh edit Customer secara umum.
 */
class CustomerReportTemplateController extends Controller
{
    public function index(int $customerId)
    {
        $templates = ReportTemplate::where('customer_id', $customerId)
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'original_filename', 'created_at']);

        return response()->json(['success' => true, 'data' => $templates]);
    }

    public function store(Request $request, int $customerId)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'template' => 'required|file|mimes:docx|max:10240', // 10 MB
        ]);

        $employee = Employee::find(session('user.id'));
        if (!$employee) {
            abort(401);
        }

        $path = $request->file('template')->store('report-templates');

        $template = ReportTemplate::create([
            'customer_id' => $customerId,
            'name' => $validated['name'],
            'original_filename' => $request->file('template')->getClientOriginalName(),
            'file_path' => $path,
            'uploaded_by' => $employee->employee_id,
        ]);

        return response()->json(['success' => true, 'data' => $template]);
    }

    public function destroy(int $customerId, ReportTemplate $reportTemplate)
    {
        abort_if($reportTemplate->customer_id !== $customerId, 404);

        Storage::delete($reportTemplate->file_path);
        $reportTemplate->delete();

        return response()->json(['success' => true]);
    }

    public function download(int $customerId, ReportTemplate $reportTemplate)
    {
        abort_if($reportTemplate->customer_id !== $customerId, 404);
        abort_if(!Storage::exists($reportTemplate->file_path), 404, 'File tidak ditemukan.');

        return Storage::download($reportTemplate->file_path, $reportTemplate->original_filename);
    }
}

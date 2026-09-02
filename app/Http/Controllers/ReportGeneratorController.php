<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateWordReportJob;
use App\Models\Employee;
use App\Models\ReportTemplate;
use App\Models\WordReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Pilih template .docx (tersimpan di library per customer — upload/kelola
 * lewat tab "Report Templates" di halaman detail Customer, lihat
 * CustomerReportTemplateController) -> generate laporan terisi data nyata
 * lewat ReportGeneratorService (queue job, lihat GenerateWordReportJob).
 * Lihat .claude/skills/laravel-word-report-generator/SKILL.md untuk alur
 * lengkap yang diikuti AI.
 */
class ReportGeneratorController extends Controller
{
    /**
     * Halaman uji coba sementara (form upload + poll status) — bukan UI final.
     */
    public function index()
    {
        return view('reports.generate');
    }

    /**
     * Cari/daftar template di library lintas-customer — dipakai picker di
     * halaman generate. Upload template TIDAK terjadi di sini (lihat
     * CustomerReportTemplateController); ini murni pencarian data yang
     * sudah ada, setiap hasil membawa nama customer-nya sebagai label.
     */
    public function templates(Request $request)
    {
        $q = trim((string) $request->get('q', ''));

        $templates = ReportTemplate::with('customer.basicData')
            ->when($q !== '', fn ($query) => $query->where('name', 'like', "%{$q}%"))
            ->orderByDesc('created_at')
            ->limit(30)
            ->get(['id', 'customer_id', 'name', 'original_filename', 'created_at']);

        return response()->json([
            'success' => true,
            'data' => $templates->map(fn (ReportTemplate $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'original_filename' => $t->original_filename,
                'customer_name' => $t->customer?->basicData?->name_1 ?? ($t->customer_id ? null : 'Umum / Internal'),
            ]),
        ]);
    }

    /**
     * Riwayat generate laporan milik employee yang sedang login — dipakai
     * panel History di halaman generate (pola sama seperti AI Research).
     */
    public function history()
    {
        $sessionUser = session('user');
        if (!$sessionUser || 'employee' !== ($sessionUser['type'] ?? null)) {
            abort(401);
        }

        $reports = WordReport::with('reportTemplate.customer.basicData')
            ->where('employee_id', $sessionUser['id'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $reports->map(fn (WordReport $r) => [
                'id' => $r->id,
                'template_name' => $r->reportTemplate?->name ?? $r->template_original_name,
                'customer_name' => $r->reportTemplate?->customer?->basicData?->name_1,
                'status' => $r->status,
                'instructions' => $r->instructions,
                'created_at' => $r->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function generate(Request $request)
    {
        $sessionUser = session('user');
        if (!$sessionUser || 'employee' !== ($sessionUser['type'] ?? null)) {
            abort(401);
        }

        $validated = $request->validate([
            'template_id' => 'required|integer|exists:report_templates,id',
            'instructions' => 'nullable|string|max:2000',
        ]);

        $employee = Employee::find($sessionUser['id']);
        if (!$employee) {
            abort(401);
        }

        $template = ReportTemplate::findOrFail($validated['template_id']);

        $report = WordReport::create([
            'employee_id' => $employee->employee_id,
            'report_template_id' => $template->id,
            'template_original_name' => $template->original_filename,
            'template_path' => $template->file_path,
            'instructions' => $validated['instructions'] ?? null,
            'status' => WordReport::STATUS_PENDING,
            'phase' => WordReport::PHASE_STRUCTURE,
        ]);

        GenerateWordReportJob::dispatch($report->id);

        return response()->json([
            'success' => true,
            'report_id' => $report->id,
            'status_url' => route('reports.status', $report),
        ]);
    }

    public function status(WordReport $report)
    {
        $this->authorizeOwner($report);

        return response()->json([
            'success' => true,
            'status' => $report->status,
            'phase' => $report->phase,
            'instructions' => $report->instructions,
            'question' => $report->question,
            'qa_log' => $report->qa_log ?? [],
            'docx_url' => $report->docx_path ? route('reports.download', [$report, 'docx']) : null,
            'pdf_url' => $report->pdf_path ? route('reports.download', [$report, 'pdf']) : null,
            'pdf_preview_url' => $report->pdf_path ? route('reports.preview', [$report, 'pdf']) : null,
            'summary' => $report->summary,
            'error_message' => $report->error_message,
        ]);
    }

    /**
     * User menjawab pertanyaan klarifikasi AI (status awaiting_input) ->
     * simpan jawabannya di qa_log lalu generate ULANG dari awal (baca
     * template + panggil tool lagi), kali ini dengan riwayat tanya-jawab
     * disisipkan ke prompt (lihat ReportGeneratorService::buildPrompt()).
     * Bukan resume persis di titik AI berhenti — restart yang aman karena
     * tool-nya read-only, jauh lebih robust daripada menyimpan-ulang state
     * mentah provider lintas job.
     */
    public function answer(Request $request, WordReport $report)
    {
        $this->authorizeOwner($report);

        abort_if(WordReport::STATUS_AWAITING_INPUT !== $report->status, 409, 'Laporan ini tidak sedang menunggu jawaban.');

        $validated = $request->validate([
            'answer' => 'required|string|max:2000',
        ]);

        $qaLog = $report->qa_log ?? [];
        if (!empty($qaLog)) {
            $qaLog[array_key_last($qaLog)]['answer'] = $validated['answer'];
        }

        $report->update([
            'qa_log' => $qaLog,
            'question' => null,
            'status' => WordReport::STATUS_PENDING,
        ]);

        GenerateWordReportJob::dispatch($report->id);

        return response()->json([
            'success' => true,
            'status_url' => route('reports.status', $report),
        ]);
    }

    /**
     * User menekan "Coba Lagi" (status failed) atau "Lanjutkan" (status
     * paused -- rate limit provider, lihat WordReport::STATUS_PAUSED) ->
     * dispatch ulang job TANPA menyentuh phase/structure_map/pulled_data,
     * jadi ReportGeneratorService::generate() resume dari fase terakhir
     * yang sudah tersimpan (bukan mengulang dari fase 1).
     */
    public function retry(WordReport $report)
    {
        $this->authorizeOwner($report);

        abort_if(
            !in_array($report->status, [WordReport::STATUS_FAILED, WordReport::STATUS_PAUSED], true),
            409,
            'Laporan ini tidak sedang gagal atau dijeda.',
        );

        $report->update([
            'status' => WordReport::STATUS_PENDING,
            'error_message' => null,
        ]);

        GenerateWordReportJob::dispatch($report->id);

        return response()->json([
            'success' => true,
            'status_url' => route('reports.status', $report),
        ]);
    }

    public function download(WordReport $report, string $type)
    {
        $this->authorizeOwner($report);

        $path = $this->resolveFilePath($report, $type);
        $downloadName = pathinfo($report->template_original_name, PATHINFO_FILENAME) . '.' . $type;

        return Storage::disk('public')->download($path, $downloadName);
    }

    /**
     * Tampilkan file inline (Content-Disposition: inline) — dipakai <iframe>
     * pratinjau PDF di tengah halaman, beda dari download() yang memaksa
     * "Save As".
     */
    public function preview(WordReport $report, string $type)
    {
        $this->authorizeOwner($report);

        $path = $this->resolveFilePath($report, $type);

        return Storage::disk('public')->response($path);
    }

    private function resolveFilePath(WordReport $report, string $type): string
    {
        $path = 'docx' === $type ? $report->docx_path : ('pdf' === $type ? $report->pdf_path : null);
        abort_if(!$path || !Storage::disk('public')->exists($path), 404, 'File tidak ditemukan.');

        return $path;
    }

    private function authorizeOwner(WordReport $report): void
    {
        $sessionUser = session('user');
        if (!$sessionUser || (int) $sessionUser['id'] !== $report->employee_id) {
            abort(403);
        }
    }
}

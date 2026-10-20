<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Http\Controllers\HR_General\Concerns\ChecksExternalAccess;
use App\Services\External\ExternalInvoiceService;
use App\Services\External\ExternalTimesheetService;
use App\Services\External\ExternalWorkflowService;
use App\Support\External\ExternalTimesheetRules as R;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * My Timesheet & Invoice (slug general.external.my-timesheet): halaman konsultan eksternal untuk dirinya sendiri — mengisi lembar jam
 * bulanan, mengajukan, mengunggah invoice setelah disetujui, dan melihat statusnya.
 *
 * Identitas SELALU dari sesi: lembar milik orang lain tidak terbuka (404), dan hanya karyawan berjenis External yang boleh masuk.
 * Tarif kontrak sendiri boleh dilihat konsultan.
 */
class ExternalMyTimesheetController extends Controller
{
    use ChecksExternalAccess;

    public function __construct(
        private ExternalTimesheetService $sheets,
        private ExternalInvoiceService $invoices,
        private ExternalWorkflowService $workflow,
    ) {}

    public function index(Request $request)
    {
        $me = $this->me();
        $periods = $this->sheets->periodsFor($me);

        // Lembar yang dibuka: ?sheet=ID, atau yang terbaru yang masih perlu tindakan, atau yang terbaru.
        $sheet = null;
        if ($request->filled('sheet')) {
            $sheet = $this->owned((int) $request->query('sheet'));
        } else {
            $pick = collect($periods)->first(fn ($p) => in_array($p['status'], [R::DRAFT, R::RETURNED, R::APPROVED], true)) ?? collect($periods)->first(fn ($p) => $p['sheet_id']);
            $sheet = $pick && $pick['sheet_id'] ? $this->sheets->find((int) $pick['sheet_id']) : null;
        }

        return view('hr-general.external.timesheet.my', [
            'periods'  => $periods,
            'sheet'    => $sheet,
            'summary'  => $this->sheets->summary($me),
            'amounts'  => true,                       // tarif dan bruto miliknya sendiri
            'projects' => $this->projects(),
            'verification' => $sheet ? R::verificationCode((int) $sheet->id, $sheet->sheet_no, (string) config('app.key')) : null,
            'approvers' => $sheet ? $this->approverNames((int) $sheet->id, $me) : [],
            'saveUrl'  => $sheet ? route('general.external.my-timesheet.save', $sheet->id) : null,
            'submitUrl' => $sheet ? route('general.external.my-timesheet.submit', $sheet->id) : null,
            'self'     => true,
        ]);
    }

    public function open(Request $request)
    {
        $d = $request->validate(['contract_id' => 'required|integer', 'year' => 'required|integer|min:2020|max:2100', 'month' => 'required|integer|min:1|max:12']);

        try {
            $id = $this->sheets->open($this->me(), (int) $d['contract_id'], (int) $d['year'], (int) $d['month'], $this->me());
        } catch (\DomainException $e) {
            return back()->withErrors(['sheet' => $e->getMessage()]);
        }

        return redirect()->route('general.external.my-timesheet.index', ['sheet' => $id]);
    }

    public function save(Request $request, int $id)
    {
        $this->owned($id);

        try {
            $this->sheets->save($id, (array) $request->input('entries', []), $this->me(), $this->milestone($request));
        } catch (\DomainException $e) {
            return back()->withInput()->withErrors(['sheet' => $e->getMessage()]);
        }

        return redirect()->route('general.external.my-timesheet.index', ['sheet' => $id])->with('success', 'Timesheet saved.');
    }

    public function submit(Request $request, int $id)
    {
        $this->owned($id);

        try {
            if ($request->has('entries')) {
                $this->sheets->save($id, (array) $request->input('entries', []), $this->me(), $this->milestone($request));
            }
            $this->sheets->submit($id, $this->me());
        } catch (\DomainException $e) {
            return back()->withInput()->withErrors(['sheet' => $e->getMessage()]);
        }

        return redirect()->route('general.external.my-timesheet.index', ['sheet' => $id])->with('success', 'Timesheet submitted. You will be notified when it is approved or returned.');
    }

    public function uploadInvoice(Request $request, int $id)
    {
        $this->owned($id);
        $d = $request->validate(['invoice_no' => 'required|string|max:100', 'invoice_date' => 'required|date', 'amount' => 'required|string|max:30',
            'invoice_file' => 'required|file|max:10240'], ['invoice_file.max' => 'The file is larger than 10 MB.']);

        try {
            $this->invoices->upload($id, $d, $request->file('invoice_file'), $this->me());
        } catch (\DomainException $e) {
            return back()->withInput()->withErrors(['sheet' => $e->getMessage()]);
        }

        return redirect()->route('general.external.my-timesheet.index', ['sheet' => $id])->with('success', 'Invoice uploaded. Finance will verify it.');
    }

    public function invoiceFile(Request $request, int $id)
    {
        $this->owned($id);
        $file = $this->invoices->file($id);
        abort_if($file === null, 404);

        return Storage::disk(ExternalInvoiceService::DISK)->response($file['path'], $file['name'], ['Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff'],
            $request->boolean('download') ? 'attachment' : 'inline');
    }

    public function print(int $id)
    {
        $sheet = $this->owned($id);

        return view('hr-general.external.timesheet.print', [
            'sheet' => $sheet, 'amounts' => true,
            'verification' => R::verificationCode($id, $sheet->sheet_no, (string) config('app.key')),
            'approver' => $sheet->approved_by ? ($sheet->actor_names[$sheet->approved_by] ?? '') : '',
            'company' => DB::table('letter_settings')->value('company_name') ?: config('app.name'),
        ]);
    }

    // ── helpers ───────────────────────────────────────────────────────────

    /** Persen tagihan fixed price dari formulir (kosong = tidak diubah). */
    private function milestone(Request $request): ?float
    {
        $v = trim((string) $request->input('milestone_pct', ''));

        return $v === '' ? null : (float) str_replace(',', '.', $v);
    }

    /** Id konsultan yang masuk; selain karyawan External ditolak. */
    private function me(): int
    {
        $id = $this->actorId();
        abort_unless(DB::table('employee_basic_data')->where('employee_id', $id)->where('employee_type', 'External')->exists(), 403,
            'This page is for external consultants.');

        return $id;
    }

    /** Lembar milik sendiri, atau 404 (tidak membocorkan bahwa lembar orang lain ada). */
    private function owned(int $id): object
    {
        $sheet = $this->sheets->find($id);
        abort_if($sheet === null || (int) $sheet->employee_id !== $this->me(), 404);

        return $sheet;
    }

    /** @return array<string,string[]> */
    private function approverNames(int $sheetId, int $consultantId): array
    {
        $out = [];
        foreach (array_keys(R::STAGES) as $stage) {
            $ids = $this->workflow->approversOf($sheetId, $stage, $consultantId);
            $names = $this->workflow->names($ids);
            $out[$stage] = array_values(array_map(fn ($i) => $names[$i] ?? ('#' . $i), $ids));
        }

        return $out;
    }

    /** @return array<int,array{id:int,label:string}> */
    private function projects(): array
    {
        return DB::table('delivery_projects')->where('is_closed', 0)->orderBy('name')->limit(300)
            ->get(['id', 'name', 'io_number'])->map(fn ($p) => ['id' => (int) $p->id, 'label' => trim(($p->io_number ? $p->io_number . ' - ' : '') . ($p->name ?? ''))])->all();
    }
}

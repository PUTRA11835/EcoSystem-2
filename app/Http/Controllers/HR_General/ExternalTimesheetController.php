<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Http\Controllers\HR_General\Concerns\ChecksExternalAccess;
use App\Http\Controllers\HR_General\Concerns\HandlesRecruitmentTables;
use App\Services\External\ExternalConsultantService;
use App\Services\External\ExternalInvoiceService;
use App\Services\External\ExternalTimesheetService;
use App\Services\External\ExternalWorkflowService;
use App\Support\External\ExternalRules;
use App\Support\External\ExternalTimesheetRules as R;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * External → Timesheet & Payment (HR / RPMO / Finance): daftar lembar jam, halaman tinjau, setujui / kembalikan, cetak, serta daftar
 * invoice dengan verifikasi dan Paid. Konsultan sendiri memakai ExternalMyTimesheetController.
 *
 * Izin: tab Timesheets `general.external.timesheet` (V lihat · C buat lembar atas nama · E ubah/hapus atas nama); tab Invoices
 * `general.external.timesheet.invoices`. Menyetujui/mengembalikan/memverifikasi mengikuti PENYETUJU di Settings (bukan kotak C/E/D);
 * bila tahap itu tak punya penyetuju sama sekali, pemegang kotak Edit boleh bertindak supaya alur tidak buntu.
 * Tarif, bruto, dan jumlah invoice hanya untuk pemegang `general.external.timesheet.amount`.
 */
class ExternalTimesheetController extends Controller
{
    use ChecksExternalAccess;
    use HandlesRecruitmentTables;

    public const SLUG = 'general.external.timesheet';
    public const SLUG_INVOICES = 'general.external.timesheet.invoices';

    public function __construct(
        private ExternalTimesheetService $sheets,
        private ExternalInvoiceService $invoices,
        private ExternalWorkflowService $workflow,
        private ExternalConsultantService $consultants,
    ) {}

    // ── Daftar ────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        return $this->listPage($request, false);
    }

    public function invoicesIndex(Request $request)
    {
        return $this->listPage($request, true);
    }

    private function listPage(Request $request, bool $invoiceView)
    {
        $filters = [
            'search'  => trim((string) $request->query('search')),
            'status'  => (string) $request->query('status'),
            'month'   => (string) $request->query('month'),
            'invoice' => (string) $request->query('invoice'),
        ];
        $perPage = $this->perPage($request);
        $amounts = $this->canView('general.external.timesheet.amount');

        $data = [
            'rows'           => $this->sheets->listing($filters, $perPage, $amounts, null, $invoiceView),
            'filters'        => $filters,
            'perPage'        => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'amounts'        => $amounts,
            'invoiceView'    => $invoiceView,
            'filterForm'     => $invoiceView ? 'extInvoiceFilters' : 'extSheetFilters',
        ];

        if ($request->ajax()) {
            return view('hr-general.external.timesheet.components.list-table', $data);
        }

        return view('hr-general.external.timesheet.index', $data + $this->tabs() + [
            'summary'     => $this->sheets->summary(),
            'canCreate'   => !$invoiceView && $this->employeeCan(self::SLUG, 'create'),
            'consultants' => $invoiceView ? [] : $this->consultants->options(),
            'periods'     => $invoiceView ? [] : $this->periodsByConsultant(),
        ]);
    }

    /** Tab yang boleh dibuka orang ini (untuk bilah tab). */
    private function tabs(): array
    {
        return [
            'tabTimesheets' => $this->canView(self::SLUG),
            'tabInvoices'   => $this->canView(self::SLUG_INVOICES),
            'tabSettings'   => $this->canView('general.external.timesheet.settings'),
        ];
    }

    /** @return array<int,array<int,array<string,mixed>>> konsultan → bulan yang boleh dibuatkan lembar (belum punya lembar) */
    private function periodsByConsultant(): array
    {
        $out = [];
        foreach ($this->consultants->options() as $c) {
            $open = array_values(array_filter($this->sheets->periodsFor($c['id']), fn ($p) => $p['sheet_id'] === null));
            if ($open) {
                $out[$c['id']] = array_map(fn ($p) => ['contract_id' => $p['contract_id'], 'year' => $p['year'], 'month' => $p['month'],
                    'label' => $p['label'] . ' — ' . $p['contract_number']], $open);
            }
        }

        return $out;
    }

    // ── Satu lembar ───────────────────────────────────────────────────────

    public function show(int $id)
    {
        $sheet = $this->sheets->find($id);
        abort_if($sheet === null, 404);

        $amounts = $this->canView('general.external.timesheet.amount');
        $actor = $this->actorId();
        $editRole = $this->employeeCan(self::SLUG, 'edit');

        return view('hr-general.external.timesheet.show', $this->tabs() + [
            'sheet'       => $sheet,
            'amounts'     => $amounts,
            'canEdit'     => $sheet->editable && $editRole,
            'canApprove'  => $sheet->status === R::SUBMITTED && $this->workflow->canAct($id, R::STAGE_APPROVAL, (int) $sheet->employee_id, $actor, $editRole),
            'canVerify'   => $sheet->invoice !== null && $this->workflow->canAct($id, R::STAGE_INVOICE, (int) $sheet->employee_id, $actor, $this->employeeCan(self::SLUG_INVOICES, 'edit')),
            'canUploadFor' => $sheet->status === R::APPROVED && $editRole && ($sheet->invoice === null || $sheet->invoice->status === R::INV_REJECTED),
            'approvers'   => $this->approverNames($id, (int) $sheet->employee_id),
            'projects'    => $this->projects(),
            'verification' => R::verificationCode($id, $sheet->sheet_no, (string) config('app.key')),
            'saveUrl'     => route('general.external.timesheet.save', $id),
            'submitUrl'   => route('general.external.timesheet.submit', $id),
            'self'        => false,
        ]);
    }

    /** Buat lembar atas nama konsultan (kotak Create). */
    public function open(Request $request)
    {
        $d = $request->validate(['employee_id' => 'required|integer', 'contract_id' => 'required|integer', 'year' => 'required|integer|min:2020|max:2100', 'month' => 'required|integer|min:1|max:12']);
        abort_if($this->consultants->find((int) $d['employee_id']) === null, 404);

        try {
            $id = $this->sheets->open((int) $d['employee_id'], (int) $d['contract_id'], (int) $d['year'], (int) $d['month'], $this->actorId());
        } catch (\DomainException $e) {
            return back()->withErrors(['sheet' => $e->getMessage()]);
        }

        return redirect()->route('general.external.timesheet.show', $id);
    }

    public function save(Request $request, int $id)
    {
        try {
            $this->sheets->save($id, (array) $request->input('entries', []), $this->actorId(), $this->milestone($request));
        } catch (\DomainException $e) {
            return back()->withInput()->withErrors(['sheet' => $e->getMessage()]);
        }

        return redirect()->route('general.external.timesheet.show', $id)->with('success', 'Timesheet saved.');
    }

    public function submit(Request $request, int $id)
    {
        try {
            if ($request->has('entries')) {
                $this->sheets->save($id, (array) $request->input('entries', []), $this->actorId(), $this->milestone($request));
            }
            $this->sheets->submit($id, $this->actorId());
        } catch (\DomainException $e) {
            return back()->withInput()->withErrors(['sheet' => $e->getMessage()]);
        }

        return redirect()->route('general.external.timesheet.show', $id)->with('success', 'Timesheet submitted for approval.');
    }

    public function destroy(int $id)
    {
        try {
            $this->sheets->delete($id);
        } catch (\DomainException $e) {
            return back()->withErrors(['sheet' => $e->getMessage()]);
        }

        return redirect()->route('general.external.timesheet.index')->with('success', 'Draft deleted.');
    }

    public function approve(int $id)
    {
        $sheet = $this->sheets->find($id);
        abort_if($sheet === null, 404);
        $this->authorizeStage($sheet, R::STAGE_APPROVAL, self::SLUG);

        try {
            $this->sheets->approve($id, $this->actorId());
        } catch (\DomainException $e) {
            return back()->withErrors(['sheet' => $e->getMessage()]);
        }

        return redirect()->route('general.external.timesheet.show', $id)->with('success', "Timesheet {$sheet->sheet_no} approved. The consultant can now upload the invoice.");
    }

    public function returnSheet(Request $request, int $id)
    {
        $sheet = $this->sheets->find($id);
        abort_if($sheet === null, 404);
        $this->authorizeStage($sheet, R::STAGE_APPROVAL, self::SLUG);

        try {
            $this->sheets->returnSheet($id, $this->actorId(), (string) $request->input('reason'));
        } catch (\DomainException $e) {
            return back()->withErrors(['sheet' => $e->getMessage()]);
        }

        return redirect()->route('general.external.timesheet.show', $id)->with('success', "Timesheet {$sheet->sheet_no} returned to the consultant.");
    }

    // ── Cetak ─────────────────────────────────────────────────────────────

    public function print(int $id)
    {
        $sheet = $this->sheets->find($id);
        abort_if($sheet === null, 404);

        return view('hr-general.external.timesheet.print', [
            'sheet' => $sheet, 'amounts' => $this->canView('general.external.timesheet.amount'),
            'verification' => R::verificationCode($id, $sheet->sheet_no, (string) config('app.key')),
            'approver' => $sheet->approved_by ? ($sheet->actor_names[$sheet->approved_by] ?? '') : '',
            'company' => DB::table('letter_settings')->value('company_name') ?: config('app.name'),
        ]);
    }

    // ── Invoice ───────────────────────────────────────────────────────────

    /** Unggah invoice atas nama konsultan (kotak Edit pada Timesheets). */
    public function uploadInvoice(Request $request, int $id)
    {
        $d = $request->validate(['invoice_no' => 'required|string|max:100', 'invoice_date' => 'required|date', 'amount' => 'required|string|max:30',
            'invoice_file' => 'required|file|max:10240'], ['invoice_file.max' => 'The file is larger than 10 MB.']);

        try {
            $this->invoices->upload($id, $d, $request->file('invoice_file'), $this->actorId());
        } catch (\DomainException $e) {
            return back()->withInput()->withErrors(['sheet' => $e->getMessage()]);
        }

        return redirect()->route('general.external.timesheet.show', $id)->with('success', 'Invoice uploaded and sent for verification.');
    }

    public function verifyInvoice(int $id)
    {
        return $this->invoiceStep($id, fn () => $this->invoices->verify($id, $this->actorId()), 'Invoice verified.');
    }

    public function rejectInvoice(Request $request, int $id)
    {
        return $this->invoiceStep($id, fn () => $this->invoices->reject($id, $this->actorId(), (string) $request->input('reason')), 'Invoice rejected; the consultant was told why.');
    }

    public function payInvoice(Request $request, int $id)
    {
        $d = $request->validate(['paid_date' => 'required|date', 'payment_ref' => 'nullable|string|max:150']);

        return $this->invoiceStep($id, fn () => $this->invoices->markPaid($id, $this->actorId(), $d['paid_date'], (string) ($d['payment_ref'] ?? '')), 'Marked as paid.');
    }

    private function invoiceStep(int $id, \Closure $act, string $message)
    {
        $sheet = $this->sheets->find($id);
        abort_if($sheet === null, 404);
        $this->authorizeStage($sheet, R::STAGE_INVOICE, self::SLUG_INVOICES);

        try {
            $act();
        } catch (\DomainException $e) {
            return back()->withErrors(['sheet' => $e->getMessage()]);
        }

        return redirect()->route('general.external.timesheet.show', $id)->with('success', $message);
    }

    /** Berkas invoice (PDF). Boleh dibuka siapa pun yang memegang salah satu tab; jumlah invoice tetap butuh izin nominal di layar. */
    public function invoiceFile(Request $request, int $id)
    {
        // Berkas invoice memuat jumlahnya: hanya pemegang izin nominal.
        abort_unless($this->canView('general.external.timesheet.amount'), 403, 'The invoice shows its amount, which needs the “View Amounts” permission.');
        $file = $this->invoices->file($id);
        abort_if($file === null, 404);

        return Storage::disk(ExternalInvoiceService::DISK)->response($file['path'], $file['name'], ['Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff'],
            $request->boolean('download') ? 'attachment' : 'inline');
    }

    // ── helpers ───────────────────────────────────────────────────────────

    /** Persen tagihan fixed price dari formulir (kosong = tidak diubah). */
    private function milestone(Request $request): ?float
    {
        $v = trim((string) $request->input('milestone_pct', ''));

        return $v === '' ? null : (float) str_replace(',', '.', $v);
    }

    /** Menolak (403) bila bukan penyetuju tahap itu — pemegang kotak Edit hanya bila tahap tak punya penyetuju. */
    private function authorizeStage(object $sheet, string $stage, string $slug): void
    {
        abort_unless($this->workflow->canAct((int) $sheet->id, $stage, (int) $sheet->employee_id, $this->actorId(), $this->employeeCan($slug, 'edit')), 403,
            'You are not an approver for this step. Approvers are set in Timesheet Settings.');
    }

    /** @return array{timesheet_approval:string[],invoice_verification:string[]} */
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

    /** @return array<int,array{id:int,label:string}> proyek yang masih berjalan (hanya dibaca) */
    private function projects(): array
    {
        return DB::table('delivery_projects')->where('is_closed', 0)->orderBy('name')->limit(300)
            ->get(['id', 'name', 'io_number'])->map(fn ($p) => ['id' => (int) $p->id, 'label' => trim(($p->io_number ? $p->io_number . ' - ' : '') . ($p->name ?? ''))])->all();
    }
}

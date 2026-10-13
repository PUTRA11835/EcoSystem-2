<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Http\Controllers\HR_General\Concerns\ChecksExternalAccess;
use App\Http\Controllers\HR_General\Concerns\HandlesRecruitmentTables;
use App\Models\EmployeeContract;
use App\Services\Contracts\ContractService;
use App\Services\External\ExternalConsultantService;
use App\Services\External\ExternalContractService;
use App\Services\HrProfile\EngagementRules;
use App\Services\Letters\LetterService;
use App\Support\Contracts\ContractRules;
use App\Support\External\ExternalRules;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * External → External Contract (slug general.external.contracts): kontrak konsultan eksternal.
 *
 * V daftar, halaman kelola, dokumen · C buat kontrak · E ubah / ganti status · D hapus kontrak Draft.
 * Kontrak = baris `employee_contract` jenis EXTERNAL (ContractService); ketentuan komersial di `external_contract_terms`.
 * Tarif hanya terlihat dan bisa diubah pemegang `general.contracts.salary`. Teks kontrak tetap dari menu Contract → Templates.
 */
class ExternalContractController extends Controller
{
    use ChecksExternalAccess;
    use HandlesRecruitmentTables;

    public const SLUG = 'general.external.contracts';

    public function __construct(
        private ExternalContractService $external,
        private ExternalConsultantService $consultants,
        private ContractService $contracts,
    ) {}

    public function index(Request $request)
    {
        $filters = [
            'search' => trim((string) $request->query('search')),
            'scheme' => (string) $request->query('scheme'),
            'status' => (string) $request->query('status'),
        ];
        $perPage = $this->perPage($request);
        $canRate = $this->canView('general.contracts.salary');

        $data = [
            'rows'           => $this->external->listing($filters, $perPage, $canRate),
            'filters'        => $filters,
            'perPage'        => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'canRate'        => $canRate,
            'filterForm'     => 'extContractFilters',
            'schemes'        => EngagementRules::SCHEMES,
            'statuses'       => ContractRules::STATUS_LABELS,
        ];

        if ($request->ajax()) {
            return view('hr-general.external.contracts.components.list-table', $data);
        }

        return view('hr-general.external.contracts.index', $data + [
            'summary'     => $this->external->summary(),
            'consultants' => $this->consultants->options(),
            'canTemplates' => $this->canView('general.contracts.templates'),
        ]);
    }

    /** Halaman kelola satu konsultan: ringkasan, riwayat kontrak, dan formulir kontrak baru / ubah. */
    public function manage(Request $request, int $employeeId)
    {
        $canRate = $this->canView('general.contracts.salary');
        $consultant = $this->consultants->find($employeeId, $this->canView('employee.section.engagement_rate.view'));
        abort_if($consultant === null, 404);

        $history = $this->external->history($employeeId, $canRate);
        $edit = $request->filled('edit') ? collect($history)->firstWhere('contract_id', (int) $request->query('edit')) : null;
        $readiness = $this->contracts->readiness($employeeId);
        $gate = ContractService::gate();

        // Nilai awal formulir baru: dari engagement konsultan (skema, tarif bila berizin, periode).
        $engagement = \Illuminate\Support\Facades\DB::table('employee_engagement')->where('employee_id', $employeeId)->first();

        return view('hr-general.external.contracts.manage', [
            'c'           => $consultant,
            'history'     => $history,
            'edit'        => $edit,
            'engagement'  => $engagement,
            'readiness'   => $readiness,
            'gate'        => $gate,
            'activeLocked' => $gate !== 'off' && !$readiness['ready'],
            'canRate'     => $canRate,
            'templates'   => $this->external->templates(),
            'signatories' => LetterService::signatoryOptions(),
            'schemes'     => EngagementRules::SCHEMES,
            'currencies'  => EngagementRules::CURRENCIES,
            'proration'   => ExternalRules::PRORATION,
            'settable'    => ContractRules::SETTABLE_STATUSES,
            'statuses'    => ContractRules::STATUS_LABELS,
            'canTemplates' => $this->canView('general.contracts.templates'),
            'canProfile'  => $this->canView('master.employee'),
        ]);
    }

    public function store(Request $request, int $employeeId)
    {
        abort_if($this->consultants->find($employeeId) === null, 404);
        $data = $request->validate($this->rules(false));

        try {
            $contract = $this->external->create($employeeId, $data, $this->actorId(), $this->canView('general.contracts.salary'));
        } catch (\DomainException $e) {
            return back()->withInput()->withErrors(['contract' => $e->getMessage()]);
        }

        return redirect()->route('general.external.contracts.manage', $employeeId)
            ->with('success', "Contract {$contract->contract_number} created" . ($contract->lifecycle_status === ContractRules::STATUS_DRAFT ? ' as a draft.' : '.'));
    }

    public function update(Request $request, int $contractId)
    {
        $contract = $this->externalContract($contractId);
        $data = $request->validate($this->rules(true));

        try {
            $this->external->update($contract, $data, $this->canView('general.contracts.salary'));
        } catch (\DomainException $e) {
            return back()->withInput()->withErrors(['contract' => $e->getMessage()]);
        }

        return redirect()->route('general.external.contracts.manage', $contract->employee_id)->with('success', "Contract {$contract->contract_number} saved.");
    }

    public function destroy(int $contractId)
    {
        $contract = $this->externalContract($contractId);

        try {
            $this->contracts->delete($contract);
        } catch (\DomainException $e) {
            return back()->withErrors(['contract' => $e->getMessage()]);
        }

        return redirect()->route('general.external.contracts.manage', $contract->employee_id)->with('success', "Draft {$contract->contract_number} deleted.");
    }

    /** Dokumen di layar — tampilan yang sama dengan menu Contract, tanpa unggah salinan bermeterai (itu di menu Contract). */
    public function document(int $contractId)
    {
        $contract = $this->externalContract($contractId);
        $mask = !$this->canView('general.contracts.salary');
        $status = ContractRules::effectiveStatus($contract->lifecycle_status, (bool) $contract->is_active, $contract->end_date?->toDateString(), now()->toDateString());

        return view('hr-general.contracts.document', [
            'external'    => true,
            'statusLabel' => ContractRules::STATUS_LABELS[$status] ?? $status,
            'statusTone'  => match ($status) {
                ContractRules::STATUS_ACTIVE => 'bg-green-100 text-green-700',
                ContractRules::STATUS_EXPIRED, ContractRules::STATUS_TERMINATED => 'bg-red-100 text-red-700',
                default => 'bg-gray-200 text-gray-600',
            },
            'contract'    => $contract,
            'pdfUrl'      => route('general.external.contracts.pdf', $contractId),
            'signedUrl'   => route('general.external.contracts.pdf', $contractId),
            'signedBy'    => null,
            'doc'         => $this->contracts->document($contract, $mask),
            'masked'      => $mask,
            'back'        => route('general.external.contracts.manage', $contract->employee_id),
        ]);
    }

    /** PDF dengan kop surat. Tanpa izin tarif ditolak: tarif tersamar tak boleh tercetak sebagai kontrak. */
    public function pdf(int $contractId)
    {
        abort_unless($this->canView('general.contracts.salary'), 403, 'Only holders of "View Salary" can print a contract.');

        $contract = $this->externalContract($contractId);
        $doc = $this->contracts->document($contract, false);

        $pdf = Pdf::loadView('hr-general.contracts.pdf', ['doc' => $doc])->setPaper(\App\Support\Contracts\PageSettings::paper($doc));
        $file = str_replace(['/', '\\'], '-', $contract->contract_number) . '.pdf';

        return request()->boolean('download') ? $pdf->download($file) : $pdf->stream($file);
    }

    // ── helpers ───────────────────────────────────────────────────────────

    /** Kontrak yang dibuka lewat menu ini harus berjenis EXTERNAL — kontrak PKWT/PKWTT bukan urusan halaman ini. */
    private function externalContract(int $contractId): EmployeeContract
    {
        $contract = EmployeeContract::findOrFail($contractId);
        abort_unless(ContractRules::normalizeType($contract->contract_type) === ContractRules::TYPE_EXTERNAL, 404);

        return $contract;
    }

    private function rules(bool $updating): array
    {
        return [
            'engagement_scheme'     => ['required', Rule::in(array_keys(EngagementRules::SCHEMES))],
            'status'                => ['required', Rule::in(ContractRules::SETTABLE_STATUSES)],
            'contract_number'       => $updating ? 'nullable|string|max:100' : 'prohibited',
            'template_id'           => 'nullable|integer',
            'start_date'            => 'required|date',
            'end_date'              => 'nullable|date|after_or_equal:start_date',
            'signed_date'           => 'nullable|date',
            'rate'                  => 'nullable|string|max:30',
            'currency'              => 'nullable|string|max:3',
            'billing_unit'          => 'nullable|string|max:30',
            'work_volume'           => 'nullable|string|max:100',
            'work_location'         => 'nullable|string|max:255',
            'hours_per_manday'      => 'nullable|numeric|min:1|max:24',
            'proration'             => ['nullable', Rule::in(array_keys(ExternalRules::PRORATION))],
            'payment_term_days'     => 'nullable|integer|min:0|max:365',
            'notes'                 => 'nullable|string|max:2000',
            'signatory_employee_id' => 'nullable|integer',
        ];
    }
}

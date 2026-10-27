<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Http\Controllers\HR_General\Concerns\ChecksExternalAccess;
use App\Http\Controllers\HR_General\Concerns\HandlesRecruitmentTables;
use App\Models\ContractOption;
use App\Models\EmployeeContract;
use App\Services\Contracts\ContractOptionService;
use App\Services\Contracts\ContractService;
use App\Services\External\ExternalConsultantService;
use App\Services\External\ExternalContractService;
use App\Services\HrProfile\EngagementRules;
use App\Services\Letters\LetterService;
use App\Support\External\ExternalSigners;
use App\Support\Contracts\ContractRules;
use App\Support\Contracts\PageSettings;
use App\Support\External\ExternalRules;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * External → External Contract (slug general.external.contracts): kontrak konsultan eksternal.
 *
 * V daftar, halaman kelola, dokumen · C buat kontrak · E ubah / ganti status · D hapus kontrak Draft.
 * Kontrak = baris `employee_contract` jenis EXTERNAL (ContractService); ketentuan komersial di `external_contract_terms`.
 * Tarif hanya terlihat dan bisa diubah pemegang `general.external.contracts.rate` (izin khusus konsultan, 27 Okt 2026) ATAU `general.contracts.salary`
 * (pemegang lama tidak kehilangan akses). Teks kontrak tetap dari menu Contract → Templates.
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
        private ContractOptionService $options,
    ) {}

    public function index(Request $request)
    {
        $filters = [
            'search' => trim((string) $request->query('search')),
            'scheme' => (string) $request->query('scheme'),
            'status' => (string) $request->query('status'),
        ];
        $perPage = $this->perPage($request);
        $canRate = $this->canRate();

        // A custom contract type is filed under its own code; a built-in scheme filters by scheme as before.
        $query = $filters;
        if ($filters['scheme'] !== '' && $this->options->resolve(ContractOption::SCOPE_EXTERNAL, $filters['scheme'])['kind']) {
            $query['kind'] = $filters['scheme'];
            $query['scheme'] = '';
        }

        $data = [
            'kindLabels'     => $this->options->labels(ContractOption::SCOPE_EXTERNAL),
            'rows'           => $this->external->listing($query, $perPage, $canRate),
            'filters'        => $filters,
            'perPage'        => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'canRate'        => $canRate,
            'filterForm'     => 'extContractFilters',
            'schemes'        => $this->options->options(ContractOption::SCOPE_EXTERNAL, ContractOption::KIND_CONTRACT_TYPE),
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
        $canRate = $this->canRate();
        $consultant = $this->consultants->find($employeeId, $canRate || $this->canView('external.section.engagement_rate.view'));
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
            // Kontrak External ditandatangani Pak Arbin atau Pak Titus (config/hc_external.php); bawaan Arbin
            'signatories' => ExternalSigners::for(ExternalSigners::CONTRACT)['options'],
            'defaultSigner' => ExternalSigners::for(ExternalSigners::CONTRACT)['default'],
            'signerNote'  => ExternalSigners::for(ExternalSigners::CONTRACT)['restricted'] ? 'Signed by ' . implode(' or ', ExternalSigners::for(ExternalSigners::CONTRACT)['names']) . '.' : null,
            'assignmentSupported' => \App\Support\SchemaCache::hasColumn('external_contract_terms', 'assignment'),
            'schemes'     => $this->schemeOptions($edit?->contract_kind ?: $edit?->scheme_key),
            'schemeBase'  => collect($this->schemeOptions($edit?->contract_kind ?: $edit?->scheme_key))->keys()
                ->mapWithKeys(fn ($code) => [$code => $this->options->resolve(ContractOption::SCOPE_EXTERNAL, $code)['base']])->all(),
            'defaultScheme' => $this->options->defaultCode(ContractOption::SCOPE_EXTERNAL, ContractOption::KIND_CONTRACT_TYPE),
            'defaultPositionType' => $this->options->defaultPositionType(ContractOption::SCOPE_EXTERNAL),
            'placements'  => $this->options->placements(ContractOption::SCOPE_EXTERNAL, $edit?->work_location),
            'defaultPlacement' => $this->options->defaultPlacement(ContractOption::SCOPE_EXTERNAL),
            'kindLabels'  => $this->options->labels(ContractOption::SCOPE_EXTERNAL),
            'positionTypes' => $this->options->positionTypes(ContractOption::SCOPE_EXTERNAL, $edit?->position_type),
            'currencies'  => EngagementRules::CURRENCIES,
            'proration'   => ExternalRules::PRORATION,
            'settable'    => ContractRules::SETTABLE_STATUSES,
            'statuses'    => ContractRules::STATUS_LABELS,
            'canTemplates' => $this->canView('general.contracts.templates'),
            'canProfile'  => $this->canView('general.external.consultants'),
        ]);
    }

    public function store(Request $request, int $employeeId)
    {
        abort_if($this->consultants->find($employeeId) === null, 404);
        $data = $this->withKind($request->validate($this->rules(false)));

        try {
            $contract = $this->external->create($employeeId, $data, $this->actorId(), $this->canRate());
        } catch (\DomainException $e) {
            return $this->refuse($e);
        }

        return redirect()->route('general.external.contracts.manage', $employeeId)
            ->with('success', "Contract {$contract->contract_number} created" . ($contract->lifecycle_status === ContractRules::STATUS_DRAFT ? ' as a draft.' : '.'));
    }

    public function update(Request $request, int $contractId)
    {
        $contract = $this->externalContract($contractId);
        $data = $this->withKind($request->validate($this->rules(true, $contract)));

        try {
            $this->external->update($contract, $data, $this->canRate());
        } catch (\DomainException $e) {
            return $this->refuse($e);
        }

        return redirect()->route('general.external.contracts.manage', $contract->employee_id)->with('success', "Contract {$contract->contract_number} saved.");
    }

    /** The contract text generated from the form for Legal / HR / RPMO to adjust — nothing is saved. */
    public function preview(Request $request, int $employeeId)
    {
        abort_unless($this->canDo('create') || $this->canDo('edit'), 403);
        abort_if($this->consultants->find($employeeId) === null, 404);

        // The edit form carries the contract number too (a new one does not); the preview ignores it.
        $data = $this->withKind($request->validate(array_merge($this->rules(false), ['contract_number' => 'nullable|string|max:100'])));
        unset($data['body_html'], $data['body_customized']);

        try {
            $doc = $this->external->preview($employeeId, $data, $this->canRate());
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'issues' => $e instanceof \App\Exceptions\ContractDataException ? $e->issues : []], 422);
        }

        return response()->json([
            'success'        => true,
            'html'           => $doc['html'],
            'template'       => ['id' => $doc['template']->id, 'name' => $doc['template']->name, 'position_type' => $doc['template']->position_type],
            'use_letterhead' => $doc['use_letterhead'],
            'page'           => ['size' => $doc['page']['size'], 'orientation' => $doc['page']['orientation'], 'margins' => $doc['page']['margins']],
            'warnings'       => $doc['warnings'],
            'gaps'           => $doc['gaps'] ?? [],
        ]);
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
        $mask = !$this->canRate();
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
        abort_unless($this->canRate(), 403, 'Printing an external contract shows its rate, which needs the permission "External Contract rate" (or "View Salary"). Ask an administrator to add it in Control Center → Menu Access.');

        $contract = $this->externalContract($contractId);
        $doc = $this->contracts->document($contract, false);

        $pdf = Pdf::loadView('hr-general.contracts.pdf', ['doc' => $doc])->setPaper(\App\Support\Contracts\PageSettings::paper($doc));
        $file = str_replace(['/', '\\'], '-', $contract->contract_number) . '.pdf';

        return request()->boolean('download') ? $pdf->download($file) : $pdf->stream($file);
    }

    // ── helpers ───────────────────────────────────────────────────────────

    /** Penolakan dari service: pesan lengkap + (bila karena data belum lengkap) daftar butir yang bisa diklik di layar. */
    private function refuse(\DomainException $e)
    {
        return back()->withInput()->withErrors(['contract' => $e->getMessage()])
            ->with('issues', $e instanceof \App\Exceptions\ContractDataException ? $e->issues : []);
    }

    /** Tarif kontrak External: izin khusus konsultan, atau "View Salary" (pemegang lama tetap bisa). */
    private function canRate(): bool
    {
        return $this->canView('general.external.contracts.rate') || $this->canView('general.contracts.salary');
    }

    /** Kontrak yang dibuka lewat menu ini harus berjenis EXTERNAL — kontrak PKWT/PKWTT bukan urusan halaman ini. */
    private function externalContract(int $contractId): EmployeeContract
    {
        $contract = EmployeeContract::findOrFail($contractId);
        abort_unless(ContractRules::normalizeType($contract->contract_type) === ContractRules::TYPE_EXTERNAL, 404);

        return $contract;
    }

    /** Contract types (schemes) HR switched on for external contracts, plus the one the contract being edited holds. */
    private function schemeOptions(?string $keep = null): array
    {
        return $this->options->options(ContractOption::SCOPE_EXTERNAL, ContractOption::KIND_CONTRACT_TYPE, $keep);
    }

    /** The form posts the option's code; the terms run on the built-in scheme it stands for, the custom code is kept beside it. */
    private function withKind(array $data): array
    {
        $resolved = $this->options->resolve(ContractOption::SCOPE_EXTERNAL, (string) $data['engagement_scheme']);
        $data['engagement_scheme'] = $resolved['base'];
        $data['contract_kind'] = $resolved['kind'];

        return $data;
    }

    private function canDo(string $action): bool
    {
        $matrix = \App\Models\Employee::find(session('user.id'))?->allPermissionMatrix() ?? [];

        return (bool) ($matrix[self::SLUG][$action] ?? false);
    }

    private function rules(bool $updating, ?EmployeeContract $existing = null): array
    {
        $keep = null;
        if ($existing) {
            $keep = $existing->contract_kind ?: ExternalRules::schemeKey(\Illuminate\Support\Facades\DB::table('external_contract_terms')->where('contract_id', $existing->contract_id)->value('engagement_scheme'));
        }
        $schemes = array_keys($this->schemeOptions($keep));
        $positionTypes = $this->options->positionTypes(ContractOption::SCOPE_EXTERNAL, $existing?->position_type);
        $margin = 'nullable|numeric|min:' . PageSettings::MARGIN_MIN_CM . '|max:' . PageSettings::MARGIN_MAX_CM;

        return [
            'engagement_scheme'     => ['required', Rule::in($schemes)],
            'position'              => 'nullable|string|max:255',
            'position_type'         => ['nullable', 'string', 'max:100', Rule::in($positionTypes)],
            'body_html'             => 'nullable|string|max:300000',
            'body_customized'       => 'nullable|boolean',
            'reset_body'            => 'nullable|boolean',
            'page_size'             => ['nullable', Rule::in(array_keys(PageSettings::SIZES))],
            'page_orientation'      => ['nullable', Rule::in(['portrait', 'landscape'])],
            'margin_top'            => $margin, 'margin_right' => $margin, 'margin_bottom' => $margin, 'margin_left' => $margin,
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
            'assignment'            => 'nullable|string|max:255',
            'work_location'         => 'nullable|string|max:255',
            'hours_per_manday'      => 'nullable|numeric|min:1|max:24',
            'proration'             => ['nullable', Rule::in(array_keys(ExternalRules::PRORATION))],
            'payment_term_days'     => 'nullable|integer|min:0|max:365',
            'notes'                 => 'nullable|string|max:2000',
            'signatory_employee_id' => 'nullable|integer',
        ];
    }
}

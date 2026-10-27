<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Http\Controllers\HR_General\Concerns\HandlesRecruitmentTables;
use App\Models\ContractOption;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\Position;
use App\Services\Contracts\ContractOptionService;
use App\Services\Contracts\ContractService;
use App\Services\Contracts\ContractSigningService;
use App\Services\Letters\LetterService;
use App\Support\Contracts\ContractRules;
use App\Support\Contracts\PageSettings;
use App\Support\Contracts\SigningRules;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * HR & General → Contract → Contracts (slug general.contracts.list): satu baris per karyawan dengan kontrak terbarunya,
 * halaman kelola per karyawan (kesiapan data, gaji, riwayat), serta dokumen kontrak untuk dilihat, dicetak, dan diunduh PDF.
 *
 * V lihat daftar & dokumen · C buat kontrak · E ubah / ganti status · D hapus kontrak Draft.
 * Nilai gaji hanya terlihat bagi pemegang `general.contracts.salary`.
 */
class ContractController extends Controller
{
    use HandlesRecruitmentTables;

    public function __construct(private ContractService $contracts, private ContractOptionService $options, private ContractSigningService $signing) {}

    /** Tab pertama yang boleh dibuka orang ini (tautan sidebar). */
    public function home()
    {
        return redirect()->route($this->employeeCanView('general.contracts.list') ? 'general.contracts.dashboard' : 'general.contracts.templates.index');
    }

    /** Dashboard: siapa yang kontraknya segera berakhir, plus angka pendukungnya. */
    public function dashboard(Request $request)
    {
        return view('hr-general.contracts.dashboard', [
            'dash' => $this->contracts->dashboard((int) $request->query('window', 30)),
        ]);
    }

    /** Daftar kontrak; tiap kolom punya filter di kepala tabelnya (data-live-region diganti di tempat). */
    public function index(Request $request)
    {
        $date = fn (string $key) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query($key)) ? (string) $request->query($key) : '';
        $text = fn (string $key) => trim((string) $request->query($key));

        $filters = [
            'search'     => mb_substr($text('search'), 0, 100),   // kotak pencarian terlihat: nama, ECI, nomor kontrak, jabatan, departemen, KTP
            'employee'   => $text('employee'),
            'number'     => $text('number'),
            'type'       => $text('type'),
            'department' => $text('department'),
            'status'     => $text('status'),
            'end_from'   => $date('end_from'),
            'end_to'     => $date('end_to'),
        ];
        $perPage = $this->perPage($request);

        // A custom contract type (Settings) is filed under its own code; a built-in one filters by type as before.
        $query = $filters;
        if ($filters['type'] !== '' && $this->options->resolve(ContractOption::SCOPE_INTERNAL, $filters['type'])['kind']) {
            $query['kind'] = $filters['type'];
            $query['type'] = '';
        }

        return view('hr-general.contracts.index', [
            'rows'           => $this->contracts->listing($query, $perPage),
            'kindLabels'     => $this->options->labels(ContractOption::SCOPE_INTERNAL),
            'filters'        => $filters,
            'perPage'        => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            // The types HR switched on in Settings, plus the legacy external one (an internal person can still hold it).
            'types'          => $this->options->options(ContractOption::SCOPE_INTERNAL, ContractOption::KIND_CONTRACT_TYPE) + [ContractRules::TYPE_EXTERNAL => ContractRules::TYPES[ContractRules::TYPE_EXTERNAL]],
            'canExternal'    => $this->employeeCanView('general.external.contracts'),
            'statuses'       => ContractRules::STATUS_LABELS + ['none' => 'No contract'],
            'departments'    => $this->contracts->departments(),
            'filterForm'     => 'contractFilters',
        ]);
    }

    public function show(int $employeeId)
    {
        // Consultants have their own menu; this one is for internal employees.
        $employeeType = DB::table('employee_basic_data')->where('employee_id', $employeeId)->value('employee_type');
        if ($employeeType === 'External') {
            abort_unless($this->employeeCanView('general.external.contracts'), 404);

            return redirect()->route('general.external.contracts.manage', $employeeId);
        }

        $header = $this->contracts->employeeHeader($employeeId);
        abort_if($header === null, 404);

        $canSalary = $this->employeeCanView('general.contracts.salary');
        $history = $this->contracts->history($employeeId);

        return view('hr-general.contracts.show', [
            'employee'      => $header,
            'readiness'     => $this->contracts->readiness($employeeId),
            'history'       => $history,
            'canSalary'     => $canSalary,
            // Contract types and position types come from Settings (switched-on ones, plus those an existing contract already holds).
            'types'         => $this->typeOptions($history),
            'positionTypes' => $this->positionTypeOptions($history),
            'statuses'      => ContractRules::STATUS_LABELS,
            'editId'        => (int) request()->query('edit_contract_id'),
            'settable'      => ContractRules::SETTABLE_STATUSES,
            'templates'     => collect(array_keys(ContractRules::TYPES))->mapWithKeys(fn ($t) => [$t => $this->contracts->templatesFor($t)->values()]),
            'positions'     => Position::options(),
            'departments'   => Department::options(),
            'signatories'   => LetterService::signatoryOptions(),
            'defaultType'   => $this->options->defaultCode(ContractOption::SCOPE_INTERNAL, ContractOption::KIND_CONTRACT_TYPE) ?: ContractRules::TYPE_PKWT,
            'defaultPositionType' => $this->options->defaultPositionType(ContractOption::SCOPE_INTERNAL),
            'kindLabels'    => $this->options->labels(ContractOption::SCOPE_INTERNAL),
            // option code → the built-in type its rules follow (a custom type behaves like its base)
            'typeBase'      => collect($this->typeOptions($history))->keys()->mapWithKeys(fn ($code) => [$code => $this->options->resolve(ContractOption::SCOPE_INTERNAL, $code)['base']])->all(),
            'gate'          => ContractService::gate(),
            'employeeDefaults' => $this->contracts->employeeDefaults($employeeId),
            'pkwtMaxMonths' => (int) config('hc_contract.pkwt_max_months', 60),
            'salaryDefaults' => $canSalary ? $this->salaryDefaults($employeeId) : null,
        ]);
    }

    /**
     * Gaji yang sedang berlaku di Salary Components (dari Offering Letter / kontrak sebelumnya) untuk mengisi awal kontrak BARU,
     * supaya HR tidak mengetik ulang dan kontrak tidak menyimpang dari gaji di master. Hanya Basic Salary + tunjangan tetap.
     *
     * @return array{salary:float,components:array<int,array{name:string,amount:float}>}|null
     */
    private function salaryDefaults(int $employeeId): ?array
    {
        $today = now()->toDateString();
        $rows = \App\Models\EmployeeSalaryComponent::where('employee_id', $employeeId)->whereIn('kind', ['base', 'fixed'])->where('is_active', true)
            ->where('effective_from', '<=', $today)->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $today))->get();
        $base = $rows->where('kind', 'base')->sortByDesc('effective_from')->first();
        if (!$base) {
            return null;
        }

        return ['salary' => (float) $base->amount, 'components' => $rows->where('kind', 'fixed')->map(fn ($r) => ['name' => $r->name, 'amount' => (float) $r->amount])->values()->all()];
    }

    public function store(Request $request, int $employeeId)
    {
        abort_if($this->contracts->employeeHeader($employeeId) === null, 404);
        $data = $this->withKind($request->validate($this->rules()));

        try {
            $contract = $this->contracts->create($employeeId, $data, (int) session('user.id'), $this->employeeCanView('general.contracts.salary'));
        } catch (\DomainException $e) {
            return $this->failed($e, 'create');
        }

        return redirect()->route('general.contracts.show', $employeeId)
            ->with('success', "Contract {$contract->contract_number} created" . ($contract->lifecycle_status === ContractRules::STATUS_DRAFT ? ' as a draft.' : '.') . ($this->contracts->salarySyncNote ? ' ' . $this->contracts->salarySyncNote : ''));
    }

    public function update(Request $request, int $contractId)
    {
        $contract = EmployeeContract::findOrFail($contractId);
        $data = $this->withKind($request->validate($this->rules($contract)));

        try {
            $this->contracts->update($contract, $data, $this->employeeCanView('general.contracts.salary'));
        } catch (\DomainException $e) {
            return $this->failed($e, 'edit', $contractId);
        }

        return redirect()->route('general.contracts.show', $contract->employee_id)->with('success', "Contract {$contract->contract_number} saved." . ($this->contracts->salarySyncNote ? ' ' . $this->contracts->salarySyncNote : ''));
    }

    /**
     * Generate the contract text from the form, without saving anything: what appears under the form for Legal / HR / RPMO to
     * adjust. Needs the right to create or edit a contract; salary figures follow the "View Salary" permission as everywhere.
     */
    public function preview(Request $request, int $employeeId)
    {
        abort_unless($this->employeeCan('general.contracts.list', 'create') || $this->employeeCan('general.contracts.list', 'edit'), 403);
        abort_if($this->contracts->employeeHeader($employeeId) === null, 404);

        $data = $this->withKind($request->validate($this->rules()));
        // The generated text is not stored by this call.
        unset($data['body_html'], $data['body_customized']);

        try {
            $doc = $this->contracts->previewDocument($employeeId, $data, $this->employeeCanView('general.contracts.salary'));
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
        ]);
    }

    public function destroy(int $contractId)
    {
        $contract = EmployeeContract::findOrFail($contractId);

        try {
            $this->contracts->delete($contract);
        } catch (\DomainException $e) {
            return back()->withErrors(['contract' => $e->getMessage()]);
        }

        return redirect()->route('general.contracts.show', $contract->employee_id)->with('success', "Draft {$contract->contract_number} deleted.");
    }

    /** Dokumen di layar (pratinjau kertas A4) dengan tombol Print. */
    public function document(int $contractId)
    {
        $contract = EmployeeContract::findOrFail($contractId);
        $mask = !$this->employeeCanView('general.contracts.salary');
        $status = ContractRules::effectiveStatus($contract->lifecycle_status, (bool) $contract->is_active, $contract->end_date?->toDateString(), now()->toDateString());

        return view('hr-general.contracts.document', [
            'statusLabel' => ContractRules::STATUS_LABELS[$status] ?? $status,
            'statusTone'  => match ($status) {
                ContractRules::STATUS_ACTIVE => 'bg-green-100 text-green-700',
                ContractRules::STATUS_EXPIRED, ContractRules::STATUS_TERMINATED => 'bg-red-100 text-red-700',
                default => 'bg-gray-200 text-gray-600',
            },
            'contract' => $contract,
            'pdfUrl'   => route('general.contracts.pdf', $contract->contract_id),
            'signedUrl' => route('general.contracts.signed', $contract->contract_id),
            'signedBy' => $contract->signed_file_by ? trim((string) \Illuminate\Support\Facades\DB::table('employee_basic_data')->where('employee_id', $contract->signed_file_by)->selectRaw("CONCAT_WS(' ', first_name, last_name) n")->value('n')) : null,
            'doc'      => $this->contracts->document($contract, $mask),
            'masked'   => $mask,
            'back'     => route('general.contracts.show', $contract->employee_id),
            'signing'  => $this->signingPanel($contract),
        ]);
    }

    /** What the Signing panel of the document page needs: the stage, the people, the trail. */
    private function signingPanel(EmployeeContract $contract): array
    {
        $stage = $contract->sign_stage;
        $approvers = LetterService::signatoryOptions();
        $default = $contract->approver_employee_id ?: $this->signing->templateApprover($contract);

        return [
            'stage'       => $stage,
            'label'       => SigningRules::LEGAL_LABELS[$stage] ?? 'Not sent for signing',
            'badge'       => SigningRules::BADGES[$stage] ?? 'bg-gray-100 text-gray-600',
            'can'         => $this->employeeCanView('general.contracts.signing'),
            'hasStamped'  => $this->signing->hasStampedFile($contract),
            'hasSigned'   => (bool) $contract->signed_pdf_path,
            'approvers'   => $approvers,
            'approverId'  => $default ? (int) $default : null,
            'events'      => $this->signing->events($contract),
            'allow'       => [
                'send'    => SigningRules::can($stage, SigningRules::ACTION_SEND),
                'forward' => SigningRules::can($stage, SigningRules::ACTION_FORWARD),
                'release' => SigningRules::can($stage, SigningRules::ACTION_RELEASE),
                'cancel'  => SigningRules::can($stage, SigningRules::ACTION_CANCEL),
            ],
            'daysLeft'    => $stage === SigningRules::WITH_EMPLOYEE ? SigningRules::daysLeft($contract->sign_deadline?->toDateString()) : null,
        ];
    }

    // ── Salinan bermeterai (metode 1: unduh → tanda tangan + meterai → unggah) ──

    public function uploadSigned(Request $request, int $contractId)
    {
        $contract = EmployeeContract::findOrFail($contractId);
        $request->validate(['signed_file' => ['required', 'file', 'max:' . (int) config('hc_contract.stamp.upload_max_kb', 10240)]], [
            'signed_file.max' => 'The file is larger than ' . round(config('hc_contract.stamp.upload_max_kb', 10240) / 1024) . ' MB.',
        ]);

        try {
            $this->contracts->storeSignedCopy($contract, $request->file('signed_file'), (int) session('user.id'));
        } catch (\DomainException $e) {
            return redirect()->route('general.contracts.document', $contractId)->withErrors(['contract' => $e->getMessage()]);
        }

        return redirect()->route('general.contracts.document', $contractId)->with('success', 'Stamped copy saved. It is now the signed version of ' . $contract->contract_number . '.');
    }

    public function signedCopy(int $contractId)
    {
        // The stamped copy carries the salary, like the contract itself.
        abort_unless($this->employeeCanView('general.contracts.salary'), 403, 'Only holders of "View Salary" can open a contract.');
        $contract = EmployeeContract::findOrFail($contractId);
        abort_if(!$contract->signed_file_path || !\Illuminate\Support\Facades\Storage::disk('local')->exists($contract->signed_file_path), 404);

        return \Illuminate\Support\Facades\Storage::disk('local')->response(
            $contract->signed_file_path,
            $contract->signed_file_name ?: 'contract.pdf',
            ['Content-Type' => 'application/pdf'],
            request()->boolean('download') ? 'attachment' : 'inline'
        );
    }

    public function removeSigned(int $contractId)
    {
        $contract = EmployeeContract::findOrFail($contractId);
        $this->contracts->removeSignedCopy($contract);

        return redirect()->route('general.contracts.document', $contractId)->with('success', 'Stamped copy removed.');
    }

    /** Unduh PDF (kop surat, DomPDF). Tanpa izin gaji ditolak: nilai gaji tersamar tak boleh tercetak sebagai kontrak. */
    public function pdf(int $contractId)
    {
        abort_unless($this->employeeCanView('general.contracts.salary'), 403, 'Only holders of "View Salary" can print a contract.');

        $contract = EmployeeContract::findOrFail($contractId);
        $doc = $this->contracts->document($contract, false);

        $pdf = Pdf::loadView('hr-general.contracts.pdf', ['doc' => $doc])->setPaper(\App\Support\Contracts\PageSettings::paper($doc));
        $file = str_replace(['/', '\\'], '-', $contract->contract_number) . '.pdf';

        // Inline = the sheet shown on the document page (and printed); ?download=1 = the PDF button.
        return request()->boolean('download') ? $pdf->download($file) : $pdf->stream($file);
    }

    // ── helpers ───────────────────────────────────────────────────────────

    /** Types HR switched on for internal contracts, plus the ones the employee's existing contracts already hold. */
    private function typeOptions(array $history = []): array
    {
        $types = $this->options->options(ContractOption::SCOPE_INTERNAL, ContractOption::KIND_CONTRACT_TYPE);
        $labels = $this->options->labels(ContractOption::SCOPE_INTERNAL);
        foreach ($history as $c) {
            // A contract holds its custom type when it has one, else the built-in type.
            $key = ($c->contract_kind ?? null) ?: ($c->type_key ?? null);
            if ($key && !isset($types[$key])) {
                $types[$key] = $labels[$key] ?? (ContractRules::TYPES[$key] ?? $key);
            }
        }

        return $types;
    }

    /** The form posts the option's code; the rules run on the built-in type it stands for, the custom code is kept beside it. */
    private function withKind(array $data): array
    {
        $resolved = $this->options->resolve(ContractOption::SCOPE_INTERNAL, (string) $data['contract_type']);
        $data['contract_type'] = $resolved['base'];
        $data['contract_kind'] = $resolved['kind'];

        return $data;
    }

    /** @return string[] position types switched on, plus the ones the employee's existing contracts hold */
    private function positionTypeOptions(array $history = []): array
    {
        $names = $this->options->positionTypes(ContractOption::SCOPE_INTERNAL);
        foreach ($history as $c) {
            if ($c->position_type && !in_array($c->position_type, $names, true)) {
                $names[] = $c->position_type;
            }
        }

        return $names;
    }

    private function rules(?EmployeeContract $existing = null): array
    {
        $types = $this->typeOptions($existing ? [(object) ['type_key' => $existing->contract_kind ?: ContractRules::normalizeType($existing->contract_type)]] : []);
        $positionTypes = $this->positionTypeOptions($existing ? [$existing] : []);
        $margin = 'nullable|numeric|min:' . PageSettings::MARGIN_MIN_CM . '|max:' . PageSettings::MARGIN_MAX_CM;

        return [
            'contract_type'            => ['required', Rule::in(array_keys($types))],
            'position_type'            => ['nullable', 'string', 'max:100', Rule::in($positionTypes)],
            'body_html'                => 'nullable|string|max:300000',
            'body_customized'          => 'nullable|boolean',
            'reset_body'               => 'nullable|boolean',
            'page_size'                => ['nullable', Rule::in(array_keys(PageSettings::SIZES))],
            'page_orientation'         => ['nullable', Rule::in(['portrait', 'landscape'])],
            'margin_top'               => $margin, 'margin_right' => $margin, 'margin_bottom' => $margin, 'margin_left' => $margin,
            'status'                   => ['required', Rule::in(ContractRules::SETTABLE_STATUSES)],
            'contract_number'          => 'nullable|string|max:100',
            'template_id'              => 'nullable|integer',
            // Kontrak BARU: tanggal mulai boleh kosong = mengikuti data karyawan (lihat ContractService::fillFromEmployee).
            'start_date'               => $existing ? 'required|date' : 'nullable|date',
            'duration_months'          => 'nullable|integer|min:1|max:60',
            'end_date'                 => 'nullable|date',
            'signed_date'              => 'nullable|date',
            'position'                 => 'nullable|string|max:255',
            'department'               => 'nullable|string|max:255',
            'work_location'            => 'nullable|string|max:255',
            'salary'                   => 'nullable|string|max:30',
            'components'               => 'nullable|array|max:20',
            'components.*.name'        => 'nullable|string|max:100',
            'components.*.amount'      => 'nullable|string|max:30',
            'notes'                    => 'nullable|string|max:2000',
            'work_volume'              => 'nullable|string|max:100',
            'signatory_employee_id'    => 'nullable|integer',
        ];
    }

    /** Gagal aturan bisnis: kembali ke halaman dengan isian utuh dan modal terbuka lagi. */
    private function failed(\DomainException $e, string $mode, ?int $contractId = null)
    {
        return back()->withInput()->withErrors(['contract' => $e->getMessage()])
            ->with('issues', $e instanceof \App\Exceptions\ContractDataException ? $e->issues : [])
            ->with('reopen_modal', ['mode' => $mode, 'id' => $contractId]);
    }

    /** One of the Create / Edit / Delete boxes of a slug, from the person's roles. */
    private function employeeCan(string $slug, string $action): bool
    {
        $matrix = Employee::find(session('user.id'))?->allPermissionMatrix() ?? [];

        return (bool) ($matrix[$slug][$action] ?? false);
    }

    private function employeeCanView(string $slug): bool
    {
        $employee = Employee::find(session('user.id'));

        return $employee !== null && $employee->canAccessMenu($slug);
    }
}

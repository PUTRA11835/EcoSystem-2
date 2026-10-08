<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Http\Controllers\HR_General\Concerns\HandlesRecruitmentTables;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\Position;
use App\Services\Contracts\ContractService;
use App\Services\Letters\LetterService;
use App\Support\Contracts\ContractRules;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
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

    public function __construct(private ContractService $contracts) {}

    /** Tab pertama yang boleh dibuka orang ini (tautan sidebar). */
    public function home()
    {
        return redirect()->route($this->employeeCanView('general.contracts.list') ? 'general.contracts.list' : 'general.contracts.templates.index');
    }

    public function index(Request $request)
    {
        $filters = [
            'search' => trim((string) $request->query('search')),
            'type'   => (string) $request->query('type'),
            'status' => (string) $request->query('status'),
        ];
        $perPage = $this->perPage($request);

        $data = [
            'rows'           => $this->contracts->listing($filters, $perPage),
            'filters'        => $filters,
            'perPage'        => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'types'          => ContractRules::TYPES,
            'statuses'       => ContractRules::STATUS_LABELS + ['none' => 'No contract'],
            'filterForm'     => 'contractFilters',
        ];

        // The live filters refresh only the table + pagination (components/list-table) in the background.
        if ($request->ajax()) {
            return view('hr-general.contracts.components.list-table', $data);
        }

        return view('hr-general.contracts.index', $data + ['summary' => $this->contracts->summary()]);
    }

    public function show(int $employeeId)
    {
        $header = $this->contracts->employeeHeader($employeeId);
        abort_if($header === null, 404);

        $canSalary = $this->employeeCanView('general.contracts.salary');
        $history = $this->contracts->history($employeeId);

        return view('hr-general.contracts.show', [
            'employee'      => $header,
            'readiness'     => $this->contracts->readiness($employeeId),
            'history'       => $history,
            'canSalary'     => $canSalary,
            'types'         => ContractRules::TYPES,
            'statuses'      => ContractRules::STATUS_LABELS,
            'editId'        => (int) request()->query('edit_contract_id'),
            'settable'      => ContractRules::SETTABLE_STATUSES,
            'templates'     => collect(array_keys(ContractRules::TYPES))->mapWithKeys(fn ($t) => [$t => $this->contracts->templatesFor($t)->values()]),
            'positions'     => Position::options(),
            'departments'   => Department::options(),
            'signatories'   => LetterService::signatoryOptions(),
            'defaultType'   => $header->employee_type === 'External' ? ContractRules::TYPE_EXTERNAL : ContractRules::TYPE_PKWT,
            'gate'          => ContractService::gate(),
            'pkwtMaxMonths' => (int) config('hc_contract.pkwt_max_months', 60),
        ]);
    }

    public function store(Request $request, int $employeeId)
    {
        abort_if($this->contracts->employeeHeader($employeeId) === null, 404);
        $data = $request->validate($this->rules());

        try {
            $contract = $this->contracts->create($employeeId, $data, (int) session('user.id'), $this->employeeCanView('general.contracts.salary'));
        } catch (\DomainException $e) {
            return $this->failed($e, 'create');
        }

        return redirect()->route('general.contracts.show', $employeeId)
            ->with('success', "Contract {$contract->contract_number} created" . ($contract->lifecycle_status === ContractRules::STATUS_DRAFT ? ' as a draft.' : '.'));
    }

    public function update(Request $request, int $contractId)
    {
        $contract = EmployeeContract::findOrFail($contractId);
        $data = $request->validate($this->rules());

        try {
            $this->contracts->update($contract, $data, $this->employeeCanView('general.contracts.salary'));
        } catch (\DomainException $e) {
            return $this->failed($e, 'edit', $contractId);
        }

        return redirect()->route('general.contracts.show', $contract->employee_id)->with('success', "Contract {$contract->contract_number} saved.");
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
        ]);
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

        $pdf = Pdf::loadView('hr-general.contracts.pdf', ['doc' => $doc])->setPaper('a4');
        $file = str_replace(['/', '\\'], '-', $contract->contract_number) . '.pdf';

        // Inline = the sheet shown on the document page (and printed); ?download=1 = the PDF button.
        return request()->boolean('download') ? $pdf->download($file) : $pdf->stream($file);
    }

    // ── helpers ───────────────────────────────────────────────────────────

    private function rules(): array
    {
        return [
            'contract_type'            => ['required', Rule::in(array_keys(ContractRules::TYPES))],
            'status'                   => ['required', Rule::in(ContractRules::SETTABLE_STATUSES)],
            'contract_number'          => 'nullable|string|max:100',
            'template_id'              => 'nullable|integer',
            'start_date'               => 'required|date',
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
            ->with('reopen_modal', ['mode' => $mode, 'id' => $contractId]);
    }

    private function employeeCanView(string $slug): bool
    {
        $employee = Employee::find(session('user.id'));

        return $employee !== null && $employee->canAccessMenu($slug);
    }
}

<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Http\Controllers\HR_General\Concerns\ChecksExternalAccess;
use App\Http\Controllers\HR_General\Concerns\HandlesRecruitmentTables;
use App\Models\Letters\Letter;
use App\Services\External\ExternalConsultantService;
use App\Services\External\ExternalDocumentService;
use App\Services\Letters\LetterNumberService;
use App\Support\External\ExternalRules;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * External → Assignment Letter / Employment Certificate / Reference Letter (tiga slug, satu controller; jenis dari rute).
 *
 * V daftar arsip + buka/unduh PDF · C buat surat. Surat tercatat di Letter Register sebagai surat keluar (nomor dari urutan
 * surat keluar); membatalkan (void) dan mengubah surat dilakukan di Letter Templates, oleh pemegang izinnya di sana.
 */
class ExternalDocumentController extends Controller
{
    use ChecksExternalAccess;
    use HandlesRecruitmentTables;

    public function __construct(
        private ExternalDocumentService $documents,
        private ExternalConsultantService $consultants,
        private LetterNumberService $numbers,
    ) {}

    public function index(Request $request, string $type)
    {
        $doc = $this->definition($type);
        $filters = ['search' => trim((string) $request->query('search')), 'status' => (string) $request->query('status')];
        $perPage = $this->perPage($request);
        $rows = $this->documents->listing($type, $filters, $perPage);

        $data = [
            'type'           => $type,
            'doc'            => $doc,
            'rows'           => $rows,
            'creators'       => $this->documents->names($rows->getCollection()->pluck('created_by')->all()),
            'filters'        => $filters,
            'perPage'        => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'filterForm'     => 'extDocFilters',
        ];

        if ($request->ajax()) {
            return view('hr-general.external.documents.components.list-table', $data);
        }

        $counts = $this->documents->counts();

        return view('hr-general.external.documents.index', $data + [
            'counts'        => $counts,
            'tabs'          => collect(ExternalRules::DOCUMENTS)->filter(fn ($d) => $this->canView($d['slug']))->all(),
            'canCreate'     => $this->employeeCan($doc['slug'], 'create'),
            'consultants'   => $this->consultants->options(),
            'contracts'     => $this->documents->contractsByConsultant(),
            'form'          => $this->documents->formOptions($this->actorId()),
            'openConsultant' => (int) $request->query('consultant'),
            'letterTypes'   => ExternalRules::DOCUMENTS,
        ]);
    }

    public function store(Request $request, string $type)
    {
        $doc = $this->definition($type);
        $input = $request->validate($this->rules($type));

        $consultant = $this->consultants->find((int) $input['employee_id']);
        if ($consultant === null) {
            throw ValidationException::withMessages(['employee_id' => 'Pick an external consultant.']);
        }
        if (!empty($input['contract_no'])) {
            $owned = collect($this->documents->contractsByConsultant()[$consultant->employee_id] ?? [])->contains('no', $input['contract_no']);
            if (!$owned) {
                throw ValidationException::withMessages(['contract_no' => 'That contract does not belong to the selected consultant.']);
            }
        }
        if ($error = ExternalRules::letterPeriodError($input['period_from'] ?? null, $input['period_to'] ?? null, $type === 'assignment')) {
            throw ValidationException::withMessages(['period_to' => $error]);
        }
        $typed = trim((string) ($input['letter_number'] ?? ''));
        if ($typed !== '' && $this->numbers->outgoingNumberUsed($typed)) {
            throw ValidationException::withMessages(['letter_number' => 'That letter number is already used.']);
        }

        $result = $this->documents->create($type, $input + ['sign_now' => $request->boolean('sign_now')], $this->actorId());
        $letter = $result['letter'];

        $redirect = redirect()->route($doc['route'])
            ->with('success', "{$doc['label']} {$letter->letter_number} created and archived" . ($result['signed'] ? ' — signed.' : '.'))
            ->with('openPdf', route('general.external.documents.pdf', $letter));

        return $result['sign_problem'] ? $redirect->with('warning', 'The letter was not signed: ' . $result['sign_problem']) : $redirect;
    }

    /** PDF dengan kop surat. Boleh dibuka siapa pun yang memegang menu jenis surat itu. */
    public function pdf(Letter $letter)
    {
        $key = ExternalRules::documentKeyOf($letter->template_key);
        abort_if($key === null || $letter->direction !== Letter::DIRECTION_OUTGOING, 404);
        abort_unless($this->canView(ExternalRules::DOCUMENTS[$key]['slug']), 403, 'You do not have access to this document type.');

        $pdf = $letter->toPdf();

        return request()->boolean('download') ? $pdf->download($letter->fileName()) : $pdf->stream($letter->fileName());
    }

    // ── helpers ───────────────────────────────────────────────────────────

    private function definition(string $type): array
    {
        abort_unless(isset(ExternalRules::DOCUMENTS[$type]), 404);

        return ExternalRules::DOCUMENTS[$type];
    }

    private function rules(string $type): array
    {
        $assignment = $type === 'assignment';

        return [
            'letter_date'           => 'required|date',
            'letter_number'         => 'nullable|string|max:100',
            'addressed_to'          => 'nullable|string|max:255',
            'purpose'               => [$type === 'reference' ? 'nullable' : 'required', 'string', 'max:5000'],
            'employee_id'           => 'required|integer|exists:employee,employee_id',
            'contract_no'           => 'nullable|string|max:100',
            'location'              => [$assignment ? 'required' : 'nullable', 'string', 'max:255'],
            'period_from'           => [$assignment ? 'required' : 'nullable', 'date'],
            'period_to'             => [$assignment ? 'required' : 'nullable', 'date'],
            'signatory_employee_id' => ['required', 'integer', Rule::in(\App\Services\Letters\LetterService::signatoryOptions()->pluck('id')->all())],
            'signatory_title'       => 'required|string|max:150',
            'language'              => ['required', Rule::in(array_keys(\App\Models\LetterTypeSetting::LANGUAGES))],
            'notes'                 => 'nullable|string|max:2000',
        ];
    }
}

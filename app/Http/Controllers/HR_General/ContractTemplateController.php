<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Models\ContractTemplate;
use App\Models\Letterhead;
use App\Models\Letters\LetterSetting;
use App\Models\Position;
use App\Services\Letters\LetterService;
use App\Services\Contracts\ContractService;
use App\Support\Contracts\ContractPlaceholders;
use App\Support\Contracts\ContractRules;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * HR & General → Contract → Templates (slug general.contracts.templates): teks kontrak dengan {{placeholder}}.
 *
 * V lihat & pratinjau · C buat template · E ubah (juga bawaan sistem) · D hapus (bukan bawaan sistem).
 * Kop surat TIDAK diatur di sini: dipasang di Letter Templates → Settings sebagai jenis "Employment Contract".
 */
class ContractTemplateController extends Controller
{
    public function __construct(private ContractService $contracts) {}

    public function index()
    {
        return view('hr-general.contracts.templates', [
            'templates'  => ContractTemplate::orderByDesc('is_system_default')->orderBy('contract_type')->orderBy('name')->get(),
            'types'      => ContractRules::TYPES,
            'letterhead' => Letterhead::forLetter(ContractService::LETTER_TYPE),
            // What the contract pages take from HR & General → Letter Templates → Settings.
            'linked'     => [
                'company' => LetterSetting::current()->company_name,
                'city'    => LetterSetting::current()->signing_city,
                'signers' => LetterService::signatoryOptions()->count(),
            ],
        ]);
    }

    public function create(Request $request)
    {
        $type = ContractRules::normalizeType((string) $request->query('contract_type')) ?? ContractRules::TYPE_PKWT;

        return $this->form(new ContractTemplate(['contract_type' => $type, 'status' => ContractTemplate::STATUS_ACTIVE, 'use_letterhead' => true, 'body_html' => '']));
    }

    public function edit(int $templateId)
    {
        return $this->form(ContractTemplate::findOrFail($templateId));
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());

        try {
            $this->contracts->saveTemplate(null, $data, (int) session('user.id'));
        } catch (\DomainException $e) {
            return back()->withInput()->withErrors(['template' => $e->getMessage()]);
        }

        return redirect()->route('general.contracts.templates.index')->with('success', "Template “{$data['name']}” created.");
    }

    public function update(Request $request, int $templateId)
    {
        $template = ContractTemplate::findOrFail($templateId);
        $data = $request->validate($this->rules());

        try {
            $this->contracts->saveTemplate($template, $data, (int) session('user.id'));
        } catch (\DomainException $e) {
            return back()->withInput()->withErrors(['template' => $e->getMessage()]);
        }

        return redirect()->route('general.contracts.templates.index')->with('success', "Template “{$data['name']}” saved.");
    }

    public function destroy(int $templateId)
    {
        $template = ContractTemplate::findOrFail($templateId);

        try {
            $this->contracts->deleteTemplate($template);
        } catch (\DomainException $e) {
            return back()->withErrors(['template' => $e->getMessage()]);
        }

        return redirect()->route('general.contracts.templates.index')->with('success', "Template “{$template->name}” deleted.");
    }

    /** Pratinjau dengan nilai contoh — tidak membaca data karyawan mana pun. */
    public function show(int $templateId)
    {
        $template = ContractTemplate::findOrFail($templateId);
        $values = ContractService::sampleValues($template->contract_type);

        return view('hr-general.contracts.template-preview', [
            'template'   => $template,
            'html'       => ContractRules::render($template->body_html, $values),
            'letterhead' => $template->use_letterhead ? Letterhead::forLetter(ContractService::LETTER_TYPE) : null,
            'pdfUrl'     => route('general.contracts.templates.pdf', $template->id),
        ]);
    }

    /** The sample contract as the real PDF (letterhead on every page) — what the preview page shows. */
    public function pdf(int $templateId)
    {
        $template = ContractTemplate::findOrFail($templateId);
        $doc = [
            'title'      => $template->name,
            'number'     => 'SAMPLE',
            'html'       => ContractRules::render($template->body_html, ContractService::sampleValues($template->contract_type)),
            'letterhead' => $template->use_letterhead ? Letterhead::forLetter(ContractService::LETTER_TYPE) : null,
        ];

        return Pdf::loadView('hr-general.contracts.pdf', ['doc' => $doc])->setPaper('a4')->stream('template-' . $template->id . '.pdf');
    }

    private function form(ContractTemplate $template)
    {
        return view('hr-general.contracts.template-form', [
            'template'     => $template,
            'types'        => ContractRules::TYPES,
            'placeholders' => ['employee' => ContractPlaceholders::EMPLOYEE, 'external' => ContractPlaceholders::EXTERNAL],
            'positions'    => Position::options(),
            'signatories'  => LetterService::signatoryOptions(),
            'letterhead'   => Letterhead::forLetter(ContractService::LETTER_TYPE),
        ]);
    }

    private function rules(): array
    {
        return [
            'name'           => 'required|string|max:150',
            'contract_type'  => ['required', Rule::in(array_keys(ContractRules::TYPES))],
            'position'       => 'nullable|string|max:255',
            'description'    => 'nullable|string|max:500',
            'body_html'      => 'required|string|max:300000',
            'use_letterhead' => 'nullable|boolean',
            'signatory_employee_id' => 'nullable|integer',
            'status'         => ['nullable', Rule::in(['active', 'inactive'])],
        ];
    }
}

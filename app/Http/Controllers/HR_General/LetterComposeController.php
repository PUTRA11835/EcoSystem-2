<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Http\Controllers\HR_General\Concerns\HandlesRecruitmentTables;
use App\Models\Employee;
use App\Models\Letterhead;
use App\Models\Letters\Letter;
use App\Models\Letters\LetterCode;
use App\Models\Letters\LetterRequest;
use App\Models\Letters\LetterSetting;
use App\Models\LetterTypeSetting;
use App\Services\Letters\LetterMailer;
use App\Services\Letters\LetterNumberService;
use App\Services\Letters\LetterService;
use App\Support\Letters\LetterTemplates;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * HR & General → Letter Templates → Create Letter (slug general.letters.compose):
 * a letter from a template — only the fields that template needs — or a
 * custom letter, in Bahasa Indonesia or English; the letters generated here,
 * signed with the master-data signature, sent, voided.
 *
 * V open, preview, download · C generate · E edit & generate again, sign, send · D void.
 */
class LetterComposeController extends Controller
{
    use HandlesRecruitmentTables;

    public const SLUG = 'general.letters.compose';

    public function __construct(private LetterService $letters) {}

    public function index(Request $request, LetterMailer $mailer, LetterNumberService $numbers)
    {
        $letterRequest = null;
        if ($request->filled('request')) {
            $letterRequest = LetterRequest::with('employee.basicData')->find($request->integer('request'));
            if (!$letterRequest || !$letterRequest->isOpen() || $letterRequest->activeLetter()) {
                return redirect()->route('general.letters.requests.index')->with('error', 'That request is no longer waiting for a letter.');
            }
        }

        return $this->page($request, $mailer, $numbers, null, $letterRequest);
    }

    public function edit(Request $request, Letter $letter, LetterMailer $mailer, LetterNumberService $numbers)
    {
        abort_unless($letter->isGenerated(), 404);

        if (!$letter->isEditable()) {
            return redirect()->route('general.letters.compose.index')->with('error', 'A letter that was sent or voided can no longer be changed.');
        }

        return $this->page($request, $mailer, $numbers, $letter, $letter->request);
    }

    /** The letter as it would print, in a new tab — nothing is saved and no number is used up. */
    public function preview(Request $request)
    {
        [$data] = $this->validated($request, null, preview: true);

        $letter = new Letter($data);
        $letter->letter_number = trim((string) ($data['letter_number'] ?? '')) ?: null;

        return $letter->toPdf(preview: true)->stream('Preview.pdf');
    }

    public function store(Request $request)
    {
        [$data, $letterRequest] = $this->validated($request);

        $letter = $this->letters->generate($data, (int) session('user.id'), $letterRequest);

        return redirect()->route('general.letters.compose.index')
            ->with('success', "Letter {$letter->letter_number} generated. Sign it with the master data signature before sending it.")
            ->with('openPdf', route('general.letters.pdf', $letter));
    }

    public function update(Request $request, Letter $letter)
    {
        abort_unless($letter->isGenerated() && $letter->isEditable(), 403, 'A letter that was sent or voided can no longer be changed.');

        [$data] = $this->validated($request, $letter);
        unset($data['letter_request_id']);

        $unsigned = $this->letters->regenerate($letter, $data, (int) session('user.id'));

        $redirect = redirect()->route('general.letters.compose.index')
            ->with('success', "Letter {$letter->letter_number} saved.")
            ->with('openPdf', route('general.letters.pdf', $letter));

        return $unsigned ? $redirect->with('warning', 'The letter changed, so its signature was removed. Sign it again before sending it.') : $redirect;
    }

    public function sign(Letter $letter)
    {
        abort_unless($letter->isGenerated() && !$letter->isVoid(), 403);

        if ($error = $this->letters->signingProblem($letter)) {
            return back()->with('error', $error);
        }

        $this->letters->sign($letter, (int) session('user.id'));

        return back()->with('success', "Letter {$letter->letter_number} signed by {$letter->signatory_name}. It can now be sent.");
    }

    /**
     * Emails the signed letter. One that answers an employee request finishes
     * that request too. After a delivered email there is no sending again.
     */
    public function send(Request $request, Letter $letter)
    {
        abort_unless($letter->isGenerated(), 403);

        if (!$letter->canBeSent()) {
            return back()->with('error', $letter->email_status === Letter::EMAIL_SENT
                ? 'This letter was already delivered by email — it is not sent twice.'
                : 'Sign the letter and give it a recipient email before sending it.');
        }

        $email = $this->validatedEmail($request);
        $letterRequest = $letter->request;

        $error = $letterRequest && $letterRequest->isOpen()
            ? $this->letters->complete($letterRequest, $email['subject'], $email['body'], (int) session('user.id'))
            : $this->letters->send($letter, $email['subject'], $email['body']);

        return $error
            ? back()->with('warning', "The email to {$letter->recipient_email} failed: {$error}. Use Resend to try again.")
            : back()->with('success', "Letter {$letter->letter_number} emailed to {$letter->recipient_email}.");
    }

    public function void(Request $request, Letter $letter)
    {
        abort_unless($letter->isGenerated() && !$letter->isVoid(), 403);

        $reason = $request->validate(['void_reason' => 'required|string|max:1000'], [], ['void_reason' => 'reason'])['void_reason'];
        $this->letters->void($letter, $reason, (int) session('user.id'));

        return back()->with('success', "Letter {$letter->letter_number} voided. Its number stays in the register and is not given out again.");
    }

    // ── internal ─────────────────────────────────────────────────────────────

    private function page(Request $request, LetterMailer $mailer, LetterNumberService $numbers, ?Letter $letter, ?LetterRequest $letterRequest)
    {
        $filters = [
            'search' => trim((string) $request->query('search')),
            'type'   => (string) $request->query('type'),
            'status' => (string) $request->query('status'),
        ];
        $perPage = $this->perPage($request);

        $list = Letter::with(['code', 'request', 'signedBy.basicData'])
            ->whereIn('source', [Letter::SOURCE_TEMPLATE, Letter::SOURCE_CUSTOM])
            ->when($filters['search'] !== '', fn ($q) => $q->where(fn ($s) => $s
                ->where('letter_number', 'like', "%{$filters['search']}%")
                ->orWhere('subject', 'like', "%{$filters['search']}%")
                ->orWhere('counterparty', 'like', "%{$filters['search']}%")))
            ->when($filters['type'] === LetterTemplates::CUSTOM, fn ($q) => $q->where('source', Letter::SOURCE_CUSTOM))
            ->when(LetterTemplates::exists($filters['type']), fn ($q) => $q->where('template_key', $filters['type']))
            ->when(in_array($filters['status'], ['draft', 'signed', 'sent', 'void'], true), fn ($q) => $q->withStatus($filters['status']))
            ->orderByDesc('letter_date')->orderByDesc('id')
            ->paginate($perPage)->withQueryString();

        $typeSettings = LetterTypeSetting::whereIn('letter_type', array_keys(LetterTemplates::letterTypes()))->get()->keyBy('letter_type');
        $signatories = LetterService::signatoryOptions();
        // Whoever writes the letter signs it — when Settings → Signers allows them — unless they pick another signer.
        $me = $signatories->firstWhere('id', (int) session('user.id')) ?? $signatories->first(); // you, if you may sign (Settings → Signers)
        $today = now();

        // What a new letter of each type starts with (Settings), used when the type is picked.
        $typeDefaults = collect(LetterTemplates::letterTypes())->map(function ($label, string $type) use ($typeSettings, $me) {
            $setting = $typeSettings[$type] ?? null;

            return [
                'language'              => $setting?->language ?? LetterTypeSetting::LANGUAGE_INDONESIAN,
                'letter_code_id'        => $setting?->letter_code_id,
                'signatory_employee_id' => $me['id'] ?? null,
                'signatory_name'        => $me['name'] ?? '',
                'signatory_title'       => $me['position'] ?? '',
            ];
        });

        $activeTemplates = collect(LetterTemplates::TEMPLATES)
            ->filter(fn ($template, $key) => ($typeSettings[$key]->is_active ?? true) || $letter?->template_key === $key || $letterRequest?->template_key === $key);

        return view('hr-general.letters.compose', [
            'letter'         => $letter,
            'letterRequest'  => $letterRequest,
            'mode'           => $letter ? ($letter->source === Letter::SOURCE_CUSTOM ? 'custom' : 'template')
                : ($letterRequest ? ($letterRequest->usesTemplate() ? 'template' : 'custom') : ($request->query('mode') === 'custom' ? 'custom' : 'template')),
            'templates'      => $activeTemplates,
            'customActive'   => $typeSettings[LetterTemplates::CUSTOM]->is_active ?? true,
            'typeDefaults'   => $typeDefaults,
            'codes'          => LetterCode::ordered()->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $letter?->letter_code_id))->get(),
            'employees'      => LetterService::employeeOptions(),
            'signatories'    => $signatories,
            // The signer is picked role first (Settings → Signers).
            'signingRoles'   => LetterService::signingRoles(),
            'languages'      => LetterTypeSetting::LANGUAGES,
            'settings'       => LetterSetting::current(),
            'nextSequence'   => $numbers->peek(LetterNumberService::OUTGOING, $today->year),
            'letters'        => $list,
            'emails'         => $list->getCollection()->filter->canBeSent()->mapWithKeys(fn (Letter $l) => [$l->id => $mailer->defaultLetterEmail($l)]),
            'filters'        => $filters,
            'hasFilters'     => collect($filters)->filter(fn ($v) => $v !== '')->isNotEmpty(),
            'perPage'        => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'typeOptions'    => LetterTemplates::letterTypes(),
            'statusOptions'  => Arr::only(Letter::STATUSES, ['draft', 'signed', 'sent', 'void']),
            'canRequests'    => $this->employeeCan('general.letters.requests', 'edit'),
        ]);
    }

    /**
     * The letter's columns from the form, and the employee request it answers.
     *
     * @return array{0: array, 1: ?LetterRequest}
     */
    private function validated(Request $request, ?Letter $letter = null, bool $preview = false): array
    {
        $mode = $letter ? ($letter->source === Letter::SOURCE_CUSTOM ? 'custom' : 'template') : ($request->input('mode') === 'custom' ? 'custom' : 'template');
        $letterRequest = !$letter && $request->filled('letter_request_id') ? LetterRequest::find($request->integer('letter_request_id')) : null;

        if ($letterRequest) {
            abort_unless($this->employeeCan('general.letters.requests', 'edit'), 403, 'Answering employee requests needs the Edit box of Letter Templates → Requests.');
            if (!$letterRequest->isOpen() || $letterRequest->activeLetter()) {
                throw ValidationException::withMessages(['letter_request_id' => 'That request is no longer waiting for a letter.']);
            }
            // The request decides what the letter is and who it is about: its template,
            // or — "Other" and options without one — a custom letter.
            $mode = $letterRequest->usesTemplate() ? 'template' : 'custom';
            $request->merge(['template_key' => $letterRequest->template_key, 'employee_id' => $letterRequest->employee_id, 'mode' => $mode]);
        }

        // Money arrives as typed, with thousands separators ("1.500.000").
        if ($request->has('fields.amount')) {
            $request->merge(['fields' => [...(array) $request->input('fields'), 'amount' => preg_replace('/\D/', '', (string) $request->input('fields.amount')) ?: null]]);
        }

        $rules = [
            'language'              => ['required', Rule::in(array_keys(LetterTypeSetting::LANGUAGES))],
            'letter_date'           => 'required|date',
            'letter_number'         => 'nullable|string|max:100',
            'letter_code_id'        => 'nullable|integer|exists:letter_codes,id',
            // Signed by someone Settings → Signers allows, by role.
            'signatory_employee_id' => ['required', 'integer', Rule::in(LetterService::signatoryOptions()->pluck('id')->all())],
            'signatory_name'        => 'required|string|max:150',
            'signatory_title'       => 'required|string|max:150',
            'recipient_email'       => 'nullable|email|max:150',
            'notes'                 => 'nullable|string|max:2000',
        ];
        $attributes = ['signatory_employee_id' => 'signatory', 'signatory_name' => 'signatory name', 'signatory_title' => 'signatory position', 'recipient_email' => 'send to (email)'];

        if ($mode === 'template') {
            $key = $letter?->template_key ?? $request->input('template_key');
            if (!LetterTemplates::exists($key)) {
                throw ValidationException::withMessages(['template_key' => 'Pick the letter template.']);
            }
            $rules['employee_id'] = [LetterTemplates::needsEmployee($key) ? 'required' : 'nullable', 'integer', 'exists:employee,employee_id'];
            $rules += LetterTemplates::rules($key);
            $attributes += LetterTemplates::attributes($key) + ['employee_id' => 'employee'];
        } else {
            $rules += [
                'subject'      => 'required|string|max:255',
                'counterparty' => 'nullable|string|max:255',
                'body'         => 'required|string|max:20000',
            ];
            $attributes += ['counterparty' => 'recipient', 'body' => 'letter text'];
        }

        $input = $request->validate($rules, ['signatory_employee_id.in' => 'Pick a signatory allowed in Letter Templates → Settings → Signers.'], $attributes);

        $typed = trim((string) ($input['letter_number'] ?? ''));
        if (!$preview && $typed !== '' && $typed !== $letter?->letter_number && app(LetterNumberService::class)->outgoingNumberUsed($typed, $letter?->id)) {
            throw ValidationException::withMessages(['letter_number' => 'That letter number is already used.']);
        }

        $settings = LetterSetting::current();
        $common = Arr::only($input, ['language', 'letter_date', 'letter_number', 'letter_code_id', 'signatory_employee_id', 'signatory_name', 'signatory_title', 'recipient_email', 'notes']);

        if ($mode === 'template') {
            $employee = isset($input['employee_id']) ? Employee::with('basicData')->find($input['employee_id']) : null;
            $fields = LetterTemplates::cleanFields($key, (array) ($input['fields'] ?? []));
            $template = LetterTemplates::get($key);
            $snapshot = LetterTemplates::employeeSnapshot($employee);

            $data = [
                ...$common,
                'source'         => Letter::SOURCE_TEMPLATE,
                'template_key'   => $key,
                'employee_id'    => $employee?->employee_id,
                'subject'        => $input['language'] === LetterTypeSetting::LANGUAGE_ENGLISH ? $template['label_en'] : $template['label'],
                'counterparty'   => $snapshot['name'] ?? ($fields['received_from'] ?? null),
                // A letter about an employee goes to their work email unless another address was typed.
                'recipient_email'=> ($common['recipient_email'] ?? null) ?: Letter::workEmailOf($employee?->employee_id),
                'use_letterhead' => true,
                'fields'         => [...$fields, 'employee' => $snapshot, 'company' => $settings->company_name, 'city' => $settings->signing_city],
            ];
            $type = $key;
        } else {
            $data = [
                ...$common,
                'source'         => Letter::SOURCE_CUSTOM,
                'template_key'   => null,
                // A custom letter answering an employee's request is about that employee.
                'employee_id'    => $letterRequest?->employee_id ?? $letter?->employee_id,
                'subject'        => $input['subject'],
                'counterparty'   => $input['counterparty'] ?? null,
                'recipient_email'=> ($common['recipient_email'] ?? null) ?: Letter::workEmailOf($letterRequest?->employee_id),
                'use_letterhead' => $request->boolean('use_letterhead'),
                'fields'         => ['body' => $input['body'], 'company' => $settings->company_name, 'city' => $settings->signing_city],
            ];
            $type = LetterTemplates::CUSTOM;
        }

        // The letterhead the letter is printed on is decided when it is generated, not changed by later settings.
        $data['letterhead_id'] = $letter?->letterhead_id ?? Letterhead::forLetter($type)?->id;
        $data['letter_request_id'] = $letterRequest?->id;

        return [$data, $letterRequest];
    }

    private function validatedEmail(Request $request): array
    {
        return $request->validate([
            'subject' => 'required|string|max:255',
            'body'    => 'required|string|max:10000',
        ], [], ['body' => 'message']);
    }
}

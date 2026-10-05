<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Http\Controllers\HR_General\Concerns\HandlesRecruitmentTables;
use App\Models\Employee;
use App\Models\EmployeeHrProfile;
use App\Models\Letterhead;
use App\Models\LetterTypeSetting;
use App\Models\Recruitment\Candidate;
use App\Models\Recruitment\Offer;
use App\Models\Recruitment\OfferComponent;
use App\Models\Recruitment\RecruitmentSetting;
use App\Services\Letters\LetterNumberService;
use App\Services\Letters\LetterService;
use App\Services\Recruitment\CandidateHireService;
use App\Services\Recruitment\OfferMailer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * HR & General → Offering Letter: the letters (first tab) and the settings
 * they are written with (second tab).
 */
class RecruitmentOfferController extends Controller
{
    use HandlesRecruitmentTables;

    public function index(Request $request, OfferMailer $mailer)
    {
        $filters = [
            'number'    => trim((string) $request->query('number')),
            'candidate' => trim((string) $request->query('candidate')),
            'position'  => trim((string) $request->query('position')),
            'status'    => $request->query('status'),
        ];

        $perPage = $this->perPage($request);

        $offers = Offer::query()
            ->when($filters['number'] !== '', fn ($q) => $q->where('letter_number', 'like', "%{$filters['number']}%"))
            ->when($filters['candidate'] !== '', fn ($q) => $q->where(fn ($c) => $c
                ->where('candidate_name', 'like', "%{$filters['candidate']}%")
                ->orWhere('candidate_email', 'like', "%{$filters['candidate']}%")))
            ->when($filters['position'] !== '', fn ($q) => $q->where('position_title', 'like', "%{$filters['position']}%"))
            ->when(isset(Offer::STATUSES[$filters['status']]), fn ($q) => $q->withStatus($filters['status']))
            ->orderByDesc('offer_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        // Offered in the modal's dropdown: everyone at the Offer stage, plus
        // anyone a letter already points at, so editing never loses the link.
        $candidates = Candidate::with(['position', 'jobOpening'])
            ->withCount('offers')
            ->where(fn ($q) => $q->where('status', Candidate::STATUS_OFFER)->orWhereHas('offers'))
            ->orderBy('name')
            ->get();

        $signatory = Employee::with('basicData')->find(session('user.id'))?->basicData;
        $settings = RecruitmentSetting::current();
        $components = OfferComponent::ordered()->get();
        $today = now();
        $language = LetterTypeSetting::languageFor(Letterhead::TYPE_OFFERING_LETTER);

        return view('hr-general.offering.index', [
            'offers'         => $offers,
            'filters'        => $filters,
            'hasFilters'     => collect($filters)->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty(),
            'perPage'        => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'statuses'       => Offer::STATUSES,
            'components'     => $components,
            'settings'       => $settings,
            'ratioNote'      => $settings->offerRatioNoteHtml(...OfferComponent::activeNamesByKind($components)),
            'candidates'     => $candidates,
            'awaitingLetter' => $candidates->where('status', Candidate::STATUS_OFFER)->where('offers_count', 0)->values(),
            // The number a new letter dated today gets, in each language it can be written in (IN / EN segment).
            'nextNumbers'    => collect(LetterTypeSetting::LANGUAGES)
                ->map(fn ($label, string $code) => Offer::numberFor($today, Offer::nextSequence($today), $code)),
            'languages'      => LetterTypeSetting::LANGUAGES,
            'signatories'    => $this->signatories(),
            // The email each signed letter starts with, adjusted by HR before it is sent.
            'emails'         => $offers->getCollection()
                ->filter(fn (Offer $offer) => $offer->isPending() && $offer->isSigned())
                ->mapWithKeys(fn (Offer $offer) => [$offer->id => $mailer->defaultEmail($offer)]),
            'defaults'       => [
                'language'              => $language,
                'offer_date'            => $today->toDateString(),
                'signatory_employee_id' => session('user.id'),
                'signatory_name'        => $signatory->full_name ?? '',
                'signatory_title'       => $signatory->position ?? '',
            ],
            'prefillCandidateId' => $request->integer('candidate_id') ?: null,
        ]);
    }

    public function store(Request $request, LetterNumberService $numbers)
    {
        $data = $this->validated($request);
        $date = Carbon::parse($data['offer_date']);

        $offer = DB::transaction(function () use ($data, $date, $numbers) {
            $number = $data['letter_number'] ?? null;
            // A number typed by hand does not use up a running number.
            $sequence = Offer::nextSequence($date);

            if (!$number) {
                // The outgoing counter of the Letter Templates hub, shared by every outgoing letter: a number
                // is never given out twice. A number typed by hand earlier may already hold the one generated.
                do {
                    $sequence = $numbers->take(LetterNumberService::OUTGOING, $date->year);
                    $number = Offer::numberFor($date, $sequence, $data['language']);
                } while ($numbers->outgoingNumberUsed($number));
            }

            return Offer::create([
                ...$data,
                'letter_number'          => $number,
                'number_sequence'        => $sequence,
                'decision'               => Offer::DECISION_PENDING,
                'created_by_employee_id' => session('user.id'),
            ]);
        });

        return back()->with('success', "Offering letter {$offer->letter_number} saved.");
    }

    public function update(Request $request, Offer $offer)
    {
        abort_unless($offer->isPending(), 403, 'A letter that was accepted or rejected can no longer be changed.');

        $data = $this->validated($request, $offer);
        // The form sends the current number back as it was: left alone (or emptied), it follows the language and date.
        $typed = trim((string) ($data['letter_number'] ?? ''));
        $data['letter_number'] = $typed === '' || $typed === $offer->letter_number ? $this->renumbered($offer, $data) : $typed;

        $wasSigned = $offer->isSigned();
        $offer->fill($data);
        $changed = $offer->isDirty();
        $offer->save();

        // What was signed is no longer what the letter says: it has to be signed again.
        if ($wasSigned && $changed) {
            $offer->removeSignature();

            return back()->with('success', "Offering letter {$offer->letter_number} saved.")
                ->with('warning', 'The letter changed, so its signature was removed. Sign it again before sending it.');
        }

        return back()->with('success', "Offering letter {$offer->letter_number} saved.");
    }

    /**
     * Signs the letter with its signatory's signature from the employee master
     * data (employee_hr_profile.signature_path), copied onto the letter.
     */
    public function sign(Offer $offer)
    {
        abort_unless($offer->isPending(), 403);

        if (!$offer->signatory_employee_id) {
            return back()->with('error', 'Pick the signatory from the employee list (edit the letter) before signing it — the signature is taken from their master data.');
        }

        if (!EmployeeHrProfile::signaturePathOf($offer->signatory_employee_id)) {
            return back()->with('error', "{$offer->signatory_name} has no signature in the employee master data yet. Upload it there first, then sign the letter.");
        }

        try {
            $offer->signWithMasterSignature((int) session('user.id'));
        } catch (\Throwable $e) {
            Log::error('Recruitment: failed to sign offering letter', ['offer_id' => $offer->id, 'error' => $e->getMessage()]);

            return back()->with('error', 'Failed to sign the letter: ' . $e->getMessage());
        }

        return back()->with('success', "Offering letter {$offer->letter_number} signed by {$offer->signatory_name}. It can now be downloaded and sent to the candidate.");
    }

    public function print(Offer $offer)
    {
        $name = preg_replace('/[^\w\s-]/u', '', $offer->candidate_name);

        return $this->pdf($offer)->stream("Offering Letter - {$name}.pdf");
    }

    public function send(Request $request, Offer $offer, OfferMailer $mailer)
    {
        abort_unless($offer->isPending(), 403);

        if (!$offer->isSigned()) {
            return back()->with('error', 'Sign the letter before sending it to the candidate.');
        }

        if (!$offer->candidate_email) {
            return back()->with('error', 'This letter has no candidate email address — add one before sending it.');
        }

        $email = $request->validate([
            'subject' => 'required|string|max:255',
            'body'    => 'required|string|max:10000',
        ], [], ['body' => 'message']);

        try {
            $mailer->sendOfferLetter($offer, $this->pdf($offer)->output(), $email['subject'], $email['body']);
            $offer->update(['sent_at' => now()]);

            return back()->with('success', "Offering letter emailed to {$offer->candidate_email}.");
        } catch (\Throwable $e) {
            Log::error('Recruitment: failed to send offering letter', ['offer_id' => $offer->id, 'error' => $e->getMessage()]);

            return back()->with('error', 'Failed to send the offering letter: ' . $e->getMessage());
        }
    }

    /**
     * The candidate said yes: their employee account is created here with the
     * default password HR set, and the sign-in details are emailed to them.
     * Signing in with that password asks them to set their own first.
     */
    public function accept(Request $request, Offer $offer, CandidateHireService $hireService, OfferMailer $mailer)
    {
        abort_unless($offer->isPending(), 403);

        $data = $request->validate([
            'eci'              => 'required|string|max:50|unique:employee,eci|unique:auth_users,username',
            'full_name'        => 'required|string|max:150',
            // Sign-in is by email, so no two accounts may share one.
            'email'            => 'required|email|max:150|unique:auth_users,email',
            // The same minimum as the password the person sets afterwards (PasswordSetupController).
            'default_password' => 'required|string|min:8|max:100',
            // HC-D65 (R3): Home Base menentukan Internal/External ("Others" = External); wajib agar tak diam-diam dianggap Internal.
            'home_base'        => ['required', Rule::in(\App\Enums\HomeBase::options())],
            // Tanggal bergabung = tanggal pada offering letter (terisi otomatis di form); wajib diisi HR bila penawaran tak memuatnya.
            'joining_date'     => [Rule::requiredIf(!$offer->joining_date), 'nullable', 'date'],
        ], [
            'email.unique'           => 'Another account already signs in with that email.',
            'joining_date.required' => 'The offer has no joining date — please enter the join date.',
        ], [
            'eci' => 'employee ID (ECI)', 'full_name' => 'full name', 'default_password' => 'default password', 'joining_date' => 'join date',
        ]);

        try {
            $joinDate = $data['joining_date'] ?? $offer->joining_date?->toDateString();

            $employee = $hireService->hire([
                'full_name' => $data['full_name'],
                'eci'       => $data['eci'],
                'email'     => $data['email'],
                'password'  => $data['default_password'],
                'position'  => $offer->position_title,
                'home_base' => $data['home_base'],
            ], $joinDate);

            $offer->update(['decision' => Offer::DECISION_ACCEPTED, 'decided_at' => now(), 'hired_employee_id' => $employee->employee_id]);

            if ($offer->candidate) {
                $offer->candidate->update(['hired_employee_id' => $employee->employee_id]);
                $offer->candidate->transitionTo(Candidate::STATUS_HIRED);
            }
        } catch (\Throwable $e) {
            Log::error('Recruitment: failed to create employee account', ['offer_id' => $offer->id, 'error' => $e->getMessage()]);

            return back()->with('error', 'Failed to create the employee account: ' . $e->getMessage());
        }

        try {
            $mailer->sendAccountDetails($offer, $data['full_name'], $data['eci'], $data['email'], $data['default_password']);
        } catch (\Throwable $e) {
            Log::error('Recruitment: failed to email sign-in details', ['offer_id' => $offer->id, 'error' => $e->getMessage()]);

            return back()
                ->with('success', 'Offer accepted and the employee account was created.')
                ->with('warning', "The sign-in details could not be emailed ({$e->getMessage()}). Give the candidate their username ({$data['eci']}) and the default password you set yourself.");
        }

        return back()->with('success', "Offer accepted. The employee account was created and the sign-in details were emailed to {$data['email']} — the candidate sets their own password after the first sign-in.");
    }

    public function reject(Offer $offer)
    {
        abort_unless($offer->isPending(), 403);

        $offer->update(['decision' => Offer::DECISION_REJECTED, 'decided_at' => now()]);
        $offer->candidate?->transitionTo(Candidate::STATUS_REJECTED);

        return back()->with('success', 'Offer marked as rejected.');
    }

    // ── Settings tab ─────────────────────────────────────────────────────────

    public function settings()
    {
        $settings = RecruitmentSetting::current();
        $components = OfferComponent::ordered()->get();

        return view('hr-general.offering.settings', [
            'settings'          => $settings,
            'components'        => $components,
            'ratioNote'         => RecruitmentSetting::cleanNote(old('offer_ratio_note', $settings->offer_ratio_note ?: RecruitmentSetting::DEFAULT_RATIO_NOTE)),
            'ratioPlaceholders' => RecruitmentSetting::RATIO_NOTE_PLACEHOLDERS,
            'ratioPreview'      => $settings->offerRatioNoteHtml(...OfferComponent::activeNamesByKind($components)),
            'kinds'             => OfferComponent::ALLOWANCE_KINDS,
            'nextSequence'      => Offer::nextSequence(now()),
            'letterhead'        => Letterhead::forLetter(Letterhead::TYPE_OFFERING_LETTER),
        ]);
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'offer_number_format'           => ['required', 'string', 'max:100', 'regex:/\{seq\}/'],
            'offer_number_digits'           => 'required|integer|between:1,6',
            'offer_base_salary_min_percent' => 'required|numeric|between:0,100',
            'offer_ratio_note'              => 'required|string|max:5000',
            'offer_response_days'           => 'required|integer|between:1,60',
            'offer_signing_city'            => 'required|string|max:100',
        ], ['offer_number_format.regex' => 'The letter number format must contain {seq}, the running number.']);

        // The editor sends HTML; only its bold / italic / underline and line breaks are kept.
        $data['offer_ratio_note'] = RecruitmentSetting::cleanNote($data['offer_ratio_note']);

        if (trim(html_entity_decode(strip_tags($data['offer_ratio_note']), ENT_QUOTES | ENT_HTML5, 'UTF-8'), " \u{A0}\n\r\t") === '') {
            throw ValidationException::withMessages(['offer_ratio_note' => 'Write the note shown under the base salary percentage.']);
        }

        RecruitmentSetting::current()->update($data);
        RecruitmentSetting::forgetCache();

        return back()->with('success', 'Offering letter settings saved.');
    }

    public function storeComponent(Request $request)
    {
        $data = $request->validate([
            'name'    => 'required|string|max:100|unique:recruitment_offer_components,name',
            'name_en' => 'nullable|string|max:100',
            'kind'    => ['required', Rule::in(array_keys(OfferComponent::ALLOWANCE_KINDS))],
        ], ['name.unique' => 'That compensation component already exists.']);

        OfferComponent::create([
            ...$data,
            'sort_order' => (int) OfferComponent::max('sort_order') + 1,
            'is_active'  => true,
        ]);

        return back()->with('success', 'Compensation component added.');
    }

    public function updateComponent(Request $request, OfferComponent $component)
    {
        $data = $request->validate([
            'name'    => ['required', 'string', 'max:100', Rule::unique('recruitment_offer_components', 'name')->ignore($component->id)],
            'name_en' => 'nullable|string|max:100',
            // The base salary stays the base salary, and is always offered.
            'kind'    => [$component->isBase() ? 'exclude' : 'required', Rule::in(array_keys(OfferComponent::ALLOWANCE_KINDS))],
        ], ['name.unique' => 'That compensation component already exists.']);

        $component->update([...$data, 'is_active' => $component->isBase() || $request->boolean('is_active')]);

        // The Settings page saves a row the moment it changes, in the background.
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => "\"{$component->name}\" saved."]);
        }

        return back()->with('success', 'Compensation component updated.');
    }

    public function destroyComponent(OfferComponent $component)
    {
        if ($component->isBase()) {
            return back()->with('error', 'The base salary cannot be deleted — every offering letter has one.');
        }

        if ($component->isInUse()) {
            return back()->with('error', "\"{$component->name}\" is on saved letters and cannot be deleted. Deactivate it instead to hide it from new letters.");
        }

        $component->delete();

        return back()->with('success', 'Compensation component deleted.');
    }

    // ── internal ─────────────────────────────────────────────────────────────

    private function pdf(Offer $offer)
    {
        return Pdf::loadView('hr-general.offering.offer-letter-pdf', [
            'offer'      => $offer,
            'settings'   => RecruitmentSetting::current(),
            'letterhead' => Letterhead::forLetter(Letterhead::TYPE_OFFERING_LETTER),
            'signature'  => $offer->signatureDataUri(),
        ])->setPaper('a4', 'portrait');
    }

    /**
     * The number an edited letter keeps. A number generated from the format is
     * generated again with the same running number when the language or the
     * date changes within the year, so its IN / EN segment stays right; a
     * number typed by hand is kept as it is.
     */
    private function renumbered(Offer $offer, array $data): string
    {
        $date = Carbon::parse($data['offer_date']);
        $wasGenerated = $offer->letter_number === Offer::numberFor($offer->offer_date, $offer->number_sequence, $offer->languageCode());

        if (!$wasGenerated || $date->year !== $offer->offer_date->year) {
            return $offer->letter_number;
        }

        $number = Offer::numberFor($date, $offer->number_sequence, $data['language']);

        return Offer::where('letter_number', $number)->whereKeyNot($offer->id)->exists() ? $offer->letter_number : $number;
    }

    /** Who can sign a letter: active employees of the master data, and whether their signature is there yet. */
    private function signatories()
    {
        return LetterService::employeeOptions();
    }

    /** The letter's columns from the modal form, with the amounts turned into the saved compensation lines. */
    private function validated(Request $request, ?Offer $offer = null): array
    {
        // Amounts arrive as typed, with thousands separators ("4.500.000").
        $request->merge(['amounts' => collect((array) $request->input('amounts'))->map(function ($value) {
            $digits = preg_replace('/\D/', '', preg_replace('/,\d{1,2}$/', '', trim((string) $value)));

            return $digits === '' ? null : $digits;
        })->all()]);

        $data = $request->validate([
            'letter_number'   => ['nullable', 'string', 'max:60', Rule::unique('recruitment_offers', 'letter_number')->ignore($offer?->id)],
            'language'        => ['required', Rule::in(array_keys(LetterTypeSetting::LANGUAGES))],
            'offer_date'      => 'required|date',
            'candidate_id'    => 'nullable|exists:recruitment_candidates,id',
            'candidate_name'  => 'required|string|max:150',
            'candidate_email' => 'nullable|email|max:150',
            'candidate_phone' => 'nullable|string|max:30',
            'position_title'  => 'required|string|max:150',
            'job_description' => 'nullable|string|max:5000',
            'benefits'        => 'nullable|string|max:5000',
            // Wajib (HC-D63): tanggal ini disalin otomatis menjadi Since Date saat akun karyawan dibuat (Accept).
            'joining_date'    => 'required|date',
            'salary_type'     => ['required', Rule::in(array_keys(Offer::SALARY_TYPES))],
            'amounts'         => 'array',
            'amounts.*'       => 'nullable|numeric|min:0|max:9999999999999',
            'notes'           => 'nullable|string|max:5000',
            'signatory_name'  => 'required|string|max:150',
            'signatory_title' => 'required|string|max:150',
            'signatory_employee_id' => 'nullable|integer|exists:employee,employee_id',
        ], ['letter_number.unique' => 'That letter number is already used.'], [
            'candidate_name' => 'candidate name', 'signatory_name' => 'signatory name', 'signatory_title' => 'signatory position',
            'signatory_employee_id' => 'signatory',
        ]);

        $lines = OfferComponent::ordered()->get()
            ->map(fn (OfferComponent $component) => [
                'component_id' => $component->id,
                'name'         => $component->name,
                'name_en'      => $component->name_en,
                'kind'         => $component->kind,
                'amount'       => (float) ($data['amounts'][$component->id] ?? 0),
            ])
            ->filter(fn (array $line) => $line['amount'] > 0)
            ->values();

        if ($lines->where('kind', OfferComponent::KIND_BASE)->isEmpty()) {
            throw ValidationException::withMessages(['amounts' => 'Enter the base salary.']);
        }

        unset($data['amounts']);

        return [
            ...$data,
            'has_probation'      => $request->boolean('has_probation'),
            'compensation'       => $lines->all(),
            'total_compensation' => $lines->sum('amount'),
        ];
    }
}

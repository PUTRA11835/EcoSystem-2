<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Http\Controllers\HR_General\Concerns\HandlesRecruitmentTables;
use App\Models\Employee;
use App\Models\Letterhead;
use App\Models\Recruitment\Candidate;
use App\Models\Recruitment\Offer;
use App\Models\Recruitment\OfferComponent;
use App\Models\Recruitment\RecruitmentSetting;
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

    public function index(Request $request)
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
        $today = now();

        return view('hr-general.offering.index', [
            'offers'         => $offers,
            'filters'        => $filters,
            'hasFilters'     => collect($filters)->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty(),
            'perPage'        => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'statuses'       => Offer::STATUSES,
            'components'     => OfferComponent::ordered()->get(),
            'settings'       => RecruitmentSetting::current(),
            'candidates'     => $candidates,
            'awaitingLetter' => $candidates->where('status', Candidate::STATUS_OFFER)->where('offers_count', 0)->values(),
            'nextNumber'     => Offer::numberFor($today, Offer::nextSequence($today)),
            'defaults'       => [
                'offer_date'      => $today->toDateString(),
                'signatory_name'  => $signatory->full_name ?? '',
                'signatory_title' => $signatory->position ?? '',
            ],
            'prefillCandidateId' => $request->integer('candidate_id') ?: null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $date = Carbon::parse($data['offer_date']);

        $offer = DB::transaction(function () use ($data, $date) {
            $sequence = Offer::nextSequence($date);
            $number = $data['letter_number'] ?? null;

            if (!$number) {
                // A number typed by hand earlier may already hold the next generated one.
                while (Offer::where('letter_number', $number = Offer::numberFor($date, $sequence))->exists()) {
                    $sequence++;
                }
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
        $data['letter_number'] = $data['letter_number'] ?? $offer->letter_number;

        $offer->update($data);

        return back()->with('success', "Offering letter {$offer->letter_number} saved.");
    }

    public function print(Offer $offer)
    {
        $name = preg_replace('/[^\w\s-]/u', '', $offer->candidate_name);

        return $this->pdf($offer)->stream("Offering Letter - {$name}.pdf");
    }

    public function send(Offer $offer, OfferMailer $mailer)
    {
        abort_unless($offer->isPending(), 403);

        if (!$offer->candidate_email) {
            return back()->with('error', 'This letter has no candidate email address — add one before sending it.');
        }

        try {
            $mailer->sendOfferLetter($offer, $this->pdf($offer)->output());
            $offer->update(['sent_at' => now()]);

            return back()->with('success', "Offering letter emailed to {$offer->candidate_email}.");
        } catch (\Throwable $e) {
            Log::error('Recruitment: failed to send offering letter', ['offer_id' => $offer->id, 'error' => $e->getMessage()]);

            return back()->with('error', 'Failed to send the offering letter: ' . $e->getMessage());
        }
    }

    /** The candidate said yes: their employee account is created here, so they can sign in straight away. */
    public function accept(Request $request, Offer $offer, CandidateHireService $hireService)
    {
        abort_unless($offer->isPending(), 403);

        $data = $request->validate([
            'eci'       => 'required|string|max:50|unique:employee,eci|unique:auth_users,username',
            'nick_name' => 'required|string|max:100|unique:employee_basic_data,nick_name',
            'email'     => 'required|email|max:150',
        ]);

        try {
            $employee = $hireService->hire($offer->candidate_name, $data);

            $offer->update(['decision' => Offer::DECISION_ACCEPTED, 'decided_at' => now(), 'hired_employee_id' => $employee->employee_id]);

            if ($offer->candidate) {
                $offer->candidate->update(['hired_employee_id' => $employee->employee_id]);
                $offer->candidate->transitionTo(Candidate::STATUS_HIRED);
            }

            return back()->with('success', 'Offer accepted. The employee account was created and a "set your password" email was sent to ' . $data['email'] . '.');
        } catch (\Throwable $e) {
            Log::error('Recruitment: failed to create employee account', ['offer_id' => $offer->id, 'error' => $e->getMessage()]);

            return back()->with('error', 'Failed to create the employee account: ' . $e->getMessage());
        }
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
        return view('hr-general.offering.settings', [
            'settings'     => RecruitmentSetting::current(),
            'components'   => OfferComponent::ordered()->get(),
            'kinds'        => OfferComponent::ALLOWANCE_KINDS,
            'nextSequence' => Offer::nextSequence(now()),
            'letterhead'   => Letterhead::forLetter(Letterhead::TYPE_OFFERING_LETTER),
        ]);
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'offer_number_format'           => ['required', 'string', 'max:100', 'regex:/\{seq\}/'],
            'offer_number_digits'           => 'required|integer|between:1,6',
            'offer_base_salary_min_percent' => 'required|numeric|between:0,100',
            'offer_legal_basis'             => 'required|string|max:255',
            'offer_default_benefits'        => 'nullable|string|max:500',
            'offer_response_days'           => 'required|integer|between:1,60',
            'offer_signing_city'            => 'required|string|max:100',
        ], ['offer_number_format.regex' => 'The letter number format must contain {seq}, the running number.']);

        RecruitmentSetting::current()->update([...$data, 'offer_default_benefits' => $data['offer_default_benefits'] ?? '']);
        RecruitmentSetting::forgetCache();

        return back()->with('success', 'Offering letter settings saved.');
    }

    public function storeComponent(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100|unique:recruitment_offer_components,name',
            'kind' => ['required', Rule::in(array_keys(OfferComponent::ALLOWANCE_KINDS))],
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
            'name' => ['required', 'string', 'max:100', Rule::unique('recruitment_offer_components', 'name')->ignore($component->id)],
            // The base salary stays the base salary, and is always offered.
            'kind' => [$component->isBase() ? 'exclude' : 'required', Rule::in(array_keys(OfferComponent::ALLOWANCE_KINDS))],
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
        ])->setPaper('a4', 'portrait');
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
            'offer_date'      => 'required|date',
            'candidate_id'    => 'nullable|exists:recruitment_candidates,id',
            'candidate_name'  => 'required|string|max:150',
            'candidate_email' => 'nullable|email|max:150',
            'candidate_phone' => 'nullable|string|max:30',
            'position_title'  => 'required|string|max:150',
            'job_description' => 'nullable|string|max:5000',
            'benefits'        => 'nullable|string|max:5000',
            'joining_date'    => 'nullable|date',
            'salary_type'     => ['required', Rule::in(array_keys(Offer::SALARY_TYPES))],
            'amounts'         => 'array',
            'amounts.*'       => 'nullable|numeric|min:0|max:9999999999999',
            'notes'           => 'nullable|string|max:5000',
            'signatory_name'  => 'required|string|max:150',
            'signatory_title' => 'required|string|max:150',
        ], ['letter_number.unique' => 'That letter number is already used.'], [
            'candidate_name' => 'candidate name', 'signatory_name' => 'signatory name', 'signatory_title' => 'signatory position',
        ]);

        $lines = OfferComponent::ordered()->get()
            ->map(fn (OfferComponent $component) => [
                'component_id' => $component->id,
                'name'         => $component->name,
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

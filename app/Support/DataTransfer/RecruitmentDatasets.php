<?php

namespace App\Support\DataTransfer;

use App\Models\Employee;
use App\Models\Recruitment\Candidate;
use App\Models\Recruitment\JobOpening;
use App\Models\Recruitment\Offer;
use App\Models\Recruitment\OfferComponent;
use App\Models\Recruitment\RecruitmentOption;
use App\Models\LetterTypeSetting;
use App\Services\Letters\LetterNumberService;
use App\Services\Recruitment\RecruitmentFormOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Recruitment: Candidates, Job Openings and Offering Letters — each both
 * exportable and importable, with the same rules as the forms.
 */
class RecruitmentDatasets
{
    private static function yn(bool $v): string
    {
        return $v ? 'Yes' : 'No';
    }

    private static function search(Request $request): string
    {
        return trim((string) $request->query('search', $request->query('q', '')));
    }

    private static function like($query, string $term, array $columns): void
    {
        if ($term === '') {
            return;
        }

        $query->where(function ($q) use ($term, $columns) {
            foreach ($columns as $column) {
                $q->orWhere($column, 'like', '%' . $term . '%');
            }
        });
    }

    private static function options(string $type): array
    {
        return RecruitmentOption::ofType($type)->orderBy('name')->pluck('name', 'id')->all();
    }

    // ── Candidates ──────────────────────────────────────────────────────────

    public static function candidates(): Dataset
    {
        $positions = fn () => DB::table('positions')->where('is_active', 1)->orderBy('name')->pluck('name', 'id')->all();
        $jobs      = fn () => JobOpening::query()->orderByDesc('id')->pluck('request_number', 'id')->all();
        $platforms = fn () => self::options(RecruitmentOption::TYPE_PLATFORM);

        return new Dataset(
            'recruitment.candidates', 'Candidates', 'general.recruitment.candidates',
            [
                ['key' => 'name', 'label' => 'Name', 'required' => true, 'get' => fn ($r) => $r->name,
                 'parse' => fn ($v) => Cell::text($v), 'rules' => ['required', 'string', 'max:150'], 'example' => 'Budi Santoso'],
                ['key' => 'email', 'label' => 'Email', 'get' => fn ($r) => $r->email,
                 'parse' => fn ($v) => ($t = Cell::text($v)) === null ? null : strtolower($t),
                 'rules' => ['nullable', 'email', 'max:150'],
                 'hint' => 'Used to recognise an existing candidate. Without an email, name + position is used.', 'example' => 'budi@example.com'],
                ['key' => 'phone', 'label' => 'Phone', 'get' => fn ($r) => $r->phone,
                 'parse' => fn ($v) => Cell::text($v), 'rules' => ['nullable', 'string', 'max:30'], 'example' => '081234567890'],
                ['key' => 'position_id', 'label' => 'Position', 'required' => true, 'get' => fn ($r) => $r->position_name,
                 'parse' => fn ($v) => Cell::choice($v, $positions(), 'position'), 'rules' => ['required', 'integer'],
                 'hint' => 'A position name from the master list.', 'example' => array_values($positions())[0] ?? ''],
                ['key' => 'job_opening_id', 'label' => 'Job Opening', 'get' => fn ($r) => $r->job_request_number,
                 'parse' => fn ($v) => Cell::choice($v, $jobs(), 'job opening'), 'rules' => ['nullable', 'integer'],
                 'hint' => 'Request No. of a job opening, e.g. REQ-202610-0001. Optional.', 'example' => ''],
                ['key' => 'source_id', 'label' => 'Source', 'get' => fn ($r) => $r->source_name ?? ($r->source_detail ? 'Other' : ''),
                 'parse' => fn ($v) => Cell::choice($v, ['other' => 'Other'] + $platforms(), 'source'), 'rules' => ['nullable'],
                 'hint' => 'One of: ' . implode(', ', $platforms()) . ', Other.', 'example' => array_values($platforms())[0] ?? ''],
                ['key' => 'source_detail', 'label' => 'Source Detail', 'get' => fn ($r) => $r->source_detail,
                 'parse' => fn ($v) => Cell::text($v), 'rules' => ['nullable', 'string', 'max:150'],
                 'hint' => 'Required when Source is Other.', 'example' => ''],
                ['key' => 'status', 'label' => 'Status', 'get' => fn ($r) => Candidate::STATUSES[$r->status] ?? $r->status,
                 'parse' => fn ($v) => Cell::choice($v, Candidate::STATUSES, 'status') ?? Candidate::STATUS_HR_INTERVIEW,
                 'rules' => ['required', Rule::in(array_keys(Candidate::STATUSES))],
                 'hint' => 'One of: ' . implode(', ', array_intersect_key(Candidate::STATUSES, array_flip(Candidate::MANUAL_STATUSES))) . '. Blank = HR Interview. "Hired" is set by the Offer workflow and cannot be chosen here.',
                 'example' => 'HR Interview'],
                ['key' => 'notes', 'label' => 'Notes', 'get' => fn ($r) => $r->notes,
                 'parse' => fn ($v) => Cell::text($v), 'rules' => ['nullable', 'string'], 'example' => ''],
            ],
            function (Request $request) {
                return Candidate::query()
                    ->leftJoin('positions as p', 'p.id', '=', 'recruitment_candidates.position_id')
                    ->leftJoin('recruitment_job_openings as j', 'j.id', '=', 'recruitment_candidates.job_opening_id')
                    ->leftJoin('recruitment_options as o', 'o.id', '=', 'recruitment_candidates.source_id')
                    ->select('recruitment_candidates.*', 'p.name as position_name', 'j.request_number as job_request_number', 'o.name as source_name')
                    ->tap(fn ($q) => self::like($q, self::search($request), [
                        'recruitment_candidates.name', 'recruitment_candidates.email', 'recruitment_candidates.phone', 'p.name',
                    ]))
                    ->when($request->query('status'), fn ($q, $s) => $q->where('recruitment_candidates.status', $s))
                    ->orderByDesc('recruitment_candidates.id')->get();
            },
            [
                'permission' => ['general.recruitment.candidates', 'create'],
                'key'        => 'email',
                'check'      => function (array $v) {
                    if (($v['source_id'] ?? null) === 'other' && empty($v['source_detail'])) {
                        return 'Source Detail is required when Source is Other.';
                    }

                    if ($v['status'] === Candidate::STATUS_HIRED) {
                        $existing = self::findCandidate($v);

                        if (!$existing || !$existing->isHired()) {
                            return 'Status "Hired" is set by the Offer workflow and cannot be chosen in an import.';
                        }
                    }

                    return null;
                },
                'find'       => fn (array $v) => self::findCandidate($v),
                'save'       => function (array $v, ?Candidate $existing, int $actor) {
                    $other = ($v['source_id'] ?? null) === 'other';

                    $data = [
                        'name' => $v['name'], 'email' => $v['email'], 'phone' => $v['phone'],
                        'position_id' => (int) $v['position_id'],
                        'job_opening_id' => $v['job_opening_id'] ?: null,
                        'source_id' => $other ? null : ($v['source_id'] ?: null),
                        'source_detail' => $other ? $v['source_detail'] : null,
                        'notes' => $v['notes'],
                    ];

                    if ($existing) {
                        // A hired candidate's status belongs to the Offer workflow.
                        if (!$existing->isHired() && $existing->status !== $v['status']) {
                            $existing->recordStatus($existing->status, $v['status']);
                            $data['status'] = $v['status'];
                        }

                        return tap($existing)->update($data);
                    }

                    $candidate = Candidate::create($data + ['status' => $v['status']]);
                    $candidate->recordStatus(null, $candidate->status);

                    return $candidate;
                },
            ]
        );
    }

    private static function findCandidate(array $v): ?Candidate
    {
        return !empty($v['email'])
            ? Candidate::where('email', $v['email'])->first()
            : Candidate::whereNull('email')->where('name', $v['name'])->where('position_id', $v['position_id'])->first();
    }

    // ── Job openings ────────────────────────────────────────────────────────

    public static function jobs(): Dataset
    {
        $platforms = fn () => self::options(RecruitmentOption::TYPE_PLATFORM);
        $types     = fn () => self::options(RecruitmentOption::TYPE_EMPLOYMENT_TYPE);
        $depts     = fn () => DB::table('departments')->where('is_active', 1)->orderBy('name')->pluck('name', 'id')->all();
        $positions = fn () => DB::table('positions')->where('is_active', 1)->orderBy('name')->pluck('name', 'id')->all();

        return new Dataset(
            'recruitment.jobs', 'Job Openings', 'general.recruitment.jobs',
            [
                ['key' => 'request_number', 'label' => 'Request No.', 'get' => fn ($r) => $r->request_number,
                 'parse' => fn ($v) => Cell::text($v), 'rules' => ['nullable', 'string', 'max:30'],
                 'hint' => 'Leave blank to generate one (REQ-YYYYMM-0001). An existing number is updated.', 'example' => ''],
                ['key' => 'position_title', 'label' => 'Position Title', 'required' => true, 'get' => fn ($r) => $r->position_title,
                 'parse' => fn ($v) => Cell::text($v), 'rules' => ['required', 'string', 'max:150'], 'example' => 'Backend Developer'],
                ['key' => 'platform_id', 'label' => 'Platform', 'required' => true, 'get' => fn ($r) => $r->platform_name,
                 'parse' => fn ($v) => Cell::choice($v, $platforms(), 'platform'), 'rules' => ['required', 'integer'],
                 'hint' => 'One of: ' . implode(', ', $platforms()), 'example' => array_values($platforms())[0] ?? ''],
                ['key' => 'employment_type_id', 'label' => 'Employment Type', 'required' => true, 'get' => fn ($r) => $r->employment_type_name,
                 'parse' => fn ($v) => Cell::choice($v, $types(), 'employment type'), 'rules' => ['required', 'integer'],
                 'hint' => 'One of: ' . implode(', ', $types()), 'example' => array_values($types())[0] ?? ''],
                ['key' => 'opens_at', 'label' => 'Opens At', 'required' => true, 'get' => fn ($r) => substr((string) $r->opens_at, 0, 10),
                 'parse' => fn ($v) => Cell::date($v), 'rules' => ['required', 'date'], 'hint' => 'YYYY-MM-DD.', 'example' => now()->toDateString()],
                ['key' => 'closes_at', 'label' => 'Closes At', 'get' => fn ($r) => $r->closes_at ? substr((string) $r->closes_at, 0, 10) : '',
                 'parse' => fn ($v) => Cell::date($v), 'rules' => ['nullable', 'date'],
                 'hint' => 'YYYY-MM-DD, after Opens At. Blank = open-ended.', 'example' => ''],
                ['key' => 'location', 'label' => 'Location', 'get' => fn ($r) => $r->location,
                 'parse' => fn ($v) => Cell::text($v), 'rules' => ['nullable', 'string', Rule::in(RecruitmentFormOptions::cities())],
                 'hint' => 'A city name from the location list.', 'example' => ''],
                ['key' => 'department_id', 'label' => 'Department', 'get' => fn ($r) => $r->department_name,
                 'parse' => fn ($v) => Cell::choice($v, $depts(), 'department'), 'rules' => ['nullable', 'integer'],
                 'hint' => 'One of: ' . implode(', ', $depts()) . '. Optional.', 'example' => ''],
                ['key' => 'position_id', 'label' => 'Position', 'get' => fn ($r) => $r->position_name,
                 'parse' => fn ($v) => Cell::choice($v, $positions(), 'position'), 'rules' => ['nullable', 'integer'],
                 'hint' => 'A position name from the master list. Optional.', 'example' => ''],
                ['key' => 'quota', 'label' => 'Quota', 'required' => true, 'format' => 'int', 'get' => fn ($r) => (int) $r->quota,
                 'parse' => fn ($v) => Cell::number($v), 'rules' => ['required', 'integer', 'min:1', 'max:65000'], 'example' => 2],
                ['key' => 'salary_min', 'label' => 'Salary Min', 'format' => 'money', 'get' => fn ($r) => $r->salary_min !== null ? (float) $r->salary_min : null,
                 'parse' => fn ($v) => Cell::number($v), 'rules' => ['nullable', 'numeric', 'min:0'], 'example' => ''],
                ['key' => 'salary_max', 'label' => 'Salary Max', 'format' => 'money', 'get' => fn ($r) => $r->salary_max !== null ? (float) $r->salary_max : null,
                 'parse' => fn ($v) => Cell::number($v), 'rules' => ['nullable', 'numeric', 'min:0'],
                 'hint' => 'Not lower than Salary Min.', 'example' => ''],
                ['key' => 'recruiter_eci', 'label' => 'Recruiter ECI', 'get' => fn ($r) => $r->recruiter_eci ?? '',
                 'parse' => fn ($v) => Cell::text($v), 'rules' => ['nullable', 'string', 'max:50'],
                 'hint' => 'ECI of the recruiter. Optional.', 'example' => ''],
                ['key' => 'posting_url', 'label' => 'Posting URL', 'get' => fn ($r) => $r->posting_url,
                 'parse' => fn ($v) => Cell::text($v), 'rules' => ['nullable', 'url', 'max:500'], 'example' => ''],
                ['key' => 'description', 'label' => 'Description', 'get' => fn ($r) => $r->description,
                 'parse' => fn ($v) => Cell::text($v), 'rules' => ['nullable', 'string'], 'example' => ''],
                ['key' => 'requirements', 'label' => 'Requirements', 'get' => fn ($r) => $r->requirements,
                 'parse' => fn ($v) => Cell::text($v), 'rules' => ['nullable', 'string'], 'example' => ''],
                ['key' => 'publish_to_website', 'label' => 'Publish To Website', 'get' => fn ($r) => self::yn((bool) $r->publish_to_website),
                 'parse' => fn ($v) => Cell::yesNo($v), 'rules' => ['boolean'], 'hint' => 'Yes/No. Blank = No.', 'example' => 'No'],
            ],
            function (Request $request) {
                return JobOpening::query()
                    ->leftJoin('recruitment_options as pf', 'pf.id', '=', 'recruitment_job_openings.platform_id')
                    ->leftJoin('recruitment_options as et', 'et.id', '=', 'recruitment_job_openings.employment_type_id')
                    ->leftJoin('departments as d', 'd.id', '=', 'recruitment_job_openings.department_id')
                    ->leftJoin('positions as p', 'p.id', '=', 'recruitment_job_openings.position_id')
                    ->leftJoin('employee as e', 'e.employee_id', '=', 'recruitment_job_openings.recruiter_employee_id')
                    ->select('recruitment_job_openings.*', 'pf.name as platform_name', 'et.name as employment_type_name',
                        'd.name as department_name', 'p.name as position_name', 'e.eci as recruiter_eci')
                    ->tap(fn ($q) => self::like($q, self::search($request), [
                        'recruitment_job_openings.position_title', 'recruitment_job_openings.request_number', 'recruitment_job_openings.location',
                    ]))
                    ->orderByDesc('recruitment_job_openings.id')->get();
            },
            [
                'permission' => ['general.recruitment.jobs', 'create'],
                'key'        => 'request_number',
                'check'      => function (array $v) {
                    if ($v['closes_at'] && $v['closes_at'] <= $v['opens_at']) {
                        return 'Closes At must be after Opens At.';
                    }

                    if ($v['salary_min'] !== null && $v['salary_max'] !== null && $v['salary_max'] < $v['salary_min']) {
                        return 'Salary Max must not be lower than Salary Min.';
                    }

                    if (!empty($v['recruiter_eci']) && !Employee::where('eci', $v['recruiter_eci'])->exists()) {
                        return "Recruiter ECI \"{$v['recruiter_eci']}\" does not match any employee.";
                    }

                    return null;
                },
                'find'       => fn (array $v) => !empty($v['request_number']) ? JobOpening::where('request_number', $v['request_number'])->first() : null,
                'save'       => function (array $v, ?JobOpening $existing, int $actor) {
                    $publish = (bool) $v['publish_to_website'];

                    $data = [
                        'position_title' => $v['position_title'], 'platform_id' => (int) $v['platform_id'],
                        'employment_type_id' => (int) $v['employment_type_id'],
                        'opens_at' => $v['opens_at'], 'closes_at' => $v['closes_at'],
                        'location' => $v['location'], 'department_id' => $v['department_id'] ?: null,
                        'position_id' => $v['position_id'] ?: null, 'quota' => (int) $v['quota'],
                        'salary_min' => $v['salary_min'], 'salary_max' => $v['salary_max'],
                        'recruiter_employee_id' => !empty($v['recruiter_eci'])
                            ? Employee::where('eci', $v['recruiter_eci'])->value('employee_id') : null,
                        'posting_url' => $v['posting_url'], 'description' => $v['description'], 'requirements' => $v['requirements'],
                        'publish_to_website' => $publish,
                        'website_published_at' => $publish ? ($existing?->website_published_at ?? now()) : null,
                    ];

                    if ($existing) {
                        return tap($existing)->update($data);
                    }

                    $prefix = 'REQ-' . now()->format('Ym') . '-';
                    $number = $v['request_number'];

                    if (!$number) {
                        $last = JobOpening::where('request_number', 'like', "{$prefix}%")->orderByDesc('request_number')->value('request_number');
                        $number = $prefix . str_pad((string) ($last ? ((int) substr($last, -4)) + 1 : 1), 4, '0', STR_PAD_LEFT);
                    }

                    return JobOpening::create($data + ['request_number' => $number, 'created_by_employee_id' => $actor]);
                },
            ]
        );
    }

    // ── Offering letters ────────────────────────────────────────────────────

    public static function offers(): Dataset
    {
        $components = OfferComponent::query()->where('is_active', true)->ordered()->get();
        $languages  = LetterTypeSetting::LANGUAGES;

        $componentColumns = $components->map(fn (OfferComponent $c) => [
            'key'      => 'comp_' . $c->id,
            'label'    => $c->name,
            'required' => $c->isBase(),
            'format'   => 'money',
            'get'      => fn ($r) => (float) ($r->lines()->firstWhere('component_id', $c->id)['amount'] ?? 0),
            'parse'    => fn ($v) => Cell::number($v) ?? 0,
            'rules'    => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'hint'     => ($c->isBase() ? 'Base salary — required, must be above 0. ' : 'Optional. ') . 'Amount in Rp, 0 or blank = not offered.',
            'example'  => $c->isBase() ? 8000000 : '',
        ])->all();

        $columns = [
            ['key' => 'letter_number', 'label' => 'Letter Number', 'get' => fn ($r) => $r->letter_number,
             'parse' => fn ($v) => Cell::text($v), 'rules' => ['nullable', 'string', 'max:60'],
             'hint' => 'Leave blank to take the next number. An existing number updates that letter (pending letters only).', 'example' => ''],
            ['key' => 'language', 'label' => 'Language', 'required' => true, 'get' => fn ($r) => $languages[$r->language] ?? $r->language,
             'parse' => fn ($v) => Cell::choice($v, $languages, 'language') ?? 'id', 'rules' => ['required', Rule::in(array_keys($languages))],
             'hint' => 'Bahasa Indonesia or English (or id / en). Blank = Bahasa Indonesia.', 'example' => 'Bahasa Indonesia'],
            ['key' => 'offer_date', 'label' => 'Offer Date', 'required' => true, 'get' => fn ($r) => $r->offer_date?->toDateString(),
             'parse' => fn ($v) => Cell::date($v), 'rules' => ['required', 'date'], 'hint' => 'YYYY-MM-DD.', 'example' => now()->toDateString()],
            ['key' => 'candidate_name', 'label' => 'Candidate Name', 'required' => true, 'get' => fn ($r) => $r->candidate_name,
             'parse' => fn ($v) => Cell::text($v), 'rules' => ['required', 'string', 'max:150'], 'example' => 'Budi Santoso'],
            ['key' => 'candidate_email', 'label' => 'Candidate Email', 'get' => fn ($r) => $r->candidate_email,
             'parse' => fn ($v) => Cell::text($v), 'rules' => ['nullable', 'email', 'max:150'],
             'hint' => 'When it matches a recruitment candidate, the letter is linked to them.', 'example' => 'budi@example.com'],
            ['key' => 'candidate_phone', 'label' => 'Candidate Phone', 'get' => fn ($r) => $r->candidate_phone,
             'parse' => fn ($v) => Cell::text($v), 'rules' => ['nullable', 'string', 'max:30'], 'example' => ''],
            ['key' => 'position_title', 'label' => 'Position', 'required' => true, 'get' => fn ($r) => $r->position_title,
             'parse' => fn ($v) => Cell::text($v), 'rules' => ['required', 'string', 'max:150'], 'example' => 'Backend Developer'],
            ['key' => 'joining_date', 'label' => 'Joining Date', 'required' => true, 'get' => fn ($r) => $r->joining_date?->toDateString(),
             'parse' => fn ($v) => Cell::date($v), 'rules' => ['required', 'date'], 'hint' => 'YYYY-MM-DD.', 'example' => now()->addMonth()->toDateString()],
            ['key' => 'has_probation', 'label' => 'Probation', 'get' => fn ($r) => self::yn((bool) $r->has_probation),
             'parse' => fn ($v) => Cell::yesNo($v), 'rules' => ['boolean'], 'hint' => 'Yes/No. Blank = No.', 'example' => 'Yes'],
            ['key' => 'salary_type', 'label' => 'Salary Type', 'get' => fn ($r) => Offer::SALARY_TYPES[$r->salary_type] ?? $r->salary_type,
             'parse' => fn ($v) => Cell::choice($v, Offer::SALARY_TYPES, 'salary type') ?? 'gross',
             'rules' => ['required', Rule::in(array_keys(Offer::SALARY_TYPES))], 'hint' => 'Gross or Nett. Blank = Gross.', 'example' => 'Gross'],
            ...$componentColumns,
            ['key' => 'signatory_eci', 'label' => 'Signatory ECI', 'get' => fn ($r) => $r->signatory_eci ?? '',
             'parse' => fn ($v) => Cell::text($v), 'rules' => ['nullable', 'string', 'max:50'],
             'hint' => 'ECI of the employee who signs — needed to sign with their master-data signature. If blank, give a Signatory Name.', 'example' => ''],
            ['key' => 'signatory_name', 'label' => 'Signatory Name', 'get' => fn ($r) => $r->signatory_name,
             'parse' => fn ($v) => Cell::text($v), 'rules' => ['nullable', 'string', 'max:150'],
             'hint' => 'Filled from the ECI when that is given.', 'example' => 'Jane Doe'],
            ['key' => 'signatory_title', 'label' => 'Signatory Title', 'required' => true, 'get' => fn ($r) => $r->signatory_title,
             'parse' => fn ($v) => Cell::text($v), 'rules' => ['required', 'string', 'max:150'], 'example' => 'HR Manager'],
            ['key' => 'job_description', 'label' => 'Job Description', 'get' => fn ($r) => $r->job_description,
             'parse' => fn ($v) => Cell::text($v), 'rules' => ['nullable', 'string', 'max:5000'], 'example' => ''],
            ['key' => 'benefits', 'label' => 'Benefits', 'get' => fn ($r) => $r->benefits,
             'parse' => fn ($v) => Cell::text($v), 'rules' => ['nullable', 'string', 'max:5000'], 'example' => ''],
            ['key' => 'notes', 'label' => 'Notes', 'get' => fn ($r) => $r->notes,
             'parse' => fn ($v) => Cell::text($v), 'rules' => ['nullable', 'string', 'max:5000'], 'example' => ''],
        ];

        return new Dataset(
            'recruitment.offers', 'Offering Letters', 'general.recruitment.offers',
            $columns,
            function (Request $request) {
                return Offer::query()
                    ->leftJoin('employee as e', 'e.employee_id', '=', 'recruitment_offers.signatory_employee_id')
                    ->select('recruitment_offers.*', 'e.eci as signatory_eci')
                    ->tap(fn ($q) => self::like($q, self::search($request), [
                        'recruitment_offers.letter_number', 'recruitment_offers.candidate_name', 'recruitment_offers.position_title',
                    ]))
                    ->when($request->query('decision'), fn ($q, $d) => $q->where('recruitment_offers.decision', $d))
                    ->orderByDesc('recruitment_offers.offer_date')->orderByDesc('recruitment_offers.id')->get();
            },
            [
                'permission' => ['general.recruitment.offers', 'create'],
                'key'        => 'letter_number',
                'check'      => function (array $v) use ($components) {
                    $base = $components->firstWhere('kind', OfferComponent::KIND_BASE);

                    if (!$base || (float) ($v['comp_' . $base->id] ?? 0) <= 0) {
                        return 'Enter the base salary.';
                    }

                    if (empty($v['signatory_eci']) && empty($v['signatory_name'])) {
                        return 'Give the Signatory ECI or the Signatory Name.';
                    }

                    if (!empty($v['signatory_eci']) && !Employee::where('eci', $v['signatory_eci'])->exists()) {
                        return "Signatory ECI \"{$v['signatory_eci']}\" does not match any employee.";
                    }

                    return null;
                },
                'find'       => fn (array $v) => !empty($v['letter_number']) ? Offer::where('letter_number', $v['letter_number'])->first() : null,
                'save'       => function (array $v, ?Offer $existing, int $actor) use ($components) {
                    $signatory = !empty($v['signatory_eci'])
                        ? Employee::with('basicData')->where('eci', $v['signatory_eci'])->first()
                        : null;

                    $lines = $components->map(fn (OfferComponent $c) => [
                        'component_id' => $c->id, 'name' => $c->name, 'name_en' => $c->name_en,
                        'kind' => $c->kind, 'amount' => (float) ($v['comp_' . $c->id] ?? 0),
                    ])->filter(fn ($l) => $l['amount'] > 0)->values();

                    $candidateId = !empty($v['candidate_email'])
                        ? Candidate::where('email', strtolower($v['candidate_email']))->value('id')
                        : null;

                    $data = [
                        'language' => $v['language'], 'offer_date' => $v['offer_date'],
                        'candidate_id' => $candidateId, 'candidate_name' => $v['candidate_name'],
                        'candidate_email' => $v['candidate_email'], 'candidate_phone' => $v['candidate_phone'],
                        'position_title' => $v['position_title'], 'job_description' => $v['job_description'],
                        'benefits' => $v['benefits'], 'joining_date' => $v['joining_date'],
                        'has_probation' => (bool) $v['has_probation'], 'salary_type' => $v['salary_type'],
                        'compensation' => $lines->all(), 'total_compensation' => $lines->sum('amount'),
                        'notes' => $v['notes'],
                        'signatory_name' => $signatory ? ($signatory->basicData?->full_name ?? $signatory->eci) : $v['signatory_name'],
                        'signatory_title' => $v['signatory_title'], 'signatory_employee_id' => $signatory?->employee_id,
                    ];

                    if ($existing) {
                        // Accepted / rejected letters can no longer change: left as they are.
                        if (!$existing->isPending()) {
                            return false;
                        }

                        $existing->fill($data);
                        $changed = $existing->isDirty();
                        $existing->save();

                        // What was signed is no longer what the letter says.
                        if ($existing->isSigned() && $changed) {
                            $existing->removeSignature();
                        }

                        return $existing;
                    }

                    $date    = \Carbon\Carbon::parse($v['offer_date']);
                    $numbers = app(LetterNumberService::class);
                    $number  = $v['letter_number'] ?? null;
                    $sequence = Offer::nextSequence($date);

                    if (!$number) {
                        do {
                            $sequence = $numbers->take(LetterNumberService::OUTGOING, $date->year);
                            $number   = Offer::numberFor($date, $sequence, $v['language']);
                        } while ($numbers->outgoingNumberUsed($number));
                    }

                    return Offer::create($data + [
                        'letter_number' => $number, 'number_sequence' => $sequence,
                        'decision' => Offer::DECISION_PENDING, 'created_by_employee_id' => $actor,
                    ]);
                },
            ]
        );
    }
}

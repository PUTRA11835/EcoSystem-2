<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Http\Controllers\HR_General\Concerns\HandlesRecruitmentTables;
use App\Models\Recruitment\JobOpening;
use App\Models\Recruitment\RecruitmentOption;
use App\Services\Recruitment\RecruitmentFormOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RecruitmentJobController extends Controller
{
    use HandlesRecruitmentTables;

    public function index(Request $request)
    {
        $filters = [
            'title'              => trim((string) $request->query('title')),
            'location'           => trim((string) $request->query('location')),
            'platform_id'        => $request->query('platform_id'),
            'employment_type_id' => $request->query('employment_type_id'),
            'department_id'      => $request->query('department_id'),
            'status'             => $request->query('status'),
        ];

        $jobs = JobOpening::with(['platform', 'employmentType', 'department', 'position', 'recruiter.basicData'])
            ->withCount('candidates')
            ->when($filters['title'] !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('position_title', 'like', "%{$filters['title']}%")
                ->orWhere('request_number', 'like', "%{$filters['title']}%")))
            ->when($filters['location'] !== '', fn ($q) => $q->where('location', 'like', "%{$filters['location']}%"))
            ->when($filters['platform_id'], fn ($q, $id) => $q->where('platform_id', $id))
            ->when($filters['employment_type_id'], fn ($q, $id) => $q->where('employment_type_id', $id))
            ->when($filters['department_id'], fn ($q, $id) => $q->where('department_id', $id))
            ->when(isset(JobOpening::STATUSES[$filters['status']]), fn ($q) => $q->withStatus($filters['status']))
            ->orderByDesc('opens_at')
            ->orderByDesc('id')
            ->paginate($perPage = $this->perPage($request))
            ->withQueryString();

        return view('hr-general.recruitment.jobs', [
            'jobs'            => $jobs,
            'filters'         => $filters,
            'hasFilters'      => collect($filters)->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty(),
            'perPage'         => $perPage,
            'perPageOptions'  => self::PER_PAGE_OPTIONS,
            'statuses'        => JobOpening::STATUSES,
            'platforms'       => RecruitmentOption::ofType(RecruitmentOption::TYPE_PLATFORM)->ordered()->get(),
            'employmentTypes' => RecruitmentOption::ofType(RecruitmentOption::TYPE_EMPLOYMENT_TYPE)->ordered()->get(),
            'departments'     => RecruitmentFormOptions::departments(),
        ]);
    }

    public function create()
    {
        return view('hr-general.recruitment.job-form', $this->formData(new JobOpening([
            'quota'    => 1,
            'opens_at' => now()->startOfMinute(),
        ])));
    }

    /** Also the read-only view of an opening, for roles without the Edit capability. */
    public function edit(JobOpening $job)
    {
        return view('hr-general.recruitment.job-form', $this->formData($job->load('requestedDocuments')));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data) {
            $job = JobOpening::create([
                ...$this->attributes($data),
                'request_number'         => $this->nextRequestNumber(),
                'created_by_employee_id' => session('user.id'),
            ]);

            $job->requestedDocuments()->sync($this->documentSync($data));
        });

        return redirect()->route('general.recruitment.jobs.index')->with('success', 'Job opening created.');
    }

    public function update(Request $request, JobOpening $job)
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($job, $data) {
            $job->update($this->attributes($data, $job));
            $job->requestedDocuments()->sync($this->documentSync($data));
        });

        return redirect()->route('general.recruitment.jobs.index')->with('success', 'Job opening updated.');
    }

    /** Closes a posting ahead of its deadline — the status follows from the closing time. */
    public function close(JobOpening $job)
    {
        $job->update(['closes_at' => now()]);

        return back()->with('success', 'Job opening closed.');
    }

    public function destroy(JobOpening $job)
    {
        // Candidates are kept; the database clears their link to this opening.
        $job->delete();

        return back()->with('success', 'Job opening deleted.');
    }

    /** Dropdowns of the form; a deactivated option is still offered on the opening that already uses it. */
    private function formData(JobOpening $job): array
    {
        return [
            'job'             => $job,
            'platforms'       => RecruitmentOption::choices(RecruitmentOption::TYPE_PLATFORM, $job->platform_id),
            'employmentTypes' => RecruitmentOption::choices(RecruitmentOption::TYPE_EMPLOYMENT_TYPE, $job->employment_type_id),
            'documentTypes'   => RecruitmentOption::ofType(RecruitmentOption::TYPE_DOCUMENT_TYPE)->ordered()->get(),
            'departments'     => RecruitmentFormOptions::departments(),
            'positions'       => RecruitmentFormOptions::positions(),
            'cities'          => RecruitmentFormOptions::cities(),
            'recruiters'      => RecruitmentFormOptions::employees(),
        ];
    }

    private function validated(Request $request): array
    {
        $option = fn (string $type) => Rule::exists('recruitment_options', 'id')->where('type', $type);

        return $request->validate([
            'position_title'        => 'required|string|max:150',
            'platform_id'           => ['required', $option(RecruitmentOption::TYPE_PLATFORM)],
            'posting_url'           => 'nullable|url|max:500',
            'opens_at'              => 'required|date',
            'closes_at'             => 'nullable|date|after:opens_at',
            'employment_type_id'    => ['required', $option(RecruitmentOption::TYPE_EMPLOYMENT_TYPE)],
            'location'              => ['nullable', 'string', Rule::in(RecruitmentFormOptions::cities())],
            'department_id'         => 'nullable|exists:departments,id',
            'position_id'           => 'nullable|exists:positions,id',
            'description'           => 'nullable|string',
            'requirements'          => 'nullable|string',
            // documents[{document type id}] = required | optional | none
            'documents'             => 'nullable|array',
            'documents.*'           => ['required', Rule::in(array_keys(RecruitmentOption::REQUIREMENTS))],
            'quota'                 => 'required|integer|min:1|max:65000',
            'salary_min'            => 'nullable|numeric|min:0',
            'salary_max'            => 'nullable|numeric|min:0|gte:salary_min',
            'recruiter_employee_id' => 'nullable|exists:employee,employee_id',
            'publish_to_website'    => 'nullable|boolean',
        ], [
            'closes_at.after'  => 'The closing date must be after the opening date.',
            'salary_max.gte'   => 'The maximum salary must not be lower than the minimum salary.',
        ]);
    }

    private function attributes(array $data, ?JobOpening $job = null): array
    {
        $publish = (bool) ($data['publish_to_website'] ?? false);

        return [
            ...collect($data)->except(['documents', 'publish_to_website'])->all(),
            'publish_to_website'   => $publish,
            // Kept from the first time the flag was switched on; cleared when switched off.
            'website_published_at' => $publish ? ($job?->website_published_at ?? now()) : null,
        ];
    }

    /** The requested documents in the shape sync() takes: [type id => ['is_required' => bool]]. */
    private function documentSync(array $data): array
    {
        $typeIds = RecruitmentOption::ofType(RecruitmentOption::TYPE_DOCUMENT_TYPE)->pluck('id')->flip();

        return collect($data['documents'] ?? [])
            ->filter(fn (string $level, $typeId) => $level !== 'none' && $typeIds->has($typeId))
            ->map(fn (string $level) => ['is_required' => $level === 'required'])
            ->all();
    }

    private function nextRequestNumber(): string
    {
        $prefix = 'REQ-' . now()->format('Ym') . '-';
        $last = JobOpening::where('request_number', 'like', "{$prefix}%")
            ->orderByDesc('request_number')
            ->value('request_number');

        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}

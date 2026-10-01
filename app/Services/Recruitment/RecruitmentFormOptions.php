<?php

namespace App\Services\Recruitment;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Recruitment\Candidate;
use App\Models\Recruitment\Interview;
use App\Models\Recruitment\JobOpening;
use App\Models\Recruitment\RecruitmentOption;
use App\Models\Wilayah;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * The dropdown contents of the Recruitment forms, in one place so the
 * "add candidate / schedule interview" modal offers the same choices on
 * every page that embeds it.
 */
class RecruitmentFormOptions
{
    /** Everything the shared candidate + interview modal needs. */
    public static function forCandidateModal(): array
    {
        return [
            'positions'       => self::positions(),
            'openJobs'        => JobOpening::open()->with('requestedDocuments')->orderBy('position_title')->get(),
            'sources'         => RecruitmentOption::choices(RecruitmentOption::TYPE_PLATFORM),
            'documentTypes'   => RecruitmentOption::choices(RecruitmentOption::TYPE_DOCUMENT_TYPE),
            'documentRules'   => self::documentRules(),
            'interviewers'    => self::interviewers(),
            'stages'          => Interview::STAGES,
            'manualStatuses'  => collect(Candidate::STATUSES)->only(Candidate::MANUAL_STATUSES)->all(),
            // Whether an outside calendar sends the invitations and generates the meeting link.
            'calendarExternal' => app(CalendarProviders::class)->external() !== null,
            'activeCandidates' => $activeCandidates = Candidate::with(['position', 'jobOpening', 'source', 'interviews'])
                ->withCount('documents')->active()->orderBy('name')->get(),
            'candidateSummaries' => $activeCandidates->mapWithKeys(fn (Candidate $candidate) => [$candidate->id => self::summary($candidate)]),
        ];
    }

    /**
     * What is already on file for a candidate — shown in the schedule form as
     * soon as the candidate is picked, so nothing has to be looked up or typed
     * again. `stage` is the interview stage that matches their current status.
     */
    private static function summary(Candidate $candidate): array
    {
        $last = $candidate->interviews->sortByDesc('scheduled_at')->first();
        $last?->setRelation('candidate', $candidate);

        return [
            'position'   => $candidate->positionLabel(),
            'jobOpening' => $candidate->jobOpening?->position_title,
            'source'     => $candidate->source?->name,
            'email'      => $candidate->email,
            'phone'      => $candidate->phone,
            'status'     => $candidate->statusLabel(),
            'documents'  => $candidate->documents_count,
            'notes'      => $candidate->notes,
            'stage'      => isset(Interview::STAGES[$candidate->status]) ? $candidate->status : null,
            'lastInterview' => $last
                ? "{$last->displayTitle()} · {$last->scheduled_at->format('d M Y, H:i')} · {$last->statusLabel()}"
                : null,
            'url'        => route('general.recruitment.candidates.show', $candidate),
        ];
    }

    /**
     * The rules of every document type (file / link, accepted formats), keyed
     * by id — the browser applies them to a document row as its type is picked;
     * the controller enforces the same rules on save.
     */
    public static function documentRules(): Collection
    {
        return RecruitmentOption::ofType(RecruitmentOption::TYPE_DOCUMENT_TYPE)->get()
            ->mapWithKeys(fn (RecruitmentOption $type) => [$type->id => $type->rulesForForms()]);
    }

    public static function positions(): Collection
    {
        return Position::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name']);
    }

    public static function departments(): Collection
    {
        return Department::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name']);
    }

    /** Active employees as [employee_id => display name], sorted by name. */
    public static function employees(): Collection
    {
        return Employee::with('basicData')
            ->where('is_active', true)
            ->get()
            ->mapWithKeys(fn (Employee $e) => [$e->employee_id => $e->basicData->full_name ?? $e->eci])
            ->sort(SORT_NATURAL | SORT_FLAG_CASE);
    }

    /**
     * Employees for the interviewer picker, each flagged with whether an
     * email address is on file — without one they cannot be sent the invitation.
     *
     * @return Collection<int, array{id: int, name: string, has_email: bool}>
     */
    public static function interviewers(): Collection
    {
        $emails = MicrosoftGraphCalendarService::attendeeEmails();

        return self::employees()->map(fn (string $name, int $id) => [
            'id'        => $id,
            'name'      => $name,
            'has_email' => isset($emails[$id]),
        ])->values();
    }

    /**
     * Indonesian cities and regencies for the job location dropdown, from the
     * `wilayah` reference table (kode "PP.KK" = kabupaten/kota level).
     *
     * @return string[]
     */
    public static function cities(): array
    {
        return Cache::remember('recruitment_city_options', now()->addDay(), fn () => Wilayah::query()
            ->where('kode', 'like', '__.__')
            ->orderBy('nama')
            ->pluck('nama')
            ->unique()
            ->values()
            ->all());
    }
}

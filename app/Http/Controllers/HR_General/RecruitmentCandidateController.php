<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Http\Controllers\HR_General\Concerns\HandlesRecruitmentTables;
use App\Models\Recruitment\Candidate;
use App\Models\Recruitment\CandidateDocument;
use App\Models\Recruitment\Interview;
use App\Models\Recruitment\JobOpening;
use App\Models\Recruitment\RecruitmentOption;
use App\Services\Recruitment\InterviewScheduler;
use App\Services\Recruitment\RecruitmentCalendar;
use App\Services\Recruitment\RecruitmentFormOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RecruitmentCandidateController extends Controller
{
    use HandlesRecruitmentTables;

    public function index(Request $request)
    {
        $filters = [
            'name'           => trim((string) $request->query('name')),
            'position_id'    => $request->query('position_id'),
            'job_opening_id' => $request->query('job_opening_id'),
            'source_id'      => $request->query('source_id'),
            'contact'        => trim((string) $request->query('contact')),
            // Ticked document types (ids), or the single value "none".
            'documents'      => array_values(array_filter((array) $request->query('documents', []), 'is_scalar')),
            'status'         => $request->query('status'),
        ];

        $perPage = $this->perPage($request);

        $candidates = Candidate::with(['position', 'jobOpening.requestedDocuments', 'source', 'documents.type'])
            ->when($filters['name'] !== '', fn ($q) => $q->where('name', 'like', "%{$filters['name']}%"))
            ->when($filters['position_id'], fn ($q, $id) => $q->where('position_id', $id))
            ->when($filters['job_opening_id'], fn ($q, $id) => $id === 'none'
                ? $q->whereNull('job_opening_id')
                : $q->where('job_opening_id', $id))
            ->when($filters['source_id'], fn ($q, $id) => $id === 'none'
                ? $q->whereNull('source_id')
                : $q->where('source_id', $id))
            ->when($filters['contact'] !== '', fn ($q) => $q->where(fn ($c) => $c
                ->where('email', 'like', "%{$filters['contact']}%")
                ->orWhere('phone', 'like', "%{$filters['contact']}%")))
            ->when($filters['documents'], function ($q, $documents) {
                if (in_array('none', $documents, true)) {
                    return $q->doesntHave('documents');
                }

                // Every ticked type must be attached.
                foreach ($documents as $typeId) {
                    $q->whereHas('documents', fn ($d) => $d->where('document_type_id', $typeId));
                }
            })
            ->when($filters['status'], fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();

        return view('hr-general.recruitment.candidates', [
            'candidates'     => $candidates,
            'filters'        => $filters,
            'hasFilters'     => collect($filters)->filter(fn ($v) => $v !== null && $v !== '' && $v !== [])->isNotEmpty(),
            'perPage'        => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'statuses'       => Candidate::STATUSES,
            // Filters list every job opening / option, not only the open or active ones.
            'filterJobs'     => JobOpening::orderBy('position_title')->get(['id', 'position_title', 'request_number'])
                ->mapWithKeys(fn ($job) => [$job->id => "{$job->position_title} ({$job->request_number})"])
                ->prepend('No job opening', 'none')->all(),
            'filterSources'  => RecruitmentOption::ofType(RecruitmentOption::TYPE_PLATFORM)->ordered()->pluck('name', 'id')
                ->prepend('No source', 'none')->all(),
            'filterDocTypes' => RecruitmentOption::ofType(RecruitmentOption::TYPE_DOCUMENT_TYPE)->ordered()->pluck('name', 'id')->all(),
            ...RecruitmentFormOptions::forCandidateModal(),
        ]);
    }

    public function store(Request $request, InterviewScheduler $scheduler)
    {
        $withInterview = $request->boolean('schedule_interview');

        // Scheduling is a capability of the Schedule tab, whichever page the form was opened from.
        abort_if($withInterview && !$this->employeeCan('general.recruitment.schedule', 'create'), 403,
            'Your role may add candidates but not schedule interviews.');

        // An interview scheduled together with the candidate decides the starting
        // status, so the form does not send one in that case.
        if ($withInterview) {
            $request->merge(['status' => $request->input('interview.stage')]);
        }

        $data = $request->validate([
            ...$this->candidateRules(),
            ...$this->documentRules(),
            'schedule_interview' => 'nullable|boolean',
            ...($withInterview ? InterviewScheduler::rules('interview.') : []),
        ], [], InterviewScheduler::attributeNames('interview.'));
        $this->assertDocumentRules($request);

        $candidate = DB::transaction(function () use ($request, $data) {
            $candidate = Candidate::create(collect($data)->only([
                'name', 'position_id', 'job_opening_id', 'source_id', 'email', 'phone', 'status', 'notes',
            ])->all());

            $candidate->recordStatus(null, $candidate->status);
            $this->storeDocuments($request, $candidate);

            return $candidate;
        });

        if ($withInterview) {
            $scheduler->schedule($candidate, $data['interview']);
        }

        $message = $withInterview
            ? 'Candidate added and the interview was scheduled.'
            : 'Candidate added.';

        return $this->respond($request, $message, $scheduler->syncWarning() ?? $this->missingDocumentsNote($candidate));
    }

    public function show(Candidate $candidate)
    {
        $candidate->load([
            'position', 'jobOpening.requestedDocuments', 'source', 'documents.type', 'latestOffer',
            'statusHistories.changedBy.basicData', 'interviews.interviewers.basicData',
        ]);

        return view('hr-general.recruitment.candidate-show', [
            'candidate'      => $candidate,
            'timeline'       => $this->timeline($candidate),
            // The schedule / reschedule forms are the ones the calendar uses.
            ...RecruitmentFormOptions::forCandidateModal(),
            'jobs'           => JobOpening::open()
                ->when($candidate->job_opening_id, fn ($q, $id) => $q->orWhere('id', $id))
                ->with('requestedDocuments')->orderBy('position_title')->get(),
            'sources'        => RecruitmentOption::choices(RecruitmentOption::TYPE_PLATFORM, $candidate->source_id),
            'documentTypes'  => RecruitmentOption::choices(RecruitmentOption::TYPE_DOCUMENT_TYPE),
            'manualStatuses' => collect(Candidate::STATUSES)->only(Candidate::MANUAL_STATUSES)->all(),
        ]);
    }

    public function update(Request $request, Candidate $candidate)
    {
        $data = $request->validate([
            ...$this->candidateRules($candidate),
            ...$this->documentRules(),
        ]);
        $this->assertDocumentRules($request);

        $newStatus = $data['status'] ?? $candidate->status;

        $statusChanged = DB::transaction(function () use ($request, $candidate, $data, $newStatus) {
            $candidate->update(collect($data)->only([
                'name', 'position_id', 'job_opening_id', 'source_id', 'email', 'phone', 'notes',
            ])->all());

            $this->storeDocuments($request, $candidate);

            return $candidate->transitionTo($newStatus);
        });

        $redirect = redirect()->route('general.recruitment.candidates.show', $candidate)
            ->with('success', 'Candidate saved.');

        if ($note = $this->missingDocumentsNote($candidate)) {
            $redirect->with('warning', $note);
        }

        if (!$statusChanged) {
            return $redirect;
        }

        if ($newStatus === Candidate::STATUS_OFFER) {
            return $redirect->with('success', 'Candidate saved. The candidate can now be picked when writing an offering letter.');
        }

        if (isset(Interview::STAGES[$newStatus])) {
            return $redirect->with('success', "Candidate saved. Status is now {$candidate->statusLabel()} — schedule the interview to put it on the calendar.");
        }

        return $redirect;
    }

    public function destroy(Candidate $candidate, InterviewScheduler $scheduler)
    {
        // Take still-pending interviews off the Teams calendar before the rows disappear.
        $candidate->interviews()->pending()->whereNotNull('ms_graph_event_id')->get()
            ->each(fn (Interview $interview) => $scheduler->cancel($interview));

        // Deleted one by one so each document's model event removes its file.
        $candidate->documents->each->delete();
        $candidate->delete();

        return redirect()->route('general.recruitment.candidates.index')->with('success', 'Candidate deleted.');
    }

    public function downloadDocument(Candidate $candidate, CandidateDocument $document)
    {
        abort_unless($document->candidate_id === $candidate->id, 404);

        if ($document->isLink()) {
            return redirect()->away($document->url);
        }

        $disk = Storage::disk($document->disk);
        abort_unless($disk->exists($document->path), 404, 'The file is no longer available.');

        return $disk->response($document->path, $document->original_name);
    }

    public function destroyDocument(Candidate $candidate, CandidateDocument $document)
    {
        abort_unless($document->candidate_id === $candidate->id, 404);

        $document->delete();

        return back()->with('success', 'Document removed.');
    }

    private function candidateRules(?Candidate $candidate = null): array
    {
        return [
            'name'           => 'required|string|max:150',
            'position_id'    => 'required|exists:positions,id',
            'job_opening_id' => 'nullable|exists:recruitment_job_openings,id',
            'source_id'      => ['nullable', Rule::exists('recruitment_options', 'id')->where('type', RecruitmentOption::TYPE_PLATFORM)],
            'email'          => 'nullable|email|max:150',
            'phone'          => 'nullable|string|max:30',
            // A hired candidate's status belongs to the Offer workflow and cannot be edited here.
            'status'         => [$candidate?->isHired() ? 'exclude' : 'required', Rule::in(Candidate::MANUAL_STATUSES)],
            'notes'          => 'nullable|string',
        ];
    }

    private function documentRules(): array
    {
        return [
            'documents'           => 'nullable|array|max:10',
            'documents.*.type_id' => ['nullable', Rule::exists('recruitment_options', 'id')->where('type', RecruitmentOption::TYPE_DOCUMENT_TYPE)],
            'documents.*.file'    => 'nullable|file|max:10240',
            'documents.*.url'     => 'nullable|url|max:500',
        ];
    }

    /**
     * Holds every submitted document to the rules HR set for its type on the
     * Settings tab: whether it may be a file, a link or either, and which
     * file formats are accepted.
     */
    private function assertDocumentRules(Request $request): void
    {
        $types = RecruitmentOption::ofType(RecruitmentOption::TYPE_DOCUMENT_TYPE)->get()->keyBy('id');
        $anyFormat = collect(RecruitmentOption::FILE_FORMATS)->flatMap->extensions->all();
        $errors = [];

        foreach ($request->input('documents', []) as $index => $row) {
            $file = $request->file("documents.{$index}.file");
            $url = $row['url'] ?? null;
            $type = $types->get($row['type_id'] ?? null);
            $name = $type->name ?? 'Document';

            if ($file && !($type?->acceptsFile() ?? true)) {
                $errors["documents.{$index}.file"] = "{$name} must be given as a link, not a file.";
            } elseif ($file) {
                $allowed = $type?->extensions() ?? $anyFormat;

                if (!in_array(strtolower($file->getClientOriginalExtension()), $allowed, true)) {
                    $errors["documents.{$index}.file"] = "{$name} must be one of: " . strtoupper(implode(', ', $allowed)) . '.';
                }
            }

            if ($url && !($type?->acceptsLink() ?? false)) {
                $errors["documents.{$index}.url"] = "{$name} must be uploaded as a file, not given as a link.";
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** Told to HR after a save — a reminder, not a block: the documents may simply not have arrived yet. */
    private function missingDocumentsNote(Candidate $candidate): ?string
    {
        $candidate->load(['jobOpening.requestedDocuments', 'documents']);
        $missing = $candidate->missingRequiredDocuments();

        return $missing->isEmpty()
            ? null
            : "Still missing for \"{$candidate->jobOpening->position_title}\": " . $missing->pluck('name')->implode(', ') . '.';
    }

    /** Stores the rows of the repeatable "documents" block — a file, or a link; empty rows are skipped. */
    private function storeDocuments(Request $request, Candidate $candidate): void
    {
        foreach ($request->input('documents', []) as $index => $row) {
            $file = $request->file("documents.{$index}.file");

            if (!$file && !empty($row['url'])) {
                $candidate->documents()->create([
                    'document_type_id'        => $row['type_id'] ?? null,
                    'original_name'           => $row['url'],
                    'url'                     => $row['url'],
                    'uploaded_by_employee_id' => session('user.id'),
                ]);
            }

            if (!$file) {
                continue;
            }

            $candidate->documents()->create([
                'document_type_id'        => $row['type_id'] ?? null,
                'original_name'           => $file->getClientOriginalName(),
                'disk'                    => 'local',
                'path'                    => $file->store("recruitment/candidates/{$candidate->id}", 'local'),
                'mime_type'               => $file->getClientMimeType(),
                'size'                    => $file->getSize(),
                'uploaded_by_employee_id' => session('user.id'),
            ]);
        }
    }

    /**
     * Status changes and interviews of a candidate as one list, newest first.
     */
    private function timeline(Candidate $candidate)
    {
        $statusEvents = $candidate->statusHistories->map(fn ($history) => [
            'kind'   => 'status',
            'at'     => $history->changed_at,
            'title'  => $history->fromLabel()
                ? "Status changed: {$history->fromLabel()} → {$history->toLabel()}"
                : "Candidate added — {$history->toLabel()}",
            'detail' => $history->changedBy ? 'By ' . ($history->changedBy->basicData->full_name ?? $history->changedBy->eci) : null,
            'badge'  => null,
            'edit'   => null,
        ]);

        $interviewEvents = $candidate->interviews->map(fn (Interview $interview) => [
            'kind'   => 'interview',
            'at'     => $interview->scheduled_at,
            'title'  => $interview->displayTitle(),
            'detail' => "{$interview->stageLabel()} · {$interview->scheduled_at->format('d M Y, H:i')}–{$interview->endsAt()->format('H:i')}"
                . " · {$interview->whereLabel()} · Interviewers: {$interview->interviewerNames()}",
            'badge'  => $interview,
            'edit'   => $interview->isPending() ? RecruitmentCalendar::editPayload($interview) : null,
        ]);

        return $statusEvents->concat($interviewEvents)->sortByDesc('at')->values();
    }

    /** The add-candidate modal is embedded on several pages; return to whichever one submitted it. */
    private function respond(Request $request, string $message, ?string $warning)
    {
        $redirect = back()->with('success', $message);

        return $warning ? $redirect->with('warning', $warning) : $redirect;
    }
}

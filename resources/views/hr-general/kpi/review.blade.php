@extends('dashboard')

@section('title', 'KPI Evaluation Review — ' . ($evaluation->isUpwardType() ? ($evaluation->supervisor?->basicData?->full_name ?? 'Employee') : ($evaluation->employee?->basicData?->full_name ?? 'Employee')))
@section('page-title', !empty($leadMode) ? (($evaluation->template?->target_type ?? '') === 'peer' ? 'Peer Assessment' : 'Lead Assessment') : 'KPI Review')

@section('content')
@php
    use Carbon\Carbon;
    $user  = session('user');
    $emp   = $evaluation->employee;
    $bd    = $emp?->basicData;
    $supBd = $evaluation->supervisor?->basicData;
    $periodObj = Carbon::createFromFormat('Y-m', $evaluation->period_month);
    $periodLabel = $periodObj->format('F Y');
    $isApproved = $evaluation->status === \App\Models\KpiEvaluation::STATUS_HR_APPROVED;
    $isUpward = $evaluation->isUpwardType();
    $isSelf = (int)($user['id'] ?? 0) === (int)$evaluation->employee_id && empty($user['is_admin']);
    // Who scores whom — upward reverses the usual meaning of the two
    // relations (the rater is employee_id, the subject being scored is
    // supervisor_id); self/lead/peer all keep employee_id as the subject.
    $ttype = $evaluation->template?->target_type ?? 'supervisor';
    $badgeLabel = match ($ttype) {
        'self'   => 'Self',
        'upward' => 'Upward',
        'peer'   => 'Peer',
        default  => 'Lead',
    };
    $badgeClass = match ($ttype) {
        'self'   => 'bg-purple-100 text-purple-700 border-purple-200',
        'upward' => 'bg-amber-100 text-amber-700 border-amber-200',
        'peer'   => 'bg-cyan-100 text-cyan-700 border-cyan-200',
        default  => 'bg-indigo-100 text-indigo-700 border-indigo-200',
    };
    $raterName   = $isUpward ? ($bd?->full_name ?? $emp?->eci ?? '—') : ($supBd?->full_name ?? ($ttype === 'peer' ? 'A peer' : 'Supervisor'));
    $subjectName = $isUpward ? ($supBd?->full_name ?? 'their supervisor') : ($bd?->full_name ?? $emp?->eci ?? '—');
    // Upward rows are filled by the rater via the self-assessment pathway
    // (My KPI), never here — this page is HR's read-only review + approval.
    // A lead (the assigned reviewer, not HR/admin) is locked out once they submit to
    // HR; a saved draft stays editable. They still see every question, score and rating.
    $leadMode       = $leadMode ?? false; // opened from My KPI (the lead's own page)
    $isLeadReviewer = $leadMode || ((int) ($user['id'] ?? 0) === (int) $evaluation->supervisor_id && !$canApprove && empty($user['is_admin']));
    $isSubmitted    = $evaluation->hasSupervisorReview();
    $isRevision     = $evaluation->status === \App\Models\KpiEvaluation::STATUS_HR_REJECTED;
    $isLeadLocked   = $isLeadReviewer && $isSubmitted && !$isUpward;
    // A self-assessment is never edited or approved here: HR only reviews how the
    // employee scored themself.
    $isSelfType     = $evaluation->isSelfType();
    $usesSelfFields = $isUpward || $isSelfType; // filled by the employee (self_* fields)
    $isReadOnly = $isApproved || $isSelf || $isUpward || $isLeadLocked || $isSelfType;
    $backUrl = $isLeadReviewer
        ? route('general.my-kpi.index', ['period' => $evaluation->period_month, 'tab' => $ttype === 'peer' ? 'peer' : 'lead'])
        : route('general.kpi-evaluation.index', ['period' => $evaluation->period_month]);
    $backLabel = $isLeadReviewer ? 'Back to My KPI' : 'Back to KPI Dashboard';
    $canReviewNow = $can('general.kpi-evaluation.review') && !$isReadOnly;
    $canApproveNow = !$leadMode && !$isSelfType && $canApprove && $evaluation->isReadyForApproval() && !$isApproved && !$isSelf;
    $scaleMax  = $evaluation->template?->scaleMax() ?: 5;
    $scaleRows = $evaluation->template ? $evaluation->template->scaleRows() : collect();
    $siblingUpwardEvaluations = $siblingUpwardEvaluations ?? collect();
@endphp

<div class="space-y-6">

    {{-- ── Page Header ─────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900">
                {{ $isLeadLocked ? 'KPI Assessment Submitted' : ($isSelfType ? 'Self-Assessment Review' : ($isReadOnly ? 'KPI Evaluation Detail' : ($isRevision ? 'Revise KPI Assessment' : ($evaluation->status === 'draft' ? 'Continue KPI Draft' : 'KPI Supervisor Review')))) }}
                <span class="text-indigo-700">— {{ $subjectName }}</span>
            </h1>
            <p class="text-xs text-gray-500 mt-0.5">
                {{ $isLeadLocked ? 'Your scores were sent to HR. This page is read-only.' : ($isSelfType ? 'How this employee scored themself. Self-assessments are not approved by HR.' : ($isReadOnly ? 'Review scorecard and final approval status.' : 'Continue evaluation for the selected period and assign scores.')) }}
                @if($isUpward)
                    Scoring <strong>{{ $subjectName }}</strong>, submitted by {{ $raterName }}.
                @endif
            </p>
        </div>
        <div class="flex items-center gap-2">
            @if($isSelf)
            <a href="{{ route('general.my-kpi.index', ['period' => $evaluation->period_month]) }}"
               class="inline-flex items-center gap-1.5 px-4 py-2 bg-gray-100 text-gray-700 text-xs font-semibold rounded-xl hover:bg-gray-200 transition-all">
                <i class="fas fa-chevron-left text-xs"></i> Back to My KPI
            </a>
            @else
            <a href="{{ $backUrl }}"
               class="inline-flex items-center gap-1.5 px-4 py-2 bg-gray-100 text-gray-700 text-xs font-semibold rounded-xl hover:bg-gray-200 transition-all">
                <i class="fas fa-chevron-left text-xs"></i> {{ $backLabel }}
            </a>
            @endif
            @if($canApproveNow)
            <button onclick="openReviseModal()"
                class="inline-flex items-center gap-1.5 px-4 py-2 bg-white text-red-600 border border-red-200 text-xs font-bold rounded-xl shadow-sm hover:bg-red-50 transition-all">
                <i class="fas fa-rotate-left text-xs"></i> Request Revision
            </button>
            <button onclick="openApproveModal()"
                class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 text-white text-xs font-bold rounded-xl shadow hover:bg-emerald-700 transition-all">
                <i class="fas fa-check-circle text-xs"></i> Approve Evaluation
            </button>
            @endif
        </div>
    </div>

    @if($isSelfType)
    <div class="bg-purple-50 border border-purple-200 rounded-2xl p-4 flex items-start gap-3">
        <i class="fas fa-eye text-purple-600 mt-0.5"></i>
        <div class="text-xs text-purple-800">
            <p class="font-bold">View only — no approval needed</p>
            <p class="mt-0.5">This is the employee's own self-assessment. It is not scored or approved by HR; you can only review how they rated themself.</p>
        </div>
    </div>
    @endif

    @if($isRevision)
    <div class="bg-red-50 border border-red-200 rounded-2xl p-4 flex items-start gap-3">
        <i class="fas fa-rotate-left text-red-600 mt-0.5"></i>
        <div class="text-xs text-red-800">
            <p class="font-bold">
                {{ ($isLeadReviewer || $isSelf) ? 'HR asked for a revision' : 'Sent back for revision — waiting for the assessor to resubmit' }}
            </p>
            @if($evaluation->hr_notes)
            <p class="mt-0.5"><span class="font-semibold">HR's note:</span> {{ $evaluation->hr_notes }}</p>
            @endif
            @if($isLeadReviewer)
            <p class="mt-0.5">Update your scores below, then use <strong>Send to HR</strong> to resubmit.</p>
            @endif
        </div>
    </div>
    @endif

    @if($isLeadLocked)
    <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-4 flex items-start gap-3">
        <i class="fas fa-lock text-emerald-600 mt-0.5"></i>
        <div class="text-xs text-emerald-800">
            <p class="font-bold">Submitted to HR{{ $evaluation->reviewed_at ? ' on ' . $evaluation->reviewed_at->format('d M Y, H:i') : '' }}</p>
            <p class="mt-0.5">You can no longer edit this assessment, but your indicator answers, scores and ratings are shown below.</p>
        </div>
    </div>
    @elseif($isLeadReviewer && !$isReadOnly && !$isRevision)
    <div class="bg-amber-50 border border-amber-200 rounded-2xl p-4 flex items-start gap-3">
        <i class="fas fa-pen-to-square text-amber-600 mt-0.5"></i>
        <div class="text-xs text-amber-800">
            <p class="font-bold">{{ $evaluation->status === 'draft' ? 'Draft — not sent to HR yet' : 'Not sent to HR yet' }}</p>
            <p class="mt-0.5"><strong>Save Draft</strong> keeps your work private and editable. <strong>Send to HR</strong> submits it and locks your changes.</p>
        </div>
    </div>
    @endif

    {{-- ── Section 1: Employee & Template Info (Screenshot 3 & 4) ──────────── --}}
    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 space-y-4">
        <div class="flex items-center justify-between border-b border-gray-100 pb-3">
            <h3 class="text-sm font-bold text-gray-800 flex items-center gap-2">
                <i class="fas fa-id-card text-indigo-500"></i> Employee Details
            </h3>
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold border
                {{ $isApproved ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200' }}">
                {{ $evaluation->status_label }}
            </span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 text-xs">
            <div>
                <span class="text-gray-400 font-medium block">EMPLOYEE NAME</span>
                <span class="font-bold text-gray-900 text-sm mt-0.5 block">{{ $bd?->full_name ?? '—' }}</span>
            </div>
            <div>
                <span class="text-gray-400 font-medium block">EMPLOYEE NO.</span>
                <span class="font-mono text-red-500 font-bold mt-0.5 block">{{ $emp?->eci ?? '—' }}</span>
            </div>
            <div>
                <span class="text-gray-400 font-medium block">POSITION</span>
                <span class="font-semibold text-gray-700 mt-0.5 block">{{ $bd?->position ?? '—' }}</span>
            </div>
            <div>
                <span class="text-gray-400 font-medium block">DEPARTMENT</span>
                <span class="font-semibold text-gray-700 mt-0.5 block">{{ $bd?->department ?? '—' }}</span>
            </div>
        </div>

        <div class="pt-3 border-t border-gray-100">
            <span class="text-xs text-gray-400 font-medium block mb-1">TEMPLATE KPI</span>
            <div class="flex flex-wrap items-center gap-2">
                <span class="font-bold text-indigo-700 text-sm">{{ $evaluation->template?->name ?? '—' }}</span>
                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold border {{ $badgeClass }}">
                    {{ $badgeLabel }} &middot; {{ $evaluation->template?->target_type_label ?? 'Lead Assessment' }}
                </span>
            </div>
            @if($evaluation->template?->description)
            <p class="text-xs text-gray-500 mt-1 italic">{{ $evaluation->template->description }}</p>
            @endif
        </div>

        @unless($evaluation->isSelfType())
        <div class="pt-3 border-t border-gray-100">
            <span class="text-xs text-gray-400 font-medium block mb-1">SCORING</span>
            <p class="text-xs text-gray-700">
                <i class="fas fa-arrow-right-long text-gray-400 mr-1"></i>
                <strong class="text-gray-900">{{ $raterName }}</strong>
                {{ $isUpward ? 'is rating' : 'scores' }}
                <strong class="text-indigo-700">{{ $subjectName }}</strong>
                @if($isUpward)
                    <span class="text-gray-400">— this evaluation's score is about {{ $subjectName }}, not {{ $raterName }}</span>
                @endif
            </p>
        </div>
        @endunless
    </div>

    {{-- ── Anonymous evaluation notice ──────────────────────────────────────── --}}
    @if($evaluation->is_anonymous)
    <div class="bg-slate-800 text-white rounded-2xl p-4 shadow-sm flex items-start gap-3">
        <i class="fas fa-user-secret text-lg mt-0.5 shrink-0 text-slate-300"></i>
        <div>
            <p class="text-xs font-bold uppercase tracking-wider text-slate-200">Anonymous Evaluation</p>
            <p class="text-xs text-slate-300 mt-1">This evaluation is anonymous; please evaluate honestly. Individual rater identities and scores are never shown to the supervisor being evaluated — only the published average is.</p>
        </div>
    </div>
    @endif

    {{-- ── Upward Assessment: sibling submissions & anonymous average publish ── --}}
    @if($isUpward && $canApprove && !$leadMode)
    @php
        $approvedSiblings = $siblingUpwardEvaluations->where('status', \App\Models\KpiEvaluation::STATUS_HR_APPROVED);
        $publishedSiblings = $siblingUpwardEvaluations->whereNotNull('published_at');
        $currentAvg = $approvedSiblings->isNotEmpty() ? round($approvedSiblings->avg('overall_score'), 2) : null;
    @endphp
    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h3 class="text-sm font-bold text-gray-800 flex items-center gap-2">
                    <i class="fas fa-users text-amber-500"></i> Upward Assessment — Rater Submissions
                </h3>
                <p class="text-xs text-gray-500 mt-0.5">
                    Every subordinate rating <strong>{{ $supBd?->full_name ?? 'this supervisor' }}</strong> for {{ $periodLabel }}.
                    Only the average is ever shown to them — visible here to HR for review purposes only.
                </p>
            </div>
            <button type="button" onclick="publishUpwardAverage()"
                {{ $approvedSiblings->isEmpty() ? 'disabled' : '' }}
                class="inline-flex items-center gap-1.5 px-4 py-2 {{ $approvedSiblings->isEmpty() ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-slate-900 text-white hover:bg-slate-800' }} text-xs font-bold rounded-xl shadow transition-all shrink-0">
                <i class="fas fa-broadcast-tower text-xs"></i> Publish Average to Supervisor
            </button>
        </div>

        <div class="overflow-x-auto border border-gray-100 rounded-xl">
            <table class="w-full text-xs">
                <thead class="bg-gray-50 border-b border-gray-200 text-gray-500 uppercase">
                    <tr>
                        <th class="text-left px-4 py-2.5 font-semibold">Rater (subordinate)</th>
                        <th class="text-center px-4 py-2.5 font-semibold w-28">Status</th>
                        <th class="text-center px-4 py-2.5 font-semibold w-24">Score</th>
                        <th class="text-center px-4 py-2.5 font-semibold w-28">Published</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($siblingUpwardEvaluations as $s)
                    <tr>
                        <td class="px-4 py-2.5 font-semibold text-gray-800">{{ $s->employee?->basicData?->full_name ?? $s->employee?->eci ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-center">
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $s->status === 'hr_approved' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                {{ $s->status_label }}
                            </span>
                        </td>
                        <td class="px-4 py-2.5 text-center font-bold">{{ $s->overall_score !== null ? number_format($s->overall_score, 1) : '—' }}</td>
                        <td class="px-4 py-2.5 text-center">{{ $s->published_at ? $s->published_at->format('d M Y') : '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($publishedSiblings->isNotEmpty())
        <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-xs text-emerald-800">
            <i class="fas fa-check-circle mr-1"></i>
            Published average <strong>{{ $currentAvg }}</strong> from {{ $approvedSiblings->count() }} approved rater(s) — visible on the supervisor's My KPI page.
        </div>
        @endif
    </div>
    @endif

    {{-- ── Scoring Scale reference (read-only) ───────────────────────────────── --}}
    @if($scaleRows->isNotEmpty())
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-5 py-3 bg-amber-50/60 border-b border-amber-100 flex items-center gap-2">
            <i class="fas fa-table-list text-amber-600 text-xs"></i>
            <h3 class="text-xs font-bold text-amber-900 uppercase tracking-wider">Rating Scale</h3>
            <span class="text-[11px] text-amber-700">Weighted Score = Score &divide; {{ $scaleMax }} &times; Weight</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs table-fixed min-w-200 text-center">
                <colgroup>
                    <col style="width:8%">
                    <col style="width:16%">
                    <col style="width:28%">
                    <col style="width:16%">
                    <col style="width:32%">
                </colgroup>
                <thead class="bg-gray-50 border-b border-gray-200 text-gray-500 uppercase">
                    <tr>
                        <th class="px-4 py-2.5 font-semibold">Scale</th>
                        <th class="px-4 py-2.5 font-semibold">Category</th>
                        <th class="px-4 py-2.5 font-semibold">Definition</th>
                        <th class="px-4 py-2.5 font-semibold">Achievement</th>
                        <th class="px-4 py-2.5 font-semibold">Remarks</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($scaleRows as $sc)
                    <tr>
                        <td class="px-4 py-2.5 font-bold text-indigo-700">{{ $sc->scale_value }}</td>
                        <td class="px-4 py-2.5 font-semibold text-gray-800">{{ $sc->category }}</td>
                        <td class="px-4 py-2.5 text-gray-600">{{ $sc->definition }}</td>
                        <td class="px-4 py-2.5 text-gray-600">{{ $sc->achievement_label }}</td>
                        <td class="px-4 py-2.5 text-gray-500">{{ $sc->description }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- ── Section 3: Scoring Form & Rating (per template scale) ─────────────── --}}
    <form id="kpiReviewForm" onsubmit="submitKpiReview(event)">
        @csrf
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden space-y-6">

            {{-- Period & General Notes --}}
            <div class="p-6 border-b border-gray-100 space-y-4">
                <h3 class="text-sm font-bold text-gray-800 flex items-center gap-2">
                    <i class="fas fa-calendar-alt text-amber-500"></i> Evaluation Period &amp; General Notes
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">PERIOD TYPE</label>
                        <input type="text" readonly value="{{ $evaluation->template?->period_type_label ?? 'Bulanan (Monthly)' }}"
                            class="w-full px-3 py-2 text-xs border border-gray-200 rounded-xl bg-gray-50 font-medium">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">PERIOD NAME</label>
                        <input type="text" readonly value="{{ $periodObj->format('F') }}"
                            class="w-full px-3 py-2 text-xs border border-gray-200 rounded-xl bg-gray-50 font-medium">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">YEAR</label>
                        <input type="text" readonly value="{{ $periodObj->format('Y') }}"
                            class="w-full px-3 py-2 text-xs border border-gray-200 rounded-xl bg-gray-50 font-medium">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">GENERAL EVALUATION NOTES</label>
                    <textarea name="general_notes" rows="2" {{ $isReadOnly ? 'readonly' : '' }}
                        placeholder="General comments on the employee's performance this period..."
                        class="w-full px-3 py-2 text-xs border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-400 resize-none">{{ old('general_notes', $evaluation->general_notes) }}</textarea>
                </div>
            </div>

            {{-- Indicator Scoring Table --}}
            <div class="p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold text-gray-800 flex items-center gap-2">
                        <i class="fas fa-star-half-alt text-yellow-500"></i> Indicator Scoring Form
                    </h3>
                    <span class="text-xs text-gray-400">
                        Select <strong>1–{{ $scaleMax }} stars</strong> for rating. Unfilled indicators are highlighted in <span class="text-amber-600 font-bold">amber</span>.
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead class="bg-gray-50/80 border-b border-gray-200">
                            <tr>
                                <th class="text-left px-4 py-3 font-semibold text-gray-500 uppercase w-10">NO</th>
                                <th class="text-left px-4 py-3 font-semibold text-gray-500 uppercase">KPI INDICATOR</th>
                                <th class="text-center px-3 py-3 font-semibold text-gray-500 uppercase w-16">WEIGHT</th>
                                <th class="text-center px-4 py-3 font-semibold text-gray-500 uppercase w-72 min-w-72">ACTUAL</th>
                                <th class="text-center px-4 py-3 font-semibold text-gray-500 uppercase w-48">RATING</th>
                                <th class="text-center px-4 py-3 font-semibold text-gray-500 uppercase w-28">WEIGHTED SCORE</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100" id="indicatorRows">
                            @foreach($evaluation->details->sortBy('indicator.order_seq') as $i => $detail)
                            @php
                                $ind = $detail->indicator;
                                $isPara = $ind && $ind->isParagraph();
                                $max = $ind?->rating_max ?: $scaleMax;
                                $weight = $ind?->weight ?? 0;
                                // Upward rows are filled via self_* fields (rater's own input);
                                // every other type via supervisor_* fields.
                                $scoreVal = $usesSelfFields ? $detail->self_achievement : $detail->supervisor_score;
                                $notesVal = $usesSelfFields ? $detail->self_notes : $detail->supervisor_notes;
                                $weightedVal = $usesSelfFields
                                    ? (is_null($detail->self_achievement) ? null : round($weight * $detail->self_achievement / 100, 2))
                                    : $detail->weighted_score;
                                $currentRating = $detail->star_rating ?? ($scoreVal ? min($max, max(1, (int) round($scoreVal / 100 * $max))) : null);
                                // A text-answer indicator counts as filled once its text is written.
                                $isUnfilled = $isPara ? trim((string) old("scores.{$detail->id}.notes", $notesVal)) === '' : is_null($currentRating);
                            @endphp
                            <tr class="indicator-tr ind-row hover:bg-gray-50/50 transition-colors {{ $isUnfilled ? 'bg-amber-50/20' : '' }}" data-weight="{{ $weight }}">
                                <td class="px-4 py-4 font-bold text-gray-400 align-top">{{ $i + 1 }}</td>
                                <td class="px-4 py-4 align-top space-y-2">
                                    <div>
                                        <p class="font-bold text-gray-900 text-xs">
                                            {{ $ind?->name ?? '—' }}
                                            @if($isPara)<span class="ml-1 px-1.5 py-0.5 rounded bg-gray-100 text-gray-500 text-[10px] font-semibold">Text answer</span>@endif
                                        </p>
                                        @if($ind?->description)
                                            <p class="text-[11px] text-gray-400 mt-0.5">{{ $ind->description }}</p>
                                        @endif
                                        @if(!$isPara && $ind?->target_value !== null)
                                            <p class="text-[11px] text-indigo-600 font-semibold mt-0.5">Target: {{ rtrim(rtrim(number_format($ind->target_value, 2), '0'), '.') }}</p>
                                        @endif
                                    </div>
                                    @if($isPara)
                                    <textarea name="scores[{{ $detail->id }}][notes]" rows="3"
                                        {{ $isReadOnly ? 'readonly' : '' }}
                                        placeholder="Notes / feedback on the employee's answer..."
                                        oninput="updateReviewSubmitState()"
                                        class="req-field w-full px-3 py-2 text-[11px] border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-400 resize-y bg-white">{{ old("scores.{$detail->id}.notes", $notesVal) }}</textarea>
                                    @if(!$usesSelfFields && $detail->self_notes)
                                    <p class="text-[11px] text-gray-500 mt-1"><span class="font-semibold text-gray-600">Employee's answer:</span> {{ $detail->self_notes }}</p>
                                    @endif
                                    @else
                                    <input type="text" name="scores[{{ $detail->id }}][notes]"
                                        value="{{ old("scores.{$detail->id}.notes", $notesVal) }}"
                                        {{ $isReadOnly ? 'readonly' : '' }}
                                        placeholder="Add a note for this indicator (optional)..."
                                        class="w-full px-3 py-1.5 text-[11px] border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-400 bg-white">
                                    @endif
                                </td>
                                @if($isPara)
                                <td colspan="4" class="px-4 py-4 align-top text-center text-[11px] text-gray-300 italic">Text answer — not scored</td>
                                @else
                                <td class="px-3 py-4 align-top text-center font-bold text-indigo-700">
                                    {{ rtrim(rtrim(number_format($weight, 2), '0'), '.') }}%
                                </td>
                                <td class="px-4 py-4 align-top text-center">
                                    <textarea name="scores[{{ $detail->id }}][actual]" rows="3" maxlength="255"
                                        {{ $isReadOnly ? 'readonly' : '' }}
                                        placeholder="Enter the actual result..."
                                        class="w-full min-h-18 px-3 py-2 text-sm text-left border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-400 resize-y {{ $isReadOnly ? 'bg-gray-50 text-gray-600' : 'bg-white' }}">{{ old("scores.{$detail->id}.actual", $detail->actual_achievement) }}</textarea>
                                </td>
                                <td class="px-4 py-4 align-top text-center">
                                    <input type="hidden" name="scores[{{ $detail->id }}][rating]" id="rating_val_{{ $detail->id }}" class="rating-val" value="{{ $currentRating ?? '' }}">

                                    <div id="stars_{{ $detail->id }}" class="flex items-center justify-center gap-1 my-1 flex-wrap">
                                        @for($star = 1; $star <= $max; $star++)
                                        <button type="button"
                                            {{ $isReadOnly ? 'disabled' : '' }}
                                            onclick="setStarRating({{ $detail->id }}, {{ $star }}, {{ $weight }}, {{ $max }})"
                                            class="star-btn text-base transition-transform hover:scale-125 focus:outline-none {{ ($currentRating && $star <= $currentRating) ? 'text-amber-400' : 'text-gray-300' }}">
                                            ★
                                        </button>
                                        @endfor
                                    </div>

                                    <span id="rating_badge_{{ $detail->id }}" class="inline-block text-[11px] font-bold px-2 py-0.5 rounded-full transition-all
                                        {{ $isUnfilled ? 'bg-amber-100 text-amber-700 border border-amber-200' : 'bg-gray-100 text-gray-700' }}">
                                        {{ $currentRating ? "{$currentRating}/{$max}" : 'Select (Not filled)' }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 align-top text-center font-bold text-sm">
                                    <span id="weighted_score_{{ $detail->id }}" class="weighted-cell text-gray-800">
                                        {{ !is_null($weightedVal) ? number_format($weightedVal, 2) : '0.00' }}
                                    </span>
                                </td>
                                @endif
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Bottom Summary Bar (Screenshot 4 & 5) --}}
                <div class="p-4 bg-gray-50 rounded-xl border border-gray-200/80 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-gray-700">Total Weight:</span>
                        <span class="font-bold text-indigo-700 text-sm">100.00%</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="font-bold text-gray-700">Final Evaluation Score:</span>
                        <span id="finalScoreDisplay" class="text-xl font-bold text-gray-900">
                            {{ number_format(!is_null($evaluation->overall_score) ? $evaluation->overall_score : $evaluation->details->sum('weighted_score'), 2) }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- Locked lead: the send button stays visible but inactive --}}
            @if($isLeadLocked)
            <div class="p-5 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
                <a href="{{ $backUrl }}" class="px-4 py-2 bg-gray-200 text-gray-700 text-xs font-semibold rounded-xl hover:bg-gray-300 transition-all">Back</a>
                <button type="button" disabled
                    class="px-6 py-2 bg-gray-200 text-gray-400 text-xs font-bold rounded-xl cursor-not-allowed">
                    <i class="fas fa-check text-xs mr-1"></i> Sent to HR
                </button>
            </div>
            @endif

            {{-- Action Footer Buttons --}}
            @if(!$isReadOnly)
            <div class="p-5 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
                <a href="{{ $backUrl }}"
                   class="px-4 py-2 bg-gray-200 text-gray-700 text-xs font-semibold rounded-xl hover:bg-gray-300 transition-all">
                    Cancel
                </a>
                <div class="flex items-center gap-2">
                    <button type="submit" name="action" value="draft" onclick="window._kpiReviewAction='draft'"
                        class="px-5 py-2 bg-white text-indigo-600 border border-indigo-200 text-xs font-bold rounded-xl shadow-sm hover:bg-indigo-50 transition-all">
                        Save Draft
                    </button>
                    <span id="reviewIncompleteHint" class="hidden text-[11px] font-semibold text-amber-700">
                        <i class="fas fa-circle-exclamation mr-1"></i><span id="reviewIncompleteCount"></span> not filled
                    </span>
                    <button type="submit" name="action" value="submit" id="submitReviewBtn" disabled onclick="window._kpiReviewAction='submit'"
                        class="px-6 py-2 primary-gradient text-white text-xs font-bold rounded-xl shadow hover:opacity-90 transition-all disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:opacity-40">
                        <i class="fas fa-paper-plane text-xs mr-1"></i> Send to HR
                    </button>
                </div>
            </div>
            @endif

        </div>
    </form>

</div>

{{-- Submit-to-HR Confirmation Modal (lead review) --}}
<div id="reviewConfirmModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-4 text-center border border-gray-100">
        <div class="w-14 h-14 rounded-2xl bg-amber-100 text-amber-600 flex items-center justify-center mx-auto text-2xl shadow-sm">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="space-y-1.5">
            <h3 class="text-base font-bold text-gray-900">Confirm Submission</h3>
            <p class="text-xs text-gray-500 leading-relaxed px-2">
                Send the assessment for <strong>{{ $bd?->full_name ?? 'this employee' }}</strong> to HR?
                Make sure the scores and notes for every indicator are correct before continuing.
            </p>
        </div>
        <div class="flex items-center justify-center gap-3 pt-2">
            <button type="button" onclick="hideKpiModal('reviewConfirmModal')"
                class="px-5 py-2.5 bg-gray-100 text-gray-700 text-xs font-semibold rounded-xl hover:bg-gray-200 transition-all">
                Cancel
            </button>
            <button type="button" onclick="executeKpiReview()"
                class="inline-flex items-center gap-1.5 px-6 py-2.5 primary-gradient text-white text-xs font-bold rounded-xl shadow hover:opacity-90 transition-all">
                <i class="fas fa-paper-plane text-xs"></i> Yes, Send to HR
            </button>
        </div>
    </div>
</div>

{{-- Request Revision Modal --}}
<div id="reviseModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-5 space-y-4">
        <h3 class="text-base font-bold text-gray-900">Request Revision</h3>
        <p class="text-sm text-gray-600">
            This sends the assessment back to the person who filled it in so they can correct it and resubmit.
            Their answers are kept, and the score stays hidden until you approve the revised version.
        </p>
        <textarea id="reviseNotes" rows="3" placeholder="What needs to change? (required)..."
            class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-red-300 resize-none"></textarea>
        <div class="flex items-center justify-end gap-3">
            <button onclick="hideKpiModal('reviseModal')" class="px-4 py-2.5 bg-gray-100 text-gray-700 text-sm font-medium rounded-xl">Cancel</button>
            <button onclick="confirmRevise()" class="inline-flex items-center gap-2 px-6 py-2.5 bg-red-600 text-white text-sm font-bold rounded-xl shadow hover:bg-red-700">
                <i class="fas fa-rotate-left text-xs"></i> Send Back
            </button>
        </div>
    </div>
</div>

{{-- Approve Modal --}}
<div id="approveModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-5 space-y-4">
        <h3 class="text-base font-bold text-gray-900">Approve Evaluation</h3>
        <p class="text-sm text-gray-600">Once approved, <strong>{{ $bd?->full_name ?? 'the employee' }}</strong> will be able to view their final KPI scorecard.</p>
        <textarea id="approveNotes" rows="3" placeholder="HR notes (optional)..."
            class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-emerald-300 resize-none"></textarea>
        <div class="flex items-center justify-end gap-3">
            <button onclick="hideKpiModal('approveModal')" class="px-4 py-2.5 bg-gray-100 text-gray-700 text-sm font-medium rounded-xl">Cancel</button>
            <button onclick="confirmApprove()" class="inline-flex items-center gap-2 px-6 py-2.5 bg-emerald-600 text-white text-sm font-bold rounded-xl shadow hover:bg-emerald-700">
                <i class="fas fa-check text-xs"></i> Confirm Approval
            </button>
        </div>
    </div>
</div>

<script>
function setStarRating(detailId, star, weight, max) {
    max = max || 5;
    document.getElementById(`rating_val_${detailId}`).value = star;

    const box = document.getElementById(`stars_${detailId}`);
    if (box) box.querySelectorAll('.star-btn').forEach((btn, idx) => {
        btn.className = (idx + 1) <= star
            ? 'star-btn text-base transition-transform hover:scale-125 focus:outline-none text-amber-400'
            : 'star-btn text-base transition-transform hover:scale-125 focus:outline-none text-gray-300';
    });

    const badge = document.getElementById(`rating_badge_${detailId}`);
    if (badge) {
        badge.textContent = `${star}/${max}`;
        badge.className = 'inline-block text-[11px] font-bold px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-100';
    }

    // Weighted score = (star ÷ max × 100) × weight ÷ 100
    const score100 = star / max * 100;
    const weighted = (weight * score100) / 100;
    const cell = document.getElementById(`weighted_score_${detailId}`);
    if (cell) cell.textContent = weighted.toFixed(2);

    recalcTotalScore();
    updateReviewSubmitState();
}

// "Send to HR" stays disabled until every indicator is complete (rating picked for
// scale rows, text answer written for text-answer rows; actual result is optional). Counted per indicator, updated live as the
// reviewer fills in. "Save Draft" is always available.
function updateReviewSubmitState() {
    const btn = document.getElementById('submitReviewBtn');
    if (!btn) return;
    let missing = 0;
    document.querySelectorAll('tr.ind-row').forEach(tr => {
        const ratingMissing = [...tr.querySelectorAll('.rating-val')].some(i => i.value === '');
        const textMissing   = [...tr.querySelectorAll('.req-field')].some(el => el.value.trim() === '');
        const rowMissing = ratingMissing || textMissing;
        if (rowMissing) missing++;
        tr.classList.toggle('bg-amber-50/20', rowMissing); // amber = indicator not filled yet
    });
    btn.disabled = missing > 0;
    const hint = document.getElementById('reviewIncompleteHint');
    if (hint) {
        hint.classList.toggle('hidden', missing === 0);
        document.getElementById('reviewIncompleteCount').textContent = `${missing} indicator(s)`;
    }
}
document.addEventListener('DOMContentLoaded', updateReviewSubmitState);

function recalcTotalScore() {
    let total = 0;
    document.querySelectorAll('.weighted-cell').forEach(cell => {
        const v = parseFloat(cell.textContent);
        if (!isNaN(v)) total += v;
    });
    const display = document.getElementById('finalScoreDisplay');
    if (display) display.textContent = total.toFixed(2);
}

// Modal wrappers stay 'flex' only while visible — kept off the static class
// list (and toggled in lockstep with 'hidden' here) so the two never sit on
// the element at the same time.
function showKpiModal(id) {
    const el = document.getElementById(id);
    if (el) { el.classList.remove('hidden'); el.classList.add('flex'); }
}
function hideKpiModal(id) {
    const el = document.getElementById(id);
    if (el) { el.classList.add('hidden'); el.classList.remove('flex'); }
}

let _kpiReviewForm = null;

function submitKpiReview(e) {
    e.preventDefault();
    _kpiReviewForm = e.target;
    // "Send to HR" needs an explicit confirmation; "Save Draft" saves silently.
    if (window._kpiReviewAction === 'submit') {
        if (document.getElementById('submitReviewBtn')?.disabled) return;
        showKpiModal('reviewConfirmModal');
        return;
    }
    executeKpiReview();
}

async function executeKpiReview() {
    hideKpiModal('reviewConfirmModal');
    const form = _kpiReviewForm || document.getElementById('kpiReviewForm');
    const fd = new FormData(form);
    fd.append('action', window._kpiReviewAction || 'draft');
    const res  = await fetch('{{ $leadMode ? route($ttype === 'peer' ? "general.my-kpi.peer-review.submit" : "general.my-kpi.lead-review.submit", $evaluation->id) : route("general.kpi-evaluation.review.submit", $evaluation->id) }}', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        body: fd,
    });
    const data = await res.json();
    showToast(data.message, data.success ? 'success' : 'error');
    if (data.success) {
        // The assessor who just sent it to HR is done here: go back to their My KPI tab (it is read-only now).
        // A saved draft, or HR itself, stays on / reloads the page.
        const sent = (window._kpiReviewAction || 'draft') === 'submit';
        setTimeout(() => { if (sent && @json((bool) $isLeadReviewer)) { location.href = @json($backUrl); } else { location.reload(); } }, 1000);
    }
}

function openApproveModal() { showKpiModal('approveModal'); }
function openReviseModal() { showKpiModal('reviseModal'); }
async function confirmRevise() {
    const notes = document.getElementById('reviseNotes').value.trim();
    if (notes.length < 5) { showToast('Please explain what needs to change (at least 5 characters).', 'error'); return; }
    const form = new FormData(); form.append('hr_notes', notes);
    const res  = await fetch('{{ route("general.kpi-evaluation.reject", $evaluation->id) }}', {
        method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }, body: form,
    });
    const data = await res.json();
    hideKpiModal('reviseModal');
    showToast(data.message, data.success ? 'success' : 'error');
    if (data.success) setTimeout(() => location.reload(), 1000);
}
async function confirmApprove() {
    const notes = document.getElementById('approveNotes').value;
    const form  = new FormData(); form.append('hr_notes', notes);
    const res   = await fetch('{{ route("general.kpi-evaluation.approve", $evaluation->id) }}', {
        method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }, body: form,
    });
    const data = await res.json();
    hideKpiModal('approveModal');
    showToast(data.message, data.success ? 'success' : 'error');
    if (data.success) setTimeout(() => location.reload(), 1000);
}

async function publishUpwardAverage() {
    if (!await showConfirm('Publish the average across all approved rater submissions? The supervisor will see only this average — never an individual rater\'s score.', 'Publish Average', 'primary')) return;
    const res  = await fetch('{{ route("general.kpi-evaluation.publish-upward", $evaluation->id) }}', {
        method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
    });
    const data = await res.json();
    showToast(data.message, data.success ? 'success' : 'error');
    if (data.success) setTimeout(() => location.reload(), 1000);
}
</script>
@endsection

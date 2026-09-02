@extends('dashboard')

@section('title', 'My KPI')
@section('page-title', 'My KPI')

@section('content')
@php
    use Carbon\Carbon;
    $user = session('user');
    $empName = $user['name'] ?? 'Employee';
    $displayEval = $selectedEval ?: $currentEval;
@endphp

<div class="space-y-5">

    {{-- ── Header ──────────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-2xl primary-gradient flex items-center justify-center shadow-lg">
                    <i class="fas fa-chart-line text-white text-xl"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-gray-900">My KPI Dashboard</h1>
                    <p class="text-sm text-gray-500 mt-0.5">Track your performance, submit self-assessments, and view scorecard details</p>
                </div>
            </div>
            {{-- Pending self-assessment alert --}}
            @if($pendingSelfAssessment->count() > 0)
                <div class="flex items-center gap-2 bg-amber-50 border border-amber-200 rounded-xl px-4 py-2.5">
                    <i class="fas fa-exclamation-circle text-amber-500"></i>
                    <span class="text-sm font-medium text-amber-800">
                        {{ $pendingSelfAssessment->count() }} self-assessment{{ $pendingSelfAssessment->count() > 1 ? 's' : '' }} pending
                    </span>
                </div>
            @endif
        </div>
    </div>

    {{-- ── Summary Cards ────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">

        {{-- Current period score --}}
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 col-span-2 lg:col-span-1">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">Current Period</p>
            @if($currentEval && $currentEval->status === \App\Models\KpiEvaluation::STATUS_HR_APPROVED)
                <div class="flex items-end gap-2">
                    <span class="text-4xl font-bold text-gray-900">{{ number_format($currentEval->overall_score, 1) }}</span>
                    <span class="text-lg text-gray-400 mb-1">/ 100</span>
                </div>
                <p class="text-xs text-emerald-600 mt-1 flex items-center gap-1 font-semibold">
                    <i class="fas fa-check-circle"></i> HR Approved
                </p>
            @elseif($currentEval)
                <div class="text-3xl font-bold text-amber-500">Pending</div>
                <p class="text-xs mt-1 flex items-center gap-1">
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-700">
                        {{ $currentEval->status_label }}
                    </span>
                </p>
            @else
                <div class="text-3xl font-bold text-gray-300">—</div>
                <p class="text-xs text-gray-400 mt-1">No evaluation created for this period</p>
            @endif
        </div>

        {{-- All-time average --}}
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">Avg Score</p>
            <div class="text-3xl font-bold text-gray-900">
                {{ $avgScore ? number_format($avgScore, 1) : '—' }}
            </div>
            <p class="text-xs text-gray-400 mt-1">Approved evaluations</p>
        </div>

        {{-- Total evaluations --}}
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">Total Evals</p>
            <div class="text-3xl font-bold text-gray-900">{{ $evaluations->count() }}</div>
            <p class="text-xs text-gray-400 mt-1">All periods</p>
        </div>

        {{-- Pending --}}
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">Pending Self-Assess</p>
            <div class="text-3xl font-bold {{ $pendingSelfAssessment->count() > 0 ? 'text-amber-500' : 'text-gray-900' }}">
                {{ $pendingSelfAssessment->count() }}
            </div>
            <p class="text-xs text-gray-400 mt-1">Action due</p>
        </div>
    </div>

    {{-- ── Score Trend Chart ────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
        <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
            <i class="fas fa-chart-area text-indigo-400"></i>
            Score Trend — Last 6 Months
        </h3>
        <div class="relative" style="height: 160px;">
            <canvas id="scoreTrendChart"></canvas>
        </div>
    </div>

    {{-- ── Pending Self-Assessments Callout Banner ────────────────────────── --}}
    @if($pendingSelfAssessment->count() > 0)
    <div class="bg-amber-50 border border-amber-200 rounded-2xl p-5 shadow-sm space-y-3">
        <h3 class="text-sm font-bold text-amber-800 flex items-center gap-2">
            <i class="fas fa-pencil-alt text-amber-500"></i>
            Action Required — Unanswered Self-Assessments
        </h3>
        <div class="space-y-2">
            @foreach($pendingSelfAssessment as $eval)
            <div class="bg-white rounded-xl p-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 border border-amber-100 shadow-sm">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-gray-900 text-sm">{{ $eval->template?->name ?? 'KPI Template' }}</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-100 text-amber-700">
                            Unanswered Assessment
                        </span>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">
                        Period: <strong>{{ Carbon::createFromFormat('Y-m', $eval->period_month)->format('F Y') }}</strong>
                        @if($eval->supervisor)
                            &nbsp;&middot;&nbsp; Supervisor: {{ $eval->supervisor->basicData?->full_name ?? 'Assigned' }}
                        @endif
                    </p>
                </div>
                <a href="{{ route('general.my-kpi.self-assessment', $eval->id) }}"
                   class="inline-flex items-center px-4 py-2 primary-gradient text-white text-xs font-bold rounded-xl shadow hover:opacity-90 transition-all shrink-0">
                    Fill Self-Assessment Now
                </a>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- ── Evaluation Details Section (with Select Month Dropdown) ────────── --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-5 border-b border-gray-100 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <i class="fas fa-star text-yellow-400"></i>
                <h3 class="text-sm font-bold text-gray-800">
                    Evaluation Details
                </h3>
            </div>

            {{-- Select Month Dropdown Switcher --}}
            <div class="flex items-center gap-2">
                <label class="text-xs text-gray-500 font-semibold">Select Month:</label>
                <select onchange="window.location.href='?period='+this.value"
                    class="px-3 py-1.5 text-xs font-bold border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-300 bg-gray-50/80 cursor-pointer">
                    @foreach($evaluations as $e)
                        <option value="{{ $e->period_month }}" {{ ($selectedPeriod === $e->period_month) ? 'selected' : '' }}>
                            {{ Carbon::createFromFormat('Y-m', $e->period_month)->format('F Y') }}
                            ({{ $e->status_label }})
                        </option>
                    @endforeach
                    @if($evaluations->isEmpty())
                        <option value="{{ $currentPeriod }}">{{ Carbon::createFromFormat('Y-m', $currentPeriod)->format('F Y') }}</option>
                    @endif
                </select>
            </div>
        </div>

        @if($displayEval)
        <div class="p-5 space-y-4">
            @if(!$displayEval->hasSelfAssessment() && $displayEval->status !== \App\Models\KpiEvaluation::STATUS_HR_APPROVED)
                {{-- Clean Unanswered State: Display Only Once when still blank --}}
                <div class="p-8 bg-amber-50/50 rounded-2xl border border-amber-200/80 text-center space-y-4 my-2">
                    <div class="w-14 h-14 rounded-2xl bg-amber-100 text-amber-600 flex items-center justify-center mx-auto text-2xl shadow-sm">
                        <i class="fas fa-edit"></i>
                    </div>
                    <div class="space-y-1">
                        <h4 class="text-base font-bold text-amber-900">Current Period Self-Assessment — Evaluation Details</h4>
                        <p class="text-xs text-amber-800 max-w-md mx-auto leading-relaxed">
                            You have an open evaluation for <strong>{{ Carbon::createFromFormat('Y-m', $displayEval->period_month)->format('F Y') }}</strong> that has not been answered yet. Please submit your self-assessment to proceed with supervisor scoring.
                        </p>
                    </div>
                    <div class="pt-1">
                        <a href="{{ route('general.my-kpi.self-assessment', $displayEval->id) }}"
                           class="inline-flex items-center px-6 py-2.5 primary-gradient text-white text-xs font-bold rounded-xl shadow hover:opacity-90 transition-all">
                            Fill Self-Assessment Now
                        </a>
                    </div>
                </div>
            @else
                {{-- Answered / Evaluated State: Show Period Status Header & Full Breakdown --}}
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 p-4 rounded-xl {{ $displayEval->status === 'hr_approved' ? 'bg-emerald-50/70 border border-emerald-200' : 'bg-blue-50/70 border border-blue-200' }}">
                    <div>
                        <h4 class="font-bold text-sm text-gray-900">{{ $displayEval->template?->name ?? 'KPI Evaluation' }}</h4>
                        <p class="text-xs text-gray-500 mt-0.5">
                            Period: <strong>{{ Carbon::createFromFormat('Y-m', $displayEval->period_month)->format('F Y') }}</strong>
                            &nbsp;&middot;&nbsp; Evaluator: {{ $displayEval->supervisor?->basicData?->full_name ?? 'Supervisor' }}
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        @if($displayEval->status === \App\Models\KpiEvaluation::STATUS_HR_APPROVED)
                            <span class="text-2xl font-bold text-emerald-600">{{ number_format($displayEval->overall_score, 2) }}</span>
                            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                                <i class="fas fa-check-circle"></i> Approved
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-800">
                                <i class="fas fa-clock"></i> {{ $displayEval->status_label }}
                            </span>
                        @endif
                    </div>
                </div>

                {{-- Indicator Breakdown Table --}}
                <div class="overflow-x-auto border border-gray-100 rounded-xl">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50/80">
                            <tr>
                                <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">KPI Indicator</th>
                                <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider w-16">Weight</th>
                                <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider w-36">My Achievement</th>
                                <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider w-36">Supervisor Rating</th>
                                <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider w-24">Weighted</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach($displayEval->details->sortBy('indicator.order_seq') as $detail)
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <td class="px-5 py-3.5">
                                    <p class="font-semibold text-gray-900 text-xs">{{ $detail->indicator?->name ?? '—' }}</p>
                                    @if($detail->self_notes)
                                        <p class="text-[11px] text-gray-400 mt-0.5 italic">Self-note: {{ $detail->self_notes }}</p>
                                    @endif
                                    @if($detail->supervisor_notes && $displayEval->status === \App\Models\KpiEvaluation::STATUS_HR_APPROVED)
                                        <p class="text-[11px] text-indigo-500 mt-0.5 italic">Supervisor: {{ $detail->supervisor_notes }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 text-center font-semibold text-xs text-indigo-600">
                                    {{ $detail->indicator?->weight ?? 0 }}%
                                </td>
                                <td class="px-4 py-3.5 text-center">
                                    @if($detail->star_rating || !is_null($detail->self_achievement))
                                        <span class="text-xs font-bold text-blue-700">
                                            {{ $detail->actual_achievement ?: ($detail->star_rating ? "{$detail->star_rating}/5 stars" : number_format($detail->self_achievement, 1)) }}
                                        </span>
                                    @else
                                        <span class="text-gray-300 text-xs">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 text-center">
                                    @if($displayEval->status === \App\Models\KpiEvaluation::STATUS_HR_APPROVED && !is_null($detail->supervisor_score))
                                        <div class="flex items-center justify-center gap-0.5 text-amber-400 text-xs">
                                            @for($star = 1; $star <= 5; $star++)
                                                <span>{{ $star <= ($detail->star_rating ?? round($detail->supervisor_score/20)) ? '★' : '☆' }}</span>
                                            @endfor
                                        </div>
                                    @else
                                        <span class="text-gray-300 text-xs">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 text-center">
                                    @if($displayEval->status === \App\Models\KpiEvaluation::STATUS_HR_APPROVED && !is_null($detail->weighted_score))
                                        <span class="text-xs font-bold text-gray-900">{{ number_format($detail->weighted_score, 2) }}</span>
                                    @else
                                        <span class="text-gray-300 text-xs">—</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($displayEval->hr_notes && $displayEval->status === \App\Models\KpiEvaluation::STATUS_HR_APPROVED)
                <div class="p-4 bg-blue-50/70 border border-blue-100 rounded-xl">
                    <p class="text-xs font-bold text-blue-800 mb-0.5"><i class="fas fa-comment-alt mr-1"></i> HR Notes</p>
                    <p class="text-xs text-blue-900">{{ $displayEval->hr_notes }}</p>
                </div>
                @endif
            @endif
        </div>
        @else
        <div class="text-center py-12">
            <p class="text-xs text-gray-400">No evaluation record found for the selected period.</p>
        </div>
        @endif
    </div>

    {{-- ── Supervisor Section: Team Member Evaluations ──────────────────────── --}}
    @if(!empty($isSupervisor))
    <div class="bg-white rounded-2xl shadow-sm border border-indigo-100 overflow-hidden">
        <div class="p-5 border-b border-indigo-50 bg-indigo-50/30 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
            <div>
                <h3 class="text-sm font-bold text-gray-900 flex items-center gap-2">
                    <span class="w-7 h-7 rounded-xl bg-indigo-600 text-white flex items-center justify-center text-xs">
                        <i class="fas fa-users"></i>
                    </span>
                    My Team KPI Evaluations — {{ Carbon::createFromFormat('Y-m', $selectedPeriod ?? $currentPeriod)->format('F Y') }}
                </h3>
                <p class="text-xs text-gray-500 mt-0.5">
                    Evaluate your direct team members using the shared position KPI template.
                </p>
            </div>
            <div class="flex items-center gap-2 text-xs font-semibold text-indigo-700 bg-white px-3.5 py-1.5 rounded-xl border border-indigo-100 shadow-sm">
                <i class="fas fa-user-check text-indigo-500"></i>
                <span>Direct Supervisor Role</span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider w-12">No</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Team Member</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Position</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">KPI Template</th>
                        <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Self-Assessment</th>
                        <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Supervisor Review</th>
                        <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider w-24">Score</th>
                        <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider w-40">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @php
                        $subordinateList = $subordinates ?? collect([]);
                        if ($subordinateList->isEmpty() && isset($assignedEvaluations)) {
                            $subordinateList = $assignedEvaluations->map(fn($ae) => $ae->employee)->filter()->unique('employee_id');
                        }
                    @endphp
                    @forelse($subordinateList as $subEmp)
                    @php
                        $tEval = $subEmp->kpiEvaluations?->firstWhere('period_month', $selectedPeriod ?? $currentPeriod)
                            ?? ($assignedEvaluations ?? collect([]))->firstWhere('employee_id', $subEmp->employee_id);
                        $sBd = $subEmp->basicData;
                        $tSelfDone = $tEval?->hasSelfAssessment();
                        $tSupDone = $tEval?->hasSupervisorReview();
                        $tIsApproved = $tEval && $tEval->status === \App\Models\KpiEvaluation::STATUS_HR_APPROVED;
                    @endphp
                    <tr class="hover:bg-indigo-50/20 transition-colors">
                        <td class="px-5 py-3.5 text-gray-400 text-xs font-medium">{{ $loop->iteration }}</td>
                        <td class="px-5 py-3.5">
                            <p class="font-semibold text-gray-900 text-sm">{{ $sBd?->full_name ?? $subEmp->eci }}</p>
                            <p class="text-xs text-indigo-500 font-mono">{{ $subEmp->eci }}</p>
                        </td>
                        <td class="px-4 py-3.5 text-xs text-gray-600">{{ $sBd?->position ?? 'Staff' }}</td>
                        <td class="px-4 py-3.5 text-xs">
                            @if($tEval)
                                <span class="font-medium text-indigo-600">{{ $tEval->template?->name ?? '—' }}</span>
                            @else
                                <span class="text-gray-400 italic">Template assigned by HR</span>
                            @endif
                        </td>
                        <td class="px-4 py-3.5 text-center">
                            @if($tSelfDone)
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                                    <i class="fas fa-check-circle"></i> Submitted
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">
                                    <i class="fas fa-clock"></i> Pending
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3.5 text-center">
                            @if($tIsApproved)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                                    Approved
                                </span>
                            @elseif($tSupDone)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800">
                                    Reviewed
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">
                                    To Evaluate
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3.5 text-center font-bold text-sm">
                            @if($tEval && $tEval->overall_score)
                                <span class="text-gray-900">{{ number_format($tEval->overall_score, 1) }}</span>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3.5 text-center">
                            @if($tEval)
                                <a href="{{ route('general.kpi-evaluation.review', $tEval->id) }}"
                                   class="inline-flex items-center px-3.5 py-1.5 rounded-xl text-xs font-bold shadow-sm transition-all {{ $tSupDone ? 'bg-indigo-50 text-indigo-700 border border-indigo-200 hover:bg-indigo-100' : 'primary-gradient text-white hover:opacity-90' }}">
                                    {{ $tSupDone ? ($tIsApproved ? 'View' : 'Edit Review') : 'Evaluate' }}
                                </a>
                            @else
                                <span class="text-xs text-gray-400 italic">No evaluation record</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="py-8 text-center text-gray-400 text-xs">No direct team members found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- ── Evaluation History Table ─────────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-5 border-b border-gray-100 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
            <div>
                <h3 class="text-sm font-semibold text-gray-700 flex items-center gap-2">
                    <i class="fas fa-history text-gray-400"></i>
                    All Evaluation Periods History
                </h3>
                <p class="text-[11px] text-gray-400 mt-0.5">
                    Each period displays two template rows: Self-Assessment (Evaluasi Mandiri) and Supervisor Evaluation (Penilaian Atasan), followed by the Final Score and Action.
                </p>
            </div>
            @php
                $availableYears = $evaluations->map(fn($e) => substr($e->period_month, 0, 4))->unique()->values();
            @endphp
            @if($availableYears->count() > 1)
            {{-- Filter Year (Custom Dropdown) --}}
            <div class="flex items-center gap-2 shrink-0">
                <span class="text-xs font-semibold text-gray-500">Filter Year:</span>
                <div class="custom-dd relative" data-onchange="onHistoryYearFilterChange" data-fixed="true">
                    <button type="button" class="custom-dd-btn inline-flex items-center justify-between gap-2 px-3 py-1.5 border border-gray-200 rounded-xl text-xs bg-white hover:border-gray-300 transition-all font-medium shadow-sm min-w-[120px]">
                        <span class="custom-dd-label text-gray-700 truncate" id="historyYearLabel">All Years</span>
                        <svg class="custom-dd-arrow w-3 h-3 text-gray-400 transition-all duration-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <input type="hidden" id="historyYearInput" value="">
                    <div class="custom-dd-panel hidden absolute top-full right-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="min-width: 130px; max-height: 200px;">
                        <button type="button" class="custom-dd-item w-full text-left px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50 font-bold text-[var(--primary-color)]" data-value="">All Years</button>
                        @foreach($availableYears as $yr)
                            <button type="button" class="custom-dd-item w-full text-left px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50" data-value="{{ $yr }}">{{ $yr }}</button>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif
        </div>

        @if($evaluations->count() > 0)
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Period</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Template & Type</th>
                        <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Score</th>
                        <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Deadline</th>
                        <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Final Score</th>
                        <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100" id="historyTableBody">
                    @foreach($evaluations as $eval)
                    @php
                        $evalYear = substr($eval->period_month, 0, 4);
                        $isApproved = $eval->status === \App\Models\KpiEvaluation::STATUS_HR_APPROVED;
                        $needsSelf  = !$eval->hasSelfAssessment() && !$isApproved;
                        $selfDone   = $eval->hasSelfAssessment();
                        $selfOverdue = $eval->self_deadline && !$selfDone && now()->gt($eval->self_deadline);

                        $supDone    = $eval->hasSupervisorReview();
                        $supOverdue = $eval->supervisor_deadline && !$supDone && now()->gt($eval->supervisor_deadline);

                        $supervisorAvgScore = $isApproved && $eval->details->isNotEmpty()
                            ? $eval->details->whereNotNull('supervisor_score')->avg('supervisor_score')
                            : null;

                        $selfAvgScore = $selfDone && $eval->details->isNotEmpty()
                            ? $eval->details->whereNotNull('self_achievement')->avg('self_achievement')
                            : null;
                    @endphp

                    {{-- Sub-row 1: Self-Assessment --}}
                    <tr class="history-row hover:bg-gray-50/80 transition-colors {{ $needsSelf ? 'bg-amber-50/30' : '' }}" data-year="{{ $evalYear }}">
                        <td rowspan="2" class="px-5 py-4 font-semibold text-gray-900 align-middle border-r border-gray-100">
                            {{ Carbon::createFromFormat('Y-m', $eval->period_month)->format('M Y') }}
                        </td>
                        {{-- Self Template --}}
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-purple-100 text-purple-700">Self</span>
                                <span class="font-medium text-gray-800 text-xs">{{ $eval->template?->name ?? 'Self-Assessment Template' }}</span>
                            </div>
                            <p class="text-[11px] text-gray-400 mt-0.5">Evaluasi Mandiri Staf</p>
                        </td>
                        {{-- Self Score --}}
                        <td class="px-4 py-3 text-center font-semibold text-xs">
                            @if($selfDone && $selfAvgScore !== null)
                                <span class="text-gray-800">{{ number_format($selfAvgScore, 1) }}</span>
                            @elseif($selfDone)
                                <span class="text-emerald-600 font-medium"><i class="fas fa-check-circle"></i> Submitted</span>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        {{-- Self Deadline --}}
                        <td class="px-4 py-3 text-center text-xs">
                            @if($eval->self_deadline)
                                <span class="{{ $selfOverdue ? 'text-red-600 font-bold' : 'text-gray-500' }}">
                                    {{ $eval->self_deadline->format('d M Y') }}
                                </span>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        {{-- Self Status --}}
                        <td class="px-4 py-3 text-center">
                            @if($selfDone)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                                    Self-Assessed
                                </span>
                            @elseif($selfOverdue)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800">
                                    Overdue
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">
                                    Pending Self
                                </span>
                            @endif
                        </td>
                        {{-- Final Score (Rowspan 2) --}}
                        <td rowspan="2" class="px-4 py-4 text-center font-bold align-middle border-l border-r border-gray-100">
                            @if($isApproved && $eval->overall_score)
                                <span class="text-xl text-emerald-600 font-black block">{{ number_format($eval->overall_score, 1) }}</span>
                                <span class="text-[10px] text-gray-400 font-normal">/ 100</span>
                            @elseif($isApproved)
                                <span class="text-xs text-emerald-600 font-bold">Approved</span>
                            @else
                                <span class="text-xs text-amber-600 font-semibold block">{{ $eval->status_label }}</span>
                            @endif
                        </td>
                        {{-- Action (Rowspan 2) --}}
                        <td rowspan="2" class="px-4 py-4 text-center align-middle">
                            <div class="flex flex-col items-center gap-1.5 justify-center">
                                @if($needsSelf)
                                    <a href="{{ route('general.my-kpi.self-assessment', $eval->id) }}"
                                       class="inline-flex items-center px-3.5 py-1.5 primary-gradient text-white text-xs font-bold rounded-xl shadow hover:opacity-90 transition-all w-full justify-center">
                                        Fill Self-Assessment
                                    </a>
                                @endif
                                <a href="?period={{ $eval->period_month }}"
                                   class="inline-flex items-center px-3.5 py-1.5 bg-gray-100 text-gray-700 text-xs font-medium rounded-xl hover:bg-gray-200 transition-all w-full justify-center">
                                    View Result
                                </a>
                            </div>
                        </td>
                    </tr>

                    {{-- Sub-row 2: Supervisor Evaluation --}}
                    <tr class="history-row hover:bg-gray-50/80 transition-colors {{ $needsSelf ? 'bg-amber-50/30' : '' }} border-b border-gray-200" data-year="{{ $evalYear }}">
                        {{-- Supervisor Template --}}
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-100 text-indigo-700">Supervisor</span>
                                <span class="font-medium text-gray-800 text-xs">
                                    {{ $eval->supervisor?->basicData?->full_name ? 'Penilaian Atasan: '.$eval->supervisor->basicData->full_name : 'Penilaian Atasan' }}
                                </span>
                            </div>
                            <p class="text-[11px] text-gray-400 mt-0.5">Penilaian Direct Manager</p>
                        </td>
                        {{-- Supervisor Score --}}
                        <td class="px-4 py-3 text-center font-semibold text-xs">
                            @if($isApproved && $supervisorAvgScore !== null)
                                <div class="flex items-center justify-center gap-0.5 text-amber-400 text-xs mb-0.5">
                                    @for($s=1; $s<=5; $s++)
                                        <span>{{ $s <= round($supervisorAvgScore/20) ? '★' : '☆' }}</span>
                                    @endfor
                                </div>
                                <span class="text-xs text-gray-700">{{ number_format($supervisorAvgScore, 1) }}</span>
                            @elseif($supDone)
                                <span class="text-indigo-600 font-medium">Reviewed</span>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        {{-- Supervisor Deadline --}}
                        <td class="px-4 py-3 text-center text-xs">
                            @if($eval->supervisor_deadline)
                                <span class="{{ $supOverdue ? 'text-red-600 font-bold' : 'text-gray-500' }}">
                                    {{ $eval->supervisor_deadline->format('d M Y') }}
                                </span>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        {{-- Supervisor Status --}}
                        <td class="px-4 py-3 text-center">
                            @if($isApproved)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                                    Approved
                                </span>
                            @elseif($supDone)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800">
                                    Reviewed
                                </span>
                            @elseif($supOverdue)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800">
                                    Overdue
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600">
                                    Awaiting Review
                                </span>
                            @endif
                        </td>
                    </tr>
                    @endforeach

                    {{-- Empty state when filtered year has no results --}}
                    <tr id="historyEmptyYearRow" class="hidden">
                        <td colspan="7" class="text-center py-10 text-gray-400 text-xs">
                            <i class="fas fa-calendar-times text-3xl mb-2 text-gray-300 block"></i>
                            No evaluation history found for the selected year.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        @endif
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const trendData = @json($scoreTrend);
const ctx = document.getElementById('scoreTrendChart');
if (ctx) {
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: trendData.map(d => d.label),
            datasets: [{
                label: 'KPI Score',
                data: trendData.map(d => d.score),
                borderColor: '#7C3AED',
                backgroundColor: 'rgba(124,58,237,0.08)',
                borderWidth: 2.5,
                pointBackgroundColor: '#7C3AED',
                tension: 0.4,
                fill: true,
                spanGaps: true,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { min: 0, max: 100 }, x: { grid: { display: false } } }
        }
    });
}

function onHistoryYearFilterChange(val) {
    const selectedYear = (typeof val === 'string' ? val : '') || document.getElementById('historyYearInput')?.value || '';
    const rows = document.querySelectorAll('#historyTableBody tr.history-row');
    let count = 0;
    rows.forEach(r => {
        const y = r.getAttribute('data-year');
        if (!selectedYear || y === selectedYear) {
            r.style.display = '';
            count++;
        } else {
            r.style.display = 'none';
        }
    });
    const emptyRow = document.getElementById('historyEmptyYearRow');
    if (emptyRow) {
        emptyRow.classList.toggle('hidden', count > 0);
    }
}
</script>
@php
    $customDdVer = file_exists(public_path('js/custom-dropdown.js')) ? filemtime(public_path('js/custom-dropdown.js')) : 1;
@endphp
<script src="/js/custom-dropdown.js?v={{ $customDdVer }}"></script>
@endsection



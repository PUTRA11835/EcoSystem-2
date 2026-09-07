@extends('dashboard')

@section('title', 'My KPI')
@section('page-title', 'My KPI')

@section('content')
@php
    use Carbon\Carbon;
    use App\Models\KpiEvaluation;
    $user = session('user');
    $empName = $user['name'] ?? 'Employee';
    $isSupervisor = !empty($isSupervisor);
    // Only HR / KPI-Evaluation users see the per-indicator breakdown of a lead
    // assessment. A regular employee sees just the overall score and comment.
    $canSeeLeadDetails = ($can ?? fn($p) => false)('general.kpi-evaluation');
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
                    <h1 class="text-xl font-bold text-gray-900">My KPI</h1>
                    <p class="text-sm text-gray-500 mt-0.5">Fill your monthly self-assessments and review the assessment your lead gives you</p>
                </div>
            </div>
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
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 col-span-2 lg:col-span-1">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">Current Period</p>
            @if($currentEval && $currentEval->status === KpiEvaluation::STATUS_HR_APPROVED)
                <div class="flex items-end gap-2">
                    <span class="text-4xl font-bold text-gray-900">{{ number_format($currentEval->overall_score, 1) }}</span>
                    <span class="text-lg text-gray-400 mb-1">/ 100</span>
                </div>
                <p class="text-xs text-emerald-600 mt-1 flex items-center gap-1 font-semibold">
                    <i class="fas fa-check-circle"></i> HR Approved
                </p>
            @elseif($currentEval)
                <div class="text-3xl font-bold text-amber-500">Pending</div>
                <p class="text-xs mt-1">
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-700">
                        {{ $currentEval->status_label }}
                    </span>
                </p>
            @else
                <div class="text-3xl font-bold text-gray-300">—</div>
                <p class="text-xs text-gray-400 mt-1">No evaluation for this period</p>
            @endif
        </div>

        <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">Avg Score</p>
            <div class="text-3xl font-bold text-gray-900">{{ $avgScore ? number_format($avgScore, 1) : '—' }}</div>
            <p class="text-xs text-gray-400 mt-1">Approved evaluations</p>
        </div>

        <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">Total Evals</p>
            <div class="text-3xl font-bold text-gray-900">{{ $evaluations->count() }}</div>
            <p class="text-xs text-gray-400 mt-1">Self + Lead, all periods</p>
        </div>

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

    {{-- ── Tab Strip ────────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl p-1.5 shadow-sm border border-gray-100 flex items-center gap-1.5 flex-wrap">
        <button type="button" data-tab="self" onclick="showKpiTab('self')"
            class="kpi-tab-btn flex-1 sm:flex-none text-center px-4 py-2 rounded-xl text-xs font-bold primary-gradient text-white shadow transition-all">
            <i class="fas fa-pen-to-square mr-1.5"></i> Self-Assessment
        </button>
        <button type="button" data-tab="lead" onclick="showKpiTab('lead')"
            class="kpi-tab-btn flex-1 sm:flex-none text-center px-4 py-2 rounded-xl text-xs font-bold text-gray-500 hover:bg-gray-50 transition-all">
            <i class="fas fa-user-tie mr-1.5"></i> Lead Assessment
        </button>
        @if($isSupervisor)
        <button type="button" data-tab="team" onclick="showKpiTab('team')"
            class="kpi-tab-btn flex-1 sm:flex-none text-center px-4 py-2 rounded-xl text-xs font-bold text-gray-500 hover:bg-gray-50 transition-all">
            <i class="fas fa-users mr-1.5"></i> My Team
        </button>
        @endif
    </div>

    {{-- ══════════════════ TAB: SELF-ASSESSMENT ══════════════════ --}}
    <div class="kpi-tab-panel space-y-5" data-tab="self">

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
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-100 text-amber-700">Unanswered</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">
                            Period: <strong>{{ Carbon::createFromFormat('Y-m', $eval->period_month)->format('F Y') }}</strong>
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

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-5 border-b border-gray-100 flex items-center gap-2">
                <i class="fas fa-list-check text-purple-400"></i>
                <h3 class="text-sm font-bold text-gray-800">Monthly Self-Assessments</h3>
            </div>

            @if($selfEvals->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Period</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Template</th>
                            <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Score</th>
                            <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Deadline</th>
                            <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($selfEvals->sortByDesc('period_month') as $eval)
                        @php
                            $done      = $eval->hasSelfAssessment();
                            $approved  = $eval->status === KpiEvaluation::STATUS_HR_APPROVED;
                            $overdue   = $eval->self_deadline && !$done && now()->gt($eval->self_deadline);
                        @endphp
                        <tr class="hover:bg-gray-50/70 transition-colors {{ (!$done && !$approved) ? 'bg-amber-50/30' : '' }}">
                            <td class="px-5 py-3.5 font-semibold text-gray-900 text-xs">
                                {{ Carbon::createFromFormat('Y-m', $eval->period_month)->format('M Y') }}
                            </td>
                            <td class="px-5 py-3.5 text-xs text-gray-700">{{ $eval->template?->name ?? 'Self-Assessment' }}</td>
                            <td class="px-4 py-3.5 text-center text-xs font-bold">
                                @if($eval->overall_score !== null && $done)
                                    <span class="text-gray-900">{{ number_format($eval->overall_score, 1) }}</span>
                                @elseif($done)
                                    <span class="text-emerald-600 font-medium">Submitted</span>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-center text-xs {{ $overdue ? 'text-red-600 font-bold' : 'text-gray-500' }}">
                                {{ $eval->self_deadline ? $eval->self_deadline->format('d M Y') : '—' }}
                            </td>
                            <td class="px-4 py-3.5 text-center">
                                @if($approved)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">Approved</span>
                                @elseif($done)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">Self-Assessed</span>
                                @elseif($overdue)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800">Overdue</span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">Pending</span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-center">
                                <a href="{{ route('general.my-kpi.self-assessment', $eval->id) }}"
                                   class="inline-flex items-center px-3.5 py-1.5 rounded-xl text-xs font-bold shadow-sm transition-all {{ ($done || $approved) ? 'bg-indigo-50 text-indigo-700 border border-indigo-200 hover:bg-indigo-100' : 'primary-gradient text-white hover:opacity-90' }}">
                                    {{ ($done || $approved) ? 'View Details' : 'Fill Self-Assessment' }}
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="text-center py-12">
                <p class="text-xs text-gray-400">No self-assessment templates have been assigned to you yet.</p>
            </div>
            @endif
        </div>
    </div>

    {{-- ══════════════════ TAB: LEAD ASSESSMENT ══════════════════ --}}
    <div class="kpi-tab-panel space-y-5 hidden" data-tab="lead">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-5 border-b border-gray-100 flex items-center gap-2">
                <i class="fas fa-user-tie text-indigo-400"></i>
                <h3 class="text-sm font-bold text-gray-800">Assessments From My Lead</h3>
            </div>

            @if($leadEvals->count() > 0)
            <div class="divide-y divide-gray-100">
                @foreach($leadEvals->sortByDesc('period_month') as $eval)
                @php
                    $reviewed = $eval->hasSupervisorReview();
                    $approved = $eval->status === KpiEvaluation::STATUS_HR_APPROVED;
                @endphp
                <div class="p-5 space-y-3">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-bold text-gray-900 text-sm">{{ $eval->template?->name ?? 'Lead Assessment' }}</span>
                                <span class="text-xs text-gray-400">·</span>
                                <span class="text-xs text-gray-500">{{ Carbon::createFromFormat('Y-m', $eval->period_month)->format('F Y') }}</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-0.5">
                                Lead: {{ $eval->supervisor?->basicData?->full_name ?? 'Assigned' }}
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            @if($reviewed)
                                <span class="text-2xl font-bold {{ $approved ? 'text-emerald-600' : 'text-indigo-600' }}">
                                    {{ number_format($eval->overall_score ?? 0, 1) }}
                                </span>
                            @endif
                            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold
                                {{ $approved ? 'bg-emerald-100 text-emerald-800' : ($reviewed ? 'bg-indigo-100 text-indigo-800' : 'bg-amber-100 text-amber-800') }}">
                                {{ $approved ? 'Approved' : ($reviewed ? 'Reviewed by Lead' : 'Awaiting Lead Review') }}
                            </span>
                        </div>
                    </div>

                    @if($reviewed)
                        @if($canSeeLeadDetails)
                        {{-- HR / KPI-Evaluation users: full per-indicator breakdown --}}
                        <div class="overflow-x-auto border border-gray-100 rounded-xl">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50/80">
                                    <tr>
                                        <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Indicator</th>
                                        <th class="text-center px-4 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wider w-16">Weight</th>
                                        <th class="text-center px-4 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wider w-36">Lead Rating</th>
                                        <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Lead Notes</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    @foreach($eval->details->sortBy('indicator.order_seq') as $detail)
                                    <tr>
                                        <td class="px-5 py-3 font-semibold text-gray-900 text-xs">{{ $detail->indicator?->name ?? '—' }}</td>
                                        <td class="px-4 py-3 text-center text-xs font-semibold text-indigo-600">{{ $detail->indicator?->weight ?? 0 }}%</td>
                                        <td class="px-4 py-3 text-center">
                                            @if(!is_null($detail->supervisor_score))
                                                <div class="flex items-center justify-center gap-0.5 text-amber-400 text-xs">
                                                    @for($s = 1; $s <= 5; $s++)
                                                        <span>{{ $s <= ($detail->star_rating ?? round($detail->supervisor_score / 20)) ? '★' : '☆' }}</span>
                                                    @endfor
                                                </div>
                                                <span class="text-[11px] text-gray-500">{{ number_format($detail->supervisor_score, 1) }}</span>
                                            @else
                                                <span class="text-gray-300 text-xs">—</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-xs text-gray-600 italic">{{ $detail->supervisor_notes ?: '—' }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @else
                        {{-- Regular employee: score + overall note only, no indicator detail --}}
                        <div class="flex items-center gap-4 p-4 bg-gray-50 border border-gray-100 rounded-xl">
                            <div class="text-center">
                                <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Overall Score</p>
                                <p class="text-2xl font-bold text-gray-900">{{ number_format($eval->overall_score ?? 0, 1) }}<span class="text-sm text-gray-400"> / 100</span></p>
                            </div>
                        </div>
                        @endif

                    @if($eval->general_notes)
                    <div class="p-4 bg-indigo-50/60 border border-indigo-100 rounded-xl">
                        <p class="text-xs font-bold text-indigo-800 mb-0.5"><i class="fas fa-comment-alt mr-1"></i> Note from Lead</p>
                        <p class="text-xs text-indigo-900">{{ $eval->general_notes }}</p>
                    </div>
                    @endif

                    @if($eval->hr_notes && $approved)
                    <div class="p-4 bg-blue-50/70 border border-blue-100 rounded-xl">
                        <p class="text-xs font-bold text-blue-800 mb-0.5"><i class="fas fa-comment-alt mr-1"></i> HR Notes</p>
                        <p class="text-xs text-blue-900">{{ $eval->hr_notes }}</p>
                    </div>
                    @endif
                    @else
                    <p class="text-xs text-gray-400 italic">Your lead has not submitted this assessment yet.</p>
                    @endif
                </div>
                @endforeach
            </div>
            @else
            <div class="text-center py-12">
                <p class="text-xs text-gray-400">No lead-assessment templates have been assigned to you yet.</p>
            </div>
            @endif
        </div>
    </div>

    {{-- ══════════════════ TAB: MY TEAM (leads only) ══════════════════ --}}
    @if($isSupervisor)
    <div class="kpi-tab-panel space-y-5 hidden" data-tab="team">
        <div class="bg-white rounded-2xl shadow-sm border border-indigo-100 overflow-hidden">
            <div class="p-5 border-b border-indigo-50 bg-indigo-50/30 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm font-bold text-gray-900 flex items-center gap-2">
                        <span class="w-7 h-7 rounded-xl bg-indigo-600 text-white flex items-center justify-center text-xs">
                            <i class="fas fa-users"></i>
                        </span>
                        My Team — Lead Assessments — {{ Carbon::createFromFormat('Y-m', $selectedPeriod ?? $currentPeriod)->format('F Y') }}
                    </h3>
                    <p class="text-xs text-gray-500 mt-0.5">
                        Score and comment on your direct reports using the assigned lead-assessment template.
                    </p>
                </div>
                <div class="flex items-center gap-2 text-xs font-semibold text-indigo-700 bg-white px-3.5 py-1.5 rounded-xl border border-indigo-100 shadow-sm">
                    <i class="fas fa-user-check text-indigo-500"></i>
                    <span>Direct Lead Role</span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider w-12">No</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Team Member</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Position</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Template</th>
                            <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Lead Review</th>
                            <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider w-24">Score</th>
                            <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider w-40">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @php
                            $teamEvals = ($assignedEvaluations ?? collect())
                                ->filter(fn($e) => $e->isLeadType())
                                ->values();
                        @endphp
                        @forelse($teamEvals as $tEval)
                        @php
                            $sBd = $tEval->employee?->basicData;
                            $tSupDone = $tEval->hasSupervisorReview();
                            $tIsApproved = $tEval->status === KpiEvaluation::STATUS_HR_APPROVED;
                        @endphp
                        <tr class="hover:bg-indigo-50/20 transition-colors">
                            <td class="px-5 py-3.5 text-gray-400 text-xs font-medium">{{ $loop->iteration }}</td>
                            <td class="px-5 py-3.5">
                                <p class="font-semibold text-gray-900 text-sm">{{ $sBd?->full_name ?? $tEval->employee?->eci }}</p>
                                <p class="text-xs text-indigo-500 font-mono">{{ $tEval->employee?->eci }}</p>
                            </td>
                            <td class="px-4 py-3.5 text-xs text-gray-600">{{ $sBd?->position ?? 'Staff' }}</td>
                            <td class="px-4 py-3.5 text-xs"><span class="font-medium text-indigo-600">{{ $tEval->template?->name ?? '—' }}</span></td>
                            <td class="px-4 py-3.5 text-center">
                                @if($tIsApproved)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">Approved</span>
                                @elseif($tSupDone)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800">Reviewed</span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">To Evaluate</span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-center font-bold text-sm">
                                {{ $tEval->overall_score ? number_format($tEval->overall_score, 1) : '—' }}
                            </td>
                            <td class="px-4 py-3.5 text-center">
                                <a href="{{ route('general.kpi-evaluation.review', $tEval->id) }}"
                                   class="inline-flex items-center px-3.5 py-1.5 rounded-xl text-xs font-bold shadow-sm transition-all {{ $tSupDone ? 'bg-indigo-50 text-indigo-700 border border-indigo-200 hover:bg-indigo-100' : 'primary-gradient text-white hover:opacity-90' }}">
                                    {{ $tSupDone ? ($tIsApproved ? 'View' : 'Edit Review') : 'Evaluate & Comment' }}
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-gray-400 text-xs">
                                No lead-assessment assignments for your team this period.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
function showKpiTab(key) {
    document.querySelectorAll('.kpi-tab-panel').forEach(p => {
        p.classList.toggle('hidden', p.getAttribute('data-tab') !== key);
    });
    document.querySelectorAll('.kpi-tab-btn').forEach(b => {
        const active = b.getAttribute('data-tab') === key;
        b.className = 'kpi-tab-btn flex-1 sm:flex-none text-center px-4 py-2 rounded-xl text-xs font-bold transition-all '
            + (active ? 'primary-gradient text-white shadow' : 'text-gray-500 hover:bg-gray-50');
    });
    try { history.replaceState(null, '', '?tab=' + key); } catch (e) {}
}
(function () {
    const initial = new URLSearchParams(location.search).get('tab');
    if (initial && document.querySelector(`.kpi-tab-panel[data-tab="${initial}"]`)) showKpiTab(initial);
})();

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
</script>
@endsection

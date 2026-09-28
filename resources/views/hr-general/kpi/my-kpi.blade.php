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
    // System administrators (EC Administrator) always see every tab.
    $isSystemAdmin = !empty($isSystemAdmin);
    $canTab = fn($t) => $isSystemAdmin || ($can ?? fn($p) => true)('general.my-kpi.tab-' . $t);
    // Lead-type rows include peer templates; peers get their own tab.
    $isPeerEval    = fn($e) => ($e->template?->target_type ?? '') === 'peer';
    $leadOnlyEvals = $leadEvals->reject($isPeerEval)->values();
    $peerReceived  = $leadEvals->filter($isPeerEval)->values();
    $peerAssigned  = collect($assignedEvaluations ?? [])->filter($isPeerEval)->values();
    // "Has a leader": at least one of my lead-type evaluations is assigned to someone to review it.
    $hasLeader = $leadOnlyEvals->contains(fn($e) => !empty($e->supervisor_id));
    // "Leads a team" = has direct reports, or has (had) lead-type assessments to score.
    // $isSupervisor is broader — it is also true for a peer reviewer — so it is not used here.
    $leadsTeam = ($subordinates ?? collect())->isNotEmpty()
        || collect($assignedEvaluations ?? [])->contains(fn($e) => $e->isLeadType() && !$isPeerEval($e))
        || collect($reviewHistory ?? [])->contains(fn($e) => !$isPeerEval($e));
    $showSelfTab   = $canTab('self');
    $showLeadTab   = $canTab('lead') && ($isSystemAdmin || $leadsTeam || $hasLeader);
    $showPeerTab   = $canTab('peer') && ($isSystemAdmin || $peerReceived->isNotEmpty() || $peerAssigned->isNotEmpty());
    $showUpwardTab = $canTab('upward');
    $firstTab = collect(['self' => $showSelfTab, 'lead' => $showLeadTab, 'peer' => $showPeerTab, 'upward' => $showUpwardTab])
        ->filter()->keys()->first() ?? 'self';
    // Only HR / KPI-Evaluation users see the per-indicator breakdown of a lead
    // assessment. A regular employee sees just the overall score and comment.
    $canSeeLeadDetails = ($can ?? fn($p) => false)('general.kpi-evaluation');
    $upwardFeedback = $upwardFeedback ?? collect();
    $upwardEvals = $upwardEvals ?? collect();
    // Three fixed tabs: Self, Lead (includes the "My Team" evaluate table for
    // supervisors), Upward (fill-in for raters + the anonymized average
    // received from subordinates). Self and Upward are both filled via the
    // same self_* pathway but live in separate tabs now.
    $isDone = fn($e) => !$e->hasSelfAssessment() && !in_array($e->status, [KpiEvaluation::STATUS_HR_APPROVED]);
    $selfPending   = $selfEvals->filter($isDone)->values();
    $upwardPending = $upwardEvals->filter($isDone)->values();
    $pendingSelfAssessment = $selfPending->concat($upwardPending);
    // Per-source averages over every submitted score, same basis as the trend chart.
    $selfAvgScore = $selfEvals->filter(fn($e) => $e->hasSelfAssessment() && $e->overall_score !== null)->avg('overall_score');
    $leadAvgScore = $leadEvals->filter(fn($e) => $e->hasSupervisorReview() && $e->overall_score !== null)->avg('overall_score');
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
                        {{ $pendingSelfAssessment->count() }} assessment{{ $pendingSelfAssessment->count() > 1 ? 's' : '' }} pending
                    </span>
                </div>
            @endif
        </div>
    </div>

    {{-- ── Summary Cards ────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 col-span-2 lg:col-span-1 flex flex-col items-center justify-center text-center">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">Current Period</p>
            @php
                // Self-assessments need no HR approval: their score shows as soon as it is submitted.
                $currentSelfDone = $currentEval && $currentEval->isSelfType() && $currentEval->hasSelfAssessment() && $currentEval->overall_score !== null;
            @endphp
            @if($currentEval && ($currentEval->status === KpiEvaluation::STATUS_HR_APPROVED || $currentSelfDone))
                <div class="flex items-end justify-center gap-2">
                    <span class="text-4xl font-bold text-gray-900">{{ number_format($currentEval->overall_score, 1) }}</span>
                    <span class="text-lg text-gray-400 mb-1">/ 100</span>
                </div>
                <p class="text-xs text-emerald-600 mt-1 flex items-center justify-center gap-1 font-semibold">
                    <i class="fas fa-check-circle"></i> {{ $currentSelfDone && $currentEval->status !== KpiEvaluation::STATUS_HR_APPROVED ? 'Self-assessed' : 'HR Approved' }}
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

        <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 flex flex-col items-center justify-center text-center">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">Avg Score</p>
            <div class="text-3xl font-bold text-gray-900">{{ $avgScore ? number_format($avgScore, 1) : '—' }}</div>
            <p class="text-xs text-gray-400 mt-1">Approved evaluations</p>
            <div class="mt-3 pt-3 border-t border-gray-100 space-y-1.5 w-full max-w-36">
                <div class="flex items-center justify-between text-xs">
                    <span class="flex items-center gap-1.5 text-gray-500"><span class="w-2 h-2 rounded-full" style="background:#7C3AED"></span>Self</span>
                    <span class="font-bold text-gray-900">{{ $selfAvgScore !== null ? number_format($selfAvgScore, 1) : '—' }}</span>
                </div>
                @if($leadEvals->isNotEmpty())
                <div class="flex items-center justify-between text-xs">
                    <span class="flex items-center gap-1.5 text-gray-500"><span class="w-2 h-2 rounded-full" style="background:#F59E0B"></span>Lead</span>
                    <span class="font-bold text-gray-900">{{ $leadAvgScore !== null ? number_format($leadAvgScore, 1) : '—' }}</span>
                </div>
                @endif
            </div>
        </div>

        <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 flex flex-col items-center justify-center text-center">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">Total Evals</p>
            <div class="text-3xl font-bold text-gray-900">{{ $evaluations->count() }}</div>
            <p class="text-xs text-gray-400 mt-1">Self + Lead, all periods</p>
        </div>

        <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 flex flex-col items-center justify-center text-center">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">Pending Assessments</p>
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

    {{-- ── Tab Strip — visibility follows Menu Access (my-kpi tab-*) and the user's data ── --}}
    <div class="bg-white rounded-2xl p-1.5 shadow-sm border border-gray-100 flex items-center gap-1.5 flex-wrap">
        @if($showSelfTab)
        <button type="button" data-tab="self" onclick="showKpiTab('self')"
            class="kpi-tab-btn flex-1 sm:flex-none text-center px-4 py-2 rounded-xl text-xs font-bold text-gray-500 hover:bg-gray-50 transition-all">
            <i class="fas fa-pen-to-square mr-1.5"></i> Self-Assessment
        </button>
        @endif
        @if($showLeadTab)
        <button type="button" data-tab="lead" onclick="showKpiTab('lead')"
            class="kpi-tab-btn flex-1 sm:flex-none text-center px-4 py-2 rounded-xl text-xs font-bold text-gray-500 hover:bg-gray-50 transition-all">
            <i class="fas fa-user-tie mr-1.5"></i> Lead Assessment
        </button>
        @endif
        @if($showPeerTab)
        <button type="button" data-tab="peer" onclick="showKpiTab('peer')"
            class="kpi-tab-btn flex-1 sm:flex-none text-center px-4 py-2 rounded-xl text-xs font-bold text-gray-500 hover:bg-gray-50 transition-all">
            <i class="fas fa-user-group mr-1.5"></i> My Team (Peer)
        </button>
        @endif
        @if($showUpwardTab)
        <button type="button" data-tab="upward" onclick="showKpiTab('upward')"
            class="kpi-tab-btn flex-1 sm:flex-none text-center px-4 py-2 rounded-xl text-xs font-bold text-gray-500 hover:bg-gray-50 transition-all">
            <i class="fas fa-arrow-up mr-1.5"></i> Upward Assessment
        </button>
        @endif
    </div>

    {{-- ══════════════════ TAB: SELF-ASSESSMENT ══════════════════ --}}
    @if($showSelfTab)
    <div class="kpi-tab-panel space-y-5 hidden" data-tab="self">

        @if($selfPending->count() > 0)
        <div class="bg-amber-50 border border-amber-200 rounded-2xl p-5 shadow-sm space-y-3">
            <h3 class="text-sm font-bold text-amber-800 flex items-center gap-2">
                <i class="fas fa-pencil-alt text-amber-500"></i>
                Action Required — Unanswered Self-Assessments
            </h3>
            <div class="space-y-2">
                @foreach($selfPending as $eval)
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
                            <td class="px-5 py-3.5 text-xs text-gray-700">
                                {{ $eval->template?->name ?? 'Self-Assessment' }}
                            </td>
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
                                @elseif($eval->status === KpiEvaluation::STATUS_HR_REJECTED)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800">Needs Revision</span>
                                @elseif($overdue)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800">Overdue</span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">Pending</span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-center">
                                <a href="{{ route('general.my-kpi.self-assessment', $eval->id) }}"
                                   class="inline-flex items-center px-3.5 py-1.5 rounded-xl text-xs font-bold shadow-sm transition-all {{ ($done || $approved) ? 'bg-indigo-50 text-indigo-700 border border-indigo-200 hover:bg-indigo-100' : 'primary-gradient text-white hover:opacity-90' }}">
                                    {{ ($done || $approved) ? 'View Details' : ($eval->status === KpiEvaluation::STATUS_HR_REJECTED ? 'Revise' : 'Fill Self-Assessment') }}
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

    @endif

    {{-- ══════════════════ TAB: LEAD ASSESSMENT ══════════════════ --}}
    @if($showLeadTab)
    <div class="kpi-tab-panel space-y-5 hidden" data-tab="lead">
        @if($hasLeader || $isSystemAdmin)
        @include('hr-general.kpi.partials.my-lead-assessments')
        @endif

        {{-- My Team + Review History only make sense for someone who actually leads a team;
             everyone else just sees "Assessments From My Lead" above. --}}
        @if($leadsTeam)
        <div class="bg-white rounded-2xl shadow-sm border border-indigo-100 overflow-hidden">
            <div class="p-5 border-b border-indigo-50 bg-indigo-50/30 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm font-bold text-gray-900 flex items-center gap-2">
                        <span class="w-7 h-7 rounded-xl bg-indigo-600 text-white flex items-center justify-center text-xs">
                            <i class="fas fa-users"></i>
                        </span>
                        My Team — {{ Carbon::createFromFormat('Y-m', $selectedPeriod ?? $currentPeriod)->format('F Y') }}
                    </h3>
                    <p class="text-xs text-gray-500 mt-0.5">Score your direct reports' lead assessment.</p>
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
                            <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider w-24">Score</th>
                            <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider w-40">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @php
                            $teamEvals = ($assignedEvaluations ?? collect())
                                ->filter(fn($e) => $e->isLeadType() && !$isPeerEval($e))
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
                                @elseif($tEval->status === KpiEvaluation::STATUS_HR_REJECTED)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800">Needs Revision</span>
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
                                <a href="{{ route('general.my-kpi.lead-review', $tEval->id) }}"
                                   class="inline-flex items-center px-3.5 py-1.5 rounded-xl text-xs font-bold shadow-sm transition-all {{ $tSupDone ? 'bg-indigo-50 text-indigo-700 border border-indigo-200 hover:bg-indigo-100' : 'primary-gradient text-white hover:opacity-90' }}">
                                    {{ $tSupDone ? 'View' : ($tEval->status === KpiEvaluation::STATUS_HR_REJECTED ? 'Revise' : 'Evaluate') }}
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

        {{-- History of assessments this lead has already filled: one row per
             month, click to expand that month's assessments; paginated. --}}
        @php $historyByMonth = ($reviewHistory ?? collect())->reject($isPeerEval)->groupBy('period_month'); @endphp
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-5 border-b border-gray-100 flex items-center gap-2">
                <i class="fas fa-clock-rotate-left text-indigo-400"></i>
                <div>
                    <h3 class="text-sm font-bold text-gray-800">My Review History</h3>
                    <p class="text-[11px] text-gray-400 mt-0.5">Lead assessments you have already submitted. Click a month to see its assessments.</p>
                </div>
            </div>
            @if($historyByMonth->isNotEmpty())
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Month</th>
                        <th class="text-center px-4 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Assessments</th>
                        <th class="text-center px-4 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Avg Score</th>
                        <th class="w-10"></th>
                    </tr>
                </thead>
                @foreach($historyByMonth as $month => $items)
                @php $scored = $items->whereNotNull('overall_score'); @endphp
                <tbody class="rh-block border-b border-gray-100">
                    <tr class="rh-month cursor-pointer hover:bg-indigo-50/40 transition-colors" onclick="toggleRh(this)">
                        <td class="px-5 py-3 text-xs font-bold text-gray-900">{{ Carbon::createFromFormat('Y-m', $month)->format('F Y') }}</td>
                        <td class="px-4 py-3 text-center text-xs text-gray-600">{{ $items->count() }}</td>
                        <td class="px-4 py-3 text-center text-xs font-bold text-gray-900">{{ $scored->isNotEmpty() ? number_format($scored->avg('overall_score'), 1) : '—' }}</td>
                        <td class="px-4 py-3 text-center"><i class="fas fa-chevron-down text-[10px] text-gray-400 transition-transform rh-chev"></i></td>
                    </tr>
                    <tr class="rh-detail hidden">
                        <td colspan="4" class="p-0 bg-gray-50/50">
                            <div class="overflow-x-auto">
                            <table class="w-full text-xs">
                                <thead>
                                    <tr class="text-[10px] uppercase tracking-wider text-gray-400">
                                        <th class="text-left px-5 py-2 font-semibold">Team Member</th>
                                        <th class="text-left px-4 py-2 font-semibold">Template</th>
                                        <th class="text-center px-4 py-2 font-semibold">Reviewed On</th>
                                        <th class="text-center px-4 py-2 font-semibold">Status</th>
                                        <th class="text-center px-4 py-2 font-semibold">Score</th>
                                        <th class="text-center px-4 py-2 font-semibold">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach($items as $h)
                                    @php $hApproved = $h->status === KpiEvaluation::STATUS_HR_APPROVED; @endphp
                                    <tr>
                                        <td class="px-5 py-2.5">
                                            <p class="font-semibold text-gray-900">{{ $h->employee?->basicData?->full_name ?? $h->employee?->eci }}</p>
                                            <p class="text-[11px] text-indigo-500 font-mono">{{ $h->employee?->eci }}</p>
                                        </td>
                                        <td class="px-4 py-2.5 font-medium text-indigo-600">{{ $h->template?->name ?? '—' }}</td>
                                        <td class="px-4 py-2.5 text-center text-gray-500">{{ $h->reviewed_at?->format('d M Y') ?? '—' }}</td>
                                        <td class="px-4 py-2.5 text-center">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $hApproved ? 'bg-emerald-100 text-emerald-800' : 'bg-indigo-100 text-indigo-800' }}">{{ $hApproved ? 'Approved' : 'Reviewed' }}</span>
                                        </td>
                                        <td class="px-4 py-2.5 text-center font-bold text-gray-900">{{ $h->overall_score !== null ? number_format($h->overall_score, 1) : '—' }}</td>
                                        <td class="px-4 py-2.5 text-center">
                                            <a href="{{ route('general.my-kpi.lead-review', $h->id) }}"
                                               class="inline-flex items-center px-3 py-1 rounded-lg text-[11px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200 hover:bg-indigo-100 transition-all">View</a>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            </div>
                        </td>
                    </tr>
                </tbody>
                @endforeach
            </table>
            <div id="rhPager" class="px-5 py-3 border-t border-gray-100 flex items-center justify-between gap-3 text-xs text-gray-500"></div>
            @else
            <div class="text-center py-10">
                <p class="text-xs text-gray-400">You haven't submitted any lead assessments yet.</p>
            </div>
            @endif
        </div>
        @endif
    </div>
    @endif

    {{-- ══════════════════ TAB: PEER ASSESSMENT (My Team — peer) ══════════════════ --}}
    @if($showPeerTab)
    <div class="kpi-tab-panel space-y-5 hidden" data-tab="peer">
        <div class="bg-white rounded-2xl shadow-sm border border-cyan-100 overflow-hidden">
            <div class="p-5 border-b border-cyan-50 bg-cyan-50/30">
                <h3 class="text-sm font-bold text-gray-900 flex items-center gap-2">
                    <span class="w-7 h-7 rounded-xl bg-cyan-600 text-white flex items-center justify-center text-xs"><i class="fas fa-user-group"></i></span>
                    My Team (Peer) — {{ Carbon::createFromFormat('Y-m', $selectedPeriod ?? $currentPeriod)->format('F Y') }}
                </h3>
                <p class="text-xs text-gray-500 mt-0.5">Peer assessments you are asked to score this period.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Colleague</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Template</th>
                            <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider w-24">Score</th>
                            <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider w-32">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($peerAssigned as $pEval)
                        @php
                            $pDone = $pEval->hasSupervisorReview();
                            $pApproved = $pEval->status === KpiEvaluation::STATUS_HR_APPROVED;
                        @endphp
                        <tr class="hover:bg-cyan-50/20 transition-colors">
                            <td class="px-5 py-3.5">
                                <p class="font-semibold text-gray-900 text-sm">{{ $pEval->employee?->basicData?->full_name ?? $pEval->employee?->eci }}</p>
                                <p class="text-xs text-cyan-600 font-mono">{{ $pEval->employee?->eci }}</p>
                            </td>
                            <td class="px-4 py-3.5 text-xs font-medium text-cyan-700">{{ $pEval->template?->name ?? '—' }}</td>
                            <td class="px-4 py-3.5 text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $pApproved ? 'bg-emerald-100 text-emerald-800' : ($pDone ? 'bg-indigo-100 text-indigo-800' : 'bg-amber-100 text-amber-800') }}">
                                    {{ $pApproved ? 'Approved' : ($pDone ? 'Reviewed' : ($pEval->status === KpiEvaluation::STATUS_HR_REJECTED ? 'Needs Revision' : 'To Evaluate')) }}
                                </span>
                            </td>
                            <td class="px-4 py-3.5 text-center font-bold text-sm">{{ $pEval->overall_score ? number_format($pEval->overall_score, 1) : '—' }}</td>
                            <td class="px-4 py-3.5 text-center">
                                <a href="{{ route('general.my-kpi.peer-review', $pEval->id) }}"
                                   class="inline-flex items-center px-3.5 py-1.5 rounded-xl text-xs font-bold shadow-sm transition-all {{ $pDone ? 'bg-indigo-50 text-indigo-700 border border-indigo-200 hover:bg-indigo-100' : 'primary-gradient text-white hover:opacity-90' }}">
                                    {{ $pDone ? 'View' : ($pEval->status === KpiEvaluation::STATUS_HR_REJECTED ? 'Revise' : 'Evaluate') }}
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="py-8 text-center text-gray-400 text-xs">No peer assessments assigned to you this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-5 border-b border-gray-100 flex items-center gap-2">
                <i class="fas fa-user-group text-cyan-500"></i>
                <h3 class="text-sm font-bold text-gray-800">Peer Assessments About Me</h3>
            </div>
            @if($peerReceived->isNotEmpty())
            <div class="divide-y divide-gray-100">
                @foreach($peerReceived->sortByDesc('period_month') as $pr)
                @php $prReviewed = $pr->hasSupervisorReview(); @endphp
                <div class="p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-bold text-gray-900 text-sm">{{ $pr->template?->name ?? 'Peer Assessment' }}</span>
                            <span class="text-xs text-gray-400">&middot;</span>
                            <span class="text-xs text-gray-500">{{ Carbon::createFromFormat('Y-m', $pr->period_month)->format('F Y') }}</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-0.5">
                            Reviewer: {{ $pr->is_anonymous ? 'Anonymous' : ($pr->supervisor?->basicData?->full_name ?? 'Assigned') }}
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        @if($prReviewed)<span class="text-2xl font-bold text-cyan-700">{{ number_format($pr->overall_score ?? 0, 1) }}</span>@endif
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold {{ $prReviewed ? 'bg-cyan-100 text-cyan-800' : 'bg-amber-100 text-amber-800' }}">
                            {{ $prReviewed ? 'Reviewed' : 'Awaiting Review' }}
                        </span>
                    </div>
                </div>
                @endforeach
            </div>
            @else
            <div class="text-center py-10"><p class="text-xs text-gray-400">No peer assessments about you yet.</p></div>
            @endif
        </div>
    </div>
    @endif

    {{-- ══════════════════ TAB: UPWARD ASSESSMENT ══════════════════ --}}
    @if($showUpwardTab)
    <div class="kpi-tab-panel space-y-5 hidden" data-tab="upward">

        {{-- Fill-in: rate my own supervisor --}}
        @if($upwardPending->count() > 0)
        <div class="bg-amber-50 border border-amber-200 rounded-2xl p-5 shadow-sm space-y-3">
            <h3 class="text-sm font-bold text-amber-800 flex items-center gap-2">
                <i class="fas fa-pencil-alt text-amber-500"></i>
                Action Required — Rate Your Supervisor
            </h3>
            <div class="space-y-2">
                @foreach($upwardPending as $eval)
                <div class="bg-white rounded-xl p-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 border border-amber-100 shadow-sm">
                    <div>
                        <span class="font-bold text-gray-900 text-sm">{{ $eval->template?->name ?? 'Upward Assessment' }}</span>
                        <p class="text-xs text-gray-500 mt-1">
                            Period: <strong>{{ Carbon::createFromFormat('Y-m', $eval->period_month)->format('F Y') }}</strong>
                            &middot; Evaluating: <strong>{{ $eval->supervisor?->basicData?->full_name ?? 'your supervisor' }}</strong>
                        </p>
                    </div>
                    <a href="{{ route('general.my-kpi.upward-assessment', $eval->id) }}"
                       class="inline-flex items-center px-4 py-2 primary-gradient text-white text-xs font-bold rounded-xl shadow hover:opacity-90 transition-all shrink-0">
                        Fill Now
                    </a>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        @if($upwardEvals->isNotEmpty())
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-5 border-b border-gray-100 flex items-center gap-2">
                <i class="fas fa-list-check text-amber-400"></i>
                <h3 class="text-sm font-bold text-gray-800">My Upward Submissions</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Period</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Evaluating</th>
                            <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($upwardEvals->sortByDesc('period_month') as $eval)
                        @php $done = $eval->hasSelfAssessment(); @endphp
                        <tr class="hover:bg-gray-50/70 transition-colors">
                            <td class="px-5 py-3.5 font-semibold text-gray-900 text-xs">
                                {{ Carbon::createFromFormat('Y-m', $eval->period_month)->format('M Y') }}
                            </td>
                            <td class="px-5 py-3.5 text-xs text-gray-700">{{ $eval->supervisor?->basicData?->full_name ?? '—' }}</td>
                            <td class="px-4 py-3.5 text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $done ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                    {{ $done ? 'Submitted' : ($eval->status === KpiEvaluation::STATUS_HR_REJECTED ? 'Needs Revision' : 'Pending') }}
                                </span>
                            </td>
                            <td class="px-4 py-3.5 text-center">
                                <a href="{{ route('general.my-kpi.upward-assessment', $eval->id) }}"
                                   class="inline-flex items-center px-3.5 py-1.5 rounded-xl text-xs font-bold shadow-sm transition-all {{ $done ? 'bg-indigo-50 text-indigo-700 border border-indigo-200 hover:bg-indigo-100' : 'primary-gradient text-white hover:opacity-90' }}">
                                    {{ $done ? 'View Details' : ($eval->status === KpiEvaluation::STATUS_HR_REJECTED ? 'Revise' : 'Fill Now') }}
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- Feedback received: anonymized average from subordinates, HR-approved only --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-5 border-b border-gray-100 flex items-center gap-2">
                <i class="fas fa-chart-simple text-slate-400"></i>
                <div>
                    <h3 class="text-sm font-bold text-gray-800">Feedback From My Team</h3>
                    <p class="text-[11px] text-gray-400 mt-0.5">Anonymous. Only the HR-approved average is shown — never individual scores.</p>
                </div>
            </div>
            @if($upwardFeedback->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider w-12">No</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Template</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Month</th>
                            <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Raters</th>
                            <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Average Score</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Published</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($upwardFeedback as $fb)
                        <tr class="hover:bg-gray-50/70 transition-colors">
                            <td class="px-5 py-3.5 text-gray-400 text-xs font-medium">{{ $loop->iteration }}</td>
                            <td class="px-4 py-3.5 text-xs font-semibold text-gray-900">{{ $fb->template?->name ?? 'Upward Assessment' }}</td>
                            <td class="px-4 py-3.5 text-xs text-gray-600 whitespace-nowrap">{{ Carbon::createFromFormat('Y-m', $fb->period_month)->format('F Y') }}</td>
                            <td class="px-4 py-3.5 text-center text-xs text-gray-600">{{ $fb->rater_count }}</td>
                            <td class="px-4 py-3.5 text-center">
                                <span class="text-sm font-bold text-slate-700">{{ number_format($fb->average_score, 1) }}</span>
                                <span class="text-[11px] text-gray-400"> / 100</span>
                            </td>
                            <td class="px-4 py-3.5 text-xs text-gray-600 whitespace-nowrap">{{ $fb->published_at?->format('d M Y') ?? '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="text-center py-12">
                <p class="text-xs text-gray-400">No feedback published yet.</p>
            </div>
            @endif
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
    const ok = t => t && document.querySelector(`.kpi-tab-panel[data-tab="${t}"]`);
    showKpiTab(ok(initial) ? initial : '{{ $firstTab }}');
})();

// ── My Review History: expand a month + client-side pagination ──────────────
function toggleRh(row) {
    const detail = row.nextElementSibling;
    const open = detail.classList.toggle('hidden') === false;
    row.querySelector('.rh-chev').style.transform = open ? 'rotate(180deg)' : '';
}
(function () {
    const blocks = [...document.querySelectorAll('.rh-block')];
    const pager = document.getElementById('rhPager');
    if (!pager) return;
    const PER_PAGE = 6;
    const pages = Math.max(1, Math.ceil(blocks.length / PER_PAGE));
    let page = 1;
    function render() {
        blocks.forEach((b, i) => b.classList.toggle('hidden', Math.floor(i / PER_PAGE) + 1 !== page));
        const from = (page - 1) * PER_PAGE + 1, to = Math.min(page * PER_PAGE, blocks.length);
        const btn = 'w-8 h-8 rounded-lg border flex items-center justify-center text-xs font-semibold transition-all ';
        let nums = '';
        for (let p = 1; p <= pages; p++) {
            nums += `<button type="button" data-p="${p}" class="${btn}${p === page ? 'primary-gradient text-white border-transparent shadow-sm' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50'}">${p}</button>`;
        }
        pager.innerHTML = `<span>Showing ${from}–${to} of ${blocks.length} months</span>
            <div class="flex items-center gap-1.5">
                <button type="button" data-p="${page - 1}" ${page === 1 ? 'disabled' : ''} class="${btn}border-gray-200 bg-white text-gray-500 disabled:opacity-40 disabled:cursor-not-allowed"><i class="fas fa-chevron-left text-[10px]"></i></button>
                ${nums}
                <button type="button" data-p="${page + 1}" ${page === pages ? 'disabled' : ''} class="${btn}border-gray-200 bg-white text-gray-500 disabled:opacity-40 disabled:cursor-not-allowed"><i class="fas fa-chevron-right text-[10px]"></i></button>
            </div>`;
        pager.querySelectorAll('button[data-p]').forEach(b => b.onclick = () => { page = +b.dataset.p; render(); });
    }
    render();
})();

const trendData = @json($scoreTrend);
const ctx = document.getElementById('scoreTrendChart');
const trendSeries = (label, key, color) => ({
    label,
    data: trendData.map(d => d[key]),
    borderColor: color,
    backgroundColor: color,
    borderWidth: 2.5,
    pointBackgroundColor: color,
    pointRadius: 5,
    pointHoverRadius: 7,
    tension: 0.4,
    spanGaps: true,
});
// Self dot always; Lead dot only when the employee has a lead assessment.
const datasets = [trendSeries('Self Assessment', 'self', '#7C3AED')];
@if($leadEvals->isNotEmpty())
datasets.push(trendSeries('Lead Assessment', 'lead', '#F59E0B'));
@endif
if (ctx) {
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: trendData.map(d => d.label),
            datasets: datasets
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: datasets.length > 1, position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, font: { size: 11 } } } },
            scales: { y: { min: 0, max: 100 }, x: { grid: { display: false } } }
        }
    });
}
</script>
@endsection

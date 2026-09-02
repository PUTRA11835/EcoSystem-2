@extends('dashboard')

@section('title', 'KPI Evaluation')
@section('page-title', 'KPI Evaluation')

@section('content')
@php
    use Carbon\Carbon;
    $user       = session('user');
    $canFunc    = $can ?? fn($p) => true;
    $canCreate  = $canCreate ?? $canFunc('general.kpi-evaluation.create');
    $canReview  = $canReview ?? $canFunc('general.kpi-evaluation.review');
    $canApprove = $canApprove ?? $canFunc('general.kpi-evaluation.approve');
    $can        = $canFunc;
    $periodObj  = Carbon::createFromFormat('Y-m', $periodMonth);
@endphp

<div class="space-y-5">

    {{-- ── Header ──────────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-xl font-bold text-gray-900 flex items-center gap-2.5">
                    <span class="w-9 h-9 rounded-xl primary-gradient text-white flex items-center justify-center text-sm shadow-sm">
                        <i class="fas fa-chart-bar"></i>
                    </span>
                    KPI Evaluation
                </h1>
                <p class="text-xs text-gray-500 mt-1">
                    Monthly KPI coverage monitoring based on active employee data as of {{ now()->format('F d, Y') }}.
                </p>
            </div>

            {{-- Controls flush right on desktop --}}
            <div class="flex flex-wrap items-center justify-start md:justify-end gap-2.5 w-full md:w-auto">
                {{-- Month picker form (auto-submits on change) --}}
                <form method="GET" action="{{ route('general.kpi-evaluation.index') }}" class="flex items-center">
                    <input type="month" name="period" value="{{ $periodMonth }}"
                        class="px-3.5 py-2 text-xs font-semibold border border-gray-200 rounded-xl focus:ring-2 focus:ring-red-300 focus:border-red-400 bg-gray-50/50 hover:bg-white transition-colors cursor-pointer shadow-sm"
                        onchange="this.form.submit()">
                </form>

                {{-- Active employees badge --}}
                <span class="inline-flex items-center gap-1.5 px-3 py-2 bg-gray-100 text-gray-700 text-xs font-semibold rounded-xl border border-gray-200/60">
                    <i class="fas fa-users text-gray-500 text-xs"></i>
                    {{ $totalEmployees }} active employees
                </span>

                {{-- Export CSV --}}
                @if($can('general.kpi-evaluation.export'))
                <a href="{{ route('general.kpi-evaluation.export', ['period' => $periodMonth]) }}"
                   class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-semibold rounded-xl hover:bg-emerald-100 transition-all">
                    <i class="fas fa-file-csv text-xs"></i> Export
                </a>
                @endif

                {{-- Prominent Templates Button --}}
                @if($can('general.settings.kpi'))
                <a href="{{ route('general.settings.kpi.index') }}"
                   class="inline-flex items-center gap-2 px-4 py-2 primary-gradient text-white text-xs font-bold rounded-xl shadow-md hover:shadow-lg hover:opacity-95 transition-all transform active:scale-95">
                    <i class="fas fa-layer-group text-sm"></i> Templates
                </a>
                @endif
            </div>
        </div>
    </div>

    {{-- ── Status Summary Tiles (Matching Screenshot 1 & 2 design) ────────── --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
        @php
            $tiles = [
                ['label' => 'Visible Employees', 'value' => $totalEmployees,   'color' => 'text-gray-900',  'bg' => 'bg-white'],
                ['label' => 'Not Created',        'value' => $countNotCreated,  'color' => 'text-red-500',   'bg' => 'bg-white'],
                ['label' => 'Draft',              'value' => $countDraft,       'color' => 'text-amber-500', 'bg' => 'bg-white'],
                ['label' => 'Submitted',          'value' => $countSubmitted,   'color' => 'text-cyan-600',  'bg' => 'bg-white'],
                ['label' => 'Approved',           'value' => $countApproved,    'color' => 'text-emerald-600','bg' => 'bg-white'],
            ];
        @endphp
        @foreach($tiles as $tile)
        <div class="{{ $tile['bg'] }} rounded-xl p-4 shadow-sm border border-gray-100">
            <div class="text-3xl font-bold {{ $tile['color'] }}">{{ $tile['value'] }}</div>
            <div class="text-xs text-gray-500 mt-1 font-medium">{{ $tile['label'] }}</div>
        </div>
        @endforeach
    </div>

    {{-- ── Charts Row ───────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-5 gap-5">

        {{-- Score trend chart (wider) --}}
        <div class="lg:col-span-3 bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-gray-700 flex items-center gap-2">
                    <i class="fas fa-chart-line text-indigo-400"></i>
                    Average KPI Score Trend
                </h3>
                <div class="flex items-center gap-1" id="trendTabs">
                    @foreach(['monthly' => 'Monthly', 'annual' => 'Annual'] as $key => $label)
                    <button onclick="switchTrend('{{ $key }}')" id="tab-{{ $key }}"
                        class="px-3 py-1 text-xs font-medium rounded-lg transition-all
                            {{ $key === 'monthly' ? 'primary-gradient text-white shadow' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                        {{ $label }}
                    </button>
                    @endforeach
                </div>
            </div>
            <div style="height:200px;">
                <canvas id="trendChart"></canvas>
            </div>
        </div>

        {{-- Score by department (bar) --}}
        <div class="lg:col-span-2 bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
            <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                <i class="fas fa-user-tie text-blue-400"></i>
                Avg Score by Position
            </h3>
            <div style="height:200px;">
                <canvas id="deptChart"></canvas>
            </div>
        </div>
    </div>

    {{-- ── Evaluation List Table ────────────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        {{-- Clean & Brief Header --}}
        <div class="p-5 border-b border-gray-100 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
            <div>
                <h3 class="text-sm font-semibold text-gray-700 flex items-center gap-2">
                    <i class="fas fa-table text-gray-400"></i>
                    KPI Evaluation Coverage — {{ $periodObj->format('F Y') }}
                </h3>
                <p class="text-xs text-gray-400 mt-0.5">
                    Monthly KPI evaluation assignments and status tracking.
                </p>
            </div>
            <div class="flex items-center gap-2">
                @if($canCreate)
                <button onclick="openBulkCreateModal()"
                    class="inline-flex items-center px-4 py-2 primary-gradient text-white text-xs font-bold rounded-xl shadow hover:opacity-90 transition-all">
                    Bulk Assignment
                </button>
                @endif
            </div>
        </div>

        {{-- Hidden Form for Table Header Filters --}}
        <form method="GET" action="{{ route('general.kpi-evaluation.index') }}" id="tableFilterForm" class="hidden">
            <input type="hidden" name="period" value="{{ $periodMonth }}">
            <input type="hidden" name="per_page" id="perPageInput" value="{{ $perPage ?? 10 }}">
            <input type="hidden" name="search" id="headerSearchInput" value="{{ $search ?? '' }}">
            <input type="hidden" name="position" id="headerPositionInput" value="{{ $positionFilter ?? '' }}">
            <input type="hidden" name="supervisor" id="headerSupervisorInput" value="{{ $supervisorId ?? '' }}">
            <input type="hidden" name="template_id" id="headerTemplateInput" value="{{ $templateId ?? '' }}">
            <input type="hidden" name="status" id="headerStatusInput" value="{{ $statusFilter ?? '' }}">
        </form>

        {{-- Table view with filter icons on the right of header cells --}}
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50/90 border-b border-gray-100 select-none">
                    <tr>
                        {{-- No --}}
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider w-12">
                            No
                        </th>

                        {{-- 1. Employee --}}
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider relative min-w-[200px]">
                            <div class="flex items-center justify-between gap-1.5">
                                <span>Employee</span>
                                <button type="button" onclick="toggleHeaderFilter(event, 'employeeFilterBox')"
                                    class="p-1 rounded-md hover:bg-gray-200/70 transition-all {{ !empty($search) ? 'text-[var(--primary-color)] font-bold' : 'text-gray-400 hover:text-gray-600' }}"
                                    title="Filter Employee">
                                    <i class="fas fa-filter text-[10px]"></i>
                                </button>
                            </div>
                            {{-- Floating Search Popover --}}
                            <div id="employeeFilterBox" class="header-filter-popover hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 p-2.5 z-50 min-w-[220px] normal-case" onclick="event.stopPropagation()">
                                <div class="relative">
                                    <input type="text" id="headerEmployeeSearch" value="{{ $search ?? '' }}" placeholder="Search name or ECI..."
                                        onkeydown="if(event.key==='Enter'){event.preventDefault();onSearchEnter(this.value);}"
                                        class="w-full bg-gray-50 border border-gray-200 text-gray-800 text-xs rounded-lg pl-7 pr-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-[var(--primary-color)] focus:border-[var(--primary-color)] transition-all font-normal">
                                    <div class="absolute inset-y-0 left-0 flex items-center pl-2 text-gray-400 pointer-events-none">
                                        <i class="fas fa-search text-[10px]"></i>
                                    </div>
                                </div>
                            </div>
                        </th>

                        {{-- 2. Position --}}
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider relative min-w-[160px]">
                            <div class="flex items-center justify-between gap-1.5">
                                <span>Position</span>
                                <button type="button" onclick="toggleHeaderFilter(event, 'positionFilterBox')"
                                    class="p-1 rounded-md hover:bg-gray-200/70 transition-all {{ !empty($positionFilter) ? 'text-[var(--primary-color)] font-bold' : 'text-gray-400 hover:text-gray-600' }}"
                                    title="Filter Position">
                                    <i class="fas fa-filter text-[10px]"></i>
                                </button>
                            </div>
                            {{-- Floating Position Popover --}}
                            <div id="positionFilterBox" class="header-filter-popover hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 py-1.5 z-50 min-w-[190px] max-h-[260px] overflow-y-auto normal-case font-normal" onclick="event.stopPropagation()">
                                <button type="button" onclick="onPositionHeaderFilterChange('')"
                                    class="w-full text-left px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50 {{ empty($positionFilter) ? 'font-bold text-[var(--primary-color)]' : '' }}">
                                    All Positions
                                </button>
                                @foreach($positions as $pos)
                                    <button type="button" onclick="onPositionHeaderFilterChange('{{ addslashes($pos) }}')"
                                        class="w-full text-left px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50 {{ ($positionFilter ?? '') === $pos ? 'font-bold text-[var(--primary-color)]' : '' }}">
                                        {{ $pos }}
                                    </button>
                                @endforeach
                            </div>
                        </th>

                        {{-- 3. Supervisor --}}
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider relative min-w-[160px]">
                            <div class="flex items-center justify-between gap-1.5">
                                <span>Supervisor</span>
                                <button type="button" onclick="toggleHeaderFilter(event, 'supervisorFilterBox')"
                                    class="p-1 rounded-md hover:bg-gray-200/70 transition-all {{ !empty($supervisorId) ? 'text-[var(--primary-color)] font-bold' : 'text-gray-400 hover:text-gray-600' }}"
                                    title="Filter Supervisor">
                                    <i class="fas fa-filter text-[10px]"></i>
                                </button>
                            </div>
                            {{-- Floating Supervisor Search Popover --}}
                            <div id="supervisorFilterBox" class="header-filter-popover hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 p-2.5 z-50 min-w-[220px] normal-case" onclick="event.stopPropagation()">
                                <div class="relative">
                                    <input type="text" id="headerSupervisorSearch" value="{{ $supervisorId ?? '' }}" placeholder="Search supervisor name/ECI..."
                                        onkeydown="if(event.key==='Enter'){event.preventDefault();onSupervisorSearchEnter(this.value);}"
                                        class="w-full bg-gray-50 border border-gray-200 text-gray-800 text-xs rounded-lg pl-7 pr-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-[var(--primary-color)] focus:border-[var(--primary-color)] transition-all font-normal">
                                    <div class="absolute inset-y-0 left-0 flex items-center pl-2 text-gray-400 pointer-events-none">
                                        <i class="fas fa-search text-[10px]"></i>
                                    </div>
                                </div>
                            </div>
                        </th>

                        {{-- 4. Template --}}
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider relative min-w-[160px]">
                            <div class="flex items-center justify-between gap-1.5">
                                <span>Template</span>
                                <button type="button" onclick="toggleHeaderFilter(event, 'templateFilterBox')"
                                    class="p-1 rounded-md hover:bg-gray-200/70 transition-all {{ !empty($templateId) ? 'text-[var(--primary-color)] font-bold' : 'text-gray-400 hover:text-gray-600' }}"
                                    title="Filter Template">
                                    <i class="fas fa-filter text-[10px]"></i>
                                </button>
                            </div>
                            {{-- Floating Template Popover --}}
                            <div id="templateFilterBox" class="header-filter-popover hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 py-1.5 z-50 min-w-[200px] max-h-[260px] overflow-y-auto normal-case font-normal" onclick="event.stopPropagation()">
                                <button type="button" onclick="onTemplateHeaderFilterChange('')"
                                    class="w-full text-left px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50 {{ empty($templateId) ? 'font-bold text-[var(--primary-color)]' : '' }}">
                                    All Templates
                                </button>
                                @foreach($activeTemplates as $tmpl)
                                    <button type="button" onclick="onTemplateHeaderFilterChange('{{ $tmpl->id }}')"
                                        class="w-full text-left px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50 {{ (string)($templateId ?? '') === (string)$tmpl->id ? 'font-bold text-[var(--primary-color)]' : '' }}">
                                        {{ $tmpl->name }}
                                    </button>
                                @endforeach
                            </div>
                        </th>

                        {{-- 5. Self Score --}}
                        <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider w-24">
                            Self Score
                        </th>

                        {{-- 6. SPV Score --}}
                        <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider w-24">
                            SPV Score
                        </th>

                        {{-- 7. Status --}}
                        <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider relative min-w-[140px]">
                            <div class="flex items-center justify-center gap-1.5">
                                <span>Status</span>
                                <button type="button" onclick="toggleHeaderFilter(event, 'statusFilterBox')"
                                    class="p-1 rounded-md hover:bg-gray-200/70 transition-all {{ !empty($statusFilter) ? 'text-[var(--primary-color)] font-bold' : 'text-gray-400 hover:text-gray-600' }}"
                                    title="Filter Status">
                                    <i class="fas fa-filter text-[10px]"></i>
                                </button>
                            </div>
                            {{-- Floating Status Popover --}}
                            <div id="statusFilterBox" class="header-filter-popover hidden absolute top-full right-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 py-1.5 z-50 min-w-[160px] text-left normal-case font-normal" onclick="event.stopPropagation()">
                                <button type="button" onclick="onStatusHeaderFilterChange('')"
                                    class="w-full text-left px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50 {{ empty($statusFilter) ? 'font-bold text-[var(--primary-color)]' : '' }}">
                                    All Status
                                </button>
                                <button type="button" onclick="onStatusHeaderFilterChange('not_created')"
                                    class="w-full text-left px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50 {{ ($statusFilter ?? '') === 'not_created' ? 'font-bold text-[var(--primary-color)]' : '' }}">
                                    Not Created
                                </button>
                                <button type="button" onclick="onStatusHeaderFilterChange('draft')"
                                    class="w-full text-left px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50 {{ ($statusFilter ?? '') === 'draft' ? 'font-bold text-[var(--primary-color)]' : '' }}">
                                    Draft
                                </button>
                                <button type="button" onclick="onStatusHeaderFilterChange('self_assessed')"
                                    class="w-full text-left px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50 {{ ($statusFilter ?? '') === 'self_assessed' ? 'font-bold text-[var(--primary-color)]' : '' }}">
                                    Self-Assessed
                                </button>
                                <button type="button" onclick="onStatusHeaderFilterChange('reviewed')"
                                    class="w-full text-left px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50 {{ ($statusFilter ?? '') === 'reviewed' ? 'font-bold text-[var(--primary-color)]' : '' }}">
                                    Reviewed
                                </button>
                                <button type="button" onclick="onStatusHeaderFilterChange('completed')"
                                    class="w-full text-left px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50 {{ ($statusFilter ?? '') === 'completed' ? 'font-bold text-[var(--primary-color)]' : '' }}">
                                    Completed
                                </button>
                                <button type="button" onclick="onStatusHeaderFilterChange('hr_approved')"
                                    class="w-full text-left px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50 {{ ($statusFilter ?? '') === 'hr_approved' ? 'font-bold text-[var(--primary-color)]' : '' }}">
                                    Approved
                                </button>
                                <button type="button" onclick="onStatusHeaderFilterChange('hr_rejected')"
                                    class="w-full text-left px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50 {{ ($statusFilter ?? '') === 'hr_rejected' ? 'font-bold text-[var(--primary-color)]' : '' }}">
                                    Rejected
                                </button>
                            </div>
                        </th>

                        {{-- 8. Action --}}
                        @php
                            $hasActiveFilters = !empty($search) || !empty($positionFilter) || !empty($statusFilter) || !empty($supervisorId) || !empty($templateId);
                        @endphp
                        <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider w-28">
                            @if($hasActiveFilters)
                                <a href="{{ route('general.kpi-evaluation.index', ['period' => $periodMonth]) }}"
                                   class="inline-flex items-center justify-center px-3 py-1 bg-gray-100 hover:bg-gray-200 text-gray-600 text-xs font-semibold rounded-lg transition-all shadow-sm normal-case"
                                   title="Reset all filters">
                                    Reset
                                </a>
                            @else
                                <span>Action</span>
                            @endif
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @php
                        $evalMap = $recentEvaluations->keyBy('employee_id');
                    @endphp
                    @forelse($activeEmployees as $emp)
                    @php
                        $eval = $evalMap->get($emp->employee_id);
                        $bd = $emp->basicData;
                        $selfScore = ($eval && $eval->hasSelfAssessment() && $eval->details->isNotEmpty())
                            ? $eval->details->whereNotNull('self_achievement')->avg('self_achievement')
                            : null;
                        $spvScore = ($eval && $eval->overall_score !== null)
                            ? $eval->overall_score
                            : (($eval && $eval->hasSupervisorReview() && $eval->details->isNotEmpty())
                                ? $eval->details->whereNotNull('supervisor_score')->avg('supervisor_score')
                                : null);
                    @endphp
                    <tr class="hover:bg-gray-50/70 transition-colors">
                        {{-- Row Numbering with pagination offset support --}}
                        <td class="px-5 py-3.5 text-gray-400 text-xs font-medium">
                            {{ method_exists($activeEmployees, 'firstItem') ? (($activeEmployees->firstItem() ?? 1) + $loop->index) : $loop->iteration }}
                        </td>
                        {{-- Employee --}}
                        <td class="px-5 py-3.5">
                            <p class="font-semibold text-gray-900 text-sm">{{ $bd?->full_name ?? $emp->eci }}</p>
                            <p class="text-xs text-red-400 font-mono">{{ $emp->eci }}</p>
                        </td>
                        {{-- Position --}}
                        <td class="px-4 py-3.5 text-xs text-gray-600">{{ $bd?->position ?? '—' }}</td>
                        {{-- Supervisor --}}
                        <td class="px-4 py-3.5 text-xs text-gray-600">
                            {{ $eval?->supervisor?->basicData?->full_name ?? ($bd?->direct_supervision ? 'Assigned' : '—') }}
                        </td>
                        {{-- Template (Select Dropdown - Auto Saves on Change) --}}
                        <td class="px-4 py-3.5 text-xs">
                            <select onchange="autoSaveEvaluationTemplate({{ $emp->employee_id }}, {{ $eval?->id ?? 0 }}, this.value)"
                                class="px-2.5 py-1.5 text-xs font-medium border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-300 bg-white max-w-[190px] cursor-pointer shadow-sm">
                                <option value="">Select template...</option>
                                @foreach($activeTemplates as $tmpl)
                                <option value="{{ $tmpl->id }}" {{ $eval && $eval->template_id == $tmpl->id ? 'selected' : '' }}>
                                    {{ $tmpl->name }}
                                </option>
                                @endforeach
                            </select>
                        </td>
                        {{-- Self Score --}}
                        <td class="px-4 py-3.5 text-center font-bold text-sm">
                            @if($selfScore !== null)
                                <span class="text-gray-900">{{ number_format($selfScore, 1) }}</span>
                            @elseif($eval && $eval->hasSelfAssessment())
                                <span class="text-xs text-purple-600 font-medium">Submitted</span>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        {{-- SPV Score --}}
                        <td class="px-4 py-3.5 text-center font-bold text-sm">
                            @if($spvScore !== null)
                                <span class="text-gray-900">{{ number_format($spvScore, 1) }}</span>
                            @elseif($eval && $eval->hasSupervisorReview())
                                <span class="text-xs text-indigo-600 font-medium">Reviewed</span>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        {{-- Status --}}
                        <td class="px-4 py-3.5 text-center">
                            @if($eval)
                                @php
                                    $statusBadges = [
                                        'draft'         => 'bg-amber-50 text-amber-600 border-amber-200',
                                        'self_assessed' => 'bg-blue-50 text-blue-600 border-blue-200',
                                        'reviewed'      => 'bg-indigo-50 text-indigo-600 border-indigo-200',
                                        'completed'     => 'bg-purple-50 text-purple-600 border-purple-200',
                                        'hr_approved'   => 'bg-emerald-50 text-emerald-600 border-emerald-200',
                                        'hr_rejected'   => 'bg-red-50 text-red-600 border-red-200',
                                    ];
                                @endphp
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $statusBadges[$eval->status] ?? 'bg-gray-100 text-gray-600 border-gray-200' }}">
                                    {{ $eval->status_label }}
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-50 text-red-500 border border-red-100">
                                    Not Created
                                </span>
                            @endif
                        </td>
                        {{-- Action (buttons without icons inside) --}}
                        <td class="px-4 py-3.5 text-center">
                            @if($eval)
                                <div class="flex items-center justify-center gap-1.5">
                                    @if($eval->status === 'draft')
                                    <a href="{{ route('general.kpi-evaluation.review', $eval->id) }}"
                                       class="inline-flex items-center px-3 py-1.5 rounded-xl bg-slate-900 text-white text-xs font-semibold hover:bg-slate-800 transition-all shadow-sm">
                                        Continue Draft
                                    </a>
                                    @else
                                    <a href="{{ route('general.kpi-evaluation.review', $eval->id) }}"
                                       class="inline-flex items-center px-3 py-1.5 rounded-xl bg-blue-50 text-blue-700 border border-blue-200 text-xs font-semibold hover:bg-blue-100 transition-all">
                                        {{ $eval->status === 'hr_approved' ? 'View' : 'Review' }}
                                    </a>
                                    @endif

                                    @if($canCreate && in_array($eval->status, ['draft', 'hr_rejected']))
                                    <button onclick="deleteEval({{ $eval->id }})"
                                        class="px-2.5 py-1.5 rounded-xl bg-red-50 text-red-500 hover:bg-red-100 border border-red-200 text-xs font-semibold transition-all">
                                        Delete
                                    </button>
                                    @endif
                                </div>
                            @else
                                <span class="text-xs text-gray-400 italic">Select template</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="py-12 text-center text-gray-400">No matching employee evaluations found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- ── Custom Modern Pagination (Matching Exact Design) ───────────────────── --}}
        @if($activeEmployees->total() > 0)
        <div class="px-5 py-4 border-t border-gray-100 bg-white flex flex-col sm:flex-row items-center justify-between gap-4">
            {{-- Left: Pagination Navigation & Results Counter --}}
            <div class="flex items-center gap-1.5 flex-wrap">
                {{-- Previous Page Button --}}
                @if($activeEmployees->onFirstPage())
                    <span class="w-8 h-8 rounded-lg border border-gray-100 bg-gray-50 text-gray-300 flex items-center justify-center text-xs cursor-not-allowed shadow-none">
                        <i class="fas fa-chevron-left text-[10px]"></i>
                    </span>
                @else
                    <a href="{{ $activeEmployees->previousPageUrl() }}"
                       class="w-8 h-8 rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 hover:text-gray-700 flex items-center justify-center text-xs shadow-sm transition-all">
                        <i class="fas fa-chevron-left text-[10px]"></i>
                    </a>
                @endif

                {{-- Page Numbers --}}
                @php
                    $current = $activeEmployees->currentPage();
                    $last = $activeEmployees->lastPage();
                    $start = max(1, $current - 2);
                    $end = min($last, $current + 2);
                    if ($end - $start < 4) {
                        if ($start === 1) {
                            $end = min($last, $start + 4);
                        } elseif ($end === $last) {
                            $start = max(1, $end - 4);
                        }
                    }
                @endphp

                @if($start > 1)
                    <a href="{{ $activeEmployees->url(1) }}"
                       class="w-8 h-8 rounded-lg border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 font-semibold flex items-center justify-center text-xs shadow-sm transition-all">
                        1
                    </a>
                    @if($start > 2)
                        <span class="w-5 text-center text-gray-400 text-xs">...</span>
                    @endif
                @endif

                @for($p = $start; $p <= $end; $p++)
                    @if($p == $current)
                        <span class="w-8 h-8 rounded-lg primary-surface text-white font-bold flex items-center justify-center text-xs shadow-sm"
                              style="background: var(--primary-surface, var(--primary-color)) !important;">
                            {{ $p }}
                        </span>
                    @else
                        <a href="{{ $activeEmployees->url($p) }}"
                           class="w-8 h-8 rounded-lg border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 font-semibold flex items-center justify-center text-xs shadow-sm transition-all">
                            {{ $p }}
                        </a>
                    @endif
                @endfor

                @if($end < $last)
                    @if($end < $last - 1)
                        <span class="w-5 text-center text-gray-400 text-xs">...</span>
                    @endif
                    <a href="{{ $activeEmployees->url($last) }}"
                       class="w-8 h-8 rounded-lg border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 font-semibold flex items-center justify-center text-xs shadow-sm transition-all">
                        {{ $last }}
                    </a>
                @endif

                {{-- Next Page Button --}}
                @if($activeEmployees->hasMorePages())
                    <a href="{{ $activeEmployees->nextPageUrl() }}"
                       class="w-8 h-8 rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 hover:text-gray-700 flex items-center justify-center text-xs shadow-sm transition-all">
                        <i class="fas fa-chevron-right text-[10px]"></i>
                    </a>
                @else
                    <span class="w-8 h-8 rounded-lg border border-gray-100 bg-gray-50 text-gray-300 flex items-center justify-center text-xs cursor-not-allowed shadow-none">
                        <i class="fas fa-chevron-right text-[10px]"></i>
                    </span>
                @endif

                {{-- Showing Results Counter --}}
                <span class="text-xs text-gray-500 ml-3 font-normal whitespace-nowrap">
                    Showing {{ $activeEmployees->firstItem() ?? 0 }} to {{ $activeEmployees->lastItem() ?? 0 }} of {{ $activeEmployees->total() }} results
                </span>
            </div>

            {{-- Right: Rows per page selector --}}
            <div class="flex items-center gap-2">
                <span class="text-xs text-gray-500 font-normal">Rows per page:</span>
                <div class="relative">
                    <select onchange="changePerPage(this.value)"
                        class="appearance-none bg-white border border-gray-200 rounded-lg pl-3 pr-7 py-1.5 text-xs font-medium text-gray-700 hover:border-gray-300 focus:outline-none focus:ring-1 focus:ring-[var(--primary-color)] cursor-pointer shadow-sm transition-all">
                        <option value="10" {{ ($perPage ?? 10) == 10 ? 'selected' : '' }}>10</option>
                        <option value="15" {{ ($perPage ?? 10) == 15 ? 'selected' : '' }}>15</option>
                        <option value="25" {{ ($perPage ?? 10) == 25 ? 'selected' : '' }}>25</option>
                        <option value="50" {{ ($perPage ?? 10) == 50 ? 'selected' : '' }}>50</option>
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2.5 text-gray-400 text-[10px]">
                        <i class="fas fa-chevron-down"></i>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>

</div>

{{-- ── Searchable Multi-Select Bulk Assignment Modal ─────────────────────── --}}
@if($canCreate)
<div id="bulkCreateModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between p-5 border-b border-gray-100">
            <div>
                <h3 class="text-base font-bold text-gray-900 flex items-center gap-2">
                    <i class="fas fa-layer-group text-indigo-500"></i>
                    Assign & Start KPI Evaluation
                </h3>
                <p class="text-xs text-gray-500 mt-0.5">Assign templates to employees individually or by roles</p>
            </div>
            <button onclick="closeBulkCreateModal()" class="w-8 h-8 rounded-lg bg-gray-100 hover:bg-gray-200 flex items-center justify-center">
                <i class="fas fa-times text-gray-500 text-sm"></i>
            </button>
        </div>

        <form id="bulkCreateForm" onsubmit="submitBulkCreate(event)" class="p-5 space-y-4">
            @csrf
            <input type="hidden" name="period_month" value="{{ $periodMonth }}">

            {{-- 1. Selection Mode --}}
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1.5">Assignment Mode <span class="text-red-500">*</span></label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="flex items-center gap-2.5 p-3 rounded-xl border border-gray-200 bg-gray-50/50 cursor-pointer hover:bg-white transition-all">
                        <input type="radio" name="assign_mode" value="by_employee" checked onchange="toggleAssignMode('by_employee')" class="text-indigo-600">
                        <div>
                            <p class="text-xs font-bold text-gray-800">Select Employees</p>
                            <p class="text-[10px] text-gray-400">Searchable multi-select</p>
                        </div>
                    </label>
                    <label class="flex items-center gap-2.5 p-3 rounded-xl border border-gray-200 bg-gray-50/50 cursor-pointer hover:bg-white transition-all">
                        <input type="radio" name="assign_mode" value="by_role" onchange="toggleAssignMode('by_role')" class="text-indigo-600">
                        <div>
                            <p class="text-xs font-bold text-gray-800">Select by Roles</p>
                            <p class="text-[10px] text-gray-400">Target all staff in role</p>
                        </div>
                    </label>
                </div>
            </div>

            {{-- 2a. Employee Multi-Select (Searchable) --}}
            <div id="employeeSelectBlock">
                <div class="flex items-center justify-between mb-1.5">
                    <label class="text-xs font-semibold text-gray-700">Select Employees <span class="text-red-500">*</span></label>
                    <button type="button" onclick="selectAllEmps(true)" class="text-[11px] text-indigo-600 font-medium hover:underline">Select All</button>
                </div>
                <input type="text" id="empSearchInput" onkeyup="filterEmpCheckboxes()" placeholder="Type to search employee name or position..."
                    class="w-full mb-2 px-3 py-2 text-xs border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-400">
                <div id="empChecklist" class="max-h-48 overflow-y-auto border border-gray-200 rounded-xl p-2.5 space-y-1.5 bg-gray-50/30">
                    @foreach($allActiveEmployees ?? $activeEmployees as $emp)
                    <label class="emp-item flex items-center gap-2.5 p-1.5 hover:bg-white rounded-lg cursor-pointer text-xs transition-colors">
                        <input type="checkbox" name="employee_ids[]" value="{{ $emp->employee_id }}" class="rounded text-indigo-600">
                        <span class="font-medium text-gray-800">{{ $emp->basicData?->full_name ?? $emp->eci }}</span>
                        <span class="text-gray-400 text-[11px]">({{ $emp->basicData?->position ?? 'Staff' }})</span>
                    </label>
                    @endforeach
                </div>
            </div>

            {{-- 2b. Role Multi-Select (Searchable) --}}
            <div id="roleSelectBlock" class="hidden">
                <div class="flex items-center justify-between mb-1.5">
                    <label class="text-xs font-semibold text-gray-700">Select Roles <span class="text-red-500">*</span></label>
                    <button type="button" onclick="selectAllRoles(true)" class="text-[11px] text-indigo-600 font-medium hover:underline">Select All</button>
                </div>
                <input type="text" id="roleSearchInput" onkeyup="filterRoleCheckboxes()" placeholder="Type to search role name..."
                    class="w-full mb-2 px-3 py-2 text-xs border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-400">
                <div id="roleChecklist" class="max-h-48 overflow-y-auto border border-gray-200 rounded-xl p-2.5 space-y-1.5 bg-gray-50/30">
                    @foreach($employeeRoles as $role)
                    <label class="role-item flex items-center gap-2.5 p-1.5 hover:bg-white rounded-lg cursor-pointer text-xs transition-colors">
                        <input type="checkbox" name="role_ids[]" value="{{ $role->id }}" class="rounded text-indigo-600">
                        <span class="font-medium text-gray-800">{{ $role->name }}</span>
                    </label>
                    @endforeach
                </div>
            </div>

            {{-- 3. Template Selection (shows Evaluator purpose) --}}
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1.5">KPI Template <span class="text-red-500">*</span></label>
                <select name="template_id" required
                    class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-red-300">
                    <option value="">Select KPI Template...</option>
                    @foreach($activeTemplates as $tmpl)
                    <option value="{{ $tmpl->id }}">
                        [{{ $tmpl->target_type_label }}] {{ $tmpl->name }}
                        ({{ $tmpl->indicators_count ?? $tmpl->indicators->count() }} indicators)
                    </option>
                    @endforeach
                </select>
                <p class="text-[11px] text-gray-400 mt-1">
                    <i class="fas fa-info-circle"></i>
                    Templates marked <strong>Mandiri</strong> are for self-assessments; <strong>Penilaian Atasan</strong> are for supervisor evaluation.
                </p>
            </div>

            {{-- 4. Supervisor Assignment --}}
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1.5">Assigned Evaluator / Supervisor</label>
                <select name="supervisor_id"
                    class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-red-300">
                    <option value="">Auto-assign (from employee's direct supervision)</option>
                    @foreach($supervisors as $sup)
                    <option value="{{ $sup->employee_id }}">
                        {{ $sup->basicData?->full_name ?? $sup->eci }}
                        @if($sup->basicData?->position) — {{ $sup->basicData->position }}@endif
                    </option>
                    @endforeach
                </select>
            </div>

            {{-- 5. Deadline Settings --}}
            <div class="border-t border-gray-100 pt-4">
                <p class="text-xs font-bold text-gray-700 mb-3 flex items-center gap-2">
                    <i class="fas fa-calendar-check text-amber-500"></i> Assessment Deadlines
                    <span class="text-gray-400 font-normal">(optional — HR can adjust anytime)</span>
                </p>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Self-Assessment Deadline</label>
                        <input type="date" name="self_deadline" id="selfDeadlineInput"
                            class="w-full px-3 py-2 text-xs border border-gray-200 rounded-xl focus:ring-2 focus:ring-amber-300">
                        <p class="text-[11px] text-gray-400 mt-0.5">Date by which employee must submit self-assessment</p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Supervisor Scoring Deadline</label>
                        <input type="date" name="supervisor_deadline" id="supervisorDeadlineInput"
                            class="w-full px-3 py-2 text-xs border border-gray-200 rounded-xl focus:ring-2 focus:ring-amber-300">
                        <p class="text-[11px] text-gray-400 mt-0.5">Date by which supervisor must complete scoring</p>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2 border-t border-gray-100">
                <button type="button" onclick="closeBulkCreateModal()"
                    class="px-4 py-2.5 bg-gray-100 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-200">
                    Cancel
                </button>
                <button type="submit" id="bulkSubmitBtn"
                    class="inline-flex items-center px-6 py-2.5 primary-gradient text-white text-sm font-semibold rounded-xl shadow hover:opacity-90">
                    Confirm & Start KPI
                </button>
            </div>
        </form>
    </div>
</div>
@endif

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Chart initialization
const trendDataMonthly = @json($monthlyTrend);
const deptData         = @json($scoreByDept);

let trendChart, deptChart, currentTrendView = 'monthly';

const trendCtx = document.getElementById('trendChart').getContext('2d');
trendChart = new Chart(trendCtx, {
    type: 'line',
    data: buildTrendData(trendDataMonthly),
    options: trendOptions(),
});

const deptCtx = document.getElementById('deptChart').getContext('2d');
deptChart = new Chart(deptCtx, {
    type: 'bar',
    data: {
        labels: deptData.map(d => d.department),
        datasets: [{
            label: 'Avg Score',
            data: deptData.map(d => d.avg_score),
            backgroundColor: deptData.map((d, i) => `hsla(${220 + i * 20}, 70%, 55%, 0.8)`),
            borderRadius: 6,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { min: 0, max: 100, grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { font: { size: 10 } } },
            x: { grid: { display: false }, ticks: { font: { size: 10 } } }
        }
    }
});

function buildTrendData(data) {
    return {
        labels: data.map(d => d.label),
        datasets: [{
            label: 'Avg KPI Score',
            data: data.map(d => d.avg_score),
            borderColor: '#7C3AED',
            backgroundColor: 'rgba(124,58,237,0.08)',
            borderWidth: 2.5,
            pointBackgroundColor: '#7C3AED',
            tension: 0.4,
            fill: true,
        }]
    };
}
function trendOptions() {
    return {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { min: 0, max: 100 }, x: { grid: { display: false } } }
    };
}

async function switchTrend(type) {
    document.querySelectorAll('#trendTabs button').forEach(btn => {
        btn.className = 'px-3 py-1 text-xs font-medium rounded-lg transition-all bg-gray-100 text-gray-600 hover:bg-gray-200';
    });
    const activeTab = document.getElementById('tab-' + type);
    if (activeTab) {
        activeTab.className = 'px-3 py-1 text-xs font-medium rounded-lg transition-all primary-gradient text-white shadow';
    }

    try {
        const res = await fetch(`{{ route('general.kpi-evaluation.dashboard-data') }}?view=${type}&period={{ $periodMonth }}`);
        const data = await res.json();
        if (data && data.trend && trendChart) {
            trendChart.data.labels = data.trend.map(d => d.label);
            trendChart.data.datasets[0].data = data.trend.map(d => d.avg_score);
            trendChart.update();
        }
    } catch (e) {
        console.error('Error fetching trend data:', e);
    }
}

// Bulk modal functions
function openBulkCreateModal() { document.getElementById('bulkCreateModal')?.classList.remove('hidden'); }
function closeBulkCreateModal() { document.getElementById('bulkCreateModal')?.classList.add('hidden'); }

function toggleAssignMode(mode) {
    if (mode === 'by_employee') {
        document.getElementById('employeeSelectBlock').classList.remove('hidden');
        document.getElementById('roleSelectBlock').classList.add('hidden');
    } else {
        document.getElementById('employeeSelectBlock').classList.add('hidden');
        document.getElementById('roleSelectBlock').classList.remove('hidden');
    }
}

function filterEmpCheckboxes() {
    const q = document.getElementById('empSearchInput').value.toLowerCase();
    document.querySelectorAll('#empChecklist .emp-item').forEach(item => {
        item.style.display = item.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}
function filterRoleCheckboxes() {
    const q = document.getElementById('roleSearchInput').value.toLowerCase();
    document.querySelectorAll('#roleChecklist .role-item').forEach(item => {
        item.style.display = item.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}

function selectAllEmps(checked) {
    document.querySelectorAll('#empChecklist input[type="checkbox"]').forEach(c => c.checked = checked);
}
function selectAllRoles(checked) {
    document.querySelectorAll('#roleChecklist input[type="checkbox"]').forEach(c => c.checked = checked);
}

// ── Floating Header Filter Popover Toggler ─────────────────────────────────
function toggleHeaderFilter(e, popoverId) {
    e.stopPropagation();
    const target = document.getElementById(popoverId);
    const isHidden = target?.classList.contains('hidden');

    // Close all other header popovers
    document.querySelectorAll('.header-filter-popover').forEach(p => p.classList.add('hidden'));

    if (target && isHidden) {
        target.classList.remove('hidden');
        const input = target.querySelector('input');
        if (input) setTimeout(() => input.focus(), 50);
    }
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.header-filter-popover')) {
        document.querySelectorAll('.header-filter-popover').forEach(p => p.classList.add('hidden'));
    }
});

// ── Header Column Filter Handlers ──────────────────────────────────────────
function onSearchEnter(val) {
    const input = document.getElementById('headerSearchInput');
    if (input) input.value = val;
    document.getElementById('tableFilterForm')?.submit();
}

function onPositionHeaderFilterChange(val) {
    const el = document.getElementById('headerPositionInput');
    if (el) el.value = val;
    document.getElementById('tableFilterForm')?.submit();
}

function onSupervisorSearchEnter(val) {
    const el = document.getElementById('headerSupervisorInput');
    if (el) el.value = val;
    document.getElementById('tableFilterForm')?.submit();
}

function onSupervisorHeaderFilterChange(val) {
    const el = document.getElementById('headerSupervisorInput');
    if (el) el.value = val;
    document.getElementById('tableFilterForm')?.submit();
}

function onTemplateHeaderFilterChange(val) {
    const el = document.getElementById('headerTemplateInput');
    if (el) el.value = val;
    document.getElementById('tableFilterForm')?.submit();
}

function onStatusHeaderFilterChange(val) {
    const el = document.getElementById('headerStatusInput');
    if (el) el.value = val;
    document.getElementById('tableFilterForm')?.submit();
}

function changePerPage(val) {
    const input = document.getElementById('perPageInput');
    if (input) input.value = val;
    document.getElementById('tableFilterForm')?.submit();
}

async function submitBulkCreate(e) {
    e.preventDefault();
    const btn = document.getElementById('bulkSubmitBtn');
    btn.disabled = true;
    btn.textContent = 'Processing...';

    const res  = await fetch('{{ route("general.kpi-evaluation.store") }}', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        body: new FormData(document.getElementById('bulkCreateForm')),
    });
    const data = await res.json();
    showToast(data.message, data.success ? 'success' : 'error');
    if (data.success) {
        setTimeout(() => location.reload(), 1000);
    } else {
        btn.disabled = false;
        btn.textContent = 'Confirm & Start KPI';
    }
}
// ── Auto-save Template Selection (No Start Button Needed) ───────────────────
async function autoSaveEvaluationTemplate(empId, evalId, templateId) {
    if (!templateId) return;

    if (evalId > 0) {
        // Update existing evaluation template
        const fd = new FormData();
        fd.append('_token', '{{ csrf_token() }}');
        fd.append('template_id', templateId);

        const res  = await fetch(`/general/kpi-evaluation/${evalId}/update-template`, {
            method: 'POST', headers: { 'Accept': 'application/json' }, body: fd
        });
        const data = await res.json();
        showToast(data.message || 'KPI Template updated successfully.', data.success ? 'success' : 'error');
    } else {
        // Create new evaluation record with selected template
        const fd = new FormData();
        fd.append('_token', '{{ csrf_token() }}');
        fd.append('employee_id', empId);
        fd.append('template_id', templateId);
        fd.append('period_month', '{{ $periodMonth }}');

        const res  = await fetch('{{ route("general.kpi-evaluation.store") }}', {
            method: 'POST', headers: { 'Accept': 'application/json' }, body: fd
        });
        const data = await res.json();
        showToast(data.message || 'Evaluation created & template saved!', data.success ? 'success' : 'error');
        if (data.success) {
            setTimeout(() => location.reload(), 800);
        }
    }
}

// ── Inline Deadline AJAX Updates ─────────────────────────────────────────────
async function updateEvaluationDeadline(evalId, dateVal) {
    const fd = new FormData();
    fd.append('_token', '{{ csrf_token() }}');
    fd.append('self_deadline', dateVal || '');

    const res  = await fetch(`/general/kpi-evaluation/${evalId}/update-deadline`, {
        method: 'POST', headers: { 'Accept': 'application/json' }, body: fd
    });
    const data = await res.json();
    showToast(data.message, data.success ? 'success' : 'error');
}

async function deleteEval(id) {
    if (!confirm('Delete this evaluation?')) return;
    const res  = await fetch(`/general/kpi-evaluation/${id}/delete`, {
        method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
    });
    const data = await res.json();
    showToast(data.message, data.success ? 'success' : 'error');
    if (data.success) setTimeout(() => location.reload(), 800);
}
</script>
@php
    $customDdVer = file_exists(public_path('js/custom-dropdown.js')) ? filemtime(public_path('js/custom-dropdown.js')) : 1;
@endphp
<script src="/js/custom-dropdown.js?v={{ $customDdVer }}"></script>
@endsection

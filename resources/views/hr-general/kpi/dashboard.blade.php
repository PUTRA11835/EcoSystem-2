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

    {{-- ── Page Tab Strip (Dashboard | Assessment Templates) ─────────────────── --}}
    <div class="bg-white rounded-2xl p-1.5 shadow-sm border border-gray-100 flex items-center gap-1.5 w-full sm:w-auto">
        <span class="flex-1 sm:flex-none text-center px-4 py-2 rounded-xl text-xs font-bold primary-gradient text-white shadow">
            <i class="fas fa-chart-bar mr-1.5"></i> Dashboard
        </span>
        @if($can('general.kpi-evaluation.templates'))
        <a href="{{ route('general.kpi-evaluation.templates.index') }}"
           class="flex-1 sm:flex-none text-center px-4 py-2 rounded-xl text-xs font-bold text-gray-500 hover:bg-gray-50 transition-all">
            <i class="fas fa-layer-group mr-1.5"></i> Assessment Templates
        </a>
        @endif
        <a href="{{ route('general.kpi-evaluation.teams') }}"
           class="flex-1 sm:flex-none text-center px-4 py-2 rounded-xl text-xs font-bold text-gray-500 hover:bg-gray-50 transition-all">
            <i class="fas fa-sitemap mr-1.5"></i> Team &amp; Leads
        </a>
    </div>

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
                {{-- Month + assessment-type picker form (auto-submits on change) --}}
                <form method="GET" action="{{ route('general.kpi-evaluation.index') }}" class="flex items-center gap-2">
                    <input type="month" name="period" value="{{ $periodMonth }}"
                        class="px-3.5 py-2 text-xs font-semibold border border-gray-200 rounded-xl focus:ring-2 focus:ring-red-300 focus:border-red-400 bg-gray-50/50 hover:bg-white transition-colors cursor-pointer shadow-sm"
                        onchange="this.form.submit()">
                    <select name="type" onchange="this.form.submit()"
                        class="px-3 py-2 text-xs font-semibold border border-gray-200 rounded-xl focus:ring-2 focus:ring-red-300 bg-gray-50/50 hover:bg-white transition-colors cursor-pointer shadow-sm">
                        <option value="" {{ ($typeFilter ?? '') === '' ? 'selected' : '' }}>All Types</option>
                        <option value="self" {{ ($typeFilter ?? '') === 'self' ? 'selected' : '' }}>Self-Assessment</option>
                        <option value="lead" {{ ($typeFilter ?? '') === 'lead' ? 'selected' : '' }}>Lead Assessment</option>
                    </select>
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
            <div class="flex flex-wrap items-center gap-2">
                <h3 class="text-sm font-semibold text-gray-700 flex items-center gap-2">
                    <i class="fas fa-table text-gray-400"></i>
                    KPI Evaluation Coverage — {{ $periodObj->format('F Y') }}
                </h3>
                @if(($hasActiveFilters ?? false))
                <a href="{{ route('general.kpi-evaluation.index', ['period' => $periodMonth]) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-600 text-xs font-semibold rounded-lg transition-all">
                    <i class="fas fa-rotate-left text-[10px]"></i> Reset filters
                </a>
                @endif
            </div>
            <div class="flex items-center gap-2">
                @if($canCreate)
                <button type="button" onclick="resyncAssignments(this)"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-indigo-50 text-indigo-700 border border-indigo-200 text-xs font-bold rounded-xl hover:bg-indigo-100 transition-all">
                    <i class="fas fa-arrows-rotate text-xs"></i> Re-sync
                </button>
                @endif
                @if($can('general.kpi-evaluation.templates'))
                <a href="{{ route('general.kpi-evaluation.templates.index') }}"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-gray-100 text-gray-700 text-xs font-bold rounded-xl hover:bg-gray-200 transition-all">
                    <i class="fas fa-layer-group text-xs"></i> Manage Templates
                </a>
                @endif
            </div>
        </div>
        <p class="px-5 pb-3 -mt-2 text-[11px] text-gray-400">
            <i class="fas fa-circle-info text-[10px] mr-0.5"></i>
            This table only <strong>shows</strong> assignments — they follow each template's
            “Who is this template for?” audience. Edit a template to change who's covered; an employee can hold more than one.
        </p>

        {{-- Hidden Form for Table Header Filters --}}
        <form method="GET" action="{{ route('general.kpi-evaluation.index') }}" id="tableFilterForm" class="hidden">
            <input type="hidden" name="period" value="{{ $periodMonth }}">
            <input type="hidden" name="per_page" id="perPageInput" value="{{ $perPage ?? 10 }}">
            <input type="hidden" name="search" id="headerSearchInput" value="{{ $search ?? '' }}">
            <input type="hidden" name="position" id="headerPositionInput" value="{{ $positionFilter ?? '' }}">
            <input type="hidden" name="supervisor" id="headerSupervisorInput" value="{{ $supervisorId ?? '' }}">
            <input type="hidden" name="template_id" id="headerTemplateInput" value="{{ $templateId ?? '' }}">
            <input type="hidden" name="status" id="headerStatusInput" value="{{ $statusFilter ?? '' }}">
            <input type="hidden" name="type" value="{{ $typeFilter ?? '' }}">
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
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider relative min-w-50">
                            <div class="flex items-center justify-between gap-1.5">
                                <span>Employee</span>
                                <button type="button" onclick="toggleHeaderFilter(event, 'employeeFilterBox')"
                                    class="p-1 rounded-md hover:bg-gray-200/70 transition-all {{ !empty($search) ? 'text-(--primary-color)] font-bold' : 'text-gray-400 hover:text-gray-600' }}"
                                    title="Filter Employee">
                                    <i class="fas fa-filter text-[10px]"></i>
                                </button>
                            </div>
                            {{-- Floating Search Popover --}}
                            <div id="employeeFilterBox" class="header-filter-popover hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 p-2.5 z-50 min-w-55 normal-case" onclick="event.stopPropagation()">
                                <div class="relative">
                                    <input type="text" id="headerEmployeeSearch" value="{{ $search ?? '' }}" placeholder="Type a name or ECI…" autocomplete="off"
                                        oninput="debouncedFilterSubmit('headerSearchInput', this.value)"
                                        onkeydown="if(event.key==='Enter'){event.preventDefault();onSearchEnter(this.value);}"
                                        class="w-full bg-gray-50 border border-gray-200 text-gray-800 text-xs rounded-lg pl-7 pr-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-(--primary-color) focus:border-(--primary-color) transition-all font-normal">
                                    <div class="absolute inset-y-0 left-0 flex items-center pl-2 text-gray-400 pointer-events-none">
                                        <i class="fas fa-search text-[10px]"></i>
                                    </div>
                                </div>
                                <p class="text-[10px] text-gray-400 mt-1.5">Results update as you type.</p>
                            </div>
                        </th>

                        {{-- 2. Position --}}
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider relative min-w-40">
                            <div class="flex items-center justify-between gap-1.5">
                                <span>Position</span>
                                <button type="button" onclick="toggleHeaderFilter(event, 'positionFilterBox')"
                                    class="p-1 rounded-md hover:bg-gray-200/70 transition-all {{ !empty($positionFilter) ? 'text-(--primary-color) font-bold' : 'text-gray-400 hover:text-gray-600' }}"
                                    title="Filter Position">
                                    <i class="fas fa-filter text-[10px]"></i>
                                </button>
                            </div>
                            {{-- Floating Position Popover --}}
                            <div id="positionFilterBox" class="header-filter-popover hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 py-1.5 z-50 min-w-47.5 max-h-65 overflow-y-auto normal-case font-normal" onclick="event.stopPropagation()">
                                <button type="button" onclick="onPositionHeaderFilterChange('')"
                                    class="w-full text-left px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50 {{ empty($positionFilter) ? 'font-bold text-(--primary-color)' : '' }}">
                                    All Positions
                                </button>
                                @foreach($positions as $pos)
                                    <button type="button" onclick="onPositionHeaderFilterChange('{{ addslashes($pos) }}')"
                                        class="w-full text-left px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50 {{ ($positionFilter ?? '') === $pos ? 'font-bold text-(--primary-color)' : '' }}">
                                        {{ $pos }}
                                    </button>
                                @endforeach
                            </div>
                        </th>

                        {{-- 3. Supervisor --}}
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider relative min-w-40">
                            <div class="flex items-center justify-between gap-1.5">
                                <span>Supervisor</span>
                                <button type="button" onclick="toggleHeaderFilter(event, 'supervisorFilterBox')"
                                    class="p-1 rounded-md hover:bg-gray-200/70 transition-all {{ !empty($supervisorId) ? 'text-(--primary-color) font-bold' : 'text-gray-400 hover:text-gray-600' }}"
                                    title="Filter Supervisor">
                                    <i class="fas fa-filter text-[10px]"></i>
                                </button>
                            </div>
                            {{-- Floating Supervisor Search Popover --}}
                            <div id="supervisorFilterBox" class="header-filter-popover hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 p-2.5 z-50 min-w-55 normal-case" onclick="event.stopPropagation()">
                                <div class="relative">
                                    <input type="text" id="headerSupervisorSearch" value="{{ $supervisorId ?? '' }}" placeholder="Type a supervisor name / ECI…" autocomplete="off"
                                        oninput="debouncedFilterSubmit('headerSupervisorInput', this.value)"
                                        onkeydown="if(event.key==='Enter'){event.preventDefault();onSupervisorSearchEnter(this.value);}"
                                        class="w-full bg-gray-50 border border-gray-200 text-gray-800 text-xs rounded-lg pl-7 pr-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-(--primary-color) focus:border-(--primary-color) transition-all font-normal">
                                    <div class="absolute inset-y-0 left-0 flex items-center pl-2 text-gray-400 pointer-events-none">
                                        <i class="fas fa-search text-[10px]"></i>
                                    </div>
                                </div>
                                <p class="text-[10px] text-gray-400 mt-1.5">Results update as you type.</p>
                            </div>
                        </th>

                        {{-- 4. Template --}}
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider relative min-w-40">
                            <div class="flex items-center justify-between gap-1.5">
                                <span>Template</span>
                                <button type="button" onclick="toggleHeaderFilter(event, 'templateFilterBox')"
                                    class="p-1 rounded-md hover:bg-gray-200/70 transition-all {{ !empty($templateId) ? 'text-(--primary-color) font-bold' : 'text-gray-400 hover:text-gray-600' }}"
                                    title="Filter Template">
                                    <i class="fas fa-filter text-[10px]"></i>
                                </button>
                            </div>
                            {{-- Floating Template Popover --}}
                            <div id="templateFilterBox" class="header-filter-popover hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 py-1.5 z-50 min-w-50 max-h-65 overflow-y-auto normal-case font-normal" onclick="event.stopPropagation()">
                                <button type="button" onclick="onTemplateHeaderFilterChange('')"
                                    class="w-full text-left px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50 {{ empty($templateId) ? 'font-bold text-(--primary-color)' : '' }}">
                                    All Templates
                                </button>
                                @foreach($activeTemplates as $tmpl)
                                    <button type="button" onclick="onTemplateHeaderFilterChange('{{ $tmpl->id }}')"
                                        class="w-full text-left px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50 {{ (string)($templateId ?? '') === (string)$tmpl->id ? 'font-bold text-(--primary-color)' : '' }}">
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
                        <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider relative min-w-35">
                            <div class="flex items-center justify-center gap-1.5">
                                <span>Status</span>
                                <button type="button" onclick="toggleHeaderFilter(event, 'statusFilterBox')"
                                    class="p-1 rounded-md hover:bg-gray-200/70 transition-all {{ !empty($statusFilter) ? 'text-(--primary-color) font-bold' : 'text-gray-400 hover:text-gray-600' }}"
                                    title="Filter Status">
                                    <i class="fas fa-filter text-[10px]"></i>
                                </button>
                            </div>
                            {{-- Floating Status Popover --}}
                            <div id="statusFilterBox" class="header-filter-popover hidden absolute top-full right-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 py-1.5 z-50 min-w-40 text-left normal-case font-normal" onclick="event.stopPropagation()">
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
                        <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider w-28">
                            <span>Action</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @php
                        $evalsByEmp = $recentEvaluations->groupBy('employee_id');
                        $rowNum = (method_exists($activeEmployees, 'firstItem') ? ($activeEmployees->firstItem() ?? 1) : 1) - 1;
                        $statusBadges = [
                            'draft'         => 'bg-amber-50 text-amber-600 border-amber-200',
                            'self_assessed' => 'bg-blue-50 text-blue-600 border-blue-200',
                            'reviewed'      => 'bg-indigo-50 text-indigo-600 border-indigo-200',
                            'completed'     => 'bg-purple-50 text-purple-600 border-purple-200',
                            'hr_approved'   => 'bg-emerald-50 text-emerald-600 border-emerald-200',
                            'hr_rejected'   => 'bg-red-50 text-red-600 border-red-200',
                        ];
                    @endphp
                    @php $doneStatuses = ['completed', 'hr_approved']; @endphp
                    @forelse($activeEmployees as $emp)
                        @php
                            $rowNum++;
                            $bd = $emp->basicData;
                            $empEvals = ($evalsByEmp->get($emp->employee_id) ?? collect())
                                ->sortBy(fn($e) => $e->template?->target_type === 'self' ? 0 : 1)->values();
                            $supName = ($empEvals->first()?->supervisor?->basicData?->full_name)
                                ?? ($bd?->direct_supervision ? (($supervisorNames[$bd->direct_supervision] ?? null) ?: 'Assigned') : '—');
                            $selfN = $empEvals->filter(fn($e) => ($e->template?->target_type ?? 'supervisor') === 'self')->count();
                            $leadN = $empEvals->count() - $selfN;
                            $doneCount = $empEvals->whereIn('status', $doneStatuses)->count();
                            $allDone = $empEvals->isNotEmpty() && $doneCount === $empEvals->count();
                        @endphp

                        @if($empEvals->isEmpty())
                        {{-- Employee not covered by any active template --}}
                        <tr class="hover:bg-gray-50/70 transition-colors bg-red-50/10">
                            <td class="px-5 py-3.5 text-gray-400 text-xs font-medium">{{ $rowNum }}</td>
                            <td class="px-5 py-3.5">
                                <p class="font-semibold text-gray-900 text-sm">{{ $bd?->full_name ?? $emp->eci }}</p>
                                <p class="text-xs text-red-400 font-mono">{{ $emp->eci }}</p>
                            </td>
                            <td class="px-4 py-3.5 text-xs text-gray-600">{{ $bd?->position ?? '—' }}</td>
                            <td class="px-4 py-3.5 text-xs text-gray-600">{{ $supName }}</td>
                            <td class="px-4 py-3.5 text-xs text-gray-400 italic" colspan="3">Not covered by any template</td>
                            <td class="px-4 py-3.5 text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-50 text-red-500 border border-red-100">No template</span>
                            </td>
                            <td></td>
                        </tr>
                        @else
                        {{-- Summary row (click to expand the per-template detail) --}}
                        <tr class="cov-summary hover:bg-indigo-50/20 transition-colors cursor-pointer" onclick="toggleCov({{ $emp->employee_id }})">
                            <td class="px-5 py-3.5 text-gray-400 text-xs font-medium">{{ $rowNum }}</td>
                            <td class="px-5 py-3.5">
                                <p class="font-semibold text-gray-900 text-sm">{{ $bd?->full_name ?? $emp->eci }}</p>
                                <p class="text-xs text-red-400 font-mono">{{ $emp->eci }}</p>
                            </td>
                            <td class="px-4 py-3.5 text-xs text-gray-600">{{ $bd?->position ?? '—' }}</td>
                            <td class="px-4 py-3.5 text-xs text-gray-600">{{ $supName }}</td>
                            <td class="px-4 py-3.5 text-xs">
                                <span class="font-semibold text-gray-800">{{ $empEvals->count() }} template{{ $empEvals->count() > 1 ? 's' : '' }}</span>
                                @if($selfN)<span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-purple-100 text-purple-700">Self ×{{ $selfN }}</span>@endif
                                @if($leadN)<span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-indigo-100 text-indigo-700">Lead ×{{ $leadN }}</span>@endif
                            </td>
                            <td class="px-4 py-3.5 text-center text-xs text-gray-500" colspan="2">{{ $doneCount }} / {{ $empEvals->count() }} done</td>
                            <td class="px-4 py-3.5 text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $allDone ? 'bg-emerald-50 text-emerald-600 border-emerald-200' : 'bg-amber-50 text-amber-600 border-amber-200' }}">
                                    {{ $allDone ? 'Complete' : 'In progress' }}
                                </span>
                            </td>
                            <td class="px-4 py-3.5 text-center">
                                <i class="fas fa-chevron-down text-[10px] text-gray-400 transition-transform" id="cov-chev-{{ $emp->employee_id }}"></i>
                            </td>
                        </tr>
                        {{-- Detail rows — one per assigned template (hidden until expanded) --}}
                        @foreach($empEvals as $eval)
                        @php
                            $isSelf = ($eval->template?->target_type ?? 'supervisor') === 'self';
                            $selfScore = ($eval->hasSelfAssessment() && $eval->details->isNotEmpty())
                                ? $eval->details->whereNotNull('self_achievement')->avg('self_achievement') : null;
                            $spvScore = ($eval->overall_score !== null && !$isSelf)
                                ? $eval->overall_score
                                : (($eval->hasSupervisorReview() && $eval->details->isNotEmpty())
                                    ? $eval->details->whereNotNull('supervisor_score')->avg('supervisor_score') : null);
                        @endphp
                        <tr class="cov-detail cov-{{ $emp->employee_id }} hidden bg-gray-50/40 border-l-2 border-indigo-200">
                            <td></td>
                            <td></td>
                            <td></td>
                            <td class="px-4 py-2.5 text-[11px] text-gray-500">{{ $eval->supervisor?->basicData?->full_name ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-xs">
                                <span class="font-medium text-gray-800">{{ $eval->template?->name ?? '—' }}</span>
                                <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold {{ $isSelf ? 'bg-purple-100 text-purple-700' : 'bg-indigo-100 text-indigo-700' }}">{{ $isSelf ? 'Self' : 'Lead' }}</span>
                            </td>
                            <td class="px-4 py-2.5 text-center font-bold text-xs">
                                @if($selfScore !== null)<span class="text-gray-900">{{ number_format($selfScore, 1) }}</span>
                                @elseif($eval->hasSelfAssessment())<span class="text-purple-600 font-medium">Submitted</span>
                                @else<span class="text-gray-300">{{ $isSelf ? '—' : 'n/a' }}</span>@endif
                            </td>
                            <td class="px-4 py-2.5 text-center font-bold text-xs">
                                @if($spvScore !== null)<span class="text-gray-900">{{ number_format($spvScore, 1) }}</span>
                                @elseif($eval->hasSupervisorReview())<span class="text-indigo-600 font-medium">Reviewed</span>
                                @else<span class="text-gray-300">{{ $isSelf ? 'n/a' : '—' }}</span>@endif
                            </td>
                            <td class="px-4 py-2.5 text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold border {{ $statusBadges[$eval->status] ?? 'bg-gray-100 text-gray-600 border-gray-200' }}">{{ $eval->status_label }}</span>
                            </td>
                            <td class="px-4 py-2.5 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    <a href="{{ route('general.kpi-evaluation.review', $eval->id) }}"
                                       class="inline-flex items-center px-3 py-1.5 rounded-lg text-[11px] font-semibold transition-all {{ (!$isSelf && $eval->status === 'draft') ? 'bg-slate-900 text-white hover:bg-slate-800' : 'bg-blue-50 text-blue-700 border border-blue-200 hover:bg-blue-100' }}">
                                        {{ $isSelf ? 'View' : ($eval->status === 'draft' ? 'Continue' : ($eval->status === 'hr_approved' ? 'View' : 'Review')) }}
                                    </a>
                                    @if($canCreate && in_array($eval->status, ['draft', 'hr_rejected']))
                                    <button onclick="event.stopPropagation(); deleteEval({{ $eval->id }})"
                                        class="px-2 py-1.5 rounded-lg bg-red-50 text-red-500 hover:bg-red-100 border border-red-200 text-[11px] font-semibold transition-all"><i class="fas fa-trash text-[9px]"></i></button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                        @endif
                    @empty
                    <tr>
                        <td colspan="9" class="py-12 text-center text-gray-400">No matching employees found.</td>
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

// ── Realtime search — auto-submit a short beat after the last keystroke ─────
let _filterDebounce = null;
function debouncedFilterSubmit(hiddenId, val) {
    const el = document.getElementById(hiddenId);
    if (el) el.value = val;
    clearTimeout(_filterDebounce);
    _filterDebounce = setTimeout(() => document.getElementById('tableFilterForm')?.submit(), 400);
}

// After a filtered reload, re-open the search popover and put the caret back
// so typing feels continuous.
document.addEventListener('DOMContentLoaded', function () {
    const reopen = [
        ['headerEmployeeSearch', 'employeeFilterBox'],
        ['headerSupervisorSearch', 'supervisorFilterBox'],
    ];
    for (const [inputId, boxId] of reopen) {
        const input = document.getElementById(inputId);
        if (input && input.value.trim() !== '') {
            document.getElementById(boxId)?.classList.remove('hidden');
            input.focus();
            const v = input.value; input.value = ''; input.value = v; // caret to end
            break;
        }
    }
});

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

// ── Coverage: expand/collapse an employee's per-template rows ──────────────
function toggleCov(empId) {
    const rows = document.querySelectorAll('.cov-' + empId);
    const chev = document.getElementById('cov-chev-' + empId);
    let opening = false;
    rows.forEach(r => { r.classList.toggle('hidden'); if (!r.classList.contains('hidden')) opening = true; });
    if (chev) chev.style.transform = opening ? 'rotate(180deg)' : '';
}

// ── Re-sync coverage with template targeting ──────────────────────────────
async function resyncAssignments(btn) {
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-circle-notch fa-spin text-xs"></i> Syncing…';
    try {
        const fd = new FormData();
        fd.append('_token', '{{ csrf_token() }}');
        fd.append('period', '{{ $periodMonth }}');
        const res = await fetch('{{ route("general.kpi-evaluation.sync") }}', {
            method: 'POST', headers: { 'Accept': 'application/json' }, body: fd,
        });
        const data = await res.json();
        showToast(data.message || 'Synced.', data.success ? 'success' : 'error');
        if (data.success) setTimeout(() => location.reload(), 700);
        else { btn.disabled = false; btn.innerHTML = original; }
    } catch (e) {
        showToast('Sync failed.', 'error');
        btn.disabled = false; btn.innerHTML = original;
    }
}

async function deleteEval(id) {
    if (!await showConfirm('Delete this evaluation?', 'Delete Evaluation', 'danger', { okText: 'Delete' })) return;
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

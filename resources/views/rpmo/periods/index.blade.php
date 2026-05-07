@extends('dashboard')
@section('title', 'Period Management')
@section('page-title', 'Period Management')

@push('styles')
<style>
    .focus-ring:focus {
        outline: none;
        border-color: var(--primary-color) !important;
        box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.15) !important;
    }
    @keyframes pulse-dot { 0%,100%{opacity:1} 50%{opacity:.4} }
    .dot-pulse { animation: pulse-dot 1.5s infinite; }
</style>
@endpush

@section('content')
@php
    $isRpmo           = $roleId === 7;
    $isProjectHead    = $roleId === 4;
    $isSupportHead    = $roleId === 5;
    $isAdmin          = $roleId === 1;
    $isHead           = $isProjectHead || $isSupportHead;
    $headDomain       = $isProjectHead ? 'project' : ($isSupportHead ? 'support' : null);
    // Superadmin gates
    $canManageGlobal  = $isRpmo || $isAdmin;   // create period, open/close global, force-close
    $canManageDomains = $isHead || $isAdmin;   // open/close domain, late exceptions
    $MONTHS           = ['','January','February','March','April','May','June','July','August','September','October','November','December'];
@endphp

{{-- Card: flex-col, tinggi maks viewport minus header navbar (~72px) + padding content (24px) --}}
<div class="bg-white rounded-xl shadow-sm flex flex-col overflow-hidden" style="max-height:calc(100vh - 6.25rem);">

    {{-- ══ LOCKED TOP: Header + Active Period ══ --}}
    <div class="flex-shrink-0 px-6 pt-6 pb-4">

    {{-- ── Page Header ──────────────────────────────────────────────────────── --}}
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 pb-4 border-b-2 border-gray-100">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Period Management</h2>
            <p class="text-sm text-gray-500 mt-0.5">
                @if($isAdmin) Full period lifecycle control (EC Administrator)
                @elseif($isRpmo) Full period lifecycle control (RPMO)
                @elseif($isProjectHead) Project domain control & late exceptions
                @elseif($isSupportHead) Support domain control & late exceptions
                @else Read-only view with audit logs
                @endif
            </p>
        </div>
        @if($canManageGlobal)
        <button onclick="openCreateModal()"
            class="inline-flex items-center gap-2 px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            New Period
        </button>
        @endif
    </div>

    {{-- ── Active Period ─────────────────────────────────────────────────────── --}}
    @if($active)
    @php
        $gStatus     = $active->global_status;
        $pStatus     = $active->project_status;
        $sStatus     = $active->support_status;
        $periodLabel = $MONTHS[$active->month] . ' ' . $active->year;
        $dateRange   = \Carbon\Carbon::parse($active->start_date)->format('d M Y') . ' – ' . \Carbon\Carbon::parse($active->end_date)->format('d M Y');
    @endphp

    <div class="mb-6">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-lg font-semibold text-gray-900">Active Period</h3>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-green-50 text-green-700 text-xs font-semibold rounded-full border border-green-200">
                <span class="w-1.5 h-1.5 rounded-full bg-green-500 dot-pulse"></span>
                {{ $periodLabel }}
            </span>
        </div>

        <div class="bg-gray-50 rounded-lg border border-gray-200 p-5">
            {{-- Date range --}}
            <p class="text-xs text-gray-500 mb-4">
                <i class="fas fa-calendar-alt mr-1"></i> {{ $dateRange }}
            </p>

            {{-- Status grid --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-5">
                <div class="bg-white rounded-lg border border-gray-200 p-3">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Global (RPMO)</p>
                    @include('rpmo.periods._status_badge', ['status' => $gStatus])
                    @if($active->opened_at)
                    <p class="text-[10px] text-gray-400 mt-1.5">Opened {{ \Carbon\Carbon::parse($active->opened_at)->format('d M Y') }}</p>
                    @endif
                </div>
                <div class="bg-white rounded-lg border {{ ($isProjectHead || $isAdmin) ? 'border-blue-300' : 'border-gray-200' }} p-3">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                        Project Domain @if($isProjectHead || $isAdmin)<span class="text-blue-500 normal-case font-normal"></span>@endif
                    </p>
                    @include('rpmo.periods._status_badge', ['status' => $pStatus])
                    @if($active->project_opened_at)
                    <p class="text-[10px] text-gray-400 mt-1.5">Opened {{ \Carbon\Carbon::parse($active->project_opened_at)->format('d M Y') }}</p>
                    @endif
                </div>
                <div class="bg-white rounded-lg border {{ ($isSupportHead || $isAdmin) ? 'border-blue-300' : 'border-gray-200' }} p-3">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                        Support Domain @if($isSupportHead || $isAdmin)<span class="text-blue-500 normal-case font-normal"></span>@endif
                    </p>
                    @include('rpmo.periods._status_badge', ['status' => $sStatus])
                    @if($active->support_opened_at)
                    <p class="text-[10px] text-gray-400 mt-1.5">Opened {{ \Carbon\Carbon::parse($active->support_opened_at)->format('d M Y') }}</p>
                    @endif
                </div>
            </div>

            {{-- Action buttons --}}
            <div class="flex flex-wrap gap-2 pt-4 border-t border-gray-200">

                {{-- Global period actions (RPMO / Admin) --}}
                @if($canManageGlobal)
                    @if($gStatus === 'not_open')
                    <button onclick="periodAction({{ $active->id }}, 'open-global')"
                        class="inline-flex items-center gap-1.5 px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all">
                        <i class="fas fa-lock-open text-xs"></i> Open Period Globally
                    </button>
                    @endif

                    @if($gStatus === 'open')
                    <button onclick="confirmCloseGlobal({{ $active->id }}, '{{ $periodLabel }}')"
                        class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold rounded-lg border transition-all
                        {{ $active->canCloseGlobal() ? 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' : 'bg-gray-100 text-gray-400 border-gray-200 cursor-not-allowed' }}"
                        {{ !$active->canCloseGlobal() ? 'disabled title="Close Project and Support domains first"' : '' }}>
                        <i class="fas fa-lock text-xs"></i> Close Period Globally
                    </button>

                    @if($pStatus !== 'closed')
                    <button onclick="confirmForceClose({{ $active->id }}, 'project', '{{ $periodLabel }}')"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-red-50 text-red-600 text-sm font-semibold rounded-lg border border-red-200 hover:bg-red-100 transition-all">
                        <i class="fas fa-times-circle text-xs"></i> Force Close Project
                    </button>
                    @endif

                    @if($sStatus !== 'closed')
                    <button onclick="confirmForceClose({{ $active->id }}, 'support', '{{ $periodLabel }}')"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-red-50 text-red-600 text-sm font-semibold rounded-lg border border-red-200 hover:bg-red-100 transition-all">
                        <i class="fas fa-times-circle text-xs"></i> Force Close Support
                    </button>
                    @endif
                    @endif
                @endif

                {{-- Domain actions: Head (own domain only) --}}
                @if($isHead)
                    @php $myDomainStatus = $headDomain === 'project' ? $pStatus : $sStatus; @endphp
                    @if($myDomainStatus !== 'open')
                    <button onclick="periodAction({{ $active->id }}, 'open-domain')"
                        class="inline-flex items-center gap-1.5 px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all">
                        Open {{ ucfirst($headDomain) }} Period
                    </button>
                    @endif
                    @if($myDomainStatus === 'open')
                    <button onclick="confirmCloseDomain({{ $active->id }}, '{{ $headDomain }}', '{{ $periodLabel }}')"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">
                        Close {{ ucfirst($headDomain) }} Period
                    </button>
                    @endif

                {{-- Domain actions: Admin (both domains) --}}
                @elseif($isAdmin)
                    @if($pStatus !== 'open')
                    <button onclick="periodAction({{ $active->id }}, 'open-domain', {domain:'project'})"
                        class="inline-flex items-center gap-1.5 px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all">
                        Open Project Period
                    </button>
                    @else
                    <button onclick="confirmCloseDomain({{ $active->id }}, 'project', '{{ $periodLabel }}')"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">
                        Close Project Period
                    </button>
                    @endif

                    @if($sStatus !== 'open')
                    <button onclick="periodAction({{ $active->id }}, 'open-domain', {domain:'support'})"
                        class="inline-flex items-center gap-1.5 px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all">
                        Open Support Period
                    </button>
                    @else
                    <button onclick="confirmCloseDomain({{ $active->id }}, 'support', '{{ $periodLabel }}')"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">
                        Close Support Period
                    </button>
                    @endif
                @endif

                {{-- Audit log --}}
                <button onclick="openAuditLog({{ $active->id }}, '{{ $periodLabel }}')"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-white text-gray-600 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">
                    Audit Log
                </button>

                {{-- Late exception requests button (visible to all with management access) --}}
                @if($canManageDomains || $canManageGlobal)
                <button onclick="openExRequestsModal()"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-white text-gray-600 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">
                    <i class="fas fa-user-clock text-yellow-500 text-xs"></i> Exception Requests
                </button>
                @endif
            </div>

            {{-- Closing hint --}}
            @if($canManageGlobal && $gStatus === 'open' && !$active->canCloseGlobal())
            <p class="text-xs text-gray-400 mt-3 flex items-center gap-1">
                <i class="fas fa-info-circle"></i>
                To close globally, both Project and Support domains must be closed first.
            </p>
            @endif
        </div>
    </div>

    @else
    {{-- No open period — check if there's a pending (not_open) one --}}
    @if($pending)
    @php
        $pendingLabel = $MONTHS[$pending->month] . ' ' . $pending->year;
        $pendingRange = \Carbon\Carbon::parse($pending->start_date)->format('d M Y') . ' – ' . \Carbon\Carbon::parse($pending->end_date)->format('d M Y');
    @endphp
    <div class="mb-6">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-lg font-semibold text-gray-900">Pending Period</h3>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-yellow-50 text-yellow-700 text-xs font-semibold rounded-full border border-yellow-200">
                <i class="fas fa-clock text-[10px]"></i> Not Opened
            </span>
        </div>
        <div class="bg-gray-50 rounded-lg border border-gray-200 p-5">
            <p class="text-sm font-semibold text-gray-800 mb-1">{{ $pendingLabel }}</p>
            <p class="text-xs text-gray-500 mb-4"><i class="fas fa-calendar-alt mr-1"></i> {{ $pendingRange }}</p>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-5">
                <div class="bg-white rounded-lg border border-gray-200 p-3">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Global (RPMO)</p>
                    @include('rpmo.periods._status_badge', ['status' => 'not_open'])
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-3">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Project Domain</p>
                    @include('rpmo.periods._status_badge', ['status' => 'not_open'])
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-3">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Support Domain</p>
                    @include('rpmo.periods._status_badge', ['status' => 'not_open'])
                </div>
            </div>

            <div class="flex flex-wrap gap-2 pt-4 border-t border-gray-200">
                @if($canManageGlobal)
                <button onclick="periodAction({{ $pending->id }}, 'open-global')"
                    class="inline-flex items-center gap-1.5 px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all">
                    <i class="fas fa-lock-open text-xs"></i> Open Period Globally
                </button>
                @else
                <div class="inline-flex items-center gap-2 px-4 py-2 bg-yellow-50 border border-yellow-200 text-yellow-700 text-sm rounded-lg">
                    <i class="fas fa-clock text-xs"></i> Waiting for RPMO to open this period globally
                </div>
                @endif
                <button onclick="openAuditLog({{ $pending->id }}, '{{ $pendingLabel }}')"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-white text-gray-600 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">
                    <i class="fas fa-list-alt text-xs"></i> Audit Log
                </button>
            </div>
        </div>
    </div>

    @else
    {{-- Truly no period at all --}}
    <div class="mb-6 bg-gray-50 rounded-lg border border-gray-200 p-10 text-center">
        <i class="fas fa-calendar-times text-3xl text-gray-300 mb-3 block"></i>
        <p class="text-gray-600 font-semibold">No Active Period</p>
        <p class="text-gray-400 text-xs mt-1">
            @if($canManageGlobal) Create and open a new period to get started.
            @else Waiting for RPMO Head to create and open a period.
            @endif
        </p>
        @if($canManageGlobal)
        <button onclick="openCreateModal()"
            class="mt-4 inline-flex items-center gap-2 px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all">
            <i class="fas fa-plus text-xs"></i> Create Period
        </button>
        @endif
    </div>
    @endif
    @endif

    </div>{{-- /LOCKED TOP --}}

    {{-- ══ SCROLLABLE BOTTOM: Period History ══ --}}
    <div class="flex-1 min-h-0 overflow-y-auto border-t border-gray-100">

    {{-- ── Period History Table ─────────────────────────────────────────────── --}}
    <div class="px-6 pb-6 pt-5">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-lg font-semibold text-gray-900">Period History</h3>
            <span class="text-xs text-gray-400">{{ $periods->count() }} period(s)</span>
        </div>

        @if($periods->count() > 0)
        <div class="overflow-x-auto border border-gray-200 rounded-lg">
            <table class="w-full text-sm" style="min-width:680px;">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3.5 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">Period</th>
                        <th class="px-4 py-3.5 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">Date Range</th>
                        <th class="px-4 py-3.5 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">Global</th>
                        <th class="px-4 py-3.5 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">Project</th>
                        <th class="px-4 py-3.5 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">Support</th>
                        <th class="px-4 py-3.5 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    @foreach($periods as $p)
                    @php
                        $pLabel = $MONTHS[$p->month] . ' ' . $p->year;
                        $pRange = \Carbon\Carbon::parse($p->start_date)->format('d M Y') . ' – ' . \Carbon\Carbon::parse($p->end_date)->format('d M Y');
                    @endphp
                    <tr class="hover:bg-gray-50 transition-colors {{ $active && $active->id === $p->id ? 'bg-blue-50' : '' }}">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-gray-800">{{ $pLabel }}</span>
                                @if($active && $active->id === $p->id)
                                <span class="px-1.5 py-0.5 bg-green-100 text-green-700 text-[10px] font-bold rounded uppercase">Active</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500">{{ $pRange }}</td>
                        <td class="px-4 py-3 text-center">@include('rpmo.periods._status_badge', ['status' => $p->global_status])</td>
                        <td class="px-4 py-3 text-center">@include('rpmo.periods._status_badge', ['status' => $p->project_status])</td>
                        <td class="px-4 py-3 text-center">@include('rpmo.periods._status_badge', ['status' => $p->support_status])</td>
                        <td class="px-4 py-3 text-center">
                            <button onclick="openPeriodMenu(event, {{ $p->id }}, '{{ $pLabel }}', {
                                    isRpmo: {{ $isRpmo ? 'true' : 'false' }},
                                    isHead: {{ $isHead ? 'true' : 'false' }},
                                    isAdmin: {{ $isAdmin ? 'true' : 'false' }},
                                    canManageGlobal: {{ $canManageGlobal ? 'true' : 'false' }},
                                    canManageDomains: {{ $canManageDomains ? 'true' : 'false' }},
                                    headDomain: '{{ $headDomain ?? '' }}',
                                    globalStatus: '{{ $p->global_status }}',
                                    projectStatus: '{{ $p->project_status }}',
                                    supportStatus: '{{ $p->support_status }}',
                                    canCloseGlobal: {{ $p->canCloseGlobal() ? 'true' : 'false' }},
                                    isActive: {{ ($active && $active->id === $p->id) ? 'true' : 'false' }},
                                    startDate: '{{ \Carbon\Carbon::parse($p->start_date)->format('Y-m-d') }}',
                                    endDate: '{{ \Carbon\Carbon::parse($p->end_date)->format('Y-m-d') }}'
                                })"
                                class="p-1.5 text-gray-400 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">
                                <i class="fas fa-bars text-xs"></i>
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="border border-gray-200 rounded-lg py-10 text-center text-sm text-gray-400">
            No periods found.
        </div>
        @endif
    </div>{{-- /period history inner --}}

    </div>{{-- /SCROLLABLE BOTTOM --}}

</div>{{-- /card wrapper --}}

{{-- ════════════════════════════════════════════════════════════════ --}}
{{-- MODAL: Create Period                                            --}}
{{-- ════════════════════════════════════════════════════════════════ --}}
@if($canManageGlobal)
<div id="createPeriodModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-md w-full shadow-2xl overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <h3 class="text-base font-bold text-gray-900">Create New Period</h3>
            <button onclick="closeCreateModal()" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 transition-colors text-gray-400 hover:text-gray-600">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="p-6 space-y-4">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Year</label>
                    <input type="number" id="cpYear" min="2020" max="2100" required
                        oninput="computeDefaultDates()"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus-ring">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Month</label>
                    <div class="relative">
                        <select id="cpMonth" onchange="computeDefaultDates()" required
                            class="w-full px-3 py-2 pr-8 border border-gray-300 rounded-lg text-sm focus-ring appearance-none">
                            <option value="">Select</option>
                            @foreach(['','January','February','March','April','May','June','July','August','September','October','November','December'] as $i => $m)
                                @if($i > 0)<option value="{{ $i }}">{{ $m }}</option>@endif
                            @endforeach
                        </select>
                        <svg class="w-4 h-4 text-gray-400 absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 text-xs text-gray-500" id="cpDefaultHint">
                Select year and month to see default date range.
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Start Date</label>
                    <input type="date" id="cpStartDate" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus-ring">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">End Date</label>
                    <input type="date" id="cpEndDate" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus-ring">
                </div>
            </div>
            <p class="text-[10px] text-gray-400">Dates can be customized. Default follows the 21st–20th rule.</p>
        </div>
        <div class="px-6 pb-6 flex gap-3">
            <button onclick="closeCreateModal()" class="flex-1 px-4 py-2.5 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">Cancel</button>
            <button id="btnCreatePeriod" onclick="submitCreatePeriod()"
                class="flex-1 px-4 py-2.5 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all">
                Create Period
            </button>
        </div>
    </div>
</div>
@endif

{{-- ════════════════════════════════════════════════════════════════ --}}
{{-- MODAL: Confirm Close Global                                     --}}
{{-- ════════════════════════════════════════════════════════════════ --}}
<div id="closeGlobalModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-md w-full shadow-2xl overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <h3 class="text-base font-bold text-gray-900">Close Period Globally</h3>
            <button onclick="document.getElementById('closeGlobalModal').classList.replace('flex','hidden')" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 transition-colors text-gray-400 hover:text-gray-600">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="p-6">
            <p class="text-sm text-gray-600 mb-2">You are about to globally close period:</p>
            <p class="text-base font-bold text-gray-900 mb-4" id="cgPeriodLabel">—</p>
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-5 text-sm text-yellow-700">
                After closing, no new timesheets can be submitted for this period unless exceptions are granted.
            </div>
            <div class="flex gap-3">
                <button onclick="document.getElementById('closeGlobalModal').classList.replace('flex','hidden')" class="flex-1 px-4 py-2.5 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50">Cancel</button>
                <button id="btnConfirmCloseGlobal" onclick="executeCloseGlobal()"
                    class="flex-1 px-4 py-2.5 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all">
                    Yes, Close Period
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ════════════════════════════════════════════════════════════════ --}}
{{-- MODAL: Confirm Close Domain                                     --}}
{{-- ════════════════════════════════════════════════════════════════ --}}
<div id="closeDomainModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-md w-full shadow-2xl overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <h3 class="text-base font-bold text-gray-900">Close Domain Period</h3>
            <button onclick="document.getElementById('closeDomainModal').classList.replace('flex','hidden')" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 transition-colors text-gray-400 hover:text-gray-600">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="p-6">
            <p class="text-sm text-gray-600 mb-1">You are about to close the <strong id="cdDomainLabel" class="capitalize">—</strong> domain for:</p>
            <p class="text-base font-bold text-gray-900 mb-4" id="cdPeriodLabel">—</p>
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-5 text-sm text-yellow-700">
                Users in your domain will not be able to submit timesheets until RPMO opens the next period or grants a late exception.
            </div>
            <div class="flex gap-3">
                <button onclick="document.getElementById('closeDomainModal').classList.replace('flex','hidden')" class="flex-1 px-4 py-2.5 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50">Cancel</button>
                <button id="btnConfirmCloseDomain" onclick="executeCloseDomain()"
                    class="flex-1 px-4 py-2.5 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all">
                    Yes, Close Domain
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ════════════════════════════════════════════════════════════════ --}}
{{-- MODAL: Force Close                                              --}}
{{-- ════════════════════════════════════════════════════════════════ --}}
<div id="forceCloseModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-md w-full shadow-2xl overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-red-200 bg-red-50">
            <div class="flex items-center gap-2">
                <i class="fas fa-exclamation-triangle text-red-500"></i>
                <h3 class="text-base font-bold text-red-700">Force Close Domain</h3>
            </div>
            <button onclick="document.getElementById('forceCloseModal').classList.replace('flex','hidden')" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-red-100 transition-colors text-red-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="p-6">
            <p class="text-sm text-gray-600 mb-1">You are about to <strong class="text-red-600">force-close</strong> the <strong id="fcDomainLabel" class="capitalize">—</strong> domain for:</p>
            <p class="text-base font-bold text-gray-900 mb-4" id="fcPeriodLabel">—</p>
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-5 text-sm text-red-700">
                This overrides the normal closing flow. The action will be recorded in the audit log.
                <strong>This cannot be undone.</strong>
            </div>
            <div class="flex gap-3">
                <button onclick="document.getElementById('forceCloseModal').classList.replace('flex','hidden')" class="flex-1 px-4 py-2.5 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50">Cancel</button>
                <button id="btnConfirmForceClose" onclick="executeForceClose()"
                    class="flex-1 px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-lg transition-all">
                    Force Close
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ════════════════════════════════════════════════════════════════ --}}
{{-- MODAL: Audit Log                                                --}}
{{-- ════════════════════════════════════════════════════════════════ --}}
<div id="auditLogModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-2xl w-full shadow-2xl overflow-hidden flex flex-col max-h-[85vh]">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 flex-shrink-0">
            <div>
                <h3 class="text-base font-bold text-gray-900">Audit Log</h3>
                <p class="text-xs text-gray-500 mt-0.5" id="auditLogPeriodLabel">—</p>
            </div>
            <button onclick="document.getElementById('auditLogModal').classList.replace('flex','hidden')" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 transition-colors text-gray-400 hover:text-gray-600">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto p-4">
            <div id="auditLogLoading" class="py-10 text-center">
                <svg class="w-7 h-7 text-gray-300 mx-auto mb-2 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <p class="text-sm text-gray-400">Loading…</p>
            </div>
            <div id="auditLogContent" class="hidden">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="pb-2.5 pt-2 px-2 text-left text-gray-500 font-semibold uppercase tracking-wide border-b border-gray-200">Action</th>
                            <th class="pb-2.5 pt-2 px-2 text-left text-gray-500 font-semibold uppercase tracking-wide border-b border-gray-200">Actor</th>
                            <th class="pb-2.5 pt-2 px-2 text-left text-gray-500 font-semibold uppercase tracking-wide border-b border-gray-200">Timestamp</th>
                        </tr>
                    </thead>
                    <tbody id="auditLogBody" class="divide-y divide-gray-100"></tbody>
                </table>
            </div>
            <div id="auditLogEmpty" class="hidden py-10 text-center text-sm text-gray-400">No audit entries yet.</div>
        </div>
    </div>
</div>

{{-- Legacy "Grant Late Access" modal removed. All access is now request-based. --}}

@push('scripts')
<script>
let _activePeriodId  = null;
let _pendingPeriodId = null;
let _pendingDomain   = null;
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content;
const getHeaders = () => ({ 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': CSRF });

// Teleport dropdown ke body agar tidak ter-clip oleh overflow-x-hidden pada <main>
document.addEventListener('DOMContentLoaded', () => {
    const menu = document.getElementById('floatingPeriodMenu');
    if (menu) document.body.appendChild(menu);
});

async function api(url, method = 'GET', body = null) {
    const opts = {
        method,
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
    };
    if (body) opts.body = JSON.stringify(body);
    const res = await fetch(url, opts);
    return res.json();
}

function reloadPage() { window.location.reload(); }

// ── Create Period ─────────────────────────────────────────────────────────────
function openCreateModal() {
    const now = new Date();
    document.getElementById('cpYear').value  = now.getFullYear();
    document.getElementById('cpMonth').value = now.getMonth() + 1;
    computeDefaultDates();
    document.getElementById('createPeriodModal').classList.replace('hidden', 'flex');
}
function closeCreateModal() {
    document.getElementById('createPeriodModal').classList.replace('flex', 'hidden');
}
function computeDefaultDates() {
    const year  = parseInt(document.getElementById('cpYear').value)  || 0;
    const month = parseInt(document.getElementById('cpMonth').value) || 0;
    if (!year || !month) return;
    const pad    = n => String(n).padStart(2, '0');
    // Period M of year Y = 21st of (M-1) → 20th of M
    const prevM  = month === 1 ? 12 : month - 1;
    const prevY  = month === 1 ? year - 1 : year;
    const start  = `${prevY}-${pad(prevM)}-21`;
    const end    = `${year}-${pad(month)}-20`;
    document.getElementById('cpStartDate').value      = start;
    document.getElementById('cpEndDate').value        = end;
    document.getElementById('cpDefaultHint').textContent = `Default: ${start} – ${end} (21st–20th rule)`;
}
async function submitCreatePeriod() {
    const year  = parseInt(document.getElementById('cpYear').value);
    const month = parseInt(document.getElementById('cpMonth').value);
    const start = document.getElementById('cpStartDate').value;
    const end   = document.getElementById('cpEndDate').value;
    if (!year || !month || !start || !end) { showNotification('Please fill in all fields.', 'error'); return; }
    const btn = document.getElementById('btnCreatePeriod');
    btn.disabled = true; btn.textContent = 'Creating…';
    const json = await api('/api/periods', 'POST', { year, month, start_date: start, end_date: end });
    btn.disabled = false; btn.textContent = 'Create Period';
    if (json.success) { showNotification(json.message, 'success'); closeCreateModal(); setTimeout(reloadPage, 800); }
    else showNotification(json.message || 'Failed to create period.', 'error');
}

// ── Generic period action ─────────────────────────────────────────────────────
async function periodAction(periodId, action, body = null) {
    const json = await api(`/api/periods/${periodId}/${action}`, 'POST', body);
    if (json.success) { showNotification(json.message, 'success'); setTimeout(reloadPage, 800); }
    else showNotification(json.message || 'Action failed.', 'error');
}

// ── Close Global ─────────────────────────────────────────────────────────────
function confirmCloseGlobal(periodId, label) {
    _pendingPeriodId = periodId;
    document.getElementById('cgPeriodLabel').textContent = label;
    document.getElementById('closeGlobalModal').classList.replace('hidden', 'flex');
}
async function executeCloseGlobal() {
    const btn = document.getElementById('btnConfirmCloseGlobal');
    btn.disabled = true; btn.textContent = 'Closing…';
    const json = await api(`/api/periods/${_pendingPeriodId}/close-global`, 'POST');
    btn.disabled = false; btn.textContent = 'Yes, Close Period';
    document.getElementById('closeGlobalModal').classList.replace('flex', 'hidden');
    if (json.success) { showNotification(json.message, 'success'); setTimeout(reloadPage, 800); }
    else showNotification(json.message || 'Failed to close period.', 'error');
}

// ── Close Domain ──────────────────────────────────────────────────────────────
function confirmCloseDomain(periodId, domain, label) {
    _pendingPeriodId = periodId; _pendingDomain = domain;
    document.getElementById('cdPeriodLabel').textContent = label;
    document.getElementById('cdDomainLabel').textContent = domain;
    document.getElementById('closeDomainModal').classList.replace('hidden', 'flex');
}
async function executeCloseDomain() {
    const btn = document.getElementById('btnConfirmCloseDomain');
    btn.disabled = true; btn.textContent = 'Closing…';
    const json = await api(`/api/periods/${_pendingPeriodId}/close-domain`, 'POST', { domain: _pendingDomain });
    btn.disabled = false; btn.textContent = 'Yes, Close Domain';
    document.getElementById('closeDomainModal').classList.replace('flex', 'hidden');
    if (json.success) { showNotification(json.message, 'success'); setTimeout(reloadPage, 800); }
    else showNotification(json.message || 'Failed to close domain.', 'error');
}

// ── Force Close ───────────────────────────────────────────────────────────────
function confirmForceClose(periodId, domain, label) {
    _pendingPeriodId = periodId; _pendingDomain = domain;
    document.getElementById('fcPeriodLabel').textContent = label;
    document.getElementById('fcDomainLabel').textContent = domain;
    document.getElementById('forceCloseModal').classList.replace('hidden', 'flex');
}
async function executeForceClose() {
    const btn = document.getElementById('btnConfirmForceClose');
    btn.disabled = true; btn.textContent = 'Force Closing…';
    const json = await api(`/api/periods/${_pendingPeriodId}/force-close`, 'POST', { domain: _pendingDomain });
    btn.disabled = false; btn.textContent = 'Force Close';
    document.getElementById('forceCloseModal').classList.replace('flex', 'hidden');
    if (json.success) { showNotification(json.message, 'success'); setTimeout(reloadPage, 800); }
    else showNotification(json.message || 'Failed to force close.', 'error');
}

// ── Audit Log ─────────────────────────────────────────────────────────────────
async function openAuditLog(periodId, label) {
    document.getElementById('auditLogPeriodLabel').textContent = label;
    document.getElementById('auditLogLoading').classList.remove('hidden');
    document.getElementById('auditLogContent').classList.add('hidden');
    document.getElementById('auditLogEmpty').classList.add('hidden');
    document.getElementById('auditLogModal').classList.replace('hidden', 'flex');
    const json = await api(`/api/periods/${periodId}/audit-logs`);
    document.getElementById('auditLogLoading').classList.add('hidden');
    if (!json.success || !json.data.length) {
        document.getElementById('auditLogEmpty').classList.remove('hidden'); return;
    }
    const esc  = s => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;');
    document.getElementById('auditLogBody').innerHTML = json.data.map(log => `
        <tr class="hover:bg-gray-50">
            <td class="py-2.5 px-2">
                <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold ${esc(log.action_color)} ${log.is_force ? 'ring-1 ring-red-400' : ''}">
                    ${log.is_force ? '⚠ ' : ''}${esc(log.action_label)}
                </span>
            </td>
            <td class="py-2.5 px-2 text-gray-700">${esc(log.actor_name)}</td>
            <td class="py-2.5 px-2 text-gray-400 whitespace-nowrap">${esc(log.created_at)}</td>
        </tr>
    `).join('');
    document.getElementById('auditLogContent').classList.remove('hidden');
}

// ── Click outside to close modals ────────────────────────────────────────────
['closeGlobalModal','closeDomainModal','forceCloseModal','auditLogModal',
 'createPeriodModal','editDatesModal','deletePeriodModal'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('click', e => { if (e.target === el) el.classList.replace('flex','hidden'); });
});

// ── Period action floating menu ───────────────────────────────────────────────
let _pmId = null, _pmLabel = null, _pmCfg = null;

function openPeriodMenu(event, id, label, cfg) {
    event.stopPropagation();
    _pmId    = id;
    _pmLabel = label;
    _pmCfg   = cfg;

    const menu = document.getElementById('floatingPeriodMenu');
    const body = document.getElementById('floatingPeriodMenuBody');
    const btn  = event.currentTarget;
    const rect = btn.getBoundingClientRect();

    // Build menu items
    let html = '';

    // Global actions (RPMO / Admin)
    if (cfg.canManageGlobal && cfg.globalStatus === 'not_open') {
        html += menuItem('fa-lock-open', 'text-green-500', 'Open Period Globally', `pmAction('open-global')`);
    }
    // RPMO can re-open a globally-closed period (override)
    if (cfg.canManageGlobal && cfg.globalStatus === 'closed') {
        html += menuItem('fa-undo', 'text-orange-500', 'Re-open Period Globally', `pmAction('open-global')`);
    }
    if (cfg.canManageGlobal && cfg.globalStatus === 'open' && cfg.canCloseGlobal) {
        html += menuItem('fa-lock', 'text-gray-500', 'Close Period Globally', `pmConfirmCloseGlobal()`);
    }

    // Domain actions: Head (own domain)
    if (cfg.isHead && cfg.globalStatus === 'open' && cfg.headDomain) {
        const domSt = cfg.headDomain === 'project' ? cfg.projectStatus : cfg.supportStatus;
        if (domSt !== 'open') {
            html += menuItem('fa-lock-open', 'text-green-500', `Open ${cap(cfg.headDomain)} Period`, `pmAction('open-domain')`);
        } else {
            html += menuItem('fa-lock', 'text-gray-500', `Close ${cap(cfg.headDomain)} Period`, `pmConfirmCloseDomain()`);
        }
    }

    // Domain actions: Admin (both domains)
    if (cfg.isAdmin && cfg.globalStatus === 'open') {
        if (cfg.projectStatus !== 'open') {
            html += menuItem('fa-lock-open', 'text-green-500', 'Open Project Period', `pmActionWithDomain('open-domain','project')`);
        } else {
            html += menuItem('fa-lock', 'text-gray-500', 'Close Project Period', `pmConfirmCloseDomainAdmin('project')`);
        }
        if (cfg.supportStatus !== 'open') {
            html += menuItem('fa-lock-open', 'text-green-500', 'Open Support Period', `pmActionWithDomain('open-domain','support')`);
        } else {
            html += menuItem('fa-lock', 'text-gray-500', 'Close Support Period', `pmConfirmCloseDomainAdmin('support')`);
        }
    }

    // Force close (RPMO / Admin)
    if (cfg.canManageGlobal && cfg.globalStatus === 'open') {
        if (cfg.projectStatus !== 'closed') {
            html += menuItem('fa-times-circle', 'text-red-400', 'Force Close Project', `pmForceClose('project')`);
        }
        if (cfg.supportStatus !== 'closed') {
            html += menuItem('fa-times-circle', 'text-red-400', 'Force Close Support', `pmForceClose('support')`);
        }
    }

    html += menuItem('fa-list-alt', 'text-gray-400', 'Audit Log', `pmAuditLog()`);
    if (cfg.canManageDomains || {{ $isRpmo ? 'true' : 'false' }} || {{ $isAdmin ? 'true' : 'false' }}) {
        html += menuItem('fa-user-clock', 'text-yellow-500', 'Exception Requests', `openExRequestsModal()`);
    }

    // Edit dates & delete (RPMO / Admin only)
    if (cfg.canManageGlobal) {
        html += `<div class="border-t border-gray-100 my-1"></div>`;
        html += menuItem('fa-calendar-edit', 'text-indigo-500', 'Edit Dates', `pmEditDates()`);
        html += menuItem('fa-trash', 'text-red-400', 'Delete Period', `pmDeletePeriod()`);
    }

    body.innerHTML = html || `<p class="px-3 py-2 text-xs text-gray-400">No actions available</p>`;
    menu.classList.remove('hidden');

    // Smart positioning: flip ke atas jika ruang bawah tidak cukup
    // Untuk position:fixed gunakan koordinat viewport langsung (tanpa scrollY)
    const mw = menu.offsetWidth;
    const mh = menu.scrollHeight;
    const spaceBelow = window.innerHeight - rect.bottom;
    const top = spaceBelow >= mh + 8
        ? rect.bottom + 4       // buka ke bawah
        : rect.top - mh - 4;   // flip ke atas
    menu.style.top  = Math.max(4, top) + 'px';
    menu.style.left = Math.max(8, Math.min(rect.right - mw, window.innerWidth - mw - 8)) + 'px';
}

function menuItem(icon, iconColor, label, onclick) {
    return `<button onclick="${onclick}" class="w-full flex items-center gap-2 px-3 py-2 text-xs text-gray-700 hover:bg-gray-50 transition-all text-left">
        <i class="fas ${icon} w-3.5 text-center ${iconColor}"></i>${label}
    </button>`;
}

function cap(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : ''; }

function closePeriodMenu() { document.getElementById('floatingPeriodMenu').classList.add('hidden'); }

function pmAction(action)                  { closePeriodMenu(); periodAction(_pmId, action); }
function pmActionWithDomain(action, domain){ closePeriodMenu(); periodAction(_pmId, action, {domain}); }
function pmConfirmCloseGlobal()            { closePeriodMenu(); confirmCloseGlobal(_pmId, _pmLabel); }
function pmConfirmCloseDomain()            { closePeriodMenu(); confirmCloseDomain(_pmId, _pmCfg.headDomain, _pmLabel); }
function pmConfirmCloseDomainAdmin(domain) { closePeriodMenu(); confirmCloseDomain(_pmId, domain, _pmLabel); }
function pmForceClose(domain)              { closePeriodMenu(); confirmForceClose(_pmId, domain, _pmLabel); }
function pmAuditLog()                      { closePeriodMenu(); openAuditLog(_pmId, _pmLabel); }
function pmEditDates()                     { closePeriodMenu(); openEditDatesModal(_pmId, _pmLabel, _pmCfg); }
function pmDeletePeriod()                  { closePeriodMenu(); openDeletePeriodModal(_pmId, _pmLabel, _pmCfg); }

document.addEventListener('click', closePeriodMenu);

// ── Edit Period Dates ─────────────────────────────────────────────────────────
let _edPeriodId = null;

function openEditDatesModal(id, label, cfg) {
    _edPeriodId = id;
    document.getElementById('edPeriodLabel').textContent = label;
    // Fetch current dates from the period row already in the menu config
    // We need to fetch them — do a quick API call
    api(`/api/periods/${id}/audit-logs`).then(() => {}); // just warm up; dates come from the row
    // Pre-fill by reading the rendered date range cell in the table
    // Simpler: store start/end in cfg — we'll add them to openPeriodMenu call below
    document.getElementById('edStartDate').value = cfg.startDate || '';
    document.getElementById('edEndDate').value   = cfg.endDate   || '';
    document.getElementById('editDatesModal').classList.replace('hidden', 'flex');
}
function closeEditDatesModal() {
    document.getElementById('editDatesModal').classList.replace('flex', 'hidden');
}
async function submitEditDates() {
    const start = document.getElementById('edStartDate').value;
    const end   = document.getElementById('edEndDate').value;
    if (!start || !end) { showNotification('Please fill in both dates.', 'error'); return; }
    if (end <= start)   { showNotification('End date must be after start date.', 'error'); return; }
    const btn = document.getElementById('btnSaveEditDates');
    btn.disabled = true; btn.textContent = 'Saving…';
    const json = await api(`/api/periods/${_edPeriodId}/dates`, 'PATCH', { start_date: start, end_date: end });
    btn.disabled = false; btn.textContent = 'Save Dates';
    if (json.success) {
        showNotification(json.message, 'success');
        closeEditDatesModal();
        setTimeout(reloadPage, 800);
    } else {
        showNotification(json.message || 'Failed to update dates.', 'error');
    }
}

// ── Delete Period ─────────────────────────────────────────────────────────────
let _dpPeriodId = null;

function openDeletePeriodModal(id, label, cfg) {
    _dpPeriodId = id;
    document.getElementById('dpPeriodLabel').textContent = label;
    const wasOpened = cfg.globalStatus !== 'not_open';
    document.getElementById('dpWarnOpened').classList.toggle('hidden', !wasOpened);
    document.getElementById('dpWarnNotOpen').classList.toggle('hidden', wasOpened);
    document.getElementById('deletePeriodModal').classList.replace('hidden', 'flex');
}
function closeDeletePeriodModal() {
    document.getElementById('deletePeriodModal').classList.replace('flex', 'hidden');
}
async function executeDeletePeriod() {
    const btn = document.getElementById('btnConfirmDeletePeriod');
    btn.disabled = true; btn.textContent = 'Deleting…';
    const json = await api(`/api/periods/${_dpPeriodId}`, 'DELETE');
    btn.disabled = false; btn.textContent = 'Delete Period';
    closeDeletePeriodModal();
    if (json.success) { showNotification(json.message, 'success'); setTimeout(reloadPage, 800); }
    else showNotification(json.message || 'Failed to delete period.', 'error');
}
</script>
@endpush

{{-- Floating period action menu --}}
<div id="floatingPeriodMenu"
     class="hidden fixed z-[9999] w-52 bg-white border border-gray-200 rounded-lg shadow-xl py-1"
     onclick="event.stopPropagation()">
    <div id="floatingPeriodMenuBody"></div>
</div>

{{-- ════════════════════════════════════════════════════════════════ --}}
{{-- MODAL: Edit Period Dates (RPMO / Admin)                        --}}
{{-- ════════════════════════════════════════════════════════════════ --}}
@if($canManageGlobal)
<div id="editDatesModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-md w-full shadow-2xl overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <div>
                <h3 class="text-base font-bold text-gray-900">Edit Period Dates</h3>
                <p class="text-xs text-gray-500 mt-0.5" id="edPeriodLabel">—</p>
            </div>
            <button onclick="closeEditDatesModal()" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 transition-colors text-gray-400 hover:text-gray-600">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="p-6 space-y-4">
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 text-xs text-yellow-700">
                <i class="fas fa-info-circle mr-1"></i>
                Changing dates will affect timesheet submission windows. All timesheets already submitted will remain unchanged.
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Start Date</label>
                    <input type="date" id="edStartDate" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus-ring">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">End Date</label>
                    <input type="date" id="edEndDate" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus-ring">
                </div>
            </div>
        </div>
        <div class="px-6 pb-6 flex gap-3">
            <button onclick="closeEditDatesModal()" class="flex-1 px-4 py-2.5 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">Cancel</button>
            <button id="btnSaveEditDates" onclick="submitEditDates()"
                class="flex-1 px-4 py-2.5 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all">
                Save Dates
            </button>
        </div>
    </div>
</div>

{{-- ════════════════════════════════════════════════════════════════ --}}
{{-- MODAL: Delete Period (RPMO / Admin)                            --}}
{{-- ════════════════════════════════════════════════════════════════ --}}
<div id="deletePeriodModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-md w-full shadow-2xl overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-red-200 bg-red-50">
            <div class="flex items-center gap-2">
                <i class="fas fa-trash text-red-500"></i>
                <h3 class="text-base font-bold text-red-700">Delete Period</h3>
            </div>
            <button onclick="closeDeletePeriodModal()" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-red-100 transition-colors text-red-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="p-6">
            <p class="text-sm text-gray-600 mb-2">You are about to permanently delete period:</p>
            <p class="text-base font-bold text-gray-900 mb-4" id="dpPeriodLabel">—</p>
            <div id="dpWarnOpened" class="hidden bg-red-50 border border-red-200 rounded-lg p-3 mb-4 text-sm text-red-700">
                <i class="fas fa-exclamation-triangle mr-1"></i>
                <strong>Warning:</strong> This period has already been opened. Deleting it will also remove all related audit logs, late exception records, and requests. This action cannot be undone.
            </div>
            <div id="dpWarnNotOpen" class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-4 text-sm text-yellow-700">
                This will permanently remove this period and all associated records. This action cannot be undone.
            </div>
            <div class="flex gap-3">
                <button onclick="closeDeletePeriodModal()" class="flex-1 px-4 py-2.5 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50">Cancel</button>
                <button id="btnConfirmDeletePeriod" onclick="executeDeletePeriod()"
                    class="flex-1 px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-lg transition-all">
                    Delete Period
                </button>
            </div>
        </div>
    </div>
</div>
@endif

{{-- ════════════════════════════════════════════════════════════════ --}}
{{-- MODAL: Exception Requests Queue (Head / RPMO / Admin)          --}}
{{-- ════════════════════════════════════════════════════════════════ --}}
@if($canManageDomains || $isRpmo || $isAdmin)
<div id="exRequestsModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-2xl w-full shadow-2xl overflow-hidden flex flex-col max-h-[85vh]">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 flex-shrink-0">
            <h3 class="text-base font-bold text-gray-900">
                <i class="fas fa-user-clock text-yellow-500 mr-1.5"></i>
                Late Access Requests
            </h3>
            <button onclick="closeExRequestsModal()" class="w-7 h-7 flex items-center justify-center rounded-lg bg-gray-100 text-gray-600 hover:bg-red-800 hover:text-white transition-all">
                <i class="fas fa-times text-xs"></i>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto px-6 py-4">
            <div id="exRequestsList"><p class="text-xs text-gray-400 text-center py-8">Loading...</p></div>
        </div>
    </div>
</div>
@endif

<script>
// ── Exception Request Queue (Head / RPMO / Admin) ─────────────────────────────
async function openExRequestsModal() {
    document.getElementById('exRequestsModal').classList.remove('hidden');
    document.getElementById('exRequestsList').innerHTML = '<p class="text-xs text-gray-400 text-center py-8">Loading...</p>';
    try {
        const res  = await fetch('/api/periods/exception-requests', { headers: getHeaders(), credentials: 'same-origin' });
        const data = await res.json();
        renderExRequests(data.data || []);
    } catch (e) {
        document.getElementById('exRequestsList').innerHTML = '<p class="text-xs text-red-400 text-center py-8">Failed to load data.</p>';
    }
}

function closeExRequestsModal() {
    document.getElementById('exRequestsModal').classList.add('hidden');
}

function renderExRequests(requests) {
    const esc = s => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;');
    const el  = document.getElementById('exRequestsList');
    if (!requests.length) {
        el.innerHTML = '<p class="text-xs text-gray-400 text-center py-8">No pending requests.</p>';
        return;
    }

    // Default deadline = 7 days from now in LOCAL time (datetime-local expects "YYYY-MM-DDTHH:mm" local)
    const defaultDeadline = (() => {
        const d   = new Date(Date.now() + 7 * 86400000);
        const pad = n => String(n).padStart(2, '0');
        return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
    })();

    el.innerHTML = requests.map(r => {
        const accessInfo = r.is_access_active
            ? `<p class="text-xs text-green-600 mt-1 flex items-center gap-1"><i class="fas fa-check-circle"></i>Access active until ${esc(r.expires_at)}</p>`
            : r.is_expired
                ? `<p class="text-xs text-gray-400 mt-1"><i class="fas fa-clock mr-1"></i>Access expired ${esc(r.expires_at)}</p>`
                : '';

        const headActions = r.status === 'pending_head' ? `
            <div class="mt-2 space-y-2">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Notes <span class="text-red-600">*</span></label>
                    <input type="text" placeholder="Notes (required)" id="headNote_${r.id}"
                        class="w-full px-2 py-1.5 border border-gray-200 rounded text-xs focus:outline-none focus:ring-1 focus:ring-blue-400">
                </div>
                <div class="flex gap-2">
                    <button onclick="decideExRequest(${r.id}, 'head', 'approve')"
                        class="px-3 py-1.5 bg-green-600 text-white text-xs font-semibold rounded hover:bg-green-700 transition-all">Approve</button>
                    <button onclick="decideExRequest(${r.id}, 'head', 'reject')"
                        class="px-3 py-1.5 bg-red-600 text-white text-xs font-semibold rounded hover:bg-red-700 transition-all">Reject</button>
                </div>
            </div>` : '';

        const rpmoActions = r.status === 'pending_rpmo' ? `
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mt-2 space-y-2">
                <p class="text-[10px] font-semibold text-yellow-700 uppercase tracking-wide">
                    <i class="fas fa-clock mr-1"></i>Head: ${esc(r.head_name ?? '-')} · ${esc(r.head_approved_at ?? '-')}
                    ${r.head_notes ? `— ${esc(r.head_notes)}` : ''}
                </p>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">
                        Access Deadline <span class="text-red-600">*</span>
                        <span class="font-normal text-gray-400">(required when approving)</span>
                    </label>
                    <input type="datetime-local" id="rpmoExpiry_${r.id}" value="${defaultDeadline}"
                        class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:outline-none focus:ring-1 focus:ring-blue-400">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">RPMO Notes <span class="text-red-600">*</span></label>
                    <input type="text" placeholder="RPMO Notes (required)" id="rpmoNote_${r.id}"
                        class="w-full px-2 py-1.5 border border-gray-200 rounded text-xs focus:outline-none focus:ring-1 focus:ring-blue-400">
                </div>
                <div class="flex gap-2">
                    <button onclick="decideExRequest(${r.id}, 'rpmo', 'approve')"
                        class="flex-1 px-3 py-1.5 bg-green-600 text-white text-xs font-semibold rounded hover:bg-green-700 transition-all">
                        <i class="fas fa-check mr-1"></i>Approve & Set Deadline
                    </button>
                    <button onclick="decideExRequest(${r.id}, 'rpmo', 'reject')"
                        class="px-3 py-1.5 bg-red-600 text-white text-xs font-semibold rounded hover:bg-red-700 transition-all">Reject</button>
                </div>
            </div>` : '';

        return `
        <div class="border border-gray-200 rounded-lg p-4 mb-3 bg-white hover:shadow-sm transition-all
            ${r.is_access_active ? 'border-green-200 bg-green-50/30' : ''}
            ${r.is_expired ? 'opacity-60' : ''}">
            <div class="flex items-start justify-between gap-3 mb-2">
                <div>
                    <p class="text-sm font-semibold text-gray-900">${esc(r.employee_name)}</p>
                    <p class="text-xs text-gray-500">${esc(r.period_label)} &nbsp;·&nbsp; ${r.domain === 'project' ? 'Project' : 'Support'}</p>
                    <p class="text-xs text-gray-400 mt-0.5">${esc(r.period_start)} – ${esc(r.period_end)}</p>
                </div>
                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold shrink-0 ${r.status_color}">${esc(r.status_label)}</span>
            </div>
            ${r.notes ? `<p class="text-xs text-gray-600 bg-gray-50 rounded px-2 py-1.5 mb-2"><i class="fas fa-quote-left text-gray-300 mr-1"></i>${esc(r.notes)}</p>` : ''}
            ${accessInfo}
            ${headActions}${rpmoActions}
            ${r.status === 'rejected' ? `<p class="text-xs text-red-500 mt-1"><i class="fas fa-times-circle mr-1"></i>Rejected by ${esc(r.rejected_by ?? '-')}: ${esc(r.rejection_notes ?? '-')}</p>` : ''}
            <p class="text-xs text-gray-300 mt-2">Submitted: ${esc(r.created_at)}</p>
        </div>`;
    }).join('');
}

async function decideExRequest(reqId, level, decision) {
    const noteEl   = document.getElementById(`${level}Note_${reqId}`);
    const expiryEl = document.getElementById(`rpmoExpiry_${reqId}`);
    const notes    = noteEl?.value?.trim() || '';
    const url      = `/api/periods/exception-requests/${reqId}/${level}-decide`;

    // Notes are required for all decisions
    if (!notes) {
        showNotification('Notes are required before submitting your decision.', 'error');
        noteEl?.focus();
        return;
    }

    const body = { decision, notes };

    // RPMO approving: expires_at is required
    if (level === 'rpmo' && decision === 'approve') {
        if (!expiryEl?.value) {
            showNotification('Access deadline is required when approving.', 'error');
            return;
        }
        // Send the raw local-time string — backend parses with app timezone (Asia/Jakarta)
        body.expires_at = expiryEl.value; // "YYYY-MM-DDTHH:mm" local
    }

    try {
        const res  = await fetch(url, {
            method: 'PATCH',
            headers: getHeaders(),
            credentials: 'same-origin',
            body: JSON.stringify(body),
        });
        const data = await res.json();
        if (data.success) {
            showNotification(data.message, 'success');
            openExRequestsModal(); // refresh list
        } else {
            showNotification(data.message || 'Failed to process request.', 'error');
        }
    } catch (e) {
        showNotification('Network error.', 'error');
    }
}
</script>
@endsection

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
    $isRpmo        = $roleId === 7;
    $isProjectHead = $roleId === 4;
    $isSupportHead = $roleId === 5;
    $isAdmin       = $roleId === 1;
    $isHead        = $isProjectHead || $isSupportHead;
    $headDomain    = $isProjectHead ? 'project' : ($isSupportHead ? 'support' : null);
    $MONTHS        = ['','January','February','March','April','May','June','July','August','September','October','November','December'];
@endphp

<div class="bg-white rounded-xl p-6 shadow-sm">

    {{-- ── Page Header ──────────────────────────────────────────────────────── --}}
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 pb-4 border-b-2 border-gray-100">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Period Management</h2>
            <p class="text-sm text-gray-500 mt-0.5">
                @if($isRpmo) Full period lifecycle control (RPMO)
                @elseif($isProjectHead) Project domain control & late exceptions
                @elseif($isSupportHead) Support domain control & late exceptions
                @else Read-only view with audit logs
                @endif
            </p>
        </div>
        @if($isRpmo)
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
                <div class="bg-white rounded-lg border {{ $isProjectHead ? 'border-blue-300' : 'border-gray-200' }} p-3">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                        Project Domain @if($isProjectHead)<span class="text-blue-500 normal-case font-normal"></span>@endif
                    </p>
                    @include('rpmo.periods._status_badge', ['status' => $pStatus])
                    @if($active->project_opened_at)
                    <p class="text-[10px] text-gray-400 mt-1.5">Opened {{ \Carbon\Carbon::parse($active->project_opened_at)->format('d M Y') }}</p>
                    @endif
                </div>
                <div class="bg-white rounded-lg border {{ $isSupportHead ? 'border-blue-300' : 'border-gray-200' }} p-3">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                        Support Domain @if($isSupportHead)<span class="text-blue-500 normal-case font-normal"></span>@endif
                    </p>
                    @include('rpmo.periods._status_badge', ['status' => $sStatus])
                    @if($active->support_opened_at)
                    <p class="text-[10px] text-gray-400 mt-1.5">Opened {{ \Carbon\Carbon::parse($active->support_opened_at)->format('d M Y') }}</p>
                    @endif
                </div>
            </div>

            {{-- Action buttons --}}
            <div class="flex flex-wrap gap-2 pt-4 border-t border-gray-200">

                {{-- RPMO actions --}}
                @if($isRpmo)
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

                {{-- Head actions --}}
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
                @endif

                {{-- Audit log --}}
                <button onclick="openAuditLog({{ $active->id }}, '{{ $periodLabel }}')"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-white text-gray-600 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">
                    Audit Log
                </button>

                {{-- Late exceptions (Heads only) --}}
                @if($isHead)
                <button onclick="openExceptionsModal({{ $active->id }}, '{{ $periodLabel }}')"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-white text-gray-600 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">
                    Late Exceptions
                </button>
                @endif
            </div>

            {{-- Closing hint --}}
            @if($isRpmo && $gStatus === 'open' && !$active->canCloseGlobal())
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
                @if($isRpmo)
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
            @if($isRpmo) Create and open a new period to get started.
            @else Waiting for RPMO Head to create and open a period.
            @endif
        </p>
        @if($isRpmo)
        <button onclick="openCreateModal()"
            class="mt-4 inline-flex items-center gap-2 px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all">
            <i class="fas fa-plus text-xs"></i> Create Period
        </button>
        @endif
    </div>
    @endif
    @endif

    {{-- ── Period History Table ─────────────────────────────────────────────── --}}
    <div class="mt-6">
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
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-1">

                                {{-- RPMO: Open Globally --}}
                                @if($isRpmo && $p->global_status === 'not_open')
                                <button onclick="periodAction({{ $p->id }}, 'open-global')"
                                    title="Open Period Globally"
                                    class="p-1.5 text-green-500 hover:text-green-700 hover:bg-green-50 rounded-lg transition-colors">
                                    <i class="fas fa-lock-open text-xs"></i>
                                </button>
                                @endif

                                {{-- RPMO: Close Globally --}}
                                @if($isRpmo && $p->global_status === 'open' && $p->canCloseGlobal())
                                <button onclick="confirmCloseGlobal({{ $p->id }}, '{{ $pLabel }}')"
                                    title="Close Period Globally"
                                    class="p-1.5 text-gray-500 hover:text-gray-800 hover:bg-gray-100 rounded-lg transition-colors">
                                    <i class="fas fa-lock text-xs"></i>
                                </button>
                                @endif

                                {{-- Head: Open Domain --}}
                                @if($isHead && $p->global_status === 'open' && $p->domainStatus($headDomain) !== 'open')
                                <button onclick="periodAction({{ $p->id }}, 'open-domain')"
                                    title="Open {{ ucfirst($headDomain) }} Domain"
                                    class="p-1.5 text-green-500 hover:text-green-700 hover:bg-green-50 rounded-lg transition-colors">
                                    <i class="fas fa-lock-open text-xs"></i>
                                </button>
                                @endif

                                {{-- Head: Close Domain --}}
                                @if($isHead && $p->domainStatus($headDomain) === 'open')
                                <button onclick="confirmCloseDomain({{ $p->id }}, '{{ $headDomain }}', '{{ $pLabel }}')"
                                    title="Close {{ ucfirst($headDomain) }} Domain"
                                    class="p-1.5 text-gray-500 hover:text-gray-800 hover:bg-gray-100 rounded-lg transition-colors">
                                    <i class="fas fa-lock text-xs"></i>
                                </button>
                                @endif

                                {{-- RPMO Force Close --}}
                                @if($isRpmo && $p->global_status === 'open')
                                    @if($p->project_status !== 'closed')
                                    <button onclick="confirmForceClose({{ $p->id }}, 'project', '{{ $pLabel }}')"
                                        title="Force Close Project"
                                        class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                                        <i class="fas fa-times-circle text-xs"></i>
                                    </button>
                                    @endif
                                    @if($p->support_status !== 'closed')
                                    <button onclick="confirmForceClose({{ $p->id }}, 'support', '{{ $pLabel }}')"
                                        title="Force Close Support"
                                        class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                                        <i class="fas fa-times-circle text-xs"></i>
                                    </button>
                                    @endif
                                @endif

                                {{-- Audit Log --}}
                                <button onclick="openAuditLog({{ $p->id }}, '{{ $pLabel }}')"
                                    title="View Audit Log"
                                    class="p-1.5 text-gray-400 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">
                                    <i class="fas fa-list-alt text-xs"></i>
                                </button>

                                {{-- Head: Late Exceptions --}}
                                @if($isHead && $active && $active->id === $p->id)
                                <button onclick="openExceptionsModal({{ $p->id }}, '{{ $pLabel }}')"
                                    title="Manage Late Exceptions"
                                    class="p-1.5 text-gray-400 hover:text-yellow-600 hover:bg-yellow-50 rounded-lg transition-colors">
                                    <i class="fas fa-user-clock text-xs"></i>
                                </button>
                                @endif

                            </div>
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
    </div>

</div>{{-- /bg-white wrapper --}}

{{-- ════════════════════════════════════════════════════════════════ --}}
{{-- MODAL: Create Period                                            --}}
{{-- ════════════════════════════════════════════════════════════════ --}}
@if($isRpmo)
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
                    <input type="number" id="cpYear" min="2020" max="2100"
                        oninput="computeDefaultDates()"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus-ring">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Month</label>
                    <div class="relative">
                        <select id="cpMonth" onchange="computeDefaultDates()"
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
                    <input type="date" id="cpStartDate" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus-ring">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">End Date</label>
                    <input type="date" id="cpEndDate" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus-ring">
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

{{-- ════════════════════════════════════════════════════════════════ --}}
{{-- MODAL: Late Exceptions (Heads only)                            --}}
{{-- ════════════════════════════════════════════════════════════════ --}}
@if($isHead)
<div id="exceptionsModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-lg w-full shadow-2xl overflow-hidden flex flex-col max-h-[85vh]">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 flex-shrink-0">
            <div>
                <h3 class="text-base font-bold text-gray-900">Late Submission Exceptions</h3>
                <p class="text-xs text-gray-500 mt-0.5" id="excPeriodLabel">—</p>
            </div>
            <button onclick="document.getElementById('exceptionsModal').classList.replace('flex','hidden')" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 transition-colors text-gray-400 hover:text-gray-600">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto p-5 space-y-4">
            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-3">Grant Late Access</p>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Employee</label>
                        <div class="relative">
                            <select id="excEmployeeSelect"
                                class="w-full px-3 py-2 pr-8 border border-gray-300 rounded-lg text-sm focus-ring appearance-none bg-white">
                                <option value="">Select employee…</option>
                                @foreach($domainEmployees as $emp)
                                <option value="{{ $emp->employee_id }}">
                                    {{ $emp->basicData->full_name ?? "EMP#{$emp->employee_id}" }}
                                </option>
                                @endforeach
                            </select>
                            <svg class="w-4 h-4 text-gray-400 absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Notes <span class="text-gray-400 font-normal">(optional)</span></label>
                        <input type="text" id="excNotes" maxlength="500" placeholder="e.g. Late due to sick leave"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus-ring">
                    </div>
                    <button id="btnGrantException" onclick="grantException()"
                        class="w-full px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all">
                        Grant Access
                    </button>
                </div>
            </div>
            <div>
                <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-2">Current Exceptions</p>
                <div id="excListLoading" class="py-4 text-center text-xs text-gray-400">Loading…</div>
                <div id="excListContent" class="hidden space-y-2"></div>
                <div id="excListEmpty" class="hidden py-4 text-center text-xs text-gray-400">No exceptions granted yet.</div>
            </div>
        </div>
    </div>
</div>
@endif

@push('scripts')
<script>
let _activePeriodId  = null;
let _pendingPeriodId = null;
let _pendingDomain   = null;
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content;

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
    const pad   = n => String(n).padStart(2, '0');
    const start = `${year}-${pad(month)}-21`;
    const nextM = month === 12 ? 1 : month + 1;
    const nextY = month === 12 ? year + 1 : year;
    const end   = `${nextY}-${pad(nextM)}-20`;
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
async function periodAction(periodId, action) {
    const json = await api(`/api/periods/${periodId}/${action}`, 'POST');
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
    const json = await api(`/api/periods/${_pendingPeriodId}/close-domain`, 'POST');
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

// ── Late Exceptions ───────────────────────────────────────────────────────────
let _excPeriodId = null;

async function openExceptionsModal(periodId, label) {
    _excPeriodId = periodId;
    document.getElementById('excPeriodLabel').textContent = label;
    document.getElementById('exceptionsModal').classList.replace('hidden', 'flex');
    await refreshExceptions();
}
async function refreshExceptions() {
    document.getElementById('excListLoading').classList.remove('hidden');
    document.getElementById('excListContent').classList.add('hidden');
    document.getElementById('excListEmpty').classList.add('hidden');
    const json = await api(`/api/periods/${_excPeriodId}/exceptions`);
    document.getElementById('excListLoading').classList.add('hidden');
    if (!json.success || !json.data.length) {
        document.getElementById('excListEmpty').classList.remove('hidden'); return;
    }
    const esc  = s => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;');
    const list = document.getElementById('excListContent');
    list.innerHTML = json.data.map(ex => `
        <div class="flex items-center justify-between bg-white border border-gray-200 rounded-lg px-3 py-2.5">
            <div>
                <p class="text-sm font-medium text-gray-800">${esc(ex.employee_name)}</p>
                <p class="text-[10px] text-gray-400">Granted ${esc(ex.granted_at)} by ${esc(ex.granted_by)}</p>
                ${ex.notes ? `<p class="text-[10px] text-gray-500 mt-0.5">${esc(ex.notes)}</p>` : ''}
            </div>
            <button onclick="revokeException(${ex.id})"
                class="ml-3 px-2.5 py-1 text-xs font-semibold text-red-600 bg-red-50 hover:bg-red-100 rounded-lg transition-colors flex-shrink-0">
                Revoke
            </button>
        </div>
    `).join('');
    list.classList.remove('hidden');
}
async function grantException() {
    const empId = document.getElementById('excEmployeeSelect').value;
    const notes = document.getElementById('excNotes').value;
    if (!empId) { showNotification('Please select an employee.', 'error'); return; }
    const btn = document.getElementById('btnGrantException');
    btn.disabled = true; btn.textContent = 'Granting…';
    const json = await api(`/api/periods/${_excPeriodId}/exceptions`, 'POST', { employee_id: empId, notes });
    btn.disabled = false; btn.textContent = 'Grant Access';
    if (json.success) {
        showNotification(json.message, 'success');
        document.getElementById('excEmployeeSelect').value = '';
        document.getElementById('excNotes').value = '';
        await refreshExceptions();
    } else showNotification(json.message || 'Failed to grant access.', 'error');
}
async function revokeException(excId) {
    const json = await api(`/api/periods/${_excPeriodId}/exceptions/${excId}`, 'DELETE');
    if (json.success) { showNotification(json.message, 'success'); await refreshExceptions(); }
    else showNotification(json.message || 'Failed to revoke.', 'error');
}

// ── Click outside to close modals ────────────────────────────────────────────
['closeGlobalModal','closeDomainModal','forceCloseModal','auditLogModal',
 'exceptionsModal','createPeriodModal'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('click', e => { if (e.target === el) el.classList.replace('flex','hidden'); });
});
</script>
@endpush
@endsection

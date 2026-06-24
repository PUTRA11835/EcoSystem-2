@extends('dashboard')
@section('title', 'MD Validation')
@section('page-title', 'MD Validation')
@section('page-subtitle', 'Timesheet Report')

@push('styles')
<style>
    .primary-focus:focus {
        outline: none;
        border-color: var(--primary-color) !important;
        box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.15) !important;
    }
    .primary-text { color: var(--primary-color) !important; }

    /* Summary card active state */
    .rpt-card-active {
        border-color: var(--primary-color) !important;
        box-shadow: 0 0 0 2px rgba(var(--primary-rgb), 0.18) !important;
    }

    /* Sticky table header */
    #rptTableHead th {
        position: sticky;
        top: 0;
        z-index: 5;
    }

    /* Loading shimmer */
    @keyframes rptShimmer {
        0%   { opacity: 1; }
        50%  { opacity: 0.45; }
        100% { opacity: 1; }
    }
    .rpt-loading-icon { animation: rptShimmer 1.4s ease-in-out infinite; }

    /* ── Sort column headers ── */
    thead th.rpt-sortable { user-select: none; cursor: pointer; }
    thead th.rpt-sortable:hover { background: #f3f4f6; }
    .rpt-sort-icon { font-style: normal; transition: color 0.15s; }
    .rpt-sort-icon.active { color: #111827; }

    /* ── Column filter dropdown active state ── */
    .custom-dd.rpt-col-dd-active .custom-dd-arrow { color: #111827; }

</style>
@endpush

@section('content')
@php
    $canManage = $can('reporting.export-excel') || $can('reporting.close-period');
@endphp

<div class="bg-white rounded-xl p-6 shadow-sm">

    {{-- ── Page Header ────────────────────────────────────────────────────── --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6 pb-4 border-b-2 border-gray-100">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">MD Validation</h2>
            <p class="text-sm text-gray-500 mt-0.5">Approved support timesheets — MD quota vs consumed per employee</p>
        </div>
        @if($canManage)
        <div class="flex flex-wrap items-center gap-2">
            {{-- Period badge --}}
            <div id="periodBadge"
                 class="hidden items-center gap-2 px-3 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-xs text-gray-600">
                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <span id="periodLabel">—</span>
                <span id="periodStatus" class="font-semibold"></span>
            </div>
            {{-- Export Excel --}}
            <button id="btnExportExcel" onclick="exportExcel()"
                class="inline-flex items-center gap-1.5 px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-semibold rounded-lg hover:bg-gray-50 transition-all duration-200">
                <i class="fas fa-file-excel text-green-600 text-sm"></i>
                Export MD
            </button>
        </div>
        @endif
    </div>

    {{-- ── Summary Cards ───────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
        {{-- Total --}}
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Total Entries</p>
                    <p class="text-3xl font-bold text-gray-900 mt-1.5" id="rptCardTotal">0</p>
                </div>
                <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                </div>
            </div>
            <p class="text-xs text-gray-400 mt-2">All timesheet records</p>
        </div>
        {{-- Match --}}
        <div id="rptCardMatchBox"
             class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm cursor-pointer hover:border-green-400 hover:shadow-md transition-all duration-200"
             onclick="filterCardStatus('Match')">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Match</p>
                    <p class="text-3xl font-bold text-green-600 mt-1.5" id="rptCardMatch">0</p>
                </div>
                <div class="w-10 h-10 bg-green-50 rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
            <p class="text-xs text-gray-400 mt-2">MD consumed = quota</p>
        </div>
        {{-- Less --}}
        <div id="rptCardLessBox"
             class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm cursor-pointer hover:border-yellow-400 hover:shadow-md transition-all duration-200"
             onclick="filterCardStatus('Less')">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Less</p>
                    <p class="text-3xl font-bold text-yellow-600 mt-1.5" id="rptCardLess">0</p>
                </div>
                <div class="w-10 h-10 bg-yellow-50 rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"/>
                    </svg>
                </div>
            </div>
            <p class="text-xs text-gray-400 mt-2">MD consumed &lt; quota</p>
        </div>
        {{-- Over --}}
        <div id="rptCardOverBox"
             class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm cursor-pointer hover:border-red-400 hover:shadow-md transition-all duration-200"
             onclick="filterCardStatus('Over')">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Over</p>
                    <p class="text-3xl font-bold text-red-600 mt-1.5" id="rptCardOver">0</p>
                </div>
                <div class="w-10 h-10 bg-red-50 rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                    </svg>
                </div>
            </div>
            <p class="text-xs text-gray-400 mt-2">MD consumed &gt; quota</p>
        </div>
    </div>

    {{-- ── Data Table ──────────────────────────────────────────────────────── --}}
    <div class="rounded-xl border border-gray-200 overflow-hidden">

        {{-- Table container --}}
        <div class="overflow-auto" style="max-height: calc(100vh - 400px); min-height: 200px;">
            <table class="w-full text-sm border-collapse" style="min-width: 820px;">
                <thead id="rptTableHead" class="sticky top-0 z-10 bg-gray-50">
                    <tr>
                        {{-- Employee: text search + sort panel --}}
                        <th class="p-0 text-left whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:150px; position:relative;">
                            <button type="button" onclick="toggleRptTextPanel(event,'Employee')" class="w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                                <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Employee</span>
                                <svg class="w-3.5 h-3.5 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                                <svg id="rptTextIcon_Employee" class="w-3.5 h-3.5 text-gray-300 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 011 1v1.586a1 1 0 01-.293.707l-4.121 4.121A1 1 0 0012 12.121V15.5l-4 1.5v-4.879a1 1 0 00-.293-.707L3.586 7.293A1 1 0 013.293 6.586L3 5z" clip-rule="evenodd"/></svg>
                            </button>
                            <div id="rptTextPanel_Employee" class="hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] p-3" style="min-width:220px;">
                                <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Search employee</label>
                                <input type="text" id="colFilterRptEmployee" placeholder="Type name…" oninput="applyRptFilter()" onclick="event.stopPropagation()"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
                                <div class="flex justify-end gap-2 mt-2">
                                    <button type="button" onclick="clearRptTextPanel('Employee')" class="px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50">Clear</button>
                                    <button type="button" onclick="closeRptTextPanel('Employee')" class="px-3 py-1.5 text-xs text-white bg-red-700 hover:bg-red-800 rounded-md">Done</button>
                                </div>
                            </div>
                        </th>
                        {{-- Date: sort panel --}}
                        <th class="p-0 text-left whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:110px; position:relative;">
                            <button type="button" onclick="toggleRptDateSortPanel(event)" class="w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                                <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Date</span>
                                <svg class="w-3.5 h-3.5 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                                <span id="rptSortDateIcon" class="text-[10px] text-red-500 font-bold shrink-0">↓</span>
                            </button>
                            <div id="rptDateSortPanel" class="hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] p-3" style="min-width:180px;">
                                <span class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Sort</span>
                                <div class="flex gap-2 mb-2">
                                    <button type="button" onclick="setRptSort('date','asc')" id="rptSortDateAsc" class="flex-1 px-2 py-1 text-xs border border-gray-200 rounded-md hover:bg-gray-50">↑ Ascending</button>
                                    <button type="button" onclick="setRptSort('date','desc')" id="rptSortDateDesc" class="flex-1 px-2 py-1 text-xs border border-gray-200 rounded-md hover:bg-gray-50">↓ Descending</button>
                                </div>
                                <div class="flex justify-end"><button type="button" onclick="closeRptDateSortPanel()" class="px-3 py-1.5 text-xs text-white bg-red-700 hover:bg-red-800 rounded-md">Done</button></div>
                            </div>
                        </th>
                        {{-- Ticket: text search panel --}}
                        <th class="p-0 text-left whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:140px; position:relative;">
                            <button type="button" onclick="toggleRptTextPanel(event,'Ticket')" class="w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                                <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Ticket</span>
                                <svg class="w-3.5 h-3.5 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                                <svg id="rptTextIcon_Ticket" class="w-3.5 h-3.5 text-gray-300 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 011 1v1.586a1 1 0 01-.293.707l-4.121 4.121A1 1 0 0012 12.121V15.5l-4 1.5v-4.879a1 1 0 00-.293-.707L3.586 7.293A1 1 0 013.293 6.586L3 5z" clip-rule="evenodd"/></svg>
                            </button>
                            <div id="rptTextPanel_Ticket" class="hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] p-3" style="min-width:220px;">
                                <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Search ticket</label>
                                <input type="text" id="colFilterRptTicket" placeholder="e.g. TKT-001…" oninput="applyRptFilter()" onclick="event.stopPropagation()"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
                                <div class="flex justify-end gap-2 mt-2">
                                    <button type="button" onclick="clearRptTextPanel('Ticket')" class="px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50">Clear</button>
                                    <button type="button" onclick="closeRptTextPanel('Ticket')" class="px-3 py-1.5 text-xs text-white bg-red-700 hover:bg-red-800 rounded-md">Done</button>
                                </div>
                            </div>
                        </th>
                        {{-- Customer: text search panel --}}
                        <th class="p-0 text-left whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:140px; position:relative;">
                            <button type="button" onclick="toggleRptTextPanel(event,'Customer')" class="w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                                <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Customer</span>
                                <svg class="w-3.5 h-3.5 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                                <svg id="rptTextIcon_Customer" class="w-3.5 h-3.5 text-gray-300 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 011 1v1.586a1 1 0 01-.293.707l-4.121 4.121A1 1 0 0012 12.121V15.5l-4 1.5v-4.879a1 1 0 00-.293-.707L3.586 7.293A1 1 0 013.293 6.586L3 5z" clip-rule="evenodd"/></svg>
                            </button>
                            <div id="rptTextPanel_Customer" class="hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] p-3" style="min-width:220px;">
                                <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Search customer</label>
                                <input type="text" id="colFilterRptCustomer" placeholder="Type customer…" oninput="applyRptFilter()" onclick="event.stopPropagation()"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
                                <div class="flex justify-end gap-2 mt-2">
                                    <button type="button" onclick="clearRptTextPanel('Customer')" class="px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50">Clear</button>
                                    <button type="button" onclick="closeRptTextPanel('Customer')" class="px-3 py-1.5 text-xs text-white bg-red-700 hover:bg-red-800 rounded-md">Done</button>
                                </div>
                            </div>
                        </th>
                        <th class="px-3 py-2.5 text-center text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap border-b border-gray-200" style="min-width:90px;">MD Input</th>
                        <th class="px-3 py-2.5 text-center text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap border-b border-gray-200" style="min-width:85px;">Quota MD</th>
                        <th class="px-3 py-2.5 text-center text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap border-b border-gray-200" style="min-width:85px;">Remain</th>
                        {{-- MD Status: custom-dd --}}
                        <th class="p-0 text-left whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:110px;">
                            <div class="custom-dd relative w-full" id="ddColFilterRptMdStatus" data-fixed="true" data-onchange="applyRptFilter">
                                <button type="button" class="custom-dd-btn w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                                    <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">MD Status</span>
                                    <svg class="custom-dd-arrow w-3.5 h-3.5 text-gray-500 transition-all duration-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <input type="hidden" id="colFilterRptMdStatus" value="">
                                <div class="custom-dd-panel hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] py-1.5" style="max-height:200px;min-width:130px;">
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="">All</button>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="Match">Match</button>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="Less">Less</button>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="Over">Over</button>
                                </div>
                            </div>
                        </th>
                        {{-- Approval: custom-dd --}}
                        <th class="p-0 text-left whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:110px;">
                            <div class="custom-dd relative w-full" id="ddColFilterRptApproval" data-fixed="true" data-onchange="applyRptFilter">
                                <button type="button" class="custom-dd-btn w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                                    <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Approval</span>
                                    <svg class="custom-dd-arrow w-3.5 h-3.5 text-gray-500 transition-all duration-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <input type="hidden" id="colFilterRptApproval" value="">
                                <div class="custom-dd-panel hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] py-1.5" style="max-height:200px;min-width:140px;">
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="">All</button>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="approved">Approved</button>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="submitted">Submitted</button>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="draft">Draft</button>
                                </div>
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody id="rptTableBody" class="divide-y divide-gray-100 bg-white">
                    {{-- Initial loading state --}}
                    <tr>
                        <td colspan="9" class="px-4 py-16 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <svg class="w-8 h-8 text-gray-300 rpt-loading-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                          d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                </svg>
                                <p class="text-sm text-gray-400 font-medium">Loading report data…</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- Empty state (outside scroll area) --}}
        <div id="rptEmpty" class="hidden py-16 text-center">
            <svg class="w-14 h-14 text-gray-200 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <p class="text-gray-600 font-semibold text-sm">No timesheet records found</p>
            <p class="text-gray-400 text-xs mt-1">Try adjusting the date range or clearing the status filter</p>
        </div>
    </div>
</div>


@push('scripts')
<script src="/js/custom-dropdown.js?v={{ filemtime(public_path('js/custom-dropdown.js')) }}"></script>
<script>
let rptAllData    = [];
let rptFiltered   = [];
let currentPeriod = null;
let rptSortKey    = 'date';
let rptSortDir    = 'desc';

const MONTH_NAMES = ['January','February','March','April','May','June',
                     'July','August','September','October','November','December'];

// ── Init ──────────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', function () {
    if (typeof initCustomDropdowns === 'function') initCustomDropdowns();
    @if($canManage)
    loadCurrentPeriod();
    @endif
    loadReport();
    _updateRptSortVisuals();
});

document.addEventListener('click', function(e) {
    if (!e.target.closest('[id^="rptTextPanel_"]') && !e.target.closest('#rptDateSortPanel') &&
        !e.target.closest('[onclick*="toggleRptTextPanel"]') && !e.target.closest('[onclick*="toggleRptDateSortPanel"]')) {
        closeRptDateSortPanel();
        closeRptTextPanelAll();
    }
});



// ── Period info ───────────────────────────────────────────────────────────────

@if($canManage)
async function loadCurrentPeriod() {
    try {
        const res  = await fetch('/api/reporting/current-period', {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
            credentials: 'same-origin'
        });
        const json = await res.json();
        if (!json.success) return;
        currentPeriod = json.data;
        updatePeriodBadge();
    } catch (e) {
        console.error('loadCurrentPeriod error', e);
    }
}

function updatePeriodBadge() {
    if (!currentPeriod) return;
    const badge  = document.getElementById('periodBadge');
    const label  = document.getElementById('periodLabel');
    const status = document.getElementById('periodStatus');

    label.textContent = `${MONTH_NAMES[currentPeriod.month - 1]} ${currentPeriod.year}`;
    if (currentPeriod.is_closed) {
        status.textContent = '(Closed)';
        status.className   = 'font-semibold text-red-500';
    } else {
        status.textContent = '(Open)';
        status.className   = 'font-semibold text-green-500';
    }
    badge.classList.remove('hidden');
    badge.classList.add('flex');
}

// ── Export Excel ──────────────────────────────────────────────────────────────

function exportExcel() {
    const btn = document.getElementById('btnExportExcel');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin text-xs mr-1"></i>Exporting…'; }

    const params = new URLSearchParams();
    const employee  = document.getElementById('colFilterRptEmployee')?.value.trim();
    const ticket    = document.getElementById('colFilterRptTicket')?.value.trim();
    const customer  = document.getElementById('colFilterRptCustomer')?.value.trim();
    const mdStatus  = document.getElementById('colFilterRptMdStatus')?.value;
    const approval  = document.getElementById('colFilterRptApproval')?.value;
    if (employee) params.append('employee',  employee);
    if (ticket)   params.append('ticket',    ticket);
    if (customer) params.append('customer',  customer);
    if (mdStatus) params.append('md_status', mdStatus);
    if (approval) params.append('approval',  approval);

    window.location.href = `/reporting/export-excel?${params.toString()}`;
    setTimeout(() => {
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-file-excel text-green-600 text-sm"></i> Export MD'; }
    }, 3000);
}
@endif

// ── Report data ───────────────────────────────────────────────────────────────

async function loadReport() {
    const tbody  = document.getElementById('rptTableBody');

    tbody.innerHTML = `<tr>
        <td colspan="9" class="px-4 py-16 text-center">
            <div class="flex flex-col items-center gap-2">
                <svg class="w-8 h-8 text-gray-300 rpt-loading-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                <p class="text-sm text-gray-400 font-medium">Loading report data…</p>
            </div>
        </td>
    </tr>`;
    document.getElementById('rptEmpty')?.classList.add('hidden');

    try {
        const res  = await fetch(`/api/reporting/timesheet-support`, {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
            credentials: 'same-origin'
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'API error');

        rptAllData = json.data || [];
        applyRptFilter();
    } catch (e) {
        console.error(e);
        tbody.innerHTML = `<tr>
            <td colspan="9" class="px-4 py-12 text-center">
                <div class="flex flex-col items-center gap-2">
                    <svg class="w-8 h-8 text-red-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <p class="text-sm text-red-500 font-medium">Failed to load report</p>
                    <p class="text-xs text-gray-400">${escRpt(e.message)}</p>
                </div>
            </td>
        </tr>`;
    }
}

function applyRptFilter() {
    const empFilter      = (document.getElementById('colFilterRptEmployee')?.value  || '').trim().toLowerCase();
    const ticketFilter   = (document.getElementById('colFilterRptTicket')?.value    || '').trim().toLowerCase();
    const customerFilter = (document.getElementById('colFilterRptCustomer')?.value  || '').trim().toLowerCase();
    const mdStatusFilter =  document.getElementById('colFilterRptMdStatus')?.value  || '';
    const approvalFilter =  document.getElementById('colFilterRptApproval')?.value  || '';

    rptFiltered = rptAllData.filter(r => {
        if (empFilter      && !String(r.employee_name   ?? '').toLowerCase().includes(empFilter))     return false;
        if (ticketFilter   && !String(r.ticket_number   ?? r.ticket_id ?? '').toLowerCase().includes(ticketFilter)) return false;
        if (customerFilter && !String(r.customer_name   ?? '').toLowerCase().includes(customerFilter)) return false;
        if (mdStatusFilter && r.status           !== mdStatusFilter) return false;
        if (approvalFilter && r.timesheet_status !== approvalFilter) return false;
        return true;
    });

    _updateRptFilterIcons();
    renderRptTable();
    updateRptCards();
}

// ── Sort & panel helpers ──────────────────────────────────────────────────────

function setRptSort(key, dir) {
    rptSortKey = key;
    rptSortDir = dir;
    _updateRptSortVisuals();
    closeRptDateSortPanel();
    closeRptTextPanelAll();
    renderRptTable();
}

function _updateRptSortVisuals() {
    const dateIcon = document.getElementById('rptSortDateIcon');
    if (dateIcon) dateIcon.textContent = rptSortKey === 'date' ? (rptSortDir === 'asc' ? '↑' : '↓') : '';
    const _btn = (id, active) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.className = `flex-1 px-2 py-1 text-xs border rounded-md hover:bg-gray-50 transition-colors ${active ? 'border-red-400 bg-red-50 text-red-700 font-semibold' : 'border-gray-200'}`;
    };
    _btn('rptSortDateAsc',  rptSortKey === 'date' && rptSortDir === 'asc');
    _btn('rptSortDateDesc', rptSortKey === 'date' && rptSortDir === 'desc');
}

function _updateRptFilterIcons() {
    [['Employee','colFilterRptEmployee'],['Ticket','colFilterRptTicket'],['Customer','colFilterRptCustomer']].forEach(([key, id]) => {
        const icon  = document.getElementById('rptTextIcon_' + key);
        const input = document.getElementById(id);
        if (!icon) return;
        const active = !!(input?.value);
        icon.classList.toggle('text-red-500', active);
        icon.classList.toggle('text-gray-300', !active);
    });
}

function toggleRptDateSortPanel(event) {
    event.stopPropagation();
    const panel = document.getElementById('rptDateSortPanel');
    if (!panel) return;
    const wasHidden = panel.classList.contains('hidden');
    closeRptDateSortPanel(); closeRptTextPanelAll();
    if (typeof _closeAllDropdowns === 'function') _closeAllDropdowns();
    if (wasHidden) panel.classList.remove('hidden');
}

function closeRptDateSortPanel() {
    const p = document.getElementById('rptDateSortPanel');
    if (p) p.classList.add('hidden');
}

function toggleRptTextPanel(event, key) {
    event.stopPropagation();
    const panel = document.getElementById('rptTextPanel_' + key);
    if (!panel) return;
    const wasHidden = panel.classList.contains('hidden');
    closeRptDateSortPanel(); closeRptTextPanelAll();
    if (typeof _closeAllDropdowns === 'function') _closeAllDropdowns();
    if (wasHidden) {
        panel.classList.remove('hidden');
        const inp = panel.querySelector('input[type="text"]');
        if (inp) setTimeout(() => inp.focus(), 30);
    }
}

function closeRptTextPanel(key) {
    const p = document.getElementById('rptTextPanel_' + key);
    if (p) p.classList.add('hidden');
}

function closeRptTextPanelAll() {
    document.querySelectorAll('[id^="rptTextPanel_"]').forEach(p => p.classList.add('hidden'));
}

function clearRptTextPanel(key) {
    const inp = document.getElementById('colFilterRpt' + key);
    if (inp) { inp.value = ''; applyRptFilter(); }
}

function filterCardStatus(status) {
    if (typeof setCustomDropdownValue === 'function') setCustomDropdownValue('colFilterRptMdStatus', status);
    applyRptFilter();
}

function resetReport() {
    if (typeof setCustomDropdownValue === 'function') {
        setCustomDropdownValue('colFilterRptMdStatus', '');
        setCustomDropdownValue('colFilterRptApproval', '');
    }
    ['colFilterRptEmployee','colFilterRptTicket','colFilterRptCustomer'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    rptSortKey = 'date';
    rptSortDir = 'desc';
    _updateRptSortVisuals();
    _updateRptFilterIcons();
    closeRptDateSortPanel();
    closeRptTextPanelAll();
    applyRptFilter();
}

function updateRptCards() {
    const all   = rptAllData;
    const match = all.filter(r => r.status === 'Match').length;
    const less  = all.filter(r => r.status === 'Less').length;
    const over  = all.filter(r => r.status === 'Over').length;

    document.getElementById('rptCardTotal').textContent = all.length;
    document.getElementById('rptCardMatch').textContent = match;
    document.getElementById('rptCardLess').textContent  = less;
    document.getElementById('rptCardOver').textContent  = over;
}

function renderRptTable() {
    const tbody = document.getElementById('rptTableBody');
    const empty = document.getElementById('rptEmpty');

    if (rptFiltered.length === 0) {
        tbody.innerHTML = '';
        if (empty) empty.classList.remove('hidden');
        return;
    }
    if (empty) empty.classList.add('hidden');

    const statusBadge = (status) => {
        if (status === 'Match') return `<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700">Match</span>`;
        if (status === 'Less')  return `<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-700">Less</span>`;
        if (status === 'Over')  return `<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700">Over</span>`;
        return `<span class="text-xs text-gray-400">—</span>`;
    };

    const fmt     = (v) => v != null ? Number(v).toFixed(1) : '—';
    const fmtDate = (d) => {
        if (!d) return '—';
        const [y, m, day] = String(d).split('-');
        return `${day}/${m}/${y}`;
    };
    const remClass = (v) => v === null ? 'text-gray-400'
        : v < 0  ? 'text-red-600 font-semibold'
        : v === 0 ? 'text-green-600 font-semibold'
        : 'text-yellow-600 font-semibold';

    const approvalBadge = (s) => {
        if (s === 'approved')  return `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-green-100 text-green-700">Approved</span>`;
        if (s === 'submitted') return `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-blue-100 text-blue-700">Submitted</span>`;
        if (s === 'draft')     return `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-gray-100 text-gray-600">Draft</span>`;
        return `<span class="text-xs text-gray-400">${escRpt(s)}</span>`;
    };

    // ── Sort ──────────────────────────────────────────────────────────────
    const STATUS_ORDER = { 'Less': 1, 'Match': 2, 'Over': 3 };
    let display = [...rptFiltered].sort((a, b) => {
        let va, vb;
        if (rptSortKey === 'date') {
            va = a.date || ''; vb = b.date || '';
        } else {
            return 0;
        }
        if (va < vb) return rptSortDir === 'asc' ? -1 : 1;
        if (va > vb) return rptSortDir === 'asc' ?  1 : -1;
        return 0;
    });

    // ── Flat list: one row per timesheet entry ─────────────────────────────
    let rows = display.map(row => {
        const initials = (row.employee_name || '?').trim().split(' ').map(w => w[0]).slice(0,2).join('').toUpperCase();
        return `<tr class="hover:bg-gray-50/60 transition-colors">
        <td class="px-4 py-3">
            <div class="flex items-center gap-2.5">
                <div class="w-7 h-7 rounded-full bg-indigo-100 text-indigo-700 text-[10px] font-bold flex items-center justify-center shrink-0">${initials}</div>
                <span class="text-sm font-medium text-gray-800 leading-tight">${escRpt(row.employee_name)}</span>
            </div>
        </td>
        <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">${fmtDate(row.date)}</td>
        <td class="px-4 py-3 whitespace-nowrap">
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-purple-50 text-purple-700 text-xs font-semibold border border-purple-100">
                <svg class="w-3 h-3 opacity-60 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                </svg>
                #${escRpt(row.ticket_number || row.ticket_id)}
            </span>
        </td>
        <td class="px-4 py-3 text-xs text-gray-600 max-w-[140px] truncate" title="${escRpt(row.customer_name)}">${escRpt(row.customer_name) || '<span class="text-gray-300">—</span>'}</td>
        <td class="px-4 py-3 text-center">
            <span class="text-sm font-bold text-gray-900">${fmt(row.md_consumed)}</span>
            <span class="text-[10px] text-gray-400 block leading-none">MD</span>
        </td>
        <td class="px-4 py-3 text-center">
            <span class="text-sm font-semibold text-gray-500">${fmt(row.jatah_md)}</span>
            <span class="text-[10px] text-gray-400 block leading-none">quota</span>
        </td>
        <td class="px-4 py-3 text-center">
            <span class="text-sm ${remClass(row.remain)}">${fmt(row.remain)}</span>
        </td>
        <td class="px-4 py-3 text-center">${statusBadge(row.status)}</td>
        <td class="px-4 py-3 text-center">${approvalBadge(row.timesheet_status)}</td>
    </tr>`;
    }).join('');


    tbody.innerHTML = rows;
}

function escRpt(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
</script>
@endpush
@endsection

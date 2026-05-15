@extends('dashboard')
@section('title', 'Reporting')
@section('page-title', 'Reporting')
@section('page-subtitle', 'Timesheet performance reports')

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
    $roleId = session('user')['role']['id'] ?? 0;
    $canManage = in_array($roleId, [1, 5]); // EC Administrator (1) or Delivery Support Head (5)
@endphp

<div class="bg-white rounded-xl p-6 shadow-sm">

    {{-- ── Page Header ────────────────────────────────────────────────────── --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6 pb-4 border-b-2 border-gray-100">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Timesheet Report</h2>
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
                class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-lg transition-all duration-200 shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Export Excel
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

    {{-- ── Filter Bar ─────────────────────────────────────────────────────── --}}
    <div class="bg-gray-50 rounded-lg border border-gray-200 p-4 mb-5">
        <div class="flex items-center gap-2 mb-3">
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/>
            </svg>
            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Filters</span>
        </div>
        <div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                {{-- Period --}}
                <div class="flex flex-col">
                    <label class="text-xs font-semibold text-gray-500 mb-1.5 uppercase tracking-wide">Period</label>
                    <div class="flex items-center gap-2">
                        <div class="custom-dd relative flex-1" data-onchange="updateRptPeriodLabel">
                            <button type="button" class="custom-dd-btn w-full flex items-center justify-between pl-3 pr-2 py-2 bg-white border border-gray-300 rounded-lg text-sm hover:border-gray-400 transition-all text-left">
                                <span class="custom-dd-label text-gray-700">—</span>
                                <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <input type="hidden" id="rptMonth" value="">
                            <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:220px;min-width:140px;">
                                <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="1">January</button>
                                <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="2">February</button>
                                <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="3">March</button>
                                <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="4">April</button>
                                <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="5">May</button>
                                <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="6">June</button>
                                <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="7">July</button>
                                <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="8">August</button>
                                <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="9">September</button>
                                <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="10">October</button>
                                <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="11">November</button>
                                <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="12">December</button>
                            </div>
                        </div>
                        <input type="number" id="rptYear" min="2000" step="1" onchange="updateRptPeriodLabel()"
                            class="px-3 py-2 border border-gray-300 rounded-lg text-sm primary-focus bg-white transition-shadow" style="width:90px;">
                    </div>
                    <p id="rptPeriodRange" class="text-xs text-gray-400 mt-1.5"></p>
                </div>
                {{-- Employee Search --}}
                <div class="flex flex-col">
                    <label class="text-xs font-semibold text-gray-500 mb-1.5 uppercase tracking-wide">Employee</label>
                    <div class="relative">
                        <input type="text" id="rptFilterName" placeholder="Search employee…"
                            class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg text-sm primary-focus bg-white transition-shadow">
                        <svg class="w-3.5 h-3.5 text-gray-400 absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                </div>
                {{-- Status --}}
                <div class="flex flex-col">
                    <label class="text-xs font-semibold text-gray-500 mb-1.5 uppercase tracking-wide">Status</label>
                    <div class="custom-dd relative" data-onchange="applyRptFilter">
                        <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm hover:border-gray-400 transition-all text-left">
                            <span class="custom-dd-label text-gray-500">All Status</span>
                            <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <input type="hidden" id="rptFilterStatus" value="">
                        <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5">
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">All Status</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Match">Match</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Less">Less</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Over">Over</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="flex gap-2 justify-end mt-3 pt-3 border-t border-gray-100">
                <button onclick="resetReport()"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-white text-gray-600 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all duration-200">
                    Reset
                </button>
                <button id="btnApplyReport" onclick="loadReport()"
                    class="inline-flex items-center gap-1.5 px-5 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200 shadow-sm">
                    Apply
                </button>
            </div>
        </div>
    </div>

    {{-- ── Data Table ──────────────────────────────────────────────────────── --}}
    <div class="rounded-xl border border-gray-200 overflow-hidden">

        {{-- Table container --}}
        <div class="overflow-auto" style="max-height: calc(100vh - 400px); min-height: 200px;">
            <table class="w-full text-sm border-collapse" style="min-width: 820px;">
                <thead id="rptTableHead">
                    <tr class="bg-gray-50 border-b border-gray-200">
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wide" style="min-width:160px;">Employee</th>
                        {{-- DATE: sortable --}}
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wide rpt-sortable transition-colors"
                            style="min-width:110px;" onclick="rptSortBy('date')" title="Sort by Date">
                            <div class="flex items-center gap-1">
                                <span>Date</span>
                                <span id="rpt-sort-icon-date" class="rpt-sort-icon text-gray-300 font-normal normal-case tracking-normal">⇅</span>
                            </div>
                        </th>
                        {{-- TICKET: full-width column filter --}}
                        <th class="p-0 text-left whitespace-nowrap" style="min-width:130px;">
                            <div class="custom-dd relative w-full" id="rptTicketFilterDd" data-onchange="rptColFilterChanged" data-fixed="true" data-searchable="true">
                                <button type="button" class="custom-dd-btn w-full flex items-center gap-1.5 px-4 py-3 cursor-pointer hover:bg-gray-100 transition-colors">
                                    <span class="text-xs font-semibold text-gray-700 uppercase tracking-wide whitespace-nowrap">Ticket</span>
                                    <svg class="custom-dd-arrow w-3.5 h-3.5 text-gray-500 transition-all duration-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <input type="hidden" id="rptFilterTicket" value="">
                                <div class="custom-dd-panel hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] py-1.5 overflow-y-auto" style="max-height:220px;min-width:180px;">
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="">All</button>
                                </div>
                            </div>
                        </th>
                        {{-- CUSTOMER: full-width column filter --}}
                        <th class="p-0 text-left whitespace-nowrap" style="min-width:130px;">
                            <div class="custom-dd relative w-full" id="rptCustomerFilterDd" data-onchange="rptColFilterChanged" data-fixed="true" data-searchable="true">
                                <button type="button" class="custom-dd-btn w-full flex items-center gap-1.5 px-4 py-3 cursor-pointer hover:bg-gray-100 transition-colors">
                                    <span class="text-xs font-semibold text-gray-700 uppercase tracking-wide whitespace-nowrap">Customer</span>
                                    <svg class="custom-dd-arrow w-3.5 h-3.5 text-gray-500 transition-all duration-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <input type="hidden" id="rptFilterCustomer" value="">
                                <div class="custom-dd-panel hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] py-1.5 overflow-y-auto" style="max-height:220px;min-width:200px;">
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="">All</button>
                                </div>
                            </div>
                        </th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wide" style="min-width:95px;">MD Input</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wide" style="min-width:90px;">Quota MD</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wide" style="min-width:90px;">Remain</th>
                        {{-- MD STATUS: sortable --}}
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wide rpt-sortable transition-colors"
                            style="min-width:100px;" onclick="rptSortBy('status')" title="Sort by RD Status">
                            <div class="flex items-center justify-center gap-1">
                                <span>MD Status</span>
                                <span id="rpt-sort-icon-status" class="rpt-sort-icon text-gray-300 font-normal normal-case tracking-normal">⇅</span>
                            </div>
                        </th>
                        {{-- APPROVAL: full-width column filter --}}
                        <th class="p-0 text-center whitespace-nowrap" style="min-width:105px;">
                            <div class="custom-dd relative w-full" id="rptApprovalFilterDd" data-onchange="rptColFilterChanged" data-fixed="true">
                                <button type="button" class="custom-dd-btn w-full flex items-center justify-center gap-1.5 px-4 py-3 cursor-pointer hover:bg-gray-100 transition-colors">
                                    <span class="text-xs font-semibold text-gray-700 uppercase tracking-wide whitespace-nowrap">Approval</span>
                                    <svg class="custom-dd-arrow w-3.5 h-3.5 text-gray-500 transition-all duration-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <input type="hidden" id="rptFilterApproval" value="">
                                <div class="custom-dd-panel hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] py-1.5 overflow-y-auto" style="max-height:220px;min-width:140px;">
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
let rptSortField  = null; // 'date' | 'status'
let rptSortDir    = null; // 'desc' | 'asc'

const MONTH_NAMES = ['January','February','March','April','May','June',
                     'July','August','September','October','November','December'];

// ── Init ──────────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', function () {
    if (typeof initCustomDropdowns === 'function') initCustomDropdowns();
    initPeriodDates();
    @if($canManage)
    loadCurrentPeriod();
    @endif
    loadReport();

    // Live employee name filter
    document.getElementById('rptFilterName').addEventListener('input', applyRptFilter);
    // rptFilterStatus is now a custom dropdown — change fires via data-onchange="applyRptFilter"
});

const RPT_MONTHS_SHORT = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

function currentActivePeriod() {
    const now  = new Date();
    const day  = now.getDate();
    const m0   = now.getMonth();   // 0-indexed: Jan=0…Dec=11
    const year = now.getFullYear();
    if (day >= 21) {
        const nextM = m0 + 2;      // +1 to make 1-indexed, +1 for next month
        return nextM > 12 ? { month: 1, year: year + 1 } : { month: nextM, year };
    }
    return { month: m0 + 1, year };
}

function initPeriodDates() {
    const p = currentActivePeriod();
    document.getElementById('rptYear').value = p.year;
    if (typeof setCustomDropdownValue === 'function') {
        setCustomDropdownValue('rptMonth', String(p.month));
    } else {
        document.getElementById('rptMonth').value = p.month;
    }
    updateRptPeriodLabel();
}

function updateRptPeriodLabel() {
    const month = parseInt(document.getElementById('rptMonth').value);
    const year  = parseInt(document.getElementById('rptYear').value);
    if (!month || !year) return;
    const startMonth = month === 1 ? 12 : month - 1;
    const startYear  = month === 1 ? year - 1 : year;
    const label = `${21} ${RPT_MONTHS_SHORT[startMonth - 1]} ${startYear} – ${20} ${RPT_MONTHS_SHORT[month - 1]} ${year}`;
    const el = document.getElementById('rptPeriodRange');
    if (el) el.textContent = label;
}

function periodToDateRange(month, year) {
    const startMonth = month === 1 ? 12 : month - 1;
    const startYear  = month === 1 ? year - 1 : year;
    const start = `${startYear}-${String(startMonth).padStart(2,'0')}-21`;
    const end   = `${year}-${String(month).padStart(2,'0')}-20`;
    return { start, end };
}

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
    if (!currentPeriod) {
        showNotification('Period info not loaded yet. Please wait.', 'error');
        return;
    }
    const btn = document.getElementById('btnExportExcel');
    if (btn) { btn.disabled = true; btn.textContent = 'Exporting…'; }
    window.location.href = `/reporting/export-excel?year=${currentPeriod.year}&month=${currentPeriod.month}`;
    setTimeout(() => {
        if (btn) { btn.disabled = false; btn.textContent = 'Export Excel'; }
    }, 3000);
}
@endif

// ── Report data ───────────────────────────────────────────────────────────────

async function loadReport() {
    const tbody  = document.getElementById('rptTableBody');
    const btn    = document.getElementById('btnApplyReport');

    // Apply button loading state
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = `<svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg> Loading…`;
    }

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
        const month = parseInt(document.getElementById('rptMonth').value);
        const year  = parseInt(document.getElementById('rptYear').value);
        const { start, end } = periodToDateRange(month, year);
        const params = new URLSearchParams();
        params.append('start_date', start);
        params.append('end_date',   end);

        const res  = await fetch(`/api/reporting/timesheet-support?${params}`, {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
            credentials: 'same-origin'
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'API error');

        rptAllData = json.data || [];
        populateRptColumnFilters();
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
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = `<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg> Apply`;
        }
    }
}

function applyRptFilter() {
    const statusFilter   = document.getElementById('rptFilterStatus').value;
    const nameFilter     = (document.getElementById('rptFilterName').value || '').trim().toLowerCase();
    const ticketFilter   = document.getElementById('rptFilterTicket').value;
    const customerFilter = document.getElementById('rptFilterCustomer').value;
    const approvalFilter = document.getElementById('rptFilterApproval').value;

    rptFiltered = rptAllData.filter(r => {
        const matchStatus   = !statusFilter   || r.status === statusFilter;
        const matchName     = !nameFilter     || String(r.employee_name ?? '').toLowerCase().includes(nameFilter);
        const matchTicket   = !ticketFilter   || String(r.ticket_number || r.ticket_id || '') === ticketFilter;
        const matchCustomer = !customerFilter || r.customer_name === customerFilter;
        const matchApproval = !approvalFilter || r.timesheet_status === approvalFilter;
        return matchStatus && matchName && matchTicket && matchCustomer && matchApproval;
    });

    // Sync active state for column filter dropdowns
    updateRptColActive('rptTicketFilterDd',   ticketFilter);
    updateRptColActive('rptCustomerFilterDd', customerFilter);
    updateRptColActive('rptApprovalFilterDd', approvalFilter);

    renderRptTable();
    updateRptCards();
}

function populateRptColumnFilters() {
    const makePanelItem = (val, label) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50';
        btn.dataset.value = val;
        btn.textContent = label;
        return btn;
    };

    const rebuildPanel = (ddId, items, currentVal) => {
        const dd = document.getElementById(ddId);
        if (!dd) return;
        // Use stored panel ref — panel may be detached to body when data-fixed="true"
        const panel = dd._ddPanel || dd.querySelector('.custom-dd-panel');
        if (!panel) return;
        // Remove existing items only, preserve injected search wrap + empty state
        panel.querySelectorAll('.custom-dd-item').forEach(el => el.remove());
        const frag = document.createDocumentFragment();
        frag.appendChild(makePanelItem('', 'All'));
        items.forEach(([val, label]) => frag.appendChild(makePanelItem(val, label)));
        const emptyEl = panel._ddEmpty || null;
        if (emptyEl) panel.insertBefore(frag, emptyEl);
        else panel.appendChild(frag);
        // If current value no longer exists in new data, reset it
        if (currentVal && !items.some(([v]) => v === currentVal)) {
            const hidden = dd.querySelector('input[type="hidden"]');
            if (hidden) hidden.value = '';
        }
    };

    const ticketHidden   = document.getElementById('rptFilterTicket');
    const customerHidden = document.getElementById('rptFilterCustomer');

    const tickets   = [...new Set(rptAllData.map(r => String(r.ticket_number || r.ticket_id || '')).filter(Boolean))].sort();
    const customers = [...new Set(rptAllData.map(r => r.customer_name || '').filter(Boolean))].sort();

    rebuildPanel('rptTicketFilterDd',   tickets.map(t => [t, '#' + t]),    ticketHidden?.value   || '');
    rebuildPanel('rptCustomerFilterDd', customers.map(c => [c, c]),        customerHidden?.value || '');
}

window.rptColFilterChanged = applyRptFilter;

function rptSortBy(field) {
    if (rptSortField !== field) { rptSortField = field; rptSortDir = 'desc'; }
    else if (rptSortDir === 'desc') { rptSortDir = 'asc'; }
    else { rptSortField = null; rptSortDir = null; }
    ['date', 'status'].forEach(f => {
        const icon = document.getElementById('rpt-sort-icon-' + f);
        if (!icon) return;
        if (f !== rptSortField) { icon.textContent = '⇅'; icon.classList.remove('active'); return; }
        icon.textContent = rptSortDir === 'desc' ? '↓' : '↑';
        icon.classList.add('active');
    });
    renderRptTable();
}

function updateRptColActive(ddId, value) {
    const dd = document.getElementById(ddId);
    if (dd) dd.classList.toggle('rpt-col-dd-active', value !== '' && value != null);
}

function filterCardStatus(status) {
    if (typeof setCustomDropdownValue === 'function') {
        setCustomDropdownValue('rptFilterStatus', status);
    } else {
        document.getElementById('rptFilterStatus').value = status;
    }
    applyRptFilter();
}

function resetReport() {
    const p = currentActivePeriod();
    document.getElementById('rptYear').value = p.year;
    if (typeof setCustomDropdownValue === 'function') {
        setCustomDropdownValue('rptMonth', String(p.month));
        setCustomDropdownValue('rptFilterStatus', '');
        setCustomDropdownValue('rptFilterTicket', '');
        setCustomDropdownValue('rptFilterCustomer', '');
        setCustomDropdownValue('rptFilterApproval', '');
    } else {
        document.getElementById('rptMonth').value = p.month;
        document.getElementById('rptFilterStatus').value = '';
        document.getElementById('rptFilterTicket').value = '';
        document.getElementById('rptFilterCustomer').value = '';
        document.getElementById('rptFilterApproval').value = '';
    }
    updateRptPeriodLabel();
    document.getElementById('rptFilterName').value = '';

    // Reset sort
    rptSortField = null;
    rptSortDir   = null;
    ['date', 'status'].forEach(f => {
        const icon = document.getElementById('rpt-sort-icon-' + f);
        if (icon) { icon.textContent = '⇅'; icon.classList.remove('active'); }
    });

    // Reset column filter active states
    ['rptTicketFilterDd', 'rptCustomerFilterDd', 'rptApprovalFilterDd'].forEach(id => updateRptColActive(id, ''));

    rptAllData  = [];
    rptFiltered = [];
    renderRptTable();
    updateRptCards();
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
    let display = rptFiltered;
    if (rptSortField && rptSortDir) {
        display = [...rptFiltered].sort((a, b) => {
            let va, vb;
            if (rptSortField === 'date') {
                va = a.date || '';
                vb = b.date || '';
            } else {
                va = STATUS_ORDER[a.status] ?? 0;
                vb = STATUS_ORDER[b.status] ?? 0;
            }
            if (va < vb) return rptSortDir === 'asc' ? -1 : 1;
            if (va > vb) return rptSortDir === 'asc' ?  1 : -1;
            return 0;
        });
    }

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

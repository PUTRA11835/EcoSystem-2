@extends('dashboard')

@section('title', 'SLA Report')
@section('page-title', 'SLA Report')
@section('page-subtitle', 'Monitor Service Level Agreement performance across all tickets')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="space-y-5">

    {{-- ── KPI Cards ────────────────────────────────────────────────────────── --}}
    <div id="kpiGrid" class="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-7 gap-4">
        @foreach([
            ['icon'=>'fa-ticket-alt',    'bg'=>'bg-gray-50',    'color'=>'text-gray-500',   'id'=>'kpiTotal',      'label'=>'Total Tickets'],
            ['icon'=>'fa-clock',         'bg'=>'bg-blue-50',    'color'=>'text-blue-500',   'id'=>'kpiActive',     'label'=>'Active'],
            ['icon'=>'fa-check-circle',  'bg'=>'bg-green-50',   'color'=>'text-green-500',  'id'=>'kpiMet',        'label'=>'Met'],
            ['icon'=>'fa-times-circle',  'bg'=>'bg-red-50',     'color'=>'text-red-500',    'id'=>'kpiBreached',   'label'=>'Breached'],
            ['icon'=>'fa-chart-pie',     'bg'=>'bg-purple-50',  'color'=>'text-purple-500', 'id'=>'kpiCompliance', 'label'=>'Compliance'],
            ['icon'=>'fa-bolt',          'bg'=>'bg-yellow-50',  'color'=>'text-yellow-500', 'id'=>'kpiAvgResp',    'label'=>'Avg Response'],
            ['icon'=>'fa-flag-checkered','bg'=>'bg-orange-50',  'color'=>'text-orange-500', 'id'=>'kpiAvgRes',     'label'=>'Avg Resolution'],
        ] as $k)
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-9 h-9 rounded-xl {{ $k['bg'] }} flex items-center justify-center flex-shrink-0">
                    <i class="fas {{ $k['icon'] }} {{ $k['color'] }} text-sm"></i>
                </div>
            </div>
            <p class="text-xl font-bold text-gray-800" id="{{ $k['id'] }}">—</p>
            <p class="text-xs text-gray-400 mt-0.5">{{ $k['label'] }}</p>
        </div>
        @endforeach
    </div>

    {{-- ── Main Card ────────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">

        {{-- Toolbar ──────────────────────────────────────────────────────────── --}}
        <div class="px-5 py-4 border-b border-gray-100">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">

                {{-- Left: title --}}
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-red-50 flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-chart-line text-red-600 text-sm"></i>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-800">Ticket SLA Overview</p>
                        <p class="text-xs text-gray-400">Auto-refreshes every 60 seconds</p>
                    </div>
                </div>

                {{-- Right: filters + refresh --}}
                <div class="flex items-center gap-2 flex-wrap">

                    {{-- Customer --}}
                    <div class="custom-dd relative" data-onchange="loadReport" data-fixed="true" style="min-width:150px">
                        <button type="button" class="custom-dd-btn w-full flex items-center justify-between pl-3 pr-2.5 py-1.5 bg-white border border-gray-200 rounded-lg text-xs text-gray-500 hover:border-gray-400 transition-all text-left gap-2">
                            <span class="custom-dd-label">All Customers</span>
                            <svg class="custom-dd-arrow w-3.5 h-3.5 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <input type="hidden" id="filterCustomer" value="">
                        <div class="custom-dd-panel hidden absolute top-full left-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:260px; min-width:200px;">
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm font-medium text-gray-900 bg-gray-50 hover:bg-gray-50 transition-colors" data-value="">All Customers</button>
                            @foreach($customers as $c)
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="{{ $c->customer_id }}">
                                {{ optional($c->basicData)->name_1 ?? 'Customer #'.$c->customer_id }}
                            </button>
                            @endforeach
                        </div>
                    </div>

                    {{-- Month --}}
                    @php $curMonth = (int) date('n'); $curMonthName = \Carbon\Carbon::now()->isoFormat('MMMM'); @endphp
                    <div class="custom-dd relative" data-onchange="loadReport" data-fixed="true" style="min-width:115px">
                        <button type="button" class="custom-dd-btn w-full flex items-center justify-between pl-3 pr-2.5 py-1.5 bg-white border border-gray-200 rounded-lg text-xs text-gray-700 hover:border-gray-400 transition-all text-left gap-2">
                            <span class="custom-dd-label">{{ $curMonthName }}</span>
                            <svg class="custom-dd-arrow w-3.5 h-3.5 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <input type="hidden" id="filterMonth" value="{{ $curMonth }}">
                        <div class="custom-dd-panel hidden absolute top-full left-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:260px; min-width:140px;">
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">All Months</button>
                            @foreach(range(1,12) as $m)
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm {{ $m == $curMonth ? 'font-medium text-gray-900 bg-gray-50' : 'text-gray-600' }} hover:bg-gray-50 transition-colors" data-value="{{ $m }}">
                                {{ \Carbon\Carbon::create((int) date('Y'), $m, 1)->isoFormat('MMMM') }}
                            </button>
                            @endforeach
                        </div>
                    </div>

                    {{-- Year --}}
                    @php $curYear = (int) date('Y'); @endphp
                    <div class="custom-dd relative" data-onchange="loadReport" data-fixed="true" style="min-width:80px">
                        <button type="button" class="custom-dd-btn w-full flex items-center justify-between pl-3 pr-2.5 py-1.5 bg-white border border-gray-200 rounded-lg text-xs text-gray-700 hover:border-gray-400 transition-all text-left gap-2">
                            <span class="custom-dd-label">{{ $curYear }}</span>
                            <svg class="custom-dd-arrow w-3.5 h-3.5 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <input type="hidden" id="filterYear" value="{{ $curYear }}">
                        <div class="custom-dd-panel hidden absolute top-full left-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:200px; min-width:100px;">
                            @foreach(range($curYear, $curYear - 3) as $y)
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm {{ $y == $curYear ? 'font-medium text-gray-900 bg-gray-50' : 'text-gray-600' }} hover:bg-gray-50 transition-colors" data-value="{{ $y }}">{{ $y }}</button>
                            @endforeach
                        </div>
                    </div>

                    {{-- Status --}}
                    <div class="custom-dd relative" data-onchange="loadReport" data-fixed="true" style="min-width:120px">
                        <button type="button" class="custom-dd-btn w-full flex items-center justify-between pl-3 pr-2.5 py-1.5 bg-white border border-gray-200 rounded-lg text-xs text-gray-500 hover:border-gray-400 transition-all text-left gap-2">
                            <span class="custom-dd-label">All Statuses</span>
                            <svg class="custom-dd-arrow w-3.5 h-3.5 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <input type="hidden" id="filterStatus" value="">
                        <div class="custom-dd-panel hidden absolute top-full left-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:200px; min-width:140px;">
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm font-medium text-gray-900 bg-gray-50 hover:bg-gray-50 transition-colors" data-value="">All Statuses</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="pending">Pending</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="paused">Paused</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="met">Met</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="breached">Breached</button>
                        </div>
                    </div>

                    {{-- Refresh --}}
                    <button onclick="loadReport()" id="refreshBtn"
                        class="inline-flex items-center gap-1.5 text-xs font-medium text-gray-500 hover:text-gray-700 border border-gray-200 hover:border-gray-300 px-3 py-1.5 rounded-lg transition bg-white whitespace-nowrap">
                        <i class="fas fa-sync-alt text-xs" id="refreshIcon"></i> Refresh
                    </button>

                </div>
            </div>
        </div>

        {{-- Table ─────────────────────────────────────────────────────────────── --}}
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50/50">
                        {{-- Ticket: text search --}}
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                            <div class="col-filter-wrap relative inline-flex items-center gap-1.5">
                                <span>Ticket</span>
                                <button type="button" class="col-filter-btn w-5 h-5 rounded flex items-center justify-center hover:bg-gray-200 transition" data-col="ticket">
                                    <i class="fas fa-filter text-[9px] col-filter-icon text-gray-300" data-col="ticket"></i>
                                </button>
                                <div id="cfPanel-ticket" class="col-filter-panel hidden absolute top-full left-0 mt-1 z-50 bg-white rounded-xl shadow-2xl border border-gray-100 p-3" style="min-width:200px">
                                    <input type="text" id="cfInput-ticket" placeholder="Search ticket…" oninput="applyReportFilters()"
                                        class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-xs text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-200">
                                    <button type="button" onclick="clearColFilter('ticket')" class="mt-2 text-[10px] text-gray-400 hover:text-red-500 transition">Clear</button>
                                </div>
                            </div>
                        </th>
                        {{-- Customer: text search --}}
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                            <div class="col-filter-wrap relative inline-flex items-center gap-1.5">
                                <span>Customer</span>
                                <button type="button" class="col-filter-btn w-5 h-5 rounded flex items-center justify-center hover:bg-gray-200 transition" data-col="customer">
                                    <i class="fas fa-filter text-[9px] col-filter-icon text-gray-300" data-col="customer"></i>
                                </button>
                                <div id="cfPanel-customer" class="col-filter-panel hidden absolute top-full left-0 mt-1 z-50 bg-white rounded-xl shadow-2xl border border-gray-100 p-3" style="min-width:210px">
                                    <input type="text" id="cfInput-customer" placeholder="Search customer…" oninput="applyReportFilters()"
                                        class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-xs text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-200">
                                    <button type="button" onclick="clearColFilter('customer')" class="mt-2 text-[10px] text-gray-400 hover:text-red-500 transition">Clear</button>
                                </div>
                            </div>
                        </th>
                        {{-- Type: option list + search --}}
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                            <div class="col-filter-wrap relative inline-flex items-center gap-1.5">
                                <span>Type</span>
                                <button type="button" class="col-filter-btn w-5 h-5 rounded flex items-center justify-center hover:bg-gray-200 transition" data-col="type">
                                    <i class="fas fa-filter text-[9px] col-filter-icon text-gray-300" data-col="type"></i>
                                </button>
                                <div id="cfPanel-type" class="col-filter-panel hidden absolute top-full left-0 mt-1 z-50 bg-white rounded-xl shadow-2xl border border-gray-100 p-3" style="min-width:200px">
                                    <input type="text" id="cfSearch-type" placeholder="Search type…" oninput="filterColOptions('type', this.value)"
                                        class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-xs text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-200 mb-2">
                                    <div id="cfOptions-type" class="space-y-0.5 max-h-[180px] overflow-y-auto"></div>
                                    <button type="button" onclick="clearColFilter('type')" class="mt-2 text-[10px] text-gray-400 hover:text-red-500 transition">Clear</button>
                                </div>
                            </div>
                        </th>
                        {{-- Priority: option list + search --}}
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                            <div class="col-filter-wrap relative inline-flex items-center gap-1.5">
                                <span>Priority</span>
                                <button type="button" class="col-filter-btn w-5 h-5 rounded flex items-center justify-center hover:bg-gray-200 transition" data-col="priority">
                                    <i class="fas fa-filter text-[9px] col-filter-icon text-gray-300" data-col="priority"></i>
                                </button>
                                <div id="cfPanel-priority" class="col-filter-panel hidden absolute top-full left-0 mt-1 z-50 bg-white rounded-xl shadow-2xl border border-gray-100 p-3" style="min-width:180px">
                                    <input type="text" id="cfSearch-priority" placeholder="Search priority…" oninput="filterColOptions('priority', this.value)"
                                        class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-xs text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-200 mb-2">
                                    <div id="cfOptions-priority" class="space-y-0.5 max-h-[180px] overflow-y-auto"></div>
                                    <button type="button" onclick="clearColFilter('priority')" class="mt-2 text-[10px] text-gray-400 hover:text-red-500 transition">Clear</button>
                                </div>
                            </div>
                        </th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">SLA Start</th>
                        <th class="text-center px-4 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Response</th>
                        <th class="text-center px-4 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Resolution</th>
                        <th class="text-center px-4 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Waiting</th>
                        {{-- Status: option list + search --}}
                        <th class="text-center px-4 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                            <div class="col-filter-wrap relative inline-flex items-center gap-1.5">
                                <span>Status</span>
                                <button type="button" class="col-filter-btn w-5 h-5 rounded flex items-center justify-center hover:bg-gray-200 transition" data-col="status">
                                    <i class="fas fa-filter text-[9px] col-filter-icon text-gray-300" data-col="status"></i>
                                </button>
                                <div id="cfPanel-status" class="col-filter-panel hidden absolute top-full right-0 mt-1 z-50 bg-white rounded-xl shadow-2xl border border-gray-100 p-3" style="min-width:180px">
                                    <input type="text" id="cfSearch-status" placeholder="Search status…" oninput="filterColOptions('status', this.value)"
                                        class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-xs text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-200 mb-2">
                                    <div id="cfOptions-status" class="space-y-0.5 max-h-[180px] overflow-y-auto"></div>
                                    <button type="button" onclick="clearColFilter('status')" class="mt-2 text-[10px] text-gray-400 hover:text-red-500 transition">Clear</button>
                                </div>
                            </div>
                        </th>
                        <th class="text-right px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody id="reportTableBody">
                    <tr>
                        <td colspan="10" class="py-16 text-center">
                            <div class="flex flex-col items-center gap-2 text-gray-300">
                                <i class="fas fa-spinner fa-spin text-3xl"></i>
                                <p class="text-sm">Loading report...</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- Table Footer --}}
        <div id="tableFooter" class="hidden px-5 py-3 bg-gray-50/50 border-t border-gray-100 flex items-center justify-between">
            <p class="text-xs text-gray-400" id="tableCount"></p>
            <p class="text-xs text-gray-300 hidden md:block">Showing up to 200 records</p>
        </div>
    </div>

</div>

{{-- ── SLA Log Modal ────────────────────────────────────────────────────────── --}}
<div id="detailModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" onclick="closeDetailModal()"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-6xl max-h-[92vh] flex flex-col overflow-hidden">

            {{-- Header --}}
            <div class="flex-shrink-0 bg-white border-b border-gray-100">
                <div class="flex items-center justify-between px-6 py-4">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center">
                            <i class="fas fa-table text-gray-500 text-sm"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-800">SLA Log</h3>
                            <p class="text-xs text-gray-400 mt-0.5">Ticket <span id="detailTicketNum" class="font-mono font-semibold text-gray-600"></span></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <div id="detailSummaryBadges" class="hidden items-center gap-2 flex-wrap"></div>
                        <button onclick="refreshDetail()" title="Refresh SLA Log"
                            class="inline-flex items-center gap-1.5 text-xs text-gray-500 hover:text-gray-700 border border-gray-200 hover:border-gray-300 bg-white px-3 py-1.5 rounded-lg transition whitespace-nowrap">
                            <i class="fas fa-sync-alt text-xs" id="detailRefreshIcon"></i> Refresh
                        </button>
                        <a id="detailPdfBtn" href="#" target="_blank" title="Download SLA Log PDF"
                            class="hidden inline-flex items-center gap-1.5 text-xs font-semibold text-red-600 border border-red-200 hover:bg-red-50 px-3 py-1.5 rounded-lg transition whitespace-nowrap">
                            <i class="fas fa-file-pdf text-xs"></i> Download PDF
                        </a>
                        <button onclick="closeDetailModal()"
                            class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                            <i class="fas fa-times text-sm"></i>
                        </button>
                    </div>
                </div>

                {{-- Summary stats bar --}}
                <div id="detailStatsBar" class="hidden px-6 pb-4">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <div class="bg-gray-50 border border-gray-100 rounded-xl px-4 py-2.5">
                            <p class="text-[10px] text-gray-400 uppercase tracking-wider font-semibold">Response</p>
                            <p class="text-sm font-bold text-gray-800 mt-0.5" id="statResponseVal">—</p>
                            <p class="text-[10px] mt-0.5" id="statResponseStatus"></p>
                        </div>
                        <div class="bg-gray-50 border border-gray-100 rounded-xl px-4 py-2.5">
                            <p class="text-[10px] text-gray-400 uppercase tracking-wider font-semibold">Resolution</p>
                            <p class="text-sm font-bold text-gray-800 mt-0.5" id="statResolutionVal">—</p>
                            <p class="text-[10px] mt-0.5" id="statResolutionStatus"></p>
                        </div>
                        <div class="bg-gray-50 border border-gray-100 rounded-xl px-4 py-2.5">
                            <p class="text-[10px] text-gray-400 uppercase tracking-wider font-semibold">Waiting</p>
                            <p class="text-sm font-bold text-gray-800 mt-0.5" id="statWaitingVal">—</p>
                            <p class="text-[10px] text-gray-400 mt-0.5">total pause time</p>
                        </div>
                        <div class="bg-gray-50 border border-gray-100 rounded-xl px-4 py-2.5">
                            <p class="text-[10px] text-gray-400 uppercase tracking-wider font-semibold">Ball Holder</p>
                            <p class="text-sm font-bold text-gray-800 mt-0.5" id="statBallHolder">—</p>
                            <p class="text-[10px] text-gray-400 mt-0.5" id="statBallHolderSub"></p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Table --}}
            <div id="detailContent" class="overflow-auto flex-1 bg-gray-50/30">
                <div class="flex items-center justify-center h-32 text-gray-300">
                    <i class="fas fa-spinner fa-spin text-3xl"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const PRIORITY_CFG = {
    'Very High': { bg:'bg-red-50',    text:'text-red-700',    dot:'bg-red-500'    },
    'High':      { bg:'bg-orange-50', text:'text-orange-700', dot:'bg-orange-500' },
    'Medium':    { bg:'bg-yellow-50', text:'text-yellow-700', dot:'bg-yellow-500' },
    'Low':       { bg:'bg-blue-50',   text:'text-blue-700',   dot:'bg-blue-400'   },
};
const STATUS_CFG = {
    'pending_validation': { bg:'bg-gray-100',  text:'text-gray-500',  dot:'bg-gray-400',  label:'Pending'  },
    'pending':            { bg:'bg-blue-50',   text:'text-blue-700',  dot:'bg-blue-500',  label:'Active'   },
    'paused':             { bg:'bg-amber-50',  text:'text-amber-700', dot:'bg-amber-500', label:'Paused'   },
    'met':                { bg:'bg-green-50',  text:'text-green-700', dot:'bg-green-500', label:'Met'      },
    'breached':           { bg:'bg-red-50',    text:'text-red-700',   dot:'bg-red-500',   label:'Breached' },
};
const BALL_CFG = {
    'helpdesk': { bg:'bg-blue-50',   text:'text-blue-700',   icon:'fa-headset' },
    'customer': { bg:'bg-orange-50', text:'text-orange-700', icon:'fa-user'    },
    'sap':      { bg:'bg-purple-50', text:'text-purple-700', icon:'fa-server'  },
};
const EVENT_CFG = {
    'email_received':       { icon:'fa-envelope',             color:'text-indigo-500', bg:'bg-indigo-50'  },
    'ticket_validated':     { icon:'fa-check-circle',         color:'text-green-500',  bg:'bg-green-50'   },
    'agent_replied':        { icon:'fa-comment-dots',         color:'text-blue-500',   bg:'bg-blue-50'    },
    'customer_replied':     { icon:'fa-reply',                color:'text-orange-500', bg:'bg-orange-50'  },
    'sla_warning':          { icon:'fa-exclamation-triangle', color:'text-yellow-500', bg:'bg-yellow-50'  },
    'sla_breached':         { icon:'fa-times-circle',         color:'text-red-500',    bg:'bg-red-50'     },
    'ticket_closed':        { icon:'fa-check-double',         color:'text-gray-500',   bg:'bg-gray-100'   },
    'escalated_to_sap':     { icon:'fa-arrow-circle-up',      color:'text-purple-500', bg:'bg-purple-50'  },
    'escalated_to_support': { icon:'fa-undo',                 color:'text-teal-500',   bg:'bg-teal-50'    },
};

// ── KPI IDs in order ──────────────────────────────────────────────────────────
const KPI_IDS = ['kpiTotal','kpiActive','kpiMet','kpiBreached','kpiCompliance','kpiAvgResp','kpiAvgRes'];

let _allTickets = [];

// ── Column filter state ───────────────────────────────────────────────────────
// For text cols: string value. For option cols: string value (single-select).
const CF = { ticket: '', customer: '', type: '', priority: '', status: '' };

// Fixed option sets for enum columns
const CF_OPTIONS = {
    type:     ['Incident', 'Service Request', 'Change Request', 'Consult'],
    priority: ['Very High', 'High', 'Medium', 'Low'],
    status:   ['pending', 'paused', 'met', 'breached', 'pending_validation'],
};
const CF_STATUS_LABELS = {
    pending: 'Active', paused: 'Paused', met: 'Met', breached: 'Breached', pending_validation: 'Pending Validation',
};

// Build option list HTML for a given col (called once on init)
function buildColOptions(col) {
    const container = document.getElementById('cfOptions-' + col);
    if (!container) return;
    const options = CF_OPTIONS[col];
    container.innerHTML = options.map(val => {
        const label = col === 'status' ? (CF_STATUS_LABELS[val] || val) : val;
        return `<button type="button" data-val="${val}"
            onclick="selectColOption('${col}', '${val}')"
            class="cf-opt w-full text-left px-2.5 py-1.5 text-xs rounded-lg text-gray-600 hover:bg-gray-50 transition flex items-center gap-2">
            <span class="cf-opt-dot w-3 h-3 rounded-full border border-gray-300 flex-shrink-0"></span>
            ${label}
        </button>`;
    }).join('');
}

function filterColOptions(col, q) {
    const term = q.toLowerCase().trim();
    document.querySelectorAll(`#cfOptions-${col} .cf-opt`).forEach(btn => {
        btn.style.display = (!term || btn.dataset.val.toLowerCase().includes(term)) ? '' : 'none';
    });
}

function selectColOption(col, val) {
    // Toggle: click same value again → clear
    CF[col] = CF[col] === val ? '' : val;
    // Update dot states
    document.querySelectorAll(`#cfOptions-${col} .cf-opt`).forEach(btn => {
        const dot = btn.querySelector('.cf-opt-dot');
        const active = btn.dataset.val === CF[col];
        dot.className = active
            ? 'cf-opt-dot w-3 h-3 rounded-full flex-shrink-0 bg-red-600'
            : 'cf-opt-dot w-3 h-3 rounded-full border border-gray-300 flex-shrink-0';
        btn.classList.toggle('bg-red-50', active);
        btn.classList.toggle('text-red-700', active);
        btn.classList.toggle('font-semibold', active);
    });
    updateColFilterIcon(col);
    applyReportFilters();
}

function clearColFilter(col) {
    CF[col] = '';
    // Reset text inputs
    const inp = document.getElementById('cfInput-' + col);
    if (inp) inp.value = '';
    const srch = document.getElementById('cfSearch-' + col);
    if (srch) { srch.value = ''; filterColOptions(col, ''); }
    // Reset option dots
    document.querySelectorAll(`#cfOptions-${col} .cf-opt`).forEach(btn => {
        btn.querySelector('.cf-opt-dot').className = 'cf-opt-dot w-3 h-3 rounded-full border border-gray-300 flex-shrink-0';
        btn.classList.remove('bg-red-50','text-red-700','font-semibold');
    });
    updateColFilterIcon(col);
    applyReportFilters();
}

function updateColFilterIcon(col) {
    document.querySelectorAll(`.col-filter-icon[data-col="${col}"]`).forEach(el => {
        el.className = CF[col]
            ? 'fas fa-filter text-[9px] col-filter-icon text-red-500'
            : 'fas fa-filter text-[9px] col-filter-icon text-gray-300';
    });
}

// Toggle panel open/close
document.addEventListener('click', function (e) {
    const btn = e.target.closest('.col-filter-btn');
    if (btn) {
        const col   = btn.dataset.col;
        const panel = document.getElementById('cfPanel-' + col);
        const wasHidden = panel.classList.contains('hidden');
        document.querySelectorAll('.col-filter-panel').forEach(p => p.classList.add('hidden'));
        if (wasHidden) {
            panel.classList.remove('hidden');
            panel.querySelector('input')?.focus();
        }
        e.stopPropagation();
        return;
    }
    // Click outside → close all panels
    if (!e.target.closest('.col-filter-panel')) {
        document.querySelectorAll('.col-filter-panel').forEach(p => p.classList.add('hidden'));
    }
});

async function loadReport() {
    const params = new URLSearchParams();
    const c = document.getElementById('filterCustomer').value;
    const m = document.getElementById('filterMonth').value;
    const y = document.getElementById('filterYear').value;
    const s = document.getElementById('filterStatus').value;
    if (c) params.set('customer_id', c);
    if (m) params.set('month', m);
    if (y) params.set('year', y);
    if (s) params.set('resolution_status', s);

    const icon = document.getElementById('refreshIcon');
    icon?.classList.add('fa-spin');

    try {
        const res  = await fetch('/api/admin/sla/report?' + params, { credentials: 'include' });
        const json = await res.json();
        if (!json.success) return;

        const sm  = json.data.summary;
        const vals = [
            sm.total,
            sm.active,
            sm.met,
            sm.breached,
            sm.compliance_rate !== null ? sm.compliance_rate + '%' : '—',
            sm.avg_response_hours   !== null ? sm.avg_response_hours + ' hrs'   : '—',
            sm.avg_resolution_hours !== null ? sm.avg_resolution_hours + ' hrs' : '—',
        ];
        KPI_IDS.forEach((id, i) => {
            const el = document.getElementById(id);
            if (el) el.textContent = vals[i];
        });

        _allTickets = json.data.tickets || [];
        applyReportFilters();
    } catch (e) {
        document.getElementById('reportTableBody').innerHTML = `
            <tr><td colspan="10" class="py-12 text-center">
                <div class="flex flex-col items-center gap-2 text-red-400">
                    <i class="fas fa-exclamation-triangle text-3xl"></i>
                    <p class="text-sm">Failed to load report.</p>
                </div>
            </td></tr>`;
    } finally {
        icon?.classList.remove('fa-spin');
    }
}

function applyReportFilters() {
    const ticketQ   = (document.getElementById('cfInput-ticket')?.value   || '').toLowerCase().trim();
    const customerQ = (document.getElementById('cfInput-customer')?.value || '').toLowerCase().trim();
    CF.ticket   = ticketQ;
    CF.customer = customerQ;

    const filtered = _allTickets.filter(t => {
        if (CF.ticket   && !(t.ticket_number   || '').toLowerCase().includes(CF.ticket))   return false;
        if (CF.customer && !(t.customer_name   || '').toLowerCase().includes(CF.customer)) return false;
        if (CF.type     && t.ticket_type     !== CF.type)     return false;
        if (CF.priority && t.ticket_priority  !== CF.priority) return false;
        if (CF.status   && t.resolution?.status !== CF.status) return false;
        return true;
    });

    renderTable(filtered);
}

// Init option lists on load
['type', 'priority', 'status'].forEach(col => buildColOptions(col));

function renderTable(tickets) {
    const tbody  = document.getElementById('reportTableBody');
    const footer = document.getElementById('tableFooter');

    if (!tickets.length) {
        footer.classList.add('hidden');
        tbody.innerHTML = `
            <tr><td colspan="10" class="py-16 text-center">
                <div class="flex flex-col items-center gap-2 text-gray-300">
                    <i class="fas fa-search text-4xl"></i>
                    <p class="text-sm font-medium text-gray-400 mt-1">No tickets found</p>
                    <p class="text-xs text-gray-300">Try adjusting the filters above</p>
                </div>
            </td></tr>`;
        return;
    }

    footer.classList.remove('hidden');
    document.getElementById('tableCount').textContent =
        `${tickets.length} ticket${tickets.length !== 1 ? 's' : ''} found`;

    tbody.innerHTML = tickets.map(t => {
        const isPending = t.is_pending_validation;
        const prio = PRIORITY_CFG[t.ticket_priority] || { bg:'bg-gray-50', text:'text-gray-600', dot:'bg-gray-400' };
        const rsc  = STATUS_CFG[t.resolution?.status] || STATUS_CFG['pending'];

        // Pending validation row — belum ada ticket resmi
        if (isPending) {
            const waitingHours = t.sla_start_at
                ? ((Date.now() - new Date(t.sla_start_at).getTime()) / 3600000).toFixed(1)
                : '—';
            return `
            <tr class="border-b border-amber-100 bg-amber-50/40 hover:bg-amber-50/70 transition-colors group">
                <td class="px-5 py-3">
                    <span class="inline-flex items-center gap-1.5 font-mono text-xs font-semibold text-amber-700 bg-amber-100 px-2 py-0.5 rounded whitespace-nowrap">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                        Staging #${t.staging_id || '?'}
                    </span>
                </td>
                <td class="px-4 py-3 max-w-[130px]">
                    <p class="text-xs text-gray-700 truncate font-medium">${t.customer_name || '—'}</p>
                </td>
                <td class="px-4 py-3" colspan="2">
                    <span class="text-[11px] font-semibold text-amber-700 bg-amber-100 px-2 py-0.5 rounded-full">Awaiting Validation</span>
                    <p class="text-[10px] text-gray-400 truncate mt-0.5">${(t.description || '').substring(0,50)}</p>
                </td>
                <td class="px-4 py-3">
                    <p class="text-xs text-gray-500 whitespace-nowrap">${t.sla_start_at ? t.sla_start_at.substring(0,16) : '—'}</p>
                </td>
                <td class="px-4 py-3" colspan="2">
                    <div class="text-center">
                        <p class="text-xs font-bold text-amber-600">${waitingHours} hrs</p>
                        <p class="text-[10px] text-gray-400">waiting</p>
                    </div>
                </td>
                <td class="px-4 py-3 text-center">—</td>
                <td class="px-4 py-3 text-center">
                    <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-amber-700 bg-amber-100 px-2.5 py-1 rounded-full whitespace-nowrap">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse flex-shrink-0"></span>Pending Validation
                    </span>
                </td>
                <td class="px-5 py-3 text-center">
                    <span class="text-[10px] text-gray-400 italic">Validate first</span>
                </td>
            </tr>`;
        }

        const rspColor = t.response?.status === 'met'     ? 'text-green-600'
                       : t.response?.status === 'breached' ? 'text-red-600'
                       : 'text-gray-500';

        const respCell = `
            <div class="text-center leading-tight">
                <p class="text-xs font-bold ${rspColor}">${t.response?.actual_hours ?? '—'} hrs</p>
                <p class="text-[10px] text-gray-400">/ ${t.response?.target_hours ?? '—'} target</p>
            </div>`;

        const resCell = t.sla_mode === 'full'
            ? `<div class="text-center leading-tight">
                <p class="text-xs font-bold ${rsc.text}">${t.resolution?.actual_hours ?? '—'} hrs</p>
                <p class="text-[10px] text-gray-400">/ ${t.resolution?.target_hours ?? '—'} target</p>
               </div>`
            : `<p class="text-[10px] text-center text-gray-400 italic">resp. only</p>`;

        const wait = parseFloat(t.waiting_hours || 0);
        const waitCell = wait > 0
            ? `<span class="text-xs font-semibold text-amber-700 bg-amber-50 px-2 py-0.5 rounded-full">${t.waiting_hours}h</span>`
            : `<span class="text-xs text-gray-300">—</span>`;

        return `
        <tr class="border-b border-gray-50 hover:bg-gray-50/60 transition-colors group">
            <td class="px-5 py-3">
                <span class="font-mono text-xs font-semibold text-gray-600 bg-gray-100 px-2 py-0.5 rounded">
                    #${t.ticket_number}
                </span>
            </td>
            <td class="px-4 py-3 max-w-[130px]">
                <p class="text-xs text-gray-700 truncate font-medium">${t.customer_name || '—'}</p>
            </td>
            <td class="px-4 py-3">
                <span class="text-xs text-gray-500">${t.ticket_type || '—'}</span>
            </td>
            <td class="px-4 py-3">
                <span class="inline-flex items-center gap-1 text-[11px] font-semibold ${prio.text} ${prio.bg} px-2 py-0.5 rounded-full whitespace-nowrap">
                    <span class="w-1.5 h-1.5 rounded-full ${prio.dot} flex-shrink-0"></span>${t.ticket_priority || '—'}
                </span>
            </td>
            <td class="px-4 py-3">
                <p class="text-xs text-gray-500 whitespace-nowrap">${t.sla_start_at ? t.sla_start_at.substring(0,16) : '—'}</p>
            </td>
            <td class="px-4 py-3">${respCell}</td>
            <td class="px-4 py-3">${resCell}</td>
            <td class="px-4 py-3 text-center">${waitCell}</td>
            <td class="px-4 py-3 text-center">
                <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold ${rsc.text} ${rsc.bg} px-2.5 py-1 rounded-full whitespace-nowrap">
                    <span class="w-1.5 h-1.5 rounded-full ${rsc.dot} flex-shrink-0"></span>${rsc.label}
                </span>
            </td>
            <td class="px-5 py-3">
                <div class="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                    <button onclick="openDetail(${t.ticket_id}, '${t.ticket_number}')" title="View SLA Log"
                        class="w-7 h-7 rounded-lg border border-gray-200 hover:border-indigo-300 hover:bg-indigo-50 flex items-center justify-center text-gray-400 hover:text-indigo-600 transition">
                        <i class="fas fa-table text-xs"></i>
                    </button>
                    <a href="/admin/sla/tickets/${t.ticket_id}/log-pdf" target="_blank" title="Download SLA Log PDF"
                        class="w-7 h-7 rounded-lg border border-gray-200 hover:border-red-300 hover:bg-red-50 flex items-center justify-center text-gray-400 hover:text-red-600 transition">
                        <i class="fas fa-file-pdf text-xs"></i>
                    </a>
                    <a href="/admin/sla/tickets/${t.ticket_id}/pdf" target="_blank" title="Download SLA Summary PDF"
                        class="w-7 h-7 rounded-lg border border-gray-200 hover:border-orange-300 hover:bg-orange-50 flex items-center justify-center text-gray-400 hover:text-orange-600 transition">
                        <i class="fas fa-file-alt text-xs"></i>
                    </a>
                </div>
            </td>
        </tr>`;
    }).join('');
}

// ── SLA Log Modal ─────────────────────────────────────────────────────────────

function _toHMM(hours) {
    if (hours === null || hours === undefined) return null;
    const h = Math.floor(hours);
    const m = Math.round((hours - h) * 60);
    return `${h}:${String(m).padStart(2, '0')}`;
}

function _toHLabel(hours) {
    if (hours === null || hours === undefined) return null;
    const mins = Math.round(hours * 60);
    return `${hours.toFixed(2)} h(${mins} min)`;
}

let _currentDetailTicketId = null;

async function openDetail(ticketId, ticketNum) {
    _currentDetailTicketId = ticketId;
    document.getElementById('detailTicketNum').textContent = '#' + ticketNum;
    document.getElementById('detailSummaryBadges').classList.add('hidden');
    document.getElementById('detailSummaryBadges').innerHTML = '';
    document.getElementById('detailStatsBar').classList.add('hidden');

    const pdfBtn = document.getElementById('detailPdfBtn');
    pdfBtn.href = '/admin/sla/tickets/' + ticketId + '/log-pdf';
    pdfBtn.classList.remove('hidden');

    document.getElementById('detailContent').innerHTML = `
        <div class="flex flex-col items-center justify-center gap-3 py-20 text-gray-300">
            <i class="fas fa-spinner fa-spin text-3xl"></i>
            <p class="text-sm text-gray-400">Loading SLA log…</p>
        </div>`;
    document.getElementById('detailModal').classList.remove('hidden');

    await _fetchAndRenderDetail(ticketId);
}

async function refreshDetail() {
    if (!_currentDetailTicketId) return;
    const icon = document.getElementById('detailRefreshIcon');
    icon?.classList.add('fa-spin');
    await _fetchAndRenderDetail(_currentDetailTicketId);
    icon?.classList.remove('fa-spin');
}

async function _fetchAndRenderDetail(ticketId) {
    try {
        const res  = await fetch('/api/tickets/' + ticketId + '/sla', { credentials: 'include' });
        const json = await res.json();
        if (!json.success || !json.data) {
            document.getElementById('detailContent').innerHTML =
                `<div class="flex flex-col items-center gap-2 py-16 text-gray-300"><i class="fas fa-inbox text-3xl"></i><p class="text-sm text-gray-400">No SLA data available for this ticket.</p></div>`;
            return;
        }
        renderDetail(json.data);
    } catch {
        document.getElementById('detailContent').innerHTML =
            `<div class="flex flex-col items-center gap-2 py-16 text-red-300"><i class="fas fa-exclamation-triangle text-3xl"></i><p class="text-sm text-red-400">Failed to load SLA log.</p></div>`;
    }
}

function renderDetail(data) {
    // ── Stats bar ──────────────────────────────────────────────────────────────
    const respSc = STATUS_CFG[data.response?.status] || STATUS_CFG['pending'];
    const resSc  = STATUS_CFG[data.resolution?.status] || STATUS_CFG['pending'];

    document.getElementById('statResponseVal').textContent   = data.response?.actual_hours != null ? data.response.actual_hours + ' hrs' : '—';
    document.getElementById('statResponseStatus').innerHTML  = `<span class="${respSc.text} font-semibold">${respSc.label}</span><span class="text-slate-400"> / target ${data.response?.target_hours ?? '—'} hrs</span>`;
    document.getElementById('statResolutionVal').textContent = data.resolution?.actual_hours != null ? data.resolution.actual_hours + ' hrs' : (data.sla_mode === 'response_only' ? 'N/A' : '—');
    document.getElementById('statResolutionStatus').innerHTML = data.resolution
        ? `<span class="${resSc.text} font-semibold">${resSc.label}</span><span class="text-slate-400"> / target ${data.resolution.target_hours ?? '—'} hrs</span>`
        : `<span class="text-slate-400">Response-only mode</span>`;

    const totalWait = data.total_waiting_hours ?? (data.events?.reduce((s, e) => s + (e.waiting_hours || 0), 0) ?? 0);
    document.getElementById('statWaitingVal').textContent    = totalWait > 0 ? totalWait.toFixed(2) + ' hrs' : '0 hrs';
    document.getElementById('statBallHolder').textContent    = data.ball_holder ? (data.ball_holder.charAt(0).toUpperCase() + data.ball_holder.slice(1)) : '—';
    document.getElementById('statBallHolderSub').textContent = 'current ball holder';
    document.getElementById('detailStatsBar').classList.remove('hidden');

    // ── Summary badges ─────────────────────────────────────────────────────────
    const badgesEl = document.getElementById('detailSummaryBadges');
    badgesEl.innerHTML = `
        <span class="inline-flex items-center gap-1 text-[11px] font-semibold ${respSc.text} ${respSc.bg} px-2.5 py-1 rounded-full whitespace-nowrap">
            <span class="w-1.5 h-1.5 rounded-full ${respSc.dot} flex-shrink-0"></span>Response: ${respSc.label}
        </span>
        ${data.resolution ? `
        <span class="inline-flex items-center gap-1 text-[11px] font-semibold ${resSc.text} ${resSc.bg} px-2.5 py-1 rounded-full whitespace-nowrap">
            <span class="w-1.5 h-1.5 rounded-full ${resSc.dot} flex-shrink-0"></span>Resolution: ${resSc.label}
        </span>` : ''}
    `;
    badgesEl.classList.remove('hidden');
    badgesEl.classList.add('flex');

    // ── Table ──────────────────────────────────────────────────────────────────
    if (!data.events?.length) {
        document.getElementById('detailContent').innerHTML = `
            <div class="flex flex-col items-center gap-3 py-20 text-gray-300">
                <i class="fas fa-table text-3xl"></i>
                <p class="text-sm text-gray-400">No events recorded yet</p>
            </div>`;
        return;
    }

    const BALL_ICON = {
        helpdesk: { icon: '▶', label: 'Helpdesk' },
        customer: { icon: '⏸', label: 'Customer' },
        sap:      { icon: '⏸', label: 'SAP'      },
        meeting:  { icon: '⏸', label: 'Meeting'  },
    };

    const EVENT_ROW_CFG = {
        email_received:       { dot: '#6366f1', rowBg: '#fafaff', label: 'Email / Request Received'  },
        ticket_validated:     { dot: '#16a34a', rowBg: '#f6fef7', label: 'Ticket Created'            },
        agent_replied:        { dot: '#2563eb', rowBg: '#f5f9ff', label: 'Helpdesk Reply'            },
        customer_replied:     { dot: '#ea580c', rowBg: '#fff8f4', label: 'Customer Reply'            },
        resolution_sent:      { dot: '#0d9488', rowBg: '#f4fefc', label: 'Resolution Sent'           },
        escalated_to_sap:     { dot: '#7c3aed', rowBg: '#faf7ff', label: 'Escalated to SAP'         },
        escalated_to_support: { dot: '#6b7280', rowBg: '#f9fafb', label: 'Returned to Helpdesk'     },
        sla_warning:          { dot: '#ca8a04', rowBg: '#fffdf0', label: 'SLA Warning'               },
        sla_breached:         { dot: '#dc2626', rowBg: '#fff8f8', label: 'SLA Breached'              },
        ticket_closed:        { dot: '#374151', rowBg: '#f9fafb', label: 'Ticket Closed'             },
        meeting_started:      { dot: '#7c3aed', rowBg: '#faf7ff', label: 'Meeting Started'           },
        meeting_ended:        { dot: '#7c3aed', rowBg: '#faf7ff', label: 'Meeting Ended'             },
    };

    let lastDate = null;

    const rows = data.events.map((e, idx) => {
        const dt      = e.event_at ? new Date(e.event_at) : null;
        const dateStr = dt ? dt.toLocaleDateString('id-ID', { day:'2-digit', month:'2-digit', year:'numeric' }) : '—';
        const timeStr = dt ? dt.toLocaleTimeString('id-ID', { hour:'2-digit', minute:'2-digit' }) : '—';
        const showDate = dateStr !== lastDate;
        lastDate = dateStr;

        const evCfg   = EVENT_ROW_CFG[e.event_type] || { dot: '#9ca3af', rowBg: '#fff', label: e.event_type };
        const ballCfg = e.ball_after ? (BALL_ICON[e.ball_after] || null) : null;

        const waitCell = e.waiting_hours !== null
            ? `<span class="text-[11px] font-semibold text-amber-600 whitespace-nowrap">${_toHLabel(e.waiting_hours)}</span>`
            : `<span class="text-gray-300 text-xs">—</span>`;

        const respCell = e.response_hours !== null
            ? `<span class="text-[11px] font-semibold text-gray-700 whitespace-nowrap">${_toHLabel(e.response_hours)}</span>`
            : `<span class="text-gray-300 text-xs">—</span>`;

        const resCell = e.resolution_hours !== null
            ? `<span class="text-[11px] font-semibold text-gray-700 whitespace-nowrap">${_toHLabel(e.resolution_hours)}</span>`
            : `<span class="text-gray-300 text-xs">—</span>`;

        const statusCell = e.jarvis_status
            ? `<span class="text-[10px] text-gray-500 whitespace-nowrap">${e.jarvis_status.replace(/_/g,' ')}</span>`
            : `<span class="text-gray-300 text-xs">—</span>`;

        const ballCell = ballCfg
            ? `<span class="text-[11px] font-semibold text-gray-600 whitespace-nowrap">${ballCfg.icon} ${ballCfg.label}</span>`
            : `<span class="text-gray-300 text-xs">—</span>`;

        const senderPrefix = e.sender_name ? `<span class="font-semibold text-gray-700">${e.sender_name}:</span> ` : '';
        const bodyText = e.message_preview || e.notes || null;
        const msgText  = bodyText
            ? `<span title="${(e.message_preview || '').replace(/"/g,'&quot;')}" class="text-gray-500 text-xs">${senderPrefix}${bodyText.substring(0, 80)}${bodyText.length > 80 ? '…' : ''}</span>`
            : (e.sender_name ? `<span class="font-semibold text-gray-700 text-xs">${e.sender_name}</span>` : `<span class="text-gray-300 text-xs">—</span>`);

        const dateSep = showDate ? `
        <tr>
            <td colspan="9" style="background:#f3f4f6; border-top:1px solid #e5e7eb; border-bottom:1px solid #e5e7eb; padding:4px 12px;">
                <span style="font-size:10px; font-weight:600; color:#6b7280; letter-spacing:0.04em;">
                    ${dt ? dt.toLocaleDateString('id-ID', { weekday:'long', day:'numeric', month:'long', year:'numeric' }) : dateStr}
                </span>
            </td>
        </tr>` : '';

        return `${dateSep}
        <tr style="background:${evCfg.rowBg}; border-left:3px solid ${evCfg.dot};" class="border-b border-gray-100/80 hover:brightness-[0.97] transition-all">
            <td class="px-3 py-2.5 text-xs text-gray-400 whitespace-nowrap">${showDate ? dateStr : ''}</td>
            <td class="px-3 py-2.5 text-xs text-gray-600 font-mono whitespace-nowrap">${timeStr}</td>
            <td class="px-3 py-2.5 text-right whitespace-nowrap">${waitCell}</td>
            <td class="px-3 py-2.5 text-right whitespace-nowrap">${respCell}</td>
            <td class="px-3 py-2.5 text-right whitespace-nowrap">${resCell}</td>
            <td class="px-3 py-2.5 whitespace-nowrap">
                <div class="flex items-center gap-2">
                    <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" style="background:${evCfg.dot};"></span>
                    <span class="text-xs text-gray-700">${evCfg.label}</span>
                </div>
            </td>
            <td class="px-3 py-2.5">${statusCell}</td>
            <td class="px-3 py-2.5">${ballCell}</td>
            <td class="px-3 py-2.5 max-w-[220px] truncate">${msgText}</td>
        </tr>`;
    }).join('');

    document.getElementById('detailContent').innerHTML = `
        <table class="w-full text-sm border-collapse" style="min-width:820px">
            <thead>
                <tr class="sticky top-0 z-10" style="background:#f8fafc; border-bottom:2px solid #e2e8f0;">
                    <th class="px-3 py-2.5 text-[10px] font-semibold tracking-wider text-gray-400 uppercase whitespace-nowrap text-left">Date</th>
                    <th class="px-3 py-2.5 text-[10px] font-semibold tracking-wider text-gray-400 uppercase whitespace-nowrap text-left">Time</th>
                    <th class="px-3 py-2.5 text-[10px] font-semibold tracking-wider text-gray-400 uppercase whitespace-nowrap text-right">Waiting</th>
                    <th class="px-3 py-2.5 text-[10px] font-semibold tracking-wider text-gray-400 uppercase whitespace-nowrap text-right">Response</th>
                    <th class="px-3 py-2.5 text-[10px] font-semibold tracking-wider text-gray-400 uppercase whitespace-nowrap text-right">
                        Resolution${data.resolution?.net_hours != null ? ` <span class="font-normal normal-case text-gray-400">(${_toHMM(data.resolution.net_hours)})</span>` : ''}
                    </th>
                    <th class="px-3 py-2.5 text-[10px] font-semibold tracking-wider text-gray-400 uppercase whitespace-nowrap text-left" style="padding-left:16px;">Event</th>
                    <th class="px-3 py-2.5 text-[10px] font-semibold tracking-wider text-gray-400 uppercase whitespace-nowrap text-left">Status</th>
                    <th class="px-3 py-2.5 text-[10px] font-semibold tracking-wider text-gray-400 uppercase whitespace-nowrap text-left">Ball</th>
                    <th class="px-3 py-2.5 text-[10px] font-semibold tracking-wider text-gray-400 uppercase text-left">Message</th>
                </tr>
            </thead>
            <tbody>${rows}</tbody>
        </table>`;
}

function closeDetailModal() {
    document.getElementById('detailModal').classList.add('hidden');
    document.getElementById('detailStatsBar').classList.add('hidden');
    document.getElementById('detailSummaryBadges').classList.add('hidden');
    _currentDetailTicketId = null;
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeDetailModal();
});

loadReport();
setInterval(loadReport, 60000);
</script>

@php $customDdVer = file_exists(public_path('js/custom-dropdown.js')) ? filemtime(public_path('js/custom-dropdown.js')) : time(); @endphp
<script src="/js/custom-dropdown.js?v={{ $customDdVer }}"></script>
<script>
document.addEventListener('DOMContentLoaded', () => initCustomDropdowns());
</script>
@endsection

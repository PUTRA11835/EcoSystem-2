@extends('dashboard')

@section('title', 'SLA Report')
@section('page-title', 'SLA Report')
@section('page-subtitle', 'Monitor Service Level Agreement performance across all tickets')

@push('scripts')
<script src="/js/custom-dropdown.js?v={{ filemtime(public_path('js/custom-dropdown.js')) }}"></script>
<script src="https://cdn.sheetjs.com/xlsx-0.20.3/package/dist/xlsx.full.min.js"></script>
<script>
    initCustomDropdowns();
</script>
@endpush

@push('styles')
<style>
    .sla-table th, .sla-table td { white-space: nowrap; }

    /* Vertical sticky for header rows */
    .sla-table thead tr:first-child th { position: sticky; top: 0;    z-index: 12; }
    .sla-table thead tr:last-child  th { position: sticky; top: 29px; z-index: 11; }

    /* Horizontal sticky columns (No → Status) */
    .sc { position: sticky !important; z-index: 3; }
    .sla-table thead tr:first-child .sc { z-index: 22; }
    .sla-table thead tr:last-child  .sc { z-index: 21; }
    .sla-table tbody .sc { background: #ffffff; z-index: 4; }
    /* Hover tint on sticky body cells */
    .sla-table tbody tr:hover .sc { background: #eff6ff; }
    /* Pending row tint */
    .sla-table tbody tr.row-pending .sc { background: #fffbeb; }
    .sla-table tbody tr.row-pending:hover .sc { background: #fef3c7; }
    /* Shadow divider after last sticky column */
    .sc-last { box-shadow: 4px 0 8px -3px rgba(0,0,0,0.12) !important; }

    .grp-info { background: #f9fafb; }
    .grp-resp { background: #eff6ff; }
    .grp-res  { background: #f0fdf4; }
    .sla-badge-met      { background:#dcfce7; color:#15803d; }
    .sla-badge-breached { background:#fee2e2; color:#b91c1c; }
    .sla-badge-pending  { background:#dbeafe; color:#1d4ed8; }
    .sla-badge-paused   { background:#fef3c7; color:#92400e; }
    .sla-badge-pv       { background:#f3f4f6; color:#6b7280; }
    .sla-check-met      { color: #16a34a; }
    .sla-check-breached { color: #dc2626; }
    .sla-check-none     { color: #d1d5db; }
</style>
@endpush

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="space-y-5">

    {{-- ── Main Card ──────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">

        {{-- Toolbar ──────────────────────────────────────────────────────── --}}
        <div class="px-5 py-4 border-b border-gray-100">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">

                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-red-50 flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-table text-red-600 text-sm"></i>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-800">SLA Tracker</p>
                        <p class="text-xs text-gray-400">Auto-refreshes every 60 seconds</p>
                    </div>
                </div>

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
                    @php $curMonth = (int) date('n'); @endphp
                    <div class="custom-dd relative" data-onchange="loadReport" data-fixed="true" style="min-width:115px">
                        <button type="button" class="custom-dd-btn w-full flex items-center justify-between pl-3 pr-2.5 py-1.5 bg-white border border-gray-200 rounded-lg text-xs text-gray-500 hover:border-gray-400 transition-all text-left gap-2">
                            <span class="custom-dd-label">All Months</span>
                            <svg class="custom-dd-arrow w-3.5 h-3.5 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <input type="hidden" id="filterMonth" value="">
                        <div class="custom-dd-panel hidden absolute top-full left-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:260px; min-width:140px;">
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm font-medium text-gray-900 bg-gray-50 hover:bg-gray-50 transition-colors" data-value="">All Months</button>
                            @foreach(range(1,12) as $m)
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm {{ $m == $curMonth ? 'font-medium text-gray-900' : 'text-gray-600' }} hover:bg-gray-50 transition-colors" data-value="{{ $m }}">
                                {{ \Carbon\Carbon::create((int) date('Y'), $m, 1)->isoFormat('MMMM') }}
                            </button>
                            @endforeach
                        </div>
                    </div>

                    {{-- Year --}}
                    @php $curYear = (int) date('Y'); @endphp
                    <div class="custom-dd relative" data-onchange="loadReport" data-fixed="true" style="min-width:100px">
                        <button type="button" class="custom-dd-btn w-full flex items-center justify-between pl-3 pr-2.5 py-1.5 bg-white border border-gray-200 rounded-lg text-xs text-gray-500 hover:border-gray-400 transition-all text-left gap-2">
                            <span class="custom-dd-label">All Years</span>
                            <svg class="custom-dd-arrow w-3.5 h-3.5 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <input type="hidden" id="filterYear" value="">
                        <div class="custom-dd-panel hidden absolute top-full left-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:200px; min-width:100px;">
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm font-medium text-gray-900 bg-gray-50 hover:bg-gray-50 transition-colors" data-value="">All Years</button>
                            @foreach(range($curYear, $curYear - 3) as $y)
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="{{ $y }}">{{ $y }}</button>
                            @endforeach
                        </div>
                    </div>

                    {{-- Status (matches ticket.status — same values as the Status column here & on the Ticket page) --}}
                    <div class="custom-dd relative" data-onchange="applyReportFilters" data-fixed="true" style="min-width:150px">
                        <button type="button" class="custom-dd-btn w-full flex items-center justify-between pl-3 pr-2.5 py-1.5 bg-white border border-gray-200 rounded-lg text-xs text-gray-500 hover:border-gray-400 transition-all text-left gap-2">
                            <span class="custom-dd-label">All Statuses</span>
                            <svg class="custom-dd-arrow w-3.5 h-3.5 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <input type="hidden" id="filterStatus" value="">
                        <div class="custom-dd-panel hidden absolute top-full left-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:260px; min-width:190px;">
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm font-medium text-gray-900 bg-gray-50 hover:bg-gray-50 transition-colors" data-value="">All Statuses</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="open">Open</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="inprocess">Inprocess</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="waiting_on_customer">Waiting on Customer</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="waiting_on_3rd_party">Waiting on 3rd Party</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="waiting_to_confirmation">Waiting to Confirmation</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="hold">Hold</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="cancelled">Cancelled</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="closed">Closed</button>
                        </div>
                    </div>

                    {{-- Type of Service --}}
                    <div class="custom-dd relative" data-onchange="applyReportFilters" data-fixed="true" style="min-width:130px">
                        <button type="button" class="custom-dd-btn w-full flex items-center justify-between pl-3 pr-2.5 py-1.5 bg-white border border-gray-200 rounded-lg text-xs text-gray-500 hover:border-gray-400 transition-all text-left gap-2">
                            <span class="custom-dd-label">All Type of Service</span>
                            <svg class="custom-dd-arrow w-3.5 h-3.5 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <input type="hidden" id="filterTypeOfService" value="">
                        <div class="custom-dd-panel hidden absolute top-full left-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:260px; min-width:160px;">
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm font-medium text-gray-900 bg-gray-50 hover:bg-gray-50 transition-colors" data-value="">All Type of Service</button>
                            @foreach(['AMS','MO','ATS','CR','RISE','CLOUD','POSTPAID','Project','Internal'] as $tos)
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="{{ $tos }}">{{ $tos }}</button>
                            @endforeach
                        </div>
                    </div>

                    {{-- Type --}}
                    <div class="custom-dd relative" data-onchange="applyReportFilters" data-fixed="true" style="min-width:130px">
                        <button type="button" class="custom-dd-btn w-full flex items-center justify-between pl-3 pr-2.5 py-1.5 bg-white border border-gray-200 rounded-lg text-xs text-gray-500 hover:border-gray-400 transition-all text-left gap-2">
                            <span class="custom-dd-label">All Types</span>
                            <svg class="custom-dd-arrow w-3.5 h-3.5 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <input type="hidden" id="filterTicketType" value="">
                        <div class="custom-dd-panel hidden absolute top-full left-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:260px; min-width:160px;">
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm font-medium text-gray-900 bg-gray-50 hover:bg-gray-50 transition-colors" data-value="">All Types</button>
                            @foreach(['Incident','Change Request','Service Request'] as $tt)
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="{{ $tt }}">{{ $tt }}</button>
                            @endforeach
                        </div>
                    </div>

                    {{-- Search ticket --}}
                    <div class="relative">
                        <input type="text" id="cfInput-ticket" placeholder="Cari tiket…" oninput="applyReportFilters()"
                            class="pl-8 pr-3 py-1.5 text-xs border border-gray-200 rounded-lg text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-200 w-36">
                        <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-300 text-[10px]"></i>
                    </div>

                    {{-- Clear Filter --}}
                    <button onclick="clearFilters()" id="clearBtn"
                        class="hidden inline-flex items-center gap-1.5 text-xs font-medium text-gray-500 hover:text-red-600 border border-gray-200 hover:border-red-300 bg-white hover:bg-red-50 px-3 py-1.5 rounded-lg transition whitespace-nowrap">
                        <i class="fas fa-times text-xs"></i> Clear Filter
                    </button>

                    {{-- Refresh --}}
                    <button onclick="loadReport()" id="refreshBtn"
                        class="inline-flex items-center gap-1.5 text-xs font-medium text-gray-500 hover:text-gray-700 border border-gray-200 hover:border-gray-300 px-3 py-1.5 rounded-lg transition bg-white whitespace-nowrap">
                        <i class="fas fa-sync-alt text-xs" id="refreshIcon"></i> Refresh
                    </button>

                    {{-- Export Excel --}}
                    <button onclick="exportToExcel()" id="exportBtn"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-semibold rounded-lg hover:bg-gray-50 transition-all duration-200">
                        <i class="fas fa-file-excel text-green-600 text-sm"></i>
                        Export SLA
                    </button>
                </div>
            </div>
        </div>

        {{-- Table ────────────────────────────────────────────────────────── --}}
        <div class="overflow-x-auto" style="max-height: calc(100vh - 280px); overflow-y: auto;">
            <table class="w-full text-xs sla-table" style="min-width: 3800px;">
                <thead>
                    {{-- Group header row --}}
                    <tr class="border-b border-gray-200">
                        <th colspan="4"  class="sc sc-last px-3 py-2 text-center text-[10px] font-bold text-gray-500 uppercase tracking-wider grp-info" style="left:0;">Informasi Tiket</th>
                        <th colspan="9"  class="px-3 py-2 text-center text-[10px] font-bold text-gray-500 uppercase tracking-wider border-r border-gray-200 grp-info"></th>
                        <th colspan="7"  class="px-3 py-2 text-center text-[10px] font-bold text-blue-700 uppercase tracking-wider border-r border-gray-200 grp-resp">SLA Response</th>
                        <th colspan="10" class="px-3 py-2 text-center text-[10px] font-bold text-green-700 uppercase tracking-wider border-r border-gray-200 grp-res">SLA Resolution</th>
                        <th colspan="1"  class="px-3 py-2 text-center text-[10px] font-bold text-gray-500 uppercase tracking-wider grp-info">Aksi</th>
                    </tr>
                    {{-- Column header row (sc = sticky column, left = cumulative px offset) --}}
                    {{--
                        Widths : 32 44 110 110 90 85 70 150 90 100 75 120 85
                        Lefts  :  0 32  76 186 296 386 471 541 691 781 881 956 1076
                    --}}
                    <tr class="border-b border-gray-100 text-[9px]">
                        {{-- Informasi Tiket --}}
                        <th class="sc text-center px-2 py-2 font-semibold text-gray-400 uppercase tracking-wider border-r border-gray-100 grp-info" style="left:0;width:32px;min-width:32px;">No</th>
                        <th class="sc text-center px-2 py-2 font-semibold text-gray-400 uppercase tracking-wider grp-info" style="left:32px;width:44px;min-width:44px;">Year</th>
                        <th class="sc text-left   px-2 py-2 font-semibold text-gray-400 uppercase tracking-wider grp-info" style="left:76px;width:110px;min-width:110px;">Customer</th>
                        <th class="sc sc-last text-left px-2 py-2 font-semibold text-gray-400 uppercase tracking-wider grp-info" style="left:186px;width:110px;min-width:110px;">Tiket</th>
                        <th class="text-center px-2 py-2 font-semibold text-gray-400 uppercase tracking-wider grp-info" style="min-width:90px;">Module<br><span class="text-[8px] font-normal normal-case">(FICO/FM/HCM/MM/BIBO/ABAP/BASIS)</span></th>
                        <th class="text-center px-2 py-2 font-semibold text-gray-400 uppercase tracking-wider grp-info" style="min-width:85px;">Priority</th>
                        <th class="text-center px-2 py-2 font-semibold text-gray-400 uppercase tracking-wider grp-info" style="min-width:70px;">Scale</th>
                        <th class="text-left   px-2 py-2 font-semibold text-gray-400 uppercase tracking-wider grp-info" style="min-width:150px;">Issue</th>
                        <th class="text-center px-2 py-2 font-semibold text-gray-400 uppercase tracking-wider grp-info" style="min-width:90px;">CUST PIC</th>
                        <th class="text-center px-2 py-2 font-semibold text-gray-400 uppercase tracking-wider grp-info" style="min-width:100px;">FUNCTIONAL PIC</th>
                        <th class="text-center px-2 py-2 font-semibold text-gray-400 uppercase tracking-wider grp-info" style="min-width:75px;">Type of<br>Service<br><span class="text-[8px] font-normal normal-case">(MO / CR)</span></th>
                        <th class="text-center px-2 py-2 font-semibold text-gray-400 uppercase tracking-wider grp-info" style="min-width:120px;">Type<br><span class="text-[8px] font-normal normal-case">(Incident/Error, Request/CR, Konsultasi)</span></th>
                        <th class="text-center px-2 py-2 font-semibold text-gray-400 uppercase tracking-wider grp-info" style="min-width:85px;">Status</th>
                        {{-- SLA Response --}}
                        <th class="text-center px-2 py-2 font-semibold text-blue-500 uppercase tracking-wider grp-resp" style="min-width:130px">Date &amp; Time<br>Received</th>
                        <th class="text-center px-2 py-2 font-semibold text-blue-500 uppercase tracking-wider grp-resp" style="min-width:130px">Date &amp; Time Start<br>SLA Response<br><span class="text-[8px] font-normal normal-case">(Working Hours 08:00–17:00)</span></th>
                        <th class="text-center px-2 py-2 font-semibold text-blue-500 uppercase tracking-wider grp-resp" style="min-width:70px">SLA<br>Response<br>Time</th>
                        <th class="text-center px-2 py-2 font-semibold text-blue-500 uppercase tracking-wider grp-resp" style="min-width:130px">Response Due On</th>
                        <th class="text-center px-2 py-2 font-semibold text-blue-500 uppercase tracking-wider grp-resp" style="min-width:130px">Date &amp; Time<br>Responded</th>
                        <th class="text-center px-2 py-2 font-semibold text-blue-500 uppercase tracking-wider grp-resp" style="min-width:80px">Response<br>Duration</th>
                        <th class="text-center px-2 py-2 font-semibold text-blue-500 uppercase tracking-wider grp-resp border-r border-gray-200" style="min-width:80px">SLA<br>Response<br>Time</th>
                        {{-- SLA Resolution --}}
                        <th class="text-center px-2 py-2 font-semibold text-green-600 uppercase tracking-wider grp-res" style="min-width:70px">SLA<br>Resolution<br>(HOUR)</th>
                        <th class="text-center px-2 py-2 font-semibold text-green-600 uppercase tracking-wider grp-res" style="min-width:130px">Date &amp; Time Start<br>SLA Resolution<br><span class="text-[8px] font-normal normal-case">(Working Day, 08:00–17:00 EXCEPT VERY HIGH)</span></th>
                        <th class="text-center px-2 py-2 font-semibold text-green-600 uppercase tracking-wider grp-res" style="min-width:130px">SLA Resolution<br>Due On<br><span class="text-[8px] font-normal normal-case">(Working Day, 08:00–17:00 EXCEPT VERY HIGH)</span></th>
                        <th class="text-center px-2 py-2 font-semibold text-green-600 uppercase tracking-wider grp-res" style="min-width:130px">First<br>Resolved Date</th>
                        <th class="text-center px-2 py-2 font-semibold text-green-600 uppercase tracking-wider grp-res" style="min-width:130px">Date of sending<br>Resolution doc./mail</th>
                        <th class="text-center px-2 py-2 font-semibold text-green-600 uppercase tracking-wider grp-res" style="min-width:80px">Resolution<br>Duration<br>(STOP-GO)</th>
                        <th class="text-center px-2 py-2 font-semibold text-green-600 uppercase tracking-wider grp-res" style="min-width:80px">SLA<br>Resolution<br>Time</th>
                        <th class="text-center px-2 py-2 font-semibold text-green-600 uppercase tracking-wider grp-res" style="min-width:80px">SLA<br>Resolution<br>Status</th>
                        <th class="text-center px-2 py-2 font-semibold text-green-600 uppercase tracking-wider grp-res" style="min-width:130px">Closed Date</th>
                        <th class="text-center px-2 py-2 font-semibold text-green-600 uppercase tracking-wider grp-res border-r border-gray-200" style="min-width:70px">Notes</th>
                        {{-- Actions --}}
                        <th class="text-center px-2 py-2 font-semibold text-gray-400 uppercase tracking-wider grp-info" style="min-width:80px">Log / PDF</th>
                    </tr>
                </thead>
                <tbody id="reportTableBody">
                    <tr>
                        <td colspan="31" class="py-16 text-center">
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

{{-- ── SLA Log Modal ─────────────────────────────────────────────────────── --}}
<div id="detailModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" onclick="closeDetailModal()"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-6xl max-h-[92vh] flex flex-col overflow-hidden">

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

            <div id="detailContent" class="overflow-auto flex-1 bg-gray-50/30">
                <div class="flex items-center justify-center h-32 text-gray-300">
                    <i class="fas fa-spinner fa-spin text-3xl"></i>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── Notes Modal ───────────────────────────────────────────────────────── --}}
<div id="notesModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" onclick="closeNotesModal()"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[80vh] flex flex-col overflow-hidden">
            <div class="flex-shrink-0 flex items-center justify-between px-5 py-3.5 border-b border-gray-100">
                <div>
                    <h3 class="text-sm font-semibold text-gray-800">Notes</h3>
                    <p class="text-xs text-gray-400 mt-0.5">Ticket <span id="notesTicketNum" class="font-mono font-semibold text-gray-600"></span></p>
                </div>
                <button onclick="closeNotesModal()"
                    class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times text-sm"></i>
                </button>
            </div>
            <div id="notesModalContent" class="overflow-auto flex-1 px-5 py-4 text-xs text-gray-600 leading-relaxed"></div>
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
    'pending_validation': { bg:'sla-badge-pv',      label:'Pending Val.',  icon:'fa-hourglass-half' },
    'pending':            { bg:'sla-badge-pending',  label:'Active',        icon:'fa-clock'           },
    'paused':             { bg:'sla-badge-paused',   label:'Paused',        icon:'fa-pause-circle'    },
    'met':                { bg:'sla-badge-met',      label:'Terpenuhi',     icon:'fa-check-circle'    },
    'breached':           { bg:'sla-badge-breached', label:'Dilanggar',     icon:'fa-times-circle'    },
};
let _allTickets = [];
let _filteredTickets = [];

// "2026-08-06T12:21:00" → "08/06/2026 12:21" (MM/DD/YYYY HH:MM)
function toMDY(dt) {
    const y = dt.substring(0, 4), m = dt.substring(5, 7), d = dt.substring(8, 10), hm = dt.substring(11, 16);
    return `${m}/${d}/${y} ${hm}`;
}

function fmtDT(dt) {
    if (!dt) return '—';
    return toMDY(dt);
}

// Notes column log-line timestamp — same MM/DD/YYYY HH:MM format as the rest of the report.
function fmtNoteDT(dt) {
    if (!dt) return '';
    return toMDY(dt);
}

function escHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));
}

// Build the "[DD.MM.YYYY HH:mm] text" chronological log (plain text lines) for the Notes
// column from the ticket's tagged SLA chat messages.
function buildNotesLog(t) {
    const lines = (t.notes || []).map(n => `[${fmtNoteDT(n.at)}] ${n.text}`);
    if (!lines.length && t.sla_policy_missing) {
        lines.push('Belum ada SLA Policy untuk delivery support ini — jam kerja tidak diterapkan');
    }
    return lines;
}

// Format decimal hours → HH:MM
function fmtHHMM(h) {
    if (h === null || h === undefined) return '—';
    const totalMins = Math.round(parseFloat(h) * 60);
    const hh = Math.floor(totalMins / 60);
    const mm = totalMins % 60;
    return String(hh).padStart(2, '0') + ':' + String(mm).padStart(2, '0');
}


function clearFilters() {
    setCustomDropdownValue('filterCustomer', '');
    setCustomDropdownValue('filterMonth', '');
    setCustomDropdownValue('filterYear', '');
    setCustomDropdownValue('filterStatus', '');
    setCustomDropdownValue('filterTypeOfService', '');
    setCustomDropdownValue('filterTicketType', '');
    const searchInput = document.getElementById('cfInput-ticket');
    if (searchInput) searchInput.value = '';
    updateClearBtn();
    loadReport();
}

function updateClearBtn() {
    const c   = document.getElementById('filterCustomer').value;
    const m   = document.getElementById('filterMonth').value;
    const y   = document.getElementById('filterYear').value;
    const s   = document.getElementById('filterStatus').value;
    const tos = document.getElementById('filterTypeOfService').value;
    const tt  = document.getElementById('filterTicketType').value;
    const q   = (document.getElementById('cfInput-ticket')?.value || '').trim();
    const defaultMonth = '';
    const defaultYear  = '';
    const hasFilter = c || s || tos || tt || q || m !== defaultMonth || y !== defaultYear;
    document.getElementById('clearBtn')?.classList.toggle('hidden', !hasFilter);
}

function exportToExcel() {
    const tickets = _filteredTickets;
    if (!tickets.length) {
        alert('Tidak ada data untuk diekspor.');
        return;
    }

    const btn = document.getElementById('exportBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin text-xs"></i> Mengekspor...';

    try {
        const rows = tickets.map((t, idx) => {
            const isPending = t.is_pending_validation;
            if (isPending) {
                return {
                    'No': idx + 1,
                    'Year': t.year || '',
                    'Customer': t.customer_name || '',
                    'Tiket': 'Staging #' + (t.staging_id || '?'),
                    'Module': '',
                    'Priority': '',
                    'Scale': '',
                    'Issue': '',
                    'CUST PIC': '',
                    'Functional PIC': '',
                    'Type of Service': '',
                    'Type': '',
                    'Status': 'Menunggu Validasi',
                    // SLA Response
                    'Date & Time Received': '',
                    'Date & Time Start SLA Response': '',
                    'SLA Response Time (Jam)': '',
                    'SLA Response Due On': '',
                    'Date & Time Responded': '',
                    'Response Duration': '',
                    'SLA Response Status': '',
                    // SLA Resolution
                    'SLA Resolution (Hour)': '',
                    'Date & Time Start SLA Resolution': '',
                    'SLA Resolution Due On': '',
                    'First Resolved Date': '',
                    'Date Sending Resolution Doc': '',
                    'Resolution Duration (STOP-GO)': '',
                    'SLA Resolution Time': '',
                    'SLA Resolution Status': '',
                    'Closed Date': '',
                    'Notes': '',
                };
            }

            const typeOfService = t.delivery_support_type || '';
            let respStatusLabel;
            if (t.sla_policy_missing) {
                respStatusLabel = 'No SLA Config';
            } else if (t.response?.actual_hours != null && t.response?.target_hours != null) {
                respStatusLabel = parseFloat(t.response.actual_hours) <= parseFloat(t.response.target_hours) ? 'Achieved' : 'Not Achieved';
            } else {
                respStatusLabel = STATUS_CFG[t.response?.status]?.label || '';
            }
            return {
                'No': idx + 1,
                'Year': t.year || '',
                'Customer': t.customer_name || '',
                'Tiket': t.ticket_number || '',
                'Module': t.module || '',
                'Priority': t.ticket_priority || '',
                'Scale': t.scale || '',
                'Issue': t.description || '',
                'CUST PIC': t.cust_pic || '',
                'Functional PIC': t.pic || '',
                'Type of Service': typeOfService,
                'Type': t.ticket_type || '',
                'Status': (t.ticket_status || '').replace(/_/g, ' '),
                // SLA Response
                'Date & Time Received': t.received_at ? toMDY(t.received_at) : '',
                'Date & Time Start SLA Response': t.sla_start_at ? toMDY(t.sla_start_at) : '',
                'SLA Response Time (Jam)': t.response?.target_hours ?? '',
                'SLA Response Due On': t.response?.due_at ? toMDY(t.response.due_at) : '',
                'Date & Time Responded': t.response?.responded_at ? toMDY(t.response.responded_at) : '',
                'Response Duration': t.response?.actual_hours != null ? fmtHHMM(t.response.actual_hours) : '',
                'SLA Response Status': respStatusLabel,
                // SLA Resolution
                'SLA Resolution (Hour)': t.resolution?.target_hours ?? '',
                'Date & Time Start SLA Resolution': t.resolution?.start_at ? toMDY(t.resolution.start_at) : '',
                'SLA Resolution Due On': t.resolution?.due_at ? toMDY(t.resolution.due_at) : '',
                'First Resolved Date': t.resolution?.resolved_at ? toMDY(t.resolution.resolved_at) : '',
                'Date Sending Resolution Doc': t.resolution?.doc_sent_at ? toMDY(t.resolution.doc_sent_at) : '',
                'Resolution Duration (STOP-GO)': t.resolution?.actual_hours != null ? fmtHHMM(t.resolution.actual_hours) : '',
                'SLA Resolution Time': t.resolution?.live_hours != null
                    ? fmtHHMM(t.resolution.live_hours) + (t.resolution?.is_final === false ? ' (berjalan)' : '')
                    : '',
                'SLA Resolution Status': t.resolution?.time_status || '',
                'Closed Date': t.closed_at ? toMDY(t.closed_at) : '',
                'Notes': buildNotesLog(t).join('\n'),
            };
        });

        const ws = XLSX.utils.json_to_sheet(rows);

        // Auto column widths
        const colWidths = Object.keys(rows[0] || {}).map(key => ({
            wch: Math.max(key.length, ...rows.map(r => String(r[key] ?? '').length)) + 2
        }));
        ws['!cols'] = colWidths;

        // Freeze first row
        ws['!freeze'] = { xSplit: 0, ySplit: 1 };

        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'SLA Report');

        const month = document.getElementById('filterMonth').value;
        const year  = document.getElementById('filterYear').value;
        const dateSuffix = [year, month ? month.padStart(2, '0') : 'all'].filter(Boolean).join('-');
        const filename = `SLA_Report_${dateSuffix}.xlsx`;

        XLSX.writeFile(wb, filename);
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-file-excel text-xs"></i> Export Excel';
    }
}

async function loadReport() {
    const params = new URLSearchParams();
    const c = document.getElementById('filterCustomer').value;
    const m = document.getElementById('filterMonth').value;
    const y = document.getElementById('filterYear').value;
    if (c) params.set('customer_id', c);
    if (m) params.set('month', m);
    if (y) params.set('year', y);

    updateClearBtn();
    const icon = document.getElementById('refreshIcon');
    icon?.classList.add('fa-spin');

    try {
        const res  = await fetch('/api/admin/sla/report?' + params, { credentials: 'include' });
        const json = await res.json();
        if (!json.success) return;

        _allTickets = json.data.tickets || [];
        applyReportFilters();
    } catch (e) {
        document.getElementById('reportTableBody').innerHTML = `
            <tr><td colspan="31" class="py-12 text-center">
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
    const ticketQ = (document.getElementById('cfInput-ticket')?.value || '').toLowerCase().trim();
    const tosF    = document.getElementById('filterTypeOfService')?.value || '';
    const ttF     = document.getElementById('filterTicketType')?.value || '';
    const stF     = document.getElementById('filterStatus')?.value || '';

    const filtered = _allTickets.filter(t => {
        if (tosF && t.delivery_support_type !== tosF) return false;
        if (ttF && t.ticket_type !== ttF) return false;
        if (stF && t.ticket_status !== stF) return false;
        if (ticketQ && !(t.ticket_number || '').toLowerCase().includes(ticketQ)
                    && !(t.customer_name || '').toLowerCase().includes(ticketQ)
                    && !(t.description  || '').toLowerCase().includes(ticketQ)) return false;
        return true;
    });

    _filteredTickets = filtered;
    updateClearBtn();
    renderTable(filtered);
}

function renderTable(tickets) {
    const tbody  = document.getElementById('reportTableBody');
    const footer = document.getElementById('tableFooter');

    if (!tickets.length) {
        footer.classList.add('hidden');
        tbody.innerHTML = `
            <tr><td colspan="31" class="py-16 text-center">
                <div class="flex flex-col items-center gap-2 text-gray-300">
                    <i class="fas fa-search text-4xl"></i>
                    <p class="text-sm font-medium text-gray-400 mt-1">Tidak ada tiket ditemukan</p>
                    <p class="text-xs text-gray-300">Coba ubah filter di atas</p>
                </div>
            </td></tr>`;
        return;
    }

    footer.classList.remove('hidden');
    document.getElementById('tableCount').textContent = `${tickets.length} tiket ditemukan`;

    tbody.innerHTML = tickets.map((t, idx) => {
        const isPending = t.is_pending_validation;
        const noPolicyWarning = t.sla_policy_missing
            ? `<span class="ml-1 text-amber-500 cursor-help" title="Belum ada SLA Policy untuk delivery support tiket ini — jam kerja tidak diterapkan, waktu ditampilkan apa adanya"><i class="fas fa-exclamation-triangle text-[9px]"></i></span>`
            : '';
        const prio = PRIORITY_CFG[t.ticket_priority] || { bg:'bg-gray-50', text:'text-gray-600', dot:'bg-gray-400' };

        if (isPending) {
            return `
            <tr class="row-pending border-b border-amber-100 bg-amber-50/40 hover:bg-amber-50/70 transition-colors">
                <td class="sc px-2 py-2 text-center text-gray-400"                style="left:0;width:32px;min-width:32px;">${idx + 1}</td>
                <td class="sc px-2 py-2 text-center text-gray-500"                style="left:32px;width:44px;min-width:44px;">${t.year || '—'}</td>
                <td class="sc px-2 py-2 text-gray-700 font-medium"                style="left:76px;width:110px;min-width:110px;">${t.customer_name || '—'}</td>
                <td class="sc sc-last px-2 py-2"                                   style="left:186px;width:110px;min-width:110px;">
                    <span class="font-mono text-[10px] font-semibold text-amber-700 bg-amber-100 px-2 py-0.5 rounded">Staging #${t.staging_id || '?'}</span>
                </td>
                <td class="px-2 py-2 text-center text-amber-600" colspan="9">
                    <span class="text-[10px] font-semibold text-amber-700 bg-amber-100 px-2 py-0.5 rounded-full">Menunggu Validasi</span>
                </td>
                <td class="px-2 py-2 text-gray-400" colspan="17">—</td>
                <td class="px-2 py-2 text-center text-gray-400 italic text-[10px]">Validasi dulu</td>
            </tr>`;
        }

        const resStatusColor = t.resolution?.status === 'met' ? 'text-green-600' : t.resolution?.status === 'breached' ? 'text-red-600' : 'text-gray-400';

        // SLA Resolution Time — live/running duration column (separate from Resolution
        // Duration (STOP-GO), which stays blank until the ticket closes). Shows progress
        // on still-open tickets, tagged amber + clock icon since it's not final yet.
        const resTimeIsOngoing = t.resolution?.live_hours != null && t.resolution?.is_final === false;
        const resTimeColor     = resTimeIsOngoing ? 'text-amber-600' : resStatusColor;

        // SLA Resolution Status — Achieved/Not Achieved/N/A verdict computed server-side
        // (SlaController::slaResolutionAchievement).
        const resAchieveColor = t.resolution?.time_status === 'Achieved' ? 'text-green-600'
            : t.resolution?.time_status === 'Not Achieved' ? 'text-red-600' : 'text-gray-400';

        // SLA Response Time (achieved) — Achieved if Response Duration <= SLA Response Time (Hour), else Not Achieved.
        // If not yet responded, fall back to the raw status label (e.g. Active).
        // If there's no SLA config (policy) for this delivery support, warn instead of silently comparing against a null target.
        let respStatusLabel, respStatusColor;
        if (t.sla_policy_missing) {
            respStatusLabel = 'No SLA Config';
            respStatusColor = 'text-amber-600';
        } else if (t.response?.actual_hours != null && t.response?.target_hours != null) {
            const respAchieved = parseFloat(t.response.actual_hours) <= parseFloat(t.response.target_hours);
            respStatusLabel = respAchieved ? 'Achieved' : 'Not Achieved';
            respStatusColor = respAchieved ? 'text-green-600' : 'text-red-600';
        } else {
            respStatusLabel = STATUS_CFG[t.response?.status]?.label || '—';
            respStatusColor = t.response?.status === 'met' ? 'text-green-600' : t.response?.status === 'breached' ? 'text-red-600' : 'text-gray-400';
        }

        const desc = (t.description || '').substring(0, 70) + ((t.description || '').length > 70 ? '…' : '');
        const ticketUrl = t.ticket_id ? `/ticket/${t.ticket_id}` : '#';

        const notesCount = buildNotesLog(t).length;
        const notesCell  = notesCount
            ? `<button onclick="openNotesModal(${t.ticket_id}, '${t.ticket_number}')" title="Lihat semua notes"
                   class="inline-flex items-center gap-1.5 text-[10px] font-semibold text-gray-500 hover:text-indigo-600 border border-gray-200 hover:border-indigo-300 hover:bg-indigo-50 px-2 py-1 rounded-lg transition">
                   <i class="fas fa-clipboard-list text-[10px]"></i> ${notesCount}
               </button>`
            : '<span class="text-gray-300 text-[10px]">—</span>';

        // Type of Service label from ticket's assigned Delivery Support type (MO/CR/AMS/ATS/...)
        const typeOfService = t.delivery_support_type || '—';

        return `
        <tr class="border-b border-gray-50 hover:bg-blue-50/20 transition-colors">
            {{-- Informasi Tiket (sticky No→Status) --}}
            <td class="sc px-2 py-2 text-center text-gray-400 text-[10px]" style="left:0;width:32px;min-width:32px;">${idx + 1}</td>
            <td class="sc px-2 py-2 text-center text-gray-600 font-medium"  style="left:32px;width:44px;min-width:44px;">${t.year || '—'}</td>
            <td class="sc px-2 py-2 overflow-hidden"                         style="left:76px;width:110px;min-width:110px;">
                <p class="text-gray-700 font-medium truncate">${t.customer_name || '—'}</p>
            </td>
            <td class="sc sc-last px-2 py-2"                                   style="left:186px;width:110px;min-width:110px;">
                <a href="${ticketUrl}" class="font-mono text-[10px] font-bold text-red-700 bg-red-50 px-1.5 py-0.5 rounded hover:bg-red-100 transition whitespace-nowrap">
                    #${t.ticket_number}
                </a>
            </td>
            <td class="px-2 py-2 text-center" style="min-width:90px;">
                <span class="text-[10px] font-semibold text-gray-600 bg-gray-100 px-1.5 py-0.5 rounded">${t.module || '—'}</span>
            </td>
            <td class="px-2 py-2 text-center" style="min-width:85px;">
                <span class="inline-flex items-center gap-1 text-[10px] font-semibold ${prio.text} ${prio.bg} px-1.5 py-0.5 rounded-full">
                    <span class="w-1.5 h-1.5 rounded-full ${prio.dot} flex-shrink-0"></span>${t.ticket_priority || '—'}
                </span>
            </td>
            <td class="px-2 py-2 text-center text-gray-500 text-[10px]" style="min-width:70px;">${t.scale || '—'}</td>
            <td class="px-2 py-2 overflow-hidden" style="min-width:150px;">
                <p class="text-gray-600 truncate text-[10px]" title="${(t.description || '').replace(/"/g, '&quot;')}">${desc || '—'}</p>
            </td>
            <td class="px-2 py-2 text-center text-gray-500 text-[10px]" style="min-width:90px;">${t.cust_pic || '—'}</td>
            <td class="px-2 py-2 text-center text-gray-500 text-[10px]" style="min-width:100px;">${t.pic || '—'}</td>
            <td class="px-2 py-2 text-center" style="min-width:75px;">
                <span class="text-[10px] font-semibold text-gray-600 bg-gray-100 px-1.5 py-0.5 rounded">${typeOfService}</span>
            </td>
            <td class="px-2 py-2 text-center" style="min-width:120px;">
                <span class="text-[10px] text-gray-500 bg-gray-50 px-1.5 py-0.5 rounded">${t.ticket_type || '—'}</span>
            </td>
            <td class="px-2 py-2 text-center" style="min-width:85px;">
                <span class="text-[10px] text-gray-500 bg-gray-50 px-1.5 py-0.5 rounded capitalize">${(t.ticket_status || '—').replace(/_/g,' ')}</span>
            </td>
            {{-- SLA Response --}}
            <td class="px-2 py-2 bg-blue-50/20 text-[10px] text-gray-600">${fmtDT(t.received_at)}</td>
            <td class="px-2 py-2 bg-blue-50/20 text-[10px] text-gray-600">${fmtDT(t.sla_start_at)}${noPolicyWarning}</td>
            <td class="px-2 py-2 text-center bg-blue-50/20">
                <span class="text-[10px] text-blue-700 font-semibold">${t.response?.target_hours ?? '—'}</span>
            </td>
            <td class="px-2 py-2 bg-blue-50/20 text-[10px] text-gray-600">${fmtDT(t.response?.due_at)}</td>
            <td class="px-2 py-2 bg-blue-50/20 text-[10px] text-gray-600">${fmtDT(t.response?.responded_at)}</td>
            <td class="px-2 py-2 text-center bg-blue-50/20">
                <span class="text-[10px] font-semibold ${respStatusColor}">${fmtHHMM(t.response?.actual_hours)}</span>
            </td>
            <td class="px-2 py-2 text-center bg-blue-50/20 border-r border-gray-200">
                <span class="text-[10px] font-semibold ${respStatusColor}">${respStatusLabel}</span>${noPolicyWarning}
            </td>
            {{-- SLA Resolution --}}
            <td class="px-2 py-2 text-center bg-green-50/20">
                <span class="text-[10px] text-green-700 font-semibold">${t.resolution?.target_hours ?? '—'}</span>
            </td>
            <td class="px-2 py-2 bg-green-50/20 text-[10px] text-gray-600">${fmtDT(t.resolution?.start_at)}${noPolicyWarning}</td>
            <td class="px-2 py-2 bg-green-50/20 text-[10px] text-gray-600">${fmtDT(t.resolution?.due_at)}</td>
            <td class="px-2 py-2 bg-green-50/20 text-[10px] text-gray-600">${fmtDT(t.resolution?.resolved_at)}</td>
            <td class="px-2 py-2 bg-green-50/20 text-[10px] text-gray-600">${fmtDT(t.resolution?.doc_sent_at)}</td>
            <td class="px-2 py-2 text-center bg-green-50/20">
                <span class="text-[10px] font-semibold ${resStatusColor}">${fmtHHMM(t.resolution?.actual_hours)}</span>
            </td>
            <td class="px-2 py-2 text-center bg-green-50/20">
                <span class="text-[10px] font-semibold ${resTimeColor}" ${resTimeIsOngoing ? `title="Tiket masih berjalan — durasi ini belum final, akan berubah sampai tiket closed"` : ''}>
                    ${fmtHHMM(t.resolution?.live_hours)}${resTimeIsOngoing ? ' <i class="fas fa-spinner text-[9px]"></i>' : ''}
                </span>
            </td>
            <td class="px-2 py-2 text-center bg-green-50/20">
                <span class="text-[10px] font-semibold ${resAchieveColor}">${t.resolution?.time_status || '—'}</span>
            </td>
            <td class="px-2 py-2 bg-green-50/20 text-[10px] text-gray-600">${fmtDT(t.closed_at)}</td>
            <td class="px-2 py-2 text-center bg-green-50/20 border-r border-gray-200">${notesCell}</td>
            {{-- Actions --}}
            <td class="px-2 py-2">
                <div class="flex items-center gap-1">
                    <button onclick="openDetail(${t.ticket_id}, '${t.ticket_number}')" title="SLA Log"
                        class="inline-flex items-center gap-1 px-2 py-1 rounded-lg border border-gray-200 hover:border-indigo-300 hover:bg-indigo-50 text-gray-400 hover:text-indigo-600 transition text-[10px]">
                        <i class="fas fa-history text-xs"></i>
                    </button>
                    <a href="/admin/sla/tickets/${t.ticket_id}/log-pdf" target="_blank" title="Log PDF"
                        class="inline-flex items-center gap-1 px-2 py-1 rounded-lg border border-gray-200 hover:border-red-300 hover:bg-red-50 text-gray-400 hover:text-red-600 transition text-[10px]">
                        <i class="fas fa-file-pdf text-xs"></i>
                    </a>
                </div>
            </td>
        </tr>`;
    }).join('');
}

// ── SLA Log Modal ──────────────────────────────────────────────────────────────

let _currentDetailTicketId = null;

async function openDetail(ticketId, ticketNum) {
    _currentDetailTicketId = ticketId;
    document.getElementById('detailTicketNum').textContent = '#' + ticketNum;
    document.getElementById('detailSummaryBadges').classList.add('hidden');
    document.getElementById('detailSummaryBadges').innerHTML = '';
    document.getElementById('detailStatsBar').classList.add('hidden');

    const pdfBtn = document.getElementById('detailPdfBtn');
    if (pdfBtn) {
        pdfBtn.href = `/admin/sla/tickets/${ticketId}/log-pdf`;
        pdfBtn.classList.remove('hidden');
    }

    document.getElementById('detailContent').innerHTML =
        '<div class="flex items-center justify-center h-32 text-gray-300"><i class="fas fa-spinner fa-spin text-3xl"></i></div>';
    document.getElementById('detailModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';

    await _loadDetailData(ticketId);
}

async function _loadDetailData(ticketId) {
    try {
        const res  = await fetch(`/api/tickets/${ticketId}/sla`, { credentials: 'include' });
        const json = await res.json();
        if (!json.success || !json.data) {
            document.getElementById('detailContent').innerHTML =
                '<p class="text-center text-gray-400 text-sm p-8">Tidak ada data SLA untuk tiket ini.</p>';
            return;
        }

        const d    = json.data;
        const resp = d.response;
        const resol = d.resolution;

        if (resp) {
            const rStat  = resp.status;
            const rColor = rStat === 'met' ? 'text-green-600' : rStat === 'breached' ? 'text-red-600' : 'text-blue-600';
            document.getElementById('statResponseVal').innerHTML =
                `<span class="${rColor}">${resp.actual_hours !== null ? fmtHHMM(resp.actual_hours) : '—'}</span>`;
            document.getElementById('statResponseStatus').textContent =
                `${STATUS_CFG[rStat]?.label || rStat} / ${resp.target_hours ?? '—'} jam target`;
            document.getElementById('statResponseStatus').className = `text-[10px] mt-0.5 ${rColor}`;
        }
        if (resol) {
            const sStat  = resol.status;
            const sColor = sStat === 'met' ? 'text-green-600' : sStat === 'breached' ? 'text-red-600' : 'text-blue-600';
            document.getElementById('statResolutionVal').innerHTML =
                `<span class="${sColor}">${resol.actual_hours !== null ? fmtHHMM(resol.actual_hours) : '—'}</span>`;
            document.getElementById('statResolutionStatus').textContent =
                `${STATUS_CFG[sStat]?.label || sStat} / ${resol.target_hours ?? '—'} jam target`;
            document.getElementById('statResolutionStatus').className = `text-[10px] mt-0.5 ${sColor}`;
        }
        document.getElementById('statWaitingVal').textContent =
            resol?.waiting_hours != null ? fmtHHMM(resol.waiting_hours) : '—';
        const ballCfg = {
            helpdesk: { label:'Helpdesk',       sub:'Waiting for agent'  },
            customer: { label:'Customer',        sub:'Waiting for customer'},
            sap:      { label:'SAP / 3rd Party', sub:'Escalated'          },
            meeting:  { label:'Meeting',         sub:'On hold - meeting'  },
        };
        const bc = ballCfg[d.ball_holder] || { label: d.ball_holder, sub: '' };
        document.getElementById('statBallHolder').textContent    = bc.label;
        document.getElementById('statBallHolderSub').textContent = bc.sub;
        document.getElementById('detailStatsBar').classList.remove('hidden');

        const events = d.events || [];
        if (!events.length) {
            document.getElementById('detailContent').innerHTML =
                '<p class="text-center text-gray-400 text-sm p-8">Belum ada event SLA tercatat.</p>';
            return;
        }

        const EVENT_CFG = {
            'email_received':       { icon:'fa-envelope',             color:'text-indigo-500', bg:'bg-indigo-50'  },
            'ticket_validated':     { icon:'fa-check-circle',         color:'text-green-500',  bg:'bg-green-50'   },
            'agent_replied':        { icon:'fa-comment-dots',         color:'text-blue-500',   bg:'bg-blue-50'    },
            'customer_replied':     { icon:'fa-reply',                color:'text-orange-500', bg:'bg-orange-50'  },
            'sla_warning':          { icon:'fa-exclamation-triangle', color:'text-yellow-500', bg:'bg-yellow-50'  },
            'sla_breached':         { icon:'fa-times-circle',         color:'text-red-500',    bg:'bg-red-50'     },
            'ticket_closed':        { icon:'fa-check-double',         color:'text-gray-500',   bg:'bg-gray-100'   },
            'meeting_started':      { icon:'fa-video',                color:'text-violet-500', bg:'bg-violet-50'  },
            'meeting_ended':        { icon:'fa-video-slash',          color:'text-gray-400',   bg:'bg-gray-50'    },
            'escalated_to_sap':     { icon:'fa-arrow-circle-up',      color:'text-purple-500', bg:'bg-purple-50'  },
            'escalated_to_support': { icon:'fa-undo',                 color:'text-teal-500',   bg:'bg-teal-50'    },
        };

        const rows = events.map(ev => {
            const cfg = EVENT_CFG[ev.event_type] || { icon:'fa-circle', color:'text-gray-400', bg:'bg-gray-100' };
            const waitBadge = ev.waiting_hours != null
                ? `<span class="text-[10px] font-semibold text-amber-700 bg-amber-50 px-2 py-0.5 rounded-full ml-1">${fmtHHMM(ev.waiting_hours)} wait</span>`
                : '';
            const respBadge = ev.response_hours != null
                ? `<span class="text-[10px] font-semibold text-blue-700 bg-blue-50 px-2 py-0.5 rounded-full ml-1">${fmtHHMM(ev.response_hours)} resp</span>`
                : '';
            const resBadge = ev.resolution_hours != null
                ? `<span class="text-[10px] font-semibold text-green-700 bg-green-50 px-2 py-0.5 rounded-full ml-1">${fmtHHMM(ev.resolution_hours)} res</span>`
                : '';
            const preview = ev.message_preview
                ? `<p class="text-[10px] text-gray-400 mt-1 italic truncate max-w-xs">"${ev.message_preview}"</p>`
                : '';
            const ball = ev.ball_after
                ? `<span class="text-[10px] text-gray-500 bg-gray-100 px-1.5 py-0.5 rounded ml-1">→ ${ev.ball_after}</span>`
                : '';

            return `<tr class="border-b border-gray-50 hover:bg-gray-50/50">
                <td class="px-4 py-3 text-[10px] text-gray-400 whitespace-nowrap">${ev.event_at ? ev.event_at.substring(0,16) : '—'}</td>
                <td class="px-4 py-3">
                    <div class="flex items-center gap-2">
                        <span class="w-6 h-6 rounded-full ${cfg.bg} flex items-center justify-center flex-shrink-0">
                            <i class="fas ${cfg.icon} text-[9px] ${cfg.color}"></i>
                        </span>
                        <div>
                            <p class="text-xs font-medium text-gray-700">${ev.label || ev.event_type}${ball}</p>
                            ${ev.sender_name ? `<p class="text-[10px] text-gray-400">${ev.sender_name}</p>` : ''}
                            ${preview}
                        </div>
                    </div>
                </td>
                <td class="px-4 py-3 text-[10px] text-gray-500">${ev.jarvis_status ? `<span class="bg-gray-100 px-2 py-0.5 rounded font-mono">${ev.jarvis_status}</span>` : '—'}</td>
                <td class="px-4 py-3">${waitBadge || '—'}</td>
                <td class="px-4 py-3">${respBadge || '—'}</td>
                <td class="px-4 py-3">${resBadge || '—'}</td>
                <td class="px-4 py-3 text-[10px] text-gray-400 max-w-[180px] truncate">${ev.notes || '—'}</td>
            </tr>`;
        }).join('');

        document.getElementById('detailContent').innerHTML = `
            <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50/80 sticky top-0">
                        <th class="text-left px-4 py-2.5 text-[10px] font-semibold text-gray-400 uppercase tracking-wider whitespace-nowrap">Waktu</th>
                        <th class="text-left px-4 py-2.5 text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Event</th>
                        <th class="text-left px-4 py-2.5 text-[10px] font-semibold text-gray-400 uppercase tracking-wider whitespace-nowrap">Status Jarvis</th>
                        <th class="text-left px-4 py-2.5 text-[10px] font-semibold text-gray-400 uppercase tracking-wider whitespace-nowrap">Waiting</th>
                        <th class="text-left px-4 py-2.5 text-[10px] font-semibold text-gray-400 uppercase tracking-wider whitespace-nowrap">Response</th>
                        <th class="text-left px-4 py-2.5 text-[10px] font-semibold text-gray-400 uppercase tracking-wider whitespace-nowrap">Resolution</th>
                        <th class="text-left px-4 py-2.5 text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Notes</th>
                    </tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>
            </div>`;

    } catch(e) {
        document.getElementById('detailContent').innerHTML =
            '<p class="text-center text-red-400 text-sm p-8">Gagal memuat data SLA.</p>';
    }
}

async function refreshDetail() {
    if (!_currentDetailTicketId) return;
    const icon = document.getElementById('detailRefreshIcon');
    icon?.classList.add('fa-spin');
    await _loadDetailData(_currentDetailTicketId);
    icon?.classList.remove('fa-spin');
}

function closeDetailModal() {
    document.getElementById('detailModal').classList.add('hidden');
    document.body.style.overflow = '';
    _currentDetailTicketId = null;
}

// ── Notes Modal ──────────────────────────────────────────────────────────────

function openNotesModal(ticketId, ticketNum) {
    const t = _allTickets.find(x => x.ticket_id === ticketId);
    const lines = t ? buildNotesLog(t) : [];

    document.getElementById('notesTicketNum').textContent = '#' + ticketNum;
    document.getElementById('notesModalContent').innerHTML = lines.length
        ? lines.map(l => `<p class="mb-2 pb-2 border-b border-gray-50 last:border-0 last:mb-0 last:pb-0">${escHtml(l)}</p>`).join('')
        : '<p class="text-center text-gray-300 py-8">Tidak ada notes.</p>';

    document.getElementById('notesModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeNotesModal() {
    document.getElementById('notesModal').classList.add('hidden');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') { closeDetailModal(); closeNotesModal(); }
});

loadReport();
setInterval(loadReport, 60000);
</script>
@endsection

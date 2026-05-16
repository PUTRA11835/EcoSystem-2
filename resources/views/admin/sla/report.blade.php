@extends('dashboard')
@section('title', 'SLA Report')
@section('page-title', 'SLA Report')
@section('page-subtitle', 'SLA ticket performance report and analytics')

@section('content')
<div class="space-y-5">

    {{-- ── FILTER ── --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Customer</label>
                <select id="filterCustomer"
                    class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300 min-w-[180px]">
                    <option value="">All Customers</option>
                    @foreach($customers as $c)
                        <option value="{{ $c->customer_id }}">
                            {{ $c->basicData->name_1 ?? $c->customer_code ?? 'Customer #'.$c->customer_id }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Month</label>
                <select id="filterMonth"
                    class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300">
                    <option value="">All Months</option>
                    @foreach(range(1,12) as $m)
                        <option value="{{ $m }}">
                            {{ DateTime::createFromFormat('!m', $m)->format('F') }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Year</label>
                <select id="filterYear"
                    class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300">
                    <option value="">All Years</option>
                    @foreach(range(date('Y'), date('Y')-2) as $y)
                        <option value="{{ $y }}">{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">SLA Status</label>
                <select id="filterStatus"
                    class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300">
                    <option value="">All Statuses</option>
                    <option value="pending">Active (Pending)</option>
                    <option value="paused">Paused</option>
                    <option value="met">Met ✅</option>
                    <option value="breached">Breached ❌</option>
                </select>
            </div>
            <button onclick="loadReport()"
                class="px-4 py-2 rounded-lg text-sm font-medium text-white transition"
                style="background-color: var(--primary-color);">
                <i class="fas fa-search mr-1.5"></i>Search
            </button>
            <button onclick="resetFilter()"
                class="px-4 py-2 rounded-lg text-sm font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 transition">
                Reset
            </button>
        </div>
    </div>

    {{-- ── SUMMARY CARDS ── --}}
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 col-span-1">
            <p class="text-xs text-gray-500 mb-1">Total Tickets</p>
            <p class="text-2xl font-bold text-gray-800" id="sumTotal">—</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
            <p class="text-xs text-gray-500 mb-1">Active</p>
            <p class="text-2xl font-bold text-blue-600" id="sumActive">—</p>
        </div>
        <div class="bg-white rounded-xl border border-green-200 shadow-sm p-4 bg-green-50">
            <p class="text-xs text-green-600 mb-1 font-medium">SLA Met ✅</p>
            <p class="text-2xl font-bold text-green-700" id="sumMet">—</p>
        </div>
        <div class="bg-white rounded-xl border border-red-200 shadow-sm p-4 bg-red-50">
            <p class="text-xs text-red-500 mb-1 font-medium">Breached ❌</p>
            <p class="text-2xl font-bold text-red-600" id="sumBreached">—</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 col-span-1 lg:col-span-1">
            <p class="text-xs text-gray-500 mb-1">Compliance Rate</p>
            <p class="text-2xl font-bold" id="sumCompliance" style="color: var(--primary-color);">—</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
            <p class="text-xs text-gray-500 mb-1">Avg Response</p>
            <p class="text-2xl font-bold text-gray-800" id="sumAvgResponse">—</p>
            <p class="text-xs text-gray-400">hrs</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
            <p class="text-xs text-gray-500 mb-1">Avg Resolution</p>
            <p class="text-2xl font-bold text-gray-800" id="sumAvgResolution">—</p>
            <p class="text-xs text-gray-400">hrs (net)</p>
        </div>
    </div>

    {{-- ── TABLE ── --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-700">
                Ticket Details
                <span id="rowCount" class="ml-2 text-xs font-normal text-gray-400"></span>
            </h2>
            <div class="flex items-center gap-2">
                <span id="lastRefreshed" class="text-xs text-gray-400"></span>
                <button id="btnRefreshReport" onclick="refreshReport()"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 transition"
                    title="Refresh latest data">
                    <i class="fas fa-sync-alt text-xs"></i> Refresh
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ticket</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Delivery</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Priority</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Scale</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Mode</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">SLA Start</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Response</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Resolution</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Waiting</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">SLA Status</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody id="reportTableBody" class="divide-y divide-gray-100">
                    <tr>
                        <td colspan="13" class="px-4 py-8 text-center text-gray-400">
                            <i class="fas fa-spinner fa-spin mr-2"></i>Loading report...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ── SLA EVENT LOG MODAL ── --}}
<div id="logModal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/40 p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-6xl flex flex-col" style="height: 90vh;">
        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 shrink-0">
            <div>
                <h3 class="text-base font-semibold text-gray-800">SLA Event Log</h3>
                <p class="text-xs text-gray-400 mt-0.5" id="logModalSubtitle"></p>
            </div>
            <div class="flex items-center gap-2">
                <button id="btnRefreshLog" onclick="refreshLogModal()"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 transition"
                    title="Refresh latest data">
                    <i class="fas fa-sync-alt text-xs"></i> Refresh
                </button>
                <a id="btnDownloadPdf" href="#" target="_blank"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-white transition"
                    style="background-color: var(--primary-color);">
                    <i class="fas fa-file-pdf text-xs"></i> Download PDF
                </a>
                <button onclick="closeLogModal()" class="text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
        </div>

        {{-- SLA summary --}}
        <div class="px-6 py-3 border-b border-gray-100 bg-gray-50 flex gap-8 text-xs shrink-0">
            <div>
                <span class="text-gray-500">Response: </span>
                <span id="logSummaryResponse" class="font-semibold">—</span>
            </div>
            <div>
                <span class="text-gray-500">Resolution: </span>
                <span id="logSummaryResolution" class="font-semibold">—</span>
            </div>
            <div>
                <span class="text-gray-500">Total Waiting: </span>
                <span id="logSummaryWaiting" class="font-semibold text-orange-600">—</span>
            </div>
        </div>

        <div class="flex-1 overflow-auto">
            <table class="w-full text-xs">
                <thead class="bg-gray-50 border-b border-gray-100 sticky top-0 z-10">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Date</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Time</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Waiting</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Response</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Resolution Time</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Event</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Jarvis Status</th>
                        <th class="px-4 py-3 text-center font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Mode</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Ball</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Message</th>
                    </tr>
                </thead>
                <tbody id="logTableBody" class="divide-y divide-gray-100">
                </tbody>
            </table>
        </div>

        <div class="px-6 py-3 border-t border-gray-100 flex justify-end shrink-0">
            <button onclick="closeLogModal()"
                class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900 transition">Close</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const PRIORITY_COLORS = {
    'Very High': 'bg-red-50 text-red-700 border-red-200',
    'High':      'bg-orange-50 text-orange-700 border-orange-200',
    'Medium':    'bg-yellow-50 text-yellow-700 border-yellow-200',
    'Low':       'bg-blue-50 text-blue-700 border-blue-200',
};
const TICKET_TYPE_COLORS = {
    'Incident':        'bg-red-50 text-red-600 border-red-200',
    'Service Request': 'bg-purple-50 text-purple-600 border-purple-200',
    'Change Request':  'bg-indigo-50 text-indigo-600 border-indigo-200',
};
const DELIVERY_TYPE_COLORS = {
    'ATS': 'bg-cyan-50 text-cyan-700 border-cyan-200',
    'AMS': 'bg-teal-50 text-teal-700 border-teal-200',
    'MO':  'bg-emerald-50 text-emerald-700 border-emerald-200',
};
const SLA_MODE_CONFIG = {
    'full':          { cls: 'bg-blue-50 text-blue-700 border-blue-200',   label: 'Full SLA' },
    'response_only': { cls: 'bg-amber-50 text-amber-700 border-amber-200', label: 'Response Only' },
};
const STATUS_CONFIG = {
    'pending_validation': { cls: 'bg-gray-100 text-gray-600',       label: 'Pending Validation', icon: 'fa-hourglass-start' },
    'pending':            { cls: 'bg-blue-50 text-blue-700',         label: 'Active',             icon: 'fa-play' },
    'paused':             { cls: 'bg-yellow-50 text-yellow-700',     label: 'Paused ⏸️',           icon: 'fa-pause' },
    'met':                { cls: 'bg-green-50 text-green-700',       label: 'Met ✅',              icon: 'fa-check-circle' },
    'breached':           { cls: 'bg-red-50 text-red-600',           label: 'Breached ❌',         icon: 'fa-times-circle' },
};
const EVENT_ICONS = {
    'email_received':        { icon: 'fa-envelope',      color: 'text-blue-500' },
    'ticket_validated':      { icon: 'fa-check',         color: 'text-green-500' },
    'agent_replied':         { icon: 'fa-paper-plane',   color: 'text-purple-500' },
    'resolution_sent':       { icon: 'fa-paper-plane',   color: 'text-purple-500' },
    'customer_replied':      { icon: 'fa-reply',         color: 'text-orange-500' },
    'solution_started':      { icon: 'fa-play-circle',   color: 'text-indigo-600' },
    'meeting_scheduled':     { icon: 'fa-calendar-alt',  color: 'text-teal-600' },
    'escalated_to_sap':      { icon: 'fa-share',         color: 'text-indigo-500' },
    'escalated_to_support':  { icon: 'fa-share-alt',     color: 'text-pink-500' },
    'sla_warning':           { icon: 'fa-exclamation-triangle', color: 'text-yellow-500' },
    'sla_breached':          { icon: 'fa-times-circle',  color: 'text-red-500' },
    'ticket_closed':         { icon: 'fa-lock',          color: 'text-gray-600' },
};
const EVENT_LABELS = {
    'email_received':        'Email / Request Received',
    'ticket_validated':      'Ticket Created',
    'agent_replied':         'Helpdesk Reply',
    'resolution_sent':       'Helpdesk Reply',
    'customer_replied':      'Customer Reply',
    'solution_started':      'Start Solution',
    'meeting_scheduled':     'Meeting Scheduled',
    'escalated_to_sap':      'Escalated to SAP',
    'escalated_to_support':  'Escalated to Support',
    'sla_warning':           'SLA Warning',
    'sla_breached':          'SLA Breach',
    'ticket_closed':         'Ticket Closed',
};

// Mode badge: Start / Run / Stop
const MODE_STOP_STATUSES = new Set(['author action', 'proposed solution', 'sent in to SAP', 'wait to close', 'closed']);
function modeBadge(eventType, jarvisStatus, extra) {
    if (eventType === 'solution_started') {
        return `<span class="px-1.5 py-0.5 rounded text-xs font-semibold border bg-indigo-50 text-indigo-700 border-indigo-200">Start</span>`;
    }
    if (eventType === 'meeting_scheduled') {
        if (extra?.meeting_ended) {
            return `<span class="px-1.5 py-0.5 rounded text-xs font-semibold border bg-teal-50 text-teal-700 border-teal-200">Stop</span>`;
        }
        return `<span class="px-1.5 py-0.5 rounded text-xs font-semibold border bg-orange-50 text-orange-600 border-orange-200">Run</span>`;
    }
    if (eventType === 'ticket_closed') {
        return `<span class="px-1.5 py-0.5 rounded text-xs font-semibold border bg-gray-100 text-gray-600 border-gray-200">Stop</span>`;
    }
    if (eventType === 'agent_replied') {
        if (MODE_STOP_STATUSES.has(jarvisStatus)) {
            return `<span class="px-1.5 py-0.5 rounded text-xs font-semibold border bg-amber-50 text-amber-700 border-amber-200">Stop</span>`;
        }
        return `<span class="px-1.5 py-0.5 rounded text-xs font-semibold border bg-green-50 text-green-700 border-green-200">Run</span>`;
    }
    if (eventType === 'customer_replied') {
        return `<span class="px-1.5 py-0.5 rounded text-xs font-semibold border bg-green-50 text-green-700 border-green-200">Run</span>`;
    }
    return '<span class="text-gray-300">—</span>';
}

function fmtHours(h) {
    if (h === null || h === undefined) return '—';
    return parseFloat(h).toFixed(2) + ' hrs';
}

const JARVIS_BADGE = {
    'in process':         'bg-blue-50 text-blue-700 border-blue-200',
    'sent it to support': 'bg-cyan-50 text-cyan-700 border-cyan-200',
    'author action':      'bg-yellow-50 text-yellow-700 border-yellow-200',
    'proposed solution':  'bg-orange-50 text-orange-700 border-orange-200',
    'sent in to SAP':     'bg-indigo-50 text-indigo-700 border-indigo-200',
    'wait to close':      'bg-purple-50 text-purple-700 border-purple-200',
    'closed':             'bg-gray-100 text-gray-600 border-gray-200',
};
const BALL_BADGE = {
    'helpdesk': { cls: 'bg-green-50 text-green-700 border-green-200',    label: '▶ Helpdesk' },
    'customer': { cls: 'bg-yellow-50 text-yellow-700 border-yellow-200', label: '⏸ Customer' },
    'sap':      { cls: 'bg-indigo-50 text-indigo-700 border-indigo-200', label: '⏸ SAP' },
};

function jarvisBadge(status) {
    if (!status) return '<span class="text-gray-300">—</span>';
    const cls = JARVIS_BADGE[status] ?? 'bg-gray-50 text-gray-600 border-gray-200';
    return `<span class="px-1.5 py-0.5 rounded text-xs font-medium border ${cls}">${status}</span>`;
}

function ballBadge(holder) {
    if (!holder) return '<span class="text-gray-300">—</span>';
    const cfg = BALL_BADGE[holder] ?? { cls: 'bg-gray-100 text-gray-600 border-gray-200', label: holder };
    return `<span class="px-1.5 py-0.5 rounded text-xs font-medium border ${cfg.cls}">${cfg.label}</span>`;
}

function fmtDuration(h) {
    if (h === null || h === undefined) return '—';
    const totalMin = Math.round(parseFloat(h) * 60);
    const hrs = Math.floor(totalMin / 60);
    const min = totalMin % 60;
    const minStr = min > 0 ? `${min} min` : '';
    const hrStr  = hrs > 0 ? `${hrs} h` : '';
    const label  = [hrStr, minStr].filter(Boolean).join(' ') || '0 min';
    return `<span class="font-mono">${parseFloat(h).toFixed(2)} h</span><span class="text-gray-400 text-xs ml-1">(${label})</span>`;
}

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

let _autoRefreshTimer = null;

function startAutoRefresh() {
    clearInterval(_autoRefreshTimer);
    _autoRefreshTimer = setInterval(() => silentRefreshReport(), 60000);
}

async function silentRefreshReport() {
    const params = new URLSearchParams({
        customer_id:        document.getElementById('filterCustomer').value,
        month:              document.getElementById('filterMonth').value,
        year:               document.getElementById('filterYear').value,
        resolution_status:  document.getElementById('filterStatus').value,
    });
    try {
        const res  = await fetch(`/api/admin/sla/report?${params}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const json = await res.json();
        if (!json.success) return;
        const s = json.summary;
        document.getElementById('sumTotal').textContent         = s.total ?? '—';
        document.getElementById('sumActive').textContent        = s.active ?? '—';
        document.getElementById('sumMet').textContent           = s.met ?? '—';
        document.getElementById('sumBreached').textContent      = s.breached ?? '—';
        document.getElementById('sumCompliance').textContent    = s.compliance_rate !== null ? s.compliance_rate + '%' : '—';
        document.getElementById('sumAvgResponse').textContent   = s.avg_response ?? '—';
        document.getElementById('sumAvgResolution').textContent = s.avg_resolution ?? '—';
        renderReportTable(json.data);
        updateLastRefreshed();
    } catch (_) {}
}

async function refreshReport() {
    const btn = document.getElementById('btnRefreshReport');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-sync-alt fa-spin text-xs"></i> Loading...';
    await loadReport();
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-sync-alt text-xs"></i> Refresh';
}

function updateLastRefreshed() {
    const now = new Date();
    const hh  = String(now.getHours()).padStart(2, '0');
    const mm  = String(now.getMinutes()).padStart(2, '0');
    const ss  = String(now.getSeconds()).padStart(2, '0');
    document.getElementById('lastRefreshed').textContent = `Last updated: ${hh}:${mm}:${ss}`;
}

async function loadReport() {
    const params = new URLSearchParams({
        customer_id:        document.getElementById('filterCustomer').value,
        month:              document.getElementById('filterMonth').value,
        year:               document.getElementById('filterYear').value,
        resolution_status:  document.getElementById('filterStatus').value,
    });

    document.getElementById('reportTableBody').innerHTML = `<tr><td colspan="13" class="px-4 py-8 text-center text-gray-400">
        <i class="fas fa-spinner fa-spin mr-2"></i>Loading report...
    </td></tr>`;

    try {
        const res  = await fetch(`/api/admin/sla/report?${params}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const json = await res.json();
        if (!json.success) throw new Error(json.message);

        const s = json.summary;
        document.getElementById('sumTotal').textContent       = s.total ?? '—';
        document.getElementById('sumActive').textContent      = s.active ?? '—';
        document.getElementById('sumMet').textContent         = s.met ?? '—';
        document.getElementById('sumBreached').textContent    = s.breached ?? '—';
        document.getElementById('sumCompliance').textContent  = s.compliance_rate !== null ? s.compliance_rate + '%' : '—';
        document.getElementById('sumAvgResponse').textContent = s.avg_response ?? '—';
        document.getElementById('sumAvgResolution').textContent = s.avg_resolution ?? '—';

        renderReportTable(json.data);
        updateLastRefreshed();
    } catch (e) {
        showToast('Failed to load report: ' + e.message, 'error');
        document.getElementById('reportTableBody').innerHTML = `<tr><td colspan="13" class="px-4 py-8 text-center text-red-400">
            Failed to load data
        </td></tr>`;
    }
}

function renderReportTable(data) {
    const tbody = document.getElementById('reportTableBody');
    document.getElementById('rowCount').textContent = `(${data.length} tickets)`;

    if (!data.length) {
        tbody.innerHTML = `<tr><td colspan="13" class="px-4 py-8 text-center text-gray-400">
            <i class="fas fa-inbox mr-2"></i>No data for the selected filters
        </td></tr>`;
        return;
    }

    tbody.innerHTML = data.map(r => {
        const pCls   = PRIORITY_COLORS[r.priority] ?? 'bg-gray-50 text-gray-600 border-gray-200';
        const tCls   = TICKET_TYPE_COLORS[r.ticket_type] ?? 'bg-gray-50 text-gray-600 border-gray-200';
        const dTypes = (r.delivery_type && r.delivery_type !== '-') ? r.delivery_type.split(', ') : [];
        const sCfg   = STATUS_CONFIG[r.resolution_status] ?? { cls: 'bg-gray-100 text-gray-600', label: r.resolution_status };
        const respBadge = r.response_status === 'met'
            ? `<span class="text-green-600 font-semibold">${r.response_actual ?? '—'}</span>/<span class="text-gray-400 text-xs">${r.response_target ?? '—'}</span>`
            : r.response_status === 'breached'
            ? `<span class="text-red-500 font-semibold">${r.response_actual ?? '—'}</span>/<span class="text-gray-400 text-xs">${r.response_target ?? '—'}</span>`
            : `<span class="text-gray-500">${r.response_actual ?? '—'}</span>/<span class="text-gray-400 text-xs">${r.response_target ?? '—'}</span>`;
        const isResponseOnly = r.sla_mode === 'response_only';
        const modeCfg = SLA_MODE_CONFIG[r.sla_mode] ?? SLA_MODE_CONFIG['full'];
        const resBadge = isResponseOnly
            ? `<span class="text-xs text-gray-400 italic">N/A</span>`
            : r.resolution_status === 'met'
            ? `<span class="text-green-600 font-semibold">${r.resolution_actual ?? '—'}</span>/<span class="text-gray-400 text-xs">${r.resolution_target ?? '—'}</span>`
            : r.resolution_status === 'breached'
            ? `<span class="text-red-500 font-semibold">${r.resolution_actual ?? '—'}</span>/<span class="text-gray-400 text-xs">${r.resolution_target ?? '—'}</span>`
            : `<span class="text-gray-500">—</span>/<span class="text-gray-400 text-xs">${r.resolution_target ?? '—'}</span>`;
        const deliveryBadges = dTypes.length
            ? dTypes.map(d => {
                const dc = DELIVERY_TYPE_COLORS[d.trim()] ?? 'bg-gray-50 text-gray-600 border-gray-200';
                return `<span class="px-1.5 py-0.5 rounded text-xs font-medium border ${dc}">${d.trim()}</span>`;
              }).join(' ')
            : '<span class="text-gray-300">—</span>';

        return `<tr class="hover:bg-gray-50 transition">
            <td class="px-4 py-3">
                <a href="/ticket/${r.ticket_id}" class="font-medium text-blue-600 hover:underline" target="_blank">
                    ${r.ticket_number}
                </a>
            </td>
            <td class="px-4 py-3 text-sm text-gray-700">${r.customer_name}</td>
            <td class="px-4 py-3 text-center">
                <span class="px-2 py-0.5 rounded-full text-xs font-medium border ${tCls}">${r.ticket_type ?? '—'}</span>
            </td>
            <td class="px-4 py-3 text-center">${deliveryBadges}</td>
            <td class="px-4 py-3 text-center">
                <span class="px-2 py-0.5 rounded-full text-xs font-medium border ${pCls}">${r.priority ?? '—'}</span>
            </td>
            <td class="px-4 py-3 text-center text-xs text-gray-600">${r.scale ?? '—'}</td>
            <td class="px-4 py-3 text-center">
                <span class="px-2 py-0.5 rounded-full text-xs font-medium border ${modeCfg.cls}">${modeCfg.label}</span>
            </td>
            <td class="px-4 py-3 text-center text-xs text-gray-600">${r.sla_start_at ?? '—'}</td>
            <td class="px-4 py-3 text-center text-xs">${respBadge}</td>
            <td class="px-4 py-3 text-center text-xs">${resBadge}</td>
            <td class="px-4 py-3 text-center text-xs text-orange-600 font-medium">${r.total_waiting > 0 ? r.total_waiting + ' hrs' : '—'}</td>
            <td class="px-4 py-3 text-center">
                <span class="px-2.5 py-1 rounded-full text-xs font-medium ${sCfg.cls}">
                    ${sCfg.label}
                </span>
            </td>
            <td class="px-4 py-3 text-center">
                <div class="flex items-center justify-center gap-1.5">
                    <button onclick="openLogModal(${r.ticket_id}, '${r.ticket_number}')"
                        class="w-8 h-8 rounded-lg bg-gray-100 text-gray-600 hover:bg-gray-200 flex items-center justify-center transition" title="View SLA Log">
                        <i class="fas fa-list-ul text-xs"></i>
                    </button>
                    <a href="/admin/sla/tickets/${r.ticket_id}/pdf" target="_blank"
                        class="w-8 h-8 rounded-lg flex items-center justify-center transition text-white"
                        style="background-color: var(--primary-color); opacity: 0.9;"
                        title="Download PDF">
                        <i class="fas fa-file-pdf text-xs"></i>
                    </a>
                </div>
            </td>
        </tr>`;
    }).join('');
}

// ── LOG MODAL ──────────────────────────────────────────────────────────────
let _currentLogTicketId = null;

async function refreshLogModal() {
    if (!_currentLogTicketId) return;
    const btn = document.getElementById('btnRefreshLog');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-sync-alt fa-spin text-xs"></i> Loading...';
    await loadLogEvents(_currentLogTicketId);
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-sync-alt text-xs"></i> Refresh';
}

async function loadLogEvents(ticketId) {
    document.getElementById('logTableBody').innerHTML = `<tr><td colspan="10" class="px-4 py-6 text-center text-gray-400">
        <i class="fas fa-spinner fa-spin mr-2"></i>Loading log...
    </td></tr>`;
    document.getElementById('logSummaryResponse').textContent   = '—';
    document.getElementById('logSummaryResolution').textContent = '—';
    document.getElementById('logSummaryWaiting').textContent    = '—';

    try {
        const res  = await fetch(`/api/tickets/${ticketId}/sla`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const json = await res.json();
        if (!json.success) throw new Error(json.message ?? 'No SLA data found');

        const s = json.sla_status;
        const isRespOnly = json.sla_mode === 'response_only';
        document.getElementById('logSummaryResponse').innerHTML   = renderStatusBadge(s.response);
        document.getElementById('logSummaryResolution').innerHTML = isRespOnly
            ? '<span class="text-xs text-gray-400 italic">Not applicable (Response Only)</span>'
            : renderStatusBadge(s.resolution);
        document.getElementById('logSummaryWaiting').textContent  = isRespOnly
            ? 'N/A'
            : (json.summary?.total_waiting ? json.summary.total_waiting + ' hrs' : '0 hrs');

        const events = json.events ?? [];
        if (!events.length) {
            document.getElementById('logTableBody').innerHTML = `<tr><td colspan="10" class="px-4 py-6 text-center text-gray-400">No events yet</td></tr>`;
            return;
        }

        // Derive ball_holder state by walking events in order
        let currentBall = 'helpdesk';
        let lastAgentJarvisStatus = null;

        document.getElementById('logTableBody').innerHTML = events.map(e => {
            const ei    = EVENT_ICONS[e.event_type] ?? { icon: 'fa-dot-circle', color: 'text-gray-400' };
            const label = EVENT_LABELS[e.event_type] ?? e.event_type;
            const [date, time] = (e.event_at ?? '').split(' ');

            // Advance ball state (mirror PHP state machine)
            if (e.event_type === 'customer_replied') {
                currentBall = 'helpdesk';
            } else if (e.event_type === 'agent_replied') {
                lastAgentJarvisStatus = e.jarvis_status;
                currentBall = (e.jarvis_status === 'sent in to SAP') ? 'sap' : 'customer';
            } else if (e.event_type === 'ticket_closed') {
                currentBall = 'helpdesk';
            } else if (e.event_type === 'meeting_scheduled') {
                if (e.meeting_ended) currentBall = 'helpdesk';
                // ongoing meeting: ball tetap di current state (customer/sap)
            }
            // solution_started does not change ball holder

            let msgCell = '<span class="text-gray-300">—</span>';
            if (e.message_preview) {
                const isAtt  = e.message_preview === 'attachment';
                const hasAtt = !isAtt && e.message_preview.includes('[+attachment]');
                const text   = isAtt ? '' : e.message_preview.replace(' [+attachment]', '');
                msgCell = isAtt
                    ? `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-blue-50 text-blue-600 text-xs border border-blue-200"><i class="fas fa-paperclip text-xs"></i> attachment</span>`
                    : `<span class="text-gray-600 text-xs leading-snug">${escHtml(text)}</span>`
                      + (hasAtt ? ` <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded bg-blue-50 text-blue-500 text-xs border border-blue-100 ml-1"><i class="fas fa-paperclip text-xs"></i></span>` : '');
            }

            const showBall = ['agent_replied', 'customer_replied', 'ticket_closed'].includes(e.event_type)
                || (e.event_type === 'meeting_scheduled' && e.meeting_ended);
            const isSolutionRow = e.event_type === 'solution_started';
            const isMeetingRow  = e.event_type === 'meeting_scheduled';

            const rowCls = isSolutionRow
                ? 'bg-indigo-50 border-l-4 border-indigo-400'
                : isMeetingRow
                    ? 'bg-teal-50 border-l-4 border-teal-400'
                    : 'hover:bg-gray-50';

            return `<tr class="${rowCls}">
                <td class="px-4 py-2.5 text-gray-600 whitespace-nowrap">${date ?? '—'}</td>
                <td class="px-4 py-2.5 text-gray-600 whitespace-nowrap">${time ?? '—'}</td>
                <td class="px-4 py-2.5 text-right ${e.waiting_hours ? 'text-orange-600 font-semibold' : 'text-gray-300'}">
                    ${e.waiting_hours != null ? fmtDuration(e.waiting_hours) : '—'}
                </td>
                <td class="px-4 py-2.5 text-right ${e.response_hours ? 'text-green-600 font-semibold' : 'text-gray-300'}">
                    ${e.response_hours != null ? fmtDuration(e.response_hours) : '—'}
                </td>
                <td class="px-4 py-2.5 text-right ${e.resolution_hours ? 'text-blue-500 font-semibold' : 'text-gray-300'}">
                    ${e.resolution_hours != null ? fmtDuration(e.resolution_hours) : '—'}
                </td>
                <td class="px-4 py-2.5 whitespace-nowrap">
                    <span class="inline-flex items-center gap-1.5">
                        <i class="fas ${ei.icon} text-xs ${ei.color}"></i>
                        <span class="text-gray-700 font-medium${isSolutionRow ? ' text-indigo-700' : isMeetingRow ? ' text-teal-700' : ''}">${label}</span>
                    </span>
                </td>
                <td class="px-4 py-2.5 whitespace-nowrap">${
                    e.event_type === 'customer_replied'
                        ? jarvisBadge('in process')
                        : jarvisBadge(e.jarvis_status)
                }</td>
                <td class="px-4 py-2.5 text-center whitespace-nowrap">${modeBadge(e.event_type, e.jarvis_status, e)}</td>
                <td class="px-4 py-2.5 whitespace-nowrap">${showBall ? ballBadge(currentBall) : '<span class="text-gray-300">—</span>'}</td>
                <td class="px-4 py-2.5 max-w-[220px]">${msgCell}</td>
            </tr>`;
        }).join('');

    } catch (err) {
        document.getElementById('logTableBody').innerHTML = `<tr><td colspan="10" class="px-4 py-6 text-center text-red-400">${err.message}</td></tr>`;
    }
}

async function openLogModal(ticketId, ticketNumber) {
    _currentLogTicketId = ticketId;
    document.getElementById('logModal').classList.remove('hidden');
    document.getElementById('logModalSubtitle').textContent = ticketNumber;
    document.getElementById('btnDownloadPdf').href = `/admin/sla/tickets/${ticketId}/pdf`;
    await loadLogEvents(ticketId);
}

function renderStatusBadge(s) {
    if (!s) return '—';
    const map = { met: 'text-green-600', breached: 'text-red-500', pending: 'text-blue-600' };
    const cls = map[s.status] ?? 'text-gray-600';
    return `<span class="${cls} font-semibold">${s.hours ?? '—'} hrs</span>
            <span class="text-gray-400 text-xs"> / target ${s.target ?? '—'} hrs</span>`;
}

function closeLogModal() { document.getElementById('logModal').classList.add('hidden'); }

function resetFilter() {
    document.getElementById('filterCustomer').value = '';
    document.getElementById('filterMonth').value    = '';
    document.getElementById('filterYear').value     = '';
    document.getElementById('filterStatus').value   = '';
    loadReport();
}

document.getElementById('logModal').addEventListener('click', function(e) {
    if (e.target === this) this.classList.add('hidden');
});

document.addEventListener('DOMContentLoaded', () => { loadReport(); startAutoRefresh(); });
</script>
@endpush

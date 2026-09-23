@extends('dashboard')

@section('title', 'Security Center')
@section('page-title', 'Security Center')
@section('page-subtitle', 'Brute-force lockouts, attack-pattern probes, and other suspicious activity')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">
<style>
    input[type="date"]::-webkit-calendar-picker-indicator {
        opacity: 0;
        position: absolute;
        right: 0;
        top: 0;
        width: 100%;
        height: 100%;
        cursor: pointer;
    }
    input[type="date"] { position: relative; }
    .hdr-filter-panel { z-index: 9999; }
    #ddEventType .custom-dd-label, #ddSeverity .custom-dd-label, #ddStatus .custom-dd-label { font-size: 0.75rem; font-weight: 600; }
</style>

<div class="space-y-6">

    <!-- Stats Row -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4" id="statsRow">
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <p class="text-xs text-gray-500 mb-1">Open Events</p>
            <p class="text-2xl font-bold text-gray-900" id="statOpen">-</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <p class="text-xs text-gray-500 mb-1">Critical / High (open)</p>
            <p class="text-2xl font-bold text-red-600" id="statCriticalHigh">-</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <p class="text-xs text-gray-500 mb-1">Locked Accounts</p>
            <p class="text-2xl font-bold text-orange-600" id="statLockedAccounts">-</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <p class="text-xs text-gray-500 mb-1">Blocked IPs</p>
            <p class="text-2xl font-bold text-amber-600" id="statBlockedIps">-</p>
        </div>
    </div>

    <!-- Trend + Top Offenders -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 lg:col-span-1">
            <h3 class="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-3">Events per day (7d)</h3>
            <div id="trendChart"><p class="text-xs text-gray-400">Loading…</p></div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
            <h3 class="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-3">Top Attacking IPs (7d)</h3>
            <ul id="topIpsList" class="space-y-1.5"><li class="text-xs text-gray-400">Loading…</li></ul>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
            <h3 class="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-3">Most Targeted Accounts (7d)</h3>
            <ul id="topTargetsList" class="space-y-1.5"><li class="text-xs text-gray-400">Loading…</li></ul>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <div class="flex items-center gap-2.5">
                <h3 class="text-sm font-semibold text-gray-800">Security Events</h3>
                <button id="btnResetFilters" onclick="resetFilters()"
                    class="hidden inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium text-red-700 bg-red-50 border border-red-100 rounded-lg hover:bg-red-100 transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                    <span>Clear filters</span>
                </button>
                <span class="text-xs text-gray-400" id="lastUpdated"></span>
            </div>
            <div class="flex items-center gap-3">
                <label class="flex items-center gap-1.5 text-xs text-gray-500 cursor-pointer select-none">
                    <input type="checkbox" id="autoRefreshToggle" checked class="rounded border-gray-300 text-red-700 focus:ring-red-400">
                    Auto-refresh
                </label>
                <button type="button" onclick="exportCsv()"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                    Export CSV
                </button>
                <div class="custom-dd relative" style="min-width:100px" data-onchange="onPerPageChange">
                    <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-2.5 py-1.5 bg-white border border-gray-300 rounded-lg text-xs hover:border-gray-400 transition-all text-left">
                        <span class="custom-dd-label text-gray-700">25 / page</span>
                        <svg class="custom-dd-arrow w-3.5 h-3.5 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <input type="hidden" id="perPageSelect" value="25">
                    <div class="custom-dd-panel hidden absolute top-full right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5" style="min-width:100px">
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-xs text-gray-600 hover:bg-gray-50 transition-colors" data-value="25">25 / page</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-xs text-gray-600 hover:bg-gray-50 transition-colors" data-value="50">50 / page</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-xs text-gray-600 hover:bg-gray-50 transition-colors" data-value="100">100 / page</button>
                    </div>
                </div>
                <span class="text-xs text-gray-500" id="tableInfo">Loading…</span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-left">
                        <th class="px-4 py-3 text-xs font-semibold text-gray-600 whitespace-nowrap">#</th>

                        {{-- EVENT: keyword search popover --}}
                        <th class="p-0 whitespace-nowrap">
                            <button type="button" id="searchFilterBtn" onclick="toggleHeaderFilter('searchFilterPanel', this)"
                                class="hdr-filter-btn w-full flex items-center gap-1.5 px-4 py-3 text-left hover:bg-gray-100 transition-colors">
                                <span class="text-xs font-semibold text-gray-600">Event</span>
                                <svg id="searchFilterIcon" class="w-3.5 h-3.5 text-gray-300 transition-colors ml-auto" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 011 1v1.586a1 1 0 01-.293.707l-4.121 4.121A1 1 0 0012 12.121V15.5l-4 1.5v-4.879a1 1 0 00-.293-.707L3.586 7.293A1 1 0 013.293 6.586L3 5z" clip-rule="evenodd" />
                                </svg>
                            </button>
                            <div id="searchFilterPanel" class="hdr-filter-panel hidden bg-white rounded-xl shadow-2xl border border-gray-100 p-3" style="min-width:260px;">
                                <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Search</label>
                                <input type="text" id="filterSearch" placeholder="Title, description, actor, IP…"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm font-normal text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
                                <div class="flex justify-end mt-3">
                                    <button type="button" onclick="clearSearchFilter()" class="px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50">Clear</button>
                                </div>
                            </div>
                        </th>

                        {{-- EVENT TYPE dropdown --}}
                        <th class="p-0 whitespace-nowrap">
                            <div class="custom-dd relative w-full" id="ddEventType" data-fixed="true" data-onchange="applyFilters">
                                <button type="button" class="custom-dd-btn w-full flex items-center gap-1.5 px-4 py-3 text-left hover:bg-gray-100 transition-colors">
                                    <span class="custom-dd-label text-xs font-semibold text-gray-600">Type</span>
                                    <svg class="custom-dd-arrow w-3.5 h-3.5 text-gray-400 transition-transform duration-200 shrink-0 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <input type="hidden" id="filterEventType" value="">
                                <div class="custom-dd-panel hidden absolute top-full left-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5" style="min-width:220px;" id="eventTypeOptions">
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">All Types</button>
                                </div>
                            </div>
                        </th>

                        {{-- SEVERITY dropdown --}}
                        <th class="p-0 whitespace-nowrap">
                            <div class="custom-dd relative w-full" id="ddSeverity" data-fixed="true" data-onchange="applyFilters">
                                <button type="button" class="custom-dd-btn w-full flex items-center gap-1.5 px-4 py-3 text-left hover:bg-gray-100 transition-colors">
                                    <span class="custom-dd-label text-xs font-semibold text-gray-600">Severity</span>
                                    <svg class="custom-dd-arrow w-3.5 h-3.5 text-gray-400 transition-transform duration-200 shrink-0 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <input type="hidden" id="filterSeverity" value="">
                                <div class="custom-dd-panel hidden absolute top-full left-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5" style="min-width:140px;">
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">All</button>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="critical">Critical</button>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="high">High</button>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="medium">Medium</button>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="low">Low</button>
                                </div>
                            </div>
                        </th>

                        {{-- STATUS dropdown --}}
                        <th class="p-0 whitespace-nowrap">
                            <div class="custom-dd relative w-full" id="ddStatus" data-fixed="true" data-onchange="applyFilters">
                                <button type="button" class="custom-dd-btn w-full flex items-center gap-1.5 px-4 py-3 text-left hover:bg-gray-100 transition-colors">
                                    <span class="custom-dd-label text-xs font-semibold text-gray-600">Status</span>
                                    <svg class="custom-dd-arrow w-3.5 h-3.5 text-gray-400 transition-transform duration-200 shrink-0 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <input type="hidden" id="filterStatus" value="open">
                                <div class="custom-dd-panel hidden absolute top-full left-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5" style="min-width:140px;">
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">All</button>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="open">Open</button>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="resolved">Resolved</button>
                                </div>
                            </div>
                        </th>

                        <th class="px-4 py-3 text-xs font-semibold text-gray-600 whitespace-nowrap">Target</th>

                        {{-- TIME: date range popover --}}
                        <th class="p-0 whitespace-nowrap">
                            <button type="button" id="timeFilterBtn" onclick="toggleHeaderFilter('timeFilterPanel', this)"
                                class="hdr-filter-btn w-full flex items-center gap-1.5 px-4 py-3 text-left hover:bg-gray-100 transition-colors">
                                <span class="text-xs font-semibold text-gray-600">Time (WIB)</span>
                                <svg id="timeFilterIcon" class="w-3.5 h-3.5 text-gray-300 transition-colors ml-auto" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 011 1v1.586a1 1 0 01-.293.707l-4.121 4.121A1 1 0 0012 12.121V15.5l-4 1.5v-4.879a1 1 0 00-.293-.707L3.586 7.293A1 1 0 013.293 6.586L3 5z" clip-rule="evenodd" />
                                </svg>
                            </button>
                            <div id="timeFilterPanel" class="hdr-filter-panel hidden bg-white rounded-xl shadow-2xl border border-gray-100 p-3" style="min-width:240px;">
                                <div class="space-y-2">
                                    <div>
                                        <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Date From</label>
                                        <input type="date" id="filterDateFrom"
                                            class="w-full px-3 py-1.5 border border-gray-300 rounded-md text-sm font-normal text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Date To</label>
                                        <input type="date" id="filterDateTo"
                                            class="w-full px-3 py-1.5 border border-gray-300 rounded-md text-sm font-normal text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
                                    </div>
                                    <p id="dateFilterError" class="hidden text-xs text-red-500">"Date To" must be on/after "Date From".</p>
                                </div>
                                <div class="flex justify-end gap-2 mt-3">
                                    <button type="button" onclick="clearTimeFilter()" class="px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50">Clear</button>
                                    <button type="button" onclick="applyTimeFilter()" class="px-3 py-1.5 text-xs text-white bg-red-700 hover:bg-red-800 rounded-md">Apply</button>
                                </div>
                            </div>
                        </th>

                        <th class="px-4 py-3 text-xs font-semibold text-gray-600 whitespace-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <tr>
                        <td colspan="8" class="px-4 py-12 text-center text-sm text-gray-400">Loading…</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="flex items-center justify-between px-5 py-3 border-t border-gray-100 bg-gray-50" id="paginationRow"></div>
    </div>

    <!-- Blocked IPs -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-800">Blocked IPs</h3>
            <button type="button" onclick="promptBlockIp()" class="px-3 py-1.5 text-xs font-medium text-white bg-gray-800 hover:bg-gray-900 rounded-lg transition-all">+ Block IP</button>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-left">
                        <th class="px-4 py-3 text-xs font-semibold text-gray-600 whitespace-nowrap">IP Address</th>
                        <th class="px-4 py-3 text-xs font-semibold text-gray-600 whitespace-nowrap">Reason</th>
                        <th class="px-4 py-3 text-xs font-semibold text-gray-600 whitespace-nowrap">Source</th>
                        <th class="px-4 py-3 text-xs font-semibold text-gray-600 whitespace-nowrap">Expires</th>
                        <th class="px-4 py-3 text-xs font-semibold text-gray-600 whitespace-nowrap">Action</th>
                    </tr>
                </thead>
                <tbody id="blockedIpsBody">
                    <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-gray-400">Loading…</td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div>

@php
    $customDdPath = public_path('js/custom-dropdown.js');
    $customDdVer  = file_exists($customDdPath) ? filemtime($customDdPath) : time();
@endphp
<script src="/js/custom-dropdown.js?v={{ $customDdVer }}" onerror="window.__customDdLoadFailed=true;console.error('custom-dropdown.js gagal dimuat');"></script>
<script>
let currentPage    = 1;
let currentPerPage = 25;
let currentFilters = { status: 'open' };

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]').content;
}

async function postJson(url, body) {
    const res = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
        body: JSON.stringify(body || {}),
    });
    return res.json();
}

// ─── Load stats ───────────────────────────────────────────────────────────────
async function loadStats() {
    try {
        const res  = await fetch('/api/admin/security-events?per_page=1&status=', { credentials: 'same-origin' });
        const data = await res.json();
        if (!data.success) return;

        document.getElementById('statOpen').textContent          = (data.stats?.open ?? 0).toLocaleString('id-ID');
        document.getElementById('statCriticalHigh').textContent  = (data.stats?.critical_high ?? 0).toLocaleString('id-ID');
        document.getElementById('statLockedAccounts').textContent = (data.stats?.locked_accounts ?? 0).toLocaleString('id-ID');
        document.getElementById('statBlockedIps').textContent    = (data.stats?.blocked_ips ?? 0).toLocaleString('id-ID');
    } catch (e) {
        console.error('loadStats error:', e);
    }
}

async function loadEventTypes() {
    try {
        const res  = await fetch('/api/admin/security-events/event-types', { credentials: 'same-origin' });
        const data = await res.json();
        if (!data.success) return;

        const panel = document.getElementById('eventTypeOptions');
        data.data.forEach(type => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors';
            btn.dataset.value = type;
            btn.textContent = labelizeEventType(type);
            panel.appendChild(btn);
        });
        if (typeof initCustomDropdowns === 'function') initCustomDropdowns();
    } catch (e) {
        console.error('loadEventTypes error:', e);
    }
}

function labelizeEventType(type) {
    return String(type).replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}

// ─── "What should I do?" guidance per event type ──────────────────────────────
const RECOMMENDED_ACTIONS = {
    brute_force_account_lockout: 'Account is auto-locked. Confirm with the employee before unlocking early.',
    brute_force_ip_lockout: 'IP is auto-blocked. Leave blocked unless it’s a known office/VPN IP.',
    credential_stuffing: 'Likely a leaked credential list being tested. Keep the IP blocked; ask affected employees to rotate passwords.',
    xss_probe: 'Review the request path. If it repeats from the same IP, consider a manual IP block.',
    sqli_probe: 'Review the request path. If it repeats from the same IP, consider a manual IP block.',
    path_traversal_probe: 'Check if the targeted route reads files from disk; verify no arbitrary path is reachable.',
    command_injection_probe: 'High risk if the field reaches a shell call. Verify the endpoint never executes user input.',
    ssrf_probe: 'Check if the field is used server-side to fetch a URL. If so, add an allowlist of external hosts.',
    lfi_probe: 'Verify the field never reaches include()/file-read calls with user-controlled paths.',
    open_redirect_probe: 'Low risk alone, but can enable phishing. Validate redirect targets against an allowlist.',
    php_object_injection_probe: 'High risk if reached by unserialize(). Verify the endpoint never deserializes user input.',
    scanner_probe: 'Known pentest/scanner tool. Consider a manual IP block if it keeps recurring.',
    path_scanning_probe: 'Reconnaissance: someone is probing for hidden endpoints. Consider a manual IP block.',
    id_enumeration_probe: 'Someone is probing record IDs they may not own. Check which records exist near the ones tried, and consider a manual IP block.',
    mass_data_access: 'Could be a busy day or data scraping. Check with the employee before treating as exfiltration.',
    suspicious_attachment_upload: 'Do not open the file directly. Verify its real content before allowing it to reach anyone else.',
    privilege_escalation: 'Confirm this role change was intentional and authorized before resolving.',
    mass_export: 'Confirm the export was expected. If not, treat as possible data exfiltration.',
    anomalous_login: 'Confirm with the employee this was really them (e.g. travel). If not, force-logout and reset credentials.',
};

function recommendedAction(eventType) {
    return RECOMMENDED_ACTIONS[eventType] || 'Review the details and resolve once handled.';
}

// ─── Load table ───────────────────────────────────────────────────────────────
async function loadTable(page = 1) {
    currentPage = page;

    const params = new URLSearchParams({ page, per_page: currentPerPage, ...currentFilters });

    const tbody  = document.getElementById('tableBody');
    const infoEl = document.getElementById('tableInfo');
    const pagEl  = document.getElementById('paginationRow');
    tbody.innerHTML = '<tr><td colspan="8" class="px-4 py-12 text-center text-sm text-gray-400">Loading…</td></tr>';

    try {
        const res  = await fetch(`/api/admin/security-events?${params}`, { credentials: 'same-origin' });
        const data = await res.json();

        if (!data.success) {
            tbody.innerHTML = `<tr><td colspan="8" class="px-4 py-6 text-center text-sm text-red-500">${data.message || 'Failed to load data'}</td></tr>`;
            return;
        }

        const { data: rows, meta } = data;

        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="px-4 py-12 text-center text-sm text-gray-400">No events found</td></tr>';
            infoEl.textContent = '0 events';
            pagEl.innerHTML = '';
            return;
        }

        infoEl.textContent = `Showing ${((meta.current_page - 1) * meta.per_page) + 1}-${Math.min(meta.current_page * meta.per_page, meta.total)} of ${meta.total.toLocaleString('id-ID')}`;

        tbody.innerHTML = rows.map((row, idx) => {
            const rowNum = ((meta.current_page - 1) * meta.per_page) + idx + 1;
            const target = [row.target_identifier !== '-' ? row.target_identifier : null, row.target_ip !== '-' ? row.target_ip : null, row.ip_address !== '-' ? row.ip_address : null]
                .filter(Boolean).filter((v, i, a) => a.indexOf(v) === i).join(' · ') || '-';
            const statusBadge = row.status === 'open'
                ? '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">Open</span>'
                : '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Resolved</span>';

            const actions = [];
            if (row.status === 'open') {
                actions.push(`<button type="button" onclick="resolveEvent(${row.id})" class="text-xs text-blue-600 hover:underline">Resolve</button>`);
            }
            if (row.event_type === 'brute_force_account_lockout' && row.status === 'open' && row.payload?.auth_user_id) {
                actions.push(`<button type="button" onclick="unlockAccount(${row.payload.auth_user_id})" class="text-xs text-amber-600 hover:underline">Unlock</button>`);
            }
            if (row.target_employee_id) {
                actions.push(`<button type="button" onclick="forceLogout(${row.target_employee_id})" class="text-xs text-orange-600 hover:underline">Force Logout</button>`);
            }
            const ipForBlock = row.target_ip !== '-' ? row.target_ip : (row.ip_address !== '-' ? row.ip_address : null);
            if (ipForBlock) {
                actions.push(`<button type="button" onclick="blockIp('${escAttr(ipForBlock)}')" class="text-xs text-red-600 hover:underline">Block IP</button>`);
            }

            return `<tr class="border-t border-gray-50 hover:bg-gray-50 transition-colors">
                <td class="px-4 py-3 text-xs text-gray-400">${rowNum}</td>
                <td class="px-4 py-3">
                    <div class="font-medium text-gray-900 text-sm">${escHtml(row.title)}</div>
                    <div class="text-xs text-gray-400 mt-0.5">${escHtml(row.description)}</div>
                    <div class="text-xs text-blue-500 mt-0.5 italic">${escHtml(recommendedAction(row.event_type))}</div>
                </td>
                <td class="px-4 py-3 text-xs text-gray-700">${escHtml(labelizeEventType(row.event_type))}</td>
                <td class="px-4 py-3"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${escAttr(row.severity_color)}">${escHtml(row.severity)}</span></td>
                <td class="px-4 py-3">${statusBadge}</td>
                <td class="px-4 py-3 text-xs text-gray-700">${escHtml(target)}</td>
                <td class="px-4 py-3 text-xs text-gray-700 whitespace-nowrap">${escHtml(row.created_at)}</td>
                <td class="px-4 py-3"><div class="flex items-center gap-2 flex-wrap">${actions.join('') || '<span class="text-xs text-gray-300">-</span>'}</div></td>
            </tr>`;
        }).join('');

        pagEl.innerHTML = buildPagination(meta);
    } catch (e) {
        console.error('loadTable error:', e);
        tbody.innerHTML = '<tr><td colspan="8" class="px-4 py-6 text-center text-sm text-red-500">Error loading data</td></tr>';
    }
}

// ─── Row actions ──────────────────────────────────────────────────────────────
async function resolveEvent(id) {
    if (!confirm('Mark this event as resolved?')) return;
    const data = await postJson(`/api/admin/security-events/${id}/resolve`, {});
    if (!data.success) { alert(data.message || 'Failed'); return; }
    loadStats(); loadTable(currentPage);
}

async function unlockAccount(authUserId) {
    if (!confirm('Unlock this account now?')) return;
    const data = await postJson('/api/admin/security-events/unlock-account', { auth_user_id: authUserId });
    if (!data.success) { alert(data.message || 'Failed'); return; }
    loadStats(); loadTable(currentPage);
}

async function forceLogout(employeeId) {
    if (!confirm('Force-logout every active session for this account?')) return;
    const data = await postJson('/api/admin/security-events/force-logout', { employee_id: employeeId });
    if (!data.success) { alert(data.message || 'Failed'); return; }
    alert(data.message);
}

async function blockIp(ip) {
    const reason = prompt(`Block IP ${ip}. Optional reason:`, '');
    if (reason === null) return;
    const data = await postJson('/api/admin/security-events/block-ip', { ip_address: ip, reason });
    if (!data.success) { alert(data.message || 'Failed'); return; }
    loadStats(); loadBlockedIps();
}

function promptBlockIp() {
    const ip = prompt('IP address to block:');
    if (!ip) return;
    blockIp(ip.trim());
}

async function unblockIp(ip) {
    if (!confirm(`Unblock IP ${ip}?`)) return;
    const data = await postJson('/api/admin/security-events/unblock-ip', { ip_address: ip });
    if (!data.success) { alert(data.message || 'Failed'); return; }
    loadStats(); loadBlockedIps();
}

// ─── Blocked IPs panel ────────────────────────────────────────────────────────
async function loadBlockedIps() {
    const tbody = document.getElementById('blockedIpsBody');
    try {
        const res  = await fetch('/api/admin/security-events/blocked-ips', { credentials: 'same-origin' });
        const data = await res.json();
        if (!data.success) { tbody.innerHTML = '<tr><td colspan="5" class="px-4 py-6 text-center text-sm text-red-500">Failed to load</td></tr>'; return; }

        if (!data.data.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="px-4 py-8 text-center text-sm text-gray-400">No IPs currently blocked</td></tr>';
            return;
        }

        tbody.innerHTML = data.data.map(row => `<tr class="border-t border-gray-50">
            <td class="px-4 py-3 text-sm text-gray-800 font-medium">${escHtml(row.ip_address)}</td>
            <td class="px-4 py-3 text-xs text-gray-600">${escHtml(row.reason)}</td>
            <td class="px-4 py-3 text-xs text-gray-600">${escHtml(row.source)}</td>
            <td class="px-4 py-3 text-xs text-gray-600 whitespace-nowrap">${escHtml(row.expires_at)}</td>
            <td class="px-4 py-3"><button type="button" onclick="unblockIp('${escAttr(row.ip_address)}')" class="text-xs text-blue-600 hover:underline">Unblock</button></td>
        </tr>`).join('');
    } catch (e) {
        console.error('loadBlockedIps error:', e);
        tbody.innerHTML = '<tr><td colspan="5" class="px-4 py-6 text-center text-sm text-red-500">Error loading data</td></tr>';
    }
}

// ─── Top offenders + trend ─────────────────────────────────────────────────────
async function loadTopOffenders() {
    try {
        const res  = await fetch('/api/admin/security-events/top-offenders?days=7', { credentials: 'same-origin' });
        const data = await res.json();
        if (!data.success) return;

        renderOffenderList('topIpsList', data.data.top_ips, r => r.ip_address, r => r.ip_address);
        renderOffenderList('topTargetsList', data.data.top_targets, r => r.target_identifier, r => r.target_identifier);
        renderTrendChart(data.data.trend);
    } catch (e) {
        console.error('loadTopOffenders error:', e);
    }
}

function renderOffenderList(elId, rows, keyFn, filterValueFn) {
    const el = document.getElementById(elId);
    if (!rows || !rows.length) {
        el.innerHTML = '<li class="text-xs text-gray-400">No activity in the last 7 days</li>';
        return;
    }

    el.innerHTML = rows.map(r => `
        <li class="flex items-center justify-between gap-2">
            <button type="button" onclick="quickFilterSearch('${escAttr(filterValueFn(r))}')"
                class="text-xs text-gray-700 hover:text-red-700 hover:underline truncate text-left" title="Filter table by this">
                ${escHtml(keyFn(r))}
            </button>
            <span class="text-xs font-semibold text-red-700 bg-red-50 rounded-full px-2 py-0.5 shrink-0">${r.event_count}</span>
        </li>
    `).join('');
}

function quickFilterSearch(value) {
    document.getElementById('filterSearch').value = value;
    applyFilters();
    window.scrollTo({ top: document.querySelector('table').getBoundingClientRect().top + window.scrollY - 80, behavior: 'smooth' });
}

// Single-hue bar chart (events/day) - thin bars, rounded ends, native tooltip,
// selective labels (first/last only) to avoid clutter over 7 short bars.
function renderTrendChart(trend) {
    const el = document.getElementById('trendChart');
    if (!trend || !trend.length) {
        el.innerHTML = '<p class="text-xs text-gray-400">No events in the last 7 days</p>';
        return;
    }

    const byDay = {};
    trend.forEach(t => { byDay[t.day] = t.count; });

    const days = [];
    for (let i = 6; i >= 0; i--) {
        const d = new Date();
        d.setDate(d.getDate() - i);
        days.push(d.toISOString().slice(0, 10));
    }
    const counts = days.map(d => byDay[d] || 0);
    const max = Math.max(1, ...counts);

    const W = 280, H = 90, padBottom = 16, barGap = 6;
    const barW = (W - barGap * (days.length - 1)) / days.length;

    const bars = days.map((day, i) => {
        const count = counts[i];
        const barH = Math.max(count > 0 ? 3 : 0, (count / max) * (H - padBottom));
        const x = i * (barW + barGap);
        const y = (H - padBottom) - barH;
        const label = new Date(day + 'T00:00:00').toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
        return `<rect x="${x}" y="${y}" width="${barW}" height="${barH}" rx="2" fill="#b91c1c" fill-opacity="${count > 0 ? 1 : 0.15}">
            <title>${label}: ${count} event${count === 1 ? '' : 's'}</title>
        </rect>`;
    }).join('');

    const firstLabel = new Date(days[0] + 'T00:00:00').toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
    const lastLabel  = new Date(days[days.length - 1] + 'T00:00:00').toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });

    el.innerHTML = `
        <svg viewBox="0 0 ${W} ${H}" class="w-full" style="max-height:100px" role="img" aria-label="Security events per day, last 7 days">
            <line x1="0" y1="${H - padBottom}" x2="${W}" y2="${H - padBottom}" stroke="#e5e7eb" stroke-width="1" />
            ${bars}
            <text x="0" y="${H}" font-size="9" fill="#9ca3af">${firstLabel}</text>
            <text x="${W}" y="${H}" font-size="9" fill="#9ca3af" text-anchor="end">${lastLabel}</text>
        </svg>`;
}

// ─── Export CSV ───────────────────────────────────────────────────────────────
function exportCsv() {
    const params = new URLSearchParams(currentFilters);
    window.location.href = `/api/admin/security-events/export?${params}`;
}

// ─── Auto-refresh ─────────────────────────────────────────────────────────────
let autoRefreshTimer = null;

function stampLastUpdated() {
    const now = new Date();
    document.getElementById('lastUpdated').textContent = `Updated ${now.toLocaleTimeString('en-GB')}`;
}

function refreshAll() {
    loadStats();
    loadTable(currentPage);
    loadBlockedIps();
    loadTopOffenders();
    stampLastUpdated();
}

function setupAutoRefresh() {
    const toggle = document.getElementById('autoRefreshToggle');
    const start = () => {
        stop();
        autoRefreshTimer = setInterval(refreshAll, 30000);
    };
    const stop = () => {
        if (autoRefreshTimer) clearInterval(autoRefreshTimer);
        autoRefreshTimer = null;
    };
    toggle.addEventListener('change', () => toggle.checked ? start() : stop());
    if (toggle.checked) start();
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
function buildPagination(meta) {
    if (meta.last_page <= 1) return '';

    const prev = meta.current_page > 1
        ? `<button onclick="loadTable(${meta.current_page - 1})" class="px-3 py-1.5 text-xs font-medium bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all">← Prev</button>`
        : `<button disabled class="px-3 py-1.5 text-xs font-medium bg-gray-100 border border-gray-200 rounded-lg text-gray-400 cursor-not-allowed">← Prev</button>`;

    const next = meta.current_page < meta.last_page
        ? `<button onclick="loadTable(${meta.current_page + 1})" class="px-3 py-1.5 text-xs font-medium bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all">Next →</button>`
        : `<button disabled class="px-3 py-1.5 text-xs font-medium bg-gray-100 border border-gray-200 rounded-lg text-gray-400 cursor-not-allowed">Next →</button>`;

    const start = Math.max(1, meta.current_page - 2);
    const end   = Math.min(meta.last_page, meta.current_page + 2);
    let pageButtons = '';
    for (let p = start; p <= end; p++) {
        const active = p === meta.current_page
            ? 'bg-red-800 text-white border-red-800'
            : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50';
        pageButtons += `<button onclick="loadTable(${p})" class="px-3 py-1.5 text-xs font-medium border rounded-lg transition-all ${active}">${p}</button>`;
    }

    return `<div class="flex items-center gap-2 flex-wrap">
        ${prev}
        <div class="flex items-center gap-1">${pageButtons}</div>
        ${next}
        <span class="text-xs text-gray-400 ml-2">Page ${meta.current_page} of ${meta.last_page}</span>
    </div>
    <span class="text-xs text-gray-400">${meta.total.toLocaleString('id-ID')} total events</span>`;
}

function escHtml(str) {
    if (str === null || str === undefined || str === '') return '-';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function escAttr(str) {
    if (str === null || str === undefined) return '';
    return String(str).replace(/'/g, '&#39;').replace(/"/g, '&quot;');
}

// ─── Header filter popovers (same pattern as Audit Log) ──────────────────────
function _positionHeaderPanel(btn, panel) {
    const r = btn.getBoundingClientRect();
    panel.style.position = 'fixed';
    panel.style.zIndex   = '9999';
    panel.style.top      = `${r.bottom + 4}px`;
    panel.style.left     = `${r.left}px`;
    panel.style.right    = 'auto';
    requestAnimationFrame(() => {
        const pr = panel.getBoundingClientRect();
        if (pr.right > window.innerWidth - 8) {
            panel.style.left = `${Math.max(8, window.innerWidth - 8 - pr.width)}px`;
        }
    });
}

function toggleHeaderFilter(panelId, btn) {
    const panel  = document.getElementById(panelId);
    const isOpen = !panel.classList.contains('hidden');
    closeHeaderFilters();
    if (typeof _closeAllDropdowns === 'function') _closeAllDropdowns();
    if (isOpen) return;

    if (!panel._origParent) panel._origParent = panel.parentElement;
    document.body.appendChild(panel);
    panel.classList.remove('hidden');
    _positionHeaderPanel(btn, panel);

    const firstInput = panel.querySelector('input[type="text"], input[type="date"]');
    if (firstInput) requestAnimationFrame(() => firstInput.focus());
}

function closeHeaderFilters() {
    document.querySelectorAll('.hdr-filter-panel').forEach(p => {
        if (p.classList.contains('hidden')) return;
        p.classList.add('hidden');
        if (p._origParent && p.parentElement !== p._origParent) {
            p._origParent.appendChild(p);
            p.style.position = '';
            p.style.top      = '';
            p.style.left     = '';
            p.style.right    = '';
            p.style.zIndex   = '';
        }
    });
}

document.addEventListener('click', function (e) {
    if (e.target.closest('.hdr-filter-panel') || e.target.closest('.hdr-filter-btn')) return;
    closeHeaderFilters();
});

function applyTimeFilter() {
    const from = document.getElementById('filterDateFrom').value;
    const to   = document.getElementById('filterDateTo').value;
    const err  = document.getElementById('dateFilterError');
    if (from && to && to < from) {
        err.classList.remove('hidden');
        return;
    }
    err.classList.add('hidden');
    applyFilters();
    closeHeaderFilters();
}

function clearTimeFilter() {
    document.getElementById('filterDateFrom').value = '';
    document.getElementById('filterDateTo').value   = '';
    document.getElementById('dateFilterError').classList.add('hidden');
    applyFilters();
    closeHeaderFilters();
}

function clearSearchFilter() {
    document.getElementById('filterSearch').value = '';
    applyFilters();
    closeHeaderFilters();
}

// ─── Filters ──────────────────────────────────────────────────────────────────
function applyFilters() {
    currentFilters = {};
    const search    = document.getElementById('filterSearch').value.trim();
    const eventType = document.getElementById('filterEventType').value;
    const severity  = document.getElementById('filterSeverity').value;
    const status    = document.getElementById('filterStatus').value;
    const dateFrom  = document.getElementById('filterDateFrom').value;
    const dateTo    = document.getElementById('filterDateTo').value;

    if (search)    currentFilters.search     = search;
    if (eventType) currentFilters.event_type = eventType;
    if (severity)  currentFilters.severity   = severity;
    if (status)    currentFilters.status     = status;
    if (dateFrom)  currentFilters.date_from  = dateFrom;
    if (dateTo)    currentFilters.date_to    = dateTo;

    updateFilterIndicators({ search, dateFrom, dateTo, eventType, severity, status });
    loadTable(1);
}

function resetFilters() {
    document.getElementById('filterSearch').value = '';
    setCustomDropdownValue('filterEventType', '');
    setCustomDropdownValue('filterSeverity', '');
    setCustomDropdownValue('filterStatus', '');
    document.getElementById('filterDateFrom').value = '';
    document.getElementById('filterDateTo').value   = '';
    document.getElementById('dateFilterError').classList.add('hidden');
    currentFilters = {};
    updateFilterIndicators({});
    closeHeaderFilters();
    loadTable(1);
}

function updateFilterIndicators({ search = '', dateFrom = '', dateTo = '', eventType = '', severity = '', status = '' } = {}) {
    const searchActive = !!search;
    const timeActive    = !!(dateFrom || dateTo);
    document.getElementById('searchFilterIcon').classList.toggle('text-red-600', searchActive);
    document.getElementById('searchFilterIcon').classList.toggle('text-gray-300', !searchActive);
    document.getElementById('timeFilterIcon').classList.toggle('text-red-600', timeActive);
    document.getElementById('timeFilterIcon').classList.toggle('text-gray-300', !timeActive);

    const anyActive = searchActive || timeActive || !!eventType || !!severity || !!status;
    const resetBtn  = document.getElementById('btnResetFilters');
    resetBtn.classList.toggle('hidden', !anyActive);
    resetBtn.classList.toggle('inline-flex', anyActive);
}

function onPerPageChange() {
    const val = document.getElementById('perPageSelect').value;
    changePerPage(val);
}

function changePerPage(val) {
    currentPerPage = parseInt(val, 10);
    loadTable(1);
}

let _searchDebounce = null;
document.getElementById('filterSearch').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') { clearTimeout(_searchDebounce); applyFilters(); }
});
document.getElementById('filterSearch').addEventListener('input', function() {
    clearTimeout(_searchDebounce);
    _searchDebounce = setTimeout(applyFilters, 400);
});

// ─── Init ─────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    if (typeof initCustomDropdowns === 'function') {
        initCustomDropdowns();
    } else {
        console.warn('initCustomDropdowns belum tersedia - dropdown filter dinonaktifkan, tabel tetap dimuat.');
    }
    document.querySelectorAll('.custom-dd-btn').forEach(btn => btn.addEventListener('click', closeHeaderFilters));

    loadStats();
    loadEventTypes();
    loadTable(1);
    loadBlockedIps();
    loadTopOffenders();
    stampLastUpdated();
    setupAutoRefresh();
});
</script>
@endsection

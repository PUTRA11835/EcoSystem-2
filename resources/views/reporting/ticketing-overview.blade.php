@extends('dashboard')
@section('title', 'Ticketing Overview')
@section('page-title', 'Ticketing Overview')
@section('page-subtitle', 'Customer ticket counts by status')

@section('content')
<div class="bg-white rounded-xl p-6 shadow-sm">

    <div class="flex items-center justify-between gap-4 mb-4 flex-wrap">
        <h2 class="text-lg font-bold text-gray-900">Customer Tickets</h2>

        <div class="relative w-full sm:w-72">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
            <input type="text" id="toSearch" placeholder="Cari nama customer..."
                   class="w-full text-sm border border-gray-200 rounded-lg pl-8 pr-3 py-2 focus:outline-none focus:ring-2 focus:ring-offset-0"
                   style="--tw-ring-color: var(--primary-color);"
                   oninput="filterTicketingOverview(this.value)">
        </div>
    </div>

    {{-- ── Table ───────────────────────────────────────────────────────────── --}}
    <div class="rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-auto" style="max-height: calc(100vh - 320px); min-height: 220px;">
            <table class="text-sm border-collapse w-full" style="min-width: 960px;">
                <thead class="sticky top-0 z-10 bg-gray-50">
                    <tr>
                        <th class="to-th" style="width: 28px;" rowspan="2"></th>
                        <th class="to-th text-left" rowspan="2">Customer Name</th>
                        <th class="to-th text-right" rowspan="2">Total Mandays</th>
                        <th class="to-th text-center to-th-group" colspan="5">Ticket Status</th>
                    </tr>
                    <tr>
                        <th class="to-th text-right"><span class="to-dot" style="background:#ef4444;"></span>Open</th>
                        <th class="to-th text-right"><span class="to-dot" style="background:#f59e0b;"></span>In Process</th>
                        <th class="to-th text-right"><span class="to-dot" style="background:#22c55e;"></span>Close</th>
                        <th class="to-th text-right"><span class="to-dot" style="background:#0f766e;"></span>Other</th>
                        <th class="to-th text-right"><span class="to-dot" style="background:#6366f1;"></span>Wait Close</th>
                    </tr>
                </thead>
                <tbody id="toTableBody" class="bg-white">
                    <tr>
                        <td colspan="8" class="px-4 py-14 text-center text-gray-400 text-sm">
                            <i class="fas fa-spinner fa-spin text-2xl mb-3 block primary-text opacity-50"></i>
                            <span class="text-gray-400">Loading data...</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- Empty state --}}
        <div id="toEmpty" class="hidden text-center py-16 px-4">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4">
                <svg class="w-8 h-8 text-gray-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
                </svg>
            </div>
            <p class="text-gray-700 font-semibold">No customer tickets found</p>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.primary-text { color: var(--primary-color) !important; }
.to-th {
    padding: 0.55rem 0.9rem; font-size: 11px; font-weight: 600;
    color: #6b7280; text-transform: uppercase; letter-spacing: 0.06em;
    white-space: nowrap; border-bottom: 1px solid #cbd5e1;
    background: #f9fafb;
}
.to-th-group {
    color: #475569; font-size: 10.5px; letter-spacing: 0.08em;
    border-bottom: 1px solid #e5e7eb; background: #f3f4f6;
}
.to-dot {
    display: inline-block; width: 7px; height: 7px; border-radius: 999px;
    margin-right: 5px; vertical-align: middle;
}
#toSearch:focus { border-color: var(--primary-color); }
.to-td {
    padding: 0.65rem 0.9rem; white-space: nowrap; border-bottom: 1px solid #e5e7eb;
    vertical-align: middle;
}
.to-bar-track { width: 100%; height: 6px; border-radius: 999px; background: #eef0f2; overflow: hidden; }
.to-bar-fill  { height: 100%; border-radius: 999px; }
.to-row { cursor: pointer; }
.to-row:hover { background: #f9fafb; }
.to-chevron { transition: transform 0.15s ease; color: #9ca3af; font-size: 11px; }
.to-row.expanded .to-chevron { transform: rotate(90deg); }
.to-detail-td { padding: 0; white-space: normal; border-bottom: 1px solid #e5e7eb; background: #f9fafb; }
.to-detail-wrap { padding: 1rem 1.25rem 1.25rem 2.75rem; }
.to-tt-card { border: 1px solid #e5e7eb; border-radius: 0.75rem; background: #fff; overflow: hidden; }
.to-tt-toolbar {
    display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; flex-wrap: wrap;
    padding: 0.6rem 0.85rem; border-bottom: 1px solid #f1f5f9; background: #fafbfc;
}
.to-tt-title { font-size: 11.5px; font-weight: 700; color: #374151; }
.to-tt-title span { color: #9ca3af; font-weight: 500; }
.to-tt-scroll { overflow: auto; max-height: 320px; }
.to-tt-table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
.to-tt-table thead th {
    position: sticky; top: 0; z-index: 1; text-align: left; padding: 0; text-transform: uppercase;
    color: #6b7280; background: #f9fafb; border-bottom: 1px solid #e5e7eb; white-space: nowrap;
}
.to-tt-th-btn {
    display: flex; align-items: center; gap: 4px; width: 100%; cursor: pointer;
    background: none; border: none; font: inherit; text-transform: inherit; letter-spacing: 0.05em;
    font-size: 10.5px; font-weight: 700; color: #6b7280; padding: 0.5rem 0.75rem;
}
.to-tt-th-btn:hover { background: #f1f5f9; color: #374151; }
.to-tt-th-btn.active { color: var(--primary-color); }
.to-tt-filter-caret { font-size: 9px; opacity: 0.6; }
.to-tt-filter-panel {
    position: absolute; top: 100%; left: 0; margin-top: 2px; background: #fff; border: 1px solid #e5e7eb;
    border-radius: 0.6rem; box-shadow: 0 10px 25px -5px rgba(0,0,0,.12), 0 8px 10px -6px rgba(0,0,0,.08);
    padding: 0.5rem; z-index: 30; min-width: 190px; text-transform: none; letter-spacing: normal;
}
.to-tt-filter-panel input.to-tt-filter-input {
    width: 100%; font-size: 12.5px; font-weight: 400; border: 1px solid #e5e7eb; border-radius: 0.4rem;
    padding: 0.35rem 0.5rem; outline: none; color: #374151;
}
.to-tt-filter-panel input.to-tt-filter-input:focus { border-color: var(--primary-color); }
.to-tt-filter-opt {
    display: block; width: 100%; text-align: left; padding: 0.35rem 0.5rem; font-size: 12.5px; font-weight: 400;
    border-radius: 0.35rem; background: none; border: none; cursor: pointer; color: #374151; white-space: nowrap;
}
.to-tt-filter-opt:hover { background: #f3f4f6; }
.to-tt-filter-opt.active { background: var(--primary-color); color: #fff; }
.to-tt-filter-clear {
    margin-top: 0.35rem; padding-top: 0.35rem; border-top: 1px solid #f1f5f9;
    font-size: 11px; font-weight: 500; color: #9ca3af; cursor: pointer; text-align: right;
}
.to-tt-filter-clear:hover { color: #ef4444; }
.to-tt-table tbody td { padding: 0.5rem 0.75rem; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
.to-tt-table tbody tr:last-child td { border-bottom: none; }
.to-tt-table tbody tr:hover { background: #f9fafb; }
.to-tt-table a { color: var(--primary-color); font-weight: 600; text-decoration: none; }
.to-tt-table a:hover { text-decoration: underline; }
.to-tt-desc {
    color: #6b7280; max-width: 420px; display: -webkit-box; -webkit-line-clamp: 1;
    -webkit-box-orient: vertical; overflow: hidden;
}
.to-tt-badge {
    display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.15rem 0.55rem; border-radius: 999px;
    font-size: 11px; font-weight: 600; white-space: nowrap;
}
.to-tt-empty, .to-tt-no-match { padding: 1.25rem; text-align: center; color: #9ca3af; font-size: 12px; }
</style>
@endpush

@push('scripts')
<script>
const TICKET_SHOW_URL_TEMPLATE = @json(route('ticket.show', ['id' => '__ID__']));

const toDetailCache = {};
const toFilterState = {};

document.addEventListener('click', (e) => {
    const thBtn = e.target.closest('.to-tt-th-btn');
    if (thBtn) {
        e.stopPropagation();
        const { cid, col } = thBtn.dataset;
        const panel = document.querySelector(`.to-tt-filter-panel[data-cid="${cssEscape(cid)}"][data-col="${col}"]`);
        const isOpen = panel && !panel.classList.contains('hidden');
        document.querySelectorAll('.to-tt-filter-panel').forEach(p => p.classList.add('hidden'));
        if (panel && !isOpen) panel.classList.remove('hidden');
        return;
    }

    const optBtn = e.target.closest('.to-tt-filter-opt');
    if (optBtn) {
        e.stopPropagation();
        const { cid, col, value } = optBtn.dataset;
        if (col === 'date' || col === 'age') {
            setSort(cid, col, value || null);
        } else {
            setSelectFilter(cid, col, value || '');
        }
        return;
    }

    const clearBtn = e.target.closest('.to-tt-filter-clear');
    if (clearBtn) {
        e.stopPropagation();
        clearColumnFilter(clearBtn.dataset.cid, clearBtn.dataset.col);
        return;
    }

    if (!e.target.closest('.to-tt-filter-panel')) {
        document.querySelectorAll('.to-tt-filter-panel').forEach(p => p.classList.add('hidden'));
    }
});

document.addEventListener('input', (e) => {
    if (e.target.classList.contains('to-tt-filter-input')) {
        const { cid, col } = e.target.dataset;
        setTextFilter(cid, col, e.target.value);
    }
});

document.addEventListener('DOMContentLoaded', loadTicketingOverview);

async function loadTicketingOverview() {
    const tbody = document.getElementById('toTableBody');
    const empty = document.getElementById('toEmpty');
    empty.classList.add('hidden');

    try {
        const res = await fetch('/api/reporting/ticketing-overview', {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '' },
            credentials: 'same-origin'
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load data');

        renderTicketingOverview(json.data || []);
    } catch (e) {
        console.error(e);
        tbody.innerHTML = `<tr><td colspan="8" class="px-4 py-10 text-center text-sm">
            <div class="inline-flex flex-col items-center gap-2 text-red-500">
                <i class="fas fa-exclamation-circle text-xl"></i>
                <span>${escHtml(e.message)}</span>
            </div>
        </td></tr>`;
    }
}

function renderTicketingOverview(rows) {
    const tbody = document.getElementById('toTableBody');
    const empty = document.getElementById('toEmpty');

    if (rows.length === 0) {
        tbody.innerHTML = '';
        empty.classList.remove('hidden');
        return;
    }
    empty.classList.add('hidden');

    tbody.innerHTML = rows.map(r => {
        const ticketCount = r.open_tickets + r.inprocess_tickets + r.close_tickets + r.other_tickets + r.wait_close_tickets;
        const cid = escHtml(r.customer_id);
        return `
        <tr class="to-row" data-customer-id="${cid}" data-name="${escHtml((r.customer_name || '').toLowerCase())}" onclick="toggleCustomerDetail('${cid}')">
            <td class="to-td text-center"><i class="fas fa-chevron-right to-chevron"></i></td>
            <td class="to-td font-semibold text-gray-800">${escHtml(r.customer_name)}</td>
            <td class="to-td text-right text-gray-800">${r.total_mandays}</td>
            ${countCell(r.open_tickets,       ticketCount, '#ef4444')}
            ${countCell(r.inprocess_tickets,  ticketCount, '#f59e0b')}
            ${countCell(r.close_tickets,      ticketCount, '#22c55e')}
            ${countCell(r.other_tickets,      ticketCount, '#0f766e')}
            ${countCell(r.wait_close_tickets, ticketCount, '#6366f1')}
        </tr>
        <tr class="to-detail-row hidden" data-customer-id="${cid}">
            <td colspan="8" class="to-detail-td"><div class="to-detail-wrap" id="toDetail-${cid}"></div></td>
        </tr>
    `;
    }).join('');
}

function filterTicketingOverview(query) {
    const q = query.trim().toLowerCase();
    const tbody = document.getElementById('toTableBody');
    const rows = tbody.querySelectorAll('.to-row');
    let visibleCount = 0;

    rows.forEach(row => {
        const match = !q || row.dataset.name.includes(q);
        row.classList.toggle('hidden', !match);
        const detailRow = tbody.querySelector(`.to-detail-row[data-customer-id="${cssEscape(row.dataset.customerId)}"]`);
        if (!match && detailRow) {
            detailRow.classList.add('hidden');
            row.classList.remove('expanded');
        }
        if (match) visibleCount++;
    });

    let noMatch = document.getElementById('toNoMatch');
    if (visibleCount === 0 && rows.length > 0) {
        if (!noMatch) {
            noMatch = document.createElement('tr');
            noMatch.id = 'toNoMatch';
            noMatch.innerHTML = `<td colspan="8" class="px-4 py-10 text-center text-gray-400 text-sm">
                <i class="fas fa-search mb-2 block text-xl opacity-50"></i>
                No customer matches "<span id="toNoMatchQuery"></span>"
            </td>`;
            tbody.appendChild(noMatch);
        }
        noMatch.querySelector('#toNoMatchQuery').textContent = query;
        noMatch.classList.remove('hidden');
    } else if (noMatch) {
        noMatch.classList.add('hidden');
    }
}

function countCell(count, total, color) {
    const pct = total > 0 ? Math.round((count / total) * 100) : 0;
    return `<td class="to-td text-right">
        <div class="to-bar-track mb-1"><div class="to-bar-fill" style="width:${pct}%; background:${color};"></div></div>
        <span class="text-gray-600 text-xs">${count} Tickets</span>
    </td>`;
}

async function toggleCustomerDetail(customerId) {
    const row = document.querySelector(`.to-row[data-customer-id="${cssEscape(customerId)}"]`);
    const detailRow = document.querySelector(`.to-detail-row[data-customer-id="${cssEscape(customerId)}"]`);
    if (!row || !detailRow) return;

    const isOpen = !detailRow.classList.contains('hidden');
    if (isOpen) {
        detailRow.classList.add('hidden');
        row.classList.remove('expanded');
        return;
    }

    row.classList.add('expanded');
    detailRow.classList.remove('hidden');

    const container = document.getElementById(`toDetail-${customerId}`);
    if (toDetailCache[customerId]) {
        container.innerHTML = renderCustomerDetail(customerId, toDetailCache[customerId]);
        return;
    }

    container.innerHTML = `<div class="to-tt-empty"><i class="fas fa-spinner fa-spin mr-1"></i> Loading tickets...</div>`;

    try {
        const res = await fetch(`/api/reporting/ticketing-overview/${encodeURIComponent(customerId)}`, {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '' },
            credentials: 'same-origin'
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load detail');

        toDetailCache[customerId] = json.tickets || [];
        container.innerHTML = renderCustomerDetail(customerId, toDetailCache[customerId]);
    } catch (e) {
        console.error(e);
        container.innerHTML = `<div class="to-tt-empty" style="color:#ef4444;">
            <i class="fas fa-exclamation-circle mr-1"></i> ${escHtml(e.message)}
        </div>`;
    }
}

const TO_STATUS_BADGES = {
    open:                     { label: 'Open',                  color: '#ef4444' },
    inprocess:                { label: 'In Process',            color: '#f59e0b' },
    closed:                   { label: 'Closed',                color: '#22c55e' },
    waiting_to_confirmation:  { label: 'Wait Close',             color: '#6366f1' },
    waiting_on_customer:      { label: 'Waiting on Customer',    color: '#0f766e' },
    waiting_on_3rd_party:     { label: 'Waiting on 3rd Party',   color: '#0f766e' },
    hold:                     { label: 'Hold',                  color: '#0f766e' },
    cancelled:                { label: 'Cancelled',             color: '#0f766e' },
};

function statusBadge(status, statusLabel) {
    const meta = TO_STATUS_BADGES[status] || { label: statusLabel || status || '-', color: '#9ca3af' };
    return `<span class="to-tt-badge" style="background:${meta.color}1a; color:${meta.color};">
        <span class="to-dot" style="background:${meta.color}; margin-right:0;"></span>${escHtml(meta.label)}
    </span>`;
}

function ensureFilterState(cid) {
    if (!toFilterState[cid]) {
        toFilterState[cid] = { ticket: '', description: '', status: '', lead: '', sortCol: null, sortDir: null };
    }
    return toFilterState[cid];
}

function uniqueStatuses(tickets) {
    const seen = new Map();
    tickets.forEach(t => {
        if (t.status && !seen.has(t.status)) seen.set(t.status, t.status_label || t.status);
    });
    return Array.from(seen.entries());
}

function uniqueLeads(tickets) {
    const seen = new Set();
    let hasUnassigned = false;
    tickets.forEach(t => {
        if (t.lead_name) seen.add(t.lead_name); else hasUnassigned = true;
    });
    const arr = Array.from(seen).sort();
    if (hasUnassigned) arr.push('__unassigned__');
    return arr;
}

function ticketRowsHtml(tickets) {
    if (tickets.length === 0) {
        return `<tr><td colspan="6" class="to-tt-no-match">No tickets match the current filters</td></tr>`;
    }
    return tickets.map(t => `
        <tr>
            <td><a href="${ticketShowUrl(t.ticket_id)}">${escHtml(t.ticket_number)}</a></td>
            <td class="to-tt-desc" title="${escHtml(t.description || '')}">${escHtml(t.description || '')}</td>
            <td>${statusBadge(t.status, t.status_label)}</td>
            <td>${escHtml(t.lead_name || '-')}</td>
            <td>${formatDate(t.created_at)}</td>
            <td>${daysNotClosed(t.created_at)} days</td>
        </tr>
    `).join('');
}

function renderCustomerDetail(customerId, tickets) {
    const cid = escHtml(customerId);
    toFilterState[cid] = { ticket: '', description: '', status: '', lead: '', sortCol: null, sortDir: null };

    if (tickets.length === 0) {
        return `<div class="to-tt-card"><div class="to-tt-empty">No open tickets for this customer</div></div>`;
    }

    const statusOptions = uniqueStatuses(tickets).map(([val, label]) => `
        <button type="button" class="to-tt-filter-opt" data-cid="${cid}" data-col="status" data-value="${escHtml(val)}">${escHtml(label)}</button>
    `).join('');

    const leadOptions = uniqueLeads(tickets).map(val => {
        const label = val === '__unassigned__' ? 'Unassigned' : val;
        return `<button type="button" class="to-tt-filter-opt" data-cid="${cid}" data-col="lead" data-value="${escHtml(val)}">${escHtml(label)}</button>`;
    }).join('');

    const header = `
        <tr>
            <th style="min-width:130px;">
                <button type="button" class="to-tt-th-btn" data-cid="${cid}" data-col="ticket">Tiket <i class="fas fa-caret-down to-tt-filter-caret"></i></button>
                <div class="to-tt-filter-panel hidden" data-cid="${cid}" data-col="ticket">
                    <input type="text" class="to-tt-filter-input" data-cid="${cid}" data-col="ticket" placeholder="Cari nomor tiket...">
                    <div class="to-tt-filter-clear" data-cid="${cid}" data-col="ticket">Clear</div>
                </div>
            </th>
            <th style="min-width:260px;">
                <button type="button" class="to-tt-th-btn" data-cid="${cid}" data-col="description">Description <i class="fas fa-caret-down to-tt-filter-caret"></i></button>
                <div class="to-tt-filter-panel hidden" data-cid="${cid}" data-col="description">
                    <input type="text" class="to-tt-filter-input" data-cid="${cid}" data-col="description" placeholder="Cari deskripsi...">
                    <div class="to-tt-filter-clear" data-cid="${cid}" data-col="description">Clear</div>
                </div>
            </th>
            <th style="min-width:150px;">
                <button type="button" class="to-tt-th-btn" data-cid="${cid}" data-col="status">Status <i class="fas fa-caret-down to-tt-filter-caret"></i></button>
                <div class="to-tt-filter-panel hidden" data-cid="${cid}" data-col="status">
                    <button type="button" class="to-tt-filter-opt active" data-cid="${cid}" data-col="status" data-value="">All</button>
                    ${statusOptions}
                </div>
            </th>
            <th style="min-width:120px;">
                <button type="button" class="to-tt-th-btn" data-cid="${cid}" data-col="lead">Lead <i class="fas fa-caret-down to-tt-filter-caret"></i></button>
                <div class="to-tt-filter-panel hidden" data-cid="${cid}" data-col="lead">
                    <button type="button" class="to-tt-filter-opt active" data-cid="${cid}" data-col="lead" data-value="">All</button>
                    ${leadOptions}
                </div>
            </th>
            <th style="min-width:120px;">
                <button type="button" class="to-tt-th-btn" data-cid="${cid}" data-col="date">Create Date <i class="fas fa-caret-down to-tt-filter-caret"></i></button>
                <div class="to-tt-filter-panel hidden" data-cid="${cid}" data-col="date">
                    <button type="button" class="to-tt-filter-opt" data-cid="${cid}" data-col="date" data-value="desc">&#8595; Newest first</button>
                    <button type="button" class="to-tt-filter-opt" data-cid="${cid}" data-col="date" data-value="asc">&#8593; Oldest first</button>
                    <div class="to-tt-filter-clear" data-cid="${cid}" data-col="date">Clear sort</div>
                </div>
            </th>
            <th style="min-width:120px;">
                <button type="button" class="to-tt-th-btn" data-cid="${cid}" data-col="age">Day Not Close <i class="fas fa-caret-down to-tt-filter-caret"></i></button>
                <div class="to-tt-filter-panel hidden" data-cid="${cid}" data-col="age">
                    <button type="button" class="to-tt-filter-opt" data-cid="${cid}" data-col="age" data-value="desc">&#8595; Highest first</button>
                    <button type="button" class="to-tt-filter-opt" data-cid="${cid}" data-col="age" data-value="asc">&#8593; Lowest first</button>
                    <div class="to-tt-filter-clear" data-cid="${cid}" data-col="age">Clear sort</div>
                </div>
            </th>
        </tr>
    `;

    return `
        <div class="to-tt-card">
            <div class="to-tt-toolbar">
                <span class="to-tt-title">Open Tickets <span id="toTicketCount-${cid}">(${tickets.length})</span></span>
            </div>
            <div class="to-tt-scroll">
                <table class="to-tt-table">
                    <thead>${header}</thead>
                    <tbody id="toTicketBody-${cid}">${ticketRowsHtml(tickets)}</tbody>
                </table>
            </div>
        </div>
    `;
}

function setHeaderActive(cid, col, isActive) {
    const btn = document.querySelector(`.to-tt-th-btn[data-cid="${cssEscape(cid)}"][data-col="${col}"]`);
    if (btn) btn.classList.toggle('active', isActive);
}

function setTextFilter(cid, col, value) {
    const v = value.trim().toLowerCase();
    ensureFilterState(cid)[col] = v;
    applyCustomerFilters(cid);
    setHeaderActive(cid, col, v.length > 0);
}

function setSelectFilter(cid, col, value) {
    ensureFilterState(cid)[col] = value;
    applyCustomerFilters(cid);
    document.querySelectorAll(`.to-tt-filter-opt[data-cid="${cssEscape(cid)}"][data-col="${col}"]`).forEach(btn => {
        btn.classList.toggle('active', (btn.dataset.value || '') === value);
    });
    setHeaderActive(cid, col, !!value);
}

function setSort(cid, col, dir) {
    const state = ensureFilterState(cid);
    state.sortCol = dir ? col : null;
    state.sortDir = dir || null;
    applyCustomerFilters(cid);

    ['date', 'age'].forEach(c => {
        document.querySelectorAll(`.to-tt-filter-opt[data-cid="${cssEscape(cid)}"][data-col="${c}"]`).forEach(btn => {
            btn.classList.toggle('active', c === col && btn.dataset.value === dir);
        });
        setHeaderActive(cid, c, c === col && !!dir);
    });
}

function clearColumnFilter(cid, col) {
    const state = ensureFilterState(cid);
    if (col === 'date' || col === 'age') {
        if (state.sortCol === col) { state.sortCol = null; state.sortDir = null; }
    } else {
        state[col] = '';
    }
    applyCustomerFilters(cid);
    setHeaderActive(cid, col, false);

    const panel = document.querySelector(`.to-tt-filter-panel[data-cid="${cssEscape(cid)}"][data-col="${col}"]`);
    if (panel) {
        panel.querySelectorAll('input.to-tt-filter-input').forEach(i => { i.value = ''; });
        panel.querySelectorAll('.to-tt-filter-opt').forEach(b => b.classList.toggle('active', (b.dataset.value || '') === ''));
    }
}

function applyCustomerFilters(cid) {
    const tickets = toDetailCache[cid] || [];
    const state = ensureFilterState(cid);

    let filtered = tickets.filter(t => {
        if (state.ticket && !(t.ticket_number || '').toLowerCase().includes(state.ticket)) return false;
        if (state.description && !(t.description || '').toLowerCase().includes(state.description)) return false;
        if (state.status && t.status !== state.status) return false;
        if (state.lead) {
            const leadVal = t.lead_name || '__unassigned__';
            if (leadVal !== state.lead) return false;
        }
        return true;
    });

    if (state.sortCol) {
        filtered = filtered.slice().sort((a, b) => {
            const va = state.sortCol === 'age' ? daysNotClosed(a.created_at) : (new Date(a.created_at).getTime() || 0);
            const vb = state.sortCol === 'age' ? daysNotClosed(b.created_at) : (new Date(b.created_at).getTime() || 0);
            return state.sortDir === 'asc' ? va - vb : vb - va;
        });
    }

    const tbody = document.getElementById(`toTicketBody-${cid}`);
    if (tbody) tbody.innerHTML = ticketRowsHtml(filtered);

    const countEl = document.getElementById(`toTicketCount-${cid}`);
    if (countEl) countEl.textContent = `(${filtered.length} of ${tickets.length})`;
}

function formatDate(value) {
    if (!value) return '-';
    const d = new Date(value);
    if (isNaN(d)) return '-';
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

function daysNotClosed(value) {
    if (!value) return 0;
    const created = new Date(value);
    if (isNaN(created)) return 0;
    const createdDate = new Date(created.getFullYear(), created.getMonth(), created.getDate());
    const today = new Date();
    const todayDate = new Date(today.getFullYear(), today.getMonth(), today.getDate());
    return Math.max(0, Math.round((todayDate - createdDate) / 86400000));
}

function ticketShowUrl(id) {
    return TICKET_SHOW_URL_TEMPLATE.replace('__ID__', encodeURIComponent(id));
}

function cssEscape(str) {
    return String(str).replace(/["\\]/g, '\\$&');
}

function escHtml(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
</script>
@endpush

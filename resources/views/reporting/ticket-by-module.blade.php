@extends('dashboard')
@section('title', 'Ticket by Modul')
@section('page-title', 'Ticket by Modul')
@section('page-subtitle', 'Tickets grouped by modul')

@section('content')
<div class="bg-white rounded-xl p-6 shadow-sm">

    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 pb-4 border-b-2 border-gray-100">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Ticket by Modul</h2>
            <p class="text-sm text-gray-500 mt-0.5">Tickets grouped by modul, including tickets with no modul assigned</p>
        </div>
        <a href="{{ route('reporting.ticket-by-module.export') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-red-800 hover:bg-red-900 text-white text-sm font-semibold rounded-lg transition-colors">
            <i class="fas fa-file-excel"></i> Export
        </a>
    </div>

    {{-- Summary bar --}}
    <div id="tbmSummary" class="flex items-center justify-between gap-3 mb-4 text-sm text-gray-500">
        <span id="tbmSummaryText">Loading...</span>
        <div class="flex items-center gap-3">
            <select id="tbmStatusFilter" onchange="applyTbmStatusFilter()" class="text-xs font-medium text-gray-700 border border-gray-200 rounded-lg pl-2.5 pr-7 py-1.5 focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-300">
                <option value="">All Status</option>
                <option value="open">Open</option>
                <option value="inprocess">Inprocess</option>
                <option value="waiting_on_customer">Waiting on Customer</option>
                <option value="waiting_on_3rd_party">Waiting on 3rd Party</option>
                <option value="waiting_to_confirmation">Waiting to Confirmation</option>
                <option value="hold">Hold</option>
                <option value="cancelled">Cancelled</option>
                <option value="closed">Closed</option>
            </select>
            <button type="button" onclick="setAllTbmCards(true)" class="text-xs font-semibold text-red-800 hover:underline">Expand All</button>
            <button type="button" onclick="setAllTbmCards(false)" class="text-xs font-semibold text-gray-500 hover:underline">Collapse All</button>
        </div>
    </div>

    {{-- Cards --}}
    <div id="tbmCards" class="space-y-6">
        {{-- Rendered by JS --}}
    </div>

    {{-- Empty state --}}
    <div id="tbmEmpty" class="hidden py-12 text-center">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-14 h-14 mx-auto mb-4 text-gray-300">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
        </svg>
        <p class="text-base font-medium text-gray-900 mb-1">No tickets found</p>
    </div>
</div>
@endsection

@push('styles')
<style>
.tbm-th {
    padding: 0.65rem 0.9rem; font-size: 11px; font-weight: 600;
    color: #6b7280; text-transform: uppercase; letter-spacing: 0.06em;
    white-space: nowrap; border-bottom: 1px solid #e5e7eb;
    background: #ffffff;
}
.tbm-td {
    padding: 0.65rem 0.9rem; white-space: nowrap; border-bottom: 1px solid #f3f4f6;
    vertical-align: middle;
}
.tbm-td-desc { white-space: normal; max-width: 420px; }
.tbm-chevron { transition: transform 0.2s ease; }
.tbm-chevron.is-collapsed { transform: rotate(-90deg); }
.tbm-body { transition: grid-template-rows 0.2s ease; display: grid; grid-template-rows: 1fr; }
.tbm-body.is-collapsed { grid-template-rows: 0fr; }
.tbm-body > div { overflow: hidden; }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', loadTicketByModule);

let tbmAllGroups = [];

async function loadTicketByModule() {
    const cards   = document.getElementById('tbmCards');
    const empty   = document.getElementById('tbmEmpty');
    const summary = document.getElementById('tbmSummaryText');
    empty.classList.add('hidden');

    try {
        const res = await fetch('/api/reporting/ticket-by-module', {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load data');

        tbmAllGroups = json.data || [];
        renderTicketByModule(tbmAllGroups);
    } catch (e) {
        console.error(e);
        summary.textContent = 'Failed to load data.';
        cards.innerHTML = `<div class="text-center py-10 text-red-500 text-sm">
            <i class="fas fa-exclamation-circle text-xl block mb-2"></i>${escHtml(e.message)}
        </div>`;
    }
}

function applyTbmStatusFilter() {
    const status = document.getElementById('tbmStatusFilter').value;

    if (!status) {
        renderTicketByModule(tbmAllGroups);
        return;
    }

    const filtered = tbmAllGroups
        .map(group => ({
            module_name: group.module_name,
            tickets: group.tickets.filter(t => t.status === status),
        }))
        .filter(group => group.tickets.length > 0);

    renderTicketByModule(filtered);
}

function renderTicketByModule(groups) {
    const cards   = document.getElementById('tbmCards');
    const empty   = document.getElementById('tbmEmpty');
    const summary = document.getElementById('tbmSummaryText');

    if (!groups.length) {
        cards.innerHTML = '';
        empty.classList.remove('hidden');
        const statusFilter = document.getElementById('tbmStatusFilter')?.value;
        summary.textContent = statusFilter ? 'No tickets match the selected status.' : 'No modules found.';
        return;
    }
    empty.classList.add('hidden');

    const totalTickets = groups.reduce((s, g) => s + g.tickets.length, 0);
    summary.textContent = `${groups.length} modul${groups.length > 1 ? 's' : ''} · ${totalTickets} ticket${totalTickets !== 1 ? 's' : ''}`;

    cards.innerHTML = groups.map((group, idx) => {
        const isUnassigned = group.module_name === 'No Modul Assign';
        const bodyId = `tbmBody-${idx}`;
        const chevronId = `tbmChevron-${idx}`;
        return `
        <div class="border border-gray-200 rounded-xl overflow-hidden">
            <button type="button" onclick="toggleTbmCard('${bodyId}','${chevronId}')" class="w-full flex items-center justify-between px-5 py-4 ${isUnassigned ? 'bg-gray-100' : 'bg-gray-50'} border-b border-gray-200 text-left hover:brightness-95 transition-all">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full ${isUnassigned ? 'bg-gray-300' : 'primary-gradient'} flex items-center justify-center flex-shrink-0">
                        <i class="fas ${isUnassigned ? 'fa-question' : 'fa-puzzle-piece'} text-white text-xs"></i>
                    </div>
                    <span class="font-semibold ${isUnassigned ? 'text-gray-500 italic' : 'text-gray-900'}">${escHtml(group.module_name)}</span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs text-gray-500 font-medium">${group.tickets.length} ticket${group.tickets.length !== 1 ? 's' : ''}</span>
                    <i id="${chevronId}" class="fas fa-chevron-down text-gray-400 text-xs tbm-chevron"></i>
                </div>
            </button>

            <div id="${bodyId}" class="tbm-body">
                <div>
                    <table class="w-full">
                        <thead>
                            <tr>
                                <th class="tbm-th text-left">No Tiket</th>
                                <th class="tbm-th text-left">Description</th>
                                <th class="tbm-th text-left">Status</th>
                                <th class="tbm-th text-left">Ticket Lead</th>
                                <th class="tbm-th text-left">Modul</th>
                                <th class="tbm-th text-left">Created At</th>
                                <th class="tbm-th text-right">Day not Close</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${group.tickets.map(t => ticketRow(t, group.module_name)).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>`;
    }).join('');
}

function toggleTbmCard(bodyId, chevronId) {
    document.getElementById(bodyId)?.classList.toggle('is-collapsed');
    document.getElementById(chevronId)?.classList.toggle('is-collapsed');
}

function setAllTbmCards(expand) {
    document.querySelectorAll('.tbm-body').forEach(el => el.classList.toggle('is-collapsed', !expand));
    document.querySelectorAll('.tbm-chevron').forEach(el => el.classList.toggle('is-collapsed', !expand));
}

const TBM_STATUS_MAP = {
    'open':                     { label: 'Open',                     cls: 'bg-blue-50 text-blue-700' },
    'inprocess':                { label: 'Inprocess',                cls: 'bg-yellow-50 text-yellow-700' },
    'waiting_on_customer':      { label: 'Waiting on Customer',      cls: 'bg-amber-50 text-amber-700' },
    'waiting_on_3rd_party':     { label: 'Waiting on 3rd Party',     cls: 'bg-indigo-50 text-indigo-700' },
    'waiting_to_confirmation':  { label: 'Waiting to Confirmation',  cls: 'bg-teal-50 text-teal-700' },
    'hold':                     { label: 'Hold',                     cls: 'bg-orange-50 text-orange-700' },
    'cancelled':                { label: 'Cancelled',                cls: 'bg-gray-100 text-gray-500' },
    'closed':                   { label: 'Closed',                   cls: 'bg-green-50 text-green-700' },
};

function ticketRow(ticket, moduleName) {
    const created = ticket.created_at;
    const dateStr = created ? new Date(created).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
    const dayOnClose = created
        ? Math.max(0, Math.ceil((Date.now() - new Date(created).getTime()) / 86400000))
        : null;
    const sInfo = TBM_STATUS_MAP[ticket.status] || { label: ticket.status_label || ticket.status || '—', cls: 'bg-gray-100 text-gray-500' };

    return `
    <tr class="cursor-pointer hover:bg-gray-50" onclick="window.location='/ticket/${ticket.ticket_id}'">
        <td class="tbm-td text-sm font-semibold text-gray-700">${escHtml(ticket.ticket_number || '—')}</td>
        <td class="tbm-td tbm-td-desc text-sm text-gray-700">${escHtml(ticket.description || '—')}</td>
        <td class="tbm-td"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${sInfo.cls}">${escHtml(sInfo.label)}</span></td>
        <td class="tbm-td text-sm text-gray-700">${ticket.lead_name ? escHtml(ticket.lead_name) : '<span class="text-gray-300 italic">Unassigned</span>'}</td>
        <td class="tbm-td text-sm text-gray-700">${escHtml(moduleName)}</td>
        <td class="tbm-td text-xs text-gray-500">${dateStr}</td>
        <td class="tbm-td text-right text-sm font-semibold text-gray-700">${dayOnClose === null ? '—' : dayOnClose}</td>
    </tr>`;
}

function escHtml(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
</script>
@endpush

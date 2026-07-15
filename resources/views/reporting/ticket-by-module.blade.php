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

    {{-- Filter toolbar --}}
    <div id="tbmToolbar" class="mb-4">
        <div class="flex flex-wrap items-center gap-2">
            {{-- No Tiket: keyword search --}}
            <div class="relative">
                <button type="button" id="tbmTicketFilterBtn" onclick="toggleTbmPopover(event, 'tbmTicketFilterPanel')" class="tbm-filter-btn">
                    <i class="fas fa-hashtag text-[10px] text-gray-400"></i>
                    <span>No Tiket</span>
                    <svg id="tbmTicketFilterIcon" class="w-3 h-3 text-gray-300 transition-colors" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 011 1v1.586a1 1 0 01-.293.707l-4.121 4.121A1 1 0 0012 12.121V15.5l-4 1.5v-4.879a1 1 0 00-.293-.707L3.586 7.293A1 1 0 013.293 6.586L3 5z" clip-rule="evenodd" />
                    </svg>
                </button>
                <div id="tbmTicketFilterPanel" class="tbm-popover hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[60] p-3" style="min-width:220px;">
                    <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Search no tiket</label>
                    <input type="text" id="tbmTicketFilterInput" placeholder="e.g. TKT-2024-001…" oninput="onTbmTextFilterInput()"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
                    <div class="flex justify-end gap-2 mt-3">
                        <button type="button" onclick="clearTbmTextFilter('ticket')" class="px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50">Clear</button>
                    </div>
                </div>
            </div>

            {{-- Description: keyword search --}}
            <div class="relative">
                <button type="button" id="tbmDescFilterBtn" onclick="toggleTbmPopover(event, 'tbmDescFilterPanel')" class="tbm-filter-btn">
                    <i class="fas fa-align-left text-[10px] text-gray-400"></i>
                    <span>Description</span>
                    <svg id="tbmDescFilterIcon" class="w-3 h-3 text-gray-300 transition-colors" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 011 1v1.586a1 1 0 01-.293.707l-4.121 4.121A1 1 0 0012 12.121V15.5l-4 1.5v-4.879a1 1 0 00-.293-.707L3.586 7.293A1 1 0 013.293 6.586L3 5z" clip-rule="evenodd" />
                    </svg>
                </button>
                <div id="tbmDescFilterPanel" class="tbm-popover hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[60] p-3" style="min-width:260px;">
                    <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Search description</label>
                    <input type="text" id="tbmDescFilterInput" placeholder="Type keyword (case-insensitive)…" oninput="onTbmTextFilterInput()"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
                    <div class="flex justify-end gap-2 mt-3">
                        <button type="button" onclick="clearTbmTextFilter('desc')" class="px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50">Clear</button>
                    </div>
                </div>
            </div>

            {{-- Status: multi-select dropdown --}}
            <div class="custom-dd relative" id="ddTbmStatus" data-multi="true" data-onchange="applyTbmColFilter" data-placeholder="Status">
                <button type="button" class="custom-dd-btn tbm-filter-btn">
                    <i class="fas fa-flag text-[10px] text-gray-400"></i>
                    <span class="custom-dd-label text-gray-500">Status</span>
                    <svg class="custom-dd-arrow w-3 h-3 text-gray-400 transition-all duration-200 shrink-0 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <input type="hidden" id="tbmColStatus" value="">
                <div class="custom-dd-panel hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[60] py-1.5 overflow-y-auto" style="max-height:260px;min-width:220px;">
                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="">All</button>
                    <button type="button" class="custom-dd-item w-full flex items-center justify-between gap-2 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="open"><span class="custom-dd-item-text">Open</span><svg class="custom-dd-check w-4 h-4 text-red-500 opacity-0 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg></button>
                    <button type="button" class="custom-dd-item w-full flex items-center justify-between gap-2 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="inprocess"><span class="custom-dd-item-text">Inprocess</span><svg class="custom-dd-check w-4 h-4 text-red-500 opacity-0 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg></button>
                    <button type="button" class="custom-dd-item w-full flex items-center justify-between gap-2 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="waiting_on_customer"><span class="custom-dd-item-text">Waiting on Customer</span><svg class="custom-dd-check w-4 h-4 text-red-500 opacity-0 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg></button>
                    <button type="button" class="custom-dd-item w-full flex items-center justify-between gap-2 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="waiting_on_3rd_party"><span class="custom-dd-item-text">Waiting on 3rd Party</span><svg class="custom-dd-check w-4 h-4 text-red-500 opacity-0 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg></button>
                    <button type="button" class="custom-dd-item w-full flex items-center justify-between gap-2 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="waiting_to_confirmation"><span class="custom-dd-item-text">Waiting to Confirmation</span><svg class="custom-dd-check w-4 h-4 text-red-500 opacity-0 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg></button>
                    <button type="button" class="custom-dd-item w-full flex items-center justify-between gap-2 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="hold"><span class="custom-dd-item-text">Hold</span><svg class="custom-dd-check w-4 h-4 text-red-500 opacity-0 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg></button>
                    <button type="button" class="custom-dd-item w-full flex items-center justify-between gap-2 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="cancelled"><span class="custom-dd-item-text">Cancelled</span><svg class="custom-dd-check w-4 h-4 text-red-500 opacity-0 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg></button>
                    <button type="button" class="custom-dd-item w-full flex items-center justify-between gap-2 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="closed"><span class="custom-dd-item-text">Closed</span><svg class="custom-dd-check w-4 h-4 text-red-500 opacity-0 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg></button>
                </div>
            </div>

            {{-- Ticket Lead: single-select searchable dropdown (populated from data) --}}
            <div class="custom-dd relative" id="ddTbmLead" data-searchable="true" data-onchange="applyTbmColFilter">
                <button type="button" class="custom-dd-btn tbm-filter-btn">
                    <i class="fas fa-user text-[10px] text-gray-400"></i>
                    <span class="custom-dd-label text-gray-500">Ticket Lead</span>
                    <svg class="custom-dd-arrow w-3 h-3 text-gray-400 transition-all duration-200 shrink-0 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <input type="hidden" id="tbmColLead" value="">
                <div class="custom-dd-panel hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[60] py-1.5 overflow-y-auto" style="max-height:260px;min-width:200px;">
                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="">All</button>
                </div>
            </div>

            {{-- Modul: single-select searchable dropdown (populated from data) --}}
            <div class="custom-dd relative" id="ddTbmModul" data-searchable="true" data-onchange="applyTbmColFilter">
                <button type="button" class="custom-dd-btn tbm-filter-btn">
                    <i class="fas fa-puzzle-piece text-[10px] text-gray-400"></i>
                    <span class="custom-dd-label text-gray-500">Modul</span>
                    <svg class="custom-dd-arrow w-3 h-3 text-gray-400 transition-all duration-200 shrink-0 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <input type="hidden" id="tbmColModul" value="">
                <div class="custom-dd-panel hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[60] py-1.5 overflow-y-auto" style="max-height:260px;min-width:200px;">
                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="">All</button>
                </div>
            </div>

            <button type="button" id="tbmClearAllBtn" onclick="resetTbmFilters()" class="hidden inline-flex items-center gap-1 text-xs font-semibold text-red-700 hover:underline ml-1">
                <i class="fas fa-times"></i>Clear Filters
            </button>
        </div>

        {{-- Summary bar --}}
        <div class="flex items-center justify-between gap-3 mt-3 text-sm text-gray-500">
            <span id="tbmSummaryText">Loading...</span>
            <div class="flex items-center gap-3">
                <button type="button" onclick="setAllTbmCards(true)" class="text-xs font-semibold text-red-800 hover:underline">Expand All</button>
                <button type="button" onclick="setAllTbmCards(false)" class="text-xs font-semibold text-gray-500 hover:underline">Collapse All</button>
            </div>
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

/* Filter toolbar */
.tbm-filter-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 10px; background: #fff; border: 1px solid #e5e7eb; border-radius: 10px;
    font-size: 12px; font-weight: 600; color: #4b5563; cursor: pointer; transition: all 0.15s;
}
.tbm-filter-btn:hover { background: #f9fafb; border-color: #d1d5db; }
.custom-dd.tbm-dd-active .custom-dd-arrow { color: #dc2626; }
.custom-dd.tbm-dd-active .custom-dd-btn { border-color: #fecaca; background: #fff8f8; }
.custom-dd.tbm-dd-active .custom-dd-btn span,
.custom-dd.tbm-dd-active .custom-dd-btn .custom-dd-label { color: #dc2626 !important; font-weight: 700; }
</style>
@endpush

@push('scripts')
@php
$customDdPath = public_path('js/custom-dropdown.js');
$customDdVer = file_exists($customDdPath) ? filemtime($customDdPath) : time();
@endphp
<script src="/js/custom-dropdown.js?v={{ $customDdVer }}"></script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    if (typeof initCustomDropdowns === 'function') initCustomDropdowns();
    loadTicketByModule();
});

let tbmAllGroups = [];
let tbmFilters = {
    ticketKw: '',
    descKw: '',
    statuses: [],
    lead: '',
    modul: '',
};

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
        populateTbmLeadFilter();
        populateTbmModulFilter();
        applyTbmFilters();
    } catch (e) {
        console.error(e);
        summary.textContent = 'Failed to load data.';
        cards.innerHTML = `<div class="text-center py-10 text-red-500 text-sm">
            <i class="fas fa-exclamation-circle text-xl block mb-2"></i>${escHtml(e.message)}
        </div>`;
    }
}

// ── Column filter option population ─────────────────────────────────
function populateTbmLeadFilter() {
    const ddEl = document.getElementById('ddTbmLead');
    if (!ddEl) return;
    const panel = ddEl._ddPanel || ddEl.querySelector('.custom-dd-panel');
    if (!panel) return;
    panel.querySelectorAll('.custom-dd-item').forEach(el => el.remove());

    let hasUnassigned = false;
    const seen = new Set();
    const names = [];
    tbmAllGroups.forEach(g => g.tickets.forEach(t => {
        if (t.lead_name) {
            if (!seen.has(t.lead_name)) { seen.add(t.lead_name); names.push(t.lead_name); }
        } else {
            hasUnassigned = true;
        }
    }));
    names.sort((a, b) => a.localeCompare(b));

    const fragment = document.createDocumentFragment();
    fragment.appendChild(tbmMakeDdItem('', 'All'));
    names.forEach(n => fragment.appendChild(tbmMakeDdItem(n, n)));
    if (hasUnassigned) fragment.appendChild(tbmMakeDdItem('__unassigned__', 'Unassigned', true));

    const emptyEl = panel._ddEmpty || null;
    if (emptyEl) panel.insertBefore(fragment, emptyEl);
    else panel.appendChild(fragment);
}

function populateTbmModulFilter() {
    const ddEl = document.getElementById('ddTbmModul');
    if (!ddEl) return;
    const panel = ddEl._ddPanel || ddEl.querySelector('.custom-dd-panel');
    if (!panel) return;
    panel.querySelectorAll('.custom-dd-item').forEach(el => el.remove());

    const fragment = document.createDocumentFragment();
    fragment.appendChild(tbmMakeDdItem('', 'All'));
    tbmAllGroups.forEach(g => {
        fragment.appendChild(tbmMakeDdItem(g.module_name, g.module_name, g.module_name === 'No Modul Assign'));
    });

    const emptyEl = panel._ddEmpty || null;
    if (emptyEl) panel.insertBefore(fragment, emptyEl);
    else panel.appendChild(fragment);
}

function tbmMakeDdItem(val, text, italic) {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50' + (italic ? ' italic text-gray-400' : '');
    btn.dataset.value = val;
    btn.textContent = text;
    return btn;
}

// ── Filter state wiring ──────────────────────────────────────────────
function applyTbmColFilter() {
    tbmFilters.statuses = (document.getElementById('tbmColStatus')?.value || '').split(',').filter(Boolean);
    tbmFilters.lead = document.getElementById('tbmColLead')?.value || '';
    tbmFilters.modul = document.getElementById('tbmColModul')?.value || '';
    applyTbmFilters();
}

let _tbmTextFilterTimer = null;
function onTbmTextFilterInput() {
    clearTimeout(_tbmTextFilterTimer);
    _tbmTextFilterTimer = setTimeout(() => {
        tbmFilters.ticketKw = document.getElementById('tbmTicketFilterInput')?.value || '';
        tbmFilters.descKw = document.getElementById('tbmDescFilterInput')?.value || '';
        applyTbmFilters();
    }, 250);
}

function clearTbmTextFilter(which) {
    if (which === 'ticket') {
        document.getElementById('tbmTicketFilterInput').value = '';
        tbmFilters.ticketKw = '';
    } else {
        document.getElementById('tbmDescFilterInput').value = '';
        tbmFilters.descKw = '';
    }
    applyTbmFilters();
}

// ── Popover open/close helpers ───────────────────────────────────────
function toggleTbmPopover(ev, panelId) {
    ev?.stopPropagation();
    const panel = document.getElementById(panelId);
    if (!panel) return;
    const isOpen = !panel.classList.contains('hidden');
    closeTbmPopovers();
    if (typeof _closeAllDropdowns === 'function') _closeAllDropdowns();
    if (!isOpen) panel.classList.remove('hidden');
}

function closeTbmPopovers() {
    document.querySelectorAll('.tbm-popover').forEach(p => p.classList.add('hidden'));
}

// Closing a text-search popover doesn't close open custom-dd panels (and
// vice versa) because both sides call stopPropagation() on their trigger
// click — this capture-phase listener runs before that and bridges the two.
document.getElementById('tbmToolbar')?.addEventListener('click', (e) => {
    if (e.target.closest('.custom-dd-btn')) closeTbmPopovers();
}, true);

document.addEventListener('click', (e) => {
    document.querySelectorAll('.tbm-popover:not(.hidden)').forEach(panel => {
        const wrap = panel.closest('.relative');
        if (wrap && !wrap.contains(e.target)) panel.classList.add('hidden');
    });
});
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeTbmPopovers();
});

// ── Core filtering + render pipeline ─────────────────────────────────
function tbmDayOnClose(created) {
    return created ? Math.max(0, Math.ceil((Date.now() - new Date(created).getTime()) / 86400000)) : -1;
}

function computeFilteredGroups() {
    let groups = tbmAllGroups;
    if (tbmFilters.modul) groups = groups.filter(g => g.module_name === tbmFilters.modul);

    const kw = tbmFilters.ticketKw.trim().toLowerCase();
    const dkw = tbmFilters.descKw.trim().toLowerCase();

    return groups.map(g => {
        let tickets = g.tickets;
        if (tbmFilters.statuses.length) tickets = tickets.filter(t => tbmFilters.statuses.includes(t.status));
        if (kw) tickets = tickets.filter(t => (t.ticket_number || '').toLowerCase().includes(kw));
        if (dkw) tickets = tickets.filter(t => (t.description || '').toLowerCase().includes(dkw));
        if (tbmFilters.lead) {
            tickets = tickets.filter(t => tbmFilters.lead === '__unassigned__' ? !t.lead_name : t.lead_name === tbmFilters.lead);
        }
        return { module_name: g.module_name, tickets };
    }).filter(g => g.tickets.length > 0);
}

function applyTbmFilters() {
    renderTicketByModule(computeFilteredGroups());
    updateTbmFilterIndicators();
}

function isTbmFilterActive() {
    return !!(tbmFilters.ticketKw.trim() || tbmFilters.descKw.trim() || tbmFilters.statuses.length
        || tbmFilters.lead || tbmFilters.modul);
}

function updateTbmFilterIndicators() {
    setTbmIconActive('tbmTicketFilterIcon', !!tbmFilters.ticketKw.trim());
    setTbmIconActive('tbmDescFilterIcon', !!tbmFilters.descKw.trim());

    document.getElementById('ddTbmStatus')?.classList.toggle('tbm-dd-active', tbmFilters.statuses.length > 0);
    document.getElementById('ddTbmLead')?.classList.toggle('tbm-dd-active', !!tbmFilters.lead);
    document.getElementById('ddTbmModul')?.classList.toggle('tbm-dd-active', !!tbmFilters.modul);

    document.getElementById('tbmClearAllBtn')?.classList.toggle('hidden', !isTbmFilterActive());
}

function setTbmIconActive(id, active) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.toggle('text-red-500', active);
    el.classList.toggle('text-gray-300', !active);
}

function resetTbmFilters() {
    tbmFilters = { ticketKw: '', descKw: '', statuses: [], lead: '', modul: '' };

    document.getElementById('tbmTicketFilterInput').value = '';
    document.getElementById('tbmDescFilterInput').value = '';

    if (typeof clearCustomDropdownMulti === 'function') clearCustomDropdownMulti('tbmColStatus');
    if (typeof setCustomDropdownValue === 'function') {
        setCustomDropdownValue('tbmColLead', '');
        setCustomDropdownValue('tbmColModul', '');
    }

    applyTbmFilters();
}

function renderTicketByModule(groups) {
    const cards   = document.getElementById('tbmCards');
    const empty   = document.getElementById('tbmEmpty');
    const summary = document.getElementById('tbmSummaryText');

    if (!groups.length) {
        cards.innerHTML = '';
        empty.classList.remove('hidden');
        summary.textContent = isTbmFilterActive() ? 'No tickets match the selected filters.' : 'No modules found.';
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
    const dayOnClose = tbmDayOnClose(created);
    const sInfo = TBM_STATUS_MAP[ticket.status] || { label: ticket.status_label || ticket.status || '—', cls: 'bg-gray-100 text-gray-500' };

    return `
    <tr class="cursor-pointer hover:bg-gray-50" onclick="window.location='/ticket/${ticket.ticket_id}'">
        <td class="tbm-td text-sm font-semibold text-gray-700">${escHtml(ticket.ticket_number || '—')}</td>
        <td class="tbm-td tbm-td-desc text-sm text-gray-700">${escHtml(ticket.description || '—')}</td>
        <td class="tbm-td"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${sInfo.cls}">${escHtml(sInfo.label)}</span></td>
        <td class="tbm-td text-sm text-gray-700">${ticket.lead_name ? escHtml(ticket.lead_name) : '<span class="text-gray-300 italic">Unassigned</span>'}</td>
        <td class="tbm-td text-sm text-gray-700">${escHtml(moduleName)}</td>
        <td class="tbm-td text-xs text-gray-500">${dateStr}</td>
        <td class="tbm-td text-right text-sm font-semibold text-gray-700">${dayOnClose < 0 ? '—' : dayOnClose}</td>
    </tr>`;
}

function escHtml(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
</script>
@endpush

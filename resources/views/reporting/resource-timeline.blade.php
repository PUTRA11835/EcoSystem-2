@extends('dashboard')
@section('title', 'Resource Timeline')
@section('page-title', 'Resource Timeline')
@section('page-subtitle', 'Daily customer assignment per SAP / Salesforce Consultant / PMO')

@section('content')

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-3">
    <div>
        <h2 class="text-xl font-bold text-gray-900">Resource Timeline</h2>
        <p class="text-sm text-gray-500 mt-0.5">Where every SAP / Salesforce Consultant is assigned, day by day. Click a consultant's row to create or edit their timeline.</p>
    </div>
    <div id="rtToolbar" class="flex items-center gap-2 flex-wrap sm:flex-nowrap">
        <input type="search" id="rtCustomerSearch" placeholder="Search customer code…" autocomplete="off"
               oninput="rtRender()" style="width:170px !important;"
               class="px-3 py-1.5 border border-gray-200 rounded-xl text-xs font-semibold text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
        <div class="rt-tb-select" style="width:150px;">
        <select id="rtHomeBase" onchange="rtLoadGrid()"
                class="px-3 py-1.5 border border-gray-200 rounded-xl text-xs font-semibold text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
            <option value="">All Home Base</option>
            @foreach ($homeBaseOptions as $hb)
                <option value="{{ $hb }}">{{ $hb }}</option>
            @endforeach
        </select>
        </div>
        <div class="rt-tb-select" style="width:110px;">
        <select id="rtView" onchange="rtOnViewChange()"
                class="px-3 py-1.5 border border-gray-200 rounded-xl text-xs font-semibold text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
            <option value="monthly">Monthly</option>
            <option value="yearly">Yearly</option>
        </select>
        </div>
        <div class="rt-tb-select" id="rtMonthWrap" style="width:120px;">
        <select id="rtMonth" onchange="rtLoadGrid()"
                class="px-3 py-1.5 border border-gray-200 rounded-xl text-xs font-semibold text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
            @foreach (['January','February','March','April','May','June','July','August','September','October','November','December'] as $i => $m)
                <option value="{{ $i + 1 }}">{{ $m }}</option>
            @endforeach
        </select>
        </div>
        <input type="number" id="rtYear" min="2000" max="2100" style="width:80px !important;"
               class="px-3 py-1.5 border border-gray-200 rounded-xl text-xs font-semibold text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400"
               onchange="rtLoadGrid()">
    </div>
</div>

<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="flex items-center justify-between px-4 py-2.5 border-b border-gray-100 bg-gray-50/60">
        <span id="rtSummary" class="text-xs text-gray-500 shrink-0">Loading…</span>
        <div id="rtLegend" class="flex flex-wrap justify-end gap-1.5 ml-4"></div>
    </div>

    <div class="overflow-x-auto touch-pan-x" id="rtScroll">
        <table class="border-collapse" id="rtTable">
            <thead>
                <tr id="rtHeadRow" class="bg-gray-50"></tr>
            </thead>
            <tbody id="rtBody" class="divide-y divide-gray-100"></tbody>
        </table>
    </div>
</div>

{{-- ── Column search panel (Consultant / Module) — lives outside the table so it
     survives renderHead() re-rendering the header row --}}
<div id="rtSearchPanel" class="hidden fixed bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] p-3" style="min-width:220px;">
    <label id="rtSearchLabel" class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Search</label>
    <input type="text" id="rtSearchInput" oninput="rtApplySearch()" autocomplete="off"
           class="w-full px-3 py-1.5 border border-gray-300 rounded-md text-sm font-normal text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
    <div class="border-t border-gray-100 mt-3 pt-3">
        <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-2">Sort</label>
        <div class="flex gap-2">
            <button type="button" id="rtSortAsc" onclick="rtSetSort('asc')"
                    class="flex-1 px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50 transition-colors">↑ Ascending</button>
            <button type="button" id="rtSortDesc" onclick="rtSetSort('desc')"
                    class="flex-1 px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50 transition-colors">↓ Descending</button>
        </div>
    </div>
    <div class="flex justify-end gap-2 mt-3">
        <button type="button" onclick="rtClearSearch()" class="px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50">Clear</button>
    </div>
</div>

{{-- ── Create Timeline modal ─────────────────────────────────────────────── --}}
<div id="rtModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <h3 class="text-base font-bold text-gray-900">Create Timeline</h3>
            <button onclick="rtCloseModal()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <form id="rtForm" class="px-5 py-4 space-y-4" onsubmit="rtSubmitForm(event)">
            <input type="hidden" id="rtFormMode" value="create">

            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Consultant</label>
                {{-- Fixed to the consultant whose name was clicked in the grid --}}
                <input type="hidden" id="rtConsultantSelect" value="">
                <p id="rtConsultantName" class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm font-semibold text-gray-800"></p>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Start Date</label>
                    <input type="date" id="rtStartDate" required onchange="rtOnStartDateChange()"
                           class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">End Date</label>
                    <input type="date" id="rtEndDate" required
                           class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Customer Code</label>
                <select id="rtLocation" data-searchable="true" data-search-placeholder="Search customer code…"
                        class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
                    <option value="">Select customer code…</option>
                </select>
                <p class="text-[11px] text-gray-400 mt-1">Overlapping dates with another project are kept side by side. Leave blank to clear every customer in the selected date range.</p>
            </div>

            <p id="rtFormError" class="hidden text-xs text-red-600"></p>

            <div class="flex items-center gap-2">
                <button type="submit" id="rtSaveBtn" class="px-4 py-2 primary-gradient text-white text-xs font-semibold rounded-xl hover:opacity-90 transition-all duration-200 disabled:opacity-60 disabled:cursor-not-allowed">
                    Save
                </button>
                <button type="button" id="rtCancelEditBtn" onclick="rtResetForm()"
                        class="hidden px-4 py-2 bg-white border border-gray-200 text-gray-600 text-xs font-semibold rounded-xl hover:bg-gray-50 transition-all">
                    Cancel Edit
                </button>
            </div>
        </form>

        <div class="px-5 pb-5">
            <h4 class="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-2">Existing entries</h4>
            <div id="rtEntriesList" class="space-y-1.5 max-h-56 overflow-y-auto">
                <p class="text-xs text-gray-400">Select a consultant to see their assigned dates.</p>
            </div>
        </div>
    </div>
</div>

<style>
    /* Compact the auto-enhanced (select-enhance.js) dropdowns in the toolbar */
    #rtToolbar .se-btn { padding: 0.375rem 0.75rem; font-size: 0.75rem; font-weight: 600; border-radius: 0.75rem; border-color: #e5e7eb; }
    #rtToolbar .se-item { font-size: 0.75rem; padding: 0.375rem 0.75rem; }
    #rtTable th, #rtTable td {
        white-space: nowrap;
        box-sizing: border-box;
    }
    .rt-sticky {
        position: sticky;
        background-color: #fff;
        z-index: 10;
    }
    /* `background: inherit` isn't reliable here — Tailwind's CDN stylesheet is
       injected at an unpredictable point relative to this block, so cascade
       order against `bg-white`/`bg-gray-50` isn't guaranteed. Hardcoding the
       actual color avoids the scrolled date columns bleeding through. */
    thead .rt-sticky { z-index: 30; background-color: #f9fafb; }
    tbody tr:hover .rt-sticky { background-color: #f9fafb; }

    /* `overflow-x: auto` on #rtScroll forces its computed `overflow-y` to
       `auto` too (CSS2.1 overflow interaction — can't have one axis `auto`
       and the other `visible`), which silently makes #rtScroll itself the
       sticky containing block instead of the viewport, breaking a
       window-scroll sticky header. Fix: make #rtScroll a bounded, genuinely
       self-scrolling box (height set in JS) so `top: 0` sticks correctly
       within it — the standard pattern for a data grid with a sticky header. */
    #rtScroll {
        overflow-y: auto;
    }
    #rtTable thead th {
        position: sticky;
        top: 0;
        z-index: 20;
    }
    #rtTable thead th.rt-sticky { z-index: 30; }
    #rtTable .rt-col-no      { left: 0px;   width: 48px;  min-width: 48px; }
    #rtTable .rt-col-name    { left: 48px;  width: 190px; min-width: 190px; }
    #rtTable .rt-col-module  { left: 238px; width: 160px; min-width: 160px; }
    #rtTable .rt-col-status  { left: 398px; width: 160px; min-width: 160px; }
    #rtTable .rt-col-day     { width: 64px; min-width: 64px; text-align: center; }
    #rtTable .rt-col-month   { width: 120px; min-width: 120px; text-align: center; }
    .rt-weekend { background-color: #fef2f2; }
    /* Monthly: one coloured band per project on that day (2+ = stacked) */
    .rt-band { padding: 6px 4px; font-weight: 600; text-align: center; }
    /* Yearly: lanes hold position-filled customer bars inside a month cell */
    .rt-lane { position: relative; height: 24px; }
    .rt-seg  { position: absolute; top: 0; bottom: 0; padding: 0 4px; font-size: 11px; font-weight: 600;
               line-height: 24px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; text-align: center; }
</style>

<script>
(function () {
    'use strict';

    const RT_MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    let rtView = 'monthly';   // 'monthly' | 'yearly'
    let rtYear = new Date().getFullYear(); // year of the grid currently shown
    let rtDays = [];
    let rtEditingRange = null; // {employee_id, start, end} when editing an existing range
    let rtRows = [];          // rows as returned by the server (default order)
    let rtSort = { key: null, dir: 'asc' }; // key: 'name' | 'module_label' | null (default order)
    let rtSearch = { name: '', module_label: '' };
    let rtSearchKey = null;   // column whose search panel is currently open

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    async function rtFetch(url, options = {}) {
        const response = await fetch(url, Object.assign({
            headers: Object.assign({
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            }, options.body ? { 'Content-Type': 'application/json' } : {}),
            credentials: 'same-origin',
        }, options));
        return response.json();
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text ?? '';
        return div.innerHTML;
    }

    function formatRangeLabel(start, end) {
        const opts = { day: '2-digit', month: 'short', year: 'numeric' };
        const s = new Date(start + 'T00:00:00').toLocaleDateString('en-GB', opts);
        const e = new Date(end + 'T00:00:00').toLocaleDateString('en-GB', opts);
        return start === end ? s : `${s} – ${e}`;
    }

    // ── Grid ──────────────────────────────────────────────────────────────
    window.rtLoadGrid = async function () {
        const month = document.getElementById('rtMonth').value;
        const year = document.getElementById('rtYear').value;
        const summary = document.getElementById('rtSummary');
        summary.textContent = 'Loading…';

        const view = rtView;
        const params = new URLSearchParams({ month, year, view });
        const homeBase = document.getElementById('rtHomeBase').value;
        if (homeBase) params.set('home_base', homeBase);

        const result = await rtFetch(`/api/reporting/resource-timeline/grid?${params.toString()}`);
        if (view !== rtView) return; // user switched view while this request was in flight
        if (!result.success) {
            summary.textContent = result.message || 'Failed to load timeline.';
            return;
        }

        rtDays = result.days || [];
        rtYear = Number(result.year);
        rtRows = result.rows;
        renderHead();
        rtRender();
    };

    window.rtOnViewChange = function () {
        rtView = document.getElementById('rtView').value;
        // Month picker is meaningless for the yearly view.
        document.getElementById('rtMonthWrap').classList.toggle('hidden', rtView === 'yearly');
        rtLoadGrid();
    };

    // ── Customer colours ──────────────────────────────────────────────────
    // Deterministic soft pastel per customer code, so the same customer has
    // the same colour in every row and in both views.
    function rtColorStyle(code) {
        let h = 0;
        for (const ch of String(code)) h = (h * 31 + ch.charCodeAt(0)) % 360;
        return `background:hsl(${h},70%,85%);color:hsl(${h},45%,24%);`;
    }

    // Place month segments on lanes so that overlapping customers stack
    // vertically while sequential ones share a lane.
    function rtPackLanes(items) {
        const segs = [];
        items.forEach(it => it.segments.forEach(([s, e]) => segs.push({ loc: it.location, s, e })));
        segs.sort((a, b) => a.s - b.s || a.e - b.e);

        const lanes = [];
        segs.forEach(seg => {
            const lane = lanes.find(l => l[l.length - 1].e < seg.s);
            if (lane) lane.push(seg); else lanes.push([seg]);
        });
        return lanes;
    }

    function rtRenderLegend(rows) {
        const codes = new Set();
        rows.forEach(r => {
            if (rtView === 'yearly') {
                Object.values(r.months || {}).forEach(items => items.forEach(it => codes.add(it.location)));
            } else {
                Object.values(r.dates || {}).forEach(locs => locs.forEach(l => codes.add(l)));
            }
        });
        document.getElementById('rtLegend').innerHTML = [...codes]
            .sort((a, b) => a.localeCompare(b, undefined, { sensitivity: 'base' }))
            .map(c => `<span class="px-2 py-0.5 rounded text-[10px] font-semibold" style="${rtColorStyle(c)}">${escapeHtml(c)}</span>`)
            .join('');
    }

    window.rtRender = rtRender; // used by the toolbar customer-code search input

    // Customer codes present on a row in the current view (monthly or yearly).
    function rtRowCodes(r) {
        return rtView === 'yearly'
            ? Object.values(r.months || {}).flatMap(items => items.map(it => it.location))
            : Object.values(r.dates || {}).flat();
    }

    // Apply the column search + sort to the server rows and redraw the body.
    // "No" is re-numbered by displayed position so it stays contiguous.
    function rtRender() {
        const customerQ = document.getElementById('rtCustomerSearch').value.trim().toLowerCase();
        let rows = rtRows.filter(r =>
            ['name', 'module_label'].every(k =>
                !rtSearch[k] || (r[k] || '').toLowerCase().includes(rtSearch[k].toLowerCase())
            ) && (!customerQ || rtRowCodes(r).some(c => c.toLowerCase().includes(customerQ)))
        );

        if (rtSort.key) {
            const dir = rtSort.dir === 'asc' ? 1 : -1;
            const key = rtSort.key;
            rows = rows.slice().sort((a, b) =>
                dir * (a[key] || '').localeCompare(b[key] || '', undefined, { numeric: true, sensitivity: 'base' })
            );
        }

        rows = rows.map((r, i) => Object.assign({}, r, { no: i + 1 }));
        renderBody(rows);
        rtRenderLegend(rows);

        const filtered = rows.length !== rtRows.length;
        document.getElementById('rtSummary').textContent =
            `${filtered ? rows.length + ' of ' + rtRows.length : rtRows.length} Consultant${rtRows.length === 1 ? '' : 's'}`;
    }

    // Same header pattern as the Ticket list: one button (label + sort glyph +
    // caret) that opens a small panel holding the search box and sort buttons.
    function rtSearchableHeader(key, label, cls) {
        const sorted = rtSort.key === key;
        const glyph = sorted ? (rtSort.dir === 'asc' ? '↑' : '↓') : '⇅';
        const active = sorted || !!rtSearch[key];
        return `
            <th class="rt-sticky ${cls} p-0 text-left border-b border-gray-200 bg-gray-50">
                <button type="button" id="rtHeadBtn_${key}" onclick="rtTogglePanel(event, '${key}', '${label}')"
                        class="w-full flex items-center gap-1.5 px-2 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                    <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">${label}</span>
                    <span class="${active ? 'text-red-500' : 'text-gray-300'} font-normal normal-case tracking-normal text-xs">${glyph}</span>
                    <svg class="w-3.5 h-3.5 ${rtSearch[key] ? 'text-red-500' : 'text-gray-500'} shrink-0 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
            </th>`;
    }

    function renderHead() {
        const row = document.getElementById('rtHeadRow');

        let html = `
            <th class="rt-sticky rt-col-no px-2 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wide border-b border-gray-200 bg-gray-50">No</th>
            ${rtSearchableHeader('name', 'Consultant', 'rt-col-name')}
            ${rtSearchableHeader('module_label', 'Module', 'rt-col-module')}
            <th class="rt-sticky rt-col-status px-2 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wide border-b border-gray-200 bg-gray-50">Status</th>
        `;

        html += rtView === 'yearly'
            ? RT_MONTHS.map(m => `
                <th class="rt-col-month px-1 py-2.5 text-[11px] font-semibold text-gray-500 uppercase tracking-wide border-b border-gray-200 bg-gray-50">${m}</th>
            `).join('')
            : rtDays.map(d => `
            <th class="rt-col-day px-1 py-2.5 text-[11px] font-semibold text-gray-500 uppercase tracking-wide border-b border-gray-200 bg-gray-50 ${d.is_weekend ? 'rt-weekend' : ''}">
                <div>${d.day}</div>
                <div class="text-[9px] font-normal normal-case text-gray-400">${d.label}</div>
            </th>
        `).join('');

        row.innerHTML = html;
    }

    function renderBody(rows) {
        const tbody = document.getElementById('rtBody');

        if (!rows.length) {
            tbody.innerHTML = `<tr><td colspan="${4 + (rtView === 'yearly' ? 12 : rtDays.length)}" class="text-center py-8 text-sm text-gray-400">No consultants found.</td></tr>`;
            return;
        }

        tbody.innerHTML = rows.map(r => {
            const fixedCells = `
                <td class="rt-sticky rt-col-no bg-white px-2 py-2 text-xs text-gray-500">${r.no}</td>
                <td class="rt-sticky rt-col-name bg-white px-2 py-2 text-xs font-medium text-gray-800 truncate">${escapeHtml(r.name)}</td>
                <td class="rt-sticky rt-col-module bg-white px-2 py-2 text-xs text-gray-600 truncate">${escapeHtml(r.module_label)}</td>
                <td class="rt-sticky rt-col-status bg-white px-2 py-2 text-xs font-semibold ${r.is_lead ? 'text-red-600' : 'text-gray-400'} truncate">${escapeHtml(r.status_label)}</td>
            `;

            // Yearly: one cell per month. Each customer is a coloured bar that
            // starts/ends at the position of its first/last day in the month
            // (day 1–15 fills the left half, 16–31 the right half). Overlapping
            // customers are stacked on separate lanes.
            const monthCells = rtView === 'yearly' ? RT_MONTHS.map((_, i) => {
                const items = (r.months && r.months[i + 1]) || [];
                const dim = new Date(rtYear, i + 1, 0).getDate();
                const lanes = rtPackLanes(items);
                const inner = lanes.map(lane => `<div class="rt-lane">${lane.map(seg => {
                    const left = (seg.s - 1) / dim * 100;
                    const width = (seg.e - seg.s + 1) / dim * 100;
                    const range = seg.s === seg.e ? `${seg.s}` : `${seg.s}–${seg.e}`;
                    return `<div class="rt-seg" style="left:${left}%;width:${width}%;${rtColorStyle(seg.loc)}"
                                 title="${escapeHtml(seg.loc)} · ${range} ${RT_MONTHS[i]}">${escapeHtml(seg.loc)}</div>`;
                }).join('')}</div>`).join('');
                return `<td class="rt-col-month p-0 align-top">${inner}</td>`;
            }).join('') : '';

            // Monthly: a day with 2+ projects is split into one coloured band per project.
            const dayCells = rtView === 'yearly' ? monthCells : rtDays.map(d => {
                const locs = r.dates[d.date] || [];
                const inner = locs.map(l => `<div class="rt-band truncate" style="${rtColorStyle(l)}">${escapeHtml(l)}</div>`).join('');
                const cellCls = locs.length ? '' : (d.is_weekend ? 'rt-weekend' : '');
                return `<td class="rt-col-day p-0 align-top text-[11px] ${cellCls}" title="${escapeHtml(locs.join(' + '))}">${inner}</td>`;
            }).join('');

            return `<tr class="hover:bg-gray-50 transition-colors cursor-pointer" onclick="rtOpenModal(${Number(r.employee_id)})"
                        title="Click to create / edit timeline for ${escapeHtml(r.name)}">${fixedCells}${dayCells}</tr>`;
        }).join('');
    }

    // ── Column sort + search (Consultant / Module) ──────────────────────────
    // Sort applies to the column whose panel is open; Clear resets it back to
    // the default (module group / lead first) order.
    function rtMarkSortButtons() {
        const on = 'bg-red-50 text-red-700 border-red-200';
        ['asc', 'desc'].forEach(dir => {
            const btn = document.getElementById(dir === 'asc' ? 'rtSortAsc' : 'rtSortDesc');
            const active = rtSort.key === rtSearchKey && rtSort.dir === dir;
            on.split(' ').forEach(c => btn.classList.toggle(c, active));
        });
    }

    window.rtSetSort = function (dir) {
        if (!rtSearchKey) return;
        rtSort = { key: rtSearchKey, dir };
        rtMarkSortButtons();
        renderHead();
        rtRender();
    };

    function rtClosePanel() {
        document.getElementById('rtSearchPanel')?.classList.add('hidden');
        rtSearchKey = null;
    }

    window.rtTogglePanel = function (ev, key, label) {
        ev?.stopPropagation();
        const panel = document.getElementById('rtSearchPanel');

        if (!panel.classList.contains('hidden') && rtSearchKey === key) {
            rtClosePanel();
            return;
        }

        rtSearchKey = key;
        const input = document.getElementById('rtSearchInput');
        document.getElementById('rtSearchLabel').textContent = `Search ${label}`;
        input.placeholder = `Search ${label.toLowerCase()}…`;
        input.value = rtSearch[key];

        const rect = document.getElementById(`rtHeadBtn_${key}`).getBoundingClientRect();
        panel.style.top = (rect.bottom + 4) + 'px';
        panel.style.left = rect.left + 'px';
        panel.classList.remove('hidden');
        rtMarkSortButtons();
        input.focus();
    };

    window.rtApplySearch = function () {
        if (!rtSearchKey) return;
        rtSearch[rtSearchKey] = document.getElementById('rtSearchInput').value.trim();
        // Re-render only the body so the open panel/input keeps focus; the
        // header icon colour is refreshed when the panel closes.
        rtRender();
    };

    window.rtClearSearch = function () {
        if (rtSearchKey) {
            rtSearch[rtSearchKey] = '';
            if (rtSort.key === rtSearchKey) rtSort = { key: null, dir: 'asc' };
        }
        rtClosePanel();
        renderHead();
        rtRender();
    };

    document.addEventListener('click', function (e) {
        const panel = document.getElementById('rtSearchPanel');
        if (panel && !panel.classList.contains('hidden') && !panel.contains(e.target)) {
            rtClosePanel();
            renderHead();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && rtSearchKey) {
            rtClosePanel();
            renderHead();
        }
    });

    // ── Modal / consultant dropdown ─────────────────────────────────────────
    // Opened by clicking a consultant's name in the grid; the modal is then
    // locked to that consultant.
    window.rtOpenModal = function (employeeId) {
        const row = rtRows.find(r => Number(r.employee_id) === Number(employeeId));
        if (!row) return;

        rtResetForm();
        document.getElementById('rtConsultantSelect').value = row.employee_id;
        document.getElementById('rtConsultantName').textContent = row.name;
        document.getElementById('rtModal').classList.remove('hidden');
        rtOnConsultantChange();
        if (!document.getElementById('rtLocation').dataset.loaded) {
            rtLoadCustomerOptions();
        }
    };

    async function rtLoadCustomerOptions() {
        const select = document.getElementById('rtLocation');
        const result = await rtFetch('/api/reporting/resource-timeline/customers');
        if (!result.success) return;

        const current = select.value;
        select.innerHTML = '<option value="">Select customer code…</option>' +
            result.data.map(code => `<option value="${escapeHtml(code)}">${escapeHtml(code)}</option>`).join('');
        select.dataset.loaded = '1';
        if (current) rtSetLocationValue(current);
    }

    // Legacy entries may hold a free-text value that is not a customer code;
    // keep it selectable so editing/deleting that range still works.
    function rtSetLocationValue(value) {
        const select = document.getElementById('rtLocation');
        if (value && ![...select.options].some(o => o.value === value)) {
            const opt = document.createElement('option');
            opt.value = value;
            opt.textContent = value;
            select.appendChild(opt);
        }
        select.value = value;
    }

    // Closes only via the header's X button (rtCloseModal) — intentionally no
    // backdrop-click or Escape handler, so an accidental click outside the
    // form never discards an in-progress entry.
    window.rtCloseModal = function () {
        document.getElementById('rtModal').classList.add('hidden');
    };

    window.rtOnStartDateChange = function () {
        const endInput = document.getElementById('rtEndDate');
        endInput.min = document.getElementById('rtStartDate').value;
        if (endInput.value && endInput.value < endInput.min) {
            endInput.value = endInput.min;
        }
    };

    window.rtOnConsultantChange = async function () {
        const employeeId = document.getElementById('rtConsultantSelect').value;
        const list = document.getElementById('rtEntriesList');

        if (!employeeId) {
            list.innerHTML = '<p class="text-xs text-gray-400">Select a consultant to see their assigned dates.</p>';
            return;
        }

        list.innerHTML = '<p class="text-xs text-gray-400">Loading…</p>';
        const result = await rtFetch(`/api/reporting/resource-timeline/entries?employee_id=${employeeId}`);
        if (!result.success) {
            list.innerHTML = `<p class="text-xs text-red-500">${escapeHtml(result.message || 'Failed to load entries.')}</p>`;
            return;
        }

        if (!result.data.length) {
            list.innerHTML = '<p class="text-xs text-gray-400">No entries yet for this consultant.</p>';
            return;
        }

        list.innerHTML = result.data.map(range => `
            <div class="flex items-center justify-between gap-2 px-3 py-2 bg-gray-50 rounded-lg border border-gray-100">
                <div class="min-w-0">
                    <p class="text-xs font-semibold text-gray-700 truncate">${escapeHtml(range.location)}</p>
                    <p class="text-[11px] text-gray-400">${formatRangeLabel(range.start, range.end)}</p>
                </div>
                <div class="flex items-center gap-1 shrink-0">
                    <button type="button" title="Edit"
                            onclick="rtEditRange('${employeeId}','${range.start}','${range.end}','${escapeHtml(range.location).replace(/'/g, "\\'")}')"
                            class="p-1.5 text-blue-600 hover:bg-blue-100 rounded transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                    </button>
                    <button type="button" title="Delete"
                            onclick="rtDeleteRange('${employeeId}','${range.start}','${range.end}','${escapeHtml(range.location).replace(/'/g, "\\'")}')"
                            class="p-1.5 text-red-600 hover:bg-red-100 rounded transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                </div>
            </div>
        `).join('');
    };

    window.rtEditRange = function (employeeId, start, end, location) {
        rtEditingRange = { employee_id: employeeId, start, end, location };
        document.getElementById('rtFormMode').value = 'edit';
        document.getElementById('rtConsultantSelect').value = employeeId;
        rtSetLocationValue(location);
        document.getElementById('rtStartDate').value = start;
        document.getElementById('rtEndDate').value = end;
        document.getElementById('rtEndDate').min = start;
        document.getElementById('rtCancelEditBtn').classList.remove('hidden');
    };

    window.rtResetForm = function () {
        rtEditingRange = null;
        document.getElementById('rtFormMode').value = 'create';
        document.getElementById('rtLocation').value = '';
        document.getElementById('rtStartDate').value = '';
        document.getElementById('rtEndDate').value = '';
        document.getElementById('rtEndDate').removeAttribute('min');
        document.getElementById('rtCancelEditBtn').classList.add('hidden');
        document.getElementById('rtFormError').classList.add('hidden');
    };

    window.rtDeleteRange = async function (employeeId, start, end) {
        if (!(await showConfirm('Clear this location for the selected date range?', 'Clear Location', 'danger', { okText: 'Clear' }))) return;

        const result = await rtFetch('/api/reporting/resource-timeline/entries/delete', {
            method: 'POST',
            body: JSON.stringify({ employee_id: employeeId, start_date: start, end_date: end, location }),
        });

        if (!result.success) {
            showAlert(result.message || 'Failed to delete.', 'Delete Failed', 'danger');
            return;
        }

        rtOnConsultantChange();
        rtLoadGrid();
    };

    window.rtSubmitForm = async function (e) {
        e.preventDefault();

        const errorEl = document.getElementById('rtFormError');
        errorEl.classList.add('hidden');

        const employeeId = document.getElementById('rtConsultantSelect').value;
        const startDate = document.getElementById('rtStartDate').value;
        const endDate = document.getElementById('rtEndDate').value;
        const location = document.getElementById('rtLocation').value;

        if (!employeeId || !startDate || !endDate) {
            errorEl.textContent = 'Please select a consultant and a full date range.';
            errorEl.classList.remove('hidden');
            return;
        }

        if (endDate < startDate) {
            errorEl.textContent = 'End Date must be on or after Start Date.';
            errorEl.classList.remove('hidden');
            return;
        }

        // Immediate feedback: this environment's dev server can take a couple of
        // seconds per request (no opcache), so without this the click can look
        // like it did nothing.
        const saveBtn = document.getElementById('rtSaveBtn');
        const originalLabel = saveBtn.textContent;
        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving…';

        try {
            // `<input type="date">` values are already plain "YYYY-MM-DD" strings —
            // no Date-object/timezone conversion involved, so no risk of the
            // UTC-shift-by-a-day bug a toISOString() approach would have.
            const payload = {
                employee_id: employeeId,
                start_date: startDate,
                end_date: endDate,
                location: location,
            };
            // Editing an existing range (not creating a fresh one): tell the
            // backend the ORIGINAL range too, so it can clear whatever part of
            // it fell outside the new range — otherwise shrinking/shifting the
            // dates while editing leaves the old tail still holding the old
            // location, which looks like the date edit did nothing.
            if (rtEditingRange) {
                payload.previous_start_date = rtEditingRange.start;
                payload.previous_end_date = rtEditingRange.end;
                payload.previous_location = rtEditingRange.location;
            }
            const result = await rtFetch('/api/reporting/resource-timeline/entries', {
                method: 'POST',
                body: JSON.stringify(payload),
            });

            if (!result.success) {
                errorEl.textContent = result.message || 'Failed to save.';
                errorEl.classList.remove('hidden');
                return;
            }

            rtResetForm();
            await Promise.all([rtOnConsultantChange(), rtLoadGrid()]);
        } finally {
            saveBtn.disabled = false;
            saveBtn.textContent = originalLabel;
        }
    };

    // ── Scroll-box height ────────────────────────────────────────────────
    // #rtScroll needs a genuine bounded height (not just "grow to fit
    // content") for its own `overflow-y: auto` to actually scroll — which is
    // what makes `position: sticky; top: 0` on the header work. Fills the
    // remaining viewport below the box's own top edge.
    function rtSyncScrollHeight() {
        const el = document.getElementById('rtScroll');
        if (!el) return;
        const top = el.getBoundingClientRect().top;
        const maxHeight = Math.max(300, Math.round(window.innerHeight - top - 24));
        el.style.maxHeight = maxHeight + 'px';
    }
    window.addEventListener('resize', rtSyncScrollHeight);

    // ── Init ─────────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        const now = new Date();
        document.getElementById('rtMonth').value = now.getMonth() + 1;
        document.getElementById('rtYear').value = now.getFullYear();
        rtSyncScrollHeight();
        rtLoadGrid();
    });
})();
</script>

@endsection

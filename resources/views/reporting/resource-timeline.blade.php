@extends('dashboard')
@section('title', 'Resource Timeline')
@section('page-title', 'Resource Timeline')
@section('page-subtitle', 'Daily client/location assignment per SAP Consultant')

@section('content')

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
    <div>
        <h2 class="text-xl font-bold text-gray-900">Resource Timeline</h2>
        <p class="text-sm text-gray-500 mt-0.5">Where every SAP Consultant is assigned, day by day</p>
    </div>
    <div class="flex items-center gap-2.5 flex-wrap">
        <select id="rtMonth" onchange="rtLoadGrid()"
                class="px-3 py-1.5 border border-gray-200 rounded-xl text-xs font-semibold text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
            @foreach (['January','February','March','April','May','June','July','August','September','October','November','December'] as $i => $m)
                <option value="{{ $i + 1 }}">{{ $m }}</option>
            @endforeach
        </select>
        <input type="number" id="rtYear" min="2000" max="2100"
               class="w-24 px-3 py-1.5 border border-gray-200 rounded-xl text-xs font-semibold text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400"
               onchange="rtLoadGrid()">
        <button onclick="rtOpenModal()"
                class="inline-flex items-center px-5 py-2.5 primary-gradient text-white text-sm font-semibold rounded-xl hover:opacity-90 transition-all duration-200">
            Create Timeline
        </button>
    </div>
</div>

<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="flex items-center justify-between px-4 py-2.5 border-b border-gray-100 bg-gray-50/60">
        <span id="rtSummary" class="text-xs text-gray-500">Loading…</span>
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

{{-- ── Consultant column filter (Home Base) — panel lives outside the table
     so it survives renderHead() re-rendering the header row on every reload --}}
<div id="rtHomeBaseFilterPanel" class="hidden fixed bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] p-3" style="min-width:200px;">
    <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-2">Filter by Home Base</label>
    <select id="rtHomeBaseFilterSelect" onchange="rtApplyHomeBaseFilter()"
            class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm font-normal text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
        <option value="">All Home Base</option>
        @foreach ($homeBaseOptions as $hb)
            <option value="{{ $hb }}">{{ $hb }}</option>
        @endforeach
    </select>
    <div class="flex justify-end gap-2 mt-3">
        <button type="button" onclick="rtClearHomeBaseFilter()" class="px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50">Clear</button>
    </div>
</div>

{{-- ── Create Timeline modal ─────────────────────────────────────────────── --}}
<div id="rtModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
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
                <select id="rtConsultantSelect" required onchange="rtOnConsultantChange()"
                        data-searchable="true" data-search-placeholder="Search consultant…"
                        class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
                    <option value="">Select consultant…</option>
                </select>
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
                <label class="block text-xs font-semibold text-gray-600 mb-1">Location</label>
                <input type="text" id="rtLocation" placeholder="e.g. PJT1, GOTO, BA…"
                       class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
                <p class="text-[11px] text-gray-400 mt-1">Leave blank to clear the location for the selected date range.</p>
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
    .rt-weekend { background-color: #fef2f2; }
</style>

<script>
(function () {
    'use strict';

    let rtDays = [];
    let rtEditingRange = null; // {employee_id, start, end} when editing an existing range
    let rtHomeBaseValue = ''; // active Consultant-column filter (Home Base)

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

        const params = new URLSearchParams({ month, year });
        if (rtHomeBaseValue) params.set('home_base', rtHomeBaseValue);

        const result = await rtFetch(`/api/reporting/resource-timeline/grid?${params.toString()}`);
        if (!result.success) {
            summary.textContent = result.message || 'Failed to load timeline.';
            return;
        }

        rtDays = result.days;
        renderHead();
        renderBody(result.rows);
        summary.textContent = `${result.rows.length} SAP Consultant${result.rows.length === 1 ? '' : 's'}`;
    };

    function renderHead() {
        const row = document.getElementById('rtHeadRow');

        // No + Consultant (the latter doubles as the Home Base filter — button
        // here, panel is static markup outside the table so it survives re-renders)
        let html = `
            <th class="rt-sticky rt-col-no px-2 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wide border-b border-gray-200 bg-gray-50">No</th>
            <th class="rt-sticky rt-col-name p-0 text-left border-b border-gray-200 bg-gray-50">
                <button type="button" id="rtHomeBaseFilterBtn" onclick="rtToggleHomeBaseFilter(event)"
                        class="w-full flex items-center gap-1.5 px-2 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                    <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">Consultant</span>
                    <svg class="w-3.5 h-3.5 ${rtHomeBaseValue ? 'text-red-500' : 'text-gray-300'} transition-colors ml-auto shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 011 1v1.586a1 1 0 01-.293.707l-4.121 4.121A1 1 0 0012 12.121V15.5l-4 1.5v-4.879a1 1 0 00-.293-.707L3.586 7.293A1 1 0 013.293 6.586L3 5z" clip-rule="evenodd" />
                    </svg>
                </button>
            </th>
        `;

        html += [['Module', 'rt-col-module'], ['Status', 'rt-col-status']].map(([label, cls]) => `
            <th class="rt-sticky ${cls} px-2 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wide border-b border-gray-200 bg-gray-50">
                ${label}
            </th>
        `).join('');

        html += rtDays.map(d => `
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
            tbody.innerHTML = `<tr><td colspan="${4 + rtDays.length}" class="text-center py-8 text-sm text-gray-400">No SAP Consultants found.</td></tr>`;
            return;
        }

        tbody.innerHTML = rows.map(r => {
            const fixedCells = `
                <td class="rt-sticky rt-col-no bg-white px-2 py-2 text-xs text-gray-500">${r.no}</td>
                <td class="rt-sticky rt-col-name bg-white px-2 py-2 text-xs font-medium text-gray-800 truncate">${escapeHtml(r.name)}</td>
                <td class="rt-sticky rt-col-module bg-white px-2 py-2 text-xs text-gray-600 truncate">${escapeHtml(r.module_label)}</td>
                <td class="rt-sticky rt-col-status bg-white px-2 py-2 text-xs font-semibold ${r.is_lead ? 'text-red-600' : 'text-gray-400'} truncate">${escapeHtml(r.status_label)}</td>
            `;

            const dayCells = rtDays.map(d => {
                const loc = r.dates[d.date] || '';
                return `<td class="rt-col-day px-1 py-2 text-[11px] text-gray-700 ${d.is_weekend ? 'rt-weekend' : ''}" title="${escapeHtml(loc)}">${escapeHtml(loc)}</td>`;
            }).join('');

            return `<tr class="hover:bg-gray-50 transition-colors">${fixedCells}${dayCells}</tr>`;
        }).join('');
    }

    // ── Consultant column filter (Home Base) ────────────────────────────────
    window.rtToggleHomeBaseFilter = function (ev) {
        ev?.stopPropagation();
        const panel = document.getElementById('rtHomeBaseFilterPanel');
        const btn = document.getElementById('rtHomeBaseFilterBtn');
        const open = !panel.classList.contains('hidden');

        if (open) {
            panel.classList.add('hidden');
            return;
        }

        const rect = btn.getBoundingClientRect();
        panel.style.top = (rect.bottom + 4) + 'px';
        panel.style.left = rect.left + 'px';
        panel.classList.remove('hidden');
    };

    window.rtApplyHomeBaseFilter = function () {
        rtHomeBaseValue = document.getElementById('rtHomeBaseFilterSelect').value;
        document.getElementById('rtHomeBaseFilterPanel').classList.add('hidden');
        rtLoadGrid();
    };

    window.rtClearHomeBaseFilter = function () {
        document.getElementById('rtHomeBaseFilterSelect').value = '';
        rtApplyHomeBaseFilter();
    };

    document.addEventListener('click', function (e) {
        const panel = document.getElementById('rtHomeBaseFilterPanel');
        const btn = document.getElementById('rtHomeBaseFilterBtn');
        if (panel && !panel.classList.contains('hidden') && !panel.contains(e.target) && !(btn && btn.contains(e.target))) {
            panel.classList.add('hidden');
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.getElementById('rtHomeBaseFilterPanel')?.classList.add('hidden');
        }
    });

    // ── Modal / consultant dropdown ─────────────────────────────────────────
    window.rtOpenModal = function () {
        document.getElementById('rtModal').classList.remove('hidden');
        if (!document.getElementById('rtConsultantSelect').dataset.loaded) {
            rtLoadConsultantOptions();
        }
    };

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

    async function rtLoadConsultantOptions() {
        const select = document.getElementById('rtConsultantSelect');
        const result = await rtFetch('/api/reporting/resource-timeline/consultants');
        if (!result.success) return;

        select.innerHTML = '<option value="">Select consultant…</option>' +
            result.data.map(c => `<option value="${c.employee_id}">${escapeHtml(c.name)}</option>`).join('');
        select.dataset.loaded = '1';
    }

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
                            onclick="rtDeleteRange('${employeeId}','${range.start}','${range.end}')"
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
        rtEditingRange = { employee_id: employeeId, start, end };
        document.getElementById('rtFormMode').value = 'edit';
        document.getElementById('rtConsultantSelect').value = employeeId;
        document.getElementById('rtLocation').value = location;
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
        if (!confirm('Clear this location for the selected date range?')) return;

        const result = await rtFetch('/api/reporting/resource-timeline/entries/delete', {
            method: 'POST',
            body: JSON.stringify({ employee_id: employeeId, start_date: start, end_date: end }),
        });

        if (!result.success) {
            alert(result.message || 'Failed to delete.');
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

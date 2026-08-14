@extends('dashboard')
@section('title', 'Diagram Report')
@section('page-title', 'Diagram Report')
@section('page-subtitle', 'Preview of every diagram type the system can display')

@push('styles')
<style>
    .diagram-card {
        background: #fff;
        border: 1px solid #e1e0d9;
        border-radius: 0.75rem;
        padding: 1.25rem;
        box-shadow: 0 1px 2px rgba(0,0,0,0.03);
        display: flex;
        flex-direction: column;
    }
    .diagram-card h3 {
        font-size: 0.95rem;
        font-weight: 700;
        color: #0b0b0b;
    }
    .diagram-card p.diagram-desc {
        font-size: 0.75rem;
        color: #898781;
        margin-top: 0.125rem;
        margin-bottom: 0.75rem;
    }
    .diagram-canvas-wrap {
        position: relative;
        height: 420px;
        flex: 1;
    }
    .diagram-canvas-wrap-lg {
        position: relative;
        height: 800px;
        flex: 1;
    }
    .diagram-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1.25rem;
    }
    @media (max-width: 767px) {
        .diagram-grid { grid-template-columns: repeat(1, minmax(0, 1fr)); }
    }
    .diagram-table { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
    .diagram-table th, .diagram-table td {
        border: 1px solid #d7d5cd;
        padding: 0.45rem 0.6rem;
        text-align: center;
        white-space: nowrap;
    }
    .diagram-table thead th {
        background: #2a78d6;
        color: #fff;
        font-weight: 700;
    }
    .diagram-table tbody td:first-child,
    .diagram-table thead th:first-child {
        text-align: left;
        position: sticky;
        left: 0;
        background: #eef3fb;
        font-weight: 600;
    }
    .diagram-table thead th:first-child { background: #2a78d6; z-index: 1; }
    .diagram-table tr.diagram-table-total td {
        background: #d9e4f5;
        font-weight: 700;
    }
    .diagram-table tr.diagram-table-total td:first-child { background: #c3d4ee; }
</style>
@endpush

@section('content')
<div class="mb-5 rounded-xl border border-dashed border-gray-300 bg-amber-50 px-4 py-3 flex items-start gap-3">
    <i class="fas fa-circle-info text-amber-500 mt-0.5"></i>
    <div class="text-sm text-amber-800">
        <span class="font-semibold">Preview mode.</span>
        This page shows every diagram type the system can provide, some using sample (dummy) data.
        Charts 1–4 are already connected to live data via the filters below; the rest will be enabled once their data source is wired up.
    </div>
</div>

{{-- ── Filters ──────────────────────────────────────────────────────────── --}}
<div class="bg-white rounded-xl p-4 shadow-sm mb-5">
    <div class="flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Customer</label>
            <div class="custom-dd relative" data-onchange="applyDiagramFilters" data-fixed="true" style="min-width:200px">
                <button type="button" class="custom-dd-btn w-full flex items-center justify-between pl-3 pr-2.5 py-1.5 bg-white border border-gray-200 rounded-lg text-xs text-gray-500 hover:border-gray-400 transition-all text-left gap-2">
                    <span class="custom-dd-label">All Customers</span>
                    <svg class="custom-dd-arrow w-3.5 h-3.5 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <input type="hidden" id="filterCustomer" value="">
                <div class="custom-dd-panel hidden absolute top-full left-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:260px; min-width:220px;">
                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm font-medium text-gray-900 bg-gray-50 hover:bg-gray-50 transition-colors" data-value="">All Customers</button>
                    @foreach($customers as $c)
                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="{{ $c->customer_id }}">
                        {{ optional($c->basicData)->name_1 ?? 'Customer #'.$c->customer_id }}
                    </button>
                    @endforeach
                </div>
            </div>
        </div>

        <div>
            <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Period</label>
            <div class="relative">
                <button type="button" id="dateFilterBtn" onclick="toggleDateFilter(event)"
                    class="flex items-center gap-1.5 pl-3 pr-2.5 py-1.5 bg-white border border-gray-200 rounded-lg text-xs text-gray-500 hover:border-gray-400 transition-all">
                    <i id="dateFilterCalIcon" class="fas fa-calendar-alt text-[11px] text-gray-400"></i>
                    <span id="dateFilterLabel">All Dates</span>
                    <svg class="w-3.5 h-3.5 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div id="dateFilterPanel" class="hidden bg-white rounded-xl shadow-2xl border border-gray-100 z-50 p-3" style="min-width:230px;">
                    <div class="space-y-2">
                        <div>
                            <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Start Date</label>
                            <input type="date" id="filterDateFrom"
                                class="w-full px-3 py-1.5 border border-gray-300 rounded-md text-sm font-normal text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">End Date</label>
                            <input type="date" id="filterDateTo"
                                class="w-full px-3 py-1.5 border border-gray-300 rounded-md text-sm font-normal text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
                        </div>
                        <p id="dateFilterError" class="hidden text-xs text-red-500">"End Date" must be on/after "Start Date".</p>
                    </div>
                    <div class="flex justify-end gap-2 mt-3">
                        <button type="button" onclick="clearDateFilter()" class="px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50">Clear</button>
                        <button type="button" onclick="applyDateFilter()" class="px-3 py-1.5 text-xs text-white bg-red-700 hover:bg-red-800 rounded-md">Apply</button>
                    </div>
                </div>
            </div>
        </div>

        <button type="button" onclick="clearDiagramFilters()" id="diagramClearBtn"
            class="hidden inline-flex items-center gap-1.5 px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
            <i class="fas fa-times text-[10px]"></i> Clear Filter
        </button>

        <span id="diagramFilterSummary" class="text-xs text-gray-400 ml-auto"></span>
    </div>
</div>

<div class="diagram-grid">

    <div class="diagram-card">
        <h3>Chart 1 — Ticket Qty from Start to Current Period</h3>
        <p class="diagram-desc">Line chart — ticket count per month, following the Customer &amp; Period filters above.</p>
        <div class="diagram-canvas-wrap-lg">
            <canvas id="chartTicketQty"></canvas>
            <div id="ticketQtyEmptyState" class="absolute inset-0 flex items-center justify-center text-center px-6">
                <p class="text-sm text-gray-400">
                    <i class="fas fa-calendar-alt mb-1 block text-lg text-gray-300"></i>
                    Select <strong>Start Date</strong> &amp; <strong>End Date</strong> in the Period filter above to display this chart.
                </p>
            </div>
        </div>
    </div>

    <div class="diagram-card">
        <h3>Chart 2 — Tickets per Module</h3>
        <p class="diagram-desc">Grouped bar chart — ticket count per module (Incident/Error, Request/Konsultasi, Request/CR, Other), following the Customer &amp; Period filters above.</p>
        <div class="diagram-canvas-wrap-lg" style="overflow-x:auto; overflow-y:hidden;">
            <div id="ticketByModuleCanvasInner" style="position:relative; height:100%; min-width:100%;">
                <canvas id="chartTicketByModule"></canvas>
            </div>
            <div id="ticketByModuleEmptyState" class="absolute inset-0 flex items-center justify-center text-center px-6">
                <p class="text-sm text-gray-400">
                    <i class="fas fa-calendar-alt mb-1 block text-lg text-gray-300"></i>
                    Select <strong>Start Date</strong> &amp; <strong>End Date</strong> in the Period filter above to display this chart.
                </p>
            </div>
        </div>
    </div>

    <div class="diagram-card">
        <h3>Chart 3 — Ticket Type per Month</h3>
        <p class="diagram-desc">Table of ticket count per Ticket Type per month, following the Customer &amp; Period filters above.</p>
        <div class="diagram-table-wrap" style="position:relative; overflow:auto; flex:1;">
            <table id="ticketTypeByMonthTable" class="diagram-table">
                <thead>
                    <tr id="ticketTypeByMonthHead">
                        <th class="text-left">Ticket Type</th>
                    </tr>
                </thead>
                <tbody id="ticketTypeByMonthBody"></tbody>
            </table>
            <div id="ticketTypeByMonthEmptyState" class="absolute inset-0 flex items-center justify-center text-center px-6 bg-white">
                <p class="text-sm text-gray-400">
                    <i class="fas fa-calendar-alt mb-1 block text-lg text-gray-300"></i>
                    Select <strong>Start Date</strong> &amp; <strong>End Date</strong> in the Period filter above to display this table.
                </p>
            </div>
        </div>
    </div>

    <div class="diagram-card">
        <h3>Chart 4 — Ticket Type per Module</h3>
        <p class="diagram-desc">Grouped bar chart — ticket count per module (Incident/Error, Request/Konsultasi, Request/CR), following the Customer &amp; Period filters above.</p>
        <div class="diagram-canvas-wrap-lg" style="overflow-x:auto; overflow-y:hidden;">
            <div id="ticketByModuleTypeCanvasInner" style="position:relative; height:100%; min-width:100%;">
                <canvas id="chartTicketByModuleType"></canvas>
            </div>
            <div id="ticketByModuleTypeEmptyState" class="absolute inset-0 flex items-center justify-center text-center px-6">
                <p class="text-sm text-gray-400">
                    <i class="fas fa-calendar-alt mb-1 block text-lg text-gray-300"></i>
                    Select <strong>Start Date</strong> &amp; <strong>End Date</strong> in the Period filter above to display this chart.
                </p>
            </div>
        </div>
    </div>

    <div class="diagram-card">
        <h3>Stacked Bar Chart</h3>
        <p class="diagram-desc">Total composition split across several categories per period.</p>
        <div class="diagram-canvas-wrap"><canvas id="chartBarStack"></canvas></div>
    </div>

    <div class="diagram-card">
        <h3>Line Chart</h3>
        <p class="diagram-desc">Value trend over time for one or more series.</p>
        <div class="diagram-canvas-wrap"><canvas id="chartLine"></canvas></div>
    </div>

    <div class="diagram-card">
        <h3>Area Chart</h3>
        <p class="diagram-desc">Value trend with emphasis on cumulative volume.</p>
        <div class="diagram-canvas-wrap"><canvas id="chartArea"></canvas></div>
    </div>

    <div class="diagram-card">
        <h3>Combo Chart (Bar + Line)</h3>
        <p class="diagram-desc">Combines actual values (bar) with a target/average (line) on a single scale.</p>
        <div class="diagram-canvas-wrap"><canvas id="chartCombo"></canvas></div>
    </div>

    <div class="diagram-card">
        <h3>Pie Chart</h3>
        <p class="diagram-desc">Proportion of each category relative to the whole.</p>
        <div class="diagram-canvas-wrap"><canvas id="chartPie"></canvas></div>
    </div>

    <div class="diagram-card">
        <h3>Doughnut Chart</h3>
        <p class="diagram-desc">Same as a pie chart, with a center space for a summary figure.</p>
        <div class="diagram-canvas-wrap"><canvas id="chartDoughnut"></canvas></div>
    </div>

    <div class="diagram-card">
        <h3>Radar Chart</h3>
        <p class="diagram-desc">Compares multiple dimensions/metrics at once across series.</p>
        <div class="diagram-canvas-wrap"><canvas id="chartRadar"></canvas></div>
    </div>

    <div class="diagram-card">
        <h3>Polar Area Chart</h3>
        <p class="diagram-desc">Proportion between categories with emphasis on radius size.</p>
        <div class="diagram-canvas-wrap"><canvas id="chartPolar"></canvas></div>
    </div>

    <div class="diagram-card">
        <h3>Scatter Chart</h3>
        <p class="diagram-desc">Relationship/correlation between two numeric variables.</p>
        <div class="diagram-canvas-wrap"><canvas id="chartScatter"></canvas></div>
    </div>

    <div class="diagram-card">
        <h3>Bubble Chart</h3>
        <p class="diagram-desc">Relationship between two numeric variables plus a third dimension via point size.</p>
        <div class="diagram-canvas-wrap"><canvas id="chartBubble"></canvas></div>
    </div>

</div>

@push('scripts')
<script src="/js/custom-dropdown.js?v={{ filemtime(public_path('js/custom-dropdown.js')) }}"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
initCustomDropdowns();

function applyDiagramFilters() {
    const customerVal   = document.getElementById('filterCustomer').value;
    const customerLabel = document.querySelector('#filterCustomer').closest('.custom-dd').querySelector('.custom-dd-label').textContent.trim();
    const from = document.getElementById('filterDateFrom').value;
    const to   = document.getElementById('filterDateTo').value;

    const parts = [];
    if (customerVal) parts.push(customerLabel);
    if (from || to) parts.push(document.getElementById('dateFilterLabel').textContent);

    document.getElementById('diagramFilterSummary').textContent = parts.length ? `Filters applied to Chart 1, 2, 3 & 4: ${parts.join(' · ')}` : '';
    document.getElementById('diagramClearBtn').classList.toggle('hidden', parts.length === 0);

    if (typeof window.loadTicketQtyChart === 'function') {
        window.loadTicketQtyChart();
    }
    if (typeof window.loadTicketByModuleChart === 'function') {
        window.loadTicketByModuleChart();
    }
    if (typeof window.loadTicketTypeByMonthTable === 'function') {
        window.loadTicketTypeByMonthTable();
    }
    if (typeof window.loadTicketByModuleTypeChart === 'function') {
        window.loadTicketByModuleTypeChart();
    }
}

function clearDiagramFilters() {
    setCustomDropdownValue('filterCustomer', '');
    document.getElementById('filterDateFrom').value = '';
    document.getElementById('filterDateTo').value = '';
    document.getElementById('dateFilterError').classList.add('hidden');
    updateDateFilterLabel();
    applyDiagramFilters();
}

// ── Date range filter (same pattern as Ticket page / SLA Report) ──────────
function toggleDateFilter(ev) {
    ev?.stopPropagation();
    const panel = document.getElementById('dateFilterPanel');
    const btn = document.getElementById('dateFilterBtn');
    const isOpen = !panel.classList.contains('hidden');
    panel.classList.add('hidden');
    if (!isOpen) {
        const rect = btn.getBoundingClientRect();
        panel.style.position = 'fixed';
        panel.style.top = (rect.bottom + 6) + 'px';
        panel.style.left = rect.left + 'px';
        panel.classList.remove('hidden');
    }
}

function applyDateFilter() {
    const from = document.getElementById('filterDateFrom').value;
    const to = document.getElementById('filterDateTo').value;
    const errEl = document.getElementById('dateFilterError');
    if (from && to && to < from) {
        errEl.classList.remove('hidden');
        return;
    }
    errEl.classList.add('hidden');
    document.getElementById('dateFilterPanel').classList.add('hidden');
    updateDateFilterLabel();
    applyDiagramFilters();
}

function clearDateFilter() {
    document.getElementById('filterDateFrom').value = '';
    document.getElementById('filterDateTo').value = '';
    document.getElementById('dateFilterError').classList.add('hidden');
    document.getElementById('dateFilterPanel').classList.add('hidden');
    updateDateFilterLabel();
    applyDiagramFilters();
}

function updateDateFilterLabel() {
    const from = document.getElementById('filterDateFrom').value;
    const to = document.getElementById('filterDateTo').value;
    const label = document.getElementById('dateFilterLabel');
    const btn = document.getElementById('dateFilterBtn');
    const active = !!(from || to);
    if (!from && !to) {
        label.textContent = 'All Dates';
    } else if (from && to) {
        label.textContent = `${from} → ${to}`;
    } else {
        label.textContent = from ? `From ${from}` : `Until ${to}`;
    }
    btn.classList.toggle('bg-red-50', active);
    btn.classList.toggle('border-red-300', active);
    btn.classList.toggle('text-red-700', active);
    btn.classList.toggle('bg-white', !active);
    btn.classList.toggle('border-gray-200', !active);
    btn.classList.toggle('text-gray-500', !active);
    document.getElementById('dateFilterCalIcon')?.classList.toggle('text-red-500', active);
    document.getElementById('dateFilterCalIcon')?.classList.toggle('text-gray-400', !active);
}

document.addEventListener('click', (e) => {
    const panel = document.getElementById('dateFilterPanel');
    const btn = document.getElementById('dateFilterBtn');
    if (panel && !panel.classList.contains('hidden') && !panel.contains(e.target) && !btn.contains(e.target)) {
        panel.classList.add('hidden');
    }
});
window.addEventListener('scroll', () => document.getElementById('dateFilterPanel')?.classList.add('hidden'), true);
window.addEventListener('resize', () => document.getElementById('dateFilterPanel')?.classList.add('hidden'));
</script>
<script>
(function () {
    // Validated categorical palette (fixed order — see dataviz skill references/palette.md)
    const PALETTE = {
        blue:    '#2a78d6',
        orange:  '#eb6834',
        aqua:    '#1baf7a',
        yellow:  '#eda100',
        magenta: '#e87ba4',
        green:   '#008300',
        violet:  '#4a3aa7',
        red:     '#e34948',
    };
    const SERIES = Object.values(PALETTE);
    const GRID_COLOR = '#e1e0d9';
    const TEXT_MUTED = '#898781';
    const TEXT_SECONDARY = '#52514e';

    Chart.defaults.font.family = "system-ui, -apple-system, 'Segoe UI', sans-serif";
    Chart.defaults.font.size = 12;
    Chart.defaults.color = TEXT_SECONDARY;

    function alpha(hex, a) {
        const r = parseInt(hex.slice(1, 3), 16);
        const g = parseInt(hex.slice(3, 5), 16);
        const b = parseInt(hex.slice(5, 7), 16);
        return `rgba(${r}, ${g}, ${b}, ${a})`;
    }

    const gridOpts = { color: GRID_COLOR, drawTicks: false };
    const tickOpts = { color: TEXT_MUTED };

    const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];

    // Draws the value above each data point — replicates the Excel chart look
    // that "Chart 1" is based on.
    const pointValueLabelPlugin = {
        id: 'pointValueLabel',
        afterDatasetsDraw(chart) {
            const { ctx } = chart;
            chart.data.datasets.forEach((dataset, i) => {
                const meta = chart.getDatasetMeta(i);
                if (meta.hidden) return;
                meta.data.forEach((point, index) => {
                    const value = dataset.data[index];
                    if (value === null || value === undefined) return;
                    ctx.save();
                    ctx.fillStyle = TEXT_SECONDARY;
                    ctx.font = '600 11px system-ui, -apple-system, "Segoe UI", sans-serif';
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'bottom';
                    ctx.fillText(value, point.x, point.y - 8);
                    ctx.restore();
                });
            });
        },
    };

    // ── Chart 1: Ticket Qty from Start to Current Period ───────────────────
    // Data comes from the API, following the Customer & Period (Start/End Date)
    // filters above. Period must be filled in first — see loadTicketQtyChart().
    const ticketQtyChart = new Chart(document.getElementById('chartTicketQty'), {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label: 'Qty Ticket',
                data: [],
                borderColor: PALETTE.blue,
                backgroundColor: PALETTE.blue,
                borderWidth: 2,
                pointRadius: 4,
                pointBackgroundColor: PALETTE.blue,
                tension: 0,
            }],
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            layout: { padding: { top: 16 } },
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false }, ticks: tickOpts },
                y: { beginAtZero: true, ticks: { ...tickOpts, precision: 0 }, grid: gridOpts, border: { display: false } },
            },
        },
        plugins: [pointValueLabelPlugin],
    });

    async function loadTicketQtyChart() {
        const emptyState = document.getElementById('ticketQtyEmptyState');
        const emptyText  = emptyState.querySelector('p');
        const from = document.getElementById('filterDateFrom').value;
        const to   = document.getElementById('filterDateTo').value;

        if (!from || !to) {
            emptyText.innerHTML = '<i class="fas fa-calendar-alt mb-1 block text-lg text-gray-300"></i>Select <strong>Start Date</strong> &amp; <strong>End Date</strong> in the Period filter above to display this chart.';
            emptyState.classList.remove('hidden');
            ticketQtyChart.data.labels = [];
            ticketQtyChart.data.datasets[0].data = [];
            ticketQtyChart.update();
            return;
        }

        const customerId = document.getElementById('filterCustomer').value;
        const params = new URLSearchParams({ date_from: from, date_to: to });
        if (customerId) params.set('customer_id', customerId);

        try {
            const res  = await fetch(`/api/reporting/diagram-report/ticket-qty?${params.toString()}`);
            const json = await res.json();
            if (!json.success) throw new Error(json.message || 'Failed to load data');

            emptyState.classList.add('hidden');
            ticketQtyChart.data.labels = json.data.labels;
            ticketQtyChart.data.datasets[0].data = json.data.values;
            ticketQtyChart.update();
        } catch (err) {
            emptyText.innerHTML = 'Failed to load data. Please try again.';
            emptyState.classList.remove('hidden');
        }
    }
    window.loadTicketQtyChart = loadTicketQtyChart;
    loadTicketQtyChart();

    // ── Chart 2: Tickets per Module (Incident/Error, Request/Konsultasi, Request/CR) ──
    // Data comes from the API, following the Customer & Period filters above.
    const TICKET_TYPE_SERIES = ['Incident / Error', 'Request / Konsultasi', 'Request / CR', 'Other'];
    const TICKET_TYPE_COLORS = [PALETTE.blue, PALETTE.orange, PALETTE.aqua, PALETTE.yellow];

    const ticketByModuleChart = new Chart(document.getElementById('chartTicketByModule'), {
        type: 'bar',
        data: {
            labels: [],
            datasets: TICKET_TYPE_SERIES.map((label, i) => ({
                label,
                data: [],
                backgroundColor: TICKET_TYPE_COLORS[i],
                borderRadius: 4,
                maxBarThickness: 20,
            })),
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            layout: { padding: { top: 16 } },
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true, pointStyle: 'circle' } } },
            scales: {
                x: { grid: { display: false }, ticks: tickOpts },
                y: { beginAtZero: true, ticks: { ...tickOpts, precision: 0 }, grid: gridOpts, border: { display: false } },
            },
        },
        plugins: [pointValueLabelPlugin],
    });

    async function loadTicketByModuleChart() {
        const emptyState = document.getElementById('ticketByModuleEmptyState');
        const emptyText  = emptyState.querySelector('p');
        const inner      = document.getElementById('ticketByModuleCanvasInner');
        const from = document.getElementById('filterDateFrom').value;
        const to   = document.getElementById('filterDateTo').value;

        if (!from || !to) {
            emptyText.innerHTML = '<i class="fas fa-calendar-alt mb-1 block text-lg text-gray-300"></i>Select <strong>Start Date</strong> &amp; <strong>End Date</strong> in the Period filter above to display this chart.';
            emptyState.classList.remove('hidden');
            ticketByModuleChart.data.labels = [];
            ticketByModuleChart.data.datasets.forEach(ds => ds.data = []);
            ticketByModuleChart.update();
            return;
        }

        const customerId = document.getElementById('filterCustomer').value;
        const params = new URLSearchParams({ date_from: from, date_to: to });
        if (customerId) params.set('customer_id', customerId);

        try {
            const res  = await fetch(`/api/reporting/diagram-report/ticket-by-module?${params.toString()}`);
            const json = await res.json();
            if (!json.success) throw new Error(json.message || 'Failed to load data');

            emptyState.classList.add('hidden');
            const wrapWidth = inner.parentElement.clientWidth;
            inner.style.width = Math.max(json.data.labels.length * 90, wrapWidth) + 'px';

            ticketByModuleChart.data.labels = json.data.labels;
            ticketByModuleChart.data.datasets.forEach(ds => { ds.data = json.data.series[ds.label] || []; });
            ticketByModuleChart.resize();
            ticketByModuleChart.update();
        } catch (err) {
            emptyText.innerHTML = 'Failed to load data. Please try again.';
            emptyState.classList.remove('hidden');
        }
    }
    window.loadTicketByModuleChart = loadTicketByModuleChart;
    loadTicketByModuleChart();

    // ── Chart 3: Ticket Type per Month (table) ──────────────────────────────
    // Data comes from the API, following the Customer & Period filters above.
    async function loadTicketTypeByMonthTable() {
        const emptyState = document.getElementById('ticketTypeByMonthEmptyState');
        const emptyText  = emptyState.querySelector('p');
        const head       = document.getElementById('ticketTypeByMonthHead');
        const body       = document.getElementById('ticketTypeByMonthBody');
        const from = document.getElementById('filterDateFrom').value;
        const to   = document.getElementById('filterDateTo').value;

        function render(months, rows, total) {
            head.innerHTML = '<th class="text-left">Ticket Type</th>' +
                months.map(m => `<th>${m}</th>`).join('');

            body.innerHTML = rows.map(row => `
                <tr>
                    <td>${row.label}</td>
                    ${row.values.map(v => `<td>${v}</td>`).join('')}
                </tr>
            `).join('') + `
                <tr class="diagram-table-total">
                    <td>TOTAL</td>
                    ${total.map(v => `<td>${v}</td>`).join('')}
                </tr>
            `;
        }

        if (!from || !to) {
            emptyText.innerHTML = '<i class="fas fa-calendar-alt mb-1 block text-lg text-gray-300"></i>Select <strong>Start Date</strong> &amp; <strong>End Date</strong> in the Period filter above to display this table.';
            emptyState.classList.remove('hidden');
            render([], [], []);
            return;
        }

        const customerId = document.getElementById('filterCustomer').value;
        const params = new URLSearchParams({ date_from: from, date_to: to });
        if (customerId) params.set('customer_id', customerId);

        try {
            const res  = await fetch(`/api/reporting/diagram-report/ticket-type-by-month?${params.toString()}`);
            const json = await res.json();
            if (!json.success) throw new Error(json.message || 'Failed to load data');

            emptyState.classList.add('hidden');
            render(json.data.months, json.data.rows, json.data.total);
        } catch (err) {
            emptyText.innerHTML = 'Failed to load data. Please try again.';
            emptyState.classList.remove('hidden');
        }
    }
    window.loadTicketTypeByMonthTable = loadTicketTypeByMonthTable;
    loadTicketTypeByMonthTable();

    // ── Chart 4: Ticket Type per Module (Incident/Error, Request/Konsultasi, Request/CR) ──
    // Data comes from the API, following the Customer & Period filters above.
    const MODULE_TYPE_SERIES = ['Incident / Error', 'Request / Konsultasi', 'Request / CR'];
    const MODULE_TYPE_COLORS = [PALETTE.blue, PALETTE.violet, PALETTE.orange];

    const ticketByModuleTypeChart = new Chart(document.getElementById('chartTicketByModuleType'), {
        type: 'bar',
        data: {
            labels: [],
            datasets: MODULE_TYPE_SERIES.map((label, i) => ({
                label,
                data: [],
                backgroundColor: MODULE_TYPE_COLORS[i],
                borderRadius: 4,
                maxBarThickness: 20,
            })),
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            layout: { padding: { top: 16 } },
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true, pointStyle: 'circle' } } },
            scales: {
                x: { grid: { display: false }, ticks: tickOpts },
                y: { beginAtZero: true, ticks: { ...tickOpts, precision: 0 }, grid: gridOpts, border: { display: false } },
            },
        },
        plugins: [pointValueLabelPlugin],
    });

    async function loadTicketByModuleTypeChart() {
        const emptyState = document.getElementById('ticketByModuleTypeEmptyState');
        const emptyText  = emptyState.querySelector('p');
        const inner      = document.getElementById('ticketByModuleTypeCanvasInner');
        const from = document.getElementById('filterDateFrom').value;
        const to   = document.getElementById('filterDateTo').value;

        if (!from || !to) {
            emptyText.innerHTML = '<i class="fas fa-calendar-alt mb-1 block text-lg text-gray-300"></i>Select <strong>Start Date</strong> &amp; <strong>End Date</strong> in the Period filter above to display this chart.';
            emptyState.classList.remove('hidden');
            ticketByModuleTypeChart.data.labels = [];
            ticketByModuleTypeChart.data.datasets.forEach(ds => ds.data = []);
            ticketByModuleTypeChart.update();
            return;
        }

        const customerId = document.getElementById('filterCustomer').value;
        const params = new URLSearchParams({ date_from: from, date_to: to });
        if (customerId) params.set('customer_id', customerId);

        try {
            const res  = await fetch(`/api/reporting/diagram-report/ticket-by-module-type?${params.toString()}`);
            const json = await res.json();
            if (!json.success) throw new Error(json.message || 'Failed to load data');

            emptyState.classList.add('hidden');
            const wrapWidth = inner.parentElement.clientWidth;
            inner.style.width = Math.max(json.data.labels.length * 90, wrapWidth) + 'px';

            ticketByModuleTypeChart.data.labels = json.data.labels;
            ticketByModuleTypeChart.data.datasets.forEach(ds => { ds.data = json.data.series[ds.label] || []; });
            ticketByModuleTypeChart.resize();
            ticketByModuleTypeChart.update();
        } catch (err) {
            emptyText.innerHTML = 'Failed to load data. Please try again.';
            emptyState.classList.remove('hidden');
        }
    }
    window.loadTicketByModuleTypeChart = loadTicketByModuleTypeChart;
    loadTicketByModuleTypeChart();

    // ── Stacked Bar ──────────────────────────────────────────────────────
    new Chart(document.getElementById('chartBarStack'), {
        type: 'bar',
        data: {
            labels: MONTHS,
            datasets: [
                { label: 'Open', data: [12, 15, 10, 14, 11, 13], backgroundColor: PALETTE.red, borderRadius: 4, maxBarThickness: 24 },
                { label: 'In Process', data: [10, 12, 9, 15, 10, 12], backgroundColor: PALETTE.yellow, borderRadius: 4, maxBarThickness: 24 },
                { label: 'Close', data: [20, 28, 19, 32, 28, 33], backgroundColor: PALETTE.aqua, borderRadius: 4, maxBarThickness: 24 },
            ],
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true, pointStyle: 'circle' } } },
            scales: {
                x: { stacked: true, grid: { display: false }, ticks: tickOpts },
                y: { stacked: true, beginAtZero: true, grid: gridOpts, ticks: tickOpts, border: { display: false } },
            },
        },
    });

    // ── Line ─────────────────────────────────────────────────────────────
    new Chart(document.getElementById('chartLine'), {
        type: 'line',
        data: {
            labels: MONTHS,
            datasets: [
                { label: 'Resolution Days', data: [3.2, 2.8, 3.5, 2.6, 2.9, 2.4], borderColor: PALETTE.blue, backgroundColor: PALETTE.blue, borderWidth: 2, pointRadius: 4, pointBackgroundColor: PALETTE.blue, tension: 0.3 },
                { label: 'SLA Target', data: [3, 3, 3, 3, 3, 3], borderColor: PALETTE.orange, backgroundColor: PALETTE.orange, borderWidth: 2, pointRadius: 4, pointBackgroundColor: PALETTE.orange, borderDash: [5, 4], tension: 0 },
            ],
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true, pointStyle: 'circle' } } },
            scales: {
                x: { grid: { display: false }, ticks: tickOpts },
                y: { beginAtZero: true, grid: gridOpts, ticks: tickOpts, border: { display: false } },
            },
        },
    });

    // ── Area ─────────────────────────────────────────────────────────────
    new Chart(document.getElementById('chartArea'), {
        type: 'line',
        data: {
            labels: MONTHS,
            datasets: [{
                label: 'Total Mandays',
                data: [18, 26, 22, 31, 27, 35],
                borderColor: PALETTE.blue,
                backgroundColor: alpha(PALETTE.blue, 0.1),
                borderWidth: 2,
                pointRadius: 4,
                pointBackgroundColor: PALETTE.blue,
                fill: true,
                tension: 0.3,
            }],
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false }, ticks: tickOpts },
                y: { beginAtZero: true, grid: gridOpts, ticks: tickOpts, border: { display: false } },
            },
        },
    });

    // ── Combo (Bar + Line, single shared axis) ─────────────────────────────
    new Chart(document.getElementById('chartCombo'), {
        data: {
            labels: MONTHS,
            datasets: [
                { type: 'bar', label: 'Tickets Completed', data: [20, 28, 19, 32, 28, 33], backgroundColor: PALETTE.blue, borderRadius: 4, maxBarThickness: 24, order: 2 },
                { type: 'line', label: '6-Month Average', data: [27, 27, 27, 27, 27, 27], borderColor: PALETTE.orange, backgroundColor: PALETTE.orange, borderWidth: 2, pointRadius: 0, borderDash: [5, 4], tension: 0, order: 1 },
            ],
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true, pointStyle: 'circle' } } },
            scales: {
                x: { grid: { display: false }, ticks: tickOpts },
                y: { beginAtZero: true, grid: gridOpts, ticks: tickOpts, border: { display: false } },
            },
        },
    });

    // ── Pie ──────────────────────────────────────────────────────────────
    new Chart(document.getElementById('chartPie'), {
        type: 'pie',
        data: {
            labels: ['Open', 'In Process', 'Close', 'Wait Close', 'Other'],
            datasets: [{
                data: [18, 24, 41, 10, 7],
                backgroundColor: [PALETTE.red, PALETTE.yellow, PALETTE.aqua, PALETTE.violet, PALETTE.magenta],
                borderColor: '#fff',
                borderWidth: 2,
            }],
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true, pointStyle: 'circle' } } },
        },
    });

    // ── Doughnut ─────────────────────────────────────────────────────────
    new Chart(document.getElementById('chartDoughnut'), {
        type: 'doughnut',
        data: {
            labels: ['Customer A', 'Customer B', 'Customer C', 'Customer D'],
            datasets: [{
                data: [35, 27, 21, 17],
                backgroundColor: [PALETTE.blue, PALETTE.orange, PALETTE.aqua, PALETTE.yellow],
                borderColor: '#fff',
                borderWidth: 2,
            }],
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            cutout: '65%',
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true, pointStyle: 'circle' } } },
        },
    });

    // ── Radar ────────────────────────────────────────────────────────────
    new Chart(document.getElementById('chartRadar'), {
        type: 'radar',
        data: {
            labels: ['Responsiveness', 'Quality', 'Timeliness', 'Communication', 'Resolution'],
            datasets: [
                { label: 'Team A', data: [80, 70, 85, 75, 90], borderColor: PALETTE.blue, backgroundColor: alpha(PALETTE.blue, 0.15), borderWidth: 2, pointRadius: 4, pointBackgroundColor: PALETTE.blue },
                { label: 'Team B', data: [65, 85, 70, 80, 72], borderColor: PALETTE.orange, backgroundColor: alpha(PALETTE.orange, 0.15), borderWidth: 2, pointRadius: 4, pointBackgroundColor: PALETTE.orange },
            ],
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true, pointStyle: 'circle' } } },
            scales: {
                r: { grid: { color: GRID_COLOR }, angleLines: { color: GRID_COLOR }, pointLabels: { color: TEXT_SECONDARY }, ticks: { display: false, backdropColor: 'transparent' } },
            },
        },
    });

    // ── Polar Area ───────────────────────────────────────────────────────
    new Chart(document.getElementById('chartPolar'), {
        type: 'polarArea',
        data: {
            labels: ['Network', 'Application', 'Hardware', 'Access', 'Database'],
            datasets: [{
                data: [28, 24, 18, 16, 12],
                backgroundColor: [alpha(PALETTE.blue, 0.7), alpha(PALETTE.orange, 0.7), alpha(PALETTE.aqua, 0.7), alpha(PALETTE.yellow, 0.7), alpha(PALETTE.magenta, 0.7)],
                borderColor: '#fff',
                borderWidth: 2,
            }],
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true, pointStyle: 'circle' } } },
            scales: { r: { grid: { color: GRID_COLOR }, ticks: { display: false, backdropColor: 'transparent' } } },
        },
    });

    // ── Scatter (capped at 3 series — all-pairs comparison) ────────────────
    function randPoints(n, xMax, yMax) {
        return Array.from({ length: n }, () => ({ x: +(Math.random() * xMax).toFixed(1), y: +(Math.random() * yMax).toFixed(1) }));
    }
    new Chart(document.getElementById('chartScatter'), {
        type: 'scatter',
        data: {
            datasets: [
                { label: 'Team A', data: randPoints(14, 10, 10), backgroundColor: PALETTE.blue },
                { label: 'Team B', data: randPoints(14, 10, 10), backgroundColor: PALETTE.orange },
                { label: 'Team C', data: randPoints(14, 10, 10), backgroundColor: PALETTE.aqua },
            ],
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true, pointStyle: 'circle' } } },
            scales: {
                x: { title: { display: true, text: 'Mandays', color: TEXT_MUTED }, grid: gridOpts, ticks: tickOpts, border: { display: false } },
                y: { title: { display: true, text: 'Resolution Days', color: TEXT_MUTED }, grid: gridOpts, ticks: tickOpts, border: { display: false } },
            },
        },
    });

    // ── Bubble (capped at 3 series) ─────────────────────────────────────────
    function randBubbles(n, xMax, yMax, rMax) {
        return Array.from({ length: n }, () => ({ x: +(Math.random() * xMax).toFixed(1), y: +(Math.random() * yMax).toFixed(1), r: 4 + Math.random() * rMax }));
    }
    new Chart(document.getElementById('chartBubble'), {
        type: 'bubble',
        data: {
            datasets: [
                { label: 'Customer A', data: randBubbles(8, 10, 10, 10), backgroundColor: alpha(PALETTE.blue, 0.6) },
                { label: 'Customer B', data: randBubbles(8, 10, 10, 10), backgroundColor: alpha(PALETTE.orange, 0.6) },
                { label: 'Customer C', data: randBubbles(8, 10, 10, 10), backgroundColor: alpha(PALETTE.aqua, 0.6) },
            ],
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true, pointStyle: 'circle' } } },
            scales: {
                x: { title: { display: true, text: 'Ticket Count', color: TEXT_MUTED }, grid: gridOpts, ticks: tickOpts, border: { display: false } },
                y: { title: { display: true, text: 'Average Mandays', color: TEXT_MUTED }, grid: gridOpts, ticks: tickOpts, border: { display: false } },
            },
        },
    });
})();
</script>
@endpush
@endsection

@extends('dashboard')

@section('title', 'Dropdown Settings')
@section('page-title', 'Dropdown Settings')

@section('content')
<div class="bg-white rounded-xl p-6 shadow-sm">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 pb-4 border-b-2 border-gray-100">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Dropdown Settings</h2>
            <p class="text-sm text-gray-500 mt-1">Manage the dropdown lists used across Employee Information (Position, Division, Department, etc.) — pick a list on the left, then add/edit/remove its values.</p>
        </div>
        @if($can('management.employee.dropdown-settings.manage'))
        <button onclick="openCreateConfigModal()" class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200 shrink-0">
            <i class="fas fa-plus mr-2"></i> New Dropdown List
        </button>
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-[280px_1fr] gap-6">
        <!-- ── Left: config list ──────────────────────────────────────────── -->
        <div class="border border-gray-200 rounded-lg overflow-hidden">
            <div class="px-4 py-2.5 bg-gray-50 border-b border-gray-200">
                <p class="text-xs font-semibold text-gray-600 uppercase tracking-wider">Dropdown Lists</p>
            </div>
            <div id="configList" class="divide-y divide-gray-100 max-h-[70vh] overflow-y-auto">
                <div class="px-4 py-8 text-center text-gray-400 text-sm">Loading...</div>
            </div>
        </div>

        <!-- ── Right: selected config's values ────────────────────────────── -->
        <div id="valuesPanel" class="border border-gray-200 rounded-lg overflow-hidden">
            <div class="px-4 py-10 text-center text-gray-400 text-sm">
                <i class="fas fa-arrow-left mr-1"></i> Select a dropdown list to manage its values.
            </div>
        </div>
    </div>
</div>

<!-- ── Modal: Create / Edit Dropdown List (config) ─────────────────────────── -->
<div id="configModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md">
        <div class="flex justify-between items-center p-6 border-b border-gray-100">
            <h3 id="configModalTitle" class="text-lg font-bold text-gray-900">New Dropdown List</h3>
            <button onclick="closeModal('configModal')" class="text-gray-400 hover:text-gray-600 transition-colors">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <form id="configForm" onsubmit="submitConfig(event)" class="p-6 space-y-4">
            <input type="hidden" id="configId">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Name <span class="text-red-500">*</span></label>
                <input type="text" id="configName" required maxlength="150" oninput="autoFillConfigCode()"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent"
                    placeholder="e.g. Cost Center">
                <p class="text-xs text-gray-400 mt-1">Shown as the label wherever this dropdown is used.</p>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Code <span class="text-red-500">*</span></label>
                <input type="text" id="configCode" required maxlength="100" pattern="[a-z0-9_]+"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent"
                    placeholder="e.g. cost_center">
                <p class="text-xs text-gray-400 mt-1">Lowercase letters, numbers, underscores only. Used internally to look this list up — avoid changing it once other code depends on it.</p>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Description</label>
                <textarea id="configDescription" maxlength="1000" rows="2"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent"
                    placeholder="Optional note about what this list is for"></textarea>
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" id="configActive" checked class="w-4 h-4 rounded cursor-pointer accent-red-800">
                <label for="configActive" class="text-sm text-gray-700 cursor-pointer">Active</label>
            </div>
            <div id="configFormError" class="hidden text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-4 py-2.5"></div>
            <div class="flex gap-3 justify-end pt-2">
                <button type="button" onclick="closeModal('configModal')" class="px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">Cancel</button>
                <button type="submit" class="px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Modal: Create / Edit Value ──────────────────────────────────────────── -->
<div id="valueModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md">
        <div class="flex justify-between items-center p-6 border-b border-gray-100">
            <h3 id="valueModalTitle" class="text-lg font-bold text-gray-900">Add Value</h3>
            <button onclick="closeModal('valueModal')" class="text-gray-400 hover:text-gray-600 transition-colors">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <form id="valueForm" onsubmit="submitValue(event)" class="p-6 space-y-4">
            <input type="hidden" id="valueId">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Value <span class="text-red-500">*</span></label>
                <input type="text" id="valueText" required maxlength="255"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent"
                    placeholder="e.g. SAP CONSULTANT">
                <p class="text-xs text-gray-400 mt-1">Exactly as it should appear in the dropdown (and as it will be stored on the employee record).</p>
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" id="valueActive" checked class="w-4 h-4 rounded cursor-pointer accent-red-800">
                <label for="valueActive" class="text-sm text-gray-700 cursor-pointer">Active (offered in the dropdown)</label>
            </div>
            <p class="text-xs text-gray-400">New values are added at the end of the list. Use the ↑ / ↓ buttons on the list to reorder afterwards.</p>
            <div id="valueFormError" class="hidden text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-4 py-2.5"></div>
            <div class="flex gap-3 justify-end pt-2">
                <button type="button" onclick="closeModal('valueModal')" class="px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">Cancel</button>
                <button type="submit" class="px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Modal: Custom Confirm ───────────────────────────────────────────────── -->
<div id="confirmModal" class="hidden fixed inset-0 z-[60] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm">
        <div class="p-6 text-center">
            <div id="confirmIconWrap" class="w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-4">
                <i id="confirmIcon" class="text-xl"></i>
            </div>
            <h3 id="confirmTitle" class="text-base font-semibold text-gray-800 mb-2"></h3>
            <p id="confirmMessage" class="text-sm text-gray-500 mb-6 leading-relaxed"></p>
            <div class="flex gap-3">
                <button id="confirmCancelBtn" class="flex-1 border border-gray-200 rounded-xl py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-50 transition">Cancel</button>
                <button id="confirmOkBtn" class="flex-1 rounded-xl py-2.5 text-sm font-semibold text-white transition">Confirm</button>
            </div>
        </div>
    </div>
</div>

<style>
.config-row { cursor: pointer; transition: background 0.1s; }
.config-row:hover { background: #f9fafb; }
.config-row.active { background: #fef2f2; box-shadow: inset 3px 0 0 #991b1b; }
</style>

<script>
const CAN_MANAGE_DROPDOWNS = {{ $can('management.employee.dropdown-settings.manage') ? 'true' : 'false' }};

let configsData  = [];
let selectedConfigId = null;
let valuesData   = [];

// ── Pagination state (values table paginates client-side, same data already loaded).
// The Dropdown Lists panel on the left just scrolls (max-h + overflow-y-auto) —
// its list is short enough that paging it adds more clicks than it saves. ──
const VALUE_PAGE_SIZE  = 10;
let valuePage  = 1;

// ── Filter state (client-side — same data already loaded) ───────────────────
let valueFilters  = { search: '', status: '' };

function matchesStatusFilter(isActive, statusFilter) {
    if (statusFilter === 'active')   return !!isActive;
    if (statusFilter === 'inactive') return !isActive;
    return true;
}

function onValueFilterSearchInput() {
    valueFilters.search = document.getElementById('valueFilterSearch').value.toLowerCase().trim();
    valuePage = 1;
    updateValueFilterIndicators();
    renderValueTableBody();
}

function clearValueFilterSearch() {
    const input = document.getElementById('valueFilterSearch');
    if (input) input.value = '';
    valueFilters.search = '';
    valuePage = 1;
    updateValueFilterIndicators();
    renderValueTableBody();
    closeValueFilterPanel();
}

function setValueStatusFilter(status) {
    valueFilters.status = status;
    valuePage = 1;
    closeStatusFilterPanel();
    updateValueFilterIndicators();
    renderValueTableBody();
}

function updateValueFilterIndicators() {
    const searchIcon = document.getElementById('valueFilterIcon');
    searchIcon?.classList.toggle('text-red-500', !!valueFilters.search);
    searchIcon?.classList.toggle('text-gray-300', !valueFilters.search);

    const statusIcon = document.getElementById('statusFilterIcon');
    statusIcon?.classList.toggle('text-red-500', !!valueFilters.status);
    statusIcon?.classList.toggle('text-gray-300', !valueFilters.status);

    document.querySelectorAll('.value-status-option').forEach(btn => {
        const match = btn.dataset.value === valueFilters.status;
        btn.classList.toggle('bg-gray-50', match);
        btn.classList.toggle('text-red-700', match);
        btn.classList.toggle('font-semibold', match);
    });
}

// ── Column filter popovers (Value / Status) — icon-triggered, same pattern
// as the Ticket table's column filters. position:fixed so the panel escapes
// the table's overflow-x-auto clipping without needing to move it in the DOM. ──
function positionFilterPanel(btn, panel) {
    const rect = btn.getBoundingClientRect();
    panel.style.top  = (rect.bottom + 4) + 'px';
    panel.style.left = rect.left + 'px';
}

function toggleValueFilterPanel(ev) {
    ev?.stopPropagation();
    const panel = document.getElementById('valueFilterPanel');
    const btn   = document.getElementById('valueColFilterBtn');
    const open  = !panel.classList.contains('hidden');
    closeStatusFilterPanel();
    if (open) { panel.classList.add('hidden'); return; }
    positionFilterPanel(btn, panel);
    panel.classList.remove('hidden');
    document.getElementById('valueFilterSearch')?.focus();
}
function closeValueFilterPanel() {
    document.getElementById('valueFilterPanel')?.classList.add('hidden');
}

function toggleStatusFilterPanel(ev) {
    ev?.stopPropagation();
    const panel = document.getElementById('statusFilterPanel');
    const btn   = document.getElementById('statusColFilterBtn');
    const open  = !panel.classList.contains('hidden');
    closeValueFilterPanel();
    if (open) { panel.classList.add('hidden'); return; }
    positionFilterPanel(btn, panel);
    panel.classList.remove('hidden');
}
function closeStatusFilterPanel() {
    document.getElementById('statusFilterPanel')?.classList.add('hidden');
}

document.addEventListener('click', (e) => {
    const vp = document.getElementById('valueFilterPanel');
    const vb = document.getElementById('valueColFilterBtn');
    if (vp && !vp.classList.contains('hidden') && !vp.contains(e.target) && vb && !vb.contains(e.target)) vp.classList.add('hidden');
    const sp = document.getElementById('statusFilterPanel');
    const sb = document.getElementById('statusColFilterBtn');
    if (sp && !sp.classList.contains('hidden') && !sp.contains(e.target) && sb && !sb.contains(e.target)) sp.classList.add('hidden');
});
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') { closeValueFilterPanel(); closeStatusFilterPanel(); }
});
window.addEventListener('scroll', (e) => {
    const t = e.target;
    if (t && t.nodeType === 1 && t.closest && (t.closest('#valueFilterPanel') || t.closest('#statusFilterPanel'))) return;
    closeValueFilterPanel();
    closeStatusFilterPanel();
}, true);
window.addEventListener('resize', () => { closeValueFilterPanel(); closeStatusFilterPanel(); });

function filteredValues() {
    return valuesData.filter(v => {
        if (!matchesStatusFilter(v.is_active, valueFilters.status)) return false;
        if (valueFilters.search && !v.value.toLowerCase().includes(valueFilters.search)) return false;
        return true;
    });
}

function isValueFilterActive() {
    return !!(valueFilters.search || valueFilters.status);
}

function paginate(items, page, pageSize) {
    const totalItems = items.length;
    const totalPages = Math.max(1, Math.ceil(totalItems / pageSize));
    const clampedPage = Math.min(Math.max(1, page), totalPages);
    const start = (clampedPage - 1) * pageSize;
    return { pageItems: items.slice(start, start + pageSize), totalItems, totalPages, page: clampedPage };
}

// Renders a compact Prev/page-numbers/Next bar into the given container id.
// `onPage(page)` is called when the user picks a page.
function renderPaginationBar(containerId, page, totalPages, onPageName) {
    const el = document.getElementById(containerId);
    if (!el) return;
    if (totalPages <= 1) { el.innerHTML = ''; return; }

    const pages = [];
    pages.push(1);
    for (let p = page - 1; p <= page + 1; p++) if (p > 1 && p < totalPages) pages.push(p);
    if (totalPages > 1) pages.push(totalPages);
    const uniquePages = [...new Set(pages)].sort((a, b) => a - b);

    let numbersHtml = '';
    let prev = 0;
    uniquePages.forEach(p => {
        if (p - prev > 1) numbersHtml += `<span class="px-1 text-xs text-gray-300 select-none">…</span>`;
        numbersHtml += `<button type="button" onclick="${onPageName}(${p})"
            class="inline-flex items-center justify-center min-w-[26px] h-[26px] px-1 rounded-md text-xs font-semibold transition-all ${p === page
                ? 'bg-red-800 text-white'
                : 'text-gray-500 bg-white border border-gray-200 hover:bg-gray-50'}">${p}</button>`;
        prev = p;
    });

    el.innerHTML = `
        <div class="flex items-center justify-center gap-1">
            <button type="button" onclick="${onPageName}(${page - 1})" ${page === 1 ? 'disabled' : ''}
                class="inline-flex items-center justify-center w-[26px] h-[26px] rounded-md text-xs text-gray-500 bg-white border border-gray-200 hover:bg-gray-50 disabled:opacity-30 disabled:cursor-not-allowed">
                <i class="fas fa-chevron-left text-[10px]"></i>
            </button>
            ${numbersHtml}
            <button type="button" onclick="${onPageName}(${page + 1})" ${page === totalPages ? 'disabled' : ''}
                class="inline-flex items-center justify-center w-[26px] h-[26px] rounded-md text-xs text-gray-500 bg-white border border-gray-200 hover:bg-gray-50 disabled:opacity-30 disabled:cursor-not-allowed">
                <i class="fas fa-chevron-right text-[10px]"></i>
            </button>
        </div>`;
}

function valueGoToPage(page)  { valuePage = page; renderValueTableBody(); }

// ── Load configs ─────────────────────────────────────────────────────────────

async function loadConfigs(reselectId) {
    const res  = await fetch('/api/management/dropdown-configs');
    const json = await res.json();
    configsData = json.data || [];
    renderConfigList();

    const idToSelect = reselectId ?? selectedConfigId ?? (configsData[0]?.id ?? null);
    if (idToSelect && configsData.some(c => c.id === idToSelect)) {
        selectConfig(idToSelect);
    } else {
        selectedConfigId = null;
        document.getElementById('valuesPanel').innerHTML = `
            <div class="px-4 py-10 text-center text-gray-400 text-sm">
                <i class="fas fa-arrow-left mr-1"></i> Select a dropdown list to manage its values.
            </div>`;
    }
}

function renderConfigList() {
    const wrap = document.getElementById('configList');
    if (!configsData.length) {
        wrap.innerHTML = `<div class="px-4 py-8 text-center text-gray-400 text-sm">No dropdown lists yet.</div>`;
        return;
    }

    wrap.innerHTML = configsData.map(c => `
        <div class="config-row px-4 py-3 ${c.id === selectedConfigId ? 'active' : ''}" onclick="selectConfig(${c.id})">
            <div class="flex items-center justify-between gap-2">
                <span class="text-sm font-semibold text-gray-800 truncate">${escHtml(c.name)}</span>
                ${c.is_active ? '' : '<span class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-gray-100 text-gray-500 shrink-0">Inactive</span>'}
            </div>
            <div class="flex items-center justify-between gap-2 mt-1">
                <span class="text-[11px] font-mono text-gray-400 truncate">${escHtml(c.code)}</span>
                <span class="text-[11px] text-gray-400 shrink-0">${c.values_count} value${c.values_count === 1 ? '' : 's'}</span>
            </div>
        </div>`).join('');
}

// ── Select config + load its values ──────────────────────────────────────────

async function selectConfig(id) {
    const isNewConfig = id !== selectedConfigId;
    selectedConfigId = id;
    renderConfigList();

    const panel = document.getElementById('valuesPanel');
    panel.innerHTML = `<div class="px-4 py-10 text-center text-gray-400 text-sm">Loading...</div>`;

    const config = configsData.find(c => c.id === id);
    const res    = await fetch(`/api/management/dropdown-configs/${id}/values`);
    const json   = await res.json();
    valuesData   = json.data || [];
    if (isNewConfig) {
        valuePage = 1; // fresh list — start from page 1
        valueFilters = { search: '', status: '' };
    }

    renderValuesPanel(config);
}

function renderValuesPanel(config) {
    const panel = document.getElementById('valuesPanel');
    panel.innerHTML = `
        <div class="px-4 py-3 bg-gray-50 border-b border-gray-200 flex items-start justify-between gap-3">
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <p class="text-sm font-bold text-gray-900 truncate">${escHtml(config.name)}</p>
                    <span class="text-[11px] font-mono text-gray-400">${escHtml(config.code)}</span>
                </div>
                ${config.description ? `<p class="text-xs text-gray-500 mt-0.5">${escHtml(config.description)}</p>` : ''}
            </div>
            <div class="flex items-center gap-1.5 shrink-0">
                ${CAN_MANAGE_DROPDOWNS ? `
                <button onclick="openEditConfigModal(${config.id})" title="Edit list"
                    class="w-8 h-8 flex items-center justify-center text-xs bg-white text-gray-700 border border-gray-200 rounded-lg hover:bg-gray-100 transition-colors">
                    <i class="fas fa-pen"></i>
                </button>
                <button onclick="deleteConfig(${config.id})" title="Delete list"
                    class="w-8 h-8 flex items-center justify-center text-xs bg-red-50 text-red-700 border border-red-200 rounded-lg hover:bg-red-100 transition-colors">
                    <i class="fas fa-trash"></i>
                </button>` : ''}
            </div>
        </div>
        <div class="px-4 py-2.5 border-b border-gray-100 flex items-center justify-between">
            <p class="text-xs text-gray-400" id="valuesCountText">${valuesData.length} value${valuesData.length === 1 ? '' : 's'}</p>
            ${CAN_MANAGE_DROPDOWNS ? `
            <button onclick="openCreateValueModal()"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-all">
                <i class="fas fa-plus text-[10px]"></i> Add Value
            </button>` : ''}
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider" style="width:70px;">Order</th>
                        <th class="p-0 text-left" style="min-width:160px;">
                            <button type="button" id="valueColFilterBtn" onclick="toggleValueFilterPanel(event)"
                                class="w-full flex items-center gap-1.5 px-4 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                                <span class="text-xs font-semibold text-gray-600 uppercase tracking-wider">Value</span>
                                <svg id="valueFilterIcon" class="w-3.5 h-3.5 text-gray-300 transition-colors ml-auto shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 011 1v1.586a1 1 0 01-.293.707l-4.121 4.121A1 1 0 0012 12.121V15.5l-4 1.5v-4.879a1 1 0 00-.293-.707L3.586 7.293A1 1 0 013.293 6.586L3 5z" clip-rule="evenodd" />
                                </svg>
                            </button>
                            <div id="valueFilterPanel" class="hidden fixed bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] p-3" style="min-width:220px;">
                                <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Search value</label>
                                <input type="text" id="valueFilterSearch" placeholder="Type to search..." oninput="onValueFilterSearchInput()"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm font-normal normal-case tracking-normal text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
                                <div class="flex justify-end gap-2 mt-3">
                                    <button type="button" onclick="clearValueFilterSearch()" class="px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50">Clear</button>
                                </div>
                            </div>
                        </th>
                        <th class="p-0 text-left" style="width:110px;">
                            <button type="button" id="statusColFilterBtn" onclick="toggleStatusFilterPanel(event)"
                                class="w-full flex items-center gap-1.5 px-4 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                                <span class="text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</span>
                                <svg id="statusFilterIcon" class="w-3.5 h-3.5 text-gray-300 transition-colors ml-auto shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 011 1v1.586a1 1 0 01-.293.707l-4.121 4.121A1 1 0 0012 12.121V15.5l-4 1.5v-4.879a1 1 0 00-.293-.707L3.586 7.293A1 1 0 013.293 6.586L3 5z" clip-rule="evenodd" />
                                </svg>
                            </button>
                            <div id="statusFilterPanel" class="hidden fixed bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] py-1.5" style="min-width:160px;">
                                <button type="button" data-value="" onclick="setValueStatusFilter('')" class="value-status-option w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">All Status</button>
                                <button type="button" data-value="active" onclick="setValueStatusFilter('active')" class="value-status-option w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">Active</button>
                                <button type="button" data-value="inactive" onclick="setValueStatusFilter('inactive')" class="value-status-option w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">Inactive</button>
                            </div>
                        </th>
                        ${CAN_MANAGE_DROPDOWNS ? '<th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider" style="width:170px;">Actions</th>' : ''}
                    </tr>
                </thead>
                <tbody id="valuesTableBody" class="divide-y divide-gray-100">
                </tbody>
            </table>
        </div>
        <div id="valuePagination" class="px-3 py-2 border-t border-gray-100"></div>`;

    document.getElementById('valueFilterSearch').value = valueFilters.search;
    updateValueFilterIndicators();

    renderValueTableBody();
}

function renderValueTableBody() {
    const body = document.getElementById('valuesTableBody');
    if (!body) return;

    const countText = document.getElementById('valuesCountText');
    if (countText) {
        countText.textContent = isValueFilterActive()
            ? `${filteredValues().length} of ${valuesData.length} value${valuesData.length === 1 ? '' : 's'}`
            : `${valuesData.length} value${valuesData.length === 1 ? '' : 's'}`;
    }

    if (!valuesData.length) {
        body.innerHTML = `<tr><td colspan="${CAN_MANAGE_DROPDOWNS ? 4 : 3}" class="px-4 py-8 text-center text-gray-400">No values yet.${CAN_MANAGE_DROPDOWNS ? ' Click "Add Value".' : ''}</td></tr>`;
        document.getElementById('valuePagination').innerHTML = '';
        return;
    }

    const filtered = filteredValues();
    if (!filtered.length) {
        body.innerHTML = `<tr><td colspan="${CAN_MANAGE_DROPDOWNS ? 4 : 3}" class="px-4 py-8 text-center text-gray-400">No values match your filter.</td></tr>`;
        document.getElementById('valuePagination').innerHTML = '';
        return;
    }

    const { pageItems, totalPages, page } = paginate(filtered, valuePage, VALUE_PAGE_SIZE);
    valuePage = page; // clamped

    // Reordering only makes sense against the true, unfiltered order — offer
    // ↑ / ↓ only while no filter narrows what's on screen.
    const canReorder = CAN_MANAGE_DROPDOWNS && !isValueFilterActive();

    body.innerHTML = pageItems.map(v => {
        const trueIdx = valuesData.findIndex(x => x.id === v.id);
        return `
        <tr class="hover:bg-gray-50 transition-colors ${v.is_active ? '' : 'opacity-50'}">
            <td class="px-4 py-2.5 text-gray-400 text-xs">${v.sort_order}</td>
            <td class="px-4 py-2.5 text-gray-800 font-medium">${escHtml(v.value)}</td>
            <td class="px-4 py-2.5">
                ${v.is_active
                    ? '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-green-50 text-green-700">Active</span>'
                    : '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-gray-100 text-gray-500">Inactive</span>'}
            </td>
            ${CAN_MANAGE_DROPDOWNS ? `
            <td class="px-4 py-2.5">
                <div class="flex items-center justify-end gap-1.5">
                    ${canReorder ? `
                    <button onclick="moveValue(${v.id}, 'up')" title="Move up" ${trueIdx === 0 ? 'disabled' : ''}
                        class="w-7 h-7 flex items-center justify-center text-xs bg-white text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-100 transition-colors disabled:opacity-30 disabled:cursor-not-allowed">
                        <i class="fas fa-arrow-up"></i>
                    </button>
                    <button onclick="moveValue(${v.id}, 'down')" title="Move down" ${trueIdx === valuesData.length - 1 ? 'disabled' : ''}
                        class="w-7 h-7 flex items-center justify-center text-xs bg-white text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-100 transition-colors disabled:opacity-30 disabled:cursor-not-allowed">
                        <i class="fas fa-arrow-down"></i>
                    </button>` : ''}
                    <button onclick="openEditValueModal(${v.id})" title="Edit"
                        class="w-7 h-7 flex items-center justify-center text-xs bg-gray-100 text-gray-700 border border-gray-200 rounded-lg hover:bg-gray-200 transition-colors">
                        <i class="fas fa-pen"></i>
                    </button>
                    <button onclick="deleteValue(${v.id})" title="Delete"
                        class="w-7 h-7 flex items-center justify-center text-xs bg-red-50 text-red-700 border border-red-200 rounded-lg hover:bg-red-100 transition-colors">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>` : ''}
        </tr>`;
    }).join('');

    renderPaginationBar('valuePagination', valuePage, totalPages, 'valueGoToPage');
}

// ── Config: create / edit / delete ───────────────────────────────────────────

function openCreateConfigModal() {
    document.getElementById('configModalTitle').textContent = 'New Dropdown List';
    document.getElementById('configId').value = '';
    document.getElementById('configName').value = '';
    document.getElementById('configCode').value = '';
    document.getElementById('configCode').readOnly = false;
    document.getElementById('configDescription').value = '';
    document.getElementById('configActive').checked = true;
    hideFormError('configFormError');
    openModal('configModal');
}

function openEditConfigModal(id) {
    const c = configsData.find(x => x.id === id);
    if (!c) return;
    document.getElementById('configModalTitle').textContent = 'Edit Dropdown List';
    document.getElementById('configId').value = c.id;
    document.getElementById('configName').value = c.name;
    document.getElementById('configCode').value = c.code;
    // Code is the internal lookup key other code may already depend on
    // (e.g. AppServiceProvider's DropdownConfig::optionsFor('position')) —
    // block accidental edits, still changeable if the admin really needs to.
    document.getElementById('configCode').readOnly = true;
    document.getElementById('configDescription').value = c.description || '';
    document.getElementById('configActive').checked = !!c.is_active;
    hideFormError('configFormError');
    openModal('configModal');
}

function autoFillConfigCode() {
    const codeEl = document.getElementById('configCode');
    if (codeEl.readOnly || codeEl.dataset.touched === '1') return;
    codeEl.value = document.getElementById('configName').value
        .toLowerCase().trim()
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '');
}
document.getElementById('configCode').addEventListener('input', () => {
    document.getElementById('configCode').dataset.touched = '1';
});

async function submitConfig(e) {
    e.preventDefault();
    const id = document.getElementById('configId').value;
    const payload = {
        code:        document.getElementById('configCode').value.trim(),
        name:        document.getElementById('configName').value.trim(),
        description: document.getElementById('configDescription').value.trim() || null,
        is_active:   document.getElementById('configActive').checked,
    };
    const url    = id ? `/api/management/dropdown-configs/${id}` : '/api/management/dropdown-configs';
    const method = id ? 'PUT' : 'POST';

    const res  = await fetch(url, { method, headers: jsonHeaders(), body: JSON.stringify(payload) });
    const json = await res.json();

    if (res.ok && json.success) {
        closeModal('configModal');
        document.getElementById('configCode').dataset.touched = '';
        showToast(id ? 'Dropdown list updated.' : 'Dropdown list created.', 'success');
        loadConfigs(id ? Number(id) : json.data.id);
    } else {
        showFormError('configFormError', firstErrorMessage(json));
    }
}

async function deleteConfig(id) {
    const c = configsData.find(x => x.id === id);
    const ok = await customConfirm({
        title:     'Delete Dropdown List?',
        message:   `You are about to delete "${c?.name ?? ''}" and all ${c?.values_count ?? 0} of its values. Any employee record already using one of those values keeps it as-is, but it will no longer be offered in the dropdown. This cannot be undone.`,
        okLabel:   'Yes, Delete',
        okClass:   'bg-red-600 hover:bg-red-700',
        icon:      'fas fa-trash',
        iconBg:    'bg-red-50',
        iconColor: 'text-red-500',
    });
    if (!ok) return;

    const res  = await fetch(`/api/management/dropdown-configs/${id}/delete`, { method: 'POST', headers: jsonHeaders() });
    const json = await res.json();
    if (json.success) {
        showToast('Dropdown list deleted.', 'success');
        if (selectedConfigId === id) selectedConfigId = null;
        loadConfigs();
    } else {
        showToast(json.message || 'Failed to delete dropdown list.', 'error');
    }
}

// ── Value: create / edit / delete ────────────────────────────────────────────

function openCreateValueModal() {
    document.getElementById('valueModalTitle').textContent = 'Add Value';
    document.getElementById('valueId').value = '';
    document.getElementById('valueText').value = '';
    document.getElementById('valueActive').checked = true;
    hideFormError('valueFormError');
    openModal('valueModal');
}

function openEditValueModal(id) {
    const v = valuesData.find(x => x.id === id);
    if (!v) return;
    document.getElementById('valueModalTitle').textContent = 'Edit Value';
    document.getElementById('valueId').value = v.id;
    document.getElementById('valueText').value = v.value;
    document.getElementById('valueActive').checked = !!v.is_active;
    hideFormError('valueFormError');
    openModal('valueModal');
}

async function submitValue(e) {
    e.preventDefault();
    if (!selectedConfigId) return;
    const id = document.getElementById('valueId').value;
    // No sort_order here on purpose — creating leaves it out so the backend
    // auto-appends to the end, and editing leaves it out so save-as-text
    // never accidentally reshuffles the list. Reordering happens only via
    // the ↑ / ↓ buttons (moveValue()), which target sort_order directly.
    const payload = {
        value:      document.getElementById('valueText').value.trim(),
        is_active:  document.getElementById('valueActive').checked,
    };
    const url    = id ? `/api/management/dropdown-configs/${selectedConfigId}/values/${id}` : `/api/management/dropdown-configs/${selectedConfigId}/values`;
    const method = id ? 'PUT' : 'POST';

    const res  = await fetch(url, { method, headers: jsonHeaders(), body: JSON.stringify(payload) });
    const json = await res.json();

    if (res.ok && json.success) {
        closeModal('valueModal');
        showToast(id ? 'Value updated.' : 'Value added.', 'success');
        const configId = selectedConfigId;
        await loadConfigs(configId); // refresh values_count on the left too
    } else {
        showFormError('valueFormError', firstErrorMessage(json));
    }
}

// ── Reorder (Move Up / Move Down) ────────────────────────────────────────────
// Swaps sort_order with the true previous/next item (valuesData is already
// sorted by sort_order from the API) — simple, error-proof nudging instead of
// hand-typed order numbers. Only offered when no filter is active so "up"/
// "down" always matches what's visibly adjacent on screen.
async function moveValue(id, direction) {
    const idx = valuesData.findIndex(v => v.id === id);
    if (idx === -1) return;
    const neighborIdx = direction === 'up' ? idx - 1 : idx + 1;
    if (neighborIdx < 0 || neighborIdx >= valuesData.length) return;

    const a = valuesData[idx];
    const b = valuesData[neighborIdx];

    const swap = (item, newSortOrder) => fetch(`/api/management/dropdown-configs/${selectedConfigId}/values/${item.id}`, {
        method: 'PUT',
        headers: jsonHeaders(),
        body: JSON.stringify({ value: item.value, is_active: item.is_active, sort_order: newSortOrder }),
    });

    const [resA, resB] = await Promise.all([swap(a, b.sort_order), swap(b, a.sort_order)]);
    if (resA.ok && resB.ok) {
        await selectConfig(selectedConfigId); // refresh in the new order
    } else {
        showToast('Failed to reorder. Please try again.', 'error');
    }
}

async function deleteValue(id) {
    const v = valuesData.find(x => x.id === id);
    const ok = await customConfirm({
        title:     'Delete Value?',
        message:   `You are about to delete "${v?.value ?? ''}". This cannot be undone.`,
        okLabel:   'Yes, Delete',
        okClass:   'bg-red-600 hover:bg-red-700',
        icon:      'fas fa-trash',
        iconBg:    'bg-red-50',
        iconColor: 'text-red-500',
    });
    if (!ok) return;

    const res  = await fetch(`/api/management/dropdown-configs/${selectedConfigId}/values/${id}/delete`, { method: 'POST', headers: jsonHeaders() });
    const json = await res.json();
    if (json.success) {
        showToast('Value deleted.', 'success');
        const configId = selectedConfigId;
        await loadConfigs(configId);
    } else {
        showToast(json.message || 'Failed to delete value.', 'error');
    }
}

// ── Custom Confirm (same pattern as Holidays admin page) ─────────────────────

function customConfirm({ title, message, okLabel = 'Confirm', okClass = 'bg-red-600 hover:bg-red-700', icon = 'fas fa-exclamation-triangle', iconBg = 'bg-red-50', iconColor = 'text-red-500' } = {}) {
    return new Promise(resolve => {
        const modal     = document.getElementById('confirmModal');
        const okBtn     = document.getElementById('confirmOkBtn');
        const cancelBtn = document.getElementById('confirmCancelBtn');
        document.getElementById('confirmTitle').textContent   = title || '';
        document.getElementById('confirmMessage').textContent = message || '';
        okBtn.textContent = okLabel;
        okBtn.className   = `flex-1 rounded-xl py-2.5 text-sm font-semibold text-white transition ${okClass}`;
        document.getElementById('confirmIconWrap').className = `w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-4 ${iconBg}`;
        document.getElementById('confirmIcon').className     = `${icon} text-xl ${iconColor}`;
        modal.classList.remove('hidden');
        function done(val) {
            modal.classList.add('hidden');
            okBtn.removeEventListener('click', onOk);
            cancelBtn.removeEventListener('click', onCancel);
            resolve(val);
        }
        const onOk     = () => done(true);
        const onCancel = () => done(false);
        okBtn.addEventListener('click', onOk);
        cancelBtn.addEventListener('click', onCancel);
    });
}

// ── Utilities ─────────────────────────────────────────────────────────────────

function openModal(id)  { document.getElementById(id).classList.remove('hidden'); }
function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

function jsonHeaders() {
    return {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
    };
}

function showFormError(elId, message) {
    const el = document.getElementById(elId);
    el.textContent = message;
    el.classList.remove('hidden');
}
function hideFormError(elId) {
    document.getElementById(elId).classList.add('hidden');
}
function firstErrorMessage(json) {
    if (json?.errors) {
        const first = Object.values(json.errors)[0];
        if (Array.isArray(first)) return first[0];
    }
    return json?.message || 'Something went wrong. Please try again.';
}

function escHtml(str) {
    const d = document.createElement('div');
    d.textContent = str ?? '';
    return d.innerHTML;
}

document.getElementById('configModal').addEventListener('click', function(e) { if (e.target === this) closeModal('configModal'); });
document.getElementById('valueModal').addEventListener('click', function(e) { if (e.target === this) closeModal('valueModal'); });

// Init
loadConfigs();
</script>
@endsection

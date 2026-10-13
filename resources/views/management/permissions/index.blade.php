@extends('dashboard')

@section('title', 'Menu Access')
@section('page-title', 'Menu Access')

@php $canGrant = (bool) $canDo('management.roles'); @endphp

@section('content')
@push('styles')
<style>
    /* Mengikuti Settings → Appearance (Accent colour), seperti sidebar. */
    .acc-primary { background: rgb(var(--primary-rgb)); color: #fff; }
    .acc-primary:hover { background: rgb(var(--primary-dark-rgb, var(--primary-rgb))); }
    .acc-accent-text { color: rgb(var(--primary-rgb)); }
    .acc-ring:focus-visible { outline: 2px solid rgba(var(--primary-rgb), .55); outline-offset: 2px; }
    #accOverlay { position: fixed; inset: 0; background: rgba(17, 24, 39, .45); z-index: 60; opacity: 0; transition: opacity .18s; }
    #accOverlay.show { opacity: 1; }
    #accDrawer { position: fixed; top: 0; right: 0; height: 100%; width: min(860px, 100%); background: #fff; z-index: 61; display: flex; flex-direction: column;
        box-shadow: -12px 0 32px rgba(0,0,0,.18); transform: translateX(100%); transition: transform .22s ease; }
    #accDrawer.show { transform: translateX(0); }
    .acc-sw { position: relative; display: inline-flex; width: 2.25rem; height: 1.25rem; flex-shrink: 0; }
    .acc-sw input { position: absolute; inset: 0; opacity: 0; margin: 0; cursor: pointer; z-index: 1; }
    .acc-sw span { width: 100%; height: 100%; border-radius: 999px; background: #d1d5db; transition: background-color .15s; }
    .acc-sw span::after { content: ''; position: absolute; top: 2px; left: 2px; width: 1rem; height: 1rem; border-radius: 999px; background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.3); transition: transform .15s; }
    .acc-sw input:checked + span { background: rgb(var(--primary-rgb)); }
    .acc-sw input:checked + span::after { transform: translateX(1rem); }
    .acc-sw input:disabled { cursor: not-allowed; } .acc-sw input:disabled + span { opacity: .5; }
    .acc-box { width: 1.05rem; height: 1.05rem; accent-color: rgb(var(--primary-rgb)); cursor: pointer; }
    .acc-box:disabled { cursor: not-allowed; opacity: .45; }
    .acc-row { border-left: 3px solid transparent; }
    .acc-row.is-new { border-left-color: #16a34a; background: #f0fdf4; }
    .acc-row.is-removed { border-left-color: #dc2626; background: #fef2f2; }
    .acc-row.is-changed { border-left-color: #d97706; background: #fffbeb; }
    .acc-seg button { padding: .35rem .8rem; font-size: .75rem; font-weight: 600; color: #4b5563; }
    .acc-seg button.on { background: rgba(var(--primary-rgb), .12); color: rgb(var(--primary-rgb)); }
</style>
@endpush

<div class="bg-white rounded-xl p-6 shadow-sm">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 pb-4 border-b-2 border-gray-100">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Menu List</h2>
            <p class="text-xs text-gray-500 mt-1">Every menu and permission of the system. Click <span class="font-semibold">Access</span> on a menu to see which roles hold it — and to grant or remove it for several roles at once.</p>
        </div>
        <div class="flex gap-2">
            <button onclick="expandAll()" class="px-3 py-2 text-xs font-semibold bg-gray-100 text-gray-700 border border-gray-200 rounded-lg hover:bg-gray-200 transition-all">
                <i class="fas fa-plus-square mr-1"></i> Expand All
            </button>
            <button onclick="collapseAll()" class="px-3 py-2 text-xs font-semibold bg-gray-100 text-gray-700 border border-gray-200 rounded-lg hover:bg-gray-200 transition-all">
                <i class="fas fa-minus-square mr-1"></i> Collapse All
            </button>
        </div>
    </div>

    <!-- Filter -->
    <div class="flex gap-3 mb-5">
        <input type="text" id="filterSearch" placeholder="Search menu name or slug..."
            oninput="applyFilter()"
            class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 bg-white acc-ring">
        <button onclick="resetFilter()"
            class="px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">
            Reset
        </button>
    </div>

    <!-- List -->
    <div class="border border-gray-200 rounded-lg overflow-hidden">
        <div id="menuList" class="divide-y divide-gray-100">
            <div class="px-4 py-10 text-center text-gray-400 text-sm">Loading...</div>
        </div>
    </div>

    <!-- Legend -->
    <div class="mt-4 flex items-center gap-6 text-xs text-gray-400">
        <span class="flex items-center gap-1.5"><i class="fas fa-layer-group text-purple-400"></i> Group</span>
        <span class="flex items-center gap-1.5"><i class="fas fa-file-alt text-blue-400"></i> Page</span>
        <span class="flex items-center gap-1.5"><i class="fas fa-bolt text-orange-400"></i> Function / Button</span>
        <span class="flex items-center gap-1.5"><i class="fas fa-shield-halved"></i> Number = roles that hold it</span>
    </div>
</div>

{{-- ───────── Access drawer ───────── --}}
<div id="accOverlay" class="hidden" onclick="closeAccess()"></div>
<aside id="accDrawer" class="hidden" role="dialog" aria-modal="true" aria-labelledby="accTitle">
    <div class="px-6 pt-5 pb-3 border-b border-gray-100">
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <p id="accPath" class="text-[11px] text-gray-400 truncate"></p>
                <h3 id="accTitle" class="text-lg font-bold text-gray-900 leading-tight truncate">Access</h3>
                <p id="accSlug" class="text-xs font-mono text-gray-400 mt-0.5 truncate"></p>
            </div>
            <button type="button" onclick="closeAccess()" class="text-gray-400 hover:text-gray-700 text-2xl leading-none acc-ring" aria-label="Close">&times;</button>
        </div>
        <div id="accBadges" class="flex flex-wrap gap-2 mt-3"></div>
        <div id="accNotices" class="mt-3 space-y-2"></div>
        <div class="flex gap-6 mt-4 text-sm">
            <button type="button" id="tabAccess" onclick="setTab('access')" class="pb-2 font-semibold border-b-2 acc-ring">Roles &amp; access</button>
            <button type="button" id="tabHistory" onclick="setTab('history')" class="pb-2 font-semibold border-b-2 acc-ring">History</button>
        </div>
    </div>

    <div id="paneAccess" class="flex-1 min-h-0 flex flex-col">
        <div class="px-6 py-3 flex flex-wrap items-center gap-3 border-b border-gray-100 bg-gray-50/60">
            <div class="relative flex-1 min-w-[180px]">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
                <input id="accSearch" type="search" placeholder="Search role…" oninput="onAccSearch()" autocomplete="off"
                       class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none acc-ring">
            </div>
            <div class="acc-seg inline-flex border border-gray-300 rounded-lg overflow-hidden bg-white" id="accFilter">
                <button type="button" data-f="all" onclick="setFilter('all')">All</button>
                <button type="button" data-f="yes" onclick="setFilter('yes')">Has access</button>
                <button type="button" data-f="no" onclick="setFilter('no')">No access</button>
            </div>
            <div id="accScopeWrap" class="hidden">
                <select id="accScope" onchange="onScope()" class="px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white acc-ring"></select>
            </div>
        </div>

        <div id="accBulk" class="hidden px-6 py-2.5 items-center gap-2 bg-indigo-50/70 border-b border-indigo-100 text-sm">
            <span id="accBulkCount" class="font-semibold text-gray-800 mr-1"></span>
            <button type="button" onclick="bulk('grant')" class="px-3 py-1.5 text-xs font-semibold rounded-lg acc-primary">Grant access</button>
            <button type="button" onclick="bulk('revoke')" class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-red-300 text-red-700 bg-white hover:bg-red-50">Remove access</button>
            <button type="button" onclick="bulk('only')" class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-gray-300 text-gray-700 bg-white hover:bg-gray-50" title="Selected roles keep access, every other role loses it">Keep only these</button>
            <button type="button" onclick="clearSel()" class="ml-auto px-2 py-1.5 text-xs font-semibold text-gray-500 hover:text-gray-800">Clear selection</button>
        </div>

        <div class="flex-1 overflow-y-auto">
            <table class="w-full text-sm">
                <thead class="sticky top-0 bg-white z-10 shadow-[0_1px_0_#e5e7eb]">
                    <tr class="text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wide">
                        <th class="pl-6 pr-2 py-2.5 w-8"><input type="checkbox" id="accAll" class="acc-box" onchange="toggleAll(this.checked)" aria-label="Select all visible roles"></th>
                        <th class="px-2 py-2.5">Role</th>
                        <th class="px-2 py-2.5 text-center w-20">Access</th>
                        <th class="px-2 py-2.5 text-center w-16 acc-col-c">Create</th>
                        <th class="px-2 py-2.5 text-center w-16 acc-col-e">Edit</th>
                        <th class="px-2 py-2.5 text-center w-16 acc-col-d">Delete</th>
                        <th class="pr-6 py-2.5 w-24"></th>
                    </tr>
                </thead>
                <tbody id="accRows" class="divide-y divide-gray-100"></tbody>
            </table>
            <div id="accEmpty" class="hidden px-6 py-10 text-center text-sm text-gray-400">No role matches.</div>
        </div>

        <div class="px-6 py-3 border-t border-gray-200 bg-white flex items-center gap-3">
            <div id="accPending" class="text-sm text-gray-500 flex-1"></div>
            <button type="button" id="accDiscard" onclick="discard()" class="hidden px-3 py-2 text-sm font-semibold text-gray-600 hover:text-gray-900">Discard</button>
            <button type="button" id="accReviewBtn" onclick="review()" disabled class="px-4 py-2 text-sm font-semibold rounded-lg acc-primary disabled:opacity-40 disabled:cursor-not-allowed"><i class="fas fa-clipboard-check mr-2"></i>Review &amp; save</button>
        </div>
    </div>

    <div id="paneHistory" class="hidden flex-1 overflow-y-auto px-6 py-4"></div>

    {{-- Snackbar Undo --}}
    <div id="accSnack" class="hidden absolute left-6 right-6 bottom-20 bg-gray-900 text-white rounded-lg px-4 py-3 text-sm flex items-center gap-3 shadow-lg">
        <i class="fas fa-circle-check text-green-400"></i><span id="accSnackMsg" class="flex-1"></span>
        <button type="button" onclick="undoLast()" class="font-semibold underline underline-offset-2">Undo</button>
    </div>
</aside>

{{-- Review modal --}}
<div id="accReview" class="hidden fixed inset-0 z-[70] items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="accReviewTitle">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[88vh] flex flex-col">
        <div class="px-6 py-4 border-b border-gray-100 flex items-start justify-between">
            <div><h3 id="accReviewTitle" class="text-base font-bold text-gray-900">Review changes</h3><p id="accReviewSub" class="text-xs text-gray-500"></p></div>
            <button type="button" onclick="closeReview()" class="text-gray-400 hover:text-gray-700 text-2xl leading-none" aria-label="Close">&times;</button>
        </div>
        <div id="accReviewBody" class="px-6 py-4 overflow-y-auto space-y-4 text-sm"></div>
        <div class="px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-xl">
            <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Reason <span class="normal-case font-normal text-gray-400">(optional — kept in the history)</span></label>
            <input id="accReason" maxlength="255" placeholder="e.g. Only HR may edit employee data" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none acc-ring">
            <div class="flex justify-end gap-2 mt-4">
                <button type="button" onclick="closeReview()" class="px-4 py-2 text-sm font-semibold rounded-lg border border-gray-300 text-gray-700 hover:bg-white">Back</button>
                <button type="button" id="accConfirm" onclick="confirmApply()" class="px-4 py-2 text-sm font-semibold rounded-lg acc-primary"><i class="fas fa-check mr-2"></i>Save changes</button>
            </div>
        </div>
    </div>
</div>

<script>
const CAN_GRANT = @json($canGrant);
const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
const JSON_HEADERS = { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': CSRF };

let menusFlat = [], menuTree = [], expandedSet = new Set();

// ───────────── Menu list (tree) ─────────────
async function loadData() {
    const res = await fetch('/api/menus/all', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
    menusFlat = (await res.json()).data || [];
    menuTree = buildTree(menusFlat, null);
    menuTree.forEach(n => expandedSet.add(n.slug));
    renderList();
}
function buildTree(items, parentId) {
    return items.filter(m => m.parent_id == parentId).sort((a, b) => (a.order_seq || 0) - (b.order_seq || 0))
        .map(m => ({ ...m, children: buildTree(items, m.id) }));
}
function renderList() {
    const search = document.getElementById('filterSearch').value.toLowerCase().trim();
    const container = document.getElementById('menuList');
    container.innerHTML = '';
    let hasAny = false;
    menuTree.forEach(node => { hasAny = renderNode(node, 0, container, search) || hasAny; });
    if (!hasAny) container.innerHTML = '<div class="px-4 py-10 text-center text-gray-400 text-sm">No menus found.</div>';
}
function renderNode(node, depth, container, search) {
    if (search && !nodeOrDescendantMatches(node, search)) return false;
    const hasChildren = node.children && node.children.length > 0;
    const isExpanded = expandedSet.has(node.slug);
    const typeStyle = {
        group: { icon: 'fa-layer-group', iconCls: 'text-purple-400', textCls: 'font-semibold text-gray-900', bgCls: 'bg-white' },
        page: { icon: 'fa-file-alt', iconCls: 'text-blue-400', textCls: 'font-medium text-gray-800', bgCls: 'bg-white' },
        function: { icon: 'fa-bolt', iconCls: 'text-orange-400', textCls: 'text-gray-700', bgCls: 'bg-gray-50' },
    }[node.type] || { icon: 'fa-circle', iconCls: 'text-gray-400', textCls: 'text-gray-700', bgCls: 'bg-white' };
    const roleCount = (node.roles || []).length;

    const div = document.createElement('div');
    div.className = `flex items-center gap-2 px-4 py-2 hover:bg-blue-50/30 transition-colors ${typeStyle.bgCls} ${depth > 0 ? 'border-t border-gray-100' : ''}`;
    div.style.paddingLeft = `${16 + depth * 22}px`;
    div.innerHTML = `
        ${hasChildren
            ? `<button onclick="toggleNode('${node.slug}')" class="w-5 h-5 flex items-center justify-center text-gray-400 hover:text-gray-600 flex-shrink-0 focus:outline-none">
                   <i class="fas ${isExpanded ? 'fa-chevron-down' : 'fa-chevron-right'} text-xs"></i></button>`
            : `<span class="w-5 flex-shrink-0"></span>`}
        <i class="fas ${typeStyle.icon} ${typeStyle.iconCls} text-xs w-4 text-center flex-shrink-0"></i>
        <span class="text-sm ${typeStyle.textCls} leading-tight">${escHtml(node.name)}</span>
        <span class="ml-auto text-xs text-gray-300 font-mono hidden md:inline">${escHtml(node.slug)}</span>
        <button type="button" onclick="openAccess(${node.id})" title="Who has access to this menu"
            class="ml-2 inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-full border border-gray-200 text-gray-600 hover:border-gray-300 hover:bg-white acc-ring">
            <i class="fas fa-shield-halved ${roleCount ? 'acc-accent-text' : 'text-gray-300'}"></i> Access <span class="${roleCount ? 'acc-accent-text' : 'text-gray-400'}">${roleCount}</span>
        </button>`;
    container.appendChild(div);
    if (hasChildren && isExpanded) node.children.forEach(child => renderNode(child, depth + 1, container, search));
    return true;
}
function nodeOrDescendantMatches(node, search) {
    if (node.name.toLowerCase().includes(search) || node.slug.toLowerCase().includes(search)) return true;
    return (node.children || []).some(c => nodeOrDescendantMatches(c, search));
}
function toggleNode(slug) { expandedSet.has(slug) ? expandedSet.delete(slug) : expandedSet.add(slug); renderList(); }
function expandAll() { menusFlat.forEach(m => expandedSet.add(m.slug)); renderList(); }
function collapseAll() { expandedSet.clear(); renderList(); }
function applyFilter() { menusFlat.forEach(m => expandedSet.add(m.slug)); renderList(); }
function resetFilter() {
    document.getElementById('filterSearch').value = '';
    expandedSet.clear(); menuTree.forEach(n => expandedSet.add(n.slug)); renderList();
}
function escHtml(s) { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; }

// ───────────── Access drawer ─────────────
let acc = null, orig = {}, cur = {}, selected = new Set();
let scope = 'self', filter = 'all', query = '', tab = 'access', lastUndo = null, snackTimer = null, planned = null;
const $ = id => document.getElementById(id);
const CRUD_LABEL = { c: 'Create', e: 'Edit', d: 'Delete' };

async function api(url, opts = {}) {
    const res = await fetch(url, { credentials: 'same-origin', headers: JSON_HEADERS, ...opts });
    let json = {};
    try { json = await res.json(); } catch (e) { /* ignore */ }
    return { ok: res.ok && json.success !== false, status: res.status, json };
}

async function openAccess(menuId) {
    $('accOverlay').classList.remove('hidden'); $('accDrawer').classList.remove('hidden');
    requestAnimationFrame(() => { $('accOverlay').classList.add('show'); $('accDrawer').classList.add('show'); });
    document.body.style.overflow = 'hidden';
    $('accTitle').textContent = 'Loading…'; $('accRows').innerHTML = '';
    scope = 'self'; filter = 'all'; query = ''; $('accSearch').value = ''; selected = new Set(); setTab('access'); hideSnack();
    await loadAccess(menuId);
}
async function loadAccess(menuId) {
    const r = await api(`/api/menus/${menuId}/access`);
    if (!r.ok) { showNotification(r.json.message || 'Could not load the access list.', 'error'); closeAccess(true); return; }
    acc = r.json.data;
    orig = {}; cur = {};
    acc.roles.forEach(ro => {
        const s = ro.state;
        orig[ro.id] = s ? { on: true, c: !!s.c, e: !!s.e, d: !!s.d } : { on: false, c: false, e: false, d: false };
        cur[ro.id] = { ...orig[ro.id] };
    });
    selected = new Set();
    renderHeader(); renderRows(); renderPending();
}
async function closeAccess(force) {
    if (!force && pendingCount() > 0) {
        const ok = await showConfirm('You have unsaved access changes. Close without saving?', 'Discard changes?', 'danger', { okText: 'Discard', cancelText: 'Keep editing' });
        if (!ok) return;
    }
    $('accOverlay').classList.remove('show'); $('accDrawer').classList.remove('show');
    setTimeout(() => { $('accOverlay').classList.add('hidden'); $('accDrawer').classList.add('hidden'); }, 220);
    document.body.style.overflow = ''; acc = null; hideSnack();
}
document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    if (!$('accReview').classList.contains('hidden')) { closeReview(); return; }
    if (acc) closeAccess();
});

function renderHeader() {
    const m = acc.menu;
    $('accPath').textContent = (m.path || []).join('  ›  ') || 'Menu';
    $('accTitle').textContent = m.name; $('accSlug').textContent = m.slug;
    const typeLabel = { group: 'Group', page: 'Page', function: 'Function / Button' }[m.type] || m.type;
    const cls = { sensitive: ['Sensitive', 'bg-amber-100 text-amber-800'], ess: ['Always-on ESS', 'bg-gray-200 text-gray-700'], standard: ['Standard', 'bg-gray-100 text-gray-600'] }[m.class];
    $('accBadges').innerHTML = [
        `<span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-blue-50 text-blue-700">${escHtml(typeLabel)}</span>`,
        `<span class="px-2.5 py-1 text-xs font-semibold rounded-full ${cls[1]}">${cls[0]}</span>`,
        `<span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-600">${escHtml(m.module)}</span>`,
        `<span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-green-50 text-green-700"><i class="fas fa-user-shield mr-1"></i>${acc.totals.roles} role(s) · ${acc.totals.employees} employee(s)</span>`,
    ].join('');
    const notes = [];
    if (m.class === 'ess') notes.push(['gray', 'This is a global ESS item: every employee always has it, so it cannot be granted or removed per role.']);
    if (m.class === 'sensitive') notes.push(['amber', 'Sensitive permission (salary, bank, identity, payroll, roles…). Grant it only to roles that need it.']);
    if (!m.is_active) notes.push(['gray', 'This menu is inactive and cannot be granted to more roles.']);
    if (!CAN_GRANT) notes.push(['gray', 'View only — you need the “Roles” permission to change access.']);
    $('accNotices').innerHTML = notes.map(([t, msg]) => `<p class="text-xs rounded-lg px-3 py-2 ${t === 'amber' ? 'bg-amber-50 text-amber-800 border border-amber-200' : 'bg-gray-50 text-gray-600 border border-gray-200'}"><i class="fas fa-circle-info mr-1.5"></i>${escHtml(msg)}</p>`).join('');
    // kolom C/E/D hanya yang ditegakkan server untuk menu ini
    ['c', 'e', 'd'].forEach(k => document.querySelectorAll('.acc-col-' + k).forEach(el => el.classList.toggle('hidden', !m.crud.includes(k))));
    // cakupan
    const wrap = $('accScopeWrap');
    if (m.children > 0) {
        wrap.classList.remove('hidden');
        $('accScope').innerHTML = `<option value="self">This item only</option><option value="subtree">This item + ${m.children} sub-item(s)</option>`;
        $('accScope').value = scope;
    } else { wrap.classList.add('hidden'); scope = 'self'; }
    document.querySelectorAll('#accFilter button').forEach(b => b.classList.toggle('on', b.dataset.f === filter));
    $('tabAccess').className = 'pb-2 font-semibold border-b-2 acc-ring ' + (tab === 'access' ? 'border-current acc-accent-text' : 'border-transparent text-gray-500');
    $('tabHistory').className = 'pb-2 font-semibold border-b-2 acc-ring ' + (tab === 'history' ? 'border-current acc-accent-text' : 'border-transparent text-gray-500');
}

function locked() { return !CAN_GRANT || !acc || acc.menu.class === 'ess'; }
function rowChanged(id) {
    const a = orig[id], b = cur[id];
    return a.on !== b.on || (b.on && (a.c !== b.c || a.e !== b.e || a.d !== b.d));
}
function pendingCount() { return acc ? acc.roles.filter(r => rowChanged(r.id)).length : 0; }

function visibleRoles() {
    const q = query.toLowerCase().trim();
    return acc.roles.filter(r => {
        if (q && !(r.name.toLowerCase().includes(q) || (r.description || '').toLowerCase().includes(q))) return false;
        if (filter === 'yes' && !cur[r.id].on) return false;
        if (filter === 'no' && cur[r.id].on) return false;
        return true;
    });
}

function renderRows() {
    if (!acc) return;
    const crud = acc.menu.crud, ro = locked();
    const rows = visibleRoles();
    $('accEmpty').classList.toggle('hidden', rows.length > 0);
    $('accRows').innerHTML = rows.map(r => {
        const c = cur[r.id], o = orig[r.id];
        const status = !rowChanged(r.id) ? '' : (!o.on && c.on ? ['New', 'text-green-700 bg-green-100'] : (o.on && !c.on ? ['Removed', 'text-red-700 bg-red-100'] : ['Changed', 'text-amber-700 bg-amber-100']));
        const cls = !rowChanged(r.id) ? '' : (!o.on && c.on ? 'is-new' : (o.on && !c.on ? 'is-removed' : 'is-changed'));
        const prot = r.protected;
        const boxes = ['c', 'e', 'd'].map(k => crud.includes(k)
            ? `<td class="px-2 py-2.5 text-center"><input type="checkbox" class="acc-box" ${c[k] ? 'checked' : ''} ${ro || prot ? 'disabled' : ''} onchange="setFlag(${r.id},'${k}',this.checked)" aria-label="${CRUD_LABEL[k]} for ${escHtml(r.name)}"></td>`
            : '').join('');
        return `<tr class="acc-row ${cls}">
            <td class="pl-6 pr-2 py-2.5"><input type="checkbox" class="acc-box" ${selected.has(r.id) ? 'checked' : ''} ${ro ? 'disabled' : ''} onchange="toggleSel(${r.id},this.checked)" aria-label="Select ${escHtml(r.name)}"></td>
            <td class="px-2 py-2.5"><p class="font-semibold text-gray-900 leading-tight">${escHtml(r.name)}${prot ? ' <i class="fas fa-lock text-gray-400 text-xs ml-1" title="Protected: administrators keep this so Menu Access can never be locked"></i>' : ''}</p>
                <p class="text-[11px] text-gray-400">${r.employees_count} employee${r.employees_count === 1 ? '' : 's'}${r.description ? ' · ' + escHtml(r.description.length > 60 ? r.description.slice(0, 60) + '…' : r.description) : ''}</p></td>
            <td class="px-2 py-2.5 text-center"><label class="acc-sw" title="${c.on ? 'Has access' : 'No access'}"><input type="checkbox" ${c.on ? 'checked' : ''} ${ro || prot ? 'disabled' : ''} onchange="setOn(${r.id},this.checked)" aria-label="Access for ${escHtml(r.name)}"><span></span></label></td>
            ${boxes}
            <td class="pr-6 py-2.5 text-right">${status ? `<span class="inline-block px-2 py-0.5 text-[11px] font-bold rounded ${status[1]}">${status[0]}</span>` : ''}</td>
        </tr>`;
    }).join('');
    const vis = rows.map(r => r.id);
    const all = $('accAll');
    all.checked = vis.length > 0 && vis.every(id => selected.has(id)); all.disabled = locked();
    renderBulk();
}
function renderBulk() {
    const n = selected.size, el = $('accBulk');
    el.classList.toggle('hidden', n === 0 || locked()); el.classList.toggle('flex', n > 0 && !locked());
    $('accBulkCount').textContent = `${n} role${n === 1 ? '' : 's'} selected`;
}
function renderPending() {
    const n = pendingCount();
    $('accPending').innerHTML = n === 0
        ? (locked() ? '' : '<i class="fas fa-circle-info mr-1.5"></i>Tick roles, switch access on or off, then review and save.')
        : `<span class="font-semibold text-gray-900">${n} unsaved change${n === 1 ? '' : 's'}</span>${scope === 'subtree' ? ' · applies to this item and its sub-items' : ''}`;
    $('accReviewBtn').disabled = n === 0 || locked();
    $('accDiscard').classList.toggle('hidden', n === 0);
    $('accReviewBtn').classList.toggle('hidden', locked());
}

function setOn(id, on) { cur[id].on = on; if (!on) { cur[id].c = cur[id].e = cur[id].d = false; } renderRows(); renderPending(); }
function setFlag(id, k, v) { cur[id][k] = v; if (v) cur[id].on = true; renderRows(); renderPending(); }
function toggleSel(id, v) { v ? selected.add(id) : selected.delete(id); renderRows(); }
function toggleAll(v) { visibleRoles().forEach(r => v ? selected.add(r.id) : selected.delete(r.id)); renderRows(); }
function clearSel() { selected = new Set(); renderRows(); }
function onAccSearch() { query = $('accSearch').value; renderRows(); }
function setFilter(f) { filter = f; document.querySelectorAll('#accFilter button').forEach(b => b.classList.toggle('on', b.dataset.f === f)); renderRows(); }
function onScope() { scope = $('accScope').value; renderPending(); }
function discard() { acc.roles.forEach(r => { cur[r.id] = { ...orig[r.id] }; }); renderRows(); renderPending(); }

function bulk(kind) {
    if (locked()) return;
    let skipped = 0;
    const apply = (r, on) => { if (r.protected && !on) { skipped++; return; } if (r.protected && on) return; cur[r.id].on = on; if (!on) { cur[r.id].c = cur[r.id].e = cur[r.id].d = false; } };
    if (kind === 'grant') acc.roles.filter(r => selected.has(r.id)).forEach(r => apply(r, true));
    else if (kind === 'revoke') acc.roles.filter(r => selected.has(r.id)).forEach(r => apply(r, false));
    else if (kind === 'only') acc.roles.forEach(r => apply(r, selected.has(r.id)));
    if (skipped) showNotification(`${skipped} protected role(s) keep their access.`, 'warning');
    selected = new Set(); renderRows(); renderPending();
}

function payload() {
    return {
        scope,
        changes: acc.roles.filter(r => rowChanged(r.id)).map(r => cur[r.id].on
            ? { role_id: r.id, action: 'set', can_create: cur[r.id].c, can_edit: cur[r.id].e, can_delete: cur[r.id].d }
            : { role_id: r.id, action: 'revoke' }),
    };
}
function stateText(s, crud) {
    if (!s) return '<span class="text-gray-400">No access</span>';
    const parts = ['View'].concat(['c', 'e', 'd'].filter(k => crud.includes(k) && s[k]).map(k => CRUD_LABEL[k]));
    return parts.join(' · ');
}

async function review() {
    if (pendingCount() === 0) return;
    const btn = $('accReviewBtn'); btn.disabled = true;
    const r = await api(`/api/menus/${acc.menu.id}/access/plan`, { method: 'POST', body: JSON.stringify(payload()) });
    btn.disabled = false;
    if (!r.ok) { showNotification(r.json.message || 'These changes cannot be saved.', 'error'); return; }
    planned = r.json;
    const s = planned.summary, crud = acc.menu.crud;
    $('accReviewSub').textContent = `${acc.menu.name} — ${s.total} role change${s.total === 1 ? '' : 's'} · ${planned.employees_affected} employee(s) affected`;
    const pill = (n, label, cls) => n ? `<span class="px-2.5 py-1 text-xs font-bold rounded-full ${cls}">${n} ${label}</span>` : '';
    const warn = (planned.warnings || []).map(w => `<p class="text-xs rounded-lg px-3 py-2 bg-amber-50 text-amber-800 border border-amber-200"><i class="fas fa-triangle-exclamation mr-1.5"></i>${escHtml(w)}</p>`).join('');
    const byRole = {};
    planned.rows.forEach(x => { (byRole[x.role_id] = byRole[x.role_id] || { name: x.role, rows: [] }).rows.push(x); });
    const rolesHtml = Object.values(byRole).map(g => {
        const first = g.rows[0];
        const more = g.rows.length > 1 ? `<span class="text-[11px] text-gray-400 ml-1">(+ ${g.rows.length - 1} more item${g.rows.length > 2 ? 's' : ''} in this group)</span>` : '';
        const tone = first.kind === 'grant' ? 'text-green-700' : (first.kind === 'revoke' ? 'text-red-700' : 'text-amber-700');
        return `<tr class="border-t border-gray-100"><td class="py-2 pr-3 font-semibold text-gray-900">${escHtml(g.name)}${more}</td>
            <td class="py-2 pr-3 text-gray-600">${stateText(first.before, crud)}</td><td class="py-2 pr-2 text-gray-400"><i class="fas fa-arrow-right text-xs"></i></td>
            <td class="py-2 font-semibold ${tone}">${stateText(first.after, crud)}</td></tr>`;
    }).join('');
    $('accReviewBody').innerHTML = `<div class="flex flex-wrap gap-2">${pill(s.grant, 'granted', 'bg-green-100 text-green-700')}${pill(s.revoke, 'removed', 'bg-red-100 text-red-700')}${pill(s.change, 'changed', 'bg-amber-100 text-amber-700')}</div>
        ${warn}<table class="w-full text-sm"><thead><tr class="text-left text-[11px] uppercase tracking-wide text-gray-500"><th class="pb-1">Role</th><th class="pb-1">Now</th><th></th><th class="pb-1">After</th></tr></thead><tbody>${rolesHtml}</tbody></table>`;
    $('accReason').value = '';
    $('accReview').classList.remove('hidden'); $('accReview').classList.add('flex'); $('accReason').focus();
}
function closeReview() { $('accReview').classList.add('hidden'); $('accReview').classList.remove('flex'); }

async function confirmApply() {
    const btn = $('accConfirm'); btn.disabled = true;
    const body = payload(); body.reason = $('accReason').value.trim() || null;
    const r = await api(`/api/menus/${acc.menu.id}/access/apply`, { method: 'POST', body: JSON.stringify(body) });
    btn.disabled = false;
    if (!r.ok) { showNotification(r.json.message || 'Could not save. Nothing was changed.', 'error'); return; }
    closeReview();
    const id = acc.menu.id;
    lastUndo = { menuId: id, ids: r.json.change_ids || [] };
    await loadAccess(id);
    syncListCount(id);
    if (lastUndo.ids.length) showSnack(`Saved: ${r.json.summary.grant} granted, ${r.json.summary.revoke} removed, ${r.json.summary.change} changed.`);
    else showNotification('No changes to save.', 'info');
    if (tab === 'history') loadHistory();
}
function syncListCount(menuId) {
    const m = menusFlat.find(x => x.id === menuId);
    if (m && acc) { m.roles = acc.roles.filter(r => r.state).map(r => ({ id: r.id, name: r.name })); renderList(); }
}

// ───────────── Undo & history ─────────────
function showSnack(msg) {
    $('accSnackMsg').textContent = msg; $('accSnack').classList.remove('hidden');
    clearTimeout(snackTimer); snackTimer = setTimeout(hideSnack, 20000);
}
function hideSnack() { $('accSnack').classList.add('hidden'); clearTimeout(snackTimer); }
async function undoLast() {
    if (!lastUndo) return;
    const { menuId, ids } = lastUndo; hideSnack();
    const r = await api(`/api/menus/${menuId}/access/undo`, { method: 'POST', body: JSON.stringify({ change_ids: ids }) });
    if (!r.ok) { showNotification(r.json.message || 'Could not undo.', 'error'); return; }
    lastUndo = null; showNotification('Change undone.', 'success');
    if (acc && acc.menu.id === menuId) { await loadAccess(menuId); syncListCount(menuId); if (tab === 'history') loadHistory(); }
}
function setTab(t) {
    tab = t; $('paneAccess').classList.toggle('hidden', t !== 'access'); $('paneHistory').classList.toggle('hidden', t !== 'history');
    if (acc) renderHeader();
    else { $('tabAccess').className = 'pb-2 font-semibold border-b-2 acc-ring border-current acc-accent-text'; $('tabHistory').className = 'pb-2 font-semibold border-b-2 acc-ring border-transparent text-gray-500'; }
    if (t === 'history' && acc) loadHistory();
}
async function loadHistory() {
    const box = $('paneHistory'); box.innerHTML = '<p class="text-sm text-gray-400">Loading…</p>';
    const r = await api(`/api/menus/${acc.menu.id}/access/history`);
    if (!r.ok) { box.innerHTML = '<p class="text-sm text-red-600">Could not load the history.</p>'; return; }
    const crud = acc.menu.crud, list = r.json.data || [];
    if (!list.length) { box.innerHTML = '<p class="text-sm text-gray-400 py-8 text-center">No access change has been recorded for this menu yet.</p>'; return; }
    box.innerHTML = list.map(h => {
        const when = h.at ? new Date(h.at.replace(' ', 'T')).toLocaleString() : '';
        const canUndo = CAN_GRANT && h.only_this && !h.reverted && h.kind === 'apply';
        return `<div class="border border-gray-200 rounded-lg px-4 py-3 mb-3 ${h.reverted ? 'opacity-60' : ''}">
            <div class="flex items-start justify-between gap-3"><div>
                <p class="text-sm font-semibold text-gray-900">${escHtml(h.role)} ${h.kind === 'undo' ? '<span class="text-[11px] font-bold text-gray-500 bg-gray-100 rounded px-1.5 py-0.5 ml-1">UNDO</span>' : ''}${h.reverted ? '<span class="text-[11px] font-bold text-gray-500 bg-gray-100 rounded px-1.5 py-0.5 ml-1">UNDONE</span>' : ''}</p>
                <p class="text-xs text-gray-600 mt-0.5">${stateText(h.before, crud)} <i class="fas fa-arrow-right text-[10px] text-gray-400 mx-1"></i> <span class="font-semibold">${stateText(h.after, crud)}</span></p>
                <p class="text-[11px] text-gray-400 mt-1">${escHtml(h.actor)} · ${escHtml(when)}${h.reason ? ' · “' + escHtml(h.reason) + '”' : ''}</p></div>
                ${canUndo ? `<button type="button" onclick="undoOne(${h.id})" class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">Undo</button>` : ''}
            </div></div>`;
    }).join('');
}
async function undoOne(id) {
    const ok = await showConfirm('This restores the previous access of that role for this menu.', 'Undo this change?', 'primary', { okText: 'Undo', cancelText: 'Cancel' });
    if (!ok) return;
    const r = await api(`/api/menus/${acc.menu.id}/access/undo`, { method: 'POST', body: JSON.stringify({ change_ids: [id] }) });
    if (!r.ok) { showNotification(r.json.message || 'Could not undo.', 'error'); return; }
    showNotification('Change undone.', 'success');
    await loadAccess(acc.menu.id); syncListCount(acc.menu.id); loadHistory();
}

loadData();
</script>
@endsection

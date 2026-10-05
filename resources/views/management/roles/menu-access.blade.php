@extends('dashboard')

@section('title', 'Menu Access — ' . $role->name)
@section('page-title', 'Menu Access')

{{--
    Halaman penuh Menu Access per role (HC-D63/D64) — menggantikan modal sempit di daftar Role.

    Cara kerja (ringkas):
      - Perubahan hanya DRAF di peramban sampai "Review & Save" ditekan; server menulis semuanya sebagai SATU unit
        (transaksi + riwayat + audit log) lewat /api/roles/{id}/menu-access/apply.
      - Kolom Create/Edit/Delete hanya aktif untuk menu yang benar-benar menegakkannya di server (matrix.menus[].crud);
        menu lain cukup satu kotak View. Flag lama yang tak berfungsi tidak dihapus, hanya ditandai.
      - Item "Global ESS" selalu diizinkan backend untuk semua karyawan → terkunci. Slug terlindung (EC Administrator)
        tidak bisa dicabut dari sini.
    Semua keputusan (teks, aturan) berasal dari server (config/menu_access.php); halaman ini hanya menampilkannya.
--}}

@section('content')
<div id="maRoot" class="space-y-4" data-role-id="{{ $role->id }}" data-role-name="{{ $role->name }}">

    {{-- Header --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div class="min-w-0">
                <a href="{{ route('management.roles.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-500 hover:text-gray-800">
                    <i class="fas fa-arrow-left text-[10px]"></i> Back to Roles
                </a>
                <h2 class="text-xl font-bold text-gray-900 mt-1 truncate">{{ $role->name }}</h2>
                <p class="text-sm text-gray-500">
                    {{ $role->employees_count }} {{ $role->employees_count === 1 ? 'member' : 'members' }}
                    @if($role->description) · {{ $role->description }} @endif
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" id="maBtnCopy" class="ma-btn"><i class="fas fa-copy mr-1.5"></i> Copy from role…</button>
                <button type="button" id="maBtnCompare" class="ma-btn"><i class="fas fa-code-compare mr-1.5"></i> Compare…</button>
                <button type="button" id="maBtnHistory" class="ma-btn"><i class="fas fa-clock-rotate-left mr-1.5"></i> History</button>
            </div>
        </div>

        <div class="mt-4 rounded-lg bg-blue-50 border border-blue-100 text-blue-900 text-xs px-3 py-2 leading-relaxed">
            <strong>How it works.</strong> Tick boxes freely — nothing is saved until you press <em>Review &amp; Save</em>.
            <span class="whitespace-nowrap"><i class="fas fa-lock text-[10px]"></i> Global ESS</span> items are always on for every employee and cannot be changed per role.
            <span class="whitespace-nowrap"><i class="fas fa-shield-halved text-[10px] text-amber-600"></i> Sensitive</span> items ask for an extra confirmation.
            Create / Edit / Delete are only available where the application really enforces them; elsewhere a single <em>Access</em> box is enough.
        </div>
    </div>

    {{-- Toolbar --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 flex flex-col lg:flex-row lg:items-center gap-3">
        <div class="relative flex-1 min-w-0">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-xs text-gray-400"></i>
            <input id="maSearch" type="search" placeholder="Search menu name or slug…" aria-label="Search menu name or slug"
                class="w-full pl-9 pr-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
        </div>
        <div class="inline-flex rounded-lg bg-gray-100 p-0.5 text-xs font-semibold" role="tablist" aria-label="Filter">
            <button type="button" class="ma-filter" data-filter="all" role="tab">All</button>
            <button type="button" class="ma-filter" data-filter="granted" role="tab">Granted</button>
            <button type="button" class="ma-filter" data-filter="not" role="tab">Not granted</button>
            <button type="button" class="ma-filter" data-filter="changed" role="tab">Changed <span id="maChangedBadge" class="ml-1 hidden px-1.5 rounded-full bg-amber-200 text-amber-900">0</span></button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-[17rem_1fr] gap-4 items-start">
        {{-- Modul --}}
        <aside class="bg-white rounded-xl shadow-sm border border-gray-200 p-2 lg:sticky lg:top-20 max-h-[70vh] overflow-y-auto" aria-label="Modules">
            <p class="px-2 pt-1 pb-2 text-[11px] font-semibold tracking-wider text-gray-400 uppercase">Modules</p>
            <ul id="maModules" class="space-y-0.5"></ul>
        </aside>

        {{-- Daftar menu --}}
        <section class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden" aria-live="polite">
            <div class="px-4 py-3 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 bg-gray-50">
                <div class="min-w-0">
                    <h3 id="maModuleTitle" class="text-sm font-bold text-gray-900 truncate">All modules</h3>
                    <p id="maShownInfo" class="text-xs text-gray-500"></p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" id="maBulkGrant" class="ma-btn ma-btn-sm" title="Give View access to every item currently shown"><i class="fas fa-plus mr-1"></i> Grant View to shown</button>
                    <button type="button" id="maBulkRevoke" class="ma-btn ma-btn-sm" title="Remove access from every item currently shown"><i class="fas fa-minus mr-1"></i> Revoke from shown</button>
                </div>
            </div>
            <div class="hidden sm:grid ma-grid px-4 py-2 text-[11px] font-semibold uppercase tracking-wider text-gray-500 border-b border-gray-100 bg-white sticky top-0 z-10">
                <div>Menu</div><div class="text-center">View</div><div class="text-center">Create</div><div class="text-center">Edit</div><div class="text-center">Delete</div>
            </div>
            <div id="maRows" class="divide-y divide-gray-100"></div>
            <p id="maEmpty" class="hidden text-center text-sm text-gray-400 py-10">Nothing matches the current search / filter.</p>
        </section>
    </div>

    {{-- Bilah draf (lengket) --}}
    <div id="maBar" class="hidden fixed bottom-0 left-0 right-0 lg:left-64 z-40 bg-white border-t border-gray-200 shadow-[0_-6px_20px_rgba(0,0,0,0.08)]">
        <div class="max-w-7xl mx-auto px-4 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <p class="text-sm text-gray-700"><span class="inline-block w-2 h-2 rounded-full bg-amber-500 mr-2"></span><strong id="maDraftCount">0</strong> unsaved <span id="maDraftWord">changes</span> for <strong>{{ $role->name }}</strong></p>
            <div class="flex items-center gap-2">
                <button type="button" id="maDiscard" class="ma-btn">Discard</button>
                <button type="button" id="maReview" class="ma-btn ma-btn-primary">Review &amp; Save</button>
            </div>
        </div>
    </div>
</div>

{{-- Modal dasar (dipakai Review/Copy/Compare/History) --}}
<div id="maModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-40" aria-hidden="true">
    <div id="maModalCard" role="dialog" aria-modal="true" aria-labelledby="maModalTitle" tabindex="-1" class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[88vh] flex flex-col">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <h3 id="maModalTitle" class="text-base font-bold text-gray-900"></h3>
            <button type="button" id="maModalClose" class="text-gray-400 hover:text-gray-700 p-1" aria-label="Close"><i class="fas fa-xmark"></i></button>
        </div>
        <div id="maModalBody" class="px-5 py-4 overflow-y-auto flex-1"></div>
        <div id="maModalFoot" class="px-5 py-3 border-t border-gray-100 flex justify-end gap-2"></div>
    </div>
</div>

<style>
    .ma-btn { display:inline-flex; align-items:center; padding:.45rem .8rem; border:1px solid #d1d5db; border-radius:.5rem; font-size:.8125rem; font-weight:600; color:#374151; background:#fff; transition:background .15s; }
    .ma-btn:hover { background:#f3f4f6; }
    .ma-btn:focus-visible, .ma-filter:focus-visible, .ma-mod:focus-visible { outline:2px solid #991b1b; outline-offset:2px; }
    .ma-btn[disabled] { opacity:.45; cursor:not-allowed; }
    .ma-btn-sm { padding:.3rem .6rem; font-size:.75rem; }
    .ma-btn-primary { color:#fff; border-color:transparent; background:var(--primary-color, #991b1b); }
    .ma-btn-primary:hover { background:var(--primary-color, #991b1b); opacity:.9; }
    .ma-filter { padding:.4rem .8rem; border-radius:.4rem; color:#6b7280; }
    .ma-filter.is-on { background:#fff; color:#111827; box-shadow:0 1px 2px rgba(0,0,0,.1); }
    .ma-mod { width:100%; display:flex; align-items:center; justify-content:space-between; gap:.5rem; padding:.45rem .6rem; border-radius:.5rem; font-size:.8125rem; text-align:left; color:#374151; }
    .ma-mod:hover { background:#f3f4f6; }
    .ma-mod.is-on { background:rgba(var(--primary-rgb, 153,27,27), .10); color:var(--primary-color, #991b1b); font-weight:700; }
    .ma-count { font-size:.6875rem; color:#6b7280; white-space:nowrap; }
    .ma-grid { display:grid; grid-template-columns:minmax(0,1fr) 4.5rem 4.5rem 4.5rem 4.5rem; align-items:center; column-gap:.25rem; }
    .ma-row { padding:.5rem 1rem; }
    .ma-row:hover { background:#fafafa; }
    .ma-row.is-changed { background:#fffbeb; box-shadow:inset 3px 0 0 #f59e0b; }
    .ma-cell { display:flex; justify-content:center; align-items:center; min-height:1.5rem; }
    .ma-cell input[type=checkbox] { width:1.05rem; height:1.05rem; accent-color:var(--primary-color, #991b1b); cursor:pointer; }
    .ma-cell input[disabled] { cursor:not-allowed; opacity:.55; }
    .ma-badge { display:inline-flex; align-items:center; gap:.25rem; font-size:.6875rem; font-weight:600; padding:.05rem .4rem; border-radius:9999px; white-space:nowrap; }
    .ma-note { font-size:.6875rem; color:#9ca3af; }
    /* bilah draf mengikuti lebar sidebar saat mode rail (5rem) */
    @media (min-width: 1024px) { html[data-sb-layout="rail"] #maBar { left: 5rem; } }
    @media (max-width: 639px) {
        .ma-grid { grid-template-columns:minmax(0,1fr) auto; row-gap:.35rem; }
        .ma-cell-c, .ma-cell-e, .ma-cell-d { display:none; }
        .ma-row.show-crud .ma-cell-c, .ma-row.show-crud .ma-cell-e, .ma-row.show-crud .ma-cell-d { display:flex; grid-column:auto; }
    }
</style>

<script>
(function () {
    'use strict';

    const root = document.getElementById('maRoot');
    const ROLE_ID = parseInt(root.dataset.roleId, 10);
    const ROLE_NAME = root.dataset.roleName;
    const API = '/api/roles/' + ROLE_ID + '/menu-access';
    const csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    let data = null;                 // matriks dari server
    let menusById = {};
    let childrenOf = {};
    let orderIndex = {};
    let grants = {};                 // menu_id -> {v,c,e,d} | undefined
    const draft = new Map();         // menu_id -> {v,c,e,d} | null   (keadaan yang DIINGINKAN, hanya yang berbeda dari server)
    let moduleSel = 'all', filter = 'all', query = '';

    const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const notify = (m, t) => (window.showNotification ? window.showNotification(m, t || 'info') : alert(m));
    const same = (a, b) => (a === null || b === null || a === undefined || b === undefined) ? (a == null && b == null)
        : ['v', 'c', 'e', 'd'].every((k) => (a[k] | 0) === (b[k] | 0));
    const server = (id) => grants[id] || null;
    const eff = (id) => (draft.has(id) ? draft.get(id) : server(id));
    const changed = (id) => draft.has(id);
    const V = { v: 1, c: 0, e: 0, d: 0 };

    async function call(method, url, body) {
        const res = await fetch(url, {
            method, credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: body ? JSON.stringify(body) : undefined,
        });
        let json = {};
        try { json = await res.json(); } catch (e) { /* tanpa isi */ }
        return { ok: res.ok && json.success !== false, status: res.status, json };
    }

    // ───────────────────────────────────────────────── muat & bangun indeks
    async function load() {
        const r = await call('GET', API);
        if (!r.ok) { notify('Could not load menu access (' + r.status + ').', 'error'); return; }
        data = r.json.data;
        grants = data.grants || {};
        menusById = {}; childrenOf = {}; orderIndex = {};
        data.menus.forEach((m, i) => { menusById[m.id] = m; orderIndex[m.id] = i; });
        data.menus.forEach((m) => { const p = m.parent_id && menusById[m.parent_id] ? m.parent_id : 0; (childrenOf[p] = childrenOf[p] || []).push(m.id); });
        draft.clear();
        render();
    }

    // urutan tampil: pohon (induk dulu), lalu filter modul/pencarian/status
    function flatOrder() {
        const out = [];
        const walk = (pid, depth) => (childrenOf[pid] || []).forEach((id) => { out.push({ id, depth }); walk(id, depth + 1); });
        walk(0, 0);
        return out;
    }

    function locked(m) { return m.class === 'ess'; }
    function canGrant(m) { return m.is_active && !locked(m); }
    function canRevoke(m) { return !locked(m) && !m.protected; }

    function visibleRows() {
        const q = query.trim().toLowerCase();
        return flatOrder().filter(({ id }) => {
            const m = menusById[id];
            if (moduleSel !== 'all' && m.module !== moduleSel) { return false; }
            if (q && !(m.name.toLowerCase().includes(q) || m.slug.toLowerCase().includes(q))) { return false; }
            const e = eff(id);
            if (filter === 'granted' && !e) { return false; }
            if (filter === 'not' && e) { return false; }
            if (filter === 'changed' && !changed(id)) { return false; }
            return true;
        });
    }

    // kedalaman relatif: hanya hitung leluhur yang ikut tampil di modul yang sama
    function relDepth(id) {
        let d = 0, p = menusById[id].parent_id;
        while (p && menusById[p]) { if (moduleSel === 'all' || menusById[p].module === moduleSel) { d++; } p = menusById[p].parent_id; }
        return d;
    }

    // ───────────────────────────────────────────────── render
    function render() {
        renderModules();
        renderRows();
        renderBar();
    }

    function renderModules() {
        const counts = {};
        data.modules.forEach((mod) => { counts[mod.key] = { total: 0, on: 0, changed: 0 }; });
        data.menus.forEach((m) => {
            const c = counts[m.module] || (counts[m.module] = { total: 0, on: 0, changed: 0 });
            if (locked(m)) { return; }
            c.total++;
            if (eff(m.id)) { c.on++; }
            if (changed(m.id)) { c.changed++; }
        });
        const li = (key, label, c) => `<li><button type="button" class="ma-mod ${moduleSel === key ? 'is-on' : ''}" data-mod="${esc(key)}" aria-pressed="${moduleSel === key}">
            <span class="truncate">${esc(label)}${c.changed ? ' <span class="ml-1 px-1.5 rounded-full bg-amber-200 text-amber-900 text-[10px]">' + c.changed + '</span>' : ''}</span>
            <span class="ma-count">${c.on}/${c.total}</span></button></li>`;
        const all = Object.values(counts).reduce((a, c) => ({ total: a.total + c.total, on: a.on + c.on, changed: a.changed + c.changed }), { total: 0, on: 0, changed: 0 });
        document.getElementById('maModules').innerHTML = li('all', 'All modules', all)
            + data.modules.filter((mod) => counts[mod.key] && (counts[mod.key].total > 0 || mod.key === 'ess')).map((mod) => li(mod.key, mod.label, counts[mod.key])).join('');
    }

    function badge(cls, icon, text, title) {
        return `<span class="ma-badge ${cls}" title="${esc(title || text)}"><i class="fas ${icon} text-[9px]"></i>${esc(text)}</span>`;
    }

    function rowHtml({ id }) {
        const m = menusById[id];
        const e = eff(id), sv = server(id);
        const depth = relDepth(id);
        const lock = locked(m);
        const typeIcon = m.type === 'group' ? 'fa-folder text-amber-500' : (m.type === 'function' ? 'fa-bolt text-orange-400' : 'fa-file-lines text-blue-400');
        const hasKids = (childrenOf[id] || []).length > 0;
        const badges = [];
        if (lock) { badges.push(badge('bg-gray-100 text-gray-600', 'fa-lock', 'Global ESS', 'Always on for every employee — cannot be changed per role')); }
        if (m.class === 'sensitive') { badges.push(badge('bg-amber-50 text-amber-800', 'fa-shield-halved', 'Sensitive', 'Gives access to sensitive data or administration')); }
        if (m.protected) { badges.push(badge('bg-red-50 text-red-700', 'fa-user-shield', 'Protected', 'Cannot be revoked from this role (would lock administrators out)')); }
        if (!m.is_active) { badges.push(badge('bg-gray-100 text-gray-500', 'fa-ban', 'Inactive')); }
        if (m.gated_routes === 0 && m.type !== 'group' && !lock) { badges.push(badge('bg-gray-50 text-gray-400', 'fa-circle-question', 'No route gate', 'No route uses this slug as a gate. It may still control a menu item or screen.')); }

        const checked = (k) => (e && e[k] ? 'checked' : '');
        const vDisabled = lock || (!canGrant(m) && !e) || (m.protected && !!e);
        const crud = !!m.crud;
        const legacy = sv && (sv.c || sv.e || sv.d) && !crud
            ? `<span class="ma-note" title="Create/Edit/Delete values saved earlier for this menu. The application does not use them, so they are kept but cannot be changed here.">stored ${['c', 'e', 'd'].filter((k) => sv[k]).map((k) => k.toUpperCase()).join('·')} · not enforced</span>` : '';

        const cell = (k, label) => {
            if (lock) { return `<div class="ma-cell ma-cell-${k}"><span class="ma-note">—</span></div>`; }
            if (!crud) { return `<div class="ma-cell ma-cell-${k}"><span class="ma-note" title="${esc(label)} is not enforced for this menu">–</span></div>`; }
            return `<div class="ma-cell ma-cell-${k}"><input type="checkbox" data-id="${id}" data-k="${k}" ${checked(k)} ${(!e) ? 'disabled' : ''} aria-label="${esc(label)} — ${esc(m.name)}"></div>`;
        };
        const vTitle = lock ? 'Always on (Global ESS)' : (m.protected && e ? 'Protected — cannot be revoked' : '');
        return `<div class="ma-row ma-grid ${changed(id) ? 'is-changed' : ''} ${crud ? 'show-crud' : ''}" data-row="${id}">
            <div class="min-w-0" style="padding-left:${depth * 18}px">
                <div class="flex items-center gap-2 min-w-0">
                    <i class="fas ${typeIcon} text-xs flex-shrink-0" aria-hidden="true"></i>
                    <span class="text-sm ${m.type === 'group' ? 'font-bold' : 'font-medium'} text-gray-900 truncate" title="${esc(m.name)}">${esc(m.name)}</span>
                    ${hasKids && !lock ? `<button type="button" class="text-[11px] text-gray-400 hover:text-gray-700 flex-shrink-0" data-tree="${id}" title="Toggle this item and everything below it" aria-label="Toggle ${esc(m.name)} and everything below"><i class="fas fa-sitemap"></i></button>` : ''}
                </div>
                <div class="flex flex-wrap items-center gap-1.5 mt-0.5">
                    <code class="text-[11px] text-gray-400 break-all">${esc(m.slug)}</code>${badges.join('')}${legacy}
                </div>
            </div>
            <div class="ma-cell ma-cell-v"><input type="checkbox" data-id="${id}" data-k="v" ${lock ? 'checked' : checked('v')} ${vDisabled ? 'disabled' : ''} title="${esc(vTitle)}" aria-label="${crud ? 'View' : 'Access'} — ${esc(m.name)}"></div>
            ${cell('c', 'Create')}${cell('e', 'Edit')}${cell('d', 'Delete')}
        </div>`;
    }

    function renderRows() {
        const rows = visibleRows();
        document.getElementById('maRows').innerHTML = rows.map(rowHtml).join('');
        document.getElementById('maEmpty').classList.toggle('hidden', rows.length > 0);
        const mod = data.modules.find((x) => x.key === moduleSel);
        document.getElementById('maModuleTitle').textContent = moduleSel === 'all' ? 'All modules' : (mod ? mod.label : moduleSel);
        const granted = rows.filter(({ id }) => eff(id)).length;
        document.getElementById('maShownInfo').textContent = `${rows.length} shown · ${granted} granted`;
        const shown = rows.map(({ id }) => menusById[id]);
        document.getElementById('maBulkGrant').disabled = !shown.some((m) => canGrant(m) && !eff(m.id));
        document.getElementById('maBulkRevoke').disabled = !shown.some((m) => canRevoke(m) && !!eff(m.id));
    }

    function renderBar() {
        const n = draft.size;
        document.getElementById('maBar').classList.toggle('hidden', n === 0);
        document.getElementById('maDraftCount').textContent = n;
        document.getElementById('maDraftWord').textContent = n === 1 ? 'change' : 'changes';
        const b = document.getElementById('maChangedBadge');
        b.textContent = n; b.classList.toggle('hidden', n === 0);
        root.style.paddingBottom = n ? '4.5rem' : '';
    }

    // ───────────────────────────────────────────────── draf
    function setDraft(id, state) {
        if (same(state, server(id))) { draft.delete(id); } else { draft.set(id, state); }
    }
    function toggleView(id, on) {
        const m = menusById[id];
        if (on) { if (canGrant(m)) { setDraft(id, eff(id) || Object.assign({}, V)); } }
        else if (canRevoke(m)) { setDraft(id, null); }
    }
    function toggleCrud(id, k, on) {
        const cur = eff(id);
        if (!cur) { return; }
        const next = Object.assign({}, cur, { [k]: on ? 1 : 0, v: 1 });
        setDraft(id, next);
    }
    function subtree(id) { const out = [id]; (childrenOf[id] || []).forEach((c) => out.push(...subtree(c))); return out; }

    // ───────────────────────────────────────────────── modal
    const modal = document.getElementById('maModal');
    const card = document.getElementById('maModalCard');
    let lastFocus = null;
    function openModal(title, bodyHtml, footHtml) {
        lastFocus = document.activeElement;
        document.getElementById('maModalTitle').textContent = title;
        document.getElementById('maModalBody').innerHTML = bodyHtml;
        document.getElementById('maModalFoot').innerHTML = footHtml || '';
        modal.classList.remove('hidden'); modal.setAttribute('aria-hidden', 'false');
        card.focus();
    }
    function closeModal() {
        modal.classList.add('hidden'); modal.setAttribute('aria-hidden', 'true');
        if (lastFocus && lastFocus.focus) { lastFocus.focus(); }
    }
    modal.addEventListener('click', (e) => { if (e.target === modal) { closeModal(); } });
    document.getElementById('maModalClose').addEventListener('click', closeModal);
    document.addEventListener('keydown', (e) => {
        if (modal.classList.contains('hidden')) { return; }
        if (e.key === 'Escape') { closeModal(); }
        if (e.key === 'Tab') {   // fokus terkurung di dalam dialog
            const f = card.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
            const list = Array.from(f).filter((el) => !el.disabled && el.offsetParent !== null);
            if (!list.length) { return; }
            const first = list[0], last = list[list.length - 1];
            if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
            else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
        }
    });

    const stateText = (s) => !s ? '—' : 'View' + (s.c ? ' + Create' : '') + (s.e ? ' + Edit' : '') + (s.d ? ' + Delete' : '');
    const apiChange = (id, s) => s === null ? { menu_id: id, revoke: true }
        : { menu_id: id, can_view: true, can_create: !!s.c, can_edit: !!s.e, can_delete: !!s.d };
    const kindOf = (b, a) => !same(b, a) ? (b == null ? 'grant' : (a == null ? 'revoke' : 'change')) : 'none';

    // ── Review & Save
    function openReview() {
        const rows = Array.from(draft.entries()).map(([id, after]) => ({ id, m: menusById[id], before: server(id), after, kind: kindOf(server(id), after) }))
            .sort((a, b) => orderIndex[a.id] - orderIndex[b.id]);
        const count = (k) => rows.filter((r) => r.kind === k).length;
        const sensitiveGrants = rows.filter((r) => r.m.class === 'sensitive' && (r.kind === 'grant' || r.kind === 'change'));
        const tone = { grant: 'bg-green-50 text-green-800', revoke: 'bg-red-50 text-red-800', change: 'bg-blue-50 text-blue-800' };
        const label = { grant: 'Grant', revoke: 'Revoke', change: 'Change' };
        const list = rows.map((r) => `<li class="flex items-start justify-between gap-3 py-1.5">
            <div class="min-w-0"><span class="text-sm font-medium text-gray-900">${esc(r.m.name)}</span>${r.m.class === 'sensitive' ? ' <i class="fas fa-shield-halved text-amber-600 text-[10px]" title="Sensitive"></i>' : ''}
            <div><code class="text-[11px] text-gray-400 break-all">${esc(r.m.slug)}</code></div></div>
            <div class="text-right flex-shrink-0"><span class="ma-badge ${tone[r.kind]}">${label[r.kind]}</span>
            <div class="text-[11px] text-gray-500 mt-0.5">${esc(stateText(r.before))} → ${esc(stateText(r.after))}</div></div></li>`).join('');
        openModal('Review changes — ' + ROLE_NAME, `
            <div class="flex flex-wrap gap-2 mb-3">
                <span class="ma-badge ${tone.grant}">${count('grant')} to grant</span>
                <span class="ma-badge ${tone.revoke}">${count('revoke')} to revoke</span>
                <span class="ma-badge ${tone.change}">${count('change')} to change</span>
            </div>
            ${sensitiveGrants.length ? `<div class="mb-3 rounded-lg bg-amber-50 border border-amber-200 text-amber-900 text-xs p-3">
                <strong>${sensitiveGrants.length} sensitive ${sensitiveGrants.length === 1 ? 'item' : 'items'}</strong> will be granted. Confirm that members of this role should have it.
                <label class="mt-2 flex items-center gap-2 font-semibold"><input type="checkbox" id="maSensOk"> I understand and confirm</label></div>` : ''}
            <ul class="divide-y divide-gray-100 max-h-[40vh] overflow-y-auto border border-gray-100 rounded-lg px-3">${list}</ul>
            <label class="block mt-3 text-xs font-semibold text-gray-600" for="maReason">Reason (optional, shown in History)</label>
            <input id="maReason" maxlength="255" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="e.g. HR staff need approval workflow access">
            <p id="maReviewError" class="hidden mt-3 text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg p-3"></p>`,
            `<button type="button" class="ma-btn" id="maRvBack">Back</button>
             <button type="button" class="ma-btn ma-btn-primary" id="maRvSave">Save ${rows.length} ${rows.length === 1 ? 'change' : 'changes'}</button>`);
        document.getElementById('maRvBack').onclick = closeModal;
        document.getElementById('maRvSave').onclick = async () => {
            const err = document.getElementById('maReviewError'); err.classList.add('hidden');
            if (sensitiveGrants.length && !document.getElementById('maSensOk').checked) { err.textContent = 'Please confirm the sensitive items first.'; err.classList.remove('hidden'); return; }
            const btn = document.getElementById('maRvSave'); btn.disabled = true; btn.textContent = 'Saving…';
            const r = await call('POST', API + '/apply', { changes: rows.map((x) => apiChange(x.id, x.after)), reason: document.getElementById('maReason').value || null });
            if (r.ok) { closeModal(); notify(r.json.message || 'Access updated.', 'success'); await load(); return; }
            btn.disabled = false; btn.textContent = 'Save ' + rows.length + ' changes';
            err.textContent = (r.json && r.json.message) || ('Could not save (' + r.status + ').'); err.classList.remove('hidden');
        };
    }

    // ── pilih role lain (untuk Copy / Compare)
    async function roleOptions() {
        const r = await call('GET', '/api/roles');
        const list = (r.ok ? r.json.data : []).filter((x) => x.id !== ROLE_ID);
        return list.sort((a, b) => a.name.localeCompare(b.name)).map((x) => `<option value="${x.id}">${esc(x.name)}</option>`).join('');
    }

    // ── Copy from role
    async function openCopy() {
        const opts = await roleOptions();
        openModal('Copy access from another role', `
            <p class="text-sm text-gray-600 mb-3">Pick a role. You will see what would change in <strong>${esc(ROLE_NAME)}</strong>; choose which differences to add to your draft. Nothing is saved yet.</p>
            <select id="maSrc" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"><option value="">Select a role…</option>${opts}</select>
            <div id="maCopyBody" class="mt-4"></div>`,
            `<button type="button" class="ma-btn" id="maCpClose">Close</button><button type="button" class="ma-btn ma-btn-primary hidden" id="maCpAdd">Add selected to draft</button>`);
        document.getElementById('maCpClose').onclick = closeModal;
        document.getElementById('maSrc').onchange = async (ev) => {
            const body = document.getElementById('maCopyBody'); const add = document.getElementById('maCpAdd');
            add.classList.add('hidden');
            if (!ev.target.value) { body.innerHTML = ''; return; }
            body.innerHTML = '<p class="text-sm text-gray-400">Loading…</p>';
            const r = await call('GET', API + '/copy-diff/' + ev.target.value);
            if (!r.ok) { body.innerHTML = '<p class="text-sm text-red-600">Could not load the comparison.</p>'; return; }
            const rows = r.json.data.rows;
            if (!rows.length) { body.innerHTML = '<p class="text-sm text-gray-500">Nothing to copy — both roles already have the same access (Global ESS items excluded).</p>'; return; }
            const tone = { grant: 'bg-green-50 text-green-800', revoke: 'bg-red-50 text-red-800', change: 'bg-blue-50 text-blue-800' };
            const s = r.json.data.summary;
            body.innerHTML = `<div class="flex flex-wrap items-center gap-2 mb-2">
                <span class="ma-badge ${tone.grant}">${s.grant} would be granted</span><span class="ma-badge ${tone.revoke}">${s.revoke} would be revoked</span><span class="ma-badge ${tone.change}">${s.change} would change</span>
                <button type="button" class="ml-auto text-xs font-semibold text-gray-500 hover:text-gray-800" id="maCpAll">Select all / none</button></div>
                <ul class="divide-y divide-gray-100 max-h-[40vh] overflow-y-auto border border-gray-100 rounded-lg px-3">${rows.map((x) => `<li class="py-1.5">
                    <label class="flex items-start gap-3 cursor-pointer"><input type="checkbox" class="mt-1 ma-cp" data-id="${x.menu_id}" checked>
                    <span class="min-w-0 flex-1"><span class="text-sm font-medium text-gray-900">${esc(x.name)}</span> <span class="ma-badge ${tone[x.kind]}">${x.kind}</span>
                    <span class="block text-[11px] text-gray-500"><code>${esc(x.slug)}</code> · ${esc(stateText(x.b))} → ${esc(stateText(x.a))}</span></span></label></li>`).join('')}</ul>`;
            add.classList.remove('hidden');
            document.getElementById('maCpAll').onclick = () => { const cbs = body.querySelectorAll('.ma-cp'); const allOn = Array.from(cbs).every((c) => c.checked); cbs.forEach((c) => { c.checked = !allOn; }); };
            add.onclick = () => {
                let n = 0;
                body.querySelectorAll('.ma-cp:checked').forEach((cb) => {
                    const row = rows.find((x) => x.menu_id === parseInt(cb.dataset.id, 10));
                    if (row) { setDraft(row.menu_id, row.a); n++; }
                });
                closeModal(); render();
                notify(n + ' difference(s) added to your draft — review and save when ready.', 'info');
            };
        };
    }

    // ── Compare
    async function openCompare() {
        const opts = await roleOptions();
        openModal('Compare with another role', `
            <select id="maCmp" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"><option value="">Select a role…</option>${opts}</select>
            <div id="maCmpBody" class="mt-4"></div>`, `<button type="button" class="ma-btn" id="maCmpClose">Close</button>`);
        document.getElementById('maCmpClose').onclick = closeModal;
        document.getElementById('maCmp').onchange = async (ev) => {
            const body = document.getElementById('maCmpBody');
            if (!ev.target.value) { body.innerHTML = ''; return; }
            body.innerHTML = '<p class="text-sm text-gray-400">Loading…</p>';
            const r = await call('GET', API + '/compare/' + ev.target.value);
            if (!r.ok) { body.innerHTML = '<p class="text-sm text-red-600">Could not load the comparison.</p>'; return; }
            const d = r.json.data;
            if (!d.rows.length) { body.innerHTML = '<p class="text-sm text-gray-500">These two roles have exactly the same menu access.</p>'; return; }
            body.innerHTML = `<p class="text-xs text-gray-500 mb-2">${d.rows.length} menu(s) differ. Showing only differences.</p>
                <div class="max-h-[45vh] overflow-auto border border-gray-100 rounded-lg"><table class="w-full text-sm"><thead class="bg-gray-50 text-[11px] uppercase text-gray-500 sticky top-0"><tr>
                <th class="text-left px-3 py-2">Menu</th><th class="text-left px-3 py-2">${esc(d.a.name)}</th><th class="text-left px-3 py-2">${esc(d.b.name)}</th></tr></thead><tbody class="divide-y divide-gray-100">
                ${d.rows.map((x) => `<tr><td class="px-3 py-1.5"><span class="font-medium text-gray-900">${esc(x.name)}</span><div><code class="text-[11px] text-gray-400">${esc(x.slug)}</code></div></td>
                    <td class="px-3 py-1.5 ${x.a ? 'text-green-700' : 'text-gray-400'}">${esc(stateText(x.a))}</td><td class="px-3 py-1.5 ${x.b ? 'text-green-700' : 'text-gray-400'}">${esc(stateText(x.b))}</td></tr>`).join('')}
                </tbody></table></div>`;
        };
    }

    // ── History + undo
    async function openHistory() {
        openModal('History — ' + ROLE_NAME, '<p class="text-sm text-gray-400">Loading…</p>', `<button type="button" class="ma-btn" id="maHiClose">Close</button>`);
        document.getElementById('maHiClose').onclick = closeModal;
        const r = await call('GET', API + '/history');
        const body = document.getElementById('maModalBody');
        if (!r.ok) { body.innerHTML = '<p class="text-sm text-red-600">Could not load history.</p>'; return; }
        const rows = r.json.data;
        if (!rows.length) { body.innerHTML = '<p class="text-sm text-gray-500">No changes recorded yet. Every Save from this page appears here and can be undone.</p>'; return; }
        body.innerHTML = '<p id="maHiMsg" class="hidden mb-3 text-sm rounded-lg p-3"></p><ul class="space-y-2">' + rows.map((h) => `<li class="border border-gray-100 rounded-lg p-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="text-sm"><strong>${esc(h.actor)}</strong> <span class="text-gray-400">· ${esc(h.at)}</span>
                    ${h.kind === 'undo' ? '<span class="ma-badge bg-gray-100 text-gray-600 ml-1">undo</span>' : ''}${h.reverted ? '<span class="ma-badge bg-gray-100 text-gray-500 ml-1">undone</span>' : ''}</div>
                <button type="button" class="ma-btn ma-btn-sm" data-undo="${h.id}" ${h.reverted ? 'disabled' : ''}><i class="fas fa-rotate-left mr-1"></i> Undo</button></div>
            <div class="text-xs text-gray-600 mt-1">${h.summary.grant} granted · ${h.summary.revoke} revoked · ${h.summary.change} changed${h.reason ? ' — “' + esc(h.reason) + '”' : ''}</div>
            <details class="mt-1"><summary class="text-xs text-gray-500 cursor-pointer">Show ${h.changes.length} item(s)</summary>
                <ul class="mt-1 text-[11px] text-gray-600 space-y-0.5">${h.changes.map((c) => `<li><code>${esc(c.slug)}</code> · ${esc(stateText(c.before))} → ${esc(stateText(c.after))}</li>`).join('')}</ul></details></li>`).join('') + '</ul>';
        body.querySelectorAll('[data-undo]').forEach((b) => b.addEventListener('click', async () => {
            const msg = document.getElementById('maHiMsg');
            if (draft.size && !confirm('You have unsaved changes. Undoing now reloads the page data and discards your draft. Continue?')) { return; }
            b.disabled = true;
            const u = await call('POST', API + '/history/' + b.dataset.undo + '/undo');
            msg.classList.remove('hidden');
            if (u.ok) { notify('Change undone. Access restored to what it was before that save.', 'success'); await load(); openHistory(); return; }
            b.disabled = false;
            msg.className = 'mb-3 text-sm rounded-lg p-3 bg-red-50 text-red-800';
            const names = (u.json.conflicts || []).map((c) => c.name).slice(0, 5).join(', ');
            msg.textContent = (u.json.message || 'Could not undo.') + (names ? ' Affected: ' + names + '.' : '');
        }));
    }

    // ───────────────────────────────────────────────── peristiwa
    document.getElementById('maModules').addEventListener('click', (e) => { const b = e.target.closest('[data-mod]'); if (b) { moduleSel = b.dataset.mod; render(); } });
    document.querySelectorAll('.ma-filter').forEach((b) => b.addEventListener('click', () => { filter = b.dataset.filter; syncFilter(); renderRows(); }));
    function syncFilter() { document.querySelectorAll('.ma-filter').forEach((b) => { const on = b.dataset.filter === filter; b.classList.toggle('is-on', on); b.setAttribute('aria-selected', on); }); }
    let t = null;
    document.getElementById('maSearch').addEventListener('input', (e) => { clearTimeout(t); t = setTimeout(() => { query = e.target.value; renderRows(); }, 120); });

    document.getElementById('maRows').addEventListener('change', (e) => {
        const cb = e.target; if (!cb.dataset || !cb.dataset.k) { return; }
        const id = parseInt(cb.dataset.id, 10);
        if (cb.dataset.k === 'v') { toggleView(id, cb.checked); } else { toggleCrud(id, cb.dataset.k, cb.checked); }
        render();
    });
    document.getElementById('maRows').addEventListener('click', (e) => {
        const b = e.target.closest('[data-tree]'); if (!b) { return; }
        const id = parseInt(b.dataset.tree, 10);
        const ids = subtree(id).map((x) => menusById[x]).filter((m) => !locked(m));
        const turnOn = !ids.every((m) => eff(m.id));
        ids.forEach((m) => toggleView(m.id, turnOn));
        render();
    });
    document.getElementById('maBulkGrant').addEventListener('click', () => { visibleRows().forEach(({ id }) => { const m = menusById[id]; if (canGrant(m) && !eff(id)) { toggleView(id, true); } }); render(); });
    document.getElementById('maBulkRevoke').addEventListener('click', () => {
        const targets = visibleRows().map(({ id }) => menusById[id]).filter((m) => canRevoke(m) && eff(m.id));
        if (targets.length > 25 && !confirm('Revoke access from ' + targets.length + ' items? You can still review before saving.')) { return; }
        targets.forEach((m) => toggleView(m.id, false)); render();
    });
    document.getElementById('maDiscard').addEventListener('click', () => { if (confirm('Discard all ' + draft.size + ' unsaved change(s)?')) { draft.clear(); render(); } });
    document.getElementById('maReview').addEventListener('click', openReview);
    document.getElementById('maBtnCopy').addEventListener('click', openCopy);
    document.getElementById('maBtnCompare').addEventListener('click', openCompare);
    document.getElementById('maBtnHistory').addEventListener('click', openHistory);
    window.addEventListener('beforeunload', (e) => { if (draft.size) { e.preventDefault(); e.returnValue = ''; } });

    syncFilter();
    load();
})();
</script>
@endsection

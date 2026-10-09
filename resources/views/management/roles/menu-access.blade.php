@extends('dashboard')

@section('title', 'Menu Access — ' . $role->name)
@section('page-title', 'Menu Access')

{{--
    Halaman penuh Menu Access per role (HC-D63/D64) — menggantikan modal sempit di daftar Role.

    Cara kerja (ringkas):
      - Perubahan hanya DRAF di peramban sampai "Review & Save" ditekan; server menulis semuanya sebagai SATU unit
        (transaksi + riwayat + audit log) lewat /api/roles/{id}/menu-access/apply.
      - Satu baris = satu HALAMAN atau TAB (matrix.rows, disusun MenuAccessLayout). Izin aksi ("Create Evaluation",
        "Approve / Reject", "Update …") dilipat ke baris halamannya: Create/Edit/Delete jadi kotak centang di kolomnya,
        aksi lain di kolom "Other actions". Tiap kotak tetap menulis ke slug-nya sendiri — data & penegakan tak berubah.
      - Tab punya izin sendiri dan dikelompokkan di bawah judul hub, sehingga role bisa diberi sebagian tab saja.
      - Flag C/E/D lama yang tak berfungsi tidak dihapus, hanya ditandai.
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

        <div class="mt-4 rounded-lg bg-blue-50 border border-blue-100 text-blue-900 text-xs px-3 py-2 leading-relaxed space-y-1">
            <p><strong>How to read this page.</strong> Each row is a <em>page</em> or a <em>tab</em>. Tick <strong>View</strong> to let the role open it, then tick what it may do there:
                <strong>Create / Edit / Delete</strong>, or the special permissions under <strong>Other actions</strong> (Approve, Export, …). A dash (–) means that action does not exist for the row.</p>
            <p><span class="ma-badge bg-indigo-50 text-indigo-700"><i class="fas fa-table-columns text-[9px]"></i>Tab</span> rows are listed one by one under their page, so you can give a role only some of the tabs.
                Nothing is saved until you press <em>Review &amp; Save</em>. <span class="whitespace-nowrap"><i class="fas fa-lock text-[10px]"></i> Global ESS</span> is always on for every employee.
                <span class="whitespace-nowrap"><i class="fas fa-shield-halved text-[10px] text-amber-600"></i> Sensitive</span> items ask for an extra confirmation.
                Turning View off also removes that row's actions.</p>
        </div>
    </div>

    {{-- Toolbar --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 flex flex-col lg:flex-row lg:items-center gap-3">
        <div class="relative flex-1 min-w-0">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-xs text-gray-400"></i>
            <input id="maSearch" type="search" placeholder="Search page, tab, action or slug…" aria-label="Search page, tab, action or slug"
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
                    <button type="button" id="maBulkGrant" class="ma-btn ma-btn-sm" title="Give View to every row currently shown"><i class="fas fa-plus mr-1"></i> Grant View to shown</button>
                    <button type="button" id="maBulkRevoke" class="ma-btn ma-btn-sm" title="Remove View (and its actions) from every row currently shown"><i class="fas fa-minus mr-1"></i> Revoke from shown</button>
                </div>
            </div>
            <div class="hidden sm:grid ma-grid px-4 py-2 text-[11px] font-semibold uppercase tracking-wider text-gray-500 border-b border-gray-100 bg-white sticky top-0 z-10">
                <div>Page / Tab</div><div class="text-center">View</div><div class="text-center">Create</div><div class="text-center">Edit</div><div class="text-center">Delete</div><div>Other actions</div>
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
    .ma-mod-group { display:flex; align-items:center; gap:.1rem; }
    .ma-mod-group .ma-mod { flex:1; min-width:0; }
    .ma-chev { padding:.45rem .55rem; border-radius:.5rem; color:#9ca3af; }
    .ma-chev:hover { background:#f3f4f6; color:#374151; }
    .ma-chev:focus-visible { outline:2px solid #991b1b; outline-offset:2px; }
    .ma-mod-subs { margin:.1rem 0 .35rem .9rem; padding-left:.35rem; border-left:1px solid #e5e7eb; }
    .ma-mod-sub { font-size:.78rem; padding:.35rem .6rem; }
    .ma-grid { display:grid; grid-template-columns:minmax(0,1fr) 3.75rem 3.75rem 3.75rem 3.75rem minmax(8rem,15rem); align-items:center; column-gap:.25rem; }
    .ma-row { padding:.5rem 1rem; }
    .ma-row:hover { background:#fafafa; }
    .ma-row.is-changed { background:#fffbeb; box-shadow:inset 3px 0 0 #f59e0b; }
    .ma-cell { display:flex; justify-content:center; align-items:center; min-height:1.5rem; }
    .ma-cell input[type=checkbox], .ma-act input[type=checkbox] { width:1.05rem; height:1.05rem; accent-color:var(--primary-color, #991b1b); cursor:pointer; }
    .ma-cell input[disabled], .ma-act input[disabled] { cursor:not-allowed; opacity:.5; }
    .ma-cell label { display:inline-flex; align-items:center; gap:.3rem; }
    .ma-cell.is-linked input[type=checkbox] { outline:2px dotted #a5b4fc; outline-offset:2px; border-radius:2px; }
    .ma-lbl { display:none; font-size:.6875rem; color:#6b7280; }
    .ma-acts { display:flex; flex-direction:column; gap:.15rem; min-width:0; }
    .ma-act { display:flex; align-items:center; gap:.4rem; font-size:.75rem; color:#374151; min-width:0; cursor:pointer; }
    .ma-act span { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .ma-act-toggle { font-size:.75rem; font-weight:600; color:#4b5563; padding:.15rem .5rem; border:1px solid #e5e7eb; border-radius:9999px; white-space:nowrap; }
    .ma-act-toggle:hover { background:#f3f4f6; }
    .ma-panel { margin:.4rem 0 .15rem; padding:.5rem .75rem; background:#f9fafb; border:1px solid #f1f5f9; border-radius:.5rem; display:grid; grid-template-columns:repeat(auto-fill,minmax(15rem,1fr)); gap:.3rem .75rem; }
    .ma-panel .ma-act span { white-space:normal; }
    .ma-hub { display:flex; align-items:center; gap:.5rem; padding:.4rem 1rem; background:#eef2ff; border-top:1px solid #e0e7ff; font-size:.75rem; color:#3730a3; }
    .ma-badge { display:inline-flex; align-items:center; gap:.25rem; font-size:.6875rem; font-weight:600; padding:.05rem .4rem; border-radius:9999px; white-space:nowrap; }
    .ma-note { font-size:.6875rem; color:#9ca3af; }
    /* bilah draf mengikuti lebar sidebar saat mode rail (5rem) */
    @media (min-width: 1024px) { html[data-sb-layout="rail"] #maBar { left: 5rem; } }
    @media (max-width: 639px) {
        .ma-grid { grid-template-columns:minmax(0,1fr) auto; row-gap:.35rem; }
        .ma-cell-c, .ma-cell-e, .ma-cell-d, .ma-cell-a { grid-column:1 / -1; justify-content:flex-start; }
        .ma-cell-c:has(.ma-dash), .ma-cell-e:has(.ma-dash), .ma-cell-d:has(.ma-dash) { display:none; }
        .ma-lbl { display:inline; }
        .ma-cell-a .ma-act-toggle { margin-left:0; }
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
    let orderIndex = {};
    let rows = [];                   // baris tampil dari server (halaman / tab / seksi / aksi tunggal)
    let rowByKey = {};
    let rowChildren = {};
    const expanded = new Set();      // baris yang panel "Other actions"-nya terbuka
    let grants = {};                 // menu_id -> {v,c,e,d} | undefined
    const draft = new Map();         // menu_id -> {v,c,e,d} | null   (keadaan yang DIINGINKAN, hanya yang berbeda dari server)
    let moduleSel = 'all', filter = 'all', query = '';
    // Modules that share a `group` (Human Resources, Master, Work & Service ...) fold into one block of the left column;
    // the block can be picked as a whole to see all its rows together. Modules without a group stand alone.
    const groupKey = (g) => 'group:' + String(g).toLowerCase().replace(/[^a-z0-9]+/g, '-');
    const groupOpen = new Set();     // blocks the person opened by hand
    let groupOf = {};                // module key -> its block's key

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
        groupOf = {};
        data.modules.forEach((m) => { if (m.group) { groupOf[m.key] = groupKey(m.group); } });
        grants = data.grants || {};
        menusById = {}; orderIndex = {};
        data.menus.forEach((m, i) => { menusById[m.id] = m; orderIndex[m.id] = i; });
        rows = data.rows || []; rowByKey = {}; rowChildren = {};
        rows.forEach((r) => { rowByKey[r.key] = r; });
        rows.forEach((r) => { const p = r.parent_key && rowByKey[r.parent_key] ? r.parent_key : ''; (rowChildren[p] = rowChildren[p] || []).push(r.key); });
        draft.clear();
        render();
    }

    // urutan tampil: pohon baris (induk dulu)
    function flatRows() {
        const out = [];
        const walk = (pk) => (rowChildren[pk] || []).forEach((k) => { out.push(rowByKey[k]); walk(k); });
        walk('');
        return out;
    }

    function locked(m) { return m.class === 'ess'; }
    function canGrant(m) { return m.is_active && !locked(m); }
    function canRevoke(m) { return !locked(m) && !m.protected; }

    const rowMain = (r) => menusById[r.main];
    const rowLocked = (r) => locked(rowMain(r));
    const rowModule = (r) => rowMain(r).module;
    const rowChanged = (r) => r.menu_ids.some((id) => changed(id));
    // Is this row in the module (or whole block of modules) picked on the left?
    const inScope = (r) => moduleSel === 'all' || rowModule(r) === moduleSel || groupOf[rowModule(r)] === moduleSel;
    const rowOn = (r) => !!eff(r.main);
    const allCells = (r) => ['c', 'e', 'd'].map((k) => r.cells[k]).filter(Boolean);

    function rowMatches(r, q) {
        if (r.name.toLowerCase().includes(q) || r.slug.toLowerCase().includes(q)) { return true; }
        if (r.hub && r.hub.label.toLowerCase().includes(q)) { return true; }
        return allCells(r).concat(r.actions).some((c) => (c.name || c.label).toLowerCase().includes(q) || c.slug.toLowerCase().includes(q));
    }

    // Tab satu hub ditaruh berurutan di bawah satu judul hub; sisanya mengikuti pohon.
    function visibleRows() {
        const q = query.trim().toLowerCase();
        const base = flatRows().filter((r) => {
            if (!inScope(r)) { return false; }
            if (q && !rowMatches(r, q)) { return false; }
            if (filter === 'granted' && !rowOn(r)) { return false; }
            if (filter === 'not' && rowOn(r)) { return false; }
            if (filter === 'changed' && !rowChanged(r)) { return false; }
            return true;
        });
        const out = [], done = {};
        base.forEach((r) => {
            if (!r.hub) { out.push({ row: r }); return; }
            if (done[r.hub.key]) { return; }
            done[r.hub.key] = true;
            const members = base.filter((x) => x.hub && x.hub.key === r.hub.key)
                .map((x, i) => ({ x, i })).sort((a, b) => (a.x.hub.order - b.x.hub.order) || (a.i - b.i)).map((o) => o.x);
            out.push({ hub: r.hub, members, row: r });
            members.forEach((m) => out.push({ row: m, inHub: true }));
        });
        return out;
    }

    // kedalaman relatif: hanya hitung leluhur yang ikut tampil di modul yang sama
    function relDepth(r) {
        let d = 0, p = r.parent_key && rowByKey[r.parent_key];
        while (p) { if (inScope(p)) { d++; } p = p.parent_key && rowByKey[p.parent_key]; }
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
        rows.forEach((r) => {
            const c = counts[rowModule(r)] || (counts[rowModule(r)] = { total: 0, on: 0, changed: 0 });
            if (rowLocked(r)) { return; }
            c.total++;
            if (rowOn(r)) { c.on++; }
            if (rowChanged(r)) { c.changed++; }
        });
        const changedPill = (c) => c.changed ? ' <span class="ml-1 px-1.5 rounded-full bg-amber-200 text-amber-900 text-[10px]">' + c.changed + '</span>' : '';
        const li = (key, label, c, sub, about) => `<li><button type="button" class="ma-mod ${sub ? 'ma-mod-sub' : ''} ${moduleSel === key ? 'is-on' : ''}" data-mod="${esc(key)}" aria-pressed="${moduleSel === key}"${about ? ' title="' + esc(about) + '"' : ''}>
            <span class="truncate">${esc(label)}${changedPill(c)}</span>
            <span class="ma-count">${c.on}/${c.total}</span></button></li>`;
        const sum = (list) => list.reduce((a, mod) => ({ total: a.total + counts[mod.key].total, on: a.on + counts[mod.key].on, changed: a.changed + counts[mod.key].changed }), { total: 0, on: 0, changed: 0 });
        const all = sum(data.modules.filter((mod) => counts[mod.key]));
        const shown = data.modules.filter((mod) => counts[mod.key] && (counts[mod.key].total > 0 || mod.key === 'ess'));

        const html = [li('all', 'All modules', all)];
        const done = {};
        shown.forEach((mod) => {
            if (!mod.group) { html.push(li(mod.key, mod.label, counts[mod.key], false, mod.about)); return; }
            if (done[mod.group]) { return; }
            done[mod.group] = true;
            const members = shown.filter((x) => x.group === mod.group);
            const gk = groupKey(mod.group), c = sum(members);
            const open = groupOpen.has(gk) || moduleSel === gk || members.some((x) => x.key === moduleSel);
            html.push(`<li><div class="ma-mod-group">
                <button type="button" class="ma-mod ${moduleSel === gk ? 'is-on' : ''}" data-mod="${esc(gk)}" aria-pressed="${moduleSel === gk}" title="Show every ${esc(mod.group)} module together">
                    <span class="truncate"><i class="fas fa-folder-tree text-[11px] mr-1.5 opacity-60"></i>${esc(mod.group)}${changedPill(c)}</span><span class="ma-count">${c.on}/${c.total}</span></button>
                <button type="button" class="ma-chev" data-group-toggle="${esc(gk)}" aria-expanded="${open}" aria-label="${open ? 'Collapse' : 'Expand'} ${esc(mod.group)}"><i class="fas fa-chevron-${open ? 'down' : 'right'} text-[10px]"></i></button></div>
                <ul class="ma-mod-subs ${open ? '' : 'hidden'}">${members.map((x) => li(x.key, x.short || x.label, counts[x.key], true, x.about)).join('')}</ul></li>`);
        });
        document.getElementById('maModules').innerHTML = html.join('');
    }

    function badge(cls, icon, text, title) {
        return `<span class="ma-badge ${cls}" title="${esc(title || text)}"><i class="fas ${icon} text-[9px]"></i>${esc(text)}</span>`;
    }

    const COL = { c: 'Create', e: 'Edit', d: 'Delete' };

    // Kotak untuk SATU slug fungsi (aksi) — menulis View pada menu fungsi itu.
    function fnBox(row, cell, label, title) {
        const m = menusById[cell.id], e = eff(cell.id);
        const parentOn = rowOn(row) || rowLocked(row);
        const disabled = locked(m) || (e ? !canRevoke(m) : (!canGrant(m) || !parentOn));
        const why = locked(m) ? 'Always on (Global ESS)' : (!e && !parentOn ? 'Give View to this row first' : (title || ''));
        return `<input type="checkbox" data-id="${cell.id}" data-k="v" data-row="${esc(row.key)}" ${e ? 'checked' : ''} ${disabled ? 'disabled' : ''} title="${esc(why)}" aria-label="${esc(label)} — ${esc(row.name)}">`;
    }

    // Kotak untuk flag C/E/D milik halaman itu sendiri (hanya halaman yang menegakkannya di server).
    function flagBox(row, cell) {
        const e = eff(cell.id);
        return `<input type="checkbox" data-id="${cell.id}" data-k="${cell.k}" data-row="${esc(row.key)}" ${e && e[cell.k] ? 'checked' : ''} ${e ? '' : 'disabled'} title="${esc(e ? (cell.name || '') : 'Give View to this row first')}" aria-label="${esc(COL[cell.k])} — ${esc(row.name)}">`;
    }

    function actionBox(row, a) {
        return `<label class="ma-act" title="${esc((a.name || a.label) + ' · ' + a.slug)}">${fnBox(row, a, a.label)}<span>${esc(a.label)}</span></label>`;
    }

    function rowHtml({ row }) {
        const m = rowMain(row);
        const e = eff(row.main), sv = server(row.main);
        const depth = relDepth(row);
        const lock = rowLocked(row);
        const isTab = row.kind === 'tab' || row.kind === 'section';
        const typeIcon = row.kind === 'group' ? 'fa-folder text-amber-500' : (isTab ? 'fa-table-columns text-indigo-400' : (row.kind === 'action' ? 'fa-bolt text-orange-400' : 'fa-file-lines text-blue-400'));
        const hasKids = (rowChildren[row.key] || []).length > 0;
        const badges = [];
        if (isTab) { badges.push(badge('bg-indigo-50 text-indigo-700', 'fa-table-columns', 'Tab', 'This tab has its own permission')); }
        if (lock) { badges.push(badge('bg-gray-100 text-gray-600', 'fa-lock', 'Global ESS', 'Always on for every employee — cannot be changed per role')); }
        if (m.class === 'sensitive') { badges.push(badge('bg-amber-50 text-amber-800', 'fa-shield-halved', 'Sensitive', 'Gives access to sensitive data or administration')); }
        if (m.protected) { badges.push(badge('bg-red-50 text-red-700', 'fa-user-shield', 'Protected', 'Cannot be revoked from this role (would lock administrators out)')); }
        if (!m.is_active) { badges.push(badge('bg-gray-100 text-gray-500', 'fa-ban', 'Inactive')); }
        if (m.gated_routes === 0 && row.kind !== 'group' && row.kind !== 'section' && !lock && !allCells(row).length && !row.actions.length) { badges.push(badge('bg-gray-50 text-gray-400', 'fa-circle-question', 'No route gate', 'No route uses this slug as a gate. It may still control a menu item or screen.')); }
        const legacy = sv && (sv.c || sv.e || sv.d) && !m.crud
            ? `<span class="ma-note" title="Create/Edit/Delete values saved earlier for this menu. The application does not use them, so they are kept but cannot be changed here.">stored ${['c', 'e', 'd'].filter((k) => sv[k]).map((k) => k.toUpperCase()).join('·')} · not enforced</span>` : '';

        const vDisabled = lock || (!canGrant(m) && !e) || (m.protected && !!e);
        const vTitle = lock ? 'Always on (Global ESS)' : (m.protected && e ? 'Protected — cannot be revoked' : '');

        const cell = (k) => {
            const c = row.cells[k];
            if (lock) { return `<div class="ma-cell ma-cell-${k}"><span class="ma-note ma-dash">—</span></div>`; }
            if (!c) { return `<div class="ma-cell ma-cell-${k}"><span class="ma-note ma-dash" title="${COL[k]} does not exist for this row">–</span></div>`; }
            const linked = c.covers && c.covers.length > 1;
            const tip = linked ? `${c.name || c.label} — one permission shared by ${c.covers.map((x) => COL[x]).join(' + ')}` : (c.name || '');
            const box = c.k === 'v' ? fnBox(row, c, COL[k], tip) : flagBox(row, c);
            return `<div class="ma-cell ma-cell-${k} ${linked ? 'is-linked' : ''}"><label>${box}<span class="ma-lbl">${COL[k]}</span></label></div>`;
        };

        let actCell = '';
        if (!lock && row.actions.length) {
            const on = row.actions.filter((a) => eff(a.id)).length;
            actCell = row.actions.length <= 3
                ? `<div class="ma-acts">${row.actions.map((a) => actionBox(row, a)).join('')}</div>`
                : `<button type="button" class="ma-act-toggle" data-expand="${esc(row.key)}" aria-expanded="${expanded.has(row.key)}"><i class="fas fa-chevron-${expanded.has(row.key) ? 'down' : 'right'} text-[9px] mr-1"></i>${on}/${row.actions.length} actions</button>`;
        }
        const panel = !lock && row.actions.length > 3 && expanded.has(row.key)
            ? `<div class="ma-panel">${row.actions.map((a) => actionBox(row, a)).join('')}</div>` : '';

        return `<div class="ma-row ${rowChanged(row) ? 'is-changed' : ''}" data-row="${esc(row.key)}">
          <div class="ma-grid">
            <div class="min-w-0" style="padding-left:${depth * 18}px">
                <div class="flex items-center gap-2 min-w-0">
                    <i class="fas ${typeIcon} text-xs flex-shrink-0" aria-hidden="true"></i>
                    <span class="text-sm ${row.kind === 'group' ? 'font-bold' : 'font-medium'} text-gray-900 truncate" title="${esc(row.name)}">${esc(row.name)}</span>
                    ${hasKids && !lock ? `<button type="button" class="text-[11px] text-gray-400 hover:text-gray-700 flex-shrink-0" data-tree="${esc(row.key)}" title="Toggle View for this row and the rows nested below it" aria-label="Toggle ${esc(row.name)} and the rows below"><i class="fas fa-sitemap"></i></button>` : ''}
                </div>
                <div class="flex flex-wrap items-center gap-1.5 mt-0.5">
                    <code class="text-[11px] text-gray-400 break-all">${esc(row.slug)}</code>${badges.join('')}${legacy}
                </div>
            </div>
            <div class="ma-cell ma-cell-v"><label><input type="checkbox" data-id="${row.main}" data-k="v" data-row="${esc(row.key)}" ${lock || e ? 'checked' : ''} ${vDisabled ? 'disabled' : ''} title="${esc(vTitle)}" aria-label="View — ${esc(row.name)}"><span class="ma-lbl">View</span></label></div>
            ${cell('c')}${cell('e')}${cell('d')}
            <div class="ma-cell-a min-w-0">${actCell}</div>
          </div>${panel}
        </div>`;
    }

    function hubHtml(item) {
        const on = item.members.filter((r) => rowOn(r)).length;
        const pad = 16 + relDepth(item.row) * 18;
        return `<div class="ma-hub" style="padding-left:${pad}px"><i class="fas fa-table-columns text-[11px]"></i>
            <strong>${esc(item.hub.label)}</strong><span class="text-indigo-500">· ${item.members.length} tabs, each with its own permission</span>
            <span class="ma-count ml-auto">${on}/${item.members.length}</span></div>`;
    }

    function renderRows() {
        const items = visibleRows();
        const shownRows = items.filter((i) => !i.hub).map((i) => i.row);
        document.getElementById('maRows').innerHTML = items.map((i) => i.hub ? hubHtml(i) : rowHtml(i)).join('');
        document.getElementById('maEmpty').classList.toggle('hidden', shownRows.length > 0);
        const mod = data.modules.find((x) => x.key === moduleSel);
        const grp = data.modules.find((x) => x.group && groupKey(x.group) === moduleSel);
        document.getElementById('maModuleTitle').textContent = moduleSel === 'all' ? 'All modules' : (grp ? grp.group + ' (all modules)' : (mod ? mod.label : moduleSel));
        const granted = shownRows.filter(rowOn).length;
        const about = mod && mod.about ? mod.about + ' ' : '';
        document.getElementById('maShownInfo').textContent = `${about}${shownRows.length} ${shownRows.length === 1 ? 'row' : 'rows'} shown · ${granted} with View`;
        document.getElementById('maBulkGrant').disabled = !shownRows.some((r) => canGrant(rowMain(r)) && !rowOn(r));
        document.getElementById('maBulkRevoke').disabled = !shownRows.some((r) => canRevoke(rowMain(r)) && rowOn(r));
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
    function subtreeRows(key) { const out = [rowByKey[key]]; (rowChildren[key] || []).forEach((c) => out.push(...subtreeRows(c))); return out; }
    // View pada baris; mencabut View juga mencabut semua aksi milik baris itu (aksi tanpa halaman tak ada gunanya).
    function setRowView(row, on) {
        toggleView(row.main, on);
        if (!on) { row.menu_ids.forEach((id) => { if (id !== row.main) { toggleView(id, false); } }); }
    }

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
    document.getElementById('maModules').addEventListener('click', (e) => {
        const t = e.target.closest('[data-group-toggle]');
        if (t) { const k = t.dataset.groupToggle; if (groupOpen.has(k)) { groupOpen.delete(k); } else { groupOpen.add(k); } renderModules(); return; }
        const b = e.target.closest('[data-mod]');
        if (b) { moduleSel = b.dataset.mod; render(); }
    });
    document.querySelectorAll('.ma-filter').forEach((b) => b.addEventListener('click', () => { filter = b.dataset.filter; syncFilter(); renderRows(); }));
    function syncFilter() { document.querySelectorAll('.ma-filter').forEach((b) => { const on = b.dataset.filter === filter; b.classList.toggle('is-on', on); b.setAttribute('aria-selected', on); }); }
    let t = null;
    document.getElementById('maSearch').addEventListener('input', (e) => { clearTimeout(t); t = setTimeout(() => { query = e.target.value; renderRows(); }, 120); });

    document.getElementById('maRows').addEventListener('change', (e) => {
        const cb = e.target; if (!cb.dataset || !cb.dataset.k) { return; }
        const id = parseInt(cb.dataset.id, 10);
        const row = rowByKey[cb.dataset.row];
        if (cb.dataset.k === 'v') { if (row && id === row.main) { setRowView(row, cb.checked); } else { toggleView(id, cb.checked); } }
        else { toggleCrud(id, cb.dataset.k, cb.checked); }
        render();
    });
    document.getElementById('maRows').addEventListener('click', (e) => {
        const x = e.target.closest('[data-expand]');
        if (x) { const k = x.dataset.expand; if (expanded.has(k)) { expanded.delete(k); } else { expanded.add(k); } renderRows(); return; }
        const b = e.target.closest('[data-tree]'); if (!b) { return; }
        const list = subtreeRows(b.dataset.tree).filter((r) => !rowLocked(r));
        const turnOn = !list.every(rowOn);
        list.forEach((r) => setRowView(r, turnOn));
        render();
    });
    document.getElementById('maBulkGrant').addEventListener('click', () => { visibleRows().filter((i) => !i.hub).forEach(({ row }) => { if (canGrant(rowMain(row)) && !rowOn(row)) { setRowView(row, true); } }); render(); });
    document.getElementById('maBulkRevoke').addEventListener('click', () => {
        const targets = visibleRows().filter((i) => !i.hub).map((i) => i.row).filter((r) => canRevoke(rowMain(r)) && rowOn(r));
        if (targets.length > 25 && !confirm('Revoke access from ' + targets.length + ' rows (and their actions)? You can still review before saving.')) { return; }
        targets.forEach((r) => setRowView(r, false)); render();
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

{{--
    Daftar rekaman satu seksi (Management → Employee → Address/Identification/Family/Education/Bank/Payment/Attachment).
    Satu permintaan ke server per halaman/pencarian (lihat ManagementEmployeeRecordsController). Parameter:
      $title, $subtitle, $dataUrl, $columns = [['key' => 'city', 'label' => 'City', 'type' => 'text|date|bool|bytes|money|datetime', 'class' => '(opsional)']], $emptyText
--}}
@php
    $sectionKey = 'rec' . substr(md5($dataUrl), 0, 6);
    $perPageOptions = \App\Http\Controllers\ManagementEmployeeRecordsController::PER_PAGE_OPTIONS;
@endphp
<div class="bg-white rounded-xl p-6 shadow-sm">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-5 pb-4 border-b-2 border-gray-100">
        <div><h2 class="text-2xl font-bold text-gray-900">{{ $title }}</h2><p class="text-sm text-gray-500 mt-1">{{ $subtitle }}</p></div>
        <div class="relative w-full sm:w-80" role="search">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
            <input type="search" id="recSearch" autocomplete="off" spellcheck="false" maxlength="100" placeholder="Search name, ECI or any column…" aria-label="Search"
                class="w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-lg text-sm bg-white placeholder-gray-400 shadow-sm hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent transition-all">
        </div>
    </div>

    <div class="overflow-x-auto border border-gray-200 rounded-lg" id="recWrap" aria-busy="true">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase w-14">No</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">ECI</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Name</th>
                    @foreach($columns as $c)<th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase whitespace-nowrap">{{ $c['label'] }}</th>@endforeach
                    <th class="px-4 py-3 w-24"></th>
                </tr>
            </thead>
            <tbody id="recBody" class="divide-y divide-gray-100"></tbody>
        </table>
    </div>

    <div class="mt-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-xs text-gray-500">
        <span id="recCount" aria-live="polite"></span>
        <div class="flex items-center gap-3">
            <label class="flex items-center gap-1.5">Rows
                <select id="recPerPage" class="border border-gray-300 rounded-md px-2 py-1 text-xs bg-white" data-no-enhance>@foreach($perPageOptions as $n)<option value="{{ $n }}">{{ $n }}</option>@endforeach</select></label>
            <div class="flex items-center gap-1" id="recPager"></div>
        </div>
    </div>
</div>

<script>
(function () {
    const URL_DATA = @json($dataUrl), COLS = @json($columns), EMPTY = @json($emptyText ?? 'No data');
    const body = document.getElementById('recBody'), wrap = document.getElementById('recWrap'), count = document.getElementById('recCount');
    const search = document.getElementById('recSearch'), perSel = document.getElementById('recPerPage'), pager = document.getElementById('recPager');
    const span = COLS.length + 4;
    let page = 1, seq = 0, timer = null;

    const esc = v => String(v ?? '').replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
    const fmt = {
        text: v => v === null || v === '' || v === undefined ? '-' : esc(v),
        date: v => v ? esc(String(v).slice(0, 10)) : '-',
        datetime: v => v ? esc(String(v).replace('T', ' ').slice(0, 16)) : '-',
        bool: v => v ? '<span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-green-100 text-green-700">Yes</span>' : '<span class="text-gray-400">No</span>',
        bytes: v => { v = Number(v); if (!v) return '-'; return v < 1024 ? v + ' B' : v < 1048576 ? (v / 1024).toFixed(1) + ' KB' : (v / 1048576).toFixed(1) + ' MB'; },
        money: v => v === null || v === undefined || v === '' ? '-' : 'Rp ' + Number(v).toLocaleString('id-ID'),
    };

    const skeleton = () => Array.from({ length: 6 }, (_, i) => `<tr aria-hidden="true"><td colspan="${span}" class="px-4 py-3.5"><div class="h-4 rounded bg-gray-100 animate-pulse" style="width:${[92, 85, 96, 78, 88, 82][i]}%"></div></td></tr>`).join('');
    const message = (title, hint, retry) => `<tr><td colspan="${span}" class="px-4 py-12 text-center"><p class="text-sm font-medium text-gray-800">${esc(title)}</p>${hint ? `<p class="text-xs text-gray-500 mt-1">${hint}</p>` : ''}${retry ? '<button type="button" data-rec-retry class="mt-3 px-3 py-1.5 text-xs font-semibold rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">Try again</button>' : ''}</td></tr>`;

    function render(rows, p) {
        if (!rows.length) {
            body.innerHTML = search.value.trim()
                ? message('No records match your search', 'Check the spelling or try fewer words.')
                : message(EMPTY, 'There is nothing to show yet.');
        } else {
            body.innerHTML = rows.map((r, i) => `<tr class="hover:bg-gray-50 transition-colors">
                <td class="px-4 py-3 text-gray-400 text-xs">${p.from + i}</td>
                <td class="px-4 py-3 font-mono text-xs text-gray-600 whitespace-nowrap">${esc(r.eci || '-')}</td>
                <td class="px-4 py-3 font-semibold text-gray-900">${esc(r.name)}</td>
                ${COLS.map(c => `<td class="px-4 py-3 text-gray-600 ${c.class || ''}">${(fmt[c.type || 'text'] || fmt.text)(r[c.key])}</td>`).join('')}
                <td class="px-4 py-3 text-right"><a href="/master/employee/${encodeURIComponent(r.employee_id)}" class="text-xs text-red-700 hover:underline whitespace-nowrap">Detail →</a></td></tr>`).join('');
        }
        count.textContent = p.total ? `Showing ${p.from}–${p.to} of ${p.total} record${p.total === 1 ? '' : 's'}` : '0 records';
        const btn = (label, target, disabled, active) => `<button type="button" data-rec-page="${target}" ${disabled ? 'disabled' : ''} class="min-w-[32px] px-2.5 py-1 rounded-md border text-xs font-semibold ${active ? 'border-transparent text-white primary-gradient' : 'border-gray-300 text-gray-700 hover:bg-gray-50'} disabled:opacity-40 disabled:cursor-not-allowed">${label}</button>`;
        if (p.last_page <= 1) { pager.innerHTML = ''; return; }
        const from = Math.max(1, Math.min(p.current_page - 2, p.last_page - 4)), to = Math.min(p.last_page, from + 4);
        pager.innerHTML = btn('‹', p.current_page - 1, p.current_page <= 1) + Array.from({ length: to - from + 1 }, (_, i) => btn(from + i, from + i, false, from + i === p.current_page)).join('') + btn('›', p.current_page + 1, p.current_page >= p.last_page);
    }

    async function load() {
        const mine = ++seq;
        wrap.setAttribute('aria-busy', 'true');
        if (!body.querySelector('tr:not([aria-hidden])') || body.querySelector('[data-rec-retry]')) body.innerHTML = skeleton(); else body.classList.add('opacity-50');
        try {
            const qs = new URLSearchParams({ page, per_page: perSel.value, q: search.value.trim() });
            const res = await fetch(URL_DATA + '?' + qs, { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            let json = null; try { json = await res.json(); } catch (e) {}
            if (mine !== seq) return;
            if (!res.ok || !json || !json.success) {
                body.innerHTML = message(res.status === 403 ? 'You do not have access to this data' : 'The list could not be loaded',
                    esc((json && json.message) || 'Please try again in a moment.'), res.status !== 403);
                count.textContent = ''; pager.innerHTML = '';
            } else if (json.pagination.current_page > json.pagination.last_page) {
                page = json.pagination.last_page; return load();
            } else { render(json.data, json.pagination); }
        } catch (e) {
            if (mine === seq) { body.innerHTML = message('Could not reach the server', 'Check your connection and try again.', true); count.textContent = ''; pager.innerHTML = ''; }
        } finally {
            if (mine === seq) { body.classList.remove('opacity-50'); wrap.setAttribute('aria-busy', 'false'); }
        }
    }

    search.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => { page = 1; load(); }, 350); });
    search.addEventListener('keydown', e => { if (e.key === 'Escape' && search.value) { e.stopPropagation(); search.value = ''; page = 1; load(); } });
    perSel.addEventListener('change', () => { page = 1; load(); });
    pager.addEventListener('click', e => { const b = e.target.closest('[data-rec-page]'); if (b && !b.disabled) { page = Number(b.dataset.recPage); load(); } });
    body.addEventListener('click', e => { if (e.target.closest('[data-rec-retry]')) load(); });
    document.addEventListener('keydown', e => {
        if (e.key !== '/' || e.ctrlKey || e.metaKey || e.altKey) return;
        const t = e.target; if (t && (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName))) return;
        e.preventDefault(); search.focus(); search.select();
    });
    load();
})();
</script>

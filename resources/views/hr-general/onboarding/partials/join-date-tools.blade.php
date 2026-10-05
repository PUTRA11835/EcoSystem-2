{{--
    Alat join date untuk HR (HC-D64) — muncul HANYA saat filter "Missing: Join date" aktif dan pengguna memegang izin
    general.onboarding.join-date. Join date dikunci dari pegawai (HC-D62), jadi untuk data LAMA yang kosong HR memakai:
      1. isian tanggal per baris + "Save dates"      (hanya mengisi yang masih kosong; tak pernah menimpa)
      2. impor CSV/tempel (ECI, tanggal) dengan pratinjau sebelum diterapkan
      3. pengingat lewat bel notifikasi ke karyawan terpilih (maks. sekali per 7 hari per orang)
    Semuanya opsional. Variabel: $rows (paginator Onboarding).
--}}
@php
    $jdMax = now()->addDays(\App\Services\Onboarding\JoinDateRules::MAX_AHEAD_DAYS)->toDateString();
@endphp
<div id="jdTools" class="mx-5 mb-4 rounded-xl border border-amber-200 bg-amber-50/50 p-4"
     data-save="{{ route('general.onboarding.join-dates.save') }}" data-preview="{{ route('general.onboarding.join-dates.preview') }}"
     data-import="{{ route('general.onboarding.join-dates.import') }}" data-remind="{{ route('general.onboarding.join-dates.remind') }}">
    <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3">
        <div class="min-w-0">
            <h3 class="text-sm font-bold text-gray-900"><i class="fas fa-calendar-check text-amber-600 mr-1.5"></i> Join date tools <span class="ml-1 text-[11px] font-semibold text-gray-500">optional</span></h3>
            <p class="text-xs text-gray-600 mt-1 leading-relaxed">
                Employees cannot edit their own join date. Fill the dates you have below, import a list, or remind employees to send HR a copy of their offer letter or contract.
                Only <strong>empty</strong> dates are filled — an existing join date is never overwritten (change those in Master › Employee).
            </p>
        </div>
        <div class="flex flex-wrap gap-2 flex-shrink-0">
            <button type="button" id="jdImportBtn" class="px-3 py-2 text-xs font-semibold bg-white text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50"><i class="fas fa-file-import mr-1.5"></i> Import list…</button>
            <button type="button" id="jdRemindBtn" class="px-3 py-2 text-xs font-semibold bg-white text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50" disabled><i class="fas fa-bell mr-1.5"></i> Remind selected <span id="jdSelCount" class="ml-1 text-gray-400">(0)</span></button>
            <button type="button" id="jdSaveBtn" class="px-3 py-2 text-xs font-semibold text-white primary-gradient rounded-lg hover:opacity-90" disabled><i class="fas fa-floppy-disk mr-1.5"></i> Save dates <span id="jdFillCount" class="ml-1 opacity-80">(0)</span></button>
        </div>
    </div>

    <div class="mt-3 overflow-x-auto bg-white rounded-lg border border-gray-100">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wide border-b border-gray-100">
                    <th class="px-3 py-2 w-10"><input type="checkbox" id="jdAll" aria-label="Select all on this page"></th>
                    <th class="px-3 py-2">Employee</th>
                    <th class="px-3 py-2">Position</th>
                    <th class="px-3 py-2 w-48">Join date</th>
                    <th class="px-3 py-2 w-40">Result</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $r)
                    @continue(!empty($r['join_date']))
                    <tr class="border-b border-gray-50" data-jd-row="{{ $r['employee_id'] }}">
                        <td class="px-3 py-2"><input type="checkbox" class="jd-sel" value="{{ $r['employee_id'] }}" aria-label="Select {{ $r['name'] }}"></td>
                        <td class="px-3 py-2"><span class="font-medium text-gray-900">{{ $r['name'] }}</span> <span class="text-xs text-gray-400">{{ $r['eci'] }}</span></td>
                        <td class="px-3 py-2 text-gray-600 text-xs">{{ $r['position'] ?? '—' }}</td>
                        <td class="px-3 py-2"><input type="date" class="jd-date w-full px-2 py-1.5 border border-gray-300 rounded-lg text-sm" min="{{ \App\Services\Onboarding\JoinDateRules::MIN_DATE }}" max="{{ $jdMax }}" aria-label="Join date of {{ $r['name'] }}"></td>
                        <td class="px-3 py-2 text-xs jd-result text-gray-400">—</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-3 py-6 text-center text-sm text-gray-500">Nobody on this page is missing a join date.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p class="text-[11px] text-gray-500 mt-2">Dates may not be before {{ \Carbon\Carbon::parse(\App\Services\Onboarding\JoinDateRules::MIN_DATE)->format('d M Y') }} or more than {{ \App\Services\Onboarding\JoinDateRules::MAX_AHEAD_DAYS }} days ahead. Every change is recorded in the employee history and the audit log.</p>
</div>

{{-- Dialog impor --}}
<div id="jdModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-40" aria-hidden="true">
    <div role="dialog" aria-modal="true" aria-labelledby="jdModalTitle" class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[88vh] flex flex-col">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <h3 id="jdModalTitle" class="text-base font-bold text-gray-900">Import join dates</h3>
            <button type="button" id="jdModalClose" class="text-gray-400 hover:text-gray-700 p-1" aria-label="Close"><i class="fas fa-xmark"></i></button>
        </div>
        <div class="px-5 py-4 overflow-y-auto flex-1">
            <p class="text-sm text-gray-600">Paste rows or choose a CSV file with two columns: <strong>ECI</strong> and <strong>join date</strong>. Dates are read as day/month/year (e.g. <code>05/10/2026</code>, <code>2026-10-05</code>, <code>5 Oct 2026</code>). A header row is optional.</p>
            <textarea id="jdCsv" rows="6" class="mt-3 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono" placeholder="C26001, 01/03/2023&#10;C26002, 15/07/2022"></textarea>
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <input type="file" id="jdFile" accept=".csv,.txt,text/csv,text/plain" class="text-xs">
                <button type="button" id="jdPreviewBtn" class="px-3 py-2 text-xs font-semibold bg-white text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50">Preview</button>
            </div>
            <div id="jdPreview" class="mt-4"></div>
        </div>
        <div class="px-5 py-3 border-t border-gray-100 flex justify-end gap-2">
            <button type="button" id="jdCancel" class="px-4 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-100 rounded-lg">Close</button>
            <button type="button" id="jdApplyBtn" class="px-4 py-2 text-xs font-semibold text-white primary-gradient rounded-lg hover:opacity-90 hidden">Apply</button>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';
    const root = document.getElementById('jdTools');
    if (!root) { return; }
    const csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    const $ = (s, r) => (r || document).querySelector(s);
    const $$ = (s, r) => Array.from((r || document).querySelectorAll(s));
    const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const notify = (m, t) => (window.showNotification ? window.showNotification(m, t || 'info') : alert(m));
    const REASON = { invalid_date: 'Not a valid date', too_old: 'Before 1990', in_future: 'Too far ahead', already_set: 'Already has a date', not_found: 'Employee not found',
        recently_reminded: 'Reminded recently', unknown_eci: 'ECI not found', inactive: 'Inactive employee', duplicate: 'Duplicate ECI in list', missing_eci: 'ECI missing', missing_date: 'Date missing' };

    async function post(url, body) {
        const res = await fetch(url, { method: 'POST', credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify(body) });
        let json = {}; try { json = await res.json(); } catch (e) { /* tanpa isi */ }
        return { ok: res.ok && json.success !== false, status: res.status, json };
    }

    // ── isian per baris
    const saveBtn = $('#jdSaveBtn'), remindBtn = $('#jdRemindBtn');
    function refresh() {
        const filled = $$('.jd-date', root).filter((i) => i.value).length;
        const sel = $$('.jd-sel:checked', root).length;
        $('#jdFillCount').textContent = '(' + filled + ')'; saveBtn.disabled = filled === 0;
        $('#jdSelCount').textContent = '(' + sel + ')'; remindBtn.disabled = sel === 0;
    }
    root.addEventListener('input', refresh); root.addEventListener('change', refresh);
    $('#jdAll').addEventListener('change', (e) => { $$('.jd-sel', root).forEach((c) => { c.checked = e.target.checked; }); refresh(); });

    saveBtn.addEventListener('click', async () => {
        const rows = $$('[data-jd-row]', root).map((tr) => ({ tr, id: parseInt(tr.dataset.jdRow, 10), date: $('.jd-date', tr).value })).filter((x) => x.date);
        if (!rows.length) { return; }
        saveBtn.disabled = true;
        const r = await post(root.dataset.save, { items: rows.map((x) => ({ employee_id: x.id, date: x.date })) });
        if (!r.ok) { notify((r.json && r.json.message) || 'Could not save the dates.', 'error'); refresh(); return; }
        const skipped = {}; (r.json.data.skipped || []).forEach((s) => { skipped[s.employee_id] = s.reason; });
        rows.forEach((x) => { const cell = $('.jd-result', x.tr); const why = skipped[x.id];
            cell.className = 'px-3 py-2 text-xs jd-result ' + (why ? 'text-red-600' : 'text-green-700'); cell.textContent = why ? (REASON[why] || why) : 'Saved'; });
        notify(r.json.message, 'success');
        if (r.json.data.set > 0) { setTimeout(() => location.reload(), 1200); } else { refresh(); }
    });

    remindBtn.addEventListener('click', async () => {
        const ids = $$('.jd-sel:checked', root).map((c) => parseInt(c.value, 10));
        if (!ids.length) { return; }
        if (!confirm('Send a reminder to ' + ids.length + ' employee(s)? Each person is reminded at most once every 7 days.')) { return; }
        remindBtn.disabled = true;
        const r = await post(root.dataset.remind, { employee_ids: ids });
        notify((r.json && r.json.message) || (r.ok ? 'Done.' : 'Could not send reminders.'), r.ok ? 'success' : 'error');
        if (r.ok) { const skipped = {}; (r.json.data.skipped || []).forEach((s) => { skipped[s.employee_id] = s.reason; });
            ids.forEach((id) => { const cell = $('[data-jd-row="' + id + '"] .jd-result', root); if (cell) { cell.className = 'px-3 py-2 text-xs jd-result ' + (skipped[id] ? 'text-gray-500' : 'text-green-700'); cell.textContent = skipped[id] ? (REASON[skipped[id]] || skipped[id]) : 'Reminder sent'; } }); }
        refresh();
    });

    // ── impor
    const modal = $('#jdModal'); let lastFocus = null;
    function openM() { lastFocus = document.activeElement; modal.classList.remove('hidden'); modal.setAttribute('aria-hidden', 'false'); $('#jdCsv').focus(); }
    function closeM() { modal.classList.add('hidden'); modal.setAttribute('aria-hidden', 'true'); if (lastFocus && lastFocus.focus) { lastFocus.focus(); } }
    $('#jdImportBtn').addEventListener('click', openM);
    $('#jdModalClose').addEventListener('click', closeM); $('#jdCancel').addEventListener('click', closeM);
    modal.addEventListener('click', (e) => { if (e.target === modal) { closeM(); } });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !modal.classList.contains('hidden')) { closeM(); } });
    $('#jdFile').addEventListener('change', (e) => { const f = e.target.files[0]; if (!f) { return; } const rd = new FileReader(); rd.onload = () => { $('#jdCsv').value = String(rd.result || ''); }; rd.readAsText(f); });

    const applyBtn = $('#jdApplyBtn');
    $('#jdPreviewBtn').addEventListener('click', async () => {
        const csv = $('#jdCsv').value.trim(); const box = $('#jdPreview'); applyBtn.classList.add('hidden');
        if (!csv) { box.innerHTML = '<p class="text-sm text-gray-500">Paste some rows first.</p>'; return; }
        box.innerHTML = '<p class="text-sm text-gray-400">Reading…</p>';
        const r = await post(root.dataset.preview, { csv });
        if (!r.ok) { box.innerHTML = '<p class="text-sm text-red-600">' + esc((r.json && r.json.message) || 'Could not read the list.') + '</p>'; return; }
        const s = r.json.data.summary, rows = r.json.data.rows;
        const tone = (st) => st === 'ok' ? 'bg-green-50 text-green-800' : (st === 'already_set' || st === 'duplicate' ? 'bg-gray-100 text-gray-600' : 'bg-red-50 text-red-700');
        box.innerHTML = `<div class="flex flex-wrap gap-2 mb-2 text-xs font-semibold">
            <span class="px-2 py-0.5 rounded-full bg-green-50 text-green-800">${s.ok} ready to apply</span>
            <span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">${s.skipped} skipped</span>
            <span class="px-2 py-0.5 rounded-full bg-red-50 text-red-700">${s.errors} with errors</span></div>
            <div class="max-h-[38vh] overflow-auto border border-gray-100 rounded-lg"><table class="w-full text-sm"><thead class="bg-gray-50 text-[11px] uppercase text-gray-500 sticky top-0"><tr>
            <th class="text-left px-3 py-2">Line</th><th class="text-left px-3 py-2">ECI</th><th class="text-left px-3 py-2">Employee</th><th class="text-left px-3 py-2">Date</th><th class="text-left px-3 py-2">Result</th></tr></thead><tbody class="divide-y divide-gray-100">
            ${rows.map((x) => `<tr><td class="px-3 py-1.5 text-gray-400">${x.line}</td><td class="px-3 py-1.5">${esc(x.eci)}</td><td class="px-3 py-1.5">${esc(x.name || '—')}</td>
                <td class="px-3 py-1.5">${esc(x.date || x.raw || '—')}</td>
                <td class="px-3 py-1.5"><span class="px-2 py-0.5 rounded-full text-[11px] font-semibold ${tone(x.status)}">${x.status === 'ok' ? 'Will be set' : esc(REASON[x.status] || x.status)}</span></td></tr>`).join('')}
            </tbody></table></div>`;
        if (s.ok > 0) { applyBtn.textContent = 'Apply ' + s.ok + ' date' + (s.ok === 1 ? '' : 's'); applyBtn.classList.remove('hidden'); }
    });
    applyBtn.addEventListener('click', async () => {
        applyBtn.disabled = true;
        const r = await post(root.dataset.import, { csv: $('#jdCsv').value });
        applyBtn.disabled = false;
        if (!r.ok) { notify((r.json && r.json.message) || 'Import failed.', 'error'); return; }
        notify(r.json.message, 'success'); closeM(); setTimeout(() => location.reload(), 1000);
    });

    refresh();
})();
</script>

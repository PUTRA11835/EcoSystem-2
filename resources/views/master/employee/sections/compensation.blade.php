{{-- Compensation (Payroll; HC-D21): penanda PAJAK & BPJS untuk payroll + ringkasan gaji (baca saja).
     Komponen gaji TIDAK diedit di sini — itu kotak "Salary Components" di tab Contract (tabel yang sama, terisi dari Offering
     Letter); payroll hanya membacanya. Data dari GET /api/employees/{id}/compensation. Izin dipilih server
     (employee.section.compensation.view|update). Warna mengikuti Accent colour (primary-gradient / partials.accent-brand-red). --}}
@include('partials.money-input')
@php
    $cpReadonly = isset($isReadonly) && $isReadonly;
@endphp
<div id="cpRoot" class="space-y-6 {{ $cpReadonly ? 'profile-readonly' : '' }}" data-employee-id="{{ (int) $employee->id }}">

    <div class="flex justify-between items-center pb-2 border-b border-gray-200">
        <div class="flex items-center gap-2">
            <h3 class="text-base font-semibold text-gray-900">Compensation</h3>
            <span id="cpStatusBadge" class="inline-block px-2.5 py-0.5 text-xs font-semibold rounded-full bg-amber-100 text-amber-700">Payroll not active yet</span>
            @if($cpReadonly)
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 text-xs font-medium"><i class="fas fa-lock text-[10px]"></i> View Only</span>
            @endif
        </div>
    </div>

    <p class="text-xs text-gray-500 -mt-3">
        Tax and BPJS markers used when payroll is calculated. Salary components (base salary, allowances) are managed in the
        <button type="button" class="font-semibold underline" onclick="switchSection('contract')">Contract tab → Salary Components</button>; payroll reads them from there.
    </p>

    {{-- Ringkasan hari ini --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="border border-gray-200 rounded-xl p-4"><p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Base salary</p><p id="cpSumBase" class="text-xl font-bold text-gray-900 mt-1">—</p></div>
        <div class="border border-gray-200 rounded-xl p-4"><p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Total earnings</p><p id="cpSumEarn" class="text-xl font-bold text-gray-900 mt-1">—</p></div>
        <div class="border border-gray-200 rounded-xl p-4"><p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Deductions</p><p id="cpSumDed" class="text-xl font-bold text-gray-900 mt-1">—</p></div>
        <div class="border border-gray-200 rounded-xl p-4"><p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">BPJS wage base</p><p id="cpSumBpjs" class="text-xl font-bold text-gray-900 mt-1">—</p></div>
    </div>

    {{-- Pajak & BPJS --}}
    <div class="border border-gray-200 rounded-xl p-4">
        <div class="flex items-center justify-between mb-3">
            <div>
                <h5 class="text-sm font-bold text-gray-900">Tax &amp; BPJS</h5>
                <p class="text-xs text-gray-500 mt-0.5">PTKP status decides the PPh 21 category; dependents follow the status automatically.</p>
            </div>
            <button type="button" id="cpTaxSave" onclick="cpSaveTax()" class="js-section-action inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all">Save</button>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">PTKP status</label>
                <select id="cpPtkp" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-red-800"></select>
                <p class="text-xs text-gray-500 mt-1" id="cpPtkpHint">&nbsp;</p>
                <p class="text-xs text-red-600 mt-1 hidden" data-cp-error="ptkp_code"></p>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">BPJS Health dependents</label>
                <input type="number" id="cpBpjsDep" min="0" max="5" step="1" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                <p class="text-xs text-red-600 mt-1 hidden" data-cp-error="bpjs_dependents_count"></p>
            </div>
            <label class="flex items-center gap-2 mt-6 text-sm text-gray-700"><input type="checkbox" id="cpBpjsHealth" class="rounded border-gray-300 text-red-800"> BPJS Health active in payroll</label>
            <label class="flex items-center gap-2 mt-6 text-sm text-gray-700"><input type="checkbox" id="cpBpjsEmp" class="rounded border-gray-300 text-red-800"> BPJS Employment active in payroll</label>
            <label class="flex items-center gap-2 md:col-span-4 text-sm text-gray-700"><input type="checkbox" id="cpActivated" class="rounded border-gray-300 text-red-800"> Include this employee when payroll is calculated</label>
            <p class="text-xs text-red-600 md:col-span-4 hidden" data-cp-error="payroll_activated"></p>
        </div>
    </div>

    {{-- Komponen gaji yang dibaca payroll (baca saja) --}}
    <div class="border border-gray-200 rounded-xl p-4">
        <h5 class="text-sm font-bold text-gray-900">Salary components payroll will use</h5>
        <p class="text-xs text-gray-500 mt-0.5 mb-3">Read-only view of the Salary Components box. A component counts for a payroll period when the period overlaps its dates.</p>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide border-b border-gray-200">
                        <th class="py-2 pr-3">Component</th><th class="py-2 pr-3">Type</th><th class="py-2 pr-3 text-right">Amount (IDR)</th><th class="py-2 pr-3">Effective</th><th class="py-2 pr-3">Counts for tax / BPJS</th><th class="py-2">Today</th>
                    </tr>
                </thead>
                <tbody id="cpRows" class="divide-y divide-gray-100"></tbody>
            </table>
            <p id="cpEmpty" class="hidden text-sm text-gray-500 py-6 text-center">No salary component yet. Add the Base Salary in the Contract tab → Salary Components.</p>
        </div>
    </div>
</div>

<script>
(function () {
    const root = document.getElementById('cpRoot');
    if (!root) { return; }
    const EMP = root.dataset.employeeId;
    const BASE = '/api/employees/' + EMP + '/compensation';
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const $ = (id) => document.getElementById(id);
    const notify = (m, t) => (typeof showNotification === 'function' ? showNotification(m, t) : alert(m));
    // 1.000.000,00 — titik ribuan, koma desimal, dua digit (sama dengan App\Support\Payroll\Money).
    const num = (n) => Number(n).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const idr = (n) => (n === null || n === undefined) ? '—' : 'Rp ' + num(n);
    let state = null;

    async function api(method, url, body) {
        const headers = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() };
        if (body) { headers['Content-Type'] = 'application/json'; }
        const res = await fetch(url, { method, credentials: 'same-origin', headers, body: body ? JSON.stringify(body) : undefined });
        let json = null;
        try { json = await res.json(); } catch (e) { /* bukan JSON */ }
        return { ok: res.ok, status: res.status, json };
    }

    function cell(text, cls) { const td = document.createElement('td'); td.className = cls || 'py-2 pr-3'; td.textContent = text; return td; }

    function clearErrors() { root.querySelectorAll('[data-cp-error]').forEach(e => { e.classList.add('hidden'); e.textContent = ''; }); }
    function showErrors(errors, fallback) {
        let shown = false;
        Object.entries(errors || {}).forEach(([k, v]) => {
            const el = root.querySelector('[data-cp-error="' + k + '"]');
            if (el) { el.textContent = v; el.classList.remove('hidden'); shown = true; }
        });
        if (!shown && fallback) { notify(fallback, 'error'); }
    }

    function render() {
        const d = state;
        $('cpStatusBadge').textContent = d.payroll_enabled ? 'Payroll active' : 'Payroll not active yet';
        $('cpStatusBadge').className = 'inline-block px-2.5 py-0.5 text-xs font-semibold rounded-full ' + (d.payroll_enabled ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700');
        $('cpSumBase').textContent = idr(d.summary.base);
        $('cpSumEarn').textContent = idr(d.summary.earnings);
        $('cpSumDed').textContent = idr(d.summary.deductions);
        $('cpSumBpjs').textContent = idr(d.summary.bpjs_wage);

        const sel = $('cpPtkp');
        sel.innerHTML = '';
        const blank = document.createElement('option'); blank.value = ''; blank.textContent = '— Select —'; sel.appendChild(blank);
        d.ptkp_options.forEach(c => { const o = document.createElement('option'); o.value = c; o.textContent = c; sel.appendChild(o); });
        sel.value = d.tax.ptkp_code || '';
        ptkpHint();
        $('cpBpjsDep').value = d.tax.bpjs_dependents_count ?? '';
        $('cpBpjsHealth').checked = !!d.tax.bpjs_health_active;
        $('cpBpjsEmp').checked = !!d.tax.bpjs_employment_active;
        $('cpActivated').checked = !!d.tax.payroll_activated;

        const tbody = $('cpRows');
        tbody.innerHTML = '';
        $('cpEmpty').classList.toggle('hidden', d.components.length > 0);
        d.components.forEach(c => {
            const tr = document.createElement('tr');
            tr.appendChild(cell(c.name, 'py-2 pr-3 font-medium text-gray-900'));
            tr.appendChild(cell(d.categories[c.category] || c.category));
            tr.appendChild(cell(idr(c.amount), 'py-2 pr-3 text-right tabular-nums'));
            tr.appendChild(cell(c.effective_from + (c.effective_to ? ' → ' + c.effective_to : ' → open')));
            tr.appendChild(cell([c.taxable ? 'Tax' : null, c.bpjs_base ? 'BPJS' : null].filter(Boolean).join(' · ') || '—', 'py-2 pr-3 text-xs text-gray-500'));
            const st = document.createElement('td'); st.className = 'py-2';
            const b = document.createElement('span');
            b.className = 'inline-block px-2 py-0.5 text-xs font-semibold rounded ' + (c.active_today ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600');
            b.textContent = c.active_today ? 'Active' : 'Not active'; st.appendChild(b); tr.appendChild(st);
            tbody.appendChild(tr);
        });
    }

    function ptkpHint() {
        const v = $('cpPtkp').value;
        const catMap = { 'TK/0': 'A', 'TK/1': 'A', 'K/0': 'A', 'TK/2': 'B', 'TK/3': 'B', 'K/1': 'B', 'K/2': 'B', 'K/3': 'C' };
        $('cpPtkpHint').textContent = v ? 'Dependents: ' + v.slice(-1) + ' · PPh 21 TER category ' + catMap[v] : ' ';
    }
    $('cpPtkp').addEventListener('change', ptkpHint);

    async function load() {
        const r = await api('GET', BASE);
        if (!r.ok || !r.json?.success) { notify('Could not load compensation data.', 'error'); return; }
        state = r.json.data; render();
    }

    window.cpSaveTax = async function () {
        clearErrors();
        const btn = $('cpTaxSave'); btn.disabled = true;
        const r = await api('POST', BASE + '/tax', {
            ptkp_code: $('cpPtkp').value,
            bpjs_dependents_count: $('cpBpjsDep').value,
            bpjs_health_active: $('cpBpjsHealth').checked,
            bpjs_employment_active: $('cpBpjsEmp').checked,
            payroll_activated: $('cpActivated').checked,
        });
        btn.disabled = false;
        if (r.ok && r.json?.success) { notify(r.json.message, 'success'); load(); return; }
        showErrors(r.json?.errors, r.json?.message || 'Could not save.');
    };

    load();
})();
</script>

{{-- Compensation (Payroll Fase 0; HC-D21 — data dikumpulkan sekarang, BELUM dipakai perhitungan sampai saklar payroll menyala).
     Komponen gaji = sumber kebenaran gaji pokok payroll. Data dari GET /api/employees/{id}/compensation.
     Izin dipilih server (employee.section.compensation.view|update). Warna tombol mengikuti Accent colour (kelas
     `primary-gradient`, sama seperti sidebar); fokus memakai ring red-800 yang sudah dipetakan ke Accent oleh layout. --}}
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
        Salary components and tax/BPJS markers are collected here ahead of time. They are not used by any payslip or report until the payroll module is switched on.
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

    {{-- Komponen gaji --}}
    <div class="border border-gray-200 rounded-xl p-4">
        <div class="flex items-center justify-between mb-3">
            <div>
                <h5 class="text-sm font-bold text-gray-900">Salary components</h5>
                <p class="text-xs text-gray-500 mt-0.5">Exactly one Base Salary can be active at any date. To raise it, end the current one and add the new one from the next date.</p>
            </div>
            <button type="button" onclick="cpOpenForm(null)" class="js-section-action inline-flex items-center px-3 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90">+ Add component</button>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide border-b border-gray-200">
                        <th class="py-2 pr-3">Component</th><th class="py-2 pr-3">Category</th><th class="py-2 pr-3 text-right">Amount (IDR)</th>
                        <th class="py-2 pr-3">Effective</th><th class="py-2 pr-3">Flags</th><th class="py-2 pr-3">Status</th><th class="py-2 text-right js-section-action">Action</th>
                    </tr>
                </thead>
                <tbody id="cpRows" class="divide-y divide-gray-100"></tbody>
            </table>
            <p id="cpEmpty" class="hidden text-sm text-gray-500 py-6 text-center">No salary component yet. Add the Base Salary first.</p>
        </div>
    </div>

    {{-- Modal tambah/ubah --}}
    <div id="cpModal" class="hidden fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-5">
            <h4 id="cpModalTitle" class="text-base font-bold text-gray-900 mb-4">Add component</h4>
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Name</label>
                    <input type="text" id="cpfName" maxlength="120" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                    <p class="text-xs text-red-600 mt-1 hidden" data-cp-error="name"></p>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Category</label>
                    <select id="cpfCategory" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-red-800"></select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Amount (IDR)</label>
                    <input type="text" inputmode="decimal" id="cpfAmount" placeholder="0,00" class="js-money w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm text-right tabular-nums focus:outline-none focus:ring-2 focus:ring-red-800">
                    <p class="text-xs text-red-600 mt-1 hidden" data-cp-error="amount"></p>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Effective from</label>
                    <input type="date" id="cpfFrom" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                    <p class="text-xs text-red-600 mt-1 hidden" data-cp-error="effective_from"></p>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Effective until <span class="font-normal text-gray-400">(optional)</span></label>
                    <input type="date" id="cpfTo" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                    <p class="text-xs text-red-600 mt-1 hidden" data-cp-error="effective_to"></p>
                </div>
                <label class="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" id="cpfTaxable" class="rounded border-gray-300 text-red-800"> Taxable (PPh 21)</label>
                <label class="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" id="cpfBpjs" class="rounded border-gray-300 text-red-800"> Part of BPJS wage base</label>
                <label class="flex items-center gap-2 text-sm text-gray-700 col-span-2"><input type="checkbox" id="cpfActive" class="rounded border-gray-300 text-red-800"> Active</label>
            </div>
            <p id="cpfError" class="hidden text-sm text-red-600 mt-3"></p>
            <div class="flex justify-end gap-2 mt-5">
                <button type="button" onclick="cpCloseForm()" class="px-4 py-2 text-sm font-semibold rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="button" id="cpfSave" onclick="cpSaveComponent()" class="px-4 py-2 text-sm font-semibold rounded-lg primary-gradient text-white hover:opacity-90">Save</button>
            </div>
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
    let editingId = null;

    async function api(method, url, body) {
        const headers = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() };
        if (body) { headers['Content-Type'] = 'application/json'; }
        const res = await fetch(url, { method, credentials: 'same-origin', headers, body: body ? JSON.stringify(body) : undefined });
        let json = null;
        try { json = await res.json(); } catch (e) { /* bukan JSON */ }
        return { ok: res.ok, status: res.status, json };
    }

    function cell(text, cls) { const td = document.createElement('td'); td.className = cls || 'py-2 pr-3'; td.textContent = text; return td; }

    function clearErrors() {
        root.querySelectorAll('[data-cp-error]').forEach(e => { e.classList.add('hidden'); e.textContent = ''; });
        $('cpfError').classList.add('hidden');
    }
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

        const cat = $('cpfCategory');
        cat.innerHTML = '';
        Object.entries(d.categories).forEach(([k, v]) => { const o = document.createElement('option'); o.value = k; o.textContent = v; cat.appendChild(o); });

        const tbody = $('cpRows');
        tbody.innerHTML = '';
        $('cpEmpty').classList.toggle('hidden', d.components.length > 0);
        d.components.forEach(c => {
            const tr = document.createElement('tr');
            tr.appendChild(cell(c.name + (c.is_mandatory ? ' (required)' : ''), 'py-2 pr-3 font-medium text-gray-900'));
            tr.appendChild(cell(d.categories[c.category] || c.category));
            tr.appendChild(cell(idr(c.amount), 'py-2 pr-3 text-right tabular-nums'));
            tr.appendChild(cell(c.effective_from + (c.effective_to ? ' → ' + c.effective_to : ' → open')));
            tr.appendChild(cell([c.taxable ? 'Taxable' : null, c.bpjs_base ? 'BPJS' : null].filter(Boolean).join(' · ') || '—', 'py-2 pr-3 text-xs text-gray-500'));
            const st = document.createElement('td'); st.className = 'py-2 pr-3';
            const b = document.createElement('span');
            b.className = 'inline-block px-2 py-0.5 text-xs font-semibold rounded ' + (c.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600');
            b.textContent = c.is_active ? 'Active' : 'Inactive'; st.appendChild(b); tr.appendChild(st);
            const act = document.createElement('td'); act.className = 'py-2 text-right whitespace-nowrap js-section-action';
            const edit = document.createElement('button'); edit.type = 'button'; edit.className = 'px-2 py-1 text-xs font-semibold rounded border border-gray-300 text-gray-700 hover:bg-gray-50'; edit.textContent = 'Edit';
            edit.addEventListener('click', () => window.cpOpenForm(c.id)); act.appendChild(edit);
            if (!c.is_mandatory) {
                const del = document.createElement('button'); del.type = 'button'; del.className = 'ml-1 px-2 py-1 text-xs font-semibold rounded border border-red-300 text-red-700 hover:bg-red-50'; del.textContent = 'Delete';
                del.addEventListener('click', () => cpDelete(c)); act.appendChild(del);
            }
            tr.appendChild(act);
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

    window.cpOpenForm = function (id) {
        clearErrors();
        editingId = id;
        const c = id ? state.components.find(x => x.id === id) : null;
        $('cpModalTitle').textContent = c ? 'Edit component' : 'Add component';
        $('cpfName').value = c ? c.name : '';
        $('cpfCategory').value = c ? c.category : (state.components.some(x => x.category === 'base' && x.is_active) ? 'fixed_allowance' : 'base');
        $('cpfCategory').disabled = !!(c && c.is_mandatory);
        $('cpfAmount').value = c ? num(c.amount) : '';
        $('cpfFrom').value = c ? c.effective_from : new Date().toISOString().slice(0, 10);
        $('cpfTo').value = c && c.effective_to ? c.effective_to : '';
        $('cpfTaxable').checked = c ? c.taxable : true;
        $('cpfBpjs').checked = c ? c.bpjs_base : true;
        $('cpfActive').checked = c ? c.is_active : true;
        if (!c) { $('cpfName').value = $('cpfCategory').value === 'base' ? 'Basic Salary' : ''; }
        $('cpModal').classList.remove('hidden');
    };
    window.cpCloseForm = function () { $('cpModal').classList.add('hidden'); };

    window.cpSaveComponent = async function () {
        clearErrors();
        const btn = $('cpfSave'); btn.disabled = true;
        const payload = {
            name: $('cpfName').value, category: $('cpfCategory').value, amount: $('cpfAmount').value,
            effective_from: $('cpfFrom').value, effective_to: $('cpfTo').value,
            taxable: $('cpfTaxable').checked, bpjs_base: $('cpfBpjs').checked, is_active: $('cpfActive').checked,
        };
        const r = await api('POST', BASE + '/components' + (editingId ? '/' + editingId : ''), payload);
        btn.disabled = false;
        if (r.ok && r.json?.success) { cpCloseForm(); notify(r.json.message, 'success'); load(); return; }
        showErrors(r.json?.errors, null);
        if (r.json?.errors?._ || !r.json?.errors) { const e = $('cpfError'); e.textContent = r.json?.message || 'Could not save.'; e.classList.remove('hidden'); }
    };

    async function cpDelete(c) {
        if (!confirm('Delete "' + c.name + '"? Use an end date instead if it was ever used for payroll.')) { return; }
        const r = await api('POST', BASE + '/components/' + c.id + '/delete');
        if (r.ok && r.json?.success) { notify(r.json.message, 'success'); load(); return; }
        notify(r.json?.message || 'Could not delete.', 'error');
    }

    load();
})();
</script>

{{--
    Salary Components — kotak yang dapat dibuka di dalam tab Contract.

    Sumber utama payroll, BPJS dan referensi kontrak. Baris pertama datang dari offering letter yang diterima
    (tanggal berlaku = join date); selanjutnya HR menyesuaikannya di sini saat ada kontrak baru atau kenaikan gaji.
    Komponen yang bisa dipilih = Compensation Components di Offering Letter → Settings.

    Dikirim dari show.blade.php: $employeeId, $canCreate, $canEdit, $canDelete.
--}}
<div id="salaryBox" class="border border-gray-200 rounded-xl bg-white overflow-hidden">
    <button type="button" id="salaryToggle" onclick="toggleSalaryBox()" aria-expanded="false" aria-controls="salaryBody"
        class="w-full flex items-center justify-between gap-4 px-5 py-4 text-left hover:bg-gray-50 transition-colors">
        <span class="flex items-center gap-3 min-w-0">
            <span class="w-9 h-9 rounded-lg bg-red-50 flex items-center justify-center flex-shrink-0"><i class="fas fa-wallet text-red-800"></i></span>
            <span class="min-w-0">
                <span class="block text-base font-semibold text-gray-900">Salary Components</span>
                <span class="block text-xs text-gray-500">Primary source for payroll, BPJS and contract references</span>
            </span>
        </span>
        <span class="flex items-center gap-4 flex-shrink-0">
            <span class="text-right hidden sm:block">
                <span class="block text-[11px] text-gray-400 uppercase tracking-wide">Total Compensation</span>
                <span id="salaryHeaderTotal" class="block text-sm font-bold text-gray-900">–</span>
            </span>
            <i id="salaryChevron" class="fas fa-chevron-down text-xs text-gray-400 transition-transform"></i>
        </span>
    </button>

    <div id="salaryBody" class="hidden border-t border-gray-200 px-5 py-5 space-y-5">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <h4 class="text-base font-bold text-gray-900">Active Salary Components</h4>
            <p class="text-xs text-gray-500">Main source for payroll, BPJS and contract references</p>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            @foreach(['base' => 'Base Salary', 'fixed' => 'Fixed Allowance', 'variable' => 'Other Income', 'total' => 'Total Compensation'] as $k => $label)
                <div class="border border-gray-200 rounded-lg px-4 py-3">
                    <span class="block text-xs text-gray-500">{{ $label }}</span>
                    <span id="salarySum-{{ $k }}" class="block text-sm font-bold {{ $k === 'total' ? 'text-blue-600' : 'text-gray-900' }}">Rp 0</span>
                </div>
            @endforeach
        </div>

        @if($canCreate || $canEdit)
            <form id="salaryForm" class="hidden border border-gray-200 rounded-lg bg-gray-50 p-4" onsubmit="saveSalaryComponent(event)">
                <input type="hidden" id="salaryEditId">
                <p id="salaryFormTitle" class="text-sm font-semibold text-gray-800 mb-3">Add component</p>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                    <div class="md:col-span-2">
                        <label for="salaryComponent" class="block text-xs font-semibold text-gray-600 mb-1">Component <span class="text-red-600">*</span></label>
                        <select id="salaryComponent" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-red-800"></select>
                        <p class="text-[11px] text-gray-400 mt-1">The list comes from Offering Letter → Settings → Compensation Components.</p>
                    </div>
                    <div>
                        <label for="salaryAmount" class="block text-xs font-semibold text-gray-600 mb-1">Amount (Rp) <span class="text-red-600">*</span></label>
                        <input type="text" id="salaryAmount" inputmode="numeric" required placeholder="0" oninput="this.value = salaryMoney(this.value)"
                            class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm text-right bg-white focus:outline-none focus:ring-2 focus:ring-red-800">
                    </div>
                    <div>
                        <label for="salaryFrom" class="block text-xs font-semibold text-gray-600 mb-1">Effective From <span class="text-red-600">*</span></label>
                        <input type="date" id="salaryFrom" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-red-800">
                    </div>
                    <div class="md:col-span-4">
                        <label for="salaryNotes" class="block text-xs font-semibold text-gray-600 mb-1">Notes <span class="font-normal text-gray-400">(optional)</span></label>
                        <input type="text" id="salaryNotes" maxlength="255" placeholder="e.g. Pay raise per new contract"
                            class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-red-800">
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-3">
                    <button type="button" onclick="closeSalaryForm()" class="px-4 py-2 bg-white text-gray-700 border border-gray-300 text-sm font-semibold rounded-lg hover:bg-gray-50">Cancel</button>
                    <button type="submit" id="salarySaveBtn" class="px-4 py-2 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900">Save</button>
                </div>
            </form>
        @endif

        <div class="border border-gray-200 rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50 text-gray-500 text-[11px] font-bold uppercase tracking-wider border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-3 w-14">No</th>
                            <th class="px-4 py-3">Component</th>
                            <th class="px-4 py-3">Category</th>
                            <th class="px-4 py-3">Effective From</th>
                            <th class="px-4 py-3 text-right">Amount</th>
                            @if($canEdit || $canDelete)<th class="px-4 py-3 text-right w-24"><span class="sr-only">Actions</span></th>@endif
                        </tr>
                    </thead>
                    <tbody id="salaryRows" class="divide-y divide-gray-100"></tbody>
                </table>
            </div>
        </div>

        @if($canCreate)
            <div>
                <button type="button" onclick="openSalaryForm()" class="inline-flex items-center gap-1.5 px-4 py-2 bg-white text-gray-700 border border-gray-300 text-sm font-semibold rounded-lg hover:bg-gray-50">
                    <i class="fas fa-plus text-xs"></i> Add Component
                </button>
            </div>
        @endif
    </div>
</div>

<script>
(function () {
    const empId = {{ (int) $employeeId }};
    const API = `/api/employees/${empId}/salary-components`;
    const canEdit = @json((bool) $canEdit), canDelete = @json((bool) $canDelete);
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const $ = id => document.getElementById(id);
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const rp = n => 'Rp ' + Math.round(Number(n) || 0).toLocaleString('id-ID');
    const day = d => d ? new Date(d + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '–';
    const notify = (m, t) => window.showNotification ? window.showNotification(m, t) : alert(m);

    let rows = [], components = [], loaded = false;

    window.salaryMoney = v => { const d = String(v ?? '').replace(/\D/g, ''); return d === '' ? '' : Number(d).toLocaleString('id-ID'); };

    async function call(method, url, body) {
        const res = await fetch(url, {
            method, credentials: 'same-origin', body: body ? JSON.stringify(body) : undefined,
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() },
        });
        const json = await res.json().catch(() => ({}));
        if (!res.ok || json.success === false) {
            const first = Object.values(json.errors || {}).flat()[0];
            throw new Error(first || json.message || 'The request failed.');
        }
        return json;
    }

    function renderSummary(s) {
        ['base', 'fixed', 'variable', 'total'].forEach(k => { $('salarySum-' + k).textContent = rp(s[k]); });
        $('salaryHeaderTotal').textContent = rp(s.total);
    }

    function renderRows() {
        const body = $('salaryRows');
        const cols = 5 + (canEdit || canDelete ? 1 : 0);
        if (!rows.length) {
            body.innerHTML = `<tr><td colspan="${cols}" class="px-4 py-8 text-center text-gray-400">No salary components yet. They are filled in when an offering letter is accepted, or added here.</td></tr>`;
            return;
        }
        body.innerHTML = rows.map((r, i) => `<tr class="hover:bg-gray-50">
            <td class="px-4 py-3 text-blue-600">${i + 1}</td>
            <td class="px-4 py-3"><span class="font-semibold text-gray-900">${esc(r.name)}</span>
                ${r.notes ? `<span class="block text-xs text-gray-500">${esc(r.notes)}</span>` : (r.source === 'offer' ? '<span class="block text-xs text-gray-400">From the offering letter</span>' : '')}</td>
            <td class="px-4 py-3 text-gray-700">${esc(r.category)}</td>
            <td class="px-4 py-3 text-gray-700 whitespace-nowrap">${esc(day(r.effective_from))}</td>
            <td class="px-4 py-3 text-right font-semibold text-gray-900 whitespace-nowrap">${rp(r.amount)}</td>
            ${canEdit || canDelete ? `<td class="px-4 py-3 text-right whitespace-nowrap">
                ${canEdit ? `<button type="button" onclick="editSalaryComponent(${r.id})" class="text-gray-400 hover:text-gray-700 mr-3" title="Edit" aria-label="Edit ${esc(r.name)}"><i class="fas fa-pen"></i></button>` : ''}
                ${canDelete ? `<button type="button" onclick="deleteSalaryComponent(${r.id})" class="text-red-400 hover:text-red-600" title="Remove" aria-label="Remove ${esc(r.name)}"><i class="fas fa-trash-can"></i></button>` : ''}
            </td>` : ''}</tr>`).join('');
    }

    async function load() {
        try {
            const json = await call('GET', API);
            rows = json.data; components = json.components; loaded = true;
            renderSummary(json.summary);
            renderRows();
        } catch (e) {
            $('salaryRows').innerHTML = `<tr><td colspan="6" class="px-4 py-8 text-center text-red-500">${esc(e.message)}</td></tr>`;
        }
    }

    window.toggleSalaryBox = function () {
        const open = $('salaryBody').classList.toggle('hidden') === false;
        $('salaryToggle').setAttribute('aria-expanded', String(open));
        $('salaryChevron').classList.toggle('rotate-180', open);
    };

    function fillComponentOptions(selectedId) {
        const taken = new Set(rows.map(r => r.component_id));
        const options = components.filter(c => !taken.has(c.id) || c.id === selectedId);
        $('salaryComponent').innerHTML = options.length
            ? options.map(c => `<option value="${c.id}" ${c.id === selectedId ? 'selected' : ''}>${esc(c.name)} — ${esc(c.category)}</option>`).join('')
            : '<option value="">Every component is already on this employee</option>';
    }

    window.openSalaryForm = function (row) {
        const form = $('salaryForm'); if (!form) return;
        $('salaryEditId').value = row?.id || '';
        $('salaryFormTitle').textContent = row ? 'Edit ' + row.name : 'Add component';
        fillComponentOptions(row?.component_id);
        $('salaryAmount').value = row ? salaryMoney(row.amount) : '';
        $('salaryFrom').value = row?.effective_from || new Date().toISOString().slice(0, 10);
        $('salaryNotes').value = row?.notes || '';
        form.classList.remove('hidden');
        $('salaryAmount').focus();
    };
    window.closeSalaryForm = function () { $('salaryForm')?.classList.add('hidden'); };
    window.editSalaryComponent = id => openSalaryForm(rows.find(r => r.id === id));

    window.saveSalaryComponent = async function (event) {
        event.preventDefault();
        const id = $('salaryEditId').value;
        const btn = $('salarySaveBtn'); btn.disabled = true;
        try {
            const json = await call(id ? 'PUT' : 'POST', id ? `${API}/${id}` : API, {
                component_id: Number($('salaryComponent').value),
                amount: Number(String($('salaryAmount').value).replace(/\D/g, '')),
                effective_from: $('salaryFrom').value,
                notes: $('salaryNotes').value || null,
            });
            notify(json.message, 'success');
            closeSalaryForm();
            await load();
        } catch (e) { notify(e.message, 'error'); } finally { btn.disabled = false; }
    };

    window.deleteSalaryComponent = async function (id) {
        const row = rows.find(r => r.id === id); if (!row) return;
        const ask = `Remove ${row.name} (${rp(row.amount)}) from this employee's salary components?`;
        const ok = window.showConfirm ? await window.showConfirm(ask, 'Remove Component', 'danger', { okText: 'Remove', cancelText: 'Cancel' }) : confirm(ask);
        if (!ok) return;
        try { notify((await call('DELETE', `${API}/${id}`)).message, 'success'); await load(); }
        catch (e) { notify(e.message, 'error'); }
    };

    document.addEventListener('DOMContentLoaded', load);
})();
</script>

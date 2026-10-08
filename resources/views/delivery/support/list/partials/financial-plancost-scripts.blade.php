{{-- ══════════════════════════════════════════════════════════════ --}}
{{-- FINANCIAL + PLAN COST + TOP — SCRIPTS (mirror Delivery Project) --}}
{{-- ══════════════════════════════════════════════════════════════ --}}
{{-- Flatpickr + HolidayCalendar (header bulan statis, weekend/libur disabled) --}}
@include('delivery.partials.holiday-flatpickr')
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

{{-- ── Financial (Sales Data) auto-calc ─────────────────────────── --}}
<script>
(function () {
    function fmtRp(n) {
        const neg = n < 0;
        const abs = Math.abs(Math.round(n));
        return (neg ? '-' : '') + abs.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }
    function parseNum(str) {
        if (str === '' || str === null || str === undefined) return 0;
        const s = String(str).trim();
        if (s.includes(',')) {
            return parseFloat(s.replace(/\./g, '').replace(',', '.')) || 0;
        }
        const dotCount = (s.match(/\./g) || []).length;
        if (dotCount > 1) {
            return parseFloat(s.replace(/\./g, '')) || 0;
        }
        return parseFloat(s) || 0;
    }
    function sfinRecalc() {
        const rev  = parseNum(document.getElementById('sfin_rev_val')?.value);
        const pc   = parseNum(document.getElementById('sfin_pc_val')?.value);
        const gp   = rev - pc;
        const pct  = (rev !== 0) ? (gp / rev) * 100 : 0;

        const gpVal   = document.getElementById('sfin_gp_val');
        const pctVal  = document.getElementById('sfin_pct_val');
        const gpDisp  = document.getElementById('sfin_gp_disp');
        const pctDisp = document.getElementById('sfin_pct_disp');

        if (gpVal)   gpVal.value   = gp;
        if (pctVal)  pctVal.value  = pct.toFixed(2);
        if (gpDisp)  gpDisp.value  = fmtRp(gp);
        if (pctDisp) pctDisp.value = pct.toFixed(2).replace('.', ',');

        sfinRecalcActual();
    }
    function sfinRecalcActual() {
        const rev = parseNum(document.getElementById('sfin_rev_val')?.value);
        const ac  = parseNum(document.getElementById('sfin_ac_val')?.value);
        const agp = rev - ac;
        const apct = (rev !== 0) ? (agp / rev) * 100 : 0;

        const agpVal   = document.getElementById('sfin_agp_val');
        const apctVal  = document.getElementById('sfin_apct_val');
        const acDisp   = document.getElementById('sfin_ac_disp');
        const agpDisp  = document.getElementById('sfin_agp_disp');
        const apctDisp = document.getElementById('sfin_apct_disp');

        if (agpVal)   agpVal.value   = agp;
        if (apctVal)  apctVal.value  = apct.toFixed(2);
        if (acDisp)   acDisp.value   = fmtRp(ac);
        if (agpDisp)  agpDisp.value  = fmtRp(agp);
        if (apctDisp) apctDisp.value = apct.toFixed(2).replace('.', ',');
    }
    // Dipanggil oleh Plan Cost saat "Total Actual" berubah.
    window.sfinSetActualCost = function (actualCost) {
        const acVal = document.getElementById('sfin_ac_val');
        if (!acVal) return;
        acVal.value = actualCost ?? 0;
        sfinRecalcActual();
    };
    function bindInput(dispId, valId) {
        const disp = document.getElementById(dispId);
        const val  = document.getElementById(valId);
        if (!disp || !val) return;
        disp.addEventListener('input', function () {
            const raw  = this.value.replace(/[^0-9]/g, '');
            this.value = raw ? raw.replace(/\B(?=(\d{3})+(?!\d))/g, '.') : '';
            val.value  = raw || '';
            sfinRecalc();
        });
    }
    document.addEventListener('DOMContentLoaded', function () {
        const revVal  = document.getElementById('sfin_rev_val');
        const pcVal   = document.getElementById('sfin_pc_val');
        const revDisp = document.getElementById('sfin_rev_disp');
        const pcDisp  = document.getElementById('sfin_pc_disp');
        if (!revVal) return;

        if (revVal.value)  revDisp.value = fmtRp(parseFloat(revVal.value) || 0);
        if (pcVal.value)   pcDisp.value  = fmtRp(parseFloat(pcVal.value)  || 0);
        sfinRecalc();

        bindInput('sfin_rev_disp', 'sfin_rev_val');
        bindInput('sfin_pc_disp',  'sfin_pc_val');
    });
})();
</script>

{{-- ── PLAN COST module ─────────────────────────────────────────── --}}
<script>
(function () {
    'use strict';

    const SUPPORT_ID = {{ $support->id }};
    const BASE_URL   = `/delivery/support/${SUPPORT_ID}/costs`;
    function getCsrf() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    function fmt(val) {
        if (val === null || val === undefined) return '—';
        return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0 }).format(val);
    }
    function fmtRp(val) {
        if (val === null || val === undefined) return '—';
        return 'Rp ' + new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0 }).format(val);
    }

    function parseNum(str) {
        if (!str && str !== 0) return null;
        const cleaned = String(str).replace(/\./g, '').replace(',', '.');
        const n = parseFloat(cleaned);
        return isNaN(n) ? null : n;
    }

    function formatCurrencyInput(input) {
        input.addEventListener('input', function () {
            const raw   = this.value.replace(/\D/g, '');
            this.value  = raw ? new Intl.NumberFormat('id-ID').format(Number(raw)) : '';
            refreshPreview();
        });
    }

    let _costs   = [];
    let _editId  = null;

    let _adCostId      = null;
    let _adTotal       = 0;
    let _adDirty       = false;
    let _adDeleteId    = null;
    let _adDeleteRowEl = null;
    let _adEditId      = null;
    let _adEditRowEl   = null;
    let _adEditRemoveDoc = false;

    let _currentActual = 0;

    async function init() {
        await ensureInit();
        await load();
    }

    async function ensureInit() {
        try {
            await axios.post(`${BASE_URL}/init`);
        } catch (e) { /* already initialised — ignore */ }
    }

    async function load() {
        try {
            const res = await axios.get(BASE_URL);
            _costs = res.data.costs ?? [];
            renderTable(_costs);
            renderSummaryCards(res.data.summary ?? {});
        } catch (e) {
            console.error('SupportPlanCost: load error', e);
            document.getElementById('supPlanCostTableBody').innerHTML =
                `<tr><td colspan="8" class="text-center py-8 text-red-500 text-sm">Failed to load data. Please refresh the page.</td></tr>`;
        }
    }

    function renderTable(costs) {
        const tbody = document.getElementById('supPlanCostTableBody');
        if (!costs.length) {
            tbody.innerHTML = `<tr><td colspan="8" class="text-center py-10 text-gray-400 text-sm">No cost items yet. Click "Add Cost Item" to get started.</td></tr>`;
            return;
        }
        let html = '';
        costs.forEach(c => {
            html += rowHtml(c, false);
            if (c.children && c.children.length) {
                c.children.forEach(ch => { html += rowHtml(ch, true); });
            }
            html += addChildRowHtml(c);
        });
        tbody.innerHTML = html;
    }

    function rowHtml(item, isChild) {
        const isParent = item.has_children;
        const budget  = item.display_budget;
        const release = item.display_release;
        const actual  = item.display_actual ?? 0;
        const avBudg  = item.avail_budget;
        const avRel   = item.avail_release;

        function availColor(val) {
            if (val === null) return 'text-gray-400';
            if (val < 0)      return 'text-red-600 font-semibold';
            if (val === 0)    return 'text-gray-500';
            return 'text-green-700';
        }

        const rowBg   = isChild  ? 'bg-white hover:bg-gray-50'
                      : isParent ? 'bg-gray-100 hover:bg-gray-200'
                      : 'bg-blue-50 hover:bg-blue-100';
        const nameClass = isParent ? 'font-bold text-gray-800 uppercase tracking-wide'
                        : isChild  ? 'pl-6 text-gray-700'
                        : 'font-semibold text-gray-800';
        const codeLabel = isChild
            ? `<span class="text-gray-400 text-xs">${item.code ?? ''}</span>`
            : `<span class="font-bold text-gray-600">${item.code ?? ''}</span>`;

        const editBtn = `
            <button type="button" title="Edit"
                    onclick="SupportPlanCost.openEditModal(${item.id})"
                    class="p-1.5 text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
            </button>`;
        const deleteBtn = `
            <button type="button" title="Delete"
                    onclick="SupportPlanCost.openDeleteModal(${item.id}, '${(item.name ?? '').replace(/'/g, "\\'")}')"
                    class="p-1.5 text-red-500 hover:text-red-700 hover:bg-red-50 rounded transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
            </button>`;

        const actualCell = !isParent
            ? `<td class="px-4 py-3 text-right whitespace-nowrap bg-orange-50/40 cursor-pointer hover:bg-orange-100 transition-colors"
                   onclick="SupportPlanCost.openActualDetailModal(${item.id}, '${(item.name ?? '').replace(/'/g, "\\'")}')"
                   title="Click to view / add expense details">
                   <span class="text-orange-700 font-mono text-xs">${fmtRp(actual)}</span>
               </td>`
            : `<td class="px-4 py-3 text-right whitespace-nowrap text-orange-700 font-mono text-xs bg-orange-50/40">${fmtRp(actual)}</td>`;

        return `
        <tr class="${rowBg} transition-colors">
            <td class="px-4 py-3 whitespace-nowrap">${codeLabel}</td>
            <td class="px-4 py-3 ${nameClass}">${item.name ?? ''}</td>
            <td class="px-4 py-3 text-right whitespace-nowrap text-gray-800 font-mono text-xs">${fmtRp(budget)}</td>
            <td class="px-4 py-3 text-right whitespace-nowrap text-blue-700 font-mono text-xs bg-blue-50/40">${fmtRp(release)}</td>
            ${actualCell}
            <td class="px-4 py-3 text-right whitespace-nowrap font-mono text-xs ${availColor(avBudg)} bg-green-50/40">${fmtRp(avBudg)}</td>
            <td class="px-4 py-3 text-right whitespace-nowrap font-mono text-xs ${availColor(avRel)}  bg-teal-50/40">${fmtRp(avRel)}</td>
            <td class="px-4 py-3 text-center whitespace-nowrap">
                <div class="inline-flex items-center gap-0.5">
                    ${editBtn}
                    ${deleteBtn}
                </div>
            </td>
        </tr>`;
    }

    function addChildRowHtml(parent) {
        return `
        <tr class="bg-white border-t border-dashed border-gray-200">
            <td colspan="8" class="px-4 py-2">
                <button type="button"
                        onclick="SupportPlanCost.openAddChildModal(${parent.id}, '${parent.cost_type}')"
                        class="inline-flex items-center gap-1 text-xs text-blue-600 hover:text-blue-800 hover:bg-blue-50 px-2 py-1 rounded transition">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add sub-item to ${parent.name}
                </button>
            </td>
        </tr>`;
    }

    function renderSummaryCards(s) {
        const wrap = document.getElementById('supPlanCostSummaryCards');
        if (!wrap) return;

        const icon = (path) =>
            `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">`
            + `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${path}"/></svg>`;

        const ICONS = {
            budget:  'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2',
            release: 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12',
            actual:  'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z',
            check:   'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
            chart:   'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
        };

        function card(label, value, colorClass, iconPath, iconTint) {
            return `
            <div class="bg-white rounded-lg border border-gray-200 p-4 flex flex-col gap-1 shadow-sm">
                <div class="flex items-center gap-2 mb-1">
                    <span class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0 ${iconTint}">${icon(iconPath)}</span>
                    <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">${label}</span>
                </div>
                <span class="text-base font-bold ${colorClass} font-mono">Rp ${fmt(value ?? 0)}</span>
            </div>`;
        }

        wrap.innerHTML =
            card('Total Budget',   s.total_budget,   'text-gray-800',   ICONS.budget,  'bg-gray-100 text-gray-600') +
            card('Total Release',  s.total_release,  'text-blue-700',   ICONS.release, 'bg-blue-100 text-blue-600') +
            card('Total Actual',   s.total_actual,   'text-orange-600', ICONS.actual,  'bg-orange-100 text-orange-600') +
            card('Avail. Budget',  s.total_avail_budget,  s.total_avail_budget  < 0 ? 'text-red-600' : 'text-green-700', ICONS.check, 'bg-green-100 text-green-600') +
            card('Avail. Release', s.total_avail_release, s.total_avail_release < 0 ? 'text-red-600' : 'text-teal-700',  ICONS.chart, 'bg-teal-100 text-teal-600');

        if (window.sfinSetActualCost) window.sfinSetActualCost(s.total_actual ?? 0);
    }

    function showModal() { document.getElementById('costModal').classList.remove('hidden'); }
    function _close()    { document.getElementById('costModal').classList.add('hidden'); }

    function resetForm() {
        document.getElementById('costCodeInput').value     = '';
        document.getElementById('costNameInput').value     = '';
        document.getElementById('costBudgetInput').value   = '';
        document.getElementById('costReleaseInput').value  = '';
        _currentActual = 0;
        document.getElementById('costModalId').value       = '';
        document.getElementById('costModalParentId').value = '';
        document.getElementById('costModalMode').value     = 'create';
        document.getElementById('costTypeIndirect').checked = false;
        document.getElementById('costTypeDirect').checked   = true;
        document.getElementById('costTypeRow').style.display      = '';
        document.getElementById('costAmountsSection').style.display  = '';
        document.getElementById('costAggregateNotice').style.display = 'none';
        refreshPreview();
    }

    function refreshPreview() {
        const b  = parseNum(document.getElementById('costBudgetInput').value.replace(/\./g,''));
        const r  = parseNum(document.getElementById('costReleaseInput').value.replace(/\./g,''));
        const a  = _currentActual ?? 0;
        const ab = (b !== null || r !== null) ? (b ?? 0) - (r ?? 0) : null;
        const ar = (r !== null || a > 0) ? (r ?? 0) - a : null;

        const abEl = document.getElementById('previewAvailBudget');
        const arEl = document.getElementById('previewAvailRelease');
        abEl.textContent = ab !== null ? `Rp ${fmt(ab)}` : '—';
        arEl.textContent = ar !== null ? `Rp ${fmt(ar)}` : '—';
        abEl.className = `font-semibold ml-1 ${ab !== null && ab < 0 ? 'text-red-600' : 'text-green-700'}`;
        arEl.className = `font-semibold ml-1 ${ar !== null && ar < 0 ? 'text-red-600' : 'text-teal-700'}`;
    }

    function findItem(id, list) {
        for (const c of list) {
            if (c.id === id) return c;
            if (c.children) {
                const found = findItem(id, c.children);
                if (found) return found;
            }
        }
        return null;
    }

    const SupportPlanCost = {
        closeModal() { _close(); },
        closeDeleteModal() { document.getElementById('costDeleteModal').classList.add('hidden'); },

        onTypeChange(type) {
            const parentItem = _costs.find(c => c.cost_type === type && c.parent_id === null);
            const parentName = parentItem ? parentItem.name : (type === 'indirect' ? 'Indirect Cost' : 'Direct Cost');
            document.getElementById('costModalTitle').textContent = `Add Item to ${parentName}`;
        },

        openAddParentModal() {
            resetForm();
            const defaultParent = _costs.find(c => c.cost_type === 'direct' && c.parent_id === null);
            const defaultName   = defaultParent ? defaultParent.name : 'Direct Cost';
            document.getElementById('costModalTitle').textContent = `Add Item to ${defaultName}`;
            document.getElementById('costModalMode').value        = 'create';
            document.getElementById('costModalParentId').value    = '';
            document.getElementById('costTypeRow').style.display  = '';
            showModal();
        },

        openAddChildModal(parentId, parentType) {
            resetForm();
            document.getElementById('costModalTitle').textContent   = 'Add Cost Sub-item';
            document.getElementById('costModalMode').value          = 'create';
            document.getElementById('costModalParentId').value      = parentId;
            document.getElementById('costTypeRow').style.display    = 'none';
            document.getElementById('costTypeIndirect').checked = (parentType === 'indirect');
            document.getElementById('costTypeDirect').checked   = (parentType === 'direct');
            showModal();
        },

        openEditModal(id) {
            const item = findItem(id, _costs);
            if (!item) return;

            resetForm();
            document.getElementById('costModalTitle').textContent     = 'Edit Cost Item';
            document.getElementById('costModalMode').value            = 'edit';
            document.getElementById('costModalId').value              = id;
            document.getElementById('costModalParentId').value        = item.parent_id ?? '';

            document.getElementById('costCodeInput').value  = item.code ?? '';
            document.getElementById('costNameInput').value  = item.name ?? '';

            document.getElementById('costTypeIndirect').checked = (item.cost_type === 'indirect');
            document.getElementById('costTypeDirect').checked   = (item.cost_type === 'direct');
            document.getElementById('costTypeRow').style.display = item.parent_id ? 'none' : '';

            const amountsSection  = document.getElementById('costAmountsSection');
            const aggregateNotice = document.getElementById('costAggregateNotice');

            if (item.has_children) {
                amountsSection.style.display  = 'none';
                aggregateNotice.style.display = '';
            } else {
                amountsSection.style.display  = '';
                aggregateNotice.style.display = 'none';

                function setFmtVal(inputId, val) {
                    const el = document.getElementById(inputId);
                    el.value = (val !== null && val !== undefined)
                        ? new Intl.NumberFormat('id-ID').format(val)
                        : '';
                }
                setFmtVal('costBudgetInput',  item.budget);
                setFmtVal('costReleaseInput', item.release_amount);
                _currentActual = item.actual_amount ?? 0;
                refreshPreview();
            }
            showModal();
        },

        openDeleteModal(id, name) {
            document.getElementById('costDeleteId').value      = id;
            document.getElementById('costDeleteName').textContent = name;
            document.getElementById('costDeleteModal').classList.remove('hidden');
        },

        async save() {
            const mode     = document.getElementById('costModalMode').value;
            const id       = document.getElementById('costModalId').value;
            let parentId = document.getElementById('costModalParentId').value;
            const costType = document.querySelector('input[name="costTypeRadio"]:checked')?.value ?? 'direct';
            const name     = document.getElementById('costNameInput').value.trim();

            if (!name) { showPlanCostToast('Item name is required.', 'warning'); return; }

            function getRawVal(inputId) {
                const v = document.getElementById(inputId).value.replace(/\./g, '').replace(',', '.');
                return v === '' ? null : parseFloat(v);
            }

            if (mode === 'create' && !parentId) {
                const matchingParent = _costs.find(c => c.cost_type === costType && c.parent_id === null);
                if (matchingParent) parentId = String(matchingParent.id);
            }

            const amountsHidden = document.getElementById('costAmountsSection').style.display === 'none';

            const payload = {
                parent_id:      parentId || null,
                code:           document.getElementById('costCodeInput').value.trim() || null,
                name,
                cost_type:      costType,
                budget:         amountsHidden ? null : getRawVal('costBudgetInput'),
                release_amount: amountsHidden ? null : getRawVal('costReleaseInput'),
                _token:         getCsrf(),
            };

            const btn = document.getElementById('costModalSaveBtn');
            btn.disabled = true;
            try {
                if (mode === 'create') {
                    await axios.post(BASE_URL, payload);
                } else {
                    await axios.post(`${BASE_URL}/${id}`, payload, { headers: { 'X-HTTP-Method-Override': 'PUT' } });
                }
                _close();
                await load();
                showPlanCostToast(mode === 'create' ? 'Cost item added successfully.' : 'Cost item updated successfully.', 'success');
            } catch (err) {
                const msg = err.response?.data?.message
                         ?? (err.response?.data?.errors ? Object.values(err.response.data.errors).flat().join(' ') : null)
                         ?? 'An error occurred.';
                showPlanCostToast(msg, 'error');
            } finally {
                btn.disabled = false;
            }
        },

        async confirmDelete() {
            const id = document.getElementById('costDeleteId').value;
            if (!id) return;
            try {
                await axios.post(`${BASE_URL}/${id}/delete`);
                SupportPlanCost.closeDeleteModal();
                await load();
                showPlanCostToast('Cost item deleted successfully.', 'success');
            } catch (err) {
                showPlanCostToast('Failed to delete item.', 'error');
            }
        },

        async openActualDetailModal(costId, costName) {
            _adCostId = costId;
            _adDirty  = false;
            document.getElementById('actualDetailSubtitle').textContent = costName;
            _adResetForm();
            document.getElementById('actualDetailModal').classList.remove('hidden');
            await _adLoadItems();
        },

        async closeActualDetailModal() {
            document.getElementById('actualDetailModal').classList.add('hidden');
            _adCostId = null;
            _adTotal  = 0;
            if (_adDirty) { _adDirty = false; await load(); }
        },

        async addExpenseItem() {
            const desc   = document.getElementById('adDescInput').value.trim();
            const rawAmt = document.getElementById('adAmountInput').value.replace(/\./g, '').replace(',', '.');
            const amount = parseFloat(rawAmt);
            const file   = document.getElementById('adFileInput').files[0];

            if (!desc) {
                showPlanCostToast('Expense name is required.', 'error');
                document.getElementById('adDescInput').focus();
                return;
            }
            if (!rawAmt || isNaN(amount) || amount <= 0) {
                showPlanCostToast('Amount must be greater than 0.', 'error');
                document.getElementById('adAmountInput').focus();
                return;
            }

            const btn = document.getElementById('adAddBtn');
            btn.disabled = true;
            try {
                const fd = new FormData();
                fd.append('description', desc);
                fd.append('amount', amount);
                fd.append('_token', getCsrf());
                if (file) fd.append('document', file);

                const res = await axios.post(`${BASE_URL}/${_adCostId}/items`, fd, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                });

                _adTotal = res.data.total ?? 0;
                _adDirty = true;
                _adAppendRow(res.data.item, _adGetCurrentCount() + 1);
                _adUpdateSummary();
                _adResetForm();
                showPlanCostToast('Expense added successfully.', 'success');
            } catch (err) {
                const msg = err.response?.data?.message
                         ?? (err.response?.data?.errors ? Object.values(err.response.data.errors).flat().join(' ') : null)
                         ?? 'Failed to add expense.';
                showPlanCostToast(msg, 'error');
            } finally {
                btn.disabled = false;
            }
        },

        deleteExpenseItem(itemId, rowEl) {
            _adDeleteId    = itemId;
            _adDeleteRowEl = rowEl;
            const name = rowEl?.querySelector('td:nth-child(2)')?.textContent?.trim() || '';
            document.getElementById('expenseDeleteName').textContent = name;
            document.getElementById('expenseDeleteModal').classList.remove('hidden');
        },

        closeExpenseDeleteModal() {
            document.getElementById('expenseDeleteModal').classList.add('hidden');
            _adDeleteId    = null;
            _adDeleteRowEl = null;
        },

        async confirmDeleteExpense() {
            if (!_adDeleteId) return;
            const itemId = _adDeleteId;
            const rowEl  = _adDeleteRowEl;
            const btn    = document.getElementById('expenseDeleteConfirmBtn');
            btn.disabled = true;
            try {
                const res = await axios.post(`${BASE_URL}/${_adCostId}/items/${itemId}/delete`);
                _adTotal = res.data.total ?? 0;
                _adDirty = true;
                rowEl?.remove();
                _adRenumberRows();
                _adUpdateSummary();
                if (_adGetCurrentCount() === 0) _adShowEmpty();
                SupportPlanCost.closeExpenseDeleteModal();
                showPlanCostToast('Expense deleted.', 'success');
            } catch (err) {
                showPlanCostToast('Failed to delete expense.', 'error');
            } finally {
                btn.disabled = false;
            }
        },

        handleDocDrop(event) {
            event.preventDefault();
            document.getElementById('adDropZone').classList.remove('border-orange-400', 'bg-orange-50/40');
            const file = event.dataTransfer.files[0];
            if (!file) return;
            const dt = new DataTransfer();
            dt.items.add(file);
            document.getElementById('adFileInput').files = dt.files;
            SupportPlanCost.onFileSelected(document.getElementById('adFileInput'));
        },

        onFileSelected(input) {
            const file = input.files[0];
            const label = document.getElementById('adDropLabel');
            if (file) {
                label.textContent = file.name;
                label.className   = 'text-xs text-orange-600 font-medium';
            } else {
                label.textContent = 'Click or drag & drop proof document';
                label.className   = 'text-xs text-gray-400';
            }
        },

        openEditExpenseModal(itemId, rowEl) {
            _adEditId        = itemId;
            _adEditRowEl     = rowEl;
            _adEditRemoveDoc = false;

            const desc    = rowEl?.dataset.desc   ?? '';
            const amount  = parseFloat(rowEl?.dataset.amount ?? '0') || 0;
            const docName = rowEl?.dataset.docName ?? '';
            const docUrl  = rowEl?.dataset.docUrl  ?? '';

            document.getElementById('aeDescInput').value   = desc;
            document.getElementById('aeAmountInput').value = amount
                ? new Intl.NumberFormat('id-ID').format(amount) : '';

            const curRow = document.getElementById('aeCurrentDocRow');
            if (docUrl) {
                document.getElementById('aeCurrentDocLink').href        = docUrl;
                document.getElementById('aeCurrentDocName').textContent = docName || 'View';
                curRow.classList.remove('hidden');
                document.getElementById('aeDropTitle').textContent = 'Replace Document';
            } else {
                curRow.classList.add('hidden');
                document.getElementById('aeDropTitle').textContent = 'Supporting Document';
            }

            document.getElementById('aeFileInput').value = '';
            const label = document.getElementById('aeDropLabel');
            label.textContent = 'Click or drag & drop proof document';
            label.className   = 'text-xs text-gray-400';

            document.getElementById('expenseEditModal').classList.remove('hidden');
        },

        closeExpenseEditModal() {
            document.getElementById('expenseEditModal').classList.add('hidden');
            _adEditId        = null;
            _adEditRowEl     = null;
            _adEditRemoveDoc = false;
        },

        removeEditDoc() {
            _adEditRemoveDoc = true;
            document.getElementById('aeCurrentDocRow').classList.add('hidden');
            document.getElementById('aeDropTitle').textContent = 'Supporting Document';
        },

        onEditFileSelected(input) {
            const file = input.files[0];
            const label = document.getElementById('aeDropLabel');
            if (file) {
                _adEditRemoveDoc = false;
                label.textContent = file.name;
                label.className   = 'text-xs text-orange-600 font-medium';
            } else {
                label.textContent = 'Click or drag & drop proof document';
                label.className   = 'text-xs text-gray-400';
            }
        },

        handleEditDocDrop(event) {
            event.preventDefault();
            document.getElementById('aeDropZone').classList.remove('border-orange-400', 'bg-orange-50/40');
            const file = event.dataTransfer.files[0];
            if (!file) return;
            const dt = new DataTransfer();
            dt.items.add(file);
            document.getElementById('aeFileInput').files = dt.files;
            SupportPlanCost.onEditFileSelected(document.getElementById('aeFileInput'));
        },

        async saveEditExpense() {
            if (!_adEditId) return;
            const desc   = document.getElementById('aeDescInput').value.trim();
            const rawAmt = document.getElementById('aeAmountInput').value.replace(/\./g, '').replace(',', '.');
            const amount = parseFloat(rawAmt);
            const file   = document.getElementById('aeFileInput').files[0];

            if (!desc) {
                showPlanCostToast('Expense name is required.', 'error');
                document.getElementById('aeDescInput').focus();
                return;
            }
            if (!rawAmt || isNaN(amount) || amount <= 0) {
                showPlanCostToast('Amount must be greater than 0.', 'error');
                document.getElementById('aeAmountInput').focus();
                return;
            }

            const btn = document.getElementById('aeSaveBtn');
            btn.disabled = true;
            try {
                const fd = new FormData();
                fd.append('description', desc);
                fd.append('amount', amount);
                if (file) fd.append('document', file);
                if (_adEditRemoveDoc) fd.append('remove_document', '1');

                const res = await axios.post(
                    `${BASE_URL}/${_adCostId}/items/${_adEditId}`,
                    fd,
                    { headers: { 'Content-Type': 'multipart/form-data', 'X-HTTP-Method-Override': 'PUT' } }
                );

                _adTotal = res.data.total ?? 0;
                _adDirty = true;
                _adUpdateRow(_adEditRowEl, res.data.item);
                _adUpdateSummary();
                SupportPlanCost.closeExpenseEditModal();
                showPlanCostToast('Expense updated successfully.', 'success');
            } catch (err) {
                const msg = err.response?.data?.message
                         ?? (err.response?.data?.errors ? Object.values(err.response.data.errors).flat().join(' ') : null)
                         ?? 'Failed to update expense.';
                showPlanCostToast(msg, 'error');
            } finally {
                btn.disabled = false;
            }
        },
    };

    function showPlanCostToast(msg, type) {
        if (typeof window.showToast === 'function') window.showToast(msg, type);
        else if (typeof window.showNotification === 'function') window.showNotification(msg, type);
        else showAlert(msg);
    }

    async function _adLoadItems() {
        const tbody = document.getElementById('actualDetailTableBody');
        const tfoot = document.getElementById('actualDetailTableFoot');
        tbody.innerHTML = `<tr><td colspan="5" class="text-center py-8 text-gray-400 text-sm">
            <svg class="animate-spin h-5 w-5 primary-text mx-auto mb-2" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>Loading data…</td></tr>`;
        tfoot.classList.add('hidden');
        try {
            const res = await axios.get(`${BASE_URL}/${_adCostId}/items`);
            _adTotal = res.data.total ?? 0;
            const items = res.data.items ?? [];
            if (!items.length) {
                _adShowEmpty();
            } else {
                tbody.innerHTML = '';
                items.forEach((it, idx) => _adAppendRow(it, idx + 1));
                tfoot.classList.remove('hidden');
            }
            _adUpdateSummary();
        } catch (e) {
            tbody.innerHTML = `<tr><td colspan="5" class="text-center py-6 text-red-500 text-sm">Failed to load data.</td></tr>`;
        }
    }

    function _adShowEmpty() {
        document.getElementById('actualDetailTableBody').innerHTML =
            `<tr><td colspan="5" class="text-center py-8 text-gray-400 text-sm italic" data-empty>No expense records found.</td></tr>`;
        document.getElementById('actualDetailTableFoot').classList.add('hidden');
    }

    function _adAppendRow(item, no) {
        const tbody = document.getElementById('actualDetailTableBody');
        const tfoot = document.getElementById('actualDetailTableFoot');

        const emptyRow = tbody.querySelector('[data-empty]');
        if (emptyRow) emptyRow.closest('tr').remove();

        const docCell = _adDocCellHtml(item);

        const tr = document.createElement('tr');
        tr.className   = 'hover:bg-gray-50 transition-colors';
        tr.dataset.itemId  = item.id;
        tr.dataset.desc    = item.description ?? '';
        tr.dataset.amount  = item.amount ?? 0;
        tr.dataset.docName = item.document_name ?? '';
        tr.dataset.docUrl  = item.document_url ?? '';
        tr.innerHTML = `
            <td class="px-4 py-2.5 text-gray-400 text-xs">${no}</td>
            <td class="px-4 py-2.5 text-gray-700 text-sm">${_esc(item.description)}</td>
            <td class="px-4 py-2.5 text-right font-mono text-sm text-blue-700 font-medium whitespace-nowrap">${fmtRp(item.amount)}</td>
            <td class="px-4 py-2.5 text-center whitespace-nowrap">${docCell}</td>
            <td class="px-4 py-2.5 text-center whitespace-nowrap">
                <div class="inline-flex items-center gap-0.5">
                    <button type="button" title="Edit"
                            onclick="SupportPlanCost.openEditExpenseModal(${item.id}, this.closest('tr'))"
                            class="p-1 text-blue-500 hover:text-blue-700 hover:bg-blue-50 rounded transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                    </button>
                    <button type="button" title="Delete"
                            onclick="SupportPlanCost.deleteExpenseItem(${item.id}, this.closest('tr'))"
                            class="p-1 text-red-400 hover:text-red-600 hover:bg-red-50 rounded transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                </div>
            </td>`;
        tbody.appendChild(tr);
        tfoot.classList.remove('hidden');
    }

    function _adDocCellHtml(item) {
        return item.document_url
            ? `<a href="${item.document_url}" target="_blank" rel="noopener"
                  class="inline-flex items-center gap-1 text-xs text-blue-600 hover:underline">
                   <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                       <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                   </svg>
                   ${_esc(item.document_name ?? 'View')}
               </a>`
            : `<span class="text-gray-300 text-xs">—</span>`;
    }

    function _adUpdateRow(tr, item) {
        if (!tr || !item) return;
        tr.dataset.desc    = item.description ?? '';
        tr.dataset.amount  = item.amount ?? 0;
        tr.dataset.docName = item.document_name ?? '';
        tr.dataset.docUrl  = item.document_url ?? '';
        const tds = tr.querySelectorAll('td');
        if (tds[1]) tds[1].textContent = item.description ?? '';
        if (tds[2]) tds[2].textContent = fmtRp(item.amount);
        if (tds[3]) tds[3].innerHTML   = _adDocCellHtml(item);
    }

    function _adRenumberRows() {
        document.querySelectorAll('#actualDetailTableBody tr[data-item-id]').forEach((tr, idx) => {
            const firstTd = tr.querySelector('td:first-child');
            if (firstTd) firstTd.textContent = idx + 1;
        });
    }

    function _adGetCurrentCount() {
        return document.querySelectorAll('#actualDetailTableBody tr[data-item-id]').length;
    }

    function _adUpdateSummary() {
        document.getElementById('adTotalItems').textContent  = fmtRp(_adTotal);
        document.getElementById('adFooterTotal').textContent = fmtRp(_adTotal);
    }

    function _adResetForm() {
        document.getElementById('adDescInput').value  = '';
        document.getElementById('adAmountInput').value = '';
        document.getElementById('adFileInput').value   = '';
        const label = document.getElementById('adDropLabel');
        label.textContent = 'Click or drag & drop proof document';
        label.className   = 'text-xs text-gray-400';
    }

    function _esc(str) {
        return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    document.addEventListener('DOMContentLoaded', function () {
        axios.defaults.headers.common['X-CSRF-TOKEN'] = getCsrf();
        ['costBudgetInput', 'costReleaseInput', 'adAmountInput', 'aeAmountInput'].forEach(id => {
            const el = document.getElementById(id);
            if (el) formatCurrencyInput(el);
        });
        init();
    });

    window.SupportPlanCost = SupportPlanCost;
})();
</script>

{{-- ── TERM OF PAYMENT (TOP) module ─────────────────────────────── --}}
<script>
window.SupportPaymentTermPlan = (function () {
    'use strict';

    // Mirror PaymentTermPlan Delivery Project. Dua Type TOP (lihat App\Services\SupportTopPlan):
    //   percentage → Amount = Payment % × revenue Sales Data (total % ≤ 100, diblokir)
    //   line_item  → termin dikaitkan ke Contract Line Item; amount diambil dari
    //                nilai line item: basis "line_item" (Payment % × nilai line item)
    //                ATAU "fixed" (Amount diisi, % terhadap line item dihitung).
    //                Basis % of Revenue dikunci. Melebihi revenue hanya diperingatkan.
    const SUPPORT_ID   = {{ $support->id }};
    const BASE_URL     = `/delivery/support/${SUPPORT_ID}/payment-terms`;
    const MODE_URL     = `/delivery/support/${SUPPORT_ID}/top-mode`;
    const LINE_URL     = `/delivery/support/${SUPPORT_ID}/contract-line-items`;
    const FREQ_MONTHS  = { monthly: 1, quarterly: 3, semiannual: 6, yearly: 12 };
    const MONTHS       = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    // Grup line item dengan termin sebanyak ini atau lebih dilipat (design: "… 8 tagihan lainnya").
    const COLLAPSE_AT  = 7;
    const COLLAPSE_SHOW = 4;

    let _mode      = @json($support->top_mode === 'line_item' ? 'line_item' : 'percentage');
    let _terms     = [];
    let _lineItems = [];
    let _warnings  = [];
    let _basis     = 'percentage';   // basis termin yang sedang diedit di modal
    let _legacyPct = false;          // termin lama basis % revenue di mode Line Item (boleh tetap)
    const _expanded = new Set();     // id line item yang grupnya sedang dibuka penuh
    // Revenue acuan untuk hitung Amount = revenue × % / 100
    let _revenue = parseFloat('{{ $support->revenue ?? 0 }}') || 0;

    function getCsrf() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    function esc(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // ── Currency formatting (Indonesian thousands-dot) ─────────────
    function fmtRp(n) {
        const num = Number(n) || 0;
        const neg = num < 0;
        const abs = Math.abs(Math.round(num));
        return 'Rp ' + (neg ? '-' : '') + abs.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function fmtThousands(n) {
        return Math.abs(Math.round(Number(n) || 0)).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    // "2.000.000" → 2000000 (input nominal memakai titik ribuan)
    function parseAmount(str) {
        const digits = String(str ?? '').replace(/[^\d]/g, '');
        return digits === '' ? null : parseInt(digits, 10);
    }

    function onAmountInput(el) {
        if (el.readOnly) return;
        const v = parseAmount(el.value);
        el.value = v === null ? '' : fmtThousands(v);
    }

    // Baca revenue terkini dari field Sales Data (jika user mengubah tanpa reload)
    function currentRevenue() {
        const el = document.getElementById('sfin_rev_val');
        if (el && el.value !== '') {
            const v = parseFloat(el.value);
            if (!isNaN(v)) return v;
        }
        return _revenue;
    }

    function statusBadge(status) {
        const map = {
            'Open':     'bg-yellow-100 text-yellow-800',
            'Invoiced': 'bg-blue-100 text-blue-800',
            'Paid':     'bg-green-100 text-green-800',
            'Delay':    'bg-red-100 text-red-700',
        };
        const cls = map[status] ?? 'bg-gray-100 text-gray-700';
        return `<span class="px-2 py-0.5 rounded-full text-xs font-semibold ${cls}">${esc(status)}</span>`;
    }

    function fmtPct(p) {
        const num = Number(p) || 0;
        // tampilkan tanpa desimal jika bulat, jika tidak 2 desimal
        return (Number.isInteger(num) ? num.toString() : num.toFixed(2).replace('.', ',')) + '%';
    }

    function errMsg(e, fallback) {
        if (e.response?.data?.errors) {
            const first = Object.values(e.response.data.errors)[0];
            return Array.isArray(first) ? first[0] : String(first);
        }
        return e.response?.data?.message ?? fallback ?? 'Something went wrong. Please try again.';
    }

    function spinner() {
        return '<svg class="animate-spin w-4 h-4 mx-auto" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>';
    }

    // Peringatan dari server ditampilkan setelah notifikasi sukses.
    function notifyWarnings(warnings) {
        (warnings || []).forEach(w => showNotification(w, 'warning'));
    }

    // Info perbandingan sebuah total dengan revenue Sales Data (sesuai / kurang / lebih).
    function revenueCompareHtml(total, revenue) {
        const pill = (cls, text) => `<span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold ${cls}">${text}</span>`;
        const head = `Revenue in Sales Data: <span class="font-semibold text-gray-700">${fmtRp(revenue)}</span>`;
        if (revenue <= 0) {
            return head + pill('bg-gray-100 text-gray-600', 'Revenue is empty — cannot compare');
        }
        const diff = total - revenue;
        if (Math.abs(diff) < 1) {
            return head + pill('bg-green-100 text-green-800', '✓ Matches revenue');
        }
        const pct = fmtPct(Math.round(Math.abs(diff) / revenue * 10000) / 100);
        return diff > 0
            ? head + pill('bg-red-100 text-red-700', `Over revenue by ${fmtRp(diff)} (${pct})`)
            : head + pill('bg-amber-100 text-amber-800', `Below revenue by ${fmtRp(-diff)} (${pct})`);
    }

    // ── Periode (mirror DeliveryProjectContractLineItem::buildPeriods) ──
    function parseYmd(s) {
        const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s || '');
        return m ? new Date(+m[1], +m[2] - 1, +m[3]) : null;
    }

    function monthLabel(d) { return `${MONTHS[d.getMonth()]} ${d.getFullYear()}`; }

    function buildPeriods(freq, startStr, endStr) {
        const months = FREQ_MONTHS[freq];
        const start  = parseYmd(startStr);
        const end    = parseYmd(endStr);
        if (!months || !start || !end) return [];

        const periods = [];
        let cursor = new Date(start.getFullYear(), start.getMonth(), 1);
        while (cursor <= end && periods.length < 120) {
            const pEnd = new Date(cursor.getFullYear(), cursor.getMonth() + months - 1, 1);
            periods.push(months === 1 ? monthLabel(cursor) : `${monthLabel(cursor)} – ${monthLabel(pEnd)}`);
            cursor = new Date(cursor.getFullYear(), cursor.getMonth() + months, 1);
        }
        return periods;
    }

    function lineItemById(id) {
        return _lineItems.find(li => li.id === Number(id)) || null;
    }

    // ── Load & render ─────────────────────────────────────────────
    async function load() {
        try {
            const res = await axios.get(BASE_URL);
            _terms     = res.data.payment_terms ?? [];
            _lineItems = res.data.line_items ?? [];
            _warnings  = res.data.warnings ?? [];
            _mode      = res.data.top_mode === 'line_item' ? 'line_item' : 'percentage';
            if (res.data.support_revenue !== undefined && res.data.support_revenue !== null) {
                _revenue = parseFloat(res.data.support_revenue) || _revenue;
            }
            render();
        } catch (e) {
            const tbody = document.getElementById('supPaymentTermBody');
            if (tbody) tbody.innerHTML =
                `<tr><td colspan="12" class="text-center py-8 text-red-500 text-sm">Failed to load data. Please refresh.</td></tr>`;
        }
    }

    function render() {
        renderMode();
        renderWarnings();
        renderLineItems();
        renderTable();
    }

    function renderMode() {
        document.querySelectorAll('#supPtModeToggle .pt-mode-btn').forEach(btn => {
            const active = btn.dataset.mode === _mode;
            btn.classList.toggle('primary-gradient', active);
            btn.classList.toggle('text-white', active);
            btn.classList.toggle('bg-white', !active);
            btn.classList.toggle('text-gray-700', !active);
            btn.classList.toggle('hover:bg-gray-50', !active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        });

        const hint = document.getElementById('supPtModeHint');
        if (hint) {
            hint.textContent = _mode === 'line_item'
                ? `Amount is taken from the contract line items (Payment % of the line item value, or a fixed Amount) and compared with the revenue in Sales Data (${fmtRp(currentRevenue())}).`
                : `Amount = Payment % × revenue in Sales Data (${fmtRp(currentRevenue())}).`;
        }

        document.getElementById('supPtLineItemSection')?.classList.toggle('hidden', _mode !== 'line_item');
    }

    function renderWarnings() {
        const box = document.getElementById('supPtWarnings');
        if (!box) return;
        box.classList.toggle('hidden', !_warnings.length);
        box.innerHTML = _warnings.length
            ? `<div class="flex gap-2">
                   <svg class="w-5 h-5 flex-shrink-0 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                   <ul class="space-y-1">${_warnings.map(w => `<li>${esc(w)}</li>`).join('')}</ul>
               </div>`
            : '';
    }

    function renderLineItems() {
        const tbody = document.getElementById('supPtLineItemBody');
        if (!tbody) return;
        renderLineItemFoot();

        if (!_lineItems.length) {
            tbody.innerHTML = `<tr><td colspan="7" class="text-center py-6 text-gray-400 text-sm">No line items yet. Add the contract line items (e.g. Service, License, ATS) before creating payment terms.</td></tr>`;
            return;
        }

        tbody.innerHTML = _lineItems.map(li => {
            const over   = li.billed_total > li.total + 0.01;
            const billedCls = over ? 'text-red-600' : (Math.abs(li.billed_total - li.total) < 0.01 ? 'text-green-700' : 'text-gray-700');
            return `<tr class="hover:bg-gray-50">
                <td class="px-3 py-3 text-xs font-medium text-gray-800">${esc(li.name)}</td>
                <td class="px-3 py-3 text-xs text-gray-600 whitespace-nowrap">${esc(li.type_label)}</td>
                <td class="px-3 py-3 text-xs text-gray-600 whitespace-nowrap">${esc(li.schedule_label)}</td>
                <td class="px-3 py-3 text-xs text-right text-gray-700 whitespace-nowrap">${fmtRp(li.amount)}</td>
                <td class="px-3 py-3 text-xs text-right font-semibold text-gray-800 whitespace-nowrap">${fmtRp(li.total)}</td>
                <td class="px-3 py-3 text-xs text-right font-semibold whitespace-nowrap ${billedCls}" title="${li.terms_count} payment term(s)">${fmtRp(li.billed_total)}</td>
                <td class="px-3 py-3 text-center whitespace-nowrap">
                    ${li.type === 'recurring' ? `
                    <button type="button" onclick="SupportPaymentTermPlan.openGenerate(${li.id})"
                            class="inline-flex items-center px-3 py-1.5 mr-1 text-xs font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                        Generate Schedule
                    </button>` : `
                    <button type="button" onclick="SupportPaymentTermPlan.openAdd(${li.id})"
                            class="inline-flex items-center px-3 py-1.5 mr-1 text-xs font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                        Add Term
                    </button>`}
                    <button type="button" onclick="SupportPaymentTermPlan.openEditLineItem(${li.id})" title="Edit Line Item"
                            class="inline-flex items-center p-1.5 text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    </button>
                    <button type="button" onclick="SupportPaymentTermPlan.openLineItemDelete(${li.id})" title="Delete Line Item"
                            class="inline-flex items-center p-1.5 text-red-500 hover:text-red-700 hover:bg-red-50 rounded transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                </td>
            </tr>`;
        }).join('');
    }

    // Footer tabel Line Item: total nilai kontrak & total yang sudah masuk TOP vs revenue.
    function renderLineItemFoot() {
        const tfoot = document.getElementById('supPtLineItemFoot');
        if (!tfoot) return;
        if (!_lineItems.length) { tfoot.innerHTML = ''; return; }

        const total  = _lineItems.reduce((s, li) => s + (Number(li.total) || 0), 0);
        const billed = _lineItems.reduce((s, li) => s + (Number(li.billed_total) || 0), 0);
        const billedCls = billed > total + 0.01 ? 'text-red-600' : (Math.abs(billed - total) < 1 ? 'text-green-700' : 'text-gray-700');

        tfoot.innerHTML = `<tr class="font-semibold text-gray-700">
                <td class="px-3 py-3 text-xs" colspan="4">Total Contract Value</td>
                <td class="px-3 py-3 text-xs text-right whitespace-nowrap">${fmtRp(total)}</td>
                <td class="px-3 py-3 text-xs text-right whitespace-nowrap ${billedCls}">${fmtRp(billed)}</td>
                <td></td>
            </tr>
            <tr>
                <td class="px-3 pb-3 text-xs font-normal text-gray-500" colspan="7">${revenueCompareHtml(total, currentRevenue())}</td>
            </tr>`;
    }

    function colCount() { return _mode === 'line_item' ? 12 : 11; }

    function renderHead() {
        const thead = document.getElementById('supPaymentTermHead');
        if (!thead) return;
        const th = (label, cls) => `<th class="px-3 py-3 font-semibold whitespace-nowrap ${cls}">${label}</th>`;
        thead.innerHTML = `<tr class="bg-gray-700 text-white">
            ${th('No', 'text-center w-[50px]')}
            ${th('Payment Term', 'text-left min-w-[160px]')}
            ${_mode === 'line_item' ? th('Period', 'text-left min-w-[110px]') : ''}
            ${th('Payment %', 'text-center w-[110px]')}
            ${th('Amount', 'text-right min-w-[150px]')}
            ${th('Payment Requirements / Evidence', 'text-left min-w-[220px]')}
            ${th('Estimated Date', 'text-left min-w-[130px]')}
            ${th('Submit Invoice Date', 'text-left min-w-[150px]')}
            ${th('Invoice No', 'text-left min-w-[130px]')}
            ${th('Paid Date', 'text-left min-w-[130px]')}
            ${th('Status', 'text-center w-[100px]')}
            ${th('Action', 'text-center w-[80px]')}
        </tr>`;
    }

    function renderTable() {
        renderHead();
        const tbody = document.getElementById('supPaymentTermBody');
        if (!tbody) return;

        if (!_terms.length) {
            const hint = _mode === 'line_item' && !_lineItems.length
                ? 'No payment terms yet. Add a contract line item first, then click "Add Payment Term" or "Generate Schedule".'
                : 'No payment terms yet. Click "Add Payment Term" to get started.';
            tbody.innerHTML = `<tr><td colspan="${colCount()}" class="text-center py-8 text-gray-400 text-sm">${hint}</td></tr>`;
        } else if (_mode === 'line_item') {
            tbody.innerHTML = groupedRowsHtml();
        } else {
            tbody.innerHTML = _terms.map(t => rowHtml(t)).join('');
        }

        renderFoot();
    }

    // Mode Line Item: termin dikelompokkan per line item + subtotal (design "Contract Line Items").
    function groupedRowsHtml() {
        const groups = _lineItems.map(li => ({ li, terms: _terms.filter(t => t.contract_line_item_id === li.id) }))
            .filter(g => g.terms.length);
        const orphan = _terms.filter(t => !lineItemById(t.contract_line_item_id));
        if (orphan.length) groups.push({ li: null, terms: orphan });

        const span = colCount();
        return groups.map(({ li, terms }) => {
            const key      = li ? li.id : 0;
            const subtotal = terms.reduce((s, t) => s + (Number(t.amount) || 0), 0);
            const title    = li ? li.name : 'Not linked to a line item';
            const collapse = terms.length >= COLLAPSE_AT && !_expanded.has(key);
            const visible  = collapse ? terms.slice(0, COLLAPSE_SHOW) : terms;
            const hidden   = terms.slice(COLLAPSE_SHOW);

            let html = `<tr class="bg-rose-50">
                <td colspan="${span}" class="px-3 py-2.5 text-xs font-semibold text-gray-800">
                    ${esc(title)} <span class="font-normal text-gray-500">(${terms.length} term${terms.length > 1 ? 's' : ''})</span>
                    ${li ? `<span class="float-right font-normal text-gray-500">Contract value ${fmtRp(li.total)}</span>`
                         : `<span class="float-right font-normal text-amber-700">Edit these terms to choose a line item.</span>`}
                </td>
            </tr>`;
            html += visible.map(t => rowHtml(t)).join('');

            if (terms.length >= COLLAPSE_AT) {
                const range = hidden.length ? `${hidden[0].period || hidden[0].payment_term} – ${hidden[hidden.length - 1].period || hidden[hidden.length - 1].payment_term}` : '';
                html += `<tr><td colspan="${span}" class="px-3 py-2 text-center text-xs">
                    <button type="button" data-perm-keep onclick="SupportPaymentTermPlan.toggleGroup(${key})" class="text-gray-500 hover:text-gray-800 underline-offset-2 hover:underline">
                        ${collapse ? `… ${hidden.length} more term${hidden.length > 1 ? 's' : ''} (${esc(range)}) · Show all` : 'Show less'}
                    </button>
                </td></tr>`;
            }

            html += `<tr class="bg-gray-50/60">
                <td colspan="4" class="px-3 py-2 text-xs font-semibold text-gray-500">Subtotal ${esc(title)}</td>
                <td class="px-3 py-2 text-right text-xs font-semibold text-gray-600 whitespace-nowrap">${fmtRp(subtotal)}</td>
                <td colspan="${span - 5}"></td>
            </tr>`;
            return html;
        }).join('');
    }

    function renderFoot() {
        const tfoot = document.getElementById('supPaymentTermFoot');
        if (!tfoot) return;

        if (!_terms.length) { tfoot.innerHTML = ''; return; }

        const totalAmt = _terms.reduce((s, t) => s + (Number(t.amount) || 0), 0);
        const revenue  = currentRevenue();
        const over     = totalAmt - revenue;
        // Total % hanya bermakna untuk basis % of Revenue (= porsi revenue).
        const revTerms = _terms.filter(t => t.basis === 'percentage');
        const totalPct = revTerms.reduce((s, t) => s + (Number(t.payment_percentage) || 0), 0);
        const pctCell  = _mode === 'percentage' && revTerms.length
            ? `<td class="px-3 py-3 text-center ${totalPct > 100 ? 'text-red-600' : 'text-gray-700'}">${fmtPct(totalPct)}</td>`
            : `<td class="px-3 py-3 text-center text-gray-400">—</td>`;

        // Rincian status penagihan (dari total TOP).
        const byStatus = s => _terms.filter(t => t.status === s).reduce((sum, t) => sum + (Number(t.amount) || 0), 0);
        const statusInfo = ['Paid', 'Invoiced']
            .map(s => ({ s, v: byStatus(s) })).filter(x => x.v > 0)
            .map(x => `${x.s} ${fmtRp(x.v)}`).join(' · ');

        const lead = _mode === 'line_item' ? 3 : 2;
        tfoot.innerHTML = `<tr class="font-semibold text-gray-700">
                <td class="px-3 py-3" colspan="${lead}">Total</td>
                ${pctCell}
                <td class="px-3 py-3 text-right whitespace-nowrap ${over > 0.01 ? 'text-red-600' : ''}">${fmtRp(totalAmt)}</td>
                <td class="px-3 py-3 text-xs font-normal text-gray-500" colspan="7">
                    ${revenueCompareHtml(totalAmt, revenue)}
                    ${statusInfo ? `<span class="ml-2 text-gray-400">· ${statusInfo}</span>` : ''}
                </td>
            </tr>`;
    }

    function rowHtml(t) {
        // % of Revenue → "x%" (+ "of revenue" di mode Line Item, data lama);
        // % of Line Item → "x% of item"; Amount → badge Fixed + porsinya ke line item.
        const sub = txt => `<div class="text-[10px] font-normal text-gray-400">${txt}</div>`;
        let pctCell;
        if (t.basis === 'fixed') {
            pctCell = `<span class="px-2 py-0.5 rounded-full text-[11px] font-medium bg-gray-100 text-gray-500">Fixed</span>`
                + (t.line_item_share !== null && t.line_item_share !== undefined ? sub(`${fmtPct(t.line_item_share)} of item`) : '');
        } else if (t.basis === 'line_item') {
            pctCell = fmtPct(t.payment_percentage) + sub('of item');
        } else {
            pctCell = fmtPct(t.payment_percentage) + (_mode === 'line_item' ? sub('of revenue') : '');
        }

        return `<tr class="hover:bg-gray-50 align-top">
            <td class="px-3 py-3 text-center text-xs font-mono text-gray-600">${t.term_number}</td>
            <td class="px-3 py-3 text-xs text-gray-800"><div class="line-clamp-3">${esc(t.payment_term)}</div></td>
            ${_mode === 'line_item' ? `<td class="px-3 py-3 text-xs text-gray-600 whitespace-nowrap">${esc(t.period) || '—'}</td>` : ''}
            <td class="px-3 py-3 text-center text-xs font-semibold text-gray-700">${pctCell}</td>
            <td class="px-3 py-3 text-right text-xs font-semibold text-gray-800 whitespace-nowrap">${fmtRp(t.amount)}</td>
            <td class="px-3 py-3 text-xs text-gray-600 max-w-[260px]"><div class="line-clamp-3">${esc(t.requirements) || '—'}</div></td>
            <td class="px-3 py-3 text-xs text-gray-500 whitespace-nowrap">${esc(t.estimated_date_label) || '—'}</td>
            <td class="px-3 py-3 text-xs text-gray-500 whitespace-nowrap">${esc(t.submit_invoice_date_label) || '—'}</td>
            <td class="px-3 py-3 text-xs text-gray-700 whitespace-nowrap">${esc(t.invoice_number) || '—'}</td>
            <td class="px-3 py-3 text-xs text-gray-500 whitespace-nowrap">${esc(t.paid_date_label) || '—'}</td>
            <td class="px-3 py-3 text-center">${statusBadge(t.status)}</td>
            <td class="px-3 py-3 text-center whitespace-nowrap">
                <button type="button" onclick="SupportPaymentTermPlan.openEdit(${t.id})"
                        class="inline-flex items-center p-1.5 text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded transition" title="Edit">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                </button>
                <button type="button" onclick="SupportPaymentTermPlan.openDeleteModal(${t.id}, ${t.term_number})"
                        class="inline-flex items-center p-1.5 text-red-500 hover:text-red-700 hover:bg-red-50 rounded transition" title="Delete">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </button>
            </td>
        </tr>`;
    }

    function toggleGroup(key) {
        if (_expanded.has(key)) _expanded.delete(key); else _expanded.add(key);
        renderTable();
    }

    // ── Mode penagihan ─────────────────────────────────────────────
    async function switchMode(mode) {
        if (mode === _mode) return;

        const question = mode === 'line_item'
            ? 'Existing % of Revenue terms stay as they are. New payment terms must be linked to a contract line item and take their amount from it — "% of Revenue" is locked.'
            : 'Contract line items are kept but hidden. Only possible when no term takes its amount from a line item.';
        const title = mode === 'line_item'
            ? 'Switch TOP Type to "Contract Line Item"?'
            : 'Switch TOP Type back to "% of Revenue"?';
        if (!(await showConfirm(question, title, 'primary', { okText: 'Switch Type' }))) return;

        try {
            const res = await axios.post(MODE_URL, { top_mode: mode, _token: getCsrf() });
            showNotification(res.data.message ?? 'Billing mode updated.', 'success');
            await load();
        } catch (e) {
            showNotification(errMsg(e, 'Failed to change TOP type.'), 'error');
        }
    }

    // ── Modal Payment Term: basis ──────────────────────────────────
    // Ketersediaan basis per Type TOP:
    //   % of Revenue       → hanya Type "% of Revenue". Di Contract Line Item
    //                        dikunci, kecuali termin lama yang sudah memakainya.
    //   Payment % / Amount → hanya Type "Contract Line Item" (acuan: nilai line item).
    function basisAllowed(basis) {
        if (_mode !== 'line_item') return basis === 'percentage';
        return basis !== 'percentage' || _legacyPct;
    }

    function selectedLineItem() {
        return lineItemById(document.getElementById('pt_line_item')?.value);
    }

    function round2(n) { return Math.round(n * 100) / 100; }

    // Porsi (%) nilai line item yang belum masuk TOP — prefill Payment %.
    function remainingShare(li) {
        if (!li || !(li.total > 0)) return 0;
        const editId = document.getElementById('paymentTermModalMode').value === 'edit'
            ? parseInt(document.getElementById('paymentTermModalId').value, 10) : null;
        const billed = _terms.filter(t => t.id !== editId && t.contract_line_item_id === li.id)
            .reduce((s, t) => s + (Number(t.amount) || 0), 0);
        return Math.max(0, round2(100 - billed / li.total * 100));
    }

    function setBasis(basis) {
        if (!basisAllowed(basis)) {
            showNotification(_mode === 'line_item'
                ? '"% of Revenue" is locked for TOP Type "Contract Line Item". Use "Payment %" or "Amount".'
                : 'Payment % / Amount of a line item is only available in TOP Type "Contract Line Item".', 'warning');
            return;
        }
        const prevAmount = modalAmount();   // nilai sebelum pindah basis → dipertahankan
        _basis = basis;
        const fixed = basis === 'fixed';

        ['percentage', 'line_item', 'fixed'].forEach(b => {
            const btn = document.getElementById('pt_basis_' + b);
            if (!btn) return;
            const active  = b === basis;
            const allowed = basisAllowed(b);
            btn.disabled = !allowed;
            btn.title = !allowed && b === 'percentage' ? 'Locked for TOP Type "Contract Line Item"' : '';
            btn.classList.toggle('primary-gradient', active);
            btn.classList.toggle('text-white', active);
            btn.classList.toggle('bg-white', !active);
            btn.classList.toggle('text-gray-700', !active && allowed);
            btn.classList.toggle('text-gray-400', !active && !allowed);
            btn.classList.toggle('bg-gray-100', !active && !allowed);
            // Type "% of Revenue" hanya punya satu basis — tombol lain disembunyikan.
            if (b !== 'percentage') btn.classList.toggle('hidden', _mode !== 'line_item');
        });

        // Payment % ↔ Amount: satu diisi, yang lain dihitung otomatis.
        const pct = document.getElementById('pt_payment_percentage');
        pct.disabled = fixed;
        document.getElementById('pt_pct_req').classList.toggle('hidden', fixed);
        document.getElementById('pt_pct_of').textContent =
            basis === 'percentage' ? '(of revenue)' : (fixed ? '(of line item, auto)' : '(of line item)');

        const amt = document.getElementById('pt_amount_disp');
        amt.readOnly = !fixed;
        amt.tabIndex = fixed ? 0 : -1;
        amt.classList.toggle('bg-gray-50', !fixed);
        amt.classList.toggle('cursor-not-allowed', !fixed);
        amt.classList.toggle('text-gray-600', !fixed);
        amt.classList.toggle('border-gray-200', !fixed);
        amt.classList.toggle('border-gray-300', fixed);
        amt.classList.toggle('primary-focus', fixed);
        document.getElementById('pt_amount_req').classList.toggle('hidden', !fixed);
        document.getElementById('pt_amount_auto').classList.toggle('hidden', fixed);

        document.getElementById('pt_basis_hint').textContent = {
            percentage: `Amount = Payment % × revenue in Sales Data (${fmtRp(currentRevenue())}).`,
            line_item:  'Fill in Payment % — Amount = Payment % × the selected line item value.',
            fixed:      'Fill in the Amount — its share (%) of the selected line item value is calculated automatically.',
        }[basis];

        // Pertahankan nilai yang sudah terisi saat berpindah Payment % ↔ Amount.
        const li = selectedLineItem();
        if (fixed) {
            amt.value = prevAmount > 0 ? fmtThousands(prevAmount) : '';
            if (!amt.value) { onLineItemChange(true); return; }
        } else if (basis === 'line_item') {
            if (prevAmount > 0 && li && li.total > 0) pct.value = round2(prevAmount / li.total * 100);
            if (pct.value === '') { onLineItemChange(true); return; }
        }
        recalcAmount();
    }

    function populateLineItemSelect(selectedId) {
        const sel = document.getElementById('pt_line_item');
        if (!sel) return;
        sel.innerHTML = `<option value="">— Select line item —</option>` +
            _lineItems.map(li => `<option value="${li.id}">${esc(li.name)} · ${esc(li.type_label)} · ${
                li.type === 'recurring' ? fmtRp(li.amount) + ' / period' : fmtRp(li.total)}</option>`).join('');
        sel.value = selectedId ? String(selectedId) : '';
    }

    // Prefill dari line item yang dipilih — tetap bisa diubah:
    //   Amount    → nominal line item (per periode untuk recurring)
    //   Payment % → sisa porsi line item yang belum masuk TOP
    function onLineItemChange(fromBasisSwitch) {
        const li  = selectedLineItem();
        const amt = document.getElementById('pt_amount_disp');
        const pct = document.getElementById('pt_payment_percentage');
        if (li && _basis === 'fixed' && (fromBasisSwitch === true || parseAmount(amt.value) === null)) {
            amt.value = fmtThousands(li.amount);
        }
        if (li && _basis === 'line_item' && pct.value === '') {
            const rem = remainingShare(li);
            if (rem > 0) pct.value = rem;
        }
        const name = document.getElementById('pt_payment_term');
        if (li && fromBasisSwitch !== true && !name.value.trim()) name.value = li.name;
        recalcAmount();
    }

    // ── Amount auto-calc (preview di modal) ────────────────────────
    function recalcAmount() {
        const pctEl = document.getElementById('pt_payment_percentage');
        const disp  = document.getElementById('pt_amount_disp');
        if (_basis === 'fixed') {
            // Basis Amount: tampilkan porsinya terhadap nilai line item.
            const li     = selectedLineItem();
            const amount = parseAmount(disp.value) || 0;
            pctEl.value  = li && li.total > 0 && amount > 0 ? round2(amount / li.total * 100) : '';
        } else if (disp) {
            disp.value = fmtThousands(modalAmount());
        }
        checkOver();
    }

    function modalAmount() {
        if (_basis === 'fixed') return parseAmount(document.getElementById('pt_amount_disp').value) || 0;
        const pct = parseFloat(document.getElementById('pt_payment_percentage').value);
        if (isNaN(pct)) return 0;
        if (_basis === 'line_item') {
            const li = selectedLineItem();
            return li ? (Number(li.total) || 0) * pct / 100 : 0;
        }
        return currentRevenue() * pct / 100;
    }

    // Notifikasi (tidak memblokir) bila total TOP melebihi revenue / nilai line item.
    function checkOver() {
        const box = document.getElementById('pt_over_warning');
        if (!box) return;
        if (_mode !== 'line_item') { box.classList.add('hidden'); return; }

        const editId  = document.getElementById('paymentTermModalMode').value === 'edit'
            ? parseInt(document.getElementById('paymentTermModalId').value, 10) : null;
        const others  = _terms.filter(t => t.id !== editId);
        const amount  = modalAmount();
        const revenue = currentRevenue();
        const total   = others.reduce((s, t) => s + (Number(t.amount) || 0), 0) + amount;
        const msgs    = [];

        if (revenue <= 0 && amount > 0) {
            msgs.push('Revenue in Sales Data is still empty, so this amount cannot be checked against it.');
        } else if (total > revenue + 0.01) {
            msgs.push(`Total payment terms will be ${fmtRp(total)}, exceeding the revenue in Sales Data (${fmtRp(revenue)}) by ${fmtRp(total - revenue)}.`);
        }

        const li = lineItemById(document.getElementById('pt_line_item')?.value);
        if (li) {
            const billed = others.filter(t => t.contract_line_item_id === li.id).reduce((s, t) => s + (Number(t.amount) || 0), 0) + amount;
            if (billed > li.total + 0.01) {
                msgs.push(`Terms for "${li.name}" will be ${fmtRp(billed)}, exceeding its contract value (${fmtRp(li.total)}).`);
            }
        }

        box.classList.toggle('hidden', !msgs.length);
        box.innerHTML = msgs.map(m => `<p>⚠ ${esc(m)}</p>`).join('') + (msgs.length ? '<p class="mt-1 text-amber-700/80">You can still save — this is only a notification.</p>' : '');
    }

    // Toggle indikator "wajib" pada Invoice Number sesuai isi Submit Invoice Date
    function toggleInvoiceRequired() {
        const hasDate = !!document.getElementById('pt_submit_invoice_date').value;
        const req  = document.getElementById('pt_invoice_number_req');
        const hint = document.getElementById('pt_invoice_number_hint');
        if (req)  req.classList.toggle('hidden', !hasDate);
        if (hint) hint.classList.toggle('hidden', !hasDate);
    }

    // Toggle indikator "wajib" sesuai nilai Status:
    //   Paid     → Paid Date wajib
    //   Invoiced → Submit Invoice Date wajib (invoice sudah dikirim)
    function togglePaidDateRequired() {
        const status = document.getElementById('pt_status').value;
        const isPaid = status === 'Paid';
        const req  = document.getElementById('pt_paid_date_req');
        const hint = document.getElementById('pt_paid_date_hint');
        if (req)  req.classList.toggle('hidden', !isPaid);
        if (hint) hint.classList.toggle('hidden', !isPaid);

        const isInvoiced = status === 'Invoiced';
        document.getElementById('pt_submit_invoice_req')?.classList.toggle('hidden', !isInvoiced);
        document.getElementById('pt_submit_invoice_hint')?.classList.toggle('hidden', !isInvoiced);
    }

    // ── Modal helpers ──────────────────────────────────────────────
    function resetForm() {
        document.getElementById('pt_payment_term').value        = '';
        document.getElementById('pt_payment_percentage').value  = '';
        document.getElementById('pt_amount_disp').value         = '';
        document.getElementById('pt_period').value              = '';
        document.getElementById('pt_requirements').value        = '';
        document.getElementById('pt_status').value              = 'Open';
        document.getElementById('pt_estimated_date').value      = '';
        document.getElementById('pt_submit_invoice_date').value = '';
        document.getElementById('pt_invoice_number').value      = '';
        document.getElementById('pt_paid_date').value           = '';
        if (window._fpSupPtEstimated)     window._fpSupPtEstimated.clear();
        if (window._fpSupPtSubmitInvoice) window._fpSupPtSubmitInvoice.clear();
        if (window._fpSupPtPaid)          window._fpSupPtPaid.clear();

        _legacyPct = false;
        const lineMode = _mode === 'line_item';
        document.getElementById('pt_line_item_wrap').classList.toggle('hidden', !lineMode);
        document.getElementById('pt_period_wrap').classList.toggle('hidden', !lineMode);
        document.getElementById('pt_over_warning').classList.add('hidden');
        populateLineItemSelect(null);

        toggleInvoiceRequired();
        togglePaidDateRequired();
    }

    // lineItemId (opsional) → dibuka dari tombol "Add Term" pada baris line item.
    function openAdd(lineItemId) {
        // Amount diambil dari Contract Line Item → line item wajib ada dulu.
        if (_mode === 'line_item' && !_lineItems.length) {
            showNotification('Add a contract line item first — payment terms in Contract Line Item mode must be linked to one.', 'warning');
            openAddLineItem();
            return;
        }

        document.getElementById('paymentTermModalMode').value  = 'create';
        document.getElementById('paymentTermModalId').value    = '';
        resetForm();
        document.getElementById('paymentTermModalTitle').textContent = 'Add Payment Term';
        if (lineItemId) populateLineItemSelect(lineItemId);
        setBasis(_mode === 'line_item' ? 'line_item' : 'percentage');
        if (lineItemId) onLineItemChange();
        document.getElementById('paymentTermModal').classList.remove('hidden');
    }

    function openEdit(id) {
        const t = _terms.find(x => x.id === id);
        if (!t) return;
        document.getElementById('paymentTermModalMode').value  = 'edit';
        document.getElementById('paymentTermModalId').value    = id;
        resetForm();
        document.getElementById('paymentTermModalTitle').textContent = `Edit Payment Term #${t.term_number}`;

        populateLineItemSelect(t.contract_line_item_id);
        document.getElementById('pt_payment_term').value       = t.payment_term ?? '';
        document.getElementById('pt_period').value             = t.period ?? '';
        document.getElementById('pt_requirements').value       = t.requirements ?? '';
        document.getElementById('pt_invoice_number').value     = t.invoice_number ?? '';
        document.getElementById('pt_status').value             = t.status ?? 'Open';

        if (t.estimated_date && window._fpSupPtEstimated) window._fpSupPtEstimated.setDate(t.estimated_date, false, 'Y-m-d');
        else if (t.estimated_date) document.getElementById('pt_estimated_date').value = t.estimated_date;

        if (t.submit_invoice_date && window._fpSupPtSubmitInvoice) window._fpSupPtSubmitInvoice.setDate(t.submit_invoice_date, false, 'Y-m-d');
        else if (t.submit_invoice_date) document.getElementById('pt_submit_invoice_date').value = t.submit_invoice_date;

        if (t.paid_date && window._fpSupPtPaid) window._fpSupPtPaid.setDate(t.paid_date, false, 'Y-m-d');
        else if (t.paid_date) document.getElementById('pt_paid_date').value = t.paid_date;

        // Termin lama basis % of Revenue di Type Contract Line Item tetap boleh diedit apa adanya.
        _legacyPct = _mode === 'line_item' && t.basis === 'percentage';
        setBasis(t.basis === 'fixed' || t.basis === 'line_item' ? t.basis : 'percentage');
        if (t.basis === 'fixed') {
            document.getElementById('pt_amount_disp').value = fmtThousands(t.amount);
        } else {
            document.getElementById('pt_payment_percentage').value = t.payment_percentage ?? '';
        }
        recalcAmount();

        toggleInvoiceRequired();
        togglePaidDateRequired();
        checkOver();
        document.getElementById('paymentTermModal').classList.remove('hidden');
    }

    function closeModal() {
        document.getElementById('paymentTermModal').classList.add('hidden');
    }

    // ── Save (create / update) ─────────────────────────────────────
    async function save() {
        const mode   = document.getElementById('paymentTermModalMode').value;
        const term   = document.getElementById('pt_payment_term').value.trim();
        const pct    = document.getElementById('pt_payment_percentage').value;
        const fixed  = _basis === 'fixed';
        const amount = parseAmount(document.getElementById('pt_amount_disp').value);
        const lineId = document.getElementById('pt_line_item').value;

        const submitInvoiceDate = document.getElementById('pt_submit_invoice_date').value || null;
        const invoiceNumber     = document.getElementById('pt_invoice_number').value.trim();
        const paidDate          = document.getElementById('pt_paid_date').value || null;
        const status            = document.getElementById('pt_status').value;

        if (_mode === 'line_item' && !lineId) { showNotification('Line Item is required in Contract Line Item mode.', 'error'); return; }
        if (!term) { showNotification('Payment Term is required.', 'error'); return; }
        if (fixed) {
            if (amount === null) { showNotification('Amount is required for a fixed-amount term.', 'error'); return; }
        } else {
            if (pct === '' || isNaN(parseFloat(pct))) { showNotification('Payment % is required.', 'error'); return; }
            if (parseFloat(pct) < 0 || parseFloat(pct) > 100) { showNotification('Payment % must be between 0 and 100.', 'error'); return; }
        }
        if (status === 'Invoiced' && !submitInvoiceDate) { showNotification('Submit Invoice Date is required when Status is Invoiced.', 'error'); return; }
        if (submitInvoiceDate && !invoiceNumber) { showNotification('Invoice Number is required when Submit Invoice Date is filled.', 'error'); return; }
        if (status === 'Paid' && !paidDate) { showNotification('Paid Date is required when Status is Paid.', 'error'); return; }

        // Guard: total termin basis % of Revenue tidak boleh melebihi 100% / revenue.
        // Basis line item tidak diblokir — hanya diperingatkan (lihat checkOver()).
        const editId = mode === 'edit' ? parseInt(document.getElementById('paymentTermModalId').value, 10) : null;
        if (_basis === 'percentage') {
            const otherPct = _terms.reduce((s, t) => (t.id === editId || t.basis !== 'percentage' ? s : s + (Number(t.payment_percentage) || 0)), 0);
            const totalPct = otherPct + parseFloat(pct);
            if (totalPct > 100 + 0.001) {
                const rev      = currentRevenue();
                const pctLabel = (Number.isInteger(totalPct) ? totalPct.toString() : totalPct.toFixed(2).replace('.', ',')) + '%';
                showNotification(`Total payment terms (${pctLabel} = ${fmtRp(rev * totalPct / 100)}) cannot exceed the support revenue (${fmtRp(rev)}).`, 'error');
                return;
            }
        }

        const btn = document.getElementById('paymentTermSaveBtn');
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = spinner();

        const payload = {
            basis:                 _basis,
            contract_line_item_id: _mode === 'line_item' ? parseInt(lineId, 10) : null,
            payment_term:          term,
            period:                _mode === 'line_item' ? (document.getElementById('pt_period').value.trim() || null) : null,
            payment_percentage:    fixed ? null : parseFloat(pct),
            amount:                fixed ? amount : null,
            requirements:          document.getElementById('pt_requirements').value.trim() || null,
            estimated_date:        document.getElementById('pt_estimated_date').value || null,
            submit_invoice_date:   submitInvoiceDate,
            invoice_number:        invoiceNumber || null,
            paid_date:             paidDate,
            status:                status,
            _token:                getCsrf(),
        };

        try {
            let res;
            if (mode === 'create') {
                res = await axios.post(BASE_URL, payload);
            } else {
                res = await axios.put(`${BASE_URL}/${editId}`, payload);
            }
            showNotification(res.data.message ?? 'Saved.', 'success');
            notifyWarnings(res.data.warnings);
            closeModal();
            await load();
        } catch (e) {
            showNotification(errMsg(e), 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = orig;
        }
    }

    // ── Delete ─────────────────────────────────────────────────────
    function openDeleteModal(id, number) {
        document.getElementById('ptDeleteId').value = id;
        document.getElementById('ptDeleteNumber').textContent = number ?? '';
        document.getElementById('paymentTermDeleteModal').classList.remove('hidden');
    }

    function closeDeleteModal() {
        document.getElementById('paymentTermDeleteModal').classList.add('hidden');
    }

    async function confirmDelete() {
        const id = document.getElementById('ptDeleteId').value;
        if (!id) return;

        const btn  = document.getElementById('ptDeleteConfirmBtn');
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = 'Deleting…';

        try {
            const res = await axios.post(`${BASE_URL}/${id}/delete`, {}, {
                headers: { 'X-CSRF-TOKEN': getCsrf() },
            });
            closeDeleteModal();
            showNotification(res.data.message ?? 'Deleted.', 'success');
            await load();
        } catch (e) {
            showNotification(e.response?.data?.message ?? 'Failed to delete.', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = orig;
        }
    }

    // ── Contract Line Item ─────────────────────────────────────────
    function setPicker(fp, inputId, value) {
        if (fp) { value ? fp.setDate(value, false, 'Y-m-d') : fp.clear(); }
        else document.getElementById(inputId).value = value || '';
    }

    function onLineItemTypeChange() {
        const type      = document.getElementById('li_type').value;
        const recurring = type === 'recurring';
        const milestone = type === 'milestone';
        document.getElementById('li_frequency_wrap').classList.toggle('hidden', !recurring);
        document.getElementById('li_end_wrap').classList.toggle('hidden', !recurring);
        // Milestone: jadwal ditentukan per termin, jadi tanpa bulan tagih.
        document.getElementById('li_start_wrap').classList.toggle('hidden', milestone);
        document.getElementById('li_start_req').classList.toggle('hidden', !recurring);
        document.getElementById('li_start_label').textContent  = recurring ? 'Start' : 'Billing Month';
        document.getElementById('li_amount_label').textContent = recurring ? 'Nominal per period' : (milestone ? 'Contract value' : 'Nominal');
        previewLineItem();
    }

    function previewLineItem() {
        const box = document.getElementById('li_preview');
        if (!box) return;
        const amount = parseAmount(document.getElementById('li_amount').value) || 0;

        if (document.getElementById('li_type').value === 'milestone') {
            box.textContent = `Contract value: ${fmtRp(amount)} — billed in several terms (milestones). Add each term with "Payment %" of this line item.`;
            return;
        }
        if (document.getElementById('li_type').value !== 'recurring') {
            box.textContent = `Contract value: ${fmtRp(amount)} (billed once).`;
            return;
        }
        const periods = buildPeriods(
            document.getElementById('li_frequency').value,
            document.getElementById('li_start_date').value,
            document.getElementById('li_end_date').value
        );
        box.textContent = periods.length
            ? `${periods.length} period(s): ${periods[0]} to ${periods[periods.length - 1]} × ${fmtRp(amount)} = ${fmtRp(amount * periods.length)}`
            : 'Fill in the start and end date to see the schedule.';
    }

    function openAddLineItem() {
        document.getElementById('li_id').value = '';
        document.getElementById('lineItemModalTitle').textContent = 'Add Line Item';
        document.getElementById('li_name').value = '';
        document.getElementById('li_type').value = 'one_time';
        document.getElementById('li_frequency').value = 'monthly';
        document.getElementById('li_amount').value = '';
        setPicker(window._fpSupLiStart, 'li_start_date', null);
        setPicker(window._fpSupLiEnd, 'li_end_date', null);
        onLineItemTypeChange();
        document.getElementById('lineItemModal').classList.remove('hidden');
    }

    function openEditLineItem(id) {
        const li = lineItemById(id);
        if (!li) return;
        document.getElementById('li_id').value = li.id;
        document.getElementById('lineItemModalTitle').textContent = `Edit Line Item: ${li.name}`;
        document.getElementById('li_name').value = li.name;
        document.getElementById('li_type').value = li.type;
        document.getElementById('li_frequency').value = li.frequency || 'monthly';
        document.getElementById('li_amount').value = fmtThousands(li.amount);
        setPicker(window._fpSupLiStart, 'li_start_date', li.start_date);
        setPicker(window._fpSupLiEnd, 'li_end_date', li.end_date);
        onLineItemTypeChange();
        document.getElementById('lineItemModal').classList.remove('hidden');
    }

    function closeLineItemModal() {
        document.getElementById('lineItemModal').classList.add('hidden');
    }

    async function saveLineItem() {
        const id        = document.getElementById('li_id').value;
        const name      = document.getElementById('li_name').value.trim();
        const type      = document.getElementById('li_type').value;
        const amount    = parseAmount(document.getElementById('li_amount').value);
        const startDate = document.getElementById('li_start_date').value || null;
        const endDate   = document.getElementById('li_end_date').value || null;

        if (!name) { showNotification('Line Item name is required.', 'error'); return; }
        if (amount === null) { showNotification('Nominal is required.', 'error'); return; }
        if (type === 'recurring') {
            if (!startDate || !endDate) { showNotification('Start and End are required for a recurring line item.', 'error'); return; }
            if (endDate < startDate) { showNotification('End must be on or after Start.', 'error'); return; }
        }

        const btn  = document.getElementById('lineItemSaveBtn');
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = spinner();

        const payload = {
            name, type, amount,
            frequency:  type === 'recurring' ? document.getElementById('li_frequency').value : null,
            start_date: type === 'milestone' ? null : startDate,
            end_date:   type === 'recurring' ? endDate : null,
            _token:     getCsrf(),
        };

        try {
            const res = await axios.post(id ? `${LINE_URL}/${id}` : LINE_URL, payload);
            showNotification(res.data.message ?? 'Saved.', 'success');
            notifyWarnings(res.data.warnings);
            closeLineItemModal();
            await load();
        } catch (e) {
            showNotification(errMsg(e), 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = orig;
        }
    }

    function openLineItemDelete(id) {
        const li = lineItemById(id);
        if (!li) return;
        if (li.terms_count > 0) {
            showNotification(`"${li.name}" still has ${li.terms_count} payment term(s). Delete those payment terms first.`, 'warning');
            return;
        }
        document.getElementById('liDeleteId').value = li.id;
        document.getElementById('liDeleteName').textContent = li.name;
        document.getElementById('lineItemDeleteModal').classList.remove('hidden');
    }

    function closeLineItemDeleteModal() {
        document.getElementById('lineItemDeleteModal').classList.add('hidden');
    }

    async function confirmDeleteLineItem() {
        const id = document.getElementById('liDeleteId').value;
        if (!id) return;
        const btn  = document.getElementById('liDeleteConfirmBtn');
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = 'Deleting…';
        try {
            const res = await axios.post(`${LINE_URL}/${id}/delete`, {}, { headers: { 'X-CSRF-TOKEN': getCsrf() } });
            closeLineItemDeleteModal();
            showNotification(res.data.message ?? 'Deleted.', 'success');
            await load();
        } catch (e) {
            showNotification(errMsg(e, 'Failed to delete.'), 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = orig;
        }
    }

    // ── Generate Schedule (line item recurring) ────────────────────
    function generateInputs() {
        return {
            amount:    parseAmount(document.getElementById('gs_amount').value),
            frequency: document.getElementById('gs_frequency').value,
            start:     document.getElementById('gs_start_date').value,
            end:       document.getElementById('gs_end_date').value,
        };
    }

    function previewGenerate() {
        const li  = lineItemById(document.getElementById('gs_id').value);
        const box = document.getElementById('gs_preview');
        const btn = document.getElementById('generateScheduleBtn');
        if (!li || !box) return;

        const { amount, frequency, start, end } = generateInputs();
        const periods  = buildPeriods(frequency, start, end);
        const existing = new Set(_terms.filter(t => t.contract_line_item_id === li.id && t.period)
            .map(t => t.period.toLowerCase()));
        const fresh    = periods.filter(p => !existing.has(p.toLowerCase()));
        const skipped  = periods.length - fresh.length;

        const ok = fresh.length > 0 && amount !== null;
        box.className = 'rounded-lg border px-3 py-2 text-sm ' + (ok ? 'border-green-200 bg-green-50 text-green-800' : 'border-gray-200 bg-gray-50 text-gray-600');

        if (!periods.length) {
            box.textContent = 'Fill in the start and end date to see the schedule.';
        } else if (!fresh.length) {
            box.textContent = `All ${periods.length} period(s) already have a payment term — nothing to generate.`;
        } else {
            box.textContent = `Will create ${fresh.length} payment term(s) (${li.name} – ${fresh[0]} to ${li.name} – ${fresh[fresh.length - 1]}), total ${fmtRp((amount || 0) * fresh.length)}.`
                + (skipped ? ` ${skipped} existing period(s) will be skipped.` : '');
        }

        btn.disabled = !ok;
        btn.textContent = ok ? `Generate ${fresh.length} term${fresh.length > 1 ? 's' : ''}` : 'Generate';
    }

    function openGenerate(id) {
        const li = lineItemById(id);
        if (!li) return;
        document.getElementById('gs_id').value = li.id;
        document.getElementById('gs_title').textContent = li.name;
        document.getElementById('gs_amount').value = fmtThousands(li.amount);
        document.getElementById('gs_frequency').value = li.frequency || 'monthly';
        document.getElementById('gs_requirements').value = '';
        setPicker(window._fpSupGsStart, 'gs_start_date', li.start_date);
        setPicker(window._fpSupGsEnd, 'gs_end_date', li.end_date);
        previewGenerate();
        document.getElementById('generateScheduleModal').classList.remove('hidden');
    }

    function closeGenerateModal() {
        document.getElementById('generateScheduleModal').classList.add('hidden');
    }

    async function confirmGenerate() {
        const id = document.getElementById('gs_id').value;
        const { amount, frequency, start, end } = generateInputs();
        if (amount === null || !start || !end) { showNotification('Nominal, Start and End are required.', 'error'); return; }
        if (end < start) { showNotification('End must be on or after Start.', 'error'); return; }

        const btn  = document.getElementById('generateScheduleBtn');
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = spinner();

        try {
            const res = await axios.post(`${LINE_URL}/${id}/generate-schedule`, {
                amount, frequency, start_date: start, end_date: end,
                requirements: document.getElementById('gs_requirements').value.trim() || null,
                _token: getCsrf(),
            });
            showNotification(res.data.message ?? 'Schedule generated.', 'success');
            notifyWarnings(res.data.warnings);
            closeGenerateModal();
            await load();
        } catch (e) {
            showNotification(errMsg(e), 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = orig;
            previewGenerate();
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        // Date pickers — pakai HolidayCalendar (sama dengan Delivery Project):
        // header bulan statis, weekend + libur nasional merah & non-selectable.
        if (window.HolidayCalendar) {
            window.HolidayCalendar.load().then(function () {
                window._fpSupPtEstimated     = HolidayCalendar.initPicker(document.getElementById('pt_estimated_date'));
                window._fpSupPtSubmitInvoice = HolidayCalendar.initPicker(document.getElementById('pt_submit_invoice_date'), {
                    onChange: toggleInvoiceRequired,
                });
                window._fpSupPtPaid          = HolidayCalendar.initPicker(document.getElementById('pt_paid_date'));

                // TOP mode Line Item — jadwal Contract Line Item & Generate Schedule.
                // Jadwal kontrak boleh jatuh di hari libur (mis. 1 Jan) → jangan disable tanggal apa pun.
                const preview = (fn) => ({ disable: [], onChange: function () { fn(); } });
                window._fpSupLiStart = HolidayCalendar.initPicker(document.getElementById('li_start_date'), preview(previewLineItem));
                window._fpSupLiEnd   = HolidayCalendar.initPicker(document.getElementById('li_end_date'),   preview(previewLineItem));
                window._fpSupGsStart = HolidayCalendar.initPicker(document.getElementById('gs_start_date'), preview(previewGenerate));
                window._fpSupGsEnd   = HolidayCalendar.initPicker(document.getElementById('gs_end_date'),   preview(previewGenerate));
            });
        }
        renderMode();
        load();
    });

    return {
        openAdd, openEdit, closeModal, save, openDeleteModal, closeDeleteModal, confirmDelete,
        recalcAmount, toggleInvoiceRequired, togglePaidDateRequired, reload: load,
        switchMode, setBasis, onLineItemChange, onAmountInput, toggleGroup,
        openAddLineItem, openEditLineItem, closeLineItemModal, onLineItemTypeChange, previewLineItem, saveLineItem,
        openLineItemDelete, closeLineItemDeleteModal, confirmDeleteLineItem,
        openGenerate, closeGenerateModal, previewGenerate, confirmGenerate,
    };
})();
</script>

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
        else alert(msg);
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

    const SUPPORT_ID = {{ $support->id }};
    const BASE_URL   = `/delivery/support/${SUPPORT_ID}/payment-terms`;

    let _terms   = [];
    let _revenue = parseFloat('{{ $support->revenue ?? 0 }}') || 0;

    function getCsrf() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    function esc(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function fmtRp(n) {
        const num = Number(n) || 0;
        const neg = num < 0;
        const abs = Math.abs(Math.round(num));
        return 'Rp ' + (neg ? '-' : '') + abs.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

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
            'Open':  'bg-yellow-100 text-yellow-800',
            'Paid':  'bg-green-100 text-green-800',
            'Delay': 'bg-red-100 text-red-700',
        };
        const cls = map[status] ?? 'bg-gray-100 text-gray-700';
        return `<span class="px-2 py-0.5 rounded-full text-xs font-semibold ${cls}">${esc(status)}</span>`;
    }

    function fmtPct(p) {
        const num = Number(p) || 0;
        return (Number.isInteger(num) ? num.toString() : num.toFixed(2).replace('.', ',')) + '%';
    }

    async function load() {
        try {
            const res = await axios.get(BASE_URL);
            _terms = res.data.payment_terms ?? [];
            if (res.data.support_revenue !== undefined && res.data.support_revenue !== null) {
                _revenue = parseFloat(res.data.support_revenue) || _revenue;
            }
            renderTable();
        } catch (e) {
            const tbody = document.getElementById('supPaymentTermBody');
            if (tbody) tbody.innerHTML =
                `<tr><td colspan="11" class="text-center py-8 text-red-500 text-sm">Failed to load data. Please refresh.</td></tr>`;
        }
    }

    function renderTable() {
        const tbody = document.getElementById('supPaymentTermBody');
        if (!tbody) return;

        if (!_terms.length) {
            tbody.innerHTML = `<tr><td colspan="11" class="text-center py-8 text-gray-400 text-sm">No payment terms yet. Click "Add Payment Term" to get started.</td></tr>`;
        } else {
            tbody.innerHTML = _terms.map(t => rowHtml(t)).join('');
        }

        const totalPct = _terms.reduce((s, t) => s + (Number(t.payment_percentage) || 0), 0);
        const totalAmt = _terms.reduce((s, t) => s + (Number(t.amount) || 0), 0);
        const pctEl = document.getElementById('supPtTotalPct');
        const amtEl = document.getElementById('supPtTotalAmount');
        if (pctEl) {
            pctEl.textContent = fmtPct(totalPct);
            pctEl.className = 'px-3 py-3 text-center ' + (totalPct > 100 ? 'text-red-600' : 'text-gray-700');
        }
        if (amtEl) amtEl.textContent = fmtRp(totalAmt);
    }

    function rowHtml(t) {
        return `<tr class="hover:bg-gray-50 align-top">
            <td class="px-3 py-3 text-center text-xs font-mono text-gray-600">${t.term_number}</td>
            <td class="px-3 py-3 text-xs text-gray-800"><div class="line-clamp-3">${esc(t.payment_term)}</div></td>
            <td class="px-3 py-3 text-center text-xs font-semibold text-gray-700">${fmtPct(t.payment_percentage)}</td>
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

    function recalcAmount() {
        const pct = parseFloat(document.getElementById('pt_payment_percentage').value);
        const amount = (isNaN(pct) ? 0 : currentRevenue() * pct / 100);
        const disp = document.getElementById('pt_amount_disp');
        if (disp) {
            const abs = Math.abs(Math.round(amount));
            disp.value = abs.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }
    }

    function toggleInvoiceRequired() {
        const hasDate = !!document.getElementById('pt_submit_invoice_date').value;
        const req  = document.getElementById('pt_invoice_number_req');
        const hint = document.getElementById('pt_invoice_number_hint');
        if (req)  req.classList.toggle('hidden', !hasDate);
        if (hint) hint.classList.toggle('hidden', !hasDate);
    }

    function togglePaidDateRequired() {
        const isPaid = document.getElementById('pt_status').value === 'Paid';
        const req  = document.getElementById('pt_paid_date_req');
        const hint = document.getElementById('pt_paid_date_hint');
        if (req)  req.classList.toggle('hidden', !isPaid);
        if (hint) hint.classList.toggle('hidden', !isPaid);
    }

    function resetForm() {
        document.getElementById('pt_payment_term').value        = '';
        document.getElementById('pt_payment_percentage').value  = '';
        document.getElementById('pt_amount_disp').value         = '';
        document.getElementById('pt_requirements').value        = '';
        document.getElementById('pt_status').value              = 'Open';
        document.getElementById('pt_estimated_date').value      = '';
        document.getElementById('pt_submit_invoice_date').value = '';
        document.getElementById('pt_invoice_number').value      = '';
        document.getElementById('pt_paid_date').value           = '';
        if (window._fpSupPtEstimated)     window._fpSupPtEstimated.clear();
        if (window._fpSupPtSubmitInvoice) window._fpSupPtSubmitInvoice.clear();
        if (window._fpSupPtPaid)          window._fpSupPtPaid.clear();
        toggleInvoiceRequired();
        togglePaidDateRequired();
    }

    function openAdd() {
        resetForm();
        document.getElementById('paymentTermModalMode').value  = 'create';
        document.getElementById('paymentTermModalId').value    = '';
        document.getElementById('paymentTermModalTitle').textContent = 'Add Payment Term';
        document.getElementById('paymentTermModal').classList.remove('hidden');
    }

    function openEdit(id) {
        const t = _terms.find(x => x.id === id);
        if (!t) return;
        resetForm();
        document.getElementById('paymentTermModalMode').value  = 'edit';
        document.getElementById('paymentTermModalId').value    = id;
        document.getElementById('paymentTermModalTitle').textContent = `Edit Payment Term #${t.term_number}`;

        document.getElementById('pt_payment_term').value       = t.payment_term ?? '';
        document.getElementById('pt_payment_percentage').value = t.payment_percentage ?? '';
        document.getElementById('pt_requirements').value       = t.requirements ?? '';
        document.getElementById('pt_invoice_number').value     = t.invoice_number ?? '';
        document.getElementById('pt_status').value             = t.status ?? 'Open';

        if (t.estimated_date && window._fpSupPtEstimated) window._fpSupPtEstimated.setDate(t.estimated_date, false, 'Y-m-d');
        else if (t.estimated_date) document.getElementById('pt_estimated_date').value = t.estimated_date;

        if (t.submit_invoice_date && window._fpSupPtSubmitInvoice) window._fpSupPtSubmitInvoice.setDate(t.submit_invoice_date, false, 'Y-m-d');
        else if (t.submit_invoice_date) document.getElementById('pt_submit_invoice_date').value = t.submit_invoice_date;

        if (t.paid_date && window._fpSupPtPaid) window._fpSupPtPaid.setDate(t.paid_date, false, 'Y-m-d');
        else if (t.paid_date) document.getElementById('pt_paid_date').value = t.paid_date;

        toggleInvoiceRequired();
        togglePaidDateRequired();
        recalcAmount();
        document.getElementById('paymentTermModal').classList.remove('hidden');
    }

    function closeModal() {
        document.getElementById('paymentTermModal').classList.add('hidden');
    }

    function notify(msg, type) {
        if (typeof window.showNotification === 'function') window.showNotification(msg, type);
        else if (typeof window.showToast === 'function') window.showToast(msg, type);
        else alert(msg);
    }

    async function save() {
        const mode = document.getElementById('paymentTermModalMode').value;
        const term = document.getElementById('pt_payment_term').value.trim();
        const pct  = document.getElementById('pt_payment_percentage').value;

        const submitInvoiceDate = document.getElementById('pt_submit_invoice_date').value || null;
        const invoiceNumber     = document.getElementById('pt_invoice_number').value.trim();
        const paidDate          = document.getElementById('pt_paid_date').value || null;
        const status            = document.getElementById('pt_status').value;

        if (!term) { notify('Payment Term is required.', 'error'); return; }
        if (pct === '' || isNaN(parseFloat(pct))) { notify('Payment % is required.', 'error'); return; }
        if (parseFloat(pct) < 0 || parseFloat(pct) > 100) { notify('Payment % must be between 0 and 100.', 'error'); return; }
        if (submitInvoiceDate && !invoiceNumber) { notify('Invoice Number is required when Submit Invoice Date is filled.', 'error'); return; }
        if (status === 'Paid' && !paidDate) { notify('Paid Date is required when Status is Paid.', 'error'); return; }

        const editId    = mode === 'edit' ? parseInt(document.getElementById('paymentTermModalId').value, 10) : null;
        const otherPct  = _terms.reduce((s, t) => (t.id === editId ? s : s + (Number(t.payment_percentage) || 0)), 0);
        const totalPct  = otherPct + parseFloat(pct);
        if (totalPct > 100 + 0.001) {
            const rev      = currentRevenue();
            const totalAmt = rev * totalPct / 100;
            const pctLabel = (Number.isInteger(totalPct) ? totalPct.toString() : totalPct.toFixed(2).replace('.', ',')) + '%';
            notify(`Total payment terms (${pctLabel} = ${fmtRp(totalAmt)}) cannot exceed the support revenue (${fmtRp(rev)}).`, 'error');
            return;
        }

        const btn = document.getElementById('paymentTermSaveBtn');
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<svg class="animate-spin w-4 h-4 mx-auto" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>';

        const payload = {
            payment_term:        term,
            payment_percentage:  parseFloat(pct),
            requirements:        document.getElementById('pt_requirements').value.trim() || null,
            estimated_date:      document.getElementById('pt_estimated_date').value || null,
            submit_invoice_date: submitInvoiceDate,
            invoice_number:      invoiceNumber || null,
            paid_date:           paidDate,
            status:              status,
            _token:              getCsrf(),
        };

        try {
            let res;
            if (mode === 'create') {
                res = await axios.post(BASE_URL, payload);
            } else {
                const id = document.getElementById('paymentTermModalId').value;
                res = await axios.put(`${BASE_URL}/${id}`, payload);
            }
            notify(res.data.message ?? 'Saved.', 'success');
            closeModal();
            await load();
        } catch (e) {
            let msg = 'Something went wrong. Please try again.';
            if (e.response?.data?.errors) {
                const first = Object.values(e.response.data.errors)[0];
                msg = Array.isArray(first) ? first[0] : String(first);
            } else if (e.response?.data?.message) {
                msg = e.response.data.message;
            }
            notify(msg, 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = orig;
        }
    }

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
            notify(res.data.message ?? 'Deleted.', 'success');
            await load();
        } catch (e) {
            notify(e.response?.data?.message ?? 'Failed to delete.', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = orig;
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
            });
        }
        load();
    });

    return { openAdd, openEdit, closeModal, save, openDeleteModal, closeDeleteModal, confirmDelete, recalcAmount, toggleInvoiceRequired, togglePaidDateRequired, reload: load };
})();
</script>

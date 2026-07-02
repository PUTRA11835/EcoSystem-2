@extends('dashboard')

@section('title', 'Holidays')
@section('page-title', 'Holidays')

@section('content')
<div class="bg-white rounded-xl p-6 shadow-sm">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 pb-4 border-b-2 border-gray-100">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Holidays</h2>
            <p class="text-sm text-gray-500 mt-1">Manage national holidays, collective leave, and custom holidays used by the system calendar &amp; date pickers.</p>
        </div>
        <button onclick="openCreateHolidayModal()" class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
            <i class="fas fa-plus mr-2"></i> Add Holiday
        </button>
    </div>

    <!-- Filters -->
    <div class="flex flex-col sm:flex-row gap-3 mb-4">
        <select id="yearFilter" onchange="loadHolidays()"
            class="w-full sm:w-44 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
            <option value="">All Years</option>
        </select>
        <input type="text" id="holidaySearch" placeholder="Search holiday name..."
            oninput="renderHolidays()"
            class="w-full sm:w-72 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
    </div>

    <!-- Table -->
    <div class="overflow-x-auto border border-gray-200 rounded-lg">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Day</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Name</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody id="holidaysTableBody" class="divide-y divide-gray-100">
                <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">Loading...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- ── Modal: Create / Edit Holiday ────────────────────────────────────────── -->
<div id="holidayModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md">
        <div class="flex justify-between items-center p-6 border-b border-gray-100">
            <h3 id="holidayModalTitle" class="text-lg font-bold text-gray-900">Add Holiday</h3>
            <button onclick="closeModal('holidayModal')" class="text-gray-400 hover:text-gray-600 transition-colors">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <form id="holidayForm" onsubmit="submitHoliday(event)" class="p-6 space-y-4">
            <input type="hidden" id="holidayId">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Date <span class="text-red-500">*</span></label>
                <input type="date" id="holidayDate" required
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Name <span class="text-red-500">*</span></label>
                <input type="text" id="holidayName" required maxlength="255"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent"
                    placeholder="e.g. Independence Day">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Type <span class="text-red-500">*</span></label>
                <select id="holidayType" required
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
                    <option value="national">National Holiday</option>
                    <option value="cuti_bersama">Collective Leave</option>
                    <option value="custom">Custom Holiday</option>
                </select>
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" id="holidayActive" checked
                    class="w-4 h-4 rounded cursor-pointer accent-red-800">
                <label for="holidayActive" class="text-sm text-gray-700 cursor-pointer">Active (applied to calendar &amp; date pickers)</label>
            </div>
            <div class="flex gap-3 justify-end pt-2">
                <button type="button" onclick="closeModal('holidayModal')" class="px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">Cancel</button>
                <button type="submit" class="px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Modal: Custom Confirm ───────────────────────────────────────────────── -->
<div id="confirmModal" class="hidden fixed inset-0 z-[60] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm">
        <div class="p-6 text-center">
            <div id="confirmIconWrap" class="w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-4">
                <i id="confirmIcon" class="text-xl"></i>
            </div>
            <h3 id="confirmTitle" class="text-base font-semibold text-gray-800 mb-2"></h3>
            <p id="confirmMessage" class="text-sm text-gray-500 mb-6 leading-relaxed"></p>
            <div class="flex gap-3">
                <button id="confirmCancelBtn" class="flex-1 border border-gray-200 rounded-xl py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-50 transition">Cancel</button>
                <button id="confirmOkBtn" class="flex-1 rounded-xl py-2.5 text-sm font-semibold text-white transition">Confirm</button>
            </div>
        </div>
    </div>
</div>

<script>
let holidaysData = [];

const TYPE_BADGE = {
    national:     { label: 'National Holiday', cls: 'bg-red-50 text-red-700 border border-red-200' },
    cuti_bersama: { label: 'Collective Leave', cls: 'bg-amber-50 text-amber-700 border border-amber-200' },
    custom:       { label: 'Custom Holiday',   cls: 'bg-blue-50 text-blue-700 border border-blue-200' },
};
const DAY_NAMES = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

// ── Load ─────────────────────────────────────────────────────────────────────

async function loadHolidays() {
    const year = document.getElementById('yearFilter').value;
    const url  = year ? `/api/management/holidays?year=${year}` : '/api/management/holidays';
    const res  = await fetch(url);
    const json = await res.json();
    holidaysData = json.data || [];
    populateYearFilter(json.years || []);
    renderHolidays();
}

function populateYearFilter(years) {
    const sel = document.getElementById('yearFilter');
    const current = sel.value;
    // Ensure the current year is always an available option
    const nowY = new Date().getFullYear();
    const set = new Set(years.map(Number));
    set.add(nowY);
    const sorted = [...set].sort((a, b) => b - a);
    sel.innerHTML = '<option value="">All Years</option>' +
        sorted.map(y => `<option value="${y}">${y}</option>`).join('');
    sel.value = current;
}

// ── Render ────────────────────────────────────────────────────────────────────

function renderHolidays() {
    const tbody = document.getElementById('holidaysTableBody');
    const query = (document.getElementById('holidaySearch')?.value || '').toLowerCase().trim();
    const filtered = query
        ? holidaysData.filter(h => h.name.toLowerCase().includes(query))
        : holidaysData;

    if (!filtered.length) {
        tbody.innerHTML = `<tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">${query ? 'No holidays match your search.' : 'No holidays yet. Click "Add Holiday".'}</td></tr>`;
        return;
    }

    tbody.innerHTML = filtered.map(h => {
        const badge = TYPE_BADGE[h.type] || { label: h.type, cls: 'bg-gray-100 text-gray-700 border border-gray-200' };
        const d = new Date(h.date + 'T00:00:00');
        const dayName = DAY_NAMES[d.getDay()];
        const dateLabel = d.toLocaleDateString('en-GB', { day: '2-digit', month: 'long', year: 'numeric' });
        return `
        <tr class="hover:bg-gray-50 transition-colors ${h.is_active ? '' : 'opacity-50'}">
            <td class="px-4 py-3 font-medium text-gray-900 whitespace-nowrap">${dateLabel}</td>
            <td class="px-4 py-3 text-gray-500">${dayName}</td>
            <td class="px-4 py-3 text-gray-700">${escHtml(h.name)}</td>
            <td class="px-4 py-3"><span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold ${badge.cls}">${badge.label}</span></td>
            <td class="px-4 py-3">
                ${h.is_active
                    ? '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-green-50 text-green-700">Active</span>'
                    : '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-500">Inactive</span>'}
            </td>
            <td class="px-4 py-3">
                <div class="flex items-center gap-2">
                    <button onclick="openEditHolidayModal(${h.id})"
                        class="px-3 py-1.5 text-xs font-semibold bg-gray-100 text-gray-700 border border-gray-200 rounded-lg hover:bg-gray-200 transition-colors">
                        <i class="fas fa-pen mr-1"></i> Edit
                    </button>
                    <button onclick="deleteHoliday(${h.id})"
                        class="px-3 py-1.5 text-xs font-semibold bg-red-50 text-red-700 border border-red-200 rounded-lg hover:bg-red-100 transition-colors">
                        <i class="fas fa-trash mr-1"></i> Delete
                    </button>
                </div>
            </td>
        </tr>`;
    }).join('');
}

// ── Create / Edit ──────────────────────────────────────────────────────────────

function openCreateHolidayModal() {
    document.getElementById('holidayModalTitle').textContent = 'Add Holiday';
    document.getElementById('holidayId').value = '';
    document.getElementById('holidayDate').value = '';
    document.getElementById('holidayName').value = '';
    document.getElementById('holidayType').value = 'national';
    document.getElementById('holidayActive').checked = true;
    openModal('holidayModal');
}

function openEditHolidayModal(id) {
    const h = holidaysData.find(x => x.id === id);
    if (!h) return;
    document.getElementById('holidayModalTitle').textContent = 'Edit Holiday';
    document.getElementById('holidayId').value = h.id;
    document.getElementById('holidayDate').value = h.date;
    document.getElementById('holidayName').value = h.name;
    document.getElementById('holidayType').value = h.type;
    document.getElementById('holidayActive').checked = !!h.is_active;
    openModal('holidayModal');
}

async function submitHoliday(e) {
    e.preventDefault();
    const id = document.getElementById('holidayId').value;
    const payload = {
        date:      document.getElementById('holidayDate').value,
        name:      document.getElementById('holidayName').value.trim(),
        type:      document.getElementById('holidayType').value,
        is_active: document.getElementById('holidayActive').checked,
    };
    const url    = id ? `/api/management/holidays/${id}` : '/api/management/holidays';
    const method = id ? 'PUT' : 'POST';

    const res  = await fetch(url, { method, headers: jsonHeaders(), body: JSON.stringify(payload) });
    const json = await res.json();

    if (json.success) {
        closeModal('holidayModal');
        showToast(id ? 'Holiday updated successfully.' : 'Holiday added successfully.', 'success');
        loadHolidays();
    } else {
        showToast(json.message || 'Failed to save holiday.', 'error');
    }
}

async function deleteHoliday(id) {
    const h = holidaysData.find(x => x.id === id);
    const ok = await customConfirm({
        title:     'Delete Holiday?',
        message:   `You are about to delete "${h?.name ?? ''}" (${h?.date ?? ''}). This action cannot be undone.`,
        okLabel:   'Yes, Delete',
        okClass:   'bg-red-600 hover:bg-red-700',
        icon:      'fas fa-trash',
        iconBg:    'bg-red-50',
        iconColor: 'text-red-500',
    });
    if (!ok) return;
    const res  = await fetch(`/api/management/holidays/${id}`, { method: 'DELETE', headers: jsonHeaders() });
    const json = await res.json();
    if (json.success) {
        showToast('Holiday deleted successfully.', 'success');
        loadHolidays();
    } else {
        showToast(json.message || 'Failed to delete holiday.', 'error');
    }
}

// ── Custom Confirm ──────────────────────────────────────────────────────────────

function customConfirm({ title, message, okLabel = 'Confirm', okClass = 'bg-red-600 hover:bg-red-700', icon = 'fas fa-exclamation-triangle', iconBg = 'bg-red-50', iconColor = 'text-red-500' } = {}) {
    return new Promise(resolve => {
        const modal     = document.getElementById('confirmModal');
        const okBtn     = document.getElementById('confirmOkBtn');
        const cancelBtn = document.getElementById('confirmCancelBtn');
        document.getElementById('confirmTitle').textContent   = title || '';
        document.getElementById('confirmMessage').textContent = message || '';
        okBtn.textContent  = okLabel;
        okBtn.className    = `flex-1 rounded-xl py-2.5 text-sm font-semibold text-white transition ${okClass}`;
        document.getElementById('confirmIconWrap').className = `w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-4 ${iconBg}`;
        document.getElementById('confirmIcon').className     = `${icon} text-xl ${iconColor}`;
        modal.classList.remove('hidden');
        function done(val) {
            modal.classList.add('hidden');
            okBtn.removeEventListener('click', onOk);
            cancelBtn.removeEventListener('click', onCancel);
            resolve(val);
        }
        const onOk     = () => done(true);
        const onCancel = () => done(false);
        okBtn.addEventListener('click', onOk);
        cancelBtn.addEventListener('click', onCancel);
    });
}

// ── Utilities ─────────────────────────────────────────────────────────────────

function openModal(id)  { document.getElementById(id).classList.remove('hidden'); }
function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

function jsonHeaders() {
    return {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
    };
}

function escHtml(str) {
    const d = document.createElement('div');
    d.textContent = str ?? '';
    return d.innerHTML;
}

document.getElementById('holidayModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal('holidayModal');
});

// Init
document.getElementById('yearFilter').value = '';
loadHolidays();
</script>
@endsection

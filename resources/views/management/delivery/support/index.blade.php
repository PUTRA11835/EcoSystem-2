@extends('dashboard')

@section('title', 'Support Type')
@section('page-title', 'Support Type')

@section('content')
<div class="bg-white rounded-xl p-6 shadow-sm">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 pb-4 border-b-2 border-gray-100">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Support Type</h2>
            <p class="text-sm text-gray-500 mt-1">Manage the list of support types available on the Delivery Support create/edit form.</p>
        </div>
        <button onclick="openCreateTypeModal()" class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
            <i class="fas fa-plus mr-2"></i> Add Support Type
        </button>
    </div>

    <!-- Filters -->
    <div class="flex flex-col sm:flex-row gap-3 mb-4">
        <input type="text" id="typeSearch" placeholder="Search support type name..."
            oninput="renderTypes()"
            class="w-full sm:w-72 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
        <select id="statusFilter" onchange="renderTypes()"
            class="w-full sm:w-44 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
            <option value="">All Status</option>
            <option value="1">Active</option>
            <option value="0">Inactive</option>
        </select>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto border border-gray-200 rounded-lg">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider w-12">No</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Support Type</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Description</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider w-28">Status</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider w-28">Actions</th>
                </tr>
            </thead>
            <tbody id="typesTableBody" class="divide-y divide-gray-100">
                <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">Loading...</td></tr>
            </tbody>
        </table>
    </div>
    <div class="mt-3 text-xs text-gray-400" id="typeCount"></div>
</div>

<!-- ── Modal: Create / Edit Support Type ──────────────────────────────────── -->
<div id="typeModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md max-h-[90vh] flex flex-col">
        <div class="flex justify-between items-center p-6 border-b border-gray-100">
            <h3 id="typeModalTitle" class="text-lg font-bold text-gray-900">Add Support Type</h3>
            <button onclick="closeModal('typeModal')" class="text-gray-400 hover:text-gray-600 transition-colors">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <form id="typeForm" onsubmit="submitType(event)" class="p-6 space-y-4 overflow-y-auto">
            <input type="hidden" id="typeId">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Support Type Name <span class="text-red-500">*</span></label>
                <input type="text" id="typeName" required maxlength="100"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent"
                    placeholder="e.g. AMS, MO, RISE">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Description</label>
                <textarea id="typeDescription" maxlength="255" rows="2"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent"
                    placeholder="Optional"></textarea>
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" id="typeActive" checked
                    class="w-4 h-4 rounded cursor-pointer accent-red-800">
                <label for="typeActive" class="text-sm text-gray-700 cursor-pointer">Active</label>
            </div>
            <div class="flex gap-3 justify-end pt-2">
                <button type="button" onclick="closeModal('typeModal')" class="px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">Cancel</button>
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
let typesData = [];

// ── Load ─────────────────────────────────────────────────────────────────────

async function loadTypes() {
    const res  = await fetch('/api/delivery-support-types');
    const json = await res.json();
    typesData = json.data || [];
    renderTypes();
}

// ── Render ────────────────────────────────────────────────────────────────────

function renderTypes() {
    const tbody  = document.getElementById('typesTableBody');
    const query  = (document.getElementById('typeSearch')?.value || '').toLowerCase().trim();
    const status = document.getElementById('statusFilter')?.value ?? '';

    const filtered = typesData.filter(t => {
        const matchQ = !query || t.name.toLowerCase().includes(query) || (t.description || '').toLowerCase().includes(query);
        const matchStatus = status === '' || String(t.is_active ? 1 : 0) === status;
        return matchQ && matchStatus;
    });

    document.getElementById('typeCount').textContent = filtered.length + ' support type(s)';

    if (!filtered.length) {
        tbody.innerHTML = `<tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">${query || status ? 'No support types match your filter.' : 'No support types yet. Click "Add Support Type".'}</td></tr>`;
        return;
    }

    tbody.innerHTML = filtered.map((t, i) => `
        <tr class="hover:bg-gray-50 transition-colors ${t.is_active ? '' : 'opacity-50'}">
            <td class="px-4 py-3 text-gray-400 text-xs">${i + 1}</td>
            <td class="px-4 py-3 font-semibold text-gray-900">${escHtml(t.name)}</td>
            <td class="px-4 py-3 text-gray-500">${escHtml(t.description || '') || '<span class="text-gray-300">—</span>'}</td>
            <td class="px-4 py-3 text-center">
                ${t.is_active
                    ? '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-green-50 text-green-700">Active</span>'
                    : '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-500">Inactive</span>'}
            </td>
            <td class="px-4 py-3">
                <div class="flex items-center justify-center gap-2">
                    <button onclick="openEditTypeModal(${t.id})"
                        class="px-3 py-1.5 text-xs font-semibold bg-gray-100 text-gray-700 border border-gray-200 rounded-lg hover:bg-gray-200 transition-colors">
                        <i class="fas fa-pen mr-1"></i> Edit
                    </button>
                    <button onclick="deleteType(${t.id})"
                        class="px-3 py-1.5 text-xs font-semibold bg-red-50 text-red-700 border border-red-200 rounded-lg hover:bg-red-100 transition-colors">
                        <i class="fas fa-trash mr-1"></i> Delete
                    </button>
                </div>
            </td>
        </tr>`).join('');
}

// ── Create / Edit ──────────────────────────────────────────────────────────────

function openCreateTypeModal() {
    document.getElementById('typeModalTitle').textContent = 'Add Support Type';
    document.getElementById('typeId').value = '';
    document.getElementById('typeName').value = '';
    document.getElementById('typeDescription').value = '';
    document.getElementById('typeActive').checked = true;
    openModal('typeModal');
    setTimeout(() => document.getElementById('typeName').focus(), 100);
}

function openEditTypeModal(id) {
    const t = typesData.find(x => x.id === id);
    if (!t) return;
    document.getElementById('typeModalTitle').textContent = 'Edit Support Type';
    document.getElementById('typeId').value = t.id;
    document.getElementById('typeName').value = t.name;
    document.getElementById('typeDescription').value = t.description || '';
    document.getElementById('typeActive').checked = !!t.is_active;
    openModal('typeModal');
    setTimeout(() => document.getElementById('typeName').focus(), 100);
}

async function submitType(e) {
    e.preventDefault();
    const id   = document.getElementById('typeId').value;
    const name = document.getElementById('typeName').value.trim();
    if (!name) { showToast('Project type name is required.', 'warning'); return; }

    const payload = {
        name,
        description: document.getElementById('typeDescription').value.trim() || null,
        is_active:   document.getElementById('typeActive').checked,
    };
    const url    = id ? `/api/delivery-support-types/${id}` : '/api/delivery-support-types';
    const method = id ? 'PUT' : 'POST';

    try {
        const res  = await fetch(url, { method, headers: jsonHeaders(), body: JSON.stringify(payload) });
        const json = await res.json();

        if (json.success) {
            closeModal('typeModal');
            showToast(id ? 'Project type updated successfully.' : 'Project type added successfully.', 'success');
            loadTypes();
        } else {
            const err = json.errors ? Object.values(json.errors).flat().join('\n') : (json.message || 'Failed to save support type.');
            showToast(err, 'error');
        }
    } catch (e) {
        showToast('An error occurred. Please try again.', 'error');
    }
}

async function deleteType(id) {
    const t = typesData.find(x => x.id === id);
    const ok = await customConfirm({
        title:     'Delete Support Type?',
        message:   `You are about to delete "${t?.name ?? ''}". Projects already using this type keep their existing value — only the dropdown option is removed. This action cannot be undone.`,
        okLabel:   'Yes, Delete',
        okClass:   'bg-red-600 hover:bg-red-700',
        icon:      'fas fa-trash',
        iconBg:    'bg-red-50',
        iconColor: 'text-red-500',
    });
    if (!ok) return;

    try {
        const res  = await fetch(`/api/delivery-support-types/${id}/delete`, { method: 'POST', headers: jsonHeaders() });
        const json = await res.json();
        if (json.success) {
            showToast(json.message || 'Project type deleted successfully.', 'success');
            loadTypes();
        } else {
            showToast(json.message || 'Failed to delete support type.', 'error');
        }
    } catch (e) {
        showToast('An error occurred. Please try again.', 'error');
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

// Intentionally no backdrop-click-to-close on #typeModal — it should only be
// dismissed via the X button (or Cancel / Escape), never by an accidental
// click outside while filling the form.
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeModal('typeModal');
});

// Init
loadTypes();
</script>
@endsection

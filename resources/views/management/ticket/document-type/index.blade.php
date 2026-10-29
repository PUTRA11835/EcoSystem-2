@extends('dashboard')

@section('title', 'Document Type')
@section('page-title', 'Document Type')

@section('content')
<div class="bg-white rounded-xl p-6 shadow-sm">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 pb-4 border-b-2 border-gray-100">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Document Type</h2>
            <p class="text-sm text-gray-500 mt-1">Manage the list of deliverable document types available on the ticket "New Document" form.</p>
        </div>
        <button onclick="openCreateTypeModal()" class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
            <i class="fas fa-plus mr-2"></i> Add Document Type
        </button>
    </div>

    <!-- Filters -->
    <div class="flex flex-col sm:flex-row gap-3 mb-4">
        <input type="text" id="typeSearch" placeholder="Search document type name..."
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
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Document Type</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Description</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider" style="min-width:220px;">Ticket Type</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider w-28">Status</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider w-28">Actions</th>
                </tr>
            </thead>
            <tbody id="typesTableBody" class="divide-y divide-gray-100">
                <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">Loading...</td></tr>
            </tbody>
        </table>
    </div>
    <div class="mt-3 text-xs text-gray-400" id="typeCount"></div>
</div>

<style>
    /* Requirement per ticket type: segmented control. Warna mengikuti tema aplikasi
       (--card-bg, --text-color, --primary-*) supaya benar di mode terang dan gelap. */
    .req-card { border: 1px solid rgba(107,114,128,.25); border-radius: .75rem; overflow: hidden; }
    .req-row { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .625rem .875rem; transition: background .15s; }
    .req-row + .req-row { border-top: 1px solid rgba(107,114,128,.15); }
    .req-row:hover { background: rgba(107,114,128,.06); }
    .req-name { font-size: .8125rem; font-weight: 600; color: var(--text-color); }
    .req-seg { display: inline-flex; padding: 2px; border-radius: .5rem; background: rgba(107,114,128,.16); flex-shrink: 0; }
    .req-seg label { position: relative; cursor: pointer; display: block; }
    .req-seg input { position: absolute; opacity: 0; pointer-events: none; }
    .req-seg span { display: block; padding: .3rem .7rem; font-size: .75rem; font-weight: 600; border-radius: .375rem; color: #6b7280; white-space: nowrap; transition: background .15s, color .15s, box-shadow .15s; }
    .req-seg label:hover span { color: var(--text-color); }
    .req-seg input:focus-visible + span { outline: 2px solid rgba(var(--primary-rgb), .5); outline-offset: 1px; }
    .req-seg input[value="none"]:checked + span { background: var(--card-bg); color: var(--text-color); box-shadow: 0 1px 2px rgba(0,0,0,.14); }
    .req-seg input[value="optional"]:checked + span { background: rgba(37,99,235,.16); color: #2563eb; }
    .req-seg input[value="mandatory"]:checked + span { background: linear-gradient(135deg, rgb(var(--primary-dark-rgb)), rgb(var(--primary-rgb))); color: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.2); }
    .req-summary { display: flex; flex-wrap: wrap; gap: .5rem; padding: .625rem .875rem; border-top: 1px solid rgba(107,114,128,.15); background: rgba(107,114,128,.06); }
    .req-chip { display: inline-flex; align-items: center; gap: .375rem; padding: .125rem .6rem; border-radius: 9999px; font-size: .6875rem; font-weight: 600; background: rgba(107,114,128,.14); color: #6b7280; }
    .req-chip b { font-weight: 700; }
    .req-chip.is-mandatory { background: rgba(var(--primary-rgb), .14); color: rgb(var(--primary-rgb)); }
    .req-chip.is-optional { background: rgba(37,99,235,.14); color: #2563eb; }

    /* Toggle "Active" */
    .req-switch-row { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .75rem .875rem; border: 1px solid rgba(107,114,128,.25); border-radius: .75rem; cursor: pointer; }
    .req-switch { position: relative; width: 2.5rem; height: 1.5rem; flex-shrink: 0; }
    .req-switch input { position: absolute; inset: 0; opacity: 0; width: 100%; height: 100%; cursor: pointer; margin: 0; }
    .req-switch-track { position: absolute; inset: 0; border-radius: 9999px; background: rgba(107,114,128,.4); transition: background .2s; pointer-events: none; }
    .req-switch-track::after { content: ''; position: absolute; top: 2px; left: 2px; width: 1.25rem; height: 1.25rem; border-radius: 9999px; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.3); transition: transform .2s; }
    .req-switch input:checked + .req-switch-track { background: linear-gradient(135deg, rgb(var(--primary-dark-rgb)), rgb(var(--primary-rgb))); }
    .req-switch input:checked + .req-switch-track::after { transform: translateX(1rem); }
    .req-switch input:focus-visible + .req-switch-track { outline: 2px solid rgba(var(--primary-rgb), .5); outline-offset: 2px; }
</style>

<!-- ── Modal: Create / Edit Document Type ─────────────────────────────────── -->
<div id="typeModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-xl max-h-[92vh] flex flex-col">
        <div class="flex justify-between items-start gap-4 px-6 py-5 border-b border-gray-100">
            <div>
                <h3 id="typeModalTitle" class="text-lg font-bold text-gray-900">Add Document Type</h3>
                <p class="text-xs text-gray-400 mt-0.5">Name the document and choose which ticket types require it.</p>
            </div>
            <button type="button" onclick="closeModal('typeModal')" class="text-gray-400 hover:text-gray-600 transition-colors" aria-label="Close">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <form id="typeForm" onsubmit="submitType(event)" class="p-6 space-y-5 overflow-y-auto">
            <input type="hidden" id="typeId">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Document Type Name <span class="text-red-500">*</span></label>
                <input type="text" id="typeName" required maxlength="50"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent"
                    placeholder="e.g. IR, RCA, CR Form">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Description</label>
                <textarea id="typeDescription" maxlength="255" rows="2"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent"
                    placeholder="Optional"></textarea>
            </div>
            <label class="req-switch-row" for="typeActive">
                <span>
                    <span class="block text-sm font-semibold text-gray-700">Active</span>
                    <span class="block text-xs text-gray-400 mt-0.5">Inactive types are hidden from the ticket document list.</span>
                </span>
                <span class="req-switch">
                    <input type="checkbox" id="typeActive" checked>
                    <span class="req-switch-track"></span>
                </span>
            </label>
            <div>
                <label class="block text-sm font-semibold text-gray-700">Requirement by Ticket Type</label>
                <p class="text-xs text-gray-400 mt-0.5 mb-2.5">The default for tickets of each type. A ticket can still change it from its Deliverable Documents window.</p>
                <div class="req-card">
                    <div id="typeRequirements"></div>
                    <div id="typeRequirementSummary" class="req-summary"></div>
                </div>
            </div>
            <div class="flex gap-3 justify-end pt-1">
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
// Enum ticket type tunggal — App\Support\TicketClassification::TYPES — dipakai
// untuk membangun grid "Required For" di modal edit.
const TICKET_TYPES = @json($ticketTypes);

// ── Load ─────────────────────────────────────────────────────────────────────

async function loadTypes() {
    const res  = await fetch('/api/deliverable-document-types');
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

    document.getElementById('typeCount').textContent = filtered.length + ' document type(s)';

    if (!filtered.length) {
        tbody.innerHTML = `<tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">${query || status ? 'No document types match your filter.' : 'No document types yet. Click "Add Document Type".'}</td></tr>`;
        return;
    }

    tbody.innerHTML = filtered.map((t, i) => `
        <tr class="hover:bg-gray-50 transition-colors ${t.is_active ? '' : 'opacity-50'}">
            <td class="px-4 py-3 text-gray-400 text-xs">${i + 1}</td>
            <td class="px-4 py-3 font-semibold text-gray-900">${escHtml(t.name)}</td>
            <td class="px-4 py-3 text-gray-500">${escHtml(t.description || '') || '<span class="text-gray-300">—</span>'}</td>
            <td class="px-4 py-3">${requirementBadges(t.ticket_type_links)}</td>
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

// Ringkasan kolom "Required For": satu badge per ticket type yang punya
// aturan, merah = Mandatory, abu-abu = Optional. Doc type tanpa aturan sama
// sekali tidak muncul di checklist tiket manapun.
function requirementBadges(links) {
    if (!links || links.length === 0) {
        return '<span class="text-gray-300 text-xs">Not required anywhere</span>';
    }
    return links.map(r => {
        const cls = r.is_mandatory ? 'bg-red-50 text-red-700 border-red-200' : 'bg-gray-50 text-gray-600 border-gray-200';
        return `<span class="inline-flex items-center px-2 py-0.5 mr-1 mb-1 rounded-md text-[11px] font-semibold border ${cls}">${escHtml(r.ticket_type)} · ${r.is_mandatory ? 'Mandatory' : 'Optional'}</span>`;
    }).join('');
}

// ── Create / Edit ──────────────────────────────────────────────────────────────

// Grid "Required For": satu baris per ticket type, tiap baris punya radio
// Not required/Optional/Mandatory. `existing` = ticket_type_links dari server
// (kosong untuk create baru, semua default "Not required").
function renderRequirementGrid(existing) {
    const byType = {};
    (existing || []).forEach(r => { byType[r.ticket_type] = r.is_mandatory; });

    document.getElementById('typeRequirements').innerHTML = TICKET_TYPES.map(type => {
        const has = Object.prototype.hasOwnProperty.call(byType, type);
        const current = has ? (byType[type] ? 'mandatory' : 'optional') : 'none';
        const safeType = escHtml(type);
        const option = (value, label) => `
            <label>
                <input type="radio" name="req_${safeType}" value="${value}" data-ticket-type="${safeType}"
                    class="req-radio" ${current === value ? 'checked' : ''}>
                <span>${label}</span>
            </label>`;
        return `
            <div class="req-row">
                <span class="req-name">${safeType}</span>
                <div class="req-seg" role="radiogroup" aria-label="${safeType}">
                    ${option('none', 'Not required')}
                    ${option('optional', 'Optional')}
                    ${option('mandatory', 'Mandatory')}
                </div>
            </div>`;
    }).join('');

    updateRequirementSummary();
}

// Ringkasan di bawah grid: berapa ticket type yang Mandatory / Optional / Not required.
function updateRequirementSummary() {
    const counts = { mandatory: 0, optional: 0, none: 0 };
    document.querySelectorAll('#typeRequirements input.req-radio:checked').forEach(input => {
        counts[input.value] = (counts[input.value] || 0) + 1;
    });
    document.getElementById('typeRequirementSummary').innerHTML = `
        <span class="req-chip is-mandatory"><b>${counts.mandatory}</b> Mandatory</span>
        <span class="req-chip is-optional"><b>${counts.optional}</b> Optional</span>
        <span class="req-chip"><b>${counts.none}</b> Not required</span>`;
}

// Baca grid balik jadi payload `ticket_type_links` untuk dikirim ke server —
// baris "Not required" tidak dikirim sama sekali (berarti dihapus/tak ada).
function readRequirementGrid() {
    const links = [];
    document.querySelectorAll('#typeRequirements input.req-radio:checked').forEach(input => {
        if (input.value === 'none') return;
        links.push({ ticket_type: input.dataset.ticketType, is_mandatory: input.value === 'mandatory' });
    });
    return links;
}

function openCreateTypeModal() {
    document.getElementById('typeModalTitle').textContent = 'Add Document Type';
    document.getElementById('typeId').value = '';
    document.getElementById('typeName').value = '';
    document.getElementById('typeDescription').value = '';
    document.getElementById('typeActive').checked = true;
    renderRequirementGrid([]);
    openModal('typeModal');
    setTimeout(() => document.getElementById('typeName').focus(), 100);
}

function openEditTypeModal(id) {
    const t = typesData.find(x => x.id === id);
    if (!t) return;
    document.getElementById('typeModalTitle').textContent = 'Edit Document Type';
    document.getElementById('typeId').value = t.id;
    document.getElementById('typeName').value = t.name;
    document.getElementById('typeDescription').value = t.description || '';
    document.getElementById('typeActive').checked = !!t.is_active;
    renderRequirementGrid(t.ticket_type_links);
    openModal('typeModal');
    setTimeout(() => document.getElementById('typeName').focus(), 100);
}

async function submitType(e) {
    e.preventDefault();
    const id   = document.getElementById('typeId').value;
    const name = document.getElementById('typeName').value.trim();
    if (!name) { showToast('Document type name is required.', 'warning'); return; }

    const payload = {
        name,
        description:        document.getElementById('typeDescription').value.trim() || null,
        is_active:          document.getElementById('typeActive').checked,
        ticket_type_links:  readRequirementGrid(),
    };
    // Edit juga lewat POST (/{id}/update), bukan PUT: verb non-POST diblokir edge/WAF di production.
    const url    = id ? `/api/deliverable-document-types/${id}/update` : '/api/deliverable-document-types';
    const method = 'POST';

    try {
        const res  = await fetch(url, { method, headers: jsonHeaders(), body: JSON.stringify(payload) });
        const json = await res.json();

        if (json.success) {
            closeModal('typeModal');
            showToast(id ? 'Document type updated successfully.' : 'Document type added successfully.', 'success');
            loadTypes();
        } else {
            const err = json.errors ? Object.values(json.errors).flat().join('\n') : (json.message || 'Failed to save document type.');
            showToast(err, 'error');
        }
    } catch (e) {
        showToast('An error occurred. Please try again.', 'error');
    }
}

async function deleteType(id) {
    const t = typesData.find(x => x.id === id);
    const ok = await customConfirm({
        title:     'Delete Document Type?',
        message:   `You are about to delete "${t?.name ?? ''}". Deliverable documents already using this type keep their existing value — only the dropdown option is removed. This action cannot be undone.`,
        okLabel:   'Yes, Delete',
        okClass:   'bg-red-600 hover:bg-red-700',
        icon:      'fas fa-trash',
        iconBg:    'bg-red-50',
        iconColor: 'text-red-500',
    });
    if (!ok) return;

    try {
        const res  = await fetch(`/api/deliverable-document-types/${id}/delete`, { method: 'POST', headers: jsonHeaders() });
        const json = await res.json();
        if (json.success) {
            showToast(json.message || 'Document type deleted successfully.', 'success');
            loadTypes();
        } else {
            showToast(json.message || 'Failed to delete document type.', 'error');
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
document.getElementById('typeRequirements').addEventListener('change', updateRequirementSummary);
loadTypes();
</script>
@endsection

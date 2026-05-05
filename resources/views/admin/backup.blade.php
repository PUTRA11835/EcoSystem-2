@extends('dashboard')

@section('title', 'Backup & Export')
@section('page-title', 'Backup & Export')
@section('page-subtitle', 'Database backup, data export, and CSV import — admin only')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="space-y-5">

    {{-- ── Row 1: DB Backup + Ticket Export ── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

        {{-- DB Backup --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm flex flex-col">
            <div class="px-5 pt-5 pb-4 border-b border-gray-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center">
                        <i class="fas fa-database text-blue-600"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-800">Database Backup</h3>
                        <p class="text-xs text-gray-400">Full mysqldump backup</p>
                    </div>
                </div>
            </div>
            <div class="p-5 flex-1 flex flex-col gap-3">
                <button id="btnCreateBackup"
                    class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-blue-600 text-white text-sm font-medium hover:bg-blue-700 transition">
                    <i class="fas fa-play text-xs"></i> Create Backup Now
                </button>

                <div id="backupProgress" class="hidden items-center gap-2 text-sm text-blue-600">
                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    <span>Running backup, please wait...</span>
                </div>

                <div class="border-t border-gray-100 pt-3">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs font-medium text-gray-500">Existing Backups</p>
                        <button onclick="loadBackups()" class="text-xs text-blue-600 hover:underline">Refresh</button>
                    </div>
                    <div id="backupList" class="space-y-2 max-h-56 overflow-y-auto">
                        <p class="text-xs text-gray-400 py-2 text-center">Loading...</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Ticket Export --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm flex flex-col">
            <div class="px-5 pt-5 pb-4 border-b border-gray-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-orange-50 flex items-center justify-center">
                        <i class="fas fa-ticket-alt text-orange-500"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-800">Ticket Export</h3>
                        <p class="text-xs text-gray-400">Filter by period, download CSV</p>
                    </div>
                </div>
            </div>
            <div class="p-5 flex-1 flex flex-col gap-4">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Year</label>
                        <select id="ticketYear" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300">
                            @for($y = date('Y'); $y >= 2024; $y--)
                                <option value="{{ $y }}" {{ $y == date('Y') ? 'selected' : '' }}>{{ $y }}</option>
                            @endfor
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Month</label>
                        <select id="ticketMonth" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300">
                            <option value="0">All Months</option>
                            <option value="1">January</option><option value="2">February</option>
                            <option value="3">March</option><option value="4">April</option>
                            <option value="5">May</option><option value="6">June</option>
                            <option value="7">July</option><option value="8">August</option>
                            <option value="9">September</option><option value="10">October</option>
                            <option value="11">November</option><option value="12">December</option>
                        </select>
                    </div>
                </div>
                <div class="bg-gray-50 rounded-xl p-4 text-sm text-gray-600 space-y-1">
                    <p class="flex items-center gap-2"><i class="fas fa-check text-orange-400 text-xs"></i> Ticket number, subject, status, priority</p>
                    <p class="flex items-center gap-2"><i class="fas fa-check text-orange-400 text-xs"></i> Type, category, customer & company</p>
                    <p class="flex items-center gap-2"><i class="fas fa-check text-orange-400 text-xs"></i> PIC info, created & updated date</p>
                </div>
                <div class="mt-auto">
                    <button id="btnExportTickets"
                        class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-orange-500 text-white text-sm font-medium hover:bg-orange-600 transition">
                        <i class="fas fa-download text-xs"></i> Download CSV
                    </button>
                </div>
            </div>
        </div>

    </div>

    {{-- ── Row 2: Employee + Customer (Export & Import) ── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

        {{-- Employee Card --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm flex flex-col">
            <div class="px-5 pt-5 pb-4 border-b border-gray-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center">
                        <i class="fas fa-users text-emerald-600"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-800">Employee Data</h3>
                        <p class="text-xs text-gray-400">Export CSV or import from CSV</p>
                    </div>
                </div>
            </div>
            <div class="p-5 flex-1 flex flex-col gap-4">

                {{-- Export section --}}
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Export</p>
                    <div class="bg-gray-50 rounded-xl p-3 text-xs text-gray-600 space-y-1 mb-3">
                        <p class="flex items-center gap-2"><i class="fas fa-check text-emerald-500"></i> Employee ID, ECI, status, role</p>
                        <p class="flex items-center gap-2"><i class="fas fa-check text-emerald-500"></i> Full name, gender, birth, position</p>
                        <p class="flex items-center gap-2"><i class="fas fa-check text-emerald-500"></i> Division, department, since date</p>
                    </div>
                    <a href="{{ route('admin.export.employees') }}"
                        class="w-full flex items-center justify-center gap-2 px-4 py-2 rounded-xl bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700 transition">
                        <i class="fas fa-download text-xs"></i> Download CSV
                    </a>
                </div>

                <div class="border-t border-gray-100"></div>

                {{-- Import section --}}
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Import</p>
                        <a href="{{ route('admin.import.template.employees') }}" class="text-xs text-emerald-600 hover:underline flex items-center gap-1">
                            <i class="fas fa-file-csv text-xs"></i> Download Template
                        </a>
                    </div>
                    <div id="empDropzone"
                        class="border-2 border-dashed border-gray-200 rounded-xl p-4 text-center cursor-pointer hover:border-emerald-400 hover:bg-emerald-50 transition-colors"
                        onclick="document.getElementById('empFileInput').click()"
                        ondragover="event.preventDefault();this.classList.add('border-emerald-400','bg-emerald-50')"
                        ondragleave="this.classList.remove('border-emerald-400','bg-emerald-50')"
                        ondrop="handleDrop(event,'emp')">
                        <i class="fas fa-cloud-upload-alt text-gray-300 text-2xl mb-1"></i>
                        <p class="text-xs text-gray-400">Drop CSV here or <span class="text-emerald-600 font-medium">browse</span></p>
                        <p id="empFileName" class="text-xs text-gray-500 mt-1 hidden"></p>
                    </div>
                    <input type="file" id="empFileInput" accept=".csv,.txt" class="hidden" onchange="onFileSelect(event,'emp')">
                    <button id="btnImportEmp" onclick="runImport('emp')"
                        class="w-full mt-2 flex items-center justify-center gap-2 px-4 py-2 rounded-xl bg-emerald-100 text-emerald-700 text-sm font-medium hover:bg-emerald-200 transition disabled:opacity-50 disabled:cursor-not-allowed"
                        disabled>
                        <i class="fas fa-upload text-xs"></i> Import Employee
                    </button>
                    <div id="empResult" class="hidden mt-3"></div>
                </div>

            </div>
        </div>

        {{-- Customer Card --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm flex flex-col">
            <div class="px-5 pt-5 pb-4 border-b border-gray-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-violet-50 flex items-center justify-center">
                        <i class="fas fa-building text-violet-600"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-800">Customer Data</h3>
                        <p class="text-xs text-gray-400">Export CSV or import from CSV</p>
                    </div>
                </div>
            </div>
            <div class="p-5 flex-1 flex flex-col gap-4">

                {{-- Export section --}}
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Export</p>
                    <div class="bg-gray-50 rounded-xl p-3 text-xs text-gray-600 space-y-1 mb-3">
                        <p class="flex items-center gap-2"><i class="fas fa-check text-violet-500"></i> Customer code, email, status</p>
                        <p class="flex items-center gap-2"><i class="fas fa-check text-violet-500"></i> Company name, group, category</p>
                        <p class="flex items-center gap-2"><i class="fas fa-check text-violet-500"></i> Industry sector, account executives</p>
                    </div>
                    <a href="{{ route('admin.export.customers') }}"
                        class="w-full flex items-center justify-center gap-2 px-4 py-2 rounded-xl bg-violet-600 text-white text-sm font-medium hover:bg-violet-700 transition">
                        <i class="fas fa-download text-xs"></i> Download CSV
                    </a>
                </div>

                <div class="border-t border-gray-100"></div>

                {{-- Import section --}}
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Import</p>
                        <a href="{{ route('admin.import.template.customers') }}" class="text-xs text-violet-600 hover:underline flex items-center gap-1">
                            <i class="fas fa-file-csv text-xs"></i> Download Template
                        </a>
                    </div>
                    <div id="custDropzone"
                        class="border-2 border-dashed border-gray-200 rounded-xl p-4 text-center cursor-pointer hover:border-violet-400 hover:bg-violet-50 transition-colors"
                        onclick="document.getElementById('custFileInput').click()"
                        ondragover="event.preventDefault();this.classList.add('border-violet-400','bg-violet-50')"
                        ondragleave="this.classList.remove('border-violet-400','bg-violet-50')"
                        ondrop="handleDrop(event,'cust')">
                        <i class="fas fa-cloud-upload-alt text-gray-300 text-2xl mb-1"></i>
                        <p class="text-xs text-gray-400">Drop CSV here or <span class="text-violet-600 font-medium">browse</span></p>
                        <p id="custFileName" class="text-xs text-gray-500 mt-1 hidden"></p>
                    </div>
                    <input type="file" id="custFileInput" accept=".csv,.txt" class="hidden" onchange="onFileSelect(event,'cust')">
                    <button id="btnImportCust" onclick="runImport('cust')"
                        class="w-full mt-2 flex items-center justify-center gap-2 px-4 py-2 rounded-xl bg-violet-100 text-violet-700 text-sm font-medium hover:bg-violet-200 transition disabled:opacity-50 disabled:cursor-not-allowed"
                        disabled>
                        <i class="fas fa-upload text-xs"></i> Import Customer
                    </button>
                    <div id="custResult" class="hidden mt-3"></div>
                </div>

            </div>
        </div>

    </div>

</div>

{{-- ── Import Error Detail Modal ── --}}
<div id="errorModal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black bg-opacity-40">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-800">Import Errors</h3>
            <button onclick="closeErrorModal()" class="w-7 h-7 flex items-center justify-center rounded-lg hover:bg-gray-100 text-gray-400">
                <i class="fas fa-times text-xs"></i>
            </button>
        </div>
        <div id="errorModalBody" class="p-5 max-h-80 overflow-y-auto space-y-1.5"></div>
        <div class="px-5 pb-4">
            <button onclick="closeErrorModal()" class="w-full px-4 py-2 rounded-xl bg-gray-100 text-gray-600 text-sm font-medium hover:bg-gray-200 transition">Tutup</button>
        </div>
    </div>
</div>

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const empFiles  = {};
const custFiles = {};

// ── File selection & drag-drop ────────────────────────────────────────────────

function onFileSelect(e, type) {
    const file = e.target.files[0];
    if (file) setFile(type, file);
}

function handleDrop(e, type) {
    e.preventDefault();
    const dropzone = document.getElementById(type + 'Dropzone');
    dropzone.classList.remove('border-emerald-400', 'bg-emerald-50', 'border-violet-400', 'bg-violet-50');
    const file = e.dataTransfer.files[0];
    if (file && (file.name.endsWith('.csv') || file.name.endsWith('.txt'))) {
        setFile(type, file);
    } else {
        showToast('Hanya file CSV yang diterima', 'error');
    }
}

function setFile(type, file) {
    if (type === 'emp') {
        empFiles.file = file;
    } else {
        custFiles.file = file;
    }
    const nameEl = document.getElementById(type + 'FileName');
    nameEl.textContent = file.name + ' (' + formatBytes(file.size) + ')';
    nameEl.classList.remove('hidden');

    const btn = document.getElementById(type === 'emp' ? 'btnImportEmp' : 'btnImportCust');
    btn.disabled = false;

    // Clear previous result
    const result = document.getElementById(type + 'Result');
    result.classList.add('hidden');
    result.innerHTML = '';
}

function formatBytes(b) {
    if (b >= 1048576) return (b / 1048576).toFixed(1) + ' MB';
    if (b >= 1024)    return (b / 1024).toFixed(1) + ' KB';
    return b + ' B';
}

// ── Run Import ────────────────────────────────────────────────────────────────

async function runImport(type) {
    const file    = type === 'emp' ? empFiles.file : custFiles.file;
    const btnId   = type === 'emp' ? 'btnImportEmp' : 'btnImportCust';
    const resultId = type + 'Result';
    const endpoint = type === 'emp' ? '/api/admin/import/employees' : '/api/admin/import/customers';

    if (!file) return;

    const btn = document.getElementById(btnId);
    btn.disabled = true;
    btn.innerHTML = '<svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg> Mengimport...';

    const form = new FormData();
    form.append('file', file);

    try {
        const res  = await fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': CSRF },
            body: form,
        });
        const json = await res.json();
        showImportResult(type, json);
    } catch (e) {
        showToast('Import gagal: ' + e.message, 'error');
    } finally {
        const color = type === 'emp' ? 'emerald' : 'violet';
        btn.disabled = false;
        btn.innerHTML = `<i class="fas fa-upload text-xs"></i> Import ${type === 'emp' ? 'Employee' : 'Customer'}`;
    }
}

function showImportResult(type, json) {
    const el    = document.getElementById(type + 'Result');
    const color = json.success ? 'green' : 'red';
    const errors = json.errors ?? [];

    el.classList.remove('hidden');

    if (json.success) {
        el.innerHTML = `
            <div class="bg-green-50 border border-green-200 rounded-xl p-3">
                <p class="text-xs font-semibold text-green-700 mb-1"><i class="fas fa-check-circle mr-1"></i>${htmlEsc(json.message)}</p>
                <div class="flex gap-3 text-xs text-gray-600">
                    <span class="flex items-center gap-1"><i class="fas fa-plus-circle text-green-500"></i> ${json.imported} ditambahkan</span>
                    <span class="flex items-center gap-1"><i class="fas fa-sync text-blue-500"></i> ${json.updated} diperbarui</span>
                    ${errors.length ? `<button onclick="openErrorModal(${JSON.stringify(errors).replace(/"/g,'&quot;')})" class="flex items-center gap-1 text-orange-500 hover:underline"><i class="fas fa-exclamation-triangle"></i> ${errors.length} error</button>` : ''}
                </div>
            </div>`;
    } else {
        el.innerHTML = `
            <div class="bg-red-50 border border-red-200 rounded-xl p-3">
                <p class="text-xs font-semibold text-red-700"><i class="fas fa-times-circle mr-1"></i>${htmlEsc(json.message)}</p>
            </div>`;
    }
}

// ── Error Modal ───────────────────────────────────────────────────────────────

function openErrorModal(errors) {
    const body = document.getElementById('errorModalBody');
    body.innerHTML = errors.map(e =>
        `<div class="flex items-start gap-2 bg-red-50 rounded-lg px-3 py-2">
            <i class="fas fa-exclamation-circle text-red-400 text-xs mt-0.5 shrink-0"></i>
            <p class="text-xs text-gray-700">${htmlEsc(e)}</p>
        </div>`
    ).join('');
    document.getElementById('errorModal').classList.remove('hidden');
}

function closeErrorModal() {
    document.getElementById('errorModal').classList.add('hidden');
}

// ── DB Backup ─────────────────────────────────────────────────────────────────

async function loadBackups() {
    const list = document.getElementById('backupList');
    list.innerHTML = '<p class="text-xs text-gray-400 py-2 text-center">Loading...</p>';
    try {
        const res  = await fetch('/api/admin/backup/list', { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message);

        if (!json.data.length) {
            list.innerHTML = '<p class="text-xs text-gray-400 py-2 text-center">No backups yet</p>';
            return;
        }

        list.innerHTML = '';
        json.data.forEach(b => {
            const row = document.createElement('div');
            row.className = 'flex items-center justify-between bg-gray-50 rounded-lg px-3 py-2';
            row.innerHTML = `
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-medium text-gray-700 truncate">${htmlEsc(b.filename)}</p>
                    <p class="text-xs text-gray-400">${htmlEsc(b.size)} · ${htmlEsc(b.created_at)}</p>
                </div>
                <div class="flex gap-1 ml-2 shrink-0">
                    <a href="/admin/backup/download/${encodeURIComponent(b.filename)}"
                        class="w-7 h-7 flex items-center justify-center rounded-lg bg-blue-100 text-blue-600 hover:bg-blue-200 transition" title="Download">
                        <i class="fas fa-download text-xs"></i>
                    </a>
                    <button onclick='deleteBackup("${htmlEsc(b.filename)}")'
                        class="w-7 h-7 flex items-center justify-center rounded-lg bg-red-100 text-red-500 hover:bg-red-200 transition" title="Delete">
                        <i class="fas fa-trash text-xs"></i>
                    </button>
                </div>`;
            list.appendChild(row);
        });
    } catch (e) {
        list.innerHTML = '<p class="text-xs text-red-400 py-2 text-center">' + htmlEsc(e.message) + '</p>';
    }
}

document.getElementById('btnCreateBackup').addEventListener('click', async () => {
    const btn      = document.getElementById('btnCreateBackup');
    const progress = document.getElementById('backupProgress');
    btn.disabled = true;
    btn.classList.add('opacity-50');
    progress.classList.remove('hidden');
    progress.classList.add('flex');
    try {
        const res  = await fetch('/api/admin/backup/create', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': CSRF },
        });
        const json = await res.json();
        showToast(json.message, json.success ? 'success' : 'error');
        if (json.success) loadBackups();
    } catch (e) {
        showToast('Backup failed: ' + e.message, 'error');
    } finally {
        btn.disabled = false;
        btn.classList.remove('opacity-50');
        progress.classList.add('hidden');
        progress.classList.remove('flex');
    }
});

async function deleteBackup(filename) {
    if (!confirm('Hapus backup: ' + filename + '?')) return;
    try {
        const res  = await fetch('/api/admin/backup/' + encodeURIComponent(filename), {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': CSRF },
        });
        const json = await res.json();
        showToast(json.message, json.success ? 'success' : 'error');
        if (json.success) loadBackups();
    } catch (e) {
        showToast('Delete failed', 'error');
    }
}

// ── Ticket Export ─────────────────────────────────────────────────────────────

document.getElementById('btnExportTickets').addEventListener('click', () => {
    const year  = document.getElementById('ticketYear').value;
    const month = document.getElementById('ticketMonth').value;
    window.location.href = `/admin/export/tickets?year=${year}&month=${month}`;
});

// ── Util ──────────────────────────────────────────────────────────────────────

function htmlEsc(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

loadBackups();
</script>
@endsection

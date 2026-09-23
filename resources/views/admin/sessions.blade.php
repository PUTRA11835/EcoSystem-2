@extends('dashboard')

@section('title', 'Active Sessions')
@section('page-title', 'Active Sessions')
@section('page-subtitle', 'Manage who is currently logged in - force logout if needed')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="space-y-6">

    <!-- Stats Row -->
    <div class="grid grid-cols-2 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <p class="text-xs text-gray-500 mb-1">Active Sessions</p>
            <p class="text-2xl font-bold text-gray-900" id="statTotal">-</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <p class="text-xs text-gray-500 mb-1">Unique Users</p>
            <p class="text-2xl font-bold text-blue-600" id="statIdentified">-</p>
        </div>
    </div>

    <!-- Table Card -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 gap-3 flex-wrap">
            <div class="flex items-center gap-2.5">
                <h2 class="text-sm font-semibold text-gray-700">Sessions</h2>
                <span class="text-xs text-gray-400" id="tableInfo"></span>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <div class="relative">
                    <svg class="w-3.5 h-3.5 text-gray-400 absolute left-2.5 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
                    <input type="text" id="filterSearch" placeholder="Name, email, IP, location"
                        class="pl-8 pr-3 py-1.5 border border-gray-300 rounded-lg text-xs w-56 focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
                </div>
                <button id="btnLogoutAll"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-red-50 text-red-700 hover:bg-red-100 border border-red-200 transition">
                    Force Logout All Others
                </button>
            </div>
        </div>

        <!-- Loading -->
        <div id="loadingState" class="flex items-center justify-center py-16">
            <svg class="animate-spin h-6 w-6 text-blue-500" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
            </svg>
        </div>

        <!-- Empty -->
        <div id="emptyState" class="hidden flex flex-col items-center py-16 text-gray-400">
            <svg class="w-10 h-10 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2h5"/>
            </svg>
            <p class="text-sm" id="emptyStateText">No active sessions found</p>
        </div>

        <!-- Table -->
        <div id="tableWrapper" class="hidden overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="p-0 text-left">
                            <button type="button" onclick="toggleSort('name')" class="flex items-center gap-1 px-4 py-3 hover:bg-gray-100 transition-colors w-full text-left">
                                <span class="text-xs font-medium text-gray-500 uppercase tracking-wider">User</span>
                                <span class="sort-icon text-[10px] text-gray-300" data-sort="name">&#9650;</span>
                            </button>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP Address</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Browser / Device</th>
                        <th class="p-0 text-left">
                            <button type="button" onclick="toggleSort('last_activity')" class="flex items-center gap-1 px-4 py-3 hover:bg-gray-100 transition-colors w-full text-left">
                                <span class="text-xs font-medium text-gray-500 uppercase tracking-wider">Last Activity</span>
                                <span class="sort-icon text-[10px] text-gray-300" data-sort="last_activity">&#9660;</span>
                            </button>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody id="sessionTableBody" class="divide-y divide-gray-100"></tbody>
            </table>
        </div>
    </div>

</div>

<!-- Detail Modal -->
<div id="detailModal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/40">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-semibold text-gray-800">Session Detail</h3>
            <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div id="modalContent" class="space-y-3 text-sm text-gray-700"></div>
        <div class="mt-5 flex gap-2 justify-end">
            <button onclick="closeModal()" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900">Cancel</button>
            <button id="modalForceLogout"
                class="px-4 py-2 text-sm font-medium bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                Force Logout
            </button>
        </div>
    </div>
</div>

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
let sessions = [];
let selectedSessionId = null;
let currentSort = { by: 'last_activity', dir: 'desc' };
let searchTerm = '';

async function loadSessions() {
    try {
        const res = await fetch('/api/admin/sessions', { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message);

        sessions = json.data;
        renderStats(sessions);
        renderTable(getFilteredSortedSessions());
    } catch (e) {
        console.error(e);
    }
}

function getFilteredSortedSessions() {
    let rows = sessions;

    if (searchTerm) {
        const term = searchTerm.toLowerCase();
        rows = rows.filter(s => [s.full_name, s.username, s.email, s.eci, s.ip_address, s.location]
            .some(v => (v || '').toLowerCase().includes(term)));
    }

    const sorted = [...rows].sort((a, b) => {
        let av, bv;
        if (currentSort.by === 'name') {
            av = (a.full_name || a.username || '').toLowerCase();
            bv = (b.full_name || b.username || '').toLowerCase();
        } else {
            av = a.last_activity_at || '';
            bv = b.last_activity_at || '';
        }
        const cmp = av < bv ? -1 : (av > bv ? 1 : 0);
        return currentSort.dir === 'asc' ? cmp : -cmp;
    });

    return sorted;
}

function toggleSort(column) {
    if (currentSort.by === column) {
        currentSort.dir = currentSort.dir === 'asc' ? 'desc' : 'asc';
    } else {
        currentSort = { by: column, dir: 'asc' };
    }
    updateSortIndicators();
    renderTable(getFilteredSortedSessions());
}

function updateSortIndicators() {
    document.querySelectorAll('.sort-icon').forEach(el => {
        if (el.dataset.sort === currentSort.by) {
            el.innerHTML = currentSort.dir === 'asc' ? '&#9650;' : '&#9660;';
            el.classList.remove('text-gray-300');
            el.classList.add('text-red-600');
        } else {
            el.classList.remove('text-red-600');
            el.classList.add('text-gray-300');
        }
    });
}

document.getElementById('filterSearch').addEventListener('input', function () {
    searchTerm = this.value.trim();
    renderTable(getFilteredSortedSessions());
});

function renderStats(data) {
    document.getElementById('statTotal').textContent = data.length;
    document.getElementById('statIdentified').textContent = new Set(data.map(s => s.user_id)).size;
}

function renderTable(data) {
    document.getElementById('loadingState').classList.add('hidden');
    document.getElementById('tableInfo').textContent = sessions.length ? `${data.length} of ${sessions.length} shown` : '';

    if (!sessions.length) {
        document.getElementById('emptyState').classList.remove('hidden');
        document.getElementById('tableWrapper').classList.add('hidden');
        return;
    }

    if (!data.length) {
        document.getElementById('emptyStateText').textContent = 'No sessions match your search';
        document.getElementById('emptyState').classList.remove('hidden');
        document.getElementById('tableWrapper').classList.add('hidden');
        return;
    }

    document.getElementById('emptyState').classList.add('hidden');
    document.getElementById('tableWrapper').classList.remove('hidden');
    const tbody = document.getElementById('sessionTableBody');
    tbody.innerHTML = '';

    data.forEach(s => {
        const name  = s.full_name || s.username || '(unknown)';
        const eci   = s.eci ? `<span class="text-xs text-gray-400 ml-1">${s.eci}</span>` : '';
        const agent = parseAgent(s.user_agent || '');
        const badge = s.is_current
            ? '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">You</span>'
            : s.is_protected
            ? '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">Protected</span>'
            : '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Active</span>';

        const row = document.createElement('tr');
        row.className = 'hover:bg-gray-50 transition-colors';
        row.innerHTML = `
            <td class="px-4 py-3">
                <p class="font-medium text-gray-800">${htmlEsc(name)}${eci}</p>
                <p class="text-xs text-gray-400">${htmlEsc(s.email || '-')}</p>
            </td>
            <td class="px-4 py-3 text-gray-600 font-mono text-xs">${htmlEsc(s.ip_address || '-')}</td>
            <td class="px-4 py-3">
                <div class="flex items-center gap-1.5 text-xs text-gray-700">
                    <svg class="w-3.5 h-3.5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" /></svg>
                    <span>${htmlEsc(s.location || 'Unknown location')}</span>
                </div>
            </td>
            <td class="px-4 py-3 text-gray-500 text-xs max-w-[200px] truncate" title="${htmlEsc(s.user_agent || '')}">${agent}</td>
            <td class="px-4 py-3 text-gray-500 text-xs">${htmlEsc(s.last_activity_at)}</td>
            <td class="px-4 py-3">${badge}</td>
            <td class="px-4 py-3 text-right">
                ${(!s.is_current && !s.is_protected) ? `<button onclick='openModal(${JSON.stringify(s)})' class="text-xs text-red-600 hover:text-red-800 font-medium">Force Logout</button>` : ''}
            </td>
        `;
        tbody.appendChild(row);
    });
}

function parseAgent(ua) {
    if (!ua) return '-';
    if (ua.includes('Chrome')) return 'Chrome';
    if (ua.includes('Firefox')) return 'Firefox';
    if (ua.includes('Safari')) return 'Safari';
    if (ua.includes('Edge')) return 'Edge';
    return ua.substring(0, 40);
}

function htmlEsc(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function openModal(s) {
    selectedSessionId = s.session_id;
    document.getElementById('modalContent').innerHTML = `
        <div class="grid grid-cols-3 gap-y-2">
            <span class="text-gray-400 col-span-1">User</span><span class="col-span-2 font-medium">${htmlEsc(s.full_name || s.username || '-')}</span>
            <span class="text-gray-400 col-span-1">ECI</span><span class="col-span-2">${htmlEsc(s.eci || '-')}</span>
            <span class="text-gray-400 col-span-1">Email</span><span class="col-span-2">${htmlEsc(s.email || '-')}</span>
            <span class="text-gray-400 col-span-1">IP</span><span class="col-span-2">${htmlEsc(s.ip_address || '-')}</span>
            <span class="text-gray-400 col-span-1">Location</span><span class="col-span-2">${htmlEsc(s.location || 'Unknown location')}</span>
            <span class="text-gray-400 col-span-1">Last Active</span><span class="col-span-2">${htmlEsc(s.last_activity_at)}</span>
            <span class="text-gray-400 col-span-1">Browser</span><span class="col-span-2 text-xs break-all">${htmlEsc((s.user_agent || '').substring(0,80))}</span>
        </div>
    `;
    document.getElementById('detailModal').classList.remove('hidden');
}

function closeModal() {
    selectedSessionId = null;
    document.getElementById('detailModal').classList.add('hidden');
}

document.getElementById('modalForceLogout').addEventListener('click', async () => {
    if (!selectedSessionId) return;
    if (!await showConfirm('Force logout this session?', 'Force Logout', 'danger')) return;
    await forceLogout(selectedSessionId);
    closeModal();
});

document.getElementById('btnLogoutAll').addEventListener('click', async () => {
    if (!await showConfirm('Force logout ALL other sessions? This cannot be undone.', 'Force Logout All', 'danger')) return;
    try {
        const res = await fetch('/api/admin/sessions/delete-all', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': CSRF },
        });
        const json = await res.json();
        if (json.success) {
            showToast(json.message, 'success');
            loadSessions();
        } else {
            showToast(json.message, 'error');
        }
    } catch (e) {
        showToast('Request failed', 'error');
    }
});

async function forceLogout(sessionId) {
    try {
        const res = await fetch(`/api/admin/sessions/${sessionId}/delete`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': CSRF },
        });
        const json = await res.json();
        if (json.success) {
            showToast('Session terminated', 'success');
            loadSessions();
        } else {
            showToast(json.message, 'error');
        }
    } catch (e) {
        showToast('Request failed', 'error');
    }
}

function showToast(msg, type) {
    const t = document.createElement('div');
    t.className = `fixed bottom-5 right-5 z-50 px-4 py-3 rounded-xl shadow-lg text-sm font-medium transition-all ${type === 'success' ? 'bg-green-600 text-white' : 'bg-red-600 text-white'}`;
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3000);
}

updateSortIndicators();
loadSessions();
</script>
@endsection

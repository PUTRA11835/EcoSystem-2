@extends('dashboard')

@section('title', 'Active Sessions')
@section('page-title', 'Active Sessions')
@section('page-subtitle', 'Manage who is currently logged in — force logout if needed')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="space-y-6">

    <!-- Stats Row -->
    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <p class="text-xs text-gray-500 mb-1">Active Sessions</p>
            <p class="text-2xl font-bold text-gray-900" id="statTotal">—</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <p class="text-xs text-gray-500 mb-1">Identified Users</p>
            <p class="text-2xl font-bold text-blue-600" id="statIdentified">—</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <p class="text-xs text-gray-500 mb-1">Anonymous Sessions</p>
            <p class="text-2xl font-bold text-gray-400" id="statAnon">—</p>
        </div>
    </div>

    <!-- Table Card -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-700">Sessions</h2>
            <button id="btnLogoutAll"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-red-50 text-red-700 hover:bg-red-100 border border-red-200 transition">
                Force Logout All Others
            </button>
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
            <p class="text-sm">No active sessions found</p>
        </div>

        <!-- Table -->
        <div id="tableWrapper" class="hidden overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP Address</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Browser / Device</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Activity</th>
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

async function loadSessions() {
    try {
        const res = await fetch('/api/admin/sessions', { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message);

        sessions = json.data;
        renderStats(sessions);
        renderTable(sessions);
    } catch (e) {
        console.error(e);
    }
}

function renderStats(data) {
    document.getElementById('statTotal').textContent = data.length;
    document.getElementById('statIdentified').textContent = data.filter(s => s.user_id).length;
    document.getElementById('statAnon').textContent = data.filter(s => !s.user_id).length;
}

function renderTable(data) {
    document.getElementById('loadingState').classList.add('hidden');

    if (!data.length) {
        document.getElementById('emptyState').classList.remove('hidden');
        return;
    }

    document.getElementById('tableWrapper').classList.remove('hidden');
    const tbody = document.getElementById('sessionTableBody');
    tbody.innerHTML = '';

    data.forEach(s => {
        const name  = s.full_name || s.username || '(unknown)';
        const eci   = s.eci ? `<span class="text-xs text-gray-400 ml-1">${s.eci}</span>` : '';
        const agent = parseAgent(s.user_agent || '');
        const badge = s.is_current
            ? '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">You</span>'
            : '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Active</span>';

        const row = document.createElement('tr');
        row.className = 'hover:bg-gray-50 transition-colors';
        row.innerHTML = `
            <td class="px-4 py-3">
                <p class="font-medium text-gray-800">${htmlEsc(name)}${eci}</p>
                <p class="text-xs text-gray-400">${htmlEsc(s.email || '—')}</p>
            </td>
            <td class="px-4 py-3 text-gray-600">${htmlEsc(s.ip_address || '—')}</td>
            <td class="px-4 py-3 text-gray-500 text-xs max-w-[200px] truncate" title="${htmlEsc(s.user_agent || '')}">${agent}</td>
            <td class="px-4 py-3 text-gray-500 text-xs">${htmlEsc(s.last_activity_at)}</td>
            <td class="px-4 py-3">${badge}</td>
            <td class="px-4 py-3 text-right">
                ${!s.is_current ? `<button onclick='openModal(${JSON.stringify(s)})' class="text-xs text-red-600 hover:text-red-800 font-medium">Force Logout</button>` : ''}
            </td>
        `;
        tbody.appendChild(row);
    });
}

function parseAgent(ua) {
    if (!ua) return '—';
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
            <span class="text-gray-400 col-span-1">User</span><span class="col-span-2 font-medium">${htmlEsc(s.full_name || s.username || '—')}</span>
            <span class="text-gray-400 col-span-1">ECI</span><span class="col-span-2">${htmlEsc(s.eci || '—')}</span>
            <span class="text-gray-400 col-span-1">Email</span><span class="col-span-2">${htmlEsc(s.email || '—')}</span>
            <span class="text-gray-400 col-span-1">IP</span><span class="col-span-2">${htmlEsc(s.ip_address || '—')}</span>
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
    if (!confirm('Force logout this session?')) return;
    await forceLogout(selectedSessionId);
    closeModal();
});

document.getElementById('btnLogoutAll').addEventListener('click', async () => {
    if (!confirm('Force logout ALL other sessions? This cannot be undone.')) return;
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

loadSessions();
</script>
@endsection

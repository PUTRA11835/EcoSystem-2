@extends('dashboard')

@section('title', 'Control Center')
@section('page-title', 'Control Center')
@section('page-subtitle', 'System monitoring and administration tools')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="space-y-6">

    <!-- Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">

        <!-- Activity Log Card -->
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden flex flex-col">
            <div class="p-5 flex-1">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center">
                        <i class="fas fa-shield-alt text-indigo-600 text-base"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-800">Activity Log</h3>
                        <p class="text-xs text-gray-400">Login, logout & access trail</p>
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-2 mb-1">
                    <div class="bg-gray-50 rounded-xl p-3 text-center">
                        <p class="text-lg font-bold text-gray-800" id="logTotal">—</p>
                        <p class="text-xs text-gray-400 mt-0.5">Total</p>
                    </div>
                    <div class="bg-green-50 rounded-xl p-3 text-center">
                        <p class="text-lg font-bold text-green-600" id="logSuccess">—</p>
                        <p class="text-xs text-gray-400 mt-0.5">Success</p>
                    </div>
                    <div class="bg-red-50 rounded-xl p-3 text-center">
                        <p class="text-lg font-bold text-red-500" id="logFailed">—</p>
                        <p class="text-xs text-gray-400 mt-0.5">Failed</p>
                    </div>
                </div>
            </div>
            <div class="px-5 pb-5">
                <a href="{{ route('admin.activity-log') }}"
                   class="block w-full text-center px-4 py-2.5 rounded-xl bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 transition">
                    Open Activity Log
                </a>
            </div>
        </div>

        <!-- Active Sessions Card -->
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden flex flex-col">
            <div class="p-5 flex-1">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center">
                        <i class="fas fa-users text-emerald-600 text-base"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-800">Active Sessions</h3>
                        <p class="text-xs text-gray-400">Who is currently logged in</p>
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-2 mb-1">
                    <div class="bg-gray-50 rounded-xl p-3 text-center">
                        <p class="text-lg font-bold text-gray-800" id="sessTotal">—</p>
                        <p class="text-xs text-gray-400 mt-0.5">Active</p>
                    </div>
                    <div class="bg-blue-50 rounded-xl p-3 text-center">
                        <p class="text-lg font-bold text-blue-600" id="sessIdentified">—</p>
                        <p class="text-xs text-gray-400 mt-0.5">Logged In</p>
                    </div>
                    <div class="bg-gray-50 rounded-xl p-3 text-center">
                        <p class="text-lg font-bold text-gray-400" id="sessAnon">—</p>
                        <p class="text-xs text-gray-400 mt-0.5">Guest</p>
                    </div>
                </div>
            </div>
            <div class="px-5 pb-5">
                <a href="{{ route('admin.sessions') }}"
                   class="block w-full text-center px-4 py-2.5 rounded-xl bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700 transition">
                    Manage Sessions
                </a>
            </div>
        </div>

        <!-- Failed Jobs Card -->
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden flex flex-col">
            <div class="p-5 flex-1">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-xl bg-red-50 flex items-center justify-center">
                        <i class="fas fa-exclamation-triangle text-red-500 text-base"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-800">Failed Jobs</h3>
                        <p class="text-xs text-gray-400">Queue errors requiring attention</p>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-2 mb-1">
                    <div id="jobsHealthy" class="hidden col-span-2 bg-green-50 rounded-xl p-4 flex items-center gap-3">
                        <i class="fas fa-check-circle text-green-500 text-xl"></i>
                        <div>
                            <p class="text-sm font-semibold text-green-700">Queue Healthy</p>
                            <p class="text-xs text-gray-400">No failed jobs</p>
                        </div>
                    </div>
                    <div id="jobsError" class="hidden col-span-2 bg-red-50 rounded-xl p-4 flex items-center gap-3">
                        <i class="fas fa-exclamation-circle text-red-500 text-xl"></i>
                        <div>
                            <p class="text-sm font-semibold text-red-700" id="jobsCount">— Failed Jobs</p>
                            <p class="text-xs text-gray-400" id="jobsNewest">—</p>
                        </div>
                    </div>
                    <div id="jobsLoading" class="col-span-2 bg-gray-50 rounded-xl p-4 flex items-center gap-3">
                        <svg class="animate-spin h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        <p class="text-sm text-gray-400">Checking queue...</p>
                    </div>
                </div>
            </div>
            <div class="px-5 pb-5">
                <a href="{{ route('admin.failed-jobs') }}"
                   class="block w-full text-center px-4 py-2.5 rounded-xl bg-red-500 text-white text-sm font-medium hover:bg-red-600 transition">
                    Open Failed Jobs
                </a>
            </div>
        </div>

        <!-- Backup & Export Card -->
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden flex flex-col">
            <div class="p-5 flex-1">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center">
                        <i class="fas fa-database text-blue-600 text-base"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-800">Backup & Export</h3>
                        <p class="text-xs text-gray-400">Database backup & CSV exports</p>
                    </div>
                </div>
                <div class="space-y-2">
                    <div class="bg-blue-50 rounded-xl p-3 flex items-center gap-3">
                        <i class="fas fa-save text-blue-500 text-sm"></i>
                        <div>
                            <p class="text-xs font-medium text-gray-700">DB Backup</p>
                            <p class="text-xs text-gray-400">mysqldump full backup</p>
                        </div>
                    </div>
                    <div class="bg-emerald-50 rounded-xl p-3 flex items-center gap-3">
                        <i class="fas fa-file-csv text-emerald-500 text-sm"></i>
                        <div>
                            <p class="text-xs font-medium text-gray-700">CSV Export</p>
                            <p class="text-xs text-gray-400">Employee & ticket data</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="px-5 pb-5">
                <a href="{{ route('admin.backup') }}"
                   class="block w-full text-center px-4 py-2.5 rounded-xl bg-blue-600 text-white text-sm font-medium hover:bg-blue-700 transition">
                    Open Backup & Export
                </a>
            </div>
        </div>

    </div>

    <!-- Quick Stats Row -->
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">System Health</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="flex items-center gap-3">
                <div class="w-2.5 h-2.5 rounded-full bg-green-500" id="dbStatus"></div>
                <div>
                    <p class="text-xs font-medium text-gray-700">Database</p>
                    <p class="text-xs text-gray-400" id="dbStatusText">Checking...</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <div class="w-2.5 h-2.5 rounded-full bg-gray-300" id="queueStatus"></div>
                <div>
                    <p class="text-xs font-medium text-gray-700">Queue</p>
                    <p class="text-xs text-gray-400" id="queueStatusText">Checking...</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <div class="w-2.5 h-2.5 rounded-full bg-blue-500"></div>
                <div>
                    <p class="text-xs font-medium text-gray-700">Environment</p>
                    <p class="text-xs text-gray-400">{{ config('app.env') }}</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <div class="w-2.5 h-2.5 rounded-full bg-purple-500"></div>
                <div>
                    <p class="text-xs font-medium text-gray-700">API Docs</p>
                    <a href="/docs/api" target="_blank" class="text-xs text-purple-600 hover:underline">Open Docs →</a>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
async function loadAll() {
    loadActivityStats();
    loadSessionStats();
    loadJobStats();
    loadHealth();
}

async function loadActivityStats() {
    try {
        const res  = await fetch('/api/admin/activity-logs?per_page=1', { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) return;

        const stats = json.stats ?? {};
        document.getElementById('logTotal').textContent   = json.meta?.total ?? '—';
        document.getElementById('logSuccess').textContent = stats.success ?? '—';
        document.getElementById('logFailed').textContent  = stats.failed  ?? '—';
    } catch (e) {}
}

async function loadSessionStats() {
    try {
        const res  = await fetch('/api/admin/sessions', { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) return;

        const data = json.data ?? [];
        document.getElementById('sessTotal').textContent      = data.length;
        document.getElementById('sessIdentified').textContent = data.filter(s => s.user_id).length;
        document.getElementById('sessAnon').textContent       = data.filter(s => !s.user_id).length;
    } catch (e) {}
}

async function loadJobStats() {
    try {
        const res  = await fetch('/api/admin/failed-jobs?per_page=1', { credentials: 'same-origin' });
        const json = await res.json();

        document.getElementById('jobsLoading').classList.add('hidden');

        if (!json.success) return;

        const total = json.pagination?.total ?? 0;
        if (total === 0) {
            document.getElementById('jobsHealthy').classList.remove('hidden');
        } else {
            document.getElementById('jobsError').classList.remove('hidden');
            document.getElementById('jobsCount').textContent  = `${total} Failed Job${total > 1 ? 's' : ''}`;
            document.getElementById('jobsNewest').textContent = `Latest: ${json.data?.[0]?.failed_at ?? '—'}`;
        }
    } catch (e) {
        document.getElementById('jobsLoading').classList.add('hidden');
    }
}

async function loadHealth() {
    try {
        const res  = await fetch('/api/health', { credentials: 'same-origin' });
        const json = await res.json();

        const dbOk = json.checks?.database === 'ok';
        document.getElementById('dbStatus').className     = `w-2.5 h-2.5 rounded-full ${dbOk ? 'bg-green-500' : 'bg-red-500'}`;
        document.getElementById('dbStatusText').textContent = dbOk ? 'Connected' : 'Error';

        const pending = json.checks?.queue_pending ?? 0;
        const failed  = json.checks?.queue_failed  ?? 0;
        const queueOk = failed === 0;
        document.getElementById('queueStatus').className     = `w-2.5 h-2.5 rounded-full ${queueOk ? 'bg-green-500' : 'bg-orange-500'}`;
        document.getElementById('queueStatusText').textContent = `${pending} pending, ${failed} failed`;
    } catch (e) {}
}

loadAll();
</script>
@endsection

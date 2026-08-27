@extends('dashboard')
@section('title', 'Leave & Permit Attendance')
@section('page-title', 'Leave & Permit Attendance Management')
@section('page-subtitle', 'Manage master leave types, employee attendance quotas, review applications, and view analytics reports')

@section('content')
    <div class="w-full space-y-6 px-1 lg:px-2">
        <!-- Header Section -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl primary-gradient flex items-center justify-center shadow-sm text-white">
                    <i class="fas fa-user-shield text-base"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-gray-900 leading-tight">Leave & Permit Management (HR / Admin)</h1>
                    <p class="text-xs text-gray-400 mt-0.5">Manage master leave & permit types, review employee
                        applications, inspect quota balances, and view reports</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <!-- Year Selector Filter -->
                <div
                    class="flex items-center gap-2 bg-white border border-gray-200 rounded-lg px-3 py-1.5 shadow-sm text-xs">
                    <i class="fas fa-calendar-alt text-gray-400"></i>
                    <span class="font-semibold text-gray-600">Year:</span>
                    <select id="selectGlobalYear"
                        class="bg-transparent font-bold text-gray-800 focus:outline-none cursor-pointer"
                        onchange="onGlobalYearChange()">
                        @php $cYear = $currentYear ?? (int) date('Y'); @endphp
                        @for($y = $cYear + 1; $y >= $cYear - 2; $y--)
                            <option value="{{ $y }}" {{ $y === $cYear ? 'selected' : '' }}>{{ $y }}</option>
                        @endfor
                    </select>
                </div>

                <!-- Action Buttons -->
                <button onclick="openApplyModal()"
                    class="inline-flex items-center gap-1.5 px-4 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-all shadow-sm">
                    <i class="fas fa-plus text-xs"></i>
                    <span>Log Leave / Permit</span>
                </button>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="border-b border-gray-200 bg-white rounded-t-xl px-4 pt-2 shadow-sm">
            <nav class="flex space-x-6 text-xs sm:text-sm font-semibold" aria-label="Tabs">
                <button id="tabBtnInbox" onclick="switchTab('inbox')"
                    class="py-3 border-b-2 border-red-700 text-red-700 flex items-center gap-2 transition-colors">
                    <i class="fas fa-inbox"></i> Approval Inbox
                    <span id="badgePendingCount"
                        class="bg-yellow-100 text-yellow-800 text-[10px] font-bold px-2 py-0.5 rounded-full hidden">0</span>
                </button>

                <button id="tabBtnTypes" onclick="switchTab('types')"
                    class="py-3 border-b-2 border-transparent text-gray-500 hover:text-gray-700 flex items-center gap-2 transition-colors">
                    <i class="fas fa-layer-group"></i> Master Data Leave Type
                </button>

                <button id="tabBtnAllQuotas" onclick="switchTab('all_quotas')"
                    class="py-3 border-b-2 border-transparent text-gray-500 hover:text-gray-700 flex items-center gap-2 transition-colors">
                    <i class="fas fa-users text-indigo-600"></i> All Employee Quotas
                </button>

                <button id="tabBtnReport" onclick="switchTab('report')"
                    class="py-3 border-b-2 border-transparent text-gray-500 hover:text-gray-700 flex items-center gap-2 transition-colors">
                    <i class="fas fa-chart-bar"></i> Reports & Analytics
                </button>
            </nav>
        </div>

        <!-- ==================== TAB 1: HR APPROVAL INBOX ==================== -->
        <div id="tabContentInbox" class="space-y-4">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div
                    class="px-5 py-3.5 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <h3 class="font-bold text-xs uppercase tracking-wider text-gray-700 flex items-center gap-2">
                        <i class="fas fa-inbox text-yellow-600"></i> Employee Applications Pending Review
                    </h3>
                    <div class="flex items-center gap-2">
                        <select id="filterInboxStatus" class="text-xs border border-gray-300 rounded-lg px-2.5 py-1.5"
                            onchange="loadInboxApplications()">
                            <option value="">All Statuses</option>
                            <option value="pending" selected>Pending Only</option>
                            <option value="approved">Approved</option>
                            <option value="revision">Revision Requested</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead
                            class="bg-gray-50 text-gray-500 uppercase tracking-wider text-[10px] font-bold border-b border-gray-200">
                            <tr>
                                <th class="px-5 py-3">App No & Date</th>
                                <th class="px-4 py-3">Employee Name</th>
                                <th class="px-4 py-3">Type</th>
                                <th class="px-4 py-3">Period</th>
                                <th class="px-4 py-3 text-center">Total Days</th>
                                <th class="px-4 py-3 text-center">Quota Limit Check</th>
                                <th class="px-4 py-3 text-center">Status</th>
                                <th class="px-5 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="tblInboxBody" class="divide-y divide-gray-100 text-gray-700">
                            <tr>
                                <td colspan="8" class="px-5 py-6 text-center text-gray-400">Loading inbox...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ==================== TAB 2: JENIS CUTI MASTER (Add & Edit Only - Requirement 1) ==================== -->
        <div id="tabContentTypes" class="space-y-6 hidden">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
                    <div>
                        <h3 class="font-bold text-sm text-gray-800 flex items-center gap-2">
                            <i class="fas fa-layer-group text-red-600"></i> Master Data Leave & Permit Type
                        </h3>
                        <p class="text-xs text-gray-500 mt-0.5">Manage master leave types, quotas, paid/unpaid provisions,
                            and gender eligibility rules. Delete action is restricted to maintain history integrity.</p>
                    </div>
                    <button onclick="openAddTypeModal()"
                        class="px-3.5 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-all shadow-sm">
                        + Add New Leave Type
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left border-collapse">
                        <thead
                            class="bg-gray-100 text-gray-700 uppercase tracking-wider text-[10px] font-bold border-b border-gray-200">
                            <tr>
                                <th class="px-3 py-3 text-center">No</th>
                                <th class="px-3 py-3">Code</th>
                                <th class="px-4 py-3">Leave & Permit Type</th>
                                <th class="px-3 py-3 text-center">Default Quota</th>
                                <th class="px-3 py-3 text-center">Paid Status</th>
                                <th class="px-3 py-3 text-center">Gender Target</th>
                                <th class="px-3 py-3 text-center">Status</th>
                                <th class="px-4 py-3">Description</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="tblMasterTypesBody" class="divide-y divide-gray-100 text-gray-700">
                            @foreach($allTypes as $idx => $t)
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-3 py-3 text-center font-bold text-gray-500">{{ $idx + 1 }}</td>
                                    <td class="px-3 py-3 font-bold text-red-700">{{ $t->code }}</td>
                                    <td class="px-4 py-3 font-semibold text-gray-900">{{ $t->name }}</td>
                                    <td class="px-3 py-3 text-center font-bold">
                                        {{ $t->default_quota > 0 ? (int) $t->default_quota . ' days' : ($t->code === 'CTU' ? 'No quota' : '0 (Event)') }}
                                    </td>
                                    <td class="px-3 py-3 text-center">
                                        @if($t->is_paid)
                                            <span
                                                class="bg-green-100 text-green-800 font-bold px-2 py-0.5 rounded text-[10px]">Paid</span>
                                        @else
                                            <span
                                                class="bg-amber-100 text-amber-800 font-bold px-2 py-0.5 rounded text-[10px]">Unpaid</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3 text-center font-bold uppercase text-gray-600">{{ $t->gender_target }}
                                    </td>
                                    <td class="px-3 py-3 text-center">
                                        @if($t->is_active)
                                            <span
                                                class="bg-green-100 text-green-800 font-bold px-2 py-0.5 rounded text-[10px]">Active</span>
                                        @else
                                            <span
                                                class="bg-gray-200 text-gray-700 font-bold px-2 py-0.5 rounded text-[10px]">Nonactive</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 leading-relaxed">{{ $t->description }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="inline-flex items-center justify-end gap-2">
                                            <!-- Edit Pencil Icon Button -->
                                            <button onclick='openEditTypeModal(@json($t))' title="Edit Leave Type"
                                                class="w-8 h-8 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-600 inline-flex items-center justify-center transition-all">
                                                <i class="fas fa-pencil-alt text-xs"></i>
                                            </button>

                                            <!-- Toggle Switch On/Off -->
                                            <button onclick="toggleTypeActive({{ $t->id }}, {{ $t->is_active ? 1 : 0 }})"
                                                title="{{ $t->is_active ? 'Click to Deactivate' : 'Click to Activate' }}"
                                                class="relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none {{ $t->is_active ? 'bg-green-500' : 'bg-gray-300' }}">
                                                <span
                                                    class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $t->is_active ? 'translate-x-4' : 'translate-x-0' }}"></span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ==================== TAB 3: ALL EMPLOYEES QUOTAS SUMMARY (Requirement 8) ==================== -->
        <div id="tabContentAllQuotas" class="space-y-4 hidden">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div
                    class="px-5 py-3.5 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h3 class="font-bold text-xs uppercase tracking-wider text-gray-700 flex items-center gap-2">
                            <i class="fas fa-users text-indigo-600"></i> All Employee Quota Balances Summary
                        </h3>
                        <p class="text-xs text-gray-400 mt-0.5">Overview of total quota allocated, used, and remaining
                            balances per employee.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="text" id="searchAllQuotasInput" placeholder="🔍 Search employee name or ECI..."
                            class="text-xs border border-gray-300 rounded-lg px-3 py-1.5 focus:ring-1 focus:ring-red-500"
                            onkeyup="loadAllEmployeesQuotas()">
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead
                            class="bg-gray-50 text-gray-500 uppercase tracking-wider text-[10px] font-bold border-b border-gray-200">
                            <tr>
                                <th class="px-5 py-3 text-center">No</th>
                                <th class="px-5 py-3">Employee Name</th>
                                <th class="px-4 py-3">ECI</th>
                                <th class="px-4 py-3 text-right">Total Allocated</th>
                                <th class="px-4 py-3 text-right">Total Used</th>
                                <th class="px-4 py-3 text-right">Pending</th>
                                <th class="px-5 py-3 text-right font-bold">Total Remaining</th>
                                <th class="px-5 py-3 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody id="tblAllQuotasSummaryBody" class="divide-y divide-gray-100 text-gray-700">
                            <tr>
                                <td colspan="8" class="px-5 py-6 text-center text-gray-400">Loading all employee quotas...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ==================== TAB 4: HR MONTHLY & YEARLY REPORTS ==================== -->
        <div id="tabContentReport" class="space-y-6 hidden">
            <!-- Report Filters — Year is controlled by the global Year selector in the page header -->
            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm flex flex-wrap items-center justify-between gap-4 text-xs">
                <div class="flex items-center gap-3 flex-wrap">
                    <div class="flex items-center gap-1.5 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-gray-500">
                        <i class="fas fa-calendar-alt text-red-500"></i>
                        <span class="font-semibold text-gray-600">Year:</span>
                        <span id="rptYearDisplay" class="font-bold text-gray-800">{{ $currentYear ?? date('Y') }}</span>
                        <span class="ml-1 text-[10px] text-gray-400">(change via header selector)</span>
                    </div>
                    <div>
                        <label class="block font-semibold text-gray-500 mb-1">Month Period</label>
                        <select id="rptFilterMonth" class="border border-gray-300 rounded-lg px-3 py-1.5 font-semibold text-gray-700 focus:ring-1 focus:ring-red-500" onchange="loadReportData()">
                            <option value="">All Months (Full Annual Report)</option>
                            <option value="1">January</option>
                            <option value="2">February</option>
                            <option value="3">March</option>
                            <option value="4">April</option>
                            <option value="5">May</option>
                            <option value="6">June</option>
                            <option value="7">July</option>
                            <option value="8">August</option>
                            <option value="9">September</option>
                            <option value="10">October</option>
                            <option value="11">November</option>
                            <option value="12">December</option>
                        </select>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button onclick="loadReportData()" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-lg transition-all shadow-sm">
                        <i class="fas fa-sync-alt"></i> Refresh Analytics Data
                    </button>
                </div>
            </div>

            <!-- Summary Metric Cards for Report (Requirement 1: Added Employee Count Card) -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                    <span class="text-[10px] font-bold text-indigo-600 uppercase tracking-wider block">
                        <i class="fas fa-user-friends mr-1"></i> Employees Taking Leave
                    </span>
                    <p class="text-2xl font-extrabold text-indigo-900 mt-1" id="rptStatEmployees">0</p>
                    <span class="text-[10px] text-gray-400 font-normal block mt-0.5" id="rptStatEmployeesSub">0 approved</span>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">Total Submissions</span>
                    <p class="text-2xl font-extrabold text-gray-800 mt-1" id="rptStatTotal">0</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">Approved Days Taken</span>
                    <p class="text-2xl font-extrabold text-green-600 mt-1" id="rptStatApprovedDays">0 <span class="text-xs text-gray-400 font-normal">days</span></p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">Pending Review</span>
                    <p class="text-2xl font-extrabold text-yellow-600 mt-1" id="rptStatPending">0</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">Rejected / Revised</span>
                    <p class="text-2xl font-extrabold text-red-600 mt-1" id="rptStatRejected">0</p>
                </div>
            </div>

            <!-- Breakdown Section 1: Employee Attendance & Leave Recap Table -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
                    <h3 class="font-bold text-xs uppercase tracking-wider text-gray-700">
                        <i class="fas fa-users text-red-600 mr-1.5"></i> Employee Attendance & Leave Recap Summary
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider text-[10px] font-bold border-b border-gray-200">
                            <tr>
                                <th class="px-5 py-3">Employee Name</th>
                                <th class="px-4 py-3">ECI</th>
                                <th class="px-4 py-3 text-center">Total Requests</th>
                                <th class="px-4 py-3 text-right text-green-700">Approved Days</th>
                                <th class="px-4 py-3 text-right text-yellow-700">Pending Days</th>
                                <th class="px-4 py-3 text-right text-red-700">Rejected</th>
                            </tr>
                        </thead>
                        <tbody id="tblRptEmployeeBody" class="divide-y divide-gray-100 text-gray-700">
                            <tr>
                                <td colspan="6" class="px-5 py-6 text-center text-gray-400">Loading employee report breakdown...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Breakdown Section 2: Leave & Permit Type Distribution Table -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100 bg-gray-50">
                    <h3 class="font-bold text-xs uppercase tracking-wider text-gray-700">
                        <i class="fas fa-chart-pie text-indigo-600 mr-1.5"></i> Leave & Permit Type Distribution Analysis
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider text-[10px] font-bold border-b border-gray-200">
                            <tr>
                                <th class="px-5 py-3">Leave Type Name</th>
                                <th class="px-4 py-3 text-center">Category</th>
                                <th class="px-4 py-3 text-center">Total Requests</th>
                                <th class="px-4 py-3 text-center text-green-700">Approved Count</th>
                                <th class="px-4 py-3 text-right font-bold text-green-700">Approved Days</th>
                                <th class="px-4 py-3 text-center text-yellow-700">Pending Requests</th>
                            </tr>
                        </thead>
                        <tbody id="tblRptTypeBody" class="divide-y divide-gray-100 text-gray-700">
                            <tr>
                                <td colspan="6" class="px-5 py-6 text-center text-gray-400">Loading leave type analysis...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Breakdown Section 3: Monthly Distribution Overview (Annual Report Mode) -->
            <div id="annualMonthlyOverviewWrapper" class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100 bg-gray-50">
                    <h3 class="font-bold text-xs uppercase tracking-wider text-gray-700">
                        <i class="fas fa-calendar-alt text-amber-600 mr-1.5"></i> Annual Monthly Distribution Overview (Jan - Dec)
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider text-[10px] font-bold border-b border-gray-200">
                            <tr>
                                <th class="px-5 py-3">Month</th>
                                <th class="px-4 py-3 text-center">Unique Employees</th>
                                <th class="px-4 py-3 text-center">Total Applications</th>
                                <th class="px-4 py-3 text-right font-bold text-green-700">Approved Days Taken</th>
                            </tr>
                        </thead>
                        <tbody id="tblRptMonthBody" class="divide-y divide-gray-100 text-gray-700">
                            <tr>
                                <td colspan="4" class="px-5 py-6 text-center text-gray-400">Loading annual monthly recap...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Partial Views -->
    @include('hr-general.leave-permit.partials.modal-apply')
    @include('hr-general.leave-permit.partials.modal-type-form')
    @include('hr-general.leave-permit.partials.modal-review')
    @include('hr-general.leave-permit.partials.modal-employee-quota-detail')

    <script>
        const isHR = true;
        let globalYear = parseInt(document.getElementById('selectGlobalYear').value);
        const globalCurrentEmpId = {{ $employeeId ?? 'null' }};
        let currentReviewApp = null;
        let pendingReviewAction = null;

        document.addEventListener('DOMContentLoaded', () => {
            loadInboxApplications();
            loadAllEmployeesQuotas();
        });

        function onGlobalYearChange() {
            globalYear = parseInt(document.getElementById('selectGlobalYear').value);
            // Sync year display in report tab
            const rptYearDisplay = document.getElementById('rptYearDisplay');
            if (rptYearDisplay) rptYearDisplay.innerText = globalYear;
            loadInboxApplications();
            loadAllEmployeesQuotas();
            loadReportData();
        }

        function switchTab(tabName) {
            const tabs = ['inbox', 'types', 'all_quotas', 'report'];
            tabs.forEach(t => {
                const btn = document.getElementById('tabBtn' + capitalize(t));
                const content = document.getElementById('tabContent' + capitalize(t));

                if (btn && content) {
                    if (t === tabName) {
                        btn.classList.remove('border-transparent', 'text-gray-500');
                        btn.classList.add('border-red-700', 'text-red-700');
                        content.classList.remove('hidden');
                    } else {
                        btn.classList.remove('border-red-700', 'text-red-700');
                        btn.classList.add('border-transparent', 'text-gray-500');
                        content.classList.add('hidden');
                    }
                }
            });

            if (tabName === 'all_quotas') loadAllEmployeesQuotas();
            if (tabName === 'report') loadReportData();
        }

        function capitalize(str) {
            if (str === 'all_quotas') return 'AllQuotas';
            return str.charAt(0).toUpperCase() + str.slice(1);
        }

        // ── HR Inbox ─────────────────────────────────────────────────────────────
        async function loadInboxApplications() {
            const status = document.getElementById('filterInboxStatus').value;
            try {
                const res = await fetch(`/api/hr-general/leave-permit/applications?year=${globalYear}&status=${status}`, { credentials: 'same-origin' });
                const json = await res.json();

                if (json.success) {
                    let html = '';
                    let pendingCount = 0;

                    json.data.forEach(app => {
                        if (app.status === 'pending') pendingCount++;

                        let quotaBadge = '';
                        if (app.is_event_based) {
                            if (app.type_code === 'CTU' || (app.leave_permit_type && app.leave_permit_type.code === 'CTU')) {
                                quotaBadge = `<span class="bg-purple-100 text-purple-800 font-bold px-2 py-0.5 rounded text-[10px]">Event-based</span>`;
                            } else {
                                quotaBadge = `<span class="bg-purple-100 text-purple-800 font-bold px-2 py-0.5 rounded text-[10px]">Doctor Note Event</span>`;
                            }
                        } else if (app.is_within_quota) {
                            quotaBadge = `<span class="bg-green-100 text-green-800 font-bold px-2 py-0.5 rounded text-[10px]">✓ Within Limit (${app.remaining_quota}d avail)</span>`;
                        } else {
                            quotaBadge = `<span class="bg-red-100 text-red-800 font-bold px-2 py-0.5 rounded text-[10px]">🔴 Exceeded (${app.remaining_quota}d avail)</span>`;
                        }

                        html += `
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-5 py-3">
                                    <span class="font-bold text-gray-900 block">${app.application_no}</span>
                                    <span class="text-[10px] text-gray-400">${new Date(app.created_at).toLocaleDateString()}</span>
                                </td>
                                <td class="px-4 py-3 font-semibold text-gray-800">${app.employee_display_name}</td>
                                <td class="px-4 py-3">${app.type_name}</td>
                                <td class="px-4 py-3 text-gray-600">${app.start_date} ~ ${app.end_date}</td>
                                <td class="px-4 py-3 text-center font-bold text-red-700">${app.total_days}</td>
                                <td class="px-4 py-3 text-center">${quotaBadge}</td>
                                <td class="px-4 py-3 text-center">${renderStatusBadge(app.status)}</td>
                                <td class="px-5 py-3 text-right">
                                    <button onclick='openReviewModal(${JSON.stringify(app)})' class="px-3 py-1 primary-gradient text-white text-[11px] font-semibold rounded hover:opacity-90">
                                        Review / Edit
                                    </button>
                                </td>
                            </tr>
                        `;
                    });

                    document.getElementById('tblInboxBody').innerHTML = html || `<tr><td colspan="8" class="px-5 py-6 text-center text-gray-400">Inbox is empty.</td></tr>`;

                    const badge = document.getElementById('badgePendingCount');
                    if (badge) {
                        badge.innerText = pendingCount;
                        badge.classList.toggle('hidden', pendingCount === 0);
                    }
                }
            } catch (err) {
                console.error(err);
            }
        }

        // ── All Employees Quotas Table (Requirement 8) ───────────────────────────
        async function loadAllEmployeesQuotas() {
            const search = document.getElementById('searchAllQuotasInput').value;
            const tbody = document.getElementById('tblAllQuotasSummaryBody');
            try {
                const res = await fetch(`/api/hr-general/leave-permit/all-employees-quotas?year=${globalYear}&search=${encodeURIComponent(search)}`, { credentials: 'same-origin' });
                const json = await res.json();

                if (json.success) {
                    if (json.data.length === 0) {
                        tbody.innerHTML = `<tr><td colspan="8" class="px-5 py-6 text-center text-gray-400">No employee quota records found.</td></tr>`;
                        return;
                    }

                    let html = '';
                    json.data.forEach((emp, idx) => {
                        html += `
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-5 py-3 text-center font-bold text-gray-500">${idx + 1}</td>
                                <td class="px-5 py-3 font-bold text-gray-900">${emp.display_name}</td>
                                <td class="px-4 py-3 text-gray-600">${emp.eci}</td>
                                <td class="px-4 py-3 text-right font-semibold text-gray-700">${emp.total_allocated} days</td>
                                <td class="px-4 py-3 text-right font-semibold text-green-600">${emp.total_used} days</td>
                                <td class="px-4 py-3 text-right font-semibold text-yellow-600">${emp.total_pending} days</td>
                                <td class="px-5 py-3 text-right font-bold ${emp.total_remaining > 0 ? 'text-red-700' : 'text-gray-400'}">${emp.total_remaining} days</td>
                                <td class="px-5 py-3 text-center">
                                    <button onclick="openEmployeeQuotaDetailModal(${emp.employee_id}, '${emp.display_name}')" class="px-2.5 py-1 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 text-xs font-semibold rounded transition-colors flex items-center justify-center gap-1 mx-auto">
                                        <i class="fas fa-list text-[10px]"></i> View Details
                                    </button>
                                </td>
                            </tr>
                        `;
                    });

                    tbody.innerHTML = html;
                }
            } catch (err) {
                console.error(err);
            }
        }

        // ── Open Employee Quota Detailed Breakdown Modal ─────────────────────────
        async function openEmployeeQuotaDetailModal(empId, empName) {
            document.getElementById('empQuotaDetailTitle').innerText = empName;
            document.getElementById('empQuotaDetailSubtitle').innerText = `Quota Breakdown for Year ${globalYear}`;
            const tbody = document.getElementById('tblEmpQuotaDetailBody');
            tbody.innerHTML = `<tr><td colspan="6" class="px-4 py-6 text-center text-gray-400">Loading quota details...</td></tr>`;

            document.getElementById('modalEmployeeQuotaDetail').classList.remove('hidden');

            try {
                const res = await fetch(`/api/hr-general/leave-permit/employee-quota-detail/${empId}?year=${globalYear}`, { credentials: 'same-origin' });
                const json = await res.json();

                if (json.success) {
                    let html = '';
                    json.data.forEach(item => {
                        let ruleBadge = item.is_monthly_reset
                            ? `<span class="bg-blue-100 text-blue-800 text-[10px] font-bold px-1.5 py-0.5 rounded">Resets Monthly</span>`
                            : (item.is_event_based
                                ? (item.type_code === 'CTU' || !item.requires_attachment
                                    ? `<span class="bg-purple-100 text-purple-800 text-[10px] font-bold px-1.5 py-0.5 rounded">Event-based</span>`
                                    : `<span class="bg-purple-100 text-purple-800 text-[10px] font-bold px-1.5 py-0.5 rounded">Doctor Note Event</span>`)
                                : `<span class="bg-gray-100 text-gray-800 text-[10px] font-bold px-1.5 py-0.5 rounded">Annual</span>`);

                        const remText = item.is_event_based ? 'Uncapped' : `${item.remaining_quota} days`;

                        html += `
                            <tr class="hover:bg-gray-50 transition-colors text-xs">
                                <td class="px-4 py-2.5 font-semibold text-gray-900">${item.type_name} <span class="text-gray-400">(${item.type_code})</span></td>
                                <td class="px-3 py-2.5 text-center">${ruleBadge}</td>
                                <td class="px-3 py-2.5 text-right text-gray-600">${item.is_event_based ? '-' : item.allocated_quota}</td>
                                <td class="px-3 py-2.5 text-right font-medium text-green-600">${item.used_quota}</td>
                                <td class="px-3 py-2.5 text-right font-medium text-yellow-600">${item.pending_quota}</td>
                                <td class="px-4 py-2.5 text-right font-bold ${item.remaining_quota > 0 || item.is_event_based ? 'text-red-700' : 'text-gray-400'}">${remText}</td>
                            </tr>
                        `;
                    });

                    tbody.innerHTML = html || `<tr><td colspan="6" class="px-4 py-6 text-center text-gray-400">No active types found.</td></tr>`;
                }
            } catch (err) {
                tbody.innerHTML = `<tr><td colspan="6" class="px-4 py-6 text-center text-red-500">Failed to load quota breakdown.</td></tr>`;
            }
        }

        function closeEmployeeQuotaDetailModal() {
            document.getElementById('modalEmployeeQuotaDetail').classList.add('hidden');
        }

        // ── Master Leave Type CRUD & Activation Protection (Requirement 1 & 2) ──
        function openAddTypeModal() {
            document.getElementById('formMasterType').reset();
            document.getElementById('typeFormId').value = '';
            document.getElementById('modalTypeFormTitle').innerText = 'Add Master Leave Type';
            document.getElementById('modalMasterTypeForm').classList.remove('hidden');
        }

        function openEditTypeModal(t) {
            document.getElementById('typeFormId').value = t.id;
            document.getElementById('typeFormCode').value = t.code;
            document.getElementById('typeFormName').value = t.name;
            document.getElementById('typeFormCategory').value = t.category;
            document.getElementById('typeFormDefaultQuota').value = t.default_quota;
            document.getElementById('typeFormMinService').value = t.min_service_period || '';
            document.getElementById('typeFormIsPaid').value = t.is_paid ? '1' : '0';
            document.getElementById('typeFormGenderTarget').value = t.gender_target;
            document.getElementById('typeFormRequiresAttachment').checked = !!t.requires_attachment;
            document.getElementById('typeFormDescription').value = t.description || '';
            document.getElementById('modalTypeFormTitle').innerText = 'Edit Master Leave Type (' + t.code + ')';
            document.getElementById('modalMasterTypeForm').classList.remove('hidden');
        }

        function closeTypeFormModal() {
            document.getElementById('modalMasterTypeForm').classList.add('hidden');
        }

        async function handleTypeFormSubmit(e) {
            e.preventDefault();
            const id = document.getElementById('typeFormId').value;
            const payload = {
                code: document.getElementById('typeFormCode').value,
                name: document.getElementById('typeFormName').value,
                category: document.getElementById('typeFormCategory').value,
                default_quota: parseFloat(document.getElementById('typeFormDefaultQuota').value),
                min_service_period: document.getElementById('typeFormMinService').value,
                is_paid: document.getElementById('typeFormIsPaid').value === '1',
                gender_target: document.getElementById('typeFormGenderTarget').value,
                requires_attachment: document.getElementById('typeFormRequiresAttachment').checked,
                description: document.getElementById('typeFormDescription').value,
            };

            const url = id
                ? `/api/hr-general/leave-permit/master-types/${id}/update`
                : `/api/hr-general/leave-permit/master-types`;

            try {
                const csrf = document.querySelector('meta[name="csrf-token"]').content;
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify(payload),
                    credentials: 'same-origin'
                });

                const json = await res.json();
                if (json.success) {
                    closeTypeFormModal();
                    if (typeof showToast === 'function') {
                        showToast(json.message || 'Master type saved successfully!', 'success');
                    }
                    setTimeout(() => window.location.reload(), 800);
                } else {
                    if (typeof showToast === 'function') {
                        showToast(json.message || 'Failed to save master type.', 'error');
                    }
                }
            } catch (err) {
                if (typeof showToast === 'function') {
                    showToast('Failed to save master type.', 'error');
                }
            }
        }

        async function toggleTypeActive(id, currentStatus) {
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]').content;
                const res = await fetch(`/api/hr-general/leave-permit/master-types/${id}/toggle-active`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf },
                    credentials: 'same-origin'
                });

                const json = await res.json();
                if (json.success) {
                    if (typeof showToast === 'function') {
                        showToast(json.message || 'Status updated successfully!', 'success');
                    }
                    setTimeout(() => window.location.reload(), 800);
                } else {
                    if (typeof showToast === 'function') {
                        showToast(json.message || 'Failed to change active status.', 'error');
                    }
                }
            } catch (err) {
                if (typeof showToast === 'function') {
                    showToast('An error occurred while updating status.', 'error');
                }
            }
        }

        let cachedEmployeesList = [];
        let cachedUserQuotas = [];

        // ── Load Report Data ──────────────────────────────────────────────────────
        async function loadReportData() {
            const year = globalYear; // Synced with the global year selector in the header
            const monthEl = document.getElementById('rptFilterMonth');
            const month = monthEl ? monthEl.value : '';

            try {
                const res = await fetch(`/api/hr-general/leave-permit/reports?year=${year}&month=${month}`, { credentials: 'same-origin' });
                const json = await res.json();

                if (json.success) {
                    const s = json.stats;
                    document.getElementById('rptStatTotal').innerText = s.total_applications || 0;
                    document.getElementById('rptStatApprovedDays').innerHTML = `${s.approved_days || 0} <span class="text-xs text-gray-400 font-normal">days</span>`;
                    document.getElementById('rptStatPending').innerText = s.pending_count || 0;
                    document.getElementById('rptStatRejected').innerText = (s.rejected_count || 0) + (s.revision_count || 0);
                    
                    if (document.getElementById('rptStatEmployees')) {
                        document.getElementById('rptStatEmployees').innerText = s.total_requesting_employees || 0;
                    }
                    if (document.getElementById('rptStatEmployeesSub')) {
                        document.getElementById('rptStatEmployeesSub').innerText = `${s.approved_employees || 0} approved`;
                    }

                    // 1. Employee Recap Table
                    let empHtml = '';
                    if (!json.by_employee || json.by_employee.length === 0) {
                        empHtml = `<tr><td colspan="6" class="px-5 py-6 text-center text-gray-400">No attendance records for this period.</td></tr>`;
                    } else {
                        json.by_employee.forEach(emp => {
                            empHtml += `
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-5 py-3 font-semibold text-gray-900">${emp.employee_name}</td>
                                    <td class="px-4 py-3 text-gray-500 font-medium">${emp.eci}</td>
                                    <td class="px-4 py-3 text-center font-bold">${emp.total_apps}</td>
                                    <td class="px-4 py-3 text-right font-bold text-green-600">${emp.approved_days} days</td>
                                    <td class="px-4 py-3 text-right font-bold text-yellow-600">${emp.pending_days} days</td>
                                    <td class="px-4 py-3 text-right text-red-600 font-semibold">${emp.rejected_apps}</td>
                                </tr>
                            `;
                        });
                    }
                    document.getElementById('tblRptEmployeeBody').innerHTML = empHtml;

                    // 2. Type Distribution Analysis Table
                    if (document.getElementById('tblRptTypeBody')) {
                        let typeHtml = '';
                        if (!json.by_type || json.by_type.length === 0) {
                            typeHtml = `<tr><td colspan="6" class="px-5 py-6 text-center text-gray-400">No leave type activity recorded.</td></tr>`;
                        } else {
                            json.by_type.forEach(t => {
                                typeHtml += `
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-5 py-3 font-semibold text-gray-900">${t.type_name} <span class="text-gray-400 font-normal">(${t.type_code})</span></td>
                                        <td class="px-4 py-3 text-center uppercase text-[10px] font-bold text-gray-500">${t.category}</td>
                                        <td class="px-4 py-3 text-center font-bold">${t.total_count}</td>
                                        <td class="px-4 py-3 text-center text-green-600 font-semibold">${t.approved_count}</td>
                                        <td class="px-4 py-3 text-right font-bold text-green-600">${t.approved_days} days</td>
                                        <td class="px-4 py-3 text-center text-yellow-600 font-semibold">${t.pending_count}</td>
                                    </tr>
                                `;
                            });
                        }
                        document.getElementById('tblRptTypeBody').innerHTML = typeHtml;
                    }

                    // 3. Monthly Breakdown Overview Table (for full annual report)
                    const mWrapper = document.getElementById('annualMonthlyOverviewWrapper');
                    if (mWrapper && !month && json.by_month) {
                        let mHtml = '';
                        json.by_month.forEach(m => {
                            mHtml += `
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-5 py-3 font-bold text-gray-900">${m.month_name}</td>
                                    <td class="px-4 py-3 text-center font-semibold text-indigo-700">${m.unique_employees} employee(s)</td>
                                    <td class="px-4 py-3 text-center font-bold">${m.total_apps}</td>
                                    <td class="px-4 py-3 text-right font-bold text-green-600">${m.approved_days} days</td>
                                </tr>
                            `;
                        });
                        document.getElementById('tblRptMonthBody').innerHTML = mHtml;
                        mWrapper.classList.remove('hidden');
                    } else if (mWrapper) {
                        mWrapper.classList.add('hidden');
                    }
                }
            } catch (err) {
                console.error(err);
            }
        }

        // ── Employee Custom Searchable Dropdown for HR Apply Modal ─────────────────
        async function fetchEmployeesForDropdown() {
            try {
                // Use the lightweight /employees-list endpoint — no quota calculations, loads fast
                const res = await fetch(`/api/hr-general/leave-permit/employees-list`, { credentials: 'same-origin' });
                const json = await res.json();
                if (json.success) {
                    cachedEmployeesList = json.data;
                    renderEmpDropdownItems(cachedEmployeesList);
                }
            } catch (err) {
                console.error('fetchEmployeesForDropdown error:', err);
            }
        }

        function renderEmpDropdownItems(list) {
            const container = document.getElementById('empDropdownItems');
            const emptyMsg  = document.getElementById('empDropdownEmpty');
            if (!container) return;

            if (!list || list.length === 0) {
                container.innerHTML = '';
                if (emptyMsg) emptyMsg.classList.remove('hidden');
                return;
            }
            if (emptyMsg) emptyMsg.classList.add('hidden');

            container.innerHTML = list.map(emp => {
                const isCurrent = emp.employee_id == globalCurrentEmpId;
                const badge = isCurrent ? `<span class="ml-1 text-[9px] bg-red-100 text-red-700 font-bold px-1 py-0.5 rounded">Me / HR</span>` : '';
                return `
                    <div class="flex items-center gap-2.5 px-3 py-2 cursor-pointer hover:bg-red-50 text-xs transition-colors"
                         onclick="selectEmpItem(${emp.employee_id}, '${emp.display_name.replace(/'/g, '\\&#39;')}', '${emp.eci || ''}')">
                        <i class="fas fa-user text-gray-300 flex-shrink-0"></i>
                        <span class="font-semibold text-gray-800 flex-1">${emp.display_name}${badge}</span>
                        <span class="text-gray-400 font-mono text-[10px]">${emp.eci || '-'}</span>
                    </div>`;
            }).join('');
        }

        function toggleEmpDropdown() {
            const list = document.getElementById('empDropdownList');
            if (!list) return;
            const isOpen = !list.classList.contains('hidden');
            if (isOpen) {
                closeEmpDropdown();
            } else {
                openEmpDropdown();
            }
        }

        function openEmpDropdown() {
            const list = document.getElementById('empDropdownList');
            const chevron = document.getElementById('empDropdownChevron');
            if (list) {
                list.classList.remove('hidden');
                list.style.display = 'flex';
            }
            if (chevron) chevron.style.transform = 'rotate(180deg)';
            
            // Render items based on current search input
            filterEmpDropdown();

            // Focus search input
            setTimeout(() => {
                const s = document.getElementById('applyEmployeeSearch');
                if (s) s.focus();
            }, 50);
        }

        function closeEmpDropdown() {
            const list = document.getElementById('empDropdownList');
            const chevron = document.getElementById('empDropdownChevron');
            if (list) {
                list.classList.add('hidden');
                list.style.display = '';
            }
            if (chevron) chevron.style.transform = '';
        }

        function filterEmpDropdown() {
            const searchEl = document.getElementById('applyEmployeeSearch');
            const query = (searchEl ? searchEl.value : '').toLowerCase().trim();

            const filtered = cachedEmployeesList.filter(emp => {
                const nameStr = (emp.display_name || '').toLowerCase();
                const eciStr = (emp.eci || '').toLowerCase();
                return nameStr.includes(query) || eciStr.includes(query);
            });

            renderEmpDropdownItems(filtered);

            const list = document.getElementById('empDropdownList');
            const chevron = document.getElementById('empDropdownChevron');
            if (list && list.classList.contains('hidden')) {
                list.classList.remove('hidden');
                list.style.display = 'flex';
                if (chevron) chevron.style.transform = 'rotate(180deg)';
            }
        }

        function selectEmpItem(id, name, eci) {
            // Set hidden value
            document.getElementById('applyEmployeeId').value = id;
            // Update the toggle button label
            const btnLabel = document.getElementById('empDropdownBtnLabel');
            if (btnLabel) {
                const meTag = (id == globalCurrentEmpId) ? ' (Me / HR)' : '';
                btnLabel.innerText = name + meTag;
                btnLabel.classList.remove('text-gray-500');
                btnLabel.classList.add('text-gray-900', 'font-semibold');
            }
            // Update selected badge below the button
            const badge = document.getElementById('empSelectedBadge');
            const badgeName = document.getElementById('empSelectedName');
            const badgeEci = document.getElementById('empSelectedEci');
            if (badge) { badge.classList.remove('hidden'); badge.classList.add('flex'); }
            if (badgeName) badgeName.innerText = name + ((id == globalCurrentEmpId) ? ' (Me / HR)' : '');
            if (badgeEci) badgeEci.innerText = eci || '';
            // Clear search and close
            const searchEl = document.getElementById('applyEmployeeSearch');
            if (searchEl) searchEl.value = '';
            closeEmpDropdown();
            onApplyEmployeeChange();
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            const wrapper = document.getElementById('empDropdownWrapper');
            if (wrapper && !wrapper.contains(e.target)) {
                closeEmpDropdown();
            }
        });

        async function onApplyEmployeeChange() {
            const empId = document.getElementById('applyEmployeeId') ? document.getElementById('applyEmployeeId').value : '';
            if (!empId) {
                cachedUserQuotas = [];
                cachedUserQuotasForEmp = [];
                const hint = document.getElementById('quotaHintText');
                if (hint) hint.classList.add('hidden');
                return;
            }

            try {
                // Use /user-quotas which accepts an employee_id param
                const res = await fetch(`/api/hr-general/leave-permit/user-quotas?year=${globalYear}&employee_id=${empId}`, { credentials: 'same-origin' });
                const json = await res.json();
                if (json.success) {
                    cachedUserQuotas = json.data;
                    cachedUserQuotasForEmp = json.data;
                    onTypeSelectChange();
                }
            } catch (err) {
                console.error('onApplyEmployeeChange error:', err);
            }
        }

        // ── Apply Modal JS for HR (onTypeSelectChange, calculateDaysPreview, handleApplySubmit) ──
        let cachedUserQuotasForEmp = [];

        function onDayTypeChange() {
            const dayType = document.getElementById('applyDayType') ? document.getElementById('applyDayType').value : 'full';
            const wfhWrapper = document.getElementById('wfhPromptWrapper');
            const startVal = document.getElementById('applyStartDate').value;
            const endInput = document.getElementById('applyEndDate');

            if (dayType === 'half') {
                if (wfhWrapper) wfhWrapper.classList.remove('hidden');
                if (startVal && endInput) endInput.value = startVal;
            } else {
                if (wfhWrapper) wfhWrapper.classList.add('hidden');
            }
            calculateDaysPreview();
        }

        function onTypeSelectChange() {
            const sel = document.getElementById('applyTypeId');
            if (!sel || !sel.value) return;
            const opt = sel.options[sel.selectedIndex];
            const reqAtt = opt.getAttribute('data-requires-attachment') === '1';
            const typeId = parseInt(sel.value);

            const astEl = document.getElementById('attachmentRequiredAsterisk');
            if (astEl) astEl.classList.toggle('hidden', !reqAtt);

            const qItem = cachedUserQuotasForEmp.find(q => q.type_id === typeId);
            const hint = document.getElementById('quotaHintText');

            if (hint && qItem) {
                hint.classList.remove('hidden');
                if (qItem.is_event_based) {
                    if (qItem.type_code === 'CTU' || !qItem.requires_attachment) {
                        hint.innerText = 'ℹ️ Event-based leave: Submitted per occurrence (Unpaid).';
                    } else {
                        hint.innerText = 'ℹ️ Event-based leave: Doctor note or supporting document is required.';
                    }
                    hint.className = 'text-xs text-purple-700 font-semibold mt-1 block';
                } else if (qItem.remaining_quota <= 0) {
                    hint.innerText = '⚠️ Quota for this leave type has been exhausted for selected employee.';
                    hint.className = 'text-xs text-red-600 font-bold mt-1 block';
                } else {
                    hint.innerText = `Available Remaining Quota: ${qItem.remaining_quota} day(s).`;
                    hint.className = 'text-xs text-green-600 font-semibold mt-1 block';
                }
            } else if (hint) {
                hint.classList.add('hidden');
            }
            calculateDaysPreview();
        }

        function calculateDaysPreview() {
            const dayTypeSelect = document.getElementById('applyDayType');
            const dayType = dayTypeSelect ? dayTypeSelect.value : 'full';
            const start = document.getElementById('applyStartDate').value;
            const end = document.getElementById('applyEndDate').value;
            const badge = document.getElementById('daysCountBadge');
            const val = document.getElementById('daysCountValue');
            const warnBanner = document.getElementById('applyQuotaWarningBanner');
            const warnText = document.getElementById('applyQuotaWarningText');

            let requestedDays = 0;
            if (dayType === 'half') {
                requestedDays = 0.5;
                if (val) val.innerText = `0.5 day (Half-Day Leave)`;
                if (badge) { badge.classList.remove('hidden'); badge.classList.add('flex'); }
            } else if (start && end) {
                const d1 = new Date(start);
                const d2 = new Date(end);
                if (d2 >= d1) {
                    const diffTime = Math.abs(d2 - d1);
                    requestedDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
                    if (val) val.innerText = `${requestedDays} day(s)`;
                    if (badge) { badge.classList.remove('hidden'); badge.classList.add('flex'); }
                } else {
                    if (badge) badge.classList.add('hidden');
                }
            } else {
                if (badge) badge.classList.add('hidden');
            }

            // Live quota check preview
            const typeId = parseInt((document.getElementById('applyTypeId') || {}).value || 0);
            const qItem = cachedUserQuotasForEmp.find(q => q.type_id === typeId);
            if (warnBanner && qItem && !qItem.is_event_based && requestedDays > 0) {
                if (requestedDays > qItem.remaining_quota) {
                    if (warnText) warnText.innerText = `Requested (${requestedDays} day(s)) exceeds available quota (${qItem.remaining_quota} day(s)).`;
                    warnBanner.classList.remove('hidden'); warnBanner.classList.add('flex');
                } else {
                    warnBanner.classList.add('hidden'); warnBanner.classList.remove('flex');
                }
            } else if (warnBanner) {
                warnBanner.classList.add('hidden'); warnBanner.classList.remove('flex');
            }
        }

        async function handleApplySubmit(e) {
            e.preventDefault();
            const typeId = parseInt((document.getElementById('applyTypeId') || {}).value || 0);
            const start = document.getElementById('applyStartDate').value;
            const end = document.getElementById('applyEndDate').value;
            const dayTypeSelect = document.getElementById('applyDayType');
            const dayType = dayTypeSelect ? dayTypeSelect.value : 'full';
            let reason = document.getElementById('applyReason').value.trim();

            if (!reason) {
                if (typeof showToast === 'function') showToast('Reason / Purpose is required and cannot be left blank.', 'warning');
                document.getElementById('applyReason').focus();
                return;
            }
            if (!start || !end) {
                if (typeof showToast === 'function') showToast('Please select valid Start Date and End Date.', 'warning');
                return;
            }
            const d1 = new Date(start), d2 = new Date(end);
            if (d2 < d1) {
                if (typeof showToast === 'function') showToast('End Date cannot be earlier than Start Date.', 'warning');
                return;
            }

            let requestedDays = 1.0;
            if (dayType === 'half') {
                requestedDays = 0.5;
                const wfhVal = (document.getElementById('applyWfhOption') || {}).value || 'wfh_off';
                const wfhText = wfhVal === 'wfh_continue'
                    ? 'Continue Working via WFH for remainder of day'
                    : 'No WFH / Off for remainder of day';
                reason += ` [Half-Day Leave: ${wfhText}]`;
            } else {
                requestedDays = Math.ceil(Math.abs(d2 - d1) / (1000 * 60 * 60 * 24)) + 1;
            }

            const qItem = cachedUserQuotasForEmp.find(q => q.type_id === typeId);
            if (qItem && !qItem.is_event_based && requestedDays > qItem.remaining_quota) {
                if (typeof showToast === 'function') {
                    showToast(`Cannot submit: Requested (${requestedDays} day(s)) exceeds remaining quota (${qItem.remaining_quota} day(s)).`, 'error');
                }
                const wb = document.getElementById('applyQuotaWarningBanner');
                if (wb) { wb.classList.remove('hidden'); wb.classList.add('flex'); }
                return;
            }

            const appId = document.getElementById('applyAppId').value;
            const form = document.getElementById('formApplyLeavePermit');
            const formData = new FormData(form);

            // HR-specific: attach selected employee ID & validate selection
            const empIdEl = document.getElementById('applyEmployeeId');
            const selectedEmpId = empIdEl ? empIdEl.value : '';
            if (isHR && !selectedEmpId) {
                if (typeof showToast === 'function') {
                    showToast('Please select an employee before submitting application.', 'warning');
                }
                return;
            }
            if (empIdEl && selectedEmpId) formData.append('employee_id', selectedEmpId);

            formData.append('leave_permit_type_id', typeId);
            formData.append('start_date', start);
            formData.append('end_date', dayType === 'half' ? start : end);
            formData.append('day_type', dayType);
            formData.append('total_days', requestedDays);
            formData.append('reason', reason);

            const fileInput = document.getElementById('applyAttachment');
            if (fileInput && fileInput.files.length > 0) formData.append('attachment', fileInput.files[0]);

            const url = appId
                ? `/api/hr-general/leave-permit/applications/${appId}/update`
                : `/api/hr-general/leave-permit/applications`;

            const btn = document.getElementById('btnSubmitApply');
            btn.disabled = true;
            btn.innerText = 'Submitting...';

            try {
                const csrf = document.querySelector('meta[name="csrf-token"]').content;
                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf },
                    body: formData,
                    credentials: 'same-origin'
                });
                const json = await res.json();
                if (json.success) {
                    closeApplyModal();
                    if (typeof showToast === 'function') showToast(json.message || 'Leave/permit logged successfully!', 'success');
                    loadInboxApplications();
                    loadAllEmployeesQuotas();
                } else {
                    if (typeof showToast === 'function') showToast(json.message || 'Failed to submit.', 'error');
                }
            } catch (err) {
                if (typeof showToast === 'function') showToast('An unexpected error occurred.', 'error');
            } finally {
                btn.disabled = false;
                btn.innerText = 'Submit Application';
            }
        }

        function renderStatusBadge(status) {
            switch (status) {
                case 'approved':
                    return `<span class="bg-green-100 text-green-800 font-bold px-2.5 py-0.5 rounded text-[10px] inline-flex items-center gap-1"><i class="fas fa-check-circle"></i> Approved</span>`;
                case 'pending':
                    return `<span class="bg-yellow-100 text-yellow-800 font-bold px-2.5 py-0.5 rounded text-[10px] inline-flex items-center gap-1"><i class="fas fa-clock"></i> Pending</span>`;
                case 'revision':
                    return `<span class="bg-blue-100 text-blue-800 font-bold px-2.5 py-0.5 rounded text-[10px] inline-flex items-center gap-1"><i class="fas fa-edit"></i> Ask for Edit</span>`;
                case 'rejected':
                    return `<span class="bg-red-100 text-red-800 font-bold px-2.5 py-0.5 rounded text-[10px] inline-flex items-center gap-1"><i class="fas fa-times-circle"></i> Rejected</span>`;
                default:
                    return `<span class="bg-gray-100 text-gray-800 font-bold px-2.5 py-0.5 rounded text-[10px]">${status}</span>`;
            }
        }

        function openApplyModal() {
            document.getElementById('formApplyLeavePermit').reset();
            document.getElementById('applyAppId').value = '';
            document.getElementById('modalApplyTitle').innerText = 'Log Employee Leave / Permit (HR)';
            document.getElementById('daysCountBadge').classList.add('hidden');
            document.getElementById('quotaHintText').classList.add('hidden');
            const warnBanner = document.getElementById('applyQuotaWarningBanner');
            if (warnBanner) warnBanner.classList.add('hidden');

            const dayTypeSel = document.getElementById('applyDayType');
            if (dayTypeSel) dayTypeSel.value = 'full';
            const wfhWrap = document.getElementById('wfhPromptWrapper');
            if (wfhWrap) wfhWrap.classList.add('hidden');

            // Reset custom employee dropdown
            document.getElementById('applyEmployeeId').value = '';
            const searchEl = document.getElementById('applyEmployeeSearch');
            if (searchEl) searchEl.value = '';
            const badge = document.getElementById('empSelectedBadge');
            if (badge) { badge.classList.add('hidden'); badge.classList.remove('flex'); }
            const btnLabel = document.getElementById('empDropdownBtnLabel');
            if (btnLabel) {
                btnLabel.innerText = 'Select an employee...';
                btnLabel.classList.add('text-gray-500');
                btnLabel.classList.remove('text-gray-900', 'font-semibold');
            }
            closeEmpDropdown();

            if (cachedEmployeesList.length > 0) {
                renderEmpDropdownItems(cachedEmployeesList);
            } else {
                fetchEmployeesForDropdown();
            }

            document.getElementById('modalApplyLeavePermit').classList.remove('hidden');
        }

        function closeApplyModal() {
            document.getElementById('modalApplyLeavePermit').classList.add('hidden');
        }

        function openReviewModal(app) {
            currentReviewApp = app;
            document.getElementById('reviewAppId').value = app.id;
            document.getElementById('reviewAppNo').innerText = app.application_no;
            document.getElementById('reviewStatusBadge').innerHTML = renderStatusBadge(app.status);
            document.getElementById('reviewEmpName').innerText = app.employee_display_name;
            document.getElementById('reviewTypeName').innerText = app.type_name;
            document.getElementById('reviewDates').innerText = `${app.start_date} ~ ${app.end_date}`;
            document.getElementById('reviewTotalDays').innerText = `${app.total_days} day(s)`;
            document.getElementById('reviewReason').innerText = app.reason;
            document.getElementById('reviewNoteText').value = '';

            const banner = document.getElementById('reviewQuotaStatusBanner');
            if (banner) {
                if (app.is_event_based) {
                    banner.className = 'p-3.5 rounded-xl border border-purple-200 bg-purple-50 text-purple-800 text-xs flex items-center gap-2.5';
                    banner.innerHTML = `<i class="fas fa-notes-medical text-purple-600 text-base"></i><div><strong class="block">Event-Based Application</strong><span class="text-[11px]">Requested: ${app.total_days} day(s) | Requires doctor note / supporting attachment.</span></div>`;
                } else if (app.is_within_quota) {
                    banner.className = 'p-3.5 rounded-xl border border-green-200 bg-green-50 text-green-800 text-xs flex items-center gap-2.5';
                    banner.innerHTML = `<i class="fas fa-check-circle text-green-600 text-base"></i><div><strong class="block">Within Quota Limit Balance</strong><span class="text-[11px]">Requested: ${app.total_days} day(s) | Available Remaining Quota: <strong>${app.remaining_quota} day(s)</strong></span></div>`;
                } else {
                    banner.className = 'p-3.5 rounded-xl border border-red-200 bg-red-50 text-red-800 text-xs flex items-center gap-2.5';
                    banner.innerHTML = `<i class="fas fa-exclamation-triangle text-red-600 text-base"></i><div><strong class="block">Quota Limit Exceeded!</strong><span class="text-[11px]">Requested: ${app.total_days} day(s) exceeds Available Remaining Quota: <strong>${app.remaining_quota} day(s)</strong></span></div>`;
                }
            }

            const attWrapper = document.getElementById('reviewAttachmentWrapper');
            if (app.attachment_path) {
                document.getElementById('reviewAttachmentLink').href = `/storage/${app.attachment_path}`;
                attWrapper.classList.remove('hidden');
            } else {
                attWrapper.classList.add('hidden');
            }

            let logHtml = '';
            if (app.logs && app.logs.length > 0) {
                app.logs.forEach(l => {
                    const perfName = l.performer && l.performer.basic_data ? l.performer.basic_data.full_name : (l.performer ? l.performer.eci : 'System');
                    logHtml += `
                        <div class="border-l-2 border-red-500 pl-2 py-0.5">
                            <span class="font-bold text-gray-800 uppercase">${l.action}</span> 
                            <span class="text-gray-400 text-[10px]">by ${perfName} at ${new Date(l.created_at).toLocaleString()}</span>
                            ${l.notes ? `<p class="text-gray-600 mt-0.5">${l.notes}</p>` : ''}
                        </div>
                    `;
                });
            } else {
                logHtml = `<p class="text-gray-400">No activity history recorded.</p>`;
            }
            document.getElementById('reviewLogTimeline').innerHTML = logHtml;

            document.getElementById('modalReviewLeavePermit').classList.remove('hidden');
        }

        function closeReviewModal() {
            document.getElementById('modalReviewLeavePermit').classList.add('hidden');
            currentReviewApp = null;
        }

        function confirmReviewAction(action) {
            const notes = document.getElementById('reviewNoteText').value.trim();
            if (action === 'reject' && !notes) {
                if (typeof showToast === 'function') {
                    showToast('Rejection reason is required. Please enter a reason in HR Notes field.', 'warning');
                }
                return;
            }

            pendingReviewAction = action;
            const modal = document.getElementById('modalConfirmReviewAction');
            const title = document.getElementById('confirmReviewModalTitle');
            const msg = document.getElementById('confirmReviewModalMessage');

            if (action === 'approve') {
                title.innerText = 'Confirm Approval';
                msg.innerText = 'Are you sure you want to APPROVE this leave/permit application?';
            } else if (action === 'reject') {
                title.innerText = 'Confirm Rejection';
                msg.innerText = 'Are you sure you want to REJECT this leave/permit application? Rejection reason will be saved.';
            } else {
                title.innerText = 'Request Revision';
                msg.innerText = 'Are you sure you want to send this application back to the employee for edit/revision?';
            }

            modal.classList.remove('hidden');
        }

        function closeConfirmReviewModal() {
            document.getElementById('modalConfirmReviewAction').classList.add('hidden');
            pendingReviewAction = null;
        }

        async function executePendingReviewAction() {
            if (!pendingReviewAction) return;
            const action = pendingReviewAction;
            closeConfirmReviewModal();
            await executeReviewAction(action);
        }

        async function executeReviewAction(action) {
            const appId = document.getElementById('reviewAppId').value;
            const notes = document.getElementById('reviewNoteText').value.trim();
            const csrf = document.querySelector('meta[name="csrf-token"]').content;

            let url = `/api/hr-general/leave-permit/applications/${appId}/${action}`;
            let body = {};
            if (action === 'reject') body.rejection_reason = notes;
            if (action === 'revision') body.revision_notes = notes;
            if (action === 'approve') body.notes = notes;

            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify(body),
                    credentials: 'same-origin'
                });

                const json = await res.json();
                if (json.success) {
                    closeReviewModal();
                    if (typeof showToast === 'function') {
                        showToast(json.message || `Application ${action}d successfully.`, 'success');
                    }
                    loadInboxApplications();
                    loadAllEmployeesQuotas();
                } else {
                    if (typeof showToast === 'function') {
                        showToast(json.message || 'Action failed.', 'error');
                    }
                }
            } catch (err) {
                if (typeof showToast === 'function') {
                    showToast('An unexpected error occurred during action execution.', 'error');
                }
            }
        }
    </script>
@endsection
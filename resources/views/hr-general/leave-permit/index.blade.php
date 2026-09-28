@extends('dashboard')
@section('title', 'Leave & Permit Attendance')
@section('page-title', 'Leave & Permit Attendance Management')
@section('page-subtitle', 'Manage master leave types, employee attendance quotas, review applications, and view analytics reports')

@php
    $pendingReqCount = $pendingCount ?? 0;
@endphp

@section('page-actions')
<div class="flex items-center gap-1.5 sm:gap-2 shrink-0">
    @if($pendingReqCount > 0)
    <button type="button" onclick="switchTab('inbox')"
        title="{{ $pendingReqCount }} pending validation"
        class="inline-flex items-center gap-1.5 border text-xs font-semibold px-2 py-1 sm:px-3 sm:py-1.5 rounded-lg hover:opacity-80 transition shadow-sm whitespace-nowrap active:scale-95"
        style="background: rgba(var(--primary-rgb), 0.08); border-color: rgba(var(--primary-rgb), 0.35); color: var(--primary-color);">
        <span class="w-2 h-2 rounded-full animate-pulse shrink-0" style="background: var(--primary-color);"></span>
        <span>{{ $pendingReqCount }} request</span>
    </button>
    @endif
</div>
@endsection

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

        <!-- Navigation Tabs (styled like the Attendance hub tab bar) -->
        <div class="mb-2 bg-white rounded-lg shadow-sm border border-gray-200">
            <nav class="flex flex-wrap gap-1 p-1" aria-label="Tabs">
                <button id="tabBtnInbox" onclick="switchTab('inbox')"
                    class="hub-tab-btn primary-gradient text-white shadow-sm flex-1 sm:flex-none px-4 py-2.5 text-sm font-medium text-center rounded-lg transition-all flex items-center justify-center gap-2">
                    <i class="fas fa-inbox"></i> Approval Inbox
                    <span id="badgePendingCount"
                        class="bg-yellow-100 text-yellow-800 text-[10px] font-bold px-2 py-0.5 rounded-full hidden">0</span>
                </button>

                <button id="tabBtnTypes" onclick="switchTab('types')"
                    class="hub-tab-btn flex-1 sm:flex-none px-4 py-2.5 text-sm font-medium text-center rounded-lg transition-all text-gray-600 hover:text-gray-900 hover:bg-gray-100 flex items-center justify-center gap-2">
                    <i class="fas fa-layer-group"></i> Master Data Leave Type
                </button>

                <button id="tabBtnAllQuotas" onclick="switchTab('all_quotas')"
                    class="hub-tab-btn flex-1 sm:flex-none px-4 py-2.5 text-sm font-medium text-center rounded-lg transition-all text-gray-600 hover:text-gray-900 hover:bg-gray-100 flex items-center justify-center gap-2">
                    <i class="fas fa-users"></i> All Employee Quotas
                </button>

                <button id="tabBtnReport" onclick="switchTab('report')"
                    class="hub-tab-btn flex-1 sm:flex-none px-4 py-2.5 text-sm font-medium text-center rounded-lg transition-all text-gray-600 hover:text-gray-900 hover:bg-gray-100 flex items-center justify-center gap-2">
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
                    <p class="text-[10px] text-gray-400">Use the <i class="fas fa-filter text-[9px]"></i> icons in the table header to search &amp; filter.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead
                            class="bg-gray-50 text-gray-500 uppercase tracking-wider text-[10px] font-bold border-b border-gray-200 select-none">
                            <tr>
                                <th class="px-5 py-3">App No & Date</th>

                                {{-- Employee Name filter --}}
                                <th class="px-4 py-3 min-w-40">
                                    <div class="flex items-center justify-between gap-1.5">
                                        <span>Employee Name</span>
                                        <button type="button" data-hf-btn onclick="toggleHF(event, 'inboxEmployeeFilterBox')"
                                            class="relative p-1 rounded-md hover:bg-gray-200/70 transition-all text-gray-400 hover:text-gray-600" title="Filter Employee">
                                            <i class="fas fa-filter text-[10px]"></i>
                                            <span id="inboxEmployeeFilterDot" class="hidden absolute -top-0.5 -right-0.5 w-1.5 h-1.5 rounded-full bg-(--primary-color) ring-2 ring-white"></span>
                                        </button>
                                    </div>
                                    <div id="inboxEmployeeFilterBox" class="header-filter-popover hidden w-60 bg-white rounded-xl shadow-xl ring-1 ring-black/5 z-50 overflow-hidden normal-case font-normal" onclick="event.stopPropagation()">
                                        <div class="px-3 py-2 border-b border-gray-100 flex items-center justify-between">
                                            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Filter · Employee</span>
                                            <button type="button" onclick="document.getElementById('inboxEmployeeSearch').value='';setInboxEmployeeFilter('');" class="text-[10px] font-semibold text-red-500 hover:text-red-600">Clear</button>
                                        </div>
                                        <div class="p-2.5">
                                            <div class="relative">
                                                <input type="text" id="inboxEmployeeSearch" placeholder="Type a name…" autocomplete="off"
                                                    oninput="setInboxEmployeeFilter(this.value)"
                                                    class="w-full bg-gray-50 border border-gray-200 text-gray-800 text-xs rounded-lg pl-7 pr-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-(--primary-color)/25 focus:border-(--primary-color) transition-all font-normal">
                                                <i class="fas fa-search text-[10px] absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400"></i>
                                            </div>
                                        </div>
                                    </div>
                                </th>

                                {{-- Type filter --}}
                                <th class="px-4 py-3 min-w-36">
                                    <div class="flex items-center justify-between gap-1.5">
                                        <span>Type</span>
                                        <button type="button" data-hf-btn onclick="toggleHF(event, 'inboxTypeFilterBox')"
                                            class="relative p-1 rounded-md hover:bg-gray-200/70 transition-all text-gray-400 hover:text-gray-600" title="Filter Type">
                                            <i class="fas fa-filter text-[10px]"></i>
                                            <span id="inboxTypeFilterDot" class="hidden absolute -top-0.5 -right-0.5 w-1.5 h-1.5 rounded-full bg-(--primary-color) ring-2 ring-white"></span>
                                        </button>
                                    </div>
                                    <div id="inboxTypeFilterBox" class="header-filter-popover hidden w-56 bg-white rounded-xl shadow-xl ring-1 ring-black/5 z-50 overflow-hidden normal-case font-normal" onclick="event.stopPropagation()">
                                        <div class="px-3 py-2 border-b border-gray-100 flex items-center justify-between">
                                            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Filter · Type</span>
                                        </div>
                                        <div class="py-1 max-h-64 overflow-y-auto" id="inboxTypeFilterOptions"></div>
                                    </div>
                                </th>

                                <th class="px-4 py-3">Period</th>
                                <th class="px-4 py-3 text-center">Total Days</th>
                                <th class="px-4 py-3 text-center">Quota Limit Check</th>

                                {{-- Status filter --}}
                                <th class="px-4 py-3 text-center min-w-32">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <span>Status</span>
                                        <button type="button" data-hf-btn onclick="toggleHF(event, 'inboxStatusFilterBox')"
                                            class="relative p-1 rounded-md hover:bg-gray-200/70 transition-all text-gray-400 hover:text-gray-600" title="Filter Status">
                                            <i class="fas fa-filter text-[10px]"></i>
                                            <span id="inboxStatusFilterDot" class="absolute -top-0.5 -right-0.5 w-1.5 h-1.5 rounded-full bg-(--primary-color) ring-2 ring-white"></span>
                                        </button>
                                    </div>
                                    <div id="inboxStatusFilterBox" class="header-filter-popover hidden w-44 bg-white rounded-xl shadow-xl ring-1 ring-black/5 z-50 overflow-hidden text-left normal-case font-normal" onclick="event.stopPropagation()">
                                        <div class="px-3 py-2 border-b border-gray-100">
                                            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Filter · Status</span>
                                        </div>
                                        <div class="py-1">
                                            <button type="button" onclick="setInboxStatusFilter('')" class="w-full px-3 py-1.5 text-xs text-left hover:bg-gray-50 transition-colors text-gray-700">All Statuses</button>
                                            <button type="button" onclick="setInboxStatusFilter('pending')" class="w-full px-3 py-1.5 text-xs text-left hover:bg-gray-50 transition-colors text-gray-700">Pending Only</button>
                                            <button type="button" onclick="setInboxStatusFilter('approved')" class="w-full px-3 py-1.5 text-xs text-left hover:bg-gray-50 transition-colors text-gray-700">Approved</button>
                                            <button type="button" onclick="setInboxStatusFilter('revision')" class="w-full px-3 py-1.5 text-xs text-left hover:bg-gray-50 transition-colors text-gray-700">Revision Requested</button>
                                            <button type="button" onclick="setInboxStatusFilter('rejected')" class="w-full px-3 py-1.5 text-xs text-left hover:bg-gray-50 transition-colors text-gray-700">Rejected</button>
                                        </div>
                                    </div>
                                </th>

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
                <div id="paginationInbox"></div>
            </div>
        </div>

        <!-- ==================== TAB 2: JENIS CUTI MASTER (Add & Edit Only - Requirement 1) ==================== -->
        <div id="tabContentTypes" class="space-y-6 hidden">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
                    <div>
                        <h3 class="font-bold text-sm text-gray-800 flex items-center gap-2">
                            <i class="fas fa-layer-group primary-text"></i> Master Data Leave & Permit Type
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
                            class="bg-gray-100 text-gray-700 uppercase tracking-wider text-[10px] font-bold border-b border-gray-200 select-none">
                            <tr>
                                <th class="px-3 py-3 text-center">No</th>
                                <th class="px-3 py-3">Code</th>

                                {{-- Name/Code search filter --}}
                                <th class="px-4 py-3 min-w-48">
                                    <div class="flex items-center justify-between gap-1.5">
                                        <span>Leave & Permit Type</span>
                                        <button type="button" data-hf-btn onclick="toggleHF(event, 'masterTypesSearchFilterBox')"
                                            class="relative p-1 rounded-md hover:bg-gray-200/70 transition-all text-gray-400 hover:text-gray-600" title="Search Type">
                                            <i class="fas fa-filter text-[10px]"></i>
                                            <span id="masterTypesSearchFilterDot" class="hidden absolute -top-0.5 -right-0.5 w-1.5 h-1.5 rounded-full bg-(--primary-color) ring-2 ring-white"></span>
                                        </button>
                                    </div>
                                    <div id="masterTypesSearchFilterBox" class="header-filter-popover hidden w-60 bg-white rounded-xl shadow-xl ring-1 ring-black/5 z-50 overflow-hidden normal-case font-normal" onclick="event.stopPropagation()">
                                        <div class="px-3 py-2 border-b border-gray-100 flex items-center justify-between">
                                            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Search · Code or Name</span>
                                            <button type="button" onclick="document.getElementById('masterTypesSearch').value='';setMasterTypesSearchFilter('');" class="text-[10px] font-semibold text-red-500 hover:text-red-600">Clear</button>
                                        </div>
                                        <div class="p-2.5">
                                            <div class="relative">
                                                <input type="text" id="masterTypesSearch" placeholder="Type code or name…" autocomplete="off"
                                                    oninput="setMasterTypesSearchFilter(this.value)"
                                                    class="w-full bg-gray-50 border border-gray-200 text-gray-800 text-xs rounded-lg pl-7 pr-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-(--primary-color)/25 focus:border-(--primary-color) transition-all font-normal">
                                                <i class="fas fa-search text-[10px] absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400"></i>
                                            </div>
                                        </div>
                                    </div>
                                </th>

                                <th class="px-3 py-3 text-center">Default Quota</th>
                                <th class="px-3 py-3 text-center">Paid Status</th>
                                <th class="px-3 py-3 text-center">Gender Target</th>

                                {{-- Status filter --}}
                                <th class="px-3 py-3 text-center min-w-28">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <span>Status</span>
                                        <button type="button" data-hf-btn onclick="toggleHF(event, 'masterTypesStatusFilterBox')"
                                            class="relative p-1 rounded-md hover:bg-gray-200/70 transition-all text-gray-400 hover:text-gray-600" title="Filter Status">
                                            <i class="fas fa-filter text-[10px]"></i>
                                            <span id="masterTypesStatusFilterDot" class="hidden absolute -top-0.5 -right-0.5 w-1.5 h-1.5 rounded-full bg-(--primary-color) ring-2 ring-white"></span>
                                        </button>
                                    </div>
                                    <div id="masterTypesStatusFilterBox" class="header-filter-popover hidden w-40 bg-white rounded-xl shadow-xl ring-1 ring-black/5 z-50 overflow-hidden text-left normal-case font-normal" onclick="event.stopPropagation()">
                                        <div class="px-3 py-2 border-b border-gray-100">
                                            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Filter · Status</span>
                                        </div>
                                        <div class="py-1">
                                            <button type="button" onclick="setMasterTypesStatusFilter('')" class="w-full px-3 py-1.5 text-xs text-left hover:bg-gray-50 transition-colors text-gray-700">All</button>
                                            <button type="button" onclick="setMasterTypesStatusFilter('1')" class="w-full px-3 py-1.5 text-xs text-left hover:bg-gray-50 transition-colors text-gray-700">Active</button>
                                            <button type="button" onclick="setMasterTypesStatusFilter('0')" class="w-full px-3 py-1.5 text-xs text-left hover:bg-gray-50 transition-colors text-gray-700">Nonactive</button>
                                        </div>
                                    </div>
                                </th>

                                <th class="px-4 py-3">Description</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="tblMasterTypesBody" class="divide-y divide-gray-100 text-gray-700">
                            @foreach($allTypes as $idx => $t)
                                <tr class="hover:bg-gray-50 transition-colors"
                                    data-search="{{ strtolower($t->code.' '.$t->name) }}"
                                    data-active="{{ $t->is_active ? '1' : '0' }}">
                                    <td class="px-3 py-3 text-center font-bold text-gray-500">{{ $idx + 1 }}</td>
                                    <td class="px-3 py-3 font-bold primary-text">{{ $t->code }}</td>
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
                <div id="paginationMasterTypes"></div>
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
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead
                            class="bg-gray-50 text-gray-500 uppercase tracking-wider text-[10px] font-bold border-b border-gray-200 select-none">
                            <tr>
                                <th class="px-5 py-3 text-center">No</th>

                                {{-- Employee Name / ECI filter --}}
                                <th class="px-5 py-3 min-w-48">
                                    <div class="flex items-center justify-between gap-1.5">
                                        <span>Employee Name</span>
                                        <button type="button" data-hf-btn onclick="toggleHF(event, 'allQuotasFilterBox')"
                                            class="relative p-1 rounded-md hover:bg-gray-200/70 transition-all text-gray-400 hover:text-gray-600" title="Filter Employee">
                                            <i class="fas fa-filter text-[10px]"></i>
                                            <span id="allQuotasFilterDot" class="hidden absolute -top-0.5 -right-0.5 w-1.5 h-1.5 rounded-full bg-(--primary-color) ring-2 ring-white"></span>
                                        </button>
                                    </div>
                                    <div id="allQuotasFilterBox" class="header-filter-popover hidden w-60 bg-white rounded-xl shadow-xl ring-1 ring-black/5 z-50 overflow-hidden normal-case font-normal" onclick="event.stopPropagation()">
                                        <div class="px-3 py-2 border-b border-gray-100 flex items-center justify-between">
                                            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Filter · Employee</span>
                                            <button type="button" onclick="document.getElementById('allQuotasSearch').value='';setAllQuotasEmployeeFilter('');" class="text-[10px] font-semibold text-red-500 hover:text-red-600">Clear</button>
                                        </div>
                                        <div class="p-2.5">
                                            <div class="relative">
                                                <input type="text" id="allQuotasSearch" placeholder="Name or ECI…" autocomplete="off"
                                                    oninput="setAllQuotasEmployeeFilter(this.value)"
                                                    class="w-full bg-gray-50 border border-gray-200 text-gray-800 text-xs rounded-lg pl-7 pr-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-(--primary-color)/25 focus:border-(--primary-color) transition-all font-normal">
                                                <i class="fas fa-search text-[10px] absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400"></i>
                                            </div>
                                        </div>
                                    </div>
                                </th>

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
                <div id="paginationAllQuotas"></div>
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
                        <i class="fas fa-users primary-text mr-1.5"></i> Employee Attendance & Leave Recap Summary
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider text-[10px] font-bold border-b border-gray-200 select-none">
                            <tr>
                                {{-- Employee Name filter --}}
                                <th class="px-5 py-3 min-w-48">
                                    <div class="flex items-center justify-between gap-1.5">
                                        <span>Employee Name</span>
                                        <button type="button" data-hf-btn onclick="toggleHF(event, 'rptEmployeeFilterBox')"
                                            class="relative p-1 rounded-md hover:bg-gray-200/70 transition-all text-gray-400 hover:text-gray-600" title="Filter Employee">
                                            <i class="fas fa-filter text-[10px]"></i>
                                            <span id="rptEmployeeFilterDot" class="hidden absolute -top-0.5 -right-0.5 w-1.5 h-1.5 rounded-full bg-(--primary-color) ring-2 ring-white"></span>
                                        </button>
                                    </div>
                                    <div id="rptEmployeeFilterBox" class="header-filter-popover hidden w-60 bg-white rounded-xl shadow-xl ring-1 ring-black/5 z-50 overflow-hidden normal-case font-normal" onclick="event.stopPropagation()">
                                        <div class="px-3 py-2 border-b border-gray-100 flex items-center justify-between">
                                            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Filter · Employee</span>
                                            <button type="button" onclick="document.getElementById('rptEmployeeSearch').value='';setRptEmployeeFilter('');" class="text-[10px] font-semibold text-red-500 hover:text-red-600">Clear</button>
                                        </div>
                                        <div class="p-2.5">
                                            <div class="relative">
                                                <input type="text" id="rptEmployeeSearch" placeholder="Name or ECI…" autocomplete="off"
                                                    oninput="setRptEmployeeFilter(this.value)"
                                                    class="w-full bg-gray-50 border border-gray-200 text-gray-800 text-xs rounded-lg pl-7 pr-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-(--primary-color)/25 focus:border-(--primary-color) transition-all font-normal">
                                                <i class="fas fa-search text-[10px] absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400"></i>
                                            </div>
                                        </div>
                                    </div>
                                </th>
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
                <div id="paginationRptEmployee"></div>
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

        // ── Shared client-side pagination + header-filter helper (Inbox, All Quotas, Master Types, Employee Report) ──
        const PAGE_SIZE = 10;
        const paginationState = {
            inbox:       { page: 1, perPage: PAGE_SIZE, data: [], filtered: [], filters: { employee: '', type: '', status: 'pending' } },
            allQuotas:   { page: 1, perPage: PAGE_SIZE, data: [], filters: { employee: '' } },
            rptEmployee: { page: 1, perPage: PAGE_SIZE, data: [], filtered: [], filters: { employee: '' } },
            masterTypes: { page: 1, perPage: PAGE_SIZE, rows: [], filters: { search: '', status: '' } },
        };

        function goToPage(key, page) {
            paginationState[key].page = page;
            if (key === 'inbox') renderInboxPage();
            else if (key === 'allQuotas') renderAllQuotasPage();
            else if (key === 'rptEmployee') renderRptEmployeePage();
            else if (key === 'masterTypes') renderMasterTypesPage();
        }

        function changeRowsPerPage(key, val) {
            paginationState[key].perPage = parseInt(val, 10);
            goToPage(key, 1);
        }

        function pageBtn(key, p, current) {
            if (p === current) {
                return `<span class="w-8 h-8 rounded-lg text-white font-bold flex items-center justify-center text-xs shadow-sm" style="background: var(--primary-color) !important;">${p}</span>`;
            }
            return `<button onclick="goToPage('${key}', ${p})" class="w-8 h-8 rounded-lg border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 font-semibold flex items-center justify-center text-xs shadow-sm transition-all">${p}</button>`;
        }

        // Numbered pagination footer (matches the KPI Evaluation page's pagination style)
        function renderPaginationControls(containerId, key, totalItems) {
            const container = document.getElementById(containerId);
            if (!container) return;

            if (totalItems === 0) {
                container.innerHTML = '';
                return;
            }

            const state = paginationState[key];
            const perPage = state.perPage || PAGE_SIZE;
            const totalPages = Math.max(1, Math.ceil(totalItems / perPage));
            if (state.page > totalPages) state.page = totalPages;
            const current = state.page;
            const firstItem = (current - 1) * perPage + 1;
            const lastItem = Math.min(current * perPage, totalItems);

            let start = Math.max(1, current - 2);
            let end = Math.min(totalPages, current + 2);
            if (end - start < 4) {
                if (start === 1) end = Math.min(totalPages, start + 4);
                else if (end === totalPages) start = Math.max(1, end - 4);
            }

            let pagesHtml = '';
            if (start > 1) {
                pagesHtml += pageBtn(key, 1, current);
                if (start > 2) pagesHtml += `<span class="w-5 text-center text-gray-400 text-xs">...</span>`;
            }
            for (let p = start; p <= end; p++) pagesHtml += pageBtn(key, p, current);
            if (end < totalPages) {
                if (end < totalPages - 1) pagesHtml += `<span class="w-5 text-center text-gray-400 text-xs">...</span>`;
                pagesHtml += pageBtn(key, totalPages, current);
            }

            container.innerHTML = `
                <div class="px-5 py-4 border-t border-gray-100 bg-white flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <button onclick="goToPage('${key}', ${current - 1})" ${current <= 1 ? 'disabled' : ''}
                            class="w-8 h-8 rounded-lg border ${current <= 1 ? 'border-gray-100 bg-gray-50 text-gray-300 cursor-not-allowed shadow-none' : 'border-gray-200 bg-white text-gray-500 hover:bg-gray-50 hover:text-gray-700 shadow-sm'} flex items-center justify-center text-xs transition-all">
                            <i class="fas fa-chevron-left text-[10px]"></i>
                        </button>
                        ${pagesHtml}
                        <button onclick="goToPage('${key}', ${current + 1})" ${current >= totalPages ? 'disabled' : ''}
                            class="w-8 h-8 rounded-lg border ${current >= totalPages ? 'border-gray-100 bg-gray-50 text-gray-300 cursor-not-allowed shadow-none' : 'border-gray-200 bg-white text-gray-500 hover:bg-gray-50 hover:text-gray-700 shadow-sm'} flex items-center justify-center text-xs transition-all">
                            <i class="fas fa-chevron-right text-[10px]"></i>
                        </button>
                        <span class="text-xs text-gray-500 ml-3 font-normal whitespace-nowrap">Showing ${firstItem} to ${lastItem} of ${totalItems} results</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-gray-500 font-normal">Rows per page:</span>
                        <select onchange="changeRowsPerPage('${key}', this.value)"
                            class="appearance-none bg-white border border-gray-200 rounded-lg pl-3 pr-7 py-1.5 text-xs font-medium text-gray-700 hover:border-gray-300 focus:outline-none focus:ring-1 focus:ring-(--primary-color) cursor-pointer shadow-sm transition-all">
                            ${[10, 15, 25, 50].map(n => `<option value="${n}" ${perPage == n ? 'selected' : ''}>${n}</option>`).join('')}
                        </select>
                    </div>
                </div>
            `;
        }

        // ── Floating per-column header filter popovers (same pattern as the KPI Evaluation page) ──
        let _hfOpen = null;
        function toggleHF(e, popoverId) {
            e.stopPropagation();
            const btn = e.currentTarget;
            const pop = document.getElementById(popoverId);
            if (!pop) return;
            const wasHidden = pop.classList.contains('hidden');
            closeAllHF();
            if (wasHidden) {
                pop.classList.remove('hidden');
                floatHF(btn, pop);
                _hfOpen = { btn, pop };
                const input = pop.querySelector('input');
                if (input) setTimeout(() => input.focus(), 50);
            }
        }
        function floatHF(btn, pop) {
            pop.style.position = 'fixed';
            pop.style.margin   = '0';
            pop.style.zIndex   = '9999';
            pop.style.top = '-9999px'; pop.style.left = '-9999px';
            const pw = pop.offsetWidth || 220, ph = pop.offsetHeight || 200;
            const r  = btn.getBoundingClientRect();
            const vw = document.documentElement.clientWidth, vh = window.innerHeight;
            let left = Math.min(Math.max(8, r.right - pw), vw - pw - 8);
            let top  = r.bottom + 4;
            if (top + ph > vh - 8 && r.top - ph - 4 > 8) top = r.top - ph - 4;
            top = Math.max(8, Math.min(top, vh - ph - 8));
            pop.style.left = left + 'px';
            pop.style.top  = top + 'px';
        }
        function closeAllHF() {
            document.querySelectorAll('.header-filter-popover').forEach(p => {
                p.classList.add('hidden');
                p.style.position = p.style.top = p.style.left = p.style.zIndex = p.style.margin = '';
            });
            _hfOpen = null;
        }
        document.addEventListener('click', function (e) {
            if (!e.target.closest('.header-filter-popover') && !e.target.closest('[data-hf-btn]')) closeAllHF();
        });
        window.addEventListener('scroll', e => {
            if (_hfOpen && !(e.target.closest && e.target.closest('.header-filter-popover'))) closeAllHF();
        }, true);
        window.addEventListener('resize', () => { if (_hfOpen) floatHF(_hfOpen.btn, _hfOpen.pop); });

        function toggleFilterDot(dotId, active) {
            const dot = document.getElementById(dotId);
            if (dot) dot.classList.toggle('hidden', !active);
        }

        // ── Approval Inbox — header filters (Employee / Type / Status) ─────────────
        let _inboxEmployeeDebounce = null;
        function setInboxEmployeeFilter(val) {
            clearTimeout(_inboxEmployeeDebounce);
            _inboxEmployeeDebounce = setTimeout(() => {
                paginationState.inbox.filters.employee = val;
                toggleFilterDot('inboxEmployeeFilterDot', !!val);
                applyInboxFilters();
            }, 250);
        }
        function setInboxTypeFilter(val) {
            paginationState.inbox.filters.type = val;
            toggleFilterDot('inboxTypeFilterDot', !!val);
            applyInboxFilters();
            closeAllHF();
        }
        function setInboxStatusFilter(val) {
            paginationState.inbox.filters.status = val;
            toggleFilterDot('inboxStatusFilterDot', !!val);
            applyInboxFilters();
            closeAllHF();
        }
        function applyInboxFilters() {
            const st = paginationState.inbox;
            const f = st.filters;
            st.filtered = st.data.filter(app => {
                if (f.employee && !(app.employee_display_name || '').toLowerCase().includes(f.employee.toLowerCase())) return false;
                if (f.type && String(app.leave_permit_type_id) !== String(f.type)) return false;
                if (f.status && app.status !== f.status) return false;
                return true;
            });
            st.page = 1;
            renderInboxPage();
        }

        // ── All Employee Quotas — header filter (Employee / ECI) ────────────────────
        let _allQuotasDebounce = null;
        function setAllQuotasEmployeeFilter(val) {
            paginationState.allQuotas.filters.employee = val;
            toggleFilterDot('allQuotasFilterDot', !!val);
            clearTimeout(_allQuotasDebounce);
            _allQuotasDebounce = setTimeout(() => loadAllEmployeesQuotas(), 350);
        }

        // ── Employee Recap Report — header filter (Employee) ────────────────────────
        let _rptEmployeeDebounce = null;
        function setRptEmployeeFilter(val) {
            clearTimeout(_rptEmployeeDebounce);
            _rptEmployeeDebounce = setTimeout(() => {
                paginationState.rptEmployee.filters.employee = val;
                toggleFilterDot('rptEmployeeFilterDot', !!val);
                applyRptEmployeeFilters();
            }, 250);
        }
        function applyRptEmployeeFilters() {
            const st = paginationState.rptEmployee;
            const f = st.filters;
            st.filtered = st.data.filter(emp => {
                if (f.employee) {
                    const needle = f.employee.toLowerCase();
                    if (!(emp.employee_name || '').toLowerCase().includes(needle) && !(emp.eci || '').toLowerCase().includes(needle)) return false;
                }
                return true;
            });
            st.page = 1;
            renderRptEmployeePage();
        }

        // ── Master Data Leave Type — header filters (Name/Code search, Status) ──────
        let _masterTypesDebounce = null;
        function setMasterTypesSearchFilter(val) {
            clearTimeout(_masterTypesDebounce);
            _masterTypesDebounce = setTimeout(() => {
                paginationState.masterTypes.filters.search = val;
                toggleFilterDot('masterTypesSearchFilterDot', !!val);
                paginationState.masterTypes.page = 1;
                renderMasterTypesPage();
            }, 250);
        }
        function setMasterTypesStatusFilter(val) {
            paginationState.masterTypes.filters.status = val;
            toggleFilterDot('masterTypesStatusFilterDot', val !== '');
            paginationState.masterTypes.page = 1;
            renderMasterTypesPage();
            closeAllHF();
        }

        document.addEventListener('DOMContentLoaded', () => {
            loadInboxApplications();
            loadAllEmployeesQuotas();
            initMasterTypesPagination();
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
                        btn.classList.remove('text-gray-600', 'hover:text-gray-900', 'hover:bg-gray-100');
                        btn.classList.add('primary-gradient', 'text-white', 'shadow-sm');
                        content.classList.remove('hidden');
                    } else {
                        btn.classList.remove('primary-gradient', 'text-white', 'shadow-sm');
                        btn.classList.add('text-gray-600', 'hover:text-gray-900', 'hover:bg-gray-100');
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
        // Fetches every application for the selected year once; status/employee/type
        // filtering happens client-side via the per-column header filters below.
        async function loadInboxApplications() {
            try {
                const res = await fetch(`/api/hr-general/leave-permit/applications?year=${globalYear}`, { credentials: 'same-origin' });
                const json = await res.json();

                if (json.success) {
                    const badge = document.getElementById('badgePendingCount');
                    if (badge) {
                        const pendingCount = json.data.filter(a => a.status === 'pending').length;
                        badge.innerText = pendingCount;
                        badge.classList.toggle('hidden', pendingCount === 0);
                    }

                    paginationState.inbox.data = json.data;
                    renderInboxTypeFilterOptions();
                    applyInboxFilters();
                }
            } catch (err) {
                console.error(err);
            }
        }

        function renderInboxTypeFilterOptions() {
            const container = document.getElementById('inboxTypeFilterOptions');
            if (!container) return;
            const seen = new Map();
            paginationState.inbox.data.forEach(a => {
                if (a.leave_permit_type_id != null && !seen.has(a.leave_permit_type_id)) {
                    seen.set(a.leave_permit_type_id, a.type_name);
                }
            });
            let html = `<button type="button" onclick="setInboxTypeFilter('')" class="w-full flex items-center gap-2 px-3 py-1.5 text-xs text-left hover:bg-gray-50 transition-colors text-gray-700">All Types</button>`;
            seen.forEach((name, id) => {
                html += `<button type="button" onclick="setInboxTypeFilter('${id}')" class="w-full flex items-center gap-2 px-3 py-1.5 text-xs text-left hover:bg-gray-50 transition-colors text-gray-700"><span class="truncate">${name}</span></button>`;
            });
            container.innerHTML = html;
        }

        function buildInboxRow(app) {
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

            return `
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-5 py-3">
                        <span class="font-bold text-gray-900 block">${app.application_no}</span>
                        <span class="text-[10px] text-gray-400">${new Date(app.created_at).toLocaleDateString()}</span>
                    </td>
                    <td class="px-4 py-3 font-semibold text-gray-800">${app.employee_display_name}</td>
                    <td class="px-4 py-3">${app.type_name}</td>
                    <td class="px-4 py-3 text-gray-600">${app.start_date} ~ ${app.end_date}</td>
                    <td class="px-4 py-3 text-center font-bold primary-text">${app.total_days}</td>
                    <td class="px-4 py-3 text-center">${quotaBadge}</td>
                    <td class="px-4 py-3 text-center">${renderStatusBadge(app.status)}</td>
                    <td class="px-5 py-3 text-right">
                        <button onclick='openReviewModal(${JSON.stringify(app)})' class="px-3 py-1 primary-gradient text-white text-[11px] font-semibold rounded hover:opacity-90">
                            Review / Edit
                        </button>
                    </td>
                </tr>
            `;
        }

        function renderInboxPage() {
            const state = paginationState.inbox;
            const totalItems = state.filtered.length;
            const perPage = state.perPage || PAGE_SIZE;
            const start = (state.page - 1) * perPage;
            const pageItems = state.filtered.slice(start, start + perPage);

            document.getElementById('tblInboxBody').innerHTML = pageItems.length
                ? pageItems.map(buildInboxRow).join('')
                : `<tr><td colspan="8" class="px-5 py-6 text-center text-gray-400">${state.data.length ? 'No applications match the current filters.' : 'Inbox is empty.'}</td></tr>`;

            renderPaginationControls('paginationInbox', 'inbox', totalItems);
        }

        // ── All Employees Quotas Table (Requirement 8) ───────────────────────────
        // Employee/ECI search lives in the "Employee Name" column header filter and
        // is sent to the backend (already supports ?search=), same as before.
        async function loadAllEmployeesQuotas() {
            const search = paginationState.allQuotas.filters.employee || '';
            try {
                const res = await fetch(`/api/hr-general/leave-permit/all-employees-quotas?year=${globalYear}&search=${encodeURIComponent(search)}`, { credentials: 'same-origin' });
                const json = await res.json();

                if (json.success) {
                    paginationState.allQuotas.data = json.data;
                    paginationState.allQuotas.page = 1;
                    renderAllQuotasPage();
                }
            } catch (err) {
                console.error(err);
            }
        }

        function buildAllQuotasRow(emp, rowNo) {
            return `
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-5 py-3 text-center font-bold text-gray-500">${rowNo}</td>
                    <td class="px-5 py-3 font-bold text-gray-900">${emp.display_name}</td>
                    <td class="px-4 py-3 text-gray-600">${emp.eci}</td>
                    <td class="px-4 py-3 text-right font-semibold text-gray-700">${emp.total_allocated} days</td>
                    <td class="px-4 py-3 text-right font-semibold text-green-600">${emp.total_used} days</td>
                    <td class="px-4 py-3 text-right font-semibold text-yellow-600">${emp.total_pending} days</td>
                    <td class="px-5 py-3 text-right font-bold ${emp.total_remaining > 0 ? 'primary-text' : 'text-gray-400'}">${emp.total_remaining} days</td>
                    <td class="px-5 py-3 text-center">
                        <button onclick="openEmployeeQuotaDetailModal(${emp.employee_id}, '${emp.display_name}')" class="px-2.5 py-1 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 text-xs font-semibold rounded transition-colors flex items-center justify-center gap-1 mx-auto">
                            <i class="fas fa-list text-[10px]"></i> View Details
                        </button>
                    </td>
                </tr>
            `;
        }

        function renderAllQuotasPage() {
            const state = paginationState.allQuotas;
            const tbody = document.getElementById('tblAllQuotasSummaryBody');
            const totalItems = state.data.length;

            if (totalItems === 0) {
                tbody.innerHTML = `<tr><td colspan="8" class="px-5 py-6 text-center text-gray-400">No employee quota records found.</td></tr>`;
                renderPaginationControls('paginationAllQuotas', 'allQuotas', 0);
                return;
            }

            const perPage = state.perPage || PAGE_SIZE;
            const start = (state.page - 1) * perPage;
            const pageItems = state.data.slice(start, start + perPage);
            tbody.innerHTML = pageItems.map((emp, i) => buildAllQuotasRow(emp, start + i + 1)).join('');

            renderPaginationControls('paginationAllQuotas', 'allQuotas', totalItems);
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
                                <td class="px-4 py-2.5 text-right font-bold ${item.remaining_quota > 0 || item.is_event_based ? 'primary-text' : 'text-gray-400'}">${remText}</td>
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

        // ── Master Data Leave Type table pagination (server-rendered rows, paginated client-side) ──
        function initMasterTypesPagination() {
            const tbody = document.getElementById('tblMasterTypesBody');
            if (!tbody) return;
            paginationState.masterTypes.rows = Array.from(tbody.querySelectorAll('tr'));
            renderMasterTypesPage();
        }

        function renderMasterTypesPage() {
            const state = paginationState.masterTypes;
            const f = state.filters;

            const visibleRows = state.rows.filter(row => {
                if (f.search && !row.dataset.search.includes(f.search.toLowerCase())) return false;
                if (f.status !== '' && row.dataset.active !== f.status) return false;
                return true;
            });

            // Hide everything first, then reveal only the current page's matches.
            state.rows.forEach(row => { row.style.display = 'none'; });

            const totalItems = visibleRows.length;
            if (totalItems === 0) {
                renderPaginationControls('paginationMasterTypes', 'masterTypes', 0);
                return;
            }

            const perPage = state.perPage || PAGE_SIZE;
            const totalPages = Math.max(1, Math.ceil(totalItems / perPage));
            if (state.page > totalPages) state.page = totalPages;
            const start = (state.page - 1) * perPage;
            const pageRows = visibleRows.slice(start, start + perPage);
            pageRows.forEach(row => { row.style.display = ''; });

            renderPaginationControls('paginationMasterTypes', 'masterTypes', totalItems);
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

                    // 1. Employee Recap Table (paginated — this list grows with headcount and can get long)
                    paginationState.rptEmployee.data = json.by_employee || [];
                    applyRptEmployeeFilters();

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

        function buildRptEmployeeRow(emp) {
            return `
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-5 py-3 font-semibold text-gray-900">${emp.employee_name}</td>
                    <td class="px-4 py-3 text-gray-500 font-medium">${emp.eci}</td>
                    <td class="px-4 py-3 text-center font-bold">${emp.total_apps}</td>
                    <td class="px-4 py-3 text-right font-bold text-green-600">${emp.approved_days} days</td>
                    <td class="px-4 py-3 text-right font-bold text-yellow-600">${emp.pending_days} days</td>
                    <td class="px-4 py-3 text-right text-red-600 font-semibold">${emp.rejected_apps}</td>
                </tr>
            `;
        }

        function renderRptEmployeePage() {
            const state = paginationState.rptEmployee;
            const totalItems = state.filtered.length;

            if (totalItems === 0) {
                document.getElementById('tblRptEmployeeBody').innerHTML = `<tr><td colspan="6" class="px-5 py-6 text-center text-gray-400">${state.data.length ? 'No employees match the current filter.' : 'No attendance records for this period.'}</td></tr>`;
                renderPaginationControls('paginationRptEmployee', 'rptEmployee', 0);
                return;
            }

            const perPage = state.perPage || PAGE_SIZE;
            const start = (state.page - 1) * perPage;
            const pageItems = state.filtered.slice(start, start + perPage);
            document.getElementById('tblRptEmployeeBody').innerHTML = pageItems.map(buildRptEmployeeRow).join('');

            renderPaginationControls('paginationRptEmployee', 'rptEmployee', totalItems);
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
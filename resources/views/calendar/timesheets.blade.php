@extends('dashboard')
@section('title', 'Timesheets')
@section('page-title', 'Timesheets')
@section('page-subtitle', isset($isHead) && $isHead ? 'Review and approve employee timesheets' : 'Track your working hours')

@section('content')
@php
    $isApprovalMode = isset($isHead) && $isHead;
    $isAdminMode    = isset($isAdmin) && $isAdmin;
    // $isHoSMode is true ONLY for Delivery Support Head (role_id=5).
    // Delivery Support Users (role_id=2) also have lockedType='support' but do NOT get isHoSMode,
    // so they see support spreadsheet WITHOUT approve/reject buttons.
    $isHoSMode      = isset($roleId) && $roleId === 5;
@endphp

<div class="space-y-6">
    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">
                @if($isApprovalMode || $isHoSMode)
                    Timesheet Approval
                @else
                    Timesheets
                @endif
            </h2>
            <p class="text-gray-600 mt-1">
                @if($isApprovalMode || $isHoSMode)
                    Review and approve/reject employee timesheet submissions
                @else
                    Log and manage your working hours
                @endif
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <div id="tsPeriodBadge" class="hidden items-center gap-2 px-3 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-xs text-gray-600">
                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <span id="tsBadgeLabel">—</span>
                <span id="tsBadgeStatus" class="font-semibold"></span>
            </div>
            @if(!$isApprovalMode && !$isHoSMode)
            <button onclick="openTimesheetModal()" class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                Create Timesheet
            </button>
            <button onclick="openLateAccessModal()" class="inline-flex items-center gap-1.5 px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-semibold rounded-lg hover:bg-gray-50 transition-all duration-200">
                <i class="fas fa-user-clock text-yellow-500 text-xs"></i>
                Request Late Access
            </button>
            @endif
        </div>
    </div>

    <!-- Stats Cards -->
    @if($isApprovalMode || $isHoSMode)
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-2">
        <div id="cardAll" class="bg-white rounded-lg border-2 border-red-600 p-3 hover:shadow-md transition-all duration-200 cursor-pointer" onclick="filterByStatus('')">
            <p class="text-xs font-medium text-gray-500 mb-1">Total</p>
            <p class="text-2xl font-bold text-gray-900" id="statTotal">0</p>
        </div>
        <div id="cardSubmitted" class="bg-white rounded-lg border border-gray-200 p-3 hover:shadow-md hover:border-red-400 transition-all duration-200 cursor-pointer" onclick="filterByStatus('submitted')">
            <p class="text-xs font-medium text-gray-500 mb-1">Pending Review</p>
            <p class="text-2xl font-bold text-gray-900" id="statSubmittedCount">0</p>
        </div>
        <div id="cardApproved" class="bg-white rounded-lg border border-gray-200 p-3 hover:shadow-md hover:border-red-400 transition-all duration-200 cursor-pointer" onclick="filterByStatus('approved')">
            <p class="text-xs font-medium text-gray-500 mb-1">Approved</p>
            <p class="text-2xl font-bold text-gray-900" id="statApprovedCount">0</p>
        </div>
        <div id="cardRejected" class="bg-white rounded-lg border border-gray-200 p-3 hover:shadow-md hover:border-red-400 transition-all duration-200 cursor-pointer" onclick="filterByStatus('rejected')">
            <p class="text-xs font-medium text-gray-500 mb-1">Rejected</p>
            <p class="text-2xl font-bold text-gray-900" id="statRejectedCount">0</p>
        </div>
    </div>
    @else
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3 mb-2">
        <div id="cardAll" class="bg-white rounded-lg border-2 border-red-600 p-3 hover:shadow-md transition-all duration-200 cursor-pointer" onclick="filterByStatus('')">
            <p class="text-xs font-medium text-gray-500 mb-1">Total</p>
            <p class="text-2xl font-bold text-gray-900" id="statTotal">0</p>
        </div>
        <div id="cardDraft" class="bg-white rounded-lg border border-gray-200 p-3 hover:shadow-md hover:border-red-400 transition-all duration-200 cursor-pointer" onclick="filterByStatus('draft')">
            <p class="text-xs font-medium text-gray-500 mb-1">Draft</p>
            <p class="text-2xl font-bold text-gray-900" id="statDraftCount">0</p>
        </div>
        <div id="cardSubmitted" class="bg-white rounded-lg border border-gray-200 p-3 hover:shadow-md hover:border-red-400 transition-all duration-200 cursor-pointer" onclick="filterByStatus('submitted')">
            <p class="text-xs font-medium text-gray-500 mb-1">Submitted</p>
            <p class="text-2xl font-bold text-gray-900" id="statSubmittedCount">0</p>
        </div>
        <div id="cardApproved" class="bg-white rounded-lg border border-gray-200 p-3 hover:shadow-md hover:border-red-400 transition-all duration-200 cursor-pointer" onclick="filterByStatus('approved')">
            <p class="text-xs font-medium text-gray-500 mb-1">Approved</p>
            <p class="text-2xl font-bold text-gray-900" id="statApprovedCount">0</p>
        </div>
        <div id="cardRejected" class="bg-white rounded-lg border border-gray-200 p-3 hover:shadow-md hover:border-red-400 transition-all duration-200 cursor-pointer" onclick="filterByStatus('rejected')">
            <p class="text-xs font-medium text-gray-500 mb-1">Rejected</p>
            <p class="text-2xl font-bold text-gray-900" id="statRejectedCount">0</p>
        </div>
    </div>
    @endif

    <!-- Type Tabs — hidden when locked to a single type, otherwise show only allowed types -->
    @php
        $lockedType   = $lockedType ?? null;
        $allowedTypes = $allowedTypes ?? ['project','support','office'];
        $tabProject   = in_array('project', $allowedTypes);
        $tabSupport   = in_array('support', $allowedTypes);
        $tabOffice    = in_array('office',  $allowedTypes);
        $showTabs     = !$lockedType && ($tabProject + $tabSupport + $tabOffice) > 1;
    @endphp
    @if($showTabs)
    <div class="flex items-center gap-2 mb-4">
        <button id="typeTabAll"
            onclick="filterByType('')"
            class="type-tab-btn inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-semibold border-2 border-red-600 bg-red-600 text-white transition-all duration-150">
            <i class="fas fa-list text-xs"></i> All
        </button>
        @if($tabProject)
        <button id="typeTabProject"
            onclick="filterByType('project')"
            class="type-tab-btn inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-semibold border-2 border-gray-200 bg-white text-gray-600 hover:border-blue-400 hover:text-blue-600 transition-all duration-150">
            <i class="fas fa-project-diagram text-xs"></i> Project
        </button>
        @endif
        @if($tabSupport)
        <button id="typeTabSupport"
            onclick="filterByType('support')"
            class="type-tab-btn inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-semibold border-2 border-gray-200 bg-white text-gray-600 hover:border-purple-400 hover:text-purple-600 transition-all duration-150">
            <i class="fas fa-headset text-xs"></i> Support
        </button>
        @endif
        @if($tabOffice)
        <button id="typeTabOffice"
            onclick="filterByType('office')"
            class="type-tab-btn inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-semibold border-2 border-gray-200 bg-white text-gray-600 hover:border-gray-400 hover:text-gray-700 transition-all duration-150">
            <i class="fas fa-building text-xs"></i> Office
        </button>
        @endif
    </div>
    @endif

    <!-- Filters & Search -->
    <div class="bg-gray-50 rounded-lg border border-gray-200 p-4 mb-5">
        <div class="flex items-center gap-2 mb-3">
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/>
            </svg>
            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Filters</span>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <!-- Period -->
            <div class="flex flex-col">
                <label class="text-xs font-semibold text-gray-500 mb-1.5 uppercase tracking-wide">Period</label>
                <div class="flex items-center gap-2">
                    <div class="custom-dd relative flex-1" data-onchange="updateTsPeriodLabel">
                        <button type="button" class="custom-dd-btn w-full flex items-center justify-between pl-3 pr-2 py-2 bg-white border border-gray-300 rounded-lg text-sm hover:border-gray-400 transition-all text-left">
                            <span class="custom-dd-label text-gray-700">—</span>
                            <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <input type="hidden" id="filterMonth" value="">
                        <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:220px;min-width:140px;">
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="1">January</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="2">February</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="3">March</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="4">April</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="5">May</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="6">June</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="7">July</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="8">August</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="9">September</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="10">October</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="11">November</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="12">December</button>
                        </div>
                    </div>
                    <input type="number" id="filterYear" min="2000" step="1" onchange="updateTsPeriodLabel()"
                        class="px-3 py-2 border border-gray-300 rounded-lg text-sm primary-focus bg-white" style="width:86px;">
                </div>
                <p id="tsPeriodRange" class="text-xs text-gray-400 mt-1.5"></p>
            </div>
            <!-- Status -->
            <div class="flex flex-col">
                <label class="text-xs font-semibold text-gray-500 mb-1.5 uppercase tracking-wide">Status</label>
                <div class="custom-dd relative" data-onchange="">
                    <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm hover:border-gray-400 transition-all text-left">
                        <span class="custom-dd-label text-gray-500">All Status</span>
                        <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <input type="hidden" id="filterStatus" value="">
                    <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5">
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">All Status</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="draft">Draft</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="submitted">Submitted</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="approved">Approved</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="rejected">Rejected</button>
                    </div>
                </div>
            </div>
            <!-- Activity Type -->
            <div class="flex flex-col">
                <label class="text-xs font-semibold text-gray-500 mb-1.5 uppercase tracking-wide">Activity Type</label>
                <div class="custom-dd relative" data-onchange="">
                    <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm hover:border-gray-400 transition-all text-left">
                        <span class="custom-dd-label text-gray-500">All Types</span>
                        <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <input type="hidden" id="filterActivityType" value="">
                    <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5">
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">All Types</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="development">Development</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="meeting">Meeting</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="documentation">Documentation</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="testing">Testing</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="support">Support</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="training">Training</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="other">Other</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="flex gap-2 justify-end mt-3 pt-3 border-t border-gray-100">
            <button onclick="resetFilters()" class="inline-flex items-center gap-1.5 px-4 py-2 bg-white text-gray-600 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all duration-200">
                Reset
            </button>
            <button onclick="applyFilters()" class="inline-flex items-center gap-1.5 px-5 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                Apply
            </button>
        </div>
    </div>

    <!-- Pagination -->
    <div class="flex items-center justify-between mb-4">
        <!-- Bulk Actions Bar (Hidden by default) -->
        <div id="bulkActions" class="hidden items-center gap-2">
            <span class="text-sm font-medium text-gray-700">
                <span id="selectedCount">0</span> selected
            </span>
            @if($isApprovalMode || $isHoSMode)
            <button id="btnBulkApprove" onclick="openBulkApproveModal()" class="inline-flex items-center gap-1.5 px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-lg transition-all duration-200">
                <i class="fas fa-check text-xs"></i> Approve
            </button>
            <button id="btnBulkReject" onclick="openBulkRejectModal()" class="inline-flex items-center gap-1.5 px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-lg transition-all duration-200">
                <i class="fas fa-times text-xs"></i> Reject
            </button>
            @else
            <button id="btnBulkEdit" onclick="editSelectedTimesheet()" class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                Edit
            </button>
            <button id="btnBulkSubmit" onclick="openBulkSubmitModal()" class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                Submit
            </button>
            <button id="btnBulkDelete" onclick="openBulkDeleteModal()" class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                Delete
            </button>
            @endif
        </div>
        <span id="noBulkActions" class="text-sm text-gray-500">
            <span id="currentRangeStart">1</span>–<span id="currentRangeEnd">20</span> of <span id="totalItems">0</span> timesheets
        </span>
        <div class="flex items-center gap-1">
            <button onclick="previousPage()" id="btnPrevPage" disabled class="p-1.5 rounded-lg border border-gray-200 hover:bg-gray-100 disabled:opacity-30 disabled:cursor-not-allowed transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-gray-600">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                </svg>
            </button>
            <button onclick="nextPage()" id="btnNextPage" class="p-1.5 rounded-lg border border-gray-200 hover:bg-gray-100 disabled:opacity-30 disabled:cursor-not-allowed transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-gray-600">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                </svg>
            </button>
        </div>
    </div>

    <!-- Timesheets Table -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="overflow-auto" style="max-height: calc(100vh - 380px); min-height: 200px;">
            <table id="timesheetTable" class="w-full text-sm border-collapse" style="min-width: {{ $lockedType === 'support' ? '1200px' : '900px' }};">
                <thead id="timesheetTableHead" class="sticky top-0 z-10 bg-gray-50">
                    @if($isApprovalMode && $lockedType !== 'support')
                    <tr>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap border-b border-gray-200" style="min-width:36px;">
                            <input type="checkbox" id="selectAll" class="w-4 h-4 rounded border-gray-300">
                        </th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap border-b border-gray-200" style="min-width:130px;">Employee</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap border-b border-gray-200" style="min-width:110px;">Date</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap border-b border-gray-200" style="min-width:120px;">Time</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap border-b border-gray-200" style="min-width:80px;">Duration</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap border-b border-gray-200" style="min-width:150px;">Project/Ticket</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap border-b border-gray-200" style="min-width:120px;">Activity</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap border-b border-gray-200" style="min-width:200px;">Description</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap border-b border-gray-200" style="min-width:110px;">Status</th>
                    </tr>
                    @elseif($lockedType === 'support')
                    <tr>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap border-b border-gray-200" style="min-width:36px;"><input type="checkbox" id="selectAll" class="w-4 h-4 rounded border-gray-300"></th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap border-b border-gray-200" style="min-width:100px;">Date</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap border-b border-gray-200" style="min-width:55px;">Month</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap border-b border-gray-200" style="min-width:55px;">Year</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap border-b border-gray-200" style="min-width:130px;">Name</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap border-b border-gray-200" style="min-width:100px;">Status</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap border-b border-gray-200" style="min-width:130px;">Ticket</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap border-b border-gray-200" style="min-width:180px;">Description</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap border-b border-gray-200" style="min-width:120px;">Customer</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap border-b border-gray-200" style="min-width:80px;">Quota MD</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap border-b border-gray-200" style="min-width:180px;">Activity</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap border-b border-gray-200" style="min-width:90px;">MD Consumed</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap border-b border-gray-200" style="min-width:70px;">On Site</th>
                    </tr>
                    @else
                    <tr>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap border-b border-gray-200" style="min-width:36px;">
                            <input type="checkbox" id="selectAll" class="w-4 h-4 rounded border-gray-300">
                        </th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap border-b border-gray-200" style="min-width:110px;">Date</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap border-b border-gray-200" style="min-width:120px;">Time</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap border-b border-gray-200" style="min-width:80px;">Duration</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap border-b border-gray-200" style="min-width:150px;">Project/Ticket</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap border-b border-gray-200" style="min-width:120px;">Activity</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap border-b border-gray-200" style="min-width:200px;">Description</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap border-b border-gray-200" style="min-width:110px;">Status</th>
                    </tr>
                    @endif
                </thead>
                <tbody id="timesheetsTableBody" class="divide-y divide-gray-100 bg-white">
                    <!-- Data will be loaded here -->
                </tbody>
            </table>
        </div>

        <div id="emptyState" class="hidden text-center py-16">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 text-gray-300 mx-auto mb-3">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
            @if($isApprovalMode || $isHoSMode)
            <p class="text-gray-600 font-semibold mb-1">No Timesheets Pending Approval</p>
            <p class="text-gray-400 text-xs">All employee timesheets have been reviewed</p>
            @else
            <p class="text-gray-600 font-semibold mb-1">No Timesheets Found</p>
            <p class="text-gray-400 text-xs mb-4">Click "Create Timesheet" button</p>
            @endif
        </div>
    </div>
</div>

@if($isApprovalMode || $isHoSMode)
<!-- Rejection Reason Modal -->
<div id="rejectModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-md w-full shadow-2xl">
        <div class="p-6">
            <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 bg-red-100 rounded-full">
                <i class="fas fa-times text-red-600 text-xl"></i>
            </div>
            <h3 class="text-lg font-bold text-gray-900 text-center mb-2">Reject Timesheet</h3>
            <p class="text-sm text-gray-600 text-center mb-4">Please provide a reason for rejection</p>
            <input type="hidden" id="rejectTimesheetId">
            <textarea id="rejectionReason" rows="3" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-800 focus:border-transparent resize-none mb-4" placeholder="Enter rejection reason..."></textarea>
            <div class="flex gap-3">
                <button onclick="closeRejectModal()" class="flex-1 px-4 py-2.5 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">Cancel</button>
                <button onclick="confirmReject()" class="flex-1 px-4 py-2.5 bg-red-600 text-white text-sm font-semibold rounded-lg hover:bg-red-700 transition-all">Reject</button>
            </div>
        </div>
    </div>
</div>

<!-- Approval Confirmation Modal (single) -->
<div id="approveModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-md w-full shadow-2xl">
        <div class="p-6">
            <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 bg-green-100 rounded-full">
                <i class="fas fa-check text-green-600 text-xl"></i>
            </div>
            <h3 class="text-lg font-bold text-gray-900 text-center mb-2">Approve Timesheet</h3>
            <p class="text-sm text-gray-600 text-center mb-6">Are you sure you want to approve this timesheet entry?</p>
            <input type="hidden" id="approveTimesheetId">
            <div class="flex gap-3">
                <button onclick="closeApproveModal()" class="flex-1 px-4 py-2.5 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">Cancel</button>
                <button onclick="switchToRejectModal()" class="flex-1 px-4 py-2.5 bg-red-600 text-white text-sm font-semibold rounded-lg hover:bg-red-700 transition-all">Reject</button>
                <button onclick="confirmApprove()" class="flex-1 px-4 py-2.5 bg-green-600 text-white text-sm font-semibold rounded-lg hover:bg-green-700 transition-all">Approve</button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Approve Modal -->
<div id="bulkApproveModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-md w-full shadow-2xl">
        <div class="p-6">
            <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 bg-green-100 rounded-full">
                <i class="fas fa-check text-green-600 text-xl"></i>
            </div>
            <h3 class="text-lg font-bold text-gray-900 text-center mb-2">Approve Selected Timesheets</h3>
            <p class="text-sm text-gray-600 text-center mb-6">Approve <span id="bulkApproveCount" class="font-bold text-green-600">0</span> selected timesheet(s)?</p>
            <div class="flex gap-3">
                <button onclick="closeBulkApproveModal()" class="flex-1 px-4 py-2.5 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">Cancel</button>
                <button onclick="confirmBulkApprove()" class="flex-1 px-4 py-2.5 bg-green-600 text-white text-sm font-semibold rounded-lg hover:bg-green-700 transition-all">Approve All</button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Reject Modal -->
<div id="bulkRejectModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-md w-full shadow-2xl">
        <div class="p-6">
            <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 bg-red-100 rounded-full">
                <i class="fas fa-times text-red-600 text-xl"></i>
            </div>
            <h3 class="text-lg font-bold text-gray-900 text-center mb-2">Reject Selected Timesheets</h3>
            <p class="text-sm text-gray-600 text-center mb-4">Reject <span id="bulkRejectCount" class="font-bold text-red-600">0</span> selected timesheet(s).</p>
            <textarea id="bulkRejectionReason" rows="3" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-800 focus:border-transparent resize-none mb-4" placeholder="Enter rejection reason for all selected..."></textarea>
            <div class="flex gap-3">
                <button onclick="closeBulkRejectModal()" class="flex-1 px-4 py-2.5 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">Cancel</button>
                <button onclick="confirmBulkReject()" class="flex-1 px-4 py-2.5 bg-red-600 text-white text-sm font-semibold rounded-lg hover:bg-red-700 transition-all">Reject All</button>
            </div>
        </div>
    </div>
</div>
@endif

<!-- Create/Edit Timesheet Modal -->
<div id="timesheetModal" class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl flex flex-col max-h-[92vh] overflow-hidden" onclick="event.stopPropagation()">

        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 flex-shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-red-50 flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-clock text-red-700 text-sm"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-gray-900 leading-tight" id="timesheetModalTitle">Log Working Hours</h3>
                    <p class="text-xs text-gray-400 mt-0.5">Fill in your timesheet details below</p>
                </div>
            </div>
            <button type="button" onclick="closeTimesheetModal()"
                class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-700 transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Body --}}
        <form id="timesheetForm" class="overflow-y-auto flex-1">
            <input type="hidden" id="timesheetId">

            @php
                $lockedType   = $lockedType ?? null;
                $allowedTypes = $allowedTypes ?? ['project', 'support', 'office'];
                $visProject   = in_array('project', $allowedTypes);
                $visSupport   = in_array('support', $allowedTypes);
                $visOffice    = in_array('office',  $allowedTypes);
                $typeCount    = (int)$visProject + (int)$visSupport + (int)$visOffice;
                $typeGrid     = match($typeCount) { 1 => 'grid-cols-1', 2 => 'grid-cols-2', default => 'grid-cols-3' };
                $defType      = $lockedType ?? ($allowedTypes[0] ?? 'support');
            @endphp

            <div class="grid grid-cols-1 md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-gray-100">

                {{-- ── Column 1: Type & Schedule ───────────────────────────── --}}
                <div class="p-6 space-y-5">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest">Type &amp; Schedule</p>

                    {{-- Timesheet Type --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-2">
                            Type <span class="text-red-500">*</span>
                        </label>
                        <div class="grid {{ $typeGrid }} gap-2">
                            @if($visProject)
                            <label class="relative cursor-pointer">
                                <input type="radio" name="timesheetType" value="project" class="peer sr-only" {{ $defType === 'project' ? 'checked' : '' }} onchange="handleTimesheetTypeChange()">
                                <div class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg border-2 border-gray-200 peer-checked:border-blue-500 peer-checked:bg-blue-50 hover:border-blue-300 transition-all">
                                    <div class="w-7 h-7 rounded-lg bg-blue-100 flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-project-diagram text-xs text-blue-600"></i>
                                    </div>
                                    <span class="text-xs font-semibold text-gray-700 peer-checked:text-blue-700">Project</span>
                                </div>
                            </label>
                            @endif
                            @if($visSupport)
                            <label class="relative cursor-pointer">
                                <input type="radio" name="timesheetType" value="support" class="peer sr-only" {{ $defType === 'support' ? 'checked' : '' }} onchange="handleTimesheetTypeChange()">
                                <div class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg border-2 border-gray-200 peer-checked:border-purple-500 peer-checked:bg-purple-50 hover:border-purple-300 transition-all">
                                    <div class="w-7 h-7 rounded-lg bg-purple-100 flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-headset text-xs text-purple-600"></i>
                                    </div>
                                    <span class="text-xs font-semibold text-gray-700 peer-checked:text-purple-700">Support</span>
                                </div>
                            </label>
                            @endif
                            @if($visOffice)
                            <label class="relative cursor-pointer">
                                <input type="radio" name="timesheetType" value="office" class="peer sr-only" {{ $defType === 'office' ? 'checked' : '' }} onchange="handleTimesheetTypeChange()">
                                <div class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg border-2 border-gray-200 peer-checked:border-gray-500 peer-checked:bg-gray-50 hover:border-gray-400 transition-all">
                                    <div class="w-7 h-7 rounded-lg bg-gray-100 flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-building text-xs text-gray-600"></i>
                                    </div>
                                    <span class="text-xs font-semibold text-gray-700 peer-checked:text-gray-900">Office</span>
                                </div>
                            </label>
                            @endif
                        </div>
                    </div>

                    {{-- Date --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">
                            Date <span class="text-red-500">*</span>
                        </label>
                        <input type="date" id="timesheetDate" required
                            class="w-full px-3 py-2.5 border border-gray-200 rounded-md text-sm focus:ring-2 focus:ring-red-700 focus:border-transparent bg-gray-50 hover:bg-white transition-colors">
                    </div>

                    {{-- Period Selector (populated by JS — shown only when late exception exists) --}}
                    <div id="periodFieldRow" class="hidden"></div>

                    {{-- Start + End Time + Duration (hidden for support) --}}
                    <div id="timesheetTimeBlock">
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">
                            Time <span class="text-red-500">*</span>
                        </label>
                        <div class="flex items-center gap-2">
                            {{-- Start time --}}
                            <div id="timesheetStartTimeField" class="flex items-center gap-1 flex-1">
                                {{-- Start Hour --}}
                                <div class="custom-dd relative flex-1" data-fixed="true" data-onchange="tsUpdateStartTime">
                                    <button type="button" class="custom-dd-btn w-full px-2 py-2 border border-gray-200 rounded-md text-sm bg-gray-50 hover:bg-white transition-colors flex items-center justify-between gap-1">
                                        <span class="custom-dd-label text-gray-700 flex-1 text-center font-mono">08</span>
                                        <svg class="custom-dd-arrow w-3 h-3 text-gray-400 transition-transform flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </button>
                                    <div class="custom-dd-panel hidden bg-white border border-gray-200 rounded-md shadow-lg overflow-y-auto max-h-48">
                                        @for($h = 0; $h < 24; $h++)
                                            <button type="button" class="custom-dd-item w-full px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 text-center font-mono" data-value="{{ str_pad($h, 2, '0', STR_PAD_LEFT) }}">{{ str_pad($h, 2, '0', STR_PAD_LEFT) }}</button>
                                        @endfor
                                    </div>
                                    <input type="hidden" id="timesheetStartHour" value="08">
                                </div>
                                <span class="text-sm font-bold text-gray-400 flex-shrink-0">:</span>
                                {{-- Start Minute --}}
                                <div class="custom-dd relative flex-1" data-fixed="true" data-onchange="tsUpdateStartTime">
                                    <button type="button" class="custom-dd-btn w-full px-2 py-2 border border-gray-200 rounded-md text-sm bg-gray-50 hover:bg-white transition-colors flex items-center justify-between gap-1">
                                        <span class="custom-dd-label text-gray-700 flex-1 text-center font-mono">00</span>
                                        <svg class="custom-dd-arrow w-3 h-3 text-gray-400 transition-transform flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </button>
                                    <div class="custom-dd-panel hidden bg-white border border-gray-200 rounded-md shadow-lg overflow-y-auto max-h-48">
                                        @for($m = 0; $m < 60; $m += 5)
                                            <button type="button" class="custom-dd-item w-full px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 text-center font-mono" data-value="{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}">{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}</button>
                                        @endfor
                                    </div>
                                    <input type="hidden" id="timesheetStartMinute" value="00">
                                </div>
                            </div>
                            <i class="fas fa-arrow-right text-xs text-gray-300 flex-shrink-0"></i>
                            {{-- End time --}}
                            <div id="timesheetEndTimeField" class="flex items-center gap-1 flex-1">
                                {{-- End Hour --}}
                                <div class="custom-dd relative flex-1" data-fixed="true" data-onchange="tsUpdateEndTime">
                                    <button type="button" class="custom-dd-btn w-full px-2 py-2 border border-gray-200 rounded-md text-sm bg-gray-50 hover:bg-white transition-colors flex items-center justify-between gap-1">
                                        <span class="custom-dd-label text-gray-700 flex-1 text-center font-mono">17</span>
                                        <svg class="custom-dd-arrow w-3 h-3 text-gray-400 transition-transform flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </button>
                                    <div class="custom-dd-panel hidden bg-white border border-gray-200 rounded-md shadow-lg overflow-y-auto max-h-48">
                                        @for($h = 0; $h < 24; $h++)
                                            <button type="button" class="custom-dd-item w-full px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 text-center font-mono" data-value="{{ str_pad($h, 2, '0', STR_PAD_LEFT) }}">{{ str_pad($h, 2, '0', STR_PAD_LEFT) }}</button>
                                        @endfor
                                    </div>
                                    <input type="hidden" id="timesheetEndHour" value="17">
                                </div>
                                <span class="text-sm font-bold text-gray-400 flex-shrink-0">:</span>
                                {{-- End Minute --}}
                                <div class="custom-dd relative flex-1" data-fixed="true" data-onchange="tsUpdateEndTime">
                                    <button type="button" class="custom-dd-btn w-full px-2 py-2 border border-gray-200 rounded-md text-sm bg-gray-50 hover:bg-white transition-colors flex items-center justify-between gap-1">
                                        <span class="custom-dd-label text-gray-700 flex-1 text-center font-mono">00</span>
                                        <svg class="custom-dd-arrow w-3 h-3 text-gray-400 transition-transform flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </button>
                                    <div class="custom-dd-panel hidden bg-white border border-gray-200 rounded-md shadow-lg overflow-y-auto max-h-48">
                                        @for($m = 0; $m < 60; $m += 5)
                                            <button type="button" class="custom-dd-item w-full px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 text-center font-mono" data-value="{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}">{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}</button>
                                        @endfor
                                    </div>
                                    <input type="hidden" id="timesheetEndMinute" value="00">
                                </div>
                            </div>
                        </div>
                        {{-- Duration badge --}}
                        <p class="mt-1.5 text-xs text-gray-400">
                            Duration: <span id="timesheetDuration" class="font-semibold text-gray-600">—</span>
                        </p>
                        <input type="hidden" id="timesheetStartTime">
                        <input type="hidden" id="timesheetEndTime">
                    </div>

                    {{-- Billable (project only) --}}
                    <div id="billableSection" class="hidden">
                        <label class="flex items-center gap-3 p-3 bg-green-50 border border-green-200 rounded-md cursor-pointer hover:bg-green-100 transition-colors">
                            <input type="checkbox" id="timesheetBillable" checked
                                class="w-4 h-4 text-green-600 border-gray-300 rounded focus:ring-green-500">
                            <span class="text-sm font-semibold text-green-800">
                                <span class="font-bold mr-1">Rp</span> Billable hours
                            </span>
                        </label>
                    </div>
                </div>

                {{-- ── Column 2: Work Details ───────────────────────────────── --}}
                <div class="p-6 space-y-5">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest">Work Details</p>

                    {{-- Dynamic Fields Container (injected by JS based on type) --}}
                    <div id="dynamicFields" class="space-y-4"></div>

                    {{-- Activity Description --}}
                    <div>
                        <label for="timesheetDescription" class="block text-xs font-semibold text-gray-600 mb-1.5">
                            Description <span class="text-red-500">*</span>
                        </label>
                        <textarea id="timesheetDescription" required rows="3"
                            class="w-full px-3 py-2.5 border border-gray-200 rounded-md text-sm focus:ring-2 focus:ring-red-700 focus:border-transparent resize-none bg-gray-50 hover:bg-white transition-colors"
                            placeholder="What did you work on?"></textarea>
                    </div>

                    {{-- Notes --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Notes <span class="text-gray-400 font-normal">(optional)</span></label>
                        <textarea id="timesheetNotes" rows="2"
                            class="w-full px-3 py-2.5 border border-gray-200 rounded-md text-sm focus:ring-2 focus:ring-red-700 focus:border-transparent resize-none bg-gray-50 hover:bg-white transition-colors"
                            placeholder="Additional notes..."></textarea>
                    </div>
                </div>

            </div>
        </form>

        {{-- Footer --}}
        <div class="flex items-center justify-between px-6 py-4 border-t border-gray-100 flex-shrink-0 bg-gray-50/40">
            <p class="text-xs text-gray-400"><span class="text-red-500">*</span> Required fields</p>
            <button type="submit" form="timesheetForm" id="btnSaveTimesheet"
                class="px-6 py-2 text-sm font-semibold text-white primary-gradient hover:opacity-90 transition-all shadow-sm rounded-lg">
                Save Timesheet
            </button>
        </div>

    </div>
</div>

<!-- Modal Konfirmasi Bulk Delete -->
<div id="confirmBulkDeleteModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full shadow-xl overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-full bg-red-100 flex items-center justify-center shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-red-600">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">Delete Timesheets</h3>
                    <p class="text-xs text-gray-500">This action cannot be undone</p>
                </div>
            </div>
            <button onclick="closeBulkDeleteModal()" class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-600 hover:bg-red-800 hover:text-white transition-all text-sm">✕</button>
        </div>
        <div class="px-6 py-5">
            <p class="text-sm text-gray-600">Are you sure you want to delete <span id="bulkActionCount" class="font-semibold text-red-600">0</span> timesheet(s)? All selected entries will be permanently removed.</p>
        </div>
        <div class="flex gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50/50">
            <button onclick="closeBulkDeleteModal()" class="flex-1 px-4 py-2 bg-white text-gray-700 text-sm font-medium rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">Cancel</button>
            <button onclick="confirmBulkDelete()" class="flex-1 px-4 py-2 bg-red-600 text-white text-sm font-semibold rounded-lg hover:bg-red-700 transition-all">Delete</button>
        </div>
    </div>
</div>

<!-- Modal Konfirmasi Bulk Submit -->
<div id="confirmBulkSubmitModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full shadow-xl overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-full bg-green-100 flex items-center justify-center shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-green-600">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">Submit Timesheets</h3>
                    <p class="text-xs text-gray-500">Send for approval</p>
                </div>
            </div>
            <button onclick="closeBulkSubmitModal()" class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-600 hover:bg-red-800 hover:text-white transition-all text-sm">✕</button>
        </div>
        <div class="px-6 py-5">
            <p class="text-sm text-gray-600">Submit <span id="bulkSubmitCount" class="font-semibold text-green-700">0</span> timesheet(s) for approval? You won't be able to edit them until they're reviewed.</p>
        </div>
        <div class="flex gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50/50">
            <button onclick="closeBulkSubmitModal()" class="flex-1 px-4 py-2 bg-white text-gray-700 text-sm font-medium rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">Cancel</button>
            <button onclick="confirmBulkSubmit()" class="flex-1 px-4 py-2 bg-green-600 text-white text-sm font-semibold rounded-lg hover:bg-green-700 transition-all">Submit</button>
        </div>
    </div>
</div>

<!-- Modal Konfirmasi Delete (Single) -->
<div id="confirmDeleteModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full shadow-xl overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-full bg-red-100 flex items-center justify-center shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-red-600">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">Delete Timesheet</h3>
                    <p class="text-xs text-gray-500">This action cannot be undone</p>
                </div>
            </div>
            <button onclick="closeConfirmDelete()" class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-600 hover:bg-red-800 hover:text-white transition-all text-sm">✕</button>
        </div>
        <div class="px-6 py-5">
            <p class="text-sm text-gray-600">Are you sure you want to delete this timesheet entry? This will permanently remove all logged hours.</p>
        </div>
        <div class="flex gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50/50">
            <button onclick="closeConfirmDelete()" class="flex-1 px-4 py-2 bg-white text-gray-700 text-sm font-medium rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">Cancel</button>
            <button onclick="confirmDelete()" class="flex-1 px-4 py-2 bg-red-600 text-white text-sm font-semibold rounded-lg hover:bg-red-700 transition-all">Delete</button>
        </div>
    </div>
</div>

<!-- Modal Konfirmasi Submit (Single) -->
<div id="confirmSubmitModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full shadow-xl overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-full bg-green-100 flex items-center justify-center shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-green-600">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">Submit Timesheet</h3>
                    <p class="text-xs text-gray-500">Send for approval</p>
                </div>
            </div>
            <button onclick="closeSubmitModal()" class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-600 hover:bg-red-800 hover:text-white transition-all text-sm">✕</button>
        </div>
        <div class="px-6 py-5">
            <p class="text-sm text-gray-600">Submit this timesheet for approval? Once submitted, you won't be able to edit it until it's reviewed.</p>
        </div>
        <input type="hidden" id="submitTimesheetId">
        <div class="flex gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50/50">
            <button onclick="closeSubmitModal()" class="flex-1 px-4 py-2 bg-white text-gray-700 text-sm font-medium rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">Cancel</button>
            <button onclick="confirmSubmit()" class="flex-1 px-4 py-2 bg-green-600 text-white text-sm font-semibold rounded-lg hover:bg-green-700 transition-all">Submit</button>
        </div>
    </div>
</div>



<script>
    // Pass PHP variables to JavaScript
    // Placed inline (not via push directive) to guarantee execution before DOMContentLoaded
    window.isApprovalMode  = {{ $isApprovalMode ? 'true' : 'false' }};
    window.isAdminMode     = {{ $isAdminMode ? 'true' : 'false' }};
    window.userRoleId      = {{ $roleId ?? 'null' }};
    window.lockedType      = {!! $lockedType ? "'{$lockedType}'" : 'null' !!};
    window.allowedTypes    = {!! json_encode($allowedTypes ?? ['project','support','office']) !!};
    window.isHoSMode       = {{ $isHoSMode ? 'true' : 'false' }};
</script>
<script src="/js/custom-dropdown.js?v={{ filemtime(public_path('js/custom-dropdown.js')) }}"></script>
<script src="/js/calendar-timesheets.js?v={{ filemtime(public_path('js/calendar-timesheets.js')) }}"></script>

{{-- ── Late Access Request Modal (Employee) ──────────────────────────────── --}}
@if(!isset($isHead) || !$isHead)
<div id="lateAccessModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-lg w-full shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 flex-shrink-0">
            <h3 class="text-base font-bold text-gray-900"><i class="fas fa-user-clock text-yellow-500 mr-1.5"></i>Request Late Access</h3>
            <button onclick="closeLateAccessModal()" class="w-7 h-7 flex items-center justify-center rounded-lg bg-gray-100 text-gray-600 hover:bg-red-800 hover:text-white transition-all">
                <i class="fas fa-times text-xs"></i>
            </button>
        </div>

        {{-- Tabs --}}
        <div class="flex border-b border-gray-200 flex-shrink-0">
            <button onclick="lateAccessTab('request')" id="laTabRequest"
                class="flex-1 py-2.5 text-xs font-semibold text-red-700 border-b-2 border-red-700 transition-all">
                Submit Request
            </button>
            <button onclick="lateAccessTab('history')" id="laTabHistory"
                class="flex-1 py-2.5 text-xs font-semibold text-gray-500 border-b-2 border-transparent hover:text-gray-700 transition-all">
                My History
            </button>
        </div>

        {{-- Tab: Submit Request --}}
        <div id="laPanelRequest" class="p-5 space-y-4 overflow-y-auto">
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-xs text-blue-700">
                <i class="fas fa-info-circle mr-1"></i>
                Select a closed period you still need to submit timesheets for. Your request will be reviewed by your Head and RPMO.
            </div>

            <div class="flex flex-col">
                <label class="text-xs font-semibold text-gray-600 mb-1.5">Select Period <span class="text-red-600">*</span></label>
                <select id="laPeriodSelect" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                    <option value="">— Loading periods... —</option>
                </select>
            </div>

            <div class="flex flex-col">
                <label class="text-xs font-semibold text-gray-600 mb-1.5">Reason / Notes <span class="text-red-600">*</span></label>
                <textarea id="laRequestNotes" rows="3" maxlength="1000" required
                    placeholder="Explain why you need late access..."
                    class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 resize-none"></textarea>
            </div>
        </div>

        {{-- Tab: History --}}
        <div id="laPanelHistory" class="hidden p-5 overflow-y-auto flex-1">
            <!-- Valid period info banner -->
            <div id="laValidPeriodsInfo" class="mb-4 bg-blue-50 border border-blue-200 rounded-lg p-3 text-xs text-blue-700 hidden">
                <p class="font-semibold mb-1"><i class="fas fa-calendar-check mr-1"></i>Periods you can submit to:</p>
                <div id="laValidPeriodsList"></div>
            </div>
            <div id="laHistoryList"><p class="text-xs text-gray-400 text-center py-6">Loading...</p></div>
        </div>

        <div id="laRequestFooter" class="flex justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50 flex-shrink-0">
            <button onclick="closeLateAccessModal()" class="px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">Cancel</button>
            <button onclick="submitLateAccessRequest()" class="px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all">
                Submit Request
            </button>
        </div>
        <div id="laHistoryFooter" class="hidden flex justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50 flex-shrink-0">
            <button onclick="closeLateAccessModal()" class="px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">Close</button>
        </div>
    </div>
</div>

@push('scripts')
<script>
const _laCsrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
const _laHdr  = () => ({ 'Content-Type':'application/json','Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':_laCsrf() });

async function openLateAccessModal() {
    document.getElementById('lateAccessModal').classList.remove('hidden');
    lateAccessTab('request');
    // Load closed periods for the dropdown
    try {
        const res  = await fetch('/api/periods/closed', { headers: _laHdr(), credentials: 'same-origin' });
        const data = await res.json();
        const sel  = document.getElementById('laPeriodSelect');
        if (data.success && data.data.length) {
            sel.innerHTML = '<option value="">— Select period —</option>'
                + data.data.map(p => `<option value="${p.id}">${p.label} (${p.start_date} – ${p.end_date})</option>`).join('');
        } else {
            sel.innerHTML = '<option value="">No closed periods available</option>';
        }
    } catch (e) {
        document.getElementById('laPeriodSelect').innerHTML = '<option value="">Failed to load periods</option>';
    }
}

function closeLateAccessModal() {
    document.getElementById('lateAccessModal').classList.add('hidden');
}

function lateAccessTab(tab) {
    const isReq = tab === 'request';
    document.getElementById('laPanelRequest').classList.toggle('hidden', !isReq);
    document.getElementById('laPanelHistory').classList.toggle('hidden', isReq);
    document.getElementById('laRequestFooter').classList.toggle('hidden', !isReq);
    document.getElementById('laHistoryFooter').classList.toggle('hidden', isReq);
    document.getElementById('laTabRequest').className = `flex-1 py-2.5 text-xs font-semibold transition-all ${isReq ? 'text-red-700 border-b-2 border-red-700' : 'text-gray-500 border-b-2 border-transparent hover:text-gray-700'}`;
    document.getElementById('laTabHistory').className = `flex-1 py-2.5 text-xs font-semibold transition-all ${!isReq ? 'text-red-700 border-b-2 border-red-700' : 'text-gray-500 border-b-2 border-transparent hover:text-gray-700'}`;
    if (!isReq) loadLateAccessHistory();
}

function _laRemainingTime(expiresAtIso) {
    if (!expiresAtIso) return null;
    const diff = new Date(expiresAtIso) - new Date();
    if (diff <= 0) return null;
    const h = Math.floor(diff / 3600000);
    const d = Math.floor(h / 24);
    if (d > 0) return `${d}d ${h % 24}h remaining`;
    if (h > 0) return `${h}h remaining`;
    const m = Math.floor(diff / 60000);
    return `${m}m remaining`;
}

async function loadLateAccessHistory() {
    document.getElementById('laHistoryList').innerHTML = '<p class="text-xs text-gray-400 text-center py-6">Loading...</p>';
    document.getElementById('laValidPeriodsInfo').classList.add('hidden');

    try {
        // Load request history + valid periods in parallel
        const [histRes, vpRes] = await Promise.all([
            fetch('/api/periods/my-exception-requests', { headers: _laHdr(), credentials: 'same-origin' }),
            fetch('/api/timesheets/valid-periods',       { headers: _laHdr(), credentials: 'same-origin' }),
        ]);
        const histData = await histRes.json();
        const vpData   = await vpRes.json();

        // ── Valid period info banner ──────────────────────────────────────────
        if (vpData.success) {
            const vp  = vpData.data;
            const all = [...(vp.active_window || []), ...(vp.late_exceptions || [])];
            if (all.length) {
                const lines = all.map(p => {
                    if (p.type === 'late_exception') {
                        const rem = _laRemainingTime(p.expires_at_iso);
                        return `<span class="inline-flex items-center gap-1 mr-2 mb-1 px-2 py-0.5 bg-yellow-100 text-yellow-800 rounded-full font-semibold">
                            <i class="fas fa-clock text-[9px]"></i>${p.label}
                            ${rem ? `<span class="font-normal opacity-75">(${rem})</span>` : '<span class="font-normal opacity-75">(expired)</span>'}
                        </span>`;
                    }
                    const ok = p.global_status === 'open' && p.domain_status === 'open';
                    return `<span class="inline-flex items-center gap-1 mr-2 mb-1 px-2 py-0.5 ${ok ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500'} rounded-full font-semibold">
                        ${p.label} <span class="font-normal opacity-75">(${p.type === 'current' ? 'current period' : 'previous period'}${ok ? '' : ' — not open'})</span>
                    </span>`;
                }).join('');
                document.getElementById('laValidPeriodsList').innerHTML = `<div class="flex flex-wrap mt-1">${lines}</div>`;
                document.getElementById('laValidPeriodsInfo').classList.remove('hidden');
            }
        }

        // ── Request history ───────────────────────────────────────────────────
        const requests = histData.data || [];
        if (!requests.length) {
            document.getElementById('laHistoryList').innerHTML = '<p class="text-xs text-gray-400 text-center py-6">No requests yet.</p>';
            return;
        }

        document.getElementById('laHistoryList').innerHTML = requests.map(r => {
            const rem = r.is_access_active ? _laRemainingTime(r.expires_at_iso) : null;
            const expiredBadge = r.is_expired
                ? `<p class="text-xs text-gray-400 mt-1.5 flex items-center gap-1"><i class="fas fa-clock-rotate-left text-[10px]"></i>Access expired ${r.expires_at}</p>`
                : '';
            const activeBadge = r.is_access_active
                ? `<p class="text-xs text-green-600 mt-1.5 font-medium flex items-center gap-1">
                    <i class="fas fa-check-circle"></i>Access active until ${r.expires_at}
                    ${rem ? `<span class="text-green-500 font-normal">(${rem})</span>` : ''}
                   </p>`
                : '';

            return `
            <div class="border border-gray-200 rounded-lg p-3 mb-3 ${r.is_access_active ? 'border-green-200 bg-green-50/40' : ''}">
                <div class="flex items-start justify-between gap-2 mb-1">
                    <p class="text-sm font-semibold text-gray-900">${r.period_label}</p>
                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold shrink-0 ${r.status_color}">${r.status_label}</span>
                </div>
                ${r.notes ? `<p class="text-xs text-gray-600 mt-1.5 bg-gray-50 rounded px-2 py-1">${r.notes}</p>` : ''}
                ${r.status === 'pending_head' ? `<p class="text-xs text-yellow-600 mt-1.5"><i class="fas fa-hourglass-half mr-1"></i>Waiting for Head approval.</p>` : ''}
                ${r.status === 'pending_rpmo' ? `<p class="text-xs text-blue-600 mt-1.5"><i class="fas fa-hourglass-half mr-1"></i>Approved by Head. Waiting for RPMO.</p>` : ''}
                ${activeBadge}${expiredBadge}
                ${r.status === 'rejected' ? `<p class="text-xs text-red-500 mt-1.5"><i class="fas fa-times-circle mr-1"></i>Rejected: ${r.rejection_notes ?? '-'}</p>` : ''}
                <p class="text-xs text-gray-300 mt-2">Submitted: ${r.created_at}</p>
            </div>`;
        }).join('');

    } catch (e) {
        document.getElementById('laHistoryList').innerHTML = '<p class="text-xs text-red-400 text-center py-6">Failed to load history.</p>';
    }
}

async function submitLateAccessRequest() {
    const periodId = document.getElementById('laPeriodSelect').value;
    const notes    = document.getElementById('laRequestNotes').value.trim();
    if (!periodId) {
        if (window.showNotification) showNotification('Please select a period first.', 'error');
        return;
    }
    if (!notes) {
        if (window.showNotification) showNotification('Please provide a reason for your request.', 'error');
        document.getElementById('laRequestNotes').focus();
        return;
    }
    try {
        const res  = await fetch('/api/periods/exception-requests', {
            method: 'POST',
            headers: _laHdr(),
            credentials: 'same-origin',
            body: JSON.stringify({ period_id: parseInt(periodId), notes: notes || null }),
        });
        const data = await res.json();
        if (data.success) {
            if (window.showNotification) showNotification(data.message, 'success');
            document.getElementById('laRequestNotes').value = '';
            lateAccessTab('history'); // switch to history to show the new request
        } else {
            if (window.showNotification) showNotification(data.message || 'Failed to submit request.', 'error');
        }
    } catch (e) {
        if (window.showNotification) showNotification('Network error.', 'error');
    }
}
</script>
@endpush
@endif

@endsection
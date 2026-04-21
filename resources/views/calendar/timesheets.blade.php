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
        @if(!$isApprovalMode && !$isHoSMode)
        <div class="flex gap-2">
            <button onclick="openTimesheetModal()" class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                Create Timesheet
            </button>
        </div>
        @endif
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
    <div class="bg-white rounded-xl border border-gray-200 p-4 mb-6 shadow-sm">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
            <div class="flex flex-col">
                <label class="text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Start Date</label>
                <input type="date" id="filterStartDate" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent bg-white" value="">
            </div>
            <div class="flex flex-col">
                <label class="text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">End Date</label>
                <input type="date" id="filterEndDate" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent bg-white" value="">
            </div>
            <div class="flex flex-col">
                <label class="text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Status</label>
                <div class="sel-wrap">
                    <select id="filterStatus" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent bg-white">
                        <option value="">All Status</option>
                        <option value="draft">Draft</option>
                        <option value="submitted">Submitted</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                    <i class="fas fa-bars sel-icon"></i>
                </div>
            </div>
            <div class="flex flex-col">
                <label class="text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Activity Type</label>
                <div class="sel-wrap">
                    <select id="filterActivityType" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent bg-white">
                        <option value="">All Types</option>
                        <option value="development">Development</option>
                        <option value="meeting">Meeting</option>
                        <option value="documentation">Documentation</option>
                        <option value="testing">Testing</option>
                        <option value="support">Support</option>
                        <option value="training">Training</option>
                        <option value="other">Other</option>
                    </select>
                    <i class="fas fa-bars sel-icon"></i>
                </div>
            </div>
        </div>
        <div class="flex gap-2 justify-end mt-3 pt-3 border-t border-gray-100">
            <button onclick="applyFilters()" class="inline-flex items-center gap-1.5 px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                Apply
            </button>
            <button onclick="resetFilters()" class="inline-flex items-center gap-1.5 px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all duration-200">
                Reset
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
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap border-b border-gray-200" style="min-width:130px;">Ticket</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap border-b border-gray-200" style="min-width:180px;">Description</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap border-b border-gray-200" style="min-width:120px;">Customer</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap border-b border-gray-200" style="min-width:80px;">Quota MD</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap border-b border-gray-200" style="min-width:180px;">Activity</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap border-b border-gray-200" style="min-width:90px;">MD Consumed</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap border-b border-gray-200" style="min-width:70px;">On Site</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap border-b border-gray-200" style="min-width:100px;">Status</th>
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
    <div class="bg-white shadow-2xl w-full max-w-3xl flex flex-col max-h-[92vh] overflow-hidden" onclick="event.stopPropagation()">

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
                class="w-8 h-8 flex items-center justify-center text-gray-400 hover:bg-gray-100 hover:text-gray-700 transition-all">
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
                                <div class="flex items-center gap-2.5 px-3 py-2.5 rounded border-2 border-gray-200 peer-checked:border-blue-500 peer-checked:bg-blue-50 hover:border-blue-300 transition-all">
                                    <div class="w-7 h-7 rounded bg-blue-100 flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-project-diagram text-xs text-blue-600"></i>
                                    </div>
                                    <span class="text-xs font-semibold text-gray-700 peer-checked:text-blue-700">Project</span>
                                </div>
                            </label>
                            @endif
                            @if($visSupport)
                            <label class="relative cursor-pointer">
                                <input type="radio" name="timesheetType" value="support" class="peer sr-only" {{ $defType === 'support' ? 'checked' : '' }} onchange="handleTimesheetTypeChange()">
                                <div class="flex items-center gap-2.5 px-3 py-2.5 rounded border-2 border-gray-200 peer-checked:border-purple-500 peer-checked:bg-purple-50 hover:border-purple-300 transition-all">
                                    <div class="w-7 h-7 rounded bg-purple-100 flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-headset text-xs text-purple-600"></i>
                                    </div>
                                    <span class="text-xs font-semibold text-gray-700 peer-checked:text-purple-700">Support</span>
                                </div>
                            </label>
                            @endif
                            @if($visOffice)
                            <label class="relative cursor-pointer">
                                <input type="radio" name="timesheetType" value="office" class="peer sr-only" {{ $defType === 'office' ? 'checked' : '' }} onchange="handleTimesheetTypeChange()">
                                <div class="flex items-center gap-2.5 px-3 py-2.5 rounded border-2 border-gray-200 peer-checked:border-gray-500 peer-checked:bg-gray-50 hover:border-gray-400 transition-all">
                                    <div class="w-7 h-7 rounded bg-gray-100 flex items-center justify-center flex-shrink-0">
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
                                <div class="sel-wrap flex-1">
                                    <select id="timesheetStartHour" required
                                        class="w-full px-2 py-2.5 border border-gray-200 rounded-md text-sm focus:ring-2 focus:ring-red-700 focus:border-transparent bg-gray-50 text-center">
                                        @for($h = 0; $h < 24; $h++)
                                            <option value="{{ str_pad($h, 2, '0', STR_PAD_LEFT) }}">{{ str_pad($h, 2, '0', STR_PAD_LEFT) }}</option>
                                        @endfor
                                    </select>
                                    <i class="fas fa-bars sel-icon" style="right:6px;"></i>
                                </div>
                                <span class="text-sm font-bold text-gray-400 flex-shrink-0">:</span>
                                <div class="sel-wrap flex-1">
                                    <select id="timesheetStartMinute" required
                                        class="w-full px-2 py-2.5 border border-gray-200 rounded-md text-sm focus:ring-2 focus:ring-red-700 focus:border-transparent bg-gray-50 text-center">
                                        @for($m = 0; $m < 60; $m += 5)
                                            <option value="{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}">{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}</option>
                                        @endfor
                                    </select>
                                    <i class="fas fa-bars sel-icon" style="right:6px;"></i>
                                </div>
                            </div>
                            <i class="fas fa-arrow-right text-xs text-gray-300 flex-shrink-0"></i>
                            {{-- End time --}}
                            <div id="timesheetEndTimeField" class="flex items-center gap-1 flex-1">
                                <div class="sel-wrap flex-1">
                                    <select id="timesheetEndHour" required
                                        class="w-full px-2 py-2.5 border border-gray-200 rounded-md text-sm focus:ring-2 focus:ring-red-700 focus:border-transparent bg-gray-50 text-center">
                                        @for($h = 0; $h < 24; $h++)
                                            <option value="{{ str_pad($h, 2, '0', STR_PAD_LEFT) }}">{{ str_pad($h, 2, '0', STR_PAD_LEFT) }}</option>
                                        @endfor
                                    </select>
                                    <i class="fas fa-bars sel-icon" style="right:6px;"></i>
                                </div>
                                <span class="text-sm font-bold text-gray-400 flex-shrink-0">:</span>
                                <div class="sel-wrap flex-1">
                                    <select id="timesheetEndMinute" required
                                        class="w-full px-2 py-2.5 border border-gray-200 rounded-md text-sm focus:ring-2 focus:ring-red-700 focus:border-transparent bg-gray-50 text-center">
                                        @for($m = 0; $m < 60; $m += 5)
                                            <option value="{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}">{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}</option>
                                        @endfor
                                    </select>
                                    <i class="fas fa-bars sel-icon" style="right:6px;"></i>
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
            <button type="submit" form="timesheetForm"
                class="px-6 py-2 text-sm font-semibold text-white primary-gradient hover:opacity-90 transition-all shadow-sm">
                Save Timesheet
            </button>
        </div>

    </div>
</div>

<!-- Modal Konfirmasi Bulk Delete -->
<div id="confirmBulkDeleteModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-md w-full shadow-2xl">
        <div class="p-6">
            <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 bg-red-100 rounded-full">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-red-600">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-900 text-center mb-2">Delete Timesheets</h3>
            <p class="text-sm text-gray-600 text-center mb-6">Are you sure you want to delete <span id="bulkActionCount" class="font-bold text-red-600">0</span> timesheet(s)? This action cannot be undone.</p>
            <div class="flex gap-3">
                <button onclick="closeBulkDeleteModal()" class="flex-1 px-4 py-2.5 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">Cancel</button>
                <button onclick="confirmBulkDelete()" class="flex-1 px-4 py-2.5 bg-red-600 text-white text-sm font-semibold rounded-lg hover:bg-red-700 transition-all">Delete</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Konfirmasi Bulk Submit -->
<div id="confirmBulkSubmitModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-md w-full shadow-2xl">
        <div class="p-6">
            <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 bg-green-100 rounded-full">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-green-600">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
                </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-900 text-center mb-2">Submit Timesheets</h3>
            <p class="text-sm text-gray-600 text-center mb-6">Submit <span id="bulkSubmitCount" class="font-bold text-green-600">0</span> timesheet(s) for approval?</p>
            <div class="flex gap-3">
                <button onclick="closeBulkSubmitModal()" class="flex-1 px-4 py-2.5 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">Cancel</button>
                <button onclick="confirmBulkSubmit()" class="flex-1 px-4 py-2.5 bg-green-600 text-white text-sm font-semibold rounded-lg hover:bg-green-700 transition-all">Submit</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Konfirmasi Delete (Single) -->
<div id="confirmDeleteModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-md w-full shadow-2xl">
        <div class="p-6">
            <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 bg-red-100 rounded-full">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-red-600">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-900 text-center mb-2">Delete Timesheet</h3>
            <p class="text-sm text-gray-600 text-center mb-6">Are you sure you want to delete this timesheet entry? This action cannot be undone.</p>
            <div class="flex gap-3">
                <button onclick="closeConfirmDelete()" class="flex-1 px-4 py-2.5 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">Cancel</button>
                <button onclick="confirmDelete()" class="flex-1 px-4 py-2.5 bg-red-600 text-white text-sm font-semibold rounded-lg hover:bg-red-700 transition-all">Delete</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Konfirmasi Submit (Single) -->
<div id="confirmSubmitModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-md w-full shadow-2xl">
        <div class="p-6">
            <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 bg-green-100 rounded-full">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-green-600">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
                </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-900 text-center mb-2">Submit Timesheet</h3>
            <p class="text-sm text-gray-600 text-center mb-6">Submit this timesheet for approval? Once submitted, you won't be able to edit it until it's reviewed.</p>
            <input type="hidden" id="submitTimesheetId">
            <div class="flex gap-3">
                <button onclick="closeSubmitModal()" class="flex-1 px-4 py-2.5 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">Cancel</button>
                <button onclick="confirmSubmit()" class="flex-1 px-4 py-2.5 bg-green-600 text-white text-sm font-semibold rounded-lg hover:bg-green-700 transition-all">Submit</button>
            </div>
        </div>
    </div>
</div>

<style>
.sel-wrap {
    position: relative;
    display: block;
}
.sel-wrap select {
    appearance: none;
    -webkit-appearance: none;
    width: 100%;
    padding-right: 2.25rem;
}
.sel-wrap .sel-icon {
    position: absolute;
    right: 0.65rem;
    top: 50%;
    transform: translateY(-50%);
    font-size: 0.65rem;
    color: #9ca3af;
    pointer-events: none;
}
</style>

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
<script src="/js/calendar-timesheets.js?v={{ filemtime(public_path('js/calendar-timesheets.js')) }}"></script>
@endsection
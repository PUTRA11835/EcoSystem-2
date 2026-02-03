@extends('dashboard')
@section('title', 'Timesheets')
@section('page-title', 'Timesheets')
@section('page-subtitle', 'Track your working hours')

@section('content')
<div class="space-y-6">
    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Timesheets</h2>
            <p class="text-gray-600 mt-1">Log and manage your working hours</p>
        </div>
        <div class="flex gap-2">
            <button onclick="openTimesheetModal()" class="inline-flex items-center px-4 py-2.5 primary-bg hover:opacity-90 text-white font-medium rounded-lg transition-all shadow-sm hover:shadow-md">
                <i class="fas fa-plus mr-2"></i>
                Log Hours
            </button>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Total Hours</p>
                    <p id="statTotalHours" class="text-2xl font-bold text-gray-900 mt-1">0.00</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-clock text-blue-600 text-xl"></i>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Billable Hours</p>
                    <p id="statBillableHours" class="text-2xl font-bold text-green-600 mt-1">0.00</p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-dollar-sign text-green-600 text-xl"></i>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">This Week</p>
                    <p id="statWeekHours" class="text-2xl font-bold text-purple-600 mt-1">0.00</p>
                </div>
                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-calendar-week text-purple-600 text-xl"></i>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Pending Approval</p>
                    <p id="statPendingCount" class="text-2xl font-bold text-yellow-600 mt-1">0</p>
                </div>
                <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-hourglass-half text-yellow-600 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter & Controls -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Start Date</label>
                <input type="date" id="filterStartDate" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-800" value="">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">End Date</label>
                <input type="date" id="filterEndDate" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-800" value="">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                <select id="filterStatus" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-800">
                    <option value="">All Status</option>
                    <option value="draft">Draft</option>
                    <option value="submitted">Submitted</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Activity Type</label>
                <select id="filterActivityType" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-800">
                    <option value="">All Types</option>
                    <option value="development">Development</option>
                    <option value="meeting">Meeting</option>
                    <option value="documentation">Documentation</option>
                    <option value="testing">Testing</option>
                    <option value="support">Support</option>
                    <option value="training">Training</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="flex items-end">
                <button onclick="applyFilters()" class="w-full primary-bg hover:opacity-90 text-white font-medium px-4 py-2 rounded-lg transition-all">
                    <i class="fas fa-filter mr-2"></i>Apply Filters
                </button>
            </div>
        </div>
    </div>

    <!-- Timesheets Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <!-- Bulk Actions Bar (Hidden by default) -->
        <div id="bulkActions" class="hidden items-center justify-between px-6 py-3 bg-blue-50 border-b border-blue-200">
            <div class="text-sm font-medium text-blue-900">
                <i class="fas fa-check-circle text-blue-600 mr-2"></i>
                <span id="selectedCount">0</span> item(s) selected
            </div>
            <div class="flex gap-2">
                <button onclick="editSelectedTimesheet()" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg transition-all shadow-sm">
                    <i class="fas fa-edit mr-2"></i>
                    Edit
                </button>
                <button onclick="openBulkSubmitModal()" class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-lg transition-all shadow-sm">
                    <i class="fas fa-paper-plane mr-2"></i>
                    Submit
                </button>
                <button onclick="openBulkDeleteModal()" class="inline-flex items-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-lg transition-all shadow-sm">
                    <i class="fas fa-trash mr-2"></i>
                    Delete
                </button>
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                            <input type="checkbox" id="selectAll" class="w-4 h-4 rounded border-gray-300">
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Time</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Duration</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Project/Ticket</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Activity</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Description</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody id="timesheetsTableBody" class="divide-y divide-gray-200">
                    <!-- Data will be loaded here -->
                </tbody>
            </table>
        </div>
        
        <div id="emptyState" class="hidden py-12 text-center">
            <i class="fas fa-clock text-gray-300 text-6xl mb-4"></i>
            <p class="text-gray-500 text-lg">No timesheets found</p>
            <p class="text-gray-400 text-sm mt-2">Start logging your hours by clicking "Log Hours" button</p>
        </div>
    </div>
</div>

<!-- Create/Edit Timesheet Modal -->
<div id="timesheetModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 overflow-y-auto">
    <div class="min-h-screen px-4 py-8 flex items-center justify-center">
        <div class="bg-white rounded-2xl shadow-2xl max-w-3xl w-full">
            <div class="primary-gradient text-white px-6 py-4 rounded-t-2xl flex items-center justify-between">
                <h3 class="text-xl font-bold flex items-center gap-2" id="timesheetModalTitle">
                    <i class="fas fa-clock"></i>
                    Log Working Hours
                </h3>
                <button onclick="closeTimesheetModal()" class="text-white hover:text-gray-200 transition-colors">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            
            <form id="timesheetForm" class="p-6 space-y-5">
                <input type="hidden" id="timesheetId">
                
                <!-- ✅ ENABLED: Radio button type selection -->
                <div class="border-b border-gray-200 pb-5">
                    <label class="block text-sm font-semibold text-gray-700 mb-3">
                        Timesheet Type <span class="text-red-600">*</span>
                    </label>
                    <div class="grid grid-cols-3 gap-3">
                        <label class="relative flex items-center justify-center p-4 border-2 border-gray-300 rounded-lg cursor-pointer hover:border-red-800 transition-all group">
                            <input type="radio" name="timesheetType" value="project" class="peer sr-only" onchange="handleTimesheetTypeChange()">
                            <div class="flex flex-col items-center gap-2 peer-checked:text-red-800 transition-colors">
                                <i class="fas fa-project-diagram text-2xl"></i>
                                <span class="text-sm font-semibold">Project</span>
                            </div>
                            <div class="absolute inset-0 border-2 border-red-800 rounded-lg opacity-0 peer-checked:opacity-100 transition-opacity"></div>
                        </label>
                        
                        <label class="relative flex items-center justify-center p-4 border-2 border-gray-300 rounded-lg cursor-pointer hover:border-red-800 transition-all group">
                            <input type="radio" name="timesheetType" value="support" class="peer sr-only" checked onchange="handleTimesheetTypeChange()">
                            <div class="flex flex-col items-center gap-2 peer-checked:text-red-800 transition-colors">
                                <i class="fas fa-headset text-2xl"></i>
                                <span class="text-sm font-semibold">Support</span>
                            </div>
                            <div class="absolute inset-0 border-2 border-red-800 rounded-lg opacity-0 peer-checked:opacity-100 transition-opacity"></div>
                        </label>
                        
                        <label class="relative flex items-center justify-center p-4 border-2 border-gray-300 rounded-lg cursor-pointer hover:border-red-800 transition-all group">
                            <input type="radio" name="timesheetType" value="office" class="peer sr-only" onchange="handleTimesheetTypeChange()">
                            <div class="flex flex-col items-center gap-2 peer-checked:text-red-800 transition-colors">
                                <i class="fas fa-building text-2xl"></i>
                                <span class="text-sm font-semibold">Office</span>
                            </div>
                            <div class="absolute inset-0 border-2 border-red-800 rounded-lg opacity-0 peer-checked:opacity-100 transition-opacity"></div>
                        </label>
                    </div>
                </div>
                
                <!-- Common Fields -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Date <span class="text-red-600">*</span>
                        </label>
                        <input type="date" id="timesheetDate" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-800 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Duration in hours
                        </label>
                        <input type="text" id="timesheetDuration" readonly class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-50 text-gray-600" placeholder="Auto calculated">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Start Time <span class="text-red-600">*</span>
                        </label>
                        <input type="time" id="timesheetStartTime" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-800 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            End Time <span class="text-red-600">*</span>
                        </label>
                        <input type="time" id="timesheetEndTime" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-800 focus:border-transparent">
                    </div>
                </div>

                <!-- ✅ Dynamic Fields Container (changes based on type) -->
                <div id="dynamicFields">
                    <!-- Fields will be injected here by JavaScript -->
                </div>

                <!-- Activity Description (common for all) -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Activity Description <span class="text-red-600">*</span>
                    </label>
                    <textarea id="timesheetDescription" required rows="3" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-800 focus:border-transparent resize-none" placeholder="Write description activity here"></textarea>
                </div>

                <!-- Notes (optional for all) -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Notes
                    </label>
                    <textarea id="timesheetNotes" rows="2" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-800 focus:border-transparent resize-none" placeholder="Write notes here"></textarea>
                </div>

                <!-- ✅ Billable Section (only for Project type) -->
                <div id="billableSection" class="hidden">
                    <div class="flex items-center gap-3 p-4 bg-green-50 border border-green-200 rounded-lg">
                        <input type="checkbox" id="timesheetBillable" checked class="w-5 h-5 text-green-600 border-gray-300 rounded focus:ring-green-500">
                        <label for="timesheetBillable" class="text-sm font-semibold text-gray-700">
                            <i class="fas fa-dollar-sign text-green-600 mr-1"></i>
                            Billable hours
                        </label>
                    </div>
                </div>

                <div class="flex gap-3 pt-4 border-t border-gray-200">
                    <button type="submit" class="flex-1 primary-bg hover:opacity-90 text-white font-semibold py-3 rounded-lg transition-all shadow-sm hover:shadow-md">
                        <i class="fas fa-save mr-2"></i>
                        Save Timesheet
                    </button>
                    <button type="button" onclick="closeTimesheetModal()" class="px-8 bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-3 rounded-lg transition-all">
                        Cancel
                    </button>
                </div>
            </form>
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

@push('scripts')
<script src="/js/calendar-timesheets.js"></script>
@endpush
@endsection
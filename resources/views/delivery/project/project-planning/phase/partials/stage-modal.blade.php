{{-- ✅ FULL RESPONSIVE STAGE MANAGEMENT MODAL --}}
{{-- ============================================================================ --}}
{{-- STAGE LIST MODAL - Main modal untuk menampilkan list stages --}}
{{-- ============================================================================ --}}
<div id="stageModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeStageModal()"></div>

        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle w-full sm:max-w-3xl md:max-w-4xl lg:max-w-5xl">
            <!-- Header - Mobile Optimized -->
            <div class="bg-gradient-to-r from-indigo-600 to-blue-600 px-4 sm:px-6 py-3 sm:py-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 flex items-center justify-center h-10 w-10 sm:h-12 sm:w-12 rounded-full bg-white bg-opacity-20">
                            <svg class="h-5 w-5 sm:h-6 sm:w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-base sm:text-lg font-semibold text-white">
                                Manage Activity Stages
                            </h3>
                            <p class="text-xs sm:text-sm text-indigo-100 mt-0.5" id="stageModalActivityName">Activity Name</p>
                        </div>
                    </div>
                    <button onclick="closeStageModal()" class="text-white hover:text-gray-200 transition">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="bg-white px-4 sm:px-6 py-4 max-h-[60vh] sm:max-h-[70vh] overflow-y-auto">
                <!-- Weight Validation Banner -->
                <div id="stageWeightValidation" class="mb-4 hidden">
                    <div class="rounded-lg p-3 sm:p-4 border-2" id="validationContent">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <svg class="w-4 h-4 sm:w-5 sm:h-5 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span class="text-sm sm:text-base font-medium" id="validationMessage">Validation message</span>
                            </div>
                            <span class="text-base sm:text-lg font-bold" id="validationWeight">0%</span>
                        </div>
                    </div>
                </div>

                <!-- Add Stage Button -->
                <div class="mb-4 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-2">
                    <h4 class="text-sm sm:text-base font-semibold text-gray-900">Stages</h4>
                    <button onclick="openAddStageForm()"
                            class="w-full sm:w-auto inline-flex justify-center items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-md shadow-sm transition">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Add Stage
                    </button>
                </div>

                <!-- Stages List -->
                <div id="stagesList" class="space-y-3">
                    <div class="text-center py-12 text-gray-500">
                        <svg class="mx-auto h-10 w-10 sm:h-12 sm:w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                        </svg>
                        <p class="mt-2 text-sm">No stages yet. Click "Add Stage" to create one.</p>
                    </div>
                </div>
            </div>

            <!-- Footer with Submit Button -->
            <div class="bg-gray-50 px-4 sm:px-6 py-3 sm:py-4 flex flex-col-reverse sm:flex-row sm:justify-end gap-2 sm:gap-3">
                <button onclick="closeStageModal()"
                        class="w-full sm:w-auto px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                    Close
                </button>
                <button id="stageSubmitBtn" onclick="saveAllStageChanges()"
                        class="w-full sm:w-auto inline-flex justify-center items-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-sm font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition disabled:opacity-50 disabled:cursor-not-allowed"
                        disabled>
                    💾 Submit All Changes
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ============================================================================ --}}
{{-- STAGE FORM MODAL - Modal untuk add/edit stage --}}
{{-- ============================================================================ --}}
<div id="stageFormModal" class="hidden fixed inset-0 z-[60] overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeStageFormModal()"></div>

        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle w-full sm:max-w-lg md:max-w-2xl">
            <form id="stageForm" onsubmit="addStageToList(event)">
                <!-- Header -->
                <div class="bg-gradient-to-r from-indigo-600 to-blue-600 px-4 sm:px-6 py-3 sm:py-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 flex items-center justify-center h-10 w-10 sm:h-12 sm:w-12 rounded-full bg-white bg-opacity-20">
                                <svg class="h-5 w-5 sm:h-6 sm:w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                                </svg>
                            </div>
                            <h3 class="ml-3 text-base sm:text-lg font-semibold text-white" id="stageFormTitle">
                                Add New Stage
                            </h3>
                        </div>
                        <button type="button" onclick="closeStageFormModal()" class="text-white hover:text-gray-200 transition">
                            <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Form Body -->
                <div class="bg-white px-4 sm:px-6 py-4 sm:py-5 max-h-[60vh] sm:max-h-[70vh] overflow-y-auto">
                    <div class="space-y-4">
                        <!-- Stage Name -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Stage Name
                                <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="stageName" required
                                   class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm sm:text-base"
                                   placeholder="e.g., Requirements Gathering, Development...">
                        </div>

                        <!-- Description -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Description
                                <span class="text-xs text-gray-500 ml-1">- Optional details</span>
                            </label>
                            <textarea id="stageDescription" rows="2"
                                      class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm sm:text-base"
                                      placeholder="Describe what happens in this stage..."></textarea>
                        </div>

                        <!-- Dates - Responsive Grid -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Start Date <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="stagePlannedStart" required
                                       placeholder="dd/mm/yyyy"
                                       pattern="\d{2}/\d{2}/\d{4}"
                                       class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm sm:text-base">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    End Date <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="stagePlannedEnd" required
                                       placeholder="dd/mm/yyyy"
                                       pattern="\d{2}/\d{2}/\d{4}"
                                       class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm sm:text-base">
                            </div>
                        </div>

                        <!-- Date Warning -->
                        <div id="stageDateWarning" class="hidden p-3 bg-blue-50 border border-blue-200 rounded-md">
                            <div class="flex">
                                <svg class="h-5 w-5 text-blue-400 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                </svg>
                                <p class="ml-3 text-xs sm:text-sm text-blue-700">
                                    📅 <strong>Auto-calculated dates:</strong> Start and end dates are automatically calculated from activities and cannot be edited manually.
                                </p>
                            </div>
                        </div>

                        <!-- Actual Dates (Edit mode only) -->
                        <div id="stageActualDates" class="hidden grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Actual Start
                                </label>
                                <input type="text" id="stageActualStart"
                                       placeholder="dd/mm/yyyy"
                                       pattern="\d{2}/\d{2}/\d{4}"
                                       class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm sm:text-base">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Actual End
                                </label>
                                <input type="text" id="stageActualEnd"
                                       placeholder="dd/mm/yyyy"
                                       pattern="\d{2}/\d{2}/\d{4}"
                                       class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm sm:text-base">
                            </div>
                        </div>

                        <!-- Weight, Progress, Status -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Weight (%)
                                    <span class="text-xs text-gray-500 block">Stage importance</span>
                                </label>
                                <input type="number" id="stageWeight" required
                                       min="0" max="100" step="0.1" value="10"
                                       class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm sm:text-base">
                            </div>
                            <div id="stageProgressField" class="hidden">
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Progress (%)
                                    <span class="text-xs text-gray-500 block">Completion</span>
                                </label>
                                <input type="number" id="stageProgress" required
                                       min="0" max="100" step="1" value="0"
                                       class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm sm:text-base">
                            </div>
                            <div id="stageStatusField" class="hidden">
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Status
                                    <span class="text-xs text-gray-500 block">Current state</span>
                                </label>
                                <select id="stageStatus" required
                                        class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm sm:text-base">
                                    <option value="not_started">Not Started</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="completed">Completed</option>
                                    <option value="delayed">Delayed</option>
                                </select>
                            </div>
                        </div>

                        <!-- Weight Info -->
                        <div class="bg-blue-50 border border-blue-200 rounded-md p-3">
                            <div class="flex items-start">
                                <svg class="h-5 w-5 text-blue-400 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                </svg>
                                <p class="ml-3 text-xs sm:text-sm text-blue-700">
                                    💡 <strong>Tip:</strong> Total weight of all stages should equal 100% for accurate progress calculation.
                                    Stage will be added to the list. Click "Submit All Changes" to save all changes.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="bg-gray-50 px-4 sm:px-6 py-3 sm:py-4 flex flex-col-reverse sm:flex-row sm:justify-end gap-2 sm:gap-3">
                    <button type="button" onclick="closeStageFormModal()"
                            class="w-full sm:w-auto inline-flex justify-center items-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition">
                        Cancel
                    </button>
                    <button type="submit"
                            class="w-full sm:w-auto inline-flex justify-center items-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-sm font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        <span id="stageFormBtnText">Add to List</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ============================================================================ --}}
{{-- STAGE DELETE CONFIRMATION MODAL --}}
{{-- ============================================================================ --}}
<div id="stageDeleteModal" class="hidden fixed inset-0 z-[60] overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeStageDeleteModal()"></div>

        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                        <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left flex-1">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">
                            Delete Stage?
                        </h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-500">
                                Are you sure you want to delete the stage "<strong id="stageDeleteName"></strong>"?
                            </p>
                            <p class="mt-2 text-sm text-yellow-600" id="stageDeleteWarning">
                                ⚠️ Stage will be marked for deletion. Click "Submit All Changes" to apply changes.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-4 py-3 sm:px-6 flex flex-col-reverse sm:flex-row sm:justify-end gap-2 sm:gap-3">
                <button type="button" onclick="closeStageDeleteModal()"
                        class="w-full sm:w-auto inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition">
                    Cancel
                </button>
                <button type="button" onclick="confirmStageDelete()"
                        class="w-full sm:w-auto inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-sm font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition">
                    <svg class="w-5 h-5 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                    Yes, Delete
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ============================================================================ --}}
{{-- STYLES --}}
{{-- ============================================================================ --}}
<style>
.stage-item {
    transition: all 0.2s ease;
}

.stage-item:hover {
    transform: translateX(4px);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.stage-color-indicator {
    width: 4px;
    height: 100%;
    position: absolute;
    left: 0;
    top: 0;
    border-radius: 4px 0 0 4px;
}

/* Deleted stage styling */
.stage-item.bg-red-50 {
    position: relative;
}

.stage-item.bg-red-50::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: repeating-linear-gradient(
        45deg,
        transparent,
        transparent 10px,
        rgba(239, 68, 68, 0.05) 10px,
        rgba(239, 68, 68, 0.05) 20px
    );
    pointer-events: none;
    border-radius: 0.5rem;
}

/* New stage styling */
.stage-item.ring-green-400 {
    animation: pulse-new 2s ease-in-out infinite;
}

@keyframes pulse-new {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.85; }
}

/* Modified stage styling */
.stage-item.ring-yellow-400 {
    animation: pulse-ring 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulse-ring {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.8; }
}

@media (max-width: 640px) {
    #stageModal .inline-block,
    #stageFormModal .inline-block {
        margin: 1rem;
        max-height: calc(100vh - 2rem);
    }

    input, select, textarea {
        font-size: 16px;
    }
}
</style>

{{-- ============================================================================ --}}
{{-- JAVASCRIPT --}}
{{-- ============================================================================ --}}
<script>

// ============================================================================
// STAGE MANAGEMENT - BATCH UPDATE SYSTEM (Like Phase Modal)
// ============================================================================

// Track all changes (will be saved when user clicks "Submit All Changes")
window.stageChanges = {
    toCreate: [],           // New stages to create
    toUpdate: {},           // { stageId: { ...updated fields } }
    toDelete: new Set(),    // Stage IDs to delete
    originalStages: []      // Original stages data for comparison
};

// ============================================================================
// DATE FORMAT HELPERS (dd/mm/yyyy - Indonesian format)
// ============================================================================

/**
 * Convert ISO date (yyyy-mm-dd) to Indonesian format (dd/mm/yyyy)
 */
function stageIsoToIndonesian(isoDate) {
    if (!isoDate) return '';
    const parts = isoDate.split(' ')[0].split('-');
    if (parts.length !== 3) return isoDate;
    return `${parts[2]}/${parts[1]}/${parts[0]}`;
}

/**
 * Convert Indonesian date (dd/mm/yyyy) to ISO format (yyyy-mm-dd)
 */
function stageIndonesianToIso(indoDate) {
    if (!indoDate) return '';
    const parts = indoDate.split('/');
    if (parts.length !== 3) return indoDate;
    return `${parts[2]}-${parts[1]}-${parts[0]}`;
}

/**
 * Get today's date in Indonesian format
 */
function stageTodayIndonesian() {
    const today = new Date();
    const day = String(today.getDate()).padStart(2, '0');
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const year = today.getFullYear();
    return `${day}/${month}/${year}`;
}

/**
 * Get date N days from now in Indonesian format
 */
function stageDateFromNowIndonesian(days) {
    const date = new Date();
    date.setDate(date.getDate() + days);
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    return `${day}/${month}/${year}`;
}

/**
 * Parse Indonesian date to Date object
 */
function stageParseIndonesian(indoDate) {
    if (!indoDate) return null;
    const parts = indoDate.split('/');
    if (parts.length !== 3) return null;
    return new Date(parseInt(parts[2]), parseInt(parts[1]) - 1, parseInt(parts[0]));
}

/**
 * Check if ID is a new temporary ID
 */
function isNewStageId(id) {
    return typeof id === 'string' && id.startsWith('new_');
}

/**
 * Convert ID to string for comparison
 */
function normalizeStageId(id) {
    return String(id);
}

// Global variables
let currentActivityId = null;
let currentGroupId = null;
let currentStageId = null;
let currentStageName = null;
let stageFormMode = 'create';

/**
 * ✅ Open Stage Modal
 */
window.openStageModal = function(activityId, activityName, groupId = null) {
    if (!activityId || activityId === 'null' || activityId === 'undefined') {
        console.error('❌ Invalid activityId:', activityId);
        if (typeof showNotification === 'function') {
            showNotification('Error: Invalid activity ID', 'error');
        }
        return;
    }

    console.log('📂 Opening stage modal:', { activityId, activityName, groupId });

    currentActivityId = activityId;
    currentGroupId = groupId;

    // Reset changes tracking
    window.stageChanges = {
        toCreate: [],
        toUpdate: {},
        toDelete: new Set(),
        originalStages: []
    };

    document.getElementById('stageModalActivityName').textContent = activityName;
    document.getElementById('stageModal').classList.remove('hidden');

    loadStages(activityId);
};

/**
 * ✅ Close Stage Modal with confirmation if there are unsaved changes
 */
window.closeStageModal = function() {
    console.log('❌ Closing stage modal');

    // Check if there are unsaved changes
    const hasChanges = window.stageChanges.toCreate.length > 0 ||
                      window.stageChanges.toDelete.size > 0 ||
                      Object.keys(window.stageChanges.toUpdate).length > 0;

    if (hasChanges) {
        if (!confirm('You have unsaved changes. Are you sure you want to close?')) {
            return;
        }
    }

    document.getElementById('stageModal').classList.add('hidden');
    currentActivityId = null;
    currentGroupId = null;

    // Reset changes
    window.stageChanges = {
        toCreate: [],
        toUpdate: {},
        toDelete: new Set(),
        originalStages: []
    };
};

/**
 * ✅ Open Add Stage Form
 */
window.openAddStageForm = function() {
    stageFormMode = 'create';
    currentStageId = null;

    const form = document.getElementById('stageForm');
    form.reset();

    document.getElementById('stageFormTitle').textContent = 'Add New Stage';
    document.getElementById('stageFormBtnText').textContent = 'Add to List';

    document.getElementById('stageActualDates').classList.add('hidden');
    document.getElementById('stageProgressField').classList.add('hidden');
    document.getElementById('stageStatusField').classList.add('hidden');
    document.getElementById('stageDateWarning').classList.add('hidden');

    const startInput = document.getElementById('stagePlannedStart');
    const endInput = document.getElementById('stagePlannedEnd');
    startInput.disabled = false;
    endInput.disabled = false;
    startInput.classList.remove('bg-gray-100', 'cursor-not-allowed');
    endInput.classList.remove('bg-gray-100', 'cursor-not-allowed');

    startInput.value = stageTodayIndonesian();
    endInput.value = stageDateFromNowIndonesian(7);

    document.getElementById('stageFormModal').classList.remove('hidden');
    document.getElementById('stageName').focus();
};

/**
 * ✅ Edit Stage - Load data into form
 */
window.editStage = function(stageId, stageName = null, groupId = null) {
    console.log('✏️ Editing stage:', { stageId, stageName, groupId });

    stageFormMode = 'edit';
    currentStageId = stageId;
    currentStageName = stageName;

    if (groupId) {
        currentGroupId = groupId;
    }

    const modal = document.getElementById('stageFormModal');
    modal.classList.remove('hidden');

    document.getElementById('stageFormTitle').textContent = 'Edit Stage';
    document.getElementById('stageFormBtnText').textContent = 'Update in List';

    const form = document.getElementById('stageForm');
    form.classList.add('opacity-50', 'pointer-events-none');

    // Check if it's a new stage (local only)
    if (isNewStageId(stageId)) {
        const newStage = window.stageChanges.toCreate.find(s => s.tempId === stageId);
        if (newStage) {
            populateStageForm(newStage, true);
            form.classList.remove('opacity-50', 'pointer-events-none');
        }
        return;
    }

    // Load existing stage from server
    axios.get(`/planning/${window.projectId}/stages/${stageId}`)
        .then(response => {
            const stage = response.data.data || response.data;
            console.log('✅ Stage data loaded:', stage);
            populateStageForm(stage, false);
            form.classList.remove('opacity-50', 'pointer-events-none');
        })
        .catch(error => {
            console.error('❌ Error loading stage:', error);
            modal.classList.add('hidden');
            showNotification(
                'Failed to load stage: ' + (error.response?.data?.message || error.message),
                'error'
            );
        });
};

/**
 * Populate stage form with data
 */
function populateStageForm(stage, isNew = false) {
    document.getElementById('stageName').value = stage.name || '';
    document.getElementById('stageDescription').value = stage.description || '';

    const startInput = document.getElementById('stagePlannedStart');
    const endInput = document.getElementById('stagePlannedEnd');
    const dateWarning = document.getElementById('stageDateWarning');

    // Convert dates to Indonesian format
    if (isNew) {
        startInput.value = stage.planned_start_date || '';
        endInput.value = stage.planned_end_date || '';
    } else {
        startInput.value = stageIsoToIndonesian(stage.planned_start_date);
        endInput.value = stageIsoToIndonesian(stage.planned_end_date);
    }

    document.getElementById('stageWeight').value = parseFloat(stage.weight) || 0;

    if (!isNew) {
        document.getElementById('stageActualDates').classList.remove('hidden');
        document.getElementById('stageProgressField').classList.remove('hidden');
        document.getElementById('stageStatusField').classList.remove('hidden');

        document.getElementById('stageActualStart').value = stageIsoToIndonesian(stage.actual_start_date);
        document.getElementById('stageActualEnd').value = stageIsoToIndonesian(stage.actual_end_date);
        document.getElementById('stageProgress').value = parseFloat(stage.progress) || 0;
        document.getElementById('stageStatus').value = stage.status || 'not_started';

        const hasActivities = stage.activities_count && stage.activities_count > 0;

        if (hasActivities) {
            startInput.disabled = true;
            endInput.disabled = true;
            startInput.classList.add('bg-gray-100', 'cursor-not-allowed');
            endInput.classList.add('bg-gray-100', 'cursor-not-allowed');
            dateWarning.classList.remove('hidden');
        } else {
            startInput.disabled = false;
            endInput.disabled = false;
            startInput.classList.remove('bg-gray-100', 'cursor-not-allowed');
            endInput.classList.remove('bg-gray-100', 'cursor-not-allowed');
            dateWarning.classList.add('hidden');
        }
    } else {
        document.getElementById('stageActualDates').classList.add('hidden');
        document.getElementById('stageProgressField').classList.add('hidden');
        document.getElementById('stageStatusField').classList.add('hidden');
        startInput.disabled = false;
        endInput.disabled = false;
        startInput.classList.remove('bg-gray-100', 'cursor-not-allowed');
        endInput.classList.remove('bg-gray-100', 'cursor-not-allowed');
        dateWarning.classList.add('hidden');
    }

    document.getElementById('stageName').focus();
}

/**
 * ✅ Add/Update Stage to Local List (not saved to server yet)
 */
window.addStageToList = function(event) {
    event.preventDefault();
    console.log('📝 Adding/updating stage to list...', stageFormMode);

    // Get form data
    const startDateIndo = document.getElementById('stagePlannedStart').value;
    const endDateIndo = document.getElementById('stagePlannedEnd').value;

    const stageData = {
        name: document.getElementById('stageName').value,
        description: document.getElementById('stageDescription').value,
        planned_start_date: startDateIndo,
        planned_end_date: endDateIndo,
        weight: parseFloat(document.getElementById('stageWeight').value)
    };

    // Validate dates
    const startDate = stageParseIndonesian(startDateIndo);
    const endDate = stageParseIndonesian(endDateIndo);

    if (endDate < startDate) {
        showNotification('End date must be after start date', 'error');
        return;
    }

    if (stageFormMode === 'create') {
        // Generate temporary ID
        const tempId = 'new_' + Date.now();
        stageData.tempId = tempId;
        stageData.planning_id = currentActivityId;
        stageData.status = 'not_started';
        stageData.progress = 0;

        // Add to pending creates
        window.stageChanges.toCreate.push(stageData);

        console.log('➕ New stage added to list:', stageData);
        showNotification('Stage added to list. Click "Submit All Changes" to save.', 'success');
    } else {
        // Edit mode
        if (isNewStageId(currentStageId)) {
            // Update local new stage
            const index = window.stageChanges.toCreate.findIndex(s => s.tempId === currentStageId);
            if (index !== -1) {
                window.stageChanges.toCreate[index] = {
                    ...window.stageChanges.toCreate[index],
                    ...stageData
                };
            }
        } else {
            // Add edit data
            const actualStartIndo = document.getElementById('stageActualStart').value;
            const actualEndIndo = document.getElementById('stageActualEnd').value;

            stageData.actual_start_date = actualStartIndo ? stageIndonesianToIso(actualStartIndo) : null;
            stageData.actual_end_date = actualEndIndo ? stageIndonesianToIso(actualEndIndo) : null;
            stageData.progress = parseFloat(document.getElementById('stageProgress').value);
            stageData.status = document.getElementById('stageStatus').value;
            stageData.planned_start_date = stageIndonesianToIso(startDateIndo);
            stageData.planned_end_date = stageIndonesianToIso(endDateIndo);

            // Track update
            window.stageChanges.toUpdate[currentStageId] = stageData;
        }

        console.log('✏️ Stage updated in list:', currentStageId);
        showNotification('Stage updated in list. Click "Submit All Changes" to save.', 'success');
    }

    closeStageFormModal();
    renderStagesFromLocal();
};

/**
 * ✅ Delete Stage - Mark for deletion
 */
window.deleteStage = function(stageId, stageName = 'this stage') {
    if (!stageId || stageId === 'null' || stageId === 'undefined') {
        console.error('❌ Invalid stageId:', stageId);
        showNotification('Error: Invalid stage ID', 'error');
        return;
    }

    console.log('🗑️ Delete stage requested:', { stageId, stageName });

    currentStageId = stageId;
    currentStageName = stageName;

    // Update warning message based on stage type
    const warningEl = document.getElementById('stageDeleteWarning');
    if (isNewStageId(stageId)) {
        warningEl.textContent = '⚠️ This new stage will be removed from the list.';
        warningEl.className = 'mt-2 text-sm text-red-600';
    } else {
        warningEl.textContent = '⚠️ Stage will be marked for deletion. Click "Submit All Changes" to apply changes.';
        warningEl.className = 'mt-2 text-sm text-yellow-600';
    }

    document.getElementById('stageDeleteName').textContent = stageName;
    document.getElementById('stageDeleteModal').classList.remove('hidden');
};

/**
 * ✅ Confirm Stage Delete
 */
window.confirmStageDelete = function() {
    if (!currentStageId) return;

    console.log('🗑️ Confirming stage deletion:', currentStageId);

    const stageId = normalizeStageId(currentStageId);

    if (isNewStageId(stageId)) {
        // Remove from toCreate list
        window.stageChanges.toCreate = window.stageChanges.toCreate.filter(s => s.tempId !== stageId);
        showNotification('New stage removed from list', 'success');
    } else {
        // Mark existing stage for deletion
        window.stageChanges.toDelete.add(stageId);
        // Remove from updates if was being edited
        delete window.stageChanges.toUpdate[stageId];
        showNotification('Stage marked for deletion. Click "Submit All Changes" to apply.', 'warning');
    }

    closeStageDeleteModal();
    renderStagesFromLocal();
};

window.closeStageFormModal = function() {
    console.log('❌ Closing stage form modal');
    document.getElementById('stageFormModal').classList.add('hidden');
    stageFormMode = 'create';
    currentStageId = null;
    currentStageName = null;
};

window.closeStageDeleteModal = function() {
    console.log('❌ Closing stage delete modal');
    document.getElementById('stageDeleteModal').classList.add('hidden');
    currentStageId = null;
    currentStageName = null;
};

/**
 * ✅ Load Stages from Server
 */
function loadStages(activityId) {
    console.log('📥 Loading stages for activity:', activityId);

    const stagesList = document.getElementById('stagesList');
    stagesList.innerHTML = `
        <div class="text-center py-8">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto"></div>
            <p class="mt-2 text-sm text-gray-500">Loading stages...</p>
        </div>
    `;

    axios.get(`/planning/${window.projectId}/activities/${activityId}`)
        .then(response => {
            console.log('✅ Stages loaded:', response.data);

            if (response.data.success) {
                // Store original stages for reference
                window.stageChanges.originalStages = response.data.data || [];
                renderStagesFromLocal();
            } else {
                throw new Error(response.data.message || 'Failed to load stages');
            }
        })
        .catch(error => {
            console.error('❌ Error loading stages:', error);
            stagesList.innerHTML = `
                <div class="text-center py-8 text-red-600">
                    <svg class="mx-auto h-12 w-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <p class="mt-2 text-sm">${error.response?.data?.message || error.message}</p>
                </div>
            `;
        });
}

/**
 * ✅ Render Stages from Local State (original + changes)
 */
function renderStagesFromLocal() {
    const stagesList = document.getElementById('stagesList');

    // Combine original stages with changes
    let allStages = [];

    // Add original stages (excluding deleted ones)
    window.stageChanges.originalStages.forEach(stage => {
        const stageId = normalizeStageId(stage.id);

        if (!window.stageChanges.toDelete.has(stageId)) {
            // Check if updated
            if (window.stageChanges.toUpdate[stageId]) {
                allStages.push({
                    ...stage,
                    ...window.stageChanges.toUpdate[stageId],
                    isModified: true
                });
            } else {
                allStages.push(stage);
            }
        } else {
            // Mark as deleted for visual
            allStages.push({
                ...stage,
                isDeleted: true
            });
        }
    });

    // Add new stages
    window.stageChanges.toCreate.forEach(stage => {
        allStages.push({
            ...stage,
            id: stage.tempId,
            isNew: true
        });
    });

    // Calculate total weight and validation
    const validation = calculateStageWeightValidation(allStages);
    updateValidationBanner(validation);
    updateSubmitButton(validation.is_valid);

    if (allStages.length === 0) {
        stagesList.innerHTML = `
            <div class="text-center py-12 text-gray-500">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                </svg>
                <p class="mt-2 text-sm">No stages yet. Click "Add Stage" to create one.</p>
            </div>
        `;
        return;
    }

    let html = '';
    allStages.forEach(stage => {
        html += createStageItemHtml(stage);
    });

    stagesList.innerHTML = html;
}

/**
 * ✅ Calculate weight validation
 */
function calculateStageWeightValidation(stages) {
    let totalWeight = 0;

    stages.forEach(stage => {
        if (!stage.isDeleted) {
            totalWeight += parseFloat(stage.weight) || 0;
        }
    });

    const isValid = Math.abs(totalWeight - 100) < 0.1;

    return {
        total_weight: totalWeight,
        is_valid: isValid,
        message: isValid ? 'Total weight = 100% ✓' : `Total weight must be 100% (currently ${totalWeight.toFixed(1)}%)`
    };
}

/**
 * ✅ Update Submit button state
 */
function updateSubmitButton(isValid) {
    const submitBtn = document.getElementById('stageSubmitBtn');
    if (submitBtn) {
        // Check if there are any changes
        const hasChanges = window.stageChanges.toCreate.length > 0 ||
                          window.stageChanges.toDelete.size > 0 ||
                          Object.keys(window.stageChanges.toUpdate).length > 0;

        if (isValid && hasChanges) {
            submitBtn.disabled = false;
            submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
        } else {
            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
        }
    }
}

/**
 * ✅ Create Stage Item HTML
 */
function createStageItemHtml(stage) {
    const statusBadge = getStatusBadge(stage.status);
    const progressColor = stage.progress >= 100 ? 'bg-green-600' :
                         stage.progress > 0 ? 'bg-indigo-600' : 'bg-gray-300';

    const escapedName = escapeHtml(stage.name || 'Unnamed Stage');
    const stageId = stage.id || stage.tempId;

    // Determine styling based on state
    let containerClass = 'stage-item relative bg-white border-2 rounded-lg p-3 sm:p-4 transition-all';
    let badges = '';
    let isDisabled = false;

    if (stage.isDeleted) {
        containerClass += ' bg-red-50 border-red-300 opacity-60';
        badges += '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 ml-2">To Delete</span>';
        isDisabled = true;
    } else if (stage.isNew) {
        containerClass += ' border-green-300 ring-2 ring-green-400';
        badges += '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 ml-2">New</span>';
    } else if (stage.isModified) {
        containerClass += ' border-yellow-300 ring-2 ring-yellow-400';
        badges += '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800 ml-2">Modified</span>';
    } else {
        containerClass += ' border-gray-200 hover:border-indigo-300';
    }

    // Format dates for display
    let displayStartDate = stage.planned_start_date;
    let displayEndDate = stage.planned_end_date;

    if (stage.isNew) {
        // New stages have Indonesian format dates
        displayStartDate = formatDateFromIndo(stage.planned_start_date);
        displayEndDate = formatDateFromIndo(stage.planned_end_date);
    } else {
        displayStartDate = formatDate(stage.planned_start_date);
        displayEndDate = formatDate(stage.planned_end_date);
    }

    return `
        <div class="${containerClass}" data-stage-id="${stageId}">
            <div class="stage-color-indicator" style="background-color: ${stage.color || '#6366F1'};"></div>

            <div class="pl-3">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between mb-3 gap-2">
                    <div class="flex-1">
                        <div class="flex items-center flex-wrap gap-2 mb-1">
                            <h4 class="text-sm font-semibold text-gray-900 ${stage.isDeleted ? 'line-through' : ''}">${escapedName}</h4>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${statusBadge.class}">
                                ${statusBadge.text}
                            </span>
                            ${badges}
                        </div>
                        ${stage.description ? `<p class="text-xs text-gray-500 mb-2">${escapeHtml(stage.description)}</p>` : ''}
                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            <span class="inline-flex items-center px-2 py-0.5 rounded bg-purple-100 text-purple-800 font-medium">
                                Weight: ${parseFloat(stage.weight).toFixed(1)}%
                            </span>
                            <span class="text-gray-500">
                                📅 ${displayStartDate} - ${displayEndDate}
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center space-x-1">
                        ${!isDisabled ? `
                        <button onclick="editStage('${stageId}', '${escapedName.replace(/'/g, "\\'")}', ${currentGroupId || 'null'})"
                                class="p-1.5 text-indigo-600 hover:bg-indigo-50 rounded transition"
                                title="Edit Stage">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                        </button>
                        <button onclick="deleteStage('${stageId}', '${escapedName.replace(/'/g, "\\'")}')"
                                class="p-1.5 text-red-600 hover:bg-red-50 rounded transition"
                                title="Delete Stage">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                        </button>
                        ` : `
                        <button onclick="undoStageDelete('${stageId}')"
                                class="p-1.5 text-green-600 hover:bg-green-50 rounded transition"
                                title="Undo Delete">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path>
                            </svg>
                        </button>
                        `}
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs font-medium text-gray-700">Progress</span>
                        <span class="text-xs font-bold text-gray-900">${Math.round(stage.progress || 0)}%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="${progressColor} h-2 rounded-full transition-all" style="width: ${stage.progress || 0}%"></div>
                    </div>
                </div>
            </div>
        </div>
    `;
}

/**
 * ✅ Undo Stage Delete
 */
window.undoStageDelete = function(stageId) {
    console.log('↩️ Undoing stage delete:', stageId);
    window.stageChanges.toDelete.delete(normalizeStageId(stageId));
    showNotification('Stage deletion cancelled', 'success');
    renderStagesFromLocal();
};

function getStatusBadge(status) {
    const badges = {
        'not_started': { class: 'bg-gray-100 text-gray-800', text: 'Not Started' },
        'in_progress': { class: 'bg-blue-100 text-blue-800', text: 'In Progress' },
        'completed': { class: 'bg-green-100 text-green-800', text: 'Completed' },
        'delayed': { class: 'bg-red-100 text-red-800', text: 'Delayed' }
    };
    return badges[status] || badges['not_started'];
}

function updateValidationBanner(validation) {
    const banner = document.getElementById('stageWeightValidation');
    const content = document.getElementById('validationContent');
    const message = document.getElementById('validationMessage');
    const weight = document.getElementById('validationWeight');

    banner.classList.remove('hidden');
    weight.textContent = validation.total_weight.toFixed(1) + '%';
    message.textContent = validation.message;

    if (validation.is_valid) {
        content.className = 'rounded-lg p-4 border-2 border-green-500 bg-green-50 text-green-800';
    } else {
        content.className = 'rounded-lg p-4 border-2 border-red-500 bg-red-50 text-red-800';
    }
}

/**
 * ✅ SAVE ALL STAGE CHANGES - Batch Update
 */
window.saveAllStageChanges = async function() {
    console.log('💾 Saving all stage changes...', window.stageChanges);

    // Validate weight before saving
    let allStages = [];
    window.stageChanges.originalStages.forEach(stage => {
        if (!window.stageChanges.toDelete.has(normalizeStageId(stage.id))) {
            if (window.stageChanges.toUpdate[stage.id]) {
                allStages.push({ ...stage, ...window.stageChanges.toUpdate[stage.id] });
            } else {
                allStages.push(stage);
            }
        }
    });
    window.stageChanges.toCreate.forEach(stage => allStages.push(stage));

    const validation = calculateStageWeightValidation(allStages);

    if (!validation.is_valid) {
        showNotification('Total weight must be 100% before saving', 'error');
        return;
    }

    // Show loading
    const submitBtn = document.getElementById('stageSubmitBtn');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<svg class="animate-spin h-5 w-5 inline-block mr-2" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Saving...';
    submitBtn.disabled = true;

    try {
        // 1. Delete stages first (to free up weight)
        for (const stageId of window.stageChanges.toDelete) {
            if (isNewStageId(stageId)) continue;

            console.log('🗑️ Deleting stage:', stageId);
            await axios.delete(`/planning/${window.projectId}/stages/${stageId}`);
        }

        // 2. Update existing stages (to adjust weights before creating new ones)
        for (const [stageId, updates] of Object.entries(window.stageChanges.toUpdate)) {
            if (isNewStageId(stageId)) continue;

            console.log('✏️ Updating stage:', stageId);
            await axios.put(`/planning/${window.projectId}/stages/${stageId}`, updates);
        }

        // 3. Create new stages last (after weights have been adjusted)
        for (const newStage of window.stageChanges.toCreate) {
            console.log('➕ Creating new stage:', newStage.name);

            const createData = {
                name: newStage.name,
                description: newStage.description,
                planned_start_date: stageIndonesianToIso(newStage.planned_start_date),
                planned_end_date: stageIndonesianToIso(newStage.planned_end_date),
                weight: newStage.weight,
                planning_id: currentActivityId
            };

            await axios.post(`/planning/${window.projectId}/stages`, createData);
        }

        showNotification('✅ All stage changes saved successfully!', 'success');

        // Reset changes
        window.stageChanges = {
            toCreate: [],
            toUpdate: {},
            toDelete: new Set(),
            originalStages: []
        };

        // Close modal and reload page
        setTimeout(() => {
            document.getElementById('stageModal').classList.add('hidden');
            location.reload();
        }, 1000);

    } catch (error) {
        console.error('❌ Error saving stage changes:', error);
        showNotification(
            '❌ Failed to save: ' + (error.response?.data?.message || error.message),
            'error'
        );

        // Restore button
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }
};

function formatDate(dateString) {
    if (!dateString) return '-';
    try {
        const date = new Date(dateString);
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return `${date.getDate()} ${months[date.getMonth()]}`;
    } catch (e) {
        return '-';
    }
}

function formatDateFromIndo(indoDate) {
    if (!indoDate) return '-';
    try {
        const parts = indoDate.split('/');
        if (parts.length !== 3) return indoDate;
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return `${parseInt(parts[0])} ${months[parseInt(parts[1]) - 1]}`;
    } catch (e) {
        return indoDate;
    }
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

console.log('✅ Stage management script loaded (Batch Mode)');
</script>

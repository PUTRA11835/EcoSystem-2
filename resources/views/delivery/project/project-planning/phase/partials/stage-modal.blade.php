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

            <!-- Footer -->
            <div class="bg-gray-50 px-4 sm:px-6 py-3 sm:py-4 flex justify-end">
                <button onclick="closeStageModal()" 
                        class="w-full sm:w-auto px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                    Close
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
            <form id="stageForm" onsubmit="saveStage(event)">
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
                                <input type="date" id="stagePlannedStart" required 
                                       class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm sm:text-base">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    End Date <span class="text-red-500">*</span>
                                </label>
                                <input type="date" id="stagePlannedEnd" required 
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
                                <input type="date" id="stageActualStart" 
                                       class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm sm:text-base">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Actual End
                                </label>
                                <input type="date" id="stageActualEnd" 
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
                        💾 Save Stage
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
                            <p class="mt-2 text-sm text-red-600">
                                ⚠️ This action cannot be undone.
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

// Global variables
let currentActivityId = null;
let currentGroupId = null;
let currentStageId = null;
let currentStageName = null;
let stageFormMode = 'create';

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
    
    document.getElementById('stageModalActivityName').textContent = activityName;
    document.getElementById('stageModal').classList.remove('hidden');
    
    loadStages(activityId);
};

window.closeStageModal = function() {
    console.log('❌ Closing stage modal');
    document.getElementById('stageModal').classList.add('hidden');
    currentActivityId = null;
    currentGroupId = null;
};

window.openAddStageForm = function() {
    stageFormMode = 'create';
    currentStageId = null;
    
    const form = document.getElementById('stageForm');
    form.reset();
    
    // ✅ RESET SUBMIT BUTTON STATE
    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = `
            <svg class="w-4 h-4 sm:w-5 sm:h-5 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            💾 Save Stage
        `;
    }
    
    document.getElementById('stageFormTitle').textContent = 'Add New Stage';
    
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
    
    const today = new Date().toISOString().split('T')[0];
    startInput.value = today;
    
    const nextWeek = new Date();
    nextWeek.setDate(nextWeek.getDate() + 7);
    endInput.value = nextWeek.toISOString().split('T')[0];
    
    document.getElementById('stageFormModal').classList.remove('hidden');
    document.getElementById('stageName').focus();
};

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
    
    const form = document.getElementById('stageForm');
    form.classList.add('opacity-50', 'pointer-events-none');

    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = `
            <svg class="w-4 h-4 sm:w-5 sm:h-5 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            💾 Save Stage
        `;
    }
    
    axios.get(`/planning/${window.projectId}/stages/${stageId}`)
        .then(response => {
            const stage = response.data.data || response.data;
            console.log('✅ Stage data loaded:', stage);
            
            document.getElementById('stageFormTitle').textContent = 'Edit Stage';
            
            document.getElementById('stageName').value = stage.name || '';
            document.getElementById('stageDescription').value = stage.description || '';
            
            const formatDate = (dateValue) => {
                if (!dateValue) return '';
                return dateValue.split(' ')[0];
            };
            
            const startInput = document.getElementById('stagePlannedStart');
            const endInput = document.getElementById('stagePlannedEnd');
            const dateWarning = document.getElementById('stageDateWarning');
            
            startInput.value = formatDate(stage.planned_start_date);
            endInput.value = formatDate(stage.planned_end_date);
            
            document.getElementById('stageWeight').value = parseFloat(stage.weight) || 0;
            
            document.getElementById('stageActualDates').classList.remove('hidden');
            document.getElementById('stageProgressField').classList.remove('hidden');
            document.getElementById('stageStatusField').classList.remove('hidden');
            
            document.getElementById('stageActualStart').value = formatDate(stage.actual_start_date);
            document.getElementById('stageActualEnd').value = formatDate(stage.actual_end_date);
            document.getElementById('stageProgress').value = parseFloat(stage.progress) || 0;
            document.getElementById('stageStatus').value = stage.status || 'not_started';
            
            const hasActivities = stage.activities_count && stage.activities_count > 0;
            
            if (hasActivities) {
                startInput.disabled = true;
                endInput.disabled = true;
                startInput.classList.add('bg-gray-100', 'cursor-not-allowed');
                endInput.classList.add('bg-gray-100', 'cursor-not-allowed');
                dateWarning.classList.remove('hidden');
                console.log('📅 Dates disabled - stage has activities');
            } else {
                startInput.disabled = false;
                endInput.disabled = false;
                startInput.classList.remove('bg-gray-100', 'cursor-not-allowed');
                endInput.classList.remove('bg-gray-100', 'cursor-not-allowed');
                dateWarning.classList.add('hidden');
            }
            
            form.classList.remove('opacity-50', 'pointer-events-none');
            document.getElementById('stageName').focus();
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

window.deleteStage = function(stageId, stageName = 'this stage') {
    if (!stageId || stageId === 'null' || stageId === 'undefined') {
        console.error('❌ Invalid stageId:', stageId);
        showNotification('Error: Invalid stage ID', 'error');
        return;
    }

    console.log('🗑️ Delete stage requested:', { stageId, stageName });
    
    currentStageId = stageId;
    currentStageName = stageName;
    
    document.getElementById('stageDeleteName').textContent = stageName;
    document.getElementById('stageDeleteModal').classList.remove('hidden');
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
                renderStages(response.data.data, response.data.validation);
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

function renderStages(stages, validation) {
    const stagesList = document.getElementById('stagesList');
    updateValidationBanner(validation);
    
    if (!stages || stages.length === 0) {
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
    stages.forEach(stage => {
        html += createStageItemHtml(stage);
    });
    
    stagesList.innerHTML = html;
}

function createStageItemHtml(stage) {
    const statusBadge = getStatusBadge(stage.status);
    const progressColor = stage.progress >= 100 ? 'bg-green-600' : 
                         stage.progress > 0 ? 'bg-indigo-600' : 'bg-gray-300';
    
    const escapedName = escapeHtml(stage.name || 'Unnamed Stage');
    
    return `
        <div class="stage-item relative bg-white border-2 border-gray-200 rounded-lg p-3 sm:p-4 hover:border-indigo-300 transition-all">
            <div class="stage-color-indicator" style="background-color: ${stage.color || '#6366F1'};"></div>
            
            <div class="pl-3">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between mb-3 gap-2">
                    <div class="flex-1">
                        <div class="flex items-center flex-wrap gap-2 mb-1">
                            <h4 class="text-sm font-semibold text-gray-900">${escapedName}</h4>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${statusBadge.class}">
                                ${statusBadge.text}
                            </span>
                        </div>
                        ${stage.description ? `<p class="text-xs text-gray-500 mb-2">${escapeHtml(stage.description)}</p>` : ''}
                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            <span class="inline-flex items-center px-2 py-0.5 rounded bg-purple-100 text-purple-800 font-medium">
                                Weight: ${parseFloat(stage.weight).toFixed(1)}%
                            </span>
                            <span class="text-gray-500">
                                📅 ${formatDate(stage.planned_start_date)} - ${formatDate(stage.planned_end_date)}
                            </span>
                        </div>
                    </div>
                    
                    <div class="flex items-center space-x-1">
                        <button onclick="editStage(${stage.id}, '${escapedName}', ${currentGroupId || 'null'})" 
                                class="p-1.5 text-indigo-600 hover:bg-indigo-50 rounded transition"
                                title="Edit Stage">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                        </button>
                        <button onclick="deleteStage(${stage.id}, '${escapedName}')" 
                                class="p-1.5 text-red-600 hover:bg-red-50 rounded transition"
                                title="Delete Stage">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs font-medium text-gray-700">Progress</span>
                        <span class="text-xs font-bold text-gray-900">${Math.round(stage.progress)}%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="${progressColor} h-2 rounded-full transition-all" style="width: ${stage.progress}%"></div>
                    </div>
                </div>
            </div>
        </div>
    `;
}

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
    
    if (!validation) {
        banner.classList.add('hidden');
        return;
    }
    
    banner.classList.remove('hidden');
    weight.textContent = validation.total_weight.toFixed(1) + '%';
    message.textContent = validation.message;
    
    if (validation.is_valid) {
        content.className = 'rounded-lg p-4 border-2 border-green-500 bg-green-50 text-green-800';
    } else {
        content.className = 'rounded-lg p-4 border-2 border-red-500 bg-red-50 text-red-800';
    }
}

window.saveStage = function(event) {
    event.preventDefault();
    console.log('💾 Saving stage...', stageFormMode);
    
    const formData = {
        name: document.getElementById('stageName').value,
        description: document.getElementById('stageDescription').value,
        planned_start_date: document.getElementById('stagePlannedStart').value,
        planned_end_date: document.getElementById('stagePlannedEnd').value,
        weight: parseFloat(document.getElementById('stageWeight').value)
    };

    // Add planning_id when creating a new stage
    if (stageFormMode === 'create' && currentActivityId) {
        formData.planning_id = currentActivityId;
    }

    if (new Date(formData.planned_end_date) < new Date(formData.planned_start_date)) {
        showNotification('End date must be after start date', 'error');
        return;
    }

    if (stageFormMode === 'edit') {
        // ✅ CRITICAL FIX: Get actual dates from inputs
        const actualStartInput = document.getElementById('stageActualStart');
        const actualEndInput = document.getElementById('stageActualEnd');
        
        formData.actual_start_date = actualStartInput.value || null;
        formData.actual_end_date = actualEndInput.value || null;
        formData.progress = parseFloat(document.getElementById('stageProgress').value);
        formData.status = document.getElementById('stageStatus').value;
        
        console.log('📅 Actual dates being sent:', {
            actual_start_date: formData.actual_start_date,
            actual_end_date: formData.actual_end_date
        });
    }
    
    const submitBtn = event.target.querySelector('button[type="submit"]');
    const originalBtnText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<svg class="animate-spin h-5 w-5 mr-2 inline" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Saving...';
    submitBtn.disabled = true;
    
    let url, method;
    if (stageFormMode === 'create') {
        url = `/planning/${window.projectId}/stages`;
        method = 'POST';
    } else {
        url = `/planning/${window.projectId}/stages/${currentStageId}`;
        method = 'PUT';
    }
    
    console.log(`  → ${method} ${url}`);
    console.log('  → Data:', formData);
    
    axios({ method: method, url: url, data: formData })
        .then(response => {
            console.log('✅ Stage saved:', response.data);
            
            showNotification(
                stageFormMode === 'create' ? 'Stage added successfully' : 'Stage updated successfully',
                'success'
            );

            closeStageFormModal();

            // Reload page to update both table and progress overview
            console.log('🔄 Reloading page to update all sections...');
            setTimeout(() => window.location.reload(), 500);
        })
        .catch(error => {
            console.error('❌ Error saving stage:', error);
            console.error('Response:', error.response?.data);
            
            showNotification(error.response?.data?.message || 'Failed to save stage', 'error');
            
            submitBtn.innerHTML = originalBtnText;
            submitBtn.disabled = false;
        });
};

window.confirmStageDelete = function() {
    console.log('🗑️ Confirming stage deletion:', currentStageId);
    
    if (!currentStageId || currentStageId === 'null' || currentStageId === 'undefined') {
        console.error('❌ Invalid stage ID');
        showNotification('Error: Invalid stage ID', 'error');
        closeStageDeleteModal();
        return;
    }
    
    const idToDelete = currentStageId;
    closeStageDeleteModal();
    showNotification('Deleting stage...', 'info');

    axios.delete(`/planning/${window.projectId}/stages/${idToDelete}`)
        .then(response => {
            console.log('✅ Stage deleted:', response.data);
            showNotification('Stage deleted successfully', 'success');

            // Reload stages list in modal
            if (currentActivityId) {
                loadStages(currentActivityId);
            }

            // Reload page to update both table and progress overview
            console.log('🔄 Reloading page to update all sections...');
            setTimeout(() => window.location.reload(), 500);
        })
        .catch(error => {
            console.error('❌ Error deleting stage:', error);
            showNotification(error.response?.data?.message || 'Failed to delete stage', 'error');
        });
};

window.closeStageModal = function() {
    console.log('❌ Closing stage modal');
    
    // ✅ Check if table needs refresh
    if (typeof window.tableDataLoaded !== 'undefined' && !window.tableDataLoaded) {
        console.log('💡 Tip: Click the Refresh button to see updated stages in table view');
    }
    
    document.getElementById('stageModal').classList.add('hidden');
    currentActivityId = null;
    currentGroupId = null;
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

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

console.log('✅ Stage management script loaded');
</script>
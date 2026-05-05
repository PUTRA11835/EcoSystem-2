{{-- Stage Management Modal for Support Planning --}}
<div id="stageModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl flex flex-col max-h-[90vh]">
        <!-- Header -->
        <div class="flex justify-between items-center px-6 py-5 border-b border-gray-200 flex-shrink-0">
            <div>
                <h3 class="text-xl font-bold text-gray-900" id="stageModalTitle">Manage Stages</h3>
                <p class="text-sm text-gray-500 mt-0.5" id="stageModalSubtitle">Add and manage stages for the selected group</p>
            </div>
            <button type="button" onclick="closeStageModal()" class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-600 hover:bg-red-800 hover:text-white transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div class="overflow-y-auto flex-1 p-6">

            {{-- Stages List --}}
            <div class="mt-4">
                <div id="stagesList" class="space-y-2 max-h-64 overflow-y-auto">
                    <div class="text-center py-4 text-gray-500">
                        <svg class="w-8 h-8 mx-auto text-gray-300 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <p class="mt-2 text-sm">Loading stages...</p>
                    </div>
                </div>
            </div>

            {{-- Add New Stage Form --}}
            <div class="mt-4 p-4 bg-gray-50 rounded-lg">
                <h4 class="text-sm font-medium text-gray-700 mb-3" id="stageFormTitle">Add New Stage</h4>
                <form id="stageForm" onsubmit="saveStage(event)">
                    <input type="hidden" id="stageId" name="stage_id">
                    <input type="hidden" id="stageGroupId" name="group_id">

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="stageName" class="block text-sm font-medium text-gray-700">Stage Name *</label>
                            <input type="text" id="stageName" name="name" required
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-800 focus:border-red-800 sm:text-sm"
                                   placeholder="Enter stage name">
                        </div>

                        <div>
                            <label for="stageWeight" class="block text-sm font-medium text-gray-700">Weight (%)</label>
                            <input type="number" id="stageWeight" name="weight" min="0" max="100" step="0.1"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-800 focus:border-red-800 sm:text-sm"
                                   placeholder="0">
                        </div>

                        <div>
                            <label for="stageColor" class="block text-sm font-medium text-gray-700">Color</label>
                            <input type="color" id="stageColor" name="color" value="#F59E0B"
                                   class="mt-1 block w-full h-10 border-gray-300 rounded-md shadow-sm focus:ring-red-800 focus:border-red-800">
                        </div>

                        <div>
                            <label for="stageOrder" class="block text-sm font-medium text-gray-700">Order</label>
                            <input type="number" id="stageOrder" name="order_sequence" min="1"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-800 focus:border-red-800 sm:text-sm"
                                   placeholder="1">
                        </div>
                    </div>

                    <div class="mt-4">
                        <label for="stageDescription" class="block text-sm font-medium text-gray-700">Description</label>
                        <textarea id="stageDescription" name="description" rows="2"
                                  class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-800 focus:border-red-800 sm:text-sm"
                                  placeholder="Optional description"></textarea>
                    </div>

                    <div class="mt-4 flex justify-end gap-3">
                        <button type="button" onclick="resetStageForm()"
                                class="px-5 py-2.5 text-sm font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all">
                            Reset
                        </button>
                        <button type="submit"
                                class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold text-white bg-red-800 rounded-lg hover:bg-red-900 transition-all shadow-sm">
                            <span id="stageSubmitText">Add Stage</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Modal Footer --}}
        <div class="flex justify-end px-6 py-4 border-t border-gray-200 bg-gray-50 flex-shrink-0">
            <button type="button" onclick="closeStageModal()"
                    class="px-5 py-2.5 text-sm font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all">
                Close
            </button>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';

    // Open Stage Modal
    window.openStageModal = function(groupId, groupName, phaseId) {

        window.currentGroupId = groupId;
        window.currentStageName = groupName;
        window.currentPhaseId = phaseId;

        document.getElementById('stageModalSubtitle').textContent = `Manage stages for "${groupName}"`;
        document.getElementById('stageGroupId').value = groupId;

        const modal = document.getElementById('stageModal');
        modal.classList.remove('hidden');

        loadStages(groupId);
    };

    // Close Stage Modal
    window.closeStageModal = function() {
        const modal = document.getElementById('stageModal');
        modal.classList.add('hidden');
        resetStageForm();
    };

    // Load Stages for Group
    function loadStages(groupId) {
        const listContainer = document.getElementById('stagesList');

        listContainer.innerHTML = `
            <div class="text-center py-4 text-gray-500">
                <svg class="w-8 h-8 mx-auto text-gray-300 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="mt-2 text-sm">Loading stages...</p>
            </div>
        `;

        axios.get(`/delivery/support/${window.supportId}/planning/${groupId}/stages`)
            .then(response => {
                const stages = response.data.data || response.data || [];
                renderStagesList(stages);
            })
            .catch(error => {
                console.error('Error loading stages:', error);
                listContainer.innerHTML = `
                    <div class="text-center py-4 text-red-500">
                        <p class="text-sm">Error loading stages</p>
                    </div>
                `;
            });
    }

    // Render Stages List
    function renderStagesList(stages) {
        const listContainer = document.getElementById('stagesList');

        if (!stages || stages.length === 0) {
            listContainer.innerHTML = `
                <div class="text-center py-4 text-gray-500">
                    <svg class="w-8 h-8 mx-auto text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                    </svg>
                    <p class="mt-2 text-sm">No stages yet</p>
                    <p class="text-xs text-gray-400">Add your first stage below</p>
                </div>
            `;
            return;
        }

        let html = '';
        stages.forEach((stage, index) => {
            html += `
                <div class="flex items-center justify-between p-3 bg-white border rounded-lg hover:bg-gray-50 transition"
                     style="border-left: 4px solid ${stage.color || '#F59E0B'}">
                    <div class="flex items-center space-x-3">
                        <span class="text-xs font-medium text-gray-400">#${index + 1}</span>
                        <div>
                            <p class="text-sm font-medium text-gray-900">${escapeHtml(stage.name)}</p>
                            <p class="text-xs text-gray-500">Weight: ${stage.weight || 0}%</p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2">
                        <button onclick="editStage(${stage.id}, '${escapeHtml(stage.name).replace(/'/g, "\\'")}', ${window.currentGroupId})"
                                class="p-1 text-blue-600 hover:bg-blue-100 rounded">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                        </button>
                        <button onclick="deleteStage(${stage.id}, '${escapeHtml(stage.name).replace(/'/g, "\\'")}')"
                                class="p-1 text-red-600 hover:bg-red-100 rounded">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </div>
                </div>
            `;
        });

        listContainer.innerHTML = html;
    }

    // Save Stage
    window.saveStage = function(event) {
        event.preventDefault();

        const form = document.getElementById('stageForm');
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        const isEdit = data.stage_id && data.stage_id !== '';
        const url = isEdit
            ? `/delivery/support/${window.supportId}/planning/stages/${data.stage_id}`
            : `/delivery/support/${window.supportId}/planning/${data.group_id}/stages`;
        const method = isEdit ? 'PUT' : 'POST';

        axios({
            method: method,
            url: url,
            data: data,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
            }
        })
        .then(response => {
            showNotification(response.data.message || 'Stage saved successfully', 'success');
            resetStageForm();
            loadStages(window.currentGroupId);

            // Refresh table data
            if (typeof window.refreshTableData === 'function') {
                window.refreshTableData();
            }
        })
        .catch(error => {
            console.error('Error saving stage:', error);
            showNotification(error.response?.data?.message || 'Error saving stage', 'error');
        });
    };

    // Edit Stage
    window.editStage = function(stageId, stageName, groupId) {

        axios.get(`/delivery/support/${window.supportId}/planning/stages/${stageId}`)
            .then(response => {
                const stage = response.data.data || response.data;

                document.getElementById('stageId').value = stage.id;
                document.getElementById('stageName').value = stage.name;
                document.getElementById('stageWeight').value = stage.weight || 0;
                document.getElementById('stageColor').value = stage.color || '#F59E0B';
                document.getElementById('stageOrder').value = stage.order_sequence || 1;
                document.getElementById('stageDescription').value = stage.description || '';
                document.getElementById('stageGroupId').value = groupId;

                document.getElementById('stageFormTitle').textContent = 'Edit Stage';
                document.getElementById('stageSubmitText').textContent = 'Update Stage';
            })
            .catch(error => {
                console.error('Error loading stage:', error);
                showNotification('Error loading stage', 'error');
            });
    };

    // Delete Stage
    window.deleteStage = function(stageId, stageName) {
        Swal.fire({
            title: 'Delete Stage?',
            text: `Are you sure you want to delete "${stageName}"?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                axios.delete(`/delivery/support/${window.supportId}/planning/stages/${stageId}`, {
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                    }
                })
                .then(response => {
                    showNotification('Stage deleted successfully', 'success');
                    loadStages(window.currentGroupId);

                    if (typeof window.refreshTableData === 'function') {
                        window.refreshTableData();
                    }
                })
                .catch(error => {
                    console.error('Error deleting stage:', error);
                    showNotification(error.response?.data?.message || 'Error deleting stage', 'error');
                });
            }
        });
    };

    // Reset Stage Form
    window.resetStageForm = function() {
        const form = document.getElementById('stageForm');
        form.reset();
        document.getElementById('stageId').value = '';
        document.getElementById('stageColor').value = '#F59E0B';
        document.getElementById('stageFormTitle').textContent = 'Add New Stage';
        document.getElementById('stageSubmitText').textContent = 'Add Stage';
    };

    // Escape HTML
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

})();
</script>

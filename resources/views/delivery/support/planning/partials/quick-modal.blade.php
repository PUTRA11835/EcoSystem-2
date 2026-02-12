{{-- ✅ FULL RESPONSIVE QUICK MODAL - GROUP ONLY (Matches Project Style) --}}
{{-- ============================================================================ --}}
{{-- QUICK MODAL - Add/Edit Group --}}
{{-- ============================================================================ --}}
<div id="quickModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeQuickModal()"></div>

        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle w-full sm:max-w-lg">
            <form id="quickForm" onsubmit="saveQuickItem(event)">
                <!-- Header with Gradient (matching project style) -->
                <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-4 sm:px-6 py-3 sm:py-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 flex items-center justify-center h-10 w-10 sm:h-12 sm:w-12 rounded-full bg-white bg-opacity-20">
                                <svg class="h-5 w-5 sm:h-6 sm:w-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"></path>
                                </svg>
                            </div>
                            <h3 class="ml-3 text-base sm:text-lg font-semibold text-white" id="quickModalTitle">
                                Add New Group
                            </h3>
                        </div>
                        <button type="button" onclick="closeQuickModal()" class="text-white hover:text-gray-200 transition">
                            <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Form Body -->
                <div class="bg-white px-4 sm:px-6 py-4 sm:py-5">
                    <div class="space-y-4">
                        <!-- Group Name -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Group Name
                                <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="quickName" required
                                   class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500 text-sm sm:text-base"
                                   placeholder="e.g., Incident Group, Bug Fixes...">
                        </div>

                        <!-- Notes -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Notes
                                <span class="text-xs text-gray-500 ml-1">- Optional</span>
                            </label>
                            <textarea id="quickNotes" rows="3"
                                      class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500 text-sm sm:text-base"
                                      placeholder="Additional notes about this group..."></textarea>
                        </div>

                        <!-- Info Banner -->
                        <div class="bg-purple-50 border border-purple-200 rounded-md p-3">
                            <div class="flex items-start">
                                <svg class="h-5 w-5 text-purple-400 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                </svg>
                                <p class="ml-3 text-xs sm:text-sm text-purple-700">
                                    <strong>Tip:</strong> Groups help organize activities within a phase. You can add activities (tickets) inside groups.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="bg-gray-50 px-4 sm:px-6 py-3 sm:py-4 flex flex-col-reverse sm:flex-row sm:justify-end gap-2 sm:gap-3">
                    <button type="button" onclick="closeQuickModal()"
                            class="w-full sm:w-auto inline-flex justify-center items-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 transition">
                        Cancel
                    </button>
                    <button type="submit"
                            class="w-full sm:w-auto inline-flex justify-center items-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-purple-600 text-sm font-medium text-white hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 transition">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Save Group
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ============================================================================ --}}
{{-- DELETE CONFIRMATION MODAL --}}
{{-- ============================================================================ --}}
<div id="deleteModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeDeleteModal()"></div>

        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                        <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left flex-1">
                        <h3 class="text-lg leading-6 font-medium text-gray-900" id="deleteModalTitle">
                            Delete Confirmation
                        </h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-500" id="deleteModalMessage">
                                Are you sure you want to delete this item?
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-4 py-3 sm:px-6 flex flex-col-reverse sm:flex-row sm:justify-end gap-2 sm:gap-3">
                <button type="button" onclick="closeDeleteModal()"
                        class="w-full sm:w-auto inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition">
                    Cancel
                </button>
                <button type="button" onclick="confirmDelete()"
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
/* Mobile-specific improvements */
@media (max-width: 640px) {
    #quickModal .inline-block,
    #deleteModal .inline-block {
        margin: 1rem;
        max-height: calc(100vh - 2rem);
    }

    input, select, textarea {
        font-size: 16px; /* Prevent zoom on iOS */
    }
}
</style>

{{-- ============================================================================ --}}
{{-- JAVASCRIPT --}}
{{-- ============================================================================ --}}
<script>
// ============================================================================
// QUICK MODAL - GROUP MANAGEMENT
// ============================================================================

// Global variables
let quickFormMode = 'create'; // 'create' or 'edit'
let currentQuickItemId = null;
let currentQuickPhaseId = null;
let currentQuickParentId = null;
let currentQuickType = 'group';

/**
 * OPEN QUICK MODAL
 */
window.openQuickModal = function(type, phaseId, parentId = null, existingItem = null) {
    console.log('Opening quick modal:', { type, phaseId, parentId, existingItem });

    if (!phaseId || phaseId === 'null' || phaseId === 'undefined') {
        console.error('Invalid phaseId:', phaseId);
        if (typeof showNotification === 'function') {
            showNotification('Error: Invalid phase ID', 'error');
        }
        return;
    }

    const modal = document.getElementById('quickModal');
    const title = document.getElementById('quickModalTitle');
    const form = document.getElementById('quickForm');

    // Reset form
    form.reset();

    // Set current values
    currentQuickType = type;
    currentQuickPhaseId = phaseId;
    currentQuickParentId = parentId;
    currentQuickItemId = existingItem ? existingItem.id : null;

    // EDIT MODE
    if (existingItem) {
        quickFormMode = 'edit';
        title.textContent = 'Edit Group';

        // Fill form with existing data
        document.getElementById('quickName').value = existingItem.name || existingItem.group_name || '';
        document.getElementById('quickNotes').value = existingItem.notes || '';

        console.log('Edit mode - loaded data:', existingItem);
    }
    // CREATE MODE
    else {
        quickFormMode = 'create';
        title.textContent = 'Add New Group';
        console.log('Create mode');
    }

    modal.classList.remove('hidden');
    document.getElementById('quickName').focus();
};

/**
 * CLOSE QUICK MODAL
 */
window.closeQuickModal = function() {
    console.log('Closing quick modal');
    const modal = document.getElementById('quickModal');
    if (modal) {
        modal.classList.add('hidden');
    }

    quickFormMode = 'create';
    currentQuickItemId = null;
    currentQuickPhaseId = null;
    currentQuickParentId = null;
    currentQuickType = 'group';
};

/**
 * SAVE QUICK ITEM
 */
window.saveQuickItem = function(event) {
    event.preventDefault();
    console.log('Saving quick item...', quickFormMode);

    // Use supportId instead of projectId
    const supportId = window.supportId || (typeof supportId !== 'undefined' ? supportId : null);
    if (!supportId) {
        console.error('Support ID not found');
        if (typeof showNotification === 'function') {
            showNotification('Error: Support ID not found', 'error');
        }
        return;
    }

    const formData = {
        phase_id: currentQuickPhaseId,
        parent_id: currentQuickParentId,
        is_group: true,
        name: document.getElementById('quickName').value,
        notes: document.getElementById('quickNotes').value || null,
    };

    console.log('Form data:', formData);

    const submitBtn = event.target.querySelector('button[type="submit"]');
    const originalBtnText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<svg class="animate-spin h-5 w-5 mr-2 inline" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Saving...';
    submitBtn.disabled = true;

    let url, method;

    // Use support planning routes
    if (quickFormMode === 'edit') {
        url = `/delivery/support/${supportId}/planning/${currentQuickItemId}`;
        method = 'PUT';
    } else {
        url = `/delivery/support/${supportId}/planning`;
        method = 'POST';
    }

    console.log(`${method} ${url}`);

    axios({
        method: method,
        url: url,
        data: formData,
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
        }
    })
        .then(response => {
            console.log('Item saved:', response.data);

            if (typeof showNotification === 'function') {
                showNotification('Group saved successfully', 'success');
            }

            closeQuickModal();

            // Reload page to update both table and progress overview
            console.log('Reloading page to update all sections...');
            setTimeout(() => window.location.reload(), 500);
        })
        .catch(error => {
            console.error('Error saving item:', error);

            if (typeof showNotification === 'function') {
                showNotification(
                    error.response?.data?.message || 'Failed to save group',
                    'error'
                );
            }

            // Restore button
            submitBtn.innerHTML = originalBtnText;
            submitBtn.disabled = false;
        });
};

// ============================================================================
// DELETE FUNCTIONS
// ============================================================================

let deleteItemId = null;
let deleteItemIsGroup = false;
let deleteItemName = '';

/**
 * DELETE ITEM
 */
window.deleteItem = function(itemId, isGroup, itemName = null) {
    console.log('Delete requested:', { itemId, isGroup, itemName });

    if (!itemId || itemId === 'null' || itemId === 'undefined') {
        console.error('Invalid item ID');
        if (typeof showNotification === 'function') {
            showNotification('Error: Invalid item ID', 'error');
        }
        return;
    }

    // Get item name if not provided
    if (!itemName) {
        const button = window.event?.target?.closest('button');
        if (button && button.dataset.itemName) {
            itemName = button.dataset.itemName;
        } else {
            itemName = isGroup ? 'Unnamed Group' : 'Unnamed Item';
        }
    }

    openDeleteModal(itemId, isGroup, itemName);
};

/**
 * OPEN DELETE MODAL
 */
function openDeleteModal(itemId, isGroup, itemName) {
    deleteItemId = itemId;
    deleteItemIsGroup = isGroup;
    deleteItemName = itemName;

    const modal = document.getElementById('deleteModal');
    const title = document.getElementById('deleteModalTitle');
    const message = document.getElementById('deleteModalMessage');

    if (isGroup) {
        title.textContent = 'Delete Group';
        message.innerHTML = `Are you sure you want to delete the group "<strong>${escapeHtml(itemName)}</strong>"?<br><br><span class="text-red-600">This will also delete all items inside this group!</span>`;
    } else {
        title.textContent = 'Delete Item';
        message.innerHTML = `Are you sure you want to delete "<strong>${escapeHtml(itemName)}</strong>"?<br><br>This action cannot be undone.`;
    }

    modal.classList.remove('hidden');
}

/**
 * CLOSE DELETE MODAL
 */
window.closeDeleteModal = function() {
    console.log('Closing delete modal');
    document.getElementById('deleteModal').classList.add('hidden');
    deleteItemId = null;
    deleteItemIsGroup = false;
    deleteItemName = '';
};

/**
 * CONFIRM DELETE
 */
window.confirmDelete = function() {
    console.log('Confirming delete:', deleteItemId);

    if (!deleteItemId || deleteItemId === 'null' || deleteItemId === 'undefined') {
        console.error('Invalid item ID for deletion');
        if (typeof showNotification === 'function') {
            showNotification('Error: Cannot delete, item ID is invalid.', 'error');
        }
        closeDeleteModal();
        return;
    }

    const idToDelete = deleteItemId;
    const supportId = window.supportId;
    closeDeleteModal();

    if (typeof showNotification === 'function') {
        showNotification('Deleting...', 'info');
    }

    axios.delete(`/delivery/support/${supportId}/planning/${idToDelete}`, {
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
        }
    })
    .then(response => {
        console.log('Delete successful:', response.data);

        if (typeof showNotification === 'function') {
            showNotification(response.data.message || 'Item deleted successfully', 'success');
        }

        // Reload page to update both table and progress overview
        console.log('Reloading page to update all sections...');
        setTimeout(() => window.location.reload(), 500);
    })
    .catch(error => {
        console.error('Delete failed:', error);

        if (typeof showNotification === 'function') {
            showNotification(
                error.response?.data?.message || 'Failed to delete item',
                'error'
            );
        }
    });
};

// ============================================================================
// UTILITY FUNCTIONS
// ============================================================================

/**
 * Escape HTML for security
 */
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Show notification (if not already defined)
 */
if (typeof showNotification !== 'function') {
    window.showNotification = function(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg z-50 transition-all ${
            type === 'success' ? 'bg-green-500 text-white' :
            type === 'error' ? 'bg-red-500 text-white' :
            'bg-blue-500 text-white'
        }`;
        notification.textContent = message;
        document.body.appendChild(notification);

        setTimeout(() => {
            notification.classList.add('opacity-0');
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    };
}

// ============================================================================
// INITIALIZATION
// ============================================================================

document.addEventListener('DOMContentLoaded', function() {
    if (typeof supportId !== 'undefined') {
        console.log('Quick Modal using Support ID:', supportId);
    } else if (typeof window.supportId !== 'undefined') {
        console.log('Quick Modal using Support ID from window:', window.supportId);
    } else {
        console.warn('Support ID not found - this may cause issues');
    }
    console.log('Quick Modal (GROUP ONLY) script loaded for Support');
});
</script>

<div class="space-y-6">
    <!-- Attachment Table -->
    <div>
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-base font-semibold text-gray-900">Attachment</h3>
            <div class="flex items-center gap-3">
                <!-- Search -->
                <div class="relative">
                    <input type="text" id="attachmentSearch" placeholder="Search" class="w-64 px-3 py-2 pr-10 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
                    <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                    </button>
                </div>

                <!-- Action Buttons -->
                <button onclick="copySelectedAttachment()" title="Copy" class="inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-gray-300 text-gray-700 text-xs font-semibold rounded-lg hover:bg-gray-50 transition-all">
                    Copy
                </button>

                <button onclick="openCreateAttachmentModal()" title="Create" class="inline-flex items-center gap-1.5 px-3 py-2 bg-red-800 text-white text-xs font-semibold rounded-lg hover:bg-red-900 transition-all">
                    Create
                </button>

                <button onclick="deleteSelectedAttachment()" title="Delete" class="inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-red-600 text-red-600 text-xs font-semibold rounded-lg hover:bg-red-50 transition-all">
                    Delete
                </button>

                <button title="Settings" class="w-8 h-8 flex items-center justify-center border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50 transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
                </button>

                <button title="Export" class="w-8 h-8 flex items-center justify-center border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50 transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto border border-gray-200 rounded-lg">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="w-10 px-4 py-3 text-left">
                            <input type="radio" name="selectedAttachment" class="w-4 h-4 text-red-800 focus:ring-red-800">
                        </th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Type</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Name</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Verify Link</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Drive Link</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Valid From</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Valid To</th>
                        <th class="w-10 px-4 py-3 text-left">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-gray-500">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                            </svg>
                        </th>
                    </tr>
                </thead>
                <tbody id="attachmentTableBody" class="bg-white divide-y divide-gray-100">
                    <tr>
                        <td colspan="8" class="px-4 py-16 text-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 mx-auto mb-4 text-gray-300">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m18.375 12.739-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13" />
                            </svg>
                            <p class="text-base font-medium text-gray-900 mb-2">No attachments found</p>
                            <small class="text-sm text-gray-500">Click "Create" to add a new attachment</small>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create/Edit Attachment Modal -->
<div id="attachmentModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-3xl w-full max-h-[90vh] overflow-y-auto shadow-2xl">
        <div class="flex justify-between items-center px-6 py-5 border-b border-gray-200">
            <h3 id="attachmentModalTitle" class="text-xl font-bold text-gray-900">Upload Attachment</h3>
            <button onclick="closeAttachmentModal()" class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-600 hover:bg-red-800 hover:text-white transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div class="p-6">
            <form id="attachmentForm" enctype="multipart/form-data">
                <input type="hidden" id="editAttachmentId">
                
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <!-- Document Type -->
                        <div class="flex flex-col">
                            <label class="text-xs font-semibold text-gray-600 mb-1.5">Document Type <span class="text-red-600">*</span></label>
                            <select id="modalDocumentType" required class="px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                                <option value="">Select Document Type</option>
                                <option value="Identification (ID)">Identification (ID)</option>
                                <option value="Contract">Contract</option>
                                <option value="Agreement">Agreement</option>
                                <option value="Invoice">Invoice</option>
                                <option value="Purchase Order">Purchase Order</option>
                                <option value="Certificate">Certificate</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <!-- Document Title -->
                        <div class="flex flex-col">
                            <label class="text-xs font-semibold text-gray-600 mb-1.5">Document Title <span class="text-red-600">*</span></label>
                            <input type="text" id="modalDocumentTitle" required class="px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="flex flex-col">
                        <label class="text-xs font-semibold text-gray-600 mb-1.5">Description</label>
                        <textarea id="modalDescription" rows="3" class="px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800"></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <!-- Verify Link -->
                        <div class="flex flex-col">
                            <label class="text-xs font-semibold text-gray-600 mb-1.5">Verify Link</label>
                            <input type="url" id="modalVerifyLink" placeholder="https://..." class="px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                        </div>

                        <!-- Drive Link -->
                        <div class="flex flex-col">
                            <label class="text-xs font-semibold text-gray-600 mb-1.5">Drive Link</label>
                            <input type="url" id="modalDriveLink" placeholder="https://drive.google.com/..." class="px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <!-- Valid From -->
                        <div class="flex flex-col">
                            <label class="text-xs font-semibold text-gray-600 mb-1.5">Valid From</label>
                            <input type="date" id="modalValidFrom" class="px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                        </div>

                        <!-- Valid To -->
                        <div class="flex flex-col">
                            <label class="text-xs font-semibold text-gray-600 mb-1.5">Valid To</label>
                            <input type="date" id="modalValidTo" class="px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                        </div>
                    </div>

                    <!-- File Upload -->
                    <div class="flex flex-col">
                        <label class="text-xs font-semibold text-gray-600 mb-1.5">File</label>
                        <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-lg hover:border-red-800 transition-colors">
                            <div class="space-y-1 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                    <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <div class="flex text-sm text-gray-600">
                                    <label for="file-upload" class="relative cursor-pointer bg-white rounded-md font-medium text-red-800 hover:text-red-900 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-red-800">
                                        <span>Upload a file</span>
                                        <input id="file-upload" name="file-upload" type="file" class="sr-only" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.xlsx,.xls">
                                    </label>
                                    <p class="pl-1">or drag and drop</p>
                                </div>
                                <p class="text-xs text-gray-500">PDF, DOC, DOCX, JPG, PNG, XLS, XLSX up to 10MB</p>
                                <p id="fileName" class="text-sm font-semibold text-red-800 mt-2"></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex gap-3 justify-end mt-6 pt-6 border-t border-gray-200">
                    <button type="button" onclick="closeAttachmentModal()" class="px-5 py-2.5 bg-white border border-gray-300 text-gray-700 text-sm font-semibold rounded-lg hover:bg-gray-50 transition-all">
                        Cancel
                    </button>
                    <button type="button" onclick="saveAttachmentFromModal()" class="px-5 py-2.5 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900 transition-all">
                        Upload Document
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    let attachmentsData = [];
    let selectedAttachmentId = null;

    /**
     * Load all attachments
     */
    async function loadAttachments() {
        try {
            const response = await fetch(`/api/customers/{{ $customerId }}/attachments`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin'
            });

            const data = await response.json();

            if (data.success && data.data && data.data.length > 0) {
                attachmentsData = data.data;
                renderAttachmentTable(data.data);
            } else {
                attachmentsData = [];
                renderEmptyAttachmentTable();
            }
        } catch (error) {
            console.error('❌ Error loading attachments:', error);
            attachmentsData = [];
            renderEmptyAttachmentTable();
        }
    }

    /**
     * Render attachment table
     */
    function renderAttachmentTable(attachments) {
        const tbody = document.getElementById('attachmentTableBody');
        
        tbody.innerHTML = attachments.map(attachment => {
            const validFrom = attachment.valid_from ? new Date(attachment.valid_from).toLocaleDateString('en-GB') : '';
            const validTo = attachment.valid_to ? new Date(attachment.valid_to).toLocaleDateString('en-GB') : '';
            const verifyLink = attachment.verify_link || '-';
            const driveLink = attachment.drive_link || '-';

            return `
                <tr class="hover:bg-gray-50 transition-colors cursor-pointer" onclick="selectAttachmentRow(${attachment.attachment_id}, event)">
                    <td class="px-4 py-3">
                        <input type="radio" name="selectedAttachment" value="${attachment.attachment_id}" 
                            onclick="selectAttachment(${attachment.attachment_id})" 
                            class="w-4 h-4 text-red-800 focus:ring-red-800">
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-900">${attachment.document_type || '-'}</td>
                    <td class="px-4 py-3 text-sm text-gray-900">${attachment.document_title || attachment.file_name || '-'}</td>
                    <td class="px-4 py-3 text-sm">
                        ${verifyLink !== '-' ? `<a href="${verifyLink}" target="_blank" class="text-blue-600 hover:text-blue-800 underline">Link</a>` : '-'}
                    </td>
                    <td class="px-4 py-3 text-sm">
                        ${driveLink !== '-' ? `<a href="${driveLink}" target="_blank" class="text-blue-600 hover:text-blue-800 underline">Link</a>` : '-'}
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">${validFrom}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${validTo}</td>
                    <td class="px-4 py-3">
                        <button onclick="downloadAttachment(${attachment.attachment_id}); event.stopPropagation();" class="text-gray-400 hover:text-gray-600">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
    }

    /**
     * Render empty table
     */
    function renderEmptyAttachmentTable() {
        const tbody = document.getElementById('attachmentTableBody');
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="px-4 py-16 text-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 mx-auto mb-4 text-gray-300">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m18.375 12.739-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13" />
                    </svg>
                    <p class="text-base font-medium text-gray-900 mb-2">No attachments found</p>
                    <small class="text-sm text-gray-500">Click "Create" to add a new attachment</small>
                </td>
            </tr>
        `;
    }

    /**
     * Select attachment
     */
    function selectAttachment(attachmentId) {
        selectedAttachmentId = attachmentId;
    }

    /**
     * Select attachment row
     */
    function selectAttachmentRow(attachmentId, event) {
        if (event.target.tagName === 'INPUT' || event.target.tagName === 'BUTTON' || event.target.closest('button') || event.target.tagName === 'A') {
            return;
        }
        
        const radio = document.querySelector(`input[name="selectedAttachment"][value="${attachmentId}"]`);
        if (radio) {
            radio.checked = true;
            selectAttachment(attachmentId);
        }
    }

    /**
     * Open create attachment modal
     */
    function openCreateAttachmentModal() {
        document.getElementById('attachmentModalTitle').textContent = 'Upload Attachment';
        document.getElementById('attachmentForm').reset();
        document.getElementById('editAttachmentId').value = '';
        document.getElementById('fileName').textContent = '';
        
        document.getElementById('attachmentModal').classList.remove('hidden');
        document.getElementById('attachmentModal').classList.add('flex');
    }

    /**
     * Close attachment modal
     */
    function closeAttachmentModal() {
        document.getElementById('attachmentModal').classList.add('hidden');
        document.getElementById('attachmentModal').classList.remove('flex');
    }

    /**
     * Show selected file name
     */
    document.getElementById('file-upload')?.addEventListener('change', function(e) {
        const fileName = e.target.files[0]?.name || '';
        document.getElementById('fileName').textContent = fileName ? `Selected: ${fileName}` : '';
    });

    /**
     * Save attachment from modal
     */
    async function saveAttachmentFromModal() {
        const form = document.getElementById('attachmentForm');
        
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const formData = new FormData();
        formData.append('document_type', document.getElementById('modalDocumentType').value);
        formData.append('document_title', document.getElementById('modalDocumentTitle').value);
        formData.append('description', document.getElementById('modalDescription').value);
        formData.append('verify_link', document.getElementById('modalVerifyLink').value);
        formData.append('drive_link', document.getElementById('modalDriveLink').value);
        formData.append('valid_from', document.getElementById('modalValidFrom').value);
        formData.append('valid_to', document.getElementById('modalValidTo').value);
        
        const fileInput = document.getElementById('file-upload');
        if (fileInput.files.length > 0) {
            formData.append('file', fileInput.files[0]);
        }

        try {
            const response = await fetch(`/api/customers/{{ $customerId }}/attachments`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                credentials: 'same-origin',
                body: formData
            });

            const data = await response.json();
            
            if (data.success) {
                showNotification('Attachment uploaded successfully!', 'success');
                closeAttachmentModal();
                loadAttachments();
            } else {
                showNotification('Failed to upload: ' + (data.message || 'Unknown error'), 'error');
            }
        } catch (error) {
            console.error('❌ Error uploading attachment:', error);
            showNotification('An error occurred while uploading', 'error');
        }
    }

    /**
     * Download attachment
     */
    async function downloadAttachment(attachmentId) {
        try {
            window.location.href = `/api/customers/{{ $customerId }}/attachments/${attachmentId}/download`;
        } catch (error) {
            console.error('❌ Error downloading attachment:', error);
            showNotification('An error occurred while downloading', 'error');
        }
    }

    /**
     * Copy selected attachment
     */
    function copySelectedAttachment() {
        if (!selectedAttachmentId) {
            showNotification('Please select an attachment first', 'warning');
            return;
        }
        showNotification('Copy feature coming soon', 'info');
    }

    /**
     * Delete selected attachment
     */
    function deleteSelectedAttachment() {
        if (!selectedAttachmentId) {
            showNotification('Please select an attachment first', 'warning');
            return;
        }
        showNotification('Delete feature coming soon', 'info');
    }

    /**
     * Show notification
     */
    function showNotification(message, type = 'info') {
        const bgColor = type === 'success' ? 'bg-green-500' : 
                        type === 'error' ? 'bg-red-500' : 
                        type === 'warning' ? 'bg-yellow-500' : 'bg-blue-500';
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 ${bgColor} text-white px-6 py-3 rounded-lg shadow-lg z-50 transition-opacity duration-300`;
        notification.textContent = message;
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.opacity = '0';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        console.log('🚀 Attachment section initialized');
        loadAttachments();
    });

    // Close modal on outside click
    document.getElementById('attachmentModal')?.addEventListener('click', function(e) {
        if (e.target === this) closeAttachmentModal();
    });

    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !document.getElementById('attachmentModal').classList.contains('hidden')) {
            closeAttachmentModal();
        }
    });
</script>
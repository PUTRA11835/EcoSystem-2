<div class="space-y-6">
    <!-- UPLOAD FORM SECTION -->
    <div>
        <div class="flex justify-between items-center mb-4 pb-2 border-b border-gray-200">
            <h3 class="text-base font-semibold text-gray-900">Upload Document</h3>
            <button type="button" onclick="submitAttachment()" class="inline-flex items-center gap-1.5 px-3 py-2 bg-red-800 text-white text-xs font-semibold rounded-lg hover:bg-red-900 transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                </svg>
                Upload
            </button>
        </div>

        <div class="grid grid-cols-6 gap-4">
            <!-- Document Type -->
            <div class="col-span-2">
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Document Type <span class="text-red-600">*</span></label>
                <div class="custom-dd relative">
                    <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-3 py-2.5 bg-white border border-gray-300 rounded-lg text-sm hover:border-gray-400 transition-all text-left">
                        <span class="custom-dd-label text-gray-500">Select Type</span>
                        <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <input type="hidden" id="attachDocumentType" value="">
                    <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5">
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">Select Type</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="KTP">KTP / ID Card</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="NPWP">NPWP</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="BPJS Kesehatan">BPJS Kesehatan</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="BPJS Ketenagakerjaan">BPJS Ketenagakerjaan</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Ijazah">Ijazah / Diploma</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Transkrip Nilai">Transkrip Nilai</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Sertifikat">Sertifikat</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Kontrak Kerja">Kontrak Kerja</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="SK Pengangkatan">SK Pengangkatan</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Surat Referensi">Surat Referensi</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="CV">CV / Resume</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Akta Lahir">Akta Lahir</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Kartu Keluarga">Kartu Keluarga</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Foto">Foto</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Lainnya">Lainnya</button>
                    </div>
                </div>
            </div>

            <!-- Document Title -->
            <div class="col-span-3">
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Document Title <span class="text-red-600">*</span></label>
                <input type="text" id="attachDocumentTitle" placeholder="e.g. KTP John Doe" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
            </div>

            <!-- File Upload -->
            <div class="col-span-1">
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">File <span class="text-red-600">*</span></label>
                <label class="flex items-center justify-center w-full h-[42px] border border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-red-800 transition-colors bg-white text-xs text-gray-500 hover:text-red-800 gap-1.5">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 flex-shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" /></svg>
                    <span id="attachFileLabel" class="truncate">Choose file</span>
                    <input type="file" id="attachFile" class="hidden" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.xlsx,.xls">
                </label>
            </div>

            <!-- Description -->
            <div class="col-span-6">
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Description</label>
                <input type="text" id="attachDescription" placeholder="Optional notes about this document" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
            </div>
        </div>

        <p class="mt-2 text-xs text-gray-400">Supported: PDF, DOC, DOCX, JPG, PNG, XLSX, XLS — Max 10MB</p>
    </div>

    <!-- DOCUMENTS TABLE -->
    <div>
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-base font-semibold text-gray-900">Uploaded Documents</h3>
            <button onclick="deleteSelectedAttachment()" class="inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-red-600 text-red-600 text-xs font-semibold rounded-lg hover:bg-red-50 transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                </svg>
                Delete
            </button>
        </div>

        <div class="overflow-x-auto border border-gray-200 rounded-lg">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="w-10 px-4 py-3 text-left">
                            <input type="radio" name="selectedAttachment" class="w-4 h-4 text-red-800 focus:ring-red-800" disabled>
                        </th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Document</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Type</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Size</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Uploaded By</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Date</th>
                        <th class="w-16 px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody id="attachmentTableBody" class="bg-white divide-y divide-gray-100">
                    <tr>
                        <td colspan="7" class="px-4 py-16 text-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 mx-auto mb-4 text-gray-300">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                            </svg>
                            <p class="text-base font-medium text-gray-900 mb-2">No documents uploaded yet</p>
                            <small class="text-sm text-gray-500">Fill the form above and click "Upload" to add a document</small>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="confirmDeleteAttachmentModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-md w-full shadow-2xl">
        <div class="p-6">
            <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 bg-red-100 rounded-full">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-red-600">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-900 text-center mb-2">Delete Document</h3>
            <p class="text-sm text-gray-600 text-center mb-1">Are you sure you want to delete this document?</p>
            <p class="text-sm font-semibold text-gray-900 text-center mb-6" id="deleteAttachmentInfo"></p>
            <div class="flex gap-3">
                <button onclick="closeConfirmDeleteAttachment()" class="flex-1 px-4 py-2.5 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">Cancel</button>
                <button onclick="confirmDeleteAttachment()" class="flex-1 px-4 py-2.5 bg-red-600 text-white text-sm font-semibold rounded-lg hover:bg-red-700 transition-all">Delete</button>
            </div>
        </div>
    </div>
</div>

<script>
    let attachmentsData = [];
    let selectedAttachmentId = null;
    let deleteAttachmentId = null;

    // Update file label when file selected
    document.getElementById('attachFile')?.addEventListener('change', function (e) {
        const file = e.target.files[0];
        document.getElementById('attachFileLabel').textContent = file ? file.name : 'Choose file';
    });

    /**
     * Load all attachments
     */
    async function loadAttachments() {
        try {
            const response = await fetch(`/api/employees/${employeeId}/attachments`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
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
            console.error('Error loading attachments:', error);
            renderEmptyAttachmentTable();
        }
    }

    /**
     * Render attachment table
     */
    function renderAttachmentTable(attachments) {
        const tbody = document.getElementById('attachmentTableBody');
        tbody.innerHTML = attachments.map(att => {
            const size   = att.file_size ? formatFileSize(att.file_size) : '-';
            const date   = att.created_at ? new Date(att.created_at).toLocaleDateString('id-ID', { year: 'numeric', month: 'short', day: 'numeric' }) : '-';
            const fileUrl = `/storage/${att.file_path}`;

            return `
                <tr class="hover:bg-gray-50 transition-colors cursor-pointer" onclick="selectAttachmentRow(${att.attachment_id}, event)">
                    <td class="px-4 py-3">
                        <input type="radio" name="selectedAttachment" value="${att.attachment_id}"
                            onclick="selectAttachment(${att.attachment_id})"
                            class="w-4 h-4 text-red-800 focus:ring-red-800">
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <strong class="font-semibold text-gray-900">${att.document_title || '-'}</strong>
                        ${att.description ? `<br><small class="text-xs text-gray-500">${att.description}</small>` : ''}
                        <br><small class="text-xs text-gray-400">${att.file_name || ''}</small>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <span class="inline-block px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                            ${att.document_type || '-'}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">${size}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${att.uploaded_by || '-'}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${date}</td>
                    <td class="px-4 py-3">
                        ${att.file_path ? `
                        <a href="${fileUrl}" target="_blank" class="text-red-800 hover:text-red-900" title="Download">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                        </a>` : ''}
                    </td>
                </tr>
            `;
        }).join('');
    }

    function renderEmptyAttachmentTable() {
        document.getElementById('attachmentTableBody').innerHTML = `
            <tr>
                <td colspan="7" class="px-4 py-16 text-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 mx-auto mb-4 text-gray-300">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                    <p class="text-base font-medium text-gray-900 mb-2">No documents uploaded yet</p>
                    <small class="text-sm text-gray-500">Fill the form above and click "Upload" to add a document</small>
                </td>
            </tr>
        `;
    }

    function formatFileSize(bytes) {
        if (!bytes) return '-';
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function selectAttachment(id) {
        selectedAttachmentId = id;
    }

    function selectAttachmentRow(id, event) {
        if (event.target.tagName === 'INPUT' || event.target.tagName === 'A' || event.target.closest('a')) return;
        const radio = document.querySelector(`input[name="selectedAttachment"][value="${id}"]`);
        if (radio) { radio.checked = true; selectAttachment(id); }
    }

    /**
     * Submit new attachment upload
     */
    async function submitAttachment() {
        const docType  = document.getElementById('attachDocumentType').value;
        const docTitle = document.getElementById('attachDocumentTitle').value;
        const file     = document.getElementById('attachFile').files[0];

        if (!docType) { showNotification('Please select a document type', 'error'); return; }
        if (!docTitle.trim()) { showNotification('Document title is required', 'error'); return; }
        if (!file) { showNotification('Please select a file to upload', 'error'); return; }

        const formData = new FormData();
        formData.append('document_type', docType);
        formData.append('document_title', docTitle);
        formData.append('description', document.getElementById('attachDescription').value);
        formData.append('file', file);

        try {
            const response = await fetch(`/api/employees/${employeeId}/attachments`, {
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
                showNotification('Document uploaded successfully!', 'success');
                // Reset form
                setCustomDropdownValue('attachDocumentType', '');
                document.getElementById('attachDocumentTitle').value = '';
                document.getElementById('attachDescription').value = '';
                document.getElementById('attachFile').value = '';
                document.getElementById('attachFileLabel').textContent = 'Choose file';
                loadAttachments();
            } else {
                showNotification(data.message || 'Failed to upload document', 'error');
            }
        } catch (error) {
            console.error('Upload error:', error);
            showNotification('An error occurred while uploading', 'error');
        }
    }

    /**
     * Delete selected attachment
     */
    function deleteSelectedAttachment() {
        if (!selectedAttachmentId) {
            showNotification('Please select a document first', 'warning');
            return;
        }
        deleteAttachmentId = selectedAttachmentId;
        const att = attachmentsData.find(a => a.attachment_id === selectedAttachmentId);
        document.getElementById('deleteAttachmentInfo').textContent = att ? att.document_title : 'this document';
        document.getElementById('confirmDeleteAttachmentModal').classList.remove('hidden');
        document.getElementById('confirmDeleteAttachmentModal').classList.add('flex');
    }

    function closeConfirmDeleteAttachment() {
        document.getElementById('confirmDeleteAttachmentModal').classList.add('hidden');
        document.getElementById('confirmDeleteAttachmentModal').classList.remove('flex');
        deleteAttachmentId = null;
    }

    async function confirmDeleteAttachment() {
        if (!deleteAttachmentId) return;
        try {
            const response = await fetch(`/api/employees/${employeeId}/attachments/${deleteAttachmentId}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                credentials: 'same-origin'
            });
            const data = await response.json();
            closeConfirmDeleteAttachment();
            if (data.success) {
                showNotification('Document deleted successfully', 'success');
                selectedAttachmentId = null;
                loadAttachments();
            } else {
                showNotification(data.message || 'Failed to delete document', 'error');
            }
        } catch (error) {
            closeConfirmDeleteAttachment();
            showNotification('An error occurred while deleting', 'error');
        }
    }

    // Initial load
    loadAttachments();
</script>

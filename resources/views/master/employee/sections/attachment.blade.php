<div class="space-y-6">
    <div class="flex justify-between items-center mb-4">
        <h3 class="text-base font-semibold text-gray-900">Documents & Attachments</h3>
        <button onclick="uploadAttachment()" class="inline-flex items-center gap-2 px-4 py-2 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900 transition-all">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
            </svg>
            Upload Document
        </button>
    </div>

    <!-- Upload Area -->
    <div class="border-2 border-dashed border-gray-300 rounded-lg p-12 text-center hover:border-red-800 transition-colors cursor-pointer" onclick="document.getElementById('fileInput').click()">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 mx-auto mb-4 text-gray-400">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0 3 3m-3-3-3 3M6.75 19.5a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.233-2.33 3 3 0 0 1 3.758 3.848A3.752 3.752 0 0 1 18 19.5H6.75Z" />
        </svg>
        <p class="text-sm text-gray-600 mb-2">Drag and drop files here, or click to browse</p>
        <p class="text-xs text-gray-500">Supported formats: PDF, DOC, DOCX, JPG, PNG (Max 5MB)</p>
        <input type="file" id="fileInput" class="hidden" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
    </div>

    <!-- Attachments Table -->
    <div class="mt-6">
        <h4 class="text-sm font-semibold text-gray-900 mb-3">Uploaded Documents</h4>
        <div class="overflow-x-auto border border-gray-200 rounded-lg">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase">File Name</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase">Document Type</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase">Size</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase">Uploaded By</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase">Upload Date</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody id="attachmentTable" class="divide-y divide-gray-100">
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-sm text-gray-500">
                            No documents uploaded yet. Upload your first document above.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function uploadAttachment() {
    document.getElementById('fileInput').click();
}

document.getElementById('fileInput')?.addEventListener('change', function(e) {
    const files = e.target.files;
    if (files.length > 0) {
        showNotification(`${files.length} file(s) selected. Upload functionality coming soon.`, 'info');
    }
});
</script>
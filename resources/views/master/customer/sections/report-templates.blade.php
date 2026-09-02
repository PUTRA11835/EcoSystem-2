<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 mb-4">
        <div>
            <h3 class="text-base font-semibold text-gray-900">Report Templates</h3>
            <p class="text-xs text-gray-500 mt-0.5">Template .docx untuk Word Report Generator — dipakai ulang tanpa upload lagi tiap generate laporan.</p>
        </div>
        <button onclick="openCreateReportTemplateModal()" class="inline-flex items-center gap-1.5 px-3 py-2 bg-red-800 text-white text-xs font-semibold rounded-lg hover:bg-red-900 transition-all">
            Upload Template
        </button>
    </div>

    <div class="overflow-x-auto border border-gray-200 rounded-lg">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Name</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">File</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Uploaded</th>
                    <th class="w-24 px-4 py-3"></th>
                </tr>
            </thead>
            <tbody id="reportTemplateTableBody" class="bg-white divide-y divide-gray-100">
                <tr><td colspan="4" class="px-4 py-10 text-center text-sm text-gray-400">Memuat...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Upload Template Modal -->
<div id="reportTemplateModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-md w-full shadow-2xl">
        <div class="flex justify-between items-center px-6 py-5 border-b border-gray-200">
            <h3 class="text-lg font-bold text-gray-900">Upload Report Template</h3>
            <button onclick="closeReportTemplateModal()" class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-600 hover:bg-red-800 hover:text-white transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div class="p-6 space-y-4">
            <div>
                <label class="text-xs font-semibold text-gray-600 mb-1.5 block">Nama Template <span class="text-red-600">*</span></label>
                <input type="text" id="reportTemplateName" placeholder="mis. Template SLA Report"
                       class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-600 mb-1.5 block">File .docx <span class="text-red-600">*</span></label>
                <input type="file" id="reportTemplateFile" accept=".docx"
                       class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm">
            </div>
            <div class="flex gap-3 justify-end pt-2">
                <button type="button" onclick="closeReportTemplateModal()" class="px-5 py-2.5 bg-white border border-gray-300 text-gray-700 text-sm font-semibold rounded-lg hover:bg-gray-50 transition-all">Cancel</button>
                <button type="button" onclick="saveReportTemplate()" class="px-5 py-2.5 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900 transition-all">Upload</button>
            </div>
        </div>
    </div>
</div>

<script>
    async function loadReportTemplates() {
        const tbody = document.getElementById('reportTemplateTableBody');
        try {
            const res = await fetch(`/api/customers/{{ $customerId }}/report-templates`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const json = await res.json();

            if (!json.success || !json.data.length) {
                tbody.innerHTML = '<tr><td colspan="4" class="px-4 py-10 text-center text-sm text-gray-400">Belum ada template untuk customer ini.</td></tr>';
                return;
            }

            tbody.innerHTML = json.data.map(t => `
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm text-gray-900 font-medium">${t.name}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${t.original_filename}</td>
                    <td class="px-4 py-3 text-sm text-gray-500">${new Date(t.created_at).toLocaleDateString('en-GB')}</td>
                    <td class="px-4 py-3 text-right">
                        <a href="/api/customers/{{ $customerId }}/report-templates/${t.id}/download" class="text-gray-400 hover:text-gray-600 mr-3" title="Download">
                            <i class="fas fa-download"></i>
                        </a>
                        <button onclick="deleteReportTemplate(${t.id})" class="text-red-400 hover:text-red-600" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `).join('');
        } catch (err) {
            tbody.innerHTML = '<tr><td colspan="4" class="px-4 py-10 text-center text-sm text-red-500">Gagal memuat daftar template.</td></tr>';
        }
    }

    function openCreateReportTemplateModal() {
        document.getElementById('reportTemplateName').value = '';
        document.getElementById('reportTemplateFile').value = '';
        document.getElementById('reportTemplateModal').classList.remove('hidden');
        document.getElementById('reportTemplateModal').classList.add('flex');
    }

    function closeReportTemplateModal() {
        document.getElementById('reportTemplateModal').classList.add('hidden');
        document.getElementById('reportTemplateModal').classList.remove('flex');
    }

    async function saveReportTemplate() {
        const name = document.getElementById('reportTemplateName').value.trim();
        const file = document.getElementById('reportTemplateFile').files[0];

        if (!name || !file) {
            showNotification('Isi nama template dan pilih file .docx-nya.', 'warning');
            return;
        }

        const formData = new FormData();
        formData.append('name', name);
        formData.append('template', file);

        try {
            const res = await fetch(`/api/customers/{{ $customerId }}/report-templates`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                credentials: 'same-origin',
                body: formData,
            });
            const json = await res.json();

            if (json.success) {
                showNotification('Template berhasil diupload.', 'success');
                closeReportTemplateModal();
                loadReportTemplates();
            } else {
                showNotification('Gagal upload: ' + (json.message || 'Unknown error'), 'error');
            }
        } catch (err) {
            showNotification('Terjadi kesalahan saat upload.', 'error');
        }
    }

    async function deleteReportTemplate(id) {
        if (!confirm('Hapus template ini? Laporan yang sudah pernah dibuat dari template ini tidak terpengaruh.')) {
            return;
        }

        try {
            const res = await fetch(`/api/customers/{{ $customerId }}/report-templates/${id}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                credentials: 'same-origin',
            });
            const json = await res.json();

            if (json.success) {
                showNotification('Template dihapus.', 'success');
                loadReportTemplates();
            } else {
                showNotification('Gagal menghapus: ' + (json.message || 'Unknown error'), 'error');
            }
        } catch (err) {
            showNotification('Terjadi kesalahan saat menghapus.', 'error');
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        loadReportTemplates();
    });
</script>

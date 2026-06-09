{{-- ====================================================================
     Project Planning — CSV Import Modal
     Bulk migration of the planning structure (Phase → Group → Activity).
     ==================================================================== --}}
<div id="planningImportModal"
     class="hidden fixed inset-0 z-[60] overflow-y-auto"
     aria-labelledby="planningImportTitle" role="dialog" aria-modal="true">
    <div class="flex min-h-screen items-center justify-center px-4 py-8">
        {{-- Backdrop --}}
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
             onclick="closePlanningImportModal()"></div>

        {{-- Panel --}}
        <div class="relative bg-white rounded-lg shadow-xl w-full max-w-lg">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                <h3 id="planningImportTitle" class="text-lg font-semibold text-gray-900">
                    Import Project Planning (CSV)
                </h3>
                <button type="button" onclick="closePlanningImportModal()"
                        class="text-gray-400 hover:text-gray-600">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <div class="px-6 py-5 space-y-4">
                <p class="text-sm text-gray-600">
                    Unggah file CSV untuk membuat seluruh struktur planning sekaligus:
                    <strong>Fase → Group → Activity</strong>. Fase &amp; group dicocokkan berdasarkan
                    nama — yang sudah ada dipakai ulang (tidak diduplikasi), yang belum ada dibuat baru.
                    Activity dengan nama sama di fase/group yang sama akan <strong>diperbarui</strong>
                    dari CSV (sel yang diisi menimpa data lama; sel kosong tetap mempertahankan nilai lama).
                </p>

                {{-- Step 1: template --}}
                <div class="rounded-md bg-indigo-50 border border-indigo-100 px-4 py-3">
                    <p class="text-sm font-medium text-indigo-900 mb-1">1. Unduh template</p>
                    <p class="text-xs text-indigo-700 mb-2">
                        Isi sesuai contoh. Kolom: Phase, Phase Weight, Group, Activity, Weight, Module,
                        Object, Complexity (low/medium/high), Start Date, End Date, Deliverable, Notes.
                        Group bersarang ditulis dengan pemisah “&gt;”, mis. <em>Realization &gt; Configuration</em>.
                        Tanggal format <em>YYYY-MM-DD</em> atau <em>DD/MM/YYYY</em>.
                    </p>
                    <ul class="text-xs text-indigo-700 mb-2 list-disc pl-4 space-y-0.5">
                        <li><strong>Bobot activity</strong> dalam satu fase harus berjumlah <strong>≤ Phase Weight</strong>
                            (mis. fase 30% → activity-nya 10 + 20 = 30). Yang melebihi akan dilewati.</li>
                        <li><strong>Tanggal</strong> harus berada dalam rentang <strong>Contract Start–End</strong> project.
                            Di luar itu baris dilewati.</li>
                        <li>Baris yang dilewati akan muncul di log hasil di bawah — periksa bila ada yang tidak masuk.</li>
                    </ul>
                    <a href="{{ route('planning.import.template', $project) }}"
                       class="inline-flex items-center text-sm font-medium text-indigo-700 hover:text-indigo-900">
                        <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                        </svg>
                        Download template.csv
                    </a>
                </div>

                {{-- Step 2: upload --}}
                <div>
                    <p class="text-sm font-medium text-gray-900 mb-1">2. Pilih file CSV</p>
                    <input type="file" id="planningImportFile" accept=".csv,text/csv"
                           class="block w-full text-sm text-gray-600 border border-gray-300 rounded-md cursor-pointer
                                  file:mr-3 file:py-2 file:px-4 file:border-0 file:text-sm file:font-medium
                                  file:bg-indigo-600 file:text-white hover:file:bg-indigo-700">
                </div>

                {{-- Result log --}}
                <div id="planningImportResult" class="hidden rounded-md px-4 py-3 text-sm"></div>
            </div>

            <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-lg">
                <button type="button" id="planningImportCloseBtn" onclick="closePlanningImportModal()"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                    Close
                </button>
                <button type="button" id="planningImportSubmitBtn" onclick="planningImportPrimaryAction()"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md hover:bg-indigo-700 disabled:opacity-50">
                    <svg id="planningImportSpinner" class="hidden animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    <span id="planningImportBtnLabel">Import</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    // Primary-button state: 'import' | 'retry' | 'done'.
    let planningImportMode = 'import';
    // True once a run actually created something — Close then refreshes the page.
    let planningImportDirty = false;

    // Set the primary button's label, colour and action mode.
    function setPlanningImportPrimary(mode) {
        planningImportMode = mode;
        const btnLabel = document.getElementById('planningImportBtnLabel');
        const btn = document.getElementById('planningImportSubmitBtn');
        const green = ['bg-green-600', 'hover:bg-green-700'];
        const indigo = ['bg-indigo-600', 'hover:bg-indigo-700'];

        if (mode === 'done') {
            btnLabel.textContent = 'Done';
            btn.classList.remove(...indigo);
            btn.classList.add(...green);
        } else { // 'import' | 'retry'
            btnLabel.textContent = (mode === 'retry') ? 'Retry' : 'Import';
            btn.classList.remove(...green);
            btn.classList.add(...indigo);
        }
    }

    function openPlanningImportModal() {
        const r = document.getElementById('planningImportResult');
        if (r) { r.classList.add('hidden'); r.innerHTML = ''; }
        const f = document.getElementById('planningImportFile');
        if (f) { f.value = ''; f.disabled = false; }
        planningImportDirty = false;
        setPlanningImportPrimary('import');
        document.getElementById('planningImportModal').classList.remove('hidden');
    }

    function closePlanningImportModal() {
        document.getElementById('planningImportModal').classList.add('hidden');
        // If a run added data, refresh so the table reflects the new structure.
        if (planningImportDirty) {
            window.location.reload();
        }
    }

    // Dispatch the primary button by its current mode.
    function planningImportPrimaryAction() {
        if (planningImportMode === 'done') {
            window.location.reload();
        } else {
            submitPlanningImport();
        }
    }

    // Lightweight toast — reuse the global showToast helper, fall back gracefully.
    function planningImportToast(message, type, duration) {
        if (typeof window.showToast === 'function') {
            window.showToast(message, type, duration);
        }
    }

    function submitPlanningImport() {
        const fileInput = document.getElementById('planningImportFile');
        const resultBox = document.getElementById('planningImportResult');
        const btn = document.getElementById('planningImportSubmitBtn');
        const spinner = document.getElementById('planningImportSpinner');
        const btnLabel = document.getElementById('planningImportBtnLabel');

        if (!fileInput.files.length) {
            planningImportToast('Pilih file CSV terlebih dahulu.', 'warning');
            return;
        }

        const formData = new FormData();
        formData.append('file', fileInput.files[0]);
        formData.append('_token', document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}');

        // Processing state: spinner + disabled + label change + info toast
        btn.disabled = true;
        fileInput.disabled = true;
        spinner.classList.remove('hidden');
        btnLabel.textContent = 'Importing…';
        resultBox.classList.add('hidden');
        planningImportToast('Sedang memproses import planning…', 'info', 8000);

        axios.post('/planning/' + window.projectId + '/import', formData)
            .then(function (response) {
                const data = response.data || {};
                const errors = data.errors || [];
                const changed = (data.activities_created || 0) + (data.activities_updated || 0)
                              + (data.groups_created || 0) + (data.phases_created || 0);

                let html = '<p class="font-medium ' + (changed ? 'text-green-800' : 'text-amber-800') + '">'
                         + (data.message || 'Import selesai') + '</p>';
                if (errors.length) {
                    html += '<div class="mt-2 max-h-48 overflow-y-auto border-t border-gray-200 pt-2">'
                          + '<p class="font-medium text-amber-700 mb-1">' + errors.length + ' baris dilewati:</p>'
                          + '<ul class="list-disc pl-5 space-y-0.5 text-xs text-amber-800">'
                          + errors.map(function (e) { return '<li>' + String(e).replace(/</g, '&lt;') + '</li>'; }).join('')
                          + '</ul></div>';
                }
                resultBox.className = 'rounded-md px-4 py-3 text-sm border '
                    + (changed ? 'bg-green-50 border-green-200' : 'bg-amber-50 border-amber-200');
                resultBox.innerHTML = html;
                resultBox.classList.remove('hidden');

                if (changed > 0) {
                    // Data was added/updated → mark dirty so Close/Done refresh; offer "Done".
                    planningImportDirty = true;
                    setPlanningImportPrimary('done');
                    planningImportToast(data.message, errors.length ? 'warning' : 'success', 5000);
                } else {
                    // Nothing created or updated (everything skipped) → let the user fix & retry.
                    setPlanningImportPrimary('retry');
                    planningImportToast(data.message || 'Tidak ada data yang diimpor', 'warning', 6000);
                }
            })
            .catch(function (error) {
                const msg = error.response?.data?.message || 'Gagal mengimpor file. Periksa format CSV.';
                resultBox.className = 'rounded-md px-4 py-3 text-sm bg-red-50 border border-red-200 text-red-800';
                resultBox.innerHTML = '<p class="font-medium">' + String(msg).replace(/</g, '&lt;') + '</p>';
                resultBox.classList.remove('hidden');
                setPlanningImportPrimary('retry');
                planningImportToast(msg, 'error', 6000);
            })
            .finally(function () {
                btn.disabled = false;
                fileInput.disabled = false;
                spinner.classList.add('hidden');
            });
    }
</script>

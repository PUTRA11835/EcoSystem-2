{{-- ====================================================================
     Project Planning — Delete All (reset) Modal
     Wipes Phase → Group → Activity for this project. Guarded by a typed
     confirmation because nothing here is recoverable from the UI.
     ==================================================================== --}}
<div id="planningResetModal"
     class="hidden fixed inset-0 z-[60] overflow-y-auto"
     aria-labelledby="planningResetTitle" role="dialog" aria-modal="true">
    <div class="flex min-h-screen items-center justify-center px-4 py-8">
        {{-- Backdrop --}}
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
             onclick="closePlanningResetModal()"></div>

        {{-- Panel --}}
        <div class="relative bg-white rounded-lg shadow-xl w-full max-w-lg">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                <h3 id="planningResetTitle" class="flex items-center gap-2 text-lg font-semibold text-red-700">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"></path>
                    </svg>
                    Delete All Planning
                </h3>
                <button type="button" onclick="closePlanningResetModal()"
                        class="text-gray-400 hover:text-gray-600">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <div class="px-6 py-5 space-y-4">
                <p class="text-sm text-gray-600">
                    Menghapus <strong>seluruh struktur planning</strong> project
                    <strong>{{ $project->name }}</strong> sampai bersih — termasuk
                    <strong>fase</strong> beserta bobotnya. Project akan kembali kosong
                    seperti sebelum planning pernah dibuat. Tindakan ini
                    <strong class="text-red-700">tidak dapat dibatalkan</strong> — tidak ada undo.
                </p>

                {{-- What will be removed (filled by the preview endpoint) --}}
                <div id="planningResetCounts" class="rounded-md bg-red-50 border border-red-100 px-4 py-3">
                    <p class="text-sm text-red-500">Menghitung data…</p>
                </div>

                {{-- Typed confirmation --}}
                <div>
                    <label for="planningResetConfirm" class="block text-sm font-medium text-gray-900 mb-1">
                        Ketik <code class="px-1 py-0.5 bg-gray-100 rounded text-red-700 font-semibold">DELETE</code> untuk mengonfirmasi
                    </label>
                    <input type="text" id="planningResetConfirm" autocomplete="off" placeholder="DELETE"
                           oninput="syncPlanningResetButton()"
                           class="block w-full text-sm border border-gray-300 rounded-md px-3 py-2
                                  focus:border-red-500 focus:ring-red-500">
                </div>

                {{-- Result --}}
                <div id="planningResetResult" class="hidden rounded-md px-4 py-3 text-sm"></div>
            </div>

            <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-lg">
                <button type="button" onclick="closePlanningResetModal()"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                    Cancel
                </button>
                <button type="button" id="planningResetSubmitBtn" disabled onclick="submitPlanningReset()"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-red-600
                               border border-transparent rounded-md hover:bg-red-700
                               disabled:opacity-40 disabled:cursor-not-allowed">
                    <svg id="planningResetSpinner" class="hidden animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    <span id="planningResetBtnLabel">Delete All</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    // True once the wipe succeeded — closing then reloads the page.
    let planningResetDone = false;

    function openPlanningResetModal() {
        const confirmInput = document.getElementById('planningResetConfirm');
        const result = document.getElementById('planningResetResult');

        confirmInput.value = '';
        confirmInput.disabled = false;
        result.classList.add('hidden');
        result.innerHTML = '';
        planningResetDone = false;
        document.getElementById('planningResetBtnLabel').textContent = 'Delete All';
        syncPlanningResetButton();

        document.getElementById('planningResetModal').classList.remove('hidden');
        loadPlanningResetCounts();
    }

    function closePlanningResetModal() {
        document.getElementById('planningResetModal').classList.add('hidden');
        if (planningResetDone) {
            window.location.reload();
        }
    }

    // Arm the button only on an exact confirmation match.
    function syncPlanningResetButton() {
        const typed = (document.getElementById('planningResetConfirm').value || '').trim().toUpperCase();
        document.getElementById('planningResetSubmitBtn').disabled = (typed !== 'DELETE') || planningResetDone;
    }

    // Show real numbers rather than a vague "are you sure".
    function loadPlanningResetCounts() {
        const box = document.getElementById('planningResetCounts');
        box.innerHTML = '<p class="text-sm text-red-500">Menghitung data…</p>';

        axios.get('/planning/' + window.projectId + '/reset/preview')
            .then(function (response) {
                const c = (response.data || {}).counts || {};
                const activities = c.activities || 0;
                const groups = c.groups || 0;
                const phases = c.phases || 0;

                if (!activities && !groups && !phases) {
                    box.innerHTML = '<p class="text-sm text-red-700">Planning project ini sudah kosong — tidak ada yang dihapus.</p>';
                    return;
                }

                let rows = ''
                    + '<li><strong>' + phases + '</strong> fase</li>'
                    + '<li><strong>' + groups + '</strong> group</li>'
                    + '<li><strong>' + activities + '</strong> activity</li>';
                if (c.members) rows += '<li><strong>' + c.members + '</strong> assignment member</li>';
                if (c.stages)  rows += '<li><strong>' + c.stages + '</strong> stage</li>';

                let html = '<p class="text-sm font-medium text-red-900 mb-1.5">Yang akan dihapus permanen:</p>'
                         + '<ul class="list-disc pl-5 space-y-0.5 text-sm text-red-800">' + rows + '</ul>';

                if (c.timesheets) {
                    html += '<p class="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded px-2 py-1.5 mt-2">'
                          + '<strong>' + c.timesheets + '</strong> timesheet terkait <strong>tidak dihapus</strong>, '
                          + 'tetapi tautannya ke activity akan hilang dan tidak bisa dipulihkan otomatis.</p>';
                }

                box.innerHTML = html;
            })
            .catch(function () {
                box.innerHTML = '<p class="text-sm text-red-700">Gagal memuat rincian data. '
                              + 'Penghapusan tetap bisa dijalankan, namun periksa kembali sebelum melanjutkan.</p>';
            });
    }

    function submitPlanningReset() {
        const btn = document.getElementById('planningResetSubmitBtn');
        const spinner = document.getElementById('planningResetSpinner');
        const btnLabel = document.getElementById('planningResetBtnLabel');
        const resultBox = document.getElementById('planningResetResult');
        const confirmInput = document.getElementById('planningResetConfirm');

        btn.disabled = true;
        confirmInput.disabled = true;
        spinner.classList.remove('hidden');
        btnLabel.textContent = 'Menghapus…';
        resultBox.classList.add('hidden');

        axios.post('/planning/' + window.projectId + '/reset', {
            confirm: confirmInput.value,
            _token: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}'
        })
            .then(function (response) {
                const data = response.data || {};
                planningResetDone = true;

                resultBox.className = 'rounded-md px-4 py-3 text-sm bg-green-50 border border-green-200 text-green-800';
                resultBox.innerHTML = '<p class="font-medium">' + String(data.message || 'Planning dihapus').replace(/</g, '&lt;') + '</p>';
                resultBox.classList.remove('hidden');

                btnLabel.textContent = 'Selesai';
                if (typeof window.showToast === 'function') {
                    window.showToast(data.message, 'success', 5000);
                }

                // Nothing left to act on — send the user back to a fresh page.
                setTimeout(function () { window.location.reload(); }, 1200);
            })
            .catch(function (error) {
                const msg = error.response?.data?.message || 'Gagal menghapus planning. Coba lagi.';
                resultBox.className = 'rounded-md px-4 py-3 text-sm bg-red-50 border border-red-200 text-red-800';
                resultBox.innerHTML = '<p class="font-medium">' + String(msg).replace(/</g, '&lt;') + '</p>';
                resultBox.classList.remove('hidden');

                confirmInput.disabled = false;
                btnLabel.textContent = 'Delete All';
                syncPlanningResetButton();

                if (typeof window.showToast === 'function') {
                    window.showToast(msg, 'error', 6000);
                }
            })
            .finally(function () {
                spinner.classList.add('hidden');
            });
    }
</script>

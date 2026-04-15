<div class="space-y-4">
    <div class="flex items-center justify-between mb-2">
        <h3 class="text-base font-semibold text-gray-900">Customer Credential</h3>
        <div class="flex items-center gap-2">
            <button id="credentialSaveBtn" onclick="saveCredential()"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-red-800 text-white hover:bg-red-900 transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                Save
            </button>
        </div>
    </div>

    <!-- Textarea -->
    <textarea id="credentialTextarea"
        class="w-full min-h-[300px] px-4 py-3 border border-gray-300 rounded-lg text-sm text-gray-800 font-mono leading-relaxed focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent resize-y"
        placeholder="Enter credential notes here...&#10;&#10;e.g.:&#10;Username: admin&#10;Password: xxxxxxxx&#10;https://drive.google.com/..."></textarea>

    <p class="text-xs text-gray-400">Press Ctrl+S to save.</p>

    <!-- Last updated info -->
    <div id="credentialMetaInfo" class="hidden text-xs text-gray-400"></div>
</div>

<script>
    async function loadCredential() {
        try {
            const response = await fetch(`/api/customers/{{ $customerId }}/credential`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const data = await response.json();

            if (data.success && data.credential) {
                document.getElementById('credentialTextarea').value = data.credential.notes ?? '';

                const meta  = document.getElementById('credentialMetaInfo');
                const parts = [];
                if (data.credential.updated_by) parts.push('Last saved by ' + data.credential.updated_by);
                if (data.credential.updated_at) {
                    const d = new Date(data.credential.updated_at);
                    parts.push(d.toLocaleString('en-GB', { timeZone: 'Asia/Jakarta', day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false }));
                }
                if (parts.length) {
                    meta.textContent = parts.join(' — ');
                    meta.classList.remove('hidden');
                }
            }
        } catch (err) {
            console.error('❌ Error loading credential:', err);
        }
    }

    async function saveCredential() {
        const notes   = document.getElementById('credentialTextarea').value;
        const saveBtn = document.getElementById('credentialSaveBtn');

        saveBtn.disabled    = true;
        saveBtn.textContent = 'Saving…';

        try {
            const response = await fetch(`/api/customers/{{ $customerId }}/credential`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ notes }),
            });

            const data = await response.json();

            if (data.success) {
                showNotification('Credential saved successfully', 'success');

                if (data.credential) {
                    const meta  = document.getElementById('credentialMetaInfo');
                    const parts = [];
                    if (data.credential.updated_by) parts.push('Last saved by ' + data.credential.updated_by);
                    if (data.credential.updated_at) {
                        const d = new Date(data.credential.updated_at);
                        parts.push(d.toLocaleString('en-GB', { timeZone: 'Asia/Jakarta', day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false }));
                    }
                    if (parts.length) {
                        meta.textContent = parts.join(' — ');
                        meta.classList.remove('hidden');
                    }
                }
            } else {
                showNotification(data.message || 'Failed to save credential', 'error');
            }
        } catch (err) {
            console.error('❌ Error saving credential:', err);
            showNotification('An error occurred while saving', 'error');
        } finally {
            saveBtn.disabled  = false;
            saveBtn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg> Save`;
        }
    }

    // Ctrl+S to save
    document.addEventListener('keydown', function (e) {
        if (e.ctrlKey && e.key === 's') {
            const section = document.getElementById('section-credential');
            if (section && !section.classList.contains('hidden')) {
                e.preventDefault();
                saveCredential();
            }
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        loadCredential();
    });
</script>

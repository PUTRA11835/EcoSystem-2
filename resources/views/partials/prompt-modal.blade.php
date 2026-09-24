{{--
    Reusable single-line text prompt modal — replaces browser native prompt().

    Usage (returns Promise<string|null>, null if cancelled):
        const code = await showPrompt('Enter your 2FA code', 'Verify Identity', { placeholder: '6-digit code', maxLength: 20 });
        if (code === null) return; // cancelled
--}}
<div id="globalPromptModal" class="hidden fixed inset-0 bg-black/50 z-[9990] flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm mx-4">
        <div class="px-6 pt-6 pb-3">
            <h3 id="globalPromptTitle" class="text-sm font-bold text-gray-900 mb-1">Input Required</h3>
            <p id="globalPromptMessage" class="text-sm text-gray-600 leading-relaxed break-words whitespace-pre-line mb-3"></p>
            <input id="globalPromptInput" type="text" autocomplete="off"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="px-6 pb-5 pt-2 flex gap-2 justify-end">
            <button id="globalPromptCancelBtn" type="button"
                class="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50 transition font-medium">
                Cancel
            </button>
            <button id="globalPromptOkBtn" type="button"
                class="px-4 py-2 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition">
                Submit
            </button>
        </div>
    </div>
</div>

@verbatim
<script>
(function () {
    // Guard: only define once even if partial accidentally loaded twice.
    if (typeof window.showPrompt === 'function') return;

    /**
     * Custom single-line text prompt. Returns Promise<string|null> (null = cancelled).
     * @param {string} message
     * @param {string} [title='Input Required']
     * @param {{placeholder?: string, maxLength?: number, inputMode?: string, okText?: string}} [opts]
     */
    window.showPrompt = function (message, title, opts) {
        title = title || 'Input Required';
        opts  = opts || {};

        return new Promise((resolve) => {
            const modal     = document.getElementById('globalPromptModal');
            const titleEl   = document.getElementById('globalPromptTitle');
            const msgEl     = document.getElementById('globalPromptMessage');
            const inputEl   = document.getElementById('globalPromptInput');
            const okBtn     = document.getElementById('globalPromptOkBtn');
            const cancelBtn = document.getElementById('globalPromptCancelBtn');

            if (!modal) { // fallback if partial missing
                resolve(window.prompt(message));
                return;
            }

            titleEl.textContent = String(title);
            msgEl.textContent   = String(message);
            inputEl.value       = '';
            inputEl.placeholder = opts.placeholder || '';
            inputEl.maxLength   = opts.maxLength || 524288;
            inputEl.inputMode   = opts.inputMode || 'text';
            okBtn.textContent   = opts.okText || 'Submit';

            modal.classList.remove('hidden');
            inputEl.focus();

            function cleanup() {
                modal.classList.add('hidden');
                okBtn.removeEventListener('click', onOk);
                cancelBtn.removeEventListener('click', onCancel);
                modal.removeEventListener('click', onBackdrop);
                document.removeEventListener('keydown', onKey);
            }
            function onOk()        { const v = inputEl.value.trim(); cleanup(); resolve(v); }
            function onCancel()    { cleanup(); resolve(null); }
            function onBackdrop(e) { if (e.target === modal) onCancel(); }
            function onKey(e) {
                if (e.key === 'Escape') onCancel();
                if (e.key === 'Enter')  onOk();
            }

            okBtn.addEventListener('click', onOk);
            cancelBtn.addEventListener('click', onCancel);
            modal.addEventListener('click', onBackdrop);
            document.addEventListener('keydown', onKey);
        });
    };
})();
</script>
@endverbatim

{{--
    Reusable confirm modal — replaces browser native confirm() dialog.

    Usage (returns Promise<boolean>):
        if (await showConfirm('Delete this item?')) { ... }

        if (await showConfirm('Delete this item permanently?', 'Delete Item', 'danger')) { ... }

    Variants: 'default' (gray) | 'primary' (blue) | 'danger' (red)

    For non-async callers, fallback works as a Promise:
        showConfirm('...').then(ok => { if (ok) doIt(); });
--}}
<div id="globalConfirmModal" class="hidden fixed inset-0 bg-black/50 z-[9990] flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm mx-4">
        <div class="px-6 pt-6 pb-3">
            <div class="flex items-start gap-3">
                <div id="globalConfirmIconWrap" class="w-9 h-9 rounded-full flex items-center justify-center shrink-0 mt-0.5"></div>
                <div class="min-w-0">
                    <h3 id="globalConfirmTitle" class="text-sm font-bold text-gray-900 mb-1">Confirm</h3>
                    <p id="globalConfirmMessage" class="text-sm text-gray-600 leading-relaxed break-words whitespace-pre-line"></p>
                </div>
            </div>
        </div>
        <div class="px-6 pb-5 pt-2 flex gap-2 justify-end">
            <button id="globalConfirmCancelBtn" type="button"
                class="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50 transition font-medium">
                Cancel
            </button>
            <button id="globalConfirmOkBtn" type="button"
                class="px-4 py-2 text-sm font-semibold text-white rounded-lg transition">
                OK
            </button>
        </div>
    </div>
</div>

@verbatim
<script>
(function () {
    // Guard: only define once even if partial accidentally loaded twice.
    if (typeof window.showConfirm === 'function') return;

    const ICONS = {
        danger:  '<svg class="w-5 h-5 text-red-600"  fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>',
        primary: '<svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 110 20A10 10 0 0112 2z"/></svg>',
        default: '<svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M12 2a10 10 0 110 20A10 10 0 0112 2z"/></svg>',
    };
    const WRAP_CLS = {
        danger:  'w-9 h-9 rounded-full flex items-center justify-center shrink-0 mt-0.5 bg-red-100',
        primary: 'w-9 h-9 rounded-full flex items-center justify-center shrink-0 mt-0.5 bg-blue-100',
        default: 'w-9 h-9 rounded-full flex items-center justify-center shrink-0 mt-0.5 bg-gray-100',
    };
    const OK_CLS = {
        danger:  'px-4 py-2 text-sm font-semibold text-white bg-red-700 hover:bg-red-800 rounded-lg transition',
        primary: 'px-4 py-2 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition',
        default: 'px-4 py-2 text-sm font-semibold text-white bg-gray-700 hover:bg-gray-800 rounded-lg transition',
    };

    /**
     * Custom confirm dialog. Returns Promise<boolean>.
     * @param {string} message
     * @param {string} [title='Confirm']
     * @param {'default'|'primary'|'danger'} [variant='default']
     * @param {{okText?: string, cancelText?: string}} [opts]
     */
    window.showConfirm = function (message, title, variant, opts) {
        title   = title   || 'Confirm';
        variant = (variant && OK_CLS[variant]) ? variant : 'default';
        opts    = opts || {};

        return new Promise((resolve) => {
            const modal     = document.getElementById('globalConfirmModal');
            const titleEl   = document.getElementById('globalConfirmTitle');
            const msgEl     = document.getElementById('globalConfirmMessage');
            const iconWrap  = document.getElementById('globalConfirmIconWrap');
            const okBtn     = document.getElementById('globalConfirmOkBtn');
            const cancelBtn = document.getElementById('globalConfirmCancelBtn');

            if (!modal) { // fallback if partial missing
                resolve(window.confirm(message));
                return;
            }

            titleEl.textContent = String(title);
            msgEl.textContent   = String(message);
            iconWrap.className  = WRAP_CLS[variant];
            iconWrap.innerHTML  = ICONS[variant];
            okBtn.className     = OK_CLS[variant];
            okBtn.textContent     = opts.okText     || 'OK';
            cancelBtn.textContent = opts.cancelText || 'Cancel';

            modal.classList.remove('hidden');
            okBtn.focus();

            function cleanup() {
                modal.classList.add('hidden');
                okBtn.removeEventListener('click',  onOk);
                cancelBtn.removeEventListener('click', onCancel);
                modal.removeEventListener('click',  onBackdrop);
                document.removeEventListener('keydown', onKey);
            }
            function onOk()       { cleanup(); resolve(true); }
            function onCancel()   { cleanup(); resolve(false); }
            function onBackdrop(e){ if (e.target === modal) onCancel(); }
            function onKey(e) {
                if (e.key === 'Escape') onCancel();
                if (e.key === 'Enter')  onOk();
            }

            okBtn.addEventListener('click',  onOk);
            cancelBtn.addEventListener('click', onCancel);
            modal.addEventListener('click',  onBackdrop);
            document.addEventListener('keydown', onKey);
        });
    };

    /**
     * Confirm helper untuk form yang pakai inline `onsubmit`.
     * Pemakaian: <form onsubmit="return confirmSubmit(this, 'Delete X?', 'Delete', 'danger')">
     * Selalu mengembalikan false (submit asli dibatalkan); form baru di-submit
     * ulang lewat HTMLFormElement.prototype.submit setelah user menekan OK,
     * sehingga handler ini tidak terpanggil dua kali.
     */
    window.confirmSubmit = function (form, message, title, variant) {
        showConfirm(message, title || 'Confirm', variant || 'danger').then(function (ok) {
            if (ok) HTMLFormElement.prototype.submit.call(form);
        });
        return false;
    };
})();
</script>
@endverbatim

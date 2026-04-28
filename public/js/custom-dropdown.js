/**
 * Custom dropdown component — notification-style panels.
 * Usage: add class `custom-dd` to a wrapper div with:
 *   data-onchange="myFunction"  (optional — called after selection)
 *   data-fixed="true"           (optional — use fixed positioning, needed inside overflow:auto modals)
 * Children:
 *   .custom-dd-btn   — the visible button trigger
 *   .custom-dd-label — text inside the button showing current selection
 *   .custom-dd-arrow — chevron SVG that rotates when open
 *   .custom-dd-panel — the dropdown panel (initially hidden)
 *   .custom-dd-item[data-value] — each option row
 *   input[type=hidden] — hidden input that holds the value (read via getElementById)
 */

function initCustomDropdowns(root) {
    const scope = root || document;
    scope.querySelectorAll('.custom-dd').forEach(dd => {
        if (dd._ddInited) return;
        dd._ddInited = true;

        const btn        = dd.querySelector('.custom-dd-btn');
        const panel      = dd.querySelector('.custom-dd-panel');
        const hidden     = dd.querySelector('input[type="hidden"]');
        const items      = dd.querySelectorAll('.custom-dd-item');
        const onchangeFn = dd.dataset.onchange;
        const useFixed   = dd.dataset.fixed === 'true';

        if (!btn || !panel || !hidden) return;

        btn.addEventListener('click', e => {
            e.stopPropagation();
            const isOpen = !panel.classList.contains('hidden');
            _closeAllDropdowns();
            if (!isOpen) {
                if (useFixed) {
                    _positionFixed(btn, panel);
                    document.body.appendChild(panel);
                    panel._ddOwner = dd;
                }
                panel.classList.remove('hidden');
                const arrow = dd.querySelector('.custom-dd-arrow');
                if (arrow) arrow.classList.add('rotate-180');
            }
        });

        items.forEach(item => {
            item.addEventListener('click', e => {
                e.stopPropagation();
                const val  = item.dataset.value;
                const text = item.textContent.trim();
                _selectItem(dd, val, text);
                if (onchangeFn && typeof window[onchangeFn] === 'function') {
                    window[onchangeFn]();
                }
            });
        });
    });

    if (!window._customDdListenerAdded) {
        window._customDdListenerAdded = true;
        document.addEventListener('click', _closeAllDropdowns);
        window.addEventListener('scroll', _onScrollMaybeClose, true);
        window.addEventListener('resize', _closeAllDropdowns);
    }
}

function _onScrollMaybeClose(e) {
    // Jangan tutup kalau scroll terjadi di dalam panel dropdown (user sedang scroll opsi).
    // Tetap tutup untuk scroll di luar (jaga posisi tombol vs panel, terutama mode fixed).
    const t = e.target;
    if (t && t.nodeType === 1 && t.closest && t.closest('.custom-dd-panel')) return;
    _closeAllDropdowns();
}

function _positionFixed(btn, panel) {
    const r = btn.getBoundingClientRect();
    panel.style.cssText = `position:fixed;top:${r.bottom + 4}px;left:${r.left}px;width:${r.width}px;z-index:9999;`;
}

function _selectItem(dd, val, text) {
    const panel  = dd.querySelector('.custom-dd-panel') || document.querySelector(`.custom-dd-panel[data-dd-id="${dd.dataset.ddId}"]`);
    const label  = dd.querySelector('.custom-dd-label');
    const arrow  = dd.querySelector('.custom-dd-arrow');
    const hidden = dd.querySelector('input[type="hidden"]');
    const items  = dd.querySelectorAll('.custom-dd-item');

    if (hidden) hidden.value = val;

    if (label) {
        label.textContent = text;
        label.className   = val ? 'custom-dd-label text-gray-700' : 'custom-dd-label text-gray-500';
    }

    items.forEach(i => i.classList.remove('bg-gray-50', 'font-medium', 'text-gray-900'));
    const active = dd.querySelector(`.custom-dd-item[data-value="${CSS.escape(val)}"]`);
    if (active && val) active.classList.add('bg-gray-50', 'font-medium', 'text-gray-900');

    if (panel) {
        panel.classList.add('hidden');
        // Re-attach to original owner if detached via fixed mode
        if (panel._ddOwner && panel.parentElement !== panel._ddOwner) {
            panel._ddOwner.appendChild(panel);
            panel.style.cssText = '';
        }
    }
    if (arrow) arrow.classList.remove('rotate-180');
}

function _closeAllDropdowns() {
    document.querySelectorAll('.custom-dd-panel:not(.hidden)').forEach(p => {
        p.classList.add('hidden');
        const owner = p._ddOwner || p.closest('.custom-dd');
        if (owner) {
            const arrow = owner.querySelector('.custom-dd-arrow');
            if (arrow) arrow.classList.remove('rotate-180');
            // Re-attach fixed panel back to its owner
            if (p._ddOwner && p.parentElement !== p._ddOwner) {
                p._ddOwner.appendChild(p);
                p.style.cssText = '';
            }
        }
    });
}

/**
 * Programmatically set a custom dropdown's value and update its label.
 * @param {string} hiddenId  — the id of the hidden input inside the dropdown
 * @param {string} value     — the value to select ('' for the "All …" option)
 */
function setCustomDropdownValue(hiddenId, value) {
    const hidden = document.getElementById(hiddenId);
    if (!hidden) return;
    const dd   = hidden.closest('.custom-dd');
    if (!dd)    return;
    const item = dd.querySelector(`.custom-dd-item[data-value="${CSS.escape(value)}"]`);
    const text = item ? item.textContent.trim()
                      : (dd.querySelector('.custom-dd-item[data-value=""]')?.textContent.trim() || '');
    _selectItem(dd, value, text);
}

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

// Threshold item count untuk otomatis menampilkan search input.
// Dropdown dengan ≤ jumlah ini tidak perlu search (mis. Type, Priority, Status).
// Bisa di-override per dropdown via data-searchable="true|false".
const _DD_SEARCH_THRESHOLD = 7;

function initCustomDropdowns(root) {
    const scope = root || document;
    scope.querySelectorAll('.custom-dd').forEach(dd => {
        if (dd._ddInited) return;
        dd._ddInited = true;

        const btn      = dd.querySelector('.custom-dd-btn');
        const panel    = dd.querySelector('.custom-dd-panel');
        const hidden   = dd.querySelector('input[type="hidden"]');
        const useFixed = dd.dataset.fixed === 'true';

        if (!btn || !panel || !hidden) return;

        // Store panel ref so _selectItem can find it even when detached (fixed mode)
        dd._ddPanel = panel;

        // Wire up search input. Tiga kondisi yang trigger:
        // 1. Hardcoded di HTML (`<input class="custom-dd-search">` sudah ada di markup)
        //    — selalu wire up, tidak peduli threshold. Ini pattern yang dipakai untuk
        //    panel yang programmer ingin selalu punya search regardless of item count.
        // 2. data-searchable="true" eksplisit di .custom-dd
        // 3. Auto-inject jika item count > _DD_SEARCH_THRESHOLD (dan tidak data-searchable="false")
        const explicit           = dd.dataset.searchable;
        const itemCount          = panel.querySelectorAll('.custom-dd-item').length;
        const hasHardcodedSearch = !!panel.querySelector('.custom-dd-search');
        const wantSearch = hasHardcodedSearch
            || explicit === 'true'
            || (explicit !== 'false' && itemCount > _DD_SEARCH_THRESHOLD);
        if (wantSearch) {
            _injectSearch(dd, panel);
        }

        btn.addEventListener('click', e => {
            e.stopPropagation();
            const isOpen = !panel.classList.contains('hidden');
            _closeAllDropdowns();
            if (!isOpen) {
                if (useFixed) {
                    // Order: detach ke body → set inline positioning (panel masih
                    // hidden, tidak masalah karena _positionFixed pakai estimasi
                    // tinggi dari inline max-height, bukan scrollHeight) → lepas
                    // class hidden. Saat panel jadi visible, sudah di posisi
                    // benar — tidak ada flash di posisi salah.
                    document.body.appendChild(panel);
                    panel._ddOwner = dd;
                    _positionFixed(btn, panel);
                }
                panel.classList.remove('hidden');
                const arrow = dd.querySelector('.custom-dd-arrow');
                if (arrow) arrow.classList.add('rotate-180');

                // Auto-focus search input setelah panel render.
                if (panel._ddSearch) {
                    requestAnimationFrame(() => panel._ddSearch.focus());
                }
            }
        });

        // Panel-level event delegation — handles both static and dynamically injected items
        panel.addEventListener('click', e => {
            const item = e.target.closest('.custom-dd-item');
            if (!item) return;
            e.stopPropagation();
            const val        = item.dataset.value;
            const text       = item.textContent.trim();
            const owner      = panel._ddOwner || dd;
            const onchangeFn = owner.dataset.onchange;
            _selectItem(owner, val, text);
            if (onchangeFn && typeof window[onchangeFn] === 'function') {
                window[onchangeFn]();
            }
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
    // Scroll di dalam panel itu sendiri (user sedang scroll opsi) → biarkan.
    const t = e.target;
    if (t && t.nodeType === 1 && t.closest && t.closest('.custom-dd-panel')) return;

    // Untuk panel mode fixed, REPOSISI mengikuti tombol bukan tutup — UX lebih
    // baik & tidak frustrating saat user scroll halaman dengan dropdown terbuka.
    // Kalau tombol sudah keluar viewport, baru tutup karena panel jadi
    // "ngambang" lepas dari konteksnya.
    let repositioned = false;
    document.querySelectorAll('.custom-dd-panel:not(.hidden)').forEach(p => {
        const owner = p._ddOwner;
        // Hanya panel mode fixed yang punya _ddOwner & sudah pindah ke <body>
        if (!owner || p.parentElement !== document.body) return;
        const btn = owner.querySelector('.custom-dd-btn');
        if (!btn) return;
        const r = btn.getBoundingClientRect();
        // Tombol keluar viewport → tutup
        if (r.bottom < 0 || r.top > window.innerHeight) {
            _closeAllDropdowns();
            return;
        }
        // Update posisi panel mengikuti tombol
        p.style.top  = `${r.bottom + 4}px`;
        p.style.left = `${r.left}px`;
        repositioned = true;
    });
    // Untuk panel mode non-fixed (panel masih di dalam .custom-dd) → tetap tutup
    // karena `position:absolute` relatif ke wrapper sehingga tidak ada masalah
    // posisi, tapi page scroll biasanya berarti user pindah konteks.
    if (!repositioned) {
        document.querySelectorAll('.custom-dd-panel:not(.hidden)').forEach(p => {
            // Skip yang sudah di-detach (sudah di-handle di atas)
            if (p.parentElement === document.body) return;
            _closeDropdown(p);
        });
    }
}

function _positionFixed(btn, panel) {
    const r = btn.getBoundingClientRect();

    // Simpan inline max-height ASLI (dari markup) sekali saja.
    if (panel._origMaxHeight === undefined) {
        panel._origMaxHeight = panel.style.maxHeight || '';
    }

    // Set core fixed positioning properties.
    panel.style.position = 'fixed';
    panel.style.left     = `${r.left}px`;
    panel.style.width    = `${r.width}px`;
    panel.style.zIndex   = '9999';

    // Estimasi tinggi panel hanya dari inline max-height (lebih reliable
    // daripada scrollHeight yang bisa 0 di edge case render). Default 280
    // kalau markup tidak set max-height.
    const margin     = 8;
    const spaceBelow = window.innerHeight - r.bottom - margin;
    const spaceAbove = r.top - margin;
    const estimateH  = parseInt(panel._origMaxHeight, 10) || 280;

    // Reset max-height ke nilai asli sebelum hitung penempatan.
    panel.style.maxHeight = panel._origMaxHeight;

    // PENTING: saat flip ke atas (set bottom), kita HARUS set `top: auto` —
    // bukan `top: ''`. Markup punya class Tailwind `top-full` (top:100%) yang
    // jadi aktif kembali begitu inline top di-clear, dan kombinasi `top:100%`
    // + `bottom:Ypx` → panel height jadi 0 → invisible.
    // Sebaliknya, saat tampil di bawah (set top), `bottom: auto` mencegah
    // residual inline bottom dari run sebelumnya.
    //
    // Pilih sisi & cap tinggi:
    // 1. Cukup di bawah → tampil di bawah dengan max-height asli
    // 2. Tidak cukup di bawah tapi cukup di atas → flip ke atas
    // 3. Dua-duanya sempit → pilih yang lebih luas + cap max-height ke ruang itu
    if (estimateH <= spaceBelow) {
        panel.style.top    = `${r.bottom + 4}px`;
        panel.style.bottom = 'auto';
    } else if (estimateH <= spaceAbove) {
        panel.style.top    = 'auto';
        panel.style.bottom = `${window.innerHeight - r.top + 4}px`;
    } else {
        // Dua-duanya sempit — pilih sisi yang lebih luas, cap max-height
        const cap = Math.max(120, Math.max(spaceBelow, spaceAbove));
        if (spaceBelow >= spaceAbove) {
            panel.style.top    = `${r.bottom + 4}px`;
            panel.style.bottom = 'auto';
        } else {
            panel.style.top    = 'auto';
            panel.style.bottom = `${window.innerHeight - r.top + 4}px`;
        }
        panel.style.maxHeight = `${cap}px`;
    }
}

// Setup sticky search input di atas panel + real-time filter.
// Dipanggil saat init untuk dropdown dengan item count > threshold
// atau yang punya data-searchable="true".
//
// Mendukung dua mode:
// 1. Hardcoded — jika di HTML panel sudah ada `<input class="custom-dd-search">`
//    (mis. dibuat manual di Blade), fungsi ini cukup wire up event filter.
// 2. Auto-inject — jika belum ada, fungsi ini buat sticky search bar di atas panel.
function _injectSearch(dd, panel) {
    if (panel._ddSearch) return; // already wired

    // Cek apakah search input sudah di-hardcode di HTML
    let input = panel.querySelector('.custom-dd-search');
    let empty = panel.querySelector('.custom-dd-empty');

    if (!input) {
        // Auto-inject: buat sticky search bar
        const placeholder = dd.dataset.searchPlaceholder || 'Search…';

        const wrap = document.createElement('div');
        wrap.className = 'custom-dd-search-wrap sticky top-0 bg-white border-b border-gray-100 px-2 py-2';
        wrap.style.zIndex = '1';

        input = document.createElement('input');
        input.type = 'text';
        input.placeholder = placeholder;
        input.className = 'custom-dd-search w-full px-3 py-1.5 text-sm border border-gray-200 rounded-md focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400';
        input.setAttribute('autocomplete', 'off');
        input.setAttribute('spellcheck', 'false');

        wrap.appendChild(input);
        panel.insertBefore(wrap, panel.firstChild);
    }

    if (!empty) {
        // Empty-state placeholder ketika tidak ada item yang cocok
        empty = document.createElement('div');
        empty.className = 'custom-dd-empty hidden px-4 py-3 text-sm text-gray-400 text-center';
        empty.textContent = 'No results';
        panel.appendChild(empty);
    }

    // Cegah klik di search input meng-trigger document click handler
    // (yang akan menutup dropdown via _closeAllDropdowns).
    input.addEventListener('click',     e => e.stopPropagation());
    input.addEventListener('mousedown', e => e.stopPropagation());
    input.addEventListener('keydown', e => {
        if (e.key === 'Enter')  e.preventDefault();   // jangan submit form
        if (e.key === 'Escape') _closeAllDropdowns(); // tutup panel
    });

    // Real-time filter — match by item textContent
    input.addEventListener('input', () => {
        const q = input.value.trim().toLowerCase();
        let visible = 0;
        panel.querySelectorAll('.custom-dd-item').forEach(item => {
            const val = item.dataset.value;
            // Saat search aktif, sembunyikan placeholder option (data-value="")
            if (val === '' && q !== '') {
                item.style.display = 'none';
                return;
            }
            const text  = item.textContent.toLowerCase();
            const match = !q || text.includes(q);
            item.style.display = match ? '' : 'none';
            if (match && val !== '') visible++;
        });
        empty.classList.toggle('hidden', !q || visible > 0);
    });

    panel._ddSearch = input;
    panel._ddEmpty  = empty;
}

// Helper: bersihkan property fixed-mode dan restore max-height asli markup.
function _clearFixedStyles(panel) {
    panel.style.position = '';
    panel.style.top      = '';
    panel.style.bottom   = '';
    panel.style.left     = '';
    panel.style.width    = '';
    panel.style.zIndex   = '';
    // Restore max-height asli (yang dari markup `style="max-height:..."`).
    // Jika _positionFixed sempat override, sini kembalikan ke nilai semula.
    if (panel._origMaxHeight !== undefined) {
        panel.style.maxHeight = panel._origMaxHeight;
    }
}

// Helper: reset search input + munculkan kembali semua item saat panel ditutup.
// Tanpa ini, search filter sebelumnya akan tetap aktif saat panel dibuka lagi.
function _resetSearchState(panel) {
    if (!panel._ddSearch) return;
    panel._ddSearch.value = '';
    panel.querySelectorAll('.custom-dd-item').forEach(item => {
        // Jangan ubah item yang di-hide oleh logika eksternal (.hidden class).
        if (item.classList.contains('hidden')) return;
        item.style.display = '';
    });
    if (panel._ddEmpty) panel._ddEmpty.classList.add('hidden');
}

// Helper: tutup satu panel (dipakai oleh _onScrollMaybeClose untuk panel non-fixed)
function _closeDropdown(p) {
    p.classList.add('hidden');
    _resetSearchState(p);
    const owner = p._ddOwner || p.closest('.custom-dd');
    if (owner) {
        const arrow = owner.querySelector('.custom-dd-arrow');
        if (arrow) arrow.classList.remove('rotate-180');
        if (p._ddOwner && p.parentElement !== p._ddOwner) {
            p._ddOwner.appendChild(p);
            _clearFixedStyles(p);
        }
    }
}

function _selectItem(dd, val, text) {
    // Panel may be detached from dd when fixed mode is active — use stored ref
    const panel  = dd.querySelector('.custom-dd-panel') || dd._ddPanel;
    const label  = dd.querySelector('.custom-dd-label');
    const arrow  = dd.querySelector('.custom-dd-arrow');
    const hidden = dd.querySelector('input[type="hidden"]');

    if (hidden) hidden.value = val;

    if (label) {
        label.textContent = text;
        label.className   = val ? 'custom-dd-label text-gray-700' : 'custom-dd-label text-gray-500';
    }

    if (panel) {
        panel.querySelectorAll('.custom-dd-item').forEach(i => i.classList.remove('bg-gray-50', 'font-medium', 'text-gray-900'));
        if (val) {
            const active = panel.querySelector(`.custom-dd-item[data-value="${CSS.escape(val)}"]`);
            if (active) active.classList.add('bg-gray-50', 'font-medium', 'text-gray-900');
        }
    }

    if (panel) {
        panel.classList.add('hidden');
        _resetSearchState(panel);
        // Re-attach to original owner if detached via fixed mode
        if (panel._ddOwner && panel.parentElement !== panel._ddOwner) {
            panel._ddOwner.appendChild(panel);
            // Hanya bersihkan property fixed-mode — pertahankan inline style asli
            // (mis. `style="max-height:280px"`) supaya tidak hilang saat reuse.
            _clearFixedStyles(panel);
        }
    }
    if (arrow) arrow.classList.remove('rotate-180');
}

function _closeAllDropdowns() {
    document.querySelectorAll('.custom-dd-panel:not(.hidden)').forEach(p => {
        p.classList.add('hidden');
        _resetSearchState(p);
        const owner = p._ddOwner || p.closest('.custom-dd');
        if (owner) {
            const arrow = owner.querySelector('.custom-dd-arrow');
            if (arrow) arrow.classList.remove('rotate-180');
            // Re-attach fixed panel back to its owner
            if (p._ddOwner && p.parentElement !== p._ddOwner) {
                p._ddOwner.appendChild(p);
                _clearFixedStyles(p);
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
    const dd    = hidden.closest('.custom-dd');
    if (!dd)     return;
    const panel = dd.querySelector('.custom-dd-panel') || dd._ddPanel;
    const item  = panel?.querySelector(`.custom-dd-item[data-value="${CSS.escape(value)}"]`);
    const text  = item ? item.textContent.trim()
                       : (panel?.querySelector('.custom-dd-item[data-value=""]')?.textContent.trim() || '');
    _selectItem(dd, value, text);
}

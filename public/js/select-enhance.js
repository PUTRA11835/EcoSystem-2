/**
 * Native <select> auto-enhancer.
 *
 * Membungkus setiap <select> di halaman dengan UI bergaya custom-dd
 * (button + panel + chevron animasi) tanpa mengubah element aslinya.
 *
 * Element <select> tetap berada di DOM (visually hidden via CSS) supaya:
 *   - Form submission tetap mengirim value
 *   - Kode lama yang membaca el.value, set el.value, atau dispatch event 'change'
 *     tetap berfungsi tanpa modifikasi
 *   - Library lain yang manipulasi <option> (AJAX populate, dsb.) tetap bekerja;
 *     enhancer ikut sinkron via MutationObserver
 *
 * Opt-out: tambahkan atribut `data-no-enhance` di <select>, atau buat <select multiple>.
 *
 * Public API:
 *   window.enhanceSelects(scope?)  — Re-scan DOM (atau scope tertentu) dan bind
 *                                    select baru. Aman dipanggil berulang.
 */
(function () {
    'use strict';

    const ENHANCED_FLAG  = '_seEnhanced';
    const SKIP_ATTR      = 'data-no-enhance';
    const PANEL_MAX_PX   = 240;

    // ── CSS injection (idempotent) ────────────────────────────────────────────
    function injectStyles() {
        if (document.getElementById('select-enhance-styles')) return;
        const css = `
            /* Sembunyikan <select> asli, tapi pertahankan focus & form behavior */
            select.se-hidden {
                position: absolute !important;
                opacity: 0 !important;
                pointer-events: none !important;
                width: 1px !important;
                height: 1px !important;
                margin: 0 !important;
                padding: 0 !important;
                border: 0 !important;
                left: 0; top: 0;
            }
            .se-wrap { position: relative; display: block; width: 100%; }
            .se-btn {
                width: 100%;
                display: flex; align-items: center; justify-content: space-between;
                padding: 0.5rem 0.75rem;
                background: #fff;
                border: 1px solid #d1d5db;
                border-radius: 0.5rem;
                font-size: 0.875rem;
                color: #374151;
                cursor: pointer;
                text-align: left;
                transition: border-color 0.15s ease, box-shadow 0.15s ease;
            }
            .se-btn:hover  { border-color: #9ca3af; }
            .se-btn:focus  { outline: none; border-color: transparent; box-shadow: 0 0 0 2px #991b1b; }
            .se-btn[disabled] { background: #f3f4f6; cursor: not-allowed; opacity: 0.7; }
            .se-label { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
            .se-label.is-placeholder { color: #9ca3af; }
            .se-arrow {
                width: 1rem; height: 1rem; color: #9ca3af; flex-shrink: 0; margin-left: 0.5rem;
                transition: transform 0.2s ease;
            }
            .se-wrap.is-open .se-arrow { transform: rotate(180deg); }
            .se-panel {
                position: absolute;
                top: 100%; left: 0; right: 0;
                margin-top: 0.375rem;
                background: #fff;
                border: 1px solid #f3f4f6;
                border-radius: 0.75rem;
                box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
                z-index: 50;
                padding: 0.375rem 0;
                max-height: ${PANEL_MAX_PX}px;
                overflow-y: auto;
                display: none;
                opacity: 0;
                transform: translateY(-4px);
                transition: opacity 0.15s ease, transform 0.15s ease;
            }
            .se-wrap.is-open .se-panel {
                display: block;
                opacity: 1;
                transform: translateY(0);
            }
            /* Panel terbuka ke atas saat ruang bawah tidak cukup */
            .se-wrap.opens-up .se-panel {
                top: auto;
                bottom: 100%;
                margin-top: 0;
                margin-bottom: 0.375rem;
                transform: translateY(4px);
            }
            .se-wrap.opens-up.is-open .se-panel {
                transform: translateY(0);
            }
            .se-item {
                display: block; width: 100%;
                padding: 0.5rem 1rem;
                font-size: 0.875rem;
                color: #4b5563;
                text-align: left;
                background: transparent;
                border: 0;
                cursor: pointer;
                transition: background-color 0.1s ease;
            }
            .se-item:hover { background: #f9fafb; }
            .se-item.is-active { background: #f9fafb; color: #111827; font-weight: 500; }
            .se-item.is-disabled { color: #d1d5db; cursor: not-allowed; }
            .se-item.is-disabled:hover { background: transparent; }
            /* Mode panel fixed (untuk select di dalam container overflow:auto/hidden).
               Saat panel di-detach ke <body>, selector descendant
               \`.se-wrap.is-open .se-panel\` tidak match lagi — jadi state visible
               harus dideklarasi ulang via class .is-fixed agar panel benar-benar
               muncul. Tanpa rule ini, panel akan tetap display:none meski sudah
               di-detach (penyebab "dropdown tidak ada value" di banyak halaman). */
            .se-panel.is-fixed {
                position: fixed;
                left: auto; right: auto;
                width: auto;
                margin-top: 0;
                display: block;
                opacity: 1;
                transform: translateY(0);
            }
        `;
        const style = document.createElement('style');
        style.id = 'select-enhance-styles';
        style.textContent = css;
        document.head.appendChild(style);
    }

    // ── Util ──────────────────────────────────────────────────────────────────
    function shouldSkip(sel) {
        if (sel[ENHANCED_FLAG]) return true;
        if (sel.multiple)        return true;
        if (sel.size > 1)        return true;
        if (sel.hasAttribute(SKIP_ATTR)) return true;
        // Skip select yang sudah berada di dalam wrapper custom-dd manual,
        // atau di dalam wrapper yang sudah kita generate
        if (sel.closest('.custom-dd, .se-wrap')) return true;
        // Skip select milik Quill: toolbar picker (mis. ql-header, ql-color)
        // dikelola Quill sendiri untuk diubah menjadi .ql-picker UI. Kalau kita
        // enhance, hasilnya double widget yang menumpuk di bawah picker Quill.
        if (sel.className && typeof sel.className === 'string' && /\bql-/.test(sel.className)) return true;
        if (sel.closest('.ql-toolbar, .ql-container, .ql-editor')) return true;
        return false;
    }

    function buildWrap(sel) {
        const wrap = document.createElement('div');
        wrap.className = 'se-wrap';

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'se-btn';
        btn.setAttribute('aria-haspopup', 'listbox');
        btn.setAttribute('aria-expanded', 'false');
        if (sel.disabled) btn.disabled = true;

        const label = document.createElement('span');
        label.className = 'se-label';

        // Chevron sama dengan custom-dd
        const arrow = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        arrow.setAttribute('class', 'se-arrow');
        arrow.setAttribute('fill', 'none');
        arrow.setAttribute('stroke', 'currentColor');
        arrow.setAttribute('viewBox', '0 0 24 24');
        arrow.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>';

        btn.appendChild(label);
        btn.appendChild(arrow);

        const panel = document.createElement('div');
        panel.className = 'se-panel';
        panel.setAttribute('role', 'listbox');

        wrap.appendChild(btn);
        wrap.appendChild(panel);
        return { wrap, btn, label, panel };
    }

    function renderItems(sel, panel, label) {
        panel.innerHTML = '';
        const opts = Array.from(sel.options);
        const selectedOpt = opts[sel.selectedIndex] ?? null;

        opts.forEach((opt, idx) => {
            // Sebagian dropdown punya placeholder berupa option pertama dengan value="".
            // Kita tetap render itu sebagai pilihan agar user bisa "kosongkan" filter.
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'se-item';
            if (opt.disabled) item.classList.add('is-disabled');
            if (idx === sel.selectedIndex) item.classList.add('is-active');
            item.dataset.index = String(idx);
            item.textContent = opt.textContent.trim() || ' ';
            item.title = opt.textContent.trim();
            panel.appendChild(item);
        });

        // Update label dari selected option
        if (selectedOpt) {
            label.textContent = selectedOpt.textContent.trim() || selectedOpt.value || '—';
            // Tandai placeholder bila value kosong DAN ada lebih dari satu opsi
            label.classList.toggle('is-placeholder',
                selectedOpt.value === '' && opts.length > 1 && sel.selectedIndex === 0);
        } else {
            label.textContent = '—';
            label.classList.add('is-placeholder');
        }
    }

    // ── Search injection (opt-in: data-searchable="true" pada <select>) ─────────
    function injectSearch(sel, panel) {
        if (panel._seSearch) return;

        const searchWrap = document.createElement('div');
        searchWrap.style.cssText = 'position:sticky;top:0;background:#fff;border-bottom:1px solid #f3f4f6;padding:0.375rem 0.5rem;z-index:1;';

        const input = document.createElement('input');
        input.type = 'text';
        input.placeholder = sel.dataset.searchPlaceholder || 'Search...';
        input.style.cssText = 'width:100%;padding:0.375rem 0.625rem;font-size:0.8125rem;border:1px solid #e5e7eb;border-radius:0.375rem;outline:none;box-sizing:border-box;';
        input.addEventListener('focus', () => { input.style.borderColor = '#991b1b'; input.style.boxShadow = '0 0 0 2px rgba(153,27,27,0.1)'; });
        input.addEventListener('blur',  () => { input.style.borderColor = '#e5e7eb'; input.style.boxShadow = ''; });

        searchWrap.appendChild(input);
        panel.insertBefore(searchWrap, panel.firstChild);

        const empty = document.createElement('div');
        empty.style.cssText = 'display:none;padding:0.75rem 1rem;font-size:0.875rem;color:#9ca3af;text-align:center;';
        empty.textContent = 'No results';
        panel.appendChild(empty);

        // Cegah klik/mousedown search input menutup panel
        input.addEventListener('click',     e => e.stopPropagation());
        input.addEventListener('mousedown', e => e.stopPropagation());

        // Real-time filter
        input.addEventListener('input', () => {
            const q = input.value.trim().toLowerCase();
            let visible = 0;
            panel.querySelectorAll('.se-item').forEach(item => {
                const idx = Number(item.dataset.index);
                const opt = sel.options[idx];
                if (!opt) return;
                if (opt.value === '' && q !== '') { item.style.display = 'none'; return; }
                const match = !q || item.textContent.toLowerCase().includes(q);
                item.style.display = match ? '' : 'none';
                if (match && opt.value !== '') visible++;
            });
            empty.style.display = (q && visible === 0) ? '' : 'none';
        });

        // Keyboard: Escape tutup, Enter pilih satu-satunya hasil
        input.addEventListener('keydown', e => {
            if (e.key === 'Escape') { e.preventDefault(); closeOpen(); return; }
            if (e.key === 'Enter') {
                e.preventDefault();
                const visible = Array.from(panel.querySelectorAll('.se-item')).filter(i => i.style.display !== 'none');
                if (visible.length === 1) visible[0].click();
            }
        });

        panel._seSearch = input;
        panel._seEmpty  = empty;
    }

    function resetSearch(panel) {
        if (!panel._seSearch) return;
        panel._seSearch.value = '';
        panel.querySelectorAll('.se-item').forEach(i => { i.style.display = ''; });
        if (panel._seEmpty) panel._seEmpty.style.display = 'none';
    }

    let openWrap = null;
    function closeOpen() {
        if (!openWrap) return;
        openWrap.classList.remove('is-open', 'opens-up');
        const btn = openWrap.querySelector('.se-btn');
        if (btn) btn.setAttribute('aria-expanded', 'false');
        // Kalau panel di-detach ke body (mode fixed), kembalikan ke wrapper
        const panel = openWrap._sePanel;
        if (panel) {
            resetSearch(panel);
            if (panel._seDetached) {
                openWrap.appendChild(panel);
                panel.classList.remove('is-fixed');
                panel.style.cssText = '';
                panel._seDetached = false;
            }
        }
        openWrap = null;
    }

    function isInsideOverflow(el) {
        // Deteksi parent dengan overflow auto/hidden/scroll yang bisa memotong panel
        let p = el.parentElement;
        while (p && p !== document.body) {
            const cs = getComputedStyle(p);
            if (/(auto|scroll|hidden)/.test(cs.overflowY) || /(auto|scroll|hidden)/.test(cs.overflowX)) {
                if (p.scrollHeight > p.clientHeight || p.scrollWidth > p.clientWidth || cs.overflowY === 'hidden' || cs.overflowX === 'hidden') {
                    return true;
                }
            }
            p = p.parentElement;
        }
        return false;
    }

    function positionFixedPanel(btn, panel) {
        const r = btn.getBoundingClientRect();
        const spaceBelow = window.innerHeight - r.bottom;
        const placeAbove = spaceBelow < (PANEL_MAX_PX + 16) && r.top > spaceBelow;
        panel.classList.add('is-fixed');
        panel.style.cssText = `
            position:fixed;
            left:${r.left}px;
            width:${r.width}px;
            ${placeAbove ? `bottom:${window.innerHeight - r.top + 4}px;` : `top:${r.bottom + 4}px;`}
            z-index:9999;
        `;
    }

    function openWrapEl(wrap, sel) {
        closeOpen();
        wrap.classList.add('is-open');
        const btn   = wrap.querySelector('.se-btn');
        const panel = wrap._sePanel;
        btn.setAttribute('aria-expanded', 'true');

        // Kalau wrapper berada di dalam overflow container, detach panel ke body
        // dengan posisi fixed agar tidak terpotong.
        if (isInsideOverflow(wrap)) {
            document.body.appendChild(panel);
            panel._seDetached = true;
            positionFixedPanel(btn, panel);
        } else {
            // Mode absolute biasa: deteksi apakah ruang bawah cukup, jika tidak
            // buka ke atas.
            const btnRect = btn.getBoundingClientRect();
            const spaceBelow = window.innerHeight - btnRect.bottom;
            const placeAbove = spaceBelow < (PANEL_MAX_PX + 16) && btnRect.top > spaceBelow;
            wrap.classList.toggle('opens-up', placeAbove);
        }

        // Auto-focus search input bila ada
        if (panel._seSearch) {
            requestAnimationFrame(() => panel._seSearch.focus());
        }

        // Scroll active item into view
        const active = panel.querySelector('.se-item.is-active');
        if (active) {
            const t = active.offsetTop;
            const ph = panel.clientHeight;
            const ah = active.offsetHeight;
            if (t < panel.scrollTop || t + ah > panel.scrollTop + ph) {
                panel.scrollTop = Math.max(0, t - (ph / 2) + (ah / 2));
            }
        }

        openWrap = wrap;
    }

    // ── Hide decorative icons sibling dari select asli ────────────────────────
    // Banyak halaman EcoSystem menempel <i class="fa-bars/chevron/caret/sort">
    // sebagai indikator dropdown manual yang diposisikan absolut di sebelah
    // <select>. Setelah kita pasang chevron sendiri, icon-icon tersebut harus
    // disembunyikan supaya tidak menumpuk.
    const DECORATIVE_ICON_RE = /\bfa-(bars|chevron-(down|up)|caret-(down|up)|sort|angle-(down|up))\b/;
    function hideDecorativeIcons(originalParent) {
        if (!originalParent) return;
        // Hanya scan anak langsung untuk menghindari false-positive di dalam panel.
        Array.from(originalParent.children).forEach((child) => {
            if (child.classList && child.classList.contains('se-wrap')) return;
            // Pola 1: <i class="fa-bars ... absolute ... pointer-events-none">
            if (child.tagName === 'I' && DECORATIVE_ICON_RE.test(child.className)) {
                if (/absolute|fixed/.test(child.className) || /(absolute|fixed)/.test(getComputedStyle(child).position)) {
                    child.style.display = 'none';
                    child.dataset.seHidden = '1';
                }
                return;
            }
            // Pola 2: <div class="absolute right-* pointer-events-none"><svg|i></div>
            if (child.tagName === 'DIV' && /absolute/.test(child.className) && /pointer-events-none/.test(child.className)) {
                // Pastikan ini diposisikan di sisi kanan (indikator dropdown), bukan kiri (icon search)
                if (/\bright-/.test(child.className) || /\binset-y-0\b.*\bright-/.test(child.className)) {
                    child.style.display = 'none';
                    child.dataset.seHidden = '1';
                }
            }
        });
    }

    // ── Enhance one <select> ──────────────────────────────────────────────────
    function enhance(sel) {
        if (shouldSkip(sel)) return;

        const originalParent = sel.parentNode;
        const { wrap, btn, label, panel } = buildWrap(sel);

        // Insert wrapper sebelum select, lalu pindahkan select ke dalam wrap
        originalParent.insertBefore(wrap, sel);
        wrap.insertBefore(sel, btn);    // select duluan, baru button (z-order tidak penting)
        sel.classList.add('se-hidden');
        sel[ENHANCED_FLAG] = true;
        wrap._seSelect = sel;
        wrap._sePanel  = panel;

        // Bersihkan icon dekorasi yang dipasang manual di parent asli
        hideDecorativeIcons(originalParent);

        // Render awal
        renderItems(sel, panel, label);

        // Inject search bila diminta
        if (sel.dataset.searchable === 'true') {
            injectSearch(sel, panel);
        }

        // Open/close lewat tombol
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            if (sel.disabled) return;
            const isOpen = wrap.classList.contains('is-open');
            if (isOpen) closeOpen();
            else        openWrapEl(wrap, sel);
        });

        // Klik item → set value & dispatch change
        panel.addEventListener('click', (e) => {
            const item = e.target.closest('.se-item');
            if (!item || item.classList.contains('is-disabled')) return;
            e.stopPropagation();
            const idx = Number(item.dataset.index);
            if (sel.selectedIndex !== idx) {
                sel.selectedIndex = idx;
                // Trigger change supaya kode legacy (onchange di view, listener
                // di JS halaman) tetap bereaksi seperti biasa.
                sel.dispatchEvent(new Event('input',  { bubbles: true }));
                sel.dispatchEvent(new Event('change', { bubbles: true }));
            }
            closeOpen();
            renderItems(sel, panel, label);   // refresh active highlight
        });

        // Sinkronisasi balik: kalau kode lain mengubah nilai/option select secara
        // programmatic, ikut update tampilan.
        sel.addEventListener('change', () => renderItems(sel, panel, label));
        // Beberapa kode set value langsung tanpa dispatch event — tangkap via
        // MutationObserver pada childList (option list berubah) + atribut.
        const mo = new MutationObserver(() => renderItems(sel, panel, label));
        mo.observe(sel, { childList: true, subtree: true, attributes: true, attributeFilter: ['value', 'disabled'] });
        wrap._seObserver = mo;

        // Reflect disabled state
        const reflectDisabled = () => { btn.disabled = sel.disabled; };
        new MutationObserver(reflectDisabled).observe(sel, { attributes: true, attributeFilter: ['disabled'] });
    }

    // ── Auto-scan ────────────────────────────────────────────────────────────
    function scan(scope) {
        const root = scope || document;
        const list = root.querySelectorAll('select');
        list.forEach(enhance);
    }

    // Global click & scroll & resize → close
    document.addEventListener('click', (e) => {
        if (!openWrap) return;
        const wrap = e.target.closest('.se-wrap');
        const panel = e.target.closest('.se-panel');
        if (wrap === openWrap || panel === openWrap._sePanel) return;
        closeOpen();
    });
    window.addEventListener('scroll', (e) => {
        if (!openWrap) return;
        // Jangan tutup kalau scroll terjadi di dalam panel itu sendiri
        const t = e.target;
        if (t && t.nodeType === 1 && t.closest && t.closest('.se-panel')) return;
        closeOpen();
    }, true);
    window.addEventListener('resize', closeOpen);
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && openWrap) closeOpen();
    });

    // ── Init ──────────────────────────────────────────────────────────────────
    function init() {
        injectStyles();
        scan(document);

        // Watch DOM untuk select yang ditambahkan kemudian (modal AJAX, dsb.)
        const rootMo = new MutationObserver((mutations) => {
            for (const m of mutations) {
                m.addedNodes.forEach((node) => {
                    if (node.nodeType !== 1) return;
                    if (node.tagName === 'SELECT') {
                        enhance(node);
                    } else if (node.querySelectorAll) {
                        node.querySelectorAll('select').forEach(enhance);
                    }
                });
            }
        });
        rootMo.observe(document.body, { subtree: true, childList: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Expose untuk pemanggilan manual setelah AJAX populate yang kompleks
    window.enhanceSelects = scan;
})();

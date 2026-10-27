/**
 * Time field (24-hour HH:MM) — pengganti <input type="time"> bawaan peramban untuk isian jam yang sering dipakai.
 *
 * Mengapa: pemilih jam/menit bawaan Chrome berupa daftar yang harus digulir; di layar sentuh atau mouse biasa terasa lambat dan
 * meloncat-loncat. Di sini jam dan menit adalah dua kotak angka yang bisa DIKETIK (auto-lanjut ke menit), dinaikkan/diturunkan dengan
 * panah atas/bawah atau roda mouse, atau diisi sekali klik dari preset (mis. 08:00, 17:00).
 *
 * Pemakaian: <input type="text" name="check_in" data-time-field data-label="Check-in" data-presets="08:00,09:00,17:00" value="08:30">
 * Input aslinya tetap ada (disembunyikan) dan MENYIMPAN nilai "HH:MM" atau kosong, jadi form, validasi server, dan kode yang membaca
 * `.value` tidak berubah. `form.checkValidity()` ikut memperhitungkan isian setengah jadi (jam tanpa menit).
 *
 * API: window.enhanceTimeFields(scope?) — pasang pada elemen baru; window.setTimeField(input, 'HH:MM' | '').
 */
(function () {
    'use strict';
    if (window.enhanceTimeFields) return;

    const FLAG = '_tfReady';
    const pad = n => String(n).padStart(2, '0');

    function injectStyles() {
        if (document.getElementById('time-field-styles')) return;
        const s = document.createElement('style');
        s.id = 'time-field-styles';
        s.textContent = `
            .tf { display: block; }
            .tf-box { display: flex; align-items: center; gap: .25rem; background: #fff; border: 1px solid #d1d5db; border-radius: .5rem; padding: .25rem .5rem; width: 100%; transition: border-color .15s, box-shadow .15s; }
            .tf-box:hover { border-color: #9ca3af; }
            .tf-box:focus-within { border-color: transparent; box-shadow: 0 0 0 2px var(--primary-color, #991b1b); }
            .tf-box.tf-invalid { border-color: #dc2626; box-shadow: 0 0 0 1px #dc2626; }
            .tf-seg { width: 2.25rem; text-align: center; font-size: 1.05rem; font-weight: 600; font-variant-numeric: tabular-nums; color: #111827; background: transparent; border: 0; border-radius: .375rem; padding: .35rem 0; outline: none; caret-color: transparent; }
            .tf-seg::placeholder { color: #cbd5e1; font-weight: 500; }
            .tf-seg:focus { background: color-mix(in srgb, var(--primary-color, #991b1b) 12%, transparent); caret-color: auto; }
            .tf-sep { font-weight: 700; color: #9ca3af; }
            .tf-clear { margin-left: auto; width: 1.5rem; height: 1.5rem; line-height: 1; border-radius: 9999px; color: #9ca3af; font-size: 1rem; background: transparent; border: 0; cursor: pointer; visibility: hidden; }
            .tf-clear:hover { color: #374151; background: #f3f4f6; }
            .tf.tf-filled .tf-clear { visibility: visible; }
            .tf-suffix { font-size: .6875rem; color: #9ca3af; margin-left: .25rem; }
            .tf-presets { display: flex; flex-wrap: wrap; gap: .375rem; margin-top: .5rem; }
            .tf-chip { font-size: .75rem; font-weight: 600; color: #374151; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 9999px; padding: .15rem .6rem; cursor: pointer; font-variant-numeric: tabular-nums; transition: background .12s, border-color .12s; }
            .tf-chip:hover { background: #f3f4f6; border-color: #d1d5db; }
            .tf-chip[aria-pressed="true"] { color: #fff; background: var(--primary-color, #991b1b); border-color: transparent; }
            .tf-msg { font-size: .6875rem; color: #dc2626; margin-top: .25rem; min-height: 1rem; }
        `;
        document.head.appendChild(s);
    }

    function build(input) {
        if (input[FLAG]) return;
        input[FLAG] = true;
        injectStyles();

        const label = input.dataset.label || 'Time';
        const presets = (input.dataset.presets || '').split(',').map(v => v.trim()).filter(v => /^\d{2}:\d{2}$/.test(v));
        const initial = /^(\d{1,2}):(\d{2})/.exec(input.value || '');

        const wrap = document.createElement('div');
        wrap.className = 'tf';
        wrap.setAttribute('role', 'group');
        wrap.setAttribute('aria-label', label);
        wrap.innerHTML = `
            <div class="tf-box">
                <input class="tf-seg" data-seg="h" inputmode="numeric" autocomplete="off" maxlength="2" placeholder="HH" aria-label="${label}: hour (00 to 23)">
                <span class="tf-sep" aria-hidden="true">:</span>
                <input class="tf-seg" data-seg="m" inputmode="numeric" autocomplete="off" maxlength="2" placeholder="MM" aria-label="${label}: minute (00 to 59)">
                <span class="tf-suffix" aria-hidden="true">24h</span>
                <button type="button" class="tf-clear" aria-label="Clear ${label}" title="Clear">&times;</button>
            </div>
            ${presets.length ? '<div class="tf-presets" role="group" aria-label="Quick times">' + presets.map(p => `<button type="button" class="tf-chip" data-preset="${p}" aria-pressed="false">${p}</button>`).join('') + '</div>' : ''}
            <p class="tf-msg" aria-live="polite"></p>`;

        input.type = 'hidden';
        input.insertAdjacentElement('afterend', wrap);

        const box = wrap.querySelector('.tf-box');
        const h = wrap.querySelector('[data-seg="h"]');
        const m = wrap.querySelector('[data-seg="m"]');
        const msg = wrap.querySelector('.tf-msg');
        const clearBtn = wrap.querySelector('.tf-clear');

        function incomplete() { return (h.value !== '' || m.value !== '') && !(h.value !== '' && m.value !== ''); }

        function sync(fire) {
            const hv = h.value, mv = m.value;
            const valid = hv !== '' && mv !== '' && +hv >= 0 && +hv <= 23 && +mv >= 0 && +mv <= 59;
            const next = valid ? `${pad(+hv)}:${pad(+mv)}` : '';
            const changed = input.value !== next;
            input.value = next;
            wrap.classList.toggle('tf-filled', hv !== '' || mv !== '');
            const bad = incomplete();
            box.classList.toggle('tf-invalid', bad);
            h.setAttribute('aria-invalid', bad ? 'true' : 'false');
            m.setAttribute('aria-invalid', bad ? 'true' : 'false');
            msg.textContent = bad ? 'Enter both the hour and the minute, or clear the field.' : '';
            wrap.querySelectorAll('.tf-chip').forEach(c => c.setAttribute('aria-pressed', c.dataset.preset === next ? 'true' : 'false'));
            wrap._incomplete = bad;
            if (fire && changed) {
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }

        function setValue(v, fire) {
            const r = /^(\d{1,2}):(\d{2})/.exec(v || '');
            h.value = r ? pad(+r[1]) : '';
            m.value = r ? pad(+r[2]) : '';
            sync(fire);
        }
        input._tfSet = v => setValue(v, false);

        function step(seg, delta) {
            const isH = seg === h, max = isH ? 23 : 59;
            let v = seg.value === '' ? (delta > 0 ? -1 : max + 1) : +seg.value;
            v += delta;
            if (v > max) v = 0;
            if (v < 0) v = max;
            seg.value = pad(v);
            sync(true);
            seg.select();
        }

        [h, m].forEach(seg => {
            const isH = seg === h, max = isH ? 23 : 59;
            seg.addEventListener('focus', () => seg.select());
            seg.addEventListener('mouseup', e => e.preventDefault());   // cegah seleksi hilang saat klik
            seg.addEventListener('input', () => {
                let v = seg.value.replace(/\D/g, '').slice(0, 2);
                if (v.length === 1 && +v > (isH ? 2 : 5)) {            // 3–9 pada jam / 6–9 pada menit hanya mungkin sebagai digit kedua
                    v = '0' + v;
                }
                if (v.length === 2 && +v > max) v = String(max);
                seg.value = v;
                sync(true);
                if (v.length === 2 && isH) { m.focus(); }
            });
            seg.addEventListener('keydown', e => {
                if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
                    e.preventDefault();
                    const base = e.key === 'ArrowUp' ? 1 : -1;
                    step(seg, base * (!isH && e.shiftKey ? 5 : 1));
                } else if ((e.key === ':' || e.key === '.' || e.key === ' ' || e.key === 'ArrowRight') && isH && (e.key !== 'ArrowRight' || seg.selectionStart === seg.value.length)) {
                    if (e.key !== 'ArrowRight') e.preventDefault();
                    if (seg.value.length === 1) { seg.value = pad(+seg.value); sync(true); }
                    m.focus();
                } else if (e.key === 'ArrowLeft' && !isH && m.selectionStart === 0) {
                    h.focus();
                } else if (e.key === 'Backspace' && !isH && m.value === '') {
                    e.preventDefault(); h.focus();
                } else if (e.key === 'Delete' || (e.key === 'Backspace' && seg.selectionStart === 0 && seg.selectionEnd === seg.value.length)) {
                    e.preventDefault(); seg.value = ''; sync(true);
                }
            });
            seg.addEventListener('wheel', e => {
                if (document.activeElement !== seg) return;           // roda hanya mengubah isian yang sedang difokus, halaman tetap bisa digulir
                e.preventDefault();
                step(seg, (e.deltaY < 0 ? 1 : -1));
            }, { passive: false });
            seg.addEventListener('blur', () => {
                if (seg.value.length === 1) seg.value = pad(+seg.value);
                sync(true);
            });
        });

        clearBtn.addEventListener('click', () => { h.value = ''; m.value = ''; sync(true); h.focus(); });
        wrap.querySelectorAll('.tf-chip').forEach(c => c.addEventListener('click', () => { setValue(c.dataset.preset, true); }));

        // Isian setengah jadi menghalangi pengiriman form dengan pesan di bawah kolom (input tersembunyi tak bisa memberi tooltip peramban).
        const form = input.form;
        if (form && !form._tfGuard) {
            form._tfGuard = true;
            form.addEventListener('submit', e => {
                const bad = [...form.querySelectorAll('.tf')].find(w => w._incomplete);
                if (bad) { e.preventDefault(); e.stopImmediatePropagation(); bad.querySelector('[data-seg]').focus(); }
            }, true);
        }

        setValue(initial ? `${initial[1]}:${initial[2]}` : '', false);
    }

    window.enhanceTimeFields = function (scope) {
        (scope || document).querySelectorAll('input[data-time-field]').forEach(build);
    };
    window.setTimeField = function (input, value) {
        if (!input[FLAG]) build(input);
        input._tfSet(value || '');
    };

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => window.enhanceTimeFields());
    else window.enhanceTimeFields();
})();

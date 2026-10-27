/**
 * Month field — pengganti <input type="month"> untuk peramban yang TIDAK mendukungnya (terutama Firefox desktop, tempat kolom itu
 * tampil sebagai teks biasa tanpa pemilih bulan sehingga filter periode tak bisa dipakai).
 *
 * Deteksi fitur: di Chrome / Edge / Safari yang mendukung `type="month"` berkas ini TIDAK melakukan apa pun (pemilih bawaan dipakai).
 * Di peramban tanpa dukungan, tiap `input[type=month]` mendapat dua dropdown (Bulan + Tahun) di sampingnya.
 *
 * Input aslinya TETAP ada (disembunyikan secara visual, tetap type=month) dan MENYIMPAN nilai "YYYY-MM" atau kosong, jadi form, validasi
 * server, selektor `input[type=month]`, dan kode yang membaca / mengisi `.value` tidak berubah. Perubahan lewat dropdown memicu
 * event `input` + `change` pada input asli; mengisi `.value` lewat kode ikut memperbarui dropdown.
 *
 * API: window.enhanceMonthFields(scope?) — pasang pada elemen baru.
 */
(function () {
    'use strict';
    if (window.enhanceMonthFields) return;

    // Deteksi dukungan: peramban tanpa dukungan mengembalikan type "text".
    const probe = document.createElement('input');
    probe.setAttribute('type', 'month');
    const supported = probe.type === 'month';

    const FLAG = '_mfReady';
    const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    const nativeValue = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');

    function injectStyles() {
        if (document.getElementById('month-field-styles')) return;
        const s = document.createElement('style');
        s.id = 'month-field-styles';
        s.textContent = `
            .mf { display: flex; gap: .5rem; width: 100%; min-width: 0; }
            .mf select { min-width: 0; flex: 1 1 0; }
            .mf select.mf-month { flex: 1.5 1 0; }
            .mf-hidden { position: absolute !important; width: 1px !important; height: 1px !important; opacity: 0 !important; pointer-events: none !important; overflow: hidden !important; }
        `;
        document.head.appendChild(s);
    }

    function build(input) {
        if (input[FLAG]) return;
        input[FLAG] = true;
        injectStyles();

        const label = input.getAttribute('aria-label') || input.getAttribute('title') || 'Period';
        const required = input.required;
        const nowYear = new Date().getFullYear();

        const wrap = document.createElement('div');
        wrap.className = 'mf';
        wrap.setAttribute('role', 'group');
        wrap.setAttribute('aria-label', label);

        // Dropdown mengambil kelas input aslinya agar tampilannya sama dengan kolom lain.
        const cls = (input.className || '').replace(/\bhidden\b/g, '');
        const month = document.createElement('select');
        month.className = cls + ' mf-month';
        month.setAttribute('data-no-enhance', '');
        month.setAttribute('aria-label', label + ': month');
        const year = document.createElement('select');
        year.className = cls + ' mf-year';
        year.setAttribute('data-no-enhance', '');
        year.setAttribute('aria-label', label + ': year');

        month.innerHTML = '<option value="">Month</option>' + MONTHS.map((m, i) => `<option value="${String(i + 1).padStart(2, '0')}">${m}</option>`).join('');

        function yearOptions(extra) {
            const years = new Set();
            for (let y = nowYear + 2; y >= nowYear - 8; y--) years.add(y);
            if (extra) years.add(extra);
            return '<option value="">Year</option>' + [...years].sort((a, b) => b - a).map(y => `<option value="${y}">${y}</option>`).join('');
        }
        year.innerHTML = yearOptions(0);

        input.insertAdjacentElement('afterend', wrap);
        wrap.appendChild(month);
        wrap.appendChild(year);
        input.classList.add('mf-hidden');
        input.tabIndex = -1;
        input.setAttribute('aria-hidden', 'true');

        // Tampilkan nilai input pada dropdown (tanpa memicu event).
        function paint() {
            const m = /^(\d{4})-(\d{2})/.exec(nativeValue.get.call(input) || '');
            if (!m) { month.value = ''; year.value = ''; return; }
            if (!year.querySelector(`option[value="${+m[1]}"]`)) year.innerHTML = yearOptions(+m[1]);
            month.value = m[2];
            year.value = String(+m[1]);
        }

        // `.value = '...'` dari kode lain ikut memperbarui dropdown.
        Object.defineProperty(input, 'value', {
            configurable: true,
            get() { return nativeValue.get.call(this); },
            set(v) {
                nativeValue.set.call(this, v);
                const m = /^(\d{4})-(\d{2})/.exec(v || '');
                if (m) { if (!year.querySelector(`option[value="${+m[1]}"]`)) year.innerHTML = yearOptions(+m[1]); month.value = m[2]; year.value = String(+m[1]); }
                else { month.value = ''; year.value = ''; }
            },
        });

        function commit() {
            // Memilih bulan tanpa tahun → tahun berjalan; mengosongkan bulan → nilai kosong.
            if (month.value && !year.value) year.value = String(nowYear);
            const next = month.value && year.value ? `${year.value}-${month.value}` : '';
            if (nativeValue.get.call(input) === next) return;
            nativeValue.set.call(input, next);
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }
        // Perubahan dropdown tidak dibiarkan menggelembung sendiri: yang menggelembung adalah event pada input asli (satu kali).
        [month, year].forEach(sel => sel.addEventListener('change', e => { e.stopPropagation(); commit(); }));

        if (required) { month.required = true; year.required = true; }
        paint();
    }

    window.enhanceMonthFields = function (scope) {
        if (supported) return;
        (scope || document).querySelectorAll('input[type="month"]').forEach(build);
    };

    function start() {
        window.enhanceMonthFields();
        if (!supported && window.MutationObserver) {
            new MutationObserver(muts => muts.forEach(m => m.addedNodes.forEach(n => {
                if (n.nodeType !== 1) return;
                if (n.matches && n.matches('input[type="month"]')) build(n);
                else if (n.querySelectorAll) n.querySelectorAll('input[type="month"]').forEach(build);
            }))).observe(document.body, { childList: true, subtree: true });
        }
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();

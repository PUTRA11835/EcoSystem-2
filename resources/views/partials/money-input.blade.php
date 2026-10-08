{{--
    Kolom uang modul keuangan: tulis `<input type="text" inputmode="decimal" class="js-money" value="{{ Money::format($n) }}">`.
    Tampilan selalu 1.000.000,00 (titik ribuan, koma desimal, dua digit). Saat mengetik, titik ribuan ditambahkan
    otomatis; saat meninggalkan kolom, ,00 dilengkapi. Server menerima format ini (App\Support\Payroll\Money::parse),
    jadi tidak ada konversi tersembunyi di sisi klien. Sertakan sekali per halaman (dijaga @once).
--}}
@once
@push('scripts')
<script>
(function () {
    // Bagian angka → "1.000.000" (tanpa desimal); desimal diketik setelah koma.
    function group(intPart) { return intPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.'); }

    function liveFormat(el) {
        const raw = el.value;
        const caretFromEnd = raw.length - (el.selectionStart ?? raw.length);
        let neg = raw.trim().startsWith('-') ? '-' : '';
        const commaAt = raw.indexOf(',');
        let intPart = (commaAt === -1 ? raw : raw.slice(0, commaAt)).replace(/[^\d]/g, '').replace(/^0+(?=\d)/, '');
        let out = neg + group(intPart);
        if (commaAt !== -1) { out += ',' + raw.slice(commaAt + 1).replace(/[^\d]/g, '').slice(0, 2); }
        el.value = out;
        const pos = Math.max(0, out.length - caretFromEnd);
        try { el.setSelectionRange(pos, pos); } catch (e) { /* tipe input tak mendukung */ }
    }

    function finalFormat(el) {
        const raw = el.value.trim();
        if (raw === '') { return; }
        const neg = raw.startsWith('-') ? '-' : '';
        const commaAt = raw.indexOf(',');
        const intPart = (commaAt === -1 ? raw : raw.slice(0, commaAt)).replace(/[^\d]/g, '') || '0';
        const dec = (commaAt === -1 ? '' : raw.slice(commaAt + 1).replace(/[^\d]/g, '')).padEnd(2, '0').slice(0, 2);
        el.value = neg + group(intPart.replace(/^0+(?=\d)/, '')) + ',' + dec;
    }

    document.addEventListener('input', (e) => { if (e.target.classList?.contains('js-money')) { liveFormat(e.target); } });
    document.addEventListener('focusout', (e) => { if (e.target.classList?.contains('js-money')) { finalFormat(e.target); } });
    document.addEventListener('focusin', (e) => { if (e.target.classList?.contains('js-money')) { e.target.select?.(); } });
    window.formatMoneyField = finalFormat;
})();
</script>
@endpush
@endonce

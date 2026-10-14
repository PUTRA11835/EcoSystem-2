{{--
    Perilaku tabel baris biaya. Satu berkas dipakai bersama form pengajuan,
    "New RB", dan Edit — supaya ketiganya tidak pernah berbeda perilakunya.

    Diharapkan berada di dalam @push('scripts').
--}}
<script>
(function () {
    const tbody      = document.getElementById('itemRows');
    const template   = document.getElementById('itemRowTemplate');
    const addButton  = document.getElementById('addItemRow');
    const totalCell  = document.getElementById('itemsTotal');
    const currencyEl = document.getElementById('reimbursementCurrency');
    const maxItems   = {{ (int) $settings->max_items_per_request }};   // 0 = tanpa batas

    if (!tbody || !template) return;

    // Kunci acak, BUKAN indeks berurutan. Dengan indeks berurutan, menghapus
    // baris di tengah membuat nomornya bolong dan baris berikutnya tertimpa.
    function newKey() {
        return 'r' + Math.random().toString(36).slice(2, 10);
    }

    function currentCurrency() {
        return currencyEl && currencyEl.value ? currencyEl.value : 'IDR';
    }

    function formatMoney(value) {
        const code = currentCurrency();

        if (code === 'IDR') {
            return 'Rp ' + Math.round(value).toLocaleString('id-ID');
        }

        return code + ' ' + value.toLocaleString('id-ID', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }

    function syncCurrencyLabels() {
        document.querySelectorAll('.js-currency-code').forEach(function (el) {
            el.textContent = currentCurrency();
        });
    }

    function recalcTotal() {
        let total = 0;

        tbody.querySelectorAll('.js-amount').forEach(function (input) {
            const value = parseFloat(input.value);
            if (!isNaN(value) && value > 0) {
                // Dibulatkan per baris lalu dijumlahkan, sama seperti di server
                // (Keputusan D115): total harus sama dengan jumlah angka yang
                // terbaca, bukan hasil aritmetika penuh.
                total += Math.round(value * 100) / 100;
            }
        });

        totalCell.textContent = formatMoney(total);
    }

    function renumber() {
        tbody.querySelectorAll('.js-item-row').forEach(function (row, index) {
            row.querySelector('.js-line-no').textContent = index + 1;
        });

        // Baris terakhir tidak boleh dihapus: dokumen tanpa item tidak sah, dan
        // tombol yang selalu terlihat tapi kadang ditolak lebih membingungkan
        // daripada tombol yang hilang.
        const rows = tbody.querySelectorAll('.js-item-row');
        rows.forEach(function (row) {
            const button = row.querySelector('.js-remove-row');
            if (button) button.classList.toggle('invisible', rows.length === 1);
        });

        if (addButton && maxItems > 0) {
            addButton.disabled = rows.length >= maxItems;
            addButton.classList.toggle('opacity-50', addButton.disabled);
            addButton.classList.toggle('cursor-not-allowed', addButton.disabled);
        }
    }

    function addRow() {
        const key  = newKey();
        const html = template.innerHTML.replace(/__KEY__/g, key);

        const holder = document.createElement('tbody');
        holder.innerHTML = html.trim();

        const row = holder.querySelector('tr');
        row.dataset.key = key;
        tbody.appendChild(row);

        renumber();
        recalcTotal();

        const first = row.querySelector('input[type="text"]');
        if (first) first.focus();
    }

    tbody.addEventListener('input', function (event) {
        if (event.target.classList.contains('js-amount')) recalcTotal();
    });

    tbody.addEventListener('click', function (event) {
        const button = event.target.closest('.js-remove-row');
        if (!button) return;

        if (tbody.querySelectorAll('.js-item-row').length === 1) return;

        button.closest('.js-item-row').remove();
        renumber();
        recalcTotal();
    });

    if (addButton) addButton.addEventListener('click', addRow);

    if (currencyEl) {
        currencyEl.addEventListener('change', function () {
            syncCurrencyLabels();
            recalcTotal();
        });
    }

    syncCurrencyLabels();
    renumber();
    recalcTotal();
})();
</script>

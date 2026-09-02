{{--
    Perilaku tabel baris permintaan. Satu berkas dipakai bersama form pengajuan,
    "New PR", dan Edit — supaya ketiganya tidak pernah berbeda perilakunya.

    Diharapkan berada di dalam @push('scripts').
--}}
<script>
(function () {
    const tbody     = document.getElementById('itemRows');
    const template  = document.getElementById('itemRowTemplate');
    const addButton = document.getElementById('addItemRow');
    const countCell = document.getElementById('itemsCount');
    const qtyCell   = document.getElementById('itemsQty');
    const maxItems  = {{ (int) $settings->max_items_per_request }};   // 0 = tanpa batas

    // Urutan satuan mengikuti `allowed_units` di Settings — sama persis dengan
    // PurchaseRequestSummaryService::qtySummary(). Kalau berbeda, ringkasan di
    // layar dan ringkasan yang tersimpan akan menampilkan urutan yang tidak sama
    // untuk dokumen yang sama.
    const unitOrder = @json($settings->unitOptions());

    if (!tbody || !template) return;

    // Kunci acak, BUKAN indeks berurutan. Dengan indeks berurutan, menghapus
    // baris di tengah membuat nomornya bolong dan baris berikutnya tertimpa.
    function newKey() {
        return 'r' + Math.random().toString(36).slice(2, 10);
    }

    /** "2" untuk 2,00 dan "0,5" untuk 0,50 — meniru PurchaseRequestItem::formatQty(). */
    function formatQty(value) {
        return Number.isInteger(value)
            ? value.toLocaleString('id-ID')
            : value.toLocaleString('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 2 });
    }

    /** Ringkasan "10 PC · 5 SET" — cermin qtySummary() di server. */
    function recalcSummary() {
        const rows   = tbody.querySelectorAll('.js-item-row');
        const totals = {};

        rows.forEach(function (row) {
            const qtyInput  = row.querySelector('.js-qty');
            const unitInput = row.querySelector('.js-unit');
            if (!qtyInput || !unitInput) return;

            const qty  = parseFloat(qtyInput.value);
            const unit = (unitInput.value || '').toUpperCase();

            if (isNaN(qty) || qty <= 0 || unit === '') return;

            totals[unit] = (totals[unit] || 0) + qty;
        });

        countCell.textContent = rows.length + (rows.length === 1 ? ' item' : ' items');

        const known   = unitOrder.filter(function (u) { return totals[u] !== undefined; });
        const unknown = Object.keys(totals).filter(function (u) { return unitOrder.indexOf(u) === -1; }).sort();
        const parts   = known.concat(unknown).map(function (u) {
            return formatQty(Math.round(totals[u] * 100) / 100) + ' ' + u;
        });

        qtyCell.textContent = parts.length ? parts.join(' · ') : '—';
    }

    function renumber() {
        const rows = tbody.querySelectorAll('.js-item-row');

        rows.forEach(function (row, index) {
            row.querySelector('.js-line-no').textContent = index + 1;
        });

        // Baris terakhir tidak boleh dihapus: dokumen tanpa item tidak sah, dan
        // tombol yang selalu terlihat tapi kadang ditolak lebih membingungkan
        // daripada tombol yang hilang.
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

    /**
     * Sinkronkan pasangan Charged To pada satu baris.
     *
     * 🔴 Dropdown yang ditinggalkan DIKOSONGKAN, bukan sekadar disembunyikan.
     * Kalau hanya disembunyikan, nilainya tetap ikut terkirim, form membawa
     * cabang DAN proyek sekaligus, dan PurchaseRequestService::checkRawItems()
     * menolaknya — penolakan yang tidak dapat dipahami pengguna karena di
     * layarnya hanya satu dropdown yang terlihat.
     */
    function syncCostCenter(row, clearOther) {
        const type    = row.querySelector('.js-cc-type');
        const branch  = row.querySelector('.js-cc-branch');
        const project = row.querySelector('.js-cc-project');

        if (!type) return;

        const isProject = type.value === 'project';

        if (branch) {
            branch.classList.toggle('hidden', isProject);
            if (isProject && clearOther) branch.value = '';
        }

        if (project) {
            project.classList.toggle('hidden', !isProject);
            if (!isProject && clearOther) project.value = '';
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

        syncCostCenter(row, false);
        renumber();
        recalcSummary();

        const first = row.querySelector('input[type="text"]');
        if (first) first.focus();
    }

    tbody.addEventListener('input', function (event) {
        if (event.target.classList.contains('js-qty')) recalcSummary();
    });

    tbody.addEventListener('change', function (event) {
        if (event.target.classList.contains('js-unit')) {
            recalcSummary();
            return;
        }

        if (event.target.classList.contains('js-cc-type')) {
            syncCostCenter(event.target.closest('.js-item-row'), true);
        }
    });

    tbody.addEventListener('click', function (event) {
        const button = event.target.closest('.js-remove-row');
        if (!button) return;

        if (tbody.querySelectorAll('.js-item-row').length === 1) return;

        button.closest('.js-item-row').remove();
        renumber();
        recalcSummary();
    });

    if (addButton) addButton.addEventListener('click', addRow);

    // Baris yang sudah ada disinkronkan TANPA mengosongkan: nilai yang tersimpan
    // maupun isian yang gagal validasi harus tetap terlihat.
    tbody.querySelectorAll('.js-item-row').forEach(function (row) {
        syncCostCenter(row, false);
    });

    renumber();
    recalcSummary();
})();
</script>

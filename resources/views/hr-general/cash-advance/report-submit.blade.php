@extends('dashboard')

@php
    use App\Models\CashAdvance\CashAdvance;

    $amounts = app(\App\Services\CashAdvance\CashAdvanceAmountService::class);

    // ═══════════════════════════════════════════════════════════════════════
    // SATU FORM UNTUK TIGA KEPERLUAN — bentuk yang sama dengan submit.blade.php.
    //
    // ESS "Create CAR" · HR "New CAR" (atas nama karyawan) · "Edit CAR".
    // Pemanggil yang tidak mengirim apa pun mendapat perilaku ESS "buat baru".
    // ═══════════════════════════════════════════════════════════════════════
    $mode      = $mode      ?? 'create';
    $report    = $report    ?? null;   // CashAdvanceReport yang sedang diubah
    $action    = $action    ?? route('general.my-cash-advance-report.store');
    $backRoute = $backRoute ?? route('general.my-cash-advance-report.index');

    // Tautan ke CA induk mengikuti sisi yang sedang membuka halaman: karyawan
    // tidak boleh dilempar ke halaman HR yang tidak dapat ia buka.
    $advanceRoute = $advanceRoute ?? route('general.my-cash-advance.show', $advance);

    $isEdit = $mode === 'edit';

    // 🔴 `report_date` di-cast Carbon; menaruhnya apa adanya pada
    // <input type="date"> menghasilkan nilai yang ditolak diam-diam oleh
    // peramban dan field-nya tampil KOSONG. Itu bukan galat yang terlihat.
    $dateVal = old('report_date', $report?->report_date?->format('Y-m-d') ?? now()->toDateString());

    // Baris item yang sudah ada, untuk diisikan JavaScript saat halaman dibuka.
    // Nominal dikirim sebagai ANGKA BULAT string supaya pembacaannya sama
    // dengan yang diketik pengguna (lihat parseAmount()).
    $existingItems = $isEdit
        ? $report->items->map(fn ($item) => [
            'expense_date'        => $item->expense_date?->format('Y-m-d'),
            'description'         => $item->description,
            'receipt_no'          => $item->receipt_no,
            'amount'              => number_format((float) $item->amount, 0, ',', '.'),
            'cost_center_type'    => $item->cost_center_type,
            'branch_id'           => $item->branch_id,
            'delivery_project_id' => $item->delivery_project_id,
            'receipt_url'         => $item->receipt_url,
        ])->values()
        : collect();

    $heading = $isEdit ? 'Edit Cash Advance Report' : 'Create Cash Advance Report';
@endphp

@section('title', $heading)
@section('page-title', $heading)
@section('page-subtitle', $isEdit
    ? 'Change an open report — the approval flow is kept, not restarted'
    : 'Account for how the cash advance was actually spent')

@section('content')

<div class="w-full space-y-5">

    <div class="flex justify-end">
        <a href="{{ $backRoute }}"
           class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
            Back
        </a>
    </div>

    @if(session('error'))
        <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3">
            <p class="text-sm text-red-900">{{ session('error') }}</p>
        </div>
    @endif

    @if($errors->any())
        <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3">
            <ul class="text-sm text-red-900 list-disc list-inside space-y-0.5">
                @foreach($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ── Uang muka yang dipertanggungjawabkan ─────────────────────────────
         Ditaruh paling atas dan tidak dapat diubah: nominal inilah yang akan
         DIBEKUKAN ke laporan (D139), dan pemohon harus melihat angka yang
         sedang ia pertanggungjawabkan sebelum mengisi apa pun. --}}
    <div class="bg-white rounded-xl p-5 md:p-6 shadow-sm">
        <h2 class="text-sm font-bold text-gray-900 mb-4">Settling this cash advance</h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
            <div>
                <p class="text-xs text-gray-500">Document No.</p>
                <a href="{{ $advanceRoute }}"
                   class="text-sm font-medium primary-text hover:underline">{{ $advance->request_no }}</a>
            </div>
            <div>
                <p class="text-xs text-gray-500">Date</p>
                <p class="text-sm">{{ $advance->request_date->format('d M Y') }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500">Purpose</p>
                <p class="text-sm">{{ $advance->description }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500">Advance amount</p>
                <p class="text-lg font-bold text-gray-900"
                   data-ca-advance="{{ (float) $advance->amount }}">
                    {{ $advance->currency }} {{ $amounts->format((float) $advance->amount) }}
                </p>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ $action }}" id="carSubmitForm"
          class="bg-white rounded-xl p-5 md:p-6 shadow-sm">
        @csrf
        <input type="hidden" name="ca" value="{{ $advance->id }}">
        @unless($isEdit)
            <input type="hidden" name="print_after_save" id="carPrintAfterSave" value="0">
        @endunless

        <div class="text-center mb-6 pb-4 border-b border-gray-100">
            <p class="text-sm font-bold tracking-wide text-gray-900">CASH ADVANCE REPORT</p>
            <p class="text-sm font-bold tracking-wide primary-text">{{ strtoupper($settings->company_name) }}</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-x-6 gap-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Report No.</label>
                <input type="text" disabled
                       value="{{ $isEdit ? $report->report_no : 'Auto-generated on submit' }}"
                       class="w-full px-3 py-2 border border-gray-200 bg-gray-50 rounded-lg text-sm text-gray-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Report Date <span class="text-red-600">*</span>
                </label>
                <input type="date" name="report_date" required
                       value="{{ $dateVal }}"
                       @if($maxDate) max="{{ $maxDate }}" @endif
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-1 primary-border">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Requester</label>
                <input type="text" value="{{ $requesterName }}" disabled
                       class="w-full px-3 py-2 border border-gray-200 bg-gray-50 rounded-lg text-sm text-gray-600">
            </div>

            <div class="md:col-span-2 xl:col-span-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Description <span class="text-red-600">*</span>
                </label>
                <textarea name="description" rows="2" required minlength="5" maxlength="255"
                          placeholder="Example: Realisation of EC Project operations June 2026"
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-1 primary-border">{{ old('description', $report->description ?? '') }}</textarea>
            </div>

            {{-- Approver — mekanisme yang sama dengan CA (D126 & D138). --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Approver @if($chooseApprover)<span class="text-red-600">*</span>@endif
                </label>
                @if($isEdit)
                    {{-- Alur laporan ini sudah dibekukan saat dibuat, dan
                         CashAdvanceReportService::update() sengaja tidak
                         menyusunnya ulang. Yang ditampilkan keadaan sebenarnya,
                         bukan pilihan yang tidak lagi terbuka. --}}
                    <input type="text" disabled
                           value="{{ $report->currentStepName() ?? $report->statusLabel() }}"
                           class="w-full px-3 py-2 border border-gray-200 bg-gray-50 rounded-lg text-sm text-gray-600">
                    <p class="text-xs text-gray-400 mt-1">
                        Frozen when the report was created — editing does not restart the flow.
                    </p>
                @elseif($chooseApprover)
                    <select name="approver_ids[{{ $firstStep->order_seq }}]" required
                            @disabled(count($approverCandidates) === 1)
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-1 primary-border disabled:bg-gray-50 disabled:text-gray-500">
                        @if(count($approverCandidates) > 1)
                            <option value="">— choose an approver —</option>
                        @endif
                        @foreach($approverCandidates as $candidate)
                            <option value="{{ $candidate['id'] }}"
                                    @selected(count($approverCandidates) === 1)>{{ $candidate['name'] }}</option>
                        @endforeach
                    </select>
                @else
                    <input type="text" disabled
                           value="{{ $firstStep?->name ?? 'No approval step configured' }}"
                           class="w-full px-3 py-2 border border-gray-200 bg-gray-50 rounded-lg text-sm text-gray-600">
                    <p class="text-xs text-gray-400 mt-1">
                        Set by configuration — {{ $firstStep?->approverLabel() ?? '—' }}.
                    </p>
                @endif
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Additional Notes</label>
                <input type="text" name="notes" value="{{ old('notes', $report->notes ?? '') }}" maxlength="2000"
                       placeholder="Internal note if any"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-1 primary-border">
            </div>
        </div>

        {{-- ══════════════════ BARIS REALISASI ══════════════════
             🔴 Nama fieldnya memakai KUNCI ACAK — `items[<uuid>][amount]`,
             BUKAN indeks berurutan. Baris ditambah dan dihapus lewat JavaScript;
             dengan indeks, menghapus baris di tengah membuat sisanya bergeser
             dan pesan validasi menunjuk baris yang SALAH. Server mengurutkan
             ulang jadi `line_no` saat menyimpan.

             Pelajaran ini sudah dibayar di Reimbursement dan diulang di
             Purchase Request. --}}
        <div class="mt-6 pt-5 border-t border-gray-100">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-3">
                <div>
                    <h3 class="text-sm font-bold text-gray-900">Expense lines</h3>
                    <p class="text-xs text-gray-500">What the money was actually spent on.</p>
                </div>
                <button type="button" id="carAddRow"
                        class="w-full sm:w-auto px-4 py-2 border primary-border rounded-lg text-sm font-medium primary-text hover:bg-gray-50 transition-colors">
                    + Add line
                </button>
            </div>

            <div class="border border-gray-200 rounded-lg overflow-x-auto">
                <table class="w-full text-sm" id="carItemsTable">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                            <th class="px-3 py-2 w-10">No</th>
                            <th class="px-3 py-2 w-40">Date</th>
                            <th class="px-3 py-2">Description</th>
                            <th class="px-3 py-2 w-36">Receipt No.</th>
                            <th class="px-3 py-2 w-36">Amount</th>
                            @if($settings->costCenterTypeOptions() !== [])
                                <th class="px-3 py-2 w-56">Charged To</th>
                            @endif
                            <th class="px-3 py-2 w-56">Receipt link</th>
                            <th class="px-3 py-2 w-12"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100" id="carItemsBody">
                        {{-- Diisi JavaScript dari <template> di bawah. --}}
                    </tbody>
                    <tfoot class="bg-gray-50 border-t border-gray-200">
                        <tr>
                            <td colspan="4" class="px-3 py-2 text-right text-xs font-semibold text-gray-600">
                                Reported total
                            </td>
                            <td class="px-3 py-2 font-bold text-gray-900" id="carTotal">0</td>
                            <td colspan="{{ $settings->costCenterTypeOptions() !== [] ? 3 : 2 }}"
                                class="px-3 py-2 text-xs" id="carDifference"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            @if($settings->car_require_receipt_url)
                <p class="text-xs text-red-600 mt-2">
                    * A receipt link is required on every line (set in Cash Advance Settings).
                </p>
            @endif
        </div>

        <div class="flex flex-col sm:flex-row sm:justify-end gap-2 mt-6 pt-5 border-t border-gray-100">
            <a href="{{ $backRoute }}"
               class="w-full sm:w-auto text-center px-6 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                Cancel
            </a>
            <button type="submit" @disabled(! $isEdit && $steps->isEmpty())
                    class="w-full sm:w-auto px-6 py-2.5 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-gray-900 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                {{ $isEdit ? 'Save Changes' : 'Submit' }}
            </button>
        </div>
    </form>
</div>

{{-- Cetakan satu baris item. `__KEY__` diganti kunci acak saat baris dibuat. --}}
<template id="carRowTemplate">
    <tr class="align-top" data-car-row>
        <td class="px-3 py-2 text-gray-500" data-car-no></td>
        <td class="px-3 py-2">
            <input type="date" name="items[__KEY__][expense_date]"
                   class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 primary-border">
        </td>
        <td class="px-3 py-2">
            <input type="text" name="items[__KEY__][description]" maxlength="200" placeholder="What was bought"
                   class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 primary-border">
        </td>
        <td class="px-3 py-2">
            <input type="text" name="items[__KEY__][receipt_no]" maxlength="60" placeholder="optional"
                   class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 primary-border">
        </td>
        <td class="px-3 py-2">
            {{-- type="text", sama alasannya dengan Amount pada CA: "100.000"
                 ditolak input number, dan server menguraikannya lewat
                 parseAmount() yang sudah ber-unit-test. --}}
            <input type="text" name="items[__KEY__][amount]" inputmode="numeric" maxlength="30" placeholder="0"
                   data-car-amount
                   class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm text-right focus:outline-none focus:ring-1 primary-border">
        </td>
        @if($settings->costCenterTypeOptions() !== [])
        <td class="px-3 py-2">
            <select name="items[__KEY__][cost_center_type]" data-car-cc-type
                    class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm mb-1 focus:outline-none focus:ring-1 primary-border">
                <option value="">— none —</option>
                @foreach($settings->costCenterTypeOptions() as $type)
                    <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                @endforeach
            </select>

            {{-- 🔴 DAFTAR KOSONG HARUS MENJELASKAN DIRINYA (Keputusan D164).
                 Form CA sudah melakukannya sejak A4; form CAR belum, dan pemilik
                 sistem menemukannya di layar: dropdown Branch terbuka kosong,
                 tanpa satu kata pun tentang sebabnya. Daftar kosong yang diam
                 terlihat seperti halaman rusak, padahal yang kurang hanya data
                 induknya — dan orang akan mencari kesalahan di tempat yang
                 salah. --}}
            <div data-cc-for="{{ CashAdvance::COST_CENTER_BRANCH }}">
                <select name="items[__KEY__][branch_id]"
                        class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 primary-border">
                    <option value="">— branch —</option>
                    @foreach($costCenters['branch'] as $option)
                        <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                    @endforeach
                </select>
                @if($costCenters['branch'] === [])
                    <p class="text-xs text-red-600 mt-1">
                        No active branch exists yet — ask an administrator to add one under
                        HR &amp; General &rarr; Branches.
                    </p>
                @endif
            </div>

            <div data-cc-for="{{ CashAdvance::COST_CENTER_PROJECT }}">
                <select name="items[__KEY__][delivery_project_id]"
                        class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 primary-border">
                    <option value="">— project —</option>
                    @foreach($costCenters['project'] as $option)
                        <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                    @endforeach
                </select>
                @if($costCenters['project'] === [])
                    <p class="text-xs text-red-600 mt-1">
                        No active project exists yet.
                    </p>
                @endif
            </div>
        </td>
        @endif
        <td class="px-3 py-2">
            <input type="url" name="items[__KEY__][receipt_url]" maxlength="500" placeholder="https://..."
                   @if($settings->car_require_receipt_url) required @endif
                   class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 primary-border">
        </td>
        <td class="px-3 py-2 text-center">
            <button type="button" data-car-remove title="Remove line"
                    class="px-2 py-1 border border-red-300 text-red-700 rounded text-xs hover:bg-red-50 transition-colors">
                &times;
            </button>
        </td>
    </tr>
</template>
@endsection

@push('scripts')
<script>
    const carBody     = document.getElementById('carItemsBody');
    const carTemplate = document.getElementById('carRowTemplate');
    const carTotalEl  = document.getElementById('carTotal');
    const carDiffEl   = document.getElementById('carDifference');
    const carAdvance  = parseFloat(document.querySelector('[data-ca-advance]').dataset.caAdvance || '0');

    // 🔴 Kunci ACAK, bukan indeks berurutan. Menghapus baris di tengah tidak
    // menggeser kunci baris lain, sehingga pesan validasi selalu menunjuk baris
    // yang benar. Server yang mengurutkan ulang jadi line_no.
    function carKey() {
        return 'r' + Math.random().toString(36).slice(2, 10);
    }

    function carAddRow() {
        const html = carTemplate.innerHTML.replaceAll('__KEY__', carKey());
        const frag = document.createElement('tbody');
        frag.innerHTML = html.trim();

        const row = frag.firstElementChild;
        carBody.appendChild(row);

        carSyncRowCostCenter(row);
        carRenumber();

        return row;
    }

    // Hanya daftar yang sesuai tipenya yang terlihat — pelajaran D149, sama
    // dengan kolom Reference di halaman Settings.
    function carSyncRowCostCenter(row) {
        const typeSelect = row.querySelector('[data-car-cc-type]');
        if (!typeSelect) return;

        const apply = () => {
            row.querySelectorAll('[data-cc-for]').forEach(function (box) {
                box.hidden = box.dataset.ccFor !== typeSelect.value;
            });
        };

        apply();
        typeSelect.addEventListener('change', apply);
    }

    function carRenumber() {
        carBody.querySelectorAll('[data-car-row]').forEach(function (row, i) {
            row.querySelector('[data-car-no]').textContent = i + 1;
        });
    }

    // Total & selisih dihitung di layar hanya sebagai BANTUAN BACA. Yang
    // menentukan tetap CashAdvanceReportService::recalculateTotals() di server —
    // satu-satunya jalur tulis untuk ketiga angka itu.
    function carRecalculate() {
        let total = 0;

        carBody.querySelectorAll('[data-car-amount]').forEach(function (input) {
            const digits = input.value.replace(/[^0-9]/g, '');
            total += digits === '' ? 0 : parseInt(digits, 10);
        });

        carTotalEl.textContent = total.toLocaleString('id-ID');

        const diff = carAdvance - total;

        if (total === 0) {
            carDiffEl.textContent = '';
            carDiffEl.className = 'px-3 py-2 text-xs';
            return;
        }

        if (Math.abs(diff) < 0.005) {
            carDiffEl.textContent = 'Fully spent — nothing to settle.';
            carDiffEl.className = 'px-3 py-2 text-xs text-gray-500';
        } else if (diff > 0) {
            carDiffEl.textContent = 'Refund ' + diff.toLocaleString('id-ID') + ' — you return the remainder.';
            carDiffEl.className = 'px-3 py-2 text-xs text-green-700';
        } else {
            carDiffEl.textContent = 'Claim ' + Math.abs(diff).toLocaleString('id-ID')
                                  + ' — the company reimburses you.';
            carDiffEl.className = 'px-3 py-2 text-xs text-amber-700';
        }
    }

    document.getElementById('carAddRow').addEventListener('click', carAddRow);

    carBody.addEventListener('click', async function (event) {
        const button = event.target.closest('[data-car-remove]');
        if (!button) return;

        // Baris terakhir tidak dihapus, hanya dikosongkan — tabel tanpa satu pun
        // baris membuat pengguna harus menebak bahwa ada tombol "Add line".
        if (carBody.querySelectorAll('[data-car-row]').length === 1) {
            button.closest('[data-car-row]').querySelectorAll('input').forEach(i => i.value = '');
            carRecalculate();
            return;
        }

        button.closest('[data-car-row]').remove();
        carRenumber();
        carRecalculate();
    });

    carBody.addEventListener('input', function (event) {
        if (event.target.matches('[data-car-amount]')) carRecalculate();
    });

    // ── Mengisi baris yang SUDAH ADA (mode edit) ───────────────────────────
    //
    // 🔴 Baris diisi lewat JavaScript, bukan dirender Blade, dan itu disengaja:
    // template barisnya SATU, dipakai baris baru maupun baris lama. Merender
    // baris lama dengan markup terpisah berarti dua salinan tata letak yang
    // sama — dan salinan yang kedua tidak akan ikut berubah saat yang pertama
    // diperbaiki.
    const carExisting = @json($existingItems);

    function carFillRow(row, data) {
        Object.entries(data).forEach(function ([field, value]) {
            if (value === null || value === '') return;

            const input = row.querySelector('[name$="[' + field + ']"]');
            if (input) input.value = value;
        });

        // Daftar Charged To mengikuti tipe yang baru diisi.
        const typeSelect = row.querySelector('[data-car-cc-type]');
        if (typeSelect) typeSelect.dispatchEvent(new Event('change'));
    }

    if (carExisting.length > 0) {
        carExisting.forEach(function (item) {
            carFillRow(carAddRow(), item);
        });
    } else {
        // Satu baris kosong disiapkan supaya form tidak dibuka dalam keadaan
        // hampa — tabel tanpa baris membuat pengguna harus menebak bahwa ada
        // tombol "Add line".
        carAddRow();
    }

    carRecalculate();

    // Dialog "PRINT DOCUMENT?" — bentuk yang sama dengan CA.
    document.getElementById('carSubmitForm').addEventListener('submit', async function (event) {
        const form = event.target;
        if (form.dataset.confirmed === 'yes') return;

        event.preventDefault();

        // Mengubah laporan memakai konfirmasi yang BERBEDA — field
        // print_after_save memang tidak dirender saat edit, dan yang perlu
        // ditegaskan adalah bahwa angka penyelesaiannya akan dihitung ulang.
        if (@json($isEdit)) {
            const saveIt = await showConfirm(
                'Save these changes? The reported total and the settlement figure are '
                + 'recalculated from the expense lines below.',
                'Save changes?',
                'warning',
                { okText: 'Yes, save changes', cancelText: 'Keep editing' }
            );

            if (!saveIt) return;

            form.dataset.confirmed = 'yes';
            form.submit();
            return;
        }

        const printIt = await showConfirm(
            'Make sure your browser allows pop-ups or new tabs if you want to open the report '
            + 'print document right away.',
            'PRINT DOCUMENT?',
            'info',
            { okText: 'Yes, save and print!', cancelText: 'No, just save document!' }
        );

        document.getElementById('carPrintAfterSave').value = printIt ? '1' : '0';
        form.dataset.confirmed = 'yes';
        form.submit();
    });
</script>
@endpush

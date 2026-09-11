@extends('dashboard')

@php
    use App\Models\CashAdvance\CashAdvance;

    // ═══════════════════════════════════════════════════════════════════════
    // SATU FORM UNTUK TIGA KEPERLUAN — dan itu disengaja.
    //
    // ESS "Submit CA" · HR "New CA" (atas nama karyawan) · "Edit CA".
    // Membuat berkas terpisah untuk masing-masing berarti tiga salinan aturan
    // yang sama — batas nominal, Charged To, pilihan Approver — dan salinan
    // pasti menyimpang. Yang menyimpang di sini adalah form dokumen keuangan.
    // Pola yang sama dipakai Purchase Request (`form.blade.php`).
    //
    // Pemanggil yang tidak mengirim apa pun mendapat perilaku ESS "buat baru"
    // seperti sebelumnya — itulah sebabnya seluruh variabel di bawah punya
    // nilai bawaan.
    // ═══════════════════════════════════════════════════════════════════════
    $mode      = $mode      ?? 'create';
    $document  = $document  ?? null;   // CashAdvance yang sedang diubah
    $employees = $employees ?? null;   // null = sisi ESS, tidak memilih siapa pun
    $action    = $action    ?? route('general.my-cash-advance.store');
    $backRoute = $backRoute ?? route('general.my-cash-advance.index');

    $isEdit = $mode === 'edit';

    // Nilai field: isian yang gagal validasi menang atas isi dokumen, dan isi
    // dokumen menang atas bawaan. Urutan ini penting — tanpa `old()` di depan,
    // satu galat validasi akan menghapus seluruh ketikan pengguna.
    $val = fn (string $field, $fallback = null) => old($field, $document->{$field} ?? $fallback);

    // 🔴 Tanggal dan nominal TIDAK boleh lewat $val() apa adanya.
    //
    // `request_date` di-cast jadi Carbon, sehingga menaruhnya langsung pada
    // <input type="date"> menghasilkan "2026-09-09 00:00:00" — nilai yang
    // ditolak diam-diam oleh peramban, dan field-nya tampil KOSONG. Itu bukan
    // galat yang terlihat; itu isian yang hilang tanpa suara.
    $dateVal = fn (string $field) => old($field, $document?->{$field}?->format('Y-m-d'));

    // Nominal disimpan `500000.00` tetapi form ini membaca "500.000" (lihat
    // parseAmount()). Ditampilkan dalam bentuk yang sama dengan yang diketik
    // pengguna, bukan bentuk penyimpanannya.
    $amountVal = old('amount', $document
        ? number_format((float) $document->amount, 0, ',', '.')
        : null);

    $heading = $isEdit
        ? 'Edit Cash Advance'
        : ($employees !== null ? 'New Cash Advance' : 'Create Cash Advance');
@endphp

@section('title', $heading)
@section('page-title', $heading)
@section('page-subtitle', $isEdit
    ? 'Change an open cash advance — the approval flow is kept, not restarted'
    : 'Create a new cash advance (CA) request for operational needs')

@section('content')

{{--
    🔴 LEBAR PENUH, dan tata letaknya grid berlabel-ATAS.

    Versi pertama memakai `max-w-4xl` dengan baris label-kiri (`grid-cols-4`) —
    kombinasi paling sempit di seluruh modul (tetangganya `max-w-6xl`), sehingga
    di layar lebar separuh halaman kosong sementara isiannya berdesakan. Pemilik
    sistem menemukannya langsung.

    Bentuk sekarang:
      < md   satu kolom, label di atas field  (ponsel)
      md     dua kolom
      xl     tiga kolom
    Field yang isinya panjang — Description, Charged To, Notes — selalu memakai
    lebar penuh, karena memotongnya jadi sepertiga justru membuatnya lebih sulit
    dibaca, bukan lebih rapi.

    Label di ATAS, bukan di kiri: pada layar sempit label-kiri memaksa field
    menciut sampai tidak terbaca, dan itu terjadi tepat di perangkat yang paling
    sering dipakai karyawan untuk mengajukan.
--}}
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

    {{-- Tanpa satu langkah alur yang aktif, dokumen tidak dapat ditinjau siapa
         pun. Dikatakan di sini, bukan dibiarkan gagal setelah tombol ditekan. --}}
    @if($steps->isEmpty() && ! $isEdit)
        <div class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3">
            <p class="text-sm text-amber-900">
                <span class="font-semibold">No approval step is configured yet.</span>
                Ask an administrator to set one up in Management → HR &amp; General →
                Cash Advance Settings before submitting.
            </p>
        </div>
    @endif

    <form method="POST" action="{{ $action }}" id="caSubmitForm"
          class="bg-white rounded-xl p-5 md:p-6 shadow-sm"
          data-mode="{{ $mode }}">
        @csrf

        {{-- Dua baris judul, meniru kartu pada aplikasi acuan. --}}
        <div class="text-center mb-6 pb-4 border-b border-gray-100">
            <p class="text-sm font-bold tracking-wide text-gray-900">CASH ADVANCE</p>
            <p class="text-sm font-bold tracking-wide primary-text">{{ strtoupper($settings->company_name) }}</p>
        </div>

        {{-- Pilihan cetak dari dialog konfirmasi. Dikirim sebagai field, bukan
             ditangani JavaScript sesudah simpan: nomor dokumennya baru ada
             SETELAH tersimpan, jadi halaman cetaknya memang hanya dapat dituju
             dari sisi server.

             Hanya saat MEMBUAT. Mengubah dokumen yang nomornya sudah ada tidak
             perlu ditawari cetak — jalannya sudah tersedia di halaman detail. --}}
        @unless($isEdit)
            <input type="hidden" name="print_after_save" id="caPrintAfterSave" value="0">
        @endunless

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-x-6 gap-y-4">

            {{-- ── Document No. — penanda saja, tidak dikirim ─────────────── --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Document No.</label>
                <input type="text" disabled
                       value="{{ $isEdit ? $document->request_no : 'Auto-generated on submit' }}"
                       class="w-full px-3 py-2 border border-gray-200 bg-gray-50 rounded-lg text-sm text-gray-500">
            </div>

            {{-- ── Tanggal ────────────────────────────────────────────────── --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Date <span class="text-red-600">*</span>
                </label>
                <input type="date" name="request_date" required
                       value="{{ $dateVal('request_date') ?: now()->toDateString() }}"
                       @if($minDate) min="{{ $minDate }}" @endif
                       @if($maxDate) max="{{ $maxDate }}" @endif
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-1 primary-border">
            </div>

            @if($settings->allow_date_range)
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date (until)</label>
                <input type="date" name="request_date_to"
                       value="{{ $dateVal('request_date_to') }}"
                       @if($minDate) min="{{ $minDate }}" @endif
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-1 primary-border">
                {{-- Teksnya merah persis seperti acuan — ini penanda "boleh
                     dikosongkan", bukan galat. --}}
                <p class="text-xs text-red-600 mt-1">* OPTIONAL (use if a date range is needed)</p>
            </div>
            @endif

            {{-- ── Description — selalu lebar penuh ───────────────────────── --}}
            <div class="md:col-span-2 xl:col-span-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Description <span class="text-red-600">*</span>
                </label>
                <textarea name="description" rows="2" required
                          minlength="{{ $settings->require_description_min_chars }}" maxlength="255"
                          placeholder="Example: EC Project operations June 2026"
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-1 primary-border">{{ $val('description') }}</textarea>
                <p class="text-xs text-gray-400 mt-1">
                    This single line is what gets printed on the document.
                    At least {{ $settings->require_description_min_chars }} characters.
                </p>
            </div>

            {{-- ── Currency ───────────────────────────────────────────────── --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Currency</label>
                @php $currencies = $settings->currencyOptions(); @endphp
                <select name="currency" @disabled(count($currencies) === 1)
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-1 primary-border disabled:bg-gray-50 disabled:text-gray-500">
                    @foreach($currencies as $code)
                        <option value="{{ $code }}" @selected($code === ($document->currency ?? $settings->defaultCurrency()))>{{ $code }}</option>
                    @endforeach
                </select>
                {{-- Dropdown terkunci saat hanya ada satu pilihan: bentuknya tetap
                     sama dengan acuan, tetapi tidak berpura-pura menawarkan
                     pilihan yang tidak ada. Nilainya ditetapkan server dari
                     setelan, jadi field mati ini aman. --}}
                @if(count($currencies) === 1)
                    <p class="text-xs text-gray-400 mt-1">Only {{ $currencies[0] }} is enabled in settings.</p>
                @endif
            </div>

            {{-- ── Amount ─────────────────────────────────────────────────── --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Amount <span class="text-red-600">*</span>
                </label>
                {{-- 🔴 type="text", BUKAN number. Acuan menampilkan "100.000", dan
                     input number menolak titik sebagai pemisah ribuan. Server
                     menguraikannya lewat parseAmount() yang sudah ber-unit-test —
                     termasuk kasus "100.000" yang oleh `(float)` polos dibaca
                     sebagai 100. --}}
                <input type="text" name="amount" required inputmode="numeric" maxlength="30"
                       value="{{ $amountVal }}" placeholder="0" id="caAmount"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-1 primary-border">
                <p class="text-xs text-gray-400 mt-1" id="caAmountHint">
                    Use a dot as the thousands separator, e.g. 350.000
                </p>
            </div>

            {{-- ── Detail URL ─────────────────────────────────────────────── --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Detail URL
                    @if($settings->require_detail_url)<span class="text-red-600">*</span>@endif
                </label>
                <input type="url" name="detail_url" value="{{ $val('detail_url') }}" maxlength="500"
                       placeholder="https://..."
                       @required($settings->require_detail_url)
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-1 primary-border">
                @php $hosts = $settings->allowedUrlHosts(); @endphp
                @if($hosts !== [])
                    <p class="text-xs text-gray-400 mt-1">
                        A link, not an upload. Allowed: {{ implode(', ', $hosts) }}.
                    </p>
                @endif
            </div>

            {{-- ── Charged To — hanya bila ada tipe yang diaktifkan ────────── --}}
            @php $ccTypes = $settings->costCenterTypeOptions(); @endphp
            @if($ccTypes !== [])
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Charged To
                        @if($settings->require_cost_center)<span class="text-red-600">*</span>@endif
                    </label>
                    <select name="cost_center_type" id="caCostCenterType"
                            @required($settings->require_cost_center)
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-1 primary-border">
                        <option value="">— none —</option>
                        @foreach($ccTypes as $type)
                            <option value="{{ $type }}" @selected($val('cost_center_type') === $type)>
                                {{ ucfirst($type) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Dua daftar terpisah, dan hanya yang sesuai tipenya yang
                     terlihat — pelajaran yang sama dengan kolom Reference di
                     halaman Settings (Keputusan D149). Keduanya menempati SATU
                     sel grid, jadi tata letaknya tidak bergeser saat bertukar. --}}
                <div class="md:col-span-1 xl:col-span-1">
                    <label class="block text-sm font-medium text-gray-700 mb-1" id="caCostCenterLabel">
                        Charged To — target
                    </label>

                    <div data-cc-for="{{ CashAdvance::COST_CENTER_BRANCH }}">
                        <select name="charged_branch_id"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-1 primary-border">
                            <option value="">— choose a branch —</option>
                            @foreach($costCenters['branch'] as $option)
                                <option value="{{ $option['id'] }}" @selected((int) $val('charged_branch_id') === $option['id'])>
                                    {{ $option['label'] }}
                                </option>
                            @endforeach
                        </select>
                        @if($costCenters['branch'] === [])
                            <p class="text-xs text-red-600 mt-1">
                                No active branch exists yet — ask an administrator to create one.
                            </p>
                        @endif
                    </div>

                    <div data-cc-for="{{ CashAdvance::COST_CENTER_PROJECT }}">
                        <select name="charged_project_id"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-1 primary-border">
                            <option value="">— choose a project —</option>
                            @foreach($costCenters['project'] as $option)
                                <option value="{{ $option['id'] }}" @selected((int) $val('charged_project_id') === $option['id'])>
                                    {{ $option['label'] }}
                                </option>
                            @endforeach
                        </select>
                        {{-- Sejalan dengan daftar cabang di atas (D164): daftar
                             kosong menjelaskan dirinya, tidak diam saja. --}}
                        @if($costCenters['project'] === [])
                            <p class="text-xs text-red-600 mt-1">
                                No active project exists yet.
                            </p>
                        @endif
                    </div>

                    <p class="text-xs text-gray-400 mt-1" data-cc-empty>Choose a type first.</p>
                </div>
            @endif

            {{-- ── Requester ──────────────────────────────────────────────────
                 TIGA keadaan, dan perbedaannya bukan kosmetik:

                 1. sisi ESS          → teks mati. `employee_id` SELALU diambil
                    dari sesi di controller, tidak pernah dari badan request.
                    Ditampilkan hanya supaya pemohon melihat atas nama siapa
                    dokumen ini dibuat.
                 2. sisi HR, buat baru → dropdown karyawan ("New CA").
                 3. sisi HR, mengubah  → 🔴 TEKS MATI LAGI. Memindahkan dokumen
                    keuangan ke nama orang lain di tengah alur bukan "mengubah
                    dokumen", itu membuat dokumen lain. Nomornya sudah beredar,
                    penyetujunya sudah membaca nama pemohonnya. Kalau salah
                    orang, dokumennya dihapus beralasan lalu dibuat ulang —
                    dan jejak itu memang yang seharusnya tertinggal.
                 ────────────────────────────────────────────────────────────── --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Requester
                    @if($employees !== null && ! $isEdit)<span class="text-red-600">*</span>@endif
                </label>

                @if($employees !== null && ! $isEdit)
                    <select name="employee_id" required id="caRequester"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-1 primary-border">
                        <option value="">— choose an employee —</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->employee_id }}"
                                    @selected((int) old('employee_id') === (int) $employee->employee_id)>
                                {{ $employee->basicData?->nick_name ?? $employee->eci }}
                                @if($employee->basicData?->department)
                                    — {{ $employee->basicData->department }}
                                @endif
                            </option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-400 mt-1">
                        The document is filed under this employee. You are recorded as its creator.
                    </p>
                @else
                    <input type="text" disabled
                           value="{{ $isEdit
                               ? ($document->employee?->basicData?->nick_name ?? $requesterName)
                               : $requesterName }}"
                           class="w-full px-3 py-2 border border-gray-200 bg-gray-50 rounded-lg text-sm text-gray-600">
                    @if($isEdit)
                        <p class="text-xs text-gray-400 mt-1">
                            The requester cannot be changed after the document exists.
                        </p>
                    @endif
                @endif
            </div>

            {{-- ══════════════════════════════════════════════════════════════
                 APPROVER — 🔴 INILAH PERBAIKAN ATAS CACAT APLIKASI ACUAN.

                 Di sana, server menuntut `approver_employee_id` sementara
                 formnya tidak pernah merender kontrolnya; pengguna menekan
                 Submit dan menerima
                   "The approver_employee_id field must only contain digits…"
                 tanpa satu pun cara untuk berhasil.

                 Di sini kontrolnya BENAR-BENAR dirender bila langkah pertama
                 ditandai "Chosen by requester" di Settings. Bila tidak,
                 fieldnya TIDAK dikirim dan TIDAK divalidasi — tidak ada jalan
                 bagi form untuk mengirim sesuatu yang server tolak.
                 ══════════════════════════════════════════════════════════════ --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Approver
                    @if($chooseApprover)<span class="text-red-600">*</span>@endif
                </label>

                @if($isEdit)
                    {{-- 🔴 Alur dokumen ini sudah DIBEKUKAN saat dibuat, dan
                         mengubah isinya tidak memutarnya kembali ke langkah
                         pertama (lihat CashAdvanceService::update()). Menawarkan
                         dropdown Approver di sini akan menyiratkan penyetujunya
                         masih bisa diganti — padahal sebagian dari mereka mungkin
                         sudah meninjau. Yang ditampilkan keadaan sebenarnya. --}}
                    <input type="text" disabled
                           value="{{ $document->currentStepName() ?? $document->statusLabel() }}"
                           class="w-full px-3 py-2 border border-gray-200 bg-gray-50 rounded-lg text-sm text-gray-600">
                    <p class="text-xs text-gray-400 mt-1">
                        Frozen when the document was created — editing does not restart the flow.
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
                                    @selected((int) old('approver_ids.' . $firstStep->order_seq) === $candidate['id']
                                              || count($approverCandidates) === 1)>
                                {{ $candidate['name'] }}
                            </option>
                        @endforeach
                    </select>

                    {{-- Kandidat tunggal: dropdown tetap dirender dalam keadaan
                         terkunci supaya pemohon MELIHAT siapa yang akan menerima
                         dokumennya, bukan menebak. Fieldnya tidak terkirim saat
                         disabled, dan itu tidak apa-apa: service mengisinya
                         sendiri bila hanya ada satu kandidat. --}}
                    @if(count($approverCandidates) === 1)
                        <p class="text-xs text-gray-400 mt-1">
                            Only one person can review step &ldquo;{{ $firstStep->name }}&rdquo;.
                        </p>
                    @else
                        <p class="text-xs text-gray-400 mt-1">
                            Step &ldquo;{{ $firstStep->name }}&rdquo; lets you pick who reviews it.
                        </p>
                    @endif
                @else
                    <input type="text" disabled
                           value="{{ $firstStep?->name ?? 'No approval step configured' }}"
                           class="w-full px-3 py-2 border border-gray-200 bg-gray-50 rounded-lg text-sm text-gray-600">
                    <p class="text-xs text-gray-400 mt-1">
                        Set by configuration — {{ $firstStep?->approverLabel() ?? '—' }}.
                    </p>
                @endif
            </div>

            {{-- ── Additional Notes — lebar penuh ─────────────────────────── --}}
            <div class="md:col-span-2 xl:col-span-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Additional Notes</label>
                <input type="text" name="notes" value="{{ $val('notes') }}" maxlength="2000"
                       placeholder="Internal note if any"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-1 primary-border">
            </div>
        </div>

        {{-- Tombol menumpuk penuh di ponsel, mengambang ke kanan di layar lebar —
             pada layar sempit tombol kecil di tengah adalah sasaran sentuh yang
             buruk. --}}
        <div class="flex flex-col sm:flex-row sm:justify-end gap-2 mt-6 pt-5 border-t border-gray-100">
            <a href="{{ $backRoute }}"
               class="w-full sm:w-auto text-center px-6 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                Cancel
            </a>
            {{-- Saat mengubah, tidak ada langkah baru yang perlu dibuat, jadi
                 alur kosong tidak boleh mengunci tombolnya — dokumennya sudah
                 punya alur sendiri. --}}
            <button type="submit" @disabled(! $isEdit && $steps->isEmpty())
                    class="w-full sm:w-auto px-6 py-2.5 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-gray-900 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                {{ $isEdit ? 'Save Changes' : 'Submit' }}
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    // ── Charged To: hanya tampilkan daftar yang sesuai tipenya ──────────────
    // Pelajaran yang sama dengan kolom Reference di halaman Settings (D149):
    // dua kontrol berdampingan yang tidak berhubungan membuat layar seolah
    // menjanjikan hubungan yang tidak ada.
    const caTypeSelect = document.getElementById('caCostCenterType');
    const caTypeLabel  = document.getElementById('caCostCenterLabel');

    function caSyncCostCenter() {
        const type = caTypeSelect ? caTypeSelect.value : '';

        document.querySelectorAll('[data-cc-for]').forEach(function (box) {
            box.hidden = box.dataset.ccFor !== type;
        });

        document.querySelectorAll('[data-cc-empty]').forEach(function (hint) {
            hint.hidden = type !== '';
        });

        if (caTypeLabel) {
            caTypeLabel.textContent = type === ''
                ? 'Charged To — target'
                : 'Charged To — ' + type.charAt(0).toUpperCase() + type.slice(1);
        }
    }

    if (caTypeSelect) {
        caSyncCostCenter();
        caTypeSelect.addEventListener('change', caSyncCostCenter);
    }

    // ── Nominal: bantu baca angkanya sambil diketik ─────────────────────────
    // 🔴 Hanya BANTUAN BACA, bukan penjagaan. Yang menentukan tetap
    // CashAdvanceAmountService::parseAmount() di server — sudah ber-unit-test,
    // termasuk kasus "100.000" yang oleh `(float)` polos dibaca sebagai 100.
    const caAmount = document.getElementById('caAmount');
    const caHint   = document.getElementById('caAmountHint');

    if (caAmount && caHint) {
        caAmount.addEventListener('input', function () {
            const digits = caAmount.value.replace(/[^0-9]/g, '');

            caHint.textContent = digits === ''
                ? 'Use a dot as the thousands separator, e.g. 350.000'
                : 'Reads as: ' + Number(digits).toLocaleString('id-ID');
        });
    }

    // ── Dialog "PRINT DOCUMENT?" — meniru acuan, memakai showConfirm() ──────
    // 🔴 showConfirm(), bukan confirm() bawaan peramban. showPrompt() TIDAK ADA
    // di aplikasi ini; hanya showConfirm() dan showToast().
    document.getElementById('caSubmitForm').addEventListener('submit', async function (event) {
        const form = event.target;
        if (form.dataset.confirmed === 'yes') return;

        event.preventDefault();

        // 🔴 Mengubah dokumen memakai konfirmasi yang BERBEDA. Menawarkan
        // "save and print" di sini keliru dua kali: field print_after_save
        // memang tidak dirender saat edit (JS-nya akan melempar), dan yang
        // perlu ditegaskan bukan soal cetak melainkan bahwa dokumen yang
        // sedang ditinjau orang lain akan berubah isinya.
        if (form.dataset.mode === 'edit') {
            const saveIt = await showConfirm(
                'Save these changes? Approvers who already reviewed this document keep their '
                + 'decision — the flow is not restarted, but the figures they saw will change.',
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
            'Make sure your browser allows pop-ups or new tabs if you want to open the cash advance '
            + 'print document right away.',
            'PRINT DOCUMENT?',
            'info',
            { okText: 'Yes, save and print!', cancelText: 'No, just save document!' }
        );

        // 🔴 Kedua tombol MENYIMPAN — persis seperti acuan. Yang membedakan hanya
        // ke mana pengguna dibawa sesudahnya, dan itu diputuskan server lewat
        // field tersembunyi di atas. Membatalkan penyimpanan pada pilihan "No"
        // akan membuang isian yang sudah diketik pengguna hanya karena ia tidak
        // ingin mencetak.
        document.getElementById('caPrintAfterSave').value = printIt ? '1' : '0';

        form.dataset.confirmed = 'yes';
        form.submit();
    });
</script>
@endpush

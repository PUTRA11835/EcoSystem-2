@extends('dashboard')

@section('title', 'Cash Advance Settings')
@section('page-title', 'Cash Advance Settings')
@section('page-subtitle', 'Request rules, settlement rules, and both approval workflows')

@section('content')
@php
    use App\Models\CashAdvance\CashAdvance;
    use App\Models\CashAdvance\CashAdvanceApprovalStep;
    use App\Models\CashAdvance\CashAdvanceSetting;
@endphp

{{-- 🔴 Lebar PENUH atas permintaan pemilik sistem. Tetangganya (Overtime,
     Reimbursement, Purchase Request Settings) masih `max-w-6xl`; ketiganya
     SENGAJA tidak ikut diubah di sini karena sudah teruji dan menuju
     produksi. Menyamakannya harus diminta terpisah. --}}
<div class="w-full space-y-5">

    {{-- 🔴 Diberitahukan di layar, bukan didiamkan. Tanpa cabang aktif, dropdown
         pembebanan hampa dan `require_cost_center` akan menolak SETIAP pengajuan
         tanpa memberi petunjuk apa pun. Setelan yang mengunci modul tanpa
         penjelasan adalah persis kegagalan D52. --}}
    @if($branchCount === 0 && $settings->require_cost_center)
        <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3">
            <p class="text-sm text-red-900">
                <span class="font-semibold">No active branch exists, but “Require cost center” is on.</span>
                Every cash advance will be rejected. Either create a branch under
                Settings → Branches, or turn the requirement off below.
            </p>
        </div>
    @elseif($branchCount === 0)
        <div class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3">
            <p class="text-sm text-amber-900">
                <span class="font-semibold">No active branch exists yet.</span>
                The “Charged to → Branch” dropdown will be empty until one is created under
                Settings → Branches. Charging to a project still works.
            </p>
        </div>
    @endif

    {{-- =============================================================
         BAGIAN 1 & 2 — DUA ALUR PERSETUJUAN
         Ditaruh paling atas karena inilah yang paling sering diubah, dan
         karena tanpa satu langkah aktif pun modulnya tidak jalan.
         ============================================================= --}}
    @include('hr-general.settings._ca_step_editor', [
        'module'    => CashAdvanceApprovalStep::MODULE_CA,
        'steps'     => $caSteps,
        'openCount' => $caOpenCount,
        'heading'   => 'Cash Advance — Approval Workflow',
        'blurb'     => 'Approval for submitted cash advance requests. Step order runs from top to bottom.',
    ])

    @include('hr-general.settings._ca_step_editor', [
        'module'    => CashAdvanceApprovalStep::MODULE_CAR,
        'steps'     => $carSteps,
        'openCount' => $carOpenCount,
        'heading'   => 'Cash Advance Report — Approval Workflow',
        'blurb'     => 'Approval for settlement reports. Approving the last step closes the cash advance.',
    ])

    {{-- =============================================================
         BAGIAN 3 — ATURAN DOKUMEN
         ============================================================= --}}
    <form method="POST" action="{{ route('management.cash-advance-settings.update') }}" class="space-y-5">
        @csrf

        <div class="bg-white rounded-xl p-5 md:p-6 shadow-sm">
            <div class="mb-5 pb-4 border-b-2 border-gray-100">
                <h2 class="text-2xl font-bold text-gray-900">Cash Advance Rules</h2>
                <p class="text-sm text-gray-500 mt-0.5">
                    Every rule that can reject a request can be relaxed here — no code change needed.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">

                {{-- Identitas cetakan --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Company name</label>
                    <input type="text" name="company_name" value="{{ old('company_name', $settings->company_name) }}"
                           required maxlength="150"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-1 primary-border">
                    <p class="text-xs text-gray-400 mt-1">Printed on the second line of the document header.</p>
                </div>

                <div class="flex items-end">
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="use_branch_name_in_header" value="1"
                               @checked(old('use_branch_name_in_header', $settings->use_branch_name_in_header))
                               class="w-4 h-4 rounded border-gray-300">
                        Use the branch name in the header when the document is charged to one branch
                    </label>
                </div>

                {{-- Tanggal --}}
                <div class="flex items-end">
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="allow_future_date" value="1"
                               @checked(old('allow_future_date', $settings->allow_future_date))
                               class="w-4 h-4 rounded border-gray-300">
                        Allow future dates
                        <span class="text-xs text-gray-400">(on by default — a cash advance funds work not yet done)</span>
                    </label>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Maximum backdating (days)</label>
                    <input type="number" name="max_backdate_days" min="0" max="3650"
                           value="{{ old('max_backdate_days', $settings->max_backdate_days) }}" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-1 primary-border">
                    <p class="text-xs text-gray-400 mt-1">0 means no limit.</p>
                </div>

                <div class="flex items-end">
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="allow_date_range" value="1"
                               @checked(old('allow_date_range', $settings->allow_date_range))
                               class="w-4 h-4 rounded border-gray-300">
                        Show the optional second date (date range)
                    </label>
                </div>

                <div></div>

                {{-- Nominal --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Minimum amount</label>
                    <input type="number" name="min_amount" step="0.01" min="0"
                           value="{{ old('min_amount', $settings->min_amount) }}" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-1 primary-border">
                    <p class="text-xs text-gray-400 mt-1">0 means no limit. Below the minimum is always rejected.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Maximum amount</label>
                    <input type="number" name="max_amount" step="0.01" min="0"
                           value="{{ old('max_amount', $settings->max_amount) }}" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-1 primary-border">
                    <p class="text-xs text-gray-400 mt-1">0 means no limit.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Over the maximum</label>
                    <select name="over_limit_policy"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-1 primary-border">
                        <option value="{{ CashAdvanceSetting::LIMIT_FLAG }}"
                                @selected(old('over_limit_policy', $settings->over_limit_policy) === CashAdvanceSetting::LIMIT_FLAG)>
                            Flag it — accept the request but mark it
                        </option>
                        <option value="{{ CashAdvanceSetting::LIMIT_BLOCK }}"
                                @selected(old('over_limit_policy', $settings->over_limit_policy) === CashAdvanceSetting::LIMIT_BLOCK)>
                            Block it — reject the request
                        </option>
                    </select>
                </div>

                {{-- Mata uang — jawaban C5: IDR saja, wadah CSV tetap ada supaya
                     menambah mata uang kelak tidak menuntut migrasi. --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Allowed currencies</label>
                    <input type="text" name="allowed_currencies"
                           value="{{ old('allowed_currencies', $settings->allowed_currencies) }}" maxlength="100"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-1 primary-border">
                    <p class="text-xs text-gray-400 mt-1">
                        Comma separated. <span class="font-medium">No currency conversion is built</span> —
                        a report must use the same currency as its cash advance.
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Default currency</label>
                    <input type="text" name="default_currency"
                           value="{{ old('default_currency', $settings->default_currency) }}" maxlength="3" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-1 primary-border">
                    <p class="text-xs text-gray-400 mt-1">Falls back to the first allowed currency if it is not in the list.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Minimum description length</label>
                    <input type="number" name="require_description_min_chars" min="0" max="255"
                           value="{{ old('require_description_min_chars', $settings->require_description_min_chars) }}" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-1 primary-border">
                </div>

                {{-- Bukti pendukung --}}
                <div class="flex items-end">
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="require_detail_url" value="1"
                               @checked(old('require_detail_url', $settings->require_detail_url))
                               class="w-4 h-4 rounded border-gray-300">
                        Require a supporting document link
                    </label>
                </div>

                <div class="md:col-span-2 xl:col-span-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Allowed link hosts</label>
                    <input type="text" name="detail_url_allowed_hosts"
                           value="{{ old('detail_url_allowed_hosts', $settings->detail_url_allowed_hosts) }}" maxlength="255"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-1 primary-border">
                    <p class="text-xs text-gray-400 mt-1">
                        Comma separated. Links are stored, never uploaded files. Leave empty to allow any host.
                    </p>
                </div>

                {{-- Pembebanan — jawaban C4 --}}
                <div class="flex items-end">
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="require_cost_center" value="1"
                               @checked(old('require_cost_center', $settings->require_cost_center))
                               class="w-4 h-4 rounded border-gray-300">
                        Require a cost center
                    </label>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Allowed cost center types</label>
                    <div class="flex items-center gap-5 mt-2">
                        @foreach(CashAdvance::COST_CENTER_TYPES as $type)
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" name="allowed_cost_center_types[]" value="{{ $type }}"
                                       @checked(in_array($type, $settings->costCenterTypeOptions(), true))
                                       class="w-4 h-4 rounded border-gray-300">
                                {{ ucfirst($type) }}
                            </label>
                        @endforeach
                    </div>
                    <p class="text-xs text-gray-400 mt-1">Unchecking one removes its dropdown — no code change.</p>
                </div>

                {{-- Persetujuan --}}
                <div class="flex items-end">
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="allow_self_approval" value="1"
                               @checked(old('allow_self_approval', $settings->allow_self_approval))
                               class="w-4 h-4 rounded border-gray-300">
                        Allow approving your own request
                    </label>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fallback reviewer position</label>
                    <select name="self_approval_fallback_role_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-1 primary-border">
                        <option value="">— none —</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->id }}"
                                    @selected((int) old('self_approval_fallback_role_id', $settings->self_approval_fallback_role_id) === (int) $role->id)>
                                {{ $role->name }}
                            </option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-400 mt-1">
                        Named in the rejection message when self-approval is blocked, so the requester
                        knows who to ask instead.
                    </p>
                </div>

                <div class="flex items-end">
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="allow_approver_adjust_amount" value="1"
                               @checked(old('allow_approver_adjust_amount', $settings->allow_approver_adjust_amount))
                               class="w-4 h-4 rounded border-gray-300">
                        Let the approver on duty adjust the amount
                    </label>
                </div>

                <div class="flex items-end">
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="allow_requester_cancel" value="1"
                               @checked(old('allow_requester_cancel', $settings->allow_requester_cancel))
                               class="w-4 h-4 rounded border-gray-300">
                        Let the requester cancel while still “Submitted”
                    </label>
                </div>

                <div class="md:col-span-2 xl:col-span-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Locked accounting period</label>
                    <select name="locked_period_policy"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-1 primary-border">
                        <option value="{{ CashAdvanceSetting::LOCK_OFF }}"
                                @selected(old('locked_period_policy', $settings->locked_period_policy) === CashAdvanceSetting::LOCK_OFF)>
                            Ignore the lock
                        </option>
                        <option value="{{ CashAdvanceSetting::LOCK_BLOCK_EMPLOYEE }}"
                                @selected(old('locked_period_policy', $settings->locked_period_policy) === CashAdvanceSetting::LOCK_BLOCK_EMPLOYEE)>
                            Block employees — holders of the manage permission can still act
                        </option>
                        <option value="{{ CashAdvanceSetting::LOCK_BLOCK_ALL }}"
                                @selected(old('locked_period_policy', $settings->locked_period_policy) === CashAdvanceSetting::LOCK_BLOCK_ALL)>
                            Block everyone
                        </option>
                    </select>
                </div>
            </div>
        </div>

        {{-- =============================================================
             BAGIAN 4 — TANDA TANGAN CETAKAN
             ============================================================= --}}
        <div class="bg-white rounded-xl p-5 md:p-6 shadow-sm">
            <div class="mb-5 pb-4 border-b-2 border-gray-100">
                <h2 class="text-2xl font-bold text-gray-900">Document Signatures</h2>
                <p class="text-sm text-gray-500 mt-0.5">
                    The printed document carries four columns: Requester · Accounting · Cashier · Approved by.
                </p>
            </div>

            {{-- 🔴 Dijelaskan, bukan didiamkan. Kalau blok ini hanya menampilkan dua
                 field tanpa keterangan, orang akan mencari field ketiga yang memang
                 SENGAJA tidak ada — dan menyimpulkan halamannya belum selesai. --}}
            <div class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-3 mb-5">
                <p class="text-sm text-blue-900">
                    <span class="font-semibold">There is deliberately no field for “Approved by”.</span>
                    That column takes the name of whoever actually approved the document, on the workflow
                    step whose Actor is “Approver”. Storing a signatory in two places would create one new
                    class of mistake: the setting says one name, the approval history says another, and the
                    paper someone signed says a third.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Accounting</label>
                    <select name="accounting_signer_employee_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-1 primary-border">
                        <option value="">— leave blank —</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->employee_id }}"
                                    @selected((int) old('accounting_signer_employee_id', $settings->accounting_signer_employee_id) === (int) $employee->employee_id)>
                                {{ $employee->basicData?->nick_name ?? $employee->eci }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cashier</label>
                    <select name="cashier_signer_employee_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-1 primary-border">
                        <option value="">— leave blank —</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->employee_id }}"
                                    @selected((int) old('cashier_signer_employee_id', $settings->cashier_signer_employee_id) === (int) $employee->employee_id)>
                                {{ $employee->basicData?->nick_name ?? $employee->eci }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        {{-- =============================================================
             BAGIAN 5 — ATURAN PERTANGGUNGJAWABAN (CAR)
             ============================================================= --}}
        <div class="bg-white rounded-xl p-5 md:p-6 shadow-sm">
            <div class="mb-5 pb-4 border-b-2 border-gray-100">
                <h2 class="text-2xl font-bold text-gray-900">Settlement Rules (CAR)</h2>
                <p class="text-sm text-gray-500 mt-0.5">
                    A cash advance is money already out. These rules govern how it is accounted for.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                <div class="flex items-end">
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="require_car_before_new_ca" value="1"
                               @checked(old('require_car_before_new_ca', $settings->require_car_before_new_ca))
                               class="w-4 h-4 rounded border-gray-300">
                        Block a new cash advance while an earlier one is unsettled
                    </label>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reporting deadline (days)</label>
                    <input type="number" name="car_due_days" min="0" max="3650"
                           value="{{ old('car_due_days', $settings->car_due_days) }}" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-1 primary-border">
                    <p class="text-xs text-gray-400 mt-1">
                        0 means no deadline. Counted from the approval date, and evaluated when a page is
                        opened — <span class="font-medium">no scheduled reminder is sent</span>.
                    </p>
                </div>

                <div class="flex items-end">
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="car_allow_over_amount" value="1"
                               @checked(old('car_allow_over_amount', $settings->car_allow_over_amount))
                               class="w-4 h-4 rounded border-gray-300">
                        Allow spending more than the advance
                        <span class="text-xs text-gray-400">(becomes a “Claim”)</span>
                    </label>
                </div>

                <div class="flex items-end">
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="car_require_receipt_url" value="1"
                               @checked(old('car_require_receipt_url', $settings->car_require_receipt_url))
                               class="w-4 h-4 rounded border-gray-300">
                        Require a receipt link on every expense line
                    </label>
                </div>

                {{-- Jawaban C8 / Keputusan D143 — sakelar, bukan aturan mati. --}}
                <div class="md:col-span-2 xl:col-span-3 border-t border-gray-100 pt-4">
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="car_multiple_per_ca" value="1"
                               @checked(old('car_multiple_per_ca', $settings->car_multiple_per_ca))
                               class="w-4 h-4 rounded border-gray-300">
                        <span class="font-medium">Allow multiple reports per cash advance</span>
                    </label>
                    <p class="text-xs text-gray-500 mt-1.5 leading-relaxed">
                        Off by default — one report per cash advance, which is easier to explain and is what
                        the reference system does. Turn it on only if a single advance is genuinely settled
                        in instalments at different times; daily receipts from one trip already fit in one
                        multi-line report. The settlement figure is always the sum of every approved report,
                        so switching this on needs no further work.
                    </p>
                </div>
            </div>
        </div>

        <div class="flex flex-col sm:flex-row sm:justify-end">
            <button type="submit"
                    class="w-full sm:w-auto px-6 py-2.5 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-gray-900 transition-colors">
                Save settings
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    // 🔴 showConfirm(), bukan confirm() bawaan peramban — permintaan eksplisit
    // pemilik sistem, dan pola yang sudah dipakai seluruh Purchase Request.
    // showPrompt() TIDAK ADA di aplikasi ini; hanya showConfirm() & showToast().
    //
    // Form-nya SUNGGUHAN dan sudah membawa @csrf dari Blade; JavaScript hanya
    // menahan submit-nya sebentar. Membangun form di JavaScript akan memaksa
    // membaca meta tag csrf-token — dan di aplikasi ini ada satu pembacaan meta
    // yang rusak karena kutip tipografis, jadi pola itu sengaja dihindari.
    document.querySelectorAll('.js-ca-delete-step').forEach(function (form) {
        form.addEventListener('submit', async function (event) {
            if (form.dataset.confirmed === 'yes') return;
            event.preventDefault();

            const ok = await showConfirm(
                `Delete the approval step "${form.dataset.name}"? Documents already in progress keep `
                + `their own copy of the workflow and are not affected.`,
                'Delete approval step',
                'danger',
                { okText: 'Delete', cancelText: 'Cancel' }
            );

            if (!ok) return;
            form.dataset.confirmed = 'yes';
            form.submit();
        });
    });

    // ── KOLOM REFERENCE: hanya tampilkan kontrol yang dipakai tipenya ────────
    //
    // 🔴 Memperbaiki kejanggalan yang dilaporkan pemilik sistem: "masa saya ganti
    // posisi, karyawannya tetap sama?". Sebelumnya dropdown posisi DAN daftar
    // karyawan tampil berdampingan apa pun tipenya, sehingga layar seolah
    // menjanjikan hubungan yang memang tidak pernah ada — keduanya milik tipe
    // yang BERBEDA, dan yang tidak dipakai dibuang server saat disimpan.
    //
    // Lingkupnya per baris (`[data-ca-step-row]`), bukan per halaman: satu baris
    // tidak boleh pernah mengubah tampilan baris lain, dan halaman ini memuat
    // DUA editor sekaligus.
    function caSyncReference(scope) {
        const typeSelect = scope.querySelector('[data-ca-type-select]');
        if (!typeSelect) return;

        const type = typeSelect.value;

        scope.querySelectorAll('[data-ref-for]').forEach(function (box) {
            box.hidden = box.dataset.refFor !== type;
        });

        caSyncRoleHint(scope);
    }

    // Menyebutkan berapa orang yang memegang posisi terpilih. Posisi tanpa
    // pemegang berarti langkah ini tidak akan pernah menemukan penyetuju — dan
    // itu harus terbaca SEBELUM disimpan, bukan setelah dokumen mandek.
    function caSyncRoleHint(scope) {
        const roleSelect = scope.querySelector('[data-ca-role-select]');
        const hint       = scope.querySelector('[data-ca-role-hint]');
        if (!roleSelect || !hint) return;

        const option  = roleSelect.selectedOptions[0];
        const holders = option ? parseInt(option.dataset.holders || '0', 10) : 0;

        if (!roleSelect.value) {
            hint.textContent = 'No position chosen yet.';
            hint.className   = 'text-xs mt-1 text-gray-400';
            return;
        }

        if (holders === 0) {
            hint.textContent = 'This position has no employee assigned — a document at this step '
                             + 'would wait for nobody.';
            hint.className   = 'text-xs mt-1 text-red-600';
            return;
        }

        hint.textContent = holders === 1
            ? 'One employee holds this position; the approver is fixed.'
            : holders + ' employees hold this position — any one of them can act.';
        hint.className = 'text-xs mt-1 text-gray-500';
    }

    document.querySelectorAll('[data-ca-step-row]').forEach(function (scope) {
        caSyncReference(scope);

        const typeSelect = scope.querySelector('[data-ca-type-select]');
        if (typeSelect) typeSelect.addEventListener('change', () => caSyncReference(scope));

        const roleSelect = scope.querySelector('[data-ca-role-select]');
        if (roleSelect) roleSelect.addEventListener('change', () => caSyncRoleHint(scope));
    });
</script>
@endpush

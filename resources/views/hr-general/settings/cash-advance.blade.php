@extends('dashboard')

@section('title', 'Cash Advance (CA) — Settings')
@section('page-title', 'Cash Advance (CA)')
@section('page-subtitle', 'Request rules and settlement rules — both approval workflows moved to their own page')

@section('content')
@php
    use App\Models\CashAdvance\CashAdvance;
    use App\Models\CashAdvance\CashAdvanceSetting;
@endphp

@include('hr-general.cash-advance.hub-tabs-cash-advance')

{{-- 🔴 Lebar PENUH atas permintaan pemilik sistem (sudah begini sejak
     sebelum D177 — Reimbursement/Purchase Request Settings baru menyusul
     lebar penuh di D177). --}}
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

    {{-- 🔴 D180 — Kedua Approval Workflow (CA dan CAR) PINDAH ke halaman
         tersendiri, MASING-MASING dengan hak akses TERPISAH dari halaman ini
         dan terpisah SATU SAMA LAIN (bisa diberikan CA saja, CAR saja, atau
         keduanya). Diberitahukan di sini, bukan didiamkan — orang yang
         terbiasa mencari alur di sini akan mengira fiturnya hilang tanpa
         pengingat ini. --}}
    <div class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-3">
        <p class="text-sm text-blue-900">
            <span class="font-semibold">Looking for the approval workflow?</span>
            Both moved to their own page —
            <a href="{{ route('general.approval-workflow.cash-advance') }}" class="underline font-semibold hover:text-blue-700">Cash Advance</a>
            and
            <a href="{{ route('general.approval-workflow.cash-advance-report') }}" class="underline font-semibold hover:text-blue-700">Cash Advance Report</a>
            under Approval Workflow.
        </p>
    </div>

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
                    class="w-full sm:w-auto px-6 py-2.5 primary-gradient text-white rounded-lg text-sm font-medium hover:opacity-90 transition-all">
                Save settings
            </button>
        </div>
    </form>
</div>
@endsection

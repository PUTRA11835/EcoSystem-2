@extends('dashboard')

@section('title', 'Reimbursement — Settings')
@section('page-title', 'Reimbursement')
@section('page-subtitle', 'Reimbursement rules and evidence requirements — the approval workflow moved to its own page')

@section('content')
@php
    use App\Models\Reimbursement\ReimbursementSetting;
@endphp

@include('hr-general.reimbursement.hub-tabs-reimbursement')

{{-- Lebar penuh (D177), mengikuti pola Attendance/Overtime/Cash Advance
     Settings — sebelumnya `max-w-6xl`. --}}
<div class="w-full space-y-5">

    {{-- 🔴 D180 — Approval Workflow PINDAH ke halaman tersendiri, dengan hak
         akses TERPISAH dari halaman ini (izinnya sekarang berbeda slug, bukan
         lagi ikut slug Settings). Diberitahukan di sini, bukan didiamkan —
         orang yang terbiasa mencari alur di sini akan mengira fiturnya hilang
         tanpa pengingat ini. --}}
    <div class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-3">
        <p class="text-sm text-blue-900">
            <span class="font-semibold">Looking for the approval workflow?</span>
            It moved to its own page —
            <a href="{{ route('general.approval-workflow.reimbursement') }}" class="underline font-semibold hover:text-blue-700">Approval Workflow</a>.
        </p>
    </div>

    {{-- =============================================================
         BAGIAN 2 — ATURAN REIMBURSEMENT
         ============================================================= --}}
    <form method="POST" action="{{ route('general.reimbursement.settings.update') }}" class="bg-white rounded-xl p-6 shadow-sm space-y-6">
        @csrf

        <div class="pb-4 border-b-2 border-gray-100">
            <h2 class="text-2xl font-bold text-gray-900">Reimbursement Rules</h2>
            <p class="text-sm text-gray-500 mt-0.5">
                Set <strong>0</strong> where you want no limit at all. Rules that only flag a document
                never stop it from being submitted.
            </p>
        </div>

        {{-- Identitas dokumen cetak --}}
        <div>
            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide mb-3">Printed Document</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Company name</label>
                    <input type="text" name="company_name" maxlength="150" required
                           value="{{ old('company_name', $settings->company_name) }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                    <p class="text-xs text-gray-400 mt-1">Printed on the second line of the document and the Excel export.</p>
                    @error('company_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <label class="flex items-start gap-2">
                    <input type="checkbox" name="use_branch_name_in_header" value="1" @checked($settings->use_branch_name_in_header)
                           class="mt-0.5 w-4 h-4 text-red-800 border-gray-300 rounded focus:ring-red-800">
                    <span class="text-sm">
                        <span class="font-semibold text-gray-800">Use the branch name when possible</span>
                        <span class="block text-xs text-gray-500">
                            If every item on a document charges the same branch, that branch name replaces the
                            company name in the header. Mixed documents always fall back to the company name.
                        </span>
                    </span>
                </label>
            </div>
        </div>

        {{-- Aturan tanggal --}}
        <div class="pt-2">
            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide mb-3">Dates</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <label class="flex items-start gap-2">
                    <input type="checkbox" name="allow_future_date" value="1" @checked($settings->allow_future_date)
                           class="mt-0.5 w-4 h-4 text-red-800 border-gray-300 rounded focus:ring-red-800">
                    <span class="text-sm">
                        <span class="font-semibold text-gray-800">Allow future dates</span>
                        <span class="block text-xs text-gray-500">
                            Normally off: a reimbursement is money already spent.
                        </span>
                    </span>
                </label>

                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Backdate limit (days)</label>
                    <input type="number" name="max_backdate_days" min="0" max="3650" required
                           value="{{ old('max_backdate_days', $settings->max_backdate_days) }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                    <p class="text-xs text-gray-400 mt-1">0 = no limit. This one <strong>blocks</strong>.</p>
                    @error('max_backdate_days')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Closed reporting period</label>
                    <select name="locked_period_policy"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                        @foreach([
                            ReimbursementSetting::LOCK_OFF            => 'Ignore — allow everything',
                            ReimbursementSetting::LOCK_BLOCK_EMPLOYEE => 'Block employees only',
                            ReimbursementSetting::LOCK_BLOCK_ALL      => 'Block everyone',
                        ] as $value => $label)
                            <option value="{{ $value }}" @selected($settings->locked_period_policy === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-400 mt-1">
                        "Block employees only" still lets Reimbursement — Edit / Delete holders act.
                    </p>
                </div>
            </div>
        </div>

        {{-- Item & nominal --}}
        <div class="pt-2">
            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide mb-3">Items &amp; Amounts</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Maximum items per document</label>
                    <input type="number" name="max_items_per_request" min="0" max="255" required
                           value="{{ old('max_items_per_request', $settings->max_items_per_request) }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                    <p class="text-xs text-gray-400 mt-1">0 = no limit. This one <strong>blocks</strong>.</p>
                    @error('max_items_per_request')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Minimum amount per item (IDR)</label>
                    <input type="number" name="min_item_amount" min="0" step="1" required
                           value="{{ old('min_item_amount', (int) $settings->min_item_amount) }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                    <p class="text-xs text-gray-400 mt-1">0 = no minimum. Items below this are <strong>flagged</strong>, never blocked.</p>
                    @error('min_item_amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            {{-- Batas nominal dan kebijakannya WAJIB terlihat berdampingan:
                 angkanya tidak punya arti tanpa mengetahui apa yang terjadi
                 saat dilewati (Keputusan D107). --}}
            <div class="mt-5 border border-gray-200 rounded-lg p-4 bg-gray-50">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Maximum total per document (IDR)</label>
                        <input type="number" name="max_request_amount" min="0" step="1" required
                               value="{{ old('max_request_amount', (int) $settings->max_request_amount) }}"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                        <p class="text-xs text-gray-400 mt-1">0 = no limit.</p>
                        @error('max_request_amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">When the limit is exceeded</label>
                        <select name="over_limit_policy"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                            @foreach([
                                ReimbursementSetting::OVER_LIMIT_FLAG  => 'Flag it — accept and highlight for the reviewer',
                                ReimbursementSetting::OVER_LIMIT_BLOCK => 'Block it — refuse the submission',
                            ] as $value => $label)
                                <option value="{{ $value }}" @selected($settings->over_limit_policy === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-400 mt-1">
                            No middle ground: flagging never blocks, blocking never merely flags.
                        </p>
                    </div>
                </div>

                @if($settings->blocksOverLimit() && !$settings->hasAmountLimit())
                <p class="mt-3 text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded px-3 py-2">
                    <span class="font-semibold">This blocking rule currently does nothing.</span>
                    The maximum is set to 0, which means no limit, so nothing can ever exceed it.
                </p>
                @endif
            </div>
        </div>

        {{-- Kelengkapan bukti --}}
        <div class="pt-2">
            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide mb-3">Evidence &amp; Completeness</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <label class="flex items-start gap-2">
                    <input type="checkbox" name="require_supporting_url" value="1" @checked($settings->require_supporting_url)
                           class="mt-0.5 w-4 h-4 text-red-800 border-gray-300 rounded focus:ring-red-800">
                    <span class="text-sm">
                        <span class="font-semibold text-gray-800">Require a supporting document link</span>
                        <span class="block text-xs text-gray-500">
                            When off, a missing link is only flagged.
                        </span>
                    </span>
                </label>

                <label class="flex items-start gap-2">
                    <input type="checkbox" name="require_receipt_no" value="1" @checked($settings->require_receipt_no)
                           class="mt-0.5 w-4 h-4 text-red-800 border-gray-300 rounded focus:ring-red-800">
                    <span class="text-sm">
                        <span class="font-semibold text-gray-800">Require a receipt number on every item</span>
                        <span class="block text-xs text-gray-500">
                            When off, items without one are flagged.
                        </span>
                    </span>
                </label>

                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Minimum title length</label>
                    <input type="number" name="require_title_min_chars" min="0" max="200" required
                           value="{{ old('require_title_min_chars', $settings->require_title_min_chars) }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                    <p class="text-xs text-gray-400 mt-1">Characters required in the document title.</p>
                    @error('require_title_min_chars')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="mt-5">
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Allowed link hosts</label>
                <input type="text" name="supporting_url_allowed_hosts" maxlength="255"
                       value="{{ old('supporting_url_allowed_hosts', $settings->supporting_url_allowed_hosts) }}"
                       placeholder="drive.google.com,docs.google.com"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                <p class="text-xs text-gray-400 mt-1">
                    Comma separated. Full URLs are accepted and trimmed down to the host automatically.
                    Leave empty to accept any link — only possible while the requirement above is off.
                </p>
                @error('supporting_url_allowed_hosts')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>

        {{-- Persetujuan --}}
        <div class="pt-2">
            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide mb-3">Approval</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <label class="flex items-start gap-2">
                    <input type="checkbox" name="allow_self_approval" value="1" @checked($settings->allow_self_approval)
                           class="mt-0.5 w-4 h-4 text-red-800 border-gray-300 rounded focus:ring-red-800">
                    <span class="text-sm">
                        <span class="font-semibold text-gray-800">Allow self-approval</span>
                        <span class="block text-xs text-gray-500">
                            Requesters who hold an approver role may approve their own document.
                            It is always recorded and flagged.
                        </span>
                    </span>
                </label>

                <label class="flex items-start gap-2">
                    <input type="checkbox" name="allow_approver_adjust_amount" value="1" @checked($settings->allow_approver_adjust_amount)
                           class="mt-0.5 w-4 h-4 text-red-800 border-gray-300 rounded focus:ring-red-800">
                    <span class="text-sm">
                        <span class="font-semibold text-gray-800">Approvers may adjust amounts</span>
                        <span class="block text-xs text-gray-500">
                            Lets an approver open <strong>Edit</strong> on a document that is waiting for
                            <em>their own</em> step, without holding the Edit / Delete permission. The right
                            disappears the moment they approve and the document moves on. Every change is
                            flagged <em>Amount adjusted</em> and written to the log.
                        </span>
                        <span class="block text-xs text-gray-500 mt-1">
                            Off by default: an amount can only be verified against the receipt,
                            and the receipt belongs to the requester.
                        </span>
                    </span>
                </label>

                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Fallback approver role</label>
                    <select name="self_approval_fallback_role_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                        <option value="">— none —</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->id }}" @selected($settings->self_approval_fallback_role_id === $role->id)>{{ $role->name }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-400 mt-1">
                        Suggested to whoever is blocked when self-approval is off.
                    </p>
                </div>
            </div>

            @if($settings->allow_self_approval && $steps->where('is_active', true)->count() < 2)
            <div class="mt-4 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3">
                <p class="text-sm text-amber-900">
                    <span class="font-semibold">Self-approval is currently enabled.</span>
                    With only {{ $steps->where('is_active', true)->count() }} active step(s), a requester who holds
                    the approver role can take their own document all the way to approved without anyone else
                    seeing it — and a reimbursement ends in a payment. Every such case is flagged as
                    <em>Self-approved</em>. Adding a second step removes this exposure.
                </p>
            </div>
            @endif
        </div>

        {{-- Penanda tangan --}}
        <div class="pt-2">
            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide mb-3">Document Signatures</h3>
            <p class="text-xs text-gray-500 mb-3">
                Printed at the bottom of the document and the Excel export. The requester column is always
                the person who submitted it.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                @foreach([
                    'accounting_signer_employee_id' => ['Accounting', 'Signs the accounting column.'],
                    'cashier_signer_employee_id'    => ['Cashier', 'Signs the cashier column.'],
                    'approver_signer_employee_id'   => ['Approved by', 'Leave empty to use the last approver from the workflow.'],
                ] as $field => [$label, $hint])
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">{{ $label }}</label>
                    <select name="{{ $field }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                        <option value="">— none —</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->employee_id }}" @selected((int) $settings->$field === (int) $employee->employee_id)>
                                {{ $employee->basicData?->nick_name ?? $employee->eci }}
                            </option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-400 mt-1">{{ $hint }}</p>
                    @error($field)<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                @endforeach
            </div>

            <p class="mt-3 text-xs text-gray-400">
                Names are stored as employee references, not as text — so when signature images arrive on the
                employee profile, they will render here without any change to this module.
            </p>
        </div>

        <div class="pt-4 border-t border-gray-100">
            <button type="submit"
                    class="px-5 py-2.5 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all">
                Save Settings
            </button>
        </div>
    </form>
</div>
@endsection

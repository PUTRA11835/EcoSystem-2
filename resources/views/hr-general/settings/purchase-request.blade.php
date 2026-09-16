@extends('dashboard')

@section('title', 'Purchase Request — Settings')
@section('page-title', 'Purchase Request')
@section('page-subtitle', 'Request rules, units, and cost centers — the approval workflow moved to its own page')

@section('content')
@php
    use App\Models\PurchaseRequest\PurchaseRequestItem;
    use App\Models\PurchaseRequest\PurchaseRequestSetting;
@endphp

@include('hr-general.purchase-request.hub-tabs-purchase-request')

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
            <a href="{{ route('general.approval-workflow.purchase-request') }}" class="underline font-semibold hover:text-blue-700">Approval Workflow</a>.
        </p>
    </div>

    {{-- =============================================================
         BAGIAN 2 — ATURAN PURCHASE REQUEST
         ============================================================= --}}
    <form method="POST" action="{{ route('general.purchase-request.settings.update') }}" class="bg-white rounded-xl p-6 shadow-sm space-y-6">
        @csrf

        <div class="pb-4 border-b-2 border-gray-100">
            <h2 class="text-2xl font-bold text-gray-900">Purchase Request Rules</h2>
            <p class="text-sm text-gray-500 mt-0.5">
                Set <strong>0</strong> where you want no limit at all. Rules that only flag a request
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
                            If every item on a request charges the same branch, that branch name replaces the
                            company name in the header. Mixed requests — and requests charged to a project —
                            always fall back to the company name: a project is who pays, not who issues the
                            document.
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
                            Normally <strong>on</strong> here — unlike reimbursement. A purchase request asks
                            for goods that have not been bought yet.
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
                            PurchaseRequestSetting::LOCK_OFF            => 'Ignore — allow everything',
                            PurchaseRequestSetting::LOCK_BLOCK_EMPLOYEE => 'Block employees only',
                            PurchaseRequestSetting::LOCK_BLOCK_ALL      => 'Block everyone',
                        ] as $value => $label)
                            <option value="{{ $value }}" @selected($settings->locked_period_policy === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-400 mt-1">
                        "Block employees only" still lets Purchase Request — Edit / Delete holders act.
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mt-5">
                <label class="flex items-start gap-2">
                    <input type="checkbox" name="require_use_date" value="1" @checked($settings->require_use_date)
                           class="mt-0.5 w-4 h-4 text-red-800 border-gray-300 rounded focus:ring-red-800">
                    <span class="text-sm">
                        <span class="font-semibold text-gray-800">Require a use date on every item</span>
                        <span class="block text-xs text-gray-500">
                            When off, items without one are only flagged. A use date already in the past is
                            always flagged, whichever way this is set.
                        </span>
                    </span>
                </label>

                <label class="flex items-start gap-2">
                    <input type="checkbox" name="require_period" value="1" @checked($settings->require_period)
                           class="mt-0.5 w-4 h-4 text-red-800 border-gray-300 rounded focus:ring-red-800">
                    <span class="text-sm">
                        <span class="font-semibold text-gray-800">Require a period on every item</span>
                        <span class="block text-xs text-gray-500">
                            Useful for subscriptions and licences. When off, missing periods are flagged.
                        </span>
                    </span>
                </label>
            </div>
        </div>

        {{-- Item, satuan, kuantitas --}}
        <div class="pt-2">
            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide mb-3">Items &amp; Quantities</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Maximum items per request</label>
                    <input type="number" name="max_items_per_request" min="0" max="255" required
                           value="{{ old('max_items_per_request', $settings->max_items_per_request) }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                    <p class="text-xs text-gray-400 mt-1">0 = no limit. This one <strong>blocks</strong>.</p>
                    @error('max_items_per_request')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Maximum quantity per item</label>
                    <input type="number" name="max_qty_per_item" min="0" step="0.01" required
                           value="{{ old('max_qty_per_item', 0 + $settings->max_qty_per_item) }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                    <p class="text-xs text-gray-400 mt-1">
                        0 = no limit. This one <strong>blocks</strong>. Decimals are allowed — half a LOT is a
                        reasonable request.
                    </p>
                    @error('max_qty_per_item')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            {{-- Satuan dan bawaannya WAJIB terlihat berdampingan: satuan bawaan
                 yang tidak ada dalam daftarnya membuat setiap baris baru lahir
                 dengan nilai yang langsung ditolak validasi (Keputusan D128). --}}
            <div class="mt-5 border border-gray-200 rounded-lg p-4 bg-gray-50">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Allowed units (UoM)</label>
                        <input type="text" name="allowed_units" maxlength="255" required
                               value="{{ old('allowed_units', $settings->allowed_units) }}"
                               placeholder="PC,UNIT,SET,BOX,LOT"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                        <p class="text-xs text-gray-400 mt-1">
                            Comma separated. Spacing and lower case are cleaned up automatically, so "pc, Unit"
                            becomes "PC,UNIT". Items are rendered as a dropdown from this list, never free text —
                            free text would make the quantity summary impossible to add up.
                        </p>
                        @error('allowed_units')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Default unit</label>
                        <input type="text" name="default_unit" maxlength="20" required
                               value="{{ old('default_unit', $settings->default_unit) }}"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                        <p class="text-xs text-gray-400 mt-1">
                            Used for new item rows. If it is not in the list above, the first listed unit is
                            used instead.
                        </p>
                        @error('default_unit')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            <div class="mt-5">
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Minimum summary length</label>
                <input type="number" name="require_title_min_chars" min="0" max="200" required
                       value="{{ old('require_title_min_chars', $settings->require_title_min_chars) }}"
                       class="w-full md:w-1/3 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                <p class="text-xs text-gray-400 mt-1">Characters required in the request summary.</p>
                @error('require_title_min_chars')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>

        {{-- Pembebanan biaya --}}
        <div class="pt-2">
            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide mb-3">Cost Centers</h3>
            <p class="text-xs text-gray-500 mb-3">
                Each item line is charged to exactly one place. A single request may mix branches and
                projects across its lines — it is then labelled <em>Multiple cost centers</em>.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="border border-gray-200 rounded-lg p-4">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Types available to requesters</p>

                    @foreach([
                        PurchaseRequestItem::COST_CENTER_BRANCH  => ['Branch', 'Charged to an office. Label is frozen as "CODE – Name".'],
                        PurchaseRequestItem::COST_CENTER_PROJECT => ['Project', 'Charged to a delivery project. Closed projects never appear. Label is frozen as "IO number – Name".'],
                    ] as $type => [$label, $hint])
                    <label class="flex items-start gap-2 {{ !$loop->last ? 'mb-3' : '' }}">
                        <input type="checkbox" name="cost_center_types[]" value="{{ $type }}"
                               @checked(in_array($type, $settings->costCenterTypeOptions(), true))
                               class="mt-0.5 w-4 h-4 text-red-800 border-gray-300 rounded focus:ring-red-800">
                        <span class="text-sm">
                            <span class="font-semibold text-gray-800">{{ $label }}</span>
                            <span class="block text-xs text-gray-500">{{ $hint }}</span>
                        </span>
                    </label>
                    @endforeach

                    <p class="text-xs text-gray-400 mt-3">
                        Unticking one hides its dropdown on the form. At least one must stay ticked.
                    </p>
                </div>

                <label class="flex items-start gap-2">
                    <input type="checkbox" name="require_cost_center_per_item" value="1" @checked($settings->require_cost_center_per_item)
                           class="mt-0.5 w-4 h-4 text-red-800 border-gray-300 rounded focus:ring-red-800">
                    <span class="text-sm">
                        <span class="font-semibold text-gray-800">Require a cost center on every item</span>
                        <span class="block text-xs text-gray-500">
                            When off, items without one are only flagged — and the request arrives with nobody
                            knowing which budget it lands on. Leave it on unless you have a reason.
                        </span>
                    </span>
                </label>
            </div>
        </div>

        {{-- Persetujuan & pembatalan --}}
        <div class="pt-2">
            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide mb-3">Approval &amp; Cancellation</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <label class="flex items-start gap-2">
                    <input type="checkbox" name="allow_self_approval" value="1" @checked($settings->allow_self_approval)
                           class="mt-0.5 w-4 h-4 text-red-800 border-gray-300 rounded focus:ring-red-800">
                    <span class="text-sm">
                        <span class="font-semibold text-gray-800">Allow self-approval</span>
                        <span class="block text-xs text-gray-500">
                            Requesters who hold an approver role may approve their own request.
                            It is always recorded and flagged.
                        </span>
                    </span>
                </label>

                <label class="flex items-start gap-2">
                    <input type="checkbox" name="allow_approver_adjust_items" value="1" @checked($settings->allow_approver_adjust_items)
                           class="mt-0.5 w-4 h-4 text-red-800 border-gray-300 rounded focus:ring-red-800">
                    <span class="text-sm">
                        <span class="font-semibold text-gray-800">Approvers may adjust items</span>
                        <span class="block text-xs text-gray-500">
                            Lets an approver open <strong>Edit</strong> on a request waiting for
                            <em>their own</em> step, without holding the Edit / Delete permission. The right
                            disappears the moment they approve. Every change is flagged
                            <em>Items adjusted</em> and written to the log.
                        </span>
                    </span>
                </label>

                <label class="flex items-start gap-2">
                    <input type="checkbox" name="allow_requester_cancel" value="1" @checked($settings->allow_requester_cancel)
                           class="mt-0.5 w-4 h-4 text-red-800 border-gray-300 rounded focus:ring-red-800">
                    <span class="text-sm">
                        <span class="font-semibold text-gray-800">Requesters may cancel their own request</span>
                        <span class="block text-xs text-gray-500">
                            Only while nobody has reviewed it yet. The button disappears the moment the first
                            approver acts — an approver's work is never thrown away by a change of mind.
                        </span>
                    </span>
                </label>
            </div>

            <div class="mt-5 md:w-1/3">
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

            @if($settings->allow_self_approval && $steps->where('is_active', true)->count() < 2)
            <div class="mt-4 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3">
                <p class="text-sm text-amber-900">
                    <span class="font-semibold">Self-approval is currently enabled.</span>
                    With only {{ $steps->where('is_active', true)->count() }} active step(s), a requester who holds
                    the approver role can take their own request all the way to approved without anyone else
                    seeing it. Every such case is flagged as <em>Self-approved</em>. Adding a second step
                    removes this exposure.
                </p>
            </div>
            @endif
        </div>

        {{-- Tanda tangan — SENGAJA tanpa field (Keputusan D129). Dijelaskan,
             bukan didiamkan: orang yang datang dari halaman Reimbursement
             Settings akan mencari tiga dropdown yang memang tidak ada di sini. --}}
        <div class="pt-2">
            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide mb-3">Document Signatures</h3>

            <div class="border border-gray-200 rounded-lg px-4 py-3 bg-gray-50">
                <p class="text-sm text-gray-700">
                    <span class="font-semibold">There is nothing to configure here, and that is deliberate.</span>
                    The signature block on the printed form is built from the approval workflow above:
                    <em>Requester</em> first, then one column per active step, titled with the step name and
                    signed by whoever actually approved it.
                </p>
                <p class="text-xs text-gray-500 mt-2">
                    So {{ max(1, $steps->where('is_active', true)->count()) }} active step(s) produce
                    {{ max(1, $steps->where('is_active', true)->count()) + 1 }} columns. Storing signers
                    separately would create a second source of truth — the settings saying one name and the
                    approval history another, with no way to tell which is right. Reimbursement does keep
                    signer fields, because its Accounting and Cashier columns are not part of any approval
                    step and cannot be derived from anything.
                </p>
            </div>
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

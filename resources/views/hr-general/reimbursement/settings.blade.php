@extends('dashboard')

@section('title', 'Reimbursement — Settings')
@section('page-title', 'Reimbursement')
@section('page-subtitle', 'Approval workflow and reimbursement rules')

@section('content')
@php
    use App\Models\Reimbursement\ReimbursementSetting;

    $input  = 'w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white';
    $label  = 'block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5';
    $hint   = 'text-xs text-gray-400 mt-1';
    $policies = [
        ReimbursementSetting::OVER_LIMIT_FLAG  => 'Flag it — accept and highlight for the reviewer',
        ReimbursementSetting::OVER_LIMIT_BLOCK => 'Block it — refuse the submission',
    ];
@endphp

@include('hr-general.reimbursement.components.hub-tabs')
@include('hr-general.recruitment.components.unsaved-guard')

<div class="flex flex-col lg:flex-row gap-6 items-start">
    @include('hr-general.partials.settings-nav', ['title' => 'Reimbursement'])

    <div class="flex-1 min-w-0 w-full space-y-5" data-settings-content>

    {{-- Approval Workflow — its own slug (D180): shown only to its holders. --}}
    @if($can('general.approval-workflow.reimbursement'))
        @include('hr-general.reimbursement.components.step-editor')
    @endif

    {{-- Rules — shown only to holders of `general.settings.reimbursement`; saved by the same slug. --}}
    @if($can('general.settings.reimbursement'))
    <form method="POST" action="{{ route('general.reimbursement.settings.update') }}" class="bg-white rounded-xl p-6 shadow-sm space-y-7" data-unsaved-label="Reimbursement Rules">
        @csrf

        <div class="pb-3 border-b-2 border-gray-100">
            <h2 class="text-2xl font-bold text-gray-900">Reimbursement Rules</h2>
            <p class="text-sm text-gray-500 mt-0.5">
                <strong>0</strong> means no limit. A rule set to <em>flag</em> only highlights the document; <em>block</em> refuses it.
            </p>
        </div>

        {{-- ── Printed document ─────────────────────────────────────────── --}}
        <section>
            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide mb-3">Printed Document</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="{{ $label }}">Company name</label>
                    <input type="text" name="company_name" maxlength="150" required
                           value="{{ old('company_name', $settings->company_name) }}" class="{{ $input }}">
                    <p class="{{ $hint }}">Shown on the printout and the Excel export.</p>
                    @error('company_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="{{ $label }}">Letterhead</label>
                    <select name="letterhead_id" data-searchable="true" data-search-placeholder="Search letterhead…" class="{{ $input }}">
                        <option value="">— Plain paper —</option>
                        @foreach($letterheads as $letterhead)
                            <option value="{{ $letterhead->id }}" @selected((int) old('letterhead_id', $settings->letterhead_id) === (int) $letterhead->id)>{{ $letterhead->name }}</option>
                        @endforeach
                    </select>
                    <p class="{{ $hint }}">
                        Printed on a letterhead from Document Identity → Letterheads.
                        @if($can('general.letters.settings.letterheads'))
                            <a href="{{ route('general.document-identity.index', ['section' => 'letterheads']) }}" class="underline font-semibold">Manage letterheads</a>
                        @endif
                    </p>
                    @error('letterhead_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <label class="flex items-start gap-2 md:col-span-2">
                    <input type="checkbox" name="use_branch_name_in_header" value="1" @checked($settings->use_branch_name_in_header)
                           class="mt-0.5 w-4 h-4 text-red-800 border-gray-300 rounded focus:ring-red-800">
                    <span class="text-sm">
                        <span class="font-semibold text-gray-800">Use the branch name in the header</span>
                        <span class="block text-xs text-gray-500">Only when every item charges the same branch; otherwise the company name is used.</span>
                    </span>
                </label>
            </div>
        </section>

        {{-- ── Dates & deadlines ────────────────────────────────────────── --}}
        <section>
            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide mb-3">Dates &amp; Deadlines</h3>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <label class="flex items-start gap-2">
                    <input type="checkbox" name="allow_future_date" value="1" @checked($settings->allow_future_date)
                           class="mt-0.5 w-4 h-4 text-red-800 border-gray-300 rounded focus:ring-red-800">
                    <span class="text-sm">
                        <span class="font-semibold text-gray-800">Allow a future request date</span>
                        <span class="block text-xs text-gray-500">Normally off: a reimbursement is money already spent.</span>
                    </span>
                </label>

                <div>
                    <label class="{{ $label }}">Request window (days)</label>
                    <input type="number" name="max_backdate_days" min="0" max="3650" required
                           value="{{ old('max_backdate_days', $settings->max_backdate_days) }}" class="{{ $input }}">
                    <p class="{{ $hint }}">How far back a request date may be, counted from today. <strong>Blocks.</strong></p>
                    @error('max_backdate_days')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="{{ $label }}">Closed reporting period</label>
                    <select name="locked_period_policy" class="{{ $input }}">
                        @foreach([
                            ReimbursementSetting::LOCK_OFF            => 'Ignore — allow everything',
                            ReimbursementSetting::LOCK_BLOCK_EMPLOYEE => 'Block employees only',
                            ReimbursementSetting::LOCK_BLOCK_ALL      => 'Block everyone',
                        ] as $value => $text)
                            <option value="{{ $value }}" @selected($settings->locked_period_policy === $value)>{{ $text }}</option>
                        @endforeach
                    </select>
                    <p class="{{ $hint }}">“Employees only” still lets Edit / Delete holders act.</p>
                </div>
            </div>

            {{-- Receipt (transaction) date against the request date --}}
            <div class="mt-5 border border-gray-200 rounded-lg p-4 bg-gray-50">
                <p class="text-sm font-semibold text-gray-800">Receipt date → request date</p>
                <p class="text-xs text-gray-500 mt-0.5">
                    A receipt can never be dated after the request. This sets how old it may be, counted back from the request date.
                </p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mt-3">
                    <div>
                        <label class="{{ $label }}">Maximum receipt age (days)</label>
                        <input type="number" name="receipt_max_age_days" min="0" max="3650" required
                               value="{{ old('receipt_max_age_days', $settings->receipt_max_age_days) }}" class="{{ $input }}">
                        <p class="{{ $hint }}">0 = no limit. e.g. 30 → a receipt must be at most 30 days older than the request.</p>
                        @error('receipt_max_age_days')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="{{ $label }}">When a receipt is too old</label>
                        <select name="receipt_age_policy" class="{{ $input }}">
                            @foreach($policies as $value => $text)
                                <option value="{{ $value }}" @selected($settings->receipt_age_policy === $value)>{{ $text }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                @if($settings->blocksOldReceipt() === false && $settings->receipt_age_policy === 'block' && !$settings->hasReceiptAgeLimit())
                    <p class="mt-3 text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded px-3 py-2">
                        This blocking rule does nothing while the age is 0 (no limit).
                    </p>
                @endif
            </div>
        </section>

        {{-- ── Items & amounts ──────────────────────────────────────────── --}}
        <section>
            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide mb-3">Items &amp; Amounts</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <div>
                    <label class="{{ $label }}">Maximum items per document</label>
                    <input type="number" name="max_items_per_request" min="0" max="255" required
                           value="{{ old('max_items_per_request', $settings->max_items_per_request) }}" class="{{ $input }}">
                    <p class="{{ $hint }}"><strong>Blocks</strong> when exceeded.</p>
                    @error('max_items_per_request')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="{{ $label }}">Minimum amount per item (IDR)</label>
                    <input type="number" name="min_item_amount" min="0" step="1" required
                           value="{{ old('min_item_amount', (int) $settings->min_item_amount) }}" class="{{ $input }}">
                    <p class="{{ $hint }}">Items below it are <strong>flagged</strong>, never blocked.</p>
                    @error('min_item_amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="{{ $label }}">Maximum total per document (IDR)</label>
                    <input type="number" name="max_request_amount" min="0" step="1" required
                           value="{{ old('max_request_amount', (int) $settings->max_request_amount) }}" class="{{ $input }}">
                    <p class="{{ $hint }}">Applies to IDR documents.</p>
                    @error('max_request_amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div class="md:col-span-3">
                    <label class="{{ $label }}">When the total is exceeded</label>
                    <select name="over_limit_policy" class="{{ $input }} md:w-1/2">
                        @foreach($policies as $value => $text)
                            <option value="{{ $value }}" @selected($settings->over_limit_policy === $value)>{{ $text }}</option>
                        @endforeach
                    </select>
                    @if($settings->blocksOverLimit() && !$settings->hasAmountLimit())
                        <p class="mt-2 text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded px-3 py-2">
                            This blocking rule does nothing while the maximum is 0 (no limit).
                        </p>
                    @endif
                </div>
            </div>
        </section>

        {{-- ── Evidence & completeness ──────────────────────────────────── --}}
        <section>
            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide mb-3">Evidence &amp; Completeness</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <label class="flex items-start gap-2">
                    <input type="checkbox" name="require_supporting_url" value="1" @checked($settings->require_supporting_url)
                           class="mt-0.5 w-4 h-4 text-red-800 border-gray-300 rounded focus:ring-red-800">
                    <span class="text-sm">
                        <span class="font-semibold text-gray-800">Require a supporting document link</span>
                        <span class="block text-xs text-gray-500">Off: a missing link is only flagged.</span>
                    </span>
                </label>

                <label class="flex items-start gap-2">
                    <input type="checkbox" name="require_receipt_no" value="1" @checked($settings->require_receipt_no)
                           class="mt-0.5 w-4 h-4 text-red-800 border-gray-300 rounded focus:ring-red-800">
                    <span class="text-sm">
                        <span class="font-semibold text-gray-800">Require a receipt number</span>
                        <span class="block text-xs text-gray-500">Off: items without one are flagged.</span>
                    </span>
                </label>

                <div>
                    <label class="{{ $label }}">Minimum title length</label>
                    <input type="number" name="require_title_min_chars" min="0" max="200" required
                           value="{{ old('require_title_min_chars', $settings->require_title_min_chars) }}" class="{{ $input }}">
                    @error('require_title_min_chars')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div class="md:col-span-3">
                    <label class="{{ $label }}">Allowed link hosts</label>
                    <input type="text" name="supporting_url_allowed_hosts" maxlength="255"
                           value="{{ old('supporting_url_allowed_hosts', $settings->supporting_url_allowed_hosts) }}"
                           placeholder="drive.google.com,docs.google.com" class="{{ $input }}">
                    <p class="{{ $hint }}">Comma separated; full URLs are trimmed to the host. Empty accepts any link — only while the link is not required.</p>
                    @error('supporting_url_allowed_hosts')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>

        {{-- ── Approval rules ───────────────────────────────────────────── --}}
        <section>
            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide mb-3">Approval Rules</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <label class="flex items-start gap-2">
                    <input type="checkbox" name="allow_self_approval" value="1" @checked($settings->allow_self_approval)
                           class="mt-0.5 w-4 h-4 text-red-800 border-gray-300 rounded focus:ring-red-800">
                    <span class="text-sm">
                        <span class="font-semibold text-gray-800">Allow self-approval</span>
                        <span class="block text-xs text-gray-500">A requester holding an approver position may approve their own document. Always flagged.</span>
                    </span>
                </label>

                <label class="flex items-start gap-2">
                    <input type="checkbox" name="allow_approver_adjust_amount" value="1" @checked($settings->allow_approver_adjust_amount)
                           class="mt-0.5 w-4 h-4 text-red-800 border-gray-300 rounded focus:ring-red-800">
                    <span class="text-sm">
                        <span class="font-semibold text-gray-800">Approvers may adjust amounts</span>
                        <span class="block text-xs text-gray-500">Only while the document waits for their own step; every change is flagged and logged.</span>
                    </span>
                </label>

                <div>
                    <label class="{{ $label }}">Fallback approver position</label>
                    <select name="self_approval_fallback_role_id" data-searchable="true" data-search-placeholder="Search position…" class="{{ $input }}">
                        <option value="">— none —</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->id }}" @selected($settings->self_approval_fallback_role_id === $role->id)>{{ $role->name }}</option>
                        @endforeach
                    </select>
                    <p class="{{ $hint }}">Suggested to whoever is blocked when self-approval is off.</p>
                </div>
            </div>

            @if($settings->allow_self_approval && $steps->where('is_active', true)->count() < 2)
                <p class="mt-4 text-sm text-amber-900 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3">
                    <span class="font-semibold">Self-approval is on with only {{ $steps->where('is_active', true)->count() }} active step(s).</span>
                    A requester holding the approver position can take their own document to approved unseen. Add a second step to remove this.
                </p>
            @endif
        </section>

        <div class="sticky bottom-0 -mx-6 -mb-6 px-6 py-3 bg-white/95 backdrop-blur border-t border-gray-100 rounded-b-xl">
            <button type="submit" class="px-5 py-2.5 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all">
                Save Settings
            </button>
        </div>
    </form>
    @endif
    </div>
</div>
@endsection

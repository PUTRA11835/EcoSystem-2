@extends('dashboard')

@section('title', 'Reimbursement Settings')
@section('page-title', 'Reimbursement Settings')
@section('page-subtitle', 'Reimbursement rules, evidence requirements, and the approval workflow')

@section('content')
@php
    use App\Models\Reimbursement\ReimbursementApprovalStep;
    use App\Models\Reimbursement\ReimbursementSetting;
@endphp

<div class="max-w-6xl space-y-5">

    {{-- =============================================================
         BAGIAN 1 — ALUR PERSETUJUAN
         Ditaruh paling atas karena inilah yang paling sering diubah,
         dan karena tanpa satu langkah aktif pun modulnya tidak jalan.
         ============================================================= --}}
    <div class="bg-white rounded-xl p-6 shadow-sm">
        <div class="mb-5 pb-4 border-b-2 border-gray-100">
            <h2 class="text-2xl font-bold text-gray-900">Approval Workflow</h2>
            <p class="text-sm text-gray-500 mt-0.5">
                Documents move through these steps in order. Each step needs only one approver to act.
            </p>
        </div>

        {{-- Aturannya ASIMETRIS, dan itu disengaja — memperketat boleh berlaku
             surut, melonggarkan tidak pernah. Dijelaskan di sini supaya tidak
             terbaca sebagai perilaku yang tidak konsisten. --}}
        <div class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-3 mb-5">
            <p class="text-sm text-blue-900">
                <span class="font-semibold">Adding a step can be applied to documents already in progress.
                Removing or changing one never is.</span>
                Adding a step tightens control, and it is exactly the documents already in flight that most
                need it. Removing a step is the dangerous direction: a document waiting at a deleted step
                could jump straight to approved without anyone reviewing it. Approvals that already happened
                are never rewritten.
            </p>
        </div>

        <div class="border border-gray-200 rounded-lg overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                        <th class="px-4 py-3 w-24">Order</th>
                        <th class="px-4 py-3">Step Name</th>
                        <th class="px-4 py-3 w-48">Approver Type</th>
                        <th class="px-4 py-3">Approver</th>
                        <th class="px-4 py-3 w-24 text-center">Active</th>
                        <th class="px-4 py-3 w-40 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($steps as $step)
                    <tr class="align-top hover:bg-gray-50 transition-colors">
                        <form method="POST" action="{{ route('general.settings.reimbursement.steps.update', $step) }}" id="step-{{ $step->id }}">
                            @csrf
                        </form>

                        <td class="px-4 py-3">
                            <div class="flex items-center gap-1">
                                <span class="w-7 h-7 flex items-center justify-center bg-red-800 text-white rounded-full text-xs font-bold">
                                    {{ $step->order_seq }}
                                </span>
                                <div class="flex flex-col gap-0.5">
                                    @if(!$loop->first)
                                    <button type="submit" form="move-up-{{ $step->id }}" title="Move up"
                                            class="px-1 text-gray-400 hover:text-gray-900 leading-none">&#9650;</button>
                                    @endif
                                    @if(!$loop->last)
                                    <button type="submit" form="move-down-{{ $step->id }}" title="Move down"
                                            class="px-1 text-gray-400 hover:text-gray-900 leading-none">&#9660;</button>
                                    @endif
                                </div>
                            </div>

                            <form method="POST" action="{{ route('general.settings.reimbursement.steps.move', $step) }}" id="move-up-{{ $step->id }}" class="hidden">
                                @csrf <input type="hidden" name="direction" value="up">
                            </form>
                            <form method="POST" action="{{ route('general.settings.reimbursement.steps.move', $step) }}" id="move-down-{{ $step->id }}" class="hidden">
                                @csrf <input type="hidden" name="direction" value="down">
                            </form>
                        </td>

                        <td class="px-4 py-3">
                            <input type="text" name="name" form="step-{{ $step->id }}" value="{{ $step->name }}" required maxlength="100"
                                   class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-red-800">
                        </td>

                        <td class="px-4 py-3">
                            <select name="approver_type" form="step-{{ $step->id }}"
                                    class="js-type w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-red-800"
                                    data-step="{{ $step->id }}">
                                @foreach(ReimbursementApprovalStep::SELECTABLE_TYPES as $type)
                                    <option value="{{ $type }}" @selected($step->approver_type === $type)>
                                        {{ ReimbursementApprovalStep::TYPE_LABELS[$type] }}
                                    </option>
                                @endforeach

                                {{-- Ditampilkan tetapi tidak dapat dipilih: hierarki
                                     atasan belum ada di basis data. Menyembunyikannya
                                     akan membuat orang mengira fitur ini tidak
                                     direncanakan sama sekali. --}}
                                <option value="{{ ReimbursementApprovalStep::TYPE_DIRECT_MANAGER }}" disabled
                                        @selected($step->approver_type === ReimbursementApprovalStep::TYPE_DIRECT_MANAGER)>
                                    Direct Manager — not available yet
                                </option>
                            </select>

                            @if($step->approver_type === ReimbursementApprovalStep::TYPE_DIRECT_MANAGER)
                                <p class="text-xs text-red-600 mt-1">
                                    This step cannot run: employee hierarchy data does not exist yet.
                                </p>
                            @endif
                        </td>

                        <td class="px-4 py-3">
                            <div class="js-role-box" data-step="{{ $step->id }}"
                                 @class(['hidden' => $step->approver_type !== ReimbursementApprovalStep::TYPE_ROLE])>
                                <select name="approver_role_id" form="step-{{ $step->id }}"
                                        class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-red-800">
                                    <option value="">— choose a role —</option>
                                    @foreach($roles as $role)
                                        <option value="{{ $role->id }}" @selected($step->approver_role_id === $role->id)>{{ $role->name }}</option>
                                    @endforeach
                                </select>
                                <p class="text-xs text-gray-400 mt-1">Anyone holding this role can approve this step.</p>
                            </div>

                            <div class="js-employee-box" data-step="{{ $step->id }}"
                                 @class(['hidden' => $step->approver_type !== ReimbursementApprovalStep::TYPE_EMPLOYEE])>
                                <select name="approver_employee_ids[]" form="step-{{ $step->id }}" multiple size="5"
                                        class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-red-800">
                                    @foreach($employees as $employee)
                                        <option value="{{ $employee->employee_id }}"
                                            @selected(in_array($employee->employee_id, $step->approver_employee_ids ?? []))>
                                            {{ $employee->basicData?->nick_name ?? $employee->eci }}
                                        </option>
                                    @endforeach
                                </select>
                                <p class="text-xs text-gray-400 mt-1">Hold Ctrl / Cmd to select more than one.</p>
                            </div>
                        </td>

                        <td class="px-4 py-3 text-center">
                            <input type="checkbox" name="is_active" value="1" form="step-{{ $step->id }}" @checked($step->is_active)
                                   class="w-4 h-4 text-red-800 border-gray-300 rounded focus:ring-red-800">
                        </td>

                        <td class="px-4 py-3">
                            <div class="flex gap-2 justify-center">
                                <button type="submit" form="step-{{ $step->id }}"
                                        class="px-3 py-1.5 bg-gray-800 text-white text-xs font-semibold rounded hover:bg-gray-900 transition-all">
                                    Save
                                </button>
                                <form method="POST" action="{{ route('general.settings.reimbursement.steps.destroy', $step) }}"
                                      class="js-delete-step" data-name="{{ $step->name }}">
                                    @csrf
                                    <button type="submit"
                                            class="px-3 py-1.5 bg-white text-red-700 text-xs font-semibold rounded border border-red-200 hover:bg-red-50 transition-all">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach

                    {{-- Baris tambah berada DI DALAM tabel yang sama dengan kolom
                         yang persis sama (Keputusan D62): bentuk yang berbeda
                         antara daftar dan form membuat pengguna mengira keduanya
                         mengisi hal yang berbeda. --}}
                    <tr class="bg-gray-50 align-top">
                        <form method="POST" action="{{ route('general.settings.reimbursement.steps.store') }}" id="newStepForm">@csrf</form>

                        <td class="px-4 py-3">
                            <span class="w-7 h-7 flex items-center justify-center bg-gray-300 text-white rounded-full text-xs font-bold">
                                {{ $steps->count() + 1 }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <input type="text" name="name" form="newStepForm" maxlength="100" placeholder="e.g. Finance Approval"
                                   class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-red-800 bg-white">
                        </td>
                        <td class="px-4 py-3">
                            <select name="approver_type" form="newStepForm" id="newStepType"
                                    class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-red-800 bg-white">
                                @foreach(ReimbursementApprovalStep::SELECTABLE_TYPES as $type)
                                    <option value="{{ $type }}">{{ ReimbursementApprovalStep::TYPE_LABELS[$type] }}</option>
                                @endforeach
                                <option value="{{ ReimbursementApprovalStep::TYPE_DIRECT_MANAGER }}" disabled>Direct Manager — not available yet</option>
                            </select>
                        </td>
                        <td class="px-4 py-3">
                            <div id="newRoleBox">
                                <select name="approver_role_id" form="newStepForm"
                                        class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-red-800 bg-white">
                                    <option value="">— choose a role —</option>
                                    @foreach($roles as $role)
                                        <option value="{{ $role->id }}">{{ $role->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div id="newEmployeeBox" class="hidden">
                                <select name="approver_employee_ids[]" form="newStepForm" multiple size="5"
                                        class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-red-800 bg-white">
                                    @foreach($employees as $employee)
                                        <option value="{{ $employee->employee_id }}">{{ $employee->basicData?->nick_name ?? $employee->eci }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-center text-xs text-gray-400">on</td>
                        <td class="px-4 py-3 text-center">
                            <button type="submit" form="newStepForm"
                                    class="px-3 py-1.5 bg-red-800 text-white text-xs font-semibold rounded hover:bg-red-900 transition-all">
                                Add Step
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- Pilihan berlaku-surut. Sengaja BUKAN otomatis: perubahan yang
             menyentuh dokumen berjalan harus terlihat dan disengaja. Jumlah
             dokumennya disebut di sini, sebelum tombolnya ditekan. --}}
        <div class="mt-4 border border-gray-200 rounded-lg px-4 py-3 {{ $openCount > 0 ? 'bg-amber-50 border-amber-200' : 'bg-gray-50' }}">
            @if($openCount > 0)
            <label class="flex items-start gap-2">
                <input type="checkbox" name="apply_to_open" value="1" form="newStepForm"
                       class="mt-0.5 w-4 h-4 text-red-800 border-gray-300 rounded focus:ring-red-800">
                <span class="text-sm">
                    <span class="font-semibold text-amber-900">
                        Also apply the new step to {{ $openCount }} document(s) still in progress
                    </span>
                    <span class="block text-xs text-amber-800 mt-0.5">
                        They will need this approval before they can be completed. Documents that are already
                        approved or rejected are never touched, and neither are approvals that already happened.
                        Each affected document is marked <em>Approval step added later</em>.
                    </span>
                </span>
            </label>
            @else
            <p class="text-sm text-gray-500">
                No document is currently in progress, so a new step will apply to new documents only.
            </p>
            @endif
        </div>
    </div>

    {{-- =============================================================
         BAGIAN 2 — ATURAN REIMBURSEMENT
         ============================================================= --}}
    <form method="POST" action="{{ route('general.settings.reimbursement.update') }}" class="bg-white rounded-xl p-6 shadow-sm space-y-6">
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
                    class="px-5 py-2.5 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900 transition-all">
                Save Settings
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    // Field penyetuju mengikuti tipe yang dipilih. Menampilkan keduanya
    // sekaligus membuat pengguna mengisi field yang akan diabaikan.
    function bindTypeToggle(select, roleBox, employeeBox) {
        function apply() {
            const isRole = select.value === @json(ReimbursementApprovalStep::TYPE_ROLE);
            roleBox.classList.toggle('hidden', !isRole);
            employeeBox.classList.toggle('hidden', isRole);
        }

        select.addEventListener('change', apply);
        apply();
    }

    document.querySelectorAll('.js-type').forEach(function (select) {
        const id = select.dataset.step;
        bindTypeToggle(
            select,
            document.querySelector('.js-role-box[data-step="' + id + '"]'),
            document.querySelector('.js-employee-box[data-step="' + id + '"]')
        );
    });

    bindTypeToggle(
        document.getElementById('newStepType'),
        document.getElementById('newRoleBox'),
        document.getElementById('newEmployeeBox')
    );

    document.querySelectorAll('.js-delete-step').forEach(function (form) {
        form.addEventListener('submit', async function (event) {
            if (form.dataset.confirmed === 'yes') return;

            event.preventDefault();

            const ok = await showConfirm(
                `Delete the approval step "${form.dataset.name}"? Documents already in progress keep their own copy and are not affected.`,
                'Delete Approval Step',
                'danger',
                { okText: 'Delete', cancelText: 'Cancel' }
            );

            if (!ok) return;

            form.dataset.confirmed = 'yes';
            form.submit();
        });
    });
</script>
@endpush

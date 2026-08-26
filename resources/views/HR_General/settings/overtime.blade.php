@extends('dashboard')

@section('title', 'Overtime Settings')
@section('page-title', 'Overtime Settings')
@section('page-subtitle', 'Overtime rules and the approval workflow')

@section('content')
@php
    use App\Models\Overtime\OvertimeApprovalStep;
    use App\Models\Overtime\OvertimeSetting;
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
                Requests move through these steps in order. Each step needs only one approver to act.
            </p>
        </div>

        <div class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-3 mb-5">
            <p class="text-sm text-blue-900">
                <span class="font-semibold">Changes apply to new requests only.</span>
                Requests already in progress keep the steps they were created with, so editing this list
                never rewrites an approval that already happened.
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
                        <form method="POST" action="{{ route('general.settings.overtime.steps.update', $step) }}" id="step-{{ $step->id }}">
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

                            <form method="POST" action="{{ route('general.settings.overtime.steps.move', $step) }}" id="move-up-{{ $step->id }}" class="hidden">
                                @csrf <input type="hidden" name="direction" value="up">
                            </form>
                            <form method="POST" action="{{ route('general.settings.overtime.steps.move', $step) }}" id="move-down-{{ $step->id }}" class="hidden">
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
                                @foreach(OvertimeApprovalStep::SELECTABLE_TYPES as $type)
                                    <option value="{{ $type }}" @selected($step->approver_type === $type)>
                                        {{ OvertimeApprovalStep::TYPE_LABELS[$type] }}
                                    </option>
                                @endforeach

                                {{-- Ditampilkan tetapi tidak dapat dipilih: hierarki
                                     atasan belum ada di basis data. Menyembunyikannya
                                     akan membuat orang mengira fitur ini tidak
                                     direncanakan sama sekali. --}}
                                <option value="{{ OvertimeApprovalStep::TYPE_DIRECT_MANAGER }}" disabled
                                        @selected($step->approver_type === OvertimeApprovalStep::TYPE_DIRECT_MANAGER)>
                                    Direct Manager — not available yet
                                </option>
                            </select>

                            @if($step->approver_type === OvertimeApprovalStep::TYPE_DIRECT_MANAGER)
                                <p class="text-xs text-red-600 mt-1">
                                    This step cannot run: employee hierarchy data does not exist yet.
                                </p>
                            @endif
                        </td>

                        <td class="px-4 py-3">
                            <div class="js-role-box" data-step="{{ $step->id }}"
                                 @class(['hidden' => $step->approver_type !== OvertimeApprovalStep::TYPE_ROLE])>
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
                                 @class(['hidden' => $step->approver_type !== OvertimeApprovalStep::TYPE_EMPLOYEE])>
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
                                <form method="POST" action="{{ route('general.settings.overtime.steps.destroy', $step) }}"
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
                        <form method="POST" action="{{ route('general.settings.overtime.steps.store') }}" id="newStepForm">@csrf</form>

                        <td class="px-4 py-3">
                            <span class="w-7 h-7 flex items-center justify-center bg-gray-300 text-white rounded-full text-xs font-bold">
                                {{ $steps->count() + 1 }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <input type="text" name="name" form="newStepForm" maxlength="100" placeholder="e.g. HR Final Approval"
                                   class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-red-800 bg-white">
                        </td>
                        <td class="px-4 py-3">
                            <select name="approver_type" form="newStepForm" id="newStepType"
                                    class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-red-800 bg-white">
                                @foreach(OvertimeApprovalStep::SELECTABLE_TYPES as $type)
                                    <option value="{{ $type }}">{{ OvertimeApprovalStep::TYPE_LABELS[$type] }}</option>
                                @endforeach
                                <option value="{{ OvertimeApprovalStep::TYPE_DIRECT_MANAGER }}" disabled>Direct Manager — not available yet</option>
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
    </div>

    {{-- =============================================================
         BAGIAN 2 — ATURAN LEMBUR
         ============================================================= --}}
    <form method="POST" action="{{ route('general.settings.overtime.update') }}" class="bg-white rounded-xl p-6 shadow-sm space-y-6">
        @csrf

        <div class="pb-4 border-b-2 border-gray-100">
            <h2 class="text-2xl font-bold text-gray-900">Overtime Rules</h2>
            <p class="text-sm text-gray-500 mt-0.5">
                Every value below can block or flag a request. Set <strong>0</strong> where you want no limit at all.
            </p>
        </div>

        {{-- Aturan tanggal --}}
        <div>
            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide mb-3">Dates</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <label class="flex items-start gap-2 md:col-span-1">
                    <input type="checkbox" name="allow_future_date" value="1" @checked($settings->allow_future_date)
                           class="mt-0.5 w-4 h-4 text-red-800 border-gray-300 rounded focus:ring-red-800">
                    <span class="text-sm">
                        <span class="font-semibold text-gray-800">Allow future dates</span>
                        <span class="block text-xs text-gray-500">
                            Attendance comparison is skipped for dates that have not happened yet.
                        </span>
                    </span>
                </label>

                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Backdate limit (days)</label>
                    <input type="number" name="max_backdate_days" min="0" max="3650" required
                           value="{{ old('max_backdate_days', $settings->max_backdate_days) }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                    <p class="text-xs text-gray-400 mt-1">0 = no limit.</p>
                    @error('max_backdate_days')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Closed reporting period</label>
                    <select name="locked_period_policy"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                        @foreach([
                            OvertimeSetting::LOCK_OFF            => 'Ignore — allow everything',
                            OvertimeSetting::LOCK_BLOCK_EMPLOYEE => 'Block employees only',
                            OvertimeSetting::LOCK_BLOCK_ALL      => 'Block everyone',
                        ] as $value => $label)
                            <option value="{{ $value }}" @selected($settings->locked_period_policy === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-400 mt-1">
                        "Block employees only" still lets Overtime — Manage holders act.
                    </p>
                </div>
            </div>
        </div>

        {{-- Aturan durasi --}}
        <div class="pt-2">
            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide mb-3">Duration</h3>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-5">
                <label class="flex items-start gap-2">
                    <input type="checkbox" name="allow_crosses_midnight" value="1" @checked($settings->allow_crosses_midnight)
                           class="mt-0.5 w-4 h-4 text-red-800 border-gray-300 rounded focus:ring-red-800">
                    <span class="text-sm">
                        <span class="font-semibold text-gray-800">Allow past midnight</span>
                        <span class="block text-xs text-gray-500">Overtime may end on the next day.</span>
                    </span>
                </label>

                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Minimum (minutes)</label>
                    <input type="number" name="min_duration_minutes" min="0" max="1440" required
                           value="{{ old('min_duration_minutes', $settings->min_duration_minutes) }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                    <p class="text-xs text-gray-400 mt-1">0 = no minimum. Flagged, not blocked.</p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Daily cap (minutes)</label>
                    <input type="number" name="max_daily_minutes" min="0" max="1440" required
                           value="{{ old('max_daily_minutes', $settings->max_daily_minutes) }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                    <p class="text-xs text-gray-400 mt-1">0 = no cap. Regulation reference: 240.</p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Weekly cap (minutes)</label>
                    <input type="number" name="max_weekly_minutes" min="0" max="10080" required
                           value="{{ old('max_weekly_minutes', $settings->max_weekly_minutes) }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                    <p class="text-xs text-gray-400 mt-1">0 = no cap. Regulation reference: 1080.</p>
                </div>
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
                            Requesters who hold an approver role may approve their own request.
                            It is always recorded and flagged.
                        </span>
                    </span>
                </label>

                <label class="flex items-start gap-2">
                    <input type="checkbox" name="allow_approver_adjust_time" value="1" @checked($settings->allow_approver_adjust_time)
                           class="mt-0.5 w-4 h-4 text-red-800 border-gray-300 rounded focus:ring-red-800">
                    <span class="text-sm">
                        <span class="font-semibold text-gray-800">Approvers may adjust times</span>
                        <span class="block text-xs text-gray-500">
                            The originally claimed times are always kept on record.
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

            @if($settings->allow_self_approval)
            <div class="mt-4 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3">
                <p class="text-sm text-amber-900">
                    <span class="font-semibold">Self-approval is currently enabled.</span>
                    With only {{ $steps->where('is_active', true)->count() }} active step(s), a requester who holds
                    the approver role can take their own request all the way to approved without anyone else seeing it.
                    Every such case is flagged as <em>Self-approved</em> in the review list. Adding a second step
                    removes this exposure.
                </p>
            </div>
            @endif
        </div>

        {{-- Perbandingan & alasan --}}
        <div class="pt-2">
            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide mb-3">Comparison &amp; Reason</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Attendance mismatch tolerance (minutes)</label>
                    <input type="number" name="mismatch_tolerance_minutes" min="0" max="1440" required
                           value="{{ old('mismatch_tolerance_minutes', $settings->mismatch_tolerance_minutes) }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                    <p class="text-xs text-gray-400 mt-1">
                        A claim differing from the attendance record by more than this is flagged for the reviewer — never rejected.
                    </p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Minimum reason length</label>
                    <input type="number" name="require_reason_min_chars" min="0" max="255" required
                           value="{{ old('require_reason_min_chars', $settings->require_reason_min_chars) }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                    <p class="text-xs text-gray-400 mt-1">Characters required in the reason field.</p>
                </div>
            </div>
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
            const isRole = select.value === @json(OvertimeApprovalStep::TYPE_ROLE);
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
                `Delete the approval step "${form.dataset.name}"? Requests already in progress keep their own copy and are not affected.`,
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

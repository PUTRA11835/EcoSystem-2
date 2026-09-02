@extends('dashboard')

@section('title', 'Purchase Request Settings')
@section('page-title', 'Purchase Request Settings')
@section('page-subtitle', 'Request rules, units, cost centers, and the approval workflow')

@section('content')
@php
    use App\Models\PurchaseRequest\PurchaseRequestApprovalStep;
    use App\Models\PurchaseRequest\PurchaseRequestItem;
    use App\Models\PurchaseRequest\PurchaseRequestSetting;
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

        {{-- Aturannya ASIMETRIS, dan itu disengaja — memperketat boleh berlaku
             surut, melonggarkan tidak pernah. Dijelaskan di sini supaya tidak
             terbaca sebagai perilaku yang tidak konsisten (Keputusan D116). --}}
        <div class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-3 mb-5">
            <p class="text-sm text-blue-900">
                <span class="font-semibold">Adding a step can be applied to requests already in progress.
                Removing or changing one never is.</span>
                Adding a step tightens control, and it is exactly the requests already in flight that most
                need it. Removing a step is the dangerous direction: a request waiting at a deleted step
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
                        <th class="px-4 py-3 w-32 text-center">Chosen by<br>requester</th>
                        <th class="px-4 py-3 w-20 text-center">Active</th>
                        <th class="px-4 py-3 w-40 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($steps as $step)
                    <tr class="align-top hover:bg-gray-50 transition-colors">
                        <form method="POST" action="{{ route('general.settings.purchase-request.steps.update', $step) }}" id="step-{{ $step->id }}">
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

                            <form method="POST" action="{{ route('general.settings.purchase-request.steps.move', $step) }}" id="move-up-{{ $step->id }}" class="hidden">
                                @csrf <input type="hidden" name="direction" value="up">
                            </form>
                            <form method="POST" action="{{ route('general.settings.purchase-request.steps.move', $step) }}" id="move-down-{{ $step->id }}" class="hidden">
                                @csrf <input type="hidden" name="direction" value="down">
                            </form>
                        </td>

                        <td class="px-4 py-3">
                            <input type="text" name="name" form="step-{{ $step->id }}" value="{{ $step->name }}" required maxlength="100"
                                   class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-red-800">
                            <p class="text-xs text-gray-400 mt-1">Also printed as a signature column title.</p>
                        </td>

                        <td class="px-4 py-3">
                            <select name="approver_type" form="step-{{ $step->id }}"
                                    class="js-type w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-red-800"
                                    data-step="{{ $step->id }}">
                                @foreach(PurchaseRequestApprovalStep::SELECTABLE_TYPES as $type)
                                    <option value="{{ $type }}" @selected($step->approver_type === $type)>
                                        {{ PurchaseRequestApprovalStep::TYPE_LABELS[$type] }}
                                    </option>
                                @endforeach

                                {{-- Ditampilkan tetapi tidak dapat dipilih: hierarki
                                     atasan belum ada di basis data. Menyembunyikannya
                                     akan membuat orang mengira fitur ini tidak
                                     direncanakan sama sekali. --}}
                                <option value="{{ PurchaseRequestApprovalStep::TYPE_DIRECT_MANAGER }}" disabled
                                        @selected($step->approver_type === PurchaseRequestApprovalStep::TYPE_DIRECT_MANAGER)>
                                    Direct Manager — not available yet
                                </option>
                            </select>

                            @if($step->approver_type === PurchaseRequestApprovalStep::TYPE_DIRECT_MANAGER)
                                <p class="text-xs text-red-600 mt-1">
                                    This step cannot run: employee hierarchy data does not exist yet.
                                </p>
                            @endif
                        </td>

                        <td class="px-4 py-3">
                            <div class="js-role-box" data-step="{{ $step->id }}"
                                 @class(['hidden' => $step->approver_type !== PurchaseRequestApprovalStep::TYPE_ROLE])>
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
                                 @class(['hidden' => $step->approver_type !== PurchaseRequestApprovalStep::TYPE_EMPLOYEE])>
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

                        {{-- Keputusan D126 — inilah yang menyatukan dua hal yang
                             pada aplikasi acuan tampak seperti dua mekanisme
                             berbeda: pengaturan penyetuju DAN dropdown Approver
                             di halaman pemohon. --}}
                        <td class="px-4 py-3 text-center">
                            <input type="checkbox" name="requester_selectable" value="1" form="step-{{ $step->id }}"
                                   @checked($step->requester_selectable)
                                   class="w-4 h-4 text-red-800 border-gray-300 rounded focus:ring-red-800">

                            @if($step->requester_selectable && !$step->offersChoice())
                                <p class="text-xs text-red-600 mt-1 text-left">
                                    No candidate — this step cannot be chosen.
                                </p>
                            @elseif($step->requester_selectable)
                                <p class="text-xs text-gray-400 mt-1 text-left">
                                    {{ count($step->candidateEmployeeIds()) }} candidate(s)
                                </p>
                            @endif
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
                                <form method="POST" action="{{ route('general.settings.purchase-request.steps.destroy', $step) }}"
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
                        <form method="POST" action="{{ route('general.settings.purchase-request.steps.store') }}" id="newStepForm">@csrf</form>

                        <td class="px-4 py-3">
                            <span class="w-7 h-7 flex items-center justify-center bg-gray-300 text-white rounded-full text-xs font-bold">
                                {{ $steps->count() + 1 }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <input type="text" name="name" form="newStepForm" maxlength="100" placeholder="e.g. Final Approval"
                                   class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-red-800 bg-white">
                        </td>
                        <td class="px-4 py-3">
                            <select name="approver_type" form="newStepForm" id="newStepType"
                                    class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-red-800 bg-white">
                                @foreach(PurchaseRequestApprovalStep::SELECTABLE_TYPES as $type)
                                    <option value="{{ $type }}">{{ PurchaseRequestApprovalStep::TYPE_LABELS[$type] }}</option>
                                @endforeach
                                <option value="{{ PurchaseRequestApprovalStep::TYPE_DIRECT_MANAGER }}" disabled>Direct Manager — not available yet</option>
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
                        <td class="px-4 py-3 text-center">
                            <input type="checkbox" name="requester_selectable" value="1" form="newStepForm"
                                   class="w-4 h-4 text-red-800 border-gray-300 rounded focus:ring-red-800">
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

        {{-- Penjelasan "Chosen by requester". Ditulis di halaman, bukan hanya di
             dokumen, karena inilah satu-satunya kolom di editor ini yang
             perilakunya tidak dapat ditebak dari namanya. --}}
        <div class="mt-4 border border-gray-200 rounded-lg px-4 py-3 bg-gray-50">
            <p class="text-sm text-gray-700">
                <span class="font-semibold">"Chosen by requester"</span> turns this step's approver list into
                a dropdown on the submission form: the requester picks one name from the candidates you set
                here. The pick is frozen onto that request — changing the candidates later never moves a
                request that is already waiting. Leave it off and the step behaves like every other module:
                the configuration alone decides.
            </p>
            <p class="text-xs text-gray-500 mt-2">
                A step marked this way must have at least one candidate, otherwise new requests would arrive
                with a step nobody can act on. Saving one without candidates is refused.
            </p>
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
                        Also apply the new step to {{ $openCount }} request(s) still in progress
                    </span>
                    <span class="block text-xs text-amber-800 mt-0.5">
                        They will need this approval before they can be completed. Requests that are already
                        approved, rejected, or cancelled are never touched, and neither are approvals that
                        already happened. Each affected request is marked <em>Approval step added later</em>.
                        A step applied this way cannot ask the requester to pick — it falls back to its own
                        approver list.
                    </span>
                </span>
            </label>
            @else
            <p class="text-sm text-gray-500">
                No request is currently in progress, so a new step will apply to new requests only.
            </p>
            @endif
        </div>
    </div>

    {{-- =============================================================
         BAGIAN 2 — ATURAN PURCHASE REQUEST
         ============================================================= --}}
    <form method="POST" action="{{ route('general.settings.purchase-request.update') }}" class="bg-white rounded-xl p-6 shadow-sm space-y-6">
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
            const isRole = select.value === @json(PurchaseRequestApprovalStep::TYPE_ROLE);
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

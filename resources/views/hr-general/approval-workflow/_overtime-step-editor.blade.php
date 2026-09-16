{{--
    Editor alur persetujuan Overtime — diekstrak dari settings/overtime.blade.php
    (D180) ke hub "Approval Workflow" tersendiri, supaya bisa diberi hak terpisah
    dari hak mengubah Aturan Overtime.

    Variabel yang wajib dikirim:
      $steps      koleksi OvertimeApprovalStep, terurut order_seq
      $roles      daftar EmployeeRole
      $employees  daftar karyawan aktif
--}}
@php
    use App\Models\Overtime\OvertimeApprovalStep;
@endphp
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
                        <form method="POST" action="{{ route('general.overtime.settings.steps.update', $step) }}" id="step-{{ $step->id }}">
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

                            <form method="POST" action="{{ route('general.overtime.settings.steps.move', $step) }}" id="move-up-{{ $step->id }}" class="hidden">
                                @csrf <input type="hidden" name="direction" value="up">
                            </form>
                            <form method="POST" action="{{ route('general.overtime.settings.steps.move', $step) }}" id="move-down-{{ $step->id }}" class="hidden">
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
                                        class="px-3 py-1.5 primary-gradient text-white text-xs font-semibold rounded hover:opacity-90 transition-all">
                                    Save
                                </button>
                                <form method="POST" action="{{ route('general.overtime.settings.steps.destroy', $step) }}"
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
                        <form method="POST" action="{{ route('general.overtime.settings.steps.store') }}" id="newStepForm">@csrf</form>

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
                                    class="px-3 py-1.5 primary-gradient text-white text-xs font-semibold rounded hover:opacity-90 transition-all">
                                Add Step
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

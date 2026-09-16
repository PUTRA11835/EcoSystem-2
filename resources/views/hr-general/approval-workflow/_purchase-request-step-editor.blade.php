{{--
    Editor alur persetujuan Purchase Request — diekstrak dari
    settings/purchase-request.blade.php (D180) ke hub "Approval Workflow" tersendiri.

    Variabel yang wajib dikirim:
      $steps      koleksi PurchaseRequestApprovalStep, terurut order_seq
      $openCount  berapa dokumen berjalan yang terkena langkah baru (D116)
      $roles      daftar EmployeeRole
      $employees  daftar karyawan aktif
--}}
@php
    use App\Models\PurchaseRequest\PurchaseRequestApprovalStep;
@endphp
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
                        <form method="POST" action="{{ route('general.purchase-request.settings.steps.update', $step) }}" id="step-{{ $step->id }}">
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

                            <form method="POST" action="{{ route('general.purchase-request.settings.steps.move', $step) }}" id="move-up-{{ $step->id }}" class="hidden">
                                @csrf <input type="hidden" name="direction" value="up">
                            </form>
                            <form method="POST" action="{{ route('general.purchase-request.settings.steps.move', $step) }}" id="move-down-{{ $step->id }}" class="hidden">
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
                                        class="px-3 py-1.5 primary-gradient text-white text-xs font-semibold rounded hover:opacity-90 transition-all">
                                    Save
                                </button>
                                <form method="POST" action="{{ route('general.purchase-request.settings.steps.destroy', $step) }}"
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
                        <form method="POST" action="{{ route('general.purchase-request.settings.steps.store') }}" id="newStepForm">@csrf</form>

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
                                    class="px-3 py-1.5 primary-gradient text-white text-xs font-semibold rounded hover:opacity-90 transition-all">
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

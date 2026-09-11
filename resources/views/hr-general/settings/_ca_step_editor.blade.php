{{--
    Editor alur persetujuan — dipakai DUA KALI di halaman yang sama, untuk modul
    `cash_advance` dan `cash_advance_report` (Keputusan D136).

    Dijadikan partial, bukan disalin dua kali, karena kedua editor HARUS berperilaku
    sama persis. Dua salinan akan menyimpang cepat atau lambat — dan yang menyimpang
    diam-diam di sini adalah aturan yang menentukan siapa boleh menyetujui uang.

    Variabel yang wajib dikirim:
      $module     'cash_advance' | 'cash_advance_report'
      $steps      koleksi langkah modul itu
      $openCount  berapa dokumen berjalan yang akan terkena langkah baru
      $heading    judul bagian
      $blurb      satu kalimat penjelas
      $roles      daftar EmployeeRole
      $employees  daftar karyawan aktif
--}}
@php
    use App\Models\CashAdvance\CashAdvanceApprovalStep;

    $uid = str_replace('_', '-', $module);   // pembeda id elemen antar dua editor
@endphp

<div class="bg-white rounded-xl p-6 shadow-sm">
    <div class="mb-5 pb-4 border-b-2 border-gray-100">
        <h2 class="text-2xl font-bold text-gray-900">{{ $heading }}</h2>
        <p class="text-sm text-gray-500 mt-0.5">{{ $blurb }}</p>
    </div>

    {{-- Aturannya ASIMETRIS, dan itu disengaja — memperketat boleh berlaku surut,
         melonggarkan tidak pernah. Dijelaskan di layar supaya tidak terbaca
         sebagai perilaku yang tidak konsisten (Keputusan D116). --}}
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
                    <th class="px-4 py-3 w-44">Approver Type</th>
                    <th class="px-4 py-3 w-36">Actor</th>
                    <th class="px-4 py-3">Reference</th>
                    <th class="px-4 py-3 w-28 text-center">Chosen by<br>requester</th>
                    <th class="px-4 py-3 w-20 text-center">Active</th>
                    <th class="px-4 py-3 w-36 text-center">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($steps as $step)
                <tr class="align-top hover:bg-gray-50 transition-colors" data-ca-step-row>
                    <form method="POST" action="{{ route('management.cash-advance-settings.steps.update', $step) }}"
                          id="step-{{ $step->id }}">
                        @csrf
                    </form>

                    <td class="px-4 py-3">
                        <div class="flex items-center gap-1">
                            <span class="w-7 h-7 flex items-center justify-center primary-solid text-white rounded-full text-xs font-bold">
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

                        <form method="POST" action="{{ route('management.cash-advance-settings.steps.move', $step) }}"
                              id="move-up-{{ $step->id }}" class="hidden">
                            @csrf <input type="hidden" name="direction" value="up">
                        </form>
                        <form method="POST" action="{{ route('management.cash-advance-settings.steps.move', $step) }}"
                              id="move-down-{{ $step->id }}" class="hidden">
                            @csrf <input type="hidden" name="direction" value="down">
                        </form>
                    </td>

                    <td class="px-4 py-3">
                        <input type="text" name="name" form="step-{{ $step->id }}" value="{{ $step->name }}"
                               required maxlength="100"
                               class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 primary-border">
                        <p class="text-xs text-gray-400 mt-1">Shown in the approval timeline.</p>
                    </td>

                    <td class="px-4 py-3">
                        <select name="approver_type" form="step-{{ $step->id }}"
                                data-ca-type-select
                                class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 primary-border">
                            @foreach(CashAdvanceApprovalStep::SELECTABLE_TYPES as $type)
                                <option value="{{ $type }}" @selected($step->approver_type === $type)>
                                    {{ CashAdvanceApprovalStep::TYPE_LABELS[$type] }}
                                </option>
                            @endforeach

                            {{-- Ditampilkan tetapi tidak dapat dipilih: hierarki atasan
                                 belum ada di basis data. Menyembunyikannya akan membuat
                                 orang mengira fitur ini tidak direncanakan sama sekali. --}}
                            <option value="{{ CashAdvanceApprovalStep::TYPE_DIRECT_MANAGER }}" disabled
                                    @selected($step->approver_type === CashAdvanceApprovalStep::TYPE_DIRECT_MANAGER)>
                                Direct Manager — not available yet
                            </option>
                        </select>

                        @if($step->approver_type === CashAdvanceApprovalStep::TYPE_DIRECT_MANAGER)
                            <p class="text-xs text-red-600 mt-1">
                                This step cannot run: employee hierarchy data does not exist yet.
                            </p>
                        @endif
                    </td>

                    {{-- 🔴 ACTOR — kolom yang menentukan DUA hal sekaligus:
                         label kiri pada Approval Timeline, dan langkah mana yang
                         namanya tercetak di kolom "Approved by" pada dokumen. --}}
                    <td class="px-4 py-3">
                        <select name="actor_role" form="step-{{ $step->id }}"
                                class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 primary-border">
                            @foreach(CashAdvanceApprovalStep::ACTOR_ROLES as $actor)
                                <option value="{{ $actor }}" @selected($step->actor_role === $actor)>
                                    {{ CashAdvanceApprovalStep::ACTOR_LABELS[$actor] }}
                                </option>
                            @endforeach
                        </select>
                        @if($step->isApproverStep())
                            <p class="text-xs text-gray-400 mt-1">Prints under “Approved by”.</p>
                        @endif
                    </td>

                    {{-- 🔴 KOLOM REFERENCE — hanya SATU kontrol yang terlihat.

                         Versi pertama menampilkan dropdown posisi DAN daftar karyawan
                         sekaligus, apa pun tipenya. Pemilik sistem langsung menemukan
                         kejanggalannya: "masa saya ganti posisi, karyawannya tetap
                         sama?" — dan ia benar. Kedua kontrol itu memang TIDAK
                         berhubungan: yang satu dipakai tipe "By Position", yang lain
                         tipe "Direct User", dan yang tidak dipakai DIBUANG saat
                         disimpan. Menampilkan keduanya berdampingan membuat layar
                         seolah menjanjikan hubungan yang tidak pernah ada.

                         Sekarang JavaScript menyembunyikan yang tidak dipakai, dan
                         dropdown posisi MENYEBUTKAN berapa orang yang memegangnya —
                         supaya memilih posisi berhenti menjadi tebakan. --}}
                    <td class="px-4 py-3" data-ca-reference>
                        <div data-ref-for="{{ CashAdvanceApprovalStep::TYPE_ROLE }}">
                            <select name="approver_role_id" form="step-{{ $step->id }}"
                                    data-ca-role-select
                                    class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 primary-border">
                                <option value="">— choose a position —</option>
                                @foreach($roles as $role)
                                    @php $held = (int) ($roleCandidates[$role->id] ?? 0); @endphp
                                    <option value="{{ $role->id }}" data-holders="{{ $held }}"
                                            @selected((int) $step->approver_role_id === (int) $role->id)>
                                        {{ $role->name }} — {{ $held }} employee(s)
                                    </option>
                                @endforeach
                            </select>

                            {{-- Diisi JavaScript. Posisi tanpa pemegang berarti langkah
                                 ini tidak akan pernah menemukan penyetuju. --}}
                            <p class="text-xs mt-1" data-ca-role-hint></p>
                        </div>

                        <div data-ref-for="{{ CashAdvanceApprovalStep::TYPE_EMPLOYEE }}">
                            <select name="approver_employee_ids[]" form="step-{{ $step->id }}" multiple size="4"
                                    class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 primary-border">
                                @foreach($employees as $employee)
                                    <option value="{{ $employee->employee_id }}"
                                        @selected(in_array((int) $employee->employee_id, array_map('intval', $step->approver_employee_ids ?? []), true))>
                                        {{ $employee->basicData?->nick_name ?? $employee->eci }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-xs text-gray-400 mt-1">Hold Ctrl (or Cmd) to pick more than one.</p>
                        </div>

                        <div data-ref-for="{{ CashAdvanceApprovalStep::TYPE_DIRECT_MANAGER }}">
                            <p class="text-xs text-red-600">
                                No reference is needed — but this type cannot run yet.
                            </p>
                        </div>
                    </td>

                    {{-- 🔴 Inilah perbaikan atas cacat aplikasi acuan: bila dicentang,
                         form pengajuan MERENDER dropdown Approver berisi kandidat
                         langkah ini. Bila tidak, field-nya tidak dikirim dan tidak
                         divalidasi — tidak ada jalan bagi pengguna untuk gagal karena
                         diminta sesuatu yang layarnya tidak pernah tampilkan. --}}
                    <td class="px-4 py-3 text-center">
                        <input type="checkbox" name="requester_selectable" value="1" form="step-{{ $step->id }}"
                               @checked($step->requester_selectable)
                               class="w-4 h-4 rounded border-gray-300">
                    </td>

                    <td class="px-4 py-3 text-center">
                        <input type="checkbox" name="is_active" value="1" form="step-{{ $step->id }}"
                               @checked($step->is_active)
                               class="w-4 h-4 rounded border-gray-300">
                    </td>

                    <td class="px-4 py-3">
                        <div class="flex items-center justify-center gap-2">
                            <button type="submit" form="step-{{ $step->id }}"
                                    class="px-3 py-1.5 bg-gray-800 text-white rounded text-xs font-medium hover:bg-gray-900 transition-colors">
                                Save
                            </button>
                            {{-- Form SUNGGUHAN ber-@csrf yang di-intercept JavaScript,
                                 bukan form yang dibangun di JavaScript. Pola yang sama
                                 dipakai seluruh Purchase Request, dan ia tidak bergantung
                                 pada meta tag csrf-token sama sekali — di aplikasi ini ada
                                 satu pembacaan meta yang rusak karena memakai kutip
                                 tipografis, dan menyalin pola itu adalah jebakan yang
                                 sudah tercatat. --}}
                            <form method="POST"
                                  action="{{ route('management.cash-advance-settings.steps.destroy', $step) }}"
                                  class="js-ca-delete-step inline"
                                  data-name="{{ $step->name }}">
                                @csrf
                                <button type="submit"
                                        class="px-3 py-1.5 border border-red-300 text-red-700 rounded text-xs font-medium hover:bg-red-50 transition-colors">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-4 py-8 text-center text-sm text-gray-500">
                        No approval step yet. Documents cannot be reviewed until you add one.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- ── Tambah langkah ───────────────────────────────────────────────── --}}
    <form method="POST" action="{{ route('management.cash-advance-settings.steps.store') }}"
          class="mt-5 border border-dashed border-gray-300 rounded-lg p-4" data-ca-step-row>
        @csrf
        <input type="hidden" name="module" value="{{ $module }}">

        <h3 class="text-sm font-semibold text-gray-900 mb-3">Add a step</h3>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Step name</label>
                <input type="text" name="name" required maxlength="100" placeholder="e.g. Final Approval"
                       class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 primary-border">
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Approver type</label>
                <select name="approver_type" data-ca-type-select
                        class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 primary-border">
                    @foreach(CashAdvanceApprovalStep::SELECTABLE_TYPES as $type)
                        <option value="{{ $type }}">{{ CashAdvanceApprovalStep::TYPE_LABELS[$type] }}</option>
                    @endforeach
                    <option value="{{ CashAdvanceApprovalStep::TYPE_DIRECT_MANAGER }}" disabled>
                        Direct Manager — not available yet
                    </option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Actor</label>
                <select name="actor_role"
                        class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 primary-border">
                    @foreach(CashAdvanceApprovalStep::ACTOR_ROLES as $actor)
                        <option value="{{ $actor }}" @selected($actor === CashAdvanceApprovalStep::ACTOR_APPROVER)>
                            {{ CashAdvanceApprovalStep::ACTOR_LABELS[$actor] }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Dua kontrol Reference, sama seperti pada baris tabel: hanya yang
                 sesuai tipenya yang terlihat. --}}
            <div data-ref-for="{{ CashAdvanceApprovalStep::TYPE_ROLE }}">
                <label class="block text-xs font-medium text-gray-600 mb-1">Position</label>
                <select name="approver_role_id" data-ca-role-select
                        class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 primary-border">
                    <option value="">— none —</option>
                    @foreach($roles as $role)
                        @php $held = (int) ($roleCandidates[$role->id] ?? 0); @endphp
                        <option value="{{ $role->id }}" data-holders="{{ $held }}">
                            {{ $role->name }} — {{ $held }} employee(s)
                        </option>
                    @endforeach
                </select>
                <p class="text-xs mt-1" data-ca-role-hint></p>
            </div>

            <div data-ref-for="{{ CashAdvanceApprovalStep::TYPE_EMPLOYEE }}">
                <label class="block text-xs font-medium text-gray-600 mb-1">Employees</label>
                <select name="approver_employee_ids[]" multiple size="4"
                        class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 primary-border">
                    @foreach($employees as $employee)
                        <option value="{{ $employee->employee_id }}">
                            {{ $employee->basicData?->nick_name ?? $employee->eci }}
                        </option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-400 mt-1">Hold Ctrl (or Cmd) to pick more than one.</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-5 mt-4">
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="requester_selectable" value="1" class="w-4 h-4 rounded border-gray-300">
                Chosen by requester
            </label>

            {{-- Angkanya disebut SEBELUM tombolnya ditekan: keputusan yang menyentuh
                 dokumen berjalan tidak boleh diambil tanpa tahu berapa yang tersentuh. --}}
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="apply_to_open" value="1" class="w-4 h-4 rounded border-gray-300">
                Also apply to {{ $openCount }} document(s) already in progress
            </label>

            <button type="submit"
                    class="ml-auto px-4 py-2 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-gray-900 transition-colors">
                Add step
            </button>
        </div>
    </form>
</div>

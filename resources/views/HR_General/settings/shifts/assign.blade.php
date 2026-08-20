@extends('dashboard')

@section('title', 'Assign Employees — ' . $shift->name)
@section('page-title', 'Assign Employees')
@section('page-subtitle', $shift->name . ' · ' . $shift->time_range)

@section('content')
<div class="space-y-5">

    {{-- Ringkasan shift --}}
    <div class="bg-white rounded-xl p-6 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">
                    {{ $shift->name }}
                    @if($shift->is_default)
                        <span class="ml-1 inline-block px-2 py-0.5 text-xs font-semibold rounded bg-amber-100 text-amber-700 align-middle">Default</span>
                    @endif
                </h2>
                <p class="text-sm text-gray-500 mt-0.5">
                    {{ $shift->time_range }} · {{ $shift->late_tolerance_minutes }} min tolerance ·
                    {{ $shift->break_minutes }} min break · {{ $assigned->count() }} employee(s) assigned
                </p>
            </div>
            <a href="{{ route('general.settings.shifts.index') }}"
               class="px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all whitespace-nowrap">
                <i class="fas fa-arrow-left mr-1"></i> Back to Shifts
            </a>
        </div>

        @if($shift->is_default)
        <div class="mt-4 flex items-start gap-2 text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-lg p-3">
            <i class="fas fa-circle-info text-amber-500 mt-0.5"></i>
            <div>
                This is the default shift. Every employee without an explicit assignment already follows it,
                so assigning them here is only needed when you want the assignment recorded explicitly.
            </div>
        </div>
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

        {{-- Karyawan yang sudah ditugaskan --}}
        <div class="bg-white rounded-xl p-6 shadow-sm">
            <h3 class="text-lg font-bold text-gray-900 mb-1">Assigned Employees</h3>
            <p class="text-sm text-gray-500 mb-4">Releasing an employee ends the assignment; past attendance records are unaffected.</p>

            <div class="border border-gray-200 rounded-lg overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                            <th class="px-4 py-3 w-12">No</th>
                            <th class="px-4 py-3">Employee</th>
                            <th class="px-4 py-3">Since</th>
                            <th class="px-4 py-3 text-center w-24">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($assigned as $index => $row)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-3 text-gray-400">{{ $index + 1 }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900">{{ $row->employee?->basicData?->nick_name ?? '—' }}</div>
                                <div class="text-xs text-gray-400">
                                    {{ $row->employee?->eci }}
                                    @if($row->employee?->basicData?->department)
                                        · {{ $row->employee->basicData->department }}
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3 text-gray-600 text-xs">{{ $row->start_date?->format('d M Y') }}</td>
                            <td class="px-4 py-3 text-center">
                                <button type="button"
                                        onclick="releaseAssignment({{ $row->id }}, @js($row->employee?->basicData?->nick_name ?? 'this employee'))"
                                        class="px-3 py-1.5 text-xs font-semibold text-red-600 border border-red-200 rounded-lg hover:bg-red-50 transition-all">
                                    Release
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="px-4 py-10 text-center">
                                <div class="flex flex-col items-center gap-2 text-gray-400">
                                    <i class="fas fa-user-clock text-2xl"></i>
                                    <p class="text-sm font-medium">No employees assigned to this shift yet.</p>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Tambah penugasan --}}
        <div class="bg-white rounded-xl p-6 shadow-sm">
            <h3 class="text-lg font-bold text-gray-900 mb-1">Add Employees</h3>
            <p class="text-sm text-gray-500 mb-4">
                @if($maxShifts > 1)
                    Each employee may hold up to <strong class="text-gray-700">{{ $maxShifts }} active shifts</strong>.
                    Those already at the limit are listed but cannot be selected.
                @else
                    Each employee may hold <strong class="text-gray-700">one active shift</strong>. Assigning someone
                    who already has a different shift ends their previous assignment automatically.
                @endif
                The limit is configurable in <em>Attendance Settings</em>.
            </p>

            <form method="GET" action="{{ route('general.settings.shifts.assign', $shift) }}" class="flex gap-2 mb-4">
                <input type="text" name="search" value="{{ $search }}" placeholder="Search by name or employee code..."
                       class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                <button type="submit" class="px-4 py-2 bg-gray-800 text-white text-sm font-semibold rounded-lg hover:bg-gray-900 transition-all">
                    <i class="fas fa-search"></i>
                </button>
            </form>

            <form method="POST" action="{{ route('general.settings.shifts.assign.store', $shift) }}" id="assignForm">
                @csrf

                <div class="border border-gray-200 rounded-lg max-h-96 overflow-y-auto divide-y divide-gray-100">
                    @forelse($candidates as $employee)
                    @php
                        $held = (int) ($activeCounts[$employee->employee_id] ?? 0);
                        // Dengan batas 1, penugasan lama otomatis berakhir sehingga
                        // orangnya tetap boleh dipilih. Dengan batas lebih dari satu,
                        // kuota penuh berarti benar-benar tidak bisa ditambah.
                        $atLimit = $maxShifts > 1 && $held >= $maxShifts;
                    @endphp
                    <label class="flex items-center gap-3 px-4 py-2.5 transition-colors {{ $atLimit ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-50 cursor-pointer' }}">
                        <input type="checkbox" name="employee_ids[]" value="{{ $employee->employee_id }}"
                               @disabled($atLimit)
                               class="assign-check w-4 h-4 rounded border-gray-300 text-red-800 focus:ring-red-800 disabled:cursor-not-allowed">
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-medium text-gray-900 truncate">{{ $employee->basicData?->nick_name ?? '—' }}</span>
                            <span class="block text-xs text-gray-400 truncate">
                                {{ $employee->eci }}
                                @if($employee->basicData?->department) · {{ $employee->basicData->department }} @endif
                            </span>
                        </span>
                        @if($held > 0)
                        <span class="shrink-0 px-2 py-0.5 text-xs font-semibold rounded {{ $atLimit ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600' }}"
                              title="{{ $held }} active shift(s) of {{ $maxShifts }} allowed">
                            {{ $held }}/{{ $maxShifts }}
                        </span>
                        @endif
                    </label>
                    @empty
                    <div class="px-4 py-10 text-center text-gray-400">
                        <i class="fas fa-users-slash text-2xl mb-2"></i>
                        <p class="text-sm font-medium">
                            {{ $search !== '' ? 'No matching employee found.' : 'Every active employee already has a shift assignment.' }}
                        </p>
                    </div>
                    @endforelse
                </div>

                @if($candidates->isNotEmpty())
                <div class="flex items-center justify-between gap-3 mt-4">
                    <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                        <input type="checkbox" id="checkAll" class="w-4 h-4 rounded border-gray-300 text-red-800 focus:ring-red-800">
                        Select all shown
                    </label>
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900 transition-all">
                        <i class="fas fa-user-plus"></i> Assign Selected
                    </button>
                </div>
                <p class="text-xs text-gray-400 mt-2">Showing up to 200 employees. Use the search box to narrow the list.</p>
                @endif
            </form>
        </div>
    </div>
</div>

<form id="releaseForm" method="POST" class="hidden">
    @csrf
    @method('DELETE')
</form>
@endsection

@push('scripts')
<script>
const SHIFT_NAME = @js($shift->name);
const SHIFT_ID   = @js($shift->id);
const MAX_SHIFTS = @js((int) $maxShifts);

(function () {
    'use strict';

    const checkAll = document.getElementById('checkAll');
    if (checkAll) {
        checkAll.addEventListener('change', function () {
            document.querySelectorAll('.assign-check:not(:disabled)').forEach((box) => { box.checked = checkAll.checked; });
        });
    }

    const assignForm = document.getElementById('assignForm');
    let confirmed = false;

    assignForm.addEventListener('submit', async function (event) {
        if (confirmed) return;
        event.preventDefault();

        const selected = document.querySelectorAll('.assign-check:checked').length;

        if (selected === 0) {
            showToast('Select at least one employee to assign.', 'warning');
            return;
        }

        const ok = await showConfirm(
            `Assign ${selected} employee(s) to "${SHIFT_NAME}"? `
            + (MAX_SHIFTS > 1
                ? `This is added alongside any shift they already hold, up to ${MAX_SHIFTS}.`
                : 'Any active assignment they have on another shift will be ended today.'),
            'Assign Employees',
            'primary',
            { okText: 'Assign', cancelText: 'Cancel' }
        );

        if (!ok) return;

        confirmed = true;
        assignForm.submit();
    });
})();

async function releaseAssignment(assignmentId, employeeName) {
    const ok = await showConfirm(
        `Release ${employeeName} from this shift? They will fall back to the default shift. Past attendance records are unaffected.`,
        'Release Assignment',
        'danger',
        { okText: 'Release', cancelText: 'Cancel' }
    );

    if (!ok) return;

    const form = document.getElementById('releaseForm');
    form.action = `/general/settings/shifts/${SHIFT_ID}/assign/${assignmentId}`;
    form.submit();
}
</script>
@endpush

@extends('dashboard')

@section('title', $isEditing ? 'Edit Shift' : 'Add Shift')
@section('page-title', $isEditing ? 'Edit Shift' : 'Add Shift')
@section('page-subtitle', 'Working hours, lateness tolerance, and working days')

@section('content')
@php
    $dayLabels   = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
    $selectedDays = old('work_days', $shift->workDayNumbers() ?: [1, 2, 3, 4, 5]);
@endphp

<form method="POST" id="shiftForm"
      action="{{ $isEditing ? route('general.settings.shifts.update', $shift) : route('general.settings.shifts.store') }}"
      class="space-y-5">
    @csrf
    @if($isEditing) @method('PUT') @endif

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 rounded-xl p-4">
        <div class="flex items-start gap-2">
            <i class="fas fa-exclamation-circle text-red-500 mt-0.5"></i>
            <div>
                <p class="text-sm font-semibold text-red-800 mb-1">Please review the following:</p>
                <ul class="list-disc list-inside text-sm text-red-700 space-y-0.5">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        </div>
    </div>
    @endif

    <div class="bg-white rounded-xl p-6 shadow-sm">
        <div class="flex items-center justify-between mb-6 pb-4 border-b-2 border-gray-100">
            <h2 class="text-2xl font-bold text-gray-900">{{ $isEditing ? 'Edit Shift' : 'Add Shift' }}</h2>
            <a href="{{ route('general.settings.shifts.index') }}"
               class="px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">
                <i class="fas fa-arrow-left mr-1"></i> Back
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5">

            <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Shift Name <span class="text-red-500">*</span></label>
                <input type="text" name="name" id="fieldShiftName" value="{{ old('name', $shift->name) }}" required
                       placeholder="Regular Office"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Check-in Time <span class="text-red-500">*</span></label>
                <input type="time" name="check_in_time" id="fieldCheckIn" required
                       value="{{ old('check_in_time', substr((string) $shift->check_in_time, 0, 5)) }}"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Check-out Time <span class="text-red-500">*</span></label>
                <input type="time" name="check_out_time" id="fieldCheckOut" required
                       value="{{ old('check_out_time', substr((string) $shift->check_out_time, 0, 5)) }}"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Late Tolerance (minutes) <span class="text-red-500">*</span></label>
                <input type="number" name="late_tolerance_minutes" min="0" max="480" required
                       value="{{ old('late_tolerance_minutes', $shift->late_tolerance_minutes ?? 0) }}"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                <p class="text-xs text-gray-400 mt-1">Grace period after check-in time before an employee is marked late.</p>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Break Duration (minutes) <span class="text-red-500">*</span></label>
                <input type="number" name="break_minutes" min="0" max="480" required
                       value="{{ old('break_minutes', $shift->break_minutes ?? 60) }}"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                <p class="text-xs text-gray-400 mt-1">Deducted from total working time.</p>
            </div>

            <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Working Days <span class="text-red-500">*</span></label>
                <div class="flex flex-wrap gap-2">
                    @foreach($dayLabels as $number => $label)
                    <label class="flex items-center gap-2 px-3 py-2 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                        <input type="checkbox" name="work_days[]" value="{{ $number }}"
                               @checked(in_array($number, (array) $selectedDays))
                               class="w-4 h-4 rounded border-gray-300 text-red-800 focus:ring-red-800">
                        <span class="text-sm text-gray-700">{{ $label }}</span>
                    </label>
                    @endforeach
                </div>
            </div>

            <div class="md:col-span-2 space-y-3 pt-2">
                <label class="flex items-start gap-2 cursor-pointer">
                    <input type="checkbox" name="crosses_midnight" id="fieldCrossesMidnight" value="1"
                           @checked(old('crosses_midnight', $shift->crosses_midnight))
                           class="mt-0.5 w-4 h-4 rounded border-gray-300 text-red-800 focus:ring-red-800">
                    <span>
                        <span class="text-sm text-gray-700">Crosses midnight</span>
                        <span class="block text-xs text-gray-400">Tick this when the shift ends on the following day, e.g. 22:00 to 06:00.</span>
                    </span>
                </label>

                <label class="flex items-start gap-2 cursor-pointer">
                    <input type="checkbox" name="is_default" id="fieldIsDefault" value="1"
                           @checked(old('is_default', $shift->is_default))
                           class="mt-0.5 w-4 h-4 rounded border-gray-300 text-red-800 focus:ring-red-800">
                    <span>
                        <span class="text-sm text-gray-700">Set as default shift</span>
                        <span class="block text-xs text-gray-400">Used by every employee without an explicit assignment. Only one shift can be the default.</span>
                    </span>
                </label>

                <label class="flex items-start gap-2 cursor-pointer">
                    <input type="checkbox" name="is_active" id="fieldIsActive" value="1"
                           @checked(old('is_active', $shift->is_active ?? true))
                           class="mt-0.5 w-4 h-4 rounded border-gray-300 text-red-800 focus:ring-red-800">
                    <span>
                        <span class="text-sm text-gray-700">Active</span>
                        <span class="block text-xs text-gray-400">The default shift must stay active.</span>
                    </span>
                </label>
            </div>

            <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Notes</label>
                <textarea name="notes" rows="2"
                          placeholder="Optional description, e.g. applies to operations team only"
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">{{ old('notes', $shift->notes) }}</textarea>
            </div>
        </div>
    </div>

    <div class="flex items-center gap-3">
        <button type="submit"
                class="inline-flex items-center gap-2 px-5 py-2.5 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900 transition-all">
            <i class="fas fa-save"></i> Save
        </button>
        <a href="{{ route('general.settings.shifts.index') }}"
           class="px-5 py-2.5 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">
            Cancel
        </a>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    const IS_EDITING = @js((bool) $isEditing);
    const form = document.getElementById('shiftForm');
    let confirmed = false;

    // Konfirmasi sebelum simpan. Jam kerja menentukan perhitungan keterlambatan
    // seluruh karyawan yang memakai shift ini, jadi ringkasannya ditampilkan
    // sekali lagi — terutama saat shift ini ditandai sebagai default.
    form.addEventListener('submit', async function (event) {
        if (confirmed) return;
        event.preventDefault();

        if (!form.reportValidity()) return;

        const name      = document.getElementById('fieldShiftName').value.trim() || '(no name)';
        const checkIn   = document.getElementById('fieldCheckIn').value;
        const checkOut  = document.getElementById('fieldCheckOut').value;
        const tolerance = document.querySelector('input[name="late_tolerance_minutes"]').value;
        const isDefault = document.getElementById('fieldIsDefault').checked;
        const days      = document.querySelectorAll('input[name="work_days[]"]:checked').length;

        if (days === 0) {
            showToast('Select at least one working day.', 'warning');
            return;
        }

        const summary =
            `${name} — ${checkIn} to ${checkOut}, ${tolerance} minute(s) late tolerance, ${days} working day(s). ` +
            (isDefault
                ? 'This will become the default shift, replacing the current one and applying to every employee without an explicit assignment.'
                : 'Only employees explicitly assigned to this shift are affected.');

        const ok = await showConfirm(
            summary,
            IS_EDITING ? 'Save changes to this shift?' : 'Add this shift?',
            'primary',
            { okText: IS_EDITING ? 'Save Changes' : 'Add Shift', cancelText: 'Review Again' }
        );

        if (!ok) return;

        confirmed = true;
        form.submit();
    });
})();
</script>
@endpush

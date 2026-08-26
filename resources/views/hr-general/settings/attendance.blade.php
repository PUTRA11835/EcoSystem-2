@extends('dashboard')

@section('title', 'Attendance Settings')
@section('page-title', 'Attendance Settings')
@section('page-subtitle', 'Company-wide rules for check-in, geofencing, and corrections')

@section('content')
<form method="POST" action="{{ route('general.settings.attendance.update') }}" id="settingsForm" class="space-y-5">
    @csrf

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

    {{-- ── Geofence ──────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl p-6 shadow-sm">
        <h2 class="text-xl font-bold text-gray-900 mb-1">Geofencing</h2>
        <p class="text-sm text-gray-500 mb-5">How location is checked when an employee records attendance.</p>

        <div class="space-y-5">
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Geofence Mode</label>
                <div class="space-y-2">
                    @foreach([
                        ['value' => 'off',     'title' => 'Off',      'desc' => 'Location is not checked at all.'],
                        ['value' => 'flag',    'title' => 'Flag',     'desc' => 'Attendance outside the radius is still recorded, then marked for HR review. Recommended.'],
                        ['value' => 'enforce', 'title' => 'Enforce',  'desc' => 'Attendance outside the radius is rejected.'],
                    ] as $mode)
                    <label class="flex items-start gap-3 p-3 border rounded-lg cursor-pointer hover:bg-gray-50 transition-colors
                                  {{ old('geofence_mode', $settings->geofence_mode) === $mode['value'] ? 'border-red-300 bg-red-50/40' : 'border-gray-200' }}">
                        <input type="radio" name="geofence_mode" value="{{ $mode['value'] }}" id="mode_{{ $mode['value'] }}"
                               @checked(old('geofence_mode', $settings->geofence_mode) === $mode['value'])
                               class="mt-0.5 w-4 h-4 border-gray-300 text-red-800 focus:ring-red-800">
                        <span>
                            <span class="block text-sm font-semibold text-gray-800">{{ $mode['title'] }}</span>
                            <span class="block text-xs text-gray-500">{{ $mode['desc'] }}</span>
                        </span>
                    </label>
                    @endforeach
                </div>

                {{-- Peringatan tetap: mode enforce memberi rasa aman yang keliru
                     bila diharapkan mencegah kecurangan. --}}
                <div id="enforceWarning" class="mt-3 hidden flex items-start gap-2 text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-lg p-3">
                    <i class="fas fa-triangle-exclamation text-amber-500 mt-0.5"></i>
                    <div>
                        Browsers cannot detect a faked location. Enforce mode rejects attendance outside the radius,
                        but it does <strong>not</strong> stop anyone determined to spoof their position — while it does
                        block honest employees whose indoor GPS drifts by 20–100 m.
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-100 pt-5">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" name="require_location" value="1" id="requireLocation"
                           @checked(old('require_location', $settings->require_location))
                           class="mt-0.5 w-4 h-4 rounded border-gray-300 text-red-800 focus:ring-red-800">
                    <span>
                        <span class="block text-sm font-semibold text-gray-800">Require location to record attendance</span>
                        <span class="block text-xs text-gray-500">
                            When on, attendance is rejected if the device cannot provide coordinates.
                        </span>
                    </span>
                </label>

                <div id="requireWarning" class="mt-3 hidden flex items-start gap-2 text-xs text-red-800 bg-red-50 border border-red-200 rounded-lg p-3">
                    <i class="fas fa-circle-exclamation text-red-500 mt-0.5"></i>
                    <div>
                        <strong>This can block attendance entirely.</strong> Employees whose operating system blocks
                        the browser from using location, who are in a basement with no signal, or who open the app over
                        a plain http:// address will not be able to check in at all — they will have to submit a
                        correction for every single day. Turn this off if you are seeing widespread failures.
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-100 pt-5">
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Accuracy Threshold (meters)</label>
                <input type="number" name="min_accuracy_meters" min="10" max="5000" required
                       value="{{ old('min_accuracy_meters', $settings->min_accuracy_meters) }}"
                       class="w-full md:w-64 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                <p class="text-xs text-gray-400 mt-1">
                    Readings less accurate than this are flagged for review, but never rejected.
                    Indoor readings commonly drift 20–100 m.
                </p>
            </div>
        </div>
    </div>

    {{-- ── Sumber & jam kerja ────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl p-6 shadow-sm">
        <h2 class="text-xl font-bold text-gray-900 mb-1">Source &amp; Default Hours</h2>
        <p class="text-sm text-gray-500 mb-5">
            Default hours apply only to employees without any shift assignment and without a default shift.
        </p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5">
            <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Maximum Active Shifts per Employee</label>
                <input type="number" name="max_shifts_per_employee" min="1" max="10" required
                       value="{{ old('max_shifts_per_employee', $settings->max_shifts_per_employee) }}"
                       class="w-full md:w-64 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                <p class="text-xs text-gray-400 mt-1">
                    With 1, assigning a new shift ends the previous one. With 2 or more, an employee may hold several
                    shifts at once — when they check in, the shift whose start time is closest to the punch is used.
                </p>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Default Check-in</label>
                <input type="time" name="default_check_in" required
                       value="{{ old('default_check_in', substr((string) $settings->default_check_in, 0, 5)) }}"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Default Check-out</label>
                <input type="time" name="default_check_out" required
                       value="{{ old('default_check_out', substr((string) $settings->default_check_out, 0, 5)) }}"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Default Late Tolerance (minutes)</label>
                <input type="number" name="default_late_tolerance_minutes" min="0" max="480" required
                       value="{{ old('default_late_tolerance_minutes', $settings->default_late_tolerance_minutes) }}"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Auto-close Hanging Punch (hours)</label>
                <input type="number" name="auto_close_hours" min="1" max="48" required
                       value="{{ old('auto_close_hours', $settings->auto_close_hours) }}"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                <p class="text-xs text-gray-400 mt-1">Used by the scheduled task that closes forgotten check-outs.</p>
            </div>
        </div>
    </div>

    {{-- ── Koreksi ───────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl p-6 shadow-sm">
        <h2 class="text-xl font-bold text-gray-900 mb-1">Corrections</h2>
        <p class="text-sm text-gray-500 mb-5">Check-in and check-out are recorded once per day; corrections are the only way to change a time.</p>

        <div class="space-y-5">
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="checkbox" name="allow_self_correction" value="1"
                       @checked(old('allow_self_correction', $settings->allow_self_correction))
                       class="mt-0.5 w-4 h-4 rounded border-gray-300 text-red-800 focus:ring-red-800">
                <span>
                    <span class="block text-sm font-semibold text-gray-800">Let employees submit their own corrections</span>
                    <span class="block text-xs text-gray-500">When off, only HR can adjust attendance times.</span>
                </span>
            </label>

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Correction Window (days)</label>
                <input type="number" name="correction_max_days" min="1" max="365" required
                       value="{{ old('correction_max_days', $settings->correction_max_days) }}"
                       class="w-full md:w-64 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                <p class="text-xs text-gray-400 mt-1">How far back an employee may request a correction.</p>
            </div>
        </div>
    </div>

    {{-- ── Master sumber presensi ─────────────────────────────────────────
         Berada DI LUAR form pengaturan karena tiap baris punya aksinya sendiri.
         Kolom pada baris data dan pada baris "tambah" sengaja DIBUAT SAMA
         persis, supaya jelas bahwa keduanya mengisi hal yang sama. --}}
    <div class="bg-white rounded-xl p-6 shadow-sm">
        <h2 class="text-xl font-bold text-gray-900 mb-1">Attendance Sources</h2>
        <p class="text-sm text-gray-500 mb-5">
            Ways attendance can reach the system. Several may be active at once. Exactly one is marked as the
            <strong class="text-gray-700">web check-in</strong> source — that is what gets recorded when an employee
            presses the button on My Attendance.
        </p>

        <div class="border border-gray-200 rounded-lg overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                        <th class="px-3 py-3 min-w-44">Name</th>
                        <th class="px-3 py-3 min-w-64">Description</th>
                        <th class="px-3 py-3">Code</th>
                        <th class="px-3 py-3 w-24" title="Controls the order these sources are listed in">Order</th>
                        <th class="px-3 py-3 text-center w-20">Active</th>
                        <th class="px-3 py-3 text-center w-24">Web check-in</th>
                        <th class="px-3 py-3 text-center w-32">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($sources as $source)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <form method="POST" action="{{ route('general.settings.sources.update', $source) }}" id="srcForm{{ $source->id }}">
                            @csrf
                        </form>

                        <td class="px-3 py-3">
                            <input type="text" name="name" form="srcForm{{ $source->id }}" required maxlength="100"
                                   value="{{ $source->name }}"
                                   class="w-full px-2 py-1.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                        </td>
                        <td class="px-3 py-3">
                            <input type="text" name="description" form="srcForm{{ $source->id }}" maxlength="255"
                                   value="{{ $source->description }}" placeholder="Optional"
                                   class="w-full px-2 py-1.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                        </td>
                        <td class="px-3 py-3">
                            <span class="font-mono text-xs text-gray-500">{{ $source->code }}</span>
                            @if($source->is_builtin)
                                <span class="block text-[10px] text-gray-400 mt-0.5">built-in</span>
                            @endif
                        </td>
                        <td class="px-3 py-3">
                            <input type="number" name="sort_order" form="srcForm{{ $source->id }}" min="1" max="999"
                                   value="{{ $source->sort_order }}"
                                   class="w-20 px-2 py-1.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                        </td>
                        <td class="px-3 py-3 text-center">
                            <input type="checkbox" name="is_active" value="1" form="srcForm{{ $source->id }}"
                                   @checked($source->is_active)
                                   class="w-4 h-4 rounded border-gray-300 text-red-800 focus:ring-red-800">
                        </td>
                        <td class="px-3 py-3 text-center">
                            <input type="radio" name="web_source" value="{{ $source->id }}"
                                   @checked($source->is_web_checkin)
                                   onchange="markWebSource({{ $source->id }})"
                                   class="w-4 h-4 border-gray-300 text-red-800 focus:ring-red-800">
                            <input type="hidden" name="is_web_checkin" value="{{ $source->is_web_checkin ? 1 : 0 }}"
                                   form="srcForm{{ $source->id }}" id="webFlag{{ $source->id }}">
                        </td>
                        <td class="px-3 py-3">
                            <div class="flex items-center justify-center gap-1.5">
                                <button type="submit" form="srcForm{{ $source->id }}" title="Save changes"
                                        class="px-2.5 py-1.5 bg-gray-800 text-white text-xs font-semibold rounded hover:bg-gray-900 transition-all">
                                    Save
                                </button>
                                @if($source->is_builtin)
                                    <span class="w-8 h-7 flex items-center justify-center text-gray-300" title="Built-in sources cannot be deleted">
                                        <i class="fas fa-lock text-xs"></i>
                                    </span>
                                @else
                                    <button type="button" title="Delete"
                                            onclick="deleteSource({{ $source->id }}, @js($source->name))"
                                            class="w-8 h-7 flex items-center justify-center rounded border border-red-200 text-red-600 hover:bg-red-50 transition-all">
                                        <i class="fas fa-trash text-xs"></i>
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach

                    {{-- Baris tambah: kolomnya SAMA dengan baris data di atas. --}}
                    <tr class="bg-gray-50">
                        <form method="POST" action="{{ route('general.settings.sources.store') }}" id="srcCreateForm">
                            @csrf
                        </form>
                        <td class="px-3 py-3">
                            <input type="text" name="name" form="srcCreateForm" required maxlength="100"
                                   placeholder="Mobile App"
                                   class="w-full px-2 py-1.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                        </td>
                        <td class="px-3 py-3">
                            <input type="text" name="description" form="srcCreateForm" maxlength="255"
                                   placeholder="Employees check in from the company mobile app"
                                   class="w-full px-2 py-1.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                        </td>
                        <td class="px-3 py-3">
                            <span class="text-xs text-gray-400 italic">auto-generated</span>
                        </td>
                        <td class="px-3 py-3">
                            <input type="number" name="sort_order" form="srcCreateForm" min="1" max="999" value="50"
                                   class="w-20 px-2 py-1.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                        </td>
                        <td class="px-3 py-3 text-center">
                            <input type="checkbox" name="is_active" value="1" form="srcCreateForm"
                                   class="w-4 h-4 rounded border-gray-300 text-red-800 focus:ring-red-800">
                        </td>
                        <td class="px-3 py-3 text-center">
                            <span class="text-xs text-gray-400">—</span>
                        </td>
                        <td class="px-3 py-3 text-center">
                            <button type="submit" form="srcCreateForm"
                                    class="px-3 py-1.5 bg-red-800 text-white text-xs font-semibold rounded hover:bg-red-900 transition-all whitespace-nowrap">
                                <i class="fas fa-plus mr-1"></i> Add
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-4 flex items-start gap-2 text-xs text-gray-500 bg-blue-50 border border-blue-100 rounded-lg p-3">
            <i class="fas fa-info-circle text-blue-400 mt-0.5"></i>
            <div class="space-y-1">
                <p><strong class="text-gray-700">Order</strong> controls only the sequence sources are listed in, here and on My Attendance. Lower numbers appear first.</p>
                <p><strong class="text-gray-700">Code</strong> is generated from the name when the source is created and can never change, because every attendance record stores it. Renaming a source is safe; its code stays the same.</p>
                <p><strong class="text-gray-700">Built-in</strong> sources can be renamed and deactivated, but never deleted. Any source already referenced by attendance records is also protected from deletion.</p>
            </div>
        </div>
    </div>

    <form id="deleteSourceForm" method="POST" class="hidden">
        @csrf
    </form>

    <div class="flex items-center gap-3">
        <button type="submit"
                class="inline-flex items-center gap-2 px-5 py-2.5 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900 transition-all">
            <i class="fas fa-save"></i> Save Settings
        </button>
        <a href="{{ route('general.attendance.daily') }}"
           class="px-5 py-2.5 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">
            Cancel
        </a>
    </div>
</form>
@endsection

@push('scripts')
<script>
// Penanda web check-in bersifat eksklusif: memilih satu harus melepas yang
// lain. Radio hanya mengatur tampilan; nilai yang dikirim ada di input hidden
// tiap baris, karena setiap baris adalah form tersendiri.
function markWebSource(selectedId) {
    document.querySelectorAll('input[id^="webFlag"]').forEach((input) => {
        input.value = input.id === 'webFlag' + selectedId ? 1 : 0;
    });
    showToast('Press Save on that row to apply the web check-in source.', 'info');
}

async function deleteSource(id, name) {
    const ok = await showConfirm(
        `Delete the attendance source "${name}"? This cannot be undone.`,
        'Delete Attendance Source',
        'danger',
        { okText: 'Delete', cancelText: 'Cancel' }
    );

    if (!ok) return;

    const form = document.getElementById('deleteSourceForm');
    form.action = `/general/settings/attendance-sources/${id}/delete`;
    form.submit();
}
</script>
<script>
(function () {
    'use strict';

    const enforceRadio    = document.getElementById('mode_enforce');
    const enforceWarning  = document.getElementById('enforceWarning');
    const requireLocation = document.getElementById('requireLocation');
    const requireWarning  = document.getElementById('requireWarning');
    const form            = document.getElementById('settingsForm');

    function syncWarnings() {
        enforceWarning.classList.toggle('hidden', !enforceRadio.checked);
        // Peringatan hanya relevan saat opsinya MENYALA — itulah keadaan yang
        // dapat memblokir presensi.
        requireWarning.classList.toggle('hidden', !requireLocation.checked);
    }

    document.querySelectorAll('input[name="geofence_mode"]').forEach((radio) => {
        radio.addEventListener('change', syncWarnings);
    });
    requireLocation.addEventListener('change', syncWarnings);
    syncWarnings();

    let confirmed = false;

    form.addEventListener('submit', async function (event) {
        if (confirmed) return;
        event.preventDefault();

        if (!form.reportValidity()) return;

        const mode = document.querySelector('input[name="geofence_mode"]:checked').value;
        const requiresLocation = requireLocation.checked;

        const summary =
            `Geofence mode: ${mode}. Location ${requiresLocation ? 'REQUIRED' : 'optional'} for attendance. `
            + (mode === 'enforce' || requiresLocation
                ? 'These settings can prevent employees from recording attendance — make sure that is intended.'
                : 'These settings apply to every employee company-wide.');

        const ok = await showConfirm(
            summary,
            'Save Attendance Settings',
            'primary',
            { okText: 'Save', cancelText: 'Review Again' }
        );

        if (!ok) return;

        confirmed = true;
        form.submit();
    });
})();
</script>
@endpush

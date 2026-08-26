@extends('dashboard')

@section('title', 'My Attendance')
@section('page-title', 'My Attendance')
@section('page-subtitle', 'Record your check-in and check-out, and review your attendance history')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
@endpush

@section('content')
@php
    /** Ubah menit menjadi "7 h 30 m" — rekap ini dibaca manusia, bukan mesin. */
    $duration = function (int $minutes): string {
        if ($minutes <= 0) return '0 m';
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        return trim(($h > 0 ? "{$h} h " : '') . ($m > 0 ? "{$m} m" : ''));
    };

    $basic      = $employee?->basicData;
    $hasCheckIn = (bool) $record?->check_in_at;
    $hasCheckOut= (bool) $record?->check_out_at;
@endphp

<div class="space-y-5">

    {{-- ── Kartu identitas ────────────────────────────────────────────── --}}
    {{-- Warna kartu mengikuti sidebar: kelas `primary-surface` didefinisikan di
         layout dan berakar pada variabel yang sama dengan sidebar, sehingga
         mengganti Accent color atau Sidebar style di Settings langsung terlihat
         di sini juga. --}}
    <div class="primary-surface rounded-xl p-6 shadow-sm text-white">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div class="min-w-0">
                <p class="text-xs text-white text-opacity-60 mb-1">
                    {{ $employee?->eci ?? '—' }}
                    @if($basic?->department) · {{ $basic->department }} @endif
                </p>
                <h2 class="text-2xl font-bold truncate">{{ $basic?->nick_name ?? 'Employee' }}</h2>
                <p class="text-sm text-white text-opacity-70 mt-0.5">
                    {{ $basic?->position ?: 'Position not set' }}, shift {{ $shift?->name ?? '–' }}
                </p>
                <p class="text-sm text-white text-opacity-70">
                    Active Project: {{ $activeProject ?: '–' }}
                </p>
            </div>
            <div class="sm:text-right shrink-0">
                <p class="text-xs text-white text-opacity-60">Today</p>
                <p class="text-xl font-bold">{{ $today->translatedFormat('d F Y') }}</p>
                <p class="text-xs text-white text-opacity-60 mt-0.5">
                    {{ $shift ? $shift->time_range : 'Shift not set' }}
                </p>
            </div>
        </div>
    </div>

    {{-- ── Kartu statistik ────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        @foreach([
            ['label' => 'Present This Month', 'value' => $summary['present'], 'id' => 'statPresent', 'hint' => 'days recorded'],
            ['label' => 'Late',               'value' => $summary['late'],    'id' => 'statLate',    'hint' => 'days late'],
            ['label' => 'Work Hours',         'value' => $duration($summary['work_minutes']),     'id' => 'statWork',     'hint' => 'this month'],
            ['label' => 'Overtime Hours',     'value' => $duration($summary['overtime_minutes']), 'id' => 'statOvertime', 'hint' => 'this month'],
        ] as $card)
        <div class="bg-white rounded-xl p-5 shadow-sm">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ $card['label'] }}</p>
            <p class="text-3xl font-bold text-gray-900 mt-1" id="{{ $card['id'] }}">{{ $card['value'] }}</p>
            <p class="text-xs text-gray-400 mt-0.5">{{ $card['hint'] }}</p>
        </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

        {{-- ── Panel presensi ─────────────────────────────────────────── --}}
        <div class="bg-white rounded-xl p-6 shadow-sm">
            <h3 class="text-lg font-bold text-gray-900">Check-in / Check-out</h3>
            <p class="text-sm text-gray-500 mb-5">Recorded from the account you are signed in with.</p>

            @php
                /**
                 * Label sumber dibaca PER SISI. Check-in dan check-out dapat
                 * berasal dari jalur berbeda — mengoreksi jam masuk saja tidak
                 * boleh membuat jam keluar ikut berlabel "Correction".
                 */
                $sideSourceLabel = function (?string $code): string {
                    if ($code === \App\Models\Attendance\AttendanceRecord::SOURCE_CORRECTION) {
                        return 'Correction';
                    }
                    if ($code === \App\Models\Attendance\AttendanceRecord::SOURCE_MANUAL_HR) {
                        return 'Manual HR';
                    }
                    return \App\Models\Attendance\AttendanceSource::labelFor(
                        $code ?? \App\Models\Attendance\AttendanceSource::webCheckinCode()
                    );
                };
            @endphp
            <div class="grid grid-cols-2 gap-4 mb-5">
                <div class="border border-gray-200 rounded-lg p-4">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Check-in Time</p>
                    <p class="text-2xl font-bold text-gray-900" id="displayCheckIn">{{ $record?->check_in_at?->format('H:i') ?? '–' }}</p>
                    @if($hasCheckIn)
                        @php $inLabel = $sideSourceLabel($record->check_in_source); @endphp
                        <span class="inline-block mt-1 px-2 py-0.5 text-xs font-semibold rounded {{ $inLabel === 'Correction' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700' }}">{{ $inLabel }}</span>
                    @endif
                </div>
                <div class="border border-gray-200 rounded-lg p-4">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Check-out Time</p>
                    <p class="text-2xl font-bold text-gray-900" id="displayCheckOut">{{ $record?->check_out_at?->format('H:i') ?? '–' }}</p>
                    @if($hasCheckOut)
                        @php $outLabel = $sideSourceLabel($record->check_out_source); @endphp
                        <span class="inline-block mt-1 px-2 py-0.5 text-xs font-semibold rounded {{ $outLabel === 'Correction' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700' }}">{{ $outLabel }}</span>
                    @endif
                </div>
            </div>

            {{-- Presensi hanya sekali sehari; setelah keduanya terisi, jalur
                 perbaikan satu-satunya adalah pengajuan koreksi di bawah. --}}
            @if($hasCheckIn && $hasCheckOut)
            <div class="flex items-start gap-2 text-xs text-gray-600 bg-gray-50 border border-gray-200 rounded-lg p-3 mb-4">
                <i class="fas fa-circle-check text-gray-400 mt-0.5"></i>
                <div>
                    Today's attendance is complete. Check-in and check-out are recorded once per day —
                    if any time needs fixing, submit an <strong class="text-gray-700">Attendance Correction</strong> below.
                </div>
            </div>
            @endif

            <div class="flex flex-col sm:flex-row gap-3 mb-4">
                <button type="button" id="btnCheckIn" @disabled($hasCheckIn)
                        class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-green-700 text-white text-sm font-semibold rounded-lg hover:bg-green-800 disabled:opacity-40 disabled:cursor-not-allowed transition-all">
                    <i class="fas fa-right-to-bracket"></i> Check-in
                </button>
                <button type="button" id="btnCheckOut" @disabled(!$hasCheckIn || $hasCheckOut)
                        class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900 disabled:opacity-40 disabled:cursor-not-allowed transition-all">
                    <i class="fas fa-right-from-bracket"></i> Check-out
                </button>
            </div>

            {{-- Alat diagnosa lokasi.
                 Ditaruh di halaman, bukan hanya di console, karena kegagalan
                 lokasi hampir selalu berasal dari pengaturan browser atau
                 sistem operasi PENGGUNA — dan tanpa data mentahnya, penyebabnya
                 hanya bisa ditebak. --}}
            <div class="mb-4">
                <button type="button" id="btnDiagnose"
                        class="inline-flex items-center gap-2 px-3 py-1.5 bg-white text-gray-700 text-xs font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">
                    <i class="fas fa-stethoscope"></i> Test location access
                </button>
                <span class="text-xs text-gray-400 ml-2">Checks what your browser reports, without recording attendance.</span>

                <div id="diagnosePanel" class="hidden mt-3 border border-gray-200 rounded-lg bg-gray-50 p-3">
                    <pre id="diagnoseOutput" class="text-xs text-gray-700 whitespace-pre-wrap break-all font-mono leading-relaxed"></pre>
                    <button type="button" id="btnCopyDiagnose"
                            class="mt-2 px-2.5 py-1 bg-gray-800 text-white text-xs font-semibold rounded hover:bg-gray-900 transition-all">
                        Copy result
                    </button>
                </div>
            </div>

            {{-- Pemberitahuan privasi. Tetap tampil, tidak dapat ditutup. --}}
            <p class="text-xs text-gray-500 leading-relaxed mb-4">
                When you check in or out, the system records your device GPS location, connection type, IP address,
                and browser details. <strong class="text-gray-700">Location is captured only at the moment you press
                the button</strong> — nothing is tracked in the background.
            </p>

            {{-- Badge status --}}
            <div class="flex flex-wrap gap-2 mb-4" id="statusBadges">
                @if(!$hasCheckIn)
                    <span class="px-2 py-1 text-xs font-semibold rounded bg-gray-100 text-gray-600">Not checked in</span>
                @elseif(!$hasCheckOut)
                    <span class="px-2 py-1 text-xs font-semibold rounded bg-blue-100 text-blue-700">Checked in</span>
                @else
                    <span class="px-2 py-1 text-xs font-semibold rounded bg-green-100 text-green-700">Completed</span>
                @endif

                <span class="px-2 py-1 text-xs font-semibold rounded {{ ($record?->late_minutes ?? 0) > 0 ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-600' }}">
                    Late {{ $record?->late_minutes ?? 0 }} m
                </span>
                <span class="px-2 py-1 text-xs font-semibold rounded bg-gray-100 text-gray-600">
                    Worked {{ $duration($record?->work_minutes ?? 0) }}
                </span>
            </div>

            {{-- Lokasi --}}
            @foreach([['check_in', 'Check-in'], ['check_out', 'Check-out']] as [$side, $label])
            @php
                $verdict = $record?->geofenceVerdict($side);
                $badgeClass = match (true) {
                    $verdict === null                          => 'bg-gray-100 text-gray-500',
                    str_starts_with($verdict, 'Inside')        => 'bg-green-100 text-green-700',
                    // Kuning, BUKAN merah: pada mode flag presensinya tetap sah,
                    // hanya perlu ditinjau. Warna merah membuat karyawan mengira
                    // dirinya bersalah dan menimbulkan pertanyaan yang tak perlu.
                    str_starts_with($verdict, 'Outside')       => 'bg-amber-100 text-amber-700',
                    default                                    => 'bg-gray-100 text-gray-500',
                };
            @endphp
            <div class="mb-3">
                <p class="text-sm font-semibold text-gray-700">{{ $label }} location:</p>
                <p class="text-xs text-gray-500 font-mono break-all">{{ $record?->locationSummary($side) ?: '–' }}</p>
                <span class="inline-block mt-1 px-2 py-0.5 text-xs font-semibold rounded {{ $badgeClass }}">
                    {{ $verdict ?? 'Geofence status unavailable' }}
                </span>
                @if($record?->{$side . '_latitude'})
                <button type="button"
                        onclick="showPunchMap({{ $record->{$side . '_latitude'} }}, {{ $record->{$side . '_longitude'} }}, @js($label . ' point'))"
                        class="block mt-1 text-xs text-blue-600 hover:text-blue-800 hover:underline">
                    View {{ strtolower($label) }} point on map
                </button>
                @endif
            </div>
            @endforeach
        </div>

        {{-- ── Sumber presensi + riwayat ──────────────────────────────── --}}
        <div class="space-y-5">

            <div class="bg-white rounded-xl p-6 shadow-sm">
                <div class="flex items-start justify-between gap-3 mb-4">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">Attendance Source</h3>
                        <p class="text-sm text-gray-500">Selected by the administrator in company settings.</p>
                    </div>
                    <button type="button" disabled
                            title="Fingerprint import is not available yet"
                            class="px-3 py-1.5 text-xs font-semibold text-gray-400 border border-gray-200 rounded-lg cursor-not-allowed whitespace-nowrap">
                        <i class="fas fa-upload mr-1"></i> Import Fingerprint
                    </button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @forelse($sources as $source)
                    <div class="border rounded-lg p-3 {{ $source->is_active ? 'border-green-200 bg-green-50' : 'border-gray-200' }}">
                        <p class="text-sm font-semibold {{ $source->is_active ? 'text-green-800' : 'text-gray-700' }}">
                            {{ $source->name }}
                            @if($source->is_web_checkin)
                                <span class="ml-1 px-1.5 py-0.5 text-[10px] font-semibold rounded bg-blue-100 text-blue-700 align-middle">Web check-in</span>
                            @endif
                        </p>
                        <p class="text-xs text-gray-500 mt-0.5">{{ $source->description ?: '—' }}</p>
                        <p class="text-xs font-semibold mt-1 {{ $source->is_active ? 'text-green-700' : 'text-gray-400' }}">
                            {{ $source->is_active ? 'Currently active.' : 'Currently inactive.' }}
                        </p>
                    </div>
                    @empty
                    <p class="text-sm text-gray-400">No attendance source configured yet.</p>
                    @endforelse
                </div>
            </div>

            <div class="bg-white rounded-xl p-6 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-900">30-Day History</h3>
                    <span class="text-xs text-gray-400">{{ $history->count() }} record(s)</span>
                </div>

                <div class="border border-gray-200 rounded-lg overflow-x-auto max-h-96 overflow-y-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 border-b border-gray-200 sticky top-0">
                            <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                <th class="px-3 py-2.5 w-10">No</th>
                                <th class="px-3 py-2.5">Date</th>
                                <th class="px-3 py-2.5">In</th>
                                <th class="px-3 py-2.5">Out</th>
                                <th class="px-3 py-2.5">Work</th>
                                <th class="px-3 py-2.5">Method</th>
                                <th class="px-3 py-2.5">Location</th>
                                <th class="px-3 py-2.5">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($history as $index => $row)
                            @php $rowVerdict = $row->geofenceVerdict('check_in'); @endphp
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-3 py-2.5 text-gray-400">{{ $index + 1 }}</td>
                                <td class="px-3 py-2.5 text-gray-700 whitespace-nowrap">{{ $row->attendance_date->format('d M Y') }}</td>
                                <td class="px-3 py-2.5 font-mono text-xs text-gray-700">{{ $row->check_in_at?->format('H:i') ?? '–' }}</td>
                                <td class="px-3 py-2.5 font-mono text-xs text-gray-700">{{ $row->check_out_at?->format('H:i') ?? '–' }}</td>
                                <td class="px-3 py-2.5 text-xs text-gray-600 whitespace-nowrap">{{ $duration($row->work_minutes) }}</td>
                                <td class="px-3 py-2.5">
                                    @php
                                        // Ditampilkan per sisi supaya baris yang hanya
                                        // dikoreksi sebagian tidak terlihat seolah
                                        // seluruhnya hasil koreksi.
                                        $inLbl  = $row->check_in_at  ? $sideSourceLabel($row->check_in_source)  : null;
                                        $outLbl = $row->check_out_at ? $sideSourceLabel($row->check_out_source) : null;
                                        $badge  = fn (string $l) => '<span class="inline-block px-2 py-0.5 text-xs font-semibold rounded '
                                            . ($l === 'Correction' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700')
                                            . ' whitespace-nowrap">' . e($l) . '</span>';
                                    @endphp
                                    @if($inLbl && $outLbl && $inLbl !== $outLbl)
                                        <span class="block">In: {!! $badge($inLbl) !!}</span>
                                        <span class="block mt-1">Out: {!! $badge($outLbl) !!}</span>
                                    @elseif($inLbl || $outLbl)
                                        {!! $badge($inLbl ?? $outLbl) !!}
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-xs text-gray-500">
                                    {{ $row->checkInBranch?->name ?? $row->checkInProjectSite?->name ?? '–' }}
                                    @if($rowVerdict && str_starts_with($rowVerdict, 'Outside'))
                                        <span class="block text-amber-600">{{ $rowVerdict }}</span>
                                    @endif
                                    @if($row->check_in_latitude)
                                    <button type="button"
                                            onclick="showPunchMap({{ $row->check_in_latitude }}, {{ $row->check_in_longitude }}, @js($row->attendance_date->format('d M Y')))"
                                            class="text-blue-600 hover:text-blue-800 hover:underline">Map</button>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5">
                                    <span class="inline-block px-2 py-0.5 text-xs font-semibold rounded
                                        {{ $row->day_status === 'late' ? 'bg-amber-100 text-amber-700' : 'bg-green-100 text-green-700' }}">
                                        {{ ucfirst($row->day_status) }}
                                    </span>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="px-3 py-10 text-center text-gray-400">
                                    <i class="fas fa-calendar-xmark text-2xl mb-2 block"></i>
                                    <span class="text-sm font-medium">No attendance data yet.</span>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Pengajuan koreksi ──────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <div class="bg-white rounded-xl p-6 shadow-sm">
            <h3 class="text-lg font-bold text-gray-900">Attendance Correction</h3>
            <p class="text-sm text-gray-500 mb-5">
                Check-in and check-out are recorded once per day. Submit a correction if a time
                needs adjusting — HR will review it.
            </p>

            @if(!$settings->allow_self_correction)
            <div class="text-sm text-gray-500 bg-gray-50 border border-gray-200 rounded-lg p-4">
                Self-service corrections are currently disabled. Please contact HR directly.
            </div>
            @else
            <form method="POST" action="{{ route('general.my-attendance.correction.store') }}" id="correctionForm" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Date <span class="text-red-500">*</span></label>
                    <input type="date" name="attendance_date" required
                           value="{{ old('attendance_date', $today->toDateString()) }}"
                           min="{{ $today->copy()->subDays($settings->correction_max_days)->toDateString() }}"
                           max="{{ $today->toDateString() }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                    <p class="text-xs text-gray-400 mt-1">Up to {{ $settings->correction_max_days }} days back.</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">New Check-in Time</label>
                        <input type="time" name="requested_check_in" value="{{ old('requested_check_in') }}"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">New Check-out Time</label>
                        <input type="time" name="requested_check_out" value="{{ old('requested_check_out') }}"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                    </div>
                </div>
                <p class="text-xs text-gray-400 -mt-2">Fill in at least one of the two.</p>

                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Reason <span class="text-red-500">*</span></label>
                    <textarea name="reason" rows="3" required minlength="10" maxlength="1000"
                              placeholder="Explain what happened, e.g. forgot to check out after a client visit"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">{{ old('reason') }}</textarea>
                </div>

                <button type="submit"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900 transition-all">
                    <i class="fas fa-paper-plane"></i> Submit Correction
                </button>
            </form>
            @endif
        </div>

        {{-- Riwayat pengajuan --}}
        <div class="bg-white rounded-xl p-6 shadow-sm">
            <h3 class="text-lg font-bold text-gray-900 mb-4">My Correction Requests</h3>

            <div class="border border-gray-200 rounded-lg overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                            <th class="px-3 py-2.5">Date</th>
                            <th class="px-3 py-2.5">Requested</th>
                            <th class="px-3 py-2.5">Reason</th>
                            <th class="px-3 py-2.5">Status</th>
                            <th class="px-3 py-2.5"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($corrections as $correction)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-3 py-2.5 text-gray-700 whitespace-nowrap">{{ $correction->attendance_date->format('d M Y') }}</td>
                            <td class="px-3 py-2.5 font-mono text-xs text-gray-700 whitespace-nowrap">
                                In: {{ $correction->requested_check_in ? substr($correction->requested_check_in, 0, 5) : '–' }}<br>
                                Out: {{ $correction->requested_check_out ? substr($correction->requested_check_out, 0, 5) : '–' }}
                            </td>
                            <td class="px-3 py-2.5 text-xs text-gray-600 max-w-xs">
                                {{ \Illuminate\Support\Str::limit($correction->reason, 60) }}
                                @if($correction->hr_note)
                                    <span class="block text-gray-400 mt-0.5">HR: {{ \Illuminate\Support\Str::limit($correction->hr_note, 60) }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5">
                                @php
                                    $statusClass = match ($correction->status) {
                                        'approved'  => 'bg-green-100 text-green-700',
                                        'rejected'  => 'bg-red-100 text-red-700',
                                        'cancelled' => 'bg-gray-100 text-gray-500',
                                        default     => 'bg-amber-100 text-amber-700',
                                    };
                                @endphp
                                <span class="inline-block px-2 py-0.5 text-xs font-semibold rounded {{ $statusClass }}">
                                    {{ ucfirst($correction->status) }}
                                </span>
                            </td>
                            <td class="px-3 py-2.5 text-right">
                                @if($correction->isPending())
                                <button type="button"
                                        onclick="cancelCorrection({{ $correction->id }})"
                                        class="text-xs text-red-600 hover:text-red-800 hover:underline whitespace-nowrap">Cancel</button>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="px-3 py-10 text-center text-gray-400">
                                <i class="fas fa-inbox text-2xl mb-2 block"></i>
                                <span class="text-sm font-medium">No correction requests yet.</span>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@include('hr-general.attendance.partials.punch_map')

<form id="cancelCorrectionForm" method="POST" class="hidden">
    @csrf
</form>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function () {
    'use strict';

    const CSRF        = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const CHECKIN_URL = @js(route('general.my-attendance.check-in', [], false));
    const CHECKOUT_URL= @js(route('general.my-attendance.check-out', [], false));
    const TODAY_URL   = @js(route('general.my-attendance.today', [], false));

    const btnIn  = document.getElementById('btnCheckIn');
    const btnOut = document.getElementById('btnCheckOut');

    btnIn.addEventListener('click', () => punch('in'));
    btnOut.addEventListener('click', () => punch('out'));

    async function punch(kind) {
        const isCheckIn = kind === 'in';
        const button    = isCheckIn ? btnIn : btnOut;
        const original  = button.innerHTML;

        // Kunci KEDUA tombol selama proses. Mengunci satu saja tidak cukup:
        // klik ganda yang sangat cepat bisa mengirim dua permintaan sebelum
        // yang pertama selesai, dan meski UNIQUE (employee_id, tanggal) di
        // basis data mencegah baris kembar, pengguna akan melihat pesan galat
        // yang membingungkan.
        setBusy(true, button, 'Requesting location...');

        const position = await getPositionSafely(button);

        // 🔴 Jangan kirim apa pun sebelum pertanyaan izin lokasi benar-benar
        // dijawab. getPositionSafely() di bawah menunggu jawaban itu.
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

        try {
            const response = await fetch(isCheckIn ? CHECKIN_URL : CHECKOUT_URL, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CSRF,
                },
                body: JSON.stringify({
                    latitude:    position.latitude,
                    longitude:   position.longitude,
                    accuracy:    position.accuracy,
                    gps_status:  position.status,
                    connection:  connectionType(),
                    client_time: new Date().toISOString(),
                }),
            });

            const json = await response.json();

            showToast(json.message || (json.success ? 'Saved.' : 'Request failed.'),
                      json.success ? 'success' : 'error',
                      json.success ? 4000 : 8000);

            if (json.success) {
                await refreshToday();
                return;             // tombol diatur ulang oleh refreshToday()
            }
        } catch (error) {
            console.error('Attendance request failed', error);
            showToast('Could not reach the server. Check your connection and try again.', 'error', 8000);
        }

        button.innerHTML = original;
        setBusy(false);
    }

    /** Waktu tunggu maksimum SETELAH izin diberikan, saat perangkat mencari sinyal. */
    const FIX_TIMEOUT_MS    = 25000;
    /** Batas menunggu pengguna menjawab dialog izin, supaya tidak menggantung selamanya. */
    const ANSWER_TIMEOUT_MS = 120000;

    /**
     * Ambil lokasi. TIDAK PERNAH menolak promise — kegagalan dikembalikan
     * sebagai status, dan server yang memutuskan apakah presensi diterima.
     *
     * 🔴 PENTING — kenapa timeout dipisah dua:
     * Timeout pada getCurrentPosition() BERJALAN JUGA SELAMA DIALOG IZIN
     * MASIH TERBUKA. Dengan satu timeout pendek, pengguna yang butuh beberapa
     * detik untuk menekan "Allow" akan mendapat error TIMEOUT, dan presensinya
     * terkirim tanpa koordinat — persis bug yang dilaporkan.
     *
     * Karena itu: selama status izin masih 'prompt', permintaan dijalankan
     * TANPA timeout perangkat sehingga menunggu jawaban pengguna. Timeout
     * pendek hanya dipakai ketika izin sudah diberikan sebelumnya, yaitu saat
     * jeda memang murni proses pencarian sinyal.
     */
    async function getPositionSafely(button) {
        const empty = (status) => ({ latitude: null, longitude: null, accuracy: null, status });

        if (!navigator.geolocation) {
            return empty('gps_unsupported');
        }

        // Browser memblokir Geolocation di luar HTTPS dan localhost.
        if (!window.isSecureContext) {
            return empty('gps_insecure_context');
        }

        const state = await permissionState();

        if (state === 'denied') {
            return empty('gps_permission_denied');
        }

        if (state !== 'granted' && button) {
            // Beri tahu pengguna bahwa sistem sedang MENUNGGU jawabannya,
            // bukan sedang menggantung.
            button.innerHTML = '<i class="fas fa-location-crosshairs"></i> Waiting for permission...';
        }

        // ── Tahap 1: akurasi tinggi ──────────────────────────────────────────
        // Meminta GPS perangkat keras. Paling tepat, tetapi di komputer desktop
        // yang tidak punya modul GPS permintaan ini bisa gagal seluruhnya.
        const precise = await tryPosition({
            enableHighAccuracy: true,
            maximumAge: 0,
            timeoutMs: state === 'granted' ? FIX_TIMEOUT_MS : null,
            answerTimeoutMs: state === 'granted' ? null : ANSWER_TIMEOUT_MS,
        });

        if (precise.status === 'gps_ok') {
            return precise;
        }

        // ── Tahap 2: akurasi rendah ──────────────────────────────────────────
        // 🔴 Inilah yang membuat presensi berhasil di laptop tanpa GPS.
        // Dengan enableHighAccuracy:false, browser memakai penentuan lokasi
        // berbasis Wi-Fi dan alamat IP — jauh lebih kasar (puluhan sampai
        // ratusan meter) tetapi hampir selalu tersedia. Tanpa tahap ini,
        // POSITION_UNAVAILABLE dari tahap 1 langsung menggagalkan presensi
        // meskipun izin sudah diberikan.
        if (button) {
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Retrying with network location...';
        }

        const approximate = await tryPosition({
            enableHighAccuracy: false,
            maximumAge: 60000,   // posisi berumur maks 1 menit boleh dipakai ulang
            timeoutMs: 20000,
            answerTimeoutMs: null,
        });

        if (approximate.status === 'gps_ok') {
            return approximate;
        }

        // ── Keduanya gagal: bedakan siapa yang menolak ───────────────────────
        // 🔴 Browser memakai kode galat YANG SAMA (PERMISSION_DENIED) untuk dua
        // keadaan yang sangat berbeda:
        //   a. situs ini yang diblokir oleh pengguna  -> perbaikannya di browser
        //   b. BROWSER-nya yang diblokir oleh sistem operasi -> perbaikannya di
        //      Pengaturan Windows, dan pengaturan situs sama sekali tidak
        //      berpengaruh
        // Kalau Permissions API menyatakan situs ini SUDAH 'granted' tetapi
        // permintaannya tetap ditolak, penyebabnya pasti (b). Tanpa pembedaan
        // ini, pesan yang tampil menyuruh pengguna mengizinkan sesuatu yang
        // sudah diizinkan — dan mereka terjebak tanpa jalan keluar.
        if (precise.status === 'gps_permission_denied' && state === 'granted') {
            return { latitude: null, longitude: null, accuracy: null, status: 'gps_system_denied' };
        }

        // Alasan dari percobaan PERTAMA paling menjelaskan penyebabnya;
        // percobaan kedua hampir selalu berakhir "unavailable".
        return precise;
    }

    /**
     * Satu percobaan pengambilan posisi. Tidak pernah menolak promise.
     *
     * `timeoutMs` diteruskan ke browser. `answerTimeoutMs` adalah pengaman di
     * sisi kita untuk kondisi "dialog izin dibiarkan tanpa dijawab" — dipisah
     * justru karena timeout browser ikut menghitung durasi dialog itu.
     */
    function tryPosition({ enableHighAccuracy, maximumAge, timeoutMs, answerTimeoutMs }) {
        return new Promise((resolve) => {
            let settled = false;
            const finish = (value) => { if (!settled) { settled = true; resolve(value); } };

            const options = { enableHighAccuracy, maximumAge };
            if (timeoutMs) options.timeout = timeoutMs;

            if (answerTimeoutMs) {
                setTimeout(() => finish({ latitude: null, longitude: null, accuracy: null, status: 'gps_timeout' }), answerTimeoutMs);
            }

            navigator.geolocation.getCurrentPosition(
                (pos) => finish({
                    latitude:  pos.coords.latitude,
                    longitude: pos.coords.longitude,
                    accuracy:  pos.coords.accuracy,
                    status:    'gps_ok',
                }),
                (err) => {
                    // Dicatat ke console supaya penyebab teknisnya dapat dilihat
                    // saat menelusuri masalah, tanpa membebani pesan di layar.
                    console.warn('Geolocation attempt failed', {
                        highAccuracy: enableHighAccuracy,
                        code: err.code,
                        message: err.message,
                    });

                    finish({
                        latitude: null, longitude: null, accuracy: null,
                        status: err.code === err.PERMISSION_DENIED ? 'gps_permission_denied'
                              : err.code === err.TIMEOUT           ? 'gps_timeout'
                              : 'gps_unavailable',
                    });
                },
                options
            );
        });
    }

    /** Status izin lokasi, atau null bila Permissions API tidak tersedia. */
    async function permissionState() {
        if (!navigator.permissions?.query) {
            return null;
        }

        try {
            const result = await navigator.permissions.query({ name: 'geolocation' });
            return result.state;
        } catch (error) {
            // Sebagian browser lama menolak nama 'geolocation'; perlakukan
            // seperti tidak diketahui dan tetap tanyakan lewat getCurrentPosition.
            return null;
        }
    }

    function connectionType() {
        const c = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
        return c?.effectiveType || c?.type || null;
    }

    function setBusy(busy, button, label) {
        btnIn.disabled  = busy || btnIn.dataset.done === '1';
        btnOut.disabled = busy || btnOut.dataset.done === '1';

        if (busy && button && label) {
            button.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${label}`;
        }
    }

    /** Segarkan kartu dan tombol dari keadaan sebenarnya di server. */
    async function refreshToday() {
        try {
            const response = await fetch(TODAY_URL, {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
            });
            const json = await response.json();
            const rec  = json?.data?.record;

            document.getElementById('displayCheckIn').textContent  = rec?.check_in_at  || '–';
            document.getElementById('displayCheckOut').textContent = rec?.check_out_at || '–';

            btnIn.dataset.done  = rec?.check_in_at  ? '1' : '0';
            btnOut.dataset.done = rec?.check_out_at ? '1' : '0';

            btnIn.disabled  = !!rec?.check_in_at;
            btnOut.disabled = !rec?.check_in_at || !!rec?.check_out_at;

            btnIn.innerHTML  = '<i class="fas fa-right-to-bracket"></i> Check-in';
            btnOut.innerHTML = '<i class="fas fa-right-from-bracket"></i> Check-out';

            // Riwayat dan badge dirakit di server; memuat ulang menjaga satu
            // sumber kebenaran alih-alih menduplikasi logikanya di JavaScript.
            setTimeout(() => window.location.reload(), 900);
        } catch (error) {
            console.warn('Could not refresh attendance state', error);
            window.location.reload();
        }
    }

    // Keadaan awal tombol, supaya setBusy() tahu mana yang memang sudah selesai.
    btnIn.dataset.done  = @js($hasCheckIn ? '1' : '0');
    btnOut.dataset.done = @js($hasCheckOut ? '1' : '0');

    // ── Konfirmasi pengajuan koreksi ─────────────────────────────────────────
    const correctionForm = document.getElementById('correctionForm');
    let correctionConfirmed = false;

    correctionForm?.addEventListener('submit', async function (event) {
        if (correctionConfirmed) return;
        event.preventDefault();

        if (!correctionForm.reportValidity()) return;

        const date = correctionForm.querySelector('input[name="attendance_date"]').value;
        const tIn  = correctionForm.querySelector('input[name="requested_check_in"]').value;
        const tOut = correctionForm.querySelector('input[name="requested_check_out"]').value;

        if (!tIn && !tOut) {
            showToast('Enter a new check-in time, a new check-out time, or both.', 'warning');
            return;
        }

        const ok = await showConfirm(
            `Submit a correction for ${date} requesting check-in ${tIn || '(unchanged)'} `
            + `and check-out ${tOut || '(unchanged)'}? HR will review it before your attendance is updated.`,
            'Submit Attendance Correction',
            'primary',
            { okText: 'Submit', cancelText: 'Review Again' }
        );

        if (!ok) return;

        correctionConfirmed = true;
        correctionForm.submit();
    });
})();

// ── Diagnosa akses lokasi ────────────────────────────────────────────────────
// Menjalankan pemeriksaan yang sama seperti presensi, tetapi TIDAK menyimpan
// apa pun. Hasilnya ditampilkan mentah supaya penyebab kegagalan dapat dilihat
// langsung, alih-alih ditebak dari pesan galat yang sudah diterjemahkan.
(function () {
    'use strict';

    const btn    = document.getElementById('btnDiagnose');
    const panel  = document.getElementById('diagnosePanel');
    const output = document.getElementById('diagnoseOutput');
    const copyBtn= document.getElementById('btnCopyDiagnose');

    if (!btn) return;

    btn.addEventListener('click', async function () {
        btn.disabled  = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing...';
        panel.classList.remove('hidden');
        output.textContent = 'Running checks...';

        const lines = [];
        const add = (label, value) => lines.push(String(label).padEnd(24) + ': ' + value);

        add('Page URL', window.location.origin);
        add('Secure context', window.isSecureContext ? 'yes' : 'NO — geolocation is blocked');
        add('Geolocation API', navigator.geolocation ? 'available' : 'NOT available');

        let state = 'unknown';
        try {
            if (navigator.permissions?.query) {
                state = (await navigator.permissions.query({ name: 'geolocation' })).state;
            } else {
                state = 'Permissions API not supported';
            }
        } catch (e) {
            state = 'query failed: ' + e.message;
        }
        add('Site permission', state);

        for (const highAccuracy of [true, false]) {
            const label = highAccuracy ? 'High accuracy attempt' : 'Network attempt';
            const result = await new Promise((resolve) => {
                if (!navigator.geolocation) return resolve('geolocation unavailable');
                navigator.geolocation.getCurrentPosition(
                    (pos) => resolve(`OK  lat=${pos.coords.latitude.toFixed(6)} lng=${pos.coords.longitude.toFixed(6)} accuracy=${Math.round(pos.coords.accuracy)}m`),
                    (err) => resolve(`FAILED  code=${err.code} (${['','PERMISSION_DENIED','POSITION_UNAVAILABLE','TIMEOUT'][err.code] || 'UNKNOWN'})  message="${err.message}"`),
                    { enableHighAccuracy: highAccuracy, timeout: 20000, maximumAge: highAccuracy ? 0 : 60000 }
                );
            });
            add(label, result);
        }

        lines.push('');
        lines.push('If both attempts show code=1 while the site permission says "granted",');
        lines.push('the operating system is blocking the browser, not this site.');
        lines.push('Windows: Settings > Privacy & security > Location, then fully restart the browser.');

        output.textContent = lines.join('\n');
        btn.disabled  = false;
        btn.innerHTML = '<i class="fas fa-stethoscope"></i> Test location access';
    });

    copyBtn?.addEventListener('click', function () {
        navigator.clipboard?.writeText(output.textContent)
            .then(() => showToast('Diagnostic result copied.', 'success'))
            .catch(() => showToast('Could not copy — select the text manually.', 'warning'));
    });
})();

async function cancelCorrection(id) {
    const ok = await showConfirm(
        'Cancel this correction request? You can submit a new one afterwards.',
        'Cancel Correction',
        'danger',
        { okText: 'Cancel Request', cancelText: 'Keep It' }
    );

    if (!ok) return;

    const form = document.getElementById('cancelCorrectionForm');
    form.action = `/general/my-attendance/correction/${id}/cancel`;
    form.submit();
}
</script>
@include('hr-general.attendance.partials.punch_map_script')
@endpush

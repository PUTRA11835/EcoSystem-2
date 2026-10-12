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

<div class="w-full space-y-6">

    {{-- ── Header halaman ──────────────────────────────────────────────── --}}
    {{-- Kartu putih bersih, selaras dengan Dashboard (bukan blok berwarna penuh): identitas di kiri, tanggal di kanan.
         "ECI · Department · Active Project" yang dulu bertumpuk di blok berwarna kini jadi chip kecil; Active Project
         hanya tampil bila ada isinya. --}}
    <section aria-label="Employee" class="rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
            <div class="flex min-w-0 items-center gap-4">
                <span class="primary-surface flex h-14 w-14 shrink-0 items-center justify-center rounded-full text-lg font-bold text-white shadow-sm">
                    {{ \App\Support\Initials::make($basic?->full_name ?: $basic?->nick_name, 'U') }}
                </span>
                <div class="min-w-0">
                    <h2 class="truncate text-xl font-bold leading-tight tracking-tight text-gray-900">{{ $basic?->full_name ?: ($basic?->nick_name ?? 'Employee') }}</h2>
                    <p class="mt-0.5 truncate text-sm text-gray-500">
                        {{ $basic?->position ?: 'Position not set' }}@if($basic?->department)<span class="mx-1 text-gray-300">&middot;</span>{{ $basic->department }}@endif
                    </p>
                    <div class="mt-2 flex flex-wrap items-center gap-1.5">
                        <span class="inline-flex items-center gap-1.5 rounded-md bg-gray-100 px-2 py-1 font-mono text-xs font-medium text-gray-700">
                            <i class="fas fa-id-badge text-[10px] text-gray-400"></i>{{ $employee?->eci ?? '—' }}
                        </span>
                        <span class="inline-flex items-center gap-1.5 rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700">
                            <i class="far fa-clock text-[10px] text-gray-400"></i>{{ $shift ? $shift->name . ' · ' . $shift->time_range : 'Shift not set' }}
                        </span>
                        @if($activeProject)
                        <span class="inline-flex items-center gap-1.5 rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700">
                            <i class="fas fa-diagram-project text-[10px] text-gray-400"></i>{{ $activeProject }}
                        </span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="sm:text-right">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Today</p>
                <p class="text-base font-semibold text-gray-900">{{ $today->translatedFormat('l, d F Y') }}</p>
            </div>
        </div>
    </section>

    {{-- ── Statistik bulan ini (satu kartu bersekat, seperti Dashboard) ──── --}}
    <section aria-label="This month">
        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-500">This month &middot; {{ $today->translatedFormat('F Y') }}</h3>
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="grid grid-cols-2 xl:grid-cols-4 divide-x divide-y divide-gray-100 xl:divide-y-0">
            @foreach([
                ['label' => 'Present This Month', 'value' => $summary['present'], 'id' => 'statPresent', 'hint' => 'days recorded'],
                ['label' => 'Late',               'value' => $summary['late'],    'id' => 'statLate',    'hint' => 'days late'],
                ['label' => 'Work Hours',         'value' => $duration($summary['work_minutes']),     'id' => 'statWork',     'hint' => 'this month'],
                ['label' => 'Overtime Hours',     'value' => $duration($summary['overtime_minutes']), 'id' => 'statOvertime', 'hint' => 'this month'],
            ] as $card)
            <div class="px-5 py-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $card['label'] }}</p>
                <p class="mt-1 text-2xl font-bold leading-none tabular-nums text-gray-900" id="{{ $card['id'] }}">{{ $card['value'] }}</p>
                <p class="mt-1 text-xs text-gray-500">{{ $card['hint'] }}</p>
            </div>
            @endforeach
        </div>
        </div>
    </section>

    <div class="grid grid-cols-1 items-start gap-6 xl:grid-cols-2">

        <div class="space-y-6">
        {{-- ── Panel presensi ─────────────────────────────────────────── --}}
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
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

                // Bahasa manusia untuk data teknis lokasi (kode mentah seperti "gps_ok" tak berguna bagi karyawan).
                $gpsLabels = [
                    'gps_ok'               => 'Location captured',
                    'gps_timeout'          => 'Location request timed out',
                    'gps_permission_denied'=> 'Location permission denied',
                    'gps_system_denied'    => 'Blocked by the operating system',
                    'gps_unsupported'      => 'Not supported by this browser',
                    'gps_insecure_context' => 'Needs a secure (HTTPS) connection',
                    'gps_unavailable'      => 'Location unavailable',
                ];
                // Mutu akurasi dibandingkan ambang di Attendance Settings (min_accuracy_meters).
                $accuracyQuality = function ($meters) use ($settings): ?array {
                    if ($meters === null) return null;
                    $limit = max(1, (int) $settings->min_accuracy_meters);
                    $m = (float) $meters;
                    return $m <= $limit * 0.5 ? ['Good', 'text-emerald-700'] : ($m <= $limit ? ['Fair', 'text-amber-700'] : ['Low', 'text-amber-700']);
                };
            @endphp

            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">Check-in / Check-out</h3>
                    <p class="text-xs text-gray-500">Recorded from the account you are signed in with.</p>
                </div>
                {{-- Badge status (id dipertahankan) --}}
                <div class="flex flex-wrap gap-1.5" id="statusBadges">
                    @if(!$hasCheckIn)
                        <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600">Not checked in</span>
                    @elseif(!$hasCheckOut)
                        <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">Checked in</span>
                    @else
                        <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">Completed</span>
                    @endif
                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ ($record?->late_minutes ?? 0) > 0 ? 'bg-amber-50 text-amber-700' : 'bg-gray-100 text-gray-600' }}">
                        Late {{ $record?->late_minutes ?? 0 }} m
                    </span>
                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600">
                        Worked {{ $duration($record?->work_minutes ?? 0) }}
                    </span>
                </div>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-3">
                <div class="rounded-lg border border-gray-200 bg-gray-50/60 p-4">
                    <p class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <i class="fas fa-arrow-right-to-bracket text-[10px] text-emerald-600"></i> Check-in
                    </p>
                    <p class="mt-1.5 text-2xl font-semibold leading-none tabular-nums text-gray-900" id="displayCheckIn">{{ $record?->check_in_at?->format('H:i') ?? '–' }}</p>
                    @if($hasCheckIn)
                        @php $inLabel = $sideSourceLabel($record->check_in_source); @endphp
                        <span class="mt-2 inline-block rounded px-2 py-0.5 text-xs font-semibold {{ $inLabel === 'Correction' ? 'bg-purple-50 text-purple-700' : 'bg-blue-50 text-blue-700' }}">{{ $inLabel }}</span>
                    @endif
                </div>
                <div class="rounded-lg border border-gray-200 bg-gray-50/60 p-4">
                    <p class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <i class="fas fa-arrow-right-from-bracket text-[10px] text-rose-500"></i> Check-out
                    </p>
                    <p class="mt-1.5 text-2xl font-semibold leading-none tabular-nums text-gray-900" id="displayCheckOut">{{ $record?->check_out_at?->format('H:i') ?? '–' }}</p>
                    @if($hasCheckOut)
                        @php $outLabel = $sideSourceLabel($record->check_out_source); @endphp
                        <span class="mt-2 inline-block rounded px-2 py-0.5 text-xs font-semibold {{ $outLabel === 'Correction' ? 'bg-purple-50 text-purple-700' : 'bg-blue-50 text-blue-700' }}">{{ $outLabel }}</span>
                    @endif
                </div>
            </div>

            {{-- Presensi hanya sekali sehari; setelah keduanya terisi, jalur
                 perbaikan satu-satunya adalah pengajuan koreksi di bawah. --}}
            @if($hasCheckIn && $hasCheckOut)
            <div class="mt-4 flex items-start gap-2 rounded-lg border border-gray-200 bg-gray-50 p-3 text-xs text-gray-600">
                <i class="fas fa-circle-check mt-0.5 text-emerald-500"></i>
                <div>
                    Today's attendance is complete. Check-in and check-out are recorded once per day —
                    if any time needs fixing, submit an <strong class="text-gray-700">Attendance Correction</strong> below.
                </div>
            </div>
            @endif

            <div class="mt-4 flex flex-col gap-3 sm:flex-row">
                <button type="button" id="btnCheckIn" @disabled($hasCheckIn)
                        class="primary-surface flex-1 inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-40">
                    <i class="fas fa-right-to-bracket"></i> Check-in
                </button>
                <button type="button" id="btnCheckOut" @disabled(!$hasCheckIn || $hasCheckOut)
                        class="flex-1 inline-flex items-center justify-center gap-2 rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-black disabled:cursor-not-allowed disabled:opacity-40">
                    <i class="fas fa-right-from-bracket"></i> Check-out
                </button>
            </div>

            {{-- Alat diagnosa lokasi.
                 Ditaruh di halaman, bukan hanya di console, karena kegagalan
                 lokasi hampir selalu berasal dari pengaturan browser atau
                 sistem operasi PENGGUNA — dan tanpa data mentahnya, penyebabnya
                 hanya bisa ditebak.

                 Tampil-atau-tidaknya diatur HR lewat Attendance Settings →
                 "Show the Test location access tool" (D173). Skrip di bawah
                 sudah berhenti sendiri bila tombolnya tidak dirender
                 (`if (!btn) return;`), jadi menyembunyikan markup-nya cukup. --}}
            @if($settings->show_location_diagnostic)
            <div class="mt-3">
                <button type="button" id="btnDiagnose"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-50">
                    <i class="fas fa-stethoscope"></i> Test location access
                </button>
                <span class="ml-2 text-xs text-gray-500">Checks what your browser reports, without recording attendance.</span>

                <div id="diagnosePanel" class="mt-3 hidden rounded-lg border border-gray-200 bg-gray-50 p-3">
                    <pre id="diagnoseOutput" class="whitespace-pre-wrap break-all font-mono text-xs leading-relaxed text-gray-700"></pre>
                    <button type="button" id="btnCopyDiagnose"
                            class="mt-2 rounded bg-gray-800 px-2.5 py-1 text-xs font-semibold text-white transition hover:bg-gray-900">
                        Copy result
                    </button>
                </div>
            </div>
            @endif

            {{-- Pemberitahuan privasi. Tetap tampil, tidak dapat ditutup. --}}
            <p class="mt-4 flex items-start gap-2 text-xs leading-relaxed text-gray-500">
                <i class="fas fa-shield-halved mt-0.5 text-gray-400"></i>
                <span>
                    When you check in or out, the system records your device location, connection type, IP address, and
                    browser details. <strong class="text-gray-700">Location is captured only at the moment you press the
                    button</strong> — nothing is tracked in the background.
                </span>
            </p>

            {{-- Detail lokasi per sisi: ringkas dan mudah dibaca; data teknis (IP, koordinat, kode status) dilipat. --}}
            <div class="mt-5 grid grid-cols-1 gap-3 border-t border-gray-100 pt-5 sm:grid-cols-2">
                @foreach([['check_in', 'Check-in'], ['check_out', 'Check-out']] as [$side, $label])
                @php
                    $punched = (bool) $record?->{$side . '_at'};
                    $verdict = $record?->geofenceVerdict($side);
                    $badgeClass = match (true) {
                        $verdict === null                          => 'bg-gray-100 text-gray-500',
                        str_starts_with($verdict, 'Inside')        => 'bg-emerald-50 text-emerald-700',
                        // Kuning, BUKAN merah: pada mode flag presensinya tetap sah,
                        // hanya perlu ditinjau. Warna merah membuat karyawan mengira
                        // dirinya bersalah dan menimbulkan pertanyaan yang tak perlu.
                        str_starts_with($verdict, 'Outside')       => 'bg-amber-50 text-amber-700',
                        default                                    => 'bg-gray-100 text-gray-600',
                    };
                    $lat   = $record?->{$side . '_latitude'};
                    $lng   = $record?->{$side . '_longitude'};
                    $acc   = $record?->{$side . '_accuracy_m'};
                    $q     = $accuracyQuality($acc);
                    $gps   = $record?->{$side . '_gps_status'};
                    $conn  = $record?->{$side . '_connection'};
                    $ip    = $record?->{$side . '_ip'};
                    $dev   = $record?->{$side . '_device'};
                    $note  = $record?->accuracyNote($side);
                @endphp
                <div class="rounded-lg border border-gray-200 p-4">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $label }} location</p>
                            @if($punched)
                            <span class="mt-1.5 inline-block rounded-full px-2.5 py-1 text-xs font-semibold {{ $badgeClass }}">{{ $verdict }}</span>
                            @else
                            <p class="mt-1.5 text-sm text-gray-400">Not recorded yet.</p>
                            @endif
                        </div>
                        @if($lat)
                        <button type="button"
                                onclick="showPunchMap({{ $lat }}, {{ $lng }}, @js($label . ' point'))"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-50">
                            <i class="fas fa-location-dot text-gray-400"></i> View on map
                        </button>
                        @endif
                    </div>

                    @if($punched)
                    <dl class="mt-3 grid grid-cols-1 gap-y-1.5 text-sm">
                        <div>
                            <dt class="text-xs text-gray-500">GPS accuracy</dt>
                            <dd class="font-medium text-gray-900">
                                @if($acc !== null)
                                    &plusmn;{{ (int) round((float) $acc) }} m <span class="text-xs font-semibold {{ $q[1] }}">{{ $q[0] }}</span>
                                @else
                                    <span class="text-gray-400">&ndash;</span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">Location</dt>
                            <dd class="font-medium text-gray-900">{{ $gpsLabels[$gps] ?? ($gps ?: '–') }}</dd>
                        </div>
                    </dl>
                    @if($note)
                    <p class="mt-2 flex items-center gap-1.5 text-xs text-amber-700"><i class="fas fa-triangle-exclamation"></i> {{ $note }}</p>
                    @endif

                    <details class="mt-3 group">
                        <summary class="cursor-pointer select-none text-xs font-medium text-gray-500 hover:text-gray-700">Technical details</summary>
                        <dl class="mt-2 grid grid-cols-1 gap-x-4 gap-y-1.5 rounded-lg bg-gray-50 p-3 text-xs sm:grid-cols-2">
                            <div><dt class="text-gray-500">IP address</dt><dd class="font-mono text-gray-800">{{ $ip ?: '–' }}</dd></div>
                            <div><dt class="text-gray-500">Network</dt><dd class="font-medium text-gray-800">{{ $conn ? strtoupper($conn) : '–' }}</dd></div>
                            <div class="sm:col-span-2"><dt class="text-gray-500">Coordinates</dt>
                                <dd class="font-mono text-gray-800">@if($lat !== null){{ number_format((float) $lat, 6, '.', '') }}, {{ number_format((float) $lng, 6, '.', '') }}@else&ndash;@endif</dd></div>
                            <div class="sm:col-span-2"><dt class="text-gray-500">Device</dt><dd class="font-medium text-gray-800">{{ $dev ?: '–' }}</dd></div>
                        </dl>
                    </details>
                    @endif
                </div>
                @endforeach
            </div>
        </div>

        {{-- Jarang dipakai -> dilipat agar halaman fokus ke aksi harian. Terbuka otomatis bila ada galat validasi / isian lama. --}}
        <details id="correctionDetails" class="group overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm" @if($errors->any() || old('reason')) open @endif>
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-4 sm:px-6">
                <span>
                    <span class="block text-sm font-semibold text-gray-900">Need to fix a time?</span>
                    <span class="block text-xs text-gray-500">Submit an attendance correction &mdash; HR will review it.</span>
                </span>
                <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform group-open:rotate-180"></i>
            </summary>
            <div class="border-t border-gray-100 p-5 sm:p-6">
            <p class="mb-5 text-xs text-gray-500">
                Check-in and check-out are recorded once per day. Submit a correction if a time
                needs adjusting.
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
                        class="inline-flex items-center gap-2 px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all">
                    <i class="fas fa-paper-plane"></i> Submit Correction
                </button>
            </form>
            @endif
            </div>
        </details>
        </div>

        <div class="space-y-6">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-semibold text-gray-900">30-Day History</h3>
                    <span class="text-xs text-gray-500">{{ $history->count() }} record(s)</span>
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
                                <td colspan="8" class="px-3 py-6 text-center text-gray-400">
                                    <i class="fas fa-calendar-xmark text-2xl mb-2 block"></i>
                                    <span class="text-sm font-medium">No attendance data yet.</span>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        {{-- Riwayat pengajuan --}}
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <h3 class="mb-4 text-sm font-semibold text-gray-900">My Correction Requests</h3>

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
                            <td colspan="5" class="px-3 py-6 text-center text-gray-400">
                                <i class="fas fa-inbox text-2xl mb-2 block"></i>
                                <span class="text-sm font-medium">No correction requests yet.</span>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

            {{-- Sumber presensi: informasi konfigurasi (diatur admin) — cukup satu baris, tak perlu kartu besar. --}}
            <div class="flex flex-wrap items-center gap-x-3 gap-y-2 rounded-xl border border-gray-200 bg-white px-5 py-3.5 shadow-sm">
                <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Attendance source</span>
                @forelse($sources->where('is_active', true) as $source)
                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700" title="{{ $source->description }}">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>{{ $source->name }}
                </span>
                @empty
                <span class="text-xs text-gray-500">No attendance source configured yet.</span>
                @endforelse
                <span class="text-xs text-gray-400">Selected by the administrator in company settings.</span>
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

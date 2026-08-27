@extends('dashboard')

@section('title', 'Attendance Recap')
@section('page-title', 'Attendance Recap')
@section('page-subtitle', "Monitor daily attendance with department, status, search, and Excel export filters")

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
@endpush

@section('content')
@php
    $duration = function (int $minutes): string {
        if ($minutes <= 0) return '0 m';
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        return trim(($h > 0 ? "{$h} h " : '') . ($m > 0 ? "{$m} m" : ''));
    };
@endphp

<div class="space-y-5">

    {{-- Header + aksi --}}
    <div class="bg-white rounded-xl p-6 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-5 pb-4 border-b-2 border-gray-100">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Attendance Recap</h2>
                <p class="text-sm text-gray-500 mt-0.5">Daily attendance for {{ $filters['date']->translatedFormat('d F Y') }}.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if($can('general.attendance.monthly'))
                <a href="{{ route('general.attendance.monthly') }}"
                   class="px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">
                    Monthly Recap
                </a>
                @endif
                @if($can('general.attendance.export'))
                <a href="{{ route('general.attendance.export', request()->query()) }}"
                   class="inline-flex items-center gap-2 px-4 py-2 bg-green-700 text-white text-sm font-semibold rounded-lg hover:bg-green-800 transition-all">
                    <i class="fas fa-download"></i> Export Excel
                </a>
                @endif
            </div>
        </div>

        {{-- Filter --}}
        <form method="GET" action="{{ route('general.attendance.daily') }}"
              class="bg-gray-50 border border-gray-200 rounded-lg p-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Date</label>
                    <input type="date" name="date" value="{{ $filters['date']->toDateString() }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Department</label>
                    <select name="department"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                        <option value="">All Departments</option>
                        @foreach($departments as $department)
                            <option value="{{ $department }}" @selected($filters['department'] === $department)>{{ $department }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Status</label>
                    <select name="status"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                        <option value="">All Statuses</option>
                        @foreach(['present' => 'Present', 'late' => 'Late', 'incomplete' => 'Incomplete'] as $value => $label)
                            <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Search</label>
                    <input type="text" name="search" value="{{ $filters['search'] }}"
                           placeholder="Name, employee code, position, or department"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="submit" class="px-4 py-2 bg-gray-800 text-white text-sm font-semibold rounded-lg hover:bg-gray-900 transition-all">Apply</button>
                <a href="{{ route('general.attendance.daily') }}"
                   class="px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">Reset</a>
                @if($can('general.attendance.correction'))
                <a href="{{ route('general.attendance.corrections.index') }}"
                   class="px-4 py-2 bg-white text-amber-700 text-sm font-semibold rounded-lg border border-amber-300 hover:bg-amber-50 transition-all">
                    Review Corrections
                </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Kartu ringkasan --}}
    <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
        @foreach([
            ['label' => 'Recorded',      'value' => $summary['recorded'], 'class' => 'text-gray-900'],
            ['label' => 'Present',       'value' => $summary['present'],  'class' => 'text-green-600'],
            ['label' => 'Late',          'value' => $summary['late'],     'class' => 'text-amber-600'],
            ['label' => 'Absent',        'value' => $summary['absent'],   'class' => 'text-red-600'],
            ['label' => 'Sick',          'value' => $summary['sick'],     'class' => 'text-teal-600'],
            ['label' => 'Leave / Permit','value' => $summary['leave'],    'class' => 'text-blue-600'],
        ] as $card)
        <div class="bg-white rounded-xl p-5 shadow-sm">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ $card['label'] }}</p>
            <p class="text-3xl font-bold mt-1 {{ $card['class'] }}">{{ $card['value'] }}</p>
        </div>
        @endforeach
    </div>

    {{-- Tabel --}}
    <div class="bg-white rounded-xl p-6 shadow-sm">
        <div class="border border-gray-200 rounded-lg overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">
                        <th class="px-3 py-3 w-10">No</th>
                        <th class="px-3 py-3">Date</th>
                        <th class="px-3 py-3">Employee</th>
                        <th class="px-3 py-3">Department</th>
                        <th class="px-3 py-3">Position</th>
                        <th class="px-3 py-3">Shift</th>
                        <th class="px-3 py-3">Check-in</th>
                        <th class="px-3 py-3">Check-out</th>
                        <th class="px-3 py-3 min-w-64">Check-in Location</th>
                        <th class="px-3 py-3 min-w-64">Check-out Location</th>
                        <th class="px-3 py-3 text-center">Attendance</th>
                        <th class="px-3 py-3 text-right">Late</th>
                        <th class="px-3 py-3 text-right">Early Leave</th>
                        <th class="px-3 py-3 text-right">Work</th>
                        <th class="px-3 py-3 text-right">Overtime</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($records as $index => $record)
                    @php
                        $basic = $record->employee?->basicData;
                        $hasFlags = !empty($record->flags);
                    @endphp
                    <tr class="hover:bg-gray-50 transition-colors align-top {{ $hasFlags ? 'bg-amber-50/40' : '' }}">
                        <td class="px-3 py-3 text-gray-400">{{ $index + 1 }}</td>
                        <td class="px-3 py-3 text-gray-700 whitespace-nowrap">
                            {{ $record->attendance_date->translatedFormat('d M Y') }}
                            <span class="block text-xs text-gray-400">{{ $record->attendance_date->format('Y-m-d') }}</span>
                        </td>
                        <td class="px-3 py-3 whitespace-nowrap">
                            <div class="font-medium text-gray-900">{{ $basic?->nick_name ?? '—' }}</div>
                            <div class="text-xs text-gray-400">{{ $record->employee?->eci }}</div>
                        </td>
                        <td class="px-3 py-3 text-gray-600 text-xs">{{ $basic?->department ?: '—' }}</td>
                        <td class="px-3 py-3 text-gray-600 text-xs">{{ $basic?->position ?: '—' }}</td>
                        <td class="px-3 py-3 text-gray-600 text-xs whitespace-nowrap">{{ $record->shift?->name ?? '–' }}</td>
                        <td class="px-3 py-3 font-mono text-xs text-gray-900">{{ $record->check_in_at?->format('H:i') ?? '–' }}</td>
                        <td class="px-3 py-3 font-mono text-xs text-gray-900">{{ $record->check_out_at?->format('H:i') ?? '–' }}</td>

                        @foreach(['check_in', 'check_out'] as $side)
                        @php
                            $verdict  = $record->geofenceVerdict($side);
                            $summaryText = $record->locationSummary($side);
                            $badgeClass = match (true) {
                                $verdict === null                    => 'bg-gray-100 text-gray-500',
                                str_starts_with($verdict, 'Inside')  => 'bg-green-100 text-green-700',
                                str_starts_with($verdict, 'Outside') => 'bg-amber-100 text-amber-700',
                                default                              => 'bg-gray-100 text-gray-500',
                            };
                        @endphp
                        <td class="px-3 py-3">
                            @if($record->{$side . '_at'})
                                <p class="text-xs text-gray-500 font-mono break-all leading-relaxed">{{ $summaryText ?: '–' }}</p>
                                <span class="inline-block mt-1 px-2 py-0.5 text-xs font-semibold rounded {{ $badgeClass }}">
                                    {{ $verdict }}
                                </span>
                                @if($record->{$side . '_latitude'})
                                <button type="button"
                                        onclick="showPunchMap({{ $record->{$side . '_latitude'} }}, {{ $record->{$side . '_longitude'} }}, @js(($basic?->nick_name ?? 'Employee') . ' — ' . str_replace('_', '-', $side)))"
                                        class="block mt-1 text-xs text-blue-600 hover:text-blue-800 hover:underline">View map</button>
                                @endif
                            @else
                                <span class="text-xs text-gray-400">–</span>
                            @endif
                        </td>
                        @endforeach

                        <td class="px-3 py-3 text-center">
                            <span class="inline-block px-2 py-0.5 text-xs font-semibold rounded whitespace-nowrap
                                {{ $record->day_status === 'late' ? 'bg-amber-100 text-amber-700' : 'bg-green-100 text-green-700' }}">
                                {{ ucfirst($record->day_status) }}
                            </span>
                            @if($record->source === 'correction')
                                <span class="block mt-1 text-xs text-purple-600">Corrected</span>
                            @endif
                        </td>
                        <td class="px-3 py-3 text-right text-xs whitespace-nowrap {{ $record->late_minutes > 0 ? 'text-amber-700 font-semibold' : 'text-gray-500' }}">{{ $record->late_minutes }} m</td>
                        <td class="px-3 py-3 text-right text-xs text-gray-500 whitespace-nowrap">{{ $record->early_leave_minutes }} m</td>
                        <td class="px-3 py-3 text-right text-xs text-gray-700 whitespace-nowrap">{{ $duration($record->work_minutes) }}</td>
                        <td class="px-3 py-3 text-right text-xs text-gray-700 whitespace-nowrap">{{ $duration($record->overtime_minutes) }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="15" class="px-4 py-12 text-center">
                            <div class="flex flex-col items-center gap-2 text-gray-400">
                                <i class="fas fa-calendar-xmark text-3xl"></i>
                                <p class="text-sm font-medium">No attendance recorded for this date.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4 flex items-start gap-2 text-xs text-gray-500 bg-blue-50 border border-blue-100 rounded-lg p-3">
            <i class="fas fa-info-circle text-blue-400 mt-0.5"></i>
            <div>
                Rows with a light amber background carry review flags such as low GPS accuracy or a location far
                outside the branch radius. <strong class="text-gray-700">Absent, Sick, and Leave counters stay at
                zero</strong> until the Leave module exists — this page reports who attended, not who was missing.
            </div>
        </div>
    </div>
</div>

@include('hr-general.attendance.partials.punch_map')
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
@include('hr-general.attendance.partials.punch_map_script')
@endpush

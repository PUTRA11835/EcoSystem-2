@extends('dashboard')

@section('title', 'Monthly Attendance Recap')
@section('page-title', 'Monthly Attendance Recap')
@section('page-subtitle', 'Compact monthly attendance matrix with quick filters and export')

@push('styles')
<style>
    /* Kolom No dan Employee tetap terlihat saat matriks 31 kolom digulir. */
    .recap-sticky { position: sticky; background: #fff; z-index: 2; }
    .recap-sticky-no   { left: 0;     width: 3rem; }
    .recap-sticky-name { left: 3rem;  min-width: 15rem; }
    thead .recap-sticky { z-index: 3; background: #f9fafb; }
</style>
@endpush

@section('content')
@php
    $month = $filters['month'];
@endphp

<div class="space-y-5">

    {{-- Header + aksi --}}
    <div class="bg-white rounded-xl p-6 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-5 pb-4 border-b-2 border-gray-100">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Monthly Attendance Recap</h2>
                <p class="text-sm text-gray-500 mt-0.5">One row per employee, one column per day.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('general.attendance.daily') }}"
                   class="px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">
                    Daily Recap
                </a>
                @if($can('general.attendance.export'))
                <a href="{{ route('general.attendance.monthly.export', request()->query()) }}"
                   class="inline-flex items-center gap-2 px-4 py-2 bg-green-700 text-white text-sm font-semibold rounded-lg hover:bg-green-800 transition-all">
                    <i class="fas fa-download"></i> Download Excel
                </a>
                @endif
            </div>
        </div>

        {{-- Filter --}}
        <form method="GET" action="{{ route('general.attendance.monthly') }}"
              class="bg-gray-50 border border-gray-200 rounded-lg p-4">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                <div class="md:col-span-5">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Employee Name</label>
                    <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Search name or employee code..."
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>
                <div class="md:col-span-3">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Month</label>
                    <input type="month" name="month" value="{{ $month->format('Y-m') }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Location</label>
                    <select name="branch"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                        <option value="">All Locations</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((string) $filters['branch'] === (string) $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-2 flex gap-2">
                    <button type="submit" class="flex-1 px-4 py-2 bg-gray-800 text-white text-sm font-semibold rounded-lg hover:bg-gray-900 transition-all">Search</button>
                    <a href="{{ route('general.attendance.monthly') }}"
                       class="px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">Reset</a>
                </div>
            </div>
        </form>
    </div>

    {{-- Kartu statistik --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        @foreach([
            ['label' => 'Total Employees', 'value' => $stats['employees'],        'hint' => 'shown on this page'],
            ['label' => 'Work Days',       'value' => $stats['work_days'],        'hint' => 'excluding weekends & holidays'],
            ['label' => 'Average Present', 'value' => $stats['average_present'],  'hint' => 'days per employee'],
            ['label' => 'Complete Days',   'value' => $stats['average_complete'], 'hint' => 'with check-out, per employee'],
        ] as $card)
        <div class="bg-white rounded-xl p-5 shadow-sm">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ $card['label'] }}</p>
            <p class="text-3xl font-bold text-gray-900 mt-1">{{ $card['value'] }}</p>
            <p class="text-xs text-gray-400 mt-0.5">{{ $card['hint'] }}</p>
        </div>
        @endforeach
    </div>

    {{-- Matriks --}}
    <div class="bg-white rounded-xl p-6 shadow-sm">

        {{-- Navigasi bulan; filter lain dipertahankan lewat query string --}}
        <div class="flex items-center justify-between gap-4 mb-4">
            <a href="{{ route('general.attendance.monthly', array_merge(request()->query(), ['month' => $month->copy()->subMonth()->format('Y-m')])) }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">
                <i class="fas fa-arrow-left"></i> Prev
            </a>
            <h3 class="text-lg font-bold text-gray-900 uppercase tracking-wide">{{ $month->translatedFormat('F Y') }}</h3>
            <a href="{{ route('general.attendance.monthly', array_merge(request()->query(), ['month' => $month->copy()->addMonth()->format('Y-m')])) }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">
                Next <i class="fas fa-arrow-right"></i>
            </a>
        </div>

        <div class="border border-gray-200 rounded-lg overflow-x-auto">
            <table class="text-sm w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr class="text-xs font-semibold text-gray-500 uppercase tracking-wide">
                        <th class="px-3 py-3 text-left recap-sticky recap-sticky-no">No</th>
                        <th class="px-3 py-3 text-left recap-sticky recap-sticky-name">Employee Name</th>
                        @foreach($days as $day => $meta)
                        <th class="px-1 py-3 text-center w-9 {{ $meta['is_workday'] ? 'text-gray-500' : 'text-red-500 bg-red-50' }}"
                            title="{{ $meta['is_holiday'] ? 'Public holiday' : ($meta['is_weekend'] ? 'Weekend' : 'Working day') }}">
                            <span class="block leading-none">{{ $day }}</span>
                            <span class="block leading-none font-normal text-[10px] mt-0.5">{{ $meta['initial'] }}</span>
                        </th>
                        @endforeach
                        <th class="px-2 py-3 text-center bg-blue-50 text-blue-700">Σ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($rows as $index => $row)
                    @php $basic = $row['employee']->basicData; @endphp
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-3 py-3 text-gray-400 recap-sticky recap-sticky-no">
                            {{ method_exists($employees, 'firstItem') ? $employees->firstItem() + $index : $index + 1 }}
                        </td>
                        <td class="px-3 py-3 recap-sticky recap-sticky-name">
                            <div class="font-medium text-gray-900">{{ $basic?->nick_name ?? '—' }}</div>
                            <div class="text-xs text-gray-400">
                                {{ $row['employee']->eci }}
                                @if($basic?->department) · {{ $basic->department }} @endif
                            </div>
                        </td>

                        @foreach($days as $day => $meta)
                        @php $cell = $row['cells'][$day] ?? null; @endphp
                        <td class="px-1 py-3 text-center {{ $meta['is_workday'] ? '' : 'bg-red-50/50' }}">
                            @if($cell)
                                <span title="In {{ $cell->check_in_at?->format('H:i') ?? '–' }} · Out {{ $cell->check_out_at?->format('H:i') ?? '–' }}{{ $cell->late_minutes > 0 ? ' · Late ' . $cell->late_minutes . ' m' : '' }}"
                                      class="inline-flex items-center justify-center w-5 h-5 rounded-full text-white text-[10px]
                                             {{ $cell->late_minutes > 0 ? 'bg-amber-500' : 'bg-blue-600' }}">
                                    <i class="fas fa-check"></i>
                                </span>
                                @if($cell->check_out_at)
                                    <span class="block mx-auto mt-0.5 w-3 h-1.5 rounded-sm bg-green-500" title="Check-out recorded"></span>
                                @endif
                            @else
                                <span class="text-gray-300">–</span>
                            @endif
                        </td>
                        @endforeach

                        <td class="px-2 py-3 text-center font-bold text-gray-900 bg-blue-50">{{ $row['present'] }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ count($days) + 3 }}" class="px-4 py-12 text-center">
                            <div class="flex flex-col items-center gap-2 text-gray-400">
                                <i class="fas fa-table-cells text-3xl"></i>
                                <p class="text-sm font-medium">No employees match this filter.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if(method_exists($employees, 'hasPages') && $employees->hasPages())
        <div class="mt-4">{{ $employees->links() }}</div>
        @endif

        {{-- Legenda --}}
        <div class="mt-5 flex flex-col sm:flex-row sm:items-center gap-x-6 gap-y-2 text-xs text-gray-500">
            <span class="flex items-center gap-1.5">
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-blue-600 text-white text-[10px]"><i class="fas fa-check"></i></span>
                Present
            </span>
            <span class="flex items-center gap-1.5">
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-amber-500 text-white text-[10px]"><i class="fas fa-check"></i></span>
                Late
            </span>
            <span class="flex items-center gap-1.5">
                <span class="inline-block w-3 h-1.5 rounded-sm bg-green-500"></span>
                Check-out recorded
            </span>
            <span class="flex items-center gap-1.5"><span class="text-gray-300">–</span> No record</span>
            <span class="flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded-sm bg-red-50 border border-red-200"></span> Weekend / holiday</span>
        </div>

        <div class="mt-3 flex items-start gap-2 text-xs text-gray-600 bg-amber-50 border border-amber-200 rounded-lg p-3">
            <i class="fas fa-triangle-exclamation text-amber-500 mt-0.5"></i>
            <div>
                A dash means <strong class="text-gray-800">no attendance record exists</strong> for that day —
                it does <strong class="text-gray-800">not</strong> mean the employee was absent. Distinguishing
                absence from leave, sick days, and business trips requires the Leave module, which is not built yet.
            </div>
        </div>
    </div>
</div>
@endsection

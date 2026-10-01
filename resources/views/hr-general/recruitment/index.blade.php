@extends('dashboard')
@section('title', 'Recruitment')
@section('page-title', 'Recruitment')
@section('page-subtitle', 'Recruitment performance to date and this week\'s interview calendar.')

@section('content')
<div class="w-full space-y-6 px-1 lg:px-2">

    @include('hr-general.recruitment.components.hub-tabs')

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-sm font-bold text-gray-800">Recruitment Overview</h2>
            <p class="text-[11px] text-gray-400 mt-0.5">All figures cover every candidate recorded to date.</p>
        </div>
        @if($can('general.recruitment.candidates') && $canDo('general.recruitment.candidates', 'create'))
            <a href="{{ route('general.recruitment.candidates.index', ['add' => 1]) }}"
                class="inline-flex items-center gap-1.5 px-4 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-all shadow-sm">
                <i class="fas fa-plus text-xs"></i> Add Candidate
            </a>
        @endif
    </div>

    <!-- Metrics -->
    @php
        $tiles = [
            [
                'label' => 'Scheduled Interviews',
                'value' => $stats['scheduled_interviews'],
                'note'  => 'Upcoming, for candidates at HR or User Interview',
            ],
            [
                'label' => 'Active Candidates',
                'value' => $stats['active_candidates'],
                'note'  => 'Still in the selection process',
            ],
            [
                'label' => 'Offer Acceptance Rate',
                'value' => $stats['acceptance_rate'] === null ? '–' : $stats['acceptance_rate'] . '%',
                'note'  => $stats['accepted'] . ' accepted of ' . $stats['offered'] . ' offered',
            ],
            [
                'label' => 'Rejection Rate',
                'value' => $stats['rejection_rate'] === null ? '–' : $stats['rejection_rate'] . '%',
                'note'  => $stats['rejected'] . ' rejected of ' . $stats['total_candidates'] . ' candidates',
            ],
            [
                'label' => 'Open Job Openings',
                'value' => $stats['open_jobs'],
                'note'  => 'Currently within their posting period',
            ],
        ];
    @endphp
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-3">
        @foreach($tiles as $tile)
            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                <span class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block">{{ $tile['label'] }}</span>
                <p class="text-2xl font-extrabold text-gray-900 mt-1">{{ $tile['value'] }}</p>
                <p class="text-[11px] text-gray-400 mt-1 leading-snug">{{ $tile['note'] }}</p>
            </div>
        @endforeach
    </div>

    <!-- This week's calendar -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-3.5 border-b border-gray-100 bg-gray-50 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h3 class="text-sm font-bold text-gray-800">This Week's Calendar</h3>
                <p class="text-[11px] text-gray-400 mt-0.5">
                    {{ $weekStart->format('d M') }} – {{ $weekDays->last()->format('d M Y') }}
                </p>
            </div>
            @if($can('general.recruitment.schedule'))
                <a href="{{ route('general.recruitment.schedule.index', ['view' => 'week']) }}" class="text-xs font-semibold" style="color: var(--primary-color);">
                    Other weeks — open the full calendar <i class="fas fa-arrow-right text-[10px]"></i>
                </a>
            @endif
        </div>
        <div class="p-4">
            @if($calendar['error'])
                <div class="flex items-start gap-2 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mb-3">
                    <i class="fas fa-triangle-exclamation mt-0.5"></i>
                    <span>{{ $calendar['error'] }}</span>
                </div>
            @endif
            {{-- A glance, not a planner: the seven dates with what is on them, and the
                 week's agenda in order beside it. Hour-by-hour detail is on the Schedule tab. --}}
            @php
                $today = now()->format('Y-m-d');
                $agenda = $calendar['events']->groupBy('date');
            @endphp
            <div class="flex flex-col xl:flex-row gap-4">
                <div class="flex-1 min-w-0 overflow-x-auto">
                    <div class="min-w-[640px] grid grid-cols-7 border border-gray-200 rounded-lg overflow-hidden">
                        @foreach($weekDays as $day)
                            @php
                                $dateKey = $day->format('Y-m-d');
                                $isToday = $dateKey === $today;
                                $dayEvents = $agenda->get($dateKey, collect());
                            @endphp
                            <div class="min-h-[168px] border-r border-gray-100 last:border-r-0 {{ $isToday ? 'bg-gray-50/70' : 'bg-white' }}">
                                <div class="px-2.5 py-2 border-b border-gray-100 border-t-2" style="border-top-color: {{ $isToday ? 'var(--primary-color)' : 'transparent' }};">
                                    <div class="text-lg leading-none {{ $isToday ? 'font-bold' : 'font-medium text-gray-700' }}" @if($isToday) style="color: var(--primary-color);" @endif>{{ $day->day }}</div>
                                    <div class="text-[11px] mt-1 {{ $isToday ? 'font-semibold' : 'text-gray-500' }}" @if($isToday) style="color: var(--primary-color);" @endif>{{ $day->format('D') }}{{ $isToday ? ' · Today' : '' }}</div>
                                </div>
                                <div class="p-1.5 space-y-1">
                                    @foreach($dayEvents as $event)
                                        <button type="button" onclick="openCalendarEvent('{{ $event['id'] }}')" title="{{ $event['start'] }} {{ $event['title'] }}"
                                            class="w-full text-left truncate rounded px-1.5 py-1 text-[11px] font-semibold border-l-[3px] transition hover:brightness-95 {{ $event['type'] === 'interview' ? 'text-gray-900' : 'bg-gray-100 border-gray-400 text-gray-700' }}"
                                            @if($event['type'] === 'interview') style="background: rgba(var(--primary-rgb), 0.14); border-left-color: var(--primary-color);" @endif>
                                            {{ $event['title'] }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <aside class="xl:w-80 shrink-0 border border-gray-200 rounded-lg">
                    <div class="px-4 py-2.5 border-b border-gray-100 flex items-center justify-between">
                        <h4 class="text-xs font-bold text-gray-800">Agenda This Week</h4>
                        <span class="text-[11px] text-gray-400">{{ $calendar['events']->count() }} {{ \Illuminate\Support\Str::plural('agenda', $calendar['events']->count()) }}</span>
                    </div>
                    <div class="p-2 max-h-[300px] overflow-y-auto">
                        @foreach($weekDays as $day)
                            @php $dayEvents = $agenda->get($day->format('Y-m-d'), collect()); @endphp
                            @continue($dayEvents->isEmpty())
                            <p class="px-2 pt-2 pb-1 text-[10px] font-bold uppercase tracking-wider {{ $day->format('Y-m-d') === $today ? '' : 'text-gray-400' }}"
                                @if($day->format('Y-m-d') === $today) style="color: var(--primary-color);" @endif>
                                {{ $day->format('D, d M') }}{{ $day->format('Y-m-d') === $today ? ' · Today' : '' }}
                            </p>
                            @foreach($dayEvents as $event)
                                <button type="button" onclick="openCalendarEvent('{{ $event['id'] }}')"
                                    class="w-full flex gap-2.5 text-left rounded-lg px-2 py-1.5 hover:bg-gray-50 transition">
                                    <span class="w-1 rounded-full shrink-0" style="background: {{ $event['type'] === 'interview' ? 'var(--primary-color)' : '#9ca3af' }};"></span>
                                    <span class="w-11 shrink-0 text-xs text-gray-800">{{ $event['start'] }}</span>
                                    <span class="min-w-0 text-xs">
                                        <span class="block font-semibold text-gray-800 truncate">{{ $event['title'] }}</span>
                                        <span class="block text-[11px] text-gray-500 truncate">{{ collect($event['participants'])->pluck('name')->implode(', ') }}</span>
                                    </span>
                                </button>
                            @endforeach
                        @endforeach
                        @if($calendar['events']->isEmpty())
                            <p class="text-xs text-gray-400 text-center py-10">Nothing scheduled this week.</p>
                        @endif
                    </div>
                </aside>
            </div>
        </div>
    </div>

    <!-- Charts -->
    @php
        $chartCards = [
            'status'   => ['title' => 'Selection Status',     'subtitle' => 'Candidates at each stage of the pipeline', 'column' => 'Status'],
            'source'   => ['title' => 'Job Source Platforms', 'subtitle' => 'Where candidates come from',               'column' => 'Platform'],
            'position' => ['title' => 'Job Positions',        'subtitle' => 'Candidates per position applied for',      'column' => 'Position'],
        ];
    @endphp
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        @foreach($chartCards as $key => $card)
            @php $chart = $charts[$key]; @endphp
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <h3 class="text-sm font-bold text-gray-800">{{ $card['title'] }}</h3>
                <p class="text-[11px] text-gray-400 mt-0.5 mb-3">{{ $card['subtitle'] }}</p>

                @if(array_sum($chart['values']) === 0)
                    <p class="text-xs text-gray-400 text-center py-10">No candidates yet.</p>
                @else
                    <div style="height: {{ count($chart['labels']) * 30 + 36 }}px;">
                        <canvas id="chart-{{ $key }}" role="img" aria-label="{{ $card['title'] }}: {{ $card['subtitle'] }}"></canvas>
                    </div>
                    <div class="mt-3 text-xs">
                        <button type="button" onclick="toggleChartTable(this)" aria-expanded="false" aria-controls="chart-table-{{ $key }}"
                            class="inline-flex items-center gap-1.5 font-semibold text-gray-500 hover:text-gray-800">
                            <i class="fas fa-chevron-right text-[9px] transition-transform"></i> View as table
                        </button>
                        <table id="chart-table-{{ $key }}" class="hidden w-full mt-2">
                            <thead>
                                <tr class="text-left text-[10px] uppercase text-gray-400">
                                    <th class="py-1 font-semibold">{{ $card['column'] }}</th>
                                    <th class="py-1 font-semibold text-right">Candidates</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($chart['labels'] as $i => $label)
                                    <tr class="border-t border-gray-100">
                                        <td class="py-1 text-gray-700">{{ $label }}</td>
                                        <td class="py-1 text-right font-semibold text-gray-800">{{ $chart['values'][$i] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>

{{-- Read-only here: rescheduling and cancelling are done from the Schedule tab or the candidate's page. --}}
@include('hr-general.recruitment.components.calendar-event-modal', [
    'events' => $calendar['events'], 'canEdit' => false, 'canCancel' => false,
    'canOpenCandidate' => $can('general.recruitment.candidates'),
    'dayUrl' => $can('general.recruitment.schedule') ? route('general.recruitment.schedule.index', ['view' => 'day', 'date' => '__DATE__']) : null,
])
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    function toggleChartTable(button) {
        const table = document.getElementById(button.getAttribute('aria-controls'));
        const open = table.classList.toggle('hidden') === false;
        button.setAttribute('aria-expanded', String(open));
        button.querySelector('i').classList.toggle('rotate-90', open);
    }

    (function () {
        const charts = @json($charts);
        const seriesColor = getComputedStyle(document.documentElement).getPropertyValue('--primary-color').trim() || '#4f46e5';

        Object.entries(charts).forEach(([key, chart]) => {
            const canvas = document.getElementById('chart-' + key);
            if (!canvas) return;

            new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: chart.labels,
                    datasets: [{
                        label: 'Candidates',
                        data: chart.values,
                        backgroundColor: seriesColor,
                        borderRadius: 4,
                        borderSkipped: 'start',
                        barThickness: 14,
                    }],
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: { precision: 0, color: '#6b7280', font: { size: 10 } },
                            grid: { color: '#f3f4f6' },
                            border: { display: false },
                        },
                        y: {
                            ticks: { color: '#374151', font: { size: 11 } },
                            grid: { display: false },
                            border: { color: '#e5e7eb' },
                        },
                    },
                },
            });
        });
    })();
</script>
@endpush

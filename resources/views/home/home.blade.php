@extends('dashboard')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
@if(($user['type'] ?? '') === 'employee' && ($user['role']['id'] ?? 0) == \App\Enums\RoleId::DELIVERY_SUPPORT_USER->value)
{{-- ===================== DELIVERY SUPPORT USER DASHBOARD ===================== --}}
<div class="space-y-6">

    {{-- Ticket Status Stats --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3">
        @php
            $stats = $data['ticket_stats'] ?? [];
            $statCards = [
                ['label' => 'Total',            'key' => 'total',                   'color' => 'text-gray-900'],
                ['label' => 'Open',             'key' => 'open',                    'color' => 'text-blue-600'],
                ['label' => 'Inprocess',        'key' => 'inprocess',               'color' => 'text-yellow-600'],
                ['label' => 'Wait Customer',    'key' => 'waiting_on_customer',     'color' => 'text-amber-500'],
                ['label' => 'Wait 3rd Party',   'key' => 'waiting_on_3rd_party',    'color' => 'text-indigo-600'],
                ['label' => 'Wait Confirm',     'key' => 'waiting_to_confirmation', 'color' => 'text-teal-600'],
                ['label' => 'Hold',             'key' => 'hold',                    'color' => 'text-orange-500'],
                ['label' => 'Cancelled',        'key' => 'cancelled',               'color' => 'text-red-400'],
                ['label' => 'Closed',           'key' => 'closed',                  'color' => 'text-green-600'],
            ];
        @endphp
        @foreach($statCards as $card)
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 hover:shadow-md transition-shadow">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide leading-tight mb-2">{{ $card['label'] }}</p>
            <p class="text-3xl font-bold {{ $card['color'] }}">{{ $stats[$card['key']] ?? 0 }}</p>
        </div>
        @endforeach
    </div>

    {{-- Main Grid: Chart + Recent Tickets --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Ticket Submissions Chart --}}
        <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <div class="flex items-start justify-between mb-1">
                <div>
                    <h3 class="text-base font-bold text-gray-900">Ticket Submissions</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Last 30 days</p>
                </div>
                <span class="text-xs text-gray-400">{{ now()->format('d M Y') }}</span>
            </div>
            <div class="mt-4">
                <canvas id="ticketChart" height="100"></canvas>
            </div>
        </div>

        {{-- Recent Tickets --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 flex flex-col">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-bold text-gray-900">Recent Tickets</h3>
                <a href="{{ route('ticket.index') }}" class="text-xs font-semibold text-red-800 hover:text-red-900">
                    View all &rarr;
                </a>
            </div>

            @php $recentTickets = $data['recent_tickets'] ?? collect(); @endphp

            @if($recentTickets->isEmpty())
            <div class="flex-1 flex flex-col items-center justify-center text-center py-8">
                <svg class="w-12 h-12 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                <p class="text-sm font-medium text-gray-500">No tickets yet</p>
            </div>
            @else
            <div class="space-y-2 flex-1">
                @foreach($recentTickets->take(5) as $ticket)
                @php
                    $statusColors = [
                        'open'                    => 'bg-blue-100 text-blue-700',
                        'inprocess'               => 'bg-yellow-100 text-yellow-700',
                        'waiting_on_customer'     => 'bg-amber-100 text-amber-700',
                        'waiting_on_3rd_party'    => 'bg-indigo-100 text-indigo-700',
                        'waiting_to_confirmation' => 'bg-teal-100 text-teal-700',
                        'hold'                    => 'bg-orange-100 text-orange-700',
                        'cancelled'               => 'bg-gray-100 text-gray-500',
                        'closed'                  => 'bg-green-100 text-green-700',
                    ];
                    $statusClass = $statusColors[$ticket->status] ?? 'bg-gray-100 text-gray-600';
                    $statusLabel = match($ticket->status) {
                        'open'                    => 'Open',
                        'inprocess'               => 'Inprocess',
                        'waiting_on_customer'     => 'Waiting Customer',
                        'waiting_on_3rd_party'    => 'Waiting 3rd Party',
                        'waiting_to_confirmation' => 'Waiting Confirm',
                        'hold'                    => 'Hold',
                        'cancelled'               => 'Cancelled',
                        'closed'                  => 'Closed',
                        default                   => ucfirst($ticket->status),
                    };
                @endphp
                <a href="{{ route('ticket.show', $ticket->ticket_id) }}"
                   class="flex items-center gap-2 px-2.5 py-2 rounded-lg hover:bg-gray-50 border border-gray-100 hover:border-gray-200 transition-all">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-1.5">
                            <p class="text-xs font-semibold text-gray-800 truncate">{{ $ticket->ticket_number ?? '#'.$ticket->ticket_id }}</p>
                        </div>
                        <p class="text-[11px] text-gray-400 truncate leading-tight">{{ $ticket->customer_name ?? 'Unknown' }} · {{ \Carbon\Carbon::parse($ticket->created_at)->format('d M Y') }}</p>
                    </div>
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold {{ $statusClass }} whitespace-nowrap flex-shrink-0">
                        {{ $statusLabel }}
                    </span>
                </a>
                @endforeach
            </div>
            @endif
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <a href="{{ route('ticket.index') }}"
           class="flex items-center gap-4 p-5 bg-white rounded-xl border border-gray-200 shadow-sm hover:shadow-md hover:border-blue-300 transition-all group">
            <div class="w-12 h-12 rounded-xl bg-blue-50 group-hover:bg-blue-100 flex items-center justify-center flex-shrink-0 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-blue-600">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/>
                </svg>
            </div>
            <div>
                <p class="text-sm font-bold text-gray-900">My Tickets</p>
                <p class="text-xs text-gray-500 mt-0.5">
                    {{ ($stats['open'] ?? 0) }} open &middot; {{ ($stats['closed'] ?? 0) }} closed
                </p>
            </div>
        </a>

        <a href="{{ route('profile.my') }}"
           class="flex items-center gap-4 p-5 bg-white rounded-xl border border-gray-200 shadow-sm hover:shadow-md hover:border-gray-300 transition-all group">
            <div class="w-12 h-12 rounded-xl bg-gray-100 group-hover:bg-gray-200 flex items-center justify-center flex-shrink-0 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-gray-600">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                </svg>
            </div>
            <div>
                <p class="text-sm font-bold text-gray-900">My Profile</p>
                <p class="text-xs text-gray-500 mt-0.5">View profile</p>
            </div>
        </a>
    </div>

</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function() {
    const labels = @json($data['ticket_chart']['labels'] ?? []);
    const chartData = @json($data['ticket_chart']['data'] ?? []);

    const ctx = document.getElementById('ticketChart');
    if (!ctx) return;

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Tickets',
                data: chartData,
                borderColor: '#ef4444',
                backgroundColor: 'rgba(239,68,68,0.08)',
                borderWidth: 2,
                pointRadius: 3,
                pointBackgroundColor: '#ef4444',
                tension: 0.3,
                fill: true,
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => ctx.parsed.y + ' ticket' + (ctx.parsed.y !== 1 ? 's' : '')
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 11 }, maxTicksLimit: 10, color: '#9ca3af' }
                },
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.05)' },
                    ticks: { stepSize: 1, precision: 0, color: '#9ca3af', font: { size: 11 } }
                }
            }
        }
    });
})();
</script>
@endpush

@elseif(($user['type'] ?? '') === 'employee' && ($user['role']['id'] ?? 0) == \App\Enums\RoleId::DELIVERY_SUPPORT_HEAD->value)
{{-- ===================== DELIVERY SUPPORT HEAD DASHBOARD ===================== --}}
<div class="space-y-6">

    {{-- Ticket Status Stats --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3">
        @php
            $stats = $data['ticket_stats'] ?? [];
            $statCards = [
                ['label' => 'Total',            'key' => 'total',                   'color' => 'text-gray-900'],
                ['label' => 'Open',             'key' => 'open',                    'color' => 'text-blue-600'],
                ['label' => 'Inprocess',        'key' => 'inprocess',               'color' => 'text-yellow-600'],
                ['label' => 'Wait Customer',    'key' => 'waiting_on_customer',     'color' => 'text-amber-500'],
                ['label' => 'Wait 3rd Party',   'key' => 'waiting_on_3rd_party',    'color' => 'text-indigo-600'],
                ['label' => 'Wait Confirm',     'key' => 'waiting_to_confirmation', 'color' => 'text-teal-600'],
                ['label' => 'Hold',             'key' => 'hold',                    'color' => 'text-orange-500'],
                ['label' => 'Cancelled',        'key' => 'cancelled',               'color' => 'text-red-400'],
                ['label' => 'Closed',           'key' => 'closed',                  'color' => 'text-green-600'],
            ];
        @endphp
        @foreach($statCards as $card)
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 hover:shadow-md transition-shadow">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide leading-tight mb-2">{{ $card['label'] }}</p>
            <p class="text-3xl font-bold {{ $card['color'] }}">{{ $stats[$card['key']] ?? 0 }}</p>
        </div>
        @endforeach
    </div>

    {{-- Main Grid: Chart + Team Load --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Ticket Submissions Chart --}}
        <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <div class="flex items-start justify-between mb-1">
                <div>
                    <h3 class="text-base font-bold text-gray-900">Ticket Submissions</h3>
                    <p class="text-xs text-gray-500 mt-0.5">All tickets — last 30 days</p>
                </div>
                <span class="text-xs text-gray-400">{{ now()->format('d M Y') }}</span>
            </div>
            <div class="mt-4">
                <canvas id="ticketChartHead" height="100"></canvas>
            </div>
        </div>

        {{-- Team Load --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 flex flex-col">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-bold text-gray-900">Team Load</h3>
                <span class="text-xs text-gray-400">Active tickets / member</span>
            </div>
            @php $teamLoad = $data['team_load'] ?? collect(); @endphp
            @if($teamLoad->isEmpty())
            <div class="flex-1 flex items-center justify-center text-center py-8">
                <p class="text-sm text-gray-400">No active tickets</p>
            </div>
            @else
            @php $maxLoad = $teamLoad->max('open_count') ?: 1; @endphp
            <div class="space-y-3 flex-1">
                @foreach($teamLoad as $member)
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs font-semibold text-gray-700 truncate max-w-[70%]">{{ $member->name }}</span>
                        <span class="text-xs font-bold text-gray-900">{{ $member->open_count }}</span>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-2">
                        <div class="bg-red-600 h-2 rounded-full transition-all"
                             style="width: {{ round(($member->open_count / $maxLoad) * 100) }}%"></div>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>

    {{-- Quick Actions — full-width horizontal grid --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
        <a href="{{ route('ticket.index') }}"
           class="flex flex-col items-center gap-3 p-5 bg-white rounded-xl border-2 border-gray-200 hover:border-red-400 hover:shadow-md transition-all group text-center">
            <div class="w-14 h-14 rounded-xl bg-red-50 group-hover:bg-red-100 flex items-center justify-center transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-7 h-7 text-red-700">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75a3.75 3.75 0 0 1-7.5 0V6m-3 6.75h13.5m-13.5 0a3 3 0 0 0-3 3v3a3 3 0 0 0 3 3h13.5a3 3 0 0 0 3-3v-3a3 3 0 0 0-3-3m-6.75 6h.008v.008h-.008V18Z"/>
                </svg>
            </div>
            <div>
                <p class="text-sm font-bold text-gray-900 group-hover:text-red-900">All Tickets</p>
                <p class="text-xs text-gray-500 mt-0.5">{{ ($stats['open'] ?? 0) }} open · {{ ($stats['very_high'] ?? 0) }} very high</p>
            </div>
        </a>

        <a href="{{ route('reporting') }}"
           class="flex flex-col items-center gap-3 p-5 bg-white rounded-xl border-2 border-gray-200 hover:border-blue-400 hover:shadow-md transition-all group text-center">
            <div class="w-14 h-14 rounded-xl bg-blue-50 group-hover:bg-blue-100 flex items-center justify-center transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-7 h-7 text-blue-600">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25Z"/>
                </svg>
            </div>
            <div>
                <p class="text-sm font-bold text-gray-900 group-hover:text-blue-900">Reporting</p>
                <p class="text-xs text-gray-500 mt-0.5">View & export reports</p>
            </div>
        </a>

        <a href="{{ route('reporting.md-recap') }}"
           class="flex flex-col items-center gap-3 p-5 bg-white rounded-xl border-2 border-gray-200 hover:border-purple-400 hover:shadow-md transition-all group text-center">
            <div class="w-14 h-14 rounded-xl bg-purple-50 group-hover:bg-purple-100 flex items-center justify-center transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-7 h-7 text-purple-600">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
                </svg>
            </div>
            <div>
                <p class="text-sm font-bold text-gray-900 group-hover:text-purple-900">MD Recap</p>
                <p class="text-xs text-gray-500 mt-0.5">Mandays summary</p>
            </div>
        </a>

        <a href="{{ route('master.employee.index') }}"
           class="flex flex-col items-center gap-3 p-5 bg-white rounded-xl border-2 border-gray-200 hover:border-green-400 hover:shadow-md transition-all group text-center">
            <div class="w-14 h-14 rounded-xl bg-green-50 group-hover:bg-green-100 flex items-center justify-center transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-7 h-7 text-green-600">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"/>
                </svg>
            </div>
            <div>
                <p class="text-sm font-bold text-gray-900 group-hover:text-green-900">Employees</p>
                <p class="text-xs text-gray-500 mt-0.5">{{ $data['employee'] ?? 0 }} active members</p>
            </div>
        </a>

        <a href="{{ route('profile.my') }}"
           class="flex flex-col items-center gap-3 p-5 bg-white rounded-xl border-2 border-gray-200 hover:border-gray-400 hover:shadow-md transition-all group text-center">
            <div class="w-14 h-14 rounded-xl bg-gray-100 group-hover:bg-gray-200 flex items-center justify-center transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-7 h-7 text-gray-600">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                </svg>
            </div>
            <div>
                <p class="text-sm font-bold text-gray-900">My Profile</p>
                <p class="text-xs text-gray-500 mt-0.5">View & edit profile</p>
            </div>
        </a>
    </div>

    {{-- Recent Tickets — full width --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 class="text-sm font-bold text-gray-900">Recent Tickets</h3>
            <a href="{{ route('ticket.index') }}" class="text-xs font-semibold text-red-800 hover:text-red-900">View all →</a>
        </div>
        @php $recentTickets = $data['recent_tickets'] ?? collect(); @endphp
        @if($recentTickets->isEmpty())
        <div class="px-6 py-10 text-center text-sm text-gray-400">No tickets yet</div>
        @else
        @php
            $statusColors = [
                'open'                    => 'bg-blue-100 text-blue-700',
                'inprocess'               => 'bg-yellow-100 text-yellow-700',
                'waiting_on_customer'     => 'bg-amber-100 text-amber-700',
                'waiting_on_3rd_party'    => 'bg-indigo-100 text-indigo-700',
                'waiting_to_confirmation' => 'bg-teal-100 text-teal-700',
                'hold'                    => 'bg-orange-100 text-orange-700',
                'cancelled'               => 'bg-gray-100 text-gray-500',
                'closed'                  => 'bg-green-100 text-green-700',
            ];
        @endphp
        {{-- Table Header --}}
        <div class="hidden md:grid grid-cols-12 gap-4 px-6 py-2.5 bg-gray-50 border-b border-gray-100 text-[11px] font-semibold text-gray-500 uppercase tracking-wide">
            <div class="col-span-2">Ticket #</div>
            <div class="col-span-4">Description</div>
            <div class="col-span-2">Customer</div>
            <div class="col-span-2">PIC</div>
            <div class="col-span-1">Date</div>
            <div class="col-span-1 text-right">Status</div>
        </div>
        <div class="divide-y divide-gray-50">
            @foreach($recentTickets as $ticket)
            @php
                $sCls = $statusColors[$ticket->status] ?? 'bg-gray-100 text-gray-600';
                $sLabel = match($ticket->status) {
                    'open'                    => 'Open',
                    'inprocess'               => 'Inprocess',
                    'waiting_on_customer'     => 'Waiting Customer',
                    'waiting_on_3rd_party'    => 'Waiting 3rd Party',
                    'waiting_to_confirmation' => 'Waiting Confirm',
                    'hold'                    => 'Hold',
                    'cancelled'               => 'Cancelled',
                    'closed'                  => 'Closed',
                    default                   => ucfirst($ticket->status),
                };
            @endphp
            <a href="{{ route('ticket.show', $ticket->ticket_id) }}"
               class="flex md:grid md:grid-cols-12 md:gap-4 items-center px-6 py-3.5 hover:bg-gray-50 transition-colors group">
                <div class="col-span-2">
                    <span class="text-sm font-bold text-gray-800 group-hover:text-red-800 transition-colors">{{ $ticket->ticket_number ?? '#'.$ticket->ticket_id }}</span>
                </div>
                <div class="col-span-4 hidden md:block">
                    <span class="text-sm text-gray-600 truncate block">{{ Str::limit($ticket->description ?? '—', 45) }}</span>
                </div>
                <div class="col-span-2 hidden md:block">
                    <span class="text-sm text-gray-600 truncate block">{{ $ticket->customer_name ?? 'Unknown' }}</span>
                </div>
                <div class="col-span-2 hidden md:block">
                    <span class="text-sm text-gray-600 truncate block">{{ $ticket->pic_name }}</span>
                </div>
                <div class="col-span-1 hidden md:block">
                    <span class="text-xs text-gray-400 whitespace-nowrap">{{ \Carbon\Carbon::parse($ticket->created_at)->format('d M') }}</span>
                </div>
                <div class="col-span-1 flex md:justify-end ml-auto md:ml-0">
                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold {{ $sCls }} whitespace-nowrap">{{ $sLabel }}</span>
                </div>
            </a>
            @endforeach
        </div>
        @endif
    </div>

</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function() {
    const labels    = @json($data['ticket_chart']['labels'] ?? []);
    const chartData = @json($data['ticket_chart']['data'] ?? []);
    const ctx = document.getElementById('ticketChartHead');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Tickets',
                data: chartData,
                borderColor: '#ef4444',
                backgroundColor: 'rgba(239,68,68,0.08)',
                borderWidth: 2,
                pointRadius: 3,
                pointBackgroundColor: '#ef4444',
                tension: 0.3,
                fill: true,
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => ctx.parsed.y + ' ticket' + (ctx.parsed.y !== 1 ? 's' : '')
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 11 }, maxTicksLimit: 10, color: '#9ca3af' }
                },
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.05)' },
                    ticks: { stepSize: 1, precision: 0, color: '#9ca3af', font: { size: 11 } }
                }
            }
        }
    });
})();
</script>
@endpush

@else
{{-- ===================== DEFAULT DASHBOARD (non role-2) ===================== --}}
<div class="space-y-6">
    <!-- Welcome Card -->
    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">
                    Welcome back, {{ $user['name'] ?? $user['company_name'] ?? 'User' }}! 👋
                </h2>
                <p class="text-gray-600 mt-1 text-sm">
                    Here's what's happening with your account today.
                </p>
            </div>
            <div class="hidden md:block">
                <div class="w-16 h-16 rounded-full bg-gradient-to-br from-red-800 to-red-950 text-white flex items-center justify-center font-bold text-2xl shadow-lg">
                    {{ strtoupper(substr($user['name'] ?? $user['company_name'] ?? 'U', 0, 2)) }}
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
        <!-- Total Employees -->
        <div class="bg-white rounded-xl shadow-sm p-6 border-2 border-gray-200 hover:border-red-300 hover:shadow-md transition-all">
            <div class="flex items-center justify-between mb-3">
                <div class="w-12 h-12 rounded-lg bg-blue-50 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-blue-600">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                    </svg>
                </div>
            </div>
            <p class="text-xs text-gray-600 font-semibold uppercase tracking-wide mb-1">Total Employees</p>
            <h3 class="text-3xl font-bold text-gray-900">{{ $data['employee'] ?? 0 }}</h3>
            <div class="mt-3 flex items-center text-xs text-gray-500">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-1">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941" />
                </svg>
                Active staff members
            </div>
        </div>

        <!-- Total Customers -->
        <div class="bg-white rounded-xl shadow-sm p-6 border-2 border-gray-200 hover:border-red-300 hover:shadow-md transition-all">
            <div class="flex items-center justify-between mb-3">
                <div class="w-12 h-12 rounded-lg bg-green-50 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-green-600">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0M12 12.75h.008v.008H12v-.008Z" />
                    </svg>
                </div>
            </div>
            <p class="text-xs text-gray-600 font-semibold uppercase tracking-wide mb-1">Total Customers</p>
            <h3 class="text-3xl font-bold text-gray-900">{{ $data['customers'] ?? 0 }}</h3>
            <div class="mt-3 flex items-center text-xs text-gray-500">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-1">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72l1.189-1.19A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72M6.75 18h3.75a.75.75 0 0 0 .75-.75V13.5a.75.75 0 0 0-.75-.75H6.75a.75.75 0 0 0-.75.75v3.75c0 .414.336.75.75.75Z" />
                    </svg>
                Registered clients
            </div>
        </div>

        <!-- Active Projects -->
        <div class="bg-white rounded-xl shadow-sm p-6 border-2 border-gray-200 hover:border-red-300 hover:shadow-md transition-all">
            <div class="flex items-center justify-between mb-3">
                <div class="w-12 h-12 rounded-lg bg-purple-50 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-purple-600">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0M12 12.75h.008v.008H12v-.008Z" />
                    </svg>
                </div>
            </div>
            <p class="text-xs text-gray-600 font-semibold uppercase tracking-wide mb-1">Active Projects</p>
            <h3 class="text-3xl font-bold text-gray-900">{{ $data['active_projects'] ?? 0 }}</h3>
            <div class="mt-3 flex items-center text-xs text-gray-500">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-1">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                Ongoing tasks
            </div>
        </div>

        <!-- Total Tickets -->
        <div class="bg-white rounded-xl shadow-sm p-6 border-2 border-gray-200 hover:border-red-300 hover:shadow-md transition-all">
            <div class="flex items-center justify-between mb-3">
                <div class="w-12 h-12 rounded-lg bg-red-50 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-red-600">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75a3.75 3.75 0 0 1-7.5 0V6m-3 6.75h13.5m-13.5 0a3 3 0 0 0-3 3v3a3 3 0 0 0 3 3h13.5a3 3 0 0 0 3-3v-3a3 3 0 0 0-3-3m-6.75 6h.008v.008h-.008V18Z" />
                    </svg>
                </div>
            </div>
            <p class="text-xs text-gray-600 font-semibold uppercase tracking-wide mb-1">Total Tickets</p>
            <h3 class="text-3xl font-bold text-gray-900">{{ number_format($data['total_tickets'] ?? 0) }}</h3>
            <div class="mt-3 flex items-center text-xs text-gray-500">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-1">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25Z" />
                </svg>
                All support tickets
            </div>
        </div>
    </div>

    <!-- Two Column Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- User Info Card -->
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
            <div class="flex items-center justify-between mb-6 pb-4 border-b-2 border-gray-100">
                <h3 class="text-lg font-bold text-gray-900">Your Information</h3>
                <div class="w-10 h-10 rounded-lg bg-red-50 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-red-800">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                    </svg>
                </div>
            </div>
            <div class="grid grid-cols-1 gap-5">
                <div class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg">
                    <div class="w-10 h-10 rounded-lg bg-white border border-gray-200 flex items-center justify-center flex-shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-gray-600">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide mb-1">User Type</p>
                        <p class="text-sm font-bold text-gray-900 capitalize">{{ $user['type'] ?? 'N/A' }}</p>
                    </div>
                </div>

                <div class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg">
                    <div class="w-10 h-10 rounded-lg bg-white border border-gray-200 flex items-center justify-center flex-shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-gray-600">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide mb-1">Email</p>
                        <p class="text-sm font-bold text-gray-900 truncate">{{ $user['email'] ?? 'N/A' }}</p>
                    </div>
                </div>

                @if($user['type'] === 'employee')
                <div class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg">
                    <div class="w-10 h-10 rounded-lg bg-white border border-gray-200 flex items-center justify-center flex-shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-gray-600">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0M12 12.75h.008v.008H12v-.008Z" />
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide mb-1">Position</p>
                        <p class="text-sm font-bold text-gray-900">{{ $user['position'] ?? 'N/A' }}</p>
                    </div>
                </div>

                <div class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg">
                    <div class="w-10 h-10 rounded-lg bg-white border border-gray-200 flex items-center justify-center flex-shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-gray-600">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide mb-1">Department</p>
                        <p class="text-sm font-bold text-gray-900">{{ $user['department'] ?? 'N/A' }}</p>
                    </div>
                </div>
                @endif

                <div class="flex items-start gap-3 p-3 bg-gradient-to-br from-red-50 to-red-100 rounded-lg border border-red-200">
                    <div class="w-10 h-10 rounded-lg bg-white border border-red-300 flex items-center justify-center flex-shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-red-800">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs text-red-700 font-semibold uppercase tracking-wide mb-1">Role</p>
                        <p class="text-sm font-bold text-red-900">{{ $user['role']['name'] ?? 'N/A' }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions Card -->
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
            <div class="flex items-center justify-between mb-6 pb-4 border-b-2 border-gray-100">
                <h3 class="text-lg font-bold text-gray-900">Quick Actions</h3>
                <div class="w-10 h-10 rounded-lg bg-red-50 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-red-800">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5 10.5 21m-6.75-7.5 10.5-10.5m0 0 6.75 6.75M10.5 10.5 21 0" />
                    </svg>
                </div>
            </div>
            <div class="grid grid-cols-1 gap-3">
                <a href="{{ route('master.employee.index') }}" class="flex items-center gap-4 p-4 bg-gray-50 hover:bg-red-50 rounded-lg border-2 border-gray-200 hover:border-red-300 transition-all group">
                    <div class="w-12 h-12 rounded-lg bg-white border border-gray-200 group-hover:border-red-300 flex items-center justify-center flex-shrink-0 transition-all">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-gray-600 group-hover:text-red-800">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-bold text-gray-900 group-hover:text-red-900">Manage Employees</p>
                        <p class="text-xs text-gray-500 group-hover:text-red-700">View and manage all employees</p>
                    </div>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-gray-400 group-hover:text-red-800">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                    </svg>
                </a>

                <a href="{{ route('master.customer.index') }}" class="flex items-center gap-4 p-4 bg-gray-50 hover:bg-red-50 rounded-lg border-2 border-gray-200 hover:border-red-300 transition-all group">
                    <div class="w-12 h-12 rounded-lg bg-white border border-gray-200 group-hover:border-red-300 flex items-center justify-center flex-shrink-0 transition-all">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-gray-600 group-hover:text-red-800">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72l1.189-1.19A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72M6.75 18h3.75a.75.75 0 0 0 .75-.75V13.5a.75.75 0 0 0-.75-.75H6.75a.75.75 0 0 0-.75.75v3.75c0 .414.336.75.75.75Z" />
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-bold text-gray-900 group-hover:text-red-900">Manage Customers</p>
                        <p class="text-xs text-gray-500 group-hover:text-red-700">View and manage all customers</p>
                    </div>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-gray-400 group-hover:text-red-800">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                    </svg>
                </a>

                <a href="{{ route('delivery.support.index') }}" class="flex items-center gap-4 p-4 bg-gray-50 hover:bg-red-50 rounded-lg border-2 border-gray-200 hover:border-red-300 transition-all group">
                    <div class="w-12 h-12 rounded-lg bg-white border border-gray-200 group-hover:border-red-300 flex items-center justify-center flex-shrink-0 transition-all">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-gray-600 group-hover:text-red-800">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 0 1 0 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 0 1 0-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375Z" />
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-bold text-gray-900 group-hover:text-red-900">Support Tickets</p>
                        <p class="text-xs text-gray-500 group-hover:text-red-700">Manage support tickets</p>
                    </div>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-gray-400 group-hover:text-red-800">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                    </svg>
                </a>

                <a href="#" class="flex items-center gap-4 p-4 bg-gradient-to-br from-red-50 to-red-100 rounded-lg border-2 border-red-300 hover:border-red-400 transition-all group">
                    <div class="w-12 h-12 rounded-lg bg-white border border-red-300 group-hover:border-red-400 flex items-center justify-center flex-shrink-0 transition-all">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-red-800">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-bold text-red-900">View Reports</p>
                        <p class="text-xs text-red-700">Generate and view reports</p>
                    </div>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-red-800">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                    </svg>
                </a>
            </div>
        </div>
    </div>

    <!-- Recent Tickets -->
    @php $recentTickets = $data['recent_tickets'] ?? collect(); @endphp
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100">
            <h3 class="text-sm font-bold text-gray-900">Recent Tickets</h3>
            <a href="{{ route('ticket.index') }}" class="text-xs font-semibold text-red-800 hover:text-red-900">View all →</a>
        </div>

        @if($recentTickets->isEmpty())
        <div class="px-5 py-8 text-center text-sm text-gray-400">No tickets yet</div>
        @else
        @php
            $statusColors = [
                'open'                    => 'bg-blue-100 text-blue-700',
                'inprocess'               => 'bg-yellow-100 text-yellow-700',
                'waiting_on_customer'     => 'bg-amber-100 text-amber-700',
                'waiting_on_3rd_party'    => 'bg-indigo-100 text-indigo-700',
                'waiting_to_confirmation' => 'bg-teal-100 text-teal-700',
                'hold'                    => 'bg-orange-100 text-orange-700',
                'cancelled'               => 'bg-gray-100 text-gray-500',
                'closed'                  => 'bg-green-100 text-green-700',
            ];
        @endphp
        <div class="divide-y divide-gray-50">
            @foreach($recentTickets->take(5) as $ticket)
            @php
                $sCls = $statusColors[$ticket->status] ?? 'bg-gray-100 text-gray-600';
                $sLabel = match($ticket->status) {
                    'open'                    => 'Open',
                    'inprocess'               => 'Inprocess',
                    'waiting_on_customer'     => 'Waiting Customer',
                    'waiting_on_3rd_party'    => 'Waiting 3rd Party',
                    'waiting_to_confirmation' => 'Waiting Confirm',
                    'hold'                    => 'Hold',
                    'cancelled'               => 'Cancelled',
                    'closed'                  => 'Closed',
                    default                   => ucfirst($ticket->status),
                };
            @endphp
            <a href="{{ route('ticket.show', $ticket->ticket_id) }}"
               class="flex items-center gap-3 px-5 py-3 hover:bg-gray-50 transition-colors">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold text-gray-800 truncate">{{ $ticket->ticket_number ?? '#'.$ticket->ticket_id }}</span>
                        <span class="text-xs text-gray-400 truncate hidden sm:block">— {{ Str::limit($ticket->description ?? '', 40) }}</span>
                    </div>
                    <p class="text-[11px] text-gray-400 mt-0.5">{{ $ticket->customer_name ?? 'Unknown' }} · {{ \Carbon\Carbon::parse($ticket->created_at)->diffForHumans() }}</p>
                </div>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold {{ $sCls }} whitespace-nowrap flex-shrink-0">{{ $sLabel }}</span>
            </a>
            @endforeach
        </div>
        @endif
    </div>
</div>
@endif
@endsection

@extends('dashboard')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
@if(($user['type'] ?? '') === 'employee' && ($user['role']['id'] ?? 0) == \App\Enums\RoleId::DELIVERY_SUPPORT_USER->value)
{{-- ===================== DELIVERY SUPPORT USER DASHBOARD ===================== --}}
@php
    $stats     = $data['ticket_stats']       ?? [];
    $recents   = $data['recent_tickets']     ?? collect();
    $urgents   = $data['urgent_tickets']     ?? collect();
    $prioBd    = $data['priority_breakdown'] ?? [];
    $asPic     = $data['as_pic_count']       ?? 0;
    $activeC   = $data['active_count']       ?? 0;
    $todayCl   = $data['today_closed']       ?? 0;
    $vhCount   = $data['very_high_count']    ?? 0;

    $hour = now()->hour;
    $greeting  = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    $firstName = explode(' ', $user['name'] ?? 'Consultant')[0];

    $statusCfg = [
        'open'                    => ['label'=>'Open',           'dot'=>'bg-blue-500',   'text'=>'text-blue-700',   'bg'=>'bg-blue-50'   ],
        'inprocess'               => ['label'=>'In Process',     'dot'=>'bg-yellow-500', 'text'=>'text-yellow-700', 'bg'=>'bg-yellow-50' ],
        'waiting_on_customer'     => ['label'=>'Wait Customer',  'dot'=>'bg-amber-500',  'text'=>'text-amber-700',  'bg'=>'bg-amber-50'  ],
        'waiting_on_3rd_party'    => ['label'=>'Wait 3rd Party', 'dot'=>'bg-indigo-500', 'text'=>'text-indigo-700', 'bg'=>'bg-indigo-50' ],
        'waiting_to_confirmation' => ['label'=>'Wait Confirm',   'dot'=>'bg-teal-500',   'text'=>'text-teal-700',   'bg'=>'bg-teal-50'   ],
        'hold'                    => ['label'=>'Hold',           'dot'=>'bg-orange-500', 'text'=>'text-orange-700', 'bg'=>'bg-orange-50' ],
        'cancelled'               => ['label'=>'Cancelled',      'dot'=>'bg-gray-400',   'text'=>'text-gray-500',   'bg'=>'bg-gray-100'  ],
        'closed'                  => ['label'=>'Closed',         'dot'=>'bg-green-500',  'text'=>'text-green-700',  'bg'=>'bg-green-50'  ],
    ];
    $prioCfg = [
        'Very High' => ['text'=>'text-red-700',    'bg'=>'bg-red-50',    'dot'=>'bg-red-500'   ],
        'High'      => ['text'=>'text-orange-700', 'bg'=>'bg-orange-50', 'dot'=>'bg-orange-500'],
        'Medium'    => ['text'=>'text-yellow-700', 'bg'=>'bg-yellow-50', 'dot'=>'bg-yellow-500'],
        'Low'       => ['text'=>'text-blue-700',   'bg'=>'bg-blue-50',   'dot'=>'bg-blue-400'  ],
    ];
@endphp

<div class="space-y-5">

{{-- ── Row 1: Greeting ─────────────────────────────────────────────────────── --}}
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
    <div>
        <div class="flex items-center gap-2">
            <h2 class="text-xl font-bold text-gray-800">{{ $greeting }}, {{ $firstName }}</h2>
            <span class="text-xs font-semibold text-blue-700 bg-blue-50 border border-blue-100 px-2.5 py-0.5 rounded-full">Consultant</span>
        </div>
        <p class="text-xs text-gray-400 mt-0.5">{{ now()->isoFormat('dddd, D MMMM Y') }} &mdash; <span id="dsuClock" class="font-mono"></span></p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        @if($vhCount > 0)
        <a href="{{ route('ticket.index') }}" class="inline-flex items-center gap-1.5 bg-red-50 border border-red-200 text-red-700 text-xs font-semibold px-3 py-1.5 rounded-xl hover:bg-red-100 transition">
            <i class="fas fa-bolt text-xs"></i>{{ $vhCount }} Very High priority
        </a>
        @endif
        <a href="{{ route('ticket.task') }}" class="inline-flex items-center gap-1.5 bg-blue-50 border border-blue-200 text-blue-700 text-xs font-semibold px-3 py-1.5 rounded-xl hover:bg-blue-100 transition">
            <i class="fas fa-tasks text-xs"></i>My Tasks
        </a>
    </div>
</div>

{{-- ── Row 2: KPI Cards ────────────────────────────────────────────────────── --}}
<div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-4">

    {{-- Total My Tickets --}}
    <a href="{{ route('ticket.index') }}" class="bg-white rounded-2xl border-2 border-gray-200 shadow-sm p-4 hover:border-red-300 hover:shadow-md transition-all group">
        <div class="w-9 h-9 rounded-xl bg-red-50 group-hover:bg-red-100 flex items-center justify-center mb-3 transition">
            <i class="fas fa-ticket-alt text-red-600 text-sm"></i>
        </div>
        <p class="text-2xl font-bold text-gray-800">{{ $stats['total'] ?? 0 }}</p>
        <p class="text-xs text-gray-400 mt-0.5">Total My Tickets</p>
    </a>

    {{-- Active --}}
    <a href="{{ route('ticket.index') }}" class="bg-white rounded-2xl border-2 border-gray-200 shadow-sm p-4 hover:border-blue-300 hover:shadow-md transition-all group">
        <div class="w-9 h-9 rounded-xl bg-blue-50 group-hover:bg-blue-100 flex items-center justify-center mb-3 transition">
            <i class="fas fa-clock text-blue-600 text-sm"></i>
        </div>
        <p class="text-2xl font-bold text-gray-800">{{ $activeC }}</p>
        <p class="text-xs text-gray-400 mt-0.5">Active</p>
        <p class="text-[10px] text-gray-300 mt-0.5">open + in progress</p>
    </a>

    {{-- As PIC --}}
    <a href="{{ route('ticket.index') }}" class="bg-white rounded-2xl border-2 {{ $asPic > 0 ? 'border-purple-200' : 'border-gray-200' }} shadow-sm p-4 hover:shadow-md transition-all group">
        <div class="w-9 h-9 rounded-xl {{ $asPic > 0 ? 'bg-purple-50' : 'bg-gray-100' }} group-hover:bg-purple-100 flex items-center justify-center mb-3 transition">
            <i class="fas fa-user-tie {{ $asPic > 0 ? 'text-purple-600' : 'text-gray-400' }} text-sm"></i>
        </div>
        <p class="text-2xl font-bold {{ $asPic > 0 ? 'text-purple-700' : 'text-gray-800' }}">{{ $asPic }}</p>
        <p class="text-xs text-gray-400 mt-0.5">As PIC</p>
        <p class="text-[10px] text-gray-300 mt-0.5">{{ ($stats['total'] ?? 0) - $asPic }} as member</p>
    </a>

    {{-- In Process --}}
    <div class="bg-white rounded-2xl border-2 {{ ($stats['inprocess'] ?? 0) > 0 ? 'border-yellow-200' : 'border-gray-200' }} shadow-sm p-4 transition-all">
        <div class="w-9 h-9 rounded-xl {{ ($stats['inprocess'] ?? 0) > 0 ? 'bg-yellow-50' : 'bg-gray-100' }} flex items-center justify-center mb-3">
            <i class="fas fa-spinner {{ ($stats['inprocess'] ?? 0) > 0 ? 'text-yellow-600' : 'text-gray-400' }} text-sm"></i>
        </div>
        <p class="text-2xl font-bold {{ ($stats['inprocess'] ?? 0) > 0 ? 'text-yellow-700' : 'text-gray-800' }}">{{ $stats['inprocess'] ?? 0 }}</p>
        <p class="text-xs text-gray-400 mt-0.5">In Process</p>
        <p class="text-[10px] text-gray-300 mt-0.5">{{ $stats['open'] ?? 0 }} open</p>
    </div>

    {{-- Very High --}}
    <a href="{{ route('ticket.index') }}" class="relative bg-white rounded-2xl border-2 {{ $vhCount > 0 ? 'border-red-300' : 'border-gray-200' }} shadow-sm p-4 hover:shadow-md transition-all">
        <div class="w-9 h-9 rounded-xl {{ $vhCount > 0 ? 'bg-red-100' : 'bg-gray-100' }} flex items-center justify-center mb-3">
            <i class="fas fa-bolt {{ $vhCount > 0 ? 'text-red-600' : 'text-gray-400' }} text-sm"></i>
        </div>
        <p class="text-2xl font-bold {{ $vhCount > 0 ? 'text-red-700' : 'text-gray-800' }}">{{ $vhCount }}</p>
        <p class="text-xs text-gray-400 mt-0.5">Very High</p>
        @if($vhCount > 0)<p class="text-[10px] text-red-400 font-medium mt-0.5 flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse"></span>Urgent</p>@endif
    </a>

    {{-- Closed Today --}}
    <div class="bg-white rounded-2xl border-2 {{ $todayCl > 0 ? 'border-green-200' : 'border-gray-200' }} shadow-sm p-4 transition-all">
        <div class="w-9 h-9 rounded-xl {{ $todayCl > 0 ? 'bg-green-50' : 'bg-gray-100' }} flex items-center justify-center mb-3">
            <i class="fas fa-check-circle {{ $todayCl > 0 ? 'text-green-600' : 'text-gray-400' }} text-sm"></i>
        </div>
        <p class="text-2xl font-bold {{ $todayCl > 0 ? 'text-green-700' : 'text-gray-800' }}">{{ $todayCl }}</p>
        <p class="text-xs text-gray-400 mt-0.5">Closed Today</p>
        <p class="text-[10px] text-gray-300 mt-0.5">{{ $stats['closed'] ?? 0 }} total closed</p>
    </div>
</div>

{{-- ── Row 3: Status Strip ─────────────────────────────────────────────────── --}}
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm px-5 py-4">
    <div class="flex items-center justify-between mb-3">
        <div class="flex items-center gap-2">
            <div class="w-6 h-6 rounded-lg bg-gray-100 flex items-center justify-center">
                <i class="fas fa-chart-bar text-gray-500 text-xs"></i>
            </div>
            <p class="text-xs font-semibold text-gray-600 uppercase tracking-wider">My Ticket Status</p>
        </div>
        <a href="{{ route('ticket.index') }}" class="text-xs font-semibold text-red-700 hover:text-red-800">View all →</a>
    </div>
    <div class="grid grid-cols-4 sm:grid-cols-8 gap-2">
        @foreach($statusCfg as $key => $cfg)
        @php $count = $stats[$key] ?? 0; $total = max($stats['total'] ?? 1, 1); $pct = round($count / $total * 100); @endphp
        <div class="text-center p-2 rounded-xl hover:{{ $cfg['bg'] }} transition-colors cursor-default group">
            <p class="text-lg font-bold text-gray-800 group-hover:{{ $cfg['text'] }} transition-colors">{{ $count }}</p>
            <div class="flex items-center justify-center gap-1 mt-1 mb-1.5">
                <span class="w-1.5 h-1.5 rounded-full {{ $cfg['dot'] }} flex-shrink-0"></span>
                <p class="text-[10px] text-gray-400 leading-tight">{{ $cfg['label'] }}</p>
            </div>
            <div class="w-full bg-gray-100 rounded-full h-1">
                <div class="{{ $cfg['dot'] }} h-1 rounded-full" style="width:{{ $pct }}%"></div>
            </div>
        </div>
        @endforeach
    </div>
</div>

{{-- ── Row 4: Chart + Priority Donut ──────────────────────────────────────── --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

    <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
        <div class="flex items-center justify-between mb-4">
            <div>
                <p class="text-sm font-semibold text-gray-800">My Ticket Activity</p>
                <p class="text-xs text-gray-400 mt-0.5">Last 30 days</p>
            </div>
            <span class="text-xs text-gray-300">{{ now()->format('d M Y') }}</span>
        </div>
        <canvas id="dsuTicketChart" height="90"></canvas>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 flex flex-col">
        <div class="flex items-center justify-between mb-4">
            <p class="text-sm font-semibold text-gray-800">Priority (Active)</p>
            <a href="{{ route('ticket.index') }}" class="text-xs text-red-700 hover:text-red-800 font-semibold">All →</a>
        </div>
        <div class="flex-1 flex items-center justify-center">
            <canvas id="dsuPrioChart" width="140" height="140"></canvas>
        </div>
        <div class="grid grid-cols-2 gap-x-4 gap-y-2 mt-4">
            @foreach(['Very High','High','Medium','Low'] as $prio)
            @php $pc = $prioCfg[$prio] ?? []; $cnt = $prioBd[$prio] ?? 0; @endphp
            <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full {{ $pc['dot'] ?? 'bg-gray-300' }} flex-shrink-0"></span>
                <span class="text-xs text-gray-500 flex-1 truncate">{{ $prio }}</span>
                <span class="text-xs font-bold text-gray-700">{{ $cnt }}</span>
            </div>
            @endforeach
        </div>
    </div>
</div>

{{-- ── Row 5: Urgent + Quick Actions ──────────────────────────────────────── --}}
@if($urgents->isNotEmpty())
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
        <div class="flex items-center gap-2">
            <div class="w-7 h-7 rounded-lg bg-red-50 flex items-center justify-center"><i class="fas fa-exclamation-triangle text-red-600 text-xs"></i></div>
            <p class="text-sm font-semibold text-gray-800">Needs Immediate Attention</p>
        </div>
        <a href="{{ route('ticket.index') }}" class="text-xs font-semibold text-red-700 hover:text-red-800">View all →</a>
    </div>
    <div class="divide-y divide-gray-50">
        @foreach($urgents as $t)
        @php
            $sc = $statusCfg[$t->status] ?? ['dot'=>'bg-gray-400','text'=>'text-gray-500','bg'=>'bg-gray-100','label'=>'Unknown'];
            $isBreached = ($t->sla_status ?? '') === 'breached';
            $isVH = ($t->ticket_priority ?? '') === 'Very High';
        @endphp
        <a href="{{ route('ticket.show', $t->ticket_id) }}" class="flex items-center gap-3 px-5 py-3.5 hover:bg-gray-50/80 transition-colors group">
            <span class="w-2 h-2 rounded-full {{ $sc['dot'] }} flex-shrink-0 mt-0.5"></span>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-xs font-bold text-gray-700 group-hover:text-red-700 transition-colors font-mono">#{{ $t->ticket_number }}</span>
                    @if($isVH)<span class="text-[10px] font-bold text-red-700 bg-red-50 px-1.5 py-0.5 rounded-full"><i class="fas fa-bolt text-[8px] mr-0.5"></i>Very High</span>@endif
                    @if($isBreached)<span class="text-[10px] font-bold text-red-600 bg-red-50 px-1.5 py-0.5 rounded-full"><i class="fas fa-stopwatch text-[8px] mr-0.5"></i>SLA Breached</span>@endif
                </div>
                <p class="text-[11px] text-gray-400 truncate mt-0.5">{{ $t->customer_name ?? '—' }}@if($t->sla_due_at) &middot; Due {{ \Carbon\Carbon::parse($t->sla_due_at)->diffForHumans() }}@endif</p>
                <p class="text-[11px] text-gray-500 truncate">{{ \Str::limit($t->description ?? '', 60) }}</p>
            </div>
            <span class="text-[10px] font-semibold {{ $sc['text'] }} {{ $sc['bg'] }} px-2 py-0.5 rounded-full whitespace-nowrap flex-shrink-0">{{ $sc['label'] }}</span>
        </a>
        @endforeach
    </div>
</div>
@endif

{{-- ── Row 6: Recent Tickets ───────────────────────────────────────────────── --}}
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
        <div class="flex items-center gap-2">
            <div class="w-7 h-7 rounded-lg bg-red-50 flex items-center justify-center"><i class="fas fa-history text-red-600 text-xs"></i></div>
            <p class="text-sm font-semibold text-gray-800">Recent Activity</p>
        </div>
        <a href="{{ route('ticket.index') }}" class="text-xs font-semibold text-red-700 hover:text-red-800">View all →</a>
    </div>

    @if($recents->isEmpty())
    <div class="flex flex-col items-center justify-center py-14 text-center">
        <div class="w-14 h-14 rounded-full bg-gray-100 flex items-center justify-center mb-3">
            <i class="fas fa-ticket-alt text-gray-300 text-2xl"></i>
        </div>
        <p class="text-sm font-semibold text-gray-500">No tickets yet</p>
        <p class="text-xs text-gray-400 mt-1">Tickets assigned to you will appear here</p>
    </div>
    @else
    <div class="hidden md:grid grid-cols-12 gap-2 px-5 py-2.5 bg-gray-50/80 border-b border-gray-100 text-[10px] font-bold text-gray-400 uppercase tracking-wider">
        <div class="col-span-2">Ticket #</div>
        <div class="col-span-3">Description</div>
        <div class="col-span-2">Customer</div>
        <div class="col-span-1 text-center">Role</div>
        <div class="col-span-1 text-center">Priority</div>
        <div class="col-span-1 text-center">SLA</div>
        <div class="col-span-1 text-center">Updated</div>
        <div class="col-span-1 text-right">Status</div>
    </div>
    <div class="divide-y divide-gray-50">
        @foreach($recents as $t)
        @php
            $sc  = $statusCfg[$t->status] ?? ['dot'=>'bg-gray-400','text'=>'text-gray-500','bg'=>'bg-gray-100','label'=>'Unknown'];
            $pc  = $prioCfg[$t->ticket_priority ?? ''] ?? ['text'=>'text-gray-400','bg'=>'bg-gray-100','dot'=>'bg-gray-300'];
            $slaSc = match($t->sla_status ?? '') { 'met'=>'text-green-600 bg-green-50','breached'=>'text-red-600 bg-red-50','pending'=>'text-blue-600 bg-blue-50','paused'=>'text-amber-600 bg-amber-50',default=>'text-gray-400 bg-gray-100' };
            $slaLabel = match($t->sla_status ?? '') { 'met'=>'Met','breached'=>'Breached','pending'=>'Active','paused'=>'Paused',default=>'—' };
        @endphp
        <a href="{{ route('ticket.show', $t->ticket_id) }}" class="flex md:grid md:grid-cols-12 md:gap-2 items-center px-5 py-3 hover:bg-gray-50/80 transition-colors group">
            <div class="col-span-2 flex items-center gap-2">
                <span class="w-1.5 h-1.5 rounded-full {{ $sc['dot'] }} flex-shrink-0"></span>
                <span class="text-xs font-bold text-gray-700 group-hover:text-red-700 transition-colors font-mono">{{ $t->ticket_number }}</span>
            </div>
            <div class="col-span-3 hidden md:block">
                <span class="text-xs text-gray-500 truncate block">{{ \Str::limit($t->description ?? '—', 38) }}</span>
            </div>
            <div class="col-span-2 hidden md:block">
                <span class="text-xs text-gray-600 truncate block">{{ $t->customer_name ?? '—' }}</span>
            </div>
            <div class="col-span-1 hidden md:flex justify-center">
                @if($t->is_pic)
                <span class="text-[10px] font-bold text-purple-700 bg-purple-50 px-1.5 py-0.5 rounded-full">PIC</span>
                @else
                <span class="text-[10px] text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded-full">Member</span>
                @endif
            </div>
            <div class="col-span-1 hidden md:flex justify-center">
                @if($t->ticket_priority)<span class="text-[10px] font-bold {{ $pc['text'] }} {{ $pc['bg'] }} px-1.5 py-0.5 rounded-full whitespace-nowrap">{{ $t->ticket_priority }}</span>@else<span class="text-gray-300 text-xs">—</span>@endif
            </div>
            <div class="col-span-1 hidden md:flex justify-center">
                <span class="text-[10px] font-semibold {{ $slaSc }} px-1.5 py-0.5 rounded-full whitespace-nowrap">{{ $slaLabel }}</span>
            </div>
            <div class="col-span-1 hidden md:block text-center">
                <span class="text-[10px] text-gray-400">{{ \Carbon\Carbon::parse($t->updated_at)->diffForHumans(null, true) }}</span>
            </div>
            <div class="col-span-1 flex md:justify-end ml-auto md:ml-0">
                <span class="text-[10px] font-semibold {{ $sc['text'] }} {{ $sc['bg'] }} px-2 py-0.5 rounded-full whitespace-nowrap">{{ $sc['label'] }}</span>
            </div>
        </a>
        @endforeach
    </div>
    @endif
</div>

{{-- ── Row 7: Quick Actions ────────────────────────────────────────────────── --}}
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
    @php
        $quickActions = [
            ['href'=>route('ticket.index'), 'icon'=>'fa-ticket-alt',      'bg'=>'bg-red-50',    'color'=>'text-red-700',    'border'=>'hover:border-red-300',    'label'=>'All Tickets',  'sub'=>($stats['total'] ?? 0) . ' tickets'],
            ['href'=>route('ticket.task'),  'icon'=>'fa-tasks',           'bg'=>'bg-blue-50',   'color'=>'text-blue-600',   'border'=>'hover:border-blue-300',   'label'=>'My Tasks',     'sub'=>'View assigned tasks'],
            ['href'=>route('calendar.timesheets'), 'icon'=>'fa-clock',    'bg'=>'bg-teal-50',   'color'=>'text-teal-600',   'border'=>'hover:border-teal-300',   'label'=>'Timesheets',   'sub'=>'Log your hours'],
            ['href'=>route('profile.my'),   'icon'=>'fa-user-circle',     'bg'=>'bg-gray-100',  'color'=>'text-gray-600',   'border'=>'hover:border-gray-300',   'label'=>'My Profile',   'sub'=>'View & edit profile'],
        ];
    @endphp
    @foreach($quickActions as $qa)
    <a href="{{ $qa['href'] }}" class="flex items-center gap-3 p-4 bg-white rounded-2xl border-2 border-gray-200 {{ $qa['border'] }} hover:shadow-md transition-all group">
        <div class="w-10 h-10 rounded-xl {{ $qa['bg'] }} flex items-center justify-center flex-shrink-0 group-hover:scale-110 transition-transform">
            <i class="fas {{ $qa['icon'] }} {{ $qa['color'] }} text-base"></i>
        </div>
        <div class="min-w-0">
            <p class="text-sm font-semibold text-gray-800 group-hover:text-gray-900">{{ $qa['label'] }}</p>
            <p class="text-xs text-gray-400 truncate">{{ $qa['sub'] }}</p>
        </div>
    </a>
    @endforeach
</div>

</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    // Clock
    function tick() { const el = document.getElementById('dsuClock'); if (el) el.textContent = new Date().toLocaleTimeString('id-ID', { hour:'2-digit', minute:'2-digit', second:'2-digit' }); }
    tick(); setInterval(tick, 1000);

    // Activity chart
    const labels    = @json($data['ticket_chart']['labels'] ?? []);
    const chartData = @json($data['ticket_chart']['data'] ?? []);
    const ctx = document.getElementById('dsuTicketChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    data: chartData,
                    borderColor: '#dc2626',
                    backgroundColor: 'rgba(220,38,38,0.07)',
                    borderWidth: 2.5,
                    pointRadius: 3,
                    pointBackgroundColor: '#dc2626',
                    pointHoverRadius: 5,
                    tension: 0.4,
                    fill: true,
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: c => c.parsed.y + ' ticket' + (c.parsed.y !== 1 ? 's' : '') } }
                },
                scales: {
                    x: { grid:{ display:false }, ticks:{ font:{ size:10 }, maxTicksLimit:10, color:'#9ca3af' } },
                    y: { beginAtZero:true, grid:{ color:'rgba(0,0,0,0.04)' }, ticks:{ stepSize:1, precision:0, color:'#9ca3af', font:{ size:10 } } }
                }
            }
        });
    }

    // Priority donut
    const prioCtx = document.getElementById('dsuPrioChart');
    if (prioCtx) {
        const pd  = { 'Very High': @json($data['priority_breakdown']['Very High'] ?? 0), 'High': @json($data['priority_breakdown']['High'] ?? 0), 'Medium': @json($data['priority_breakdown']['Medium'] ?? 0), 'Low': @json($data['priority_breakdown']['Low'] ?? 0) };
        const tot = Object.values(pd).reduce((a,b) => a+b, 0);
        new Chart(prioCtx, {
            type: 'doughnut',
            data: {
                labels: Object.keys(pd),
                datasets: [{ data: Object.values(pd), backgroundColor:['#dc2626','#ea580c','#ca8a04','#2563eb'], borderColor:'#fff', borderWidth:2, hoverOffset:4 }]
            },
            options: {
                responsive: false,
                cutout: '68%',
                plugins: { legend:{ display:false }, tooltip:{ callbacks:{ label: c => c.label+': '+c.raw+' ('+(tot>0?Math.round(c.raw/tot*100):0)+'%)' } } }
            },
            plugins: [{ id:'center', beforeDraw(chart) {
                const { width, height, ctx } = chart;
                ctx.save();
                ctx.font = 'bold 20px Inter,Arial,sans-serif'; ctx.fillStyle = '#1f2937'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
                ctx.fillText(tot, width/2, height/2 - 6);
                ctx.font = '10px Inter,Arial,sans-serif'; ctx.fillStyle = '#9ca3af';
                ctx.fillText('active', width/2, height/2 + 12);
                ctx.restore();
            }}]
        });
    }
})();
</script>
@endpush

@elseif(($user['type'] ?? '') === 'employee' && ($user['role']['id'] ?? 0) == \App\Enums\RoleId::DELIVERY_HELPDESK->value)
{{-- ===================== HELPDESK DASHBOARD ===================== --}}
@php
    $stats     = $data['ticket_stats']      ?? [];
    $urgents   = $data['urgent_tickets']    ?? collect();
    $recents   = $data['recent_tickets']    ?? collect();
    $prioBd    = $data['priority_breakdown'] ?? [];
    $stagPend  = $data['staging_pending']   ?? 0;
    $unassign  = $data['unassigned_count']  ?? 0;
    $slaBreachCount = $data['sla_breached'] ?? 0;
    $slaWarnCount   = $data['sla_warning']  ?? 0;
    $slaCr     = $data['sla_compliance']    ?? null;

    $hour = now()->hour;
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    $firstName = explode(' ', $user['name'] ?? 'Helpdesk')[0];

    $activeCount = ($stats['open'] ?? 0) + ($stats['inprocess'] ?? 0)
                 + ($stats['waiting_on_customer'] ?? 0) + ($stats['waiting_on_3rd_party'] ?? 0)
                 + ($stats['waiting_to_confirmation'] ?? 0) + ($stats['hold'] ?? 0);

    $statusCfg = [
        'open'                    => ['label'=>'Open',           'dot'=>'bg-blue-500',   'text'=>'text-blue-700',   'bg'=>'bg-blue-50'   ],
        'inprocess'               => ['label'=>'In Process',     'dot'=>'bg-yellow-500', 'text'=>'text-yellow-700', 'bg'=>'bg-yellow-50' ],
        'waiting_on_customer'     => ['label'=>'Wait Customer',  'dot'=>'bg-amber-500',  'text'=>'text-amber-700',  'bg'=>'bg-amber-50'  ],
        'waiting_on_3rd_party'    => ['label'=>'Wait 3rd Party', 'dot'=>'bg-indigo-500', 'text'=>'text-indigo-700', 'bg'=>'bg-indigo-50' ],
        'waiting_to_confirmation' => ['label'=>'Wait Confirm',   'dot'=>'bg-teal-500',   'text'=>'text-teal-700',   'bg'=>'bg-teal-50'   ],
        'hold'                    => ['label'=>'Hold',           'dot'=>'bg-orange-500', 'text'=>'text-orange-700', 'bg'=>'bg-orange-50' ],
        'cancelled'               => ['label'=>'Cancelled',      'dot'=>'bg-gray-400',   'text'=>'text-gray-500',   'bg'=>'bg-gray-100'  ],
        'closed'                  => ['label'=>'Closed',         'dot'=>'bg-green-500',  'text'=>'text-green-700',  'bg'=>'bg-green-50'  ],
    ];
    $prioCfg = [
        'Very High' => ['text'=>'text-red-700',    'bg'=>'bg-red-50',    'dot'=>'bg-red-500'   ],
        'High'      => ['text'=>'text-orange-700', 'bg'=>'bg-orange-50', 'dot'=>'bg-orange-500'],
        'Medium'    => ['text'=>'text-yellow-700', 'bg'=>'bg-yellow-50', 'dot'=>'bg-yellow-500'],
        'Low'       => ['text'=>'text-blue-700',   'bg'=>'bg-blue-50',   'dot'=>'bg-blue-400'  ],
    ];
    $todayNew    = DB::table('ticket')->whereNull('deleted_at')->whereDate('created_at', today())->count();
    $todayClosed = DB::table('ticket')->whereNull('deleted_at')->whereDate('updated_at', today())->where('status','closed')->count();
@endphp

<div class="space-y-5">

{{-- ── Row 1: Greeting bar ─────────────────────────────────────────────────── --}}
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
    <div>
        <div class="flex items-center gap-2">
            <h2 class="text-xl font-bold text-gray-800">{{ $greeting }}, {{ $firstName }}</h2>
            <span class="text-xs font-semibold text-red-700 bg-red-50 border border-red-100 px-2.5 py-0.5 rounded-full">Helpdesk</span>
        </div>
        <p class="text-xs text-gray-400 mt-0.5">{{ now()->isoFormat('dddd, D MMMM Y') }} &mdash; <span id="hdClock" class="font-mono"></span></p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        @if($stagPend > 0)
        <a href="{{ route('staging.index') }}" class="inline-flex items-center gap-1.5 bg-amber-50 border border-amber-200 text-amber-700 text-xs font-semibold px-3 py-1.5 rounded-xl hover:bg-amber-100 transition">
            <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>{{ $stagPend }} pending validation
        </a>
        @endif
        @if($slaBreachCount > 0)
        <a href="{{ route('sla.report') }}" class="inline-flex items-center gap-1.5 bg-red-50 border border-red-200 text-red-700 text-xs font-semibold px-3 py-1.5 rounded-xl hover:bg-red-100 transition">
            <i class="fas fa-exclamation-circle text-xs"></i>{{ $slaBreachCount }} SLA breached
        </a>
        @endif
        @if($unassign > 0)
        <a href="{{ route('ticket.index') }}" class="inline-flex items-center gap-1.5 bg-indigo-50 border border-indigo-200 text-indigo-700 text-xs font-semibold px-3 py-1.5 rounded-xl hover:bg-indigo-100 transition">
            <i class="fas fa-user-clock text-xs"></i>{{ $unassign }} unassigned
        </a>
        @endif
    </div>
</div>

{{-- ── Row 2: KPI Cards ────────────────────────────────────────────────────── --}}
<div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-4">
    <a href="{{ route('staging.index') }}" class="relative bg-white rounded-2xl border-2 {{ $stagPend > 0 ? 'border-amber-300' : 'border-gray-200' }} shadow-sm p-4 hover:shadow-md transition-all group overflow-hidden">
        <div class="w-9 h-9 rounded-xl {{ $stagPend > 0 ? 'bg-amber-100' : 'bg-gray-100' }} flex items-center justify-center mb-3">
            <i class="fas fa-inbox {{ $stagPend > 0 ? 'text-amber-600' : 'text-gray-400' }} text-sm"></i>
        </div>
        <p class="text-2xl font-bold {{ $stagPend > 0 ? 'text-amber-600' : 'text-gray-800' }}">{{ $stagPend }}</p>
        <p class="text-xs text-gray-400 mt-0.5">Pending Validation</p>
        @if($stagPend > 0)<p class="text-[10px] text-amber-500 font-medium mt-1">Requires action →</p>@endif
    </a>

    <a href="{{ route('ticket.index') }}" class="relative bg-white rounded-2xl border-2 {{ $unassign > 0 ? 'border-indigo-300' : 'border-gray-200' }} shadow-sm p-4 hover:shadow-md transition-all group">
        <div class="w-9 h-9 rounded-xl {{ $unassign > 0 ? 'bg-indigo-100' : 'bg-gray-100' }} flex items-center justify-center mb-3">
            <i class="fas fa-user-clock {{ $unassign > 0 ? 'text-indigo-600' : 'text-gray-400' }} text-sm"></i>
        </div>
        <p class="text-2xl font-bold {{ $unassign > 0 ? 'text-indigo-700' : 'text-gray-800' }}">{{ $unassign }}</p>
        <p class="text-xs text-gray-400 mt-0.5">Unassigned</p>
        @if($unassign > 0)<p class="text-[10px] text-indigo-500 font-medium mt-1">Need assignment →</p>@endif
    </a>

    <a href="{{ route('ticket.index') }}" class="bg-white rounded-2xl border-2 border-gray-200 shadow-sm p-4 hover:border-blue-300 hover:shadow-md transition-all group">
        <div class="w-9 h-9 rounded-xl bg-blue-50 group-hover:bg-blue-100 flex items-center justify-center mb-3 transition">
            <i class="fas fa-ticket-alt text-blue-600 text-sm"></i>
        </div>
        <p class="text-2xl font-bold text-gray-800">{{ $activeCount }}</p>
        <p class="text-xs text-gray-400 mt-0.5">Active Tickets</p>
        <p class="text-[10px] text-gray-300 mt-1">{{ $stats['total'] ?? 0 }} total</p>
    </a>

    <a href="{{ route('ticket.index') }}" class="relative bg-white rounded-2xl border-2 {{ ($data['very_high_count'] ?? 0) > 0 ? 'border-red-300' : 'border-gray-200' }} shadow-sm p-4 hover:shadow-md transition-all group">
        <div class="w-9 h-9 rounded-xl {{ ($data['very_high_count'] ?? 0) > 0 ? 'bg-red-100' : 'bg-gray-100' }} flex items-center justify-center mb-3">
            <i class="fas fa-bolt {{ ($data['very_high_count'] ?? 0) > 0 ? 'text-red-600' : 'text-gray-400' }} text-sm"></i>
        </div>
        <p class="text-2xl font-bold {{ ($data['very_high_count'] ?? 0) > 0 ? 'text-red-700' : 'text-gray-800' }}">{{ $data['very_high_count'] ?? 0 }}</p>
        <p class="text-xs text-gray-400 mt-0.5">Very High Priority</p>
        @if(($data['very_high_count'] ?? 0) > 0)
        <p class="text-[10px] text-red-400 font-medium mt-1 flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse"></span>Urgent</p>
        @endif
    </a>

    <a href="{{ route('sla.report') }}" class="bg-white rounded-2xl border-2 {{ $slaBreachCount > 0 ? 'border-red-200' : ($slaWarnCount > 0 ? 'border-orange-200' : 'border-gray-200') }} shadow-sm p-4 hover:shadow-md transition-all group">
        <div class="w-9 h-9 rounded-xl {{ $slaBreachCount > 0 ? 'bg-red-50' : ($slaWarnCount > 0 ? 'bg-orange-50' : 'bg-gray-100') }} flex items-center justify-center mb-3">
            <i class="fas fa-stopwatch {{ $slaBreachCount > 0 ? 'text-red-600' : ($slaWarnCount > 0 ? 'text-orange-500' : 'text-gray-400') }} text-sm"></i>
        </div>
        <p class="text-2xl font-bold {{ $slaBreachCount > 0 ? 'text-red-700' : 'text-gray-800' }}">{{ $slaBreachCount }}</p>
        <p class="text-xs text-gray-400 mt-0.5">SLA Breached</p>
        @if($slaWarnCount > 0)<p class="text-[10px] text-orange-500 font-medium mt-1">+{{ $slaWarnCount }} warning (4h)</p>@endif
    </a>

    <a href="{{ route('sla.report') }}" class="bg-white rounded-2xl border-2 border-gray-200 shadow-sm p-4 hover:border-emerald-300 hover:shadow-md transition-all group">
        <div class="w-9 h-9 rounded-xl bg-emerald-50 group-hover:bg-emerald-100 flex items-center justify-center mb-3 transition">
            <i class="fas fa-chart-pie text-emerald-600 text-sm"></i>
        </div>
        @if($slaCr !== null)
        <p class="text-2xl font-bold {{ $slaCr >= 80 ? 'text-emerald-600' : ($slaCr >= 60 ? 'text-yellow-600' : 'text-red-600') }}">{{ $slaCr }}%</p>
        @else
        <p class="text-2xl font-bold text-gray-400">—</p>
        @endif
        <p class="text-xs text-gray-400 mt-0.5">SLA Compliance</p>
        @if($slaCr !== null)
        <div class="mt-1.5 w-full bg-gray-100 rounded-full h-1">
            <div class="h-1 rounded-full {{ $slaCr >= 80 ? 'bg-emerald-500' : ($slaCr >= 60 ? 'bg-yellow-500' : 'bg-red-500') }}" style="width:{{ min($slaCr, 100) }}%"></div>
        </div>
        @endif
    </a>
</div>

{{-- ── Row 3: Status Strip ─────────────────────────────────────────────────── --}}
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm px-5 py-4">
    <div class="flex items-center justify-between mb-3">
        <div class="flex items-center gap-2">
            <div class="w-6 h-6 rounded-lg bg-gray-100 flex items-center justify-center"><i class="fas fa-chart-bar text-gray-500 text-xs"></i></div>
            <p class="text-xs font-semibold text-gray-600 uppercase tracking-wider">Ticket Status Breakdown</p>
        </div>
        <a href="{{ route('ticket.index') }}" class="text-xs font-semibold text-red-700 hover:text-red-800">View all →</a>
    </div>
    <div class="grid grid-cols-4 sm:grid-cols-8 gap-2">
        @foreach($statusCfg as $key => $cfg)
        @php $count = $stats[$key] ?? 0; $total = max($stats['total'] ?? 1, 1); $pct = round($count / $total * 100); @endphp
        <div class="text-center p-2 rounded-xl hover:{{ $cfg['bg'] }} transition-colors cursor-default">
            <p class="text-lg font-bold text-gray-800">{{ $count }}</p>
            <div class="flex items-center justify-center gap-1 mt-1 mb-1.5">
                <span class="w-1.5 h-1.5 rounded-full {{ $cfg['dot'] }} flex-shrink-0"></span>
                <p class="text-[10px] text-gray-400 leading-tight">{{ $cfg['label'] }}</p>
            </div>
            <div class="w-full bg-gray-100 rounded-full h-1">
                <div class="{{ $cfg['dot'] }} h-1 rounded-full" style="width:{{ $pct }}%"></div>
            </div>
        </div>
        @endforeach
    </div>
</div>

{{-- ── Row 4: Chart + Priority Donut ──────────────────────────────────────── --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
        <div class="flex items-center justify-between mb-4">
            <div>
                <p class="text-sm font-semibold text-gray-800">Ticket Volume</p>
                <p class="text-xs text-gray-400 mt-0.5">Last 30 days</p>
            </div>
            <span class="text-xs text-gray-300">{{ now()->format('d M Y') }}</span>
        </div>
        <canvas id="hdTicketChart" height="90"></canvas>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 flex flex-col">
        <div class="flex items-center justify-between mb-4">
            <p class="text-sm font-semibold text-gray-800">Priority (Active)</p>
            <a href="{{ route('ticket.index') }}" class="text-xs text-red-700 hover:text-red-800 font-semibold">All →</a>
        </div>
        <div class="flex-1 flex items-center justify-center">
            <canvas id="hdPrioChart" width="140" height="140"></canvas>
        </div>
        <div class="grid grid-cols-2 gap-x-4 gap-y-2 mt-4">
            @foreach(['Very High','High','Medium','Low'] as $prio)
            @php $pc = $prioCfg[$prio] ?? []; $cnt = $prioBd[$prio] ?? 0; @endphp
            <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full {{ $pc['dot'] ?? 'bg-gray-300' }} flex-shrink-0"></span>
                <span class="text-xs text-gray-500 flex-1 truncate">{{ $prio }}</span>
                <span class="text-xs font-bold text-gray-700">{{ $cnt }}</span>
            </div>
            @endforeach
        </div>
    </div>
</div>

{{-- ── Row 5: Urgent + Quick Actions ──────────────────────────────────────── --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

    <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <div class="flex items-center gap-2">
                <div class="w-7 h-7 rounded-lg bg-red-50 flex items-center justify-center"><i class="fas fa-exclamation-triangle text-red-600 text-xs"></i></div>
                <p class="text-sm font-semibold text-gray-800">Needs Attention</p>
                <span class="text-[10px] text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">Very High + SLA Breached</span>
            </div>
            <a href="{{ route('ticket.index') }}" class="text-xs font-semibold text-red-700 hover:text-red-800">View all →</a>
        </div>
        @if($urgents->isEmpty())
        <div class="flex flex-col items-center justify-center py-12 text-center">
            <div class="w-12 h-12 rounded-full bg-green-50 flex items-center justify-center mb-3">
                <i class="fas fa-check-circle text-green-500 text-xl"></i>
            </div>
            <p class="text-sm font-semibold text-gray-600">All clear!</p>
            <p class="text-xs text-gray-400 mt-1">No urgent tickets at the moment</p>
        </div>
        @else
        <div class="divide-y divide-gray-50">
            @foreach($urgents as $t)
            @php
                $sc = $statusCfg[$t->status] ?? ['dot'=>'bg-gray-400','text'=>'text-gray-500','bg'=>'bg-gray-100','label'=>'Unknown'];
                $isBreached = ($t->sla_status ?? '') === 'breached';
                $isVH = ($t->ticket_priority ?? '') === 'Very High';
            @endphp
            <a href="{{ route('ticket.show', $t->ticket_id) }}" class="flex items-center gap-3 px-5 py-3.5 hover:bg-gray-50/80 transition-colors group">
                <span class="w-2 h-2 rounded-full {{ $sc['dot'] }} flex-shrink-0 mt-0.5"></span>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-xs font-bold text-gray-700 group-hover:text-red-700 transition-colors font-mono">#{{ $t->ticket_number }}</span>
                        @if($isBreached)<span class="text-[10px] font-bold text-red-600 bg-red-50 px-1.5 py-0.5 rounded-full"><i class="fas fa-stopwatch text-[8px] mr-0.5"></i>SLA Breached</span>@endif
                        @if($isVH)<span class="text-[10px] font-bold text-red-700 bg-red-50 px-1.5 py-0.5 rounded-full"><i class="fas fa-bolt text-[8px] mr-0.5"></i>Very High</span>@endif
                    </div>
                    <p class="text-[11px] text-gray-400 mt-0.5 truncate">{{ $t->customer_name ?? '—' }}@if($t->sla_due_at) &middot; Due {{ \Carbon\Carbon::parse($t->sla_due_at)->diffForHumans() }}@endif</p>
                    <p class="text-[11px] text-gray-500 truncate">{{ \Str::limit($t->description ?? '', 55) }}</p>
                </div>
                <span class="text-[10px] font-semibold {{ $sc['text'] }} {{ $sc['bg'] }} px-2 py-0.5 rounded-full whitespace-nowrap flex-shrink-0">{{ $sc['label'] }}</span>
            </a>
            @endforeach
        </div>
        @endif
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
        <p class="text-sm font-semibold text-gray-800 mb-4">Quick Actions</p>
        <div class="grid grid-cols-2 gap-2">
            @php
                $navItems = [
                    ['href'=>route('ticket.index'),               'icon'=>'fa-ticket-alt',     'bg'=>'bg-red-50',     'color'=>'text-red-700',     'label'=>'All Tickets',  'badge'=>0],
                    ['href'=>route('staging.index'),              'icon'=>'fa-clipboard-check','bg'=>'bg-amber-50',   'color'=>'text-amber-600',   'label'=>'Validation',   'badge'=>$stagPend],
                    ['href'=>route('ticket.consultant-workload'), 'icon'=>'fa-users-cog',      'bg'=>'bg-blue-50',    'color'=>'text-blue-600',    'label'=>'Workload',     'badge'=>0],
                    ['href'=>route('sla.report'),                 'icon'=>'fa-stopwatch',      'bg'=>'bg-emerald-50', 'color'=>'text-emerald-600', 'label'=>'SLA Report',   'badge'=>$slaBreachCount],
                    ['href'=>route('sla.config'),                 'icon'=>'fa-sliders-h',      'bg'=>'bg-indigo-50',  'color'=>'text-indigo-600',  'label'=>'SLA Config',   'badge'=>0],
                    ['href'=>route('reporting'),                  'icon'=>'fa-chart-bar',      'bg'=>'bg-purple-50',  'color'=>'text-purple-600',  'label'=>'Reporting',    'badge'=>0],
                    ['href'=>route('calendar.timesheets'),        'icon'=>'fa-clock',          'bg'=>'bg-teal-50',    'color'=>'text-teal-600',    'label'=>'Timesheets',   'badge'=>0],
                    ['href'=>route('profile.my'),                 'icon'=>'fa-user-circle',    'bg'=>'bg-gray-100',   'color'=>'text-gray-600',    'label'=>'My Profile',   'badge'=>0],
                ];
            @endphp
            @foreach($navItems as $nav)
            <a href="{{ $nav['href'] }}" class="relative flex flex-col items-center gap-1.5 p-3 rounded-xl {{ $nav['bg'] }} hover:ring-2 hover:ring-offset-1 hover:ring-gray-300 transition-all text-center">
                @if(!empty($nav['badge']) && $nav['badge'] > 0)
                <span class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 text-white text-[9px] font-bold rounded-full flex items-center justify-center leading-none">{{ $nav['badge'] > 9 ? '9+' : $nav['badge'] }}</span>
                @endif
                <i class="fas {{ $nav['icon'] }} {{ $nav['color'] }} text-base"></i>
                <p class="text-[11px] font-semibold text-gray-600 leading-tight">{{ $nav['label'] }}</p>
            </a>
            @endforeach
        </div>
        <div class="mt-4 pt-4 border-t border-gray-100">
            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-2">Today's Summary</p>
            <div class="space-y-1.5">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-gray-500 flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-blue-400"></span>New today</span>
                    <span class="text-xs font-bold text-gray-700">{{ $todayNew }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-xs text-gray-500 flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-green-400"></span>Closed today</span>
                    <span class="text-xs font-bold text-gray-700">{{ $todayClosed }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-xs text-gray-500 flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-red-400"></span>SLA breached</span>
                    <span class="text-xs font-bold {{ $slaBreachCount > 0 ? 'text-red-600' : 'text-gray-700' }}">{{ $slaBreachCount }}</span>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── Row 6: Recent Tickets ───────────────────────────────────────────────── --}}
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
        <div class="flex items-center gap-2">
            <div class="w-7 h-7 rounded-lg bg-red-50 flex items-center justify-center"><i class="fas fa-history text-red-600 text-xs"></i></div>
            <p class="text-sm font-semibold text-gray-800">Recent Tickets</p>
        </div>
        <a href="{{ route('ticket.index') }}" class="text-xs font-semibold text-red-700 hover:text-red-800">View all →</a>
    </div>
    @if($recents->isEmpty())
    <div class="py-12 text-center text-sm text-gray-400">No tickets yet</div>
    @else
    <div class="hidden md:grid grid-cols-12 gap-2 px-5 py-2.5 bg-gray-50/80 border-b border-gray-100 text-[10px] font-bold text-gray-400 uppercase tracking-wider">
        <div class="col-span-2">Ticket #</div>
        <div class="col-span-3">Description</div>
        <div class="col-span-2">Customer</div>
        <div class="col-span-2">PIC</div>
        <div class="col-span-1 text-center">Priority</div>
        <div class="col-span-1 text-center">SLA</div>
        <div class="col-span-1 text-right">Status</div>
    </div>
    <div class="divide-y divide-gray-50">
        @foreach($recents as $t)
        @php
            $sc  = $statusCfg[$t->status] ?? ['dot'=>'bg-gray-400','text'=>'text-gray-500','bg'=>'bg-gray-100','label'=>'Unknown'];
            $pc  = $prioCfg[$t->ticket_priority ?? ''] ?? ['text'=>'text-gray-400','bg'=>'bg-gray-100','dot'=>'bg-gray-300'];
            $slaSc = match($t->sla_status ?? '') { 'met'=>'text-green-600 bg-green-50','breached'=>'text-red-600 bg-red-50','pending'=>'text-blue-600 bg-blue-50','paused'=>'text-amber-600 bg-amber-50',default=>'text-gray-400 bg-gray-100' };
            $slaLabel = match($t->sla_status ?? '') { 'met'=>'Met','breached'=>'Breached','pending'=>'Active','paused'=>'Paused',default=>'—' };
        @endphp
        <a href="{{ route('ticket.show', $t->ticket_id) }}" class="flex md:grid md:grid-cols-12 md:gap-2 items-center px-5 py-3 hover:bg-gray-50/80 transition-colors group">
            <div class="col-span-2 flex items-center gap-2">
                <span class="w-1.5 h-1.5 rounded-full {{ $sc['dot'] }} flex-shrink-0"></span>
                <span class="text-xs font-bold text-gray-700 group-hover:text-red-700 transition-colors font-mono">{{ $t->ticket_number }}</span>
            </div>
            <div class="col-span-3 hidden md:block">
                <span class="text-xs text-gray-500 truncate block">{{ \Str::limit($t->description ?? '—', 40) }}</span>
                <span class="text-[10px] text-gray-300">{{ \Carbon\Carbon::parse($t->updated_at)->diffForHumans() }}</span>
            </div>
            <div class="col-span-2 hidden md:block"><span class="text-xs text-gray-600 truncate block">{{ $t->customer_name ?? '—' }}</span></div>
            <div class="col-span-2 hidden md:block">
                <span class="text-xs text-gray-500 truncate block">{{ $t->pic_name ?? 'Unassigned' }}</span>
                @if(($t->pic_name ?? 'Unassigned') === 'Unassigned')<span class="text-[10px] text-indigo-500 font-semibold">Needs PIC</span>@endif
            </div>
            <div class="col-span-1 hidden md:flex justify-center">
                @if($t->ticket_priority)<span class="text-[10px] font-bold {{ $pc['text'] }} {{ $pc['bg'] }} px-1.5 py-0.5 rounded-full whitespace-nowrap">{{ $t->ticket_priority }}</span>@else<span class="text-gray-300 text-xs">—</span>@endif
            </div>
            <div class="col-span-1 hidden md:flex justify-center">
                <span class="text-[10px] font-semibold {{ $slaSc }} px-1.5 py-0.5 rounded-full whitespace-nowrap">{{ $slaLabel }}</span>
            </div>
            <div class="col-span-1 flex md:justify-end ml-auto md:ml-0">
                <span class="text-[10px] font-semibold {{ $sc['text'] }} {{ $sc['bg'] }} px-2 py-0.5 rounded-full whitespace-nowrap">{{ $sc['label'] }}</span>
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
(function () {
    function tick() { const el = document.getElementById('hdClock'); if (el) el.textContent = new Date().toLocaleTimeString('id-ID', { hour:'2-digit', minute:'2-digit', second:'2-digit' }); }
    tick(); setInterval(tick, 1000);

    const labels    = @json($data['ticket_chart']['labels'] ?? []);
    const chartData = @json($data['ticket_chart']['data']   ?? []);
    const ctx = document.getElementById('hdTicketChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    data: chartData,
                    backgroundColor: chartData.map(v => { const max = Math.max(...chartData,1); return `rgba(153,27,27,${0.3+(v/max)*0.65})`; }),
                    borderColor: '#991b1b', borderWidth: 0, borderRadius: 4, borderSkipped: false,
                }]
            },
            options: {
                responsive: true,
                plugins: { legend:{display:false}, tooltip:{callbacks:{label:c=>c.parsed.y+' ticket'+(c.parsed.y!==1?'s':'')}} },
                scales: {
                    x:{grid:{display:false},ticks:{font:{size:10},maxTicksLimit:10,color:'#9ca3af'}},
                    y:{beginAtZero:true,grid:{color:'rgba(0,0,0,0.04)'},ticks:{stepSize:1,precision:0,color:'#9ca3af',font:{size:10}}}
                }
            }
        });
    }

    const prioCtx = document.getElementById('hdPrioChart');
    if (prioCtx) {
        const pd = { 'Very High': @json($data['priority_breakdown']['Very High'] ?? 0), 'High': @json($data['priority_breakdown']['High'] ?? 0), 'Medium': @json($data['priority_breakdown']['Medium'] ?? 0), 'Low': @json($data['priority_breakdown']['Low'] ?? 0) };
        const tot = Object.values(pd).reduce((a,b)=>a+b,0);
        new Chart(prioCtx, {
            type: 'doughnut',
            data: { labels: Object.keys(pd), datasets: [{ data: Object.values(pd), backgroundColor:['#dc2626','#ea580c','#ca8a04','#2563eb'], borderColor:'#fff', borderWidth:2, hoverOffset:4 }] },
            options: { responsive:false, cutout:'68%', plugins:{ legend:{display:false}, tooltip:{callbacks:{label:c=>c.label+': '+c.raw+' ('+(tot>0?Math.round(c.raw/tot*100):0)+'%)'}} } },
            plugins: [{ id:'center', beforeDraw(chart){ const {width,height,ctx}=chart; ctx.save(); ctx.font='bold 20px Inter,Arial,sans-serif'; ctx.fillStyle='#1f2937'; ctx.textAlign='center'; ctx.textBaseline='middle'; ctx.fillText(tot,width/2,height/2-6); ctx.font='10px Inter,Arial,sans-serif'; ctx.fillStyle='#9ca3af'; ctx.fillText('active',width/2,height/2+12); ctx.restore(); } }]
        });
    }
})();
</script>
@endpush

@elseif(($user['type'] ?? '') === 'employee' && ($user['role']['id'] ?? 0) == \App\Enums\RoleId::DELIVERY_SUPPORT_HEAD->value)
{{-- ===================== DELIVERY SUPPORT HEAD DASHBOARD ===================== --}}
@php
    $stats      = $data['ticket_stats']     ?? [];
    $sla        = $data['sla_summary']       ?? null;
    $teamLoad   = $data['team_load']         ?? collect();
    $recentTkts = $data['recent_tickets']    ?? collect();
    $tsPending  = $data['timesheet_pending'] ?? 0;

    $statusCfg = [
        'open'                    => ['label'=>'Open',           'dot'=>'bg-blue-500',   'text'=>'text-blue-700',   'bg'=>'bg-blue-50'  ],
        'inprocess'               => ['label'=>'In Process',     'dot'=>'bg-yellow-500', 'text'=>'text-yellow-700', 'bg'=>'bg-yellow-50'],
        'waiting_on_customer'     => ['label'=>'Wait Customer',  'dot'=>'bg-amber-500',  'text'=>'text-amber-700',  'bg'=>'bg-amber-50' ],
        'waiting_on_3rd_party'    => ['label'=>'Wait 3rd Party', 'dot'=>'bg-indigo-500', 'text'=>'text-indigo-700', 'bg'=>'bg-indigo-50'],
        'waiting_to_confirmation' => ['label'=>'Wait Confirm',   'dot'=>'bg-teal-500',   'text'=>'text-teal-700',   'bg'=>'bg-teal-50'  ],
        'hold'                    => ['label'=>'Hold',           'dot'=>'bg-orange-500', 'text'=>'text-orange-700', 'bg'=>'bg-orange-50'],
        'cancelled'               => ['label'=>'Cancelled',      'dot'=>'bg-gray-400',   'text'=>'text-gray-500',   'bg'=>'bg-gray-100' ],
        'closed'                  => ['label'=>'Closed',         'dot'=>'bg-green-500',  'text'=>'text-green-700',  'bg'=>'bg-green-50' ],
    ];
    $prioCfg = [
        'Very High' => 'text-red-700 bg-red-50',
        'High'      => 'text-orange-700 bg-orange-50',
        'Medium'    => 'text-yellow-700 bg-yellow-50',
        'Low'       => 'text-blue-700 bg-blue-50',
    ];
@endphp
<div class="space-y-5">

    {{-- ── Row 1: Greeting + Alert Badges ─────────────────────────────────── --}}
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-gray-800">
                @php
                    $hour = now()->hour;
                    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
                @endphp
                {{ $greeting }}, {{ explode(' ', $user['name'] ?? 'Head')[0] }}
            </h2>
            <p class="text-xs text-gray-400 mt-0.5">{{ now()->format('l, d F Y') }} &mdash; Delivery Support Head</p>
        </div>
        <div class="hidden sm:flex items-center gap-3">
            @if($tsPending > 0)
            <a href="{{ route('calendar.timesheets') }}"
                class="inline-flex items-center gap-2 bg-amber-50 border border-amber-200 text-amber-700 text-xs font-semibold px-3 py-1.5 rounded-lg hover:bg-amber-100 transition">
                <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                {{ $tsPending }} timesheet{{ $tsPending > 1 ? 's' : '' }} pending
            </a>
            @endif
            @if($sla && ($sla['breached'] ?? 0) > 0)
            <a href="{{ route('sla.report') }}"
                class="inline-flex items-center gap-2 bg-red-50 border border-red-200 text-red-700 text-xs font-semibold px-3 py-1.5 rounded-lg hover:bg-red-100 transition">
                <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></span>
                {{ $sla['breached'] }} SLA breach{{ $sla['breached'] > 1 ? 'es' : '' }}
            </a>
            @endif
            <span class="text-xs text-gray-400" id="headClock"></span>
        </div>
    </div>

    {{-- ── Row 2: KPI Cards ───────────────────────────────────────────────── --}}
    @php
        $activeTickets = ($stats['open'] ?? 0) + ($stats['inprocess'] ?? 0)
            + ($stats['waiting_on_customer'] ?? 0) + ($stats['waiting_on_3rd_party'] ?? 0)
            + ($stats['waiting_to_confirmation'] ?? 0) + ($stats['hold'] ?? 0);
    @endphp
    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-4">
        {{-- Active Tickets --}}
        <a href="{{ route('ticket.index') }}"
            class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4 hover:border-red-300 hover:shadow-md transition-all group">
            <div class="w-9 h-9 rounded-xl bg-red-50 group-hover:bg-red-100 flex items-center justify-center mb-3 transition">
                <i class="fas fa-fire text-red-600 text-sm"></i>
            </div>
            <p class="text-2xl font-bold text-gray-800">{{ $activeTickets }}</p>
            <p class="text-xs text-gray-400 mt-0.5">Active Tickets</p>
        </a>
        {{-- Total Tickets --}}
        <a href="{{ route('ticket.index') }}"
            class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4 hover:border-blue-300 hover:shadow-md transition-all group">
            <div class="w-9 h-9 rounded-xl bg-blue-50 group-hover:bg-blue-100 flex items-center justify-center mb-3 transition">
                <i class="fas fa-ticket-alt text-blue-600 text-sm"></i>
            </div>
            <p class="text-2xl font-bold text-gray-800">{{ number_format($stats['total'] ?? 0) }}</p>
            <p class="text-xs text-gray-400 mt-0.5">Total Tickets</p>
        </a>
        {{-- Timesheets Pending --}}
        <a href="{{ route('calendar.timesheets') }}"
            class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4 hover:border-amber-300 hover:shadow-md transition-all group">
            <div class="w-9 h-9 rounded-xl bg-amber-50 group-hover:bg-amber-100 flex items-center justify-center mb-3 transition">
                <i class="fas fa-clock text-amber-600 text-sm"></i>
            </div>
            <p class="text-2xl font-bold {{ $tsPending > 0 ? 'text-amber-600' : 'text-gray-800' }}">{{ $tsPending }}</p>
            <p class="text-xs text-gray-400 mt-0.5">TS Pending Review</p>
        </a>
        {{-- SLA Compliance --}}
        <a href="{{ route('sla.report') }}"
            class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4 hover:border-emerald-300 hover:shadow-md transition-all group">
            <div class="w-9 h-9 rounded-xl bg-emerald-50 group-hover:bg-emerald-100 flex items-center justify-center mb-3 transition">
                <i class="fas fa-stopwatch text-emerald-600 text-sm"></i>
            </div>
            @if($sla && $sla['compliance_rate'] !== null)
                <p class="text-2xl font-bold {{ $sla['compliance_rate'] >= 80 ? 'text-emerald-600' : ($sla['compliance_rate'] >= 60 ? 'text-yellow-600' : 'text-red-600') }}">
                    {{ $sla['compliance_rate'] }}%
                </p>
            @else
                <p class="text-2xl font-bold text-gray-400">—</p>
            @endif
            <p class="text-xs text-gray-400 mt-0.5">SLA Compliance</p>
        </a>
        {{-- SLA Breached --}}
        <a href="{{ route('sla.report') }}"
            class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4 hover:border-rose-300 hover:shadow-md transition-all group">
            <div class="w-9 h-9 rounded-xl bg-rose-50 group-hover:bg-rose-100 flex items-center justify-center mb-3 transition">
                <i class="fas fa-exclamation-triangle text-rose-600 text-sm"></i>
            </div>
            @if($sla)
                <p class="text-2xl font-bold {{ ($sla['breached'] ?? 0) > 0 ? 'text-rose-600' : 'text-gray-800' }}">{{ $sla['breached'] ?? 0 }}</p>
            @else
                <p class="text-2xl font-bold text-gray-400">—</p>
            @endif
            <p class="text-xs text-gray-400 mt-0.5">SLA Breached</p>
        </a>
        {{-- Team Members --}}
        <a href="{{ route('master.employee.index') }}"
            class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4 hover:border-purple-300 hover:shadow-md transition-all group">
            <div class="w-9 h-9 rounded-xl bg-purple-50 group-hover:bg-purple-100 flex items-center justify-center mb-3 transition">
                <i class="fas fa-users text-purple-600 text-sm"></i>
            </div>
            <p class="text-2xl font-bold text-gray-800">{{ $data['employee'] ?? 0 }}</p>
            <p class="text-xs text-gray-400 mt-0.5">Team Members</p>
        </a>
    </div>

    {{-- ── Row 3: Ticket Status Strip ─────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm px-5 py-4">
        <div class="flex items-center justify-between mb-3">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Ticket Status Breakdown</p>
            <a href="{{ route('ticket.index') }}" class="text-xs font-semibold text-red-700 hover:text-red-800">View all →</a>
        </div>
        <div class="grid grid-cols-4 sm:grid-cols-8 gap-3">
            @foreach($statusCfg as $key => $cfg)
            <div class="text-center">
                <p class="text-xl font-bold text-gray-800">{{ $stats[$key] ?? 0 }}</p>
                <div class="flex items-center justify-center gap-1 mt-1">
                    <span class="w-1.5 h-1.5 rounded-full {{ $cfg['dot'] }} flex-shrink-0"></span>
                    <p class="text-[10px] text-gray-400 leading-tight">{{ $cfg['label'] }}</p>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- ── Row 4: Chart + Agent Workload ──────────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        {{-- Chart --}}
        <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-sm font-semibold text-gray-800">Ticket Submissions</p>
                    <p class="text-xs text-gray-400 mt-0.5">Last 30 days</p>
                </div>
                <span class="text-xs text-gray-400">{{ now()->format('d M Y') }}</span>
            </div>
            <canvas id="headTicketChart" height="80"></canvas>
        </div>

        {{-- Agent Workload --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 flex flex-col">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-sm font-semibold text-gray-800">Agent Workload</p>
                    <p class="text-xs text-gray-400 mt-0.5">Active tickets per agent</p>
                </div>
                <a href="{{ route('ticket.consultant-workload') }}" class="text-xs font-semibold text-red-700 hover:text-red-800">Full →</a>
            </div>
            @if($teamLoad->isEmpty())
            <div class="flex-1 flex items-center justify-center text-center py-6">
                <div>
                    <i class="fas fa-check-circle text-green-400 text-3xl mb-2"></i>
                    <p class="text-xs text-gray-400">No active workload</p>
                </div>
            </div>
            @else
            @php $maxLoad = $teamLoad->max('open_count') ?: 1; @endphp
            <div class="space-y-3 flex-1">
                @foreach($teamLoad as $m)
                @php
                    $pct   = round(($m->open_count / $maxLoad) * 100);
                    $barCl = $pct >= 80 ? 'bg-red-500' : ($pct >= 50 ? 'bg-amber-500' : 'bg-emerald-500');
                @endphp
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs font-medium text-gray-700 truncate max-w-[75%]">{{ $m->name }}</span>
                        <span class="text-xs font-bold text-gray-800">{{ $m->open_count }}</span>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-1.5">
                        <div class="{{ $barCl }} h-1.5 rounded-full transition-all" style="width:{{ $pct }}%"></div>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>

    {{-- ── Row 5: Recent Tickets + Quick Nav ──────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        {{-- Recent Tickets --}}
        <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-lg bg-red-50 flex items-center justify-center">
                        <i class="fas fa-ticket-alt text-red-600 text-xs"></i>
                    </div>
                    <p class="text-sm font-semibold text-gray-800">Recent Tickets</p>
                </div>
                <a href="{{ route('ticket.index') }}" class="text-xs font-semibold text-red-700 hover:text-red-800">View all →</a>
            </div>
            @if($recentTkts->isEmpty())
            <div class="py-12 text-center text-sm text-gray-400">No tickets yet</div>
            @else
            <div class="divide-y divide-gray-50">
                @foreach($recentTkts as $t)
                @php
                    $sc = $statusCfg[$t->status] ?? ['dot'=>'bg-gray-400','text'=>'text-gray-500','bg'=>'bg-gray-100','label'=>'Unknown'];
                    $pc = $prioCfg[$t->ticket_priority ?? ''] ?? 'text-gray-500 bg-gray-100';
                @endphp
                <a href="{{ route('ticket.show', $t->ticket_id) }}"
                    class="flex items-center gap-3 px-5 py-3 hover:bg-gray-50/80 transition-colors group">
                    <span class="w-2 h-2 rounded-full {{ $sc['dot'] }} flex-shrink-0 mt-0.5"></span>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold text-gray-700 group-hover:text-red-700 transition-colors font-mono">#{{ $t->ticket_number }}</span>
                            <span class="text-xs text-gray-500 truncate hidden sm:block">{{ Str::limit($t->description ?? '', 42) }}</span>
                        </div>
                        <p class="text-[10px] text-gray-400 mt-0.5">{{ $t->customer_name ?? '—' }} &middot; {{ $t->pic_name }} &middot; {{ \Carbon\Carbon::parse($t->created_at)->diffForHumans() }}</p>
                    </div>
                    <div class="flex items-center gap-1.5 flex-shrink-0">
                        @if($t->ticket_priority)
                        <span class="text-[10px] font-semibold {{ $pc }} px-1.5 py-0.5 rounded-full hidden md:inline-flex">{{ $t->ticket_priority }}</span>
                        @endif
                        <span class="inline-flex items-center gap-1 text-[10px] font-semibold {{ $sc['text'] }} {{ $sc['bg'] }} px-2 py-0.5 rounded-full whitespace-nowrap">
                            {{ $sc['label'] }}
                        </span>
                    </div>
                </a>
                @endforeach
            </div>
            @endif
        </div>

        {{-- Quick Navigation --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
            <p class="text-sm font-semibold text-gray-800 mb-4">Quick Navigation</p>
            <div class="grid grid-cols-2 gap-2">
                @php
                    $navItems = [
                        ['href'=>route('ticket.index'),               'icon'=>'fa-ticket-alt',   'bg'=>'bg-red-50',     'color'=>'text-red-600',    'label'=>'Tickets'],
                        ['href'=>route('calendar.timesheets'),        'icon'=>'fa-clock',        'bg'=>'bg-amber-50',   'color'=>'text-amber-600',  'label'=>'TS Approval', 'badge'=>$tsPending],
                        ['href'=>route('sla.report'),                 'icon'=>'fa-stopwatch',    'bg'=>'bg-emerald-50', 'color'=>'text-emerald-600','label'=>'SLA Report'],
                        ['href'=>route('sla.config'),                 'icon'=>'fa-cog',          'bg'=>'bg-teal-50',    'color'=>'text-teal-600',   'label'=>'SLA Config'],
                        ['href'=>route('ticket.consultant-workload'), 'icon'=>'fa-tasks',        'bg'=>'bg-purple-50',  'color'=>'text-purple-600', 'label'=>'Workload'],
                        ['href'=>route('reporting.md-recap'),         'icon'=>'fa-calendar-alt', 'bg'=>'bg-indigo-50',  'color'=>'text-indigo-600', 'label'=>'MD Recap'],
                        ['href'=>route('reporting'),                  'icon'=>'fa-chart-bar',    'bg'=>'bg-blue-50',    'color'=>'text-blue-600',   'label'=>'Reporting'],
                        ['href'=>route('profile.my'),                 'icon'=>'fa-user',         'bg'=>'bg-gray-100',   'color'=>'text-gray-600',   'label'=>'My Profile'],
                    ];
                @endphp
                @foreach($navItems as $nav)
                <a href="{{ $nav['href'] }}"
                    class="relative flex flex-col items-center gap-2 p-3 rounded-xl {{ $nav['bg'] }} hover:ring-2 hover:ring-offset-1 hover:ring-gray-200 transition-all group text-center">
                    @if(!empty($nav['badge']) && $nav['badge'] > 0)
                    <span class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 text-white text-[9px] font-bold rounded-full flex items-center justify-center">
                        {{ $nav['badge'] > 9 ? '9+' : $nav['badge'] }}
                    </span>
                    @endif
                    <i class="fas {{ $nav['icon'] }} {{ $nav['color'] }} text-base"></i>
                    <p class="text-[11px] font-semibold text-gray-600 leading-tight">{{ $nav['label'] }}</p>
                </a>
                @endforeach
            </div>

            {{-- SLA Quick Status --}}
            <div class="mt-4 pt-4 border-t border-gray-100">
                <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-2">SLA Overview</p>
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            <span class="text-xs text-gray-500">Met</span>
                        </div>
                        <span class="text-xs font-semibold text-emerald-600">{{ $sla['met'] ?? '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-red-500"></span>
                            <span class="text-xs text-gray-500">Breached</span>
                        </div>
                        <span class="text-xs font-semibold {{ ($sla['breached'] ?? 0) > 0 ? 'text-red-600' : 'text-gray-500' }}">{{ $sla['breached'] ?? '—' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    // Live clock
    const headClockEl = document.getElementById('headClock');
    function tickHead() {
        if (!headClockEl) return;
        const now = new Date();
        headClockEl.textContent = now.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }
    tickHead();
    setInterval(tickHead, 1000);

    // Ticket chart
    const labels    = @json($data['ticket_chart']['labels'] ?? []);
    const chartData = @json($data['ticket_chart']['data']   ?? []);
    const ctx = document.getElementById('headTicketChart');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'line',
        data: {
            labels,
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
                tooltip: { callbacks: { label: c => c.parsed.y + ' ticket' + (c.parsed.y !== 1 ? 's' : '') } }
            },
            scales: {
                x: { grid: { display: false }, ticks: { font: { size: 11 }, maxTicksLimit: 10, color: '#9ca3af' } },
                y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { stepSize: 1, precision: 0, color: '#9ca3af', font: { size: 11 } } }
            }
        }
    });
})();
</script>
@endpush

@elseif(($user['type'] ?? '') === 'employee' && ($user['role']['id'] ?? 0) == \App\Enums\RoleId::DELIVERY_SUPPORT_MANAGER->value)
{{-- ===================== DELIVERY SUPPORT MANAGER DASHBOARD ===================== --}}
@php
    $stats      = $data['ticket_stats']       ?? [];
    $sla        = $data['sla_summary']        ?? null;
    $teamLoad   = $data['team_load']          ?? collect();
    $recentTkts = $data['recent_tickets']     ?? collect();
    $urgents    = $data['urgent_tickets']     ?? collect();
    $prioBd     = $data['priority_breakdown'] ?? [];
    $vhCount    = $data['very_high_count']    ?? 0;
    $unassign   = $data['unassigned_count']   ?? 0;
    $slaWarn    = $data['sla_warning']        ?? 0;
    $todayNew   = $data['today_new']          ?? 0;
    $todayCl    = $data['today_closed']       ?? 0;

    $activeTickets = ($stats['open'] ?? 0) + ($stats['inprocess'] ?? 0)
        + ($stats['waiting_on_customer'] ?? 0) + ($stats['waiting_on_3rd_party'] ?? 0)
        + ($stats['waiting_to_confirmation'] ?? 0) + ($stats['hold'] ?? 0);

    $hour      = now()->hour;
    $greeting  = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    $firstName = explode(' ', $user['name'] ?? 'Manager')[0];

    $statusCfg = [
        'open'                    => ['label'=>'Open',           'dot'=>'bg-blue-500',   'text'=>'text-blue-700',   'bg'=>'bg-blue-50'   ],
        'inprocess'               => ['label'=>'In Process',     'dot'=>'bg-yellow-500', 'text'=>'text-yellow-700', 'bg'=>'bg-yellow-50' ],
        'waiting_on_customer'     => ['label'=>'Wait Customer',  'dot'=>'bg-amber-500',  'text'=>'text-amber-700',  'bg'=>'bg-amber-50'  ],
        'waiting_on_3rd_party'    => ['label'=>'Wait 3rd Party', 'dot'=>'bg-indigo-500', 'text'=>'text-indigo-700', 'bg'=>'bg-indigo-50' ],
        'waiting_to_confirmation' => ['label'=>'Wait Confirm',   'dot'=>'bg-teal-500',   'text'=>'text-teal-700',   'bg'=>'bg-teal-50'   ],
        'hold'                    => ['label'=>'Hold',           'dot'=>'bg-orange-500', 'text'=>'text-orange-700', 'bg'=>'bg-orange-50' ],
        'cancelled'               => ['label'=>'Cancelled',      'dot'=>'bg-gray-400',   'text'=>'text-gray-500',   'bg'=>'bg-gray-100'  ],
        'closed'                  => ['label'=>'Closed',         'dot'=>'bg-green-500',  'text'=>'text-green-700',  'bg'=>'bg-green-50'  ],
    ];
    $prioCfg = [
        'Very High' => ['text'=>'text-red-700',    'bg'=>'bg-red-50',    'dot'=>'bg-red-500'   ],
        'High'      => ['text'=>'text-orange-700', 'bg'=>'bg-orange-50', 'dot'=>'bg-orange-500'],
        'Medium'    => ['text'=>'text-yellow-700', 'bg'=>'bg-yellow-50', 'dot'=>'bg-yellow-500'],
        'Low'       => ['text'=>'text-blue-700',   'bg'=>'bg-blue-50',   'dot'=>'bg-blue-400'  ],
    ];
@endphp

<div class="space-y-5">

{{-- ── Row 1: Greeting + Alert Badges ──────────────────────────────────────── --}}
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
    <div>
        <div class="flex items-center gap-2">
            <h2 class="text-xl font-bold text-gray-800">{{ $greeting }}, {{ $firstName }}</h2>
            <span class="text-xs font-semibold text-violet-700 bg-violet-50 border border-violet-200 px-2.5 py-0.5 rounded-full">Manager</span>
        </div>
        <p class="text-xs text-gray-400 mt-0.5">{{ now()->isoFormat('dddd, D MMMM Y') }} &mdash; <span id="mgrClock" class="font-mono"></span></p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        @if($vhCount > 0)
        <a href="{{ route('ticket.index') }}" class="inline-flex items-center gap-1.5 bg-red-50 border border-red-200 text-red-700 text-xs font-semibold px-3 py-1.5 rounded-xl hover:bg-red-100 transition">
            <i class="fas fa-bolt text-xs animate-pulse"></i>{{ $vhCount }} Very High
        </a>
        @endif
        @if(($sla['breached'] ?? 0) > 0)
        <a href="{{ route('sla.report') }}" class="inline-flex items-center gap-1.5 bg-rose-50 border border-rose-200 text-rose-700 text-xs font-semibold px-3 py-1.5 rounded-xl hover:bg-rose-100 transition">
            <span class="w-2 h-2 rounded-full bg-rose-500 animate-pulse"></span>{{ $sla['breached'] }} SLA breached
        </a>
        @endif
        @if($unassign > 0)
        <a href="{{ route('ticket.index') }}" class="inline-flex items-center gap-1.5 bg-indigo-50 border border-indigo-200 text-indigo-700 text-xs font-semibold px-3 py-1.5 rounded-xl hover:bg-indigo-100 transition">
            <i class="fas fa-user-clock text-xs"></i>{{ $unassign }} unassigned
        </a>
        @endif
    </div>
</div>

{{-- ── Row 2: KPI Cards ─────────────────────────────────────────────────────── --}}
<div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-4">

    {{-- Active Tickets --}}
    <a href="{{ route('ticket.index') }}" class="relative bg-white rounded-2xl border-2 border-gray-200 shadow-sm p-4 hover:border-red-300 hover:shadow-md transition-all group overflow-hidden">
        <div class="absolute inset-0 primary-gradient opacity-0 group-hover:opacity-5 transition-opacity rounded-2xl"></div>
        <div class="w-9 h-9 rounded-xl bg-red-50 group-hover:bg-red-100 flex items-center justify-center mb-3 transition">
            <i class="fas fa-fire text-red-600 text-sm"></i>
        </div>
        <p class="text-2xl font-bold text-gray-800">{{ $activeTickets }}</p>
        <p class="text-xs text-gray-400 mt-0.5">Active Tickets</p>
        <p class="text-[10px] text-gray-300 mt-0.5">{{ $stats['total'] ?? 0 }} total</p>
    </a>

    {{-- Unassigned --}}
    <a href="{{ route('ticket.index') }}" class="relative bg-white rounded-2xl border-2 {{ $unassign > 0 ? 'border-indigo-300' : 'border-gray-200' }} shadow-sm p-4 hover:shadow-md transition-all group">
        <div class="w-9 h-9 rounded-xl {{ $unassign > 0 ? 'bg-indigo-100' : 'bg-gray-100' }} flex items-center justify-center mb-3">
            <i class="fas fa-user-clock {{ $unassign > 0 ? 'text-indigo-600' : 'text-gray-400' }} text-sm"></i>
        </div>
        <p class="text-2xl font-bold {{ $unassign > 0 ? 'text-indigo-700' : 'text-gray-800' }}">{{ $unassign }}</p>
        <p class="text-xs text-gray-400 mt-0.5">Unassigned</p>
        @if($unassign > 0)<p class="text-[10px] text-indigo-500 font-medium mt-0.5">Needs agent →</p>@endif
    </a>

    {{-- Very High Priority --}}
    <a href="{{ route('ticket.index') }}" class="relative bg-white rounded-2xl border-2 {{ $vhCount > 0 ? 'border-red-300' : 'border-gray-200' }} shadow-sm p-4 hover:shadow-md transition-all group">
        <div class="w-9 h-9 rounded-xl {{ $vhCount > 0 ? 'bg-red-100' : 'bg-gray-100' }} flex items-center justify-center mb-3">
            <i class="fas fa-bolt {{ $vhCount > 0 ? 'text-red-600' : 'text-gray-400' }} text-sm"></i>
        </div>
        <p class="text-2xl font-bold {{ $vhCount > 0 ? 'text-red-700' : 'text-gray-800' }}">{{ $vhCount }}</p>
        <p class="text-xs text-gray-400 mt-0.5">Very High</p>
        @if($vhCount > 0)<p class="text-[10px] text-red-400 font-medium mt-0.5 flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse"></span>Urgent</p>@endif
    </a>

    {{-- SLA Breached --}}
    <a href="{{ route('sla.report') }}" class="bg-white rounded-2xl border-2 {{ ($sla['breached'] ?? 0) > 0 ? 'border-rose-300' : 'border-gray-200' }} shadow-sm p-4 hover:shadow-md transition-all group">
        <div class="w-9 h-9 rounded-xl {{ ($sla['breached'] ?? 0) > 0 ? 'bg-rose-100' : 'bg-gray-100' }} flex items-center justify-center mb-3">
            <i class="fas fa-exclamation-triangle {{ ($sla['breached'] ?? 0) > 0 ? 'text-rose-600' : 'text-gray-400' }} text-sm"></i>
        </div>
        <p class="text-2xl font-bold {{ ($sla['breached'] ?? 0) > 0 ? 'text-rose-600' : 'text-gray-800' }}">{{ $sla['breached'] ?? '—' }}</p>
        <p class="text-xs text-gray-400 mt-0.5">SLA Breached</p>
        @if($slaWarn > 0)<p class="text-[10px] text-orange-500 font-medium mt-0.5">+{{ $slaWarn }} at risk</p>@endif
    </a>

    {{-- SLA Compliance --}}
    <a href="{{ route('sla.report') }}" class="bg-white rounded-2xl border-2 border-gray-200 shadow-sm p-4 hover:border-emerald-300 hover:shadow-md transition-all group">
        <div class="w-9 h-9 rounded-xl bg-emerald-50 group-hover:bg-emerald-100 flex items-center justify-center mb-3 transition">
            <i class="fas fa-chart-pie text-emerald-600 text-sm"></i>
        </div>
        @if($sla && $sla['compliance_rate'] !== null)
        <p class="text-2xl font-bold {{ $sla['compliance_rate'] >= 80 ? 'text-emerald-600' : ($sla['compliance_rate'] >= 60 ? 'text-yellow-600' : 'text-red-600') }}">{{ $sla['compliance_rate'] }}%</p>
        @else
        <p class="text-2xl font-bold text-gray-400">—</p>
        @endif
        <p class="text-xs text-gray-400 mt-0.5">SLA Compliance</p>
        @if($sla && $sla['compliance_rate'] !== null)
        <div class="mt-1.5 w-full bg-gray-100 rounded-full h-1">
            <div class="h-1 rounded-full {{ ($sla['compliance_rate'] ?? 0) >= 80 ? 'bg-emerald-500' : (($sla['compliance_rate'] ?? 0) >= 60 ? 'bg-yellow-500' : 'bg-red-500') }}" style="width:{{ min($sla['compliance_rate'] ?? 0, 100) }}%"></div>
        </div>
        @endif
    </a>

    {{-- Team Size --}}
    <a href="{{ route('master.employee.index') }}" class="bg-white rounded-2xl border-2 border-gray-200 shadow-sm p-4 hover:border-violet-300 hover:shadow-md transition-all group">
        <div class="w-9 h-9 rounded-xl bg-violet-50 group-hover:bg-violet-100 flex items-center justify-center mb-3 transition">
            <i class="fas fa-users text-violet-600 text-sm"></i>
        </div>
        <p class="text-2xl font-bold text-gray-800">{{ $teamLoad->count() }}</p>
        <p class="text-xs text-gray-400 mt-0.5">Active Agents</p>
        <p class="text-[10px] text-gray-300 mt-0.5">with open tickets</p>
    </a>
</div>

{{-- ── Row 3: Status Strip ──────────────────────────────────────────────────── --}}
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm px-5 py-4">
    <div class="flex items-center justify-between mb-3">
        <div class="flex items-center gap-2">
            <div class="w-6 h-6 rounded-lg bg-gray-100 flex items-center justify-center">
                <i class="fas fa-chart-bar text-gray-500 text-xs"></i>
            </div>
            <p class="text-xs font-semibold text-gray-600 uppercase tracking-wider">Team Ticket Status</p>
        </div>
        <a href="{{ route('ticket.index') }}" class="text-xs font-semibold text-red-700 hover:text-red-800">View all →</a>
    </div>
    <div class="grid grid-cols-4 sm:grid-cols-8 gap-2">
        @foreach($statusCfg as $key => $cfg)
        @php $count = $stats[$key] ?? 0; $total = max($stats['total'] ?? 1, 1); $pct = round($count / $total * 100); @endphp
        <div class="text-center p-2 rounded-xl hover:{{ $cfg['bg'] }} transition-colors cursor-default group">
            <p class="text-lg font-bold text-gray-800 group-hover:{{ $cfg['text'] }} transition-colors">{{ $count }}</p>
            <div class="flex items-center justify-center gap-1 mt-1 mb-1.5">
                <span class="w-1.5 h-1.5 rounded-full {{ $cfg['dot'] }} flex-shrink-0"></span>
                <p class="text-[10px] text-gray-400 leading-tight">{{ $cfg['label'] }}</p>
            </div>
            <div class="w-full bg-gray-100 rounded-full h-1">
                <div class="{{ $cfg['dot'] }} h-1 rounded-full" style="width:{{ $pct }}%"></div>
            </div>
        </div>
        @endforeach
    </div>
</div>

{{-- ── Row 4: Trend Chart + Priority Donut ─────────────────────────────────── --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

    <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
        <div class="flex items-center justify-between mb-4">
            <div>
                <p class="text-sm font-semibold text-gray-800">Ticket Volume</p>
                <p class="text-xs text-gray-400 mt-0.5">Last 30 days</p>
            </div>
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-1.5 text-xs text-gray-400">
                    <span class="w-2 h-2 rounded-full bg-blue-400"></span>{{ $todayNew }} new today
                </div>
                <div class="flex items-center gap-1.5 text-xs text-gray-400">
                    <span class="w-2 h-2 rounded-full bg-green-400"></span>{{ $todayCl }} closed
                </div>
            </div>
        </div>
        <canvas id="mgrTicketChart" height="90"></canvas>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 flex flex-col">
        <div class="flex items-center justify-between mb-4">
            <p class="text-sm font-semibold text-gray-800">Priority (Active)</p>
            <a href="{{ route('ticket.index') }}" class="text-xs text-red-700 hover:text-red-800 font-semibold">All →</a>
        </div>
        <div class="flex-1 flex items-center justify-center">
            <canvas id="mgrPrioChart" width="140" height="140"></canvas>
        </div>
        <div class="grid grid-cols-2 gap-x-4 gap-y-2 mt-4">
            @foreach(['Very High','High','Medium','Low'] as $prio)
            @php $pc = $prioCfg[$prio] ?? []; $cnt = $prioBd[$prio] ?? 0; @endphp
            <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full {{ $pc['dot'] ?? 'bg-gray-300' }} flex-shrink-0"></span>
                <span class="text-xs text-gray-500 flex-1 truncate">{{ $prio }}</span>
                <span class="text-xs font-bold text-gray-700">{{ $cnt }}</span>
            </div>
            @endforeach
        </div>
    </div>
</div>

{{-- ── Row 5: Agent Workload + Quick Actions ────────────────────────────────── --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

    {{-- Agent Workload --}}
    <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
        <div class="flex items-center justify-between mb-5">
            <div>
                <p class="text-sm font-semibold text-gray-800">Agent Workload</p>
                <p class="text-xs text-gray-400 mt-0.5">Active tickets per agent — {{ $teamLoad->sum('open_count') }} total open</p>
            </div>
            <a href="{{ route('ticket.consultant-workload') }}" class="text-xs font-semibold text-red-700 hover:text-red-800">Full view →</a>
        </div>
        @if($teamLoad->isEmpty())
        <div class="flex flex-col items-center justify-center py-10 text-center">
            <div class="w-12 h-12 rounded-full bg-green-50 flex items-center justify-center mb-3">
                <i class="fas fa-check-circle text-green-400 text-xl"></i>
            </div>
            <p class="text-sm font-semibold text-gray-500">All clear!</p>
            <p class="text-xs text-gray-400 mt-1">No active agent workload</p>
        </div>
        @else
        @php $maxLoad = $teamLoad->max('open_count') ?: 1; @endphp
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4">
            @foreach($teamLoad as $m)
            @php
                $pct   = round(($m->open_count / $maxLoad) * 100);
                $barCl = $pct >= 80 ? 'bg-red-500' : ($pct >= 50 ? 'bg-amber-500' : 'bg-emerald-500');
                $txtCl = $pct >= 80 ? 'text-red-600' : ($pct >= 50 ? 'text-amber-600' : 'text-emerald-600');
            @endphp
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <div class="flex items-center gap-2 min-w-0">
                        <div class="w-6 h-6 rounded-full primary-gradient text-white flex items-center justify-center text-[9px] font-bold flex-shrink-0">
                            {{ strtoupper(substr($m->name ?? 'U', 0, 1)) }}
                        </div>
                        <span class="text-xs font-medium text-gray-700 truncate">{{ $m->name }}</span>
                    </div>
                    <span class="text-xs font-bold {{ $txtCl }} ml-2 flex-shrink-0">{{ $m->open_count }}</span>
                </div>
                <div class="w-full bg-gray-100 rounded-full h-1.5">
                    <div class="{{ $barCl }} h-1.5 rounded-full transition-all duration-500" style="width:{{ $pct }}%"></div>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>

    {{-- Quick Actions + Today's Summary --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
        <p class="text-sm font-semibold text-gray-800 mb-4">Quick Actions</p>
        <div class="grid grid-cols-2 gap-2">
            @php
                $navItems = [
                    ['href'=>route('ticket.index'),               'icon'=>'fa-ticket-alt',  'bg'=>'bg-red-50',     'color'=>'text-red-700',    'label'=>'All Tickets'],
                    ['href'=>route('ticket.consultant-workload'), 'icon'=>'fa-users-cog',   'bg'=>'bg-violet-50',  'color'=>'text-violet-600', 'label'=>'Workload'],
                    ['href'=>route('sla.report'),                 'icon'=>'fa-stopwatch',   'bg'=>'bg-emerald-50', 'color'=>'text-emerald-600','label'=>'SLA Report', 'badge'=>($sla['breached'] ?? 0)],
                    ['href'=>route('sla.config'),                 'icon'=>'fa-sliders-h',   'bg'=>'bg-teal-50',    'color'=>'text-teal-600',   'label'=>'SLA Config'],
                    ['href'=>route('reporting'),                  'icon'=>'fa-chart-bar',   'bg'=>'bg-blue-50',    'color'=>'text-blue-600',   'label'=>'Reporting'],
                    ['href'=>route('reporting.md-recap'),         'icon'=>'fa-calendar-alt','bg'=>'bg-indigo-50',  'color'=>'text-indigo-600', 'label'=>'MD Recap'],
                    ['href'=>route('calendar.timesheets'),        'icon'=>'fa-clock',       'bg'=>'bg-amber-50',   'color'=>'text-amber-600',  'label'=>'Timesheets'],
                    ['href'=>route('profile.my'),                 'icon'=>'fa-user-circle', 'bg'=>'bg-gray-100',   'color'=>'text-gray-600',   'label'=>'My Profile'],
                ];
            @endphp
            @foreach($navItems as $nav)
            <a href="{{ $nav['href'] }}" class="relative flex flex-col items-center gap-1.5 p-3 rounded-xl {{ $nav['bg'] }} hover:ring-2 hover:ring-offset-1 hover:ring-gray-300 transition-all text-center">
                @if(!empty($nav['badge']) && $nav['badge'] > 0)
                <span class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 text-white text-[9px] font-bold rounded-full flex items-center justify-center leading-none">{{ $nav['badge'] > 9 ? '9+' : $nav['badge'] }}</span>
                @endif
                <i class="fas {{ $nav['icon'] }} {{ $nav['color'] }} text-base"></i>
                <p class="text-[11px] font-semibold text-gray-600 leading-tight">{{ $nav['label'] }}</p>
            </a>
            @endforeach
        </div>

        <div class="mt-4 pt-4 border-t border-gray-100">
            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-2.5">Today's Summary</p>
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-gray-500 flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-blue-400"></span>New today</span>
                    <span class="text-xs font-bold text-gray-700">{{ $todayNew }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-xs text-gray-500 flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-green-400"></span>Closed today</span>
                    <span class="text-xs font-bold text-gray-700">{{ $todayCl }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-xs text-gray-500 flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-red-400"></span>SLA breached</span>
                    <span class="text-xs font-bold {{ ($sla['breached'] ?? 0) > 0 ? 'text-red-600' : 'text-gray-700' }}">{{ $sla['breached'] ?? '—' }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-xs text-gray-500 flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>SLA met</span>
                    <span class="text-xs font-bold text-gray-700">{{ $sla['met'] ?? '—' }}</span>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── Row 6: Urgent Tickets ────────────────────────────────────────────────── --}}
@if($urgents->isNotEmpty())
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
        <div class="flex items-center gap-2">
            <div class="w-7 h-7 rounded-lg bg-red-50 flex items-center justify-center"><i class="fas fa-exclamation-triangle text-red-600 text-xs"></i></div>
            <p class="text-sm font-semibold text-gray-800">Needs Immediate Attention</p>
            <span class="text-[10px] text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">Very High + SLA Breached</span>
        </div>
        <a href="{{ route('ticket.index') }}" class="text-xs font-semibold text-red-700 hover:text-red-800">View all →</a>
    </div>
    <div class="divide-y divide-gray-50">
        @foreach($urgents as $t)
        @php
            $sc = $statusCfg[$t->status] ?? ['dot'=>'bg-gray-400','text'=>'text-gray-500','bg'=>'bg-gray-100','label'=>'Unknown'];
            $isBreached = ($t->sla_status ?? '') === 'breached';
            $isVH = ($t->ticket_priority ?? '') === 'Very High';
        @endphp
        <a href="{{ route('ticket.show', $t->ticket_id) }}" class="flex items-center gap-3 px-5 py-3.5 hover:bg-gray-50/80 transition-colors group">
            <span class="w-2 h-2 rounded-full {{ $sc['dot'] }} flex-shrink-0 mt-0.5"></span>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-xs font-bold text-gray-700 group-hover:text-red-700 transition-colors font-mono">#{{ $t->ticket_number }}</span>
                    @if($isVH)<span class="text-[10px] font-bold text-red-700 bg-red-50 px-1.5 py-0.5 rounded-full"><i class="fas fa-bolt text-[8px] mr-0.5"></i>Very High</span>@endif
                    @if($isBreached)<span class="text-[10px] font-bold text-rose-600 bg-rose-50 px-1.5 py-0.5 rounded-full"><i class="fas fa-stopwatch text-[8px] mr-0.5"></i>SLA Breached</span>@endif
                </div>
                <p class="text-[11px] text-gray-400 truncate mt-0.5">{{ $t->customer_name ?? '—' }}@if($t->sla_due_at) &middot; Due {{ \Carbon\Carbon::parse($t->sla_due_at)->diffForHumans() }}@endif</p>
                <p class="text-[11px] text-gray-500 truncate">{{ \Str::limit($t->description ?? '', 60) }}</p>
            </div>
            <span class="text-[10px] font-semibold {{ $sc['text'] }} {{ $sc['bg'] }} px-2 py-0.5 rounded-full whitespace-nowrap flex-shrink-0">{{ $sc['label'] }}</span>
        </a>
        @endforeach
    </div>
</div>
@endif

{{-- ── Row 7: Recent Tickets ────────────────────────────────────────────────── --}}
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
        <div class="flex items-center gap-2">
            <div class="w-7 h-7 rounded-lg bg-red-50 flex items-center justify-center"><i class="fas fa-history text-red-600 text-xs"></i></div>
            <p class="text-sm font-semibold text-gray-800">Recent Tickets</p>
        </div>
        <a href="{{ route('ticket.index') }}" class="text-xs font-semibold text-red-700 hover:text-red-800">View all →</a>
    </div>
    @if($recentTkts->isEmpty())
    <div class="py-12 text-center text-sm text-gray-400">No tickets yet</div>
    @else
    <div class="hidden md:grid grid-cols-12 gap-2 px-5 py-2.5 bg-gray-50/80 border-b border-gray-100 text-[10px] font-bold text-gray-400 uppercase tracking-wider">
        <div class="col-span-2">Ticket #</div>
        <div class="col-span-3">Description</div>
        <div class="col-span-2">Customer</div>
        <div class="col-span-2">PIC</div>
        <div class="col-span-1 text-center">Priority</div>
        <div class="col-span-1 text-center">SLA</div>
        <div class="col-span-1 text-right">Status</div>
    </div>
    <div class="divide-y divide-gray-50">
        @foreach($recentTkts as $t)
        @php
            $sc    = $statusCfg[$t->status] ?? ['dot'=>'bg-gray-400','text'=>'text-gray-500','bg'=>'bg-gray-100','label'=>'Unknown'];
            $pc    = $prioCfg[$t->ticket_priority ?? ''] ?? ['text'=>'text-gray-400','bg'=>'bg-gray-100','dot'=>'bg-gray-300'];
            $slaSc = match($t->sla_status ?? '') { 'met'=>'text-green-600 bg-green-50','breached'=>'text-red-600 bg-red-50','pending'=>'text-blue-600 bg-blue-50','paused'=>'text-amber-600 bg-amber-50',default=>'text-gray-400 bg-gray-100' };
            $slaLb = match($t->sla_status ?? '') { 'met'=>'Met','breached'=>'Breached','pending'=>'Active','paused'=>'Paused',default=>'—' };
        @endphp
        <a href="{{ route('ticket.show', $t->ticket_id) }}" class="flex md:grid md:grid-cols-12 md:gap-2 items-center px-5 py-3 hover:bg-gray-50/80 transition-colors group">
            <div class="col-span-2 flex items-center gap-2">
                <span class="w-1.5 h-1.5 rounded-full {{ $sc['dot'] }} flex-shrink-0"></span>
                <span class="text-xs font-bold text-gray-700 group-hover:text-red-700 transition-colors font-mono">{{ $t->ticket_number }}</span>
            </div>
            <div class="col-span-3 hidden md:block">
                <span class="text-xs text-gray-500 truncate block">{{ \Str::limit($t->description ?? '—', 40) }}</span>
                <span class="text-[10px] text-gray-300">{{ \Carbon\Carbon::parse($t->updated_at)->diffForHumans() }}</span>
            </div>
            <div class="col-span-2 hidden md:block"><span class="text-xs text-gray-600 truncate block">{{ $t->customer_name ?? '—' }}</span></div>
            <div class="col-span-2 hidden md:block">
                <span class="text-xs text-gray-500 truncate block">{{ $t->pic_name ?? 'Unassigned' }}</span>
                @if(($t->pic_name ?? 'Unassigned') === 'Unassigned')<span class="text-[10px] text-indigo-500 font-semibold">Needs PIC</span>@endif
            </div>
            <div class="col-span-1 hidden md:flex justify-center">
                @if($t->ticket_priority)<span class="text-[10px] font-bold {{ $pc['text'] }} {{ $pc['bg'] }} px-1.5 py-0.5 rounded-full whitespace-nowrap">{{ $t->ticket_priority }}</span>@else<span class="text-gray-300 text-xs">—</span>@endif
            </div>
            <div class="col-span-1 hidden md:flex justify-center">
                <span class="text-[10px] font-semibold {{ $slaSc }} px-1.5 py-0.5 rounded-full whitespace-nowrap">{{ $slaLb }}</span>
            </div>
            <div class="col-span-1 flex md:justify-end ml-auto md:ml-0">
                <span class="text-[10px] font-semibold {{ $sc['text'] }} {{ $sc['bg'] }} px-2 py-0.5 rounded-full whitespace-nowrap">{{ $sc['label'] }}</span>
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
(function () {
    // Live clock
    function tickMgr() { const el = document.getElementById('mgrClock'); if (el) el.textContent = new Date().toLocaleTimeString('en-GB', { hour:'2-digit', minute:'2-digit', second:'2-digit' }); }
    tickMgr(); setInterval(tickMgr, 1000);

    // Trend chart
    const labels    = @json($data['ticket_chart']['labels'] ?? []);
    const chartData = @json($data['ticket_chart']['data']   ?? []);
    const ctx = document.getElementById('mgrTicketChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    data: chartData,
                    borderColor: '#7c3aed',
                    backgroundColor: 'rgba(124,58,237,0.07)',
                    borderWidth: 2.5,
                    pointRadius: 3,
                    pointBackgroundColor: '#7c3aed',
                    pointHoverRadius: 5,
                    tension: 0.4,
                    fill: true,
                }]
            },
            options: {
                responsive: true,
                plugins: { legend:{display:false}, tooltip:{callbacks:{label:c=>c.parsed.y+' ticket'+(c.parsed.y!==1?'s':'')}} },
                scales: {
                    x:{grid:{display:false},ticks:{font:{size:10},maxTicksLimit:10,color:'#9ca3af'}},
                    y:{beginAtZero:true,grid:{color:'rgba(0,0,0,0.04)'},ticks:{stepSize:1,precision:0,color:'#9ca3af',font:{size:10}}}
                }
            }
        });
    }

    // Priority donut
    const prioCtx = document.getElementById('mgrPrioChart');
    if (prioCtx) {
        const pd  = { 'Very High': @json($data['priority_breakdown']['Very High'] ?? 0), 'High': @json($data['priority_breakdown']['High'] ?? 0), 'Medium': @json($data['priority_breakdown']['Medium'] ?? 0), 'Low': @json($data['priority_breakdown']['Low'] ?? 0) };
        const tot = Object.values(pd).reduce((a,b)=>a+b,0);
        new Chart(prioCtx, {
            type: 'doughnut',
            data: { labels: Object.keys(pd), datasets: [{ data: Object.values(pd), backgroundColor:['#dc2626','#ea580c','#ca8a04','#2563eb'], borderColor:'#fff', borderWidth:2, hoverOffset:4 }] },
            options: { responsive:false, cutout:'68%', plugins:{ legend:{display:false}, tooltip:{callbacks:{label:c=>c.label+': '+c.raw+' ('+(tot>0?Math.round(c.raw/tot*100):0)+'%)'}} } },
            plugins: [{ id:'center', beforeDraw(chart){ const {width,height,ctx}=chart; ctx.save(); ctx.font='bold 20px Inter,Arial,sans-serif'; ctx.fillStyle='#1f2937'; ctx.textAlign='center'; ctx.textBaseline='middle'; ctx.fillText(tot,width/2,height/2-6); ctx.font='10px Inter,Arial,sans-serif'; ctx.fillStyle='#9ca3af'; ctx.fillText('active',width/2,height/2+12); ctx.restore(); } }]
        });
    }
})();
</script>
@endpush

@elseif(($user['type'] ?? '') === 'employee' && ($user['role']['id'] ?? 0) == \App\Enums\RoleId::EC_ADMINISTRATOR->value)
{{-- ===================== EC ADMINISTRATOR DASHBOARD ===================== --}}
@php
    $stats       = $data['ticket_stats'] ?? [];
    $sla         = $data['sla_summary']  ?? null;
    $teamLoad    = $data['team_load']    ?? collect();
    $recentTkts  = $data['recent_tickets'] ?? collect();
    $stagingPend = $data['staging_pending'] ?? 0;

    $statusCfg = [
        'open'                    => ['label'=>'Open',          'dot'=>'bg-blue-500',   'text'=>'text-blue-700',   'bg'=>'bg-blue-50'   ],
        'inprocess'               => ['label'=>'In Process',    'dot'=>'bg-yellow-500', 'text'=>'text-yellow-700', 'bg'=>'bg-yellow-50' ],
        'waiting_on_customer'     => ['label'=>'Wait Customer', 'dot'=>'bg-amber-500',  'text'=>'text-amber-700',  'bg'=>'bg-amber-50'  ],
        'waiting_on_3rd_party'    => ['label'=>'Wait 3rd Party','dot'=>'bg-indigo-500', 'text'=>'text-indigo-700', 'bg'=>'bg-indigo-50' ],
        'waiting_to_confirmation' => ['label'=>'Wait Confirm',  'dot'=>'bg-teal-500',   'text'=>'text-teal-700',   'bg'=>'bg-teal-50'   ],
        'hold'                    => ['label'=>'Hold',          'dot'=>'bg-orange-500', 'text'=>'text-orange-700', 'bg'=>'bg-orange-50' ],
        'cancelled'               => ['label'=>'Cancelled',     'dot'=>'bg-gray-400',   'text'=>'text-gray-500',   'bg'=>'bg-gray-100'  ],
        'closed'                  => ['label'=>'Closed',        'dot'=>'bg-green-500',  'text'=>'text-green-700',  'bg'=>'bg-green-50'  ],
    ];
    $prioCfg = [
        'Very High' => 'text-red-700 bg-red-50',
        'High'      => 'text-orange-700 bg-orange-50',
        'Medium'    => 'text-yellow-700 bg-yellow-50',
        'Low'       => 'text-blue-700 bg-blue-50',
    ];
@endphp
<div class="space-y-5">

    {{-- ── Row 1: Greeting + Date ─────────────────────────────────────────── --}}
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-gray-800">
                @php
                    $hour = now()->hour;
                    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
                @endphp
                {{ $greeting }}, {{ explode(' ', $user['name'] ?? 'Admin')[0] }}
            </h2>
            <p class="text-xs text-gray-400 mt-0.5">{{ now()->format('l, d F Y') }} &mdash; EC Administrator</p>
        </div>
        <div class="hidden sm:flex items-center gap-3">
            @if($stagingPend > 0)
            <a href="{{ route('staging.index') }}"
                class="inline-flex items-center gap-2 bg-amber-50 border border-amber-200 text-amber-700 text-xs font-semibold px-3 py-1.5 rounded-lg hover:bg-amber-100 transition">
                <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                {{ $stagingPend }} pending validation
            </a>
            @endif
            <span class="text-xs text-gray-400" id="adminClock"></span>
        </div>
    </div>

    {{-- ── Row 2: KPI Cards ───────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-4">
        {{-- Employees --}}
        <a href="{{ route('master.employee.index') }}"
            class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4 hover:border-blue-300 hover:shadow-md transition-all group">
            <div class="w-9 h-9 rounded-xl bg-blue-50 group-hover:bg-blue-100 flex items-center justify-center mb-3 transition">
                <i class="fas fa-users text-blue-600 text-sm"></i>
            </div>
            <p class="text-2xl font-bold text-gray-800">{{ $data['employee'] ?? 0 }}</p>
            <p class="text-xs text-gray-400 mt-0.5">Employees</p>
        </a>
        {{-- Customers --}}
        <a href="{{ route('master.customer.index') }}"
            class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4 hover:border-green-300 hover:shadow-md transition-all group">
            <div class="w-9 h-9 rounded-xl bg-green-50 group-hover:bg-green-100 flex items-center justify-center mb-3 transition">
                <i class="fas fa-building text-green-600 text-sm"></i>
            </div>
            <p class="text-2xl font-bold text-gray-800">{{ $data['customers'] ?? 0 }}</p>
            <p class="text-xs text-gray-400 mt-0.5">Customers</p>
        </a>
        {{-- Active Projects --}}
        <a href="{{ route('projects.index') }}"
            class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4 hover:border-purple-300 hover:shadow-md transition-all group">
            <div class="w-9 h-9 rounded-xl bg-purple-50 group-hover:bg-purple-100 flex items-center justify-center mb-3 transition">
                <i class="fas fa-project-diagram text-purple-600 text-sm"></i>
            </div>
            <p class="text-2xl font-bold text-gray-800">{{ $data['active_projects'] ?? 0 }}</p>
            <p class="text-xs text-gray-400 mt-0.5">Active Projects</p>
        </a>
        {{-- Total Tickets --}}
        <a href="{{ route('ticket.index') }}"
            class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4 hover:border-red-300 hover:shadow-md transition-all group">
            <div class="w-9 h-9 rounded-xl bg-red-50 group-hover:bg-red-100 flex items-center justify-center mb-3 transition">
                <i class="fas fa-ticket-alt text-red-600 text-sm"></i>
            </div>
            <p class="text-2xl font-bold text-gray-800">{{ number_format($stats['total'] ?? 0) }}</p>
            <p class="text-xs text-gray-400 mt-0.5">Total Tickets</p>
        </a>
        {{-- Open Tickets --}}
        <a href="{{ route('ticket.index') }}"
            class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4 hover:border-blue-300 hover:shadow-md transition-all group">
            <div class="w-9 h-9 rounded-xl bg-blue-50 group-hover:bg-blue-100 flex items-center justify-center mb-3 transition">
                <i class="fas fa-clock text-blue-600 text-sm"></i>
            </div>
            <p class="text-2xl font-bold text-gray-800">
                {{ ($stats['open'] ?? 0) + ($stats['inprocess'] ?? 0) + ($stats['waiting_on_customer'] ?? 0) + ($stats['waiting_on_3rd_party'] ?? 0) + ($stats['waiting_to_confirmation'] ?? 0) + ($stats['hold'] ?? 0) }}
            </p>
            <p class="text-xs text-gray-400 mt-0.5">Active Tickets</p>
        </a>
        {{-- SLA Compliance --}}
        <a href="{{ route('sla.report') }}"
            class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4 hover:border-emerald-300 hover:shadow-md transition-all group">
            <div class="w-9 h-9 rounded-xl bg-emerald-50 group-hover:bg-emerald-100 flex items-center justify-center mb-3 transition">
                <i class="fas fa-stopwatch text-emerald-600 text-sm"></i>
            </div>
            @if($sla && $sla['compliance_rate'] !== null)
                <p class="text-2xl font-bold {{ $sla['compliance_rate'] >= 80 ? 'text-emerald-600' : ($sla['compliance_rate'] >= 60 ? 'text-yellow-600' : 'text-red-600') }}">
                    {{ $sla['compliance_rate'] }}%
                </p>
            @else
                <p class="text-2xl font-bold text-gray-400">—</p>
            @endif
            <p class="text-xs text-gray-400 mt-0.5">SLA Compliance</p>
        </a>
    </div>

    {{-- ── Row 3: Ticket Status Strip ─────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm px-5 py-4">
        <div class="flex items-center justify-between mb-3">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Ticket Status Breakdown</p>
            <a href="{{ route('ticket.index') }}" class="text-xs font-semibold text-red-700 hover:text-red-800">View all →</a>
        </div>
        <div class="grid grid-cols-4 sm:grid-cols-8 gap-3">
            @foreach($statusCfg as $key => $cfg)
            <div class="text-center">
                <p class="text-xl font-bold text-gray-800">{{ $stats[$key] ?? 0 }}</p>
                <div class="flex items-center justify-center gap-1 mt-1">
                    <span class="w-1.5 h-1.5 rounded-full {{ $cfg['dot'] }} flex-shrink-0"></span>
                    <p class="text-[10px] text-gray-400 leading-tight">{{ $cfg['label'] }}</p>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- ── Row 4: Chart + Team Load ────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        {{-- Chart --}}
        <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-sm font-semibold text-gray-800">Ticket Submissions</p>
                    <p class="text-xs text-gray-400 mt-0.5">Last 30 days</p>
                </div>
                <span class="text-xs text-gray-400">{{ now()->format('d M Y') }}</span>
            </div>
            <canvas id="adminTicketChart" height="80"></canvas>
        </div>

        {{-- Team Load --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 flex flex-col">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-sm font-semibold text-gray-800">Agent Workload</p>
                    <p class="text-xs text-gray-400 mt-0.5">Active tickets per agent</p>
                </div>
                <a href="{{ route('master.employee.index') }}" class="text-xs font-semibold text-red-700 hover:text-red-800">All →</a>
            </div>
            @if($teamLoad->isEmpty())
            <div class="flex-1 flex items-center justify-center text-center py-6">
                <div>
                    <i class="fas fa-check-circle text-green-400 text-3xl mb-2"></i>
                    <p class="text-xs text-gray-400">No active workload</p>
                </div>
            </div>
            @else
            @php $maxLoad = $teamLoad->max('open_count') ?: 1; @endphp
            <div class="space-y-3 flex-1">
                @foreach($teamLoad as $m)
                @php
                    $pct   = round(($m->open_count / $maxLoad) * 100);
                    $barCl = $pct >= 80 ? 'bg-red-500' : ($pct >= 50 ? 'bg-amber-500' : 'bg-emerald-500');
                @endphp
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs font-medium text-gray-700 truncate max-w-[75%]">{{ $m->name }}</span>
                        <span class="text-xs font-bold text-gray-800">{{ $m->open_count }}</span>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-1.5">
                        <div class="{{ $barCl }} h-1.5 rounded-full transition-all" style="width:{{ $pct }}%"></div>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>

    {{-- ── Row 5: Recent Tickets + Quick Nav ──────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        {{-- Recent Tickets --}}
        <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-lg bg-red-50 flex items-center justify-center">
                        <i class="fas fa-ticket-alt text-red-600 text-xs"></i>
                    </div>
                    <p class="text-sm font-semibold text-gray-800">Recent Tickets</p>
                </div>
                <a href="{{ route('ticket.index') }}" class="text-xs font-semibold text-red-700 hover:text-red-800">View all →</a>
            </div>
            @if($recentTkts->isEmpty())
            <div class="py-12 text-center text-sm text-gray-400">No tickets yet</div>
            @else
            <div class="divide-y divide-gray-50">
                @foreach($recentTkts as $t)
                @php
                    $sc  = $statusCfg[$t->status] ?? ['dot'=>'bg-gray-400','text'=>'text-gray-500','bg'=>'bg-gray-100','label'=>'Unknown'];
                    $pc  = $prioCfg[$t->ticket_priority ?? ''] ?? 'text-gray-500 bg-gray-100';
                @endphp
                <a href="{{ route('ticket.show', $t->ticket_id) }}"
                    class="flex items-center gap-3 px-5 py-3 hover:bg-gray-50/80 transition-colors group">
                    {{-- Priority dot --}}
                    <span class="w-2 h-2 rounded-full {{ $sc['dot'] }} flex-shrink-0 mt-0.5"></span>
                    {{-- Info --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold text-gray-700 group-hover:text-red-700 transition-colors font-mono">#{{ $t->ticket_number }}</span>
                            <span class="text-xs text-gray-500 truncate hidden sm:block">{{ Str::limit($t->description ?? '', 42) }}</span>
                        </div>
                        <p class="text-[10px] text-gray-400 mt-0.5">{{ $t->customer_name ?? '—' }} &middot; {{ $t->pic_name }} &middot; {{ \Carbon\Carbon::parse($t->created_at)->diffForHumans() }}</p>
                    </div>
                    {{-- Priority + Status --}}
                    <div class="flex items-center gap-1.5 flex-shrink-0">
                        @if($t->ticket_priority)
                        <span class="text-[10px] font-semibold {{ $pc }} px-1.5 py-0.5 rounded-full hidden md:inline-flex">{{ $t->ticket_priority }}</span>
                        @endif
                        <span class="inline-flex items-center gap-1 text-[10px] font-semibold {{ $sc['text'] }} {{ $sc['bg'] }} px-2 py-0.5 rounded-full whitespace-nowrap">
                            {{ $sc['label'] }}
                        </span>
                    </div>
                </a>
                @endforeach
            </div>
            @endif
        </div>

        {{-- Quick Navigation --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
            <p class="text-sm font-semibold text-gray-800 mb-4">Quick Navigation</p>
            <div class="grid grid-cols-2 gap-2">
                @php
                    $navItems = [
                        ['href'=>route('ticket.index'),          'icon'=>'fa-ticket-alt',       'bg'=>'bg-red-50',    'color'=>'text-red-600',    'label'=>'Tickets'],
                        ['href'=>route('staging.index'),         'icon'=>'fa-inbox',            'bg'=>'bg-amber-50',  'color'=>'text-amber-600',  'label'=>'Validation', 'badge'=>$stagingPend],
                        ['href'=>route('master.employee.index'), 'icon'=>'fa-users',            'bg'=>'bg-blue-50',   'color'=>'text-blue-600',   'label'=>'Employees'],
                        ['href'=>route('master.customer.index'), 'icon'=>'fa-building',         'bg'=>'bg-green-50',  'color'=>'text-green-600',  'label'=>'Customers'],
                        ['href'=>route('projects.index'),        'icon'=>'fa-project-diagram',  'bg'=>'bg-purple-50', 'color'=>'text-purple-600', 'label'=>'Projects'],
                        ['href'=>route('reporting'),             'icon'=>'fa-chart-bar',        'bg'=>'bg-indigo-50', 'color'=>'text-indigo-600', 'label'=>'Reporting'],
                        ['href'=>route('sla.report'),            'icon'=>'fa-stopwatch',        'bg'=>'bg-emerald-50','color'=>'text-emerald-600','label'=>'SLA'],
                        ['href'=>route('admin.index'),           'icon'=>'fa-shield-alt',       'bg'=>'bg-gray-100',  'color'=>'text-gray-600',   'label'=>'Control'],
                    ];
                @endphp
                @foreach($navItems as $nav)
                <a href="{{ $nav['href'] }}"
                    class="relative flex flex-col items-center gap-2 p-3 rounded-xl {{ $nav['bg'] }} hover:ring-2 hover:ring-offset-1 hover:ring-gray-200 transition-all group text-center">
                    @if(!empty($nav['badge']) && $nav['badge'] > 0)
                    <span class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 text-white text-[9px] font-bold rounded-full flex items-center justify-center">
                        {{ $nav['badge'] > 9 ? '9+' : $nav['badge'] }}
                    </span>
                    @endif
                    <i class="fas {{ $nav['icon'] }} {{ $nav['color'] }} text-base"></i>
                    <p class="text-[11px] font-semibold text-gray-600 leading-tight">{{ $nav['label'] }}</p>
                </a>
                @endforeach
            </div>

            {{-- System Health --}}
            <div class="mt-4 pt-4 border-t border-gray-100">
                <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-2">System Health</p>
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-green-500" id="adminDbDot"></span>
                            <span class="text-xs text-gray-500">Database</span>
                        </div>
                        <span class="text-xs font-medium text-gray-500" id="adminDbTxt">Checking...</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-gray-300" id="adminQueueDot"></span>
                            <span class="text-xs text-gray-500">Queue</span>
                        </div>
                        <span class="text-xs font-medium text-gray-500" id="adminQueueTxt">Checking...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    // ── Ticket chart ──────────────────────────────────────────────────────────
    const labels    = @json($data['ticket_chart']['labels'] ?? []);
    const chartData = @json($data['ticket_chart']['data'] ?? []);
    const ctx = document.getElementById('adminTicketChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    label: 'Tickets',
                    data: chartData,
                    borderColor: '#dc2626',
                    backgroundColor: 'rgba(220,38,38,0.07)',
                    borderWidth: 2,
                    pointRadius: 3,
                    pointBackgroundColor: '#dc2626',
                    tension: 0.4,
                    fill: true,
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: c => c.parsed.y + ' ticket' + (c.parsed.y !== 1 ? 's' : '')
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { font: { size: 11 }, maxTicksLimit: 10, color: '#9ca3af' } },
                    y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { stepSize: 1, precision: 0, color: '#9ca3af', font: { size: 11 } } }
                }
            }
        });
    }

    // ── Live clock ────────────────────────────────────────────────────────────
    function updateClock() {
        const el = document.getElementById('adminClock');
        if (el) el.textContent = new Date().toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }
    updateClock();
    setInterval(updateClock, 1000);

    // ── System health ─────────────────────────────────────────────────────────
    fetch('/api/health', { credentials: 'same-origin' })
        .then(r => r.json())
        .then(d => {
            const dbOk = d.checks?.database === 'ok';
            document.getElementById('adminDbDot').className = 'w-2 h-2 rounded-full ' + (dbOk ? 'bg-green-500' : 'bg-red-500');
            document.getElementById('adminDbTxt').textContent = dbOk ? 'Connected' : 'Error';

            const failed  = d.checks?.queue_failed  ?? 0;
            const pending = d.checks?.queue_pending ?? 0;
            const qOk     = failed === 0;
            document.getElementById('adminQueueDot').className = 'w-2 h-2 rounded-full ' + (qOk ? 'bg-green-500' : 'bg-orange-500');
            document.getElementById('adminQueueTxt').textContent = pending + ' pending' + (failed ? ', ' + failed + ' failed' : '');
        })
        .catch(() => {});
})();
</script>
@endpush

@else
{{-- ===================== DEFAULT DASHBOARD (other roles) ===================== --}}
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

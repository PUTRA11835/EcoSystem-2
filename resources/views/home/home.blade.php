@extends('dashboard')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
@php
    $stats       = $data['ticket_stats']    ?? [];
    $sla         = $data['sla_summary']     ?? null;
    $teamLoad    = $data['team_load']       ?? collect();
    $recentTkts  = $data['recent_tickets']  ?? collect();
    $stagingPend = $data['staging_pending'] ?? 0;

    $hour      = now()->hour;
    $greeting  = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    $firstName = explode(' ', $user['name'] ?? 'User')[0];
    $roleName  = $user['role']['name'] ?? 'User';

    $roleColors = [
        'EC Administrator'                   => 'text-red-700 bg-red-50 border-red-100',
        'EC User'                            => 'text-purple-700 bg-purple-50 border-purple-100',
        'Delivery Support User'              => 'text-green-700 bg-green-50 border-green-100',
        'Delivery Support Head'              => 'text-blue-700 bg-blue-50 border-blue-100',
        'Delivery Support Manager'           => 'text-indigo-700 bg-indigo-50 border-indigo-100',
        'Delivery Support Service Helpdesk'  => 'text-teal-700 bg-teal-50 border-teal-100',
        'Delivery RPMO Head'                 => 'text-cyan-700 bg-cyan-50 border-cyan-100',
        'Delivery Project Head'              => 'text-violet-700 bg-violet-50 border-violet-100',
    ];
    $roleBadge = $roleColors[$roleName] ?? 'text-gray-700 bg-gray-100 border-gray-200';

    $statusCfg = [
        'open'                    => ['label' => 'Open',           'dot' => 'bg-blue-500',   'text' => 'text-blue-700',   'bg' => 'bg-blue-50'  ],
        'inprocess'               => ['label' => 'In Process',     'dot' => 'bg-yellow-500', 'text' => 'text-yellow-700', 'bg' => 'bg-yellow-50'],
        'waiting_on_customer'     => ['label' => 'Wait Customer',  'dot' => 'bg-amber-500',  'text' => 'text-amber-700',  'bg' => 'bg-amber-50' ],
        'waiting_on_3rd_party'    => ['label' => 'Wait 3rd Party', 'dot' => 'bg-indigo-500', 'text' => 'text-indigo-700', 'bg' => 'bg-indigo-50'],
        'waiting_to_confirmation' => ['label' => 'Wait Confirm',   'dot' => 'bg-teal-500',   'text' => 'text-teal-700',   'bg' => 'bg-teal-50'  ],
        'hold'                    => ['label' => 'Hold',           'dot' => 'bg-orange-500', 'text' => 'text-orange-700', 'bg' => 'bg-orange-50'],
        'cancelled'               => ['label' => 'Cancelled',      'dot' => 'bg-gray-400',   'text' => 'text-gray-500',   'bg' => 'bg-gray-100' ],
        'closed'                  => ['label' => 'Closed',         'dot' => 'bg-green-500',  'text' => 'text-green-700',  'bg' => 'bg-green-50' ],
    ];
    $prioCfg = [
        'Very High' => 'text-red-700 bg-red-50',
        'High'      => 'text-orange-700 bg-orange-50',
        'Medium'    => 'text-yellow-700 bg-yellow-50',
        'Low'       => 'text-blue-700 bg-blue-50',
    ];

    $activeTickets = ($stats['open'] ?? 0)
        + ($stats['inprocess'] ?? 0)
        + ($stats['waiting_on_customer'] ?? 0)
        + ($stats['waiting_on_3rd_party'] ?? 0)
        + ($stats['waiting_to_confirmation'] ?? 0)
        + ($stats['hold'] ?? 0);

    $cardBase = 'bg-white rounded-2xl border border-gray-200 shadow-sm p-4 transition-all group';
@endphp

<div class="space-y-5">

{{-- ── Row 1: Greeting ──────────────────────────────────────────────────────── --}}
<div class="flex items-center justify-between">
    <div>
        <div class="flex items-center gap-2 flex-wrap">
            <h2 class="text-xl font-bold text-gray-800">{{ $greeting }}, {{ $firstName }}</h2>
            <span class="text-xs font-semibold border px-2.5 py-0.5 rounded-full {{ $roleBadge }}">{{ $roleName }}</span>
        </div>
        <p class="text-xs text-gray-400 mt-0.5">
            {{ now()->isoFormat('dddd, D MMMM Y') }}
            @if(!empty($user['position'])) - {{ $user['position'] }}@endif
        </p>
    </div>
    <div class="hidden sm:flex items-center gap-3">
        @if($stagingPend > 0 && $can('tickets.staging'))
        <a href="{{ route('staging.index') }}"
            class="inline-flex items-center gap-2 bg-amber-50 border border-amber-200 text-amber-700 text-xs font-semibold px-3 py-1.5 rounded-lg hover:bg-amber-100 transition">
            <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
            {{ $stagingPend }} pending validation
        </a>
        @endif
        <span class="text-xs text-gray-400 font-mono" id="dashClock"></span>
    </div>
</div>

{{-- ── Superadmin System Health & Security Snapshot ────────────────────────────
     Only ever populated by the controller for EC Administrator - gated on the
     data being present rather than re-deriving the role check here, so this
     block can never show for a role the controller didn't compute it for. --}}
@if(isset($data['security_summary']))
@php
    $sec      = $data['security_summary'];
    $sched    = $data['schedule_summary'];
    $queue    = $data['queue_summary'];
    $failedJobs = $data['failed_jobs_count'] ?? 0;

    $secNeedsAttention   = $sec['open_critical'] > 0 || $sec['open_high'] > 0;
    $schedNeedsAttention = $sched['issues'] > 0;
    $queueNeedsAttention = $queue['unhealthy'] > 0;
    $jobsNeedAttention   = $failedJobs > 0;

    $anyIssue = $secNeedsAttention || $schedNeedsAttention || $queueNeedsAttention || $jobsNeedAttention;

    // Same icon-circle-card shape as every KPI card below ($cardBase) - only
    // the icon's own color shifts between neutral and attention-needed, so
    // this reads as one more row of the same dashboard, not a separate widget.
    $iconCls = fn (bool $bad) => $bad ? 'bg-red-50 text-red-600' : 'bg-gray-50 text-gray-400';
@endphp
<div>
    <div class="flex items-center justify-between mb-2">
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider flex items-center gap-1.5">
            <i class="fas fa-shield-halved text-red-700"></i> Superadmin Control Panel
        </p>
        <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold px-2.5 py-1 rounded-full border {{ $anyIssue ? 'text-amber-700 bg-amber-50 border-amber-100' : 'text-emerald-700 bg-emerald-50 border-emerald-100' }}">
            <span class="w-1.5 h-1.5 rounded-full {{ $anyIssue ? 'bg-amber-500' : 'bg-emerald-500' }}"></span>
            {{ $anyIssue ? 'Needs attention' : 'All systems normal' }}
        </span>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">

        <a href="{{ route('admin.security-center') }}" class="{{ $cardBase }} hover:border-red-300 hover:shadow-md">
            <div class="flex items-center justify-between mb-3">
                <div class="w-9 h-9 rounded-xl {{ $iconCls($secNeedsAttention) }} flex items-center justify-center transition">
                    <i class="fas fa-triangle-exclamation text-sm"></i>
                </div>
                @if($sec['open_critical'] > 0)
                <span class="text-[10px] font-bold text-red-700 bg-red-50 px-1.5 py-0.5 rounded">{{ $sec['open_critical'] }} critical</span>
                @endif
            </div>
            <p class="text-2xl font-bold text-gray-800">{{ $sec['open_total'] }}</p>
            <p class="text-xs text-gray-400 mt-0.5">Open Security Events</p>
        </a>

        <a href="{{ route('admin.sessions') }}" class="{{ $cardBase }} hover:border-amber-300 hover:shadow-md">
            <div class="w-9 h-9 rounded-xl {{ $iconCls($sec['locked_accounts'] > 0) }} flex items-center justify-center mb-3 transition">
                <i class="fas fa-lock text-sm"></i>
            </div>
            <p class="text-2xl font-bold text-gray-800">{{ $sec['locked_accounts'] }}</p>
            <p class="text-xs text-gray-400 mt-0.5">Locked Accounts</p>
        </a>

        <a href="{{ route('admin.schedule-monitor') }}" class="{{ $cardBase }} hover:border-amber-300 hover:shadow-md">
            <div class="w-9 h-9 rounded-xl {{ $iconCls($schedNeedsAttention || $queueNeedsAttention) }} flex items-center justify-center mb-3 transition">
                <i class="fas fa-clock text-sm"></i>
            </div>
            <p class="text-2xl font-bold text-gray-800">{{ $sched['issues'] }}<span class="text-sm text-gray-400 font-normal">/{{ $sched['total'] }}</span></p>
            <p class="text-xs text-gray-400 mt-0.5">Scheduled Tasks Need Attention</p>
        </a>

        <a href="{{ route('admin.failed-jobs') }}" class="{{ $cardBase }} hover:border-red-300 hover:shadow-md">
            <div class="w-9 h-9 rounded-xl {{ $iconCls($jobsNeedAttention) }} flex items-center justify-center mb-3 transition">
                <i class="fas fa-xmark text-sm"></i>
            </div>
            <p class="text-2xl font-bold text-gray-800">{{ $failedJobs }}</p>
            <p class="text-xs text-gray-400 mt-0.5">Failed Jobs</p>
        </a>

    </div>
</div>
@endif

{{-- ── Command Center: pinned-menu shortcuts (see sidebar's pin button, dashboard.blade.php) ── --}}
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm px-4 py-3">
    <div class="flex items-center justify-between mb-2.5">
        <div class="flex items-center gap-2">
            <i class="fas fa-bolt text-red-700 text-sm"></i>
            <h3 class="text-sm font-bold text-gray-800">Command Center</h3>
        </div>
        <div class="flex gap-1 bg-gray-100 p-1 rounded-lg">
            <button type="button" id="ccTabPinned" onclick="ccSwitchTab('pinned')" class="px-3 py-1.5 rounded-md text-xs font-semibold transition-colors">Pinned</button>
            <button type="button" id="ccTabAll" onclick="ccSwitchTab('all')" class="px-3 py-1.5 rounded-md text-xs font-semibold transition-colors">All Menu</button>
        </div>
    </div>
    <div id="ccGrid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:8px;"></div>
</div>

{{-- ── Row 2: KPI Cards ──────────────────────────────────────────────────────── --}}
<div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-4">

    {{-- Employees --}}
    @if($can('master.employee'))
    <a href="{{ route('master.employee.index') }}" class="{{ $cardBase }} hover:border-blue-300 hover:shadow-md">
    @else
    <div class="{{ $cardBase }}">
    @endif
        <div class="w-9 h-9 rounded-xl bg-blue-50 group-hover:bg-blue-100 flex items-center justify-center mb-3 transition">
            <i class="fas fa-users text-blue-600 text-sm"></i>
        </div>
        <p class="text-2xl font-bold text-gray-800">{{ $data['employee'] ?? 0 }}</p>
        <p class="text-xs text-gray-400 mt-0.5">Employees</p>
    @if($can('master.employee'))</a>@else</div>@endif

    {{-- Customers --}}
    @if($can('master.customer'))
    <a href="{{ route('master.customer.index') }}" class="{{ $cardBase }} hover:border-green-300 hover:shadow-md">
    @else
    <div class="{{ $cardBase }}">
    @endif
        <div class="w-9 h-9 rounded-xl bg-green-50 group-hover:bg-green-100 flex items-center justify-center mb-3 transition">
            <i class="fas fa-building text-green-600 text-sm"></i>
        </div>
        <p class="text-2xl font-bold text-gray-800">{{ $data['customers'] ?? 0 }}</p>
        <p class="text-xs text-gray-400 mt-0.5">Customers</p>
    @if($can('master.customer'))</a>@else</div>@endif

    {{-- Active Projects --}}
    @if($can('delivery.project'))
    <a href="{{ route('projects.index') }}" class="{{ $cardBase }} hover:border-purple-300 hover:shadow-md">
    @else
    <div class="{{ $cardBase }}">
    @endif
        <div class="w-9 h-9 rounded-xl bg-purple-50 group-hover:bg-purple-100 flex items-center justify-center mb-3 transition">
            <i class="fas fa-project-diagram text-purple-600 text-sm"></i>
        </div>
        <p class="text-2xl font-bold text-gray-800">{{ $data['active_projects'] ?? 0 }}</p>
        <p class="text-xs text-gray-400 mt-0.5">Active Projects</p>
    @if($can('delivery.project'))</a>@else</div>@endif

    {{-- Total Tickets --}}
    <a href="{{ route('ticket.index') }}" class="{{ $cardBase }} hover:border-red-300 hover:shadow-md">
        <div class="w-9 h-9 rounded-xl bg-red-50 group-hover:bg-red-100 flex items-center justify-center mb-3 transition">
            <i class="fas fa-ticket-alt text-red-600 text-sm"></i>
        </div>
        <p class="text-2xl font-bold text-gray-800">{{ number_format($stats['total'] ?? 0) }}</p>
        <p class="text-xs text-gray-400 mt-0.5">Total Tickets</p>
    </a>

    {{-- Active Tickets --}}
    <a href="{{ route('ticket.index') }}" class="{{ $cardBase }} hover:border-blue-300 hover:shadow-md">
        <div class="w-9 h-9 rounded-xl bg-blue-50 group-hover:bg-blue-100 flex items-center justify-center mb-3 transition">
            <i class="fas fa-clock text-blue-600 text-sm"></i>
        </div>
        <p class="text-2xl font-bold text-gray-800">{{ $activeTickets }}</p>
        <p class="text-xs text-gray-400 mt-0.5">Active Tickets</p>
    </a>

    {{-- SLA Compliance --}}
    @if($can('sla.report'))
    <a href="{{ route('sla.report') }}" class="{{ $cardBase }} hover:border-emerald-300 hover:shadow-md">
    @else
    <div class="{{ $cardBase }}">
    @endif
        <div class="w-9 h-9 rounded-xl bg-emerald-50 group-hover:bg-emerald-100 flex items-center justify-center mb-3 transition">
            <i class="fas fa-stopwatch text-emerald-600 text-sm"></i>
        </div>
        @if($sla && $sla['compliance_rate'] !== null)
            <p class="text-2xl font-bold {{ $sla['compliance_rate'] >= 80 ? 'text-emerald-600' : ($sla['compliance_rate'] >= 60 ? 'text-yellow-600' : 'text-red-600') }}">
                {{ $sla['compliance_rate'] }}%
            </p>
        @else
            <p class="text-2xl font-bold text-gray-400">-</p>
        @endif
        <p class="text-xs text-gray-400 mt-0.5">SLA Compliance</p>
    @if($can('sla.report'))</a>@else</div>@endif

</div>

{{-- ── Row 3: Ticket Status Breakdown ──────────────────────────────────────── --}}
@if(!empty($stats))
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
@endif

{{-- ── Row 4: Chart + Team Load ──────────────────────────────────────────────── --}}
@if(!empty($data['ticket_chart']['labels']))
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

    <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
        <div class="flex items-center justify-between mb-4">
            <div>
                <p class="text-sm font-semibold text-gray-800">Ticket Submissions</p>
                <p class="text-xs text-gray-400 mt-0.5">Last 30 days</p>
            </div>
            <span class="text-xs text-gray-400">{{ now()->format('d M Y') }}</span>
        </div>
        <canvas id="dashTicketChart" height="80"></canvas>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 flex flex-col">
        <div class="flex items-center justify-between mb-4">
            <div>
                <p class="text-sm font-semibold text-gray-800">Agent Workload</p>
                <p class="text-xs text-gray-400 mt-0.5">Active tickets per agent</p>
            </div>
            @if($can('ticket.consultant-workload'))
            <a href="{{ route('ticket.consultant-workload') }}" class="text-xs font-semibold text-red-700 hover:text-red-800">All →</a>
            @endif
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
@endif

{{-- ── Row 5: Recent Tickets + Quick Nav ───────────────────────────────────── --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

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
                    <p class="text-[10px] text-gray-400 mt-0.5">
                        {{ $t->customer_name ?? '-' }} &middot; {{ $t->pic_name ?? 'Unassigned' }} &middot; {{ \Carbon\Carbon::parse($t->created_at)->diffForHumans() }}
                    </p>
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

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
        <p class="text-sm font-semibold text-gray-800 mb-4">Quick Navigation</p>
        @php
            $navItems = [];
            if ($can('tickets.inbox'))
                $navItems[] = ['href' => route('ticket.index'),          'icon' => 'fa-ticket-alt',      'bg' => 'bg-red-50',    'color' => 'text-red-600',    'label' => 'Tickets'];
            if ($can('tickets.staging'))
                $navItems[] = ['href' => route('staging.index'),         'icon' => 'fa-inbox',           'bg' => 'bg-amber-50',  'color' => 'text-amber-600',  'label' => 'Validation', 'badge' => $stagingPend];
            if ($can('ticket.my-tasks'))
                $navItems[] = ['href' => route('ticket.task'),           'icon' => 'fa-tasks',           'bg' => 'bg-sky-50',    'color' => 'text-sky-600',    'label' => 'My Tasks'];
            if ($can('master.employee'))
                $navItems[] = ['href' => route('master.employee.index'), 'icon' => 'fa-users',           'bg' => 'bg-blue-50',   'color' => 'text-blue-600',   'label' => 'Employees'];
            if ($can('master.customer'))
                $navItems[] = ['href' => route('master.customer.index'), 'icon' => 'fa-building',        'bg' => 'bg-green-50',  'color' => 'text-green-600',  'label' => 'Customers'];
            if ($can('delivery.project'))
                $navItems[] = ['href' => route('projects.index'),        'icon' => 'fa-project-diagram', 'bg' => 'bg-purple-50', 'color' => 'text-purple-600', 'label' => 'Projects'];
            if ($can('reporting'))
                $navItems[] = ['href' => route('reporting'),             'icon' => 'fa-chart-bar',       'bg' => 'bg-indigo-50', 'color' => 'text-indigo-600', 'label' => 'Reporting'];
            if ($can('sla.report'))
                $navItems[] = ['href' => route('sla.report'),            'icon' => 'fa-stopwatch',       'bg' => 'bg-emerald-50','color' => 'text-emerald-600','label' => 'SLA'];
            if ($can('management'))
                $navItems[] = ['href' => route('admin.index'),           'icon' => 'fa-shield-alt',      'bg' => 'bg-gray-100',  'color' => 'text-gray-600',   'label' => 'Control'];
        @endphp
        <div class="grid grid-cols-2 gap-2">
            @foreach($navItems as $nav)
            <a href="{{ $nav['href'] }}"
                class="relative flex flex-col items-center gap-2 p-3 rounded-xl {{ $nav['bg'] }} hover:ring-2 hover:ring-offset-1 hover:ring-gray-200 transition-all text-center">
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

        {{-- System health has its own dedicated panel above (Superadmin Control Panel) - not duplicated here. --}}
    </div>

</div>

</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    const labels    = @json($data['ticket_chart']['labels'] ?? []);
    const chartData = @json($data['ticket_chart']['data']   ?? []);
    const ctx = document.getElementById('dashTicketChart');
    if (ctx && labels.length) {
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
                    tooltip: { callbacks: { label: c => c.parsed.y + ' ticket' + (c.parsed.y !== 1 ? 's' : '') } }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { font: { size: 11 }, maxTicksLimit: 10, color: '#9ca3af' } },
                    y: { beginAtZero: true, grid: { color: '{{ (session('user_preferences')['theme'] ?? 'light') === 'dark' ? 'rgba(255,255,255,0.09)' : 'rgba(0,0,0,0.04)' }}' }, ticks: { stepSize: 1, precision: 0, color: '#9ca3af', font: { size: 11 } } }
                }
            }
        });
    }

    function updateClock() {
        const el = document.getElementById('dashClock');
        if (el) el.textContent = new Date().toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }
    updateClock();
    setInterval(updateClock, 1000);
})();
</script>

<script>
// Command Center — reads the sidebar's already-rendered nav links (this page
// and the sidebar share one document) rather than querying a backend menu
// list, so it only ever shows exactly what this user's sidebar shows them.
(function () {
    var ccAllItems = [];
    var ccCurrentTab = 'pinned';

    function ccEsc(str) {
        return String(str == null ? '' : str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function ccCollectMenuItems() {
        var scroll = document.getElementById('sidebarNavScroll');
        if (!scroll) return [];
        var items = [];

        scroll.querySelectorAll('a.nav-link').forEach(function (link) {
            var href = link.getAttribute('href');
            if (!href || href === '#') return;

            var textEl = link.querySelector('.nav-text');
            var label = (textEl ? textEl.textContent : link.textContent).trim();
            if (!label) return;

            var iconEl = link.querySelector('.nav-icon i');
            var icon = iconEl ? iconEl.className : 'fas fa-circle';

            // Breadcrumb: walk up through every ancestor "...Dropdown" container
            // and read its toggler button's own label (e.g. Reporting > Support).
            var crumbs = [];
            var el = link.parentElement;
            while (el && el !== scroll) {
                if (el.id && /Dropdown$/.test(el.id)) {
                    var toggler = el.previousElementSibling;
                    var tTextEl = toggler ? toggler.querySelector('.nav-text') : null;
                    var tText = toggler ? (tTextEl ? tTextEl.textContent : toggler.textContent).trim() : '';
                    if (tText) crumbs.unshift(tText);
                }
                el = el.parentElement;
            }

            items.push({ href: href, label: label, icon: icon, breadcrumb: crumbs.join(' → ') });
        });

        return items;
    }

    function ccRender() {
        var grid = document.getElementById('ccGrid');
        if (!grid) return;

        var pinned = window.PINNED_MENUS || [];
        var items = ccCurrentTab === 'pinned'
            ? ccAllItems.filter(function (it) { return pinned.indexOf(it.href) !== -1; })
            : ccAllItems;

        document.getElementById('ccTabPinned').className = 'px-3 py-1.5 rounded-md text-xs font-semibold transition-colors ' + (ccCurrentTab === 'pinned' ? 'bg-red-800 text-white' : 'text-gray-500 hover:text-gray-700');
        document.getElementById('ccTabAll').className = 'px-3 py-1.5 rounded-md text-xs font-semibold transition-colors ' + (ccCurrentTab === 'all' ? 'bg-red-800 text-white' : 'text-gray-500 hover:text-gray-700');

        if (!items.length) {
            grid.innerHTML = '<div class="col-span-full text-center text-xs text-gray-400 py-6">' + (
                ccCurrentTab === 'pinned'
                    ? 'No pinned menus yet. Hover any menu in the sidebar and click the <i class="fas fa-thumbtack"></i> icon to add it here.'
                    : 'No accessible menu items.'
            ) + '</div>';
            return;
        }

        grid.innerHTML = items.map(function (it) {
            var unpinBtn = ccCurrentTab === 'pinned'
                ? '<button type="button" class="cc-unpin-btn flex items-center justify-center rounded-full text-gray-300 hover:text-red-600 hover:bg-red-50" style="position:absolute;top:4px;right:4px;width:16px;height:16px;font-size:10px;line-height:1;" data-href="' + ccEsc(it.href) + '" title="Unpin"><i class="fas fa-xmark"></i></button>'
                : '';
            return '<a href="' + ccEsc(it.href) + '" class="relative flex items-center rounded-lg border border-gray-200 hover:border-red-300 hover:shadow-sm transition-all" style="gap:10px;padding:8px 10px;">'
                + '<div class="rounded-md bg-red-50 text-red-700 text-xs flex items-center justify-center flex-shrink-0" style="width:32px;height:32px;"><i class="' + ccEsc(it.icon) + '"></i></div>'
                + '<div class="min-w-0 flex-1" style="padding-right:14px;">'
                + '<p class="text-xs font-bold text-gray-800 truncate">' + ccEsc(it.label) + '</p>'
                + (it.breadcrumb ? '<p class="text-[10px] text-gray-400 truncate">' + ccEsc(it.breadcrumb) + '</p>' : '')
                + '</div>'
                + unpinBtn
                + '</a>';
        }).join('');

        grid.querySelectorAll('.cc-unpin-btn').forEach(function (btn) {
            btn.addEventListener('click', function (ev) {
                ev.preventDefault();
                ev.stopPropagation();
                if (typeof window.toggleMenuPin === 'function') window.toggleMenuPin(btn.dataset.href, null);
                ccRender();
            });
        });
    }

    window.ccSwitchTab = function (tab) {
        ccCurrentTab = tab;
        ccRender();
    };

    // Sidebar's toggleMenuPin() (dashboard.blade.php) calls this after every
    // pin/unpin so this widget stays in sync without a page reload.
    window.onPinnedMenusChanged = ccRender;

    document.addEventListener('DOMContentLoaded', function () {
        ccAllItems = ccCollectMenuItems();
        ccRender();
    });
})();
</script>
@endpush
@endsection

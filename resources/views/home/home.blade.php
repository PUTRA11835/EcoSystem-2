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

    // Sapaan, nama depan, dan badge peran pindah ke kartu hero milik modul
    // HR & General yang disisipkan di bawah. Variabelnya dihapus dari sini
    // supaya tidak ada dua sumber untuk teks yang sama.

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

    // Build dynamic KPI cards list based on user role permissions
    $kpiCards = [];

    if ($can('master.employee')) {
        $kpiCards[] = [
            'href' => route('management.employee.basic-data.index'),
            'icon' => 'fa-users',
            'bg'   => 'bg-blue-50',
            'hover' => 'group-hover:bg-blue-100',
            'color' => 'text-blue-600',
            'border' => 'hover:border-blue-300',
            'val'   => number_format($data['employee'] ?? 0),
            'label' => 'Employees',
        ];
    }

    if ($can('master.customer')) {
        $kpiCards[] = [
            'href' => route('master.customer.index'),
            'icon' => 'fa-building',
            'bg'   => 'bg-green-50',
            'hover' => 'group-hover:bg-green-100',
            'color' => 'text-green-600',
            'border' => 'hover:border-green-300',
            'val'   => number_format($data['customers'] ?? 0),
            'label' => 'Customers',
        ];
    }

    if ($can('delivery.project')) {
        $kpiCards[] = [
            'href' => route('projects.index'),
            'icon' => 'fa-project-diagram',
            'bg'   => 'bg-purple-50',
            'hover' => 'group-hover:bg-purple-100',
            'color' => 'text-purple-600',
            'border' => 'hover:border-purple-300',
            'val'   => number_format($data['active_projects'] ?? 0),
            'label' => 'Active Projects',
        ];
    }

    // ESS Cards DIHAPUS 3 Sep 2026 (bukan diperkecil fontnya).
    //
    // Blok ini menaruh Leave & Permit / My Attendance / My Overtime /
    // Reimbursement sebagai "kartu KPI" berlabel teks di tengah grid yang
    // seharusnya berisi ANGKA (Employees, Total Tickets, dst). Slot
    // `text-lg sm:text-2xl font-bold ... truncate` dirancang untuk angka
    // pendek, sehingga label panjang terpotong ("Leave & Pe...").
    //
    // Menghapus, bukan memperkecil font, karena keempatnya murni DUPLIKAT:
    // Leave & Permit dan My Attendance sudah punya tombol cepat di hero
    // ("Apply Leave & Permit" / "My Attendance") DAN ubin di Daily Access;
    // Overtime dan Reimbursement sudah jadi item tingkat atas di sidebar.
    // Prinsip yang sama sudah dipakai untuk Daily Access sendiri — lihat
    // komentar di hr-general/dashboard/attendance.blade.php.

    // Tickets Cards
    if ($can('ticket') || $can('ticket.index') || !empty($stats['total'])) {
        $kpiCards[] = [
            'href' => route('ticket.index'),
            'icon' => 'fa-ticket-alt',
            'bg'   => 'bg-red-50',
            'hover' => 'group-hover:bg-red-100',
            'color' => 'text-red-600',
            'border' => 'hover:border-red-300',
            'val'   => number_format($stats['total'] ?? 0),
            'label' => 'Total Tickets',
        ];
        $kpiCards[] = [
            'href' => route('ticket.index'),
            'icon' => 'fa-clock',
            'bg'   => 'bg-blue-50',
            'hover' => 'group-hover:bg-blue-100',
            'color' => 'text-blue-600',
            'border' => 'hover:border-blue-300',
            'val'   => number_format($activeTickets ?? 0),
            'label' => 'Active Tickets',
        ];
    }

    // Tren mingguan: 7 hari terakhir vs 7 hari sebelumnya, dihitung dari deret grafik 30 hari yang sudah dimuat
    // controller (TANPA kueri tambahan). Netral (bukan hijau/merah): lebih banyak tiket bukan otomatis "buruk".
    $chartSeries = array_map('intval', (array) ($data['ticket_chart']['data'] ?? []));
    if (count($chartSeries) >= 14 && !empty($kpiCards)) {
        $wk  = array_sum(array_slice($chartSeries, -7));
        $pwk = array_sum(array_slice($chartSeries, -14, 7));
        foreach ($kpiCards as $i => $c) {
            if (($c['label'] ?? '') === 'Total Tickets') {
                if ($wk > 0 || $pwk > 0) {
                    $kpiCards[$i]['sub']       = ($wk > $pwk ? '▲ ' : ($wk < $pwk ? '▼ ' : '• ')) . $wk . ' this week';
                    $kpiCards[$i]['sub_title'] = "{$wk} tickets in the last 7 days vs {$pwk} in the 7 days before";
                }
            }
        }
    }

    // SLA Compliance
    if ($can('sla.report')) {
        $slaVal = ($sla && $sla['compliance_rate'] !== null) ? $sla['compliance_rate'] . '%' : '—';
        $kpiCards[] = [
            'href' => route('sla.report'),
            'icon' => 'fa-stopwatch',
            'bg'   => 'bg-emerald-50',
            'hover' => 'group-hover:bg-emerald-100',
            'color' => 'text-emerald-600',
            'border' => 'hover:border-emerald-300',
            'val'   => $slaVal,
            'label' => 'SLA Compliance',
        ];
    }
@endphp

@section('page-actions')
<div class="flex items-center gap-1.5 sm:gap-2 shrink-0">
    @if($stagingPend > 0 && $can('tickets.staging'))
    <a href="{{ route('staging.index') }}"
        title="{{ $stagingPend }} pending validation"
        class="inline-flex items-center gap-1.5 bg-amber-50 border border-amber-200 text-amber-700 text-xs font-semibold px-2 py-1 sm:px-3 sm:py-1.5 rounded-lg hover:bg-amber-100 transition shadow-sm whitespace-nowrap active:scale-95">
        <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse shrink-0"></span>
        <span class="hidden md:inline">{{ $stagingPend }} pending validation</span>
        <span class="inline md:hidden">{{ $stagingPend }} Pending</span>
    </a>
    @endif
    {{-- Jam perusahaan (WIB), terang dan tenang: tanpa detik (tak ada gerakan yang mengalihkan perhatian). --}}
    <span class="hidden shrink-0 items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-medium tabular-nums text-gray-700 lg:inline-flex" title="Company time">
        <i class="far fa-clock text-gray-400"></i><span id="dashClock">{{ now()->format('H:i') }}</span><span class="text-gray-400">{{ ['Asia/Jakarta' => 'WIB', 'Asia/Makassar' => 'WITA', 'Asia/Jayapura' => 'WIT'][config('app.timezone')] ?? config('app.timezone') }}</span>
    </span>
</div>
@endsection

{{-- Wadah dashboard: LEBAR PENUH dan responsif. Sebelumnya dibatasi 1600 px sehingga saat browser di-zoom-out
     konten tidak ikut melebar (keluhan pemilik 8 Okt). Kini kolom-kolom grid yang menyesuaikan lebar layar
     (auto-fit / breakpoint xl), bukan batas lebar tetap. --}}
<div class="w-full space-y-6">

{{-- ── Row 1: Hero + Command Center + blok Attendance (partial modul HR & General) ───────── --}}
@push('dash-after-hero')
{{-- Ringkasan perusahaan (didorong ke slot 'dash-after-hero' di partial, tepat di bawah header) — satu kartu bersekat, bukan deretan kartu terpisah ──── --}}
@if(!empty($kpiCards))
<section aria-label="Company overview">
    <div class="mb-2 flex items-end justify-between">
        <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500">Company overview</h3>
    </div>
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        @php
            // Kolom mengikuti jumlah kartu yang boleh dilihat pengguna, agar strip selalu terisi penuh
            // (nama kelas ditulis utuh supaya terbaca Tailwind CDN).
            $kpiCols = [1 => 'xl:grid-cols-1', 2 => 'xl:grid-cols-2', 3 => 'xl:grid-cols-3', 4 => 'xl:grid-cols-4', 5 => 'xl:grid-cols-5'][count($kpiCards)] ?? 'xl:grid-cols-6';
        @endphp
        <div class="grid grid-cols-2 sm:grid-cols-3 {{ $kpiCols }} divide-x divide-y divide-gray-100 xl:divide-y-0">
            @foreach($kpiCards as $card)
            <a href="{{ $card['href'] }}" class="group relative flex items-center gap-3.5 px-5 py-4 transition-colors hover:bg-gray-50">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ $card['bg'] }}">
                    <i class="fas {{ $card['icon'] }} {{ $card['color'] }} text-sm"></i>
                </span>
                <span class="min-w-0">
                    <span class="block truncate text-2xl font-bold leading-none tabular-nums text-gray-900">{{ $card['val'] }}</span>
                    {{-- Label + tren dalam SATU baris, supaya tinggi semua sel sama (sebelumnya sel Total Tickets lebih tinggi). --}}
                    <span class="mt-1 flex items-baseline gap-2 truncate text-xs font-medium text-gray-500">
                        <span class="truncate">{{ $card['label'] }}</span>
                        @if(!empty($card['sub']))
                        <span class="shrink-0 font-normal text-gray-400" title="{{ $card['sub_title'] ?? '' }}">{{ $card['sub'] }}</span>
                        @endif
                    </span>
                </span>
                <i class="fas fa-chevron-right absolute right-4 top-1/2 -translate-y-1/2 text-[10px] text-gray-300 opacity-0 transition-opacity group-hover:opacity-100"></i>
            </a>
            @endforeach
        </div>
    </div>
</section>
@endif
@endpush

@include('hr-general.dashboard.attendance')

{{-- ── Row 3: Tiket — ringkasan status + grafik (kiri) dan beban agen (kanan) ──────────── --}}
@if(!empty($stats) || !empty($data['ticket_chart']['labels']))
@php $statusTotal = max(1, collect(array_keys($statusCfg))->sum(fn ($k) => (int) ($stats[$k] ?? 0))); @endphp
<section aria-label="Tickets" class="grid grid-cols-1 gap-6 xl:grid-cols-3">

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm xl:col-span-2">
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3.5">
            <div>
                <h3 class="text-sm font-semibold text-gray-900">Ticket overview</h3>
                <p class="text-xs text-gray-500">Status distribution and submissions over the last 30 days</p>
            </div>
            <a href="{{ route('ticket.index') }}" class="text-xs font-semibold primary-text hover:underline">View all &rarr;</a>
        </div>

        @if(!empty($stats))
        <div class="px-5 pt-5">
            {{-- Bar bertumpuk: panjang tiap segmen = porsi status itu dari seluruh tiket. --}}
            <div class="flex h-2 w-full overflow-hidden rounded-full bg-gray-100" role="img" aria-label="Ticket status distribution">
                @foreach($statusCfg as $key => $cfg)
                    @php $n = (int) ($stats[$key] ?? 0); @endphp
                    @if($n > 0)
                    <span class="{{ $cfg['dot'] }} h-full" style="width: {{ round($n / $statusTotal * 100, 2) }}%" title="{{ $cfg['label'] }}: {{ $n }}"></span>
                    @endif
                @endforeach
            </div>
            <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 sm:grid-cols-4">
                @foreach($statusCfg as $key => $cfg)
                <div class="flex items-center gap-2.5">
                    <span class="h-2 w-2 shrink-0 rounded-full {{ $cfg['dot'] }}"></span>
                    <div class="min-w-0">
                        <dt class="truncate text-[11px] text-gray-500">{{ $cfg['label'] }}</dt>
                        <dd class="text-base font-bold leading-tight tabular-nums text-gray-900">{{ number_format($stats[$key] ?? 0) }}</dd>
                    </div>
                </div>
                @endforeach
            </dl>
        </div>
        @endif

        @if(!empty($data['ticket_chart']['labels']))
        <div class="px-5 pb-5 pt-5">
            <div class="relative h-56">
                <canvas id="dashTicketChart"></canvas>
            </div>
        </div>
        @endif
    </div>

    <div class="flex flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3.5">
            <div>
                <h3 class="text-sm font-semibold text-gray-900">Agent workload</h3>
                <p class="text-xs text-gray-500">Active tickets per agent</p>
            </div>
            @if($can('master.employee'))
            <a href="{{ route('master.employee.index') }}" class="text-xs font-semibold primary-text hover:underline">All &rarr;</a>
            @endif
        </div>
        @if($teamLoad->isEmpty())
        <div class="flex flex-1 items-center justify-center px-5 py-10 text-center">
            <div>
                <i class="fas fa-check-circle mb-2 text-3xl text-green-400"></i>
                <p class="text-xs text-gray-500">No active workload</p>
            </div>
        </div>
        @else
        @php $maxLoad = $teamLoad->max('open_count') ?: 1; @endphp
        <ul class="flex-1 divide-y divide-gray-100">
            @foreach($teamLoad as $m)
            @php
                $pct   = round(($m->open_count / $maxLoad) * 100);
                $barCl = $pct >= 80 ? 'bg-red-500' : ($pct >= 50 ? 'bg-amber-500' : 'bg-emerald-500');
                $initials = collect(preg_split('/\s+/', trim((string) $m->name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
            @endphp
            <li class="flex items-center gap-3 px-5 py-3">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-100 text-[11px] font-bold text-gray-600">{{ $initials ?: '?' }}</span>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center justify-between gap-2">
                        <span class="truncate text-xs font-medium text-gray-800">{{ $m->name }}</span>
                        <span class="text-xs font-bold tabular-nums text-gray-900">{{ $m->open_count }}</span>
                    </div>
                    <div class="mt-1.5 h-1.5 w-full rounded-full bg-gray-100">
                        <div class="{{ $barCl }} h-1.5 rounded-full transition-all" style="width: {{ $pct }}%"></div>
                    </div>
                </div>
            </li>
            @endforeach
        </ul>
        @endif
    </div>

</section>
@endif

{{-- ── Row 4: Tiket terbaru (kiri) + navigasi cepat (kanan) ─────────────────────────────── --}}
<section aria-label="Recent activity" class="grid grid-cols-1 gap-6 xl:grid-cols-3">

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm xl:col-span-2">
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3.5">
            <div>
                <h3 class="text-sm font-semibold text-gray-900">Recent tickets</h3>
                <p class="text-xs text-gray-500">Latest tickets across all customers</p>
            </div>
            <a href="{{ route('ticket.index') }}" class="text-xs font-semibold primary-text hover:underline">View all &rarr;</a>
        </div>
        @if($recentTkts->isEmpty())
        <div class="py-12 text-center text-sm text-gray-500">No tickets yet</div>
        @else
        <div class="hidden grid-cols-12 gap-3 border-b border-gray-100 bg-gray-50/60 px-5 py-2 text-[11px] font-semibold uppercase tracking-wide text-gray-500 md:grid">
            <span class="col-span-7">Ticket</span>
            <span class="col-span-2">Priority</span>
            <span class="col-span-3 text-right">Status</span>
        </div>
        <div class="divide-y divide-gray-100">
            @foreach($recentTkts as $t)
            @php
                $sc = $statusCfg[$t->status] ?? ['dot'=>'bg-gray-400','text'=>'text-gray-500','bg'=>'bg-gray-100','label'=>'Unknown'];
                $pc = $prioCfg[$t->ticket_priority ?? ''] ?? 'text-gray-500 bg-gray-100';
            @endphp
            <a href="{{ route('ticket.show', $t->ticket_id) }}" class="group grid grid-cols-12 items-center gap-3 px-5 py-3 transition-colors hover:bg-gray-50">
                <div class="col-span-12 min-w-0 md:col-span-7">
                    <div class="flex items-center gap-2">
                        <span class="font-mono text-xs font-bold text-gray-800 group-hover:text-red-700">#{{ $t->ticket_number }}</span>
                        <span class="truncate text-xs text-gray-600">{{ Str::limit($t->description ?? '', 60) }}</span>
                    </div>
                    <p class="mt-0.5 truncate text-[11px] text-gray-500">
                        {{ $t->customer_name ?? '—' }} &middot; {{ $t->pic_name ?? 'Unassigned' }} &middot; {{ \Carbon\Carbon::parse($t->created_at)->diffForHumans() }}
                    </p>
                </div>
                <div class="hidden md:col-span-2 md:block">
                    @if($t->ticket_priority)
                    <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $pc }}">{{ $t->ticket_priority }}</span>
                    @else
                    <span class="text-xs text-gray-400">&ndash;</span>
                    @endif
                </div>
                <div class="col-span-12 md:col-span-3 md:text-right">
                    <span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $sc['text'] }} {{ $sc['bg'] }}">
                        <span class="h-1.5 w-1.5 rounded-full {{ $sc['dot'] }}"></span>{{ $sc['label'] }}
                    </span>
                </div>
            </a>
            @endforeach
        </div>
        @endif
    </div>

    <div class="flex flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-5 py-3.5">
            <h3 class="text-sm font-semibold text-gray-900">Quick navigation</h3>
            <p class="text-xs text-gray-500">Jump to the areas you use most</p>
        </div>
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
                $navItems[] = ['href' => route('admin.index'),           'icon' => 'fa-shield-alt',      'bg' => 'bg-gray-100',  'color' => 'text-gray-600',   'label' => 'Control Center'];
        @endphp
        <ul class="flex-1 divide-y divide-gray-100">
            @foreach($navItems as $nav)
            <li>
                <a href="{{ $nav['href'] }}" class="group flex items-center gap-3 px-5 py-2.5 transition-colors hover:bg-gray-50">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $nav['bg'] }}">
                        <i class="fas {{ $nav['icon'] }} {{ $nav['color'] }} text-xs"></i>
                    </span>
                    <span class="flex-1 text-sm font-medium text-gray-800">{{ $nav['label'] }}</span>
                    @if(!empty($nav['badge']) && $nav['badge'] > 0)
                    <span class="rounded-full bg-red-500 px-1.5 py-0.5 text-[10px] font-bold leading-none text-white">{{ $nav['badge'] > 99 ? '99+' : $nav['badge'] }}</span>
                    @endif
                    <i class="fas fa-chevron-right text-[10px] text-gray-300 transition-transform group-hover:translate-x-0.5 group-hover:text-gray-500"></i>
                </a>
            </li>
            @endforeach
        </ul>

        @if($can('management'))
        <div class="border-t border-gray-100 bg-gray-50/60 px-5 py-3">
            <p class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-gray-500">System health</p>
            <div class="space-y-1.5">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-green-500" id="dashDbDot"></span>
                        <span class="text-xs text-gray-600">Database</span>
                    </div>
                    <span class="text-xs font-medium text-gray-600" id="dashDbTxt">Checking...</span>
                </div>
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-gray-300" id="dashQueueDot"></span>
                        <span class="text-xs text-gray-600">Queue</span>
                    </div>
                    <span class="text-xs font-medium text-gray-600" id="dashQueueTxt">Checking...</span>
                </div>
            </div>
        </div>
        @endif
    </div>

</section>

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
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    pointBackgroundColor: '#dc2626',
                    tension: 0.4,
                    fill: true,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
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

    // Jam zona perusahaan + koreksi selisih jam browser (sama dengan jam kartu sapaan); diperbarui tiap 15 dtk.
    const clockTz = @json(config('app.timezone'));
    const clockSkew = {{ now()->getTimestampMs() }} - Date.now();
    const clockFmt = new Intl.DateTimeFormat('en-GB', { timeZone: clockTz, hour: '2-digit', minute: '2-digit', hour12: false });
    function updateClock() {
        const el = document.getElementById('dashClock');
        if (el) el.textContent = clockFmt.format(new Date(Date.now() + clockSkew));
    }
    setInterval(updateClock, 15000);

    @if($can('management'))
    fetch('/api/health', { credentials: 'same-origin' })
        .then(r => r.json())
        .then(d => {
            const dbOk = d.checks?.database === 'ok';
            document.getElementById('dashDbDot').className = 'w-2 h-2 rounded-full ' + (dbOk ? 'bg-green-500' : 'bg-red-500');
            document.getElementById('dashDbTxt').textContent = dbOk ? 'Connected' : 'Error';
            const failed  = d.checks?.queue_failed  ?? 0;
            const pending = d.checks?.queue_pending ?? 0;
            document.getElementById('dashQueueDot').className = 'w-2 h-2 rounded-full ' + (failed === 0 ? 'bg-green-500' : 'bg-orange-500');
            document.getElementById('dashQueueTxt').textContent = pending + ' pending' + (failed ? ', ' + failed + ' failed' : '');
        })
        .catch(() => {});
    @endif
})();
</script>
@endpush
@endsection

@extends('dashboard')

@section('title', 'Reimbursement — Dashboard')
@section('page-title', 'Reimbursement')
@section('page-subtitle', 'Where reimbursement stands for the period you pick, and what needs you')

@section('content')
@php
    use App\Models\Reimbursement\ReimbursementRequest;

    $rupiah  = fn ($n) => ReimbursementRequest::formatRupiah($n);
    $maxBar  = max(1, (float) $trend->max('amount'));
    $statusTotal = max(1, array_sum($statusRows));
    $statusTone  = ['Open' => 'bg-blue-500', 'Approved' => 'bg-green-500', 'Rejected' => 'bg-red-500'];
@endphp

@include('hr-general.reimbursement.components.hub-tabs')

<div class="space-y-5">

    {{-- Period: the figures, trend, status mix and top requesters follow it; the queues on the left of the
         bottom row are always "right now". --}}
    <form method="GET" action="{{ route('general.reimbursement.dashboard') }}" id="rbPeriodForm"
          class="bg-white rounded-xl p-4 shadow-sm flex flex-col sm:flex-row sm:items-end gap-3">
        <div class="sm:w-60">
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1" for="rbPeriod">Period</label>
            <select name="period" id="rbPeriod" onchange="rbPeriodChange()">
                @foreach($periods as $value => $text)
                    <option value="{{ $value }}" @selected($period['key'] === $value)>{{ $text }}</option>
                @endforeach
            </select>
        </div>
        <div id="rbMonthBox" class="sm:w-44 {{ $period['key'] === 'month' ? '' : 'hidden' }}">
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1" for="rbMonth">Month</label>
            <input type="month" name="month" id="rbMonth" value="{{ $period['month'] }}" onchange="this.form.requestSubmit()"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
        </div>
        <div id="rbYearBox" class="sm:w-32 {{ $period['key'] === 'year' ? '' : 'hidden' }}">
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1" for="rbYear">Year</label>
            <select name="year" id="rbYear" onchange="this.form.requestSubmit()">
                @for($y = now()->year + 1; $y >= now()->year - 5; $y--)
                    <option value="{{ $y }}" @selected($period['year'] === $y)>{{ $y }}</option>
                @endfor
            </select>
        </div>
        <p class="sm:ml-auto text-xs text-gray-500">Showing <span class="font-semibold text-gray-800">{{ $period['label'] }}</span>
            @if($period['from']) <span class="text-gray-400">· {{ $period['from']->format('d M Y') }} – {{ $period['to']->format('d M Y') }}</span>@endif
        </p>
    </form>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach([
            ['Waiting for You', $cards['mine'], 'fa-hourglass-half', 'text-red-800', route('general.reimbursement.index', ['scope' => 'mine']), 'Documents at your step'],
            ['Open Documents', $cards['pending'], 'fa-clock', 'text-amber-600', route('general.reimbursement.index'), $rupiah($cards['pendingAmount']) . ' pending'],
            [$monthLabel, $cards['month'], 'fa-calendar-days', 'text-gray-900', $period['from'] && in_array($period['key'], ['month', 'this_month', 'last_month'], true) ? route('general.reimbursement.index', ['month' => $period['from']->format('Y-m'), 'status' => 'all']) : null, $rupiah($cards['monthAmount']) . ' requested'],
            ['Avg. Approval Time', $cards['avgDays'] !== null ? $cards['avgDays'] . ' d' : '—', 'fa-stopwatch', 'text-green-600', null, $period['from'] ? 'Approved documents of ' . $period['label'] : 'Approved in the last 90 days'],
        ] as [$label, $value, $icon, $tone, $href, $sub])
            @if($href)<a href="{{ $href }}" class="block bg-white rounded-xl p-5 shadow-sm hover:shadow-md transition-all">@else<div class="bg-white rounded-xl p-5 shadow-sm">@endif
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ $label }}</p>
                    <i class="fas {{ $icon }} text-sm {{ $tone }} opacity-60"></i>
                </div>
                <p class="text-2xl font-bold mt-1 {{ $tone }}">{{ $value }}</p>
                <p class="text-[11px] text-gray-400 mt-0.5">{{ $sub }}</p>
            @if($href)</a>@else</div>@endif
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        {{-- Trend --}}
        <div class="lg:col-span-2 bg-white rounded-xl p-6 shadow-sm">
            <h3 class="text-sm font-bold text-gray-800">{{ $trend->count() }}-Month Trend</h3>
            <p class="text-[11px] text-gray-400 mb-4">Requested amount (IDR) and number of documents per month, by request date.</p>

            <div class="space-y-2.5">
                @foreach($trend as $row)
                <div class="flex items-center gap-3 text-xs">
                    <span class="w-16 shrink-0 text-gray-500">{{ $row['label'] }}</span>
                    <div class="flex-1 h-5 bg-gray-100 rounded">
                        <div class="h-5 rounded primary-gradient" style="width: {{ $row['amount'] > 0 ? max(2, round($row['amount'] / $maxBar * 100)) : 0 }}%"></div>
                    </div>
                    <span class="w-32 shrink-0 text-right font-semibold text-gray-800">{{ $rupiah($row['amount']) }}</span>
                    <span class="w-14 shrink-0 text-right text-gray-400">{{ $row['count'] }} doc</span>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Status mix --}}
        <div class="bg-white rounded-xl p-6 shadow-sm">
            <h3 class="text-sm font-bold text-gray-800">Status Mix</h3>
            <p class="text-[11px] text-gray-400 mb-4">Documents of {{ $period['label'] }}.</p>

            <div class="flex h-3 rounded-full overflow-hidden bg-gray-100 mb-4">
                @foreach($statusRows as $name => $n)
                    <span class="{{ $statusTone[$name] }}" style="width: {{ $n / $statusTotal * 100 }}%" title="{{ $name }}: {{ $n }}"></span>
                @endforeach
            </div>

            <ul class="space-y-2 text-sm">
                @foreach($statusRows as $name => $n)
                <li class="flex items-center justify-between">
                    <span class="inline-flex items-center gap-2 text-gray-700"><span class="w-2.5 h-2.5 rounded-full {{ $statusTone[$name] }}"></span>{{ $name }}</span>
                    <span class="font-semibold text-gray-900">{{ $n }}</span>
                </li>
                @endforeach
            </ul>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        {{-- Waiting for you --}}
        <div class="bg-white rounded-xl p-6 shadow-sm">
            <h3 class="text-sm font-bold text-gray-800 mb-3">Waiting for You</h3>
            @forelse($waiting as $doc)
                <a href="{{ route('general.reimbursement.show', $doc) }}" class="flex items-center justify-between gap-3 py-2 border-b border-gray-100 last:border-0 hover:bg-gray-50 -mx-2 px-2 rounded">
                    <span class="min-w-0">
                        <span class="block font-mono text-xs text-red-800 font-semibold">{{ $doc->request_no }}</span>
                        <span class="block text-xs text-gray-500 truncate">{{ $doc->employee?->fullNameLabel() }} · {{ $doc->title }}</span>
                    </span>
                    <span class="text-xs font-semibold text-gray-800 whitespace-nowrap">{{ $doc->totalLabel() }}</span>
                </a>
            @empty
                <p class="text-sm text-gray-400 py-6 text-center">Nothing is waiting for you.</p>
            @endforelse
        </div>

        {{-- Needs attention --}}
        <div class="bg-white rounded-xl p-6 shadow-sm">
            <h3 class="text-sm font-bold text-gray-800 mb-3">Needs Attention</h3>
            @forelse($attention as $doc)
                <a href="{{ route('general.reimbursement.show', $doc) }}" class="block py-2 border-b border-gray-100 last:border-0 hover:bg-gray-50 -mx-2 px-2 rounded">
                    <span class="flex items-center justify-between gap-3">
                        <span class="font-mono text-xs text-red-800 font-semibold">{{ $doc->request_no }}</span>
                        <span class="text-xs text-gray-500 truncate">{{ $doc->employee?->fullNameLabel() }}</span>
                    </span>
                    <span class="flex flex-wrap gap-1 mt-1">
                        @foreach($doc->attentionFlags() as $flag)
                            <span class="px-1.5 py-0.5 bg-amber-100 text-amber-800 text-[10px] font-semibold rounded">{{ ReimbursementRequest::FLAG_LABELS[$flag] ?? $flag }}</span>
                        @endforeach
                    </span>
                </a>
            @empty
                <p class="text-sm text-gray-400 py-6 text-center">No open document is flagged.</p>
            @endforelse
        </div>

        {{-- Top requesters --}}
        <div class="bg-white rounded-xl p-6 shadow-sm">
            <h3 class="text-sm font-bold text-gray-800 mb-3">Top Requesters · {{ $monthLabel }}</h3>
            @forelse($topRequesters as $row)
                <div class="flex items-center justify-between gap-3 py-2 border-b border-gray-100 last:border-0">
                    <span class="min-w-0">
                        <span class="block text-sm text-gray-800 truncate">{{ $row->employee?->fullNameLabel() ?? '—' }}</span>
                        <span class="block text-[11px] text-gray-400">{{ $row->docs }} document(s)</span>
                    </span>
                    <span class="text-xs font-semibold text-gray-800 whitespace-nowrap">{{ $rupiah($row->amount) }}</span>
                </div>
            @empty
                <p class="text-sm text-gray-400 py-6 text-center">No request in {{ $period['label'] }}.</p>
            @endforelse
        </div>
    </div>
</div>

<script>
    // "Choose a month" / "Annual" reveal their value box; every other period applies at once.
    function rbPeriodChange() {
        const form = document.getElementById('rbPeriodForm');
        const key = document.getElementById('rbPeriod').value;
        document.getElementById('rbMonthBox').classList.toggle('hidden', key !== 'month');
        document.getElementById('rbYearBox').classList.toggle('hidden', key !== 'year');
        form.requestSubmit();
    }
</script>
@endsection

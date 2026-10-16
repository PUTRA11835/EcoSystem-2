@extends('dashboard')

@section('title', 'Cash Advance Management — Dashboard')
@section('page-title', 'Cash Advance Management')
@section('page-subtitle', 'Where cash advances (CA) and their settlement reports (CAR) stand, and what needs you')

@section('content')
@php
    $amounts = app(\App\Services\CashAdvance\CashAdvanceAmountService::class);
    $maxBar = max(1, (float) $trend->max('amount'));
    $name = fn ($employee) => $employee?->basicData?->full_name ?: ($employee?->eci ?? '—');
@endphp

@include('hr-general.cash-advance.components.hub-tabs')

<div class="space-y-5">

    {{-- Documents and money. Refund (money coming IN) and claim (money going OUT) are kept apart on purpose:
         adding them gives one number that means nothing to anybody. --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach([
            ['Waiting for You', $cards['mine'], 'fa-hourglass-half', 'text-red-800', $cards['mineCa'] . ' CA · ' . $cards['mineCar'] . ' CAR', null],
            ['CA in progress', $cards['caOpen'], 'fa-clock', 'text-amber-600', $cards['caApproved'] . ' approved · ' . $cards['caRejected'] . ' rejected', $canCa ? route('general.cash-advance.index', ['status' => 'open']) : null],
            ['Outstanding', $amounts->format($cards['outAmount'], false), 'fa-sack-dollar', 'text-gray-900', $cards['outCount'] . ' approved, not yet reported', $canCa ? route('general.cash-advance.index', ['status' => 'outstanding']) : null],
            ['CAR in progress', $cards['carOpen'], 'fa-file-signature', 'text-blue-700', $cards['carApproved'] . ' approved', $canCar ? route('general.cash-advance-report.index', ['status' => 'open']) : null],
        ] as [$label, $value, $icon, $tone, $sub, $href])
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

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="bg-white rounded-xl p-5 shadow-sm flex items-center gap-4">
            <span class="w-11 h-11 rounded-xl bg-green-50 text-green-700 flex items-center justify-center"><i class="fas fa-arrow-down-to-line"></i></span>
            <div>
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">To collect (refund)</p>
                <p class="text-xl font-bold text-gray-900">{{ $amounts->format($cards['refund'], false) }}</p>
                <p class="text-[11px] text-gray-400">employees return this after an approved CAR</p>
            </div>
        </div>
        <div class="bg-white rounded-xl p-5 shadow-sm flex items-center gap-4">
            <span class="w-11 h-11 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center"><i class="fas fa-arrow-up-from-line"></i></span>
            <div>
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">To pay (claim)</p>
                <p class="text-xl font-bold text-gray-900">{{ $amounts->format($cards['claim'], false) }}</p>
                <p class="text-[11px] text-gray-400">the company reimburses this after an approved CAR</p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        {{-- Trend --}}
        <div class="lg:col-span-2 bg-white rounded-xl p-6 shadow-sm">
            <h3 class="text-sm font-bold text-gray-800">Last 6 Months</h3>
            <p class="text-[11px] text-gray-400 mb-4">Cash advances requested (amount and number of documents), by request date.</p>
            <div class="space-y-2.5">
                @foreach($trend as $row)
                    <div class="flex items-center gap-3 text-xs">
                        <span class="w-16 shrink-0 text-gray-500">{{ $row['label'] }}</span>
                        <div class="flex-1 h-5 bg-gray-100 rounded">
                            <div class="h-5 rounded primary-gradient" style="width: {{ $row['amount'] > 0 ? max(2, round($row['amount'] / $maxBar * 100)) : 0 }}%"></div>
                        </div>
                        <span class="w-32 shrink-0 text-right font-semibold text-gray-800">{{ $amounts->format($row['amount'], false) }}</span>
                        <span class="w-14 shrink-0 text-right text-gray-400">{{ $row['count'] }} doc</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Oldest outstanding --}}
        <div class="bg-white rounded-xl p-6 shadow-sm">
            <h3 class="text-sm font-bold text-gray-800 mb-1">Oldest outstanding</h3>
            <p class="text-[11px] text-gray-400 mb-3">Approved, still waiting for a report.</p>
            @forelse($oldest as $ca)
                @php $days = (int) $ca->request_date->startOfDay()->diffInDays(now()->startOfDay()); @endphp
                <a href="{{ $canCa ? route('general.cash-advance.show', $ca) : '#' }}"
                    class="flex items-center justify-between gap-3 py-2 border-b border-gray-100 last:border-0 hover:bg-gray-50 -mx-2 px-2 rounded">
                    <span class="min-w-0">
                        <span class="block font-mono text-xs text-red-800 font-semibold">{{ $ca->request_no }}</span>
                        <span class="block text-xs text-gray-500 truncate">{{ $name($ca->employee) }}</span>
                    </span>
                    <span class="text-right whitespace-nowrap">
                        <span class="block text-xs font-semibold text-gray-800">{{ $amounts->format((float) $ca->amount, false) }}</span>
                        <span class="block text-[11px] {{ $days > 30 ? 'text-red-600' : 'text-gray-400' }}">{{ $days }} d</span>
                    </span>
                </a>
            @empty
                <p class="text-sm text-gray-400 py-6 text-center">Nothing is outstanding.</p>
            @endforelse
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <div class="bg-white rounded-xl p-6 shadow-sm">
            <h3 class="text-sm font-bold text-gray-800 mb-3">CA waiting for you</h3>
            @forelse($waitingCa as $ca)
                <a href="{{ route('general.cash-advance.show', $ca) }}" class="flex items-center justify-between gap-3 py-2 border-b border-gray-100 last:border-0 hover:bg-gray-50 -mx-2 px-2 rounded">
                    <span class="min-w-0">
                        <span class="block font-mono text-xs text-red-800 font-semibold">{{ $ca->request_no }}</span>
                        <span class="block text-xs text-gray-500 truncate">{{ $name($ca->employee) }} · {{ $ca->description }}</span>
                    </span>
                    <span class="text-xs font-semibold text-gray-800 whitespace-nowrap">{{ $amounts->format((float) $ca->amount, false) }}</span>
                </a>
            @empty
                <p class="text-sm text-gray-400 py-6 text-center">No cash advance is waiting for you.</p>
            @endforelse
        </div>

        <div class="bg-white rounded-xl p-6 shadow-sm">
            <h3 class="text-sm font-bold text-gray-800 mb-3">CAR waiting for you</h3>
            @forelse($waitingCar as $car)
                <a href="{{ route('general.cash-advance-report.show', $car) }}" class="flex items-center justify-between gap-3 py-2 border-b border-gray-100 last:border-0 hover:bg-gray-50 -mx-2 px-2 rounded">
                    <span class="min-w-0">
                        <span class="block font-mono text-xs text-red-800 font-semibold">{{ $car->report_no }}</span>
                        <span class="block text-xs text-gray-500 truncate">{{ $name($car->employee) }} · {{ $car->cashAdvance?->request_no }}</span>
                    </span>
                    <span class="text-xs font-semibold text-gray-800 whitespace-nowrap">{{ $amounts->format((float) $car->reported_amount, false) }}</span>
                </a>
            @empty
                <p class="text-sm text-gray-400 py-6 text-center">No report is waiting for you.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection

@extends('dashboard')
@section('title', 'Letter Templates')
@section('page-title', 'Letter Templates')
@section('page-subtitle', 'Letters in and out, the letters employees asked for, and what is waiting on HR.')

@php
    $tones = [
        'blue'  => 'bg-blue-50 text-blue-600',
        'green' => 'bg-green-50 text-green-600',
        'amber' => 'bg-amber-50 text-amber-600',
        'red'   => 'bg-red-50 text-red-600',
        'gray'  => 'bg-gray-100 text-gray-500',
    ];
    $canRequests = $can('general.letters.requests');
    $canRegister = $can('general.letters.register');
    $maxType = max(1, $byType->max('total'));
@endphp

@section('content')
<div class="w-full space-y-6 px-1 lg:px-2">
    @include('hr-general.letters.components.tabs')

    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
        @foreach($kpis as $kpi)
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 flex items-center gap-3">
                <span class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0 {{ $tones[$kpi['tone']] }}">
                    <i class="fas fa-{{ $kpi['icon'] }}"></i>
                </span>
                <div class="min-w-0">
                    <p class="text-2xl font-bold text-gray-800 leading-none">{{ $kpi['value'] }}</p>
                    <p class="text-[11px] text-gray-500 mt-1">{{ $kpi['label'] }}</p>
                </div>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-gray-800">Requests needing attention</h3>
                    <p class="text-[10px] text-gray-400 mt-0.5">The oldest requests still waiting on HR.</p>
                </div>
                @if($canRequests)
                    <a href="{{ route('general.letters.requests.index') }}" class="text-xs font-semibold" style="color: var(--primary-color);">Open Requests <i class="fas fa-arrow-right text-[10px]"></i></a>
                @endif
            </div>
            <table class="w-full text-xs text-left">
                <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider text-[10px] font-bold border-b border-gray-200">
                    <tr><th class="px-4 py-2.5">Employee</th><th class="px-4 py-2.5">Letter</th><th class="px-4 py-2.5">Needed by</th><th class="px-4 py-2.5">Status</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700">
                    @forelse($oldestPending as $letterRequest)
                        <tr>
                            <td class="px-4 py-2.5 font-semibold text-gray-800">{{ $letterRequest->employeeName() }}</td>
                            <td class="px-4 py-2.5">{{ $letterRequest->typeLabel() }}<span class="block text-[10px] text-gray-400">{{ $letterRequest->purpose }}</span></td>
                            <td class="px-4 py-2.5 whitespace-nowrap {{ $letterRequest->needed_by?->isPast() ? 'text-red-600 font-semibold' : '' }}">{{ $letterRequest->needed_by?->format('d M Y') ?? '-' }}</td>
                            <td class="px-4 py-2.5">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ \App\Models\Letters\LetterRequest::STATUS_BADGES[$letterRequest->status] }}">
                                    {{ \App\Models\Letters\LetterRequest::STATUSES[$letterRequest->status] }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400">Nothing waiting — every request was handled.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
            <div class="px-5 py-3.5 border-b border-gray-100">
                <h3 class="text-sm font-bold text-gray-800">Letters generated in {{ $year }}</h3>
                <p class="text-[10px] text-gray-400 mt-0.5">Per letter type, voided letters not counted.</p>
            </div>
            <div class="px-5 py-4 space-y-3">
                @foreach($byType as $type)
                    <div>
                        <div class="flex justify-between text-xs mb-1">
                            <span class="text-gray-700">{{ $type['label'] }}</span>
                            <span class="font-semibold text-gray-800">{{ $type['total'] }}</span>
                        </div>
                        <div class="h-1.5 rounded-full bg-gray-100 overflow-hidden">
                            <div class="h-full rounded-full primary-gradient" style="width: {{ round($type['total'] / $maxType * 100) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h3 class="text-sm font-bold text-gray-800">Recent letters</h3>
                <p class="text-[10px] text-gray-400 mt-0.5">The last ten letters in and out.</p>
            </div>
            @if($canRegister)
                <a href="{{ route('general.letters.register.index') }}" class="text-xs font-semibold" style="color: var(--primary-color);">Open the register <i class="fas fa-arrow-right text-[10px]"></i></a>
            @endif
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left">
                <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider text-[10px] font-bold border-b border-gray-200">
                    <tr><th class="px-4 py-2.5">Number</th><th class="px-4 py-2.5">Date</th><th class="px-4 py-2.5">To / From</th><th class="px-4 py-2.5">Subject</th><th class="px-4 py-2.5">Status</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700">
                    @forelse($recent as $letter)
                        @php $status = $letter->status(); @endphp
                        <tr>
                            <td class="px-4 py-2.5 whitespace-nowrap">
                                <span class="px-1.5 py-0.5 rounded text-[9px] font-bold {{ $letter->isOutgoing() ? 'bg-blue-50 text-blue-700' : 'bg-green-50 text-green-700' }}">{{ $letter->isOutgoing() ? 'OUT' : 'IN' }}</span>
                                <span class="font-semibold text-gray-800 ml-1">{{ $letter->isOutgoing() ? $letter->letter_number : $letter->agenda_number }}</span>
                            </td>
                            <td class="px-4 py-2.5 whitespace-nowrap">{{ $letter->letter_date->format('d M Y') }}</td>
                            <td class="px-4 py-2.5">{{ $letter->counterparty ?: '-' }}</td>
                            <td class="px-4 py-2.5">{{ $letter->subject }}</td>
                            <td class="px-4 py-2.5"><span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ \App\Models\Letters\Letter::STATUS_BADGES[$status] }}">{{ \App\Models\Letters\Letter::STATUSES[$status] }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">No letters yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

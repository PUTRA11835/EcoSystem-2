@extends('dashboard')
@section('title', 'Legal')
@section('page-title', 'Legal')
@section('page-subtitle', 'Contracts, signing and document identity at a glance')

@section('content')
@php
    $tones = ['green' => 'text-green-600', 'amber' => 'text-amber-600', 'red' => 'text-red-600', 'gray' => 'text-gray-900'];
    $navItems = [];
    foreach ($cards as $key => $card) {
        $navItems[] = ['id' => 'card-' . $key, 'label' => $card['title'], 'icon' => 'fa-' . $card['icon']];
    }
@endphp

<div class="w-full px-1 lg:px-2">
    @include('legal.components.tabs')

    @if($cards)
        <div class="lg:grid lg:grid-cols-[14.5rem_minmax(0,1fr)] lg:gap-6 items-start space-y-4 lg:space-y-0">
            @include('partials.page-nav', ['navLabel' => 'Dashboard', 'navItems' => $navItems])

            <div class="space-y-6 min-w-0">
                @foreach($cards as $key => $card)
                    <div id="card-{{ $key }}" class="pst-section bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                        <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between gap-3">
                            <h3 class="text-sm font-bold text-gray-800 flex items-center gap-2">
                                <i class="fas fa-{{ $card['icon'] }} text-gray-400"></i> {{ $card['title'] }}
                            </h3>
                            <a href="{{ $card['link'] }}" class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-50">
                                Open <i class="fas fa-arrow-right ml-1 text-[10px]"></i>
                            </a>
                        </div>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 p-5">
                            @foreach($card['stats'] as $stat)
                                <div>
                                    <p class="text-xs text-gray-500">{{ $stat[0] }}</p>
                                    <p class="text-3xl font-bold mt-1 {{ $tones[$stat[2]] ?? 'text-gray-900' }}">
                                        @if(!empty($stat[3]))<a href="{{ $stat[3] }}" class="hover:underline">{{ number_format($stat[1]) }}</a>@else{{ number_format($stat[1]) }}@endif
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-10 text-center">
            <p class="text-sm font-semibold text-gray-700">Nothing to summarise yet</p>
            <p class="text-xs text-gray-500 mt-1">The Legal dashboard shows a card for each Legal sub menu your position can open. Ask an administrator for access to Contract, External Contract or Document Identity.</p>
        </div>
    @endif
</div>
@endsection

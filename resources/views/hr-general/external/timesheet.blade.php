@extends('dashboard')
@section('title', 'Timesheet & Payment')
@section('page-title', 'Timesheet & Payment')
@section('page-subtitle', 'Hours and mandays of external consultants, and what they are paid for them.')

@section('content')
<div class="w-full space-y-6 px-1 lg:px-2">
    @include('partials.accent-remap')

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-6 py-14 text-center">
        <span class="w-14 h-14 rounded-2xl tone-primary inline-flex items-center justify-center text-xl mb-4"><i class="fas fa-hourglass-half"></i></span>
        <h2 class="text-lg font-bold text-gray-800">Coming soon</h2>
        <p class="text-sm text-gray-500 mt-2 max-w-xl mx-auto">
            Timesheet &amp; Payment for external consultants is being prepared. Until then, consultants and their contracts are managed from the other External menus.
        </p>
        <div class="mt-6 flex flex-wrap justify-center gap-2">
            @if($canDo('general.external.consultants'))<a href="{{ route('general.external.consultants.index') }}" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 text-xs font-semibold hover:bg-gray-50"><i class="fas fa-users mr-1"></i> Consultants</a>@endif
            @if($canDo('general.external.contracts'))<a href="{{ route('general.external.contracts.index') }}" class="px-4 py-2 rounded-lg primary-gradient text-white text-xs font-semibold hover:opacity-90"><i class="fas fa-file-contract mr-1"></i> External Contract</a>@endif
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        @foreach([
            ['clock', 'Timesheet', 'Consultants record the mandays or hours worked per project and period.'],
            ['circle-check', 'Approval', 'The project manager reviews and approves the period before it is billed.'],
            ['money-check-dollar', 'Payment', 'Approved mandays are priced with the contract rate and the payment term.'],
        ] as [$icon, $title, $text])
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 flex items-start gap-3">
                <span class="w-10 h-10 rounded-lg bg-gray-50 text-gray-400 flex items-center justify-center shrink-0"><i class="fas fa-{{ $icon }}"></i></span>
                <div>
                    <p class="text-sm font-bold text-gray-800">{{ $title }}</p>
                    <p class="text-[11px] text-gray-500 mt-1">{{ $text }}</p>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection

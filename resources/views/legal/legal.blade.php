@extends('dashboard')
@section('title', 'Legal')
@section('page-title', 'Legal')
@section('page-subtitle', 'Legal and compliance module')

@section('content')
<div class="bg-white rounded-xl p-6 shadow-sm">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6 pb-4 border-b-2 border-gray-100">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Legal</h2>
            <p class="text-sm text-gray-500 mt-0.5">Legal and compliance module</p>
        </div>
    </div>

    <div class="flex flex-col items-center justify-center py-20 text-center">
        <div class="w-20 h-20 bg-gray-100 rounded-2xl flex items-center justify-center mb-5">
            <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
            </svg>
        </div>
        <h3 class="text-lg font-bold text-gray-700 mb-2">Coming Soon</h3>
        <p class="text-sm text-gray-400 max-w-xs">The Legal module is currently under development. Please check back later.</p>
    </div>
</div>
@endsection

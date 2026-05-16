@extends('dashboard')
@section('title', 'Business')
@section('page-title', 'Business')
@section('page-subtitle', 'Business management module')

@section('content')
<div class="bg-white rounded-xl p-6 shadow-sm">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6 pb-4 border-b-2 border-gray-100">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Business</h2>
            <p class="text-sm text-gray-500 mt-0.5">Business management module</p>
        </div>
    </div>

    <div class="flex flex-col items-center justify-center py-20 text-center">
        <div class="w-20 h-20 bg-gray-100 rounded-2xl flex items-center justify-center mb-5">
            <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
        </div>
        <h3 class="text-lg font-bold text-gray-700 mb-2">Coming Soon</h3>
        <p class="text-sm text-gray-400 max-w-xs">The Business module is currently under development. Please check back later.</p>
    </div>
</div>
@endsection

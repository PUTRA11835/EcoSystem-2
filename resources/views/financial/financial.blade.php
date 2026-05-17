@extends('dashboard')
@section('title', 'Financial')
@section('page-title', 'Financial')
@section('page-subtitle', 'Financial management module')

@section('content')
<div class="bg-white rounded-xl p-6 shadow-sm">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6 pb-4 border-b-2 border-gray-100">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Financial</h2>
            <p class="text-sm text-gray-500 mt-0.5">Financial management module</p>
        </div>
    </div>

    <div class="flex flex-col items-center justify-center py-20 text-center">
        <div class="w-20 h-20 bg-gray-100 rounded-2xl flex items-center justify-center mb-5">
            <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 11h.01M12 11h.01M15 11h.01M4 19h16a2 2 0 002-2V7a2 2 0 00-2-2H4a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
        </div>
        <h3 class="text-lg font-bold text-gray-700 mb-2">Coming Soon</h3>
        <p class="text-sm text-gray-400 max-w-xs">The Financial module is currently under development. Please check back later.</p>
    </div>
</div>
@endsection

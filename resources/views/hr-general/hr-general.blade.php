@extends('dashboard')
@section('title', 'HR & General')
@section('page-title', 'HR & General')
@section('page-subtitle', 'Human resources and general administration module')

@section('content')
<div class="bg-white rounded-xl p-6 shadow-sm">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6 pb-4 border-b-2 border-gray-100">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">HR &amp; General</h2>
            <p class="text-sm text-gray-500 mt-0.5">Human resources and general administration module</p>
        </div>
    </div>

    <div class="flex flex-col items-center justify-center py-20 text-center">
        <div class="w-20 h-20 bg-gray-100 rounded-2xl flex items-center justify-center mb-5">
            <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
        </div>
        <h3 class="text-lg font-bold text-gray-700 mb-2">Coming Soon</h3>
        <p class="text-sm text-gray-400 max-w-xs">The HR &amp; General module is currently under development. Please check back later.</p>
    </div>
</div>
@endsection

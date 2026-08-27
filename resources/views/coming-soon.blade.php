@extends('dashboard')

@section('title', $feature . ' - Coming Soon')
@section('page-title', $feature)

@section('content')
<div class="max-w-4xl mx-auto space-y-6 py-6">
    <div class="bg-white rounded-2xl p-8 sm:p-12 shadow-sm border border-gray-100 text-center relative overflow-hidden">
        <!-- Subtle Decorative Background Shape -->
        <div class="absolute -top-16 -right-16 w-48 h-48 bg-red-50 rounded-full blur-2xl opacity-60"></div>
        <div class="absolute -bottom-16 -left-16 w-48 h-48 bg-amber-50 rounded-full blur-2xl opacity-60"></div>

        <div class="relative z-10 max-w-lg mx-auto space-y-5">
            <!-- Icon Container -->
            <div class="w-16 h-16 rounded-2xl primary-gradient text-white flex items-center justify-center mx-auto text-2xl shadow-lg shadow-red-800/20">
                <i class="fas fa-rocket"></i>
            </div>

            <!-- Title & Status -->
            <div>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 border border-amber-200 mb-3">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                    Coming Soon
                </span>
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-900">
                    {{ $feature }}
                </h2>
                <p class="text-sm text-gray-500 mt-2 leading-relaxed">
                    The <strong>{{ $feature }}</strong> module is currently under active development. This feature will be available in an upcoming EcoSystem-2 release.
                </p>
            </div>

            <!-- Feature Card Highlights -->
            <div class="p-4 rounded-xl bg-gray-50 border border-gray-100 text-left text-xs text-gray-600 space-y-2">
                <div class="flex items-center gap-2 text-gray-800 font-semibold">
                    <i class="fas fa-info-circle text-red-800"></i> Module Status & Overview
                </div>
                <p class="text-gray-500 leading-relaxed">
                    This module is enabled in your ESS Settings configuration. Once development concludes, full interactive workflows, data tracking, and reporting capabilities for {{ $feature }} will be activated automatically.
                </p>
            </div>

            <!-- Action Button -->
            <div class="pt-2">
                <a href="{{ route('dashboard') }}"
                    class="inline-flex items-center justify-center gap-2 px-6 py-2.5 primary-gradient text-white text-sm font-semibold rounded-xl shadow-md hover:opacity-95 transition-all">
                    <i class="fas fa-arrow-left"></i> Return to Dashboard
                </a>
            </div>
        </div>
    </div>
</div>
@endsection

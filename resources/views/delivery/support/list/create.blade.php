@extends('dashboard')
@section('title', 'Create Support')
@section('page-title', 'Create New Support')
@section('page-subtitle', 'Add a new support delivery item')

@push('styles')
<style>
    .primary-link { color: var(--primary-color); }
    .primary-link:hover { opacity: 0.75; }
    .primary-focus:focus { border-color: var(--primary-color) !important; box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.15) !important; outline: none !important; }
</style>
@endpush

@section('content')
@php
$clients   = ($clients ?? collect())->sortBy(fn($c) => strtolower($c->basicData->name_1 ?? $c->email ?? 'zzz'))->values();
$employees = ($employees ?? collect())->sortBy(fn($e) => strtolower($e->basicData->full_name ?? 'zzz'))->values();
@endphp
<div class="min-h-screen bg-gray-50">
    {{-- Flash Notifications --}}
    @if(session('success'))
        <script>document.addEventListener('DOMContentLoaded',()=>showNotification(@json(session('success')),'success'));</script>
    @endif
    @if(session('error'))
        <script>document.addEventListener('DOMContentLoaded',()=>showNotification(@json(session('error')),'error'));</script>
    @endif
    @if($errors->any())
        <script>document.addEventListener('DOMContentLoaded',()=>showNotification(@json($errors->first()),'error'));</script>
    @endif

    {{-- Breadcrumb --}}
    <div class="mb-6">
        <nav class="flex" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                <li class="inline-flex items-center">
                    <a href="{{ route('delivery.support.index') }}" class="text-gray-600 primary-link text-sm font-medium transition-colors">
                        <i class="fas fa-headset mr-2"></i>Support
                    </a>
                </li>
                <li>
                    <div class="flex items-center">
                        <svg class="w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="ml-1 text-sm font-medium text-gray-500">Create New</span>
                    </div>
                </li>
            </ol>
        </nav>
    </div>

    <form action="{{ route('delivery.support.store') }}" method="POST" id="supportForm">
        @csrf

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Main Form --}}
            <div class="lg:col-span-2 space-y-6">
                {{-- Basic Information --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Basic Information</h3>
                    </div>
                    <div class="p-6 space-y-4">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
                                Support Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="name" id="name" required
                                   class="block w-full border border-gray-300 rounded-lg shadow-sm primary-focus text-sm px-4 py-2.5"
                                   placeholder="Enter support name">
                            @error('name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="client_id" class="block text-sm font-medium text-gray-700 mb-1">
                                Client <span class="text-red-500">*</span>
                            </label>
                            {{-- custom-dd manual untuk konsistensi visual & animasi.
                                 Hidden input pakai name="client_id" supaya form submit
                                 tetap kirim value seperti <select> biasa. --}}
                            <div class="custom-dd relative" data-fixed="true">
                                <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm hover:border-gray-400 transition-all text-left">
                                    <span class="custom-dd-label text-gray-500">Select Client</span>
                                    <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <input type="hidden" name="client_id" id="client_id" value="" required>
                                <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:400px;">
                                    <div class="custom-dd-search-wrap sticky top-0 bg-white border-b border-gray-100 px-2 py-2" style="z-index:1">
                                        <input type="text" class="custom-dd-search w-full px-3 py-1.5 text-sm border border-gray-200 rounded-md focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400" placeholder="Search client…" autocomplete="off" spellcheck="false">
                                    </div>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">Select Client</button>
                                    @foreach($clients ?? [] as $client)
                                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="{{ $client->customer_id }}">{{ $client->basicData->name_1 ?? 'N/A' }}</button>
                                    @endforeach
                                    <div class="custom-dd-empty hidden px-4 py-3 text-sm text-gray-400 text-center">No results</div>
                                </div>
                            </div>
                            @error('client_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-xs text-gray-500">Tickets can be assigned to this delivery support from the ticket page</p>
                        </div>

                        <div>
                            <label for="type" class="block text-sm font-medium text-gray-700 mb-1">
                                Type <span class="text-red-500">*</span>
                            </label>
                            <div class="custom-dd relative" data-fixed="true">
                                <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm hover:border-gray-400 transition-all text-left">
                                    <span class="custom-dd-label text-gray-500">Select Type</span>
                                    <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <input type="hidden" name="type" id="type" value="" required>
                                <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:240px;">
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">Select Type</button>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="AMS">AMS</button>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="MO">MO</button>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="ATS">ATS</button>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Project">Project</button>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Internal">Internal</button>
                                </div>
                            </div>
                            @error('type')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-xs text-gray-500">The type determines the category of support delivery and associated tickets</p>
                        </div>

                        <div>
                            <label for="support_method" class="block text-sm font-medium text-gray-700 mb-1">
                                Support Method
                            </label>
                            <div class="custom-dd relative" data-fixed="true">
                                <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm hover:border-gray-400 transition-all text-left">
                                    <span class="custom-dd-label text-gray-500">Select method</span>
                                    <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <input type="hidden" name="support_method" id="support_method" value="">
                                <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:200px;">
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">Select method</button>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Remote">Remote</button>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="On-Site">On-Site</button>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Hybrid">Hybrid</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Timeline --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Timeline</h3>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">
                                    Start Date
                                </label>
                                <input type="date" name="start_date" id="start_date"
                                       class="block w-full border border-gray-300 rounded-lg shadow-sm primary-focus text-sm px-4 py-2.5">
                            </div>

                            <div>
                                <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">
                                    End Date
                                </label>
                                <input type="date" name="end_date" id="end_date"
                                       class="block w-full border border-gray-300 rounded-lg shadow-sm primary-focus text-sm px-4 py-2.5">
                            </div>

                            <div>
                                <label for="resolution_estimated" class="block text-sm font-medium text-gray-700 mb-1">
                                    Resolution Estimated
                                </label>
                                <input type="date" name="resolution_estimated" id="resolution_estimated"
                                       class="block w-full border border-gray-300 rounded-lg shadow-sm primary-focus text-sm px-4 py-2.5">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Team Assignment --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Team Assignment</h3>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="delivery_owner_id" class="block text-sm font-medium text-gray-700 mb-1">
                                    Delivery Owner
                                </label>
                                <div class="custom-dd relative" data-fixed="true">
                                    <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm hover:border-gray-400 transition-all text-left">
                                        <span class="custom-dd-label text-gray-500">Select delivery owner</span>
                                        <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </button>
                                    <input type="hidden" name="delivery_owner_id" id="delivery_owner_id" value="">
                                    <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:400px;">
                                        <div class="custom-dd-search-wrap sticky top-0 bg-white border-b border-gray-100 px-2 py-2" style="z-index:1">
                                            <input type="text" class="custom-dd-search w-full px-3 py-1.5 text-sm border border-gray-200 rounded-md focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400" placeholder="Search employee…" autocomplete="off" spellcheck="false">
                                        </div>
                                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">Select delivery owner</button>
                                        @foreach($employees ?? [] as $employee)
                                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="{{ $employee->employee_id }}">{{ $employee->basicData->full_name ?? 'N/A' }}</button>
                                        @endforeach
                                        <div class="custom-dd-empty hidden px-4 py-3 text-sm text-gray-400 text-center">No results</div>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label for="support_manager_id" class="block text-sm font-medium text-gray-700 mb-1">
                                    Support Manager
                                </label>
                                <div class="custom-dd relative" data-fixed="true">
                                    <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm hover:border-gray-400 transition-all text-left">
                                        <span class="custom-dd-label text-gray-500">Select support manager</span>
                                        <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </button>
                                    <input type="hidden" name="support_manager_id" id="support_manager_id" value="">
                                    <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:400px;">
                                        <div class="custom-dd-search-wrap sticky top-0 bg-white border-b border-gray-100 px-2 py-2" style="z-index:1">
                                            <input type="text" class="custom-dd-search w-full px-3 py-1.5 text-sm border border-gray-200 rounded-md focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400" placeholder="Search employee…" autocomplete="off" spellcheck="false">
                                        </div>
                                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">Select support manager</button>
                                        @foreach($employees ?? [] as $employee)
                                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="{{ $employee->employee_id }}">{{ $employee->basicData->full_name ?? 'N/A' }}</button>
                                        @endforeach
                                        <div class="custom-dd-empty hidden px-4 py-3 text-sm text-gray-400 text-center">No results</div>
                                    </div>
                                </div>
                            </div>

                            {{-- Co PM --}}
                            <div>
                                <label for="co_pm_id" class="block text-sm font-medium text-gray-700 mb-1">
                                    Co PM
                                </label>
                                <div class="custom-dd relative" data-fixed="true">
                                    <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm hover:border-gray-400 transition-all text-left">
                                        <span class="custom-dd-label text-gray-500">Select co pm</span>
                                        <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </button>
                                    <input type="hidden" name="co_pm_id" id="co_pm_id" value="">
                                    <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:400px;">
                                        <div class="custom-dd-search-wrap sticky top-0 bg-white border-b border-gray-100 px-2 py-2" style="z-index:1">
                                            <input type="text" class="custom-dd-search w-full px-3 py-1.5 text-sm border border-gray-200 rounded-md focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400" placeholder="Search employee…" autocomplete="off" spellcheck="false">
                                        </div>
                                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">Select co pm</button>
                                        @foreach($employees ?? [] as $employee)
                                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="{{ $employee->employee_id }}">{{ $employee->basicData->full_name ?? 'N/A' }}</button>
                                        @endforeach
                                        <div class="custom-dd-empty hidden px-4 py-3 text-sm text-gray-400 text-center">No results</div>
                                    </div>
                                </div>
                            </div>

                            {{-- Support Admin --}}
                            <div>
                                <label for="support_admin_id" class="block text-sm font-medium text-gray-700 mb-1">
                                    Support Admin
                                </label>
                                <div class="custom-dd relative" data-fixed="true">
                                    <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm hover:border-gray-400 transition-all text-left">
                                        <span class="custom-dd-label text-gray-500">Select support admin</span>
                                        <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </button>
                                    <input type="hidden" name="support_admin_id" id="support_admin_id" value="">
                                    <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:400px;">
                                        <div class="custom-dd-search-wrap sticky top-0 bg-white border-b border-gray-100 px-2 py-2" style="z-index:1">
                                            <input type="text" class="custom-dd-search w-full px-3 py-1.5 text-sm border border-gray-200 rounded-md focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400" placeholder="Search employee…" autocomplete="off" spellcheck="false">
                                        </div>
                                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">Select support admin</button>
                                        @foreach($employees ?? [] as $employee)
                                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="{{ $employee->employee_id }}">{{ $employee->basicData->full_name ?? 'N/A' }}</button>
                                        @endforeach
                                        <div class="custom-dd-empty hidden px-4 py-3 text-sm text-gray-400 text-center">No results</div>
                                    </div>
                                </div>
                            </div>

                            {{-- Sales --}}
                            <div>
                                <label for="sales_id" class="block text-sm font-medium text-gray-700 mb-1">
                                    Sales
                                </label>
                                <div class="custom-dd relative" data-fixed="true">
                                    <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm hover:border-gray-400 transition-all text-left">
                                        <span class="custom-dd-label text-gray-500">Select sales</span>
                                        <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </button>
                                    <input type="hidden" name="sales_id" id="sales_id" value="">
                                    <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:400px;">
                                        <div class="custom-dd-search-wrap sticky top-0 bg-white border-b border-gray-100 px-2 py-2" style="z-index:1">
                                            <input type="text" class="custom-dd-search w-full px-3 py-1.5 text-sm border border-gray-200 rounded-md focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400" placeholder="Search employee…" autocomplete="off" spellcheck="false">
                                        </div>
                                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">Select sales</button>
                                        @foreach($employees ?? [] as $employee)
                                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="{{ $employee->employee_id }}">{{ $employee->basicData->full_name ?? 'N/A' }}</button>
                                        @endforeach
                                        <div class="custom-dd-empty hidden px-4 py-3 text-sm text-gray-400 text-center">No results</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Sidebar --}}
            <div class="space-y-6">
                {{-- Mandays --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Effort Estimation</h3>
                    </div>
                    <div class="p-6">
                        <div>
                            <label for="total_mandays" class="block text-sm font-medium text-gray-700 mb-1">
                                Total Mandays
                            </label>
                            <input type="number" name="total_mandays" id="total_mandays" min="0"
                                   class="block w-full border border-gray-300 rounded-lg shadow-sm primary-focus text-sm px-4 py-2.5"
                                   placeholder="0">
                        </div>
                    </div>
                </div>

                {{-- Approval Info --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Approval</h3>
                    </div>
                    <div class="p-6 space-y-4">
                        <div>
                            <label for="approval_date" class="block text-sm font-medium text-gray-700 mb-1">
                                Approval Date
                            </label>
                            <input type="date" name="approval_date" id="approval_date"
                                   class="block w-full border border-gray-300 rounded-lg shadow-sm primary-focus text-sm px-4 py-2.5">
                        </div>
                        <div>
                            <label for="approval_name" class="block text-sm font-medium text-gray-700 mb-1">
                                Approved By
                            </label>
                            <input type="text" name="approval_name" id="approval_name"
                                   class="block w-full border border-gray-300 rounded-lg shadow-sm primary-focus text-sm px-4 py-2.5"
                                   placeholder="Approver name">
                        </div>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="p-6 space-y-3">
                        <button type="submit"
                                class="w-full inline-flex items-center justify-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                            Create Delivery Support
                        </button>
                        <a href="{{ route('delivery.support.index') }}"
                           class="w-full inline-flex items-center justify-center px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all duration-200">
                            Cancel
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Date validation
    const startDate = document.getElementById('start_date');
    const endDate = document.getElementById('end_date');
    const resolutionDate = document.getElementById('resolution_estimated');

    startDate.addEventListener('change', function() {
        endDate.min = this.value;
        if (endDate.value && endDate.value < this.value) {
            endDate.value = this.value;
        }
    });

    endDate.addEventListener('change', function() {
        if (this.value && startDate.value && this.value < startDate.value) {
            showNotification('End date cannot be before start date', 'warning');
            this.value = startDate.value;
        }
    });

    // Init custom-dd untuk semua dropdown form. Guard typeof biar halaman
    // tidak crash kalau custom-dropdown.js gagal di-load.
    if (typeof initCustomDropdowns === 'function') {
        initCustomDropdowns();
    }
});
</script>
{{-- Load custom-dd component (sama dengan halaman admin lain). filemtime
     cache buster supaya production auto-invalidate setiap deploy. --}}
@php
    $customDdPath = public_path('js/custom-dropdown.js');
    $customDdVer  = file_exists($customDdPath) ? filemtime($customDdPath) : time();
@endphp
<script src="/js/custom-dropdown.js?v={{ $customDdVer }}"></script>
@endsection

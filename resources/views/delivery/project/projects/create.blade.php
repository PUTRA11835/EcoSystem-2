@extends('dashboard')
@section('title', 'Add New Delivery Project')
@section('page-title', 'Add New Delivery Project')
@section('page-subtitle', 'Create a new delivery project')
@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
<style>
    .primary-focus:focus { border-color: var(--primary-color) !important; box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.15) !important; outline: none !important; }
    .flatpickr-day.fp-weekend:not(.flatpickr-disabled) { color: #ef4444; }
    .flatpickr-day.fp-holiday { color: #dc2626 !important; font-weight: 600; }
    .flatpickr-day.flatpickr-disabled.fp-holiday { color: #dc2626 !important; }
</style>
@endpush

@section('content')
@php
$clients   = ($clients ?? collect())->sortBy(fn($c) => strtolower($c->basicData->name_1 ?? $c->email ?? 'zzz'))->values();
$employees = ($employees ?? collect())->sortBy(fn($e) => strtolower($e->basicData->full_name ?? 'zzz'))->values();
@endphp
<form action="{{ route('projects.store') }}" method="POST" id="createProjectForm">
    @csrf
    {{-- Validation error toast + inline highlights --}}
    @if($errors->any())
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const msgs = @json($errors->all());
            if (typeof showNotification === 'function') {
                showNotification(msgs[0] + (msgs.length > 1 ? ' (+' + (msgs.length-1) + ' more)' : ''), 'error');
            }
        });
    </script>
    @endif
    
    {{-- Basic Project Information --}}
    <div class="bg-white overflow-hidden shadow-md sm:rounded-lg mb-6">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-xl font-semibold text-gray-700">New Delivery Project Form</h3>
            <p class="mt-1 text-sm text-gray-600">Fill in the delivery project details below.</p>
        </div>
        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- Semua dropdown dikonversi ke custom-dd manual untuk konsistensi
                 visual. Hidden input pakai name asli supaya form submit tetap
                 mengirim value seperti <select> biasa. --}}
            <div>
                <label class="block font-medium text-sm text-gray-700">Customer/Client <span class="text-red-500">*</span></label>
                <div class="custom-dd relative mt-1" data-fixed="true" data-onchange="refreshIoOptions">
                    @php $oldClient = old('client_id'); $oldClientLabel = ''; @endphp
                    @foreach($clients as $c)@if($oldClient == $c->customer_id)@php $oldClientLabel = $c->basicData->name_1 ?? $c->email ?? 'Unknown'; @endphp @endif @endforeach
                    <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-4 py-2.5 bg-white border {{ $errors->has('client_id') ? 'border-red-400' : 'border-gray-300' }} rounded-lg shadow-sm text-sm hover:border-gray-400 transition-all text-left">
                        <span class="custom-dd-label {{ $oldClientLabel ? 'text-gray-700' : 'text-gray-500' }}">{{ $oldClientLabel ?: '-- Select Client --' }}</span>
                        <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <input type="hidden" name="client_id" id="client_id" value="{{ $oldClient }}" required>
                    <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:400px;">
                        <div class="custom-dd-search-wrap sticky top-0 bg-white border-b border-gray-100 px-2 py-2" style="z-index:1">
                            <input type="text" class="custom-dd-search w-full px-3 py-1.5 text-sm border border-gray-200 rounded-md focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400" placeholder="Search client…" autocomplete="off" spellcheck="false">
                        </div>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">-- Select Client --</button>
                        @foreach($clients as $client)
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="{{ $client->customer_id }}">{{ $client->basicData->name_1 ?? $client->email ?? 'Unknown' }}</button>
                        @endforeach
                        <div class="custom-dd-empty hidden px-4 py-3 text-sm text-gray-400 text-center">No results</div>
                    </div>
                </div>
                @error('client_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block font-medium text-sm text-gray-700">Project Owner <span class="text-red-500">*</span></label>
                <div class="custom-dd relative mt-1" data-fixed="true">
                    @php $oldProjectOwner = old('project_owner'); @endphp
                    <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-4 py-2.5 bg-white border border-gray-300 rounded-lg shadow-sm text-sm hover:border-gray-400 transition-all text-left">
                        <span class="custom-dd-label {{ $oldProjectOwner ? 'text-gray-700' : 'text-gray-500' }}">{{ $oldProjectOwner ?: '-- Select Project Owner --' }}</span>
                        <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <input type="hidden" name="project_owner" id="project_owner" value="{{ $oldProjectOwner }}" required>
                    <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:400px;">
                        <div class="custom-dd-search-wrap sticky top-0 bg-white border-b border-gray-100 px-2 py-2" style="z-index:1">
                            <input type="text" class="custom-dd-search w-full px-3 py-1.5 text-sm border border-gray-200 rounded-md focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400" placeholder="Search employee…" autocomplete="off" spellcheck="false">
                        </div>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">-- Select Project Owner --</button>
                        @foreach($employees as $employee)
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="{{ $employee->basicData->full_name ?? '-' }}">{{ $employee->basicData->full_name ?? '-' }}</button>
                        @endforeach
                        <div class="custom-dd-empty hidden px-4 py-3 text-sm text-gray-400 text-center">No results</div>
                    </div>
                </div>
            </div>
            <div>
                <label class="block font-medium text-sm text-gray-700">Project Type <span class="text-red-500">*</span></label>
                <div class="custom-dd relative mt-1" data-fixed="true" data-onchange="refreshIoOptions">
                    @php $oldPt = old('project_type', 'Implementation'); @endphp
                    <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-4 py-2.5 bg-white border border-gray-300 rounded-lg shadow-sm text-sm hover:border-gray-400 transition-all text-left">
                        <span class="custom-dd-label text-gray-700">{{ $oldPt }}</span>
                        <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <input type="hidden" name="project_type" id="project_type" value="{{ $oldPt }}" required>
                    <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:240px;">
                        @foreach(['Implementation','Roll Out','Migration','Upgrade','WRICEF','Body Hire'] as $pt)
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="{{ $pt }}">{{ $pt }}</button>
                        @endforeach
                    </div>
                </div>
            </div>
            <!-- High Level Risk -->
            <div>
                <label class="block font-medium text-sm text-gray-700">High Level Risk</label>
                <div class="custom-dd relative mt-1" data-fixed="true">
                    @php $oldRisk = old('high_level_risk'); @endphp
                    <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-4 py-2.5 bg-white border border-gray-300 rounded-lg shadow-sm text-sm hover:border-gray-400 transition-all text-left">
                        <span class="custom-dd-label {{ $oldRisk ? 'text-gray-700' : 'text-gray-500' }}">{{ $oldRisk ?: '-- Select Risk Level --' }}</span>
                        <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <input type="hidden" name="high_level_risk" id="high_level_risk" value="{{ $oldRisk }}">
                    <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:200px;">
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">-- Select Risk Level --</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Low">Low</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Moderate">Moderate</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="High">High</button>
                    </div>
                </div>
            </div>
            {{-- Contract Start Date --}}
            <div>
                <label for="contract_start_date" class="block font-medium text-sm text-gray-700">
                    Contract Start Date <span class="text-red-500">*</span>
                </label>
                <input type="text"
                       name="contract_start_date"
                       id="contract_start_date"
                       autocomplete="off"
                       readonly
                       class="mt-1 block w-full border {{ $errors->has('contract_start_date') ? 'border-red-400 bg-red-50' : 'border-gray-300' }} rounded-lg shadow-sm primary-focus text-sm px-4 py-2.5 bg-white cursor-pointer"
                       value="{{ old('contract_start_date') }}"
                       placeholder="yyyy-mm-dd"
                       required>
                @error('contract_start_date')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            {{-- Contract End Date --}}
            <div>
                <label for="contract_end_date" class="block font-medium text-sm text-gray-700">
                    Contract End Date <span class="text-red-500">*</span>
                </label>
                <input type="text"
                       name="contract_end_date"
                       id="contract_end_date"
                       autocomplete="off"
                       readonly
                       class="mt-1 block w-full border {{ $errors->has('contract_end_date') ? 'border-red-400 bg-red-50' : 'border-gray-300' }} rounded-lg shadow-sm primary-focus text-sm px-4 py-2.5 bg-white cursor-pointer"
                       value="{{ old('contract_end_date') }}"
                       placeholder="yyyy-mm-dd"
                       required>
                @error('contract_end_date')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="md:col-span-2">
                <label for="io_number" class="block font-medium text-sm text-gray-700">
                    IO/Number Order <span class="text-red-500">*</span>
                </label>
                <input type="text"
                       name="io_number"
                       id="io_number"
                       list="io_number_options"
                       autocomplete="off"
                       class="mt-1 block w-full border {{ $errors->has('io_number') ? 'border-red-400 bg-red-50' : 'border-gray-300' }} rounded-lg shadow-sm primary-focus text-sm px-4 py-2.5"
                       value="{{ old('io_number') }}"
                       placeholder="e.g. IO-2026-001"
                       required>
                {{-- Opsi IO existing (khusus Body Hire) diisi oleh refreshIoOptions() sesuai company terpilih. --}}
                <datalist id="io_number_options"></datalist>
                <p id="io_number_hint" class="mt-1 text-xs text-blue-600 hidden">
                    <i class="fas fa-info-circle mr-1"></i>Body Hire: pilih IO number yang sudah ada milik company terpilih, atau ketik IO number baru.
                </p>
                @error('io_number')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="md:col-span-2">
                <label for="name" class="block font-medium text-sm text-gray-700">Project Name <span class="text-red-500">*</span></label>
                <input type="text"
                       name="name"
                       id="name"
                       class="mt-1 block w-full border {{ $errors->has('name') ? 'border-red-400 bg-red-50' : 'border-gray-300' }} rounded-lg shadow-sm primary-focus text-sm px-4 py-2.5"
                       value="{{ old('name') }}"
                       placeholder="Insert Project Name"
                       required>
                @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="md:col-span-2">
                <label for="description" class="block font-medium text-sm text-gray-700">Description <span class="text-red-500">*</span></label>
                <textarea name="description"
                          id="description"
                          rows="4"
                          class="mt-1 block w-full border {{ $errors->has('description') ? 'border-red-400 bg-red-50' : 'border-gray-300' }} rounded-lg shadow-sm primary-focus text-sm px-4 py-2.5"
                          required>{{ old('description') }}</textarea>
                @error('description')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>
    </div>

    {{-- Auto-filled Information Section --}}
    <div class="bg-blue-50 border border-blue-200 overflow-hidden shadow-sm sm:rounded-lg mb-6">
        <div class="px-6 py-4 border-b border-blue-200 flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-blue-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
            </svg>
            <div>
                <h3 class="text-sm font-semibold text-blue-800">Informasi Auto-filled</h3>
                <p class="text-xs text-blue-600">The following fields are auto-filled once the Delivery Project Planning is configured. No manual entry required.</p>
            </div>
        </div>
        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- Category --}}
            <div>
                <label for="category" class="block font-medium text-sm text-blue-700">Category</label>
                <input type="text"
                       name="category"
                       id="category"
                       class="mt-1 block w-full bg-blue-100/60 cursor-not-allowed border border-blue-200 rounded-lg shadow-sm text-sm px-4 py-2.5 text-gray-500"
                       value="{{ old('category') }}"
                       readonly
                       placeholder="Auto-filled…">
                <p class="mt-1 text-xs text-blue-500">Auto-filled dari Delivery Project Planning.</p>
            </div>
            {{-- Phase --}}
            <div>
                <label for="phase" class="block font-medium text-sm text-blue-700">Phase</label>
                <input type="text"
                       name="phase"
                       id="phase"
                       class="mt-1 block w-full bg-blue-100/60 cursor-not-allowed border border-blue-200 rounded-lg shadow-sm text-sm px-4 py-2.5 text-gray-500"
                       value="{{ old('phase') }}"
                       readonly
                       placeholder="Auto-filled…">
                <p class="mt-1 text-xs text-blue-500">Auto-filled dari Delivery Project Planning.</p>
            </div>
            {{-- Go Live Estimated --}}
            <div>
                <label for="go_live_estimated" class="block font-medium text-sm text-blue-700">Go Live Estimated</label>
                <input type="date"
                       name="go_live_estimated"
                       id="go_live_estimated"
                       class="mt-1 block w-full bg-blue-100/60 cursor-not-allowed border border-blue-200 rounded-lg shadow-sm text-sm px-4 py-2.5 text-gray-500"
                       value="{{ old('go_live_estimated') }}"
                       readonly>
                <p class="mt-1 text-xs text-blue-500">Auto-filled dari Delivery Project Planning (fase Go-Live).</p>
            </div>
        </div>
    </div>

    {{-- Delivery Information Section --}}
    <div class="bg-white overflow-hidden shadow-md sm:rounded-lg mb-6">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-xl font-semibold text-gray-700">Delivery Information</h3>
            <p class="mt-1 text-sm text-gray-600">Delivery and sales information</p>
        </div>
        <div class="p-6">
            {{-- Sales Data Section (including Financial Data) --}}
            <div class="mb-6">
                <h4 class="text-lg font-medium text-gray-900 mb-4">Sales Data</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label for="ae_type" class="block text-sm font-medium text-gray-700 mb-1">
                            Account Executive Type
                        </label>
                        @php $oldAeType = old('ae_type'); @endphp
                        <div class="custom-dd relative mt-1" data-onchange="toggleAEFields" data-fixed="true">
                            <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-4 py-2.5 bg-white border border-gray-300 rounded-lg shadow-sm text-sm hover:border-gray-400 transition-all text-left">
                                <span class="custom-dd-label {{ $oldAeType ? 'text-gray-700' : 'text-gray-500' }}">{{ $oldAeType ?: '-- Select Type --' }}</span>
                                <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <input type="hidden" name="ae_type" id="ae_type" value="{{ $oldAeType }}">
                            <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:200px;">
                                <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">-- Select Type --</button>
                                <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Internal">Internal</button>
                                <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="External">External</button>
                            </div>
                        </div>
                    </div>

                    <div id="ae_name_container">
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Account Executive Name
                        </label>
                        @php
                            $oldAeNameVal = old('ae_name');
                            $isInternal   = (old('ae_type') === 'Internal');
                            $isExternal   = (old('ae_type') === 'External');
                            $hasAeType    = ($isInternal || $isExternal);
                        @endphp
                        {{-- Placeholder ter-disable: tampil sampai Account Executive Type dipilih --}}
                        <input type="text" id="ae_name_placeholder" disabled
                               placeholder="-- Select type first --"
                               style="{{ $hasAeType ? 'display:none;' : '' }}"
                               class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm text-sm px-4 py-2.5 bg-gray-100 text-gray-400 cursor-not-allowed">
                        {{-- Custom-dd dengan search untuk AE Internal --}}
                        <div id="ae_employee_dd_wrapper" style="{{ $isInternal ? '' : 'display:none;' }}">
                            <div class="custom-dd relative mt-1" data-fixed="true" data-onchange="fillAEContactInfo">
                                <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-4 py-2.5 bg-white border border-gray-300 rounded-lg shadow-sm text-sm hover:border-gray-400 transition-all text-left">
                                    <span class="custom-dd-label {{ ($isInternal && $oldAeNameVal) ? 'text-gray-700' : 'text-gray-500' }}">{{ ($isInternal && $oldAeNameVal) ? $oldAeNameVal : '-- Select Employee --' }}</span>
                                    <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <input type="hidden" name="{{ $isInternal ? 'ae_name' : '' }}" id="ae_employee_hidden" value="{{ $isInternal ? $oldAeNameVal : '' }}">
                                <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:400px;">
                                    <div class="custom-dd-search-wrap sticky top-0 bg-white border-b border-gray-100 px-2 py-2" style="z-index:1">
                                        <input type="text" class="custom-dd-search w-full px-3 py-1.5 text-sm border border-gray-200 rounded-md focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400" placeholder="Search employee…" autocomplete="off" spellcheck="false">
                                    </div>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">-- Select Employee --</button>
                                    @foreach($aeEmployees as $employee)
                                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="{{ $employee->basicData->full_name ?? '-' }}">{{ $employee->basicData->full_name ?? '-' }}</button>
                                    @endforeach
                                    <div class="custom-dd-empty hidden px-4 py-3 text-sm text-gray-400 text-center">No results</div>
                                </div>
                            </div>
                        </div>
                        {{-- Text input untuk AE External --}}
                        <input type="text" name="{{ $isExternal ? 'ae_name' : '' }}" id="ae_name_input"
                               value="{{ $isExternal ? $oldAeNameVal : '' }}"
                               style="{{ $isExternal ? '' : 'display:none;' }}"
                               class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm primary-focus text-sm px-4 py-2.5">
                    </div>

                    <div>
                        <label for="ae_phone" class="block text-sm font-medium text-gray-700 mb-1">
                            Phone Number
                        </label>
                        <input type="text" name="ae_phone" id="ae_phone"
                               value="{{ old('ae_phone') }}"
                               class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm primary-focus text-sm px-4 py-2.5">
                    </div>

                    <div>
                        <label for="ae_email" class="block text-sm font-medium text-gray-700 mb-1">
                            Email
                        </label>
                        <input type="email" name="ae_email" id="ae_email"
                               value="{{ old('ae_email') }}"
                               class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm primary-focus text-sm px-4 py-2.5">
                    </div>

                    {{-- Revenue --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Revenue</label>
                        <div class="relative mt-1">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-gray-500 pointer-events-none">Rp.</span>
                            <input type="text" id="sfin_rev_disp" inputmode="numeric" autocomplete="off"
                                   class="block w-full pl-9 pr-3 py-2.5 border border-gray-300 rounded-lg shadow-sm primary-focus text-sm text-right"
                                   placeholder="0">
                            <input type="hidden" name="revenue" id="sfin_rev_val" value="{{ old('revenue', '') }}">
                        </div>
                    </div>
                    {{-- Plan Cost --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Plan Cost</label>
                        <div class="relative mt-1">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-gray-500 pointer-events-none">Rp.</span>
                            <input type="text" id="sfin_pc_disp" inputmode="numeric" autocomplete="off"
                                   class="block w-full pl-9 pr-3 py-2.5 border border-gray-300 rounded-lg shadow-sm primary-focus text-sm text-right"
                                   placeholder="0">
                            <input type="hidden" name="plan_cost" id="sfin_pc_val" value="{{ old('plan_cost', '') }}">
                        </div>
                    </div>
                    {{-- Gross Profit (auto-calc: Revenue - Plan Cost) --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Gross Profit
                        </label>
                        <div class="relative mt-1">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-gray-500 pointer-events-none">Rp.</span>
                            <input type="text" id="sfin_gp_disp" readonly tabindex="-1"
                                   class="block w-full pl-9 pr-3 py-2.5 border border-gray-200 rounded-lg bg-gray-50 cursor-not-allowed text-sm text-gray-500 text-right"
                                   placeholder="0">
                            <input type="hidden" name="gross_profit" id="sfin_gp_val" value="{{ old('gross_profit', '') }}">
                        </div>
                    </div>
                    {{-- % Gross Profit (auto-calc: GP / Revenue × 100) --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            % Gross Profit
                        </label>
                        <div class="relative mt-1">
                            <input type="text" id="sfin_pct_disp" readonly tabindex="-1"
                                   class="block w-full pr-9 pl-3 py-2.5 border border-gray-200 rounded-lg bg-gray-50 cursor-not-allowed text-sm text-gray-500 text-right"
                                   placeholder="0,00">
                            <span class="absolute inset-y-0 right-0 flex items-center pr-3 text-sm text-gray-500 pointer-events-none">%</span>
                            <input type="hidden" name="gross_profit_percentage" id="sfin_pct_val" value="{{ old('gross_profit_percentage', '') }}">
                        </div>
                    </div>
                </div>
            </div>

            {{-- Delivery Data Section --}}
            <div class="border-t border-gray-200 pt-6">
                <h4 class="text-lg font-medium text-gray-900 mb-4">Delivery Data</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <label for="delivery_method" class="block text-sm font-medium text-gray-700 mb-1">
                            Delivery Method
                        </label>
                        @php $oldDm = old('delivery_method'); @endphp
                        <div class="custom-dd relative mt-1" data-fixed="true">
                            <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-4 py-2.5 bg-white border border-gray-300 rounded-lg shadow-sm text-sm hover:border-gray-400 transition-all text-left">
                                <span class="custom-dd-label {{ $oldDm ? 'text-gray-700' : 'text-gray-500' }}">{{ $oldDm ?: '-- Select Method --' }}</span>
                                <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <input type="hidden" name="delivery_method" id="delivery_method" value="{{ $oldDm }}">
                            <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:200px;">
                                <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">-- Select Method --</button>
                                <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Onsite">Onsite</button>
                                <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Hybrid">Hybrid</button>
                                <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="WFH">WFH</button>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <label for="warranty_period" class="block text-sm font-medium text-gray-700 mb-1">
                            Warranty Period (Weeks)
                        </label>
                        <input type="number" name="warranty_period" id="warranty_period" 
                               value="{{ old('warranty_period') }}"
                               min="0"
                               class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm primary-focus text-sm px-4 py-2.5">
                    </div>
                    
                    <div>
                        <label for="total_mandays" class="block text-sm font-medium text-gray-700 mb-1">
                            Total Mandays
                        </label>
                        <input type="number" name="total_mandays" id="total_mandays" 
                               value="{{ old('total_mandays') }}"
                               min="0"
                               class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm primary-focus text-sm px-4 py-2.5">
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Team Members Section --}}
    <div class="bg-white overflow-hidden shadow-md sm:rounded-lg mb-6">
        <div class="p-6 border-b border-gray-200 flex justify-between items-center">
            <div>
                <h3 class="text-xl font-semibold text-gray-700">Team Members</h3>
                <p class="mt-1 text-sm text-gray-600">Project roles and team member assignments</p>
            </div>
            <button type="button" onclick="openAddTeamModal()"
                    class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                <svg class="-ml-1 mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Add Team Member
            </button>
        </div>
        <div class="p-6">
            {{-- Team Members Table --}}
            <div>
                <p class="text-sm text-gray-500 text-center py-8" id="teamEmptyMsg">No additional team members added yet.</p>
                <div class="overflow-x-auto hidden" id="teamTableWrap">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Module</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Period</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200" id="teamTableBody"></tbody>
                    </table>
                </div>
                {{-- Hidden inputs generated by JS, inside the form --}}
                <div id="teamHiddenInputs"></div>
            </div>
        </div>
    </div>

    {{-- Location Information Section --}}
    <div class="bg-white overflow-hidden shadow-md sm:rounded-lg mb-6">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-xl font-semibold text-gray-700">Location Information</h3>
            <p class="mt-1 text-sm text-gray-600">Project location information</p>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
                <div>
                    <label for="location_name" class="block text-sm font-medium text-gray-700 mb-1">
                        Location Name
                    </label>
                    <input type="text" name="location_name" id="location_name" 
                           value="{{ old('location_name') }}"
                           class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm primary-focus text-sm px-4 py-2.5">
                </div>
                
                <div>
                    <label for="location_type" class="block text-sm font-medium text-gray-700 mb-1">
                        Type of Address
                    </label>
                    @php $oldLt = old('location_type'); @endphp
                    <div class="custom-dd relative mt-1" data-fixed="true">
                        <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-4 py-2.5 bg-white border border-gray-300 rounded-lg shadow-sm text-sm hover:border-gray-400 transition-all text-left">
                            <span class="custom-dd-label {{ $oldLt ? 'text-gray-700' : 'text-gray-500' }}">{{ $oldLt ?: '-- Select Type --' }}</span>
                            <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <input type="hidden" name="location_type" id="location_type" value="{{ $oldLt }}">
                        <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:200px;">
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">-- Select Type --</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Head Office">Head Office</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Plant">Plant</button>
                        </div>
                    </div>
                </div>
                
                <div>
                    <label for="location_country" class="block text-sm font-medium text-gray-700 mb-1">
                        Country
                    </label>
                    <input type="text" name="location_country" id="location_country" 
                           value="Indonesia"
                           readonly
                           class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm bg-gray-50 text-sm px-4 py-2.5">
                </div>
                
                <div>
                    <label for="location_geographical" class="block text-sm font-medium text-gray-700 mb-1">
                        Geographical
                    </label>
                    @php $oldGeo = old('location_geographical'); @endphp
                    <div class="custom-dd relative mt-1" data-onchange="updateRegions" data-fixed="true">
                        <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-4 py-2.5 bg-white border border-gray-300 rounded-lg shadow-sm text-sm hover:border-gray-400 transition-all text-left">
                            <span class="custom-dd-label {{ $oldGeo ? 'text-gray-700' : 'text-gray-500' }}">{{ $oldGeo ?: '-- Select Geographical --' }}</span>
                            <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <input type="hidden" name="location_geographical" id="location_geographical" value="{{ $oldGeo }}">
                        <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:240px;">
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">-- Select Geographical --</button>
                            @foreach(['Jawa','Sumatera','Bali & N.Tenggara','Kalimantan','Sulawesi','Maluku','Papua'] as $g)
                                <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="{{ $g }}">{{ $g }}</button>
                            @endforeach
                        </div>
                    </div>
                </div>
                
                <div>
                    <label for="location_region" class="block text-sm font-medium text-gray-700 mb-1">
                        Region / Province
                    </label>
                    {{-- Region & City native: opsi dipopulasi JS lewat innerHTML.
                         data-no-enhance opt-out dari select-enhance.js. Styling
                         ringan via appearance-none + chevron background image
                         supaya visual mendekati custom-dd. --}}
                    <select name="location_region" id="location_region"
                            data-no-enhance
                            class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm text-sm px-4 py-2.5 pr-10 appearance-none bg-white hover:border-gray-400 transition-all"
                            style="background-image: url(&quot;data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%239ca3af' stroke-width='2'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E&quot;); background-repeat: no-repeat; background-position: right 0.625rem center; background-size: 1rem;"
                            onchange="updateCities()">
                        <option value="">-- Select Region --</option>
                    </select>
                </div>
                
                <div>
                    <label for="location_city" class="block text-sm font-medium text-gray-700 mb-1">
                        City
                    </label>
                    <select name="location_city" id="location_city"
                            data-no-enhance
                            class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm text-sm px-4 py-2.5 pr-10 appearance-none bg-white hover:border-gray-400 transition-all"
                            style="background-image: url(&quot;data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%239ca3af' stroke-width='2'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E&quot;); background-repeat: no-repeat; background-position: right 0.625rem center; background-size: 1rem;">
                        <option value="">-- Select City --</option>
                    </select>
                </div>
                
                <div class="md:col-span-2 lg:col-span-3">
                    <label for="location_street" class="block text-sm font-medium text-gray-700 mb-1">
                        Street Address
                    </label>
                    <textarea name="location_street" id="location_street" rows="3"
                              class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm primary-focus text-sm px-4 py-2.5">{{ old('location_street') }}</textarea>
                </div>
            </div>
        </div>
    </div>

    {{-- Submit Buttons --}}
    <div class="bg-white overflow-hidden shadow-md sm:rounded-lg">
        <div class="p-6 bg-gray-50 text-right">
            <a href="{{ route('projects.index') }}" 
               class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 mr-3">
                <svg class="-ml-1 mr-2 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
                Cancel
            </a>
            <button type="submit"
                    class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                Save Project
            </button>
        </div>
    </div>
</form>

{{-- Add / Edit Team Member Modal (outside <form> to avoid nesting issues) --}}
<div id="cTeamModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="closeCTeamModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4 pointer-events-none">
        <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] flex flex-col pointer-events-auto">
            <div class="p-6 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900" id="cTeamModalTitle">Add Team Member</h3>
                <button type="button" onclick="closeCTeamModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-6 overflow-y-auto flex-1">
                {{-- Pemilih sumber anggota: master Employee vs orang Vendor --}}
                <div class="mb-4">
                    <span class="block text-sm font-medium text-gray-900 mb-1.5">Member Source</span>
                    <div class="inline-flex rounded-lg border border-gray-300 p-0.5 bg-gray-50">
                        <button type="button" id="ctmSrcBtnEmployee" onclick="ctmSetSource('employee')"
                                class="px-4 py-1.5 text-sm font-semibold rounded-md transition-all duration-200">Employee</button>
                        <button type="button" id="ctmSrcBtnVendor" onclick="ctmSetSource('vendor')"
                                class="px-4 py-1.5 text-sm font-semibold rounded-md transition-all duration-200">Vendor</button>
                    </div>
                    <p id="ctmSrcHint" class="text-xs text-gray-400 mt-1.5"></p>
                </div>

                {{-- ── Pane EMPLOYEE ─────────────────────────────────────────── --}}
                <div id="ctmPaneEmployee" class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    {{-- Employee --}}
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-900 mb-1">
                            Consultant <span class="text-red-500">*</span>
                        </label>
                        <div class="custom-dd relative" data-fixed="true" id="ctm_emp_dd" data-onchange="ctmOnEmployeeChange">
                            <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-4 py-2.5 bg-white border border-gray-300 rounded-lg shadow-sm text-sm hover:border-gray-400 transition-all text-left">
                                <span class="custom-dd-label text-gray-500">-- Select Employee --</span>
                                <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <input type="hidden" id="ctm_employee_id">
                            <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-[60] py-1.5 overflow-y-auto" style="max-height:280px;">
                                <div class="custom-dd-search-wrap sticky top-0 bg-white border-b border-gray-100 px-2 py-2" style="z-index:1">
                                    <input type="text" class="custom-dd-search w-full px-3 py-1.5 text-sm border border-gray-200 rounded-md focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400" placeholder="Search employee…" autocomplete="off" spellcheck="false">
                                </div>
                                <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">-- Select Employee --</button>
                                @foreach($employees as $employee)
                                    <button type="button"
                                            class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors"
                                            data-value="{{ $employee->employee_id }}"
                                            data-position="{{ $employee->basicData->position ?? '' }}">
                                        {{ $employee->basicData->full_name ?? '-' }}
                                    </button>
                                @endforeach
                                <div class="custom-dd-empty hidden px-4 py-3 text-sm text-gray-400 text-center">No results</div>
                            </div>
                        </div>
                        <p id="ctm_emp_err" class="mt-1 text-xs text-red-500 hidden">Please select an employee.</p>
                    </div>
                    {{-- Employee Type (turunan data employee) --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-900 mb-1">
                            Employee Type <span class="text-xs text-gray-400 font-normal">— from employee data</span>
                        </label>
                        <input type="text" id="ctm_employee_type_display" readonly
                               placeholder="Select a consultant first"
                               class="block w-full py-2.5 px-3 border border-gray-200 rounded-md shadow-sm text-sm bg-gray-50 text-gray-500 cursor-not-allowed">
                    </div>
                    {{-- Module (dari kualifikasi employee) --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-900 mb-1">
                            Module <span class="text-xs text-gray-400 font-normal">— from consultant qualification</span>
                        </label>
                        <div id="ctm_module_picker"
                             class="block w-full min-h-[42px] max-h-28 overflow-y-auto py-2 px-3 border border-gray-300 rounded-md shadow-sm bg-white">
                            <p id="ctm_module_placeholder" class="text-sm text-gray-400">Select a consultant first.</p>
                            <div id="ctm_module_options" class="flex flex-wrap gap-x-4 gap-y-1"></div>
                        </div>
                    </div>
                </div>

                {{-- ── Pane VENDOR ───────────────────────────────────────────── --}}
                <div id="ctmPaneVendor" class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4" style="display:none;">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-900 mb-1">
                            Vendor <span class="text-red-500">*</span>
                            <span class="text-xs text-gray-400 font-normal">— from Business Partner (type Vendor)</span>
                        </label>
                        <select id="ctm_vendor_id"
                                class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus">
                            <option value="">-- Select Vendor --</option>
                            @foreach($vendors as $vendor)
                                <option value="{{ $vendor->customer_id }}">{{ $vendor->basicData->name_1 ?? $vendor->customer_code }}</option>
                            @endforeach
                        </select>
                        @if($vendors->isEmpty())
                            <p class="text-xs text-amber-600 mt-1">No Business Partner of type Vendor yet — add one in Master → Business Partner.</p>
                        @endif
                        <p id="ctm_vendor_err" class="mt-1 text-xs text-red-500 hidden">Please select a vendor.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-900 mb-1">Consultant Name <span class="text-red-500">*</span></label>
                        <input type="text" id="ctm_member_name" maxlength="255"
                               class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus"
                               placeholder="Vendor consultant name">
                        <p id="ctm_member_name_err" class="mt-1 text-xs text-red-500 hidden">Consultant name is required.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-900 mb-1">Position <span class="text-xs text-gray-400 font-normal">— optional</span></label>
                        <input type="text" id="ctm_member_position" maxlength="255"
                               class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus"
                               placeholder="e.g. SAP Consultant">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-900 mb-1">Module <span class="text-xs text-gray-400 font-normal">— optional, comma separated</span></label>
                        <input type="text" id="ctm_vendor_module" maxlength="255"
                               class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus"
                               placeholder="e.g. FI, CO">
                    </div>
                </div>

                {{-- ── Field bersama ─────────────────────────────────────────── --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- Role --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-900 mb-1">Role <span class="text-red-500">*</span></label>
                        <select id="ctm_role"
                                class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus">
                            <option value="">-- Select Role --</option>
                            <option value="Project Manager">Project Manager</option>
                            <option value="Co Project Manager">Co Project Manager</option>
                            <option value="Project Admin">Project Admin</option>
                            <option value="Lead">Lead</option>
                            <option value="Member">Member</option>
                        </select>
                        <p id="ctm_role_err" class="mt-1 text-xs text-red-500 hidden">Please select a role.</p>
                    </div>
                    {{-- Start Date --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-900 mb-1">
                            Start Date <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="ctm_start_date"
                               placeholder="Select Start Date"
                               class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus"
                               autocomplete="off" readonly>
                        <p id="ctm_start_err" class="mt-1 text-xs text-red-500 hidden">Start date is required.</p>
                    </div>
                    {{-- End Date --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-900 mb-1">
                            End Date <span class="text-xs text-gray-400 font-normal">— optional</span>
                        </label>
                        <input type="text" id="ctm_end_date"
                               placeholder="Select End Date"
                               class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus"
                               autocomplete="off" readonly>
                    </div>
                    {{-- Notes --}}
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-900 mb-1">
                            Notes <span class="text-xs text-gray-400 font-normal">— optional</span>
                        </label>
                        <textarea id="ctm_notes" rows="2"
                                  class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus"
                                  placeholder="Additional notes or remarks..."></textarea>
                    </div>
                </div>
            </div>
            <div class="p-6 border-t border-gray-200 flex justify-end space-x-3">
                <button type="button" onclick="closeCTeamModal()"
                        class="inline-flex items-center px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all duration-200">
                    Cancel
                </button>
                <button type="button" onclick="saveCTeamMember()"
                        class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                    Save Member
                </button>
            </div>
        </div>
    </div>
</div>

{{-- JavaScript for Dynamic Form --}}
<script>
const indonesiaRegions = {
    'Jawa': ['DKI Jakarta', 'Jawa Barat', 'Jawa Tengah', 'DI Yogyakarta', 'Jawa Timur', 'Banten'],
    'Sumatera': ['Aceh', 'Sumatera Utara', 'Sumatera Barat', 'Riau', 'Kepulauan Riau', 'Jambi', 'Sumatera Selatan', 'Bengkulu', 'Lampung', 'Kepulauan Bangka Belitung'],
    'Bali & N.Tenggara': ['Bali', 'Nusa Tenggara Barat', 'Nusa Tenggara Timur'],
    'Kalimantan': ['Kalimantan Barat', 'Kalimantan Tengah', 'Kalimantan Selatan', 'Kalimantan Timur', 'Kalimantan Utara'],
    'Sulawesi': ['Sulawesi Utara', 'Sulawesi Tengah', 'Sulawesi Selatan', 'Sulawesi Tenggara', 'Gorontalo', 'Sulawesi Barat'],
    'Maluku': ['Maluku', 'Maluku Utara'],
    'Papua': ['Papua', 'Papua Barat', 'Papua Selatan', 'Papua Tengah', 'Papua Pegunungan', 'Papua Barat Daya']
};

const indonesiaCities = {
    'DKI Jakarta': ['Jakarta Pusat', 'Jakarta Utara', 'Jakarta Barat', 'Jakarta Selatan', 'Jakarta Timur', 'Kepulauan Seribu'],

    'Banten' : [ 'Serang', 'Tangerang', 'Tangerang Selatan', 'Cilegon', 'Pandeglang', 'Lebak'],

    'Jawa Barat': ['Bandung', 'Bekasi', 'Bogor', 'Cirebon', 'Depok', 'Sukabumi', 'Tasikmalaya','Banjar', 'Cimahi', 'Garut', 'Indramayu', 'Karawang', 
                    'Kuningan', 'Majalengka', 'Purwakarta', 'Subang', 'Sumedang', 'Ciamis', 'Cianjur', 'Pangandaran'],

    'Jawa Tengah': ['Semarang', 'Solo', 'Magelang', 'Salatiga', 'Pekalongan', 'Tegal', 'Banyumas', 'Cilacap', 'Purbalingga', 'Banjarnegara', 'Kebumen', 
                    'Purworejo', 'Wonosobo', 'Klaten', 'Boyolali', 'Sukoharjo', 'Wonogiri', 'Karanganyar', 'Sragen', 'Grobogan', 'Blora', 'Rembang', 
                    'Pati', 'Kudus', 'Jepara', 'Demak', 'Kendal', 'Temanggung', 'Batang', 'Pemalang', 'Brebes'],

    'Jawa Timur': ['Surabaya', 'Malang', 'Sidoarjo', 'Gresik', 'Mojokerto', 'Kediri', 'Jember', 'Batu', 'Blitar', 'Madiun', 'Pasuruan', 'Probolinggo',
                    'Bangkalan', 'Banyuwangi', 'Bojonegoro', 'Bondowoso', 'Jombang', 'Lamongan', 'Lumajang', 'Magetan', 'Nganjuk', 'Ngawi', 'Pacitan',
                    'Pamekasan', 'Ponorogo', 'Sampang', 'Situbondo', 'Sumenep', 'Trenggalek', 'Tuban', 'Tulungagung'],

    'DI Yogyakarta' : ['Yogyakarta', 'Bantul', 'Sleman', 'Gunungkidul', 'Kulon Progo'],

    'Aceh' : [ 'Banda Aceh', 'Sabang', 'Langsa', 'Lhokseumawe', 'Subulussalam', 'Aceh Besar', 'Aceh Jaya', 'Aceh Selatan', 'Aceh Singkil', 'Aceh Tengah',
                'Aceh Tenggara', 'Aceh Timur', 'Aceh Utara', 'Bener Meriah', 'Bireuen', 'Gayo Lues', 'Nagan Raya', 'Pidie', 'Pidie Jaya', 'Simeulue'],
    
    'Sumatera Utara' : ['Medan', 'Binjai', 'Pematangsiantar', 'Tanjungbalai', 'Tebing Tinggi', 'Padang Sidempuan', 'Gunungsitoli', 'Sibolga',
                        'Asahan', 'Batubara', 'Dairi', 'Deli Serdang', 'Humbang Hasundutan', 'Karo', 'Labuhanbatu', 'Labuhanbatu Selatan', 'Labuhanbatu Utara',
                        'Langkat', 'Mandailing Natal', 'Nias', 'Nias Barat', 'Nias Selatan', 'Nias Utara', 'Padang Lawas', 'Padang Lawas Utara', 'Pakpak Bharat',
                        'Samosir', 'Serdang Bedagai', 'Simalungun', 'Tapanuli Selatan', 'Tapanuli Tengah', 'Tapanuli Utara', 'Toba Samosir'],
    
    'Sumatera Barat' : ['Padang', 'Bukittinggi', 'Padang Panjang', 'Pariaman', 'Payakumbuh', 'Sawahlunto', 'Solok', 'Agam', 'Dharmasraya', 'Kepulauan Mentawai', 'Lima Puluh Kota',
                        'Padang Pariaman', 'Pasaman', 'Pasaman Barat', 'Pesisir Selatan', 'Sijunjung', 'Solok Selatan', 'Tanah Datar'],
    
    'Riau' : ['Pekanbaru', 'Dumai', 'Bengkalis', 'Indragiri Hilir', 'Indragiri Hulu', 'Kampar', 'Kepulauan Meranti', 'Kuantan Singingi', 'Pelalawan', 'Rokan Hilir',
                'Rokan Hulu', 'Siak'],
    
    'Kepulauan Riau' : ['Batam', 'Tanjung Pinang', 'Bintan', 'Karimun', 'Kepulauan Anambas', 'Lingga', 'Natuna'],
    
    'Jambi': ['Jambi', 'Sungai Penuh', 'Batang Hari', 'Bungo', 'Kerinci', 'Merangin', 'Muaro Jambi', 'Sarolangun', 'Tanjung Jabung Barat', 'Tanjung Jabung Timur', 'Tebo'],
    
    'Sumatera Selatan' : ['Palembang', 'Lubuklinggau', 'Pagar Alam', 'Prabumulih', 'Banyuasin', 'Empat Lawang', 'Lahat', 'Muara Enim', 'Musi Banyuasin',
                            'Musi Rawas', 'Musi Rawas Utara', 'Ogan Ilir', 'Ogan Komering Ilir', 'Ogan Komering Ulu', 'Ogan Komering Ulu Selatan', 'Ogan Komering Ulu Timur',
                            'Penukal Abab Lematang Ilir'],
    
    'Bengkulu': ['Bengkulu', 'Bengkulu Selatan', 'Bengkulu Tengah', 'Bengkulu Utara', 'Kaur', 'Kepahiang', 'Lebong', 'Mukomuko', 'Rejang Lebong', 'Seluma'],
    
    'Lampung' :['Bandar Lampung', 'Metro', 'Lampung Barat', 'Lampung Selatan', 'Lampung Tengah', 'Lampung Timur', 'Lampung Utara', 'Mesuji', 'Pesawaran', 'Pesisir Barat', 'Pringsewu',
                'Tanggamus', 'Tulang Bawang', 'Tulang Bawang Barat', 'Way Kanan'],
    
    'Kepulauan Bangka Belitung': ['Pangkal Pinang', 'Bangka', 'Bangka Barat', 'Bangka Selatan', 'Bangka Tengah', 'Belitung', 'Belitung Timur'],
    
    'Bali' : ['Denpasar','Badung', 'Bangli', 'Buleleng', 'Gianyar', 'Jembrana', 'Karangasem', 'Klungkung', 'Tabanan'],
    
    'Nusa Tenggara Barat': ['Mataram', 'Bima', 'Dompu', 'Lombok Barat', 'Lombok Tengah', 'Lombok Timur', 'Lombok Utara', 'Sumbawa', 'Sumbawa Barat'],
    
    'Nusa Tenggara Timur' : ['Kupang', 'Alor', 'Belu', 'Ende', 'Flores Timur', 'Kupang', 'Lembata', 'Manggarai', 'Manggarai Barat', 'Manggarai Timur', 'Nagekeo', 'Ngada',
                                'Rote Ndao', 'Sabu Raijua', 'Sikka', 'Sumba Barat', 'Sumba Barat Daya', 'Sumba Tengah', 'Sumba Timur', 'Timor Tengah Selatan', 'Timor Tengah Utara'],
    
    'Kalimantan Barat': ['Pontianak', 'Singkawang', 'Bengkayang', 'Kapuas Hulu', 'Kayong Utara', 'Ketapang', 'Kubu Raya', 
                            'Landak', 'Melawi', 'Mempawah', 'Sambas', 'Sanggau', 'Sekadau', 'Sintang'],
    
    'Kalimantan Tengah' :['Palangka Raya', 'Barito Selatan', 'Barito Timur', 'Barito Utara', 'Gunung Mas', 'Kapuas', 'Katingan', 'Kotawaringin Barat', 'Kotawaringin Timur',
                            'Lamandau', 'Murung Raya', 'Pulang Pisau', 'Seruyan', 'Sukamara'],
    
    'Kalimantan Selatan': ['Banjarmasin', 'Banjarbaru', 'Balangan', 'Banjar', 'Barito Kuala', 'Hulu Sungai Selatan', 'Hulu Sungai Tengah', 'Hulu Sungai Utara', 'Kotabaru', 'Tabalong',
                            'Tanah Bumbu', 'Tanah Laut', 'Tapin'],
    
    'Kalimantan Timur' : ['Balikpapan', 'Bontang', 'Samarinda', 'Berau', 'Kutai Barat', 'Kutai Kartanegara', 'Kutai Timur', 'Mahakam Ulu', 'Paser', 'Penajam Paser Utara'],
    
    'Kalimantan Utara' :['Tarakan', 'Bulungan', 'Malinau', 'Nunukan', 'Tana Tidung'],
    
    'Sulawesi Utara' : ['Manado', 'Bitung', 'Kotamobagu', 'Tomohon', 'Bolaang Mongondow', 'Bolaang Mongondow Selatan', 'Bolaang Mongondow Timur', 'Bolaang Mongondow Utara', 
                        'Kepulauan Sangihe', 'Kepulauan Siau Tagulandang Biaro', 'Kepulauan Talaud', 'Minahasa', 'Minahasa Selatan', 'Minahasa Tenggara', 'Minahasa Utara'],
    
    'Sulawesi Tengah' : ['Palu', 'Banggai', 'Banggai Kepulauan', 'Banggai Laut', 'Buol', 'Donggala', 'Morowali', 'Morowali Utara', 'Parigi Moutong', 'Poso', 'Sigi',
                            'Tojo Una-Una', 'Toli-Toli'],
    
    'Sulawesi Selatan' : ['Makassar', 'Palopo', 'Parepare', 'Bantaeng', 'Barru', 'Bone', 'Bulukumba', 'Enrekang', 'Gowa', 'Jeneponto', 'Kepulauan Selayar', 'Luwu', 
                            'Luwu Timur', 'Luwu Utara', 'Maros', 'Pangkajene dan Kepulauan', 'Pinrang', 'Sidenreng Rappang', 'Sinjai', 'Soppeng', 'Takalar', 'Tana Toraja', 
                            'Toraja Utara', 'Wajo'],
    
    'Sulawesi Tenggara' : ['Kendari', 'Baubau', 'Bombana', 'Buton', 'Buton Selatan', 'Buton Tengah', 'Buton Utara', 'Kolaka', 'Kolaka Timur', 'Kolaka Utara', 'Konawe', 
                            'Konawe Kepulauan', 'Konawe Selatan', 'Konawe Utara', 'Muna', 'Muna Barat', 'Wakatobi'],
    
    'Gorontalo' : ['Gorontalo', 'Boalemo', 'Bone Bolango', 'Gorontalo', 'Gorontalo Utara', 'Pohuwato'],
    
    'Sulawesi Barat' : ['Mamuju', 'Majene', 'Mamasa', 'Mamuju', 'Mamuju Tengah', 'Mamuju Utara', 'Polewali Mandar'],
    
    'Maluku' : ['Ambon', 'Tual', 'Buru', 'Buru Selatan', 'Kepulauan Aru', 'Maluku Barat Daya', 'Maluku Tengah', 'Maluku Tenggara', 'Maluku Tenggara Barat', 
                'Seram Bagian Barat', 'Seram Bagian Timur'],
    
    'Maluku Utara' : ['Ternate', 'Tidore Kepulauan', 'Halmahera Barat', 'Halmahera Selatan', 'Halmahera Tengah', 'Halmahera Timur', 'Halmahera Utara', 'Kepulauan Sula', 
                        'Pulau Morotai', 'Pulau Taliabu'], 

    'Papua' : ['Jayapura', 'Biak Numfor', 'Jayapura', 'Keerom', 'Kepulauan Yapen', 'Mamberamo Raya', 'Sarmi', 'Supiori', 'Waropen'],

    'Papua Barat': ['Manokwari', 'Fakfak', 'Kaimana', 'Manokwari Selatan', 'Pegunungan Arfak', 'Teluk Bintuni', 'Teluk Wondama'],

    'Papua Selatan' : ['Merauke', 'Asmat', 'Boven Digoel', 'Mappi'],

    'Papua Tengah' : ['Nabire', 'Mimika', 'Paniai', 'Puncak Jaya', 'Puncak', 'Dogiyai', 'Intan Jaya', 'Deiyai'],

    'Papua Pegunungan' : ['Jayawijaya', 'Lanny Jaya', 'Tolikara', 'Mamberamo Tengah', 'Yalimo', 'Nduga', 'Pegunungan Bintang', 'Yahukimo'],

    'Papua Barat Daya' :['Sorong', 'Sorong Selatan', 'Raja Ampat', 'Maybrat', 'Tambrauw'],
};

// Contact map: full_name → { phone, email } — di-generate dari data employee
const aeEmployeeContacts = {
@foreach($employees as $employee)
@php
    $addr  = $employee->addresses->first();
    $phone = $addr?->cell_phone ?: ($addr?->telephone ?? '');
    $email = $addr?->email_work ?: ($addr?->email_personal ?? '');
    $name  = addslashes($employee->basicData->full_name ?? '-');
@endphp
    "{{ $name }}": { phone: "{{ $phone }}", email: "{{ $email }}" },
@endforeach
};

// Auto-fill phone & email saat employee AE Internal dipilih
function fillAEContactInfo() {
    const name    = document.getElementById('ae_employee_hidden').value;
    const contact = aeEmployeeContacts[name] || {};
    const phoneEl = document.getElementById('ae_phone');
    const emailEl = document.getElementById('ae_email');
    if (contact.phone) phoneEl.value = contact.phone;
    if (contact.email) emailEl.value = contact.email;
}

// Toggle AE fields based on type (empty / Internal / External)
function toggleAEFields() {
    const aeType      = document.getElementById('ae_type').value;
    const placeholder = document.getElementById('ae_name_placeholder');
    const ddWrapper   = document.getElementById('ae_employee_dd_wrapper');
    const aeHidden    = document.getElementById('ae_employee_hidden');
    const aeTextInput = document.getElementById('ae_name_input');

    if (aeType === 'Internal') {
        if (placeholder) placeholder.style.display = 'none';
        ddWrapper.style.display   = 'block';
        aeHidden.name             = 'ae_name';
        aeTextInput.style.display = 'none';
        aeTextInput.name          = '';
        // Re-init custom-dd in case it was hidden during init
        if (typeof initCustomDropdowns === 'function') {
            initCustomDropdowns(ddWrapper);
        }
    } else if (aeType === 'External') {
        if (placeholder) placeholder.style.display = 'none';
        ddWrapper.style.display   = 'none';
        aeHidden.name             = '';
        aeTextInput.style.display = 'block';
        aeTextInput.name          = 'ae_name';
    } else {
        // No type selected yet → AE Name disabled (placeholder only).
        if (placeholder) placeholder.style.display = 'block';
        ddWrapper.style.display   = 'none';
        aeHidden.name             = '';
        aeHidden.value            = '';
        aeTextInput.style.display = 'none';
        aeTextInput.name          = '';
        aeTextInput.value         = '';
    }
}

// Update regions based on geographical selection
function updateRegions() {
    const geoSelect = document.getElementById('location_geographical');
    const regionSelect = document.getElementById('location_region');
    const selectedGeo = geoSelect.value;
    const oldRegion = '{{ old('location_region') }}';
    
    regionSelect.innerHTML = '<option value="">-- Select Region --</option>';
    document.getElementById('location_city').innerHTML = '<option value="">-- Select City --</option>';
    
    if (selectedGeo && indonesiaRegions[selectedGeo]) {
        indonesiaRegions[selectedGeo].forEach(region => {
            const option = document.createElement('option');
            option.value = region;
            option.textContent = region;
            if (oldRegion === region) {
                option.selected = true;
            }
            regionSelect.appendChild(option);
        });
        
        // If there's an old region value, update cities too
        if (oldRegion && indonesiaRegions[selectedGeo].includes(oldRegion)) {
            updateCities();
        }
    }
}

// Update cities based on region selection
function updateCities() {
    const regionSelect = document.getElementById('location_region');
    const citySelect = document.getElementById('location_city');
    const selectedRegion = regionSelect.value;
    const oldCity = '{{ old('location_city') }}';
    
    citySelect.innerHTML = '<option value="">-- Select City --</option>';
    
    if (selectedRegion && indonesiaCities[selectedRegion]) {
        indonesiaCities[selectedRegion].forEach(city => {
            const option = document.createElement('option');
            option.value = city;
            option.textContent = city;
            if (oldCity === city) {
                option.selected = true;
            }
            citySelect.appendChild(option);
        });
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Init custom-dd untuk semua dropdown form. Guard typeof biar halaman
    // tidak crash kalau custom-dropdown.js gagal di-load.
    if (typeof initCustomDropdowns === 'function') {
        initCustomDropdowns();
    }

    // Always sync AE fields: applies the disabled placeholder when no type is
    // selected, or the correct control (dropdown/text) when a type exists.
    toggleAEFields();

    // Initialize location dropdowns if there are old values
    @if(old('location_geographical'))
        updateRegions();
    @endif

    // Populate IO options for the currently-selected client/type (Body Hire flow).
    refreshIoOptions();
});

// ===== Body Hire: pilih IO existing (per company) atau ketik baru =====
// IO number existing dikelompokkan per client_id. Untuk Body Hire, datalist
// diisi HANYA dengan IO milik company (client) yang sedang dipilih.
window.IOS_BY_CLIENT = @json($iosByClient ?? []);
window.refreshIoOptions = function () {
    const type   = document.getElementById('project_type')?.value || '';
    const client = document.getElementById('client_id')?.value || '';
    const dl     = document.getElementById('io_number_options');
    const hint   = document.getElementById('io_number_hint');
    if (!dl) return;

    const isBodyHire = (type === 'Body Hire');
    dl.innerHTML = '';
    if (isBodyHire && client && window.IOS_BY_CLIENT[client]) {
        window.IOS_BY_CLIENT[client].forEach(io => {
            const opt = document.createElement('option');
            opt.value = io;
            dl.appendChild(opt);
        });
    }
    if (hint) hint.classList.toggle('hidden', !isBodyHire);
};
</script>
{{-- ===== Sales Financial: Thousand Separator + Auto-Calculation ===== --}}
<script>
(function () {
    // Format angka dengan titik sebagai pemisah ribuan (gaya Indonesia)
    function fmtRp(n) {
        const neg = n < 0;
        const abs = Math.abs(Math.round(n));
        return (neg ? '-' : '') + abs.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }
    // Parse string berformat (Indonesian thousands-dot, or raw DB decimal)
    function parseNum(str) {
        if (str === '' || str === null || str === undefined) return 0;
        const s = String(str).trim();
        // Indonesian display format with comma decimal (e.g. "1.000.000,50")
        if (s.includes(',')) {
            return parseFloat(s.replace(/\./g, '').replace(',', '.')) || 0;
        }
        // Indonesian thousands-dot format with multiple dots (e.g. "1.000.000.000")
        const dotCount = (s.match(/\./g) || []).length;
        if (dotCount > 1) {
            return parseFloat(s.replace(/\./g, '')) || 0;
        }
        // Raw DB value: standard integer or decimal (e.g. "1000000000" or "1000000000.00")
        return parseFloat(s) || 0;
    }
    // Hitung ulang Gross Profit & % GP dari Revenue dan Plan Cost
    function sfinRecalc() {
        const rev  = parseNum(document.getElementById('sfin_rev_val')?.value);
        const pc   = parseNum(document.getElementById('sfin_pc_val')?.value);
        const gp   = rev - pc;
        const pct  = (rev !== 0) ? (gp / rev) * 100 : 0;

        const gpVal   = document.getElementById('sfin_gp_val');
        const pctVal  = document.getElementById('sfin_pct_val');
        const gpDisp  = document.getElementById('sfin_gp_disp');
        const pctDisp = document.getElementById('sfin_pct_disp');

        if (gpVal)   gpVal.value   = gp;
        if (pctVal)  pctVal.value  = pct.toFixed(2);
        if (gpDisp)  gpDisp.value  = fmtRp(gp);
        if (pctDisp) pctDisp.value = pct.toFixed(2).replace('.', ',');
    }
    // Attach listener ke input display: format on-the-fly + sync hidden
    function bindInput(dispId, valId) {
        const disp = document.getElementById(dispId);
        const val  = document.getElementById(valId);
        if (!disp || !val) return;
        disp.addEventListener('input', function () {
            const raw    = this.value.replace(/[^0-9]/g, '');
            this.value   = raw ? raw.replace(/\B(?=(\d{3})+(?!\d))/g, '.') : '';
            val.value    = raw || '';
            sfinRecalc();
        });
    }
    document.addEventListener('DOMContentLoaded', function () {
        const revVal  = document.getElementById('sfin_rev_val');
        const pcVal   = document.getElementById('sfin_pc_val');
        const revDisp = document.getElementById('sfin_rev_disp');
        const pcDisp  = document.getElementById('sfin_pc_disp');
        if (!revVal) return; // guard: elemen tidak ada di halaman ini

        // Inisialisasi display dari value lama (old input setelah validasi error)
        if (revVal.value)  revDisp.value = fmtRp(parseFloat(revVal.value) || 0);
        if (pcVal.value)   pcDisp.value  = fmtRp(parseFloat(pcVal.value)  || 0);
        sfinRecalc();

        bindInput('sfin_rev_disp', 'sfin_rev_val');
        bindInput('sfin_pc_disp',  'sfin_pc_val');
    });
})();
</script>

{{-- Load custom-dd component (sama dengan halaman admin lain). filemtime
     cache buster supaya production auto-invalidate setiap deploy. --}}
@php
    $customDdPath = public_path('js/custom-dropdown.js');
    $customDdVer  = file_exists($customDdPath) ? filemtime($customDdPath) : time();
@endphp
<script src="/js/custom-dropdown.js?v={{ $customDdVer }}"></script>

{{-- Flatpickr + HolidayCalendar (inline, uses fetch — no axios needed) --}}
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
<script>
window.HolidayCalendar = (function () {
    var _set  = new Set();
    var _meta = {};
    var _loaded = false, _promise = null;

    function isWeekend(d) { var day = d.getDay(); return day === 0 || day === 6; }
    function toISO(d) {
        return d.getFullYear() + '-' +
               String(d.getMonth() + 1).padStart(2, '0') + '-' +
               String(d.getDate()).padStart(2, '0');
    }
    function isNonWorkingDay(d) { return isWeekend(d) || _set.has(toISO(d)); }
    function holidayInfo(d) { return _meta[toISO(d)] || null; }

    function load(from, to) {
        if (_loaded) return Promise.resolve();
        if (_promise) return _promise;
        var y = new Date().getFullYear();
        from = from || (y - 1); to = to || (y + 2);
        _promise = fetch('/api/holidays?from=' + from + '&to=' + to)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                (data.holidays || []).forEach(function (h) {
                    _set.add(h.date);
                    _meta[h.date] = { name: h.name, type: h.type };
                });
                _loaded = true;
            })
            .catch(function (err) {
                console.warn('HolidayCalendar: failed to load holidays', err);
                _loaded = true;
            });
        return _promise;
    }

    function initPicker(input, options) {
        if (!input || typeof flatpickr === 'undefined') return null;
        var cfg = Object.assign({
            dateFormat: 'Y-m-d',
            allowInput: false,
            disableMobile: true,
            monthSelectorType: 'static',
            appendTo: document.body,
            disable: [function (date) { return isNonWorkingDay(date); }],
            onDayCreate: function (_, __, ___, dayElem) {
                if (isWeekend(dayElem.dateObj)) dayElem.classList.add('fp-weekend');
                var info = holidayInfo(dayElem.dateObj);
                if (info) { dayElem.classList.add('fp-holiday'); dayElem.title = info.name; }
            }
        }, options || {});
        return flatpickr(input, cfg);
    }

    document.addEventListener('DOMContentLoaded', function () { load(); });

    return { load: load, isNonWorkingDay: isNonWorkingDay, holidayInfo: holidayInfo, initPicker: initPicker, toISO: toISO };
})();
</script>

{{-- Contract date pickers (linked: start = lower bound of end, end = upper bound of start) --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    var csEl = document.getElementById('contract_start_date');
    var ceEl = document.getElementById('contract_end_date');
    if (!csEl || !ceEl) return;
    var fpCS = null, fpCE = null;

    function init() {
        var base = { dateFormat: 'Y-m-d', allowInput: false };
        if (window.HolidayCalendar) {
            fpCS = window.HolidayCalendar.initPicker(csEl, Object.assign({}, base, {
                onChange: function (_, str) { if (fpCE) fpCE.set('minDate', str || null); }
            }));
            fpCE = window.HolidayCalendar.initPicker(ceEl, Object.assign({}, base, {
                onChange: function (_, str) { if (fpCS) fpCS.set('maxDate', str || null); }
            }));
        } else if (typeof flatpickr !== 'undefined') {
            fpCS = flatpickr(csEl, base);
            fpCE = flatpickr(ceEl, base);
        }
        // Re-apply linkage for old() values after a failed submit
        if (csEl.value && fpCE) fpCE.set('minDate', csEl.value);
        if (ceEl.value && fpCS) fpCS.set('maxDate', ceEl.value);
    }

    if (window.HolidayCalendar && window.HolidayCalendar.load) {
        window.HolidayCalendar.load().then(init);
    } else {
        init();
    }
});
</script>

{{-- ===== Team Members (Create Form) ===== --}}
<script>
(function () {
    // Employee data map: id → { name, position, employee_type, modules }
    // employee_type & modules ikut supaya keduanya TIDAK diketik manual:
    // type = turunan data employee, modules = kualifikasi employee.
    var empMap = {!! json_encode(
        $employees->mapWithKeys(fn ($e) => [
            (string) $e->employee_id => [
                'name'          => $e->basicData->full_name ?? '-',
                'position'      => $e->basicData->position ?? '',
                'employee_type' => $e->basicData->employee_type ?? 'Internal',
                'modules'       => $e->qualifications
                    ->map(fn ($q) => trim((string) ($q->module->name ?? '')))
                    ->filter()->unique()->sort()->values(),
            ],
        ]),
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) !!};

    var teamMembers = [];   // array of member objects
    var editIndex   = -1;   // -1 = add mode, >=0 = edit index
    var fpStart = null, fpEnd = null;

    /* ---- helpers ---- */
    function escHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function fmtDate(d) {
        if (!d) return '-';
        var dt = new Date(d + 'T00:00:00');
        return dt.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    /* ---- member source: Employee vs Vendor ----
       Employee → orang master Employee; Employee Type & Module ikut datanya.
       Vendor   → orang vendor di luar master: vendornya dari Business Partner
                  (type Vendor), identitas & modulnya diketik manual.          */
    var ctmSource = 'employee';

    function ctmSetSource(source) {
        ctmSource = (source === 'vendor') ? 'vendor' : 'employee';
        var isVendor = ctmSource === 'vendor';

        document.getElementById('ctmPaneEmployee').style.display = isVendor ? 'none' : 'grid';
        document.getElementById('ctmPaneVendor').style.display   = isVendor ? 'grid' : 'none';

        var active   = 'px-4 py-1.5 text-sm font-semibold rounded-md transition-all duration-200 bg-white text-gray-900 shadow-sm';
        var inactive = 'px-4 py-1.5 text-sm font-semibold rounded-md transition-all duration-200 text-gray-500 hover:text-gray-700';
        document.getElementById('ctmSrcBtnEmployee').className = isVendor ? inactive : active;
        document.getElementById('ctmSrcBtnVendor').className   = isVendor ? active : inactive;

        document.getElementById('ctmSrcHint').textContent = isVendor
            ? 'Vendor is taken from Master Business Partner (type Vendor); the consultant details are typed in manually.'
            : 'Consultant comes from Master Employee — Employee Type and Module follow their data.';
    }
    window.ctmSetSource = ctmSetSource;

    /* ---- module picker (dari kualifikasi employee) ---- */
    function ctmRenderModules(modules, checked) {
        var box = document.getElementById('ctm_module_options');
        var ph  = document.getElementById('ctm_module_placeholder');
        box.innerHTML = '';

        var list = modules || [];
        if (!list.length) {
            ph.textContent = modules === null
                ? 'Select a consultant first.'
                : 'No module found in this consultant\'s qualification.';
            ph.classList.remove('hidden');
            return;
        }
        ph.classList.add('hidden');

        var pre = (checked || []).map(function (m) { return m.trim().toLowerCase(); });
        list.forEach(function (name) {
            var label = document.createElement('label');
            label.className = 'inline-flex items-center gap-1.5 text-sm text-gray-700 cursor-pointer';
            var cb = document.createElement('input');
            cb.type      = 'checkbox';
            cb.value     = name;
            cb.className = 'ctm-module-option rounded border-gray-300';
            cb.checked   = pre.indexOf(name.trim().toLowerCase()) !== -1;
            label.appendChild(cb);
            label.appendChild(document.createTextNode(name));
            box.appendChild(label);
        });
    }

    function ctmPickedModules() {
        return Array.prototype.slice
            .call(document.querySelectorAll('.ctm-module-option:checked'))
            .map(function (cb) { return cb.value; });
    }

    /* Dipanggil custom-dd (data-onchange) setiap consultant berganti. */
    function ctmOnEmployeeChange() {
        var id   = document.getElementById('ctm_employee_id').value;
        var info = id ? empMap[id] : null;
        document.getElementById('ctm_employee_type_display').value = info ? (info.employee_type || 'Internal') : '';
        ctmRenderModules(info ? (info.modules || []) : null, []);
    }
    window.ctmOnEmployeeChange = ctmOnEmployeeChange;

    /* ---- pickers ---- */
    function initModalPickers() {
        if (fpStart) { fpStart.destroy(); fpStart = null; }
        if (fpEnd)   { fpEnd.destroy(); fpEnd = null; }
        var sEl = document.getElementById('ctm_start_date');
        var eEl = document.getElementById('ctm_end_date');
        if (window.HolidayCalendar) {
            fpStart = window.HolidayCalendar.initPicker(sEl, { dateFormat: 'Y-m-d', allowInput: false });
            fpEnd   = window.HolidayCalendar.initPicker(eEl, { dateFormat: 'Y-m-d', allowInput: false });
        } else if (typeof flatpickr !== 'undefined') {
            fpStart = flatpickr(sEl, { dateFormat: 'Y-m-d' });
            fpEnd   = flatpickr(eEl, { dateFormat: 'Y-m-d' });
        }
    }

    /* ---- reset modal fields ---- */
    function resetModal() {
        // custom-dd reset
        var ddLabel = document.querySelector('#ctm_emp_dd .custom-dd-label');
        if (ddLabel) { ddLabel.textContent = '-- Select Employee --'; ddLabel.className = 'custom-dd-label text-gray-500'; }
        document.getElementById('ctm_employee_id').value = '';
        document.getElementById('ctm_employee_type_display').value = '';
        ctmRenderModules(null, []);

        document.getElementById('ctm_vendor_id').value       = '';
        document.getElementById('ctm_member_name').value     = '';
        document.getElementById('ctm_member_position').value = '';
        document.getElementById('ctm_vendor_module').value   = '';

        document.getElementById('ctm_role').value         = '';
        document.getElementById('ctm_start_date').value   = '';
        document.getElementById('ctm_end_date').value     = '';
        document.getElementById('ctm_notes').value        = '';
        ['ctm_emp_err', 'ctm_role_err', 'ctm_start_err', 'ctm_vendor_err', 'ctm_member_name_err'].forEach(function (id) {
            document.getElementById(id).classList.add('hidden');
        });
        ctmSetSource('employee');
    }

    /* ---- open (add) ---- */
    function openAddTeamModal() {
        editIndex = -1;
        document.getElementById('cTeamModalTitle').textContent = 'Add Team Member';
        resetModal();
        document.getElementById('cTeamModal').classList.remove('hidden');
        setTimeout(function () {
            initModalPickers();
            if (typeof initCustomDropdowns === 'function') {
                initCustomDropdowns(document.getElementById('ctm_emp_dd'));
            }
        }, 50);
    }
    window.openAddTeamModal = openAddTeamModal;

    /* ---- open (edit) ---- */
    function editTeamMember(idx) {
        var m = teamMembers[idx];
        if (!m) return;
        editIndex = idx;
        document.getElementById('cTeamModalTitle').textContent = 'Edit Team Member';
        resetModal();

        ctmSetSource(m.member_source || 'employee');

        if ((m.member_source || 'employee') === 'vendor') {
            document.getElementById('ctm_vendor_id').value       = m.vendor_id || '';
            document.getElementById('ctm_member_name').value     = m.member_name || '';
            document.getElementById('ctm_member_position').value = m.member_position || '';
            document.getElementById('ctm_vendor_module').value   = m.module || '';
        } else {
            // Fill employee custom-dd
            var ddLabel = document.querySelector('#ctm_emp_dd .custom-dd-label');
            if (ddLabel) { ddLabel.textContent = m.name; ddLabel.className = 'custom-dd-label text-gray-700'; }
            document.getElementById('ctm_employee_id').value = m.employee_id;
            var info = empMap[m.employee_id];
            document.getElementById('ctm_employee_type_display').value = m.employee_type || (info ? info.employee_type : 'Internal');
            ctmRenderModules(info ? (info.modules || []) : [], (m.module || '').split(','));
        }

        document.getElementById('ctm_role').value  = m.role || '';
        document.getElementById('ctm_notes').value = m.notes || '';

        document.getElementById('cTeamModal').classList.remove('hidden');
        setTimeout(function () {
            initModalPickers();
            if (m.start_date && fpStart) fpStart.setDate(m.start_date, true);
            if (m.end_date   && fpEnd)   fpEnd.setDate(m.end_date, true);
            if (typeof initCustomDropdowns === 'function') {
                initCustomDropdowns(document.getElementById('ctm_emp_dd'));
            }
        }, 50);
    }
    window.editTeamMember = editTeamMember;

    /* ---- close ---- */
    function closeCTeamModal() {
        document.getElementById('cTeamModal').classList.add('hidden');
        if (fpStart) { fpStart.destroy(); fpStart = null; }
        if (fpEnd)   { fpEnd.destroy(); fpEnd = null; }
    }
    window.closeCTeamModal = closeCTeamModal;

    /* ---- remove ---- */
    function removeTeamMember(idx) {
        teamMembers.splice(idx, 1);
        renderTeamTable();
    }
    window.removeTeamMember = removeTeamMember;

    /* ---- save ---- */
    function saveCTeamMember() {
        var isVendor = ctmSource === 'vendor';
        var role  = document.getElementById('ctm_role').value;
        var start = document.getElementById('ctm_start_date').value;
        var valid = true;

        var empId      = document.getElementById('ctm_employee_id').value;
        var vendorId   = document.getElementById('ctm_vendor_id').value;
        var memberName = document.getElementById('ctm_member_name').value.trim();

        function mark(id, bad) {
            document.getElementById(id).classList.toggle('hidden', !bad);
            if (bad) valid = false;
        }
        mark('ctm_emp_err',         !isVendor && !empId);
        mark('ctm_vendor_err',      isVendor  && !vendorId);
        mark('ctm_member_name_err', isVendor  && !memberName);
        mark('ctm_role_err',        !role);
        mark('ctm_start_err',       !start);
        if (!valid) return;

        var common = {
            role:       role,
            start_date: start,
            end_date:   document.getElementById('ctm_end_date').value || '',
            notes:      document.getElementById('ctm_notes').value.trim(),
        };

        var member;
        if (isVendor) {
            var vendorSel = document.getElementById('ctm_vendor_id');
            member = Object.assign({
                member_source:   'vendor',
                employee_id:     '',
                vendor_id:       vendorId,
                vendor_name:     vendorSel.options[vendorSel.selectedIndex].text,
                member_name:     memberName,
                member_position: document.getElementById('ctm_member_position').value.trim(),
                name:            memberName,
                position:        document.getElementById('ctm_member_position').value.trim(),
                module:          document.getElementById('ctm_vendor_module').value.trim(),
                employee_type:   'Vendor',
            }, common);
        } else {
            var empInfo = empMap[empId] || { name: empId, position: '', employee_type: 'Internal' };
            member = Object.assign({
                member_source:   'employee',
                employee_id:     empId,
                vendor_id:       '',
                vendor_name:     '',
                member_name:     '',
                member_position: '',
                name:            empInfo.name,
                position:        empInfo.position,
                module:          ctmPickedModules().join(', '),
                employee_type:   empInfo.employee_type || 'Internal',
            }, common);
        }

        if (editIndex >= 0) {
            teamMembers[editIndex] = member;
        } else {
            teamMembers.push(member);
        }
        closeCTeamModal();
        renderTeamTable();
    }
    window.saveCTeamMember = saveCTeamMember;

    /* ---- render table + hidden inputs ---- */
    function renderTeamTable() {
        var tbody  = document.getElementById('teamTableBody');
        var wrap   = document.getElementById('teamTableWrap');
        var empty  = document.getElementById('teamEmptyMsg');
        var hidden = document.getElementById('teamHiddenInputs');

        if (teamMembers.length === 0) {
            wrap.classList.add('hidden');
            empty.classList.remove('hidden');
        } else {
            wrap.classList.remove('hidden');
            empty.classList.add('hidden');
        }

        tbody.innerHTML = '';
        teamMembers.forEach(function (m, i) {
            var tr = document.createElement('tr');
            tr.className = 'hover:bg-gray-50';
            var period = fmtDate(m.start_date) + ' – ' + (m.end_date ? fmtDate(m.end_date) : 'Present');
            var typeDisplay = escHtml(m.employee_type) + (m.vendor_name ? ' (' + escHtml(m.vendor_name) + ')' : '');
            var nameCell = escHtml(m.name) + (m.position ? '<div class="text-xs text-gray-400 mt-0.5">' + escHtml(m.position) + '</div>' : '');
            tr.innerHTML =
                '<td class="px-6 py-4 text-sm font-medium text-gray-900">' + nameCell + '</td>' +
                '<td class="px-6 py-4 text-sm text-gray-500">' + (m.module ? escHtml(m.module) : '-') + '</td>' +
                '<td class="px-6 py-4 text-sm text-gray-500">' + escHtml(m.role) + '</td>' +
                '<td class="px-6 py-4 text-sm text-gray-500">' + typeDisplay + '</td>' +
                '<td class="px-6 py-4 text-sm text-gray-500">' + escHtml(period) + '</td>' +
                '<td class="px-6 py-4 text-sm whitespace-nowrap">' +
                    '<button type="button" onclick="editTeamMember(' + i + ')" class="text-blue-600 hover:text-blue-800 text-xs font-medium mr-3">Edit</button>' +
                    '<button type="button" onclick="removeTeamMember(' + i + ')" class="text-red-500 hover:text-red-700 text-xs font-medium">Remove</button>' +
                '</td>';
            tbody.appendChild(tr);
        });

        // Regenerate hidden inputs inside the main form
        hidden.innerHTML = '';
        var fields = ['member_source', 'employee_id', 'vendor_id', 'member_name', 'member_position',
                      'module', 'role', 'start_date', 'end_date', 'notes'];
        teamMembers.forEach(function (m, i) {
            fields.forEach(function (f) {
                var inp = document.createElement('input');
                inp.type  = 'hidden';
                inp.name  = 'team_members[' + i + '][' + f + ']';
                inp.value = m[f] || '';
                hidden.appendChild(inp);
            });
        });
    }
    window.renderTeamTable = renderTeamTable;
})();
</script>

@endsection
@extends('dashboard')
@section('title', 'Edit Support')
@section('page-title', 'Edit Support')
@section('page-subtitle', $support->name ?? 'Support #' . $support->id)

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

    // Build current labels for dropdowns
    $currentClientLabel = '';
    foreach ($clients as $_c) {
        if ($_c->customer_id == $support->client_id) { $currentClientLabel = $_c->basicData->name_1 ?? 'N/A'; break; }
    }

    $teamLabels = [
        'delivery_owner_id'  => '',
        'co_pm_id'           => '',
        'support_admin_id'   => '',
        'sales_id'           => '',
    ];
    foreach ($employees as $_e) {
        $name = $_e->basicData->full_name ?? 'N/A';
        if ($support->delivery_owner_id  == $_e->employee_id) $teamLabels['delivery_owner_id']  = $name;
        if ($support->co_pm_id           == $_e->employee_id) $teamLabels['co_pm_id']           = $name;
        if ($support->support_admin_id   == $_e->employee_id) $teamLabels['support_admin_id']   = $name;
        if ($support->sales_id           == $_e->employee_id) $teamLabels['sales_id']           = $name;
    }

    $roleId      = session('user.role.id');
    $canEditType = in_array($roleId, \App\Enums\RoleId::TICKET_MANAGER_GROUP, true);

    $teamFields = [
        ['key' => 'delivery_owner_id',  'label' => 'Delivery Owner',  'placeholder' => 'Select delivery owner',  'value' => $support->delivery_owner_id],
        ['key' => 'co_pm_id',           'label' => 'Co PM',           'placeholder' => 'Select co pm',           'value' => $support->co_pm_id],
        ['key' => 'support_admin_id',   'label' => 'Support Admin',   'placeholder' => 'Select support admin',   'value' => $support->support_admin_id],
        ['key' => 'sales_id',           'label' => 'Sales',           'placeholder' => 'Select sales',           'value' => $support->sales_id],
    ];

    $currentSupportManagerIds   = old('support_manager_ids', $support->supportManagers->pluck('employee_id')->implode(','));
    $currentSupportManagerLabel = $support->supportManagers->pluck('basicData.full_name')->filter()->join(', ');
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
                        <a href="{{ route('delivery.support.show', $support->id) }}" class="ml-1 text-sm font-medium text-gray-600 primary-link transition-colors">
                            {{ \Illuminate\Support\Str::limit($support->name ?? 'Support #' . $support->id, 40) }}
                        </a>
                    </div>
                </li>
                <li>
                    <div class="flex items-center">
                        <svg class="w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="ml-1 text-sm font-medium text-gray-500">Edit</span>
                    </div>
                </li>
            </ol>
        </nav>
    </div>

    <form action="{{ route('delivery.support.update', $support->id) }}" method="POST" id="supportForm">
        @csrf
        @method('PUT')

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
                                   value="{{ old('name', $support->name) }}"
                                   class="block w-full border border-gray-300 rounded-lg shadow-sm primary-focus text-sm px-4 py-2.5"
                                   placeholder="Enter support name">
                            @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="client_id" class="block text-sm font-medium text-gray-700 mb-1">
                                Client <span class="text-red-500">*</span>
                            </label>
                            <div class="custom-dd relative" data-fixed="true">
                                <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm hover:border-gray-400 transition-all text-left">
                                    <span class="custom-dd-label {{ $currentClientLabel ? 'text-gray-700' : 'text-gray-500' }}">{{ $currentClientLabel ?: 'Select Client' }}</span>
                                    <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <input type="hidden" name="client_id" id="client_id" value="{{ old('client_id', $support->client_id) }}" required>
                                <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:400px;">
                                    <div class="custom-dd-search-wrap sticky top-0 bg-white border-b border-gray-100 px-2 py-2" style="z-index:1">
                                        <input type="text" class="custom-dd-search w-full px-3 py-1.5 text-sm border border-gray-200 rounded-md focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400" placeholder="Search client…" autocomplete="off" spellcheck="false">
                                    </div>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">Select Client</button>
                                    @foreach($clients as $client)
                                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="{{ $client->customer_id }}">{{ $client->basicData->name_1 ?? 'N/A' }}</button>
                                    @endforeach
                                    <div class="custom-dd-empty hidden px-4 py-3 text-sm text-gray-400 text-center">No results</div>
                                </div>
                            </div>
                            @error('client_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        @if($canEditType)
                        <div>
                            <label for="type" class="block text-sm font-medium text-gray-700 mb-1">
                                Type <span class="text-red-500">*</span>
                            </label>
                            @php $typeVal = old('type', $support->type); @endphp
                            <div class="custom-dd relative" data-fixed="true">
                                <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm hover:border-gray-400 transition-all text-left">
                                    <span class="custom-dd-label {{ $typeVal ? 'text-gray-700' : 'text-gray-500' }}">{{ $typeVal ?: 'Select Type' }}</span>
                                    <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <input type="hidden" name="type" id="type" value="{{ $typeVal }}" required>
                                <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:240px;">
                                    @foreach(['AMS','MO','ATS','CR','RISE','CLOUD','POSTPAID','Project','Internal'] as $t)
                                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="{{ $t }}">{{ $t }}</button>
                                    @endforeach
                                </div>
                            </div>
                            @error('type')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        @else
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                            <p class="px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-700">
                                {{ $support->type ?: 'No type set' }}
                                <span class="ml-2 text-xs text-gray-400">(read-only)</span>
                            </p>
                        </div>
                        @endif

                        <div>
                            <label for="support_method" class="block text-sm font-medium text-gray-700 mb-1">Support Method</label>
                            @php $sm = old('support_method', $support->support_method); @endphp
                            <div class="custom-dd relative" data-fixed="true">
                                <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm hover:border-gray-400 transition-all text-left">
                                    <span class="custom-dd-label {{ $sm ? 'text-gray-700' : 'text-gray-500' }}">{{ $sm ?: 'Select method' }}</span>
                                    <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <input type="hidden" name="support_method" id="support_method" value="{{ $sm }}">
                                <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:200px;">
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">Select method</button>
                                    @foreach(['Remote','On-Site','Hybrid'] as $m)
                                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="{{ $m }}">{{ $m }}</button>
                                    @endforeach
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
                    <div class="p-6 space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                                <input type="date" name="start_date" id="start_date"
                                       value="{{ old('start_date', $support->start_date?->format('Y-m-d')) }}"
                                       class="block w-full border border-gray-300 rounded-lg shadow-sm primary-focus text-sm px-4 py-2.5">
                            </div>
                            <div>
                                <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                                <input type="date" name="end_date" id="end_date"
                                       value="{{ old('end_date', $support->end_date?->format('Y-m-d')) }}"
                                       class="block w-full border border-gray-300 rounded-lg shadow-sm primary-focus text-sm px-4 py-2.5">
                            </div>
                            <div>
                                <label for="resolution_estimated" class="block text-sm font-medium text-gray-700 mb-1">Resolution Estimated</label>
                                <input type="date" name="resolution_estimated" id="resolution_estimated"
                                       value="{{ old('resolution_estimated', $support->resolution_estimated?->format('Y-m-d')) }}"
                                       class="block w-full border border-gray-300 rounded-lg shadow-sm primary-focus text-sm px-4 py-2.5">
                            </div>
                        </div>

                        {{-- Service Window --}}
                        <div class="border-t border-gray-100 pt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Service Window
                                <span class="ml-1 text-xs font-normal text-gray-400">(operational hours per day)</span>
                            </label>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="service_window_start" class="block text-xs text-gray-500 mb-1">Start Time</label>
                                    <input type="time" name="service_window_start" id="service_window_start"
                                           value="{{ old('service_window_start', $support->service_window_start ? \Illuminate\Support\Str::substr($support->service_window_start, 0, 5) : '') }}"
                                           class="block w-full border border-gray-300 rounded-lg shadow-sm primary-focus text-sm px-4 py-2.5">
                                </div>
                                <div>
                                    <label for="service_window_end" class="block text-xs text-gray-500 mb-1">End Time</label>
                                    <input type="time" name="service_window_end" id="service_window_end"
                                           value="{{ old('service_window_end', $support->service_window_end ? \Illuminate\Support\Str::substr($support->service_window_end, 0, 5) : '') }}"
                                           class="block w-full border border-gray-300 rounded-lg shadow-sm primary-focus text-sm px-4 py-2.5">
                                </div>
                            </div>
                            <p id="swError" class="mt-1 text-xs text-red-500 hidden">End time must be after start time.</p>
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
                                <label for="support_manager_ids" class="block text-sm font-medium text-gray-700 mb-1">Support Manager</label>
                                <div class="custom-dd relative" data-fixed="true" data-multi="true" data-placeholder="Select support manager(s)">
                                    <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm hover:border-gray-400 transition-all text-left">
                                        <span class="custom-dd-label {{ $currentSupportManagerLabel ? 'text-gray-700' : 'text-gray-500' }}">{{ $currentSupportManagerLabel ?: 'Select support manager(s)' }}</span>
                                        <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </button>
                                    <input type="hidden" name="support_manager_ids" id="support_manager_ids" value="{{ $currentSupportManagerIds }}">
                                    <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:400px;">
                                        <div class="custom-dd-search-wrap sticky top-0 bg-white border-b border-gray-100 px-2 py-2" style="z-index:1">
                                            <input type="text" class="custom-dd-search w-full px-3 py-1.5 text-sm border border-gray-200 rounded-md focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400" placeholder="Search employee…" autocomplete="off" spellcheck="false">
                                        </div>
                                        @foreach($employees as $employee)
                                            <button type="button" class="custom-dd-item w-full flex items-center justify-between gap-2 px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="{{ $employee->employee_id }}">
                                                <span class="custom-dd-item-text">{{ $employee->basicData->full_name ?? 'N/A' }}</span>
                                                <svg class="custom-dd-check w-4 h-4 text-red-500 opacity-0 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                            </button>
                                        @endforeach
                                        <div class="custom-dd-empty hidden px-4 py-3 text-sm text-gray-400 text-center">No results</div>
                                    </div>
                                </div>
                            </div>
                            @foreach($teamFields as $tf)
                            <div>
                                <label for="{{ $tf['key'] }}" class="block text-sm font-medium text-gray-700 mb-1">{{ $tf['label'] }}</label>
                                @php $val = old($tf['key'], $tf['value']); @endphp
                                <div class="custom-dd relative" data-fixed="true">
                                    <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm hover:border-gray-400 transition-all text-left">
                                        <span class="custom-dd-label {{ $teamLabels[$tf['key']] ? 'text-gray-700' : 'text-gray-500' }}">{{ $teamLabels[$tf['key']] ?: $tf['placeholder'] }}</span>
                                        <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </button>
                                    <input type="hidden" name="{{ $tf['key'] }}" id="{{ $tf['key'] }}" value="{{ $val }}">
                                    <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:400px;">
                                        <div class="custom-dd-search-wrap sticky top-0 bg-white border-b border-gray-100 px-2 py-2" style="z-index:1">
                                            <input type="text" class="custom-dd-search w-full px-3 py-1.5 text-sm border border-gray-200 rounded-md focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400" placeholder="Search employee…" autocomplete="off" spellcheck="false">
                                        </div>
                                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">{{ $tf['placeholder'] }}</button>
                                        @foreach($employees as $employee)
                                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="{{ $employee->employee_id }}">{{ $employee->basicData->full_name ?? 'N/A' }}</button>
                                        @endforeach
                                        <div class="custom-dd-empty hidden px-4 py-3 text-sm text-gray-400 text-center">No results</div>
                                    </div>
                                </div>
                            </div>
                            @endforeach
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
                            <label for="total_mandays" class="block text-sm font-medium text-gray-700 mb-1">Total Mandays</label>
                            <input type="number" name="total_mandays" id="total_mandays" min="0"
                                   value="{{ old('total_mandays', $support->total_mandays) }}"
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
                            <label for="approval_date" class="block text-sm font-medium text-gray-700 mb-1">Approval Date</label>
                            <input type="date" name="approval_date" id="approval_date"
                                   value="{{ old('approval_date', $support->approval_date?->format('Y-m-d')) }}"
                                   class="block w-full border border-gray-300 rounded-lg shadow-sm primary-focus text-sm px-4 py-2.5">
                        </div>
                        <div>
                            <label for="approval_name" class="block text-sm font-medium text-gray-700 mb-1">Approved By</label>
                            <input type="text" name="approval_name" id="approval_name"
                                   value="{{ old('approval_name', $support->approval_name) }}"
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
                            Save Changes
                        </button>
                        <a href="{{ route('delivery.support.show', $support->id) }}"
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
    const endDate   = document.getElementById('end_date');

    startDate.addEventListener('change', function() {
        endDate.min = this.value;
        if (endDate.value && endDate.value < this.value) endDate.value = this.value;
    });

    endDate.addEventListener('blur', function() {
        if (this.value && startDate.value && this.value < startDate.value) {
            showNotification('End date cannot be before start date', 'warning');
            this.value = startDate.value;
        }
    });

    // Service window validation
    const swStart = document.getElementById('service_window_start');
    const swEnd   = document.getElementById('service_window_end');
    const swError = document.getElementById('swError');

    function validateServiceWindow() {
        if (swStart.value && swEnd.value && swEnd.value < swStart.value) {
            swError.classList.remove('hidden');
            swEnd.setCustomValidity('End time must be after start time.');
        } else {
            swError.classList.add('hidden');
            swEnd.setCustomValidity('');
        }
    }
    swStart.addEventListener('change', validateServiceWindow);
    swEnd.addEventListener('change', validateServiceWindow);

    if (typeof initCustomDropdowns === 'function') initCustomDropdowns();
});
</script>
@php
    $customDdPath = public_path('js/custom-dropdown.js');
    $customDdVer  = file_exists($customDdPath) ? filemtime($customDdPath) : time();
@endphp
<script src="/js/custom-dropdown.js?v={{ $customDdVer }}"></script>
@endsection

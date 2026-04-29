@extends('dashboard')
@section('title', 'Add New Delivery Project')
@section('page-title', 'Add New Delivery Project')
@section('page-subtitle', 'Create a new delivery project')
@push('styles')
<style>
    .primary-focus:focus { border-color: var(--primary-color) !important; box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.15) !important; outline: none !important; }
</style>
@endpush

@section('content')
<form action="{{ route('projects.store') }}" method="POST">
    @csrf
    
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
                <div class="custom-dd relative mt-1" data-fixed="true">
                    @php $oldClient = old('client_id'); $oldClientLabel = ''; @endphp
                    @foreach($clients as $c)@if($oldClient == $c->customer_id)@php $oldClientLabel = $c->basicData->name_1 ?? $c->email ?? 'Unknown'; @endphp @endif @endforeach
                    <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-4 py-2.5 bg-white border border-gray-300 rounded-lg shadow-sm text-sm hover:border-gray-400 transition-all text-left">
                        <span class="custom-dd-label {{ $oldClientLabel ? 'text-gray-700' : 'text-gray-500' }}">{{ $oldClientLabel ?: '-- Select Client --' }}</span>
                        <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <input type="hidden" name="client_id" id="client_id" value="{{ $oldClient }}" required>
                    <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:280px;">
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">-- Select Client --</button>
                        @foreach($clients as $client)
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="{{ $client->customer_id }}">{{ $client->basicData->name_1 ?? $client->email ?? 'Unknown' }}</button>
                        @endforeach
                    </div>
                </div>
            </div>
            <div>
                <label class="block font-medium text-sm text-gray-700">PIC / Project Manager <span class="text-red-500">*</span></label>
                <div class="custom-dd relative mt-1" data-fixed="true">
                    @php $oldPic = old('pic'); @endphp
                    <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-4 py-2.5 bg-white border border-gray-300 rounded-lg shadow-sm text-sm hover:border-gray-400 transition-all text-left">
                        <span class="custom-dd-label {{ $oldPic ? 'text-gray-700' : 'text-gray-500' }}">{{ $oldPic ?: '-- Select Project Manager --' }}</span>
                        <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <input type="hidden" name="pic" id="pic" value="{{ $oldPic }}" required>
                    <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:280px;">
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">-- Select Project Manager --</button>
                        @foreach($projectManagers as $pm)
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="{{ $pm->basicData->full_name ?? '-' }}">{{ $pm->basicData->full_name ?? '-' }}</button>
                        @endforeach
                    </div>
                </div>
            </div>
            <div>
                <label class="block font-medium text-sm text-gray-700">Project Type <span class="text-red-500">*</span></label>
                <div class="custom-dd relative mt-1" data-fixed="true">
                    @php $oldPt = old('project_type', 'Implementation'); @endphp
                    <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-4 py-2.5 bg-white border border-gray-300 rounded-lg shadow-sm text-sm hover:border-gray-400 transition-all text-left">
                        <span class="custom-dd-label text-gray-700">{{ $oldPt }}</span>
                        <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <input type="hidden" name="project_type" id="project_type" value="{{ $oldPt }}" required>
                    <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:240px;">
                        @foreach(['Implementation','Roll Out','Migration','Upgrade','WRICEF'] as $pt)
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="{{ $pt }}">{{ $pt }}</button>
                        @endforeach
                    </div>
                </div>
            </div>
            <!-- Category (Readonly) -->
            <div>
                <label for="category" class="block font-medium text-sm text-gray-700">Category</label>
                <input type="text" 
                       name="category" 
                       id="category" 
                       class="mt-1 block w-full bg-gray-100 cursor-not-allowed border border-gray-300 rounded-lg shadow-sm text-sm px-4 py-2.5" 
                       value="{{ old('category') }}"
                       readonly>
                <p class="mt-1 text-xs text-gray-500">Auto-filled from Delivery Project Planning.</p>
            </div>
            <!-- Phase (Readonly) -->
            <div>
                <label for="phase" class="block font-medium text-sm text-gray-700">Phase</label>
                <input type="text" 
                       name="phase" 
                       id="phase" 
                       class="mt-1 block w-full bg-gray-100 cursor-not-allowed border border-gray-300 rounded-lg shadow-sm text-sm px-4 py-2.5" 
                       value="{{ old('phase') }}"
                       readonly>
                <p class="mt-1 text-xs text-gray-500">Auto-filled from Delivery Project Planning.</p>
            </div>
            <div class="md:col-span-2">
                <label for="name" class="block font-medium text-sm text-gray-700">Project Name <span class="text-red-500">*</span></label>
                <input type="text" 
                       name="name" 
                       id="name" 
                       class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm primary-focus text-sm px-4 py-2.5"
                       value="{{ old('name') }}"
                       required>
            </div>
            <div class="md:col-span-2">
                <label for="description" class="block font-medium text-sm text-gray-700">Description <span class="text-red-500">*</span></label>
                <textarea name="description" 
                          id="description" 
                          rows="4" 
                          class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm primary-focus text-sm px-4 py-2.5"
                          required>{{ old('description') }}</textarea>
            </div>
            <div>
                <label for="start_date" class="block font-medium text-sm text-gray-700">Start Date</label>
                <input type="date" 
                       name="start_date" 
                       id="start_date" 
                       class="mt-1 block w-full bg-gray-100 cursor-not-allowed border border-gray-300 rounded-lg shadow-sm text-sm px-4 py-2.5" 
                       value="{{ old('start_date') }}"
                       readonly>
                <p class="mt-1 text-xs text-gray-500">Auto-filled from Delivery Project Planning.</p>
            </div>
            <div>
                <label for="end_date" class="block font-medium text-sm text-gray-700">End Date</label>
                <input type="date" 
                       name="end_date" 
                       id="end_date" 
                       class="mt-1 block w-full bg-gray-100 cursor-not-allowed border border-gray-300 rounded-lg shadow-sm text-sm px-4 py-2.5" 
                       value="{{ old('end_date') }}"
                       readonly>
                <p class="mt-1 text-xs text-gray-500">Auto-filled from Delivery Project Planning.</p>
            </div>
            <div>
                <label for="go_live_estimated" class="block font-medium text-sm text-gray-700">Go Live Estimated</label>
                <input type="date" 
                       name="go_live_estimated" 
                       id="go_live_estimated" 
                       class="mt-1 block w-full bg-gray-100 cursor-not-allowed border border-gray-300 rounded-lg shadow-sm text-sm px-4 py-2.5" 
                       value="{{ old('go_live_estimated') }}"
                       readonly>
                <p class="mt-1 text-xs text-gray-500">Auto-filled from Delivery Project Planning.</p>
            </div>
        </div>
    </div>

    {{-- Delivery Information Section --}}
    <div class="bg-white overflow-hidden shadow-md sm:rounded-lg mb-6">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-xl font-semibold text-gray-700">Delivery Information</h3>
            <p class="mt-1 text-sm text-gray-600">Delivery and sales information (optional)</p>
        </div>
        <div class="p-6">
            {{-- Sales Data Section --}}
            <div class="border-t border-gray-200 pt-6 mb-6">
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
                        <label for="ae_name" class="block text-sm font-medium text-gray-700 mb-1">
                            Account Executive Name
                        </label>
                        {{-- AE Employee select dibiarkan native: display:block↔none-nya
                             di-handle toggleAEFields(), dan name attribute-nya bisa
                             swap antara 'ae_employee_id' ↔ 'ae_name' tergantung
                             AE Type (Internal/External). Konversi ke custom-dd
                             akan rumit & berisiko. data-no-enhance opt-out dari
                             auto-wrap supaya tetap native. Styling ringan via
                             appearance-none + chevron background image. --}}
                        <select name="ae_employee_id" id="ae_employee_select"
                                data-no-enhance
                                class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm text-sm px-4 py-2.5 pr-10 appearance-none bg-white hover:border-gray-400 transition-all"
                                style="display: none; background-image: url(&quot;data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%239ca3af' stroke-width='2'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E&quot;); background-repeat: no-repeat; background-position: right 0.625rem center; background-size: 1rem;"
                                onchange="fillAEInfo()">
                            <option value="">-- Select Employee --</option>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->employee_id }}"
                                        data-phone=""
                                        data-email="">
                                    {{ $employee->basicData->full_name ?? '-' }}
                                </option>
                            @endforeach
                        </select>
                        <input type="text" name="ae_name" id="ae_name_input" 
                               value="{{ old('ae_name') }}"
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
                </div>
            </div>

            {{-- Delivery Data Section --}}
            <div class="border-t border-gray-200 pt-6">
                <h4 class="text-lg font-medium text-gray-900 mb-4">Delivery Data</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <label for="delivery_owner_id" class="block text-sm font-medium text-gray-700 mb-1">
                            Delivery Owner
                        </label>
                        @php
                            $oldDoId = old('delivery_owner_id');
                            $oldDoLabel = '';
                            foreach($employees as $e) { if ($oldDoId == $e->employee_id) { $oldDoLabel = $e->basicData->full_name ?? '-'; break; } }
                        @endphp
                        <div class="custom-dd relative mt-1" data-fixed="true">
                            <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-4 py-2.5 bg-white border border-gray-300 rounded-lg shadow-sm text-sm hover:border-gray-400 transition-all text-left">
                                <span class="custom-dd-label {{ $oldDoLabel ? 'text-gray-700' : 'text-gray-500' }}">{{ $oldDoLabel ?: '-- Select Employee --' }}</span>
                                <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <input type="hidden" name="delivery_owner_id" id="delivery_owner_id" value="{{ $oldDoId }}">
                            <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:280px;">
                                <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">-- Select Employee --</button>
                                @foreach($employees as $employee)
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="{{ $employee->employee_id }}">{{ $employee->basicData->full_name ?? '-' }}</button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <label for="delivery_manager_id" class="block text-sm font-medium text-gray-700 mb-1">
                            Delivery Manager
                        </label>
                        @php
                            $oldDmId = old('delivery_manager_id');
                            $oldDmLabel = '';
                            foreach($employees as $e) { if ($oldDmId == $e->employee_id) { $oldDmLabel = $e->basicData->full_name ?? '-'; break; } }
                        @endphp
                        <div class="custom-dd relative mt-1" data-fixed="true">
                            <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-4 py-2.5 bg-white border border-gray-300 rounded-lg shadow-sm text-sm hover:border-gray-400 transition-all text-left">
                                <span class="custom-dd-label {{ $oldDmLabel ? 'text-gray-700' : 'text-gray-500' }}">{{ $oldDmLabel ?: '-- Select Employee --' }}</span>
                                <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <input type="hidden" name="delivery_manager_id" id="delivery_manager_id" value="{{ $oldDmId }}">
                            <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:280px;">
                                <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">-- Select Employee --</button>
                                @foreach($employees as $employee)
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="{{ $employee->employee_id }}">{{ $employee->basicData->full_name ?? '-' }}</button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    
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

    {{-- Location Information Section --}}
    <div class="bg-white overflow-hidden shadow-md sm:rounded-lg mb-6">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-xl font-semibold text-gray-700">Location Information</h3>
            <p class="mt-1 text-sm text-gray-600">Project location information (optional)</p>
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

// Toggle AE fields based on type (Internal/External)
function toggleAEFields() {
    const aeType = document.getElementById('ae_type').value;
    const aeEmployeeSelect = document.getElementById('ae_employee_select');
    const aeNameInput = document.getElementById('ae_name_input');
    const aePhone = document.getElementById('ae_phone');
    const aeEmail = document.getElementById('ae_email');
    
    if (aeType === 'Internal') {
        aeEmployeeSelect.style.display = 'block';
        aeEmployeeSelect.name = 'ae_name';
        aeNameInput.style.display = 'none';
        aeNameInput.name = '';
        aePhone.readOnly = false;
        aeEmail.readOnly = false;
    } else if (aeType === 'External') {
        aeEmployeeSelect.style.display = 'none';
        aeEmployeeSelect.name = '';
        aeNameInput.style.display = 'block';
        aeNameInput.name = 'ae_name';
        aePhone.readOnly = false;
        aeEmail.readOnly = false;
    } else {
        aeEmployeeSelect.style.display = 'none';
        aeNameInput.style.display = 'block';
        aeNameInput.name = 'ae_name';
        aePhone.readOnly = false;
        aeEmail.readOnly = false;
    }
}

// Fill AE info when Internal employee selected.
// PENTING: readOnly hanya di-set true KALAU benar-benar ada data (phone/email
// dari data attribute). Markup <option> sekarang hardcode data-phone=""
// data-email="" — jadi tanpa guard ini, input phone & email selalu jadi
// readonly + kosong sehingga user tidak bisa ketik manual.
function fillAEInfo() {
    const select = document.getElementById('ae_employee_select');
    const selectedOption = select.options[select.selectedIndex];
    const phoneEl = document.getElementById('ae_phone');
    const emailEl = document.getElementById('ae_email');

    if (selectedOption && selectedOption.value) {
        const phone = selectedOption.dataset.phone || '';
        const email = selectedOption.dataset.email || '';
        phoneEl.value    = phone;
        emailEl.value    = email;
        // Hanya kunci field jika data tersedia → user tetap bisa ketik manual
        // saat data atribut kosong.
        phoneEl.readOnly = phone !== '';
        emailEl.readOnly = email !== '';
    } else {
        phoneEl.value    = '';
        emailEl.value    = '';
        phoneEl.readOnly = false;
        emailEl.readOnly = false;
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

    // Initialize AE fields if there's an old value
    @if(old('ae_type'))
        toggleAEFields();
    @endif

    // Initialize location dropdowns if there are old values
    @if(old('location_geographical'))
        updateRegions();
    @endif
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
@extends('dashboard')
@section('title', 'Support Details')
@section('page-title', 'Support Details')
@section('page-subtitle', $support->name ?? 'Support #' . $support->id)

@push('styles')
<style>
    .primary-link { color: var(--primary-color); }
    .primary-link:hover { opacity: 0.75; }
    .edit-btn:hover { color: var(--primary-color) !important; background-color: rgba(var(--primary-rgb), 0.08) !important; }
    .primary-focus:focus { border-color: var(--primary-color) !important; box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.15) !important; outline: none !important; }
</style>
@endpush

@section('content')
<div class="min-h-screen bg-gray-50">
    {{-- Breadcrumb --}}
    <div class="mb-6">
        <nav class="flex" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                <li class="inline-flex items-center">
                    <a href="{{ route('delivery.support.index') }}" class="text-gray-600 hover:primary-link text-sm font-medium transition-colors">
                        <i class="fas fa-headset mr-2"></i>Support
                    </a>
                </li>
                <li>
                    <div class="flex items-center">
                        <svg class="w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="ml-1 text-sm font-medium text-gray-500">{{ $support->name ?? 'Support #' . $support->id }}</span>
                    </div>
                </li>
            </ol>
        </nav>
    </div>

    {{-- Action Buttons --}}
    <div class="flex justify-end gap-3 mb-6">
        <a href="{{ route('delivery.support.edit', $support->id) }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all duration-200">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
            Edit Support
        </a>
        <a href="{{ route('delivery.support.planning.index', $support->id) }}"
           class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
            Open Planning
        </a>

        {{-- Customer Deliverable Folder Dropdown --}}
        <div class="relative" id="dlvDropdownContainer">
            <button type="button" id="dlvDropdownBtn" onclick="toggleDeliverableDropdown()"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white text-sm font-semibold rounded-lg hover:bg-emerald-700 transition-all duration-200">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>
                </svg>
                Deliverable Folder
                <svg id="dlvDropdownChevron" class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            {{-- Dropdown Panel --}}
            <div id="dlvDropdownMenu"
                 class="hidden absolute right-0 mt-2 w-72 bg-white rounded-xl shadow-xl border border-gray-200 z-40 overflow-hidden">

                {{-- Loading --}}
                <div id="dlvDdLoading" class="flex items-center gap-2 px-4 py-3 text-sm text-gray-400">
                    <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    Loading sub-folders…
                </div>

                {{-- Content --}}
                <div id="dlvDdContent" class="hidden">
                    {{-- Header --}}
                    <div class="px-4 py-2.5 bg-gray-50 border-b border-gray-100">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Sub-folders</p>
                        <p id="dlvDdCustomerName" class="text-xs text-gray-400 mt-0.5 truncate"></p>
                    </div>

                    {{-- Sub-folder list --}}
                    <div id="dlvDdList" class="max-h-56 overflow-y-auto divide-y divide-gray-50">
                        {{-- filled by JS --}}
                    </div>

                    {{-- Empty state (shown when no sub-folders) --}}
                    <div id="dlvDdEmpty" class="hidden px-4 py-4 text-sm text-gray-400 text-center">
                        <svg class="w-8 h-8 mx-auto mb-1 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>
                        </svg>
                        No sub-folders yet
                    </div>

                    {{-- Create new --}}
                    <div class="border-t border-gray-100">
                        <button type="button"
                                onclick="closeDeliverableDropdown(); openDeliverableModal()"
                                class="w-full flex items-center gap-2 px-4 py-3 text-sm font-medium text-emerald-700 hover:bg-emerald-50 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Create New Sub-folder
                        </button>
                    </div>
                </div>

                {{-- Error state --}}
                <div id="dlvDdError" class="hidden px-4 py-3 text-sm text-red-500">
                    <span id="dlvDdErrorMsg">Failed to load sub-folders.</span>
                </div>
            </div>
        </div>

    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Main Info --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Support Information --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-900">Support Information</h3>
                    <button type="button" onclick="openEditModal('support-info')"
                            class="p-2 text-gray-400 edit-btn rounded-lg transition"
                            title="Edit Support Information">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                    </button>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Support Name</label>
                            <p class="text-base font-semibold text-gray-900" id="display-name">{{ $support->name ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Client</label>
                            <p class="text-base text-gray-900" id="display-client">{{ $support->client->basicData->name_1 ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Type</label>
                            @if($support->type)
                                <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full bg-blue-100 text-blue-800" id="display-type">
                                    {{ $support->type }}
                                </span>
                            @else
                                <p class="text-gray-400" id="display-type">No type set</p>
                            @endif
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Support Method</label>
                            <p class="text-base text-gray-900" id="display-support_method">{{ $support->support_method ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Start Date</label>
                            <p class="text-base text-gray-900" id="display-start_date">{{ $support->start_date ? $support->start_date->format('d F Y') : 'N/A' }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">End Date</label>
                            <p class="text-base text-gray-900" id="display-end_date">{{ $support->end_date ? $support->end_date->format('d F Y') : 'N/A' }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Resolution Estimated</label>
                            <p class="text-base text-gray-900" id="display-resolution_estimated">{{ $support->resolution_estimated ? $support->resolution_estimated->format('d F Y') : 'N/A' }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Total Mandays</label>
                            <p class="text-base text-gray-900" id="display-total_mandays">{{ $support->total_mandays ?? '0' }} days</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Service Window</label>
                            <p class="text-base text-gray-900" id="display-service_window">
                                @if($support->service_window_start && $support->service_window_end)
                                    {{ \Illuminate\Support\Str::substr($support->service_window_start, 0, 5) }} – {{ \Illuminate\Support\Str::substr($support->service_window_end, 0, 5) }}
                                @else
                                    N/A
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Approval Information --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-900">Approval Information</h3>
                    <button type="button" onclick="openEditModal('approval-info')"
                            class="p-2 text-gray-400 edit-btn rounded-lg transition"
                            title="Edit Approval Information">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                    </button>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Approval Date</label>
                            <p class="text-base text-gray-900" id="display-approval_date">{{ $support->approval_date ? $support->approval_date->format('d F Y') : 'Not approved yet' }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Approved By</label>
                            <p class="text-base text-gray-900" id="display-approval_name">{{ $support->approval_name ?? 'N/A' }}</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Activities Summary --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-900">Activities</h3>
                    <a href="{{ route('delivery.support.planning.index', $support->id) }}" class="text-sm primary-link">
                        View all <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
                <div class="p-6">
                    @if($support->activities && $support->activities->count() > 0)
                        <div class="space-y-3">
                            @foreach($support->activities->take(5) as $activity)
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-2 h-2 rounded-full {{ $activity->status === 'completed' ? 'bg-green-500' : ($activity->status === 'in_progress' ? 'bg-blue-500' : 'bg-gray-300') }}"></div>
                                        <span class="text-sm font-medium text-gray-900">{{ $activity->name }}</span>
                                    </div>
                                    <div class="flex items-center space-x-3">
                                        <div class="w-16 bg-gray-200 rounded-full h-1.5">
                                            <div class="h-1.5 rounded-full bg-blue-500" style="width: {{ $activity->progress_percentage }}%;"></div>
                                        </div>
                                        <span class="text-xs text-gray-500">{{ number_format($activity->progress_percentage, 0) }}%</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @if($support->activities->count() > 5)
                            <p class="mt-3 text-sm text-gray-500 text-center">
                                + {{ $support->activities->count() - 5 }} more activities
                            </p>
                        @endif
                    @else
                        <div class="text-center py-6">
                            <p class="text-gray-500">No activities yet</p>
                            <a href="{{ route('delivery.support.planning.index', $support->id) }}" class="inline-flex items-center mt-2 text-sm primary-link">
                                <i class="fas fa-plus mr-1"></i>
                                Add activities
                            </a>
                        </div>
                    @endif
                </div>
            </div>
            {{-- SLA Configuration --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
                    <div class="flex items-center gap-3">
                        <div class="w-7 h-7 rounded-lg bg-red-50 flex items-center justify-center">
                            <i class="fas fa-stopwatch text-red-600 text-xs"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">SLA Configuration</h3>
                            <p class="text-xs text-gray-400">Response &amp; resolution targets untuk delivery ini</p>
                        </div>
                    </div>
                    @if($canManage)
                    <button onclick="openSlaAddModal()"
                        class="inline-flex items-center gap-1.5 bg-red-700 hover:bg-red-800 text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition">
                        <i class="fas fa-plus text-xs"></i> Add Policy
                    </button>
                    @endif
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200">
                                <th class="text-left px-4 py-2.5 text-[11px] font-bold text-gray-500 uppercase tracking-wider">Priority</th>
                                <th class="text-left px-4 py-2.5 text-[11px] font-bold text-gray-500 uppercase tracking-wider">Scale</th>
                                <th class="text-center px-4 py-2.5 text-[11px] font-bold text-gray-500 uppercase tracking-wider whitespace-nowrap">Response (h)</th>
                                <th class="text-center px-4 py-2.5 text-[11px] font-bold text-gray-500 uppercase tracking-wider whitespace-nowrap">Resolution (h)</th>
                                <th class="text-center px-4 py-2.5 text-[11px] font-bold text-gray-500 uppercase tracking-wider">Mode</th>
                                <th class="text-center px-4 py-2.5 text-[11px] font-bold text-gray-500 uppercase tracking-wider">Status</th>
                                @if($canManage)
                                <th class="text-center px-4 py-2.5 text-[11px] font-bold text-gray-500 uppercase tracking-wider">Actions</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody id="slaPolicyTableBody">
                            <tr>
                                <td colspan="{{ $canManage ? 7 : 6 }}" class="py-10 text-center">
                                    <div class="flex flex-col items-center gap-2 text-gray-300">
                                        <i class="fas fa-spinner fa-spin text-2xl"></i>
                                        <p class="text-xs">Loading policies...</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            {{-- Progress Card --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Overall Progress</h3>
                </div>
                <div class="p-6">
                    @php
                        $progress = $support->calculated_progress ?? 0;
                        $progressColor = $progress >= 100 ? '#10b981' : ($progress > 50 ? '#3b82f6' : ($progress > 0 ? '#f59e0b' : '#9ca3af'));
                    @endphp
                    <div class="flex flex-col items-center">
                        <div class="relative w-32 h-32 mb-4">
                            <svg class="w-32 h-32 transform -rotate-90" viewBox="0 0 100 100">
                                <circle cx="50" cy="50" r="45" fill="transparent" stroke="#e5e7eb" stroke-width="8"/>
                                <circle cx="50" cy="50" r="45" fill="transparent"
                                        stroke="{{ $progressColor }}"
                                        stroke-width="8"
                                        stroke-dasharray="{{ 283 * $progress / 100 }} 283"
                                        stroke-linecap="round"/>
                            </svg>
                            <div class="absolute inset-0 flex items-center justify-center">
                                <span class="text-3xl font-bold" style="color: {{ $progressColor }}">{{ number_format($progress, 0) }}%</span>
                            </div>
                        </div>
                        <p class="text-sm text-gray-500">
                            @if($progress >= 100)
                                Completed
                            @elseif($progress > 0)
                                In Progress
                            @else
                                Not Started
                            @endif
                        </p>
                    </div>
                </div>
            </div>

            {{-- Team Members --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-900">Team</h3>
                    <button type="button" onclick="openEditModal('team-info')"
                            class="p-2 text-gray-400 edit-btn rounded-lg transition"
                            title="Edit Team">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                    </button>
                </div>
                <div class="p-6">
                    <div class="space-y-3">
                        <div class="flex items-center space-x-3" id="team-delivery-owner">
                            <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center text-white text-sm font-bold">
                                DO
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">Delivery Owner</p>
                                <p class="text-xs text-gray-500" id="display-delivery_owner">{{ $support->deliveryOwner->basicData->full_name ?? 'Not assigned' }}</p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-3" id="team-support-manager">
                            <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center text-white text-sm font-bold">
                                SM
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">Support Manager</p>
                                <p class="text-xs text-gray-500" id="display-support_manager">{{ $support->supportManager->basicData->full_name ?? 'Not assigned' }}</p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-3" id="team-co-pm">
                            <div class="w-8 h-8 bg-purple-500 rounded-full flex items-center justify-center text-white text-sm font-bold">
                                CP
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">Co PM</p>
                                <p class="text-xs text-gray-500" id="display-co_pm">{{ $support->coPm->basicData->full_name ?? 'Not assigned' }}</p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-3" id="team-support-admin">
                            <div class="w-8 h-8 bg-amber-500 rounded-full flex items-center justify-center text-white text-sm font-bold">
                                SA
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">Support Admin</p>
                                <p class="text-xs text-gray-500" id="display-support_admin">{{ $support->supportAdmin->basicData->full_name ?? 'Not assigned' }}</p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-3" id="team-sales">
                            <div class="w-8 h-8 bg-pink-500 rounded-full flex items-center justify-center text-white text-sm font-bold">
                                SL
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">Sales</p>
                                <p class="text-xs text-gray-500" id="display-sales">{{ $support->sales->basicData->full_name ?? 'Not assigned' }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Customer PIC --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-900">Customer PIC</h3>
                    @if($can('delivery-support.manage-customer-pic'))
                    <button type="button" onclick="openCustomerPicModal()"
                            class="p-2 text-gray-400 edit-btn rounded-lg transition"
                            title="Manage Customer PIC">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                    </button>
                    @endif
                </div>
                <div class="p-6" id="customerPicPanel">
                    <div id="customerPicList" class="space-y-2">
                        <p class="text-xs text-gray-400 italic">Loading...</p>
                    </div>
                </div>
            </div>

            {{-- Quick Actions --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Quick Actions</h3>
                </div>
                <div class="p-4 space-y-2">
                    <a href="{{ route('delivery.support.planning.index', $support->id) }}"
                       class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 transition">
                        <span class="text-sm font-medium text-gray-700">View Planning</span>
                        <i class="fas fa-chevron-right text-gray-400"></i>
                    </a>
                    <button type="button" onclick="openDocumentsModal()"
                            class="w-full flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 transition text-left">
                        <span class="text-sm font-medium text-gray-700">Documents</span>
                        <span class="text-xs text-gray-500">{{ $support->documents->count() ?? 0 }}</span>
                    </button>
                    <button type="button" onclick="openUpdatesModal()"
                            class="w-full flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 transition text-left">
                        <span class="text-sm font-medium text-gray-700">Updates & Notes</span>
                        <span class="text-xs text-gray-500">{{ $support->updates->count() ?? 0 }}</span>
                    </button>
                    @if($isEcAdmin)
                    <div class="border-t border-gray-100 mt-1 pt-1">
                        <button type="button" onclick="openRemoveTicketModal()"
                                class="w-full flex items-center justify-between p-3 rounded-lg hover:bg-orange-50 transition text-left text-orange-600">
                            <span class="text-sm font-medium">Remove Ticket from DS</span>
                            <span class="flex items-center gap-1.5">
                                @if($linkedTickets->count() > 0)
                                <span class="text-xs bg-orange-100 text-orange-700 font-semibold px-1.5 py-0.5 rounded-full">{{ $linkedTickets->count() }}</span>
                                @endif
                                <i class="fas fa-unlink text-sm"></i>
                            </span>
                        </button>
                    </div>
                    @endif
                    <div class="border-t border-gray-100 mt-1 pt-1">
                        <form id="deleteSupportForm" action="{{ route('delivery.support.destroy', $support->id, false) }}" method="POST">
                            @csrf
                            @method('DELETE')
                            <button type="button" onclick="confirmDeleteSupport()"
                                    class="w-full flex items-center justify-between p-3 rounded-lg hover:bg-red-50 transition text-left text-red-600">
                                <span class="text-sm font-medium">Delete Support</span>
                                <i class="fas fa-trash text-sm"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ============================================================================ --}}
{{-- ONEDRIVE MODAL --}}
{{-- ============================================================================ --}}
{{-- CUSTOMER DELIVERABLE FOLDER MODAL --}}
{{-- ============================================================================ --}}
<div id="deliverableModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeDeliverableModal()"></div>
        <div class="relative bg-white rounded-xl shadow-2xl max-w-md w-full z-10 overflow-hidden">

            {{-- Header --}}
            <div class="flex items-center justify-between px-6 pt-5 pb-4 border-b border-gray-100">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>
                    </svg>
                    <h3 class="text-base font-semibold text-gray-900">Customer Deliverable Folder</h3>
                </div>
                <button onclick="closeDeliverableModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Body --}}
            <div class="px-6 py-5 space-y-4">
                {{-- Info path preview --}}
                <div class="bg-gray-50 rounded-lg px-4 py-3 text-xs text-gray-500 font-mono leading-relaxed">
                    <span class="text-gray-400">Delivery Support / Customer Deliverable /</span><br>
                    <span class="text-emerald-700 font-semibold" id="dlvCustomerFolderPreview">
                        {{ str_pad($support->client_id, 3, '0', STR_PAD_LEFT) }} {{ strtoupper($support->client->basicData->name_1 ?? 'CUSTOMER') }}
                    </span><span class="text-gray-400"> /</span><br>
                    <span class="text-amber-700 font-semibold" id="dlvSupportFolderPreview">
                        {{ $support->name }}
                    </span><span class="text-gray-400"> /</span><br>
                    <span class="text-blue-700 font-semibold" id="dlvSubfolderPreview">subfolder name...</span>
                </div>

                {{-- Subfolder name input --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Sub-Folder Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="dlvSubfolderName"
                           placeholder="e.g. CONTRACT#001 ATS 2026"
                           class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                           oninput="document.getElementById('dlvSubfolderPreview').textContent = this.value || 'subfolder name...'">
                    <p class="text-xs text-gray-400 mt-1">Cannot contain: \ / : * ? " &lt; &gt; |</p>
                </div>

                @if($support->onedrive_deliverable_folder_url)
                <div class="bg-emerald-50 rounded-lg p-3 text-xs text-emerald-700">
                    <span class="font-semibold">This support folder already exists.</span>
                    Each new sub-folder is added inside it (existing ones are kept).
                </div>
                @endif
            </div>

            {{-- Footer --}}
            <div class="flex gap-2 px-6 pb-5">
                <button onclick="generateDeliverableFolder()" id="dlvGenerateBtn"
                    class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-emerald-600 text-white text-sm font-semibold rounded-lg hover:bg-emerald-700 active:bg-emerald-800 transition-all disabled:opacity-60 disabled:cursor-not-allowed">
                    <svg id="dlvGenerateIcon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>
                    </svg>
                    <svg id="dlvGenerateSpinner" class="hidden animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    <span id="dlvGenerateLabel">Generate Folder</span>
                </button>
                <button type="button" onclick="closeDeliverableModal()"
                    class="px-4 py-2.5 border border-gray-300 text-sm text-gray-700 rounded-lg hover:bg-gray-50 transition-all">
                    Cancel
                </button>
            </div>

        </div>
    </div>
</div>

{{-- ============================================================================ --}}
{{-- EDIT MODALS --}}
{{-- ============================================================================ --}}

{{-- Support Information Modal --}}
<div id="modal-support-info" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeEditModal('support-info')"></div>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle w-full sm:max-w-2xl">
            <form id="form-support-info" onsubmit="saveSection(event, 'support-info')">
                <div class="primary-gradient px-4 sm:px-6 py-3 sm:py-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-white">Edit Support Information</h3>
                        <button type="button" onclick="closeEditModal('support-info')" class="text-white hover:text-gray-200">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="bg-white px-4 sm:px-6 py-4 sm:py-5 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Support Name <span class="text-red-500">*</span></label>
                        <input type="text" name="name" id="edit-name" required
                               value="{{ $support->name }}"
                               class="block w-full border-gray-300 rounded-md shadow-sm primary-focus text-sm">
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Client <span class="text-red-500">*</span></label>
                            @php
                                $currentClientLabel = '';
                                if ($support->client_id) {
                                    foreach($clients ?? [] as $_c) {
                                        if ($_c->customer_id == $support->client_id) {
                                            $currentClientLabel = $_c->basicData->name_1 ?? 'N/A';
                                            break;
                                        }
                                    }
                                }
                            @endphp
                            <div class="custom-dd relative" data-fixed="true">
                                <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm hover:border-gray-400 transition-all text-left">
                                    <span class="custom-dd-label {{ $currentClientLabel ? 'text-gray-700' : 'text-gray-500' }}">{{ $currentClientLabel ?: 'Select Client' }}</span>
                                    <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <input type="hidden" name="client_id" id="edit-client_id" value="{{ $support->client_id }}" required>
                                <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:400px;">
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">Select Client</button>
                                    @foreach($clients ?? [] as $client)
                                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="{{ $client->customer_id }}">{{ $client->basicData->name_1 ?? 'N/A' }}</button>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Support Method</label>
                            <select name="support_method" id="edit-support_method"
                                    class="block w-full border-gray-300 rounded-md shadow-sm primary-focus text-sm">
                                <option value="">Select method</option>
                                <option value="Remote" {{ $support->support_method == 'Remote' ? 'selected' : '' }}>Remote</option>
                                <option value="On-Site" {{ $support->support_method == 'On-Site' ? 'selected' : '' }}>On-Site</option>
                                <option value="Hybrid" {{ $support->support_method == 'Hybrid' ? 'selected' : '' }}>Hybrid</option>
                            </select>
                        </div>
                    </div>
                    @php $roleId = session('user.role.id'); @endphp
                    @if(in_array($roleId, \App\Enums\RoleId::TICKET_MANAGER_GROUP, true))
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                        <select name="type" id="edit-type"
                                class="block w-full border-gray-300 rounded-md shadow-sm primary-focus text-sm">
                            <option value="">No type set</option>
                            <option value="AMS" {{ $support->type == 'AMS' ? 'selected' : '' }}>AMS</option>
                            <option value="MO" {{ $support->type == 'MO' ? 'selected' : '' }}>MO</option>
                            <option value="ATS" {{ $support->type == 'ATS' ? 'selected' : '' }}>ATS</option>
                            <option value="CR" {{ $support->type == 'CR' ? 'selected' : '' }}>CR</option>
                            <option value="RISE" {{ $support->type == 'RISE' ? 'selected' : '' }}>RISE</option>
                            <option value="CLOUD" {{ $support->type == 'CLOUD' ? 'selected' : '' }}>CLOUD</option>
                            <option value="POSTPAID" {{ $support->type == 'POSTPAID' ? 'selected' : '' }}>POSTPAID</option>
                            <option value="Project" {{ $support->type == 'Project' ? 'selected' : '' }}>Project</option>
                            <option value="Internal" {{ $support->type == 'Internal' ? 'selected' : '' }}>Internal</option>
                        </select>
                    </div>
                    @endif
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                            <input type="date" name="start_date" id="edit-start_date"
                                   value="{{ $support->start_date?->format('Y-m-d') }}"
                                   class="block w-full border-gray-300 rounded-md shadow-sm primary-focus text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                            <input type="date" name="end_date" id="edit-end_date"
                                   value="{{ $support->end_date?->format('Y-m-d') }}"
                                   class="block w-full border-gray-300 rounded-md shadow-sm primary-focus text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Resolution Estimated</label>
                            <input type="date" name="resolution_estimated" id="edit-resolution_estimated"
                                   value="{{ $support->resolution_estimated?->format('Y-m-d') }}"
                                   class="block w-full border-gray-300 rounded-md shadow-sm primary-focus text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Total Mandays</label>
                        <input type="number" name="total_mandays" id="edit-total_mandays" min="0"
                               value="{{ $support->total_mandays }}"
                               class="block w-full border-gray-300 rounded-md shadow-sm primary-focus text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Service Window
                            <span class="ml-1 text-xs font-normal text-gray-400">(operational hours per day)</span>
                        </label>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Start Time</label>
                                <input type="time" name="service_window_start" id="edit-service_window_start"
                                       value="{{ $support->service_window_start ? \Illuminate\Support\Str::substr($support->service_window_start, 0, 5) : '' }}"
                                       class="block w-full border-gray-300 rounded-md shadow-sm primary-focus text-sm">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">End Time</label>
                                <input type="time" name="service_window_end" id="edit-service_window_end"
                                       value="{{ $support->service_window_end ? \Illuminate\Support\Str::substr($support->service_window_end, 0, 5) : '' }}"
                                       class="block w-full border-gray-300 rounded-md shadow-sm primary-focus text-sm">
                            </div>
                        </div>
                        <p id="edit-sw-error" class="mt-1 text-xs text-red-500 hidden">End time must be after start time.</p>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 sm:px-6 py-3 sm:py-4 flex flex-col-reverse sm:flex-row sm:justify-end gap-2 sm:gap-3">
                    <button type="button" onclick="closeEditModal('support-info')"
                            class="inline-flex items-center justify-center px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all duration-200">
                        Cancel
                    </button>
                    <button type="submit"
                            class="inline-flex items-center justify-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Approval Information Modal --}}
<div id="modal-approval-info" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeEditModal('approval-info')"></div>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle w-full sm:max-w-lg">
            <form id="form-approval-info" onsubmit="saveSection(event, 'approval-info')">
                <div class="primary-gradient px-4 sm:px-6 py-3 sm:py-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-white">Edit Approval Information</h3>
                        <button type="button" onclick="closeEditModal('approval-info')" class="text-white hover:text-gray-200">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="bg-white px-4 sm:px-6 py-4 sm:py-5 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Approval Date</label>
                        <input type="date" name="approval_date" id="edit-approval_date"
                               value="{{ $support->approval_date?->format('Y-m-d') }}"
                               class="block w-full border-gray-300 rounded-md shadow-sm primary-focus text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Approved By</label>
                        <input type="text" name="approval_name" id="edit-approval_name"
                               value="{{ $support->approval_name }}"
                               class="block w-full border-gray-300 rounded-md shadow-sm primary-focus text-sm"
                               placeholder="Enter approver name">
                    </div>
                </div>
                <div class="bg-gray-50 px-4 sm:px-6 py-3 sm:py-4 flex flex-col-reverse sm:flex-row sm:justify-end gap-2 sm:gap-3">
                    <button type="button" onclick="closeEditModal('approval-info')"
                            class="inline-flex items-center justify-center px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all duration-200">
                        Cancel
                    </button>
                    <button type="submit"
                            class="inline-flex items-center justify-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Team Information Modal --}}
<div id="modal-team-info" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeEditModal('team-info')"></div>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle w-full sm:max-w-lg">
            <form id="form-team-info" onsubmit="saveSection(event, 'team-info')">
                <div class="primary-gradient px-4 sm:px-6 py-3 sm:py-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-white">Edit Team Assignment</h3>
                        <button type="button" onclick="closeEditModal('team-info')" class="text-white hover:text-gray-200">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="bg-white px-4 sm:px-6 py-4 sm:py-5 space-y-4">
                    @php
                        $currentLabels = [
                            'delivery_owner_id'  => '',
                            'support_manager_id' => '',
                            'co_pm_id'           => '',
                            'support_admin_id'   => '',
                            'sales_id'           => '',
                        ];
                        foreach($employees ?? [] as $_e) {
                            $name = $_e->basicData->full_name ?? 'N/A';
                            if ($support->delivery_owner_id  == $_e->employee_id) $currentLabels['delivery_owner_id']  = $name;
                            if ($support->support_manager_id == $_e->employee_id) $currentLabels['support_manager_id'] = $name;
                            if ($support->co_pm_id           == $_e->employee_id) $currentLabels['co_pm_id']           = $name;
                            if ($support->support_admin_id   == $_e->employee_id) $currentLabels['support_admin_id']   = $name;
                            if ($support->sales_id           == $_e->employee_id) $currentLabels['sales_id']           = $name;
                        }

                        $teamFields = [
                            ['key' => 'delivery_owner_id',  'label' => 'Delivery Owner',  'value' => $support->delivery_owner_id],
                            ['key' => 'support_manager_id', 'label' => 'Support Manager', 'value' => $support->support_manager_id],
                            ['key' => 'co_pm_id',           'label' => 'Co PM',           'value' => $support->co_pm_id],
                            ['key' => 'support_admin_id',   'label' => 'Support Admin',   'value' => $support->support_admin_id],
                            ['key' => 'sales_id',           'label' => 'Sales',           'value' => $support->sales_id],
                        ];
                    @endphp

                    @foreach($teamFields as $tf)
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ $tf['label'] }}</label>
                        <div class="custom-dd relative" data-fixed="true">
                            <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm hover:border-gray-400 transition-all text-left">
                                <span class="custom-dd-label {{ $currentLabels[$tf['key']] ? 'text-gray-700' : 'text-gray-500' }}">{{ $currentLabels[$tf['key']] ?: 'Not assigned' }}</span>
                                <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <input type="hidden" name="{{ $tf['key'] }}" id="edit-{{ $tf['key'] }}" value="{{ $tf['value'] }}">
                            <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:400px;">
                                <div class="custom-dd-search-wrap sticky top-0 bg-white border-b border-gray-100 px-2 py-2" style="z-index:1">
                                    <input type="text" class="custom-dd-search w-full px-3 py-1.5 text-sm border border-gray-200 rounded-md focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400" placeholder="Search employee…" autocomplete="off" spellcheck="false">
                                </div>
                                <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">Not assigned</button>
                                @foreach($employees ?? [] as $employee)
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="{{ $employee->employee_id }}">{{ $employee->basicData->full_name ?? 'N/A' }}</button>
                                @endforeach
                                <div class="custom-dd-empty hidden px-4 py-3 text-sm text-gray-400 text-center">No results</div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                <div class="bg-gray-50 px-4 sm:px-6 py-3 sm:py-4 flex flex-col-reverse sm:flex-row sm:justify-end gap-2 sm:gap-3">
                    <button type="button" onclick="closeEditModal('team-info')"
                            class="inline-flex items-center justify-center px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all duration-200">
                        Cancel
                    </button>
                    <button type="submit"
                            class="inline-flex items-center justify-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const supportId = {{ $support->id }};
const csrfToken = '{{ csrf_token() }}';

// Employee data for updating display
const employeesData = @json($employees ?? []);
const clientsData = @json($clients ?? []);

// ============================================================================
// DELETE SUPPORT (uses global showConfirm modal — no browser confirm())
// ============================================================================
async function confirmDeleteSupport() {
    const ok = await showConfirm(
        'Are you sure you want to delete this support delivery? This cannot be undone.',
        'Delete Support Delivery',
        'danger',
        { okText: 'Delete' }
    );
    if (ok) document.getElementById('deleteSupportForm').submit();
}

// ============================================================================
// MODAL FUNCTIONS
// ============================================================================
function openEditModal(section) {
    document.getElementById(`modal-${section}`).classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeEditModal(section) {
    document.getElementById(`modal-${section}`).classList.add('hidden');
    document.body.style.overflow = 'auto';
}

// ============================================================================
// SAVE SECTION DATA
// ============================================================================
function saveSection(event, section) {
    event.preventDefault();

    const form = document.getElementById(`form-${section}`);
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    // Show loading
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<svg class="animate-spin h-5 w-5 mr-2 inline" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Saving...';
    submitBtn.disabled = true;

    fetch(`/delivery/support/${supportId}/field`, {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
        },
        body: JSON.stringify({ section: section, data: data })
    })
    .then(async response => {
        const json = await response.json();
        if (!response.ok) {
            throw json;
        }
        showNotification(json.message || 'Changes saved successfully', 'success');
        closeEditModal(section);
        updateDisplayValues(section, data);
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    })
    .catch(error => {
        console.error('Error saving:', error);
        showNotification(error.message || 'Failed to save changes', 'error');
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}

// ============================================================================
// UPDATE DISPLAY VALUES
// ============================================================================
function updateDisplayValues(section, data) {
    if (section === 'support-info') {
        if (data.name) document.getElementById('display-name').textContent = data.name;
        if (data.support_method) document.getElementById('display-support_method').textContent = data.support_method || 'N/A';
        if (data.total_mandays !== undefined) document.getElementById('display-total_mandays').textContent = (data.total_mandays || 0) + ' days';

        // Update dates
        if (data.start_date) {
            const startDate = new Date(data.start_date);
            document.getElementById('display-start_date').textContent = startDate.toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
        }
        if (data.end_date) {
            const endDate = new Date(data.end_date);
            document.getElementById('display-end_date').textContent = endDate.toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
        }
        if (data.resolution_estimated) {
            const resDate = new Date(data.resolution_estimated);
            document.getElementById('display-resolution_estimated').textContent = resDate.toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
        }

        // Update client
        if (data.client_id) {
            const client = clientsData.find(c => c.customer_id == data.client_id);
            if (client) {
                document.getElementById('display-client').textContent = client.basic_data?.name_1 || 'N/A';
            }
        }

        // Update type display
        const typeEl = document.getElementById('display-type');
        if (typeEl) {
            if (data.type) {
                typeEl.outerHTML = `<span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full bg-blue-100 text-blue-800" id="display-type">${data.type}</span>`;
            } else {
                typeEl.outerHTML = `<p class="text-gray-400" id="display-type">No type set</p>`;
            }
        }

        // Update service window display
        const swEl = document.getElementById('display-service_window');
        if (swEl) {
            const swStart = data.service_window_start || '';
            const swEnd   = data.service_window_end   || '';
            swEl.textContent = (swStart && swEnd) ? `${swStart} – ${swEnd}` : 'N/A';
        }
    }
    else if (section === 'approval-info') {
        if (data.approval_date) {
            const approvalDate = new Date(data.approval_date);
            document.getElementById('display-approval_date').textContent = approvalDate.toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
        } else {
            document.getElementById('display-approval_date').textContent = 'Not approved yet';
        }
        document.getElementById('display-approval_name').textContent = data.approval_name || 'N/A';
    }
    else if (section === 'team-info') {
        const teamMap = {
            delivery_owner_id:  'display-delivery_owner',
            support_manager_id: 'display-support_manager',
            co_pm_id:           'display-co_pm',
            support_admin_id:   'display-support_admin',
            sales_id:           'display-sales',
        };
        Object.entries(teamMap).forEach(([field, displayId]) => {
            const el = document.getElementById(displayId);
            if (!el) return;
            const empId = data[field];
            if (empId) {
                const emp = employeesData.find(e => e.employee_id == empId);
                el.textContent = emp?.basic_data?.full_name || 'Not assigned';
            } else {
                el.textContent = 'Not assigned';
            }
        });
    }
}

// ============================================================================
// OTHER MODALS (TO BE IMPLEMENTED)
// ============================================================================
function openDocumentsModal() {
    showNotification('Documents modal will be implemented', 'info');
}

function openUpdatesModal() {
    showNotification('Updates modal will be implemented', 'info');
}

// ============================================================================
// INITIALIZATION
// ============================================================================
document.addEventListener('DOMContentLoaded', function() {
    // Init custom-dd untuk semua dropdown (Client, Delivery Owner, Support Manager).
    // Auto-inject search input bila > 7 item (lihat custom-dropdown.js).
    if (typeof initCustomDropdowns === 'function') {
        initCustomDropdowns();
    }

    // Service window validation inside edit modal
    const editSwStart = document.getElementById('edit-service_window_start');
    const editSwEnd   = document.getElementById('edit-service_window_end');
    const editSwError = document.getElementById('edit-sw-error');

    function validateEditServiceWindow() {
        if (editSwStart.value && editSwEnd.value && editSwEnd.value < editSwStart.value) {
            editSwError.classList.remove('hidden');
            editSwEnd.setCustomValidity('End time must be after start time.');
        } else {
            editSwError.classList.add('hidden');
            editSwEnd.setCustomValidity('');
        }
    }
    editSwStart.addEventListener('change', validateEditServiceWindow);
    editSwEnd.addEventListener('change', validateEditServiceWindow);


    // Close modals on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('[id^="modal-"]').forEach(modal => {
                if (!modal.classList.contains('hidden')) {
                    const section = modal.id.replace('modal-', '');
                    closeEditModal(section);
                }
            });
        }
    });
});

// =========================================================================
// CUSTOMER DELIVERABLE FOLDER — DROPDOWN
// =========================================================================

let _dlvDropdownOpen   = false;
let _dlvSubfoldersLoaded = false;

function toggleDeliverableDropdown() {
    if (_dlvDropdownOpen) {
        closeDeliverableDropdown();
    } else {
        openDeliverableDropdown();
    }
}

function openDeliverableDropdown() {
    _dlvDropdownOpen = true;
    document.getElementById('dlvDropdownMenu').classList.remove('hidden');
    document.getElementById('dlvDropdownChevron').style.transform = 'rotate(180deg)';
    if (!_dlvSubfoldersLoaded) {
        fetchDeliverableSubfolders();
    }
}

function closeDeliverableDropdown() {
    _dlvDropdownOpen = false;
    document.getElementById('dlvDropdownMenu').classList.add('hidden');
    document.getElementById('dlvDropdownChevron').style.transform = 'rotate(0deg)';
}

async function fetchDeliverableSubfolders() {
    // Show loading, hide others
    document.getElementById('dlvDdLoading').classList.remove('hidden');
    document.getElementById('dlvDdContent').classList.add('hidden');
    document.getElementById('dlvDdError').classList.add('hidden');

    try {
        const res  = await fetch('{{ route('delivery.support.deliverable-subfolders', $support->id, false) }}', {
            headers: { 'Accept': 'application/json' },
        });
        const data = await res.json();

        if (!res.ok || data.error) {
            throw new Error(data.error || 'Server error');
        }

        // Populate customer name
        document.getElementById('dlvDdCustomerName').textContent = data.customer_folder ?? '';

        // Populate list
        const list = document.getElementById('dlvDdList');
        const empty = document.getElementById('dlvDdEmpty');
        list.innerHTML = '';

        if (data.subfolders && data.subfolders.length > 0) {
            empty.classList.add('hidden');
            data.subfolders.forEach(folder => {
                const row = document.createElement('div');
                row.className = 'flex items-center gap-1 px-3 py-2 hover:bg-emerald-50 group cursor-pointer';
                row.title = 'Open folder in OneDrive';
                row.onclick = () => openDeliverableLink(folder.id, row);
                row.innerHTML = `
                    <svg class="w-4 h-4 flex-shrink-0 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>
                    </svg>
                    <span class="flex-1 min-w-0 text-sm text-gray-700 truncate px-1">${escapeHtml(folder.name)}</span>
                    <svg class="dlv-open-icon w-3.5 h-3.5 flex-shrink-0 text-gray-400 group-hover:text-emerald-600 opacity-0 group-hover:opacity-100 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                    </svg>
                    <svg class="dlv-spin-icon hidden animate-spin w-3.5 h-3.5 flex-shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>`;
                list.appendChild(row);
            });
        } else {
            empty.classList.remove('hidden');
        }

        _dlvSubfoldersLoaded = true;
        document.getElementById('dlvDdLoading').classList.add('hidden');
        document.getElementById('dlvDdContent').classList.remove('hidden');

    } catch (err) {
        document.getElementById('dlvDdLoading').classList.add('hidden');
        document.getElementById('dlvDdErrorMsg').textContent = 'Failed to load: ' + err.message;
        document.getElementById('dlvDdError').classList.remove('hidden');
    }
}

function escapeHtml(str) {
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

async function openDeliverableLink(folderId, row) {
    const openIcon = row.querySelector('.dlv-open-icon');
    const spinIcon = row.querySelector('.dlv-spin-icon');

    // Buka tab kosong dulu di dalam gesture klik agar tidak diblok popup blocker,
    // lalu arahkan ke URL share setelah fetch selesai.
    const tab = window.open('', '_blank');

    openIcon.classList.add('hidden');
    spinIcon.classList.remove('hidden');

    try {
        const res  = await fetch('{{ route('delivery.support.deliverable-share-link', $support->id, false) }}', {
            method:  'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept':       'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ folder_id: folderId }),
        });
        const data = await res.json();

        if (data.success && data.url) {
            if (tab) { tab.location.href = data.url; }
            else { window.open(data.url, '_blank', 'noopener'); }
        } else {
            throw new Error(data.message || 'Failed to get link');
        }
    } catch (err) {
        if (tab) { tab.close(); }
        showToast('Failed to open folder: ' + err.message, 'error');
    } finally {
        spinIcon.classList.add('hidden');
        openIcon.classList.remove('hidden');
    }
}

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    const container = document.getElementById('dlvDropdownContainer');
    if (container && !container.contains(e.target)) {
        closeDeliverableDropdown();
    }
});

// ── Deliverable Modal ──────────────────────────────────────────────────────

function openDeliverableModal() {
    document.getElementById('deliverableModal').classList.remove('hidden');
    const input = document.getElementById('dlvSubfolderName');
    input.value = '';
    document.getElementById('dlvSubfolderPreview').textContent = 'subfolder name...';
    setTimeout(() => input.focus(), 100);
}

function closeDeliverableModal() {
    document.getElementById('deliverableModal').classList.add('hidden');
}

async function generateDeliverableFolder() {
    const name = document.getElementById('dlvSubfolderName').value.trim();
    if (!name) {
        showToast('Sub-folder name is required.', 'error');
        document.getElementById('dlvSubfolderName').focus();
        return;
    }

    const btn     = document.getElementById('dlvGenerateBtn');
    const icon    = document.getElementById('dlvGenerateIcon');
    const spinner = document.getElementById('dlvGenerateSpinner');
    const label   = document.getElementById('dlvGenerateLabel');

    btn.disabled = true;
    icon.classList.add('hidden');
    spinner.classList.remove('hidden');
    label.textContent = 'Generating…';

    try {
        const res  = await fetch('{{ route('delivery.support.generate-deliverable-folder', $support->id, false) }}', {
            method:  'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept':       'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ subfolder_name: name }),
        });
        const data = await res.json();

        if (data.success) {
            closeDeliverableModal();
            showToast('Customer deliverable folder created!', 'success');
            // Force reload sub-folder list on next dropdown open
            _dlvSubfoldersLoaded = false;
        } else {
            showToast(data.message || 'Failed to generate deliverable folder.', 'error');
        }
    } catch (err) {
        showToast('Error: ' + err.message, 'error');
    } finally {
        btn.disabled = false;
        icon.classList.remove('hidden');
        spinner.classList.add('hidden');
        label.textContent = 'Generate Folder';
    }
}

// ── SLA Policies (scoped to this delivery support) ───────────────────────────

const SLA_DS_ID    = {{ $support->id }};
const SLA_CAN_MGMT = {{ $canManage ? 'true' : 'false' }};
const SLA_COL      = SLA_CAN_MGMT ? 7 : 6;

const SLA_PRIO_NUM   = { 'Very High': 1, 'High': 2, 'Medium': 3, 'Low': 4 };
const SLA_PRIO_LABEL = { 'Very High': '1: Very High', 'High': '2: High', 'Medium': '3: Medium', 'Low': '4: Low' };
const SLA_PRIO_CFG   = {
    'Very High': { bg: 'bg-red-50',    text: 'text-red-700',    dot: 'bg-red-500'    },
    'High':      { bg: 'bg-orange-50', text: 'text-orange-700', dot: 'bg-orange-500' },
    'Medium':    { bg: 'bg-yellow-50', text: 'text-yellow-700', dot: 'bg-yellow-500' },
    'Low':       { bg: 'bg-blue-50',   text: 'text-blue-700',   dot: 'bg-blue-400'   },
};

let _slaPolicies = [];

async function loadSlaPolicies() {
    const tbody = document.getElementById('slaPolicyTableBody');
    tbody.innerHTML = `<tr><td colspan="${SLA_COL}" class="py-10 text-center">
        <div class="flex flex-col items-center gap-2 text-gray-300">
            <i class="fas fa-spinner fa-spin text-2xl"></i>
            <p class="text-xs">Loading...</p>
        </div></td></tr>`;
    try {
        const res  = await fetch(`/api/admin/sla/policies?delivery_support_id=${SLA_DS_ID}`, { credentials: 'include' });
        const json = await res.json();
        _slaPolicies = json.data || [];
        renderSlaPolicies(_slaPolicies);
    } catch {
        tbody.innerHTML = `<tr><td colspan="${SLA_COL}" class="py-8 text-center text-red-400 text-xs">
            <i class="fas fa-exclamation-triangle mr-1"></i>Gagal memuat SLA policies.</td></tr>`;
    }
}

function renderSlaPolicies(policies) {
    const tbody = document.getElementById('slaPolicyTableBody');
    if (!policies.length) {
        tbody.innerHTML = `<tr><td colspan="${SLA_COL}" class="py-10 text-center">
            <div class="flex flex-col items-center gap-2">
                <i class="fas fa-file-contract text-gray-300 text-2xl mb-1"></i>
                <p class="text-sm font-medium text-gray-400">Belum ada SLA policy</p>
                ${SLA_CAN_MGMT ? `<p class="text-xs text-gray-300">Klik "Add Policy" untuk membuat policy pertama</p>` : ''}
            </div></td></tr>`;
        return;
    }
    tbody.innerHTML = policies.map(p => {
        const pc  = SLA_PRIO_CFG[p.priority] || { bg:'bg-gray-100', text:'text-gray-600', dot:'bg-gray-400' };
        const modeCell = p.is_24_hours
            ? `<span class="inline-flex items-center gap-1 text-xs font-semibold text-blue-700 bg-blue-50 px-2 py-0.5 rounded-full"><i class="fas fa-infinity text-[9px]"></i> 24/7</span>`
            : `<span class="text-xs font-medium text-gray-500 bg-gray-100 px-2 py-0.5 rounded-full">Business</span>`;
        const statusCell = p.is_active
            ? `<span class="inline-flex items-center gap-1.5 text-xs font-semibold text-green-700 bg-green-50 px-2.5 py-0.5 rounded-full"><span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>Active</span>`
            : `<span class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-400 bg-gray-100 px-2.5 py-0.5 rounded-full"><span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>Inactive</span>`;
        const actions = SLA_CAN_MGMT
            ? `<div class="flex items-center justify-center gap-1.5 opacity-0 group-hover:opacity-100 transition-opacity">
                <button onclick='openSlaEditModal(${JSON.stringify(p)})' title="Edit"
                    class="w-7 h-7 rounded-lg border border-gray-200 hover:border-blue-300 hover:bg-blue-50 flex items-center justify-center text-gray-400 hover:text-blue-600 transition">
                    <i class="fas fa-edit text-xs"></i>
                </button>
                <button onclick="deleteSlaPolicy(${p.id})" title="Delete"
                    class="w-7 h-7 rounded-lg border border-gray-200 hover:border-red-300 hover:bg-red-50 flex items-center justify-center text-gray-400 hover:text-red-500 transition">
                    <i class="fas fa-trash text-xs"></i>
                </button>
               </div>` : '';
        return `
        <tr class="bg-white border-b border-gray-100 hover:bg-red-50/20 transition-colors group">
            <td class="px-4 py-3">
                <span class="inline-flex items-center gap-1.5 text-xs font-semibold ${pc.text} ${pc.bg} px-2.5 py-1 rounded-full whitespace-nowrap">
                    <span class="w-1.5 h-1.5 rounded-full ${pc.dot}"></span>${SLA_PRIO_LABEL[p.priority] || p.priority}
                </span>
            </td>
            <td class="px-4 py-3"><span class="text-xs font-medium text-gray-600 bg-gray-100 px-2 py-0.5 rounded">${p.scale}</span></td>
            <td class="px-4 py-3 text-center"><span class="text-sm font-bold text-gray-800">${p.response_hours}</span></td>
            <td class="px-4 py-3 text-center"><span class="text-sm font-bold text-gray-800">${p.resolution_hours}</span></td>
            <td class="px-4 py-3 text-center">${modeCell}</td>
            <td class="px-4 py-3 text-center">${statusCell}</td>
            ${SLA_CAN_MGMT ? `<td class="px-4 py-3 text-center">${actions}</td>` : ''}
        </tr>`;
    }).join('');
}

// ── SLA Add Modal ─────────────────────────────────────────────────────────────
function openSlaAddModal() {
    document.getElementById('slaAddForm').reset();
    document.getElementById('slaAddError').classList.add('hidden');
    slaUpdateAdd24h();
    document.getElementById('slaAddModal').classList.remove('hidden');
}
function closeSlaAddModal() {
    document.getElementById('slaAddModal').classList.add('hidden');
}
function slaUpdateAdd24h() {
    const prio   = document.getElementById('slaAddPriority').value;
    const el     = document.getElementById('slaAdd24h');
    const label  = document.getElementById('slaAdd24hLabel');
    const note   = document.getElementById('slaAdd24hNote');
    if (prio === 'Very High') {
        el.checked = true; el.disabled = true;
        label.classList.add('opacity-75','cursor-not-allowed');
        label.classList.remove('cursor-pointer','hover:bg-gray-100');
        note.textContent = 'Wajib 24/7 — priority Very High selalu menggunakan kalender penuh.';
    } else {
        el.disabled = false;
        label.classList.remove('opacity-75','cursor-not-allowed');
        label.classList.add('cursor-pointer','hover:bg-gray-100');
        note.textContent = 'Hitung semua jam; jika tidak, hanya jam kerja (Sen–Jum 09:00–18:00)';
    }
}
async function submitSlaAddPolicy(e) {
    e.preventDefault();
    const form   = document.getElementById('slaAddForm');
    const btn    = document.getElementById('slaAddSubmitBtn');
    const errDiv = document.getElementById('slaAddError');
    const errTxt = document.getElementById('slaAddErrorText');
    errDiv.classList.add('hidden');
    btn.disabled = true;
    const fd = new FormData(form);
    fd.set('delivery_support_id', SLA_DS_ID);
    const payload = Object.fromEntries(fd.entries());
    payload.is_24_hours = form.querySelector('[name=is_24_hours]').checked;
    try {
        const res  = await fetch('/api/admin/sla/policies', {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
            body: JSON.stringify(payload),
        });
        const json = await res.json();
        if (json.success) {
            closeSlaAddModal();
            showToast('SLA policy berhasil ditambahkan!', 'success');
            loadSlaPolicies();
        } else {
            errTxt.textContent = json.message || 'Gagal menyimpan policy.';
            errDiv.classList.remove('hidden');
        }
    } catch {
        errTxt.textContent = 'Terjadi kesalahan. Coba lagi.';
        errDiv.classList.remove('hidden');
    } finally {
        btn.disabled = false;
    }
}

// ── SLA Edit Modal ────────────────────────────────────────────────────────────
function openSlaEditModal(p) {
    document.getElementById('slaEditId').value         = p.id;
    document.getElementById('slaEditPriority').value   = p.priority;
    document.getElementById('slaEditScale').value      = p.scale;
    document.getElementById('slaEditResponse').value   = p.response_hours;
    document.getElementById('slaEditResolution').value = p.resolution_hours;
    document.getElementById('slaEdit24h').checked      = p.is_24_hours;
    document.getElementById('slaEditActive').checked   = p.is_active;
    document.getElementById('slaEditError').classList.add('hidden');
    slaUpdateEdit24h();
    document.getElementById('slaEditModal').classList.remove('hidden');
}
function closeSlaEditModal() {
    document.getElementById('slaEditModal').classList.add('hidden');
}
function slaUpdateEdit24h() {
    const prio  = document.getElementById('slaEditPriority').value;
    const el    = document.getElementById('slaEdit24h');
    const label = document.getElementById('slaEdit24hLabel');
    const note  = document.getElementById('slaEdit24hNote');
    if (prio === 'Very High') {
        el.checked = true; el.disabled = true;
        label.classList.add('opacity-75','cursor-not-allowed');
        label.classList.remove('cursor-pointer','hover:bg-gray-100');
        note.textContent = 'Wajib 24/7 — priority Very High selalu menggunakan kalender penuh.';
    } else {
        el.disabled = false;
        label.classList.remove('opacity-75','cursor-not-allowed');
        label.classList.add('cursor-pointer','hover:bg-gray-100');
        note.textContent = 'Hitung semua jam; jika tidak, hanya jam kerja (Sen–Jum 09:00–18:00)';
    }
}
async function submitSlaEditPolicy(e) {
    e.preventDefault();
    const id     = document.getElementById('slaEditId').value;
    const btn    = document.getElementById('slaEditSubmitBtn');
    const errDiv = document.getElementById('slaEditError');
    const errTxt = document.getElementById('slaEditErrorText');
    errDiv.classList.add('hidden');
    btn.disabled = true;
    const payload = {
        priority:         document.getElementById('slaEditPriority').value,
        scale:            document.getElementById('slaEditScale').value,
        response_hours:   document.getElementById('slaEditResponse').value,
        resolution_hours: document.getElementById('slaEditResolution').value,
        is_24_hours:      document.getElementById('slaEdit24h').checked,
        is_active:        document.getElementById('slaEditActive').checked,
        delivery_support_id: SLA_DS_ID,
    };
    try {
        const res  = await fetch(`/api/admin/sla/policies/${id}`, {
            method: 'PUT',
            credentials: 'include',
            headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
            body: JSON.stringify(payload),
        });
        const json = await res.json();
        if (json.success) {
            closeSlaEditModal();
            showToast('SLA policy berhasil diperbarui!', 'success');
            loadSlaPolicies();
        } else {
            errTxt.textContent = json.message || 'Gagal memperbarui policy.';
            errDiv.classList.remove('hidden');
        }
    } catch {
        errTxt.textContent = 'Terjadi kesalahan. Coba lagi.';
        errDiv.classList.remove('hidden');
    } finally {
        btn.disabled = false;
    }
}

async function deleteSlaPolicy(id) {
    if (!confirm('Hapus SLA policy ini?')) return;
    try {
        const res  = await fetch(`/api/admin/sla/policies/${id}`, {
            method: 'DELETE',
            credentials: 'include',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
        });
        const json = await res.json();
        if (json.success) {
            showToast('SLA policy dihapus.', 'success');
            loadSlaPolicies();
        } else {
            showToast(json.message || 'Gagal menghapus policy.', 'error');
        }
    } catch {
        showToast('Terjadi kesalahan.', 'error');
    }
}

// Auto-load on page ready
document.addEventListener('DOMContentLoaded', loadSlaPolicies);

</script>

{{-- ── SLA Add Modal ──────────────────────────────────────────────────────── --}}
<div id="slaAddModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-gray-900/40 backdrop-blur-sm" onclick="closeSlaAddModal()"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-red-50 flex items-center justify-center">
                        <i class="fas fa-plus text-red-600 text-xs"></i>
                    </div>
                    <h3 class="text-sm font-semibold text-gray-800">Add SLA Policy</h3>
                </div>
                <button onclick="closeSlaAddModal()" class="w-7 h-7 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times text-xs"></i>
                </button>
            </div>
            <form id="slaAddForm" onsubmit="submitSlaAddPolicy(event)" class="p-6 space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Priority <span class="text-red-500">*</span></label>
                        <select name="priority" id="slaAddPriority" required onchange="slaUpdateAdd24h()"
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm text-gray-700 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-300">
                            <option value="Very High">1: Very High</option>
                            <option value="High">2: High</option>
                            <option value="Medium" selected>3: Medium</option>
                            <option value="Low">4: Low</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Scale <span class="text-red-500">*</span></label>
                        <select name="scale" id="slaAddScale" required
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm text-gray-700 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-300">
                            <option value="Simple" selected>Simple</option>
                            <option value="Medium">Medium</option>
                            <option value="Complex">Complex</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Response (Jam) <span class="text-red-500">*</span></label>
                        <input type="number" name="response_hours" step="0.5" min="0.5" required placeholder="mis. 4"
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm text-gray-700 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-300">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Resolution (Jam) <span class="text-red-500">*</span></label>
                        <input type="number" name="resolution_hours" step="0.5" min="0.5" required placeholder="mis. 24"
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm text-gray-700 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-300">
                    </div>
                </div>
                <label id="slaAdd24hLabel" class="flex items-start gap-3 p-3 rounded-xl border border-gray-200 bg-gray-50/80 cursor-pointer hover:bg-gray-100 transition">
                    <input type="checkbox" name="is_24_hours" id="slaAdd24h" class="mt-0.5 w-4 h-4 rounded accent-red-700">
                    <div>
                        <p class="text-sm font-medium text-gray-700">24/7 Calendar Hours</p>
                        <p class="text-xs text-gray-400 mt-0.5" id="slaAdd24hNote">Hitung semua jam; jika tidak, hanya jam kerja (Sen–Jum 09:00–18:00)</p>
                    </div>
                </label>
                <div id="slaAddError" class="hidden items-center gap-2 bg-red-50 rounded-xl px-4 py-3 border border-red-100">
                    <i class="fas fa-exclamation-circle text-red-500 flex-shrink-0 text-xs"></i>
                    <span id="slaAddErrorText" class="text-xs text-red-600"></span>
                </div>
                <div class="flex gap-3 pt-1">
                    <button type="button" onclick="closeSlaAddModal()"
                        class="flex-1 border border-gray-200 rounded-xl py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-50 transition">Cancel</button>
                    <button type="submit" id="slaAddSubmitBtn"
                        class="flex-1 bg-red-700 hover:bg-red-800 text-white rounded-xl py-2.5 text-sm font-semibold transition">Save Policy</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ── SLA Edit Modal ─────────────────────────────────────────────────────── --}}
<div id="slaEditModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-gray-900/40 backdrop-blur-sm" onclick="closeSlaEditModal()"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center">
                        <i class="fas fa-edit text-blue-600 text-xs"></i>
                    </div>
                    <h3 class="text-sm font-semibold text-gray-800">Edit SLA Policy</h3>
                </div>
                <button onclick="closeSlaEditModal()" class="w-7 h-7 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times text-xs"></i>
                </button>
            </div>
            <form id="slaEditForm" onsubmit="submitSlaEditPolicy(event)" class="p-6 space-y-4">
                <input type="hidden" id="slaEditId">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Priority</label>
                        <select id="slaEditPriority" required onchange="slaUpdateEdit24h()"
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm text-gray-700 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-300">
                            <option value="Very High">1: Very High</option>
                            <option value="High">2: High</option>
                            <option value="Medium">3: Medium</option>
                            <option value="Low">4: Low</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Scale</label>
                        <select id="slaEditScale" required
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm text-gray-700 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-300">
                            <option value="Simple">Simple</option>
                            <option value="Medium">Medium</option>
                            <option value="Complex">Complex</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Response (Jam)</label>
                        <input type="number" id="slaEditResponse" step="0.5" min="0.5" required
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm text-gray-700 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-300">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Resolution (Jam)</label>
                        <input type="number" id="slaEditResolution" step="0.5" min="0.5" required
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm text-gray-700 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-300">
                    </div>
                </div>
                <label id="slaEdit24hLabel" class="flex items-start gap-3 p-3 rounded-xl border border-gray-200 bg-gray-50/80 cursor-pointer hover:bg-gray-100 transition">
                    <input type="checkbox" id="slaEdit24h" class="mt-0.5 w-4 h-4 rounded accent-blue-600">
                    <div>
                        <p class="text-sm font-medium text-gray-700">24/7 Calendar Hours</p>
                        <p class="text-xs text-gray-400 mt-0.5" id="slaEdit24hNote">Hitung semua jam; jika tidak, hanya jam kerja (Sen–Jum 09:00–18:00)</p>
                    </div>
                </label>
                <label class="flex items-start gap-3 p-3 rounded-xl border border-gray-200 bg-gray-50/80 cursor-pointer hover:bg-gray-100 transition">
                    <input type="checkbox" id="slaEditActive" class="mt-0.5 w-4 h-4 rounded accent-green-600">
                    <div>
                        <p class="text-sm font-medium text-gray-700">Active</p>
                        <p class="text-xs text-gray-400 mt-0.5">Policy aktif akan digunakan untuk penghitungan SLA tiket</p>
                    </div>
                </label>
                <div id="slaEditError" class="hidden items-center gap-2 bg-red-50 rounded-xl px-4 py-3 border border-red-100">
                    <i class="fas fa-exclamation-circle text-red-500 flex-shrink-0 text-xs"></i>
                    <span id="slaEditErrorText" class="text-xs text-red-600"></span>
                </div>
                <div class="flex gap-3 pt-1">
                    <button type="button" onclick="closeSlaEditModal()"
                        class="flex-1 border border-gray-200 rounded-xl py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-50 transition">Cancel</button>
                    <button type="submit" id="slaEditSubmitBtn"
                        class="flex-1 bg-blue-600 hover:bg-blue-700 text-white rounded-xl py-2.5 text-sm font-semibold transition">Update Policy</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Customer PIC Modal --}}
<div id="customerPicModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeCustomerPicModal()"></div>
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-lg z-10">
            <div class="flex items-center justify-between px-6 py-4 bg-gray-800 rounded-t-xl">
                <h3 class="text-lg font-semibold text-white">Manage Customer PIC</h3>
                <button type="button" onclick="closeCustomerPicModal()" class="text-white hover:text-gray-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="p-6 space-y-4">
                <p class="text-xs text-gray-500">Pilih customer contact yang menjadi PIC untuk delivery support ini. Customer tersebut hanya akan melihat ticket yang terhubung ke DS ini di JARVIES.</p>

                {{-- Search input --}}
                <div class="relative">
                    <input type="text" id="cpicSearchInput" placeholder="Cari nama atau email..."
                        class="w-full pl-9 pr-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-800"
                        oninput="filterCpicOptions()">
                    <svg class="absolute left-3 top-2.5 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>

                {{-- Contact list with checkboxes --}}
                <div id="cpicContactList" class="border border-gray-200 rounded-lg max-h-64 overflow-y-auto divide-y divide-gray-100">
                    <p class="text-xs text-gray-400 px-4 py-3 italic">Loading contacts...</p>
                </div>

                {{-- Selected count --}}
                <p class="text-xs text-gray-500">Dipilih: <span id="cpicSelectedCount" class="font-semibold text-gray-800">0</span> contact</p>
            </div>
            <div class="flex gap-3 px-6 pb-6">
                <button type="button" onclick="closeCustomerPicModal()"
                    class="flex-1 border border-gray-200 rounded-xl py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-50 transition">
                    Batal
                </button>
                <button type="button" id="cpicSaveBtn" onclick="saveCustomerPics()"
                    class="flex-1 bg-gray-800 hover:bg-gray-700 text-white rounded-xl py-2.5 text-sm font-semibold transition">
                    Simpan
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// ==================== CUSTOMER PIC ====================
const SUPPORT_ID = {{ $support->id }};
const CPIC_CONTACTS_URL = '{{ route("delivery.support.client-contacts", $support->id) }}';
const CPIC_SYNC_URL     = '{{ route("delivery.support.customer-pics.sync", $support->id) }}';
const CPIC_INDEX_URL    = '{{ route("delivery.support.customer-pics.index", $support->id) }}';
const CSRF_TOKEN        = '{{ csrf_token() }}';

let cpicAllContacts  = [];  // semua contact milik client
let cpicSelectedIds  = new Set();  // contact_id yang dipilih

// ── Panel render ─────────────────────────────────────────────────────────────

async function loadCustomerPicPanel() {
    try {
        const res  = await fetch(CPIC_INDEX_URL);
        const json = await res.json();
        renderCustomerPicPanel(json.data ?? []);
    } catch (e) {
        document.getElementById('customerPicList').innerHTML =
            '<p class="text-xs text-red-400 italic">Gagal memuat data.</p>';
    }
}

function renderCustomerPicPanel(pics) {
    const el = document.getElementById('customerPicList');
    if (!pics.length) {
        el.innerHTML = '<p class="text-xs text-gray-400 italic">Belum ada Customer PIC yang ditentukan.</p>';
        return;
    }
    el.innerHTML = pics.map(p => `
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 text-xs font-bold flex-shrink-0">
                ${(p.full_name || '?').charAt(0).toUpperCase()}
            </div>
            <div class="min-w-0">
                <p class="text-sm font-medium text-gray-900 truncate">${escHtml(p.full_name ?? '—')}</p>
                <p class="text-xs text-gray-400 truncate">${escHtml(p.email_work ?? '')}${p.position ? ' · ' + escHtml(p.position) : ''}</p>
            </div>
        </div>
    `).join('');
}

// ── Modal ────────────────────────────────────────────────────────────────────

async function openCustomerPicModal() {
    document.getElementById('customerPicModal').classList.remove('hidden');
    document.getElementById('cpicSearchInput').value = '';
    document.getElementById('cpicContactList').innerHTML =
        '<p class="text-xs text-gray-400 px-4 py-3 italic">Loading contacts...</p>';

    try {
        const [contactsRes, picsRes] = await Promise.all([
            fetch(CPIC_CONTACTS_URL),
            fetch(CPIC_INDEX_URL),
        ]);
        const contactsJson = await contactsRes.json();
        const picsJson     = await picsRes.json();

        cpicAllContacts = contactsJson.data ?? [];
        cpicSelectedIds = new Set((picsJson.data ?? []).map(p => p.contact_id));

        renderCpicList(cpicAllContacts);
        updateCpicCount();
    } catch (e) {
        document.getElementById('cpicContactList').innerHTML =
            '<p class="text-xs text-red-400 px-4 py-3 italic">Gagal memuat data contact.</p>';
    }
}

function closeCustomerPicModal() {
    document.getElementById('customerPicModal').classList.add('hidden');
}

function renderCpicList(contacts) {
    const el = document.getElementById('cpicContactList');
    if (!contacts.length) {
        el.innerHTML = '<p class="text-xs text-gray-400 px-4 py-3 italic">Tidak ada contact ditemukan.</p>';
        return;
    }
    el.innerHTML = contacts.map(c => {
        const checked = cpicSelectedIds.has(c.contact_id) ? 'checked' : '';
        return `
        <label class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 cursor-pointer">
            <input type="checkbox" value="${c.contact_id}" ${checked}
                class="cpic-checkbox w-4 h-4 rounded border-gray-300 text-gray-800 focus:ring-gray-700"
                onchange="toggleCpic(${c.contact_id}, this.checked)">
            <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-gray-900">${escHtml(c.full_name ?? '—')}</p>
                <p class="text-xs text-gray-400">${escHtml(c.email_work ?? '')}${c.position ? ' · ' + escHtml(c.position) : ''}</p>
            </div>
        </label>`;
    }).join('');
}

function toggleCpic(contactId, checked) {
    if (checked) cpicSelectedIds.add(contactId);
    else         cpicSelectedIds.delete(contactId);
    updateCpicCount();
}

function filterCpicOptions() {
    const q = document.getElementById('cpicSearchInput').value.trim().toLowerCase();
    const filtered = q
        ? cpicAllContacts.filter(c =>
            (c.full_name ?? '').toLowerCase().includes(q) ||
            (c.email_work ?? '').toLowerCase().includes(q))
        : cpicAllContacts;
    renderCpicList(filtered);
}

function updateCpicCount() {
    document.getElementById('cpicSelectedCount').textContent = cpicSelectedIds.size;
}

async function saveCustomerPics() {
    const btn = document.getElementById('cpicSaveBtn');
    btn.disabled = true;
    btn.textContent = 'Menyimpan...';

    try {
        const res = await fetch(CPIC_SYNC_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
            credentials: 'same-origin',
            body: JSON.stringify({ contact_ids: [...cpicSelectedIds] }),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message ?? 'Gagal menyimpan');

        renderCustomerPicPanel(json.data ?? []);
        closeCustomerPicModal();
        showToast('Customer PIC berhasil disimpan.', 'success');
    } catch (e) {
        showToast(e.message || 'Gagal menyimpan Customer PIC.', 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Simpan';
    }
}

function escHtml(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function showToast(msg, type) {
    // Gunakan notifikasi bawaan sistem jika tersedia, fallback ke alert
    if (typeof showNotification === 'function') {
        showNotification(msg, type);
    } else {
        alert(msg);
    }
}

// Auto-load panel saat halaman siap
document.addEventListener('DOMContentLoaded', () => loadCustomerPicPanel());
</script>

{{-- Load custom-dd script + cache buster supaya production auto-invalidate setiap deploy. --}}
@php
    $customDdPath = public_path('js/custom-dropdown.js');
    $customDdVer  = file_exists($customDdPath) ? filemtime($customDdPath) : time();
@endphp
<script src="/js/custom-dropdown.js?v={{ $customDdVer }}"></script>

@if($isEcAdmin)
{{-- Remove Ticket from DS Modal --}}
<div id="removeTicketModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-base font-semibold text-gray-900">Remove Ticket from DS</h3>
            <button onclick="closeRemoveTicketModal()" class="text-gray-400 hover:text-gray-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="px-6 py-4 space-y-2 max-h-72 overflow-y-auto">
            @forelse($linkedTickets as $lt)
            <div class="flex items-center justify-between gap-3 p-3 rounded-xl border border-gray-100 hover:border-orange-200 hover:bg-orange-50 transition">
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-gray-800 truncate">{{ $lt->ticket_number }}</p>
                    <p class="text-xs text-gray-500 truncate">{{ Str::limit($lt->description, 60) }}</p>
                </div>
                <button onclick="confirmRemoveTicket({{ $lt->activity_id }}, '{{ $lt->ticket_number }}')"
                        class="flex-shrink-0 flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-orange-700 bg-orange-100 hover:bg-orange-200 rounded-lg transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                    </svg>
                    Remove
                </button>
            </div>
            @empty
            <p class="text-sm text-gray-400 italic text-center py-4">No tickets currently linked to this DS.</p>
            @endforelse
        </div>
        <div class="px-6 py-4 border-t border-gray-100 flex justify-end">
            <button onclick="closeRemoveTicketModal()" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition">Close</button>
        </div>
    </div>
</div>

<script>
function openRemoveTicketModal() {
    const modal = document.getElementById('removeTicketModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeRemoveTicketModal() {
    const modal = document.getElementById('removeTicketModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

async function confirmRemoveTicket(activityId, ticketNumber) {
    const ok = await showConfirm(
        `Remove ticket ${ticketNumber} from this delivery support? The activity will remain; only the ticket link will be cleared.`,
        'Remove Ticket from DS',
        'warning',
        { okText: 'Remove' }
    );
    if (!ok) return;

    const supportId = {{ $support->id }};
    const csrf = document.querySelector('meta[name="csrf-token"]').content;

    try {
        const res  = await fetch(`/delivery/support/${supportId}/activities/${activityId}/remove-ticket`, {
            method: 'PATCH',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        });
        const data = await res.json();

        if (data.success) {
            showNotification(data.message, 'success');
            closeRemoveTicketModal();
            setTimeout(() => location.reload(), 800);
        } else {
            showNotification(data.message || 'Failed to remove ticket.', 'error');
        }
    } catch (e) {
        showNotification('Network error. Please try again.', 'error');
    }
}

document.getElementById('removeTicketModal').addEventListener('click', function(e) {
    if (e.target === this) closeRemoveTicketModal();
});
</script>
@endif

@endsection

@extends('dashboard')
@section('title', 'Support Details')
@section('page-title', 'Support Details')
@section('page-subtitle', $support->name ?? 'Support #' . $support->id)

@section('content')
<div class="min-h-screen bg-gray-50">
    {{-- Toast Notification Container --}}
    <div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>

    {{-- Breadcrumb --}}
    <div class="mb-6">
        <nav class="flex" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                <li class="inline-flex items-center">
                    <a href="{{ route('delivery.support.index') }}" class="text-gray-600 hover:text-blue-600 text-sm font-medium">
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
        <a href="{{ route('delivery.support.planning.index', $support->id) }}"
           class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition">
            <i class="fas fa-tasks mr-2"></i>
            Open Planning
        </a>
        <form action="{{ route('delivery.support.destroy', $support->id) }}" method="POST" class="inline"
              onsubmit="return confirm('Are you sure you want to delete this support delivery?');">
            @csrf
            @method('DELETE')
            <button type="submit"
                    class="inline-flex items-center px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition">
                <i class="fas fa-trash mr-2"></i>
                Delete
            </button>
        </form>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Main Info --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Support Information --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-900">Support Information</h3>
                    <button type="button" onclick="openEditModal('support-info')"
                            class="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition"
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
                    </div>
                </div>
            </div>

            {{-- Approval Information --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-900">Approval Information</h3>
                    <button type="button" onclick="openEditModal('approval-info')"
                            class="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition"
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
                    <a href="{{ route('delivery.support.planning.index', $support->id) }}" class="text-sm text-blue-600 hover:text-blue-800">
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
                            <a href="{{ route('delivery.support.planning.index', $support->id) }}" class="inline-flex items-center mt-2 text-sm text-blue-600 hover:text-blue-800">
                                <i class="fas fa-plus mr-1"></i>
                                Add activities
                            </a>
                        </div>
                    @endif
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
                            class="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition"
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
                </div>
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
                <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-4 sm:px-6 py-3 sm:py-4">
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
                               class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Client <span class="text-red-500">*</span></label>
                            <select name="client_id" id="edit-client_id" required
                                    class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
                                <option value="">Select Client</option>
                                @foreach($clients ?? [] as $client)
                                    <option value="{{ $client->customer_id }}" {{ $support->client_id == $client->customer_id ? 'selected' : '' }}>
                                        {{ $client->basicData->name_1 ?? 'N/A' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Support Method</label>
                            <select name="support_method" id="edit-support_method"
                                    class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
                                <option value="">Select method</option>
                                <option value="Remote" {{ $support->support_method == 'Remote' ? 'selected' : '' }}>Remote</option>
                                <option value="On-Site" {{ $support->support_method == 'On-Site' ? 'selected' : '' }}>On-Site</option>
                                <option value="Hybrid" {{ $support->support_method == 'Hybrid' ? 'selected' : '' }}>Hybrid</option>
                            </select>
                        </div>
                    </div>
                    @php $roleId = session('user.role.id'); @endphp
                    @if(in_array($roleId, [1, 6, 7]))
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                        <select name="type" id="edit-type"
                                class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
                            <option value="">No type set</option>
                            <option value="AMS" {{ $support->type == 'AMS' ? 'selected' : '' }}>AMS</option>
                            <option value="MO" {{ $support->type == 'MO' ? 'selected' : '' }}>MO</option>
                            <option value="ATS" {{ $support->type == 'ATS' ? 'selected' : '' }}>ATS</option>
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
                                   class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                            <input type="date" name="end_date" id="edit-end_date"
                                   value="{{ $support->end_date?->format('Y-m-d') }}"
                                   class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Resolution Estimated</label>
                            <input type="date" name="resolution_estimated" id="edit-resolution_estimated"
                                   value="{{ $support->resolution_estimated?->format('Y-m-d') }}"
                                   class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Total Mandays</label>
                        <input type="number" name="total_mandays" id="edit-total_mandays" min="0"
                               value="{{ $support->total_mandays }}"
                               class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                </div>
                <div class="bg-gray-50 px-4 sm:px-6 py-3 sm:py-4 flex flex-col-reverse sm:flex-row sm:justify-end gap-2 sm:gap-3">
                    <button type="button" onclick="closeEditModal('support-info')"
                            class="w-full sm:w-auto px-4 py-2 border border-gray-300 rounded-md text-gray-700 bg-white hover:bg-gray-50 text-sm font-medium">
                        Cancel
                    </button>
                    <button type="submit"
                            class="w-full sm:w-auto px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm font-medium">
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
                <div class="bg-gradient-to-r from-green-600 to-teal-600 px-4 sm:px-6 py-3 sm:py-4">
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
                               class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Approved By</label>
                        <input type="text" name="approval_name" id="edit-approval_name"
                               value="{{ $support->approval_name }}"
                               class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 text-sm"
                               placeholder="Enter approver name">
                    </div>
                </div>
                <div class="bg-gray-50 px-4 sm:px-6 py-3 sm:py-4 flex flex-col-reverse sm:flex-row sm:justify-end gap-2 sm:gap-3">
                    <button type="button" onclick="closeEditModal('approval-info')"
                            class="w-full sm:w-auto px-4 py-2 border border-gray-300 rounded-md text-gray-700 bg-white hover:bg-gray-50 text-sm font-medium">
                        Cancel
                    </button>
                    <button type="submit"
                            class="w-full sm:w-auto px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 text-sm font-medium">
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
                <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-4 sm:px-6 py-3 sm:py-4">
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
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Delivery Owner</label>
                        <select name="delivery_owner_id" id="edit-delivery_owner_id"
                                class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500 text-sm">
                            <option value="">Not assigned</option>
                            @foreach($employees ?? [] as $employee)
                                <option value="{{ $employee->employee_id }}" {{ $support->delivery_owner_id == $employee->employee_id ? 'selected' : '' }}>
                                    {{ $employee->basicData->full_name ?? 'N/A' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Support Manager</label>
                        <select name="support_manager_id" id="edit-support_manager_id"
                                class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500 text-sm">
                            <option value="">Not assigned</option>
                            @foreach($employees ?? [] as $employee)
                                <option value="{{ $employee->employee_id }}" {{ $support->support_manager_id == $employee->employee_id ? 'selected' : '' }}>
                                    {{ $employee->basicData->full_name ?? 'N/A' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 sm:px-6 py-3 sm:py-4 flex flex-col-reverse sm:flex-row sm:justify-end gap-2 sm:gap-3">
                    <button type="button" onclick="closeEditModal('team-info')"
                            class="w-full sm:w-auto px-4 py-2 border border-gray-300 rounded-md text-gray-700 bg-white hover:bg-gray-50 text-sm font-medium">
                        Cancel
                    </button>
                    <button type="submit"
                            class="w-full sm:w-auto px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 text-sm font-medium">
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

// showToast is provided globally by dashboard.blade.php

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
        showToast(json.message || 'Changes saved successfully', 'success');
        closeEditModal(section);
        updateDisplayValues(section, data);
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    })
    .catch(error => {
        console.error('Error saving:', error);
        showToast(error.message || 'Failed to save changes', 'error');
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
        // Update delivery owner
        if (data.delivery_owner_id) {
            const owner = employeesData.find(e => e.employee_id == data.delivery_owner_id);
            if (owner) {
                document.getElementById('display-delivery_owner').textContent = owner.basic_data?.full_name || 'Not assigned';
            }
        } else {
            document.getElementById('display-delivery_owner').textContent = 'Not assigned';
        }

        // Update support manager
        if (data.support_manager_id) {
            const manager = employeesData.find(e => e.employee_id == data.support_manager_id);
            if (manager) {
                document.getElementById('display-support_manager').textContent = manager.basic_data?.full_name || 'Not assigned';
            }
        } else {
            document.getElementById('display-support_manager').textContent = 'Not assigned';
        }
    }
}

// ============================================================================
// OTHER MODALS (TO BE IMPLEMENTED)
// ============================================================================
function openDocumentsModal() {
    showToast('Documents modal will be implemented', 'info');
}

function openUpdatesModal() {
    showToast('Updates modal will be implemented', 'info');
}

// ============================================================================
// INITIALIZATION
// ============================================================================
document.addEventListener('DOMContentLoaded', function() {
    // Show flash messages as toasts
    @if(session('success'))
        showToast('{{ session('success') }}', 'success');
    @endif

    @if(session('error'))
        showToast('{{ session('error') }}', 'error');
    @endif

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
</script>
@endsection

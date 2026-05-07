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
        <a href="{{ route('delivery.support.planning.index', $support->id) }}"
           class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
            Open Planning
        </a>
        @if($support->onedrive_folder_url)
        <a id="headerFolderBtn" href="{{ $support->onedrive_folder_url }}" target="_blank" rel="noopener"
           class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition-all duration-200">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
            </svg>
            Open Folder
        </a>
        @else
        <button type="button" id="headerFolderBtn" onclick="openOneDriveModal()"
                class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition-all duration-200">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>
            </svg>
            Create Folder
        </button>
        @endif
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
                    <div class="border-t border-gray-100 mt-1 pt-1">
                        <form action="{{ route('delivery.support.destroy', $support->id) }}" method="POST"
                              onsubmit="return confirm('Are you sure you want to delete this support delivery?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
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
<div id="oneDriveModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeOneDriveModal()"></div>
        <div class="relative bg-white rounded-xl shadow-2xl max-w-md w-full z-10 overflow-hidden">

            {{-- State 1: Generate form --}}
            <div id="odrStateGenerate">
                <div class="flex items-center justify-between px-6 pt-5 pb-4 border-b border-gray-100">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>
                        </svg>
                        <h3 class="text-base font-semibold text-gray-900">Create OneDrive Folder</h3>
                    </div>
                    <button onclick="closeOneDriveModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="px-6 py-5 space-y-4">
                    <p class="text-sm text-gray-500">
                        The folder will be created in the OneDrive account <strong class="text-gray-700">{{ config('services.microsoft_graph.sender_email') }}</strong>
                        and accessible to anyone with the link (edit &amp; upload access).
                    </p>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Folder Name <span class="font-normal text-gray-400">(optional)</span>
                        </label>
                        <input type="text" id="odrFolderName"
                               value="{{ $support->name ?? 'Support-' . $support->id }}"
                               class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <p class="text-xs text-gray-400 mt-1">Name of the folder to be created in OneDrive.</p>
                    </div>
                    <div class="flex gap-2 pt-1">
                        <button onclick="generateSupportFolder()" id="odrGenerateBtn"
                            class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 active:bg-blue-800 transition-all disabled:opacity-60 disabled:cursor-not-allowed">
                            <svg id="odrGenerateIcon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>
                            </svg>
                            <svg id="odrGenerateSpinner" class="hidden animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            <span id="odrGenerateLabel">{{ $support->onedrive_folder_id ? 'Regenerate Link' : 'Generate Folder' }}</span>
                        </button>
                        <button type="button" id="odrDeleteBtnForm" onclick="deleteSupportFolder()"
                            class="{{ $support->onedrive_folder_id ? '' : 'hidden' }} px-4 py-2.5 border border-red-200 text-sm text-red-600 rounded-lg hover:bg-red-50 transition-all inline-flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            Delete Folder
                        </button>
                        <button type="button" onclick="closeOneDriveModal()"
                            class="px-4 py-2.5 border border-gray-300 text-sm text-gray-700 rounded-lg hover:bg-gray-50 transition-all">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>

            {{-- State 2: Success --}}
            <div id="odrStateSuccess" class="hidden">
                <div class="flex items-center justify-between px-6 pt-5 pb-4 border-b border-gray-100">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <h3 class="text-base font-semibold text-gray-900">OneDrive Folder Ready</h3>
                    </div>
                    <button onclick="closeOneDriveModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="px-6 py-5 space-y-4">
                    <div class="flex justify-center">
                        <div class="w-14 h-14 bg-green-100 rounded-full flex items-center justify-center">
                            <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                    </div>
                    <p class="text-sm text-gray-600 text-center">
                        Folder created successfully. Share the link below with anyone who needs access.
                    </p>
                    {{-- Link row --}}
                    <div class="flex gap-2">
                        <input type="text" id="odrFolderUrl" readonly
                               class="flex-1 px-3 py-2 text-xs border border-gray-300 rounded-lg bg-gray-50 text-gray-700 focus:outline-none cursor-text select-all">
                        <button onclick="copyFolderLink()" id="odrCopyBtn" title="Copy link"
                            class="flex-shrink-0 inline-flex items-center justify-center w-9 h-9 border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50 transition-all">
                            <svg id="odrCopyIcon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                            <svg id="odrCopiedIcon" class="hidden w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                            </svg>
                        </button>
                    </div>
                    {{-- Action buttons --}}
                    <div class="flex gap-2 pt-1">
                        <a id="odrOpenLink" href="#" target="_blank" rel="noopener"
                           class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition-all">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                            </svg>
                            Open Folder
                        </a>
                        <button onclick="deleteSupportFolder()"
                            class="px-4 py-2.5 border border-red-200 text-sm text-red-600 rounded-lg hover:bg-red-50 transition-all inline-flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            Delete Folder
                        </button>
                    </div>
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
                        $currentDoLabel = '';
                        $currentSmLabel = '';
                        foreach($employees ?? [] as $_e) {
                            $name = $_e->basicData->full_name ?? 'N/A';
                            if ($support->delivery_owner_id  == $_e->employee_id) $currentDoLabel = $name;
                            if ($support->support_manager_id == $_e->employee_id) $currentSmLabel = $name;
                        }
                    @endphp
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Delivery Owner</label>
                        <div class="custom-dd relative" data-fixed="true">
                            <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm hover:border-gray-400 transition-all text-left">
                                <span class="custom-dd-label {{ $currentDoLabel ? 'text-gray-700' : 'text-gray-500' }}">{{ $currentDoLabel ?: 'Not assigned' }}</span>
                                <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <input type="hidden" name="delivery_owner_id" id="edit-delivery_owner_id" value="{{ $support->delivery_owner_id }}">
                            <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:400px;">
                                <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">Not assigned</button>
                                @foreach($employees ?? [] as $employee)
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="{{ $employee->employee_id }}">{{ $employee->basicData->full_name ?? 'N/A' }}</button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Support Manager</label>
                        <div class="custom-dd relative" data-fixed="true">
                            <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm hover:border-gray-400 transition-all text-left">
                                <span class="custom-dd-label {{ $currentSmLabel ? 'text-gray-700' : 'text-gray-500' }}">{{ $currentSmLabel ?: 'Not assigned' }}</span>
                                <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <input type="hidden" name="support_manager_id" id="edit-support_manager_id" value="{{ $support->support_manager_id }}">
                            <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:400px;">
                                <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">Not assigned</button>
                                @foreach($employees ?? [] as $employee)
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="{{ $employee->employee_id }}">{{ $employee->basicData->full_name ?? 'N/A' }}</button>
                                @endforeach
                            </div>
                        </div>
                    </div>
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

    // Show flash messages as toasts
    @if(session('success'))
        showNotification('{{ session('success') }}', 'success');
    @endif

    @if(session('error'))
        showNotification('{{ session('error') }}', 'error');
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

// ── OneDrive Modal ────────────────────────────────────────────────────────
let _odrHasFolder = {{ $support->onedrive_folder_id ? 'true' : 'false' }};

function openOneDriveModal() {
    document.getElementById('oneDriveModal').classList.remove('hidden');
    _showOdrGenerate();
}

function closeOneDriveModal() {
    document.getElementById('oneDriveModal').classList.add('hidden');
}

function _showOdrGenerate() {
    document.getElementById('odrStateGenerate').classList.remove('hidden');
    document.getElementById('odrStateSuccess').classList.add('hidden');
    const del = document.getElementById('odrDeleteBtnForm');
    if (del) del.classList.toggle('hidden', !_odrHasFolder);
}

function _showOdrSuccess(url) {
    document.getElementById('odrStateGenerate').classList.add('hidden');
    document.getElementById('odrStateSuccess').classList.remove('hidden');
    document.getElementById('odrFolderUrl').value = url;
    document.getElementById('odrOpenLink').href   = url;
    document.getElementById('odrCopyIcon').classList.remove('hidden');
    document.getElementById('odrCopiedIcon').classList.add('hidden');
    _odrHasFolder = true;
    // Swap header button to "Open Folder"
    const btn = document.getElementById('headerFolderBtn');
    if (btn && btn.tagName === 'BUTTON') {
        const a = document.createElement('a');
        a.id = 'headerFolderBtn'; a.href = url; a.target = '_blank'; a.rel = 'noopener';
        a.className = btn.className;
        a.innerHTML = `<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg> Open Folder`;
        btn.replaceWith(a);
    }
}

async function deleteSupportFolder() {
    if (!confirm('Are you sure you want to delete this OneDrive folder? The folder and all its contents will be permanently deleted.')) return;
    try {
        const res  = await fetch('{{ route('delivery.support.delete-folder', $support->id) }}', {
            method:  'DELETE',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        });
        const data = await res.json();
        if (data.success) {
            _odrHasFolder = false;
            closeOneDriveModal();
            // Revert header button to "Create Folder"
            const el = document.getElementById('headerFolderBtn');
            if (el) {
                const btn = document.createElement('button');
                btn.type = 'button'; btn.id = 'headerFolderBtn'; btn.onclick = openOneDriveModal;
                btn.className = el.className;
                btn.innerHTML = `<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/></svg> Create Folder`;
                el.replaceWith(btn);
            }
            showToast('Folder deleted successfully.', 'success');
        } else {
            showToast(data.message || 'Failed to delete folder.', 'error');
        }
    } catch (err) {
        showToast('Error: ' + err.message, 'error');
    }
}

async function generateSupportFolder() {
    const btn     = document.getElementById('odrGenerateBtn');
    const icon    = document.getElementById('odrGenerateIcon');
    const spinner = document.getElementById('odrGenerateSpinner');
    const label   = document.getElementById('odrGenerateLabel');

    btn.disabled = true;
    icon.classList.add('hidden');
    spinner.classList.remove('hidden');
    label.textContent = 'Creating folder…';

    try {
        const res  = await fetch('{{ route('delivery.support.generate-folder', $support->id) }}', {
            method:  'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept':       'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ folder_name: document.getElementById('odrFolderName').value.trim() }),
        });
        const data = await res.json();

        if (data.success) {
            _showOdrSuccess(data.folder_url);
            showToast('OneDrive folder created successfully!', 'success');
        } else {
            showToast(data.message || 'Failed to create folder.', 'error');
            label.textContent = 'Generate Folder';
        }
    } catch (err) {
        showToast('Error: ' + err.message, 'error');
        label.textContent = 'Generate Folder';
    } finally {
        btn.disabled = false;
        icon.classList.remove('hidden');
        spinner.classList.add('hidden');
    }
}

function copyFolderLink() {
    const val = document.getElementById('odrFolderUrl').value;
    navigator.clipboard.writeText(val).then(() => {
        document.getElementById('odrCopyIcon').classList.add('hidden');
        document.getElementById('odrCopiedIcon').classList.remove('hidden');
        setTimeout(() => {
            document.getElementById('odrCopyIcon').classList.remove('hidden');
            document.getElementById('odrCopiedIcon').classList.add('hidden');
        }, 2000);
        showToast('Link copied to clipboard!', 'success');
    });
}

</script>

{{-- Load custom-dd script + cache buster supaya production auto-invalidate setiap deploy. --}}
@php
    $customDdPath = public_path('js/custom-dropdown.js');
    $customDdVer  = file_exists($customDdPath) ? filemtime($customDdPath) : time();
@endphp
<script src="/js/custom-dropdown.js?v={{ $customDdVer }}"></script>

@endsection

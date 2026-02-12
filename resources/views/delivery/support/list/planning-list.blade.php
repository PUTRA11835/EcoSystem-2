@extends('dashboard')
@section('title', 'Delivery Support Planning')
@section('page-title', 'Delivery Support')
@section('page-subtitle', 'Manage support planning and progress')

@section('content')
<div class="min-h-screen bg-gray-50">
    {{-- Flash Notifications --}}
    @if(session('success'))
        <div id="success-alert" class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg relative" role="alert">
            <div class="flex items-center">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <span class="font-medium">{{ session('success') }}</span>
            </div>
            <button onclick="document.getElementById('success-alert').remove()" class="absolute top-0 bottom-0 right-0 px-4 py-3">
                <svg class="fill-current h-5 w-5 text-green-700" role="button" viewBox="0 0 20 20">
                    <path d="M14.348 5.652a.5.5 0 010 .707L10.707 10l3.641 3.641a.5.5 0 01-.707.707L10 10.707l-3.641 3.641a.5.5 0 01-.707-.707L9.293 10 5.652 6.359a.5.5 0 01.707-.707L10 9.293l3.641-3.641a.5.5 0 01.707 0z"/>
                </svg>
            </button>
        </div>
    @endif

    {{-- Sub Navigation Tabs --}}
    <div class="mb-6 bg-white rounded-lg shadow-sm border border-gray-200">
        <nav class="flex space-x-1 p-1" aria-label="Tabs">
            <a href="{{ route('delivery.support.index') }}"
               class="flex-1 sm:flex-none px-4 py-2.5 text-sm font-medium text-center rounded-lg transition-all
                      {{ Request::routeIs('delivery.support.index') ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100' }}">
                <i class="fas fa-headset mr-2"></i>
                <span>Support</span>
            </a>
            <a href="{{ route('delivery.support.planning-list') }}"
               class="flex-1 sm:flex-none px-4 py-2.5 text-sm font-medium text-center rounded-lg transition-all
                      {{ Request::routeIs('delivery.support.planning-list') ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100' }}">
                <i class="fas fa-tasks mr-2"></i>
                <span>Planning</span>
            </a>
        </nav>
    </div>

    <div class="bg-white shadow-sm rounded-lg overflow-hidden">
        <div class="px-4 sm:px-6 py-4 bg-gray-50 border-b border-gray-200">
            <div class="flex justify-between items-center flex-wrap gap-4">
                <h3 class="text-lg font-medium text-gray-900">Support Planning List</h3>
                <select id="statusFilter" class="px-4 py-2 text-sm border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 transition">
                    <option value="">All Status</option>
                    <option value="completed">Completed</option>
                    <option value="in_progress">In Progress</option>
                    <option value="not_started">Not Started</option>
                </select>
            </div>
        </div>

        {{-- Search Bar --}}
        <div class="p-4">
            <input type="search" id="searchInput"
                   placeholder="Search by support name, client..."
                   class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 transition text-sm">
        </div>

        {{-- MOBILE VIEW: Card Layout --}}
        <div class="block lg:hidden px-4 pb-4">
            <div id="mobile-supports-container" class="space-y-4">
                @forelse($supports as $support)
                    @php
                        $progress = $support->calculated_progress ?? 0;
                        $statusText = $progress >= 100 ? 'Completed' : ($progress > 0 ? 'In Progress' : 'Not Started');
                        $statusValue = $progress >= 100 ? 'completed' : ($progress > 0 ? 'in_progress' : 'not_started');
                        $statusColor = $progress >= 100 ? '#10b981' : ($progress > 50 ? '#3b82f6' : ($progress > 0 ? '#f59e0b' : '#9ca3af'));
                        $statusBgClass = $progress >= 100 ? 'bg-green-50' : ($progress > 0 ? 'bg-blue-50' : 'bg-gray-50');

                        $totalActivities = $support->activities->count();
                        $completedActivities = $support->activities->where('status', 'completed')->count();
                        $delayedActivities = $support->activities->where('status', 'delayed')->count();

                        $searchableContent = strtolower(
                            ($support->name ?? '') . ' ' .
                            ($support->client->basicData->name_1 ?? 'N/A') . ' ' .
                            $statusText . ' ' .
                            'progress ' . $progress . '%'
                        );

                        $targetUrl = route('delivery.support.planning.index', $support->id);
                    @endphp
                    <div class="mobile-support-card bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow cursor-pointer {{ $delayedActivities > 0 ? 'border-red-200' : '' }}"
                         onclick="window.location.href='{{ $targetUrl }}'"
                         data-support-status="{{ $statusValue }}"
                         data-searchable-content="{{ e($searchableContent) }}">
                        {{-- Card Header --}}
                        <div class="{{ $statusBgClass }} px-4 py-3 border-b border-gray-200">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <h4 class="text-base font-semibold text-gray-900">{{ $support->name ?? 'Support #' . $support->id }}</h4>
                                    <p class="text-sm text-gray-600 mt-1">{{ $support->client->basicData->name_1 ?? 'N/A' }}</p>
                                </div>
                                @if($support->ticket_id)
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-purple-100 text-purple-800">
                                        #{{ $support->ticket_id }}
                                    </span>
                                @endif
                            </div>
                        </div>
                        {{-- Card Body --}}
                        <div class="px-4 py-3 space-y-3">
                            {{-- Status and Progress --}}
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="w-2 h-2 rounded-full mr-2" style="background-color: {{ $statusColor }}"></div>
                                    <span class="text-sm font-medium text-gray-700">{{ $statusText }}</span>
                                </div>
                                <div class="flex items-center space-x-3">
                                    {{-- Progress Ring --}}
                                    <div class="relative w-12 h-12">
                                        <svg class="w-12 h-12 transform -rotate-90" viewBox="0 0 32 32">
                                            <circle cx="16" cy="16" r="14" fill="transparent" stroke="#e5e7eb" stroke-width="2"/>
                                            <circle cx="16" cy="16" r="14" fill="transparent"
                                                    stroke="{{ $statusColor }}"
                                                    stroke-width="2"
                                                    stroke-dasharray="{{ 88 * $progress / 100 }} 88"
                                                    stroke-linecap="round"/>
                                        </svg>
                                        <div class="absolute inset-0 flex items-center justify-center">
                                            <span class="text-xs font-bold text-gray-900">{{ number_format($progress, 0) }}%</span>
                                        </div>
                                    </div>
                                    {{-- Progress Bar --}}
                                    <div class="w-20 bg-gray-200 rounded-full h-2">
                                        <div class="h-2 rounded-full" style="width: {{ $progress }}%; background-color: {{ $statusColor }};"></div>
                                    </div>
                                </div>
                            </div>
                            {{-- Timeline --}}
                            <div class="grid grid-cols-2 gap-3 text-sm">
                                <div>
                                    <span class="text-gray-500">Start:</span>
                                    <p class="font-medium text-gray-900">{{ $support->start_date ? $support->start_date->format('d M Y') : 'N/A' }}</p>
                                </div>
                                <div>
                                    <span class="text-gray-500">End:</span>
                                    <p class="font-medium text-gray-900">{{ $support->end_date ? $support->end_date->format('d M Y') : 'N/A' }}</p>
                                </div>
                            </div>
                            {{-- Activities Summary --}}
                            @if($totalActivities > 0)
                                <div class="pt-2 border-t border-gray-200">
                                    <div class="flex justify-between text-xs text-gray-600">
                                        <span>Total Activities: {{ $totalActivities }}</span>
                                        <span>Completed: {{ $completedActivities }}</span>
                                    </div>
                                    @if($delayedActivities > 0)
                                        <div class="mt-1 text-xs font-semibold text-red-600">
                                            {{ $delayedActivities }} activities delayed
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No Support Planning</h3>
                        <p class="mt-1 text-sm text-gray-500">Start by creating a support item first</p>
                        <div class="mt-4">
                            <a href="{{ route('delivery.support.create') }}"
                               class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                <svg class="mr-2 -ml-1 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                </svg>
                                Add New Support
                            </a>
                        </div>
                    </div>
                @endforelse
                {{-- Mobile No Results --}}
                <div id="mobile-no-results" class="hidden text-center py-8">
                    <p class="text-sm text-gray-500">No support items match your search.</p>
                </div>
            </div>
        </div>

        {{-- DESKTOP VIEW: Table Layout --}}
        <div class="hidden lg:block overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Support</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Progress</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Activities</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Timeline</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200" id="desktop-supports-table-body">
                    @forelse($supports as $support)
                        @php
                            $progress = $support->calculated_progress ?? 0;
                            $statusText = $progress >= 100 ? 'Completed' : ($progress > 0 ? 'In Progress' : 'Not Started');
                            $statusValue = $progress >= 100 ? 'completed' : ($progress > 0 ? 'in_progress' : 'not_started');
                            $statusColor = $progress >= 100 ? '#10b981' : ($progress > 50 ? '#3b82f6' : ($progress > 0 ? '#f59e0b' : '#9ca3af'));

                            $totalActivities = $support->activities->count();
                            $completedActivities = $support->activities->where('status', 'completed')->count();
                            $delayedActivities = $support->activities->where('status', 'delayed')->count();

                            $searchableContent = strtolower(
                                ($support->name ?? '') . ' ' .
                                ($support->client->basicData->name_1 ?? 'N/A') . ' ' .
                                $statusText . ' ' .
                                'progress ' . $progress . '%'
                            );

                            $targetUrl = route('delivery.support.planning.index', $support->id);
                        @endphp
                        <tr data-support-status="{{ $statusValue }}"
                            data-searchable-content="{{ e($searchableContent) }}"
                            onclick="window.location.href='{{ $targetUrl }}'"
                            class="desktop-support-row cursor-pointer {{ $delayedActivities > 0 ? 'bg-red-50' : '' }} hover:bg-gray-50 transition-colors duration-150">
                            {{-- Support Name --}}
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">{{ $support->name ?? 'Support #' . $support->id }}</div>
                                @if($support->ticket_id)
                                    <span class="inline-flex mt-1 px-2 py-0.5 text-xs font-semibold rounded-full bg-purple-100 text-purple-800">
                                        Ticket #{{ $support->ticket_id }}
                                    </span>
                                @endif
                            </td>
                            {{-- Client --}}
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-600">{{ $support->client->basicData->name_1 ?? 'N/A' }}</div>
                            </td>
                            {{-- Progress Ring + Bar --}}
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center space-x-3">
                                    <div class="relative w-12 h-12">
                                        <svg class="w-12 h-12 transform -rotate-90" viewBox="0 0 32 32">
                                            <circle cx="16" cy="16" r="14" fill="transparent" stroke="#e5e7eb" stroke-width="2"/>
                                            <circle cx="16" cy="16" r="14" fill="transparent"
                                                    stroke="{{ $statusColor }}"
                                                    stroke-width="2"
                                                    stroke-dasharray="{{ 88 * $progress / 100 }} 88"
                                                    stroke-linecap="round"/>
                                        </svg>
                                        <div class="absolute inset-0 flex items-center justify-center">
                                            <span class="text-xs font-bold text-gray-900">{{ number_format($progress, 0) }}%</span>
                                        </div>
                                    </div>
                                    <div class="w-20 bg-gray-200 rounded-full h-2">
                                        <div class="h-2 rounded-full" style="width: {{ $progress }}%; background-color: {{ $statusColor }};"></div>
                                    </div>
                                </div>
                            </td>
                            {{-- Status --}}
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="w-2 h-2 rounded-full mr-2" style="background-color: {{ $statusColor }}"></div>
                                    <span class="text-sm font-medium text-gray-700">{{ $statusText }}</span>
                                </div>
                            </td>
                            {{-- Activities --}}
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                <div class="flex flex-col">
                                    <span>{{ $completedActivities }}/{{ $totalActivities }} completed</span>
                                    @if($delayedActivities > 0)
                                        <span class="text-xs text-red-600 font-medium">{{ $delayedActivities }} delayed</span>
                                    @endif
                                </div>
                            </td>
                            {{-- Timeline --}}
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $support->start_date ? $support->start_date->format('d M') : '-' }} - {{ $support->end_date ? $support->end_date->format('d M Y') : '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900">No Support Planning</h3>
                                <p class="mt-1 text-sm text-gray-500">Start by creating a support item first</p>
                            </td>
                        </tr>
                    @endforelse
                    {{-- Desktop No Results Row --}}
                    <tr id="desktop-no-results-row" class="hidden">
                        <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                            No support items match your search.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Floating Action Button --}}
    <div class="fixed bottom-6 right-6">
        <a href="{{ route('delivery.support.create') }}"
           class="bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 text-white w-14 h-14 rounded-full shadow-2xl hover:shadow-3xl transform hover:scale-105 transition-all duration-300 flex items-center justify-center">
            <i class="fas fa-plus text-lg"></i>
        </a>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('[id$="-alert"]');
        alerts.forEach(function(alert) {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(function() {
                alert.remove();
            }, 500);
        });
    }, 5000);

    const searchInput = document.getElementById('searchInput');
    const statusFilter = document.getElementById('statusFilter');

    // Filter function
    function filterSupports(searchTerm, selectedStatus) {
        // Mobile cards
        const mobileCards = document.querySelectorAll('.mobile-support-card');
        const mobileNoResults = document.getElementById('mobile-no-results');
        let mobileVisibleCount = 0;

        mobileCards.forEach(card => {
            const searchableContent = card.dataset.searchableContent ? card.dataset.searchableContent.toLowerCase() : '';
            const supportStatus = card.dataset.supportStatus || '';

            const matchesSearch = searchTerm === '' || searchableContent.includes(searchTerm);
            const matchesStatus = selectedStatus === '' || supportStatus === selectedStatus;

            if (matchesSearch && matchesStatus) {
                card.style.display = '';
                mobileVisibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        if (mobileNoResults) {
            mobileNoResults.classList.toggle('hidden', mobileVisibleCount > 0 || mobileCards.length === 0);
        }

        // Desktop rows
        const desktopRows = document.querySelectorAll('.desktop-support-row');
        const desktopNoResults = document.getElementById('desktop-no-results-row');
        let desktopVisibleCount = 0;

        desktopRows.forEach(row => {
            const searchableContent = row.dataset.searchableContent ? row.dataset.searchableContent.toLowerCase() : '';
            const supportStatus = row.dataset.supportStatus || '';

            const matchesSearch = searchTerm === '' || searchableContent.includes(searchTerm);
            const matchesStatus = selectedStatus === '' || supportStatus === selectedStatus;

            if (matchesSearch && matchesStatus) {
                row.style.display = '';
                desktopVisibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        if (desktopNoResults) {
            desktopNoResults.classList.toggle('hidden', desktopVisibleCount > 0 || desktopRows.length === 0);
        }
    }

    // Search
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            filterSupports(searchTerm, statusFilter ? statusFilter.value : '');
        });

        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                this.value = '';
                filterSupports('', statusFilter ? statusFilter.value : '');
            }
        });
    }

    // Status filter
    if (statusFilter) {
        statusFilter.addEventListener('change', function() {
            const searchTerm = searchInput ? searchInput.value.toLowerCase().trim() : '';
            filterSupports(searchTerm, this.value);
        });
    }
});
</script>
@endsection

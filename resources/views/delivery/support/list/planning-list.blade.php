@extends('dashboard')
@section('title', 'Delivery Support Planning')
@section('page-title', 'Delivery Support')
@section('page-subtitle', 'Manage support planning and progress')

@section('content')
<div class="min-h-screen bg-gray-50">
    {{-- Flash Notifications --}}
    @if(session('success'))
        <script>document.addEventListener('DOMContentLoaded',()=>showNotification(@json(session('success')),'success'));</script>
    @endif
    @if(session('error'))
        <script>document.addEventListener('DOMContentLoaded',()=>showNotification(@json(session('error')),'error'));</script>
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
                {{-- custom-dd manual untuk style konsisten. Hidden input pakai
                     id="statusFilter" supaya reading .value tetap jalan. JS
                     handler dipanggil via data-onchange (custom-dd tidak fire
                     event 'change' di hidden input). --}}
                <div class="custom-dd relative" data-onchange="onSupportStatusFilterChange" data-fixed="true" style="min-width:160px">
                    <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-4 py-2.5 bg-white border border-gray-300 rounded-lg shadow-sm text-sm hover:border-gray-400 transition-all text-left">
                        <span class="custom-dd-label text-gray-700">All Status</span>
                        <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <input type="hidden" id="statusFilter" value="">
                    <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:240px;">
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">All Status</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="completed">Completed</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="in_progress">In Progress</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="not_started">Not Started</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Search Bar — selaras dengan style index lain --}}
        <div class="p-4">
            <input type="search" id="searchInput"
                   placeholder="Search by support name, client..."
                   class="block w-full border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 transition text-sm px-4 py-2.5">
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
                                @if($support->type)
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                        {{ $support->type }}
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
                                @if($support->type)
                                    <span class="inline-flex mt-1 px-2 py-0.5 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                        {{ $support->type }}
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

    // Init custom-dd untuk status filter
    if (typeof initCustomDropdowns === 'function') {
        initCustomDropdowns();
    }
});

// Dipanggil custom-dd via data-onchange="onSupportStatusFilterChange" setiap
// kali user memilih opsi (custom-dd tidak dispatch event 'change' di hidden
// input, harus pakai pattern callback ini).
function onSupportStatusFilterChange() {
    const search   = document.getElementById('searchInput');
    const statusEl = document.getElementById('statusFilter');
    const term     = search ? search.value.toLowerCase().trim() : '';
    const status   = statusEl ? statusEl.value : '';
    if (typeof filterSupports === 'function') filterSupports(term, status);
}
</script>
{{-- Load custom-dd component (sama dengan halaman admin lain). filemtime
     cache buster supaya production auto-invalidate setiap deploy. --}}
@php
    $customDdPath = public_path('js/custom-dropdown.js');
    $customDdVer  = file_exists($customDdPath) ? filemtime($customDdPath) : time();
@endphp
<script src="/js/custom-dropdown.js?v={{ $customDdVer }}"></script>
@endsection

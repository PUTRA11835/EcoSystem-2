@extends('dashboard')
@section('title', 'Delivery Support')
@section('page-title', 'Delivery Support')
@section('page-subtitle', 'Manage all support delivery items')

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

    @if(session('error'))
        <div id="error-alert" class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative" role="alert">
            <div class="flex items-center">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                <span class="font-medium">{{ session('error') }}</span>
            </div>
            <button onclick="document.getElementById('error-alert').remove()" class="absolute top-0 bottom-0 right-0 px-4 py-3">
                <svg class="fill-current h-5 w-5 text-red-700" role="button" viewBox="0 0 20 20">
                    <path d="M14.348 5.652a.5.5 0 010 .707L10.707 10l3.641 3.641a.5.5 0 01-.707.707L10 10.707l-3.641 3.641a.5.5 0 01-.707-.707L9.293 10 5.652 6.359a.5.5 0 01.707-.707L10 9.293l3.641-3.641a.5.5 0 01.707 0z"/>
                </svg>
            </button>
        </div>
    @endif

    @if($errors->any())
        <div id="validation-alert" class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative" role="alert">
            <div class="flex items-start">
                <svg class="w-5 h-5 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                <div>
                    <span class="font-medium">Please fix the following errors:</span>
                    <ul class="mt-1 list-disc list-inside text-sm">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
            <button onclick="document.getElementById('validation-alert').remove()" class="absolute top-0 right-0 px-4 py-3">
                <svg class="fill-current h-5 w-5 text-red-700" role="button" viewBox="0 0 20 20">
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

    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 mb-1">Total Delivery Support</p>
            <p class="text-2xl font-bold text-gray-900" id="totalCount">{{ $supports->count() ?? 0 }}</p>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 mb-1">In Progress</p>
            <p class="text-2xl font-bold text-blue-600" id="inProgressCount">{{ $supports->where('calculated_progress', '<', 100)->where('calculated_progress', '>', 0)->count() ?? 0 }}</p>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 mb-1">Completed</p>
            <p class="text-2xl font-bold text-green-600" id="completedCount">{{ $supports->where('calculated_progress', '>=', 100)->count() ?? 0 }}</p>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 mb-1">Not Started</p>
            <p class="text-2xl font-bold text-gray-400" id="notStartedCount">{{ $supports->where('calculated_progress', 0)->count() ?? 0 }}</p>
        </div>
    </div>

    <div class="bg-white shadow-sm rounded-lg overflow-hidden">
        {{-- Card Header --}}
        <div class="px-4 sm:px-6 py-4 bg-gray-50 border-b border-gray-200">
            <div class="flex justify-between items-center flex-wrap gap-4">
                <h3 class="text-lg font-medium text-gray-900">All Support Items</h3>
                <a href="{{ route('delivery.support.create') }}"
                   class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition duration-300 text-sm">
                    <i class="fas fa-plus mr-2"></i>
                    Add Delivery Support
                </a>
            </div>
        </div>

        {{-- Search Bar --}}
        <div class="p-4">
            <input type="search" id="support-search"
                placeholder="Search by client, ticket ID, or description..."
                class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 transition text-sm">
        </div>

        {{-- MOBILE VIEW: Card Layout --}}
        <div class="block lg:hidden px-4 pb-4">
            <div id="mobile-support-list" class="space-y-4">
                @forelse($supports as $support)
                    @php
                        $progress = $support->calculated_progress ?? 0;
                        $progressColor = $progress >= 100 ? '#10b981' : ($progress > 50 ? '#3b82f6' : ($progress > 0 ? '#f59e0b' : '#9ca3af'));
                    @endphp
                    <div class="support-card bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow cursor-pointer"
                         onclick="window.location.href='{{ route('delivery.support.show', $support->id) }}'"
                         data-searchable="{{ strtolower(($support->client->basicData->name_1 ?? '') . ' ' . ($support->ticket_id ?? '') . ' ' . ($support->name ?? '')) }}">
                        <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <h4 class="text-base font-semibold text-gray-900">
                                        {{ $support->name ?? 'Support #' . $support->id }}
                                    </h4>
                                    <p class="text-sm text-gray-600 mt-1">{{ $support->client->basicData->name_1 ?? 'N/A' }}</p>
                                </div>
                                @if($support->ticket_id)
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-purple-100 text-purple-800">
                                        Ticket #{{ $support->ticket_id }}
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div class="px-4 py-3 space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-500">Progress</span>
                                <div class="flex items-center space-x-3">
                                    <div class="w-24 bg-gray-200 rounded-full h-2">
                                        <div class="h-2 rounded-full" style="width: {{ $progress }}%; background-color: {{ $progressColor }};"></div>
                                    </div>
                                    <span class="text-sm font-bold" style="color: {{ $progressColor }}">{{ number_format($progress, 1) }}%</span>
                                </div>
                            </div>
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
                            <div class="pt-2 border-t border-gray-200">
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-gray-500">Support Method:</span>
                                    <span class="font-medium text-gray-900">{{ $support->support_method ?? 'N/A' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No support items</h3>
                        <p class="mt-1 text-sm text-gray-500">Get started by creating a new support item.</p>
                    </div>
                @endforelse
                <div id="mobile-no-results" class="hidden text-center py-8">
                    <p class="text-sm text-gray-500">No support items match your search.</p>
                </div>
            </div>
        </div>

        {{-- DESKTOP VIEW: Table Layout --}}
        <div class="hidden lg:block px-4 pb-4">
            <div class="table-container border border-gray-200 rounded-lg" style="max-height: 600px; overflow-y: auto;">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50 sticky top-0 z-10">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50">Client</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50">Support Name</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50">Ticket</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50">Progress</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50">Timeline</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50">Method</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200" id="desktop-support-table-body">
                        @forelse($supports as $support)
                            @php
                                $progress = $support->calculated_progress ?? 0;
                                $progressColor = $progress >= 100 ? '#10b981' : ($progress > 50 ? '#3b82f6' : ($progress > 0 ? '#f59e0b' : '#9ca3af'));
                            @endphp
                            <tr class="support-row hover:bg-gray-50 transition-colors cursor-pointer"
                                onclick="window.location.href='{{ route('delivery.support.show', $support->id) }}'"
                                data-searchable="{{ strtolower(($support->client->basicData->name_1 ?? '') . ' ' . ($support->ticket_id ?? '') . ' ' . ($support->name ?? '')) }}">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ $support->client->basicData->name_1 ?? 'N/A' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    {{ $support->name ?? 'Support #' . $support->id }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($support->ticket_id)
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-purple-100 text-purple-800">
                                            #{{ $support->ticket_id }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-24 bg-gray-200 rounded-full h-2">
                                            <div class="h-2 rounded-full" style="width: {{ $progress }}%; background-color: {{ $progressColor }};"></div>
                                        </div>
                                        <span class="text-sm font-bold" style="color: {{ $progressColor }}">{{ number_format($progress, 1) }}%</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $support->start_date ? $support->start_date->format('d M') : '-' }} - {{ $support->end_date ? $support->end_date->format('d M Y') : '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $support->support_method ?? 'N/A' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    <h3 class="mt-2 text-sm font-medium text-gray-900">No support items</h3>
                                    <p class="mt-1 text-sm text-gray-500">Get started by creating a new support item.</p>
                                </td>
                            </tr>
                        @endforelse
                        <tr id="desktop-no-results-row" class="hidden">
                            <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                                No support items match your search.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
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

    const searchInput = document.getElementById('support-search');

    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase().trim();

        // Mobile search
        const mobileCards = document.querySelectorAll('.support-card');
        const mobileNoResults = document.getElementById('mobile-no-results');
        let mobileVisible = 0;

        mobileCards.forEach(card => {
            const searchable = card.dataset.searchable || '';
            if (searchable.includes(searchTerm)) {
                card.style.display = '';
                mobileVisible++;
            } else {
                card.style.display = 'none';
            }
        });

        if (mobileNoResults) {
            mobileNoResults.classList.toggle('hidden', mobileVisible > 0 || mobileCards.length === 0);
        }

        // Desktop search
        const desktopRows = document.querySelectorAll('.support-row');
        const desktopNoResults = document.getElementById('desktop-no-results-row');
        let desktopVisible = 0;

        desktopRows.forEach(row => {
            const searchable = row.dataset.searchable || '';
            if (searchable.includes(searchTerm)) {
                row.style.display = '';
                desktopVisible++;
            } else {
                row.style.display = 'none';
            }
        });

        if (desktopNoResults) {
            desktopNoResults.classList.toggle('hidden', desktopVisible > 0 || desktopRows.length === 0);
        }
    });

    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            this.value = '';
            this.dispatchEvent(new Event('input'));
        }
    });
});
</script>
@endsection

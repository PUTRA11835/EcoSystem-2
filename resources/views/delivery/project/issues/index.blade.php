@extends('dashboard')
@section('title', 'Project Issues')
@section('page-title', 'Project Delivery')
@section('page-subtitle', 'Manage project risks and issues')

@push('styles')
<style>
    .primary-focus:focus { border-color: var(--primary-color) !important; box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.15) !important; outline: none !important; }
</style>
@endpush

@section('content')
    {{-- Sub Navigation Tabs --}}
    <div class="mb-6 bg-white rounded-lg shadow-sm border border-gray-200">
        <nav class="flex space-x-1 p-1" aria-label="Tabs">
            <a href="{{ route('projects.index') }}"
               class="flex-1 sm:flex-none px-4 py-2.5 text-sm font-medium text-center rounded-lg transition-all
                      {{ Request::routeIs('projects.index') ? 'primary-gradient text-white shadow-sm' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100' }}">
                <i class="fas fa-project-diagram mr-2"></i>
                <span>Project</span>
            </a>
            <a href="{{ route('issues.index') }}"
               class="flex-1 sm:flex-none px-4 py-2.5 text-sm font-medium text-center rounded-lg transition-all
                      {{ Request::routeIs('issues.*') ? 'primary-gradient text-white shadow-sm' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100' }}">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <span>Issues</span>
            </a>
            <a href="{{ route('planning.index') }}"
               class="flex-1 sm:flex-none px-4 py-2.5 text-sm font-medium text-center rounded-lg transition-all
                      {{ Request::routeIs('planning.*') ? 'primary-gradient text-white shadow-sm' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100' }}">
                <i class="fas fa-tasks mr-2"></i>
                <span>Planning</span>
            </a>
        </nav>
    </div>

    {{-- Flash Notifications --}}
    @if(session('success'))
        <script>document.addEventListener('DOMContentLoaded',()=>showNotification(@json(session('success')),'success'));</script>
    @endif
    @if(session('error'))
        <script>document.addEventListener('DOMContentLoaded',()=>showNotification(@json(session('error')),'error'));</script>
    @endif

    <div class="bg-white shadow-sm rounded-lg overflow-hidden">
        <div class="px-4 sm:px-6 py-4 bg-gray-50 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">
                All Project Issues
            </h3>
        </div>

        {{-- Search Bar --}}
        <div class="p-4">
            <input type="search" id="issue-search"
                   placeholder="Search by customer, issue, action, status..."
                   class="block w-full border-gray-300 rounded-md shadow-sm primary-focus transition text-sm sm:text-base">
        </div>

        {{-- MOBILE VIEW: Card Layout --}}
        <div class="block lg:hidden px-4 pb-4">
            <div id="mobile-issue-list" class="space-y-4">
                @forelse($issues as $issue)
                    {{-- Tambahkan onclick ke div.card utama --}}
                    <div class="issue-card bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow cursor-pointer"
                         @if($issue->delivery_project) onclick="window.location.href='{{ route('issues.show', $issue->delivery_project->id) }}'" @endif
                         data-searchable-content="{{ strtolower(($issue->delivery_project?->client?->basicData?->name_1 ?? '') . ' ' . $issue->highlight_issue . ' ' . $issue->action . ' ' . $issue->status . ' ' . $issue->complexity . ' ' . ($issue->delivery_project?->status ?? '')) }}">
                        {{-- Card Header --}}
                        <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <h4 class="text-base font-semibold text-gray-900">
                                        {{ $issue->delivery_project?->client?->basicData?->name_1 ?? 'N/A' }}
                                    </h4>
                                    <div class="flex flex-wrap gap-2 mt-2">
                                        {{-- Issue Status Badge --}}
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                            {{ $issue->status === 'To Be Discussed' ? 'bg-yellow-100 text-yellow-800' : 
                                               ($issue->status === 'To Be Confirmed' ? 'bg-blue-100 text-blue-800' :
                                               ($issue->status === 'Open' ? 'bg-green-100 text-green-800' :
                                               ($issue->status === 'Closed' ? 'bg-gray-100 text-gray-800' : 'bg-gray-100 text-gray-800'))) }}">
                                            {{ $issue->status }}
                                        </span>
                                        {{-- Complexity Badge --}}
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                            {{ $issue->complexity === 'Medium' ? 'bg-yellow-100 text-yellow-800' : 
                                               ($issue->complexity === 'Low' ? 'bg-gray-100 text-gray-800' :
                                               ($issue->complexity === 'High' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800')) }}">
                                            {{ $issue->complexity }}
                                        </span>
                                        {{-- Project Status Badge --}}
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                            {{ ($issue->delivery_project?->status ?? '') === 'Closed' ? 'bg-green-100 text-green-800' :
                                               (($issue->delivery_project?->status ?? '') === 'In Progress' ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800') }}">
                                            Project: {{ $issue->delivery_project?->status ?? 'N/A' }}
                                        </span>
                                    </div>
                                </div>
                                {{-- Hapus tombol aksi (ikon mata) --}}
                                {{-- <a href="{{ route('issues.show', $issue->delivery_project->id) }}" 
                                   class="ml-2 p-2 text-indigo-600 hover:bg-indigo-50 rounded-lg transition">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </a> --}}
                            </div>
                        </div>
                        
                        {{-- Card Body --}}
                        <div class="px-4 py-3 space-y-3">
                            {{-- Date Info --}}
                            <div class="flex flex-wrap justify-between text-sm">
                                <div>
                                    <span class="text-gray-500">Date:</span>
                                    <span class="font-medium text-gray-900 ml-1">{{ $issue->created_at->format('d M Y') }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-500">Due:</span>
                                    <span class="font-medium text-gray-900 ml-1">{{ \Carbon\Carbon::parse($issue->due_date)->format('d M Y') }}</span>
                                    @if(!in_array($issue->status, ['Done', 'Closed']) && \Carbon\Carbon::parse($issue->due_date)->isPast())
                                        <span class="text-xs font-semibold text-red-600 ml-1">Overdue</span>
                                    @endif
                                </div>
                            </div>

                            {{-- Issue Details --}}
                            <div class="space-y-2">
                                <div>
                                    <p class="text-sm text-gray-500">Issue:</p>
                                    <p class="text-sm font-medium text-gray-900 mt-1">{{ $issue->highlight_issue }}</p>
                                </div>

                                <div>
                                    <p class="text-sm text-gray-500">Action:</p>
                                    <p class="text-sm font-medium text-gray-900 mt-1">{{ $issue->action }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No issues found</h3>
                        <p class="mt-1 text-sm text-gray-500">There are no issues recorded in the system yet.</p>
                    </div>
                @endforelse

                {{-- No Results Message --}}
                <div id="mobile-no-results" class="hidden text-center py-8">
                    <p class="text-sm text-gray-500">No issues match your search.</p>
                </div>
            </div>
        </div>

        {{-- DESKTOP VIEW: Table Layout --}}
        <div class="hidden lg:block overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Customer
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Date
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Issue
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Action
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Complexity
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Project Status
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Due Date
                        </th>
                        {{-- Hapus kolom "Aksi" dari thead
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Aksi
                        </th>
                        --}}
                    </tr>
                </thead>
                <tbody id="desktop-issue-table-body" class="bg-white divide-y divide-gray-200">
                    @forelse($issues as $issue)
                        {{-- Tambahkan onclick dan cursor-pointer ke tr --}}
                        <tr class="issue-row hover:bg-gray-50 transition-colors cursor-pointer"
                            @if($issue->delivery_project) onclick="window.location.href='{{ route('issues.show', $issue->delivery_project->id) }}'" @endif
                            data-searchable-content="{{ strtolower(($issue->delivery_project?->customer?->basicData?->name_1 ?? '') . ' ' . $issue->highlight_issue . ' ' . $issue->action . ' ' . $issue->status . ' ' . $issue->complexity . ' ' . ($issue->delivery_project?->status ?? '')) }}">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">
                                    {{ $issue->delivery_project?->client?->basicData?->name_1 ?? 'N/A' }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $issue->created_at->format('d M Y') }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                <div class="max-w-xs truncate" title="{{ $issue->highlight_issue }}">
                                    {{ $issue->highlight_issue }}
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                <div class="max-w-xs truncate" title="{{ $issue->action }}">
                                    {{ $issue->action }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                    {{ $issue->status === 'To Be Discussed' ? 'bg-yellow-100 text-yellow-800' : 
                                       ($issue->status === 'To Be Confirmed' ? 'bg-blue-100 text-blue-800' :
                                       ($issue->status === 'Open' ? 'bg-green-100 text-green-800' :
                                       ($issue->status === 'Closed' ? 'bg-gray-100 text-gray-800' : 'bg-gray-100 text-gray-800'))) }}">
                                    {{ $issue->status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                    {{ $issue->complexity === 'Medium' ? 'bg-yellow-100 text-yellow-800' : 
                                       ($issue->complexity === 'Low' ? 'bg-gray-100 text-gray-800' :
                                       ($issue->complexity === 'High' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800')) }}">
                                    {{ $issue->complexity }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                    {{ ($issue->delivery_project?->status ?? '') === 'Closed' ? 'bg-green-100 text-green-800' :
                                       (($issue->delivery_project?->status ?? '') === 'In Progress' ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800') }}">
                                    {{ $issue->delivery_project?->status ?? 'N/A' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <div class="flex flex-col">
                                    <span>{{ \Carbon\Carbon::parse($issue->due_date)->format('d M Y') }}</span>
                                    @if(!in_array($issue->status, ['Done', 'Closed']) && \Carbon\Carbon::parse($issue->due_date)->isPast())
                                        <span class="text-xs font-semibold text-red-600 mt-1">
                                            Overdue
                                        </span>
                                    @endif
                                </div>
                            </td>
                            {{-- Hapus seluruh <td> untuk kolom "Aksi"
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <a href="{{ route('issues.show', $issue->project->id) }}" 
                                   class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded text-indigo-700 bg-indigo-100 hover:bg-indigo-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    Detail
                                </a>
                            </td>
                            --}}
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900">No issues found</h3>
                                <p class="mt-1 text-sm text-gray-500">There are no issues recorded in the system yet.</p>
                            </td>
                        </tr>
                    @endforelse
                    {{-- No Results Row --}}
                    <tr id="desktop-no-results-row" class="hidden">
                        <td colspan="8" class="px-6 py-4 text-center text-sm text-gray-500">
                            No issues match your search.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <style>
        /* Smooth scroll behavior */
        html {
            scroll-behavior: smooth;
        }

        /* Card animation on mobile */
        @media (max-width: 1023px) {
            .issue-card {
                animation: fadeIn 0.3s ease-in;
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Custom scrollbar for desktop table */
        @media (min-width: 1024px) {
            .overflow-x-auto::-webkit-scrollbar {
                height: 8px;
            }
            
            .overflow-x-auto::-webkit-scrollbar-track {
                background: #f3f4f6;
                border-radius: 4px;
            }
            
            .overflow-x-auto::-webkit-scrollbar-thumb {
                background: #9ca3af;
                border-radius: 4px;
            }
            
            .overflow-x-auto::-webkit-scrollbar-thumb:hover {
                background: #6b7280;
            }
        }

        /* Mobile view specific styles */
        @media (max-width: 1023px) {
            .issue-card {
                touch-action: manipulation;
            }
        }

        /* Tambahkan gaya hover untuk menunjukkan bahwa elemen bisa diklik */
        .issue-card:hover,
        .issue-row:hover {
            background-color: #f3f4f6;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('issue-search');
            
            // Search functionality for both mobile and desktop
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase().trim();
                
                // Mobile search
                const mobileCards = document.querySelectorAll('.issue-card');
                const mobileNoResults = document.getElementById('mobile-no-results');
                let mobileVisibleCards = 0;
                
                mobileCards.forEach(card => {
                    const searchableContent = card.dataset.searchableContent;
                    
                    if (searchableContent.includes(searchTerm)) {
                        card.style.display = '';
                        mobileVisibleCards++;
                    } else {
                        card.style.display = 'none';
                    }
                });
                
                if (mobileNoResults) {
                    if (mobileVisibleCards === 0 && mobileCards.length > 0) {
                        mobileNoResults.classList.remove('hidden');
                    } else {
                        mobileNoResults.classList.add('hidden');
                    }
                }
                
                // Desktop search
                const desktopRows = document.querySelectorAll('#desktop-issue-table-body tr.issue-row');
                const desktopNoResults = document.getElementById('desktop-no-results-row');
                let desktopVisibleRows = 0;
                
                desktopRows.forEach(row => {
                    const searchableContent = row.dataset.searchableContent;
                    
                    if (searchableContent.includes(searchTerm)) {
                        row.style.display = '';
                        desktopVisibleRows++;
                    } else {
                        row.style.display = 'none';
                    }
                });
                
                if (desktopNoResults) {
                    if (desktopVisibleRows === 0 && desktopRows.length > 0) {
                        desktopNoResults.classList.remove('hidden');
                    } else {
                        desktopNoResults.classList.add('hidden');
                    }
                }
            });

            // Clear search on ESC key
            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    this.value = '';
                    this.dispatchEvent(new Event('input'));
                }
            });

            // Prevent double-tap zoom on mobile for buttons
            let lastTouchEnd = 0;
            document.addEventListener('touchend', function(event) {
                const now = Date.now();
                if (now - lastTouchEnd <= 300) {
                    event.preventDefault();
                }
                lastTouchEnd = now;
            }, false);
        });
    </script>
@endsection
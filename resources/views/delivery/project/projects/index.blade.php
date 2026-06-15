@extends('dashboard')
@section('title', 'Project Delivery')
@section('page-title', 'Project Delivery')
@section('page-subtitle', 'Manage all delivery projects')
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
        {{-- Card Header --}}
        <div class="px-4 sm:px-6 py-4 bg-gray-50 border-b border-gray-200">
            <div class="flex justify-between items-center flex-wrap gap-4">
                <h3 class="text-lg font-medium text-gray-900">
                    All Delivery Projects
                </h3>
                @if($can('delivery-project.add-new'))
                <a href="{{ route('projects.create') }}"
                   class="primary-gradient text-white font-bold py-2 px-4 rounded-lg hover:opacity-90 transition duration-300 text-sm sm:text-base">
                    <span class="hidden sm:inline">Add New</span>
                    <span class="sm:hidden">+ Add</span>
                </a>
                @endif
            </div>
        </div>
        {{-- Search Bar — selaras dengan style input pada Create form (rounded-lg + border eksplisit + padding seragam) --}}
        <div class="p-4">
            <input type="search" id="project-search"
                placeholder="Search by customer, type, description, PIC, or status..."
                class="block w-full border border-gray-300 rounded-lg shadow-sm primary-focus transition text-sm px-4 py-2.5">
        </div>
        {{-- MOBILE VIEW: Card Layout --}}
        <div class="block lg:hidden px-4 pb-4">
            <div id="mobile-project-list" class="space-y-4">
                @forelse($projects as $project)
                    <div class="project-card bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow cursor-pointer"
                         onclick="window.location.href='{{ route('projects.show', $project->id) }}'"
                         data-searchable-content="{{ strtolower(($project->client->basicData->name_1 ?? '') . ' ' . $project->project_type . ' ' . $project->description . ' ' . $project->project_owner . '' . $project->status . ' ' . $project->category) }}">
                        {{-- Card Header --}}
                        <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <h4 class="text-base font-semibold text-gray-900">
                                        {{ $project->client->basicData->name_1 ?? 'N/A' }}
                                    </h4>
                                    <p class="text-sm text-gray-600 mt-1">{{ $project->project_type }}</p>
                                </div>
                            </div>
                        </div>
                        {{-- Card Body --}}
                        <div class="px-4 py-3 space-y-3">
                            {{-- Project Description --}}
                            <div>
                                <p class="text-sm text-gray-500">Description:</p>
                                <p class="text-sm font-medium text-gray-900 mt-1">{{ $project->description }}</p>
                            </div>
                            {{-- Project Owner --}}
                            <div>
                                <p class="text-sm text-gray-500">Project Owner:</p>
                                <p class="text-sm font-medium text-gray-900 mt-1">{{ $project->project_owner ?? 'N/A' }}</p>
                            </div>
                            {{-- Status Badges --}}
                            <div class="flex flex-wrap gap-2">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                    {{ $project->category === 'Open' ? 'bg-yellow-100 text-yellow-800' :
                                       ($project->category === 'In Process' ? 'bg-blue-100 text-blue-800' : 
                                       ($project->category === 'Closed' ? 'bg-green-100 text-green-800' :'bg-blue-100 text-blue-800')) }}">
                                    {{ $project->category ?? 'N/A' }}
                                </span>
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                    {{ $project->status === 'On Track' ? 'bg-green-100 text-green-800' :
                                       ($project->status === 'At Risk' ? 'bg-yellow-100 text-yellow-800' :
                                       ($project->status === 'At Critical' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800')) }}">
                                    {{ $project->status ?? 'N/A' }}
                                </span>
                            </div>
                            {{-- Last Update --}}
                            <div class="pt-2 border-t border-gray-200">
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-gray-500">Last Update:</span>
                                    <span class="font-medium text-gray-900">{{ $project->updated_at->format('d M Y') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No project data</h3>
                        <p class="mt-1 text-sm text-gray-500">Start by adding a new project.</p>
                    </div>
                @endforelse
                {{-- No Results Message --}}
                <div id="mobile-no-results" class="hidden text-center py-8">
                    <p class="text-sm text-gray-500">No projects match your search.</p>
                </div>
            </div>
        </div>
        
        {{-- DESKTOP VIEW: Table Layout dengan Fixed Height dan Scroll --}}
        <div class="hidden lg:block px-4 pb-4">
            {{-- Table Container dengan scroll --}}
            <div class="table-container border border-gray-200 rounded-lg" 
                 style="height: 500px; max-height: 500px; overflow-y: scroll; overflow-x: auto;">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50 sticky top-0 z-10">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50 border-b">
                                Customer
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50 border-b">
                                Project Type
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50 border-b">
                                Description
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50 border-b">
                                PIC/Project Manager
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50 border-b">
                                Project Status
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50 border-b">
                                Last Update Date
                            </th>
                        </tr>
                    </thead>
                        <tbody id="desktop-project-table-body" class="bg-white divide-y divide-gray-200">
                            @forelse($projects as $project)
                                <tr class="project-row hover:bg-gray-50 transition-colors cursor-pointer"
                                    onclick="window.location.href='{{ route('projects.show', $project->id) }}'"
                                    data-searchable-content="{{ strtolower(($project->client->basicData->name_1 ?? '') . ' ' . $project->project_type . ' ' . $project->description . ' ' . $project->project_owner . '' . $project->status . ' ' . $project->category) }}">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            {{ $project->client->basicData->name_1 ?? 'N/A' }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $project->project_type }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        <div class="max-w-xs truncate" title="{{ $project->description }}">
                                            {{ $project->description }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $project->project_owner ?? 'N/A' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <div class="flex flex-col space-y-1">
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                                {{ $project->category === 'Open' ? 'bg-yellow-100 text-yellow-800' :
                                                   ($project->category === 'In Process' ? 'bg-blue-100 text-blue-800' : 
                                                   ($project->category === 'Closed' ? 'bg-green-100 text-green-800' :'bg-blue-100 text-blue-800')) }}">
                                                {{ $project->category ?? 'N/A' }}
                                            </span>
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                                {{ $project->status === 'On Track' ? 'bg-green-100 text-green-800' :
                                                   ($project->status === 'At Risk' ? 'bg-yellow-100 text-yellow-800' :
                                                   ($project->status === 'At Critical' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800')) }}">
                                                {{ $project->status ?? 'N/A' }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $project->updated_at->format('d M Y') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                        <h3 class="mt-2 text-sm font-medium text-gray-900">No project data</h3>
                                        <p class="mt-1 text-sm text-gray-500">Start by adding a new project.</p>
                                    </td>
                                </tr>
                            @endforelse
                            {{-- No Results Row --}}
                            <tr id="desktop-no-results-row" class="hidden">
                                <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                                    No projects match your search.
                                </td>
                            </tr>
                        </tbody>
                </table>
            </div>
        </div>
    </div>

    <style>
        /* Smooth scroll behavior */
        html {
            scroll-behavior: smooth;
        }

        /* Fixed Table Container Styles untuk Desktop */
        @media (min-width: 1024px) {
            /* Pastikan container table memiliki scrollbar */
            .table-container {
                position: relative;
                max-height: 600px !important;
                height: 600px !important;
                overflow-y: auto !important;
                overflow-x: auto !important;
            }

            /* Sticky header */
            .table-container thead th {
                position: sticky;
                top: 0;
                z-index: 20;
                background-color: #f9fafb;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }

            /* Custom scrollbar untuk table container */
            .table-container::-webkit-scrollbar {
                width: 12px;
                height: 12px;
            }

            .table-container::-webkit-scrollbar-track {
                background: #f3f4f6;
                border-radius: 6px;
            }

            .table-container::-webkit-scrollbar-thumb {
                background: #9ca3af;
                border-radius: 6px;
                border: 2px solid #f3f4f6;
            }

            .table-container::-webkit-scrollbar-thumb:hover {
                background: #6b7280;
            }

            .table-container::-webkit-scrollbar-corner {
                background: #f3f4f6;
            }

            /* Ensure table takes full width */
            .table-container table {
                width: 100%;
                min-width: 100%;
            }

            /* Firefox scrollbar */
            .table-container {
                scrollbar-width: thin;
                scrollbar-color: #9ca3af #f3f4f6;
            }
        }

        /* Card animation on mobile */
        @media (max-width: 1023px) {
            .project-card {
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

        /* Mobile view specific styles */
        @media (max-width: 1023px) {
            .project-card {
                touch-action: manipulation;
            }
        }

        /* Status badges styling */
        .inline-flex {
            width: fit-content;
        }

        /* Row hover effect */
        .table-container tbody tr:hover {
            background-color: #f9fafb;
        }

        /* Ensure the table container is visible */
        .table-container {
            display: block !important;
            visibility: visible !important;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('project-search');
            const tableContainer = document.querySelector('.table-container');
            
            // Adjust table container height based on viewport (optional)
            function adjustTableHeight() {
                if (window.innerWidth >= 1024 && tableContainer) {
                    // Calculate available height
                    const windowHeight = window.innerHeight;
                    const containerTop = tableContainer.getBoundingClientRect().top;
                    const footerSpace = 50; // Space for footer/padding
                    const calculatedHeight = windowHeight - containerTop - footerSpace;
                    
                    // Set height between 400px and 700px
                    const finalHeight = Math.min(700, Math.max(400, calculatedHeight));
                    tableContainer.style.height = finalHeight + 'px';
                    tableContainer.style.maxHeight = finalHeight + 'px';
                }
            }

            // Call on load and resize
            adjustTableHeight();
            window.addEventListener('resize', adjustTableHeight);

            // Search functionality for both mobile and desktop
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase().trim();
                
                // Mobile search
                const mobileCards = document.querySelectorAll('.project-card');
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
                const desktopRows = document.querySelectorAll('#desktop-project-table-body tr.project-row');
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

                // Reset scroll position after search
                if (tableContainer) {
                    tableContainer.scrollTop = 0;
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

            // Add scroll to top on double-click header
            const tableHeader = document.querySelector('.table-container thead');
            if (tableHeader) {
                tableHeader.addEventListener('dblclick', function() {
                    if (tableContainer) {
                        tableContainer.scrollTo({
                            top: 0,
                            behavior: 'smooth'
                        });
                    }
                });
            }

            // Debug: Check if container has correct styles
            if (tableContainer) {
            }
        });
    </script>
@endsection
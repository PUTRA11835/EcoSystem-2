@extends('dashboard')
@section('title', 'Phase Management - ' . $project->name)
@section('page-title', 'Phase Management')
@section('page-subtitle', $project->name ?? 'Manage project phases')

{{-- ✅ ADD SWEETALERT2 CDN --}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/frappe-gantt@0.6.1/dist/frappe-gantt.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">

@section('content')
<div class="min-h-screen bg-gray-50 pb-20 sm:pb-6" data-project-id="{{ $project->id }}">
    <script>
        window.currentProjectId = {{ $project->id }};
        // Contract window — used to constrain planning (activity) date pickers.
        window.projectContractDates = {
            start: @json($project->contract_start_date ? \Carbon\Carbon::parse($project->contract_start_date)->format('Y-m-d') : null),
            end:   @json($project->contract_end_date ? \Carbon\Carbon::parse($project->contract_end_date)->format('Y-m-d') : null)
        };
    </script>
    <!-- ======================================== -->
         <!-- MOBILE HEADER (< lg) -->
    <!-- ======================================== -->
    <div class="lg:hidden bg-white shadow-sm border-b border-gray-200 sticky top-0 z-30">
        <div class="px-4 py-3">
            {{-- Back Button & Title --}}
            <div class="flex items-center justify-between mb-3">
                <a href="{{ route('planning.index') }}" 
                   class="flex items-center text-gray-600 hover:text-gray-900">
                    <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    <span class="text-sm font-medium">Back</span>
                </a>
                
                {{-- Menu Button --}}
                <button onclick="toggleMobileMenu()" class="p-2 rounded-lg hover:bg-gray-100">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
            </div>
            
            {{-- Project Title --}}
            <h1 class="text-lg font-bold text-gray-900 mb-1">Phase Management</h1>
            <p class="text-xs text-gray-600 line-clamp-2">{{ $project->description }}</p>
            
            {{-- View Toggle (Mobile) --}}
            <div class="mt-3 flex rounded-lg shadow-sm overflow-hidden" role="group">
                <button type="button" 
                        data-view="table"
                        onclick="window.switchView('table')"
                        class="view-toggle flex-1 px-3 py-2 text-xs font-medium text-white bg-blue-600 border border-gray-200">
                    <svg class="inline-block w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM11 5a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V5zM11 13a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                    </svg>
                    Table
                </button>
                <button type="button"
                        data-view="gantt"
                        onclick="window.switchView('gantt')"
                        class="view-toggle flex-1 px-3 py-2 text-xs font-medium text-gray-900 bg-white border border-gray-200">
                    <svg class="inline-block w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"></path>
                    </svg>
                    Gantt
                </button>
            </div>
        </div>
        
        {{-- Mobile Menu Dropdown --}}
        <div id="mobileMenu" class="hidden border-t border-gray-200 bg-gray-50">
            <div class="px-4 py-3 space-y-2">
                <button onclick="openPhaseConfigModal(); toggleMobileMenu();" 
                        class="w-full flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white rounded-lg border border-gray-300 hover:bg-gray-50">
                    <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    Configure Phases
                </button>
                
                <button onclick="toggleMobileExportMenu()" 
                        class="w-full flex items-center justify-between px-3 py-2 text-sm font-medium text-gray-700 bg-white rounded-lg border border-gray-300 hover:bg-gray-50">
                    <div class="flex items-center">
                        <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        Export
                    </div>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>
                
                {{-- Export Submenu --}}
                <div id="mobileExportMenu" class="hidden pl-8 space-y-2 mt-2">
                    <a href="{{ route('planning.export.table-pdf', $project) }}" 
                       class="flex items-center px-3 py-2 text-xs text-gray-600 hover:bg-gray-100 rounded">
                        <svg class="w-3 h-3 mr-2 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"></path>
                        </svg>
                        Table PDF
                    </a>
                    <a href="{{ route('planning.export.table-excel', $project) }}" 
                       class="flex items-center px-3 py-2 text-xs text-gray-600 hover:bg-gray-100 rounded">
                        <svg class="w-3 h-3 mr-2 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 2a2 2 0 00-2 2v8a2 2 0 002 2h6a2 2 0 002-2V6.414A2 2 0 0016.414 5L14 2.586A2 2 0 0012.586 2H9z"></path>
                        </svg>
                        Table Excel
                    </a>
                    <a href="{{ route('planning.export.gantt-pdf', $project) }}" 
                       class="flex items-center px-3 py-2 text-xs text-gray-600 hover:bg-gray-100 rounded">
                        <svg class="w-3 h-3 mr-2 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"></path>
                        </svg>
                        Gantt PDF
                    </a>
                    <a href="{{ route('planning.export.gantt-excel', $project) }}" 
                       class="flex items-center px-3 py-2 text-xs text-gray-600 hover:bg-gray-100 rounded">
                        <svg class="w-3 h-3 mr-2 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 2a2 2 0 00-2 2v8a2 2 0 002 2h6a2 2 0 002-2V6.414A2 2 0 0016.414 5L14 2.586A2 2 0 0012.586 2H9z"></path>
                        </svg>
                        Gantt Excel
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================
         DESKTOP HEADER (>= lg)
         ======================================== -->
    <div class="hidden lg:block bg-white shadow-sm border-b border-gray-200 mb-6 rounded-lg mx-4 sm:mx-6 lg:mx-8 mt-4">
        <div class="px-6 py-4">
            <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center space-y-4 lg:space-y-0">
                <div class="flex-1 min-w-0 pr-4">
                    <div class="flex items-center space-x-3">
                        <h2 class="text-2xl font-bold text-gray-900">Phase Management</h2>
                        <span class="px-3 py-1 text-sm bg-blue-100 text-blue-800 rounded-full flex-shrink-0">
                            {{ $project->phases()->count() }} Phases
                        </span>
                    </div>
                    
                    {{-- Project Description with Tooltip --}}
                    <div class="relative mt-1">
                        <p class="text-sm text-gray-600 line-clamp-2 cursor-pointer hover:text-gray-800 transition" 
                           id="projectDescription"
                           data-full-text="{{ $project->description }}">
                            {{ Str::limit($project->description, 150) }}
                        </p>
                        
                        {{-- Custom Tooltip --}}
                        <div id="descriptionTooltip" 
                             class="hidden fixed z-[9999] px-4 py-3 text-sm text-white bg-gray-900 rounded-lg shadow-xl max-w-md break-words"
                             style="pointer-events: none;">
                            <div class="relative">
                                <p class="text-sm leading-relaxed whitespace-pre-wrap" id="tooltipText"></p>
                                <div id="tooltipArrow" class="absolute w-2 h-2 bg-gray-900 transform rotate-45"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-2 flex items-center space-x-4 text-xs text-gray-500">
                        <span class="flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                            </svg>
                            {{ $project->client->basicData->name_1 ?? 'N/A' }}
                        </span>
                        <span class="flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            {{ $project->contract_start_date ? \Carbon\Carbon::parse($project->contract_start_date)->format('d M Y') : 'Not set' }}
                        </span>
                    </div>
                </div>
                
                <div class="flex items-center space-x-3 flex-shrink-0 flex-wrap gap-2">
                    <button onclick="openPhaseConfigModal()" 
                            class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        <svg class="mr-2 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        Configure Phases
                    </button>
                    
                    <div class="inline-flex rounded-md shadow-sm" role="group">
                        <button type="button" 
                                data-view="table"
                                onclick="window.switchView('table')"
                                class="view-toggle px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-gray-200 rounded-l-lg hover:bg-blue-700">
                            <svg class="inline-block w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM11 5a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V5zM11 13a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                            </svg>
                            Table View
                        </button>
                        <button type="button"
                                data-view="gantt"
                                onclick="window.switchView('gantt')"
                                class="view-toggle px-4 py-2 text-sm font-medium text-gray-900 bg-white border border-gray-200 hover:bg-gray-100">
                            <svg class="inline-block w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"></path>
                            </svg>
                            Gantt Chart
                        </button>

                        <button type="button"
                                data-view="scurve"
                                onclick="window.switchView('scurve')"
                                class="view-toggle px-4 py-2 text-sm font-medium text-gray-900 bg-white border border-gray-200 rounded-r-lg hover:bg-gray-100">
                            <svg class="inline-block w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"></path>
                            </svg>
                            S-Curve
                        </button>

                        <button type="button" 
                                id="exportMenuButton"
                                onclick="toggleExportMenu()"
                                class="inline-flex items-center ml-1 px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            <svg class="mr-2 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Export
                            <svg class="ml-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                    </div>

                    {{-- Export Menu Dropdown --}}
                    <div id="exportMenu" class="hidden absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50">
                        <div class="py-1" role="menu">
                            <div class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase">Table View</div>
                            <a href="{{ route('planning.export.table-pdf', $project) }}" 
                               class="group flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" 
                               role="menuitem">
                                <svg class="mr-3 h-5 w-5 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"></path>
                                </svg>
                                Export as PDF
                            </a>
                            <a href="{{ route('planning.export.table-excel', $project) }}" 
                               class="group flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" 
                               role="menuitem">
                                <svg class="mr-3 h-5 w-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9 2a2 2 0 00-2 2v8a2 2 0 002 2h6a2 2 0 002-2V6.414A2 2 0 0016.414 5L14 2.586A2 2 0 0012.586 2H9z"></path>
                                </svg>
                                Export as Excel
                            </a>
                            
                            <div class="border-t border-gray-100"></div>
                            
                            <div class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase">Gantt Chart</div>
                            <a href="{{ route('planning.export.gantt-pdf', $project) }}" 
                               class="group flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" 
                               role="menuitem">
                                <svg class="mr-3 h-5 w-5 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"></path>
                                </svg>
                                Export as PDF
                            </a>
                            <a href="{{ route('planning.export.gantt-excel', $project) }}" 
                               class="group flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" 
                               role="menuitem">
                                <svg class="mr-3 h-5 w-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9 2a2 2 0 00-2 2v8a2 2 0 002 2h6a2 2 0 002-2V6.414A2 2 0 0016.414 5L14 2.586A2 2 0 0012.586 2H9z"></path>
                                </svg>
                                Export as Excel
                            </a>

                            <div class="border-t border-gray-100"></div>
        
                            {{-- ✅ NEW: S-Curve Export --}}
                            <div class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase">S-Curve</div>
                            <a href="{{ route('planning.export.scurve-pdf', $project) }}" 
                            class="group flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" 
                            role="menuitem">
                                <svg class="mr-3 h-5 w-5 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"></path>
                                </svg>
                                Export as PDF
                            </a>
                            <a href="{{ route('planning.export.scurve-excel', $project) }}" 
                            class="group flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" 
                            role="menuitem">
                                <svg class="mr-3 h-5 w-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9 2a2 2 0 00-2 2v8a2 2 0 002 2h6a2 2 0 002-2V6.414A2 2 0 0016.414 5L14 2.586A2 2 0 0012.586 2H9z"></path>
                                </svg>
                                Export as Excel
                            </a>
                        </div>
                    </div>
                    
                    <a href="{{ route('planning.index') }}" 
                       class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        <svg class="-ml-1 mr-2 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Back
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Progress Overview (Responsive) --}}
    <div class="px-4 sm:px-6 lg:px-8 mb-4">
        @include('delivery.project.project-planning.phase.partials.progress-overview', ['project' => $project])
    </div>

    {{-- Modals --}}
    @include('delivery.project.project-planning.phase.partials.stage-modal')
    @include('delivery.project.project-planning.phase.partials.activity-modal')
    @include('delivery.project.project-planning.phase.partials.phase-modal', ['project' => $project])
    @include('delivery.project.project-planning.phase.partials.quick-modal', ['project' => $project])

    {{-- Activity Detail Drawer (Table View only) --}}
    @include('delivery.project.project-planning.phase.partials.activity-drawer')

    {{-- Content Views --}}
    <div class="px-4 sm:px-6 lg:px-8">
        <div id="tableViewContainer" class="view-container">
            @include('delivery.project.project-planning.phase.partials.table-view', ['project' => $project])
        </div>

        <div id="ganttViewContainer" class="view-container hidden">
            @include('delivery.project.project-planning.phase.partials.gantt-view', ['project' => $project])
        </div>

        <div id="scurveViewContainer" class="view-container hidden">
            @include('delivery.project.project-planning.phase.partials.scurve-view', ['project' => $project])
        </div>
    </div>
</div>

{{-- Scripts --}}
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/frappe-gantt@0.6.1/dist/frappe-gantt.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>

{{-- HolidayCalendar helper: fetch + cache + working-day utils + Flatpickr factory --}}
<script>
window.HolidayCalendar = (function() {
    let _holidayDateSet = new Set();   // 'YYYY-MM-DD' lookup
    let _holidayMeta    = {};          // 'YYYY-MM-DD' → {name, type}
    let _loaded         = false;
    let _loadPromise    = null;

    function isWeekend(d) {
        const day = d.getDay();
        return day === 0 || day === 6;
    }

    function toISO(d) {
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const dd = String(d.getDate()).padStart(2, '0');
        return `${y}-${m}-${dd}`;
    }

    function isNonWorkingDay(d) {
        if (isWeekend(d)) return true;
        return _holidayDateSet.has(toISO(d));
    }

    function holidayInfo(d) {
        return _holidayMeta[toISO(d)] || null;
    }

    /**
     * Add N working days to start (inclusive: 1 day = start == end).
     */
    function addWorkingDays(start, n) {
        const date = new Date(start.getTime());
        if (n <= 0) return date;
        let remaining = n - 1;
        while (remaining > 0) {
            date.setDate(date.getDate() + 1);
            if (!isNonWorkingDay(date)) remaining--;
        }
        return date;
    }

    /**
     * Count working days between start and end (inclusive both ends).
     */
    function countWorkingDays(start, end) {
        if (end < start) return 0;
        let count = 0;
        const cursor = new Date(start.getTime());
        while (cursor <= end) {
            if (!isNonWorkingDay(cursor)) count++;
            cursor.setDate(cursor.getDate() + 1);
        }
        return count;
    }

    /**
     * Load holidays for [from..to] (years). Memoized.
     */
    function load(from, to) {
        if (_loaded) return Promise.resolve();
        if (_loadPromise) return _loadPromise;

        const y = new Date().getFullYear();
        from = from || (y - 1);
        to   = to   || (y + 2);

        _loadPromise = axios.get(`/api/holidays?from=${from}&to=${to}`)
            .then(res => {
                (res.data.holidays || []).forEach(h => {
                    _holidayDateSet.add(h.date);
                    _holidayMeta[h.date] = { name: h.name, type: h.type };
                });
                _loaded = true;
            })
            .catch(err => {
                console.warn('HolidayCalendar: failed to load holidays', err);
                _loaded = true; // don't keep retrying
            });

        return _loadPromise;
    }

    /**
     * Initialize a Flatpickr instance on an input with weekends + holidays disabled.
     * Returns the flatpickr instance.
     */
    function initPicker(input, options) {
        if (!input) return null;
        if (typeof flatpickr === 'undefined') {
            console.warn('Flatpickr not loaded');
            return null;
        }

        const cfg = Object.assign({
            dateFormat: 'd/m/Y',
            allowInput: true,
            disableMobile: true,
            // 'static' → tampilkan bulan sebagai <span> bukan <select>,
            // sehingga label bulan selalu update saat navigasi dan tidak ada
            // dropdown yang overlap/muncul di belakang tanggal.
            monthSelectorType: 'static',
            // Pastikan kalender selalu di-append ke body agar positioning
            // benar di dalam modal (tidak terpengaruh overflow/scroll kontainer).
            appendTo: document.body,
            disable: [
                function(date) { return isNonWorkingDay(date); }
            ],
            onDayCreate: function(_, __, ___, dayElem) {
                const d = dayElem.dateObj;
                if (isWeekend(d)) {
                    dayElem.classList.add('fp-weekend');
                }
                const info = holidayInfo(d);
                if (info) {
                    dayElem.classList.add('fp-holiday');
                    dayElem.title = info.name;
                }
            }
        }, options || {});

        return flatpickr(input, cfg);
    }

    return {
        load: load,
        isNonWorkingDay: isNonWorkingDay,
        holidayInfo: holidayInfo,
        addWorkingDays: addWorkingDays,
        countWorkingDays: countWorkingDays,
        initPicker: initPicker,
        toISO: toISO,
    };
})();

// Pre-load holidays as soon as the page is ready
document.addEventListener('DOMContentLoaded', function() {
    window.HolidayCalendar.load();
});
</script>

<style>
/* Flatpickr customization for Indonesian holidays */
.flatpickr-day.fp-weekend:not(.flatpickr-disabled) { color: #ef4444; }
.flatpickr-day.fp-holiday { color: #dc2626 !important; font-weight: 600; }
.flatpickr-day.flatpickr-disabled.fp-holiday {
    color: #dc2626 !important;
    background: #fef2f2;
    text-decoration: line-through;
}
.flatpickr-day.flatpickr-disabled.fp-weekend {
    color: #9ca3af !important;
}

/*
 * Fix: Flatpickr di dalam modal
 * - z-index tinggi agar kalender muncul di atas backdrop modal (z-50 = 50)
 * - monthSelectorType:'static' → .cur-month adalah <span>, bukan <select>,
 *   sehingga tidak ada dropdown yang overlap dengan tanggal
 */
.flatpickr-calendar {
    z-index: 99999 !important;
}
/* Pastikan header bulan/tahun tidak tertutup elemen lain di dalam kalender */
.flatpickr-months {
    position: relative;
    z-index: 1;
}
/* Prev/next arrow selalu dapat diklik */
.flatpickr-prev-month,
.flatpickr-next-month {
    position: relative;
    z-index: 2;
    cursor: pointer;
}
/* Nama bulan (static span) terlihat jelas */
.flatpickr-current-month .cur-month {
    font-weight: 600;
    pointer-events: none;
}
</style>

{{-- ✅ STEP 1: INITIALIZE PROJECT ID & GLOBAL VARIABLES FIRST --}}
<script>
(function() {
    'use strict';

    // ==========================================
    // Get Project ID with multiple fallbacks
    // ==========================================
    function getProjectId() {
        let pid = null;
        
        // Try from Blade variable
        @if(isset($project))
            pid = {{ $project->id }};
        @endif
        
        // Fallback: from data attribute
        if (!pid) {
            const container = document.querySelector('[data-project-id]');
            if (container && container.dataset.projectId) {
                pid = parseInt(container.dataset.projectId);
            }
        }
        
        // Fallback: from URL
        if (!pid) {
            const urlMatch = window.location.pathname.match(/planning\/(\d+)/);
            if (urlMatch) {
                pid = parseInt(urlMatch[1]);
            }
        }
        
        return pid;
    }

    // Set project ID globally
    const projectId = getProjectId();
    if (projectId) {
        window.projectId = projectId;
    } else {
        console.error('❌ Could not determine project ID!');
    }

    
    // Stage Modal Variables
    window.currentActivityId = null;
    window.currentStageId = null;
    window.currentStageName = null;
    window.stageFormMode = 'create';
    window.currentGroupId = null;

    // Activity Modal Variables  
    window.currentPhaseId = null;
    window.activityFormMode = 'create';
    window.currentTotalWeight = 0;
    window.maxAllowedWeight = 100;

    // Quick Modal Variables
    window.currentQuickType = null;
    window.currentQuickPhaseId = null;
    window.currentQuickParentId = null;
    window.currentQuickItemId = null;
    window.quickFormMode = 'create';

    // View Variables
    window.currentView = '{{ $viewConfig->default_view ?? "table" }}';
    window.ganttChart = null;
    window.phaseData = {
        vertical: @json($verticalPhases ?? []),
        horizontal: @json($horizontalPhases ?? [])
    };

})();
</script>

{{-- ✅ STEP 2: EDIT ITEM FUNCTION --}}
<script>
window.editItem = function(itemId) {
    
    if (!itemId || itemId === 'null' || itemId <= 0) {
        console.error('❌ Invalid item ID:', itemId);
        showNotification('Invalid item ID', 'error');
        return;
    }
    
    showNotification('Loading...', 'info');

    axios.get(`/planning/${window.projectId}/activities/${itemId}`)
        .then(response => {
            const item = response.data;
            
            if (item.is_group) {
                if (typeof openQuickModal === 'function') {
                    openQuickModal('group', item.phase_id, item.parent_id, item);
                } else {
                    console.error('❌ openQuickModal not found');
                    showNotification('Quick modal not available', 'error');
                }
            } else {
                if (typeof window.openActivityModal === 'function') {
                    window.openActivityModal(item.stage_id, item.parent_id, item.id);
                } else {
                    console.error('❌ openActivityModal not found');
                    showNotification('Activity modal not available', 'error');
                }
            }
        })
        .catch(error => {
            console.error('❌ Error loading item:', error);
            showNotification('Failed to load item: ' + (error.response?.data?.message || error.message), 'error');
        });
};

</script>

{{-- ✅ STEP 3: MAIN FUNCTIONS (switchView, selectPhase, etc) --}}
<script>
(function() {
    'use strict';


    window.tableDataLoaded = false;


    window.showNotification = function(message, type) {
        showToast(message, type || 'info');
    };

    // ==========================================
    // MANUAL REFRESH FUNCTION
    // ==========================================
    window.refreshTableData = function() {
        window.tableDataLoaded = false;
        loadTableData();
    };

    // ==========================================
    // SWITCH VIEW (Table / Gantt)
    // ==========================================
    window.switchView = function(view) {
        window.currentView = view;
        
        // Update button states
        document.querySelectorAll('.view-toggle').forEach(function(btn) {
            if (btn.dataset.view === view) {
                btn.classList.remove('bg-white', 'text-gray-900');
                btn.classList.add('bg-blue-600', 'text-white');
            } else {
                btn.classList.remove('bg-blue-600', 'text-white');
                btn.classList.add('bg-white', 'text-gray-900');
            }
        });
        
        // Toggle containers
        const tableContainer = document.getElementById('tableViewContainer');
        const ganttContainer = document.getElementById('ganttViewContainer');
        const scurveContainer = document.getElementById('scurveViewContainer');
        
        if (!tableContainer || !ganttContainer || !scurveContainer) {
            console.error('❌ View containers not found!');
            return;
        }
        
        if (view === 'table') {
            tableContainer.classList.remove('hidden');
            ganttContainer.classList.add('hidden');
            scurveContainer.classList.add('hidden');
            
            if (!window.tableDataLoaded) {
                loadTableData();
            }
        } else if (view === 'gantt') {
            tableContainer.classList.add('hidden');
            ganttContainer.classList.remove('hidden');
            scurveContainer.classList.add('hidden');
            
            if (typeof window.loadGanttChartView === 'function') {
                window.loadGanttChartView();
            }
        } else if (view === 'scurve') {
            tableContainer.classList.add('hidden');
            ganttContainer.classList.add('hidden');
            scurveContainer.classList.remove('hidden');
            
            if (typeof window.loadSCurveView === 'function') {
                window.loadSCurveView();
            }
        }
        
        saveViewPreference(view);
    };

    // ==========================================
    // LOAD TABLE DATA
    // ==========================================
    function loadTableData() {
        
        // Call the function from table-view partial
        if (typeof window.loadAllPhasesData === 'function') {
            window.loadAllPhasesData();
            window.tableDataLoaded = true;
        } else {
            console.error('❌ loadAllPhasesData function not found!');
        }
    }

    // ==========================================
    // SELECT PHASE (DEPRECATED - Not used anymore)
    // ==========================================
    window.selectPhase = function(phaseId) {
        // Keep for backward compatibility but don't do anything
    };


    window.openPhaseConfigModal = function() {
        const modal = document.getElementById('phaseConfigModal');
        if (modal) {
            modal.classList.remove('hidden');
        } else {
            console.warn('⚠️ Phase config modal not found');
        }
    };

    // ==========================================
    // SAVE VIEW PREFERENCE
    // ==========================================
    function saveViewPreference(view) {
        if (!window.projectId) {
            console.warn('⚠️ Cannot save view preference: Project ID not available');
            return;
        }
        
        const data = {
            default_view: view,
            _token: document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}'
        };
        
        
        axios.post('/planning/' + window.projectId + '/view-config', data)
            .then(function(response) {
            })
            .catch(function(error) {
                console.error('❌ Failed to save view preference:', error);
                
                if (error.response) {
                    console.error('Response data:', error.response.data);
                    console.error('Response status:', error.response.status);
                }
            });
    }

    // ==========================================
    // DOM READY - INITIALIZE
    // ==========================================
    document.addEventListener('DOMContentLoaded', function() {
        
        if (window.currentView) {
            switchView(window.currentView);
        } else {
            switchView('table');
        }
    });

})();
</script>

{{-- ✅ MOBILE MENU TOGGLE --}}
<script>
function toggleMobileMenu() {
    const menu = document.getElementById('mobileMenu');
    if (menu) menu.classList.toggle('hidden');
}

function toggleMobileExportMenu() {
    const menu = document.getElementById('mobileExportMenu');
    if (menu) menu.classList.toggle('hidden');
}

// Close mobile menu when clicking outside
document.addEventListener('click', function(e) {
    const menu = document.getElementById('mobileMenu');
    const menuButton = e.target.closest('[onclick*="toggleMobileMenu"]');
    
    if (!menuButton && menu && !menu.contains(e.target) && !menu.classList.contains('hidden')) {
        menu.classList.add('hidden');
    }
});
</script>

{{-- ✅ TOOLTIP SCRIPT --}}
<script>
(function() {
    const descElement = document.getElementById('projectDescription');
    const tooltip = document.getElementById('descriptionTooltip');
    const tooltipText = document.getElementById('tooltipText');
    const tooltipArrow = document.getElementById('tooltipArrow');
    
    if (!descElement || !tooltip) return;
    
    let hideTimeout;
    const TOOLTIP_OFFSET = 12;
    const ARROW_SIZE = 8;
    const VIEWPORT_PADDING = 16;
    
    function calculateTooltipPosition() {
        const rect = descElement.getBoundingClientRect();
        const tooltipRect = tooltip.getBoundingClientRect();
        
        const viewportWidth = window.innerWidth;
        const viewportHeight = window.innerHeight;
        
        let top, left;
        let arrowPosition = 'bottom';
        
        const spaceAbove = rect.top;
        const spaceBelow = viewportHeight - rect.bottom;
        const tooltipHeight = tooltipRect.height;
        
        if (spaceAbove >= tooltipHeight + TOOLTIP_OFFSET + VIEWPORT_PADDING) {
            top = rect.top - tooltipHeight - TOOLTIP_OFFSET;
            arrowPosition = 'bottom';
        } else if (spaceBelow >= tooltipHeight + TOOLTIP_OFFSET + VIEWPORT_PADDING) {
            top = rect.bottom + TOOLTIP_OFFSET;
            arrowPosition = 'top';
        } else {
            top = (viewportHeight - tooltipHeight) / 2;
            arrowPosition = 'left';
        }
        
        const tooltipWidth = tooltipRect.width;
        left = rect.left;
        
        if (left + tooltipWidth > viewportWidth - VIEWPORT_PADDING) {
            left = rect.right - tooltipWidth;
        }
        
        if (left < VIEWPORT_PADDING) {
            left = VIEWPORT_PADDING;
        }
        
        tooltip.style.top = top + 'px';
        tooltip.style.left = left + 'px';
        
        positionArrow(arrowPosition, rect, left, top, tooltipWidth, tooltipHeight);
    }
    
    function positionArrow(position, elementRect, tooltipLeft, tooltipTop, tooltipWidth, tooltipHeight) {
        tooltipArrow.style.removeProperty('top');
        tooltipArrow.style.removeProperty('bottom');
        tooltipArrow.style.removeProperty('left');
        tooltipArrow.style.removeProperty('right');
        
        const elementCenter = elementRect.left + (elementRect.width / 2);
        const arrowLeft = Math.max(ARROW_SIZE * 2, Math.min(
            elementCenter - tooltipLeft - ARROW_SIZE,
            tooltipWidth - ARROW_SIZE * 3
        ));
        
        if (position === 'bottom') {
            tooltipArrow.style.bottom = '-4px';
            tooltipArrow.style.left = arrowLeft + 'px';
            tooltipArrow.style.transform = 'rotate(45deg)';
        } else if (position === 'top') {
            tooltipArrow.style.top = '-4px';
            tooltipArrow.style.left = arrowLeft + 'px';
            tooltipArrow.style.transform = 'rotate(45deg)';
        } else if (position === 'left') {
            tooltipArrow.style.left = '-4px';
            tooltipArrow.style.top = '50%';
            tooltipArrow.style.transform = 'translateY(-50%) rotate(45deg)';
        }
    }
    
    function showTooltip() {
        clearTimeout(hideTimeout);
        
        const fullText = descElement.dataset.fullText;
        tooltipText.textContent = fullText;
        
        tooltip.classList.remove('hidden');
        
        requestAnimationFrame(() => {
            calculateTooltipPosition();
            
            setTimeout(() => {
                tooltip.style.opacity = '1';
                tooltip.style.transform = 'translateY(0) scale(1)';
            }, 10);
        });
    }
    
    function hideTooltip() {
        hideTimeout = setTimeout(() => {
            tooltip.style.opacity = '0';
            tooltip.style.transform = 'translateY(5px) scale(0.95)';
            
            setTimeout(() => {
                tooltip.classList.add('hidden');
            }, 200);
        }, 100);
    }
    
    descElement.addEventListener('mouseenter', showTooltip);
    descElement.addEventListener('mouseleave', hideTooltip);
    
    window.addEventListener('resize', () => {
        if (!tooltip.classList.contains('hidden')) {
            calculateTooltipPosition();
        }
    });
    
    window.addEventListener('scroll', () => {
        if (!tooltip.classList.contains('hidden')) {
            calculateTooltipPosition();
        }
    }, { passive: true });
})();
</script>


<script>
function toggleExportMenu() {
    const menu = document.getElementById('exportMenu');
    if (menu) menu.classList.toggle('hidden');
}

document.addEventListener('click', function(e) {
    const menu = document.getElementById('exportMenu');
    const button = document.getElementById('exportMenuButton');
    
    if (menu && button && !menu.contains(e.target) && !button.contains(e.target)) {
        if (!menu.classList.contains('hidden')) {
            menu.classList.add('hidden');
        }
    }
});
</script>


<script>
(function() {
    
    const requiredFunctions = [
        'switchView',
        'selectPhase',
        'openPhaseConfigModal',
        'showNotification',
        'editItem',
        'openStageModal',
        'closeStageModal',
        'openActivityModal',
        'closeActivityModal',
        'openQuickModal'
    ];
    
    let allLoaded = true;
    const missing = [];
    
    requiredFunctions.forEach(funcName => {
        const exists = typeof window[funcName] === 'function';
        
        if (!exists) {
            allLoaded = false;
            missing.push(funcName);
        }
    });
    
    if (allLoaded) {
    } else {
        console.error('❌ Missing functions:', missing);
        console.error('⚠️ Check if modal partials are included correctly');
    }
    
    if (typeof window.projectId !== 'undefined' && window.projectId !== null) {
    } else {
        console.error('❌ Project ID not set!');
    }
    
})();
</script>


<style>
#descriptionTooltip {
    opacity: 0;
    transform: translateY(5px) scale(0.95);
    transition: opacity 0.2s ease-in-out, transform 0.2s ease-in-out;
}

.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

#descriptionTooltip {
    will-change: transform, opacity;
}

@media (max-width: 1024px) {
    .gantt-sidebar {
        flex: 0 0 250px !important;
        width: 250px !important;
    }
    
    .gantt-wrapper {
        height: 400px !important;
    }
}

@media (max-width: 768px) {
    .gantt-sidebar {
        flex: 0 0 200px !important;
        width: 200px !important;
    }
    
    .gantt-header-row {
        font-size: 0.65rem !important;
    }
    
    .gantt-activity-row {
        font-size: 0.7rem !important;
    }
}

button, a {
    touch-action: manipulation;
}
</style>

@endsection
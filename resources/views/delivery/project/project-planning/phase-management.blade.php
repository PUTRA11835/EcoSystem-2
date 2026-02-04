@extends('dashboard')
@section('title', 'Phase Management - ' . $project->name)
@section('page-title', 'Phase Management')
@section('page-subtitle', $project->name ?? 'Kelola fase proyek')

{{-- ✅ ADD SWEETALERT2 CDN --}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/frappe-gantt@0.6.1/dist/frappe-gantt.min.css">

@section('content')
<div class="min-h-screen bg-gray-50 pb-20 sm:pb-6" data-project-id="{{ $project->id }}">
    <script>
        window.currentProjectId = {{ $project->id }};
        console.log('Project initialized:', window.currentProjectId);
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
                            {{ $project->start_date ? \Carbon\Carbon::parse($project->start_date)->format('d M Y') : 'Not set' }}
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

{{-- ✅ STEP 1: INITIALIZE PROJECT ID & GLOBAL VARIABLES FIRST --}}
<script>
(function() {
    'use strict';
    console.log('🔧 Step 1: Initializing global variables...');

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
            const urlMatch = window.location.pathname.match(/project-planning\/(\d+)/);
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
        console.log('✅ Project ID set:', projectId);
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

    console.log('✅ All global variables initialized:', {
        projectId: window.projectId,
        currentView: window.currentView,
        hasPhaseData: !!window.phaseData
    });
})();
</script>

{{-- ✅ STEP 2: EDIT ITEM FUNCTION --}}
<script>
window.editItem = function(itemId) {
    console.log('✏️ Edit item:', itemId);
    
    if (!itemId || itemId === 'null' || itemId <= 0) {
        console.error('❌ Invalid item ID:', itemId);
        showNotification('Invalid item ID', 'error');
        return;
    }
    
    showNotification('Loading...', 'info');

    axios.get(`/planning/${window.projectId}/activities/${itemId}`)
        .then(response => {
            const item = response.data;
            console.log('📦 Item data:', item);
            
            if (item.is_group) {
                console.log('✏️ Editing group:', item.name);
                if (typeof openQuickModal === 'function') {
                    openQuickModal('group', item.phase_id, item.parent_id, item);
                } else {
                    console.error('❌ openQuickModal not found');
                    showNotification('Quick modal not available', 'error');
                }
            } else {
                console.log('✏️ Editing activity:', item.name);
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

console.log('✅ editItem function loaded');
</script>

{{-- ✅ STEP 3: MAIN FUNCTIONS (switchView, selectPhase, etc) --}}
<script>
(function() {
    'use strict';
    console.log('🚀 Step 3: Loading main functions...');


    window.tableDataLoaded = false;


    window.showNotification = function(message, type) {
        type = type || 'info';
        console.log('📢 Notification [' + type + ']:', message);
        
        const existing = document.querySelectorAll('.notification-toast');
        existing.forEach(function(n) { n.remove(); });
        
        const notification = document.createElement('div');
        notification.className = 'notification-toast fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg z-50 transition-all ' + 
            (type === 'success' ? 'bg-green-500 text-white' :
             type === 'error' ? 'bg-red-500 text-white' :
             'bg-blue-500 text-white');
        notification.textContent = message;
        document.body.appendChild(notification);
        
        setTimeout(function() {
            notification.classList.add('opacity-0');
            setTimeout(function() { notification.remove(); }, 300);
        }, 3000);
    };

    // ==========================================
    // MANUAL REFRESH FUNCTION
    // ==========================================
    window.refreshTableData = function() {
        console.log('🔄 Manual refresh triggered');
        window.tableDataLoaded = false;
        loadTableData();
    };

    // ==========================================
    // SWITCH VIEW (Table / Gantt)
    // ==========================================
    window.switchView = function(view) {
        console.log('🔄 Switching view to:', view);
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
                console.log('📊 First time loading table data...');
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
        console.log('📊 Loading table data...');
        
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
        console.log('⚠️ selectPhase is deprecated - using hierarchical view');
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
        
        // ✅ FIXED: Send proper data with CSRF token
        const data = {
            default_view: view,
            _token: document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}'
        };
        
        console.log('💾 Saving view preference:', data);
        
        axios.post('/project-planning/' + window.projectId + '/view-config', data)
            .then(function(response) {
                console.log('✅ View preference saved:', response.data);
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
        console.log('📦 DOM Content Loaded - Initializing view...');
        
        // Initialize default view
        if (window.currentView) {
            switchView(window.currentView);
        } else {
            switchView('table');
        }
    });

    console.log('✅ Main functions loaded');
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

// Close export menu when clicking outside
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
    console.log('=== VERIFYING ALL FUNCTIONS ===');
    
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
        console.log(`  ${exists ? '✅' : '❌'} ${funcName}`);
        
        if (!exists) {
            allLoaded = false;
            missing.push(funcName);
        }
    });
    
    if (allLoaded) {
        console.log('✅ All required functions loaded successfully!');
    } else {
        console.error('❌ Missing functions:', missing);
        console.error('⚠️ Check if modal partials are included correctly');
    }
    
    // Check projectId
    if (typeof window.projectId !== 'undefined' && window.projectId !== null) {
        console.log('✅ Project ID available:', window.projectId);
    } else {
        console.error('❌ Project ID not set!');
    }
    
    console.log('=== END VERIFICATION ===');
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

/* Responsive adjustments */
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

/* Prevent zoom on double tap for mobile */
button, a {
    touch-action: manipulation;
}
</style>

@endsection
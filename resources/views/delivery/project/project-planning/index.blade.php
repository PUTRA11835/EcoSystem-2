<!-- resources/views/project-planning/index.blade.php -->
@extends('dashboard')
@section('title', 'Delivery Project Planning')
@section('page-title', 'Delivery Project')
@section('page-subtitle', 'Manage project planning')

@section('content')
<div class="min-h-screen bg-gray-50">
    {{-- Sub Navigation Tabs --}}
    <div class="mb-6 bg-white rounded-lg shadow-sm border border-gray-200">
        <nav class="flex space-x-1 p-1" aria-label="Tabs">
            <a href="{{ route('projects.index') }}"
               class="flex-1 sm:flex-none px-4 py-2.5 text-sm font-medium text-center rounded-lg transition-all
                      {{ Request::routeIs('projects.index') ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100' }}">
                <i class="fas fa-project-diagram mr-2"></i>
                <span>Project</span>
            </a>
            <a href="{{ route('issues.index') }}"
               class="flex-1 sm:flex-none px-4 py-2.5 text-sm font-medium text-center rounded-lg transition-all
                      {{ Request::routeIs('issues.*') ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100' }}">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <span>Issues</span>
            </a>
            <a href="{{ route('planning.index') }}"
               class="flex-1 sm:flex-none px-4 py-2.5 text-sm font-medium text-center rounded-lg transition-all
                      {{ Request::routeIs('planning.*') ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100' }}">
                <i class="fas fa-tasks mr-2"></i>
                <span>Planning</span>
            </a>
        </nav>
    </div>

    <div class="bg-white shadow-sm rounded-lg overflow-hidden">
        <div class="px-4 sm:px-6 py-4 bg-gray-50 border-b border-gray-200">
            <div class="flex justify-between items-center flex-wrap gap-4">
                <h3 class="text-lg font-medium text-gray-900">Project Planning List</h3>
                {{-- custom-dd manual untuk style konsisten. Hidden input pakai
                     id="statusFilter" supaya reading .value tetap jalan. JS
                     handler dipanggil via data-onchange (custom-dd tidak fire
                     event 'change' di hidden input). --}}
                <div class="custom-dd relative" data-onchange="onProjectStatusFilterChange" data-fixed="true" style="min-width:160px">
                    <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-4 py-2.5 bg-white border border-gray-300 rounded-lg shadow-sm text-sm hover:border-gray-400 transition-all text-left">
                        <span class="custom-dd-label text-gray-700">Semua Status</span>
                        <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <input type="hidden" id="statusFilter" value="">
                    <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:240px;">
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">Semua Status</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="On Track">On Track</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Monitoring">Monitoring</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="At Risk">At Risk</button>
                    </div>
                </div>
            </div>
        </div>
        {{-- Search Bar — selaras dengan style index lain --}}
        <div class="p-4">
            <input type="search" id="searchInput"
                   placeholder="Search project, client, or description..."
                   class="block w-full border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 transition text-sm px-4 py-2.5">
        </div>
        {{-- MOBILE VIEW: Card Layout --}}
        <div class="block lg:hidden px-4 pb-4">
            <div id="mobile-projects-container" class="space-y-4">
                @forelse($projects as $project)
                    @php
                        // Ambil status langsung dari model project
                        $statusText = $project->status ?? 'On Track';
                        $projectStatus = strtolower(str_replace(' ', '_', $statusText));
                        
                        // Mapping warna berdasarkan status
                        if ($projectStatus === 'delayed') {
                            $statusColor = '#ef4444';
                            $statusBgClass = 'bg-red-50';
                        } elseif ($projectStatus === 'completed') {
                            $statusColor = '#6366f1';
                            $statusBgClass = 'bg-indigo-50';
                        } elseif ($projectStatus === 'at_risk') {
                            $statusColor = '#f59e0b';
                            $statusBgClass = 'bg-yellow-50';
                        } elseif ($projectStatus === 'monitoring') {
                            $statusColor = '#fef9c3';
                            $statusBgClass = 'bg-yellow-50';
                        } else { // default: on_track
                            $statusColor = '#10b981';
                            $statusBgClass = 'bg-green-50';
                        }
                        
                        // ✅ METODE BARU: Hitung Overall Progress seperti di progress-overview
                        // Ambil semua groups untuk project ini
                        $groups = $project->plannings->where('is_group', true);
                        
                        // Ambil visible phases untuk project ini
                        $visiblePhases = $project->phases()
                            ->where('is_visible', true)
                            ->get();
                        
                        $totalPhaseWeight = 0;
                        $weightedPhaseProgress = 0;
                        
                        foreach ($visiblePhases as $phase) {
                            $phaseWeight = $phase->weight ?? 0;
                            
                            // Hitung progress phase dari groups
                            $phaseGroups = $groups->where('phase_id', $phase->id);
                            
                            if ($phaseGroups->count() > 0) {
                                $totalGroupWeight = 0;
                                $weightedGroupProgress = 0;
                                
                                foreach ($phaseGroups as $group) {
                                    $groupWeight = $group->calculated_weight ?? $group->weight ?? 0;
                                    $groupProgress = $group->calculated_progress ?? $group->progress_percentage ?? 0;
                                    
                                    $totalGroupWeight += $groupWeight;
                                    $weightedGroupProgress += ($groupProgress * $groupWeight);
                                }
                                
                                // Phase progress = weighted average dari groups
                                $phaseProgress = $totalGroupWeight > 0 
                                    ? ($weightedGroupProgress / $totalGroupWeight) 
                                    : 0;
                            } else {
                                $phaseProgress = 0;
                            }
                            
                            $totalPhaseWeight += $phaseWeight;
                            $weightedPhaseProgress += ($phaseProgress * $phaseWeight);
                        }
                        
                        // Overall progress = weighted average dari phases
                        $overallProgress = $totalPhaseWeight > 0 
                            ? round($weightedPhaseProgress / $totalPhaseWeight, 1) 
                            : 0;
                        
                        // Filter only real activities (not groups) untuk statistics
                        $realActivities = $project->plannings->where('is_group', false);
                        $totalActivities = $realActivities->count();
                        
                        // Count by status
                        $monitoringActivities = $realActivities->where('status', 'monitoring')->count();
                        $completedActivities = $realActivities->where('status', 'completed')->count();
                        $inProgressActivities = $realActivities->where('status', 'in_progress')->count();
                        $delayedActivities = $realActivities->where('status', 'delayed')->count();
                        $notStartedActivities = $realActivities->where('status', 'not_started')->count();
                        
                        // Perluas data yang bisa dicari
                        $searchableContent = strtolower(
                            ($project->name ?? '') . ' ' .
                            ($project->client->basicData->name_1 ?? 'N/A') . ' ' .
                            $statusText . ' ' .
                            ($project->project_type ?? '') . ' ' .
                            'progress ' . $overallProgress . '% ' .
                            ($project->budget ? number_format($project->budget, 0, '', '') : '') . ' ' .
                            ($project->created_at->format('d M Y')) . ' ' .
                            ($project->name ?? '') . ' ' .
                            ($project->id ?? '')
                        );
                        
                        // Tentukan URL tujuan
                        $targetUrl = route('planning.phases.index', $project);
                    @endphp
                    <div class="mobile-project-card bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow cursor-pointer {{ $delayedActivities > 0 ? 'border-red-200' : '' }}"
                         onclick="window.location.href='{{ $targetUrl }}'"
                         data-project-name="{{ e(strtolower($project->description)) }}"
                         data-project-status="{{ $statusText }}"
                         data-searchable-content="{{ e($searchableContent) }}">
                        {{-- Card Header --}}
                        <div class="{{ $statusBgClass }} px-4 py-3 border-b border-gray-200">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <h4 class="text-base font-semibold text-gray-900">{{ $project->name }}</h4>
                                    <p class="text-sm text-gray-600 mt-1">{{ $project->client->basicData->name_1 ?? 'N/A' }}</p>
                                </div>
                            </div>
                        </div>
                        {{-- Card Body --}}
                        <div class="px-4 py-3 space-y-3">
                            {{-- Status and Progress --}}
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="status-indicator status-{{ str_replace('_', '-', $projectStatus) }}"></div>
                                    <span class="text-sm font-medium text-gray-700">{{ $statusText }}</span>
                                </div>
                                <div class="flex items-center space-x-3">
                                    {{-- Progress Ring --}}
                                    <div class="relative w-12 h-12">
                                        <svg class="progress-ring w-12 h-12" viewBox="0 0 32 32">
                                            <circle cx="16" cy="16" r="14" fill="transparent" stroke="#e5e7eb" stroke-width="2"/>
                                            <circle cx="16" cy="16" r="14" fill="transparent" 
                                                    stroke="{{ $statusColor }}" 
                                                    stroke-width="2" 
                                                    stroke-dasharray="{{ 88 * $overallProgress / 100 }} 88"
                                                    stroke-linecap="round"/>
                                        </svg>
                                        <div class="absolute inset-0 flex items-center justify-center">
                                            <span class="text-xs font-bold text-gray-900">{{ $overallProgress }}%</span>
                                        </div>
                                    </div>
                                    {{-- Progress Bar --}}
                                    <div class="w-20 bg-gray-200 rounded-full h-2">
                                        <div class="h-2 rounded-full" 
                                             style="width: {{ $overallProgress }}%; background-color: {{ $statusColor }};"></div>
                                    </div>
                                </div>
                            </div>
                            {{-- Additional Info --}}
                            <div class="grid grid-cols-2 gap-3 text-sm">
                                <div>
                                    <span class="text-gray-500">Budget:</span>
                                    <p class="font-medium text-gray-900">Rp {{ number_format($project->budget ?? 0, 0, ',', '.') }}</p>
                                </div>
                                <div>
                                    <span class="text-gray-500">Dibuat:</span>
                                    <p class="font-medium text-gray-900">{{ $project->created_at->format('d M Y') }}</p>
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
                                            ⚠️ {{ $delayedActivities }} activities delayed
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
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No Project Planning Yet</h3>
                        <p class="mt-1 text-sm text-gray-500">Start by creating a planning for your first project</p>
                        <div class="mt-4">
                            <a href="{{ route('projects.index') }}" 
                               class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                <svg class="mr-2 -ml-1 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                </svg>
                                Lihat Projects
                            </a>
                        </div>
                    </div>
                @endforelse
                {{-- Mobile No Results --}}
                <div id="mobile-no-results" class="hidden text-center py-8">
                    <p class="text-sm text-gray-500">No projects match your search criteria.</p>
                </div>
            </div>
        </div>
        {{-- DESKTOP VIEW: Table Layout --}}
        <div class="hidden lg:block overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Project</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Progress</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Budget</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dibuat</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200" id="desktop-projects-table-body">
                    @forelse($projects as $project)
                        @php
                            // Ambil status langsung dari model project
                            $statusText = $project->status ?? 'On Track';
                            $projectStatus = strtolower(str_replace(' ', '_', $statusText));
                            
                            // Mapping warna berdasarkan status
                            if ($projectStatus === 'delayed') {
                                $statusColor = '#ef4444';
                                $statusBgClass = 'bg-red-50';
                            } elseif ($projectStatus === 'completed') {
                                $statusColor = '#6366f1';
                                $statusBgClass = 'bg-indigo-50';
                            } elseif ($projectStatus === 'at_risk') {
                                $statusColor = '#f59e0b';
                                $statusBgClass = 'bg-yellow-50';
                            } elseif ($projectStatus === 'monitoring') {
                                $statusColor = '#fef9c3';
                                $statusBgClass = 'bg-yellow-50';
                            } else { // default: on_track
                                $statusColor = '#10b981';
                                $statusBgClass = 'bg-green-50';
                            }
                            
                            // ✅ METODE BARU: Hitung Overall Progress seperti di progress-overview
                            // Ambil semua groups untuk project ini
                            $groups = $project->plannings->where('is_group', true);
                            
                            // Ambil visible phases untuk project ini
                            $visiblePhases = $project->phases()
                                ->where('is_visible', true)
                                ->get();
                            
                            $totalPhaseWeight = 0;
                            $weightedPhaseProgress = 0;
                            
                            foreach ($visiblePhases as $phase) {
                                $phaseWeight = $phase->weight ?? 0;
                                
                                // Hitung progress phase dari groups
                                $phaseGroups = $groups->where('phase_id', $phase->id);
                                
                                if ($phaseGroups->count() > 0) {
                                    $totalGroupWeight = 0;
                                    $weightedGroupProgress = 0;
                                    
                                    foreach ($phaseGroups as $group) {
                                        $groupWeight = $group->calculated_weight ?? $group->weight ?? 0;
                                        $groupProgress = $group->calculated_progress ?? $group->progress_percentage ?? 0;
                                        
                                        $totalGroupWeight += $groupWeight;
                                        $weightedGroupProgress += ($groupProgress * $groupWeight);
                                    }
                                    
                                    // Phase progress = weighted average dari groups
                                    $phaseProgress = $totalGroupWeight > 0 
                                        ? ($weightedGroupProgress / $totalGroupWeight) 
                                        : 0;
                                } else {
                                    $phaseProgress = 0;
                                }
                                
                                $totalPhaseWeight += $phaseWeight;
                                $weightedPhaseProgress += ($phaseProgress * $phaseWeight);
                            }
                            
                            // Overall progress = weighted average dari phases
                            $overallProgress = $totalPhaseWeight > 0 
                                ? round($weightedPhaseProgress / $totalPhaseWeight, 1) 
                                : 0;
                            
                            // Filter only real activities (not groups) untuk statistics
                            $realActivities = $project->plannings->where('is_group', false);
                            $totalActivities = $realActivities->count();
                            
                            // Count by status
                            $monitoringActivities = $realActivities->where('status', 'monitoring')->count();
                            $completedActivities = $realActivities->where('status', 'completed')->count();
                            $inProgressActivities = $realActivities->where('status', 'in_progress')->count();
                            $delayedActivities = $realActivities->where('status', 'delayed')->count();
                            $notStartedActivities = $realActivities->where('status', 'not_started')->count();
                            
                            // Perluas data yang bisa dicari
                            $searchableContent = strtolower(
                                ($project->name ?? '') . ' ' .
                                ($project->client->basicData->name_1 ?? 'N/A') . ' ' .
                                $statusText . ' ' .
                                ($project->project_type ?? '') . ' ' .
                                'progress ' . $overallProgress . '% ' .
                                ($project->budget ? number_format($project->budget, 0, '', '') : '') . ' ' .
                                ($project->created_at->format('d M Y')) . ' ' .
                                ($project->name ?? '') . ' ' .
                                ($project->id ?? '')
                            );
                            
                            // Tentukan URL tujuan
                            $targetUrl = route('planning.phases.index', $project);
                        @endphp
                        <tr data-project-name="{{ e(strtolower($project->description)) }}"
                            data-project-status="{{ $statusText }}"
                            data-searchable-content="{{ e($searchableContent) }}"
                            onclick="window.location.href='{{ $targetUrl }}'"
                            class="desktop-project-row cursor-pointer {{ $delayedActivities > 0 ? 'bg-red-50' : '' }} hover:bg-gray-50 transition-colors duration-150">
                            <!-- Project Name -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">{{ $project->name }}</div>
                            </td>
                            <!-- Client -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-600">{{ $project->client->basicData->name_1 ?? 'N/A' }}</div>
                            </td>
                            <!-- Progress Ring + Bar -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center space-x-3">
                                    <div class="relative w-12 h-12">
                                        <svg class="progress-ring w-12 h-12" viewBox="0 0 32 32">
                                            <circle cx="16" cy="16" r="14" fill="transparent" stroke="#e5e7eb" stroke-width="2"/>
                                            <circle cx="16" cy="16" r="14" fill="transparent" 
                                                    stroke="{{ $statusColor }}" 
                                                    stroke-width="2" 
                                                    stroke-dasharray="{{ 88 * $overallProgress / 100 }} 88"
                                                    stroke-linecap="round"/>
                                        </svg>
                                        <div class="absolute inset-0 flex items-center justify-center">
                                            <span class="text-xs font-bold text-gray-900">{{ $overallProgress }}%</span>
                                        </div>
                                    </div>
                                    <div class="w-20 bg-gray-200 rounded-full h-2">
                                        <div class="h-2 rounded-full" 
                                             style="width: {{ $overallProgress }}%; background-color: {{ $statusColor }};"></div>
                                    </div>
                                </div>
                            </td>
                            <!-- Status -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="status-indicator status-{{ str_replace('_', '-', $projectStatus) }}"></div>
                                    <span class="text-sm font-medium text-gray-700">{{ $statusText }}</span>
                                </div>
                            </td>
                            <!-- Budget -->
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                Rp {{ number_format($project->budget ?? 0, 0, ',', '.') }}
                            </td>
                            <!-- Created At -->
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $project->created_at->format('d M Y') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900">No Project Planning Yet</h3>
                                <p class="mt-1 text-sm text-gray-500">Start by creating a planning for your first project</p>
                            </td>
                        </tr>
                    @endforelse
                    {{-- Desktop No Results Row --}}
                    <tr id="desktop-no-results-row" class="hidden">
                        <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                            No projects match your search criteria.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <!-- Floating Action Button -->
    <div class="floating-action">
        <a href="{{ route('projects.index') }}" 
           class="bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 text-white w-14 h-14 sm:w-16 sm:h-16 rounded-full shadow-2xl hover:shadow-3xl transform hover:scale-105 transition-all duration-300 flex items-center justify-center mt-2">
            <i class="fas fa-plus text-lg sm:text-xl"></i>
        </a>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('searchInput');
    const statusFilter = document.getElementById('statusFilter');
    // Search functionality for both mobile and desktop
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            filterProjects(searchTerm, statusFilter ? statusFilter.value : '');
        });
        // Clear search on ESC key
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                this.value = '';
                filterProjects('', statusFilter ? statusFilter.value : '');
            }
        });
    }
    // Status filter — custom-dd memanggil onProjectStatusFilterChange via
    // data-onchange. Expose filterProjects ke window biar bisa dipanggil
    // handler global di luar scope DOMContentLoaded ini.
    window._filterProjects = function (searchTerm, selectedStatus) {
        filterProjects(searchTerm, selectedStatus);
    };

    // Init custom-dd untuk status filter
    if (typeof initCustomDropdowns === 'function') {
        initCustomDropdowns();
    }

    function filterProjects(searchTerm, selectedStatus) {
        // Apply to mobile cards
        const mobileCards = document.querySelectorAll('.mobile-project-card');
        const mobileNoResults = document.getElementById('mobile-no-results');
        let mobileVisibleCount = 0;
        mobileCards.forEach(card => {
            // Get searchable content - include all relevant text from the card
            const projectName = card.querySelector('h4') ? card.querySelector('h4').textContent.toLowerCase() : '';
            const clientName = card.querySelector('p.text-gray-600') ? card.querySelector('p.text-gray-600').textContent.toLowerCase() : '';
            const statusText = card.dataset.projectStatus ? card.dataset.projectStatus.toLowerCase() : '';
            const searchableContent = card.dataset.searchableContent ? card.dataset.searchableContent.toLowerCase() : '';
            // Combine all searchable fields
            const allContent = projectName + ' ' + clientName + ' ' + statusText + ' ' + searchableContent;
            const matchesSearch = searchTerm === '' || allContent.includes(searchTerm);
            const matchesStatus = selectedStatus === '' || card.dataset.projectStatus === selectedStatus;
            if (matchesSearch && matchesStatus) {
                card.style.display = '';
                mobileVisibleCount++;
            } else {
                card.style.display = 'none';
            }
        });
        // Show/hide mobile no results
        if (mobileNoResults) {
            if (mobileVisibleCount === 0 && mobileCards.length > 0) {
                mobileNoResults.classList.remove('hidden');
            } else {
                mobileNoResults.classList.add('hidden');
            }
        }
        // Apply to desktop rows
        const desktopRows = document.querySelectorAll('#desktop-projects-table-body tr.desktop-project-row');
        const desktopNoResults = document.getElementById('desktop-no-results-row');
        let desktopVisibleCount = 0;
        desktopRows.forEach(row => {
            // Get all text content from row cells
            const cells = row.querySelectorAll('td');
            let rowContent = '';
            cells.forEach(cell => {
                rowContent += ' ' + cell.textContent.toLowerCase();
            });
            // Also include data attributes
            const searchableContent = row.dataset.searchableContent ? row.dataset.searchableContent.toLowerCase() : '';
            const projectStatus = row.dataset.projectStatus || '';
            const allContent = rowContent + ' ' + searchableContent;
            const matchesSearch = searchTerm === '' || allContent.includes(searchTerm);
            const matchesStatus = selectedStatus === '' || projectStatus === selectedStatus;
            if (matchesSearch && matchesStatus) {
                row.style.display = '';
                desktopVisibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        // Show/hide desktop no results
        if (desktopNoResults) {
            if (desktopVisibleCount === 0 && desktopRows.length > 0) {
                desktopNoResults.classList.remove('hidden');
            } else {
                desktopNoResults.classList.add('hidden');
            }
        }
    }
    // Prevent double-tap zoom on mobile
    let lastTouchEnd = 0;
    document.addEventListener('touchend', function(event) {
        const now = Date.now();
        if (now - lastTouchEnd <= 300) {
            event.preventDefault();
        }
        lastTouchEnd = now;
    }, false);
});

// Dipanggil custom-dd via data-onchange="onProjectStatusFilterChange" setiap
// kali user pilih opsi status. Wrapper untuk memanggil filterProjects yang
// di-expose ke window dari DOMContentLoaded scope.
function onProjectStatusFilterChange() {
    const search   = document.getElementById('searchInput');
    const statusEl = document.getElementById('statusFilter');
    const term     = search ? search.value.toLowerCase().trim() : '';
    const status   = statusEl ? statusEl.value : '';
    if (typeof window._filterProjects === 'function') window._filterProjects(term, status);
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
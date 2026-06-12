{{-- ✅ FULL RESPONSIVE PROGRESS OVERVIEW - All Original Logic Preserved --}}
@php
    // Get all groups for this project
    $groups = $project->plannings->where('is_group', true);
    
    // Get visible phases
    $visiblePhases = $project->phases()
        ->where('is_visible', true)
        ->get();
    
    // Calculate overall progress
    // - "actual"  : real progress from completed work (group->calculated_progress)
    // - "planned" : schedule-based progress — where the project SHOULD be today
    //               based on weights and planned dates (group->planned_progress).
    // Both use the SAME phase/group weighting so they are directly comparable.
    $totalPhaseWeight = 0;
    $weightedPhaseProgress = 0;
    $weightedPhasePlanned  = 0;

    $phaseProgressData = [];

    foreach ($visiblePhases as $phase) {
        $phaseWeight = $phase->weight ?? 0;

        // Calculate phase progress from groups
        $phaseGroups = $groups->where('phase_id', $phase->id);

        if ($phaseGroups->count() > 0) {
            $totalGroupWeight = 0;
            $weightedGroupProgress = 0;
            $weightedGroupPlanned  = 0;

            foreach ($phaseGroups as $group) {
                $groupWeight = $group->calculated_weight ?? $group->weight ?? 0;
                $groupProgress = $group->calculated_progress ?? $group->progress_percentage ?? 0;
                $groupPlanned  = $group->planned_progress ?? 0;

                $totalGroupWeight += $groupWeight;
                $weightedGroupProgress += ($groupProgress * $groupWeight);
                $weightedGroupPlanned  += ($groupPlanned * $groupWeight);
            }

            $phaseProgress = $totalGroupWeight > 0
                ? ($weightedGroupProgress / $totalGroupWeight)
                : 0;
            $phasePlanned = $totalGroupWeight > 0
                ? ($weightedGroupPlanned / $totalGroupWeight)
                : 0;
        } else {
            $phaseProgress = 0;
            $phasePlanned  = 0;
        }

        $totalPhaseWeight += $phaseWeight;
        $weightedPhaseProgress += ($phaseProgress * $phaseWeight);
        $weightedPhasePlanned  += ($phasePlanned * $phaseWeight);

        $phaseProgressData[] = [
            'phase' => $phase,
            'progress' => round($phaseProgress),
            'planned'  => round($phasePlanned),
            'weight' => $phaseWeight
        ];
    }

    // Raw (unrounded) values — used for SPI/CPI so rounding artifacts don't
    // distort the indices. The displayed bars use the rounded versions below.
    $overallProgressRaw = $totalPhaseWeight > 0 ? $weightedPhaseProgress / $totalPhaseWeight : 0;
    $plannedProgressRaw = $totalPhaseWeight > 0 ? $weightedPhasePlanned / $totalPhaseWeight : 0;

    $overallProgress = round($overallProgressRaw);

    // Keep 1 decimal so half-points (e.g. 62.5%) are shown faithfully instead
    // of being rounded away — this value is meant to be compared against actual.
    $plannedProgress = round($plannedProgressRaw, 1);

    // Variance: positive = ahead of schedule, negative = behind schedule
    $progressVariance = round($overallProgress - $plannedProgress, 1);

    // =====================================================================
    // PERFORMANCE INDICES (EVM)
    //   SPI = Actual Progress / Planning Progress  (schedule efficiency)
    //   CPI = Earned Value / Actual Cost           (cost efficiency)
    //         where Earned Value = Actual Progress % × Budget (BAC)
    //
    // Status band (shared by both): >=0.95 On Track, 0.80-0.949 At Risk,
    //                               <0.80 At Critical. null = N/A (no baseline).
    // =====================================================================

    // SPI — needs a schedule baseline (planned > 0)
    $spi = $plannedProgressRaw > 0
        ? round($overallProgressRaw / $plannedProgressRaw, 2)
        : null;

    // Cost totals from the Cost tab. Sum only leaf rows (rows that are not a
    // parent of any other cost) so parent aggregates aren't double-counted.
    $allCosts       = \App\Models\DeliveryProjectCost::where('delivery_projects_id', $project->id)->get();
    $parentCostIds  = $allCosts->whereNotNull('parent_id')->pluck('parent_id')->unique();
    $leafCosts      = $allCosts->reject(fn ($c) => $parentCostIds->contains($c->id));

    $totalBudget = (float) $leafCosts->sum('budget');        // BAC
    $totalActual = (float) $leafCosts->sum('actual_amount'); // AC
    $earnedValue = ($overallProgressRaw / 100) * $totalBudget; // EV

    // CPI — needs actual cost spent (AC > 0)
    $cpi = $totalActual > 0
        ? round($earnedValue / $totalActual, 2)
        : null;

    // Shared helper: map an index value to a status band for display.
    $indexStatus = function ($index) {
        if ($index === null) {
            return ['label' => 'N/A', 'badge' => 'bg-gray-100 text-gray-500', 'ring' => 'ring-gray-300', 'bar' => 'bg-gray-400', 'card' => 'bg-gray-50 border-gray-300 text-gray-600'];
        }
        if ($index >= 0.95) {
            return ['label' => 'On Track', 'badge' => 'bg-green-100 text-green-700', 'ring' => 'ring-green-500', 'bar' => 'bg-green-600', 'card' => 'bg-green-50 border-green-500 text-green-800'];
        }
        if ($index >= 0.80) {
            return ['label' => 'At Risk', 'badge' => 'bg-yellow-100 text-yellow-700', 'ring' => 'ring-yellow-500', 'bar' => 'bg-yellow-500', 'card' => 'bg-yellow-50 border-yellow-500 text-yellow-800'];
        }
        return ['label' => 'At Critical', 'badge' => 'bg-red-100 text-red-700', 'ring' => 'ring-red-500', 'bar' => 'bg-red-600', 'card' => 'bg-red-50 border-red-500 text-red-800'];
    };

    $spiStatus = $indexStatus($spi);
    $cpiStatus = $indexStatus($cpi);

    // Calculate activity/stage/group statistics
    $allItems = collect();

    // Get all groups, stages, and activities
    foreach ($groups as $group) {
        // Add the group itself
        $allItems->push([
            'type' => 'group',
            'status' => $group->status ?? 'not_started',
            'progress' => $group->calculated_progress ?? $group->progress_percentage ?? 0
        ]);

        // Get all stages from this group
        $stages = \App\Models\ActivityStage::where('planning_id', $group->id)->get();

        foreach ($stages as $stage) {
            // Add the stage itself
            $allItems->push([
                'type' => 'stage',
                'status' => $stage->status ?? 'not_started',
                'progress' => $stage->progress ?? 0
            ]);

            // Add all activities in this stage
            $activities = \App\Models\DeliveryProjectActivity::where('stage_id', $stage->id)->get();
            foreach ($activities as $activity) {
                $allItems->push([
                    'type' => 'activity',
                    'status' => $activity->status ?? 'not_started',
                    'progress' => $activity->progress_percentage ?? 0
                ]);
            }
        }
    }

    // Count by status
    $completedCount = $allItems->where('status', 'completed')->count();
    $inProgressCount = $allItems->whereIn('status', ['in_progress', 'delayed', 'on_hold'])
        ->where('progress', '<', 100)->count();
    $notStartedCount = $allItems->where('status', 'not_started')
        ->where('progress', 0)->count();
    $totalItemsCount = $allItems->count();
@endphp

{{-- ✅ RESPONSIVE PROGRESS CARDS --}}
<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden mb-4">
    {{-- Header - Mobile Optimized --}}
    <div class="px-3 sm:px-4 py-2 sm:py-3 bg-gradient-to-r from-blue-600 to-indigo-600 border-b border-indigo-700">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <h3 class="text-sm sm:text-base font-semibold text-white">📊 Project Progress Overview</h3>
            <div class="flex items-center space-x-2">
                <div class="px-2 sm:px-3 py-1 bg-white bg-opacity-20 rounded-full">
                    <span class="text-xs sm:text-sm font-bold text-white">{{ $overallProgress }}%</span>
                </div>
                <span class="text-xs text-indigo-100 hidden sm:inline">Overall Progress</span>
            </div>
        </div>
    </div>

    {{-- Planning Progress Bar (schedule-based — where the project SHOULD be today) --}}
    <div class="px-3 sm:px-4 pt-3 sm:pt-4 pb-2 bg-gradient-to-r from-amber-50 to-orange-50 border-b border-amber-100">
        <div class="flex items-center justify-between mb-2">
            <span class="inline-flex items-center text-xs sm:text-sm font-medium text-gray-700">
                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 mr-1.5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                Planning Progress
                <span class="ml-1.5 text-[10px] sm:text-xs font-normal text-gray-400 hidden sm:inline">(target by today)</span>
            </span>
            <span class="text-base sm:text-xl font-bold text-amber-600">{{ $plannedProgress }}%</span>
        </div>

        <div class="w-full bg-amber-100 rounded-full h-3 sm:h-4 overflow-hidden">
            <div class="h-3 sm:h-4 rounded-full transition-all duration-500 bg-gradient-to-r from-amber-400 to-orange-500"
                 style="width: {{ $plannedProgress }}%">
                <div class="h-full w-full bg-gradient-to-r from-white to-transparent opacity-30"></div>
            </div>
        </div>
    </div>

    {{-- Overall Progress Bar --}}
    <div class="px-3 sm:px-4 py-3 sm:py-4 bg-gradient-to-r from-blue-50 to-indigo-50">
        <div class="flex items-center justify-between mb-2">
            <span class="inline-flex items-center text-xs sm:text-sm font-medium text-gray-700">
                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 mr-1.5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
                Overall Project Progress
                <span class="ml-1.5 text-[10px] sm:text-xs font-normal text-gray-400 hidden sm:inline">(actual)</span>
            </span>
            <span class="inline-flex items-center gap-2">
                @if($progressVariance > 0)
                    <span class="px-1.5 py-0.5 rounded text-[10px] sm:text-xs font-semibold bg-green-100 text-green-700">
                        +{{ $progressVariance }}% ahead
                    </span>
                @elseif($progressVariance < 0)
                    <span class="px-1.5 py-0.5 rounded text-[10px] sm:text-xs font-semibold bg-red-100 text-red-700">
                        {{ $progressVariance }}% behind
                    </span>
                @else
                    <span class="px-1.5 py-0.5 rounded text-[10px] sm:text-xs font-semibold bg-gray-100 text-gray-600">
                        on schedule
                    </span>
                @endif
                <span class="text-base sm:text-xl font-bold text-gray-900">{{ $overallProgress }}%</span>
            </span>
        </div>
        
        <div class="w-full bg-gray-200 rounded-full h-3 sm:h-4 overflow-hidden">
            <div class="h-3 sm:h-4 rounded-full transition-all duration-500 bg-gradient-to-r from-blue-500 to-indigo-600" 
                 style="width: {{ $overallProgress }}%">
                <div class="h-full w-full bg-gradient-to-r from-white to-transparent opacity-30"></div>
            </div>
        </div>
        
        {{-- Progress Status Text --}}
        <div class="mt-2 text-xs text-gray-600 text-center sm:text-left">
            @if($overallProgress >= 100)
                <span class="inline-flex items-center text-green-600 font-semibold">
                    <svg class="w-3 h-3 sm:w-4 sm:h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    Project Completed!
                </span>
            @elseif($overallProgress >= 75)
                <span class="text-blue-600 font-medium">🎯 Almost there! Final stretch</span>
            @elseif($overallProgress >= 50)
                <span class="text-indigo-600 font-medium">⚡ Making good progress</span>
            @elseif($overallProgress >= 25)
                <span class="text-yellow-600 font-medium">🚀 Getting started</span>
            @else
                <span class="text-gray-600">📋 Project in early stage</span>
            @endif
        </div>
    </div>

    {{-- Phase Progress Cards - Responsive Grid --}}
    <div class="px-3 sm:px-4 py-3 sm:py-4">
        <h4 class="text-xs sm:text-sm font-semibold text-gray-700 mb-3">Progress by Phase</h4>
        
        @if(count($phaseProgressData) > 0)
            {{-- Mobile: Vertical Stack, Tablet: 2-3 cols, Desktop: 5 cols (all phases in one row) --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-3">
                @foreach($phaseProgressData as $data)
                    @php
                        $phase = $data['phase'];
                        $phaseProgress = $data['progress'];
                        $phaseWeight = $data['weight'];
                        
                        // Determine status color
                        if ($phaseProgress >= 100) {
                            $statusClass = 'bg-green-100 border-green-500 text-green-800';
                            $ringClass = 'ring-green-500';
                            $barColor = 'bg-green-600';
                        } elseif ($phaseProgress >= 75) {
                            $statusClass = 'bg-blue-100 border-blue-500 text-blue-800';
                            $ringClass = 'ring-blue-500';
                            $barColor = 'bg-blue-600';
                        } elseif ($phaseProgress >= 50) {
                            $statusClass = 'bg-indigo-100 border-indigo-500 text-indigo-800';
                            $ringClass = 'ring-indigo-500';
                            $barColor = 'bg-indigo-600';
                        } elseif ($phaseProgress >= 25) {
                            $statusClass = 'bg-yellow-100 border-yellow-500 text-yellow-800';
                            $ringClass = 'ring-yellow-500';
                            $barColor = 'bg-yellow-600';
                        } else {
                            $statusClass = 'bg-gray-100 border-gray-300 text-gray-700';
                            $ringClass = 'ring-gray-300';
                            $barColor = 'bg-gray-500';
                        }
                    @endphp
                    
                    <div class="relative {{ $statusClass }} border-l-4 rounded-lg p-3 shadow-sm hover:shadow-md transition-all duration-200">
                        {{-- Phase Header --}}
                        <div class="flex items-start justify-between mb-2">
                            <div class="flex items-center space-x-2 flex-1 min-w-0">
                                <div class="w-3 h-3 sm:w-4 sm:h-4 rounded flex-shrink-0" style="background-color: {{ $phase->color }}"></div>
                                <div class="min-w-0 flex-1">
                                    <h5 class="text-xs sm:text-sm font-semibold truncate" title="{{ $phase->name }}">
                                        {{ $phase->name }}
                                    </h5>
                                    <p class="text-xs opacity-75">Weight: {{ number_format($phaseWeight, 1) }}%</p>
                                </div>
                            </div>
                            
                            {{-- Progress Badge --}}
                            <div class="flex-shrink-0 ml-2">
                                <span class="inline-flex items-center justify-center w-10 h-10 sm:w-12 sm:h-12 rounded-full ring-2 {{ $ringClass }} bg-white font-bold text-xs sm:text-sm">
                                    {{ $phaseProgress }}%
                                </span>
                            </div>
                        </div>
                        
                        {{-- Progress Bar --}}
                        <div class="w-full bg-white bg-opacity-50 rounded-full h-2 overflow-hidden">
                            <div class="{{ $barColor }} h-2 rounded-full transition-all duration-500" 
                                 style="width: {{ $phaseProgress }}%"></div>
                        </div>
                        
                        {{-- Status Icon (Mobile: Hidden, Desktop: Show) --}}
                        @if($phaseProgress >= 100)
                            <div class="absolute top-2 right-2 hidden sm:block">
                                <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            {{-- Empty State --}}
            <div class="text-center py-8 sm:py-12 text-gray-500">
                <svg class="mx-auto h-8 w-8 sm:h-12 sm:w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
                <p class="mt-2 text-xs sm:text-sm">No visible phases configured yet</p>
                <p class="text-xs text-gray-400 mt-1">Configure phases to see progress breakdown</p>
            </div>
        @endif

        {{-- Performance Indices: SPI & CPI --}}
        <div class="mt-4 pt-4 border-t border-gray-200">
            <h4 class="text-xs sm:text-sm font-semibold text-gray-700 mb-3">Performance Indices</h4>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">

                {{-- SPI — Schedule Performance Index --}}
                <div class="relative {{ $spiStatus['card'] }} border-l-4 rounded-lg p-3 shadow-sm hover:shadow-md transition-all duration-200">
                    <div class="flex items-start justify-between mb-2">
                        <div class="flex items-center space-x-2 flex-1 min-w-0">
                            <svg class="w-4 h-4 sm:w-5 sm:h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <div class="min-w-0 flex-1">
                                <h5 class="text-xs sm:text-sm font-bold">SPI</h5>
                                <p class="text-xs opacity-75">Schedule Performance Index</p>
                            </div>
                        </div>
                        <div class="flex-shrink-0 ml-2">
                            <span class="inline-flex items-center justify-center w-12 h-12 sm:w-14 sm:h-14 rounded-full ring-2 {{ $spiStatus['ring'] }} bg-white font-bold text-sm sm:text-base text-gray-900">
                                {{ $spi !== null ? number_format($spi, 2) : 'N/A' }}
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="px-2 py-0.5 rounded text-[10px] sm:text-xs font-semibold {{ $spiStatus['badge'] }}">
                            {{ $spiStatus['label'] }}
                        </span>
                        <span class="text-[10px] sm:text-xs text-gray-500">
                            Actual {{ $overallProgress }}% &divide; Planned {{ $plannedProgress }}%
                        </span>
                    </div>
                </div>

                {{-- CPI — Cost Performance Index --}}
                <div class="relative {{ $cpiStatus['card'] }} border-l-4 rounded-lg p-3 shadow-sm hover:shadow-md transition-all duration-200">
                    <div class="flex items-start justify-between mb-2">
                        <div class="flex items-center space-x-2 flex-1 min-w-0">
                            <svg class="w-4 h-4 sm:w-5 sm:h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <div class="min-w-0 flex-1">
                                <h5 class="text-xs sm:text-sm font-bold">CPI</h5>
                                <p class="text-xs opacity-75">Cost Performance Index</p>
                            </div>
                        </div>
                        <div class="flex-shrink-0 ml-2">
                            <span class="inline-flex items-center justify-center w-12 h-12 sm:w-14 sm:h-14 rounded-full ring-2 {{ $cpiStatus['ring'] }} bg-white font-bold text-sm sm:text-base text-gray-900">
                                {{ $cpi !== null ? number_format($cpi, 2) : 'N/A' }}
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="px-2 py-0.5 rounded text-[10px] sm:text-xs font-semibold {{ $cpiStatus['badge'] }}">
                            {{ $cpiStatus['label'] }}
                        </span>
                        <span class="text-[10px] sm:text-xs text-gray-500">
                            EV Rp {{ number_format($earnedValue, 0, ',', '.') }} &divide; AC Rp {{ number_format($totalActual, 0, ',', '.') }}
                        </span>
                    </div>
                    @if($cpi === null)
                        <p class="text-[10px] sm:text-xs text-gray-400 mt-1.5">No actual cost recorded yet (Cost tab).</p>
                    @endif
                </div>

            </div>
        </div>
    </div>

    {{-- Quick Stats Summary (Mobile: 2 cols, Tablet+: 4 cols) --}}
    @if(count($phaseProgressData) > 0)
        <div class="px-3 sm:px-4 py-3 sm:py-4 bg-gray-50 border-t border-gray-200">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="text-center">
                    <div class="text-lg sm:text-2xl font-bold text-gray-900">{{ count($phaseProgressData) }}</div>
                    <div class="text-xs text-gray-600">Total Phases</div>
                </div>

                <div class="text-center">
                    <div class="text-lg sm:text-2xl font-bold text-green-600">
                        {{ $completedCount }}
                    </div>
                    <div class="text-xs text-gray-600">Completed</div>
                </div>

                <div class="text-center">
                    <div class="text-lg sm:text-2xl font-bold text-blue-600">
                        {{ $inProgressCount }}
                    </div>
                    <div class="text-xs text-gray-600">In Progress</div>
                </div>

                <div class="text-center">
                    <div class="text-lg sm:text-2xl font-bold text-gray-600">
                        {{ $notStartedCount }}
                    </div>
                    <div class="text-xs text-gray-600">Not Started</div>
                </div>
            </div>
        </div>
    @endif
</div>

<style>
/* ✅ Responsive Progress Overview Animations */
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.grid > div {
    animation: slideIn 0.3s ease-out;
}

/* Stagger animation for grid items */
@media (min-width: 640px) {
    .grid > div:nth-child(1) { animation-delay: 0.05s; }
    .grid > div:nth-child(2) { animation-delay: 0.1s; }
    .grid > div:nth-child(3) { animation-delay: 0.15s; }
    .grid > div:nth-child(4) { animation-delay: 0.2s; }
    .grid > div:nth-child(5) { animation-delay: 0.25s; }
    .grid > div:nth-child(6) { animation-delay: 0.3s; }
}

/* Touch feedback for mobile */
@media (max-width: 640px) {
    .grid > div:active {
        transform: scale(0.98);
        transition: transform 0.1s ease;
    }
}

/* Line clamp for phase names */
.truncate {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Ensure animations work smoothly on mobile */
@media (prefers-reduced-motion: reduce) {
    * {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}
</style>
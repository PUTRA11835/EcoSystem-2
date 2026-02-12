{{-- RESPONSIVE PROGRESS OVERVIEW FOR SUPPORT --}}
@php
    // Get all groups for this support
    $groups = $support->planning->where('is_group', true);

    // Get visible phases
    $visiblePhases = $support->phases()
        ->where('is_visible', true)
        ->get();

    // Calculate overall progress
    $totalPhaseWeight = 0;
    $weightedPhaseProgress = 0;

    $phaseProgressData = [];

    foreach ($visiblePhases as $phase) {
        $phaseWeight = $phase->weight ?? 0;

        // Calculate phase progress from groups
        $phaseGroups = $groups->where('delivery_support_phase_id', $phase->id);

        if ($phaseGroups->count() > 0) {
            $totalGroupWeight = 0;
            $weightedGroupProgress = 0;

            foreach ($phaseGroups as $group) {
                $groupWeight = $group->calculated_weight ?? $group->weight ?? 0;
                $groupProgress = $group->calculated_progress ?? $group->progress_percentage ?? 0;

                $totalGroupWeight += $groupWeight;
                $weightedGroupProgress += ($groupProgress * $groupWeight);
            }

            $phaseProgress = $totalGroupWeight > 0
                ? ($weightedGroupProgress / $totalGroupWeight)
                : 0;
        } else {
            $phaseProgress = 0;
        }

        $totalPhaseWeight += $phaseWeight;
        $weightedPhaseProgress += ($phaseProgress * $phaseWeight);

        $phaseProgressData[] = [
            'phase' => $phase,
            'progress' => round($phaseProgress),
            'weight' => $phaseWeight
        ];
    }

    $overallProgress = $totalPhaseWeight > 0
        ? round($weightedPhaseProgress / $totalPhaseWeight)
        : round($support->calculated_progress ?? 0);

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
        $stages = \App\Models\DeliverySupportActivityStage::where('planning_id', $group->id)->get();

        foreach ($stages as $stage) {
            // Add the stage itself
            $allItems->push([
                'type' => 'stage',
                'status' => $stage->status ?? 'not_started',
                'progress' => $stage->progress ?? 0
            ]);

            // Add all activities in this stage
            $activities = \App\Models\DeliverySupportActivity::where('stage_id', $stage->id)->get();
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

{{-- RESPONSIVE PROGRESS CARDS --}}
<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden mb-4">
    {{-- Header - Mobile Optimized --}}
    <div class="px-3 sm:px-4 py-2 sm:py-3 bg-gradient-to-r from-blue-600 to-indigo-600 border-b border-indigo-700">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <h3 class="text-sm sm:text-base font-semibold text-white">Support Progress Overview</h3>
            <div class="flex items-center space-x-2">
                <div class="px-2 sm:px-3 py-1 bg-white bg-opacity-20 rounded-full">
                    <span class="text-xs sm:text-sm font-bold text-white">{{ $overallProgress }}%</span>
                </div>
                <span class="text-xs text-indigo-100 hidden sm:inline">Overall Progress</span>
            </div>
        </div>
    </div>

    {{-- Overall Progress Bar --}}
    <div class="px-3 sm:px-4 py-3 sm:py-4 bg-gradient-to-r from-blue-50 to-indigo-50">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs sm:text-sm font-medium text-gray-700">Overall Support Progress</span>
            <span class="text-base sm:text-xl font-bold text-gray-900">{{ $overallProgress }}%</span>
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
                    Support Completed!
                </span>
            @elseif($overallProgress >= 75)
                <span class="text-blue-600 font-medium">Almost there! Final stretch</span>
            @elseif($overallProgress >= 50)
                <span class="text-indigo-600 font-medium">Making good progress</span>
            @elseif($overallProgress >= 25)
                <span class="text-yellow-600 font-medium">Getting started</span>
            @else
                <span class="text-gray-600">Support in early stage</span>
            @endif
        </div>
    </div>

    {{-- Phase Progress Cards - Responsive Grid --}}
    <div class="px-3 sm:px-4 py-3 sm:py-4">
        <h4 class="text-xs sm:text-sm font-semibold text-gray-700 mb-3">Progress by Phase</h4>

        @if(count($phaseProgressData) > 0)
            {{-- Mobile: Vertical Stack, Tablet: 2 cols, Desktop: 3-4 cols --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
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

                        {{-- Status Icon (Desktop only) --}}
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
    </div>

    {{-- Quick Stats Summary (Mobile: 2 cols, Tablet+: 4 cols) --}}
    @if(count($phaseProgressData) > 0 || $totalItemsCount > 0)
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
/* Responsive Progress Overview Animations */
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

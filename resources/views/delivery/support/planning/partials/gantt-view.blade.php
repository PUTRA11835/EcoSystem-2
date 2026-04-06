{{-- RESPONSIVE GANTT CHART VIEW for Support Planning --}}
<div id="ganttViewContainer" class="bg-white rounded-lg shadow" data-support-id="{{ $support->id ?? '' }}">
    {{-- Gantt Controls - Mobile Optimized --}}
    <div class="px-3 sm:px-4 py-2 sm:py-3 bg-gray-50 border-b border-gray-200">
        <div class="flex justify-between items-center">
            <h3 class="text-xs sm:text-sm font-semibold text-gray-900">Gantt Chart</h3>
            <div class="flex items-center space-x-1 sm:space-x-2">
                <button onclick="expandAllGantt()"
                        class="inline-flex items-center px-2 sm:px-3 py-1 sm:py-1.5 border border-gray-300 text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50">
                    <svg class="sm:mr-1 h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                    <span class="hidden sm:inline">Expand</span>
                </button>
                <button onclick="collapseAllGantt()"
                        class="inline-flex items-center px-2 sm:px-3 py-1 sm:py-1.5 border border-gray-300 text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50">
                    <svg class="sm:mr-1 h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                    </svg>
                    <span class="hidden sm:inline">Collapse</span>
                </button>
                <button onclick="refreshGanttView()"
                        class="inline-flex items-center px-2 sm:px-3 py-1 sm:py-1.5 border border-gray-300 text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50">
                    <svg class="sm:mr-1 h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    <span class="hidden sm:inline">Refresh</span>
                </button>
            </div>
        </div>
    </div>

    {{-- Statistics Cards - Responsive Grid --}}
    <div class="px-3 sm:px-4 py-2 sm:py-3 bg-gradient-to-r from-purple-50 to-indigo-50 border-b border-gray-200">
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2 sm:gap-3">
            <div class="bg-white rounded-lg p-2 sm:p-3 text-center shadow-sm">
                <div class="text-lg sm:text-2xl font-bold text-gray-900" id="ganttTotalActivities">0</div>
                <div class="text-xs text-gray-600 mt-1">Total Tasks</div>
                <div class="text-xs text-gray-400 mt-0.5">(Stages)</div>
            </div>

            <div class="bg-white rounded-lg p-2 sm:p-3 text-center shadow-sm border-l-4 border-gray-400">
                <div class="text-lg sm:text-2xl font-bold text-gray-600" id="ganttNotStartedActivities">0</div>
                <div class="text-xs text-gray-600 mt-1">Not Started</div>
                <div class="text-xs text-gray-400 mt-0.5" id="ganttNotStartedPercent">0%</div>
            </div>

            <div class="bg-white rounded-lg p-2 sm:p-3 text-center shadow-sm border-l-4 border-blue-500">
                <div class="text-lg sm:text-2xl font-bold text-blue-600" id="ganttProgressActivities">0</div>
                <div class="text-xs text-gray-600 mt-1">In Progress</div>
                <div class="text-xs text-gray-400 mt-0.5" id="ganttProgressPercent">0%</div>
            </div>

            <div class="bg-white rounded-lg p-2 sm:p-3 text-center shadow-sm border-l-4 border-green-500">
                <div class="text-lg sm:text-2xl font-bold text-green-600" id="ganttCompletedActivities">0</div>
                <div class="text-xs text-gray-600 mt-1">Completed</div>
                <div class="text-xs text-gray-400 mt-0.5" id="ganttCompletedPercent">0%</div>
            </div>

            <div class="bg-white rounded-lg p-2 sm:p-3 text-center shadow-sm border-l-4 border-red-500">
                <div class="text-lg sm:text-2xl font-bold text-red-600" id="ganttDelayedActivities">0</div>
                <div class="text-xs text-gray-600 mt-1">Delayed</div>
                <div class="text-xs text-gray-400 mt-0.5" id="ganttDelayedPercent">0%</div>
            </div>
        </div>

        <div class="mt-2 sm:mt-3 bg-white rounded-lg p-2 sm:p-3 shadow-sm">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs sm:text-sm font-medium text-gray-700">Overall Progress</span>
                <span class="text-base sm:text-xl font-bold text-gray-900" id="ganttProgressText">0%</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2 sm:h-3">
                <div id="ganttProgressBar" class="h-2 sm:h-3 rounded-full transition-all duration-500 bg-purple-600" style="width: 0%"></div>
            </div>
        </div>
    </div>

    {{-- Main Gantt Chart Container - Mobile Optimized --}}
    <div class="bg-white rounded-lg border-b border-gray-200 overflow-hidden">
        <div id="ganttLoading" class="flex items-center justify-center py-12 sm:py-20">
            <div class="text-center">
                <svg class="animate-spin h-6 w-6 sm:h-8 sm:w-8 text-purple-600 mx-auto mb-2" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="text-xs sm:text-sm text-gray-600">Loading Gantt Chart...</p>
            </div>
        </div>

        <div id="ganttChartContent" class="hidden">
            {{-- Mobile: Scroll Hint --}}
            <div class="lg:hidden px-3 py-2 bg-yellow-50 border-b border-yellow-200 flex items-center justify-center text-xs text-yellow-800">
                <svg class="w-3 h-3 mr-1 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"></path>
                </svg>
                Scroll horizontally to see timeline
            </div>

            <div class="gantt-wrapper">
                <div class="gantt-sidebar" id="ganttSidebar">
                    {{-- Header --}}
                    <div class="gantt-sidebar-header">
                        <div class="gantt-header-row">
                            <div class="gantt-header-col gantt-header-col-name">Task Name</div>
                            <div class="gantt-header-col gantt-header-col-date hidden sm:block">Start Date</div>
                            <div class="gantt-header-col gantt-header-col-date hidden sm:block">Due Date</div>
                            <div class="gantt-header-col gantt-header-col-duration hidden md:block">Duration</div>
                            <div class="gantt-header-col gantt-header-col-complete">Progress</div>
                        </div>
                    </div>
                    <div class="gantt-sidebar-body" id="ganttSidebarBody"></div>
                </div>

                {{-- Hide resize handle on mobile --}}
                <div class="gantt-resize-handle hidden lg:block" id="ganttResizeHandle">
                    <div class="gantt-resize-line"></div>
                </div>

                <div class="gantt-timeline">
                    <div class="gantt-timeline-header" id="ganttTimelineHeader"></div>
                    <div class="gantt-timeline-body" id="ganttTimelineBody"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Legend - Responsive --}}
    <div class="px-3 sm:px-4 py-2 sm:py-3 bg-gray-50 border-t border-gray-200">
        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-3 lg:gap-4">
            <div class="flex-1">
                <span class="text-xs font-semibold text-gray-700 block mb-2">Phases:</span>
                <div id="verticalPhasesLegend" class="flex flex-wrap gap-2"></div>
            </div>
            <div class="flex-1">
                <span class="text-xs font-semibold text-gray-700 block mb-2">Status:</span>
                <div class="flex flex-wrap items-center gap-2 sm:gap-3 text-xs text-gray-500">
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-gray-400 rounded mr-1"></div>
                        <span>Not Started</span>
                    </div>
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-blue-500 rounded mr-1"></div>
                        <span>In Progress</span>
                    </div>
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-green-500 rounded mr-1"></div>
                        <span>Completed</span>
                    </div>
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-red-500 rounded mr-1"></div>
                        <span>Delayed</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* GANTT CHART STYLES for Support Planning */
.gantt-wrapper {
    display: flex;
    width: 100%;
    height: 600px;
    overflow: hidden;
    background-color: #fff;
    position: relative;
}

@media (max-width: 639px) {
    .gantt-wrapper {
        height: 400px;
    }
}

.gantt-sidebar {
    flex: 0 0 200px;
    width: 200px;
    min-width: 150px;
    display: flex;
    flex-direction: column;
    border-right: 2px solid #d1d5db;
    background-color: #fafafa;
    overflow: hidden;
    z-index: 50;
}

@media (min-width: 640px) {
    .gantt-sidebar {
        flex: 0 0 250px;
        width: 250px;
        min-width: 200px;
    }
}

@media (min-width: 1024px) {
    .gantt-sidebar {
        flex: 0 0 400px;
        width: 400px;
        min-width: 250px;
        max-width: 800px;
    }
}

.gantt-sidebar-header {
    padding: 8px;
    background-color: #f9fafb;
    border-bottom: 2px solid #d1d5db;
    height: 50px;
    min-height: 50px;
    max-height: 50px;
    display: flex;
    align-items: center;
    position: sticky;
    top: 0;
    z-index: 30;
    flex-shrink: 0;
    overflow: hidden;
}

@media (min-width: 640px) {
    .gantt-sidebar-header {
        padding: 12px;
    }
}

.gantt-header-row {
    display: grid;
    grid-template-columns: 1fr 0.8fr;
    gap: 4px;
    width: 100%;
    align-items: center;
}

@media (min-width: 640px) {
    .gantt-header-row {
        grid-template-columns: 1.5fr 1fr 1fr 1fr;
        gap: 8px;
    }
}

@media (min-width: 1024px) {
    .gantt-header-row {
        grid-template-columns: 2fr 1fr 1fr 1fr 1fr;
    }
}

.gantt-header-col {
    font-size: 0.65rem;
    font-weight: 600;
    color: #374151;
    text-transform: uppercase;
    letter-spacing: 0.025em;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

@media (min-width: 640px) {
    .gantt-header-col {
        font-size: 0.75rem;
    }
}

.gantt-sidebar-body {
    overflow-y: auto;
    overflow-x: hidden;
    flex: 1;
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 #f1f5f9;
}

.gantt-sidebar-body::-webkit-scrollbar {
    width: 6px;
}

@media (min-width: 640px) {
    .gantt-sidebar-body::-webkit-scrollbar {
        width: 8px;
    }
}

.gantt-resize-handle {
    flex: 0 0 8px;
    width: 8px;
    cursor: col-resize;
    background: linear-gradient(90deg, #e5e7eb 0%, #f3f4f6 50%, #e5e7eb 100%);
    position: relative;
    z-index: 40;
    transition: background 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.gantt-resize-handle:hover {
    background: linear-gradient(90deg, #cbd5e1 0%, #d1d5db 50%, #cbd5e1 100%);
}

.gantt-resize-line {
    width: 2px;
    height: 40px;
    background-color: #9ca3af;
    border-radius: 2px;
}

.gantt-timeline {
    flex: 1 1 0;
    min-width: 0;
    width: 0;
    height: 100%;
    overflow-x: auto;
    overflow-y: auto;
    position: relative;
    -webkit-overflow-scrolling: touch;
    scroll-behavior: smooth;
}

.gantt-timeline::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

@media (min-width: 640px) {
    .gantt-timeline::-webkit-scrollbar {
        width: 12px;
        height: 12px;
    }
}

.gantt-timeline::-webkit-scrollbar-track {
    background: #f1f5f9;
}

.gantt-timeline::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 6px;
}

.gantt-timeline::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

.gantt-timeline-header {
    position: sticky;
    top: 0;
    z-index: 20;
    background-color: #f9fafb;
    border-bottom: 2px solid #d1d5db;
    height: 50px;
    min-height: 50px;
    max-height: 50px;
    overflow: hidden;
}

.gantt-week-row {
    display: flex;
    height: 24px;
    border-bottom: 1px solid #e5e7eb;
}

.gantt-week-cell {
    border-right: 1px solid #e5e7eb;
    background-color: #f3f4f6;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.65rem;
    font-weight: 600;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

.gantt-day-row {
    display: flex;
    height: 24px;
}

.gantt-day-cell {
    border-right: 1px solid #e5e7eb;
    background-color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    color: #4b5563;
    font-weight: 500;
}

.gantt-day-cell.weekend {
    background-color: #fef3c7;
}

.gantt-day-cell.today {
    background-color: #dbeafe;
    color: #1e40af;
    font-weight: 700;
}

.gantt-timeline-body {
    position: relative;
}

.gantt-phase-row {
    padding: 6px 8px;
    background-color: #f3f4f6;
    border-bottom: 1px solid #e5e7eb;
    height: 35px;
    min-height: 35px;
    max-height: 35px;
    display: flex;
    align-items: center;
    border-left: 4px solid;
    font-size: 0.7rem;
}

@media (min-width: 640px) {
    .gantt-phase-row {
        padding: 8px 12px;
        font-size: 0.75rem;
    }
}

.gantt-activity-row {
    padding: 8px;
    border-bottom: 1px solid #e5e7eb;
    height: 60px;
    min-height: 60px;
    max-height: 60px;
    display: grid;
    grid-template-columns: 1fr 0.8fr;
    gap: 4px;
    align-items: center;
    font-size: 0.7rem;
    transition: background-color 0.15s ease;
}

@media (min-width: 640px) {
    .gantt-activity-row {
        padding: 10px 12px;
        grid-template-columns: 1.5fr 1fr 1fr 1fr;
        gap: 8px;
        font-size: 0.75rem;
    }
}

@media (min-width: 1024px) {
    .gantt-activity-row {
        grid-template-columns: 2fr 1fr 1fr 1fr 1fr;
    }
}

.gantt-activity-row:hover {
    background-color: #f9fafb;
}

.gantt-timeline-row {
    position: relative;
    height: 60px;
    min-height: 60px;
    max-height: 60px;
    border-bottom: 1px solid #e5e7eb;
}

.gantt-timeline-row.gantt-phase-bg {
    height: 35px;
    min-height: 35px;
    max-height: 35px;
    background-color: #f3f4f6;
}

.gantt-row-hidden {
    display: none !important;
}

.gantt-toggle-button {
    cursor: pointer;
    transition: transform 0.2s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    border-radius: 3px;
    margin-right: 4px;
    touch-action: manipulation;
    background-color: transparent;
    border: none;
    padding: 0;
}

@media (max-width: 639px) {
    .gantt-toggle-button {
        min-width: 24px;
        min-height: 24px;
    }
}

.gantt-toggle-button:hover {
    background-color: rgba(0, 0, 0, 0.05);
}

.gantt-toggle-button.collapsed .gantt-toggle-icon {
    transform: rotate(0deg);
}

.gantt-toggle-button.expanded .gantt-toggle-icon {
    transform: rotate(90deg);
}

.gantt-grid-container {
    display: flex;
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
}

.gantt-grid-cell {
    border-right: 1px solid #e5e7eb;
    height: 100%;
    flex-shrink: 0;
}

.gantt-grid-cell.weekend {
    background-color: #fef3c7;
}

.gantt-bar-container {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    z-index: 10;
}

.gantt-bar {
    height: 100%;
    border-radius: 4px;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    cursor: pointer;
    transition: all 0.2s ease;
}

.gantt-bar:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.15);
}

.gantt-bar-progress {
    position: absolute;
    left: 0;
    top: 0;
    height: 100%;
    background-color: rgba(255, 255, 255, 0.3);
    border-radius: 4px 0 0 4px;
    transition: width 0.3s ease;
}

.gantt-bar-text {
    position: relative;
    z-index: 2;
    font-size: 0.65rem;
    font-weight: 600;
    color: white;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
}

.gantt-today-line {
    position: absolute;
    top: 0;
    bottom: 0;
    width: 2px;
    background-color: #ef4444;
    z-index: 15;
    pointer-events: none;
}

.gantt-today-label {
    position: absolute;
    top: 2px;
    left: 50%;
    transform: translateX(-50%);
    background-color: #ef4444;
    color: white;
    font-size: 0.65rem;
    font-weight: 600;
    padding: 2px 6px;
    border-radius: 3px;
    white-space: nowrap;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
}

.gantt-today-marker {
    position: absolute;
    top: -8px;
    left: 50%;
    transform: translateX(-50%);
    width: 0;
    height: 0;
    border-left: 6px solid transparent;
    border-right: 6px solid transparent;
    border-top: 8px solid #ef4444;
}
</style>

<script>
(function() {
    'use strict';

    console.log('Gantt Chart View for Support Initializing...');

    // GLOBAL VARIABLES
    var ganttData = null;
    var ganttInitialized = false;
    var cellWidth = 40;
    var sidebar, resizeHandle;
    var isResizing = false;
    var startX, startWidth;
    var isSidebarScrolling = false;
    var isTimelineScrolling = false;

    // GET SUPPORT ID
    function getSupportId() {
        var container = document.getElementById('ganttViewContainer');
        if (container && container.dataset.supportId) {
            return container.dataset.supportId;
        }

        if (window.supportId) {
            return window.supportId;
        }

        var urlParts = window.location.pathname.split('/');
        var supportIndex = urlParts.indexOf('support');
        if (supportIndex !== -1 && urlParts[supportIndex + 1]) {
            return urlParts[supportIndex + 1];
        }

        console.error('No support ID found');
        return null;
    }

    // EXPAND ALL
    window.expandAllGantt = function() {
        console.log('Expanding all rows...');

        var allToggleButtons = document.querySelectorAll('.gantt-toggle-button');
        allToggleButtons.forEach(function(btn) {
            if (btn.classList.contains('collapsed')) {
                btn.classList.remove('collapsed');
                btn.classList.add('expanded');
            }
        });

        var allSidebarRows = document.querySelectorAll('.gantt-sidebar-body .gantt-row-hidden');
        var allTimelineRows = document.querySelectorAll('.gantt-timeline-body .gantt-row-hidden');

        allSidebarRows.forEach(function(row) {
            row.classList.remove('gantt-row-hidden');
        });

        allTimelineRows.forEach(function(row) {
            row.classList.remove('gantt-row-hidden');
        });
    };

    // COLLAPSE ALL
    window.collapseAllGantt = function() {
        console.log('Collapsing all rows...');

        var allSidebarRows = document.querySelectorAll('.gantt-sidebar-body .gantt-activity-row');
        var allTimelineRows = document.querySelectorAll('.gantt-timeline-body .gantt-timeline-row:not(.gantt-phase-bg)');

        allSidebarRows.forEach(function(row) {
            var level = parseInt(row.getAttribute('data-level') || '0');
            if (level > 0) {
                row.classList.add('gantt-row-hidden');
            }
        });

        allTimelineRows.forEach(function(row) {
            var level = parseInt(row.getAttribute('data-level') || '0');
            if (level > 0) {
                row.classList.add('gantt-row-hidden');
            }
        });

        var allToggleButtons = document.querySelectorAll('.gantt-toggle-button');
        allToggleButtons.forEach(function(btn) {
            if (btn.classList.contains('expanded')) {
                btn.classList.remove('expanded');
                btn.classList.add('collapsed');
            }
        });
    };

    // TOGGLE ROW
    window.toggleRow = function(rowId) {
        console.log('Toggling row:', rowId);

        var sidebarRow = document.querySelector('.gantt-sidebar-body [data-id="' + rowId + '"]');
        if (!sidebarRow) return;

        var toggleBtn = sidebarRow.querySelector('.gantt-toggle-button');
        if (!toggleBtn) return;

        var isExpanded = toggleBtn.classList.contains('expanded');

        if (isExpanded) {
            toggleBtn.classList.remove('expanded');
            toggleBtn.classList.add('collapsed');
        } else {
            toggleBtn.classList.remove('collapsed');
            toggleBtn.classList.add('expanded');
        }

        toggleChildRows(rowId, isExpanded);
    };

    // TOGGLE CHILD ROWS
    function toggleChildRows(parentId, shouldHide) {
        var sidebarChildren = document.querySelectorAll('.gantt-sidebar-body [data-parent="' + parentId + '"]');
        sidebarChildren.forEach(function(child) {
            if (shouldHide) {
                child.classList.add('gantt-row-hidden');
                var childId = child.getAttribute('data-id');
                toggleChildRows(childId, true);
            } else {
                child.classList.remove('gantt-row-hidden');
            }
        });

        var timelineChildren = document.querySelectorAll('.gantt-timeline-body [data-parent="' + parentId + '"]');
        timelineChildren.forEach(function(child) {
            if (shouldHide) {
                child.classList.add('gantt-row-hidden');
                var childId = child.getAttribute('data-id');
                toggleChildRows(childId, true);
            } else {
                child.classList.remove('gantt-row-hidden');
            }
        });
    }

    // REFRESH
    window.refreshGanttView = function() {
        console.log('Refreshing Gantt View...');
        ganttInitialized = false;
        document.getElementById('ganttLoading').classList.remove('hidden');
        document.getElementById('ganttChartContent').classList.add('hidden');

        ganttData = null;

        setTimeout(function() {
            window.loadGanttChartView();
        }, 300);
    };

    // LOAD GANTT CHART DATA
    window.loadGanttChartView = function() {
        console.log('Loading Gantt Chart View...');

        if (ganttInitialized) {
            console.log('Gantt already initialized');
            return;
        }

        var supportId = getSupportId();
        if (!supportId) {
            console.error('No support ID found');
            showEmptyState();
            return;
        }

        console.log('Fetching Gantt data for support:', supportId);

        var apiUrl = '/delivery/support/' + supportId + '/data/gantt';

        axios.get(apiUrl)
            .then(function(response) {
                console.log('Gantt data loaded:', response.data);
                ganttData = response.data;

                if (!ganttData.vertical_groups || ganttData.vertical_groups.length === 0) {
                    showEmptyState();
                    return;
                }

                updateStatistics(ganttData.vertical_groups);
                renderGanttChart();
                initializeResize();
                ganttInitialized = true;
            })
            .catch(function(error) {
                console.error('Error loading Gantt data:', error);
                showErrorState(error);
            });
    };

    // UPDATE STATISTICS
    function updateStatistics(verticalGroups) {
        console.log('Calculating statistics from vertical_groups:', verticalGroups);

        var totalStages = 0;
        var completedStages = 0;
        var inProgressStages = 0;
        var delayedStages = 0;
        var notStartedStages = 0;
        var totalPhaseWeight = 0;
        var weightedPhaseProgress = 0;

        function countStagesInGroup(group) {
            if (group.stages && group.stages.length > 0) {
                group.stages.forEach(function(stage) {
                    totalStages++;
                    var status = (stage.status || 'not_started').toLowerCase();

                    switch(status) {
                        case 'completed':
                            completedStages++;
                            break;
                        case 'in_progress':
                            inProgressStages++;
                            break;
                        case 'delayed':
                            delayedStages++;
                            break;
                        default:
                            notStartedStages++;
                    }
                });
            }

            if (group.sub_groups && group.sub_groups.length > 0) {
                group.sub_groups.forEach(function(subGroup) {
                    countStagesInGroup(subGroup);
                });
            }
        }

        verticalGroups.forEach(function(phase) {
            var phaseWeight = parseFloat(phase.weight) || 0;
            var phaseProgress = parseFloat(phase.progress) || 0;

            totalPhaseWeight += phaseWeight;
            weightedPhaseProgress += (phaseProgress * phaseWeight);

            if (phase.tasks && phase.tasks.length > 0) {
                phase.tasks.forEach(function(group) {
                    countStagesInGroup(group);
                });
            }
        });

        var notStartedPercent = totalStages > 0 ? Math.round((notStartedStages / totalStages) * 100) : 0;
        var inProgressPercent = totalStages > 0 ? Math.round((inProgressStages / totalStages) * 100) : 0;
        var completedPercent = totalStages > 0 ? Math.round((completedStages / totalStages) * 100) : 0;
        var delayedPercent = totalStages > 0 ? Math.round((delayedStages / totalStages) * 100) : 0;
        var overallProgress = totalPhaseWeight > 0 ? Math.round(weightedPhaseProgress / totalPhaseWeight) : 0;

        document.getElementById('ganttTotalActivities').textContent = totalStages;
        document.getElementById('ganttNotStartedActivities').textContent = notStartedStages;
        document.getElementById('ganttProgressActivities').textContent = inProgressStages;
        document.getElementById('ganttCompletedActivities').textContent = completedStages;
        document.getElementById('ganttDelayedActivities').textContent = delayedStages;

        document.getElementById('ganttNotStartedPercent').textContent = notStartedPercent + '%';
        document.getElementById('ganttProgressPercent').textContent = inProgressPercent + '%';
        document.getElementById('ganttCompletedPercent').textContent = completedPercent + '%';
        document.getElementById('ganttDelayedPercent').textContent = delayedPercent + '%';

        var progressBar = document.getElementById('ganttProgressBar');
        progressBar.style.width = overallProgress + '%';

        if (overallProgress >= 100) {
            progressBar.className = 'h-3 rounded-full transition-all duration-500 bg-green-600';
        } else if (overallProgress >= 75) {
            progressBar.className = 'h-3 rounded-full transition-all duration-500 bg-purple-600';
        } else if (overallProgress >= 50) {
            progressBar.className = 'h-3 rounded-full transition-all duration-500 bg-yellow-500';
        } else if (overallProgress >= 25) {
            progressBar.className = 'h-3 rounded-full transition-all duration-500 bg-orange-500';
        } else {
            progressBar.className = 'h-3 rounded-full transition-all duration-500 bg-red-500';
        }

        document.getElementById('ganttProgressText').textContent = overallProgress + '%';
    }

    // INITIALIZE RESIZE
    function initializeResize() {
        sidebar = document.getElementById('ganttSidebar');
        resizeHandle = document.getElementById('ganttResizeHandle');

        if (!sidebar || !resizeHandle) {
            console.warn('Sidebar or resize handle not found');
            return;
        }

        var savedWidth = localStorage.getItem('supportGanttSidebarWidth');
        if (savedWidth) {
            var width = parseInt(savedWidth);
            if (width >= 250 && width <= 800) {
                setSidebarWidth(width);
            }
        }

        resizeHandle.addEventListener('mousedown', function(e) {
            isResizing = true;
            startX = e.clientX;
            startWidth = sidebar.offsetWidth;

            document.body.style.cursor = 'col-resize';
            document.body.style.userSelect = 'none';

            e.preventDefault();
        });

        document.addEventListener('mousemove', function(e) {
            if (!isResizing) return;

            var delta = e.clientX - startX;
            var newWidth = startWidth + delta;
            newWidth = Math.max(250, Math.min(800, newWidth));

            setSidebarWidth(newWidth);
        });

        document.addEventListener('mouseup', function() {
            if (isResizing) {
                isResizing = false;
                document.body.style.cursor = '';
                document.body.style.userSelect = '';
                localStorage.setItem('supportGanttSidebarWidth', sidebar.offsetWidth);
            }
        });

        resizeHandle.addEventListener('dblclick', function() {
            setSidebarWidth(400);
            localStorage.setItem('supportGanttSidebarWidth', 400);
        });

        console.log('Resize functionality initialized');
    }

    function setSidebarWidth(width) {
        if (!sidebar) return;

        sidebar.style.flex = '0 0 ' + width + 'px';
        sidebar.style.width = width + 'px';
        sidebar.style.minWidth = width + 'px';
        sidebar.style.maxWidth = width + 'px';
    }

    // RENDER GANTT CHART
    function renderGanttChart() {
        console.log('Rendering Gantt Chart...');

        document.getElementById('ganttLoading').classList.add('hidden');
        document.getElementById('ganttChartContent').classList.remove('hidden');

        var startDate = new Date(ganttData.start_date);
        var endDate = new Date(ganttData.end_date);
        var allDates = getDatesInRange(startDate, endDate);
        var totalDays = allDates.length;
        var timelineWidth = totalDays * cellWidth;

        var timelineHeader = document.getElementById('ganttTimelineHeader');
        var timelineBody = document.getElementById('ganttTimelineBody');
        var timeline = document.querySelector('.gantt-timeline');

        if (!timelineHeader || !timelineBody) {
            console.error('Timeline elements not found');
            return;
        }

        timelineHeader.style.width = timelineWidth + 'px';
        timelineHeader.style.minWidth = timelineWidth + 'px';
        timelineBody.style.width = timelineWidth + 'px';
        timelineBody.style.minWidth = timelineWidth + 'px';

        if (timeline) {
            timeline.style.overflowX = 'auto';
        }

        renderTimelineHeader(allDates);
        renderTasks(ganttData.vertical_groups, allDates, startDate);
        addGlobalTodayLine(allDates);
        renderLegends();
        syncScroll();
        scrollToToday(allDates);

        console.log('Gantt Chart rendered successfully');
    }

    // RENDER TIMELINE HEADER
    function renderTimelineHeader(allDates) {
        var header = document.getElementById('ganttTimelineHeader');
        header.innerHTML = '';

        var weekRow = document.createElement('div');
        weekRow.className = 'gantt-week-row';

        var weeks = groupByWeek(allDates);
        weeks.forEach(function(week) {
            var weekCell = document.createElement('div');
            weekCell.className = 'gantt-week-cell';
            weekCell.style.width = (week.days * cellWidth) + 'px';
            weekCell.textContent = 'WEEK ' + week.weekNumber;
            weekRow.appendChild(weekCell);
        });
        header.appendChild(weekRow);

        var dayRow = document.createElement('div');
        dayRow.className = 'gantt-day-row';

        allDates.forEach(function(date) {
            var dayCell = document.createElement('div');
            dayCell.className = 'gantt-day-cell';
            dayCell.style.width = cellWidth + 'px';
            if (isWeekend(date)) dayCell.classList.add('weekend');
            if (isToday(date)) dayCell.classList.add('today');
            dayCell.textContent = formatDay(date);
            dayRow.appendChild(dayCell);
        });
        header.appendChild(dayRow);
    }

    // RENDER TASKS (VERTICAL GROUPS)
    function renderTasks(verticalGroups, allDates, startDate) {
        var sidebarBody = document.getElementById('ganttSidebarBody');
        var timelineBody = document.getElementById('ganttTimelineBody');

        sidebarBody.innerHTML = '';
        timelineBody.innerHTML = '';

        verticalGroups.forEach(function(phase) {
            var phaseRow = createPhaseRow(phase);
            sidebarBody.appendChild(phaseRow);

            var phaseTimelineRow = createPhaseTimelineRow(allDates);
            timelineBody.appendChild(phaseTimelineRow);

            if (phase.tasks && phase.tasks.length > 0) {
                phase.tasks.forEach(function(group) {
                    renderGroupRecursive(group, sidebarBody, timelineBody, allDates, startDate, phase.id, 0, null);
                });
            }
        });
    }

    // RENDER GROUP RECURSIVE
    function renderGroupRecursive(group, sidebarBody, timelineBody, allDates, startDate, phaseId, level, actualParentId) {
        var groupId = 'group-' + group.id;
        var parentId = actualParentId || ('phase-' + phaseId);

        var groupRow = createGroupRow(group, level, phaseId, parentId);
        sidebarBody.appendChild(groupRow);

        var groupTimelineRow = createGroupTimelineRow(group, allDates, startDate, level, parentId);
        timelineBody.appendChild(groupTimelineRow);

        if (group.sub_groups && group.sub_groups.length > 0) {
            group.sub_groups.forEach(function(subGroup) {
                renderGroupRecursive(subGroup, sidebarBody, timelineBody, allDates, startDate, phaseId, level + 1, groupId);
            });
        }

        if (group.stages && group.stages.length > 0) {
            group.stages.forEach(function(stage) {
                var stageRow = createStageRow(stage, groupId, level + 1);
                sidebarBody.appendChild(stageRow);

                var stageTimelineRow = createStageTimelineRow(stage, allDates, startDate, groupId, level + 1);
                timelineBody.appendChild(stageTimelineRow);

                if (stage.activities && stage.activities.length > 0) {
                    stage.activities.forEach(function(activity) {
                        var stageId = 'stage-' + stage.id;
                        var activityRow = createActivityRow(activity, stageId, level + 2);
                        sidebarBody.appendChild(activityRow);

                        var activityTimelineRow = createActivityTimelineRow(activity, allDates, startDate, stageId, level + 2);
                        timelineBody.appendChild(activityTimelineRow);
                    });
                }
            });
        }
    }

    // CREATE ROWS
    function createPhaseRow(phase) {
        var row = document.createElement('div');
        row.className = 'gantt-phase-row';
        row.style.borderLeftColor = phase.color;
        row.setAttribute('data-id', 'phase-' + phase.id);
        row.setAttribute('data-type', 'phase');

        var toggleBtn = document.createElement('button');
        toggleBtn.className = 'gantt-toggle-button collapsed';
        toggleBtn.onclick = function() { toggleRow('phase-' + phase.id); };
        toggleBtn.innerHTML = '<svg class="w-4 h-4 gantt-toggle-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>';

        var nameSpan = document.createElement('span');
        nameSpan.className = 'font-bold text-xs';
        nameSpan.textContent = phase.name.toUpperCase();

        row.appendChild(toggleBtn);
        row.appendChild(nameSpan);

        return row;
    }

    function createGroupRow(group, level, phaseId, parentId) {
        var row = document.createElement('div');
        var groupId = 'group-' + group.id;
        row.className = 'gantt-activity-row gantt-level-' + level;
        row.setAttribute('data-id', groupId);
        row.setAttribute('data-parent', parentId);
        row.setAttribute('data-type', 'group');
        row.setAttribute('data-level', level);

        if (level > 0) {
            row.classList.add('gantt-row-hidden');
        }

        var duration = calculateDuration(new Date(group.start), new Date(group.end));
        var hasChildren = (group.sub_groups && group.sub_groups.length > 0) || (group.stages && group.stages.length > 0);

        var toggleHtml = '';
        if (hasChildren) {
            toggleHtml = '<button class="gantt-toggle-button collapsed" onclick="toggleRow(\'' + groupId + '\')"><svg class="w-3 h-3 gantt-toggle-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg></button>';
        } else {
            toggleHtml = '<span class="w-5 inline-block"></span>';
        }

        var indent = '&nbsp;'.repeat(level * 4);

        row.innerHTML = `
            <div class="font-medium text-gray-900 truncate flex items-center" title="${group.name}">
                ${indent}${toggleHtml}
                <svg class="w-4 h-4 text-purple-600 mr-1 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"></path>
                </svg>
                <span>${group.name}</span>
            </div>
            <div class="text-gray-600">${formatDate(new Date(group.start))}</div>
            <div class="text-gray-600">${formatDate(new Date(group.end))}</div>
            <div class="text-gray-600">${duration}d</div>
            <div class="font-semibold ${getProgressColor(group.progress)}">${group.progress}%</div>
        `;

        return row;
    }

    function createStageRow(stage, parentId, level) {
        var row = document.createElement('div');
        var stageId = 'stage-' + stage.id;
        row.className = 'gantt-activity-row gantt-level-' + level;
        row.setAttribute('data-id', stageId);
        row.setAttribute('data-parent', parentId);
        row.setAttribute('data-type', 'stage');
        row.setAttribute('data-level', level);
        row.classList.add('gantt-row-hidden');

        var stageStart = new Date(stage.planned_start_date);
        var stageEnd = new Date(stage.planned_end_date);
        var duration = calculateDuration(stageStart, stageEnd);
        var hasActivities = stage.activities && stage.activities.length > 0;

        var toggleHtml = '';
        if (hasActivities) {
            toggleHtml = '<button class="gantt-toggle-button collapsed" onclick="toggleRow(\'' + stageId + '\')"><svg class="w-3 h-3 gantt-toggle-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg></button>';
        } else {
            toggleHtml = '<span class="w-5 inline-block"></span>';
        }

        var indent = '&nbsp;'.repeat(level * 4);

        row.innerHTML = `
            <div class="font-medium text-gray-700 text-xs truncate flex items-center" title="${stage.name}">
                ${indent}${toggleHtml}
                <svg class="w-3 h-3 text-cyan-600 mr-1 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0z"></path>
                </svg>
                <span>${stage.name}</span>
            </div>
            <div class="text-gray-600 text-xs">${formatDate(stageStart)}</div>
            <div class="text-gray-600 text-xs">${formatDate(stageEnd)}</div>
            <div class="text-gray-600 text-xs">${duration}d</div>
            <div class="font-semibold text-xs ${getProgressColor(stage.progress)}">${stage.progress}%</div>
        `;

        return row;
    }

    function createActivityRow(activity, parentId, level) {
        var row = document.createElement('div');
        var activityId = 'activity-' + activity.id;
        row.className = 'gantt-activity-row gantt-level-' + level;
        row.setAttribute('data-id', activityId);
        row.setAttribute('data-parent', parentId);
        row.setAttribute('data-type', 'activity');
        row.setAttribute('data-level', level);
        row.classList.add('gantt-row-hidden');

        var activityName = activity.name;
        if (activity.activity && activity.activity.name) {
            activityName = activity.activity.name;
        }

        var activityStart = new Date(activity.start);
        var activityEnd = new Date(activity.end);
        var duration = calculateDuration(activityStart, activityEnd);

        var indent = '&nbsp;'.repeat(level * 4);

        row.innerHTML = `
            <div class="font-medium text-gray-700 text-xs truncate flex items-center" title="${activityName}">
                ${indent}<span class="w-5 inline-block"></span>
                <svg class="w-3 h-3 text-green-600 mr-1 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <span>${activityName}</span>
            </div>
            <div class="text-gray-600 text-xs">${formatDate(activityStart)}</div>
            <div class="text-gray-600 text-xs">${formatDate(activityEnd)}</div>
            <div class="text-gray-600 text-xs">${duration}d</div>
            <div class="font-semibold text-xs ${getProgressColor(activity.progress)}">${activity.progress}%</div>
        `;

        return row;
    }

    // CREATE TIMELINE ROWS
    function createPhaseTimelineRow(allDates) {
        var row = document.createElement('div');
        row.className = 'gantt-timeline-row gantt-phase-bg';
        row.setAttribute('data-type', 'phase');

        var gridContainer = document.createElement('div');
        gridContainer.className = 'gantt-grid-container';
        allDates.forEach(function(date) {
            var cell = document.createElement('div');
            cell.className = 'gantt-grid-cell';
            cell.style.width = cellWidth + 'px';
            if (isWeekend(date)) cell.classList.add('weekend');
            gridContainer.appendChild(cell);
        });
        row.appendChild(gridContainer);
        return row;
    }

    function createGroupTimelineRow(group, allDates, startDate, level, parentId) {
        var row = document.createElement('div');
        row.className = 'gantt-timeline-row';
        row.setAttribute('data-id', 'group-' + group.id);
        row.setAttribute('data-parent', parentId);
        row.setAttribute('data-type', 'group');
        row.setAttribute('data-level', level);

        if (level > 0) {
            row.classList.add('gantt-row-hidden');
        }

        return addBarToTimelineRow(row, group, allDates, group.status_color);
    }

    function createStageTimelineRow(stage, allDates, startDate, parentId, level) {
        var row = document.createElement('div');
        row.className = 'gantt-timeline-row';
        row.style.backgroundColor = '#ecfeff';
        row.setAttribute('data-id', 'stage-' + stage.id);
        row.setAttribute('data-parent', parentId);
        row.setAttribute('data-type', 'stage');
        row.setAttribute('data-level', level);
        row.classList.add('gantt-row-hidden');

        var gridContainer = document.createElement('div');
        gridContainer.className = 'gantt-grid-container';
        allDates.forEach(function(date) {
            var cell = document.createElement('div');
            cell.className = 'gantt-grid-cell';
            cell.style.width = cellWidth + 'px';
            if (isWeekend(date)) cell.classList.add('weekend');
            gridContainer.appendChild(cell);
        });
        row.appendChild(gridContainer);

        var stageStart = new Date(stage.planned_start_date);
        var stageEnd = new Date(stage.planned_end_date);
        var startOffset = 0;
        var duration = 0;

        allDates.forEach(function(date, index) {
            if (isSameDay(date, stageStart)) startOffset = index;
            if (date >= stageStart && date <= stageEnd) duration++;
        });

        if (duration > 0) {
            var barContainer = document.createElement('div');
            barContainer.className = 'gantt-bar-container';
            barContainer.style.left = (startOffset * cellWidth) + 'px';
            barContainer.style.width = (duration * cellWidth) + 'px';
            barContainer.style.height = '20px';

            var bar = document.createElement('div');
            bar.className = 'gantt-bar';
            bar.style.backgroundColor = stage.color || '#06b6d4';
            bar.style.opacity = '0.85';
            bar.title = stage.name + '\n' + stage.planned_start_date + ' - ' + stage.planned_end_date + '\n' + stage.progress + '% Complete';

            if (stage.progress > 0) {
                var progressBar = document.createElement('div');
                progressBar.className = 'gantt-bar-progress';
                progressBar.style.width = stage.progress + '%';
                bar.appendChild(progressBar);
            }

            var barText = document.createElement('span');
            barText.className = 'gantt-bar-text';
            barText.textContent = stage.progress + '%';
            bar.appendChild(barText);

            barContainer.appendChild(bar);
            row.appendChild(barContainer);
        }

        return row;
    }

    function createActivityTimelineRow(activity, allDates, startDate, parentId, level) {
        var row = document.createElement('div');
        row.className = 'gantt-timeline-row';
        row.style.backgroundColor = '#f0fdf4';
        row.setAttribute('data-id', 'activity-' + activity.id);
        row.setAttribute('data-parent', parentId);
        row.setAttribute('data-type', 'activity');
        row.setAttribute('data-level', level);
        row.classList.add('gantt-row-hidden');

        return addBarToTimelineRow(row, activity, allDates, activity.status_color || '#10b981');
    }

    function addBarToTimelineRow(row, item, allDates, color) {
        var gridContainer = document.createElement('div');
        gridContainer.className = 'gantt-grid-container';
        allDates.forEach(function(date) {
            var cell = document.createElement('div');
            cell.className = 'gantt-grid-cell';
            cell.style.width = cellWidth + 'px';
            if (isWeekend(date)) cell.classList.add('weekend');
            gridContainer.appendChild(cell);
        });
        row.appendChild(gridContainer);

        var itemStart = new Date(item.start);
        var itemEnd = new Date(item.end);
        var startOffset = 0;
        var duration = 0;

        allDates.forEach(function(date, index) {
            if (isSameDay(date, itemStart)) startOffset = index;
            if (date >= itemStart && date <= itemEnd) duration++;
        });

        if (duration > 0) {
            var barContainer = document.createElement('div');
            barContainer.className = 'gantt-bar-container';
            barContainer.style.left = (startOffset * cellWidth) + 'px';
            barContainer.style.width = (duration * cellWidth) + 'px';
            barContainer.style.height = '18px';

            var bar = document.createElement('div');
            bar.className = 'gantt-bar';
            bar.style.backgroundColor = color;
            bar.title = item.name + '\n' + formatDate(itemStart) + ' - ' + formatDate(itemEnd) + '\n' + item.progress + '% Complete';

            if (item.progress > 0) {
                var progressBar = document.createElement('div');
                progressBar.className = 'gantt-bar-progress';
                progressBar.style.width = item.progress + '%';
                bar.appendChild(progressBar);
            }

            var barText = document.createElement('span');
            barText.className = 'gantt-bar-text';
            barText.textContent = item.progress + '%';
            bar.appendChild(barText);

            barContainer.appendChild(bar);
            row.appendChild(barContainer);
        }

        return row;
    }

    // ADD GLOBAL TODAY LINE
    function addGlobalTodayLine(allDates) {
        var todayIndex = findTodayIndex(allDates);
        if (todayIndex === -1) return;

        var timelineBody = document.getElementById('ganttTimelineBody');
        var todayLine = document.createElement('div');
        todayLine.className = 'gantt-today-line';
        todayLine.style.left = (todayIndex * cellWidth + cellWidth / 2) + 'px';

        var marker = document.createElement('div');
        marker.className = 'gantt-today-marker';
        todayLine.appendChild(marker);

        var label = document.createElement('div');
        label.className = 'gantt-today-label';
        label.textContent = 'Today';
        todayLine.appendChild(label);

        timelineBody.appendChild(todayLine);
    }

    // RENDER LEGENDS
    function renderLegends() {
        var legendContainer = document.getElementById('verticalPhasesLegend');
        legendContainer.innerHTML = '';

        ganttData.vertical_groups.forEach(function(phase) {
            var legendItem = document.createElement('div');
            legendItem.className = 'flex items-center px-2 py-1 bg-white rounded border text-xs';
            legendItem.innerHTML = '<div class="w-3 h-3 rounded mr-1.5" style="background-color: ' + phase.color + '"></div><span>' + phase.name + '</span>';
            legendContainer.appendChild(legendItem);
        });
    }

    // SYNC SCROLL
    function syncScroll() {
        var sidebar = document.querySelector('.gantt-sidebar-body');
        var timeline = document.querySelector('.gantt-timeline');

        if (!sidebar || !timeline) return;

        sidebar.addEventListener('scroll', function() {
            if (!isSidebarScrolling) {
                isTimelineScrolling = true;
                timeline.scrollTop = sidebar.scrollTop;
                setTimeout(function() { isTimelineScrolling = false; }, 50);
            }
        });

        timeline.addEventListener('scroll', function() {
            if (!isTimelineScrolling) {
                isSidebarScrolling = true;
                sidebar.scrollTop = timeline.scrollTop;
                setTimeout(function() { isSidebarScrolling = false; }, 50);
            }
        });
    }

    // SCROLL TO TODAY
    function scrollToToday(allDates) {
        var timeline = document.querySelector('.gantt-timeline');
        if (!timeline) return;

        var todayIndex = findTodayIndex(allDates);
        if (todayIndex !== -1) {
            var scrollPosition = (todayIndex * cellWidth) - (timeline.clientWidth / 2);
            setTimeout(function() {
                timeline.scrollTo({
                    left: Math.max(0, scrollPosition),
                    behavior: 'smooth'
                });
            }, 500);
        }
    }

    // HELPER FUNCTIONS
    function getDatesInRange(start, end) {
        var dates = [];
        var current = new Date(start);
        while (current <= end) {
            dates.push(new Date(current));
            current.setDate(current.getDate() + 1);
        }
        return dates;
    }

    function groupByWeek(dates) {
        var weeks = [];
        var currentWeek = null;
        var weekDays = 0;
        dates.forEach(function(date) {
            var weekNum = getWeekNumber(date);
            if (currentWeek !== weekNum) {
                if (currentWeek !== null) {
                    weeks.push({ weekNumber: currentWeek, days: weekDays });
                }
                currentWeek = weekNum;
                weekDays = 1;
            } else {
                weekDays++;
            }
        });
        if (currentWeek !== null) {
            weeks.push({ weekNumber: currentWeek, days: weekDays });
        }
        return weeks;
    }

    function getWeekNumber(date) {
        var d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
        var dayNum = d.getUTCDay() || 7;
        d.setUTCDate(d.getUTCDate() + 4 - dayNum);
        var yearStart = new Date(Date.UTC(d.getUTCFullYear(),0,1));
        return Math.ceil((((d - yearStart) / 86400000) + 1)/7);
    }

    function isWeekend(date) {
        var day = date.getDay();
        return day === 0 || day === 6;
    }

    function isToday(date) {
        var today = new Date();
        return isSameDay(date, today);
    }

    function isSameDay(date1, date2) {
        return date1.getFullYear() === date2.getFullYear() &&
               date1.getMonth() === date2.getMonth() &&
               date1.getDate() === date2.getDate();
    }

    function findTodayIndex(dates) {
        var today = new Date();
        for (var i = 0; i < dates.length; i++) {
            if (isSameDay(dates[i], today)) return i;
        }
        return -1;
    }

    function formatDay(date) {
        return date.getDate();
    }

    function formatDate(date) {
        var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return date.getDate() + ' ' + months[date.getMonth()];
    }

    function calculateDuration(start, end) {
        var diff = Math.abs(end - start);
        return Math.ceil(diff / (1000 * 60 * 60 * 24)) + 1;
    }

    function getProgressColor(progress) {
        if (progress === 0) return 'text-gray-500';
        if (progress === 100) return 'text-green-600';
        return 'text-blue-600';
    }

    // STATE FUNCTIONS
    function showEmptyState() {
        document.getElementById('ganttLoading').innerHTML = '<div class="text-center py-12"><svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg><h3 class="mt-2 text-sm font-medium text-gray-900">No Activities</h3><p class="mt-1 text-sm text-gray-500">Add activities to see them in Gantt Chart.</p></div>';
    }

    function showErrorState(error) {
        var errorMessage = error.response?.data?.message || error.message || 'Unknown error';
        document.getElementById('ganttLoading').innerHTML = '<div class="text-center py-12 text-red-600"><svg class="mx-auto h-12 w-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg><p class="mt-2 text-sm">Error loading Gantt Chart</p><p class="text-xs text-gray-500 mt-1">' + errorMessage + '</p></div>';
    }

    // AUTO-INIT ON VIEW CHANGE
    var checkInterval = setInterval(function() {
        var ganttContainer = document.getElementById('ganttViewContainer');
        if (ganttContainer && !ganttContainer.classList.contains('hidden') && !ganttInitialized) {
            clearInterval(checkInterval);
            setTimeout(window.loadGanttChartView, 200);
        }
    }, 100);

    setTimeout(function() { clearInterval(checkInterval); }, 10000);
})();
</script>

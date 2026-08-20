<!-- ✅ ENHANCED S-CURVE VIEW with Professional Visualization -->
<div id="scurveViewContainer" class="bg-white rounded-lg shadow" data-project-id="{{ $project->id ?? '' }}">
    <!-- Header with Actions -->
    <div class="px-3 sm:px-4 py-3 bg-gradient-to-r from-blue-600 to-indigo-600 border-b border-blue-700 rounded-t-lg">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
            <div class="flex items-center">
                <svg class="w-6 h-6 text-white mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
                <h3 class="text-base sm:text-lg font-bold text-white">S-Curve Progress Analysis</h3>
            </div>
            <div class="flex items-center space-x-2 w-full sm:w-auto">
                <button onclick="refreshSCurve()" 
                        class="flex-1 sm:flex-initial inline-flex items-center justify-center px-3 py-2 bg-white bg-opacity-20 hover:bg-opacity-30 text-white text-sm font-medium rounded-lg transition">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    Refresh
                </button>
                
                <!-- View Mode Toggle -->
                <div class="flex-1 sm:flex-initial inline-flex rounded-lg shadow-sm bg-white bg-opacity-20 p-1" role="group">
                    <button type="button" 
                            data-mode="cumulative"
                            onclick="switchSCurveMode('cumulative')"
                            class="scurve-mode-toggle flex-1 sm:flex-initial px-3 py-1.5 text-xs font-semibold text-white bg-white bg-opacity-30 rounded-md transition">
                        Cumulative
                    </button>
                    <button type="button"
                            data-mode="weekly"
                            onclick="switchSCurveMode('weekly')"
                            class="scurve-mode-toggle flex-1 sm:flex-initial px-3 py-1.5 text-xs font-semibold text-white hover:bg-white hover:bg-opacity-20 rounded-md transition">
                        Weekly
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Enhanced Statistics Cards -->
    <div class="px-3 sm:px-4 py-4 bg-gradient-to-br from-gray-50 to-blue-50 border-b border-gray-200">
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            <!-- Total Tasks -->
            <div class="bg-white rounded-xl p-3 sm:p-4 shadow-md hover:shadow-lg transition-shadow border-l-4 border-gray-500">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-2xl sm:text-3xl font-bold text-gray-900" id="scurveTotalTasks">0</div>
                    <div class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center">
                        <svg class="w-5 h-5 text-gray-600" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"></path>
                            <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                </div>
                <div class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Total Tasks</div>
            </div>
            
            <!-- Completed -->
            <div class="bg-white rounded-xl p-3 sm:p-4 shadow-md hover:shadow-lg transition-shadow border-l-4 border-green-500">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-2xl sm:text-3xl font-bold text-green-600" id="scurveCompleted">0</div>
                    <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                        <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                </div>
                <div class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Completed</div>
                <div class="text-xs text-green-600 font-medium mt-1" id="scurveCompletedPercent">0%</div>
            </div>
            
            <!-- On Track -->
            <div class="bg-white rounded-xl p-3 sm:p-4 shadow-md hover:shadow-lg transition-shadow border-l-4 border-blue-500">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-2xl sm:text-3xl font-bold text-blue-600" id="scurveOnTrack">0</div>
                    <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                        <svg class="w-5 h-5 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                </div>
                <div class="text-xs font-semibold text-gray-600 uppercase tracking-wide">On Track</div>
                <div class="text-xs text-blue-600 font-medium mt-1" id="scurveOnTrackPercent">0%</div>
            </div>
            
            <!-- Delayed -->
            <div class="bg-white rounded-xl p-3 sm:p-4 shadow-md hover:shadow-lg transition-shadow border-l-4 border-red-500">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-2xl sm:text-3xl font-bold text-red-600" id="scurveDelayed">0</div>
                    <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                        <svg class="w-5 h-5 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                </div>
                <div class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Delayed</div>
                <div class="text-xs text-red-600 font-medium mt-1" id="scurveDelayedPercent">0%</div>
            </div>
            
            <!-- Not Started -->
            <div class="bg-white rounded-xl p-3 sm:p-4 shadow-md hover:shadow-lg transition-shadow border-l-4 border-gray-400">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-2xl sm:text-3xl font-bold text-gray-600" id="scurveNotStarted">0</div>
                    <div class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center">
                        <svg class="w-5 h-5 text-gray-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM7 9a1 1 0 000 2h6a1 1 0 100-2H7z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                </div>
                <div class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Not Started</div>
                <div class="text-xs text-gray-600 font-medium mt-1" id="scurveNotStartedPercent">0%</div>
            </div>
            
            <!-- Overall Progress -->
            <div class="col-span-2 sm:col-span-1 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-xl p-3 sm:p-4 shadow-md hover:shadow-lg transition-shadow">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-2xl sm:text-3xl font-bold text-white" id="scurveOverallProgress">0%</div>
                    <div class="w-10 h-10 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                </div>
                <div class="text-xs font-semibold text-white uppercase tracking-wide">Overall Progress</div>
                <div class="mt-2 bg-white bg-opacity-20 rounded-full h-2">
                    <div id="scurveOverallProgressBar" class="bg-white h-2 rounded-full transition-all duration-500" style="width: 0%"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Chart Container -->
    <div class="bg-white rounded-lg overflow-hidden">
        <div id="scurveLoading" class="flex items-center justify-center py-20">
            <div class="text-center">
                <svg class="animate-spin h-10 w-10 text-blue-600 mx-auto mb-3" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="text-sm text-gray-600 font-medium">Loading S-Curve Analysis...</p>
            </div>
        </div>
        
        <div id="scurveChartContent" class="hidden p-4 sm:p-6">
            <!-- Report Title -->
            <h3 class="text-center text-xl sm:text-2xl font-extrabold text-blue-800 tracking-wide mb-4">Project Progress</h3>

            <div class="grid grid-cols-1 xl:grid-cols-4 gap-4">
                <!-- Chart + aligned data grid -->
                <div class="xl:col-span-3 bg-white rounded-lg border-2 border-gray-200 p-3 sm:p-4">
                    <div class="relative" style="height: 400px;">
                        <canvas id="scurveChart"></canvas>
                    </div>

                    <!-- Excel-style data rows (aligned to chart x-axis) -->
                    <div id="scurveGridWrap" class="mt-2 select-none text-[10px] sm:text-[11px]">
                        <div id="scurveGridActual" class="flex items-stretch border-t border-gray-200"></div>
                        <div id="scurveGridPlan" class="flex items-stretch border-t border-b border-gray-200"></div>
                    </div>
                </div>

                <!-- Summary panel -->
                <aside class="xl:col-span-1 space-y-3">
                    <div class="border-2 border-gray-300 rounded-lg overflow-hidden">
                        <div class="grid grid-cols-3 bg-gray-100 text-center text-[11px] font-bold text-gray-700 uppercase tracking-wide">
                            <div class="px-2 py-2 border-r border-gray-300">Plan</div>
                            <div class="px-2 py-2 border-r border-gray-300">Actual</div>
                            <div class="px-2 py-2">Deviation</div>
                        </div>
                        <div class="grid grid-cols-3 text-center">
                            <div id="scurveSummaryPlan" class="px-2 py-3 text-lg font-bold text-purple-700 border-r border-gray-300">0%</div>
                            <div id="scurveSummaryActual" class="px-2 py-3 text-lg font-bold text-orange-600 border-r border-gray-300">0%</div>
                            <div id="scurveSummaryDeviation" class="px-2 py-3 text-lg font-bold text-gray-700">0%</div>
                        </div>
                    </div>

                    <div class="border-2 border-gray-300 rounded-lg px-3 py-2">
                        <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wide">Current Phase</span>
                        <div id="scurveSummaryPhase" class="text-sm font-bold text-gray-900 mt-0.5">-</div>
                    </div>

                    <div class="border-2 border-gray-300 rounded-lg px-3 py-2">
                        <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wide">Deviation</span>
                        <ul id="scurveSummaryNotes" class="mt-1 space-y-1 text-xs text-gray-700 list-disc list-inside"></ul>
                    </div>

                    <div id="scurveSummaryStatus" class="rounded-lg px-3 py-2 border-l-4 text-xs font-semibold"></div>
                </aside>
            </div>
            
            <!-- Data Table (Mobile Scrollable) -->
            <div class="mt-8">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="text-base font-bold text-gray-900">📊 Weekly Progress Data</h4>
                    <div class="lg:hidden text-xs text-yellow-600 flex items-center">
                        <svg class="w-3 h-3 mr-1 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"></path>
                        </svg>
                        Scroll to see all →
                    </div>
                </div>
                
                <div class="overflow-x-auto rounded-lg border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200 text-xs sm:text-sm">
                        <thead class="bg-gradient-to-r from-gray-100 to-gray-50">
                            <tr>
                                <th class="px-3 sm:px-4 py-3 text-left font-bold text-gray-700 uppercase tracking-wider">Week</th>
                                <th class="px-3 sm:px-4 py-3 text-center font-bold text-gray-700 uppercase tracking-wider">Date</th>
                                <th class="px-3 sm:px-4 py-3 text-right font-bold text-blue-700 uppercase tracking-wider bg-blue-50">Planned %</th>
                                <th class="px-3 sm:px-4 py-3 text-right font-bold text-green-700 uppercase tracking-wider bg-green-50">Actual %</th>
                                <th class="px-3 sm:px-4 py-3 text-right font-bold text-purple-700 uppercase tracking-wider bg-purple-50">Variance</th>
                                <th class="px-3 sm:px-4 py-3 text-center font-bold text-gray-700 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody id="scurveTableBody" class="bg-white divide-y divide-gray-200">
                            <!-- Data will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Enhanced Legend -->
    <div class="px-4 py-4 bg-gradient-to-r from-gray-50 to-blue-50 border-t border-gray-200 rounded-b-lg">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div class="flex-1">
                <span class="text-xs font-bold text-gray-700 block mb-3 uppercase tracking-wide">📈 Chart Lines</span>
                <div class="flex flex-wrap items-center gap-4 text-sm">
                    <div class="flex items-center bg-white px-3 py-2 rounded-lg shadow-sm">
                        <div class="w-10 h-1 mr-2 rounded" style="background-color:#7030A0"></div>
                        <span class="font-medium text-gray-700">Plan</span>
                    </div>
                    <div class="flex items-center bg-white px-3 py-2 rounded-lg shadow-sm">
                        <div class="w-10 h-1 mr-2 rounded" style="background-color:#ED7D31"></div>
                        <span class="font-medium text-gray-700">Actual</span>
                    </div>
                    <div class="flex items-center bg-white px-3 py-2 rounded-lg shadow-sm">
                        <div class="w-1 h-4 mr-2 rounded" style="background-color:#1F4E79"></div>
                        <span class="font-medium text-gray-700">Latest Week</span>
                    </div>
                </div>
            </div>
            
            <div class="flex-1">
                <span class="text-xs font-bold text-gray-700 block mb-3 uppercase tracking-wide">🎯 Project Phases</span>
                <div id="scurvePhasesLegend" class="flex flex-wrap gap-2"></div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
(function() {
    'use strict';
    
    
    // ==========================================
    // GLOBAL VARIABLES
    // ==========================================
    let scurveChart = null;
    let scurveData = null;
    let currentMode = 'cumulative';

    // Palet mengikuti format laporan Project Progress
    const PLAN_COLOR = '#7030A0';
    const ACTUAL_COLOR = '#ED7D31';
    const IS_DARK = '{{ (session('user_preferences')['theme'] ?? 'light') === 'dark' ? '1' : '0' }}' === '1';
    const TEXT_COLOR = IS_DARK ? '#e5e7eb' : '#374151';
    const AXIS_COLOR = IS_DARK ? '#9ca3af' : '#4b5563';
    const GRID_COLOR = IS_DARK ? 'rgba(255,255,255,0.09)' : 'rgba(0, 0, 0, 0.08)';
    
    // ==========================================
    // GET PROJECT ID
    // ==========================================
    function getProjectId() {
        const container = document.getElementById('scurveViewContainer');
        if (container && container.dataset.projectId) {
            return container.dataset.projectId;
        }
        
        if (typeof window.projectId !== 'undefined') {
            return window.projectId;
        }
        
        const urlMatch = window.location.pathname.match(/project-planning\/(\d+)/);
        if (urlMatch) {
            return urlMatch[1];
        }
        
        console.error('❌ No project ID found');
        return null;
    }
    
    // ==========================================
    // LOAD S-CURVE DATA
    // ==========================================
    window.loadSCurveView = function() {
        
        const projectId = getProjectId();
        if (!projectId) {
            console.error('❌ No project ID found');
            showEmptyState();
            return;
        }
        

        axios.get(`/planning/${projectId}/data/scurve`)
            .then(function(response) {
                scurveData = response.data;
                
                if (!scurveData.weekly_data || scurveData.weekly_data.length === 0) {
                    showEmptyState();
                    return;
                }
                
                updateStatistics(scurveData.statistics);
                updateSummaryPanel(scurveData);
                renderSCurveChart();
                renderPhasesLegend(scurveData.phases);
                updateDataTable();
                
                document.getElementById('scurveLoading').classList.add('hidden');
                document.getElementById('scurveChartContent').classList.remove('hidden');
            })
            .catch(function(error) {
                console.error('❌ Error loading S-Curve data:', error);
                showErrorState(error);
            });
    };
    
    // ==========================================
    // UPDATE SUMMARY PANEL (Plan / Actual / Deviation)
    // ==========================================
    function updateSummaryPanel(data) {
        if (!data.weekly_data || data.weekly_data.length === 0) return;

        const latest = data.weekly_data.find(w => w.is_latest) || data.weekly_data[data.weekly_data.length - 1];
        const summary = data.summary || {};

        const plan = summary.plan !== undefined ? summary.plan : latest.planned_cumulative;
        const actual = summary.actual !== undefined ? summary.actual : latest.actual_cumulative;
        const deviation = summary.deviation !== undefined ? summary.deviation : (actual - plan);

        document.getElementById('scurveSummaryPlan').textContent = fmtPct(plan);
        document.getElementById('scurveSummaryActual').textContent = fmtPct(actual);

        const devEl = document.getElementById('scurveSummaryDeviation');
        devEl.textContent = (deviation > 0 ? '+' : '') + fmtPct(deviation);
        devEl.classList.remove('text-gray-700', 'text-red-600', 'text-green-600');
        devEl.classList.add(deviation < -0.05 ? 'text-red-600' : (deviation > 0.05 ? 'text-green-600' : 'text-gray-700'));

        document.getElementById('scurveSummaryPhase').textContent = summary.current_phase || '-';

        // Catatan deviasi
        const notesEl = document.getElementById('scurveSummaryNotes');
        notesEl.innerHTML = '';
        const notes = (summary.deviation_notes && summary.deviation_notes.length)
            ? summary.deviation_notes
            : ['Tidak ada keterlambatan tercatat'];
        notes.forEach(function(note) {
            const li = document.createElement('li');
            li.textContent = note;
            notesEl.appendChild(li);
        });

        // Status ringkas
        const statusEl = document.getElementById('scurveSummaryStatus');
        statusEl.classList.remove('bg-red-50', 'border-red-500', 'text-red-700',
                                  'bg-green-50', 'border-green-500', 'text-green-700',
                                  'bg-blue-50', 'border-blue-500', 'text-blue-700');
        if (deviation < -10) {
            statusEl.classList.add('bg-red-50', 'border-red-500', 'text-red-700');
            statusEl.textContent = 'Behind schedule ' + fmtPct(Math.abs(deviation)) + ' dari rencana.';
        } else if (deviation > 10) {
            statusEl.classList.add('bg-blue-50', 'border-blue-500', 'text-blue-700');
            statusEl.textContent = 'Ahead of schedule ' + fmtPct(deviation) + ' dari rencana.';
        } else {
            statusEl.classList.add('bg-green-50', 'border-green-500', 'text-green-700');
            statusEl.textContent = 'On track. Deviasi ' + fmtPct(deviation) + '.';
        }
    }

    function fmtPct(value) {
        return (Math.round((value || 0) * 10) / 10).toFixed(1) + '%';
    }

    // ==========================================
    // PLUGIN: garis vertikal "Latest Week"
    // ==========================================
    const latestWeekMarker = {
        id: 'latestWeekMarker',
        afterDatasetsDraw(chart) {
            const idx = chart.$latestWeekIndex;
            if (idx === null || idx === undefined || idx < 0) return;

            const x = chart.scales.x.getPixelForValue(idx);
            const area = chart.chartArea;
            const ctx = chart.ctx;

            ctx.save();
            ctx.strokeStyle = '#1F4E79';
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.moveTo(x, area.top);
            ctx.lineTo(x, area.bottom);
            ctx.stroke();

            // Kepala panah ke bawah
            ctx.beginPath();
            ctx.moveTo(x, area.bottom);
            ctx.lineTo(x - 5, area.bottom - 9);
            ctx.lineTo(x + 5, area.bottom - 9);
            ctx.closePath();
            ctx.fillStyle = '#1F4E79';
            ctx.fill();

            // Label
            ctx.font = 'bold 11px sans-serif';
            ctx.fillStyle = '#1F4E79';
            ctx.textBaseline = 'bottom';
            const label = 'Latest Week';
            const w = ctx.measureText(label).width;
            let lx = x - w / 2;
            lx = Math.max(area.left, Math.min(lx, area.right - w));
            ctx.fillText(label, lx, area.top - 4);
            ctx.restore();
        }
    };

    // ==========================================
    // PLUGIN: sinkron tabel data dengan sumbu X
    // ==========================================
    const alignedDataGrid = {
        id: 'alignedDataGrid',
        afterRender(chart) {
            if (chart.config.type !== 'line') {
                hideDataGrid();
                return;
            }
            renderAlignedDataGrid(chart);
        }
    };

    function hideDataGrid() {
        const wrap = document.getElementById('scurveGridWrap');
        if (!wrap) return;
        wrap.classList.add('hidden');
        delete wrap.dataset.signature;
    }

    function resetDataGridCache() {
        const wrap = document.getElementById('scurveGridWrap');
        if (wrap) delete wrap.dataset.signature;
    }

    function renderAlignedDataGrid(chart) {
        const wrap = document.getElementById('scurveGridWrap');
        const rowActual = document.getElementById('scurveGridActual');
        const rowPlan = document.getElementById('scurveGridPlan');
        if (!wrap || !rowActual || !rowPlan || !scurveData) return;

        // Terlalu banyak minggu -> sel jadi tak terbaca, cukup andalkan tabel detail
        if (scurveData.weekly_data.length > 40) {
            hideDataGrid();
            return;
        }

        wrap.classList.remove('hidden');

        const area = chart.chartArea;

        // afterRender juga terpanggil tiap hover; bangun ulang hanya saat geometri berubah
        const signature = [
            Math.round(area.left), Math.round(area.right), Math.round(chart.width),
            scurveData.weekly_data.length
        ].join('|');
        if (wrap.dataset.signature === signature) return;
        wrap.dataset.signature = signature;

        // Lebar kolom label = lebar area sumbu Y, supaya tiap nilai jatuh tepat
        // di bawah titik minggunya (persis tabel data di bawah grafik Excel).
        const labelWidth = Math.round(area.left);

        const build = function(row, title, color, values) {
            row.innerHTML = '';
            row.style.width = chart.width + 'px';

            const label = document.createElement('div');
            label.textContent = title;
            label.style.width = labelWidth + 'px';
            label.style.flex = '0 0 ' + labelWidth + 'px';
            label.style.color = color;
            label.className = 'px-1 py-1 font-bold text-right border-r border-gray-200 truncate';
            row.appendChild(label);

            const cells = document.createElement('div');
            cells.className = 'flex';
            cells.style.width = Math.round(area.right - area.left) + 'px';

            values.forEach(function(v) {
                const cell = document.createElement('div');
                cell.textContent = (Math.round(v * 10) / 10).toFixed(0) + '%';
                cell.className = 'flex-1 min-w-0 py-1 text-center text-gray-600 border-r border-gray-100 truncate';
                cells.appendChild(cell);
            });

            row.appendChild(cells);
        };

        build(rowActual, 'Actual', ACTUAL_COLOR, scurveData.weekly_data.map(d => d.actual_cumulative));
        build(rowPlan, 'Plan', PLAN_COLOR, scurveData.weekly_data.map(d => d.planned_cumulative));
    }

    // ==========================================
    // RENDER S-CURVE CHART (format laporan Project Progress)
    // ==========================================
    function renderSCurveChart() {
        const canvas = document.getElementById('scurveChart');
        if (!canvas) {
            console.error('❌ Canvas element not found');
            return;
        }

        const ctx = canvas.getContext('2d');

        if (scurveChart) {
            scurveChart.destroy();
        }

        resetDataGridCache();

        const weeks = scurveData.weekly_data;
        const labels = weeks.map((d, i) => d.week_index || (i + 1));
        const plannedData = weeks.map(d => d.planned_cumulative);
        const actualData = weeks.map(d => d.actual_cumulative);

        let latestIndex = weeks.findIndex(d => d.is_latest);
        if (latestIndex < 0) latestIndex = weeks.length - 1;

        scurveChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Actual',
                        data: actualData,
                        borderColor: ACTUAL_COLOR,
                        backgroundColor: ACTUAL_COLOR,
                        borderWidth: 2,
                        tension: 0,
                        fill: false,
                        pointStyle: 'rectRot',
                        pointRadius: 4,
                        pointHoverRadius: 7,
                        pointBackgroundColor: ACTUAL_COLOR,
                        pointBorderColor: ACTUAL_COLOR,
                        pointBorderWidth: 1,
                    },
                    {
                        label: 'Plan',
                        data: plannedData,
                        borderColor: PLAN_COLOR,
                        backgroundColor: PLAN_COLOR,
                        borderWidth: 2,
                        tension: 0,
                        fill: false,
                        pointStyle: 'rectRot',
                        pointRadius: 4,
                        pointHoverRadius: 7,
                        pointBackgroundColor: PLAN_COLOR,
                        pointBorderColor: PLAN_COLOR,
                        pointBorderWidth: 1,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: { padding: { top: 22 } },
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            pointStyle: 'rectRot',
                            padding: 18,
                            font: { size: 12, weight: 'bold' },
                            color: TEXT_COLOR
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        titleFont: { size: 13, weight: 'bold' },
                        bodyFont: { size: 12 },
                        callbacks: {
                            title: function(items) {
                                const w = scurveData.weekly_data[items[0].dataIndex];
                                return 'Week ' + (w.week_index || (items[0].dataIndex + 1)) + ' — ' + w.week_label;
                            },
                            label: function(context) {
                                return context.dataset.label + ': ' + context.parsed.y.toFixed(1) + '%';
                            },
                            afterBody: function(tooltipItems) {
                                const idx = tooltipItems[0].dataIndex;
                                const variance = scurveData.weekly_data[idx].variance;
                                return '\nDeviation: ' + (variance > 0 ? '+' : '') + variance.toFixed(1) + '%';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        title: {
                            display: true,
                            text: 'Progress',
                            font: { size: 12, weight: 'bold' },
                            color: TEXT_COLOR
                        },
                        ticks: {
                            stepSize: 10,
                            callback: function(value) { return value + '%'; },
                            font: { size: 11 },
                            color: AXIS_COLOR
                        },
                        grid: { color: GRID_COLOR, drawBorder: false }
                    },
                    x: {
                        // offset:true -> titik berada di tengah kolom, sejajar
                        // dengan sel tabel data di bawah grafik
                        offset: true,
                        grid: { display: false, drawBorder: false },
                        ticks: {
                            autoSkip: weeks.length > 30,
                            maxRotation: 0,
                            minRotation: 0,
                            font: { size: 10, weight: 'bold' },
                            color: AXIS_COLOR
                        }
                    }
                }
            },
            plugins: [latestWeekMarker, alignedDataGrid]
        });

        scurveChart.$latestWeekIndex = latestIndex;
        scurveChart.update('none');
    }

    // ==========================================
    // UPDATE STATISTICS
    // ==========================================
    function updateStatistics(stats) {
        document.getElementById('scurveTotalTasks').textContent = stats.total_tasks;
        document.getElementById('scurveCompleted').textContent = stats.completed;
        document.getElementById('scurveOnTrack').textContent = stats.on_track;
        document.getElementById('scurveDelayed').textContent = stats.delayed;
        document.getElementById('scurveNotStarted').textContent = stats.not_started;
        document.getElementById('scurveOverallProgress').textContent = stats.overall_progress + '%';
        
        const progressBar = document.getElementById('scurveOverallProgressBar');
        if (progressBar) {
            setTimeout(() => { progressBar.style.width = stats.overall_progress + '%'; }, 100);
        }
        
        const total = stats.total_tasks;
        if (total > 0) {
            document.getElementById('scurveCompletedPercent').textContent = Math.round((stats.completed / total) * 100) + '%';
            document.getElementById('scurveOnTrackPercent').textContent = Math.round((stats.on_track / total) * 100) + '%';
            document.getElementById('scurveDelayedPercent').textContent = Math.round((stats.delayed / total) * 100) + '%';
            document.getElementById('scurveNotStartedPercent').textContent = Math.round((stats.not_started / total) * 100) + '%';
        }
    }
    
    // ==========================================
    // UPDATE DATA TABLE
    // ==========================================
    function updateDataTable() {
        const tbody = document.getElementById('scurveTableBody');
        if (!tbody) return;
        
        tbody.innerHTML = '';
        
        scurveData.weekly_data.forEach(function(week) {
            const tr = document.createElement('tr');
            const variance = week.variance;
            
            let varClass = 'text-gray-600';
            let statusIcon = '●';
            let statusClass = 'text-gray-600';
            let statusText = 'On Track';
            
            if (variance < -5) {
                varClass = 'text-red-600 font-bold';
                statusIcon = '⚠️';
                statusClass = 'text-red-600 font-semibold';
                statusText = 'Behind';
            } else if (variance > 5) {
                varClass = 'text-blue-600 font-bold';
                statusIcon = '✓';
                statusClass = 'text-blue-600 font-semibold';
                statusText = 'Ahead';
            } else {
                varClass = 'text-green-600 font-semibold';
                statusIcon = '✓';
                statusClass = 'text-green-600 font-semibold';
            }
            
            // ✅ UPDATED: Show date as main label with week number below
            if (week.is_latest) {
                tr.classList.add('bg-yellow-50');
            }

            tr.innerHTML = `
                <td class="px-3 sm:px-4 py-3 whitespace-nowrap">
                    <div class="flex flex-col">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-100 text-blue-800">
                            Week ${week.week_index || ''}${week.is_latest ? ' • Latest' : ''}
                        </span>
                        <span class="text-[10px] text-gray-500 mt-1 text-center">${week.week_label} (${week.week_number || ''})</span>
                    </div>
                </td>
                <td class="px-3 sm:px-4 py-3 whitespace-nowrap text-center text-gray-600 text-xs font-medium">${week.date_label}</td>
                <td class="px-3 sm:px-4 py-3 whitespace-nowrap text-right font-bold text-blue-700 bg-blue-50">${week.planned_cumulative.toFixed(1)}%</td>
                <td class="px-3 sm:px-4 py-3 whitespace-nowrap text-right font-bold text-green-700 bg-green-50">${week.actual_cumulative.toFixed(1)}%</td>
                <td class="px-3 sm:px-4 py-3 whitespace-nowrap text-right ${varClass} bg-purple-50">${(week.variance > 0 ? '+' : '')}${week.variance.toFixed(1)}%</td>
                <td class="px-3 sm:px-4 py-3 whitespace-nowrap text-center ${statusClass}">${statusIcon} ${statusText}</td>
            `;
            
            tr.addEventListener('mouseenter', function() { this.classList.add('bg-gray-50'); });
            tr.addEventListener('mouseleave', function() { this.classList.remove('bg-gray-50'); });
            
            tbody.appendChild(tr);
        });
    }
    
    // ==========================================
    // RENDER PHASES LEGEND
    // ==========================================
    function renderPhasesLegend(phases) {
        const container = document.getElementById('scurvePhasesLegend');
        if (!container) return;
        
        container.innerHTML = '';
        
        phases.forEach(function(phase) {
            const div = document.createElement('div');
            div.innerHTML = `
                <div class="w-4 h-4 rounded-full mr-2 shadow-inner" style="background-color: ${phase.color}"></div>
                <span class="text-xs font-semibold text-gray-700">${phase.name}</span>
                <span class="ml-2 px-2 py-0.5 bg-gray-100 rounded text-xs font-bold text-gray-600">${phase.weight}%</span>
            `;
            div.classList.add('flex', 'items-center', 'px-3', 'py-2', 'bg-white', 'rounded-lg', 'shadow-sm', 'border', 'border-gray-200', 'hover:shadow-md', 'transition-shadow');
            container.appendChild(div);
        });
    }
    
    // ==========================================
    // SWITCH MODE
    // ==========================================
    window.switchSCurveMode = function(mode) {
        currentMode = mode;
        
        document.querySelectorAll('.scurve-mode-toggle').forEach(function(btn) {
            btn.classList.remove('bg-white', 'bg-opacity-30', 'hover:bg-white', 'hover:bg-opacity-20');
            if (btn.dataset.mode === mode) {
                btn.classList.add('bg-white', 'bg-opacity-30');
            } else {
                btn.classList.add('hover:bg-white', 'hover:bg-opacity-20');
            }
        });
        
        if (mode === 'weekly') {
            renderWeeklyChart();
        } else {
            renderSCurveChart();
        }
    };
    
    // ==========================================
    // RENDER WEEKLY CHART
    // ==========================================
    function renderWeeklyChart() {
        const canvas = document.getElementById('scurveChart');
        if (!canvas) return;

        hideDataGrid();

        const ctx = canvas.getContext('2d');
        if (scurveChart) scurveChart.destroy();

        const labels = scurveData.weekly_data.map((d, i) => d.week_index || (i + 1));
        const plannedWeekly = [];
        const actualWeekly = [];
        
        for (let i = 0; i < scurveData.weekly_data.length; i++) {
            const curr = scurveData.weekly_data[i];
            const prev = i > 0 ? scurveData.weekly_data[i - 1] : { planned_cumulative: 0, actual_cumulative: 0 };
            plannedWeekly.push(curr.planned_cumulative - prev.planned_cumulative);
            actualWeekly.push(curr.actual_cumulative - prev.actual_cumulative);
        }
        
        scurveChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Plan (per week)',
                        data: plannedWeekly,
                        backgroundColor: PLAN_COLOR,
                        borderColor: PLAN_COLOR,
                        borderWidth: 1,
                        borderRadius: 4,
                    },
                    {
                        label: 'Actual (per week)',
                        data: actualWeekly,
                        backgroundColor: ACTUAL_COLOR,
                        borderColor: ACTUAL_COLOR,
                        borderWidth: 1,
                        borderRadius: 4,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: true, position: 'top', labels: { padding: 20, font: { size: 13, weight: 'bold' }, color: '{{ (session('user_preferences')['theme'] ?? 'light') === 'dark' ? '#e5e7eb' : '#374151' }}' } },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        callbacks: {
                            label: function(ctx) { return ctx.dataset.label + ': ' + ctx.parsed.y.toFixed(2) + '%'; }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: '{{ (session('user_preferences')['theme'] ?? 'light') === 'dark' ? 'rgba(255,255,255,0.09)' : 'rgba(0, 0, 0, 0.05)' }}' },
                        ticks: { callback: function(val) { return val + '%'; }, font: { weight: 'bold' }, color: '{{ (session('user_preferences')['theme'] ?? 'light') === 'dark' ? '#9ca3af' : '#6b7280' }}' }
                    },
                    x: { grid: { display: false }, ticks: { font: { weight: 'bold' }, color: '{{ (session('user_preferences')['theme'] ?? 'light') === 'dark' ? '#9ca3af' : '#6b7280' }}' } }
                }
            }
        });
    }
    
    // ==========================================
    // REFRESH
    // ==========================================
    window.refreshSCurve = function() {
        document.getElementById('scurveLoading').classList.remove('hidden');
        document.getElementById('scurveChartContent').classList.add('hidden');
        setTimeout(window.loadSCurveView, 300);
    };
    
    // ==========================================
    // STATE FUNCTIONS
    // ==========================================
    function showEmptyState() {
        document.getElementById('scurveLoading').innerHTML = `
            <div class="text-center py-16">
                <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
                <h3 class="mt-4 text-base font-semibold text-gray-900">No Data Available</h3>
                <p class="mt-2 text-sm text-gray-500">Add activities with dates to generate S-Curve analysis.</p>
            </div>
        `;
    }
    
    function showErrorState(error) {
        const msg = error.response?.data?.message || error.message || 'Unknown error';
        document.getElementById('scurveLoading').innerHTML = `
            <div class="text-center py-16">
                <svg class="mx-auto h-16 w-16 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <h3 class="mt-4 text-base font-semibold text-red-600">Error Loading S-Curve</h3>
                <p class="text-sm text-gray-500 mt-2">${msg}</p>
                <button onclick="window.loadSCurveView()" class="mt-4 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">Retry</button>
            </div>
        `;
    }
    
    // ==========================================
    // AUTO-INIT
    // ==========================================
    let checkInterval = setInterval(function() {
        const container = document.getElementById('scurveViewContainer');
        if (container && !container.classList.contains('hidden')) {
            clearInterval(checkInterval);
            setTimeout(window.loadSCurveView, 200);
        }
    }, 100);
    
    setTimeout(function() { clearInterval(checkInterval); }, 10000);
    
})();
</script>
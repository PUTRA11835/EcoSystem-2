{{-- ============================================================================ --}}
{{-- ENHANCED S-CURVE VIEW for Support Planning --}}
{{-- ============================================================================ --}}
<div id="scurveViewContainer" class="bg-white rounded-lg shadow" data-support-id="{{ $support->id }}">
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
                <div class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Total Activities</div>
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

        <div id="scurveChartContent" class="hidden p-6">
            <!-- Progress Status Banner -->
            <div id="scurveStatusBanner" class="mb-6 p-4 rounded-lg border-l-4 hidden">
                <div class="flex items-center">
                    <svg id="scurveStatusIcon" class="w-6 h-6 mr-3" fill="currentColor" viewBox="0 0 20 20"></svg>
                    <div class="flex-1">
                        <h4 id="scurveStatusTitle" class="font-bold text-sm"></h4>
                        <p id="scurveStatusMessage" class="text-xs mt-1"></p>
                    </div>
                    <div id="scurveStatusPercentage" class="text-2xl font-bold ml-4"></div>
                </div>
            </div>

            <!-- Chart Canvas -->
            <div class="relative bg-white rounded-lg border-2 border-gray-100 p-4" style="height: 450px;">
                <canvas id="scurveChart"></canvas>
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
                        <div class="w-10 h-1 bg-blue-500 mr-2 rounded"></div>
                        <span class="font-medium text-gray-700">Planned</span>
                    </div>
                    <div class="flex items-center bg-white px-3 py-2 rounded-lg shadow-sm">
                        <div class="w-10 h-1 bg-green-500 mr-2 rounded"></div>
                        <span class="font-medium text-gray-700">Actual</span>
                    </div>
                    <div class="flex items-center bg-white px-3 py-2 rounded-lg shadow-sm">
                        <div class="w-10 h-1 bg-red-500 border-t-2 border-dashed border-red-500 mr-2"></div>
                        <span class="font-medium text-gray-700">Behind Schedule</span>
                    </div>
                </div>
            </div>
            <div class="flex-1">
                <span class="text-xs font-bold text-gray-700 block mb-3 uppercase tracking-wide">🎯 Support Phases</span>
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
    console.log('🚀 Enhanced S-Curve View Initializing...');

    // ==========================================
    // GLOBAL VARIABLES
    // ==========================================
    let scurveChart = null;
    let scurveData = null;
    let currentMode = 'cumulative';

    // ==========================================
    // GET SUPPORT ID
    // ==========================================
    function getSupportId() {
        const container = document.getElementById('scurveViewContainer');
        if (container && container.dataset.supportId) {
            return container.dataset.supportId;
        }
        if (typeof window.supportId !== 'undefined') {
            return window.supportId;
        }
        const urlMatch = window.location.pathname.match(/support\/(\d+)/);
        if (urlMatch) {
            return urlMatch[1];
        }
        console.error('❌ No support ID found');
        return null;
    }

    // ==========================================
    // LOAD S-CURVE DATA
    // ==========================================
    window.loadSCurveView = function() {
        console.log('📊 Loading Enhanced S-Curve View...');
        const supportId = getSupportId();
        if (!supportId) {
            console.error('❌ No support ID found');
            showEmptyState();
            return;
        }

        console.log('🔍 Fetching S-Curve data for support:', supportId);

        axios.get(`/delivery/support/${supportId}/data/scurve`)
            .then(function(response) {
                console.log('✅ S-Curve data loaded:', response.data);
                scurveData = response.data;

                if (!scurveData.weekly_data || scurveData.weekly_data.length === 0) {
                    showEmptyState();
                    return;
                }

                updateStatistics(scurveData.statistics);
                updateStatusBanner(scurveData);
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
    // UPDATE STATUS BANNER
    // ==========================================
    function updateStatusBanner(data) {
        const banner = document.getElementById('scurveStatusBanner');
        const icon = document.getElementById('scurveStatusIcon');
        const title = document.getElementById('scurveStatusTitle');
        const message = document.getElementById('scurveStatusMessage');
        const percentage = document.getElementById('scurveStatusPercentage');

        if (!data.weekly_data || data.weekly_data.length === 0) return;

        const lastWeek = data.weekly_data[data.weekly_data.length - 1];
        const variance = lastWeek.variance;

        // Remove hidden class
        banner.classList.remove('hidden');

        // Remove all possible classes first
        banner.classList.remove('bg-red-50', 'border-red-500', 'bg-blue-50', 'border-blue-500', 'bg-green-50', 'border-green-500');
        icon.classList.remove('text-red-600', 'text-blue-600', 'text-green-600');
        title.classList.remove('text-red-800', 'text-blue-800', 'text-green-800');
        message.classList.remove('text-red-700', 'text-blue-700', 'text-green-700');
        percentage.classList.remove('text-red-600', 'text-blue-600', 'text-green-600');

        if (variance < -10) {
            // Behind Schedule
            banner.classList.add('bg-red-50', 'border-red-500');
            icon.classList.add('text-red-600');
            icon.innerHTML = '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>';
            title.classList.add('text-red-800');
            title.textContent = '⚠️ Support Behind Schedule';
            message.classList.add('text-red-700');
            message.textContent = 'Currently ' + Math.abs(variance).toFixed(1) + '% behind planned progress. Immediate action recommended.';
            percentage.classList.add('text-red-600');
            percentage.textContent = variance.toFixed(1) + '%';
        } else if (variance > 10) {
            // Ahead of Schedule
            banner.classList.add('bg-blue-50', 'border-blue-500');
            icon.classList.add('text-blue-600');
            icon.innerHTML = '<path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>';
            title.classList.add('text-blue-800');
            title.textContent = '🎉 Support Ahead of Schedule';
            message.classList.add('text-blue-700');
            message.textContent = 'Excellent progress! ' + variance.toFixed(1) + '% ahead of planned schedule. Maintain current momentum.';
            percentage.classList.add('text-blue-600');
            percentage.textContent = '+' + variance.toFixed(1) + '%';
        } else {
            // On Track
            banner.classList.add('bg-green-50', 'border-green-500');
            icon.classList.add('text-green-600');
            icon.innerHTML = '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>';
            title.classList.add('text-green-800');
            title.textContent = '✓ Support On Track';
            message.classList.add('text-green-700');
            message.textContent = 'Progress aligned with plan. Variance: ' + variance.toFixed(1) + '%. Continue monitoring key milestones.';
            percentage.classList.add('text-green-600');
            percentage.textContent = variance > 0 ? '+' + variance.toFixed(1) + '%' : variance.toFixed(1) + '%';
        }
    }

    // ==========================================
    // RENDER S-CURVE CHART
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

        const labels = scurveData.weekly_data.map(d => d.week_label);
        const plannedData = scurveData.weekly_data.map(d => d.planned_cumulative);
        const actualData = scurveData.weekly_data.map(d => d.actual_cumulative);

        const plannedGradient = ctx.createLinearGradient(0, 0, 0, 400);
        plannedGradient.addColorStop(0, 'rgba(59, 130, 246, 0.3)');
        plannedGradient.addColorStop(1, 'rgba(59, 130, 246, 0.05)');

        const actualGradient = ctx.createLinearGradient(0, 0, 0, 400);
        actualGradient.addColorStop(0, 'rgba(16, 185, 129, 0.3)');
        actualGradient.addColorStop(1, 'rgba(16, 185, 129, 0.05)');

        scurveChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Planned Progress',
                        data: plannedData,
                        borderColor: '#3b82f6',
                        backgroundColor: plannedGradient,
                        borderWidth: 3,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 5,
                        pointHoverRadius: 8,
                        pointBackgroundColor: '#3b82f6',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 3,
                    },
                    {
                        label: 'Actual Progress',
                        data: actualData,
                        borderColor: '#10b981',
                        backgroundColor: actualGradient,
                        borderWidth: 3,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 5,
                        pointHoverRadius: 8,
                        pointBackgroundColor: '#10b981',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 3,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 20,
                            font: { size: 13, weight: 'bold' },
                            color: '#374151'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        titleFont: { size: 14, weight: 'bold' },
                        bodyFont: { size: 13 },
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.parsed.y.toFixed(1) + '%';
                            },
                            afterBody: function(tooltipItems) {
                                const idx = tooltipItems[0].dataIndex;
                                const variance = scurveData.weekly_data[idx].variance;
                                return '\nVariance: ' + (variance > 0 ? '+' : '') + variance.toFixed(1) + '%';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 110,
                        grid: { color: 'rgba(0, 0, 0, 0.05)', drawBorder: false },
                        ticks: {
                            callback: function(value) { return value + '%'; },
                            font: { size: 12, weight: 'bold' },
                            color: '#6b7280'
                        }
                    },
                    x: {
                        grid: { display: false, drawBorder: false },
                        ticks: {
                            maxRotation: 45,
                            minRotation: 0,
                            font: { size: 11, weight: 'bold' },
                            color: '#6b7280'
                        }
                    }
                }
            }
        });

        console.log('✅ S-Curve chart rendered');
    }

    // ==========================================
    // UPDATE STATISTICS
    // ==========================================
    function updateStatistics(stats) {
        document.getElementById('scurveTotalTasks').textContent = stats.total_activities || 0;
        document.getElementById('scurveCompleted').textContent = stats.completed || 0;
        document.getElementById('scurveOnTrack').textContent = stats.on_track || 0;
        document.getElementById('scurveDelayed').textContent = stats.delayed || 0;
        document.getElementById('scurveNotStarted').textContent = stats.not_started || 0;
        document.getElementById('scurveOverallProgress').textContent = (stats.overall_progress || 0).toFixed(1) + '%';

        const progressBar = document.getElementById('scurveOverallProgressBar');
        if (progressBar) {
            setTimeout(() => {
                progressBar.style.width = (stats.overall_progress || 0) + '%';
            }, 100);
        }

        const total = stats.total_activities || 0;
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

            tr.innerHTML = `
                <td class="px-3 sm:px-4 py-3 whitespace-nowrap">
                    <div class="flex flex-col">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-100 text-blue-800">
                            ${week.week_label}
                        </span>
                        <span class="text-[10px] text-gray-500 mt-1 text-center">${week.week_number || ''}</span>
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

        const ctx = canvas.getContext('2d');
        if (scurveChart) scurveChart.destroy();

        const labels = scurveData.weekly_data.map(d => d.week_label);
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
                        label: 'Planned Weekly',
                        data: plannedWeekly,
                        backgroundColor: 'rgba(59, 130, 246, 0.8)',
                        borderColor: '#3b82f6',
                        borderWidth: 2,
                        borderRadius: 6,
                    },
                    {
                        label: 'Actual Weekly',
                        data: actualWeekly,
                        backgroundColor: 'rgba(16, 185, 129, 0.8)',
                        borderColor: '#10b981',
                        borderWidth: 2,
                        borderRadius: 6,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: true, position: 'top', labels: { padding: 20, font: { size: 13, weight: 'bold' } } },
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
                        grid: { color: 'rgba(0, 0, 0, 0.05)' },
                        ticks: { callback: function(val) { return val + '%'; }, font: { weight: 'bold' } }
                    },
                    x: { grid: { display: false }, ticks: { font: { weight: 'bold' } } }
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

    console.log('✅ Enhanced S-Curve View loaded');
})();
</script>
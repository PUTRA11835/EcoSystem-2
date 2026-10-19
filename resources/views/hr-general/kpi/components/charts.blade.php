{{--
    KPI dashboard — the two charts (average score trend, average score by position). The markup only: the dashboard script builds the charts
    into #trendChart / #deptChart and fills #trendEmpty / #deptEmpty when a chart has nothing to draw (or Chart.js could not be loaded),
    so a quiet period shows a sentence instead of an empty frame.
--}}
<div class="grid grid-cols-1 lg:grid-cols-5 gap-5">

    <div class="lg:col-span-3 bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
            <h3 class="text-sm font-semibold text-gray-700 flex items-center gap-2">
                <i class="fas fa-chart-line text-indigo-400"></i>
                Average KPI Score Trend <span class="text-[11px] font-normal text-gray-400">by assessment type</span>
            </h3>
            <div class="flex items-center gap-1" id="trendTabs">
                @foreach(['monthly' => 'Monthly', 'annual' => 'Annual'] as $key => $label)
                <button type="button" onclick="switchTrend('{{ $key }}')" id="tab-{{ $key }}"
                    class="px-3 py-1 text-xs font-medium rounded-lg transition-all
                        {{ $key === 'monthly' ? 'primary-gradient text-white shadow' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                    {{ $label }}
                </button>
                @endforeach
            </div>
        </div>
        <div id="trendBox" class="relative transition-opacity" style="height:200px;">
            <canvas id="trendChart"></canvas>
            <div id="trendEmpty" class="hidden absolute inset-0 flex items-center justify-center text-center text-xs text-gray-400 px-6"></div>
        </div>
    </div>

    <div class="lg:col-span-2 bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
        <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
            <i class="fas fa-user-tie text-blue-400"></i>
            Avg Score by Position
        </h3>
        <div class="relative" style="height:200px;">
            <canvas id="deptChart"></canvas>
            <div id="deptEmpty" class="hidden absolute inset-0 flex items-center justify-center text-center text-xs text-gray-400 px-6"></div>
        </div>
    </div>
</div>

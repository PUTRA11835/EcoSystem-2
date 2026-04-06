<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>S-Curve Analysis - {{ $project->name }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 9px;
            color: #1f2937;
            padding: 15px;
        }
        
        .page-break {
            page-break-after: always;
        }
        
        .header {
            margin-bottom: 15px;
            padding: 12px;
            background: #4f46e5;
            color: white;
            border-radius: 6px;
        }
        
        .header h1 {
            font-size: 18px;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .header-info {
            font-size: 9px;
        }
        
        .statistics {
            margin-bottom: 15px;
            width: 100%;
        }
        
        .stat-row {
            width: 100%;
        }
        
        .stat-card {
            display: inline-block;
            width: 16%;
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 6px;
            padding: 8px;
            text-align: center;
            margin-right: 0.5%;
            vertical-align: top;
        }
        
        .stat-value {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 3px;
        }
        
        .stat-label {
            font-size: 7px;
            color: #6b7280;
            text-transform: uppercase;
        }
        
        /* ===== CHART CONTAINER ===== */
        .chart-container {
            margin: 15px 0;
            padding: 15px;
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
        }
        
        .chart-title {
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 12px;
            text-align: center;
            color: #1f2937;
        }
        
        /* ===== BAR CHART STYLES ===== */
        .chart-bars {
            width: 100%;
            border: none;
            border-collapse: collapse;
            margin: 10px 0;
        }
        
        .chart-bars td {
            border: none;
            padding: 0;
        }
        
        .y-axis-label {
            width: 5%;
            text-align: right;
            padding-right: 8px;
            font-size: 7px;
            color: #6b7280;
            vertical-align: middle;
        }
        
        .chart-area {
            width: 95%;
            position: relative;
            border-left: 2px solid #9ca3af;
            border-bottom: 2px solid #9ca3af;
            padding: 5px 0 0 5px;
            background: 
                repeating-linear-gradient(
                    to top,
                    transparent,
                    transparent 19.8%,
                    #e5e7eb 19.8%,
                    #e5e7eb 20%
                );
        }
        
        .week-column {
            display: inline-block;
            vertical-align: bottom;
            text-align: center;
            margin: 0 1px;
        }
        
        .bar-container {
            position: relative;
            display: inline-block;
            width: 100%;
            margin: 0 2px;
        }
        
        .bar-planned {
            display: inline-block;
            width: 45%;
            background: #3b82f6;
            border-radius: 2px 2px 0 0;
            margin-right: 2%;
            vertical-align: bottom;
        }
        
        .bar-actual {
            display: inline-block;
            width: 45%;
            background: #10b981;
            border-radius: 2px 2px 0 0;
            vertical-align: bottom;
        }
        
        .bar-label {
            font-size: 6px;
            color: #6b7280;
            margin-top: 3px;
            transform: rotate(-45deg);
            transform-origin: center;
        }
        
        .legend {
            margin-top: 15px;
            text-align: center;
            padding: 8px;
            background: #f9fafb;
            border-radius: 4px;
        }
        
        .legend-item {
            display: inline-block;
            margin: 0 15px;
            font-size: 9px;
            font-weight: bold;
        }
        
        .legend-color {
            display: inline-block;
            width: 25px;
            height: 10px;
            margin-right: 6px;
            vertical-align: middle;
            border-radius: 2px;
        }
        
        /* ===== LINE CHART (ASCII style) ===== */
        .line-chart {
            width: 100%;
            border: none;
            border-collapse: collapse;
            margin: 15px 0;
            font-family: 'Courier New', monospace;
            font-size: 7px;
            background: #f9fafb;
        }
        
        .line-chart td {
            border: none;
            padding: 1px;
            text-align: center;
            height: 15px;
        }
        
        .chart-point-planned {
            color: #3b82f6;
            font-weight: bold;
            font-size: 10px;
        }
        
        .chart-point-actual {
            color: #10b981;
            font-weight: bold;
            font-size: 10px;
        }
        
        .chart-line {
            color: #9ca3af;
        }
        
        /* ===== TABLES ===== */
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        table.data-table th {
            background: #4f46e5;
            color: white;
            padding: 8px 6px;
            text-align: center;
            font-weight: bold;
            font-size: 9px;
            border: 1px solid #3730a3;
        }
        
        table.data-table td {
            padding: 6px;
            border: 1px solid #d1d5db;
            font-size: 9px;
            text-align: center;
        }
        
        .bg-planned { background: #dbeafe; }
        .bg-actual { background: #d1fae5; }
        .bg-variance { background: #fef3c7; }
        
        .positive { color: #10b981; font-weight: bold; }
        .negative { color: #ef4444; font-weight: bold; }
        
        .summary-box {
            margin: 15px 0;
            padding: 12px;
            background: #f0fdf4;
            border-left: 4px solid #10b981;
            border-radius: 4px;
        }
        
        .summary-box h3 {
            font-size: 11px;
            margin-bottom: 8px;
            color: #1f2937;
        }
        
        .summary-box p {
            font-size: 9px;
            line-height: 1.6;
            color: #4b5563;
            margin: 4px 0;
        }
        
        .footer {
            position: fixed;
            bottom: 10px;
            left: 0;
            right: 0;
            padding: 8px;
            text-align: center;
            font-size: 8px;
            color: #6b7280;
            border-top: 1px solid #d1d5db;
        }
    </style>
</head>
<body>
    {{-- HEADER --}}
    <div class="header">
        <h1>📈 S-Curve Analysis Report</h1>
        <div class="header-info">
            <strong>Project:</strong> {{ $project->name }} | 
            <strong>Period:</strong> {{ \Carbon\Carbon::parse($sCurveData['start_date'])->format('d M Y') }} to {{ \Carbon\Carbon::parse($sCurveData['end_date'])->format('d M Y') }} | 
            <strong>Exported:</strong> {{ $exportDate }}
        </div>
    </div>

    {{-- STATISTICS CARDS --}}
    @if(!empty($sCurveData['statistics']))
        <div class="statistics">
            <div class="stat-card">
                <div class="stat-value">{{ $sCurveData['statistics']['total_tasks'] }}</div>
                <div class="stat-label">Total Tasks</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: #10b981;">{{ $sCurveData['statistics']['completed'] }}</div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: #3b82f6;">{{ $sCurveData['statistics']['on_track'] }}</div>
                <div class="stat-label">On Track</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: #ef4444;">{{ $sCurveData['statistics']['delayed'] }}</div>
                <div class="stat-label">Delayed</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: #6b7280;">{{ $sCurveData['statistics']['not_started'] }}</div>
                <div class="stat-label">Not Started</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: #8b5cf6;">{{ $sCurveData['statistics']['overall_progress'] }}%</div>
                <div class="stat-label">Overall Progress</div>
            </div>
        </div>
    @endif

    {{-- S-CURVE BAR CHART VISUALIZATION --}}
    @php
        $weeklyData = $sCurveData['weekly_data'] ?? [];
        $maxWeeks = count($weeklyData);
        $chartHeight = 150; // pixels
    @endphp
    
    <div class="chart-container">
        <div class="chart-title">📊 S-Curve: Cumulative Progress Over Time</div>
        
        <table class="chart-bars">
            <tr>
                <td class="y-axis-label">100%</td>
                <td class="chart-area" style="height: {{ $chartHeight }}px;">
                    @foreach($weeklyData as $index => $week)
                        @php
                            $plannedHeight = ($week['planned_cumulative'] / 100) * $chartHeight;
                            $actualHeight = ($week['actual_cumulative'] / 100) * $chartHeight;
                            $columnWidth = (95 / $maxWeeks);
                        @endphp
                        
                        <div class="week-column" style="width: {{ $columnWidth }}%; height: {{ $chartHeight }}px;">
                            <div class="bar-container">
                                <div class="bar-planned" style="height: {{ $plannedHeight }}px;" title="Planned: {{ number_format($week['planned_cumulative'], 1) }}%"></div>
                                <div class="bar-actual" style="height: {{ $actualHeight }}px;" title="Actual: {{ number_format($week['actual_cumulative'], 1) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </td>
            </tr>
            <tr>
                <td class="y-axis-label">0%</td>
                <td style="border-top: 2px solid #9ca3af; padding-top: 5px;">
                    @foreach($weeklyData as $index => $week)
                        @php
                            $columnWidth = (95 / $maxWeeks);
                            $showLabel = ($index % 2 == 0) || ($index == $maxWeeks - 1); // Show every 2nd week
                        @endphp
                        <div class="week-column" style="width: {{ $columnWidth }}%; display: inline-block; text-align: center;">
                            @if($showLabel)
                                <div style="font-size: 6px; color: #6b7280;">{{ $week['week_label'] }}</div>
                            @endif
                        </div>
                    @endforeach
                </td>
            </tr>
        </table>
        
        <div class="legend">
            <div class="legend-item">
                <span class="legend-color" style="background: #3b82f6;"></span>
                <span>Planned Progress</span>
            </div>
            <div class="legend-item">
                <span class="legend-color" style="background: #10b981;"></span>
                <span>Actual Progress</span>
            </div>
        </div>
    </div>

    {{-- PROGRESS SUMMARY --}}
    @if(!empty($sCurveData['statistics']))
        @php
            $overallProgress = $sCurveData['statistics']['overall_progress'];
            $lastWeek = end($weeklyData);
            $finalVariance = $lastWeek['variance'] ?? 0;
            
            $status = 'On Track';
            $statusColor = '#10b981';
            $statusBg = '#f0fdf4';
            $statusBorder = '#10b981';
            
            if ($finalVariance < -10) {
                $status = 'Behind Schedule';
                $statusColor = '#ef4444';
                $statusBg = '#fef2f2';
                $statusBorder = '#ef4444';
            } elseif ($finalVariance > 10) {
                $status = 'Ahead of Schedule';
                $statusColor = '#3b82f6';
                $statusBg = '#eff6ff';
                $statusBorder = '#3b82f6';
            }
        @endphp
        
        <div class="summary-box" style="background: {{ $statusBg }}; border-left-color: {{ $statusBorder }};">
            <h3>📊 Project Progress Summary</h3>
            <p>
                <strong>Overall Progress:</strong> {{ $overallProgress }}% | 
                <strong>Project Status:</strong> <span style="color: {{ $statusColor }}; font-weight: bold;">{{ $status }}</span> |
                <strong>Schedule Variance:</strong> 
                <span style="color: {{ $finalVariance >= 0 ? '#10b981' : '#ef4444' }}; font-weight: bold;">
                    {{ $finalVariance > 0 ? '+' : '' }}{{ number_format($finalVariance, 1) }}%
                </span>
            </p>
            <p>
                <strong>Task Completion:</strong> {{ $sCurveData['statistics']['completed'] }} of {{ $sCurveData['statistics']['total_tasks'] }} tasks completed
                ({{ round(($sCurveData['statistics']['completed'] / max($sCurveData['statistics']['total_tasks'], 1)) * 100, 1) }}%) |
                <strong>Delayed:</strong> {{ $sCurveData['statistics']['delayed'] }} tasks |
                <strong>Not Started:</strong> {{ $sCurveData['statistics']['not_started'] }} tasks
            </p>
            @if($finalVariance < -10)
                <p style="color: #ef4444; font-weight: bold; margin-top: 6px;">
                    ⚠️ Action Required: Project is significantly behind schedule. Immediate corrective actions recommended.
                </p>
            @elseif($finalVariance > 10)
                <p style="color: #3b82f6; font-weight: bold; margin-top: 6px;">
                    ✓ Excellent Progress: Project is ahead of schedule. Continue current momentum.
                </p>
            @else
                <p style="color: #10b981; font-weight: bold; margin-top: 6px;">
                    ✓ Good Progress: Project is on track. Continue monitoring key milestones.
                </p>
            @endif
        </div>
    @endif

    {{-- PAGE BREAK --}}
    <div class="page-break"></div>

    {{-- DETAILED WEEKLY DATA TABLE --}}
    <h2 style="font-size: 14px; margin: 20px 0 10px 0; color: #1f2937; border-bottom: 2px solid #4f46e5; padding-bottom: 5px;">
        📅 Weekly Progress Breakdown
    </h2>
    
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 8%;">Week</th>
                <th style="width: 12%;">Date Range</th>
                <th style="width: 13%; background: #3b82f6;">Planned<br>Cumulative</th>
                <th style="width: 13%; background: #10b981;">Actual<br>Cumulative</th>
                <th style="width: 12%; background: #f59e0b;">Variance</th>
                <th style="width: 10%;">Planned<br>Weekly</th>
                <th style="width: 10%;">Actual<br>Weekly</th>
                <th style="width: 22%;">Status & Notes</th>
            </tr>
        </thead>
        <tbody>
            @if(!empty($weeklyData))
                @php
                    $prevPlanned = 0;
                    $prevActual = 0;
                @endphp
                @foreach($weeklyData as $index => $week)
                    @php
                        $variance = $week['variance'];
                        $plannedWeekly = $week['planned_cumulative'] - $prevPlanned;
                        $actualWeekly = $week['actual_cumulative'] - $prevActual;
                        
                        $statusText = '✓ On Track';
                        $statusColor = '#10b981';
                        
                        if ($variance < -5) {
                            $statusText = '⚠️ Behind';
                            $statusColor = '#ef4444';
                        } elseif ($variance > 5) {
                            $statusText = '✓ Ahead';
                            $statusColor = '#3b82f6';
                        }
                        
                        $prevPlanned = $week['planned_cumulative'];
                        $prevActual = $week['actual_cumulative'];
                    @endphp
                    <tr>
                        <td><strong>{{ $week['week_label'] }}</strong></td>
                        <td>{{ $week['date_label'] }}</td>
                        <td class="bg-planned"><strong>{{ number_format($week['planned_cumulative'], 1) }}%</strong></td>
                        <td class="bg-actual"><strong>{{ number_format($week['actual_cumulative'], 1) }}%</strong></td>
                        <td class="bg-variance {{ $variance >= 0 ? 'positive' : 'negative' }}">
                            <strong>{{ $variance > 0 ? '+' : '' }}{{ number_format($variance, 1) }}%</strong>
                        </td>
                        <td style="font-size: 8px;">+{{ number_format($plannedWeekly, 1) }}%</td>
                        <td style="font-size: 8px;">+{{ number_format($actualWeekly, 1) }}%</td>
                        <td style="font-size: 8px; color: {{ $statusColor }}; font-weight: bold;">
                            {{ $statusText }}
                        </td>
                    </tr>
                @endforeach
            @endif
        </tbody>
        <tfoot>
            <tr style="background: #f3f4f6; font-weight: bold;">
                <td colspan="2">FINAL</td>
                <td class="bg-planned">{{ number_format($lastWeek['planned_cumulative'] ?? 0, 1) }}%</td>
                <td class="bg-actual">{{ number_format($lastWeek['actual_cumulative'] ?? 0, 1) }}%</td>
                <td class="bg-variance {{ $finalVariance >= 0 ? 'positive' : 'negative' }}">
                    {{ $finalVariance > 0 ? '+' : '' }}{{ number_format($finalVariance, 1) }}%
                </td>
                <td colspan="3" style="text-align: center;">
                    <span style="color: {{ $statusColor }};">{{ $status }}</span>
                </td>
            </tr>
        </tfoot>
    </table>

    {{-- PHASE INFORMATION --}}
    @if(!empty($sCurveData['phases']))
        <h2 style="font-size: 14px; margin: 20px 0 10px 0; color: #1f2937; border-bottom: 2px solid #4f46e5; padding-bottom: 5px;">
            🎯 Phase Distribution
        </h2>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Phase Name</th>
                    <th style="width: 15%;">Weight (%)</th>
                    <th style="width: 15%;">Color Indicator</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sCurveData['phases'] as $phase)
                    <tr>
                        <td style="text-align: left; padding-left: 10px;"><strong>{{ $phase['name'] }}</strong></td>
                        <td><strong>{{ number_format($phase['weight'], 1) }}%</strong></td>
                        <td>
                            <div style="width: 40px; height: 15px; background: {{ $phase['color'] }}; margin: 0 auto; border-radius: 3px; border: 1px solid #d1d5db;"></div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- KEY INSIGHTS --}}
    <div style="margin-top: 20px; padding: 12px; background: #fffbeb; border: 2px solid #f59e0b; border-radius: 6px;">
        <h3 style="font-size: 11px; color: #92400e; margin-bottom: 8px;">💡 Key Insights & Recommendations</h3>
        <ul style="font-size: 9px; line-height: 1.6; color: #78350f; margin-left: 20px;">
            @if($finalVariance < -10)
                <li>Project is significantly behind schedule ({{ number_format(abs($finalVariance), 1) }}% delay)</li>
                <li>Recommend: Resource reallocation, overtime consideration, or scope adjustment</li>
                <li>Focus on critical path activities to minimize further delays</li>
            @elseif($finalVariance < -5)
                <li>Project is slightly behind schedule ({{ number_format(abs($finalVariance), 1) }}% delay)</li>
                <li>Monitor closely and identify bottlenecks preventing progress</li>
                <li>Consider minor adjustments to recover schedule</li>
            @elseif($finalVariance > 10)
                <li>Project is ahead of schedule ({{ number_format($finalVariance, 1) }}% ahead)</li>
                <li>Excellent execution - document best practices for future projects</li>
                <li>Consider opportunities for early completion or scope enhancement</li>
            @else
                <li>Project is on track with acceptable variance</li>
                <li>Continue current execution strategy</li>
                <li>Maintain regular monitoring of key milestones</li>
            @endif
            <li>Completion rate: {{ round(($sCurveData['statistics']['completed'] / max($sCurveData['statistics']['total_tasks'], 1)) * 100, 1) }}% of tasks completed</li>
            @if($sCurveData['statistics']['delayed'] > 0)
                <li style="color: #ef4444;">⚠️ {{ $sCurveData['statistics']['delayed'] }} delayed tasks require immediate attention</li>
            @endif
        </ul>
    </div>

    {{-- FOOTER --}}
    <div class="footer">
        Page <span class="pagenum"></span> | Generated by Project Planning System | {{ now()->format('d M Y H:i:s') }}
    </div>
</body>
</html>
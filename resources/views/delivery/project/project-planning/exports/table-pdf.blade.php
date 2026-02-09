<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Table View - {{ $project->name }}</title>
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
        }
        
        .header {
            margin-bottom: 15px;
            padding: 10px;
            background: #f3f4f6;
            border-bottom: 3px solid #4f46e5;
        }
        
        .header h1 {
            font-size: 16px;
            color: #1f2937;
            margin-bottom: 5px;
        }
        
        .header-info {
            font-size: 8px;
            color: #6b7280;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        th {
            background: #4f46e5;
            color: white;
            padding: 8px 4px;
            text-align: left;
            font-weight: bold;
            font-size: 8px;
            border: 1px solid #3730a3;
        }
        
        td {
            padding: 6px 4px;
            border: 1px solid #d1d5db;
            font-size: 8px;
        }
        
        .phase-row {
            background: #e0e7ff !important;
            font-weight: bold;
            font-size: 9px;
        }
        
        .group-row {
            background: #f3f4f6;
            font-weight: bold;
        }
        
        .stage-row {
            background: #fef3c7;
        }
        
        .activity-row {
            background: white;
        }
        
        .indent-1 { padding-left: 10px !important; }
        .indent-2 { padding-left: 20px !important; }
        .indent-3 { padding-left: 30px !important; }
        .indent-4 { padding-left: 40px !important; }
        
        .status-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 7px;
            font-weight: bold;
        }
        
        .status-not-started { background: #f3f4f6; color: #4b5563; }
        .status-in-progress { background: #dbeafe; color: #1e40af; }
        .status-completed { background: #d1fae5; color: #065f46; }
        .status-delayed { background: #fee2e2; color: #991b1b; }
        
        .progress-bar {
            width: 40px;
            height: 8px;
            background: #e5e7eb;
            border-radius: 2px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: #3b82f6;
        }
        
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 8px;
            text-align: center;
            font-size: 7px;
            color: #6b7280;
            border-top: 1px solid #d1d5db;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📊 Project Planning - Table View</h1>
        <div class="header-info">
            <strong>Project:</strong> {{ $project->name }} | 
            <strong>Client:</strong> {{ $project->client->company_name ?? 'N/A' }} | 
            <strong>Exported:</strong> {{ $exportDate }}
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 30%;">Phase / Group / Stage / Activity</th>
                <th style="width: 8%;">Weight %</th>
                <th style="width: 12%;">Module</th>
                <th style="width: 12%;">Object</th>
                <th style="width: 10%;">Start</th>
                <th style="width: 10%;">End</th>
                <th style="width: 8%;">Days</th>
                <th style="width: 10%;">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($phasesData as $phaseData)
                {{-- Phase Row --}}
                <tr class="phase-row">
                    <td style="font-weight: bold;">
                        📋 {{ strtoupper($phaseData['phase']['name']) }}
                    </td>
                    <td style="text-align: center;">{{ number_format($phaseData['phase']['weight'], 1) }}%</td>
                    <td colspan="2">Phase Level</td>
                    <td>{{ $phaseData['phase']['start_date'] }}</td>
                    <td>{{ $phaseData['phase']['end_date'] }}</td>
                    <td style="text-align: center;">{{ $phaseData['phase']['duration_in_days'] ?? '-' }}</td>
                    <td>
                        <span class="status-badge status-{{ $phaseData['phase']['status'] }}">
                            {{ $phaseData['phase']['status_text'] }}
                        </span>
                    </td>
                </tr>

                {{-- Groups --}}
                @foreach($phaseData['groups'] as $group)
                    @include('project-planning.exports.partials.group-row', ['group' => $group, 'level' => 1])
                @endforeach
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Page <span class="pagenum"></span> | Generated by Project Planning System
    </div>
</body>
</html>
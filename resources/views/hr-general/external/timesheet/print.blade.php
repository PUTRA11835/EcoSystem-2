<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>External timesheet {{ $sheet->sheet_no }}</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #111827; margin: 32px; font-size: 12px; }
        h1 { font-size: 20px; margin: 18px 0 2px; letter-spacing: .5px; }
        .status { font-size: 11px; font-weight: bold; color: #166534; text-transform: uppercase; margin-bottom: 14px; }
        table { border-collapse: collapse; width: 100%; }
        .meta td { padding: 3px 6px; vertical-align: top; }
        .meta td:nth-child(odd) { color: #4b5563; width: 16%; }
        .grid th, .grid td { border: 1px solid #9ca3af; padding: 5px 8px; text-align: left; }
        .grid th { background: #f3f4f6; }
        .grid td.r, .grid th.r { text-align: right; }
        .grid tfoot td { background: #f3f4f6; font-weight: bold; }
        .sign { margin-top: 36px; width: 240px; margin-left: auto; text-align: center; }
        .sign .line { height: 64px; }
        .small { font-size: 10px; color: #6b7280; }
        button { padding: 5px 12px; border: 1px solid #9ca3af; background: #f9fafb; border-radius: 4px; cursor: pointer; }
        @media print { button { display: none; } body { margin: 12mm; } }
    </style>
</head>
<body>
    <button onclick="window.print()">Print / Save as PDF</button>
    <p class="small" style="margin:14px 0 0">{{ $company }}</p>
    <h1>EXTERNAL CONSULTANT TIMESHEET</h1>
    <div class="status">{{ $sheet->status === 'approved' ? 'Approved' : ($sheet->status === 'submitted' ? 'Waiting for approval' : ucfirst($sheet->status)) }}</div>

    @php $p = $sheet->pricing; @endphp
    <table class="meta">
        <tr><td>Name</td><td>: {{ $sheet->name }}</td><td>Period</td><td>: {{ $sheet->period_label }}</td></tr>
        <tr><td>Consultant ID</td><td>: {{ $sheet->eci }}</td><td>Contract</td><td>: {{ $sheet->contract_number }}</td></tr>
        <tr><td>Scheme</td><td>: {{ \App\Support\External\ExternalRules::schemeLabel($p['scheme']) }}</td><td>Timesheet no.</td><td>: {{ $sheet->sheet_no }}</td></tr>
        @if($amounts)<tr><td>Rate</td><td>: {{ $p['rate'] !== null ? \App\Support\External\ExternalRules::money($p['rate'], $p['currency']) : '—' }}</td><td>Gross amount</td><td>: {{ $p['gross'] !== null ? \App\Support\External\ExternalRules::money($p['gross'], $p['currency']) : '—' }}</td></tr>@endif
    </table>
    <br>
    <table class="grid">
        <thead><tr><th>No</th><th>Date</th><th>Project</th><th>Time</th><th>Activity</th><th class="r">Hours</th></tr></thead>
        <tbody>
            @foreach($sheet->entries as $e)
                <tr><td>{{ $loop->iteration }}</td><td>{{ \Illuminate\Support\Carbon::parse($e->work_date)->format('d M Y') }}</td><td>{{ $e->project_label ?: '-' }}</td>
                    <td>{{ $e->start_time ? substr($e->start_time, 0, 5) . '–' . substr($e->end_time, 0, 5) : '-' }}{{ $e->location ? ' · ' . ucfirst(strtolower($e->location)) : '' }}</td>
                    <td>{{ $e->activity }}</td><td class="r">{{ number_format($e->hours, 2, ',', '.') }}</td></tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr><td colspan="5" class="r">Total hours</td><td class="r">{{ number_format($sheet->live_hours, 2, ',', '.') }}</td></tr>
            <tr><td colspan="5" class="r">Billable quantity</td><td class="r">{{ number_format($p['qty'], 2, ',', '.') }}</td></tr>
        </tfoot>
    </table>

    <div class="sign">
        <div>{{ $sheet->status === 'approved' ? 'Approved by Head of RPMO' : 'Not approved yet' }}</div>
        <div class="line"></div>
        <div>{{ $approver ?: '-' }}</div>
        @if($sheet->approved_at)<div class="small">{{ \Illuminate\Support\Carbon::parse($sheet->approved_at)->format('d M Y H:i') }}</div>@endif
        <div class="small" style="margin-top:8px">Verification: {{ $verification }}</div>
    </div>
</body>
</html>

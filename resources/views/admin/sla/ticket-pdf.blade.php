<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SLA Report — Ticket #{{ $ticket->ticket_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color: #1f2937; line-height: 1.5; }

        .header { background: #8b1a1a; color: #fff; padding: 18px 24px; }
        .header h1 { font-size: 18px; font-weight: bold; }
        .header p  { font-size: 11px; color: rgba(255,255,255,0.7); margin-top: 2px; }

        .section { padding: 16px 24px; border-bottom: 1px solid #e5e7eb; }
        .section-title { font-size: 10px; font-weight: bold; text-transform: uppercase;
                          letter-spacing: 0.05em; color: #9ca3af; margin-bottom: 8px; }

        .meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 24px; }
        .meta-row  { display: flex; gap: 6px; }
        .meta-label { color: #6b7280; min-width: 100px; font-size: 10px; }
        .meta-value { font-weight: 600; font-size: 11px; }

        .sla-boxes { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .sla-box   { border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px 16px; }
        .sla-box .label { font-size: 10px; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em; }
        .sla-box .value { font-size: 18px; font-weight: bold; margin-top: 2px; }
        .sla-box .sub   { font-size: 10px; color: #6b7280; margin-top: 4px; }
        .status-met      { color: #16a34a; }
        .status-breached { color: #dc2626; }
        .status-pending  { color: #2563eb; }
        .status-paused   { color: #d97706; }

        .metrics-strip { display: flex; gap: 16px; flex-wrap: wrap; }
        .metric { flex: 1; min-width: 100px; }
        .metric .label { font-size: 10px; color: #9ca3af; }
        .metric .value { font-size: 14px; font-weight: bold; color: #1f2937; }

        table { width: 100%; border-collapse: collapse; font-size: 10px; }
        th    { background: #f9fafb; border-bottom: 1px solid #e5e7eb; padding: 6px 8px; text-align: left; font-weight: 600; color: #6b7280; }
        td    { padding: 6px 8px; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
        tr:last-child td { border-bottom: none; }

        .badge { display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 9px; font-weight: 600; }
        .badge-met      { background: #dcfce7; color: #16a34a; }
        .badge-breached { background: #fee2e2; color: #dc2626; }
        .badge-pending  { background: #dbeafe; color: #2563eb; }
        .badge-paused   { background: #fef3c7; color: #d97706; }

        .signature { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 24px; margin-top: 8px; }
        .sig-box   { border-top: 1px solid #e5e7eb; padding-top: 8px; text-align: center; }
        .sig-title { font-size: 10px; color: #6b7280; }
        .sig-space { height: 48px; }
        .sig-name  { border-top: 1px solid #9ca3af; padding-top: 4px; font-size: 10px; }

        .footer { text-align: center; font-size: 9px; color: #9ca3af; padding: 12px 24px;
                  border-top: 1px solid #e5e7eb; margin-top: 24px; }
    </style>
</head>
<body>

{{-- Header --}}
<div class="header">
    <h1>PT Eclectic Consulting</h1>
    <p>Service Level Agreement Report &bull; Official Document</p>
</div>

{{-- Ticket Metadata --}}
<div class="section">
    <div class="section-title">Ticket Information</div>
    <div class="meta-grid">
        <div class="meta-row">
            <span class="meta-label">Ticket Number</span>
            <span class="meta-value">#{{ $ticket->ticket_number }}</span>
        </div>
        <div class="meta-row">
            <span class="meta-label">Customer</span>
            <span class="meta-value">{{ optional(optional($ticket->customer)->basicData)->name_1 ?? '—' }}</span>
        </div>
        <div class="meta-row">
            <span class="meta-label">Ticket Type</span>
            <span class="meta-value">{{ $ticket->ticket_type ?? '—' }}</span>
        </div>
        <div class="meta-row">
            <span class="meta-label">Priority</span>
            <span class="meta-value">{{ $ticket->ticket_priority ?? '—' }}</span>
        </div>
        <div class="meta-row">
            <span class="meta-label">Scale</span>
            <span class="meta-value">{{ $ticket->scale ?? '—' }}</span>
        </div>
        <div class="meta-row">
            <span class="meta-label">SLA Mode</span>
            <span class="meta-value">{{ $sla?->sla_mode ?? '—' }}</span>
        </div>
        <div class="meta-row">
            <span class="meta-label">SLA Started</span>
            <span class="meta-value">{{ $sla?->sla_start_at?->format('d M Y H:i') ?? '—' }}</span>
        </div>
        <div class="meta-row">
            <span class="meta-label">Description</span>
            <span class="meta-value">{{ mb_substr($ticket->description ?? '', 0, 80) }}</span>
        </div>
    </div>
</div>

{{-- SLA Status --}}
@if($sla)
<div class="section">
    <div class="section-title">SLA Status</div>
    <div class="sla-boxes">
        {{-- Response --}}
        <div class="sla-box">
            <div class="label">Response SLA</div>
            @php $rClass = match($sla->response_status) { 'met' => 'status-met', 'breached' => 'status-breached', default => 'status-pending' }; @endphp
            <div class="value {{ $rClass }}">
                {{ $sla->validation_duration_hours !== null ? number_format($sla->validation_duration_hours, 2) : '—' }} hrs
            </div>
            <div class="sub">
                Target: {{ $policy ? $policy->response_hours : '—' }} hrs &bull;
                Deadline: {{ $sla->response_due_at?->format('d M Y H:i') ?? '—' }}<br>
                Status: <strong>{{ strtoupper($sla->response_status) }}</strong>
            </div>
        </div>

        {{-- Resolution --}}
        <div class="sla-box">
            <div class="label">Resolution SLA</div>
            @if($sla->sla_mode === 'full')
                @php $resClass = match($sla->resolution_status) { 'met' => 'status-met', 'breached' => 'status-breached', 'paused' => 'status-paused', default => 'status-pending' }; @endphp
                <div class="value {{ $resClass }}">
                    {{ $sla->net_resolution_hours !== null ? number_format($sla->net_resolution_hours, 2) : '—' }} hrs (net)
                </div>
                <div class="sub">
                    Target: {{ $policy ? $policy->resolution_hours : '—' }} hrs &bull;
                    Deadline: {{ $sla->resolution_due_at?->format('d M Y H:i') ?? '—' }}<br>
                    Status: <strong>{{ strtoupper($sla->resolution_status) }}</strong>
                </div>
            @else
                <div class="value status-pending">Response Only</div>
                <div class="sub">This ticket type only tracks response SLA.</div>
            @endif
        </div>
    </div>
</div>

{{-- Metrics Strip --}}
<div class="section">
    <div class="section-title">Metrics</div>
    <div class="metrics-strip">
        <div class="metric">
            <div class="label">Total Waiting</div>
            <div class="value">{{ number_format($sla->total_waiting_hours, 2) }} hrs</div>
        </div>
        <div class="metric">
            <div class="label">Hour Calculation</div>
            <div class="value">{{ $policy?->is_24_hours ? '24h / 7d' : 'Business Hours' }}</div>
        </div>
        <div class="metric">
            <div class="label">Total Events</div>
            <div class="value">{{ $events->count() }}</div>
        </div>
        <div class="metric">
            <div class="label">Total Pauses</div>
            <div class="value">{{ $pauses->count() }}</div>
        </div>
        @if($sla->resolved_at)
        <div class="metric">
            <div class="label">Resolved At</div>
            <div class="value">{{ $sla->resolved_at->format('d M Y H:i') }}</div>
        </div>
        @endif
    </div>
</div>

{{-- Pause History --}}
@if($pauses->isNotEmpty())
<div class="section">
    <div class="section-title">Pause History</div>
    <table>
        <thead>
            <tr>
                <th>Started</th>
                <th>Ended</th>
                <th>Duration</th>
                <th>Reason</th>
                <th>Ticket Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($pauses as $pause)
            <tr>
                <td>{{ $pause->started_at?->format('d M Y H:i') }}</td>
                <td>{{ $pause->ended_at?->format('d M Y H:i') ?? 'Still active' }}</td>
                <td>{{ $pause->duration_hours !== null ? number_format($pause->duration_hours, 2) . ' hrs' : '—' }}</td>
                <td>{{ str_replace('_', ' ', $pause->pause_reason) }}</td>
                <td>{{ $pause->triggered_by_status ?? '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- Event Timeline --}}
@if($events->isNotEmpty())
<div class="section">
    <div class="section-title">SLA Event Timeline</div>
    <table>
        <thead>
            <tr>
                <th>Time</th>
                <th>Event</th>
                <th>Ticket Status</th>
                <th style="text-align:right">Waiting</th>
                <th style="text-align:right">Resolution</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
            @foreach($events as $event)
            <tr>
                <td style="white-space:nowrap">{{ $event->event_at?->format('d M Y H:i') }}</td>
                <td>{{ $event->event_label }}</td>
                <td>{{ $event->jarvis_status ?? '—' }}</td>
                <td style="text-align:right">{{ $event->waiting_hours !== null ? number_format($event->waiting_hours, 2) . ' h' : '—' }}</td>
                <td style="text-align:right">{{ $event->resolution_hours !== null ? number_format($event->resolution_hours, 2) . ' h' : '—' }}</td>
                <td>{{ $event->notes }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif
@endif

{{-- Signature --}}
<div class="section">
    <div class="section-title">Signatures</div>
    <div class="signature">
        <div class="sig-box">
            <div class="sig-title">Prepared by</div>
            <div class="sig-space"></div>
            <div class="sig-name">( ______________________ )</div>
        </div>
        <div class="sig-box">
            <div class="sig-title">Acknowledged by</div>
            <div class="sig-space"></div>
            <div class="sig-name">( ______________________ )</div>
        </div>
        <div class="sig-box">
            <div class="sig-title">Approved by</div>
            <div class="sig-space"></div>
            <div class="sig-name">( ______________________ )</div>
        </div>
    </div>
</div>

<div class="footer">
    This document is confidential and intended solely for authorised parties.<br>
    PT Eclectic Consulting &bull; Generated on {{ now()->format('d M Y H:i') }}
</div>

</body>
</html>

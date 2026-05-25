<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<title>SLA Report - {{ $ticket->ticket_number }}</title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
        font-family: 'DejaVu Sans', sans-serif;
        font-size: 9.5px;
        color: #1a1a2e;
        background: #fff;
        line-height: 1.5;
    }

    /* ── LETTERHEAD ── */
    .letterhead {
        padding: 28px 40px 0;
        border-bottom: 3px solid #1a1a2e;
        padding-bottom: 14px;
        display: table;
        width: 100%;
    }
    .lh-left  { display: table-cell; vertical-align: middle; width: 60%; }
    .lh-right { display: table-cell; vertical-align: middle; text-align: right; width: 40%; }
    .company-name {
        font-size: 20px;
        font-weight: 700;
        color: #1a1a2e;
        letter-spacing: 1px;
        text-transform: uppercase;
    }
    .company-sub {
        font-size: 8px;
        color: #6b7280;
        letter-spacing: 2px;
        text-transform: uppercase;
        margin-top: 1px;
    }
    .doc-meta { font-size: 8px; color: #6b7280; line-height: 1.7; }
    .doc-meta strong { color: #1a1a2e; }

    /* ── DOCUMENT TITLE BAND ── */
    .title-band {
        background: #1a1a2e;
        color: #fff;
        padding: 10px 40px;
        display: table;
        width: 100%;
    }
    .title-band-left  { display: table-cell; vertical-align: middle; }
    .title-band-right { display: table-cell; vertical-align: middle; text-align: right; }
    .doc-title { font-size: 13px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase; }
    .doc-subtitle { font-size: 8px; color: #9ca3af; margin-top: 1px; letter-spacing: 0.5px; }
    .ticket-badge {
        background: #f97316;
        color: #fff;
        padding: 4px 14px;
        border-radius: 2px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.5px;
    }

    /* ── BODY ── */
    .body { padding: 20px 40px 10px; }

    /* ── SECTION ── */
    .section { margin-bottom: 16px; }
    .section-header {
        background: #f3f4f6;
        border-left: 3px solid #f97316;
        padding: 5px 10px;
        font-size: 9px;
        font-weight: 700;
        color: #1a1a2e;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        margin-bottom: 8px;
    }

    /* ── INFO TABLE ── */
    .info-table { width: 100%; border-collapse: collapse; }
    .info-table td { padding: 4px 8px; vertical-align: top; font-size: 9px; border-bottom: 1px solid #f3f4f6; }
    .info-table .lbl { color: #6b7280; width: 160px; }
    .info-table .val { color: #1a1a2e; font-weight: 600; }
    .info-table .sep { width: 12px; color: #d1d5db; }

    /* ── TWO-COLUMN LAYOUT ── */
    .two-col { display: table; width: 100%; border-collapse: separate; border-spacing: 10px; margin: -10px; }
    .col      { display: table-cell; vertical-align: top; }

    /* ── SLA STATUS BOX ── */
    .sla-box {
        border: 1px solid #e5e7eb;
        border-top: 3px solid #1a1a2e;
        padding: 12px 14px;
    }
    .sla-box.met      { border-top-color: #16a34a; }
    .sla-box.breached { border-top-color: #dc2626; }
    .sla-box.pending  { border-top-color: #2563eb; }
    .sla-box.paused   { border-top-color: #d97706; }

    .sla-box-title { font-size: 8px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.8px; }
    .sla-box-value { font-size: 22px; font-weight: 800; margin: 5px 0 2px; }
    .sla-box-value.met      { color: #16a34a; }
    .sla-box-value.breached { color: #dc2626; }
    .sla-box-value.pending  { color: #2563eb; }
    .sla-box-value.paused   { color: #d97706; }
    .sla-box-target { font-size: 8px; color: #9ca3af; }
    .sla-box-status { margin-top: 8px; font-size: 8.5px; font-weight: 700; padding: 3px 8px; display: inline-block; }
    .sla-box-status.met      { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
    .sla-box-status.breached { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
    .sla-box-status.pending  { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; }
    .sla-box-status.paused   { background: #fffbeb; color: #d97706; border: 1px solid #fde68a; }

    /* ── METRICS STRIP ── */
    .metrics-strip { border: 1px solid #e5e7eb; background: #f9fafb; display: table; width: 100%; }
    .metric-item { display: table-cell; text-align: center; padding: 10px 8px; border-right: 1px solid #e5e7eb; }
    .metric-item:last-child { border-right: none; }
    .metric-val { font-size: 16px; font-weight: 800; color: #1a1a2e; }
    .metric-lbl { font-size: 7.5px; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 2px; }
    .metric-unit { font-size: 7.5px; color: #9ca3af; }

    /* ── FORMAL TABLE ── */
    .formal-table { width: 100%; border-collapse: collapse; }
    .formal-table thead tr { background: #1a1a2e; color: #fff; }
    .formal-table thead th {
        padding: 7px 10px;
        text-align: left;
        font-size: 8px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        white-space: nowrap;
    }
    .formal-table thead th.r { text-align: right; }
    .formal-table thead th.c { text-align: center; }
    .formal-table tbody tr:nth-child(even) { background: #f9fafb; }
    .formal-table tbody tr { border-bottom: 1px solid #e5e7eb; }
    .formal-table tbody td { padding: 5.5px 10px; font-size: 8.5px; vertical-align: middle; }
    .formal-table tbody td.r { text-align: right; font-family: monospace; font-size: 8px; }
    .formal-table tbody td.c { text-align: center; }
    .formal-table tfoot tr { background: #f3f4f6; border-top: 2px solid #1a1a2e; }
    .formal-table tfoot td { padding: 5px 10px; font-size: 8.5px; font-weight: 700; }

    /* ── EVENT DOT ── */
    .event-dot { display: inline-block; width: 6px; height: 6px; border-radius: 50%; margin-right: 5px; vertical-align: middle; }
    .dot-email_received       { background: #3b82f6; }
    .dot-ticket_validated     { background: #22c55e; }
    .dot-resolution_sent      { background: #a855f7; }
    .dot-customer_replied     { background: #f97316; }
    .dot-escalated_to_sap     { background: #6366f1; }
    .dot-escalated_to_support { background: #ec4899; }
    .dot-sla_warning          { background: #eab308; }
    .dot-sla_breached         { background: #ef4444; }
    .dot-ticket_closed        { background: #6b7280; }

    .num-ok    { color: #16a34a; font-weight: 700; }
    .num-warn  { color: #ea580c; font-weight: 700; }
    .num-info  { color: #2563eb; font-weight: 700; }
    .num-empty { color: #d1d5db; }

    /* ── BADGE ── */
    .badge {
        display: inline-block;
        padding: 2px 7px;
        font-size: 8px;
        font-weight: 700;
        border: 1px solid;
        letter-spacing: 0.3px;
    }
    .b-incident  { background:#fef2f2; color:#dc2626; border-color:#fecaca; }
    .b-sr        { background:#f5f3ff; color:#7c3aed; border-color:#ddd6fe; }
    .b-cr        { background:#eef2ff; color:#4338ca; border-color:#c7d2fe; }
    .b-ats       { background:#ecfeff; color:#0e7490; border-color:#a5f3fc; }
    .b-ams       { background:#f0fdfa; color:#0f766e; border-color:#99f6e4; }
    .b-mo        { background:#f0fdf4; color:#15803d; border-color:#bbf7d0; }
    .b-default   { background:#f9fafb; color:#6b7280; border-color:#e5e7eb; }
    .b-vh        { background:#fef2f2; color:#b91c1c; border-color:#fecaca; }
    .b-h         { background:#fff7ed; color:#c2410c; border-color:#fed7aa; }
    .b-m         { background:#fefce8; color:#a16207; border-color:#fde68a; }
    .b-l         { background:#eff6ff; color:#1d4ed8; border-color:#bfdbfe; }

    /* ── SIGNATURE ── */
    .signature-area { display: table; width: 100%; margin-top: 20px; border-top: 1px solid #e5e7eb; padding-top: 14px; }
    .sig-cell { display: table-cell; text-align: center; vertical-align: bottom; }
    .sig-line { border-top: 1px solid #1a1a2e; margin: 30px 20px 4px; }
    .sig-name { font-size: 8px; font-weight: 700; }
    .sig-role { font-size: 7.5px; color: #9ca3af; }

    /* ── FOOTER ── */
    .page-footer {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        border-top: 2px solid #1a1a2e;
        padding: 5px 40px;
        display: table;
        width: 100%;
        background: #fff;
        font-size: 7.5px;
        color: #9ca3af;
    }
    .footer-left   { display: table-cell; }
    .footer-center { display: table-cell; text-align: center; }
    .footer-right  { display: table-cell; text-align: right; }

    .no-data { text-align: center; padding: 12px; color: #9ca3af; font-style: italic; font-size: 8.5px; }
    .page-break { page-break-before: always; }

    /* Bottom margin so content doesn't overlap footer */
    .body-wrap { margin-bottom: 30px; }
</style>
</head>
<body>

{{-- ── FOOTER (fixed) ── --}}
<div class="page-footer">
    <div class="footer-left">CONFIDENTIAL — For internal use only</div>
    <div class="footer-center">EC EcoSystem · SLA Report · {{ $ticket->ticket_number }}</div>
    <div class="footer-right">Printed: {{ now()->format('d/m/Y H:i') }}</div>
</div>

<div class="body-wrap">

{{-- ── LETTERHEAD ── --}}
<div class="letterhead">
    <div class="lh-left">
        <div class="company-name">EC EcoSystem</div>
        <div class="company-sub">Enterprise Consulting &amp; Technology Solutions</div>
    </div>
    <div class="lh-right">
        <div class="doc-meta">
            <div><strong>Document No.</strong> &nbsp; SLA/{{ now()->format('Y') }}/{{ $ticket->ticket_number }}</div>
            <div><strong>Print Date</strong> &nbsp; {{ now()->format('d F Y') }}</div>
            <div><strong>Issued For</strong> &nbsp; {{ $ticket->customer?->basicData?->name_1 ?? $ticket->customer?->customer_code ?? '-' }}</div>
        </div>
    </div>
</div>

{{-- ── TITLE BAND ── --}}
<div class="title-band">
    <div class="title-band-left">
        <div class="doc-title">Service Level Agreement (SLA) Report</div>
        <div class="doc-subtitle">Ticket handling performance summary based on service agreement</div>
    </div>
    <div class="title-band-right">
        <span class="ticket-badge">{{ $ticket->ticket_number }}</span>
    </div>
</div>

<div class="body">

    {{-- ── TICKET INFORMATION ── --}}
    <div class="section">
        <div class="section-header">I. Ticket Information</div>
        <table class="info-table">
            <tr>
                <td class="lbl">Ticket Number</td>
                <td class="sep">:</td>
                <td class="val">{{ $ticket->ticket_number }}</td>
                <td class="lbl">Ticket Type</td>
                <td class="sep">:</td>
                <td class="val">
                    @php $tCls = match($ticket->ticket_type) { 'Incident' => 'b-incident', 'Service Request' => 'b-sr', 'Change Request' => 'b-cr', default => 'b-default' }; @endphp
                    <span class="badge {{ $tCls }}">{{ $ticket->ticket_type ?? '-' }}</span>
                </td>
            </tr>
            <tr>
                <td class="lbl">Customer</td>
                <td class="sep">:</td>
                <td class="val">{{ $ticket->customer?->basicData?->name_1 ?? $ticket->customer?->customer_code ?? '-' }}</td>
                <td class="lbl">Delivery Type</td>
                <td class="sep">:</td>
                <td class="val">
                    @forelse($deliveryTypes as $dt)
                        @php $dCls = match($dt) { 'ATS' => 'b-ats', 'AMS' => 'b-ams', 'MO' => 'b-mo', default => 'b-default' }; @endphp
                        <span class="badge {{ $dCls }}">{{ $dt }}</span>
                    @empty
                        <span style="color:#9ca3af;">—</span>
                    @endforelse
                </td>
            </tr>
            <tr>
                <td class="lbl">Subject</td>
                <td class="sep">:</td>
                <td class="val" colspan="4">{{ $ticket->subject ?? '-' }}</td>
            </tr>
            <tr>
                <td class="lbl">Priority</td>
                <td class="sep">:</td>
                <td class="val">
                    @php $pCls = match($ticket->ticket_priority) { 'Very High' => 'b-vh', 'High' => 'b-h', 'Medium' => 'b-m', 'Low' => 'b-l', default => 'b-default' }; @endphp
                    <span class="badge {{ $pCls }}">{{ $ticket->ticket_priority ?? '-' }}</span>
                </td>
                <td class="lbl">Scale</td>
                <td class="sep">:</td>
                <td class="val">{{ $ticket->scale ?? '-' }}</td>
            </tr>
            <tr>
                <td class="lbl">SLA Start Time</td>
                <td class="sep">:</td>
                <td class="val">{{ $sla->sla_start_at?->format('d F Y, H:i') ?? '-' }}</td>
                <td class="lbl">Resolved At</td>
                <td class="sep">:</td>
                <td class="val">{{ $sla->resolved_at?->format('d F Y, H:i') ?? 'Not yet resolved' }}</td>
            </tr>
            <tr>
                <td class="lbl">SLA Policy</td>
                <td class="sep">:</td>
                <td class="val" colspan="4">
                    @if($sla->policy)
                        {{ $sla->policy->customer_id ? 'Custom (Per-Customer)' : 'Global Default' }}
                        &mdash; Response: {{ $sla->policy->response_hours }} hrs &nbsp;|&nbsp;
                        Resolution: {{ $sla->policy->resolution_hours }} hrs &nbsp;|&nbsp;
                        Mode: {{ $sla->policy->is_24_hours ? '24 hrs / 7 days' : 'Business Hours (08:00–17:00)' }}
                    @else
                        <span style="color:#9ca3af;">No policy assigned</span>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    {{-- ── SLA STATUS ── --}}
    <div class="section">
        <div class="section-header">II. SLA Status</div>

        @php
            $respSt = $sla->response_status ?? 'pending';
            $resoSt = $sla->resolution_status ?? 'pending';
            $respBox = in_array($respSt, ['met','breached','pending','paused']) ? $respSt : 'pending';
            $resoBox = in_array($resoSt, ['met','breached','pending','paused']) ? $resoSt : 'pending';
            $respLabel = match($respSt) { 'met' => '✓ Target Met', 'breached' => '✗ Target Breached', default => '⏳ In Progress' };
            $resoLabel = match($resoSt) { 'met' => '✓ Target Met', 'breached' => '✗ Target Breached', 'paused' => '⏸ Paused', default => '⏳ In Progress' };
        @endphp

        <div class="two-col">
            <div class="col">
                <div class="sla-box {{ $respBox }}">
                    <div class="sla-box-title">Response Time</div>
                    <div class="sla-box-value {{ $respBox }}">
                        {{ $sla->validation_duration_hours ? number_format((float)$sla->validation_duration_hours, 2) . ' hrs' : '—' }}
                    </div>
                    <div class="sla-box-target">Target: {{ $sla->policy?->response_hours ?? '—' }} hrs</div>
                    <div><span class="sla-box-status {{ $respBox }}">{{ $respLabel }}</span></div>
                    <div style="margin-top:6px; font-size:8px; color:#6b7280;">
                        Time from email received to ticket validated.
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="sla-box {{ $resoBox }}">
                    <div class="sla-box-title">Resolution Time (Net)</div>
                    <div class="sla-box-value {{ $resoBox }}">
                        {{ $sla->net_resolution_hours ? number_format((float)$sla->net_resolution_hours, 2) . ' hrs' : '—' }}
                    </div>
                    <div class="sla-box-target">Target: {{ $sla->policy?->resolution_hours ?? '—' }} hrs</div>
                    <div><span class="sla-box-status {{ $resoBox }}">{{ $resoLabel }}</span></div>
                    <div style="margin-top:6px; font-size:8px; color:#6b7280;">
                        Effective resolution time (total waiting time excluded).
                    </div>
                </div>
            </div>
        </div>

        {{-- Metrics strip --}}
        <div class="metrics-strip" style="margin-top:8px;">
            <div class="metric-item">
                <div class="metric-val">{{ number_format((float)$sla->total_waiting_hours, 2) }}</div>
                <div class="metric-unit">hrs</div>
                <div class="metric-lbl">Total Waiting</div>
            </div>
            <div class="metric-item">
                <div class="metric-val">{{ $events->count() }}</div>
                <div class="metric-lbl">Total Events</div>
            </div>
            <div class="metric-item">
                <div class="metric-val">{{ $pauses->count() }}</div>
                <div class="metric-lbl">Total Pauses</div>
            </div>
            <div class="metric-item">
                <div class="metric-val">{{ $sla->policy?->is_24_hours ? '24/7' : 'Biz Hrs' }}</div>
                <div class="metric-lbl">Calculation Mode</div>
            </div>
            <div class="metric-item">
                <div class="metric-val">{{ $sla->response_due_at ? \Carbon\Carbon::parse($sla->response_due_at)->format('d/m H:i') : '—' }}</div>
                <div class="metric-lbl">Due Response</div>
            </div>
            <div class="metric-item">
                <div class="metric-val">{{ $sla->resolution_due_at ? \Carbon\Carbon::parse($sla->resolution_due_at)->format('d/m H:i') : '—' }}</div>
                <div class="metric-lbl">Due Resolution</div>
            </div>
        </div>
    </div>

    {{-- ── SLA PAUSE HISTORY ── --}}
    @if($pauses->count())
    <div class="section">
        <div class="section-header">III. SLA Pause History</div>
        <table class="formal-table">
            <thead>
                <tr>
                    <th style="width:20px;">#</th>
                    <th>Pause Reason</th>
                    <th>Trigger Status</th>
                    <th>Start Time</th>
                    <th>End Time</th>
                    <th class="r">Duration (hrs)</th>
                </tr>
            </thead>
            <tbody>
                @php $totalPauseDuration = 0; @endphp
                @foreach($pauses as $i => $p)
                @php
                    $reasonLabel = match($p->pause_reason) {
                        'waiting_customer' => 'Waiting for Customer Response',
                        'sent_to_sap'      => 'Escalated to SAP',
                        'sent_to_support'  => 'Escalated to Support Team',
                        'on_hold'          => 'Ticket On Hold',
                        default            => ucwords(str_replace('_', ' ', $p->pause_reason)),
                    };
                    $totalPauseDuration += (float)($p->duration_hours ?? 0);
                @endphp
                <tr>
                    <td class="c" style="color:#9ca3af;">{{ $i + 1 }}</td>
                    <td>{{ $reasonLabel }}</td>
                    <td style="color:#6b7280; font-style:italic;">{{ $p->triggered_by_status ?? '—' }}</td>
                    <td>{{ $p->started_at ? \Carbon\Carbon::parse($p->started_at)->format('d/m/Y H:i') : '—' }}</td>
                    <td>
                        @if($p->ended_at)
                            {{ \Carbon\Carbon::parse($p->ended_at)->format('d/m/Y H:i') }}
                        @else
                            <span style="color:#d97706; font-weight:700;">Ongoing</span>
                        @endif
                    </td>
                    <td class="r {{ $p->duration_hours ? 'num-warn' : 'num-empty' }}">
                        {{ $p->duration_hours ? number_format((float)$p->duration_hours, 2) : '—' }}
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" style="text-align:right; color:#6b7280;">Total Pause Duration</td>
                    <td class="r" style="color:#ea580c;">{{ number_format($totalPauseDuration, 2) }} hrs</td>
                </tr>
            </tfoot>
        </table>
    </div>
    @endif

    {{-- ── EVENT LOG ── --}}
    <div class="section">
        <div class="section-header">{{ $pauses->count() ? 'IV' : 'III' }}. SLA Event History</div>

        @if($events->count())
        <table class="formal-table">
            <thead>
                <tr>
                    <th style="width:18px;">#</th>
                    <th style="width:70px;">Date</th>
                    <th style="width:42px;">Time</th>
                    <th>Event Description</th>
                    <th class="r" style="width:72px;">Waiting (hrs)</th>
                    <th class="r" style="width:75px;">Response (hrs)</th>
                    <th class="r" style="width:80px;">Resolution (hrs)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($events as $i => $e)
                @php
                    $eventLabel = match($e->event_type) {
                        'email_received'        => 'Email / Request Received',
                        'ticket_validated'      => 'Ticket Validated & Created',
                        'resolution_sent'       => 'Resolution Sent',
                        'customer_replied'      => 'Customer Replied',
                        'escalated_to_sap'      => 'Ticket Escalated to SAP',
                        'escalated_to_support'  => 'Ticket Escalated to Support',
                        'sla_warning'           => 'SLA Deadline Warning',
                        'sla_breached'          => 'SLA Deadline Breached',
                        'ticket_closed'         => 'Ticket Closed / Resolved',
                        default                 => ucwords(str_replace('_', ' ', $e->event_type)),
                    };
                    $eventAt = $e->event_at ? \Carbon\Carbon::parse($e->event_at) : null;
                @endphp
                <tr>
                    <td class="c" style="color:#9ca3af;">{{ $i + 1 }}</td>
                    <td style="color:#374151;">{{ $eventAt?->format('d/m/Y') ?? '—' }}</td>
                    <td style="color:#374151;">{{ $eventAt?->format('H:i') ?? '—' }}</td>
                    <td>
                        <span class="event-dot dot-{{ $e->event_type }}"></span>{{ $eventLabel }}
                        @if($e->notes)
                            <br><span style="color:#9ca3af; font-style:italic; font-size:8px; margin-left:11px;">{{ $e->notes }}</span>
                        @endif
                    </td>
                    <td class="r {{ $e->waiting_hours ? 'num-warn' : 'num-empty' }}">
                        {{ $e->waiting_hours ? number_format((float)$e->waiting_hours, 2) : '—' }}
                    </td>
                    <td class="r {{ $e->response_hours ? 'num-ok' : 'num-empty' }}">
                        {{ $e->response_hours ? number_format((float)$e->response_hours, 2) : '—' }}
                    </td>
                    <td class="r {{ $e->resolution_hours ? 'num-info' : 'num-empty' }}">
                        {{ $e->resolution_hours ? number_format((float)$e->resolution_hours, 2) : '—' }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
            <p class="no-data">No events recorded for this ticket.</p>
        @endif
    </div>

    {{-- ── SIGNATURES ── --}}
    <div class="signature-area">
        <div class="sig-cell">
            <div style="font-size:8px; color:#6b7280; margin-bottom:4px;">Prepared by,</div>
            <div class="sig-line"></div>
            <div class="sig-name">EC EcoSystem Helpdesk Team</div>
            <div class="sig-role">Support &amp; SLA Management</div>
        </div>
        <div class="sig-cell">
            <div style="font-size:8px; color:#6b7280; margin-bottom:4px;">Acknowledged by,</div>
            <div class="sig-line"></div>
            <div class="sig-name">{{ $ticket->customer?->basicData?->name_1 ?? 'Customer' }}</div>
            <div class="sig-role">Service Recipient</div>
        </div>
        <div class="sig-cell">
            <div style="font-size:8px; color:#6b7280; margin-bottom:4px;">Approved by,</div>
            <div class="sig-line"></div>
            <div class="sig-name">Service Lead</div>
            <div class="sig-role">EC EcoSystem Management</div>
        </div>
    </div>

    <div style="margin-top:14px; border:1px solid #e5e7eb; padding:8px 12px; background:#f9fafb; font-size:7.5px; color:#9ca3af; line-height:1.6;">
        <strong style="color:#6b7280;">Note:</strong>
        This document is an official report related to the Service Level Agreement between EC EcoSystem and the respective customer.
        All data is sourced from the EcoSystem platform and is strictly confidential.
        Distribution of this document to unauthorized parties is prohibited.
        All timestamps use WIB timezone (UTC+7).
    </div>

</div>
</div>

</body>
</html>

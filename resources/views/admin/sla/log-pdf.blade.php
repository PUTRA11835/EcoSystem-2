<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SLA Activity Log — {{ $ticket->ticket_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 9.5px;
            color: #1a1a2e;
            line-height: 1.4;
            background: #fff;
        }

        /* ── Page Layout ─────────────────────────────────────── */
        .page-wrap { padding: 0 0 16px 0; }

        /* ── Letterhead ──────────────────────────────────────── */
        .letterhead {
            background: #7b1010;
            color: #fff;
            padding: 14px 20px 12px 20px;
        }
        .letterhead-inner {
            display: table;
            width: 100%;
        }
        .letterhead-left  { display: table-cell; vertical-align: middle; width: 60%; }
        .letterhead-right { display: table-cell; vertical-align: middle; width: 40%; text-align: right; }
        .company-name   { font-size: 15px; font-weight: bold; letter-spacing: 0.5px; }
        .company-sub    { font-size: 8px; color: rgba(255,255,255,0.75); margin-top: 1px; letter-spacing: 0.3px; }
        .doc-title      { font-size: 11px; font-weight: bold; color: #fff; }
        .doc-number     { font-size: 8px; color: rgba(255,255,255,0.7); margin-top: 2px; }

        /* ── Document Banner ─────────────────────────────────── */
        .doc-banner {
            background: #f0f0f0;
            border-bottom: 2px solid #7b1010;
            padding: 5px 20px;
            display: table;
            width: 100%;
        }
        .doc-banner-left  { display: table-cell; vertical-align: middle; font-size: 9px; color: #555; }
        .doc-banner-right { display: table-cell; vertical-align: middle; text-align: right; font-size: 8.5px; color: #555; }
        .doc-banner strong { color: #1a1a2e; }

        /* ── Section ─────────────────────────────────────────── */
        .section { padding: 10px 20px; border-bottom: 1px solid #e8e8e8; }
        .section-title {
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #7b1010;
            border-left: 3px solid #7b1010;
            padding-left: 6px;
            margin-bottom: 7px;
        }

        /* ── Info Grid ───────────────────────────────────────── */
        .info-table { width: 100%; border-collapse: collapse; }
        .info-table td { padding: 2px 6px 2px 0; vertical-align: top; font-size: 9px; }
        .info-label { color: #888; width: 100px; white-space: nowrap; }
        .info-value { font-weight: 600; color: #1a1a2e; }
        .info-separator { width: 20px; }

        /* ── SLA Summary Boxes ───────────────────────────────── */
        .sla-summary-table { width: 100%; border-collapse: collapse; }
        .sla-summary-table td { vertical-align: top; padding: 0 6px 0 0; }
        .sla-box {
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            padding: 8px 10px;
            background: #fafafa;
        }
        .sla-box-label { font-size: 7.5px; color: #999; text-transform: uppercase; letter-spacing: 0.07em; }
        .sla-box-value { font-size: 16px; font-weight: bold; margin: 2px 0; }
        .sla-box-detail { font-size: 8px; color: #666; }
        .sla-box-status {
            display: inline-block;
            font-size: 7.5px;
            font-weight: bold;
            padding: 2px 6px;
            border-radius: 3px;
            margin-top: 3px;
        }
        .c-met      { color: #15803d; }
        .c-breached { color: #dc2626; }
        .c-pending  { color: #2563eb; }
        .c-paused   { color: #d97706; }
        .bg-met      { background: #dcfce7; color: #15803d; }
        .bg-breached { background: #fee2e2; color: #dc2626; }
        .bg-pending  { background: #dbeafe; color: #2563eb; }
        .bg-paused   { background: #fef3c7; color: #d97706; }

        /* ── Metrics Strip ───────────────────────────────────── */
        .metrics-table { width: 100%; border-collapse: collapse; }
        .metrics-table td { padding: 0 12px 0 0; vertical-align: top; }
        .metric-label { font-size: 7.5px; color: #aaa; text-transform: uppercase; letter-spacing: 0.05em; }
        .metric-value { font-size: 12px; font-weight: bold; color: #1a1a2e; margin-top: 1px; }

        /* ── SLA Log Table ───────────────────────────────────── */
        .log-section { padding: 10px 20px 6px 20px; }
        .log-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5px;
        }
        .log-table thead tr {
            background: #1a1a2e;
            color: #fff;
        }
        .log-table thead th {
            padding: 5px 6px;
            text-align: left;
            font-size: 8px;
            font-weight: bold;
            letter-spacing: 0.04em;
            border-right: 1px solid rgba(255,255,255,0.1);
            white-space: nowrap;
        }
        .log-table thead th:last-child { border-right: none; }
        .log-table tbody tr:nth-child(even) { background: #f9f9f9; }
        .log-table tbody tr:nth-child(odd)  { background: #fff; }
        .log-table tbody td {
            padding: 4px 6px;
            border-bottom: 1px solid #efefef;
            vertical-align: top;
            font-size: 8.5px;
        }
        .log-table tbody tr:last-child td { border-bottom: none; }
        .log-table .col-date   { width: 54px;  }
        .log-table .col-time   { width: 34px;  }
        .log-table .col-wait   { width: 52px; text-align: right; }
        .log-table .col-resp   { width: 52px; text-align: right; }
        .log-table .col-res    { width: 36px; text-align: right; }
        .log-table .col-event  { width: 80px; }
        .log-table .col-status { width: 72px; }
        .log-table .col-ball   { width: 50px; }
        .log-table .col-msg    { }

        .dot {
            display: inline-block;
            width: 6px; height: 6px;
            border-radius: 50%;
            margin-right: 3px;
            vertical-align: middle;
        }
        .event-label { display: inline-block; vertical-align: middle; }

        .ball-badge {
            display: inline-block;
            font-size: 7.5px;
            font-weight: bold;
            padding: 1px 5px;
            border-radius: 3px;
            white-space: nowrap;
        }
        .ball-helpdesk { background: #dbeafe; color: #1d4ed8; }
        .ball-customer { background: #ffedd5; color: #c2410c; }
        .ball-sap      { background: #f3e8ff; color: #7c3aed; }

        .status-badge {
            display: inline-block;
            font-size: 7.5px;
            font-weight: 600;
            padding: 1px 5px;
            border-radius: 3px;
            background: #f3f4f6;
            color: #374151;
            white-space: nowrap;
        }

        .date-group-header td {
            background: #f0f0f0 !important;
            font-weight: bold;
            color: #444;
            font-size: 8px;
            padding: 3px 6px;
            border-bottom: 1px solid #ddd;
        }

        /* ── Pause Table ─────────────────────────────────────── */
        .pause-table { width: 100%; border-collapse: collapse; font-size: 8.5px; }
        .pause-table thead tr { background: #f0f0f0; }
        .pause-table thead th {
            padding: 4px 8px;
            text-align: left;
            font-size: 8px;
            font-weight: bold;
            color: #444;
            border-bottom: 1px solid #ddd;
        }
        .pause-table tbody td {
            padding: 4px 8px;
            border-bottom: 1px solid #f0f0f0;
        }

        /* ── Signature ───────────────────────────────────────── */
        .sig-table { width: 100%; border-collapse: collapse; }
        .sig-table td { vertical-align: top; padding: 0 16px 0 0; width: 33.33%; }
        .sig-box { border-top: 1.5px solid #7b1010; padding-top: 6px; text-align: center; }
        .sig-role { font-size: 8px; color: #888; font-weight: bold; text-transform: uppercase; letter-spacing: 0.05em; }
        .sig-space { height: 40px; }
        .sig-line { border-top: 1px solid #aaa; margin: 0 20px; padding-top: 4px; font-size: 8px; color: #555; }

        /* ── Footer ──────────────────────────────────────────── */
        .footer {
            text-align: center;
            font-size: 7.5px;
            color: #aaa;
            padding: 8px 20px;
            border-top: 1px solid #e0e0e0;
            margin-top: 12px;
        }
        .footer strong { color: #555; }

        /* ── Watermark stripe ────────────────────────────────── */
        .stripe { height: 4px; background: #7b1010; }
        .stripe-thin { height: 2px; background: #c0392b; }
    </style>
</head>
<body>
<div class="page-wrap">

{{-- ── Top accent stripe ──────────────────────────────────────────────────── --}}
<div class="stripe"></div>
<div class="stripe-thin"></div>

{{-- ── Letterhead ─────────────────────────────────────────────────────────── --}}
<div class="letterhead">
    <div class="letterhead-inner">
        <div class="letterhead-left">
            <div class="company-name">PT Eclectic Consulting</div>
            <div class="company-sub">Technology Consulting &bull; ERP Solutions &bull; IT Support</div>
        </div>
        <div class="letterhead-right">
            <div class="doc-title">SLA Activity Log</div>
            <div class="doc-number">{{ $docNumber }}</div>
        </div>
    </div>
</div>

{{-- ── Document Banner ─────────────────────────────────────────────────────── --}}
<div class="doc-banner">
    <div class="doc-banner-left">
        <strong>Ticket #{{ $ticket->ticket_number }}</strong>
        &nbsp;&bull;&nbsp;
        {{ mb_substr($ticket->description ?? '—', 0, 80) }}{{ strlen($ticket->description ?? '') > 80 ? '…' : '' }}
    </div>
    <div class="doc-banner-right">
        Generated: <strong>{{ now()->format('d M Y, H:i') }}</strong>
        &nbsp;&bull;&nbsp;
        Status: <strong>{{ strtoupper(str_replace('_', ' ', $ticket->status ?? '—')) }}</strong>
    </div>
</div>

{{-- ── Ticket Information ───────────────────────────────────────────────────── --}}
<div class="section">
    <div class="section-title">Ticket Information</div>
    <table class="info-table">
        <tr>
            <td class="info-label">Customer</td>
            <td style="width:6px">:</td>
            <td class="info-value" style="width:30%">{{ optional(optional($ticket->customer)->basicData)->name_1 ?? '—' }}</td>
            <td class="info-separator"></td>
            <td class="info-label">Ticket Type</td>
            <td style="width:6px">:</td>
            <td class="info-value" style="width:30%">{{ $ticket->ticket_type ?? '—' }}</td>
            <td class="info-separator"></td>
            <td class="info-label">Priority</td>
            <td style="width:6px">:</td>
            <td class="info-value">{{ $ticket->ticket_priority ?? '—' }}</td>
        </tr>
        <tr>
            <td class="info-label">SLA Start</td>
            <td style="width:6px">:</td>
            <td class="info-value">{{ $sla?->sla_start_at?->format('d M Y, H:i') ?? '—' }}</td>
            <td class="info-separator"></td>
            <td class="info-label">Scale</td>
            <td style="width:6px">:</td>
            <td class="info-value">{{ $ticket->scale ?? '—' }}</td>
            <td class="info-separator"></td>
            <td class="info-label">SLA Mode</td>
            <td style="width:6px">:</td>
            <td class="info-value">{{ $sla?->sla_mode === 'full' ? 'Full (Response + Resolution)' : 'Response Only' }}</td>
        </tr>
        <tr>
            <td class="info-label">Hour Calculation</td>
            <td style="width:6px">:</td>
            <td class="info-value">{{ $policy?->is_24_hours ? '24/7 Calendar Hours' : 'Business Hours (Mon–Fri 09:00–18:00)' }}</td>
            <td class="info-separator"></td>
            <td class="info-label">Resolved At</td>
            <td style="width:6px">:</td>
            <td class="info-value">{{ $sla?->resolved_at?->format('d M Y, H:i') ?? 'Not yet resolved' }}</td>
            <td class="info-separator"></td>
            <td class="info-label">Ball Holder</td>
            <td style="width:6px">:</td>
            <td class="info-value">{{ $sla ? ucfirst($sla->ball_holder) : '—' }}</td>
        </tr>
    </table>
</div>

{{-- ── SLA Summary ──────────────────────────────────────────────────────────── --}}
@if($sla)
<div class="section">
    <div class="section-title">SLA Performance Summary</div>
    <table class="sla-summary-table">
        <tr>
            {{-- Response SLA --}}
            <td style="width:25%; padding-right:8px;">
                <div class="sla-box">
                    <div class="sla-box-label">Response SLA</div>
                    @php $rCls = match($sla->response_status) { 'met' => 'c-met', 'breached' => 'c-breached', default => 'c-pending' }; @endphp
                    <div class="sla-box-value {{ $rCls }}">
                        {{ $sla->validation_duration_hours !== null ? number_format($sla->validation_duration_hours, 2) : '—' }}
                        <span style="font-size:10px; font-weight:normal; color:#888;">hrs</span>
                    </div>
                    <div class="sla-box-detail">
                        Target: <strong>{{ $policy?->response_hours ?? '—' }} hrs</strong><br>
                        Deadline: {{ $sla->response_due_at?->format('d M Y H:i') ?? '—' }}<br>
                        Responded: {{ $sla->first_responded_at?->format('d M Y H:i') ?? '—' }}
                    </div>
                    @php $rBg = match($sla->response_status) { 'met' => 'bg-met', 'breached' => 'bg-breached', default => 'bg-pending' }; @endphp
                    <div class="sla-box-status {{ $rBg }}">{{ strtoupper($sla->response_status) }}</div>
                </div>
            </td>

            {{-- Resolution SLA --}}
            <td style="width:25%; padding-right:8px;">
                <div class="sla-box">
                    <div class="sla-box-label">Resolution SLA (Net)</div>
                    @if($sla->sla_mode === 'full')
                        @php $resCls = match($sla->resolution_status) { 'met' => 'c-met', 'breached' => 'c-breached', 'paused' => 'c-paused', default => 'c-pending' }; @endphp
                        <div class="sla-box-value {{ $resCls }}">
                            {{ $sla->net_resolution_hours !== null ? number_format($sla->net_resolution_hours, 2) : '—' }}
                            <span style="font-size:10px; font-weight:normal; color:#888;">hrs</span>
                        </div>
                        <div class="sla-box-detail">
                            Target: <strong>{{ $policy?->resolution_hours ?? '—' }} hrs</strong><br>
                            Deadline: {{ $sla->resolution_due_at?->format('d M Y H:i') ?? '—' }}<br>
                            Waiting deducted: {{ number_format($sla->total_waiting_hours, 2) }} hrs
                        </div>
                        @php $resBg = match($sla->resolution_status) { 'met' => 'bg-met', 'breached' => 'bg-breached', 'paused' => 'bg-paused', default => 'bg-pending' }; @endphp
                        <div class="sla-box-status {{ $resBg }}">{{ strtoupper($sla->resolution_status) }}</div>
                    @else
                        <div class="sla-box-value c-pending">— <span style="font-size:10px; font-weight:normal; color:#888;">hrs</span></div>
                        <div class="sla-box-detail">Response-only mode.<br>Resolution is not tracked for this ticket type.</div>
                        <div class="sla-box-status bg-pending">RESPONSE ONLY</div>
                    @endif
                </div>
            </td>

            {{-- Metrics --}}
            <td style="width:50%; padding-left:8px;">
                <table class="metrics-table">
                    <tr>
                        <td>
                            <div class="metric-label">Total Waiting</div>
                            <div class="metric-value">{{ number_format($sla->total_waiting_hours, 2) }} hrs</div>
                        </td>
                        <td>
                            <div class="metric-label">Gross Hours</div>
                            @php
                                $grossH = $sla->resolved_at
                                    ? round($sla->sla_start_at->floatDiffInHours($sla->resolved_at), 2)
                                    : null;
                            @endphp
                            <div class="metric-value">{{ $grossH !== null ? $grossH . ' hrs' : '—' }}</div>
                        </td>
                        <td>
                            <div class="metric-label">Pause Count</div>
                            <div class="metric-value">{{ $pauses->count() }}</div>
                        </td>
                        <td>
                            <div class="metric-label">Event Count</div>
                            <div class="metric-value">{{ $events->count() }}</div>
                        </td>
                    </tr>
                    <tr style="padding-top:6px;">
                        <td colspan="4" style="padding-top:8px;">
                            <div class="metric-label">Resolution Formula</div>
                            <div style="font-size:8px; color:#555; margin-top:2px; background:#f8f8f8; border-radius:3px; padding:4px 6px; border-left:2px solid #7b1010;">
                                Net Resolution = Gross Hours − Total Waiting Hours
                                @if($grossH !== null)
                                &nbsp;→&nbsp; {{ $grossH }} − {{ number_format($sla->total_waiting_hours, 2) }} = <strong>{{ number_format(max(0, $grossH - $sla->total_waiting_hours), 2) }} hrs</strong>
                                @endif
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</div>

{{-- ── Pause History ────────────────────────────────────────────────────────── --}}
@if($pauses->isNotEmpty())
<div class="section">
    <div class="section-title">Waiting / Pause History</div>
    <table class="pause-table">
        <thead>
            <tr>
                <th>Started</th>
                <th>Ended</th>
                <th style="text-align:right">Duration (hrs)</th>
                <th>Reason</th>
                <th>Triggered by Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($pauses as $p)
            <tr>
                <td style="white-space:nowrap">{{ $p->started_at?->format('d M Y, H:i') ?? '—' }}</td>
                <td style="white-space:nowrap">{{ $p->ended_at?->format('d M Y, H:i') ?? 'Still active' }}</td>
                <td style="text-align:right; font-weight:600;">{{ $p->duration_hours !== null ? number_format($p->duration_hours, 2) : '—' }}</td>
                <td>{{ ucwords(str_replace('_', ' ', $p->pause_reason)) }}</td>
                <td>{{ $p->triggered_by_status ? str_replace('_', ' ', $p->triggered_by_status) : '—' }}</td>
            </tr>
            @endforeach
            <tr style="background:#f0f0f0; font-weight:bold;">
                <td colspan="2" style="text-align:right; color:#555;">Total Waiting Time:</td>
                <td style="text-align:right; color:#7b1010;">{{ number_format($sla->total_waiting_hours, 2) }} hrs</td>
                <td colspan="2"></td>
            </tr>
        </tbody>
    </table>
</div>
@endif

{{-- ── SLA Activity Log ─────────────────────────────────────────────────────── --}}
@if($events->isNotEmpty())
<div class="log-section">
    <div class="section-title" style="margin-bottom:8px;">SLA Activity Log</div>
    <table class="log-table">
        <thead>
            <tr>
                <th class="col-date">Date</th>
                <th class="col-time">Time</th>
                <th class="col-wait" style="text-align:right">Waiting</th>
                <th class="col-resp" style="text-align:right">Response</th>
                <th class="col-res"  style="text-align:right">
                    Resolution
                    @if($sla->net_resolution_hours !== null)
                        @php
                            $netH = (float) $sla->net_resolution_hours;
                            $h = floor($netH);
                            $m = round(($netH - $h) * 60);
                        @endphp
                        <span style="color:#a5b4fc; font-weight:normal;">({{ $h }}:{{ str_pad($m,2,'0',STR_PAD_LEFT) }})</span>
                    @endif
                </th>
                <th class="col-event">Event</th>
                <th class="col-status">Ticket Status</th>
                <th class="col-ball">Ball Holder</th>
                <th class="col-msg">Message / Notes</th>
            </tr>
        </thead>
        <tbody>
            @php
                $lastDate = null;
                $dotColors = [
                    'email_received'       => '#6366f1',
                    'ticket_validated'     => '#16a34a',
                    'agent_replied'        => '#2563eb',
                    'customer_replied'     => '#ea580c',
                    'resolution_sent'      => '#0d9488',
                    'escalated_to_sap'     => '#7c3aed',
                    'escalated_to_support' => '#6b7280',
                    'sla_warning'          => '#ca8a04',
                    'sla_breached'         => '#dc2626',
                    'ticket_closed'        => '#374151',
                ];
                $eventLabels = [
                    'email_received'       => 'Email / Request Received',
                    'ticket_validated'     => 'Ticket Created',
                    'agent_replied'        => 'Helpdesk Reply',
                    'customer_replied'     => 'Customer Reply',
                    'resolution_sent'      => 'Resolution Sent',
                    'escalated_to_sap'     => 'Escalated to SAP',
                    'escalated_to_support' => 'Returned to Helpdesk',
                    'sla_warning'          => 'SLA Warning',
                    'sla_breached'         => 'SLA Breached',
                    'ticket_closed'        => 'Ticket Closed',
                ];
                $ballLabels = ['helpdesk' => '▶ Helpdesk', 'customer' => '⏸ Customer', 'sap' => '⏸ SAP'];
                $ballClass  = ['helpdesk' => 'ball-helpdesk', 'customer' => 'ball-customer', 'sap' => 'ball-sap'];
            @endphp

            @foreach($events as $idx => $e)
                @php
                    $eAt       = \Carbon\Carbon::parse($e['event_at']);
                    $dateStr   = $eAt->format('d/m/Y');
                    $timeStr   = $eAt->format('H:i');
                    $isNewDate = ($dateStr !== $lastDate);
                    $lastDate  = $dateStr;
                    $dot       = $dotColors[$e['event_type']] ?? '#9ca3af';
                    $evLabel   = $eventLabels[$e['event_type']] ?? $e['event_type'];
                    $rowBg     = $idx % 2 === 0 ? '#ffffff' : '#f9f9f9';

                    // Waiting
                    $waitStr = null;
                    if (($e['waiting_hours'] ?? null) !== null) {
                        $wm = round($e['waiting_hours'] * 60);
                        $waitStr = number_format((float)$e['waiting_hours'], 2) . ' h(' . $wm . ' min)';
                    }

                    // Response
                    $respStr = null;
                    if (($e['response_hours'] ?? null) !== null) {
                        $rm = round($e['response_hours'] * 60);
                        $respStr = number_format((float)$e['response_hours'], 2) . ' h(' . $rm . ' min)';
                    }

                    // Resolution — format sama dengan Waiting: "1.20 h(72 min)"
                    $resStr = null;
                    if ($e['meeting_paused'] ?? false) {
                        $resStr = 'Paused (Meeting)';
                    } elseif (($e['resolution_hours'] ?? null) !== null) {
                        $rm2    = round((float)$e['resolution_hours'] * 60);
                        $resStr = number_format((float)$e['resolution_hours'], 2) . ' h(' . $rm2 . ' min)';
                    }

                    // Status label
                    $statusLabel = ($e['jarvis_status'] ?? null)
                        ? str_replace('_', ' ', $e['jarvis_status'])
                        : null;

                    // Ball after
                    $ballLabel = ($e['ball_after'] ?? null) ? ($ballLabels[$e['ball_after']] ?? null) : null;
                    $ballCls   = ($e['ball_after'] ?? null) ? ($ballClass[$e['ball_after']] ?? '') : '';

                    // Message / notes — sender_name ditampilkan di kolom pesan untuk identifikasi
                    $msgText = $e['message_preview'] ?? $e['notes'] ?? null;
                    if ($msgText && ($e['sender_name'] ?? null)) {
                        $msgText = $e['sender_name'] . ': ' . $msgText;
                    } elseif ($e['sender_name'] ?? null) {
                        $msgText = $e['sender_name'];
                    }
                @endphp

                @if($isNewDate)
                <tr class="date-group-header">
                    <td colspan="9">{{ $eAt->format('l, d F Y') }}</td>
                </tr>
                @endif

                <tr style="background:{{ $rowBg }};">
                    <td class="col-date" style="color:#888;">{{ $dateStr }}</td>
                    <td class="col-time" style="font-family:monospace; color:#444;">{{ $timeStr }}</td>
                    <td class="col-wait" style="text-align:right; color:#b45309; font-weight:{{ $waitStr ? '600' : 'normal' }};">
                        {{ $waitStr ?? '—' }}
                    </td>
                    <td class="col-resp" style="text-align:right; color:#1d4ed8; font-weight:{{ $respStr ? '600' : 'normal' }};">
                        {{ $respStr ?? '—' }}
                    </td>
                    <td class="col-res" style="text-align:right; color:#4f46e5; font-weight:{{ $resStr ? '600' : 'normal' }}; font-family:monospace;">
                        {{ $resStr ?? '—' }}
                    </td>
                    <td class="col-event">
                        <span class="dot" style="background:{{ $dot }};"></span>
                        <span class="event-label">{{ $evLabel }}</span>
                    </td>
                    <td class="col-status">
                        @if($statusLabel)
                            <span class="status-badge">{{ $statusLabel }}</span>
                        @else
                            <span style="color:#ccc;">—</span>
                        @endif
                    </td>
                    <td class="col-ball">
                        @if($ballLabel)
                            <span class="ball-badge {{ $ballCls }}">{{ $ballLabel }}</span>
                        @else
                            <span style="color:#ccc;">—</span>
                        @endif
                    </td>
                    <td class="col-msg" style="color:#4b5563; word-break:break-word;">{{ $msgText ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- ── Signature ────────────────────────────────────────────────────────────── --}}
<div class="section" style="margin-top:8px;">
    <div class="section-title">Authorization & Acknowledgment</div>
    <table class="sig-table">
        <tr>
            <td>
                <div class="sig-box">
                    <div class="sig-role">Prepared by</div>
                    <div class="sig-space"></div>
                    <div class="sig-line">( &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; )</div>
                    <div style="font-size:7px; color:#aaa; margin-top:3px; text-align:center;">Helpdesk / Support Team</div>
                </div>
            </td>
            <td>
                <div class="sig-box">
                    <div class="sig-role">Acknowledged by</div>
                    <div class="sig-space"></div>
                    <div class="sig-line">( &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; )</div>
                    <div style="font-size:7px; color:#aaa; margin-top:3px; text-align:center;">Customer Representative</div>
                </div>
            </td>
            <td>
                <div class="sig-box">
                    <div class="sig-role">Approved by</div>
                    <div class="sig-space"></div>
                    <div class="sig-line">( &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; )</div>
                    <div style="font-size:7px; color:#aaa; margin-top:3px; text-align:center;">Delivery Support Head</div>
                </div>
            </td>
        </tr>
    </table>
</div>
@endif

{{-- ── Footer ───────────────────────────────────────────────────────────────── --}}
<div class="footer">
    <strong>PT Eclectic Consulting</strong> &bull; SLA Activity Log &bull; Document No: {{ $docNumber }}<br>
    This document is <strong>CONFIDENTIAL</strong> and intended solely for authorised parties involved in this service engagement.<br>
    Unauthorized disclosure, reproduction, or distribution is strictly prohibited. &bull; Generated: {{ now()->format('d M Y, H:i:s') }} WIB
</div>

<div class="stripe-thin"></div>
<div class="stripe"></div>

</div>
</body>
</html>

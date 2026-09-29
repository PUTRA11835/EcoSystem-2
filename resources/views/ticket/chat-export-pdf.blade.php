<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Ticket #{{ $ticket->ticket_number }} — Chat</title>
    <style>
        /* Margin atas/bawah per halaman supaya halaman lanjutan tidak menempel ke tepi;
           halaman pertama tanpa margin atas agar header tetap full-bleed. */
        @page { margin: 28px 0; }
        @page :first { margin-top: 0; }
        * { margin: 0; padding: 0; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color: #1f2937; line-height: 1.55; }

        .hdr { background: #8b1a1a; color: #fff; padding: 16px 28px; }
        .hdr h1 { font-size: 16px; font-weight: bold; }

        .content { padding: 20px 28px 0; }
        .eyebrow { font-size: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.08em; color: #6b7280; }

        table.info { width: 100%; border-collapse: collapse; border: 1px solid #e5e7eb; }
        table.info th, table.info td { text-align: left; padding: 7px 12px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        table.info th { width: 150px; font-weight: normal; color: #6b7280; background: #f9fafb; }
        table.info td { font-weight: bold; }
        table.info tr.last th, table.info tr.last td { border-bottom: none; }

        .logtitle { margin: 24px 0 10px; padding-bottom: 6px; border-bottom: 1px solid #e5e7eb; }

        .msg { border-left: 3px solid #8b1a1a; background: #fbeeee; margin-bottom: 10px; page-break-inside: avoid; }
        .msg.cust { border-left-color: #1d4ed8; background: #eef3fe; }
        table.mh { width: 100%; border-collapse: collapse; }
        table.mh td { padding: 7px 12px 2px; vertical-align: top; }
        .who { font-size: 11.5px; font-weight: bold; }
        .em { color: #6b7280; font-size: 10px; margin-left: 6px; font-weight: normal; }
        .when { font-size: 10px; color: #6b7280; text-align: right; white-space: nowrap; width: 120px; }
        .mb { padding: 2px 12px 9px; word-wrap: break-word; }
        .mb p { margin: 0 0 6px; }
        .mb ul, .mb ol { margin: 0 0 6px 18px; }
        .mb blockquote { border-left: 2px solid #d1d5db; padding-left: 8px; color: #6b7280; margin: 0 0 6px; }
        .mb table { border-collapse: collapse; margin: 0 0 6px; }
        .mb table td, .mb table th { border: 1px solid #d1d5db; padding: 3px 6px; }
        .mb img { max-width: 100%; }
        .mb a { color: #1d4ed8; }

        .empty { color: #6b7280; font-style: italic; padding: 8px 0; }
    </style>
</head>
<body>

<div class="hdr"><h1>Ticket #{{ $ticket->ticket_number }}</h1></div>

<div class="content">
    <table class="info">
        <tr><th>Ticket Number</th><td>{{ $ticket->ticket_number }}</td></tr>
        <tr><th>Description</th><td>{{ $ticket->description ?: '—' }}</td></tr>
        <tr><th>Customer</th><td>{{ $ticket->customer?->basicData?->name_1 ?? '—' }}</td></tr>
        <tr><th>Date Raised</th><td>{{ $ticket->created_at?->format('d M Y, H:i') ?? '—' }}</td></tr>
        <tr><th>Ticket Type</th><td>{{ $ticket->ticket_type ?: '—' }}</td></tr>
        <tr><th>Module</th><td>{{ $moduleName }}</td></tr>
        <tr><th>Priority</th><td>{{ $ticket->ticket_priority ?: '—' }}</td></tr>
        <tr class="last"><th>Status</th><td>{{ $ticket->status_label }}</td></tr>
    </table>

    <div class="logtitle"><span class="eyebrow">Message Log</span></div>

    @forelse($messages as $msg)
    <div class="msg {{ $msg['is_customer'] ? 'cust' : '' }}">
        <table class="mh">
            <tr>
                <td>
                    <span class="who">{{ $msg['sender_name'] }}</span>
                    @if($msg['sender_email'])<span class="em">{{ $msg['sender_email'] }}</span>@endif
                </td>
                <td class="when">{{ $msg['sent_at']?->format('d M Y, H:i') }}</td>
            </tr>
        </table>
        {{-- Sudah disanitasi HTMLPurifier di TicketMessageController::pdfMessageHtml() --}}
        <div class="mb">{!! $msg['html'] !!}</div>
    </div>
    @empty
    <p class="empty">No messages.</p>
    @endforelse
</div>

</body>
</html>

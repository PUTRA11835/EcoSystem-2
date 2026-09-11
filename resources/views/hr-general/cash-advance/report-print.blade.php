{{--
    Cetakan dokumen Cash Advance Report.

    Berdiri sendiri, TIDAK memakai layout `dashboard` — halaman cetak tidak boleh
    membawa sidebar, notifikasi, maupun skrip aplikasi.

    Bentuknya mengikuti cetakan CA, ditambah dua hal yang memang hanya ada di
    laporan: tabel realisasi MULTI-BARIS, dan tiga baris ringkas di bawahnya
    (Advance / Reported / Refund atau Claim).

    🔴 `advance_amount` yang tercetak adalah nilai yang DIBEKUKAN saat laporan
    dibuat (D139), bukan nilai CA hari ini. Kalau nominal CA diubah sesudahnya,
    kertas yang sudah ditandatangani tetap menunjukkan angka yang disetujui.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $report->report_no }} — Cash Advance Report</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            color: #1f2937;
            background: #e5e7eb;
            padding: 24px;
        }

        .sheet {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            padding: 18mm 16mm;
            background: #fff;
            box-shadow: 0 1px 8px rgba(0, 0, 0, .15);
        }

        .doc-title { text-align: center; font-size: 18px; font-weight: bold; letter-spacing: .5px; }
        .doc-company { text-align: center; font-size: 13px; font-weight: bold; margin-top: 2px; }

        .meta { margin-top: 22px; font-size: 12px; }
        .meta td { padding: 2px 0; vertical-align: top; }
        .meta .label { width: 110px; }
        .meta .colon { width: 14px; }

        table.items { width: 100%; border-collapse: collapse; margin-top: 18px; }
        table.items th, table.items td { border: 1px solid #9ca3af; padding: 5px 7px; }
        table.items th { background: #f3f4f6; font-size: 11px; text-align: center; }
        table.items .num { text-align: center; width: 30px; }
        table.items .date { text-align: center; width: 74px; white-space: nowrap; }
        table.items .amount { text-align: right; width: 110px; white-space: nowrap; }
        table.items .total-label { text-align: right; font-weight: bold; }

        table.summary { width: 62%; border-collapse: collapse; margin-top: 12px; margin-left: auto; }
        table.summary td { border: 1px solid #9ca3af; padding: 5px 7px; }
        table.summary .label { font-weight: bold; background: #f3f4f6; }
        table.summary .value { text-align: right; white-space: nowrap; }
        table.summary .settle { font-weight: bold; }

        .signatures { width: 100%; border-collapse: collapse; margin-top: 30px; }
        .signatures th {
            border: 1px solid #9ca3af; background: #f3f4f6;
            padding: 5px; font-size: 11px; font-weight: bold;
        }
        .signatures td {
            border: 1px solid #9ca3af; height: 74px; padding: 5px;
            text-align: center; vertical-align: bottom; font-size: 11px;
        }
        .signatures td.pending { color: #9ca3af; font-style: italic; }

        .note { margin-top: 14px; font-size: 10px; color: #6b7280; }
        .approved-line { margin-top: 10px; font-size: 10px; text-align: right; }

        .toolbar { width: 210mm; margin: 0 auto 12px; text-align: right; }
        .toolbar button {
            padding: 7px 16px; font-size: 12px; font-weight: bold; cursor: pointer;
            background: #991b1b; color: #fff; border: 0; border-radius: 6px;
        }

        @media print {
            body { background: #fff; padding: 0; }
            .sheet { width: auto; min-height: 0; margin: 0; padding: 0; box-shadow: none; }
            .toolbar { display: none; }
        }
    </style>
</head>
<body>

<div class="toolbar">
    <button type="button" onclick="window.print()">Print</button>
</div>

@php
    $amounts = app(\App\Services\CashAdvance\CashAdvanceAmountService::class);
    $difference = (float) $report->difference_amount;
@endphp

<div class="sheet">
    <div class="doc-title">CASH ADVANCE REPORT</div>
    <div class="doc-company">{{ strtoupper($heading) }}</div>

    <table class="meta">
        <tr>
            <td class="label">Report No</td>
            <td class="colon">:</td>
            <td>{{ $report->report_no }}</td>
        </tr>
        <tr>
            <td class="label">Date</td>
            <td class="colon">:</td>
            <td>{{ $report->report_date->format('d.m.Y') }}</td>
        </tr>
        <tr>
            <td class="label">Cash Advance No</td>
            <td class="colon">:</td>
            <td>{{ $report->cashAdvance?->request_no ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Requester</td>
            <td class="colon">:</td>
            <td>{{ $report->employee?->basicData?->nick_name ?? $report->employee?->eci ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Purpose</td>
            <td class="colon">:</td>
            <td>{{ $report->description }}</td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th class="num">No.</th>
                <th class="date">Date</th>
                <th>Description</th>
                <th>Charged To</th>
                <th class="amount">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($report->items as $item)
            <tr>
                <td class="num">{{ $item->line_no }}</td>
                <td class="date">{{ $item->expense_date->format('d.m.Y') }}</td>
                <td>
                    {{ $item->description }}
                    @if($item->receipt_no)
                        <br><span style="font-size:10px;color:#6b7280">Receipt {{ $item->receipt_no }}</span>
                    @endif
                </td>
                <td>{{ $item->costCenterLabel() }}</td>
                <td class="amount">{{ $amounts->format((float) $item->amount) }}</td>
            </tr>
            @endforeach

            <tr>
                <td colspan="4" class="total-label">Total</td>
                <td class="amount"><strong>{{ $amounts->format((float) $report->reported_amount) }}</strong></td>
            </tr>
        </tbody>
    </table>

    {{-- 🔴 TIGA baris yang hanya ada di CAR. Baris ketiga menyebut ARAH uangnya,
         bukan sekadar selisihnya — "Refund 50.000" tanpa keterangan masih
         menyisakan pertanyaan siapa yang membayar siapa. --}}
    <table class="summary">
        <tr>
            <td class="label">Advance Amount</td>
            <td class="value">{{ $report->currency }} {{ $amounts->format((float) $report->advance_amount) }}</td>
        </tr>
        <tr>
            <td class="label">Reported Amount</td>
            <td class="value">{{ $report->currency }} {{ $amounts->format((float) $report->reported_amount) }}</td>
        </tr>
        <tr>
            <td class="label settle">{{ strtoupper($report->settlementLabel()) }}</td>
            <td class="value settle">
                {{ $report->currency }} {{ $amounts->format(abs($difference)) }}
            </td>
        </tr>
    </table>

    <p class="note" style="text-align:right">{{ $report->settlementDirection() }}.</p>

    <table class="signatures">
        <thead>
            <tr>
                @foreach($signatures as $column)
                    <th>{{ $column['title'] }},</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            <tr>
                @foreach($signatures as $column)
                    <td @class(['pending' => $column['pending']])>
                        {{ $column['name'] !== '' ? $column['name'] : '' }}
                    </td>
                @endforeach
            </tr>
        </tbody>
    </table>

    @php
        $lastApproved = $report->approvals
            ->where('actor_role', \App\Models\CashAdvance\CashAdvanceApprovalStep::ACTOR_APPROVER)
            ->where('status', \App\Models\CashAdvance\CashAdvanceReportApproval::STATUS_APPROVED)
            ->sortByDesc('order_seq')
            ->first();
    @endphp

    @if($report->isApproved() && $lastApproved)
    <div class="approved-line">
        approved by: {{ $lastApproved->actor?->basicData?->nick_name ?? $lastApproved->actor?->eci ?? '—' }}<br>
        on: {{ optional($lastApproved->acted_at)->format('d/m/Y') }}
    </div>
    @endif

    @if($report->notes)
    <p class="note">Notes: {{ $report->notes }}</p>
    @endif

    <p class="note">
        Status: {{ $report->statusLabel() }}
        &middot; Printed {{ now()->format('d/m/Y H:i') }}
    </p>
</div>

</body>
</html>

{{--
    Cetakan dokumen reimbursement.

    Berdiri sendiri, TIDAK memakai layout `dashboard` — halaman cetak tidak boleh
    membawa sidebar, notifikasi, maupun skrip aplikasi. Pola yang sama dipakai
    berkas cetak di modul Delivery (`project-planning/exports/*.blade.php`).

    Tata letaknya sengaja dibuat sama persis dengan berkas Excel (Keputusan D113
    & D114): judul, identitas perusahaan, No & Date, tabel item, Total, lalu
    empat kolom tanda tangan. Yang dicetak dan yang diekspor harus dapat
    ditumpuk dan cocok.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $request->request_no }} — Reimbursement</title>
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
        .meta td { padding: 2px 0; }
        .meta .label { width: 70px; }
        .meta .colon { width: 14px; }

        table.items { width: 100%; border-collapse: collapse; margin-top: 18px; }
        table.items th, table.items td { border: 1px solid #9ca3af; padding: 5px 7px; }
        table.items th { background: #f3f4f6; font-size: 11px; text-align: center; }
        table.items .group { text-align: center; font-weight: bold; background: #fafafa; }
        table.items .num { text-align: center; width: 34px; }
        table.items .curr { text-align: center; width: 46px; }
        table.items .amount { text-align: right; width: 110px; white-space: nowrap; }
        table.items .total-label { text-align: right; font-weight: bold; }

        .signatures { width: 100%; border-collapse: collapse; margin-top: 34px; }
        .signatures th {
            border: 1px solid #9ca3af; background: #f3f4f6;
            padding: 5px; font-size: 11px; font-weight: bold;
        }
        .signatures td {
            border: 1px solid #9ca3af; height: 74px; padding: 5px;
            text-align: center; vertical-align: bottom; font-size: 11px;
        }

        .note { margin-top: 14px; font-size: 10px; color: #6b7280; }

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

<div class="sheet">
    <div class="doc-title">REIMBURSEMENT</div>
    <div class="doc-company">{{ strtoupper($heading) }}</div>

    <table class="meta">
        <tr>
            <td class="label">No</td>
            <td class="colon">:</td>
            <td>{{ $request->request_no }}</td>
        </tr>
        <tr>
            <td class="label">Date</td>
            <td class="colon">:</td>
            <td>{{ $request->request_date->format('d.m.Y') }}</td>
        </tr>
        <tr>
            <td class="label">Requester</td>
            <td class="colon">:</td>
            <td>{{ $signatories['requester'] }}</td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th class="num">No.</th>
                <th>Description</th>
                <th style="width:90px;">Receipt No.</th>
                <th class="curr">Curr</th>
                <th class="amount">Amount</th>
            </tr>
        </thead>
        <tbody>
            {{-- Baris judul dokumen, mengikuti bentuk berkas acuan: satu baris
                 penuh berisi judul, lalu rinciannya di bawahnya. --}}
            <tr>
                <td colspan="5" class="group">{{ $request->title }}</td>
            </tr>

            @foreach($request->items as $item)
            <tr>
                <td class="num">{{ $item->line_no }}</td>
                <td>{{ $item->exportDescription() }}</td>
                <td style="text-align:center;">{{ $item->receipt_no ?? '—' }}</td>
                <td class="curr">{{ $item->currency }}</td>
                <td class="amount">{{ number_format((float) $item->amount, 2, ',', '.') }}</td>
            </tr>
            @endforeach

            <tr>
                <td colspan="4" class="total-label">Total</td>
                <td class="amount"><strong>{{ number_format((float) $request->total_amount, 2, ',', '.') }}</strong></td>
            </tr>
        </tbody>
    </table>

    <table class="signatures">
        <thead>
            <tr>
                <th>Requester,</th>
                <th>Accounting,</th>
                <th>Cashier,</th>
                <th>Approved by,</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $signatories['requester'] }}</td>
                <td>{{ $signatories['accounting'] }}</td>
                <td>{{ $signatories['cashier'] }}</td>
                <td>{{ $signatories['approver'] }}</td>
            </tr>
        </tbody>
    </table>

    @if($request->supporting_url)
    <p class="note">Supporting document: {{ $request->supporting_url }}</p>
    @endif

    <p class="note">
        Status: {{ $request->statusLabel() }}
        @if($request->completed_at) · Completed {{ $request->completed_at->format('d.m.Y') }} @endif
    </p>
</div>

</body>
</html>

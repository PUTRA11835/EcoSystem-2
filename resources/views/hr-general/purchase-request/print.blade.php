{{--
    Cetakan dokumen Purchase Request.

    Berdiri sendiri, TIDAK memakai layout `dashboard` — halaman cetak tidak boleh
    membawa sidebar, notifikasi, maupun skrip aplikasi. Pola yang sama dipakai
    berkas cetak Reimbursement dan modul Delivery.

    Tata letaknya mengikuti cetakan PR pada aplikasi acuan: judul, identitas
    perusahaan, No & Date, tabel item, lalu blok tanda tangan dan satu baris
    keterangan persetujuan.

    🔴 JUMLAH KOLOM TANDA TANGAN TIDAK TETAP (Keputusan D129). Kolomnya
    diturunkan dari LANGKAH ALUR milik dokumen ini — satu langkah menghasilkan
    dua kolom, dua langkah menghasilkan tiga. Karena barisnya salinan yang
    dibekukan saat dokumen dibuat, mencetak ulang dokumen lama setelah alurnya
    diubah tetap menghasilkan kertas yang sama.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $request->request_no }} — Purchase Request</title>
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
        .meta .label { width: 80px; }
        .meta .colon { width: 14px; }

        table.items { width: 100%; border-collapse: collapse; margin-top: 18px; }
        table.items th, table.items td { border: 1px solid #9ca3af; padding: 5px 7px; }
        table.items th { background: #f3f4f6; font-size: 11px; text-align: center; }
        table.items .num { text-align: center; width: 34px; }
        table.items .qty { text-align: right; width: 74px; white-space: nowrap; }
        table.items .uom { text-align: center; width: 54px; }
        table.items .date { text-align: center; width: 76px; white-space: nowrap; }
        table.items .period { text-align: center; width: 138px; white-space: nowrap; }
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

<div class="sheet">
    <div class="doc-title">PURCHASE REQUEST</div>
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
            <td class="label">Summary</td>
            <td class="colon">:</td>
            <td>{{ $request->title }}</td>
        </tr>
        <tr>
            <td class="label">Charged To</td>
            <td class="colon">:</td>
            <td>{{ $request->charged_to_label ?? '—' }}</td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th class="num">No.</th>
                <th>Item Description</th>
                <th class="qty">Qty</th>
                <th class="uom">UoM</th>
                <th class="date">Use Date</th>
                <th class="period">Period</th>
                <th>Charged To</th>
            </tr>
        </thead>
        <tbody>
            @foreach($request->items as $item)
            <tr>
                <td class="num">{{ $item->line_no }}</td>
                <td>{{ $item->description }}</td>
                <td class="qty">{{ \App\Models\PurchaseRequest\PurchaseRequestItem::formatQty($item->qty) }}</td>
                <td class="uom">{{ $item->unit }}</td>
                <td class="date">{{ $item->useDateLabel() }}</td>
                <td class="period">{{ $item->periodLabel() }}</td>
                <td>{{ $item->costCenterLabel() }}</td>
            </tr>
            @endforeach

            <tr>
                <td colspan="6" class="total-label">Total Items</td>
                <td><strong>{{ $request->item_count }}</strong></td>
            </tr>
            <tr>
                <td colspan="6" class="total-label">Total Qty</td>
                <td><strong>{{ $request->qtySummaryLabel() }}</strong></td>
            </tr>
        </tbody>
    </table>

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

    {{-- Baris keterangan persetujuan, mengikuti bentuk cetakan acuan. Tidak
         dirender bila dokumennya belum selesai — mencetak "approved by" pada
         dokumen yang masih berjalan akan terbaca sebagai persetujuan yang tidak
         pernah terjadi. --}}
    @php
        $lastApproved = $request->approvals
            ->where('status', \App\Models\PurchaseRequest\PurchaseRequestApproval::STATUS_APPROVED)
            ->sortByDesc('order_seq')
            ->first();
    @endphp

    @if($request->status === \App\Models\PurchaseRequest\PurchaseRequest::STATUS_APPROVED && $lastApproved)
    <div class="approved-line">
        approved by: {{ $lastApproved->actor?->basicData?->nick_name ?? $lastApproved->actor?->eci ?? '—' }}<br>
        on: {{ optional($lastApproved->acted_at)->format('d/m/Y') }}
    </div>
    @endif

    @if($request->notes)
    <p class="note">Notes: {{ $request->notes }}</p>
    @endif

    <p class="note">
        Status: {{ $request->statusLabel() }}
        @if($request->completed_at) · Completed {{ $request->completed_at->format('d.m.Y') }} @endif
    </p>
</div>

</body>
</html>

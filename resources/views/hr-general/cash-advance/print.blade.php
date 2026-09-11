{{--
    Cetakan dokumen Cash Advance.

    Berdiri sendiri, TIDAK memakai layout `dashboard` — halaman cetak tidak boleh
    membawa sidebar, notifikasi, maupun skrip aplikasi. Pola yang sama dipakai
    berkas cetak Reimbursement dan Purchase Request.

    Tata letaknya mengikuti cetakan CA pada aplikasi acuan:
        CASH ADVANCE
        PT ECLECTIC SOLUSI HANDAL
        No.  : CA/2026/09/00001
        Date : 18.08.2026
        No. | Description | Curr | Amount
        1.  | ...         | IDR  | 350.000,00
                            Total | 350.000,00
        Requester, | Accounting, | Cashier, | Approved by,

    🔴 SATU BARIS TABEL, dan itu bukan kekurangan (jawaban C3). CA punya satu
    deskripsi dan satu nominal; tabelnya ada supaya bentuk kertasnya sama dengan
    acuan dan supaya baris Total punya tempat berdiri.

    🔴 EMPAT KOLOM TANDA TANGAN, sumbernya BERBEDA-BEDA (Keputusan D137):
    Requester dari dokumen, Accounting & Cashier dari setelan, dan "Approved by"
    dari orang yang BENAR-BENAR menyetujui pada langkah ber-actor_role = approver.
    Kolom terakhir sengaja TIDAK diambil dari setelan — menyimpan penanda tangan
    di dua tempat hanya melahirkan satu kelas kesalahan baru.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $request->request_no }} — Cash Advance</title>
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
        table.items .curr { text-align: center; width: 54px; }
        table.items .amount { text-align: right; width: 120px; white-space: nowrap; }
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
    <div class="doc-title">CASH ADVANCE</div>
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
            <td>
                {{ $request->request_date->format('d.m.Y') }}
                @if($request->request_date_to)
                    &ndash; {{ $request->request_date_to->format('d.m.Y') }}
                @endif
            </td>
        </tr>
        <tr>
            <td class="label">Requester</td>
            <td class="colon">:</td>
            <td>{{ $request->employee?->basicData?->nick_name ?? $request->employee?->eci ?? '—' }}</td>
        </tr>
        @if($request->charged_to_label)
        <tr>
            <td class="label">Charged To</td>
            <td class="colon">:</td>
            <td>{{ $request->charged_to_label }}</td>
        </tr>
        @endif
    </table>

    @php
        $amounts = app(\App\Services\CashAdvance\CashAdvanceAmountService::class);
    @endphp

    <table class="items">
        <thead>
            <tr>
                <th class="num">No.</th>
                <th>Description</th>
                <th class="curr">Curr</th>
                <th class="amount">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="num">1.</td>
                <td>{{ $request->description }}</td>
                <td class="curr">{{ $request->currency }}</td>
                <td class="amount">{{ $amounts->format((float) $request->amount) }}</td>
            </tr>
            <tr>
                <td colspan="3" class="total-label">Total</td>
                <td class="amount"><strong>{{ $amounts->format((float) $request->amount) }}</strong></td>
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

    {{-- Baris keterangan persetujuan, mengikuti bentuk cetakan acuan. TIDAK
         dirender bila dokumennya belum selesai — mencetak "approved by" pada
         dokumen yang masih berjalan akan terbaca sebagai persetujuan yang tidak
         pernah terjadi. --}}
    @php
        $lastApproved = $request->approvals
            ->where('actor_role', \App\Models\CashAdvance\CashAdvanceApprovalStep::ACTOR_APPROVER)
            ->where('status', \App\Models\CashAdvance\CashAdvanceApproval::STATUS_APPROVED)
            ->sortByDesc('order_seq')
            ->first();
    @endphp

    @if($request->isApproved() && $lastApproved)
    <div class="approved-line">
        approved by: {{ $lastApproved->actor?->basicData?->nick_name ?? $lastApproved->actor?->eci ?? '—' }}<br>
        on: {{ optional($lastApproved->acted_at)->format('d/m/Y') }}
    </div>
    @endif

    @if($request->detail_url)
    <p class="note">Supporting document: {{ $request->detail_url }}</p>
    @endif

    @if($request->notes)
    <p class="note">Notes: {{ $request->notes }}</p>
    @endif

    <p class="note">
        Status: {{ $request->statusLabel() }}
        &middot; Settlement: {{ $request->settlementLabel() }}
        &middot; Printed {{ now()->format('d/m/Y H:i') }}
    </p>
</div>

</body>
</html>

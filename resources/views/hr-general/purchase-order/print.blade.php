<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $order->po_no }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: #111; margin: 24px; }
        h1 { font-size: 16px; margin: 0; text-align: center; letter-spacing: 1px; }
        .company { text-align: center; font-weight: bold; margin: 2px 0 14px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #444; padding: 5px 6px; vertical-align: top; }
        th { background: #eee; text-align: left; }
        .r { text-align: right; }
        .meta td { border: none; padding: 2px 6px; }
        .sign { margin-top: 28px; }
        .sign td { text-align: center; height: 90px; vertical-align: bottom; }
        .tot td { border: none; padding: 2px 6px; }
        @media print { body { margin: 8mm; } }
    </style>
</head>
<body onload="window.print()">
    <h1>PURCHASE ORDER</h1>
    <p class="company">{{ $company }}</p>

    <table class="meta" style="margin-bottom:10px">
        <tr><td width="18%">Document No.</td><td width="32%">: {{ $order->po_no }}</td><td width="18%">Supplier</td><td>: {{ $order->supplier_name }}</td></tr>
        <tr><td>Date</td><td>: {{ $order->po_date->format('d M Y') }}</td><td>Invoice No.</td><td>: {{ $order->invoice_no ?: '—' }}</td></tr>
        <tr><td>PR No.</td><td>: {{ $order->purchaseRequest?->request_no ?? '—' }}</td><td>Invoice Date</td><td>: {{ $order->invoice_date?->format('d M Y') ?? '—' }}</td></tr>
        <tr><td>Transaction No.</td><td>: {{ $order->transaction_no ?: '—' }}</td><td>Delivered</td><td>: {{ $order->delivered_date?->format('d M Y') ?? '—' }}</td></tr>
    </table>

    <table>
        <thead>
            <tr><th>No</th><th>Description</th><th class="r">Qty</th><th class="r">Unit price</th><th class="r">Discount</th><th>Tax</th><th class="r">Total</th></tr>
        </thead>
        <tbody>
            @foreach($order->items as $item)
            <tr>
                <td>{{ $item->line_no }}</td>
                <td>{{ $item->description }}@if($item->project_label)<br><small>{{ $item->project_label }}</small>@endif</td>
                <td class="r">{{ rtrim(rtrim(number_format($item->qty, 2, ',', '.'), '0'), ',') }} {{ $item->unit }}</td>
                <td class="r">{{ $item->currency }} {{ number_format($item->unit_price, 2, ',', '.') }}</td>
                <td class="r">{{ $item->discount_amount > 0 ? number_format($item->discount_amount, 2, ',', '.') : '—' }}</td>
                <td>{{ collect($item->tax_keys ?? [])->map(fn ($k) => $taxLabels[$k] ?? $k)->implode(', ') ?: '—' }}</td>
                <td class="r">{{ number_format($item->line_total, 2, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table class="tot" style="width:45%; margin-left:auto; margin-top:8px">
        <tr><td>Subtotal</td><td class="r">{{ number_format($order->subtotal, 2, ',', '.') }}</td></tr>
        <tr><td>Discount</td><td class="r">- {{ number_format($order->discount_total, 2, ',', '.') }}</td></tr>
        <tr><td>VAT</td><td class="r">{{ number_format($order->vat_total, 2, ',', '.') }}</td></tr>
        <tr><td>Withholding</td><td class="r">- {{ number_format($order->withholding_total, 2, ',', '.') }}</td></tr>
        <tr><td><strong>Grand Total</strong></td><td class="r"><strong>{{ number_format($order->grand_total, 2, ',', '.') }}</strong></td></tr>
    </table>

    @if($order->notes)<p><strong>Notes:</strong> {{ $order->notes }}</p>@endif

    <table class="sign">
        <tr>
            @foreach(['Requester' => $order->requester, 'Accounting' => $order->accounting, 'Cashier' => $order->cashier, 'Approved by' => $order->approver] as $label => $person)
            <td>{{ $label }}<br><br><br><br><u>{{ $person?->fullNameLabel() ?? '(                    )' }}</u></td>
            @endforeach
        </tr>
    </table>
</body>
</html>

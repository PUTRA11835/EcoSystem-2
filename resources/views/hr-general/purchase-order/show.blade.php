@extends('dashboard')

@php use App\Models\PurchaseOrder\PurchaseOrder; @endphp

@section('title', 'Purchase Order ' . $order->po_no)
@section('page-title', 'Purchase Order')
@section('page-subtitle', 'Document ' . $order->po_no)

@section('content')
@include('partials.page-toolbar', ['back' => route('general.purchase-order.index'), 'backLabel' => 'Back to List', 'edit' => $canEdit ? route('general.purchase-order.edit', $order) : null, 'editLabel' => 'Edit'])
@php
    $tone = ['approved' => 'bg-green-100 text-green-700', 'rejected' => 'bg-red-100 text-red-700'][$order->status] ?? 'bg-amber-100 text-amber-700';
@endphp
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5 items-start">

    <div class="space-y-5">
        <div class="bg-white rounded-xl p-6 shadow-sm">
            <h2 class="text-lg font-bold text-gray-900 mb-4 pb-3 border-b border-gray-100">Main Information</h2>
            <dl class="space-y-3 text-sm">
                @foreach([
                    'Document No.' => $order->po_no,
                    'PR No.'       => $order->purchaseRequest?->request_no ?? '—',
                    'Date'         => $order->po_date->format('d M Y'),
                    'Supplier'     => $order->supplier_name,
                    'Transaction'  => $order->transaction_no ?: '—',
                    'Invoice No.'  => $order->invoice_no ?: '—',
                    'Invoice Date' => $order->invoice_date?->format('d M Y') ?? '—',
                    'Delivered'    => $order->delivered_date?->format('d M Y') ?? '—',
                    'Notes'        => $order->notes ?: '—',
                ] as $k => $v)
                <div class="flex gap-3">
                    <dt class="w-28 shrink-0 text-gray-500">{{ $k }}</dt>
                    <dd class="text-gray-900 font-medium break-words">{{ $v }}</dd>
                </div>
                @endforeach
                <div class="flex gap-3">
                    <dt class="w-28 shrink-0 text-gray-500">Status</dt>
                    <dd><span class="inline-block px-2.5 py-1 rounded-full text-xs font-semibold {{ $tone }}">{{ $order->statusLabel() }}</span></dd>
                </div>
                @if($order->decider)
                <div class="flex gap-3">
                    <dt class="w-28 shrink-0 text-gray-500">Decided by</dt>
                    <dd class="text-gray-900">{{ $order->decider->fullNameLabel() }}
                        <span class="text-xs text-gray-400">· {{ $order->decided_at?->format('d M Y H:i') }}</span>
                        @if($order->decision_notes)<span class="block text-xs italic text-gray-500">"{{ $order->decision_notes }}"</span>@endif
                    </dd>
                </div>
                @endif
            </dl>
        </div>

        @if($canDecide)
        <div class="bg-white rounded-xl p-6 shadow-sm">
            <h2 class="text-lg font-bold text-gray-900 mb-4 text-center">Approval Actions</h2>

            <form method="POST" action="{{ route('general.purchase-order.approve', $order) }}" class="js-po-approve mb-2">
                @csrf
                <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-green-700 text-white text-sm font-semibold rounded-lg hover:bg-green-800">
                    <i class="fas fa-circle-check"></i> Approve
                </button>
            </form>

            <button type="button" id="poRejectToggle"
                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-white text-red-700 text-sm font-semibold rounded-lg border border-red-300 hover:bg-red-50">
                <i class="fas fa-circle-xmark"></i> Reject
            </button>

            <form method="POST" action="{{ route('general.purchase-order.reject', $order) }}" id="poRejectForm" class="hidden mt-3 space-y-2">
                @csrf
                <textarea name="notes" rows="3" required minlength="5" maxlength="255" placeholder="Explain why this purchase order is rejected..."
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800"></textarea>
                <button type="submit" class="w-full px-4 py-2 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900">Confirm Rejection</button>
            </form>
        </div>
        @endif
    </div>

    <div class="lg:col-span-2 bg-white rounded-xl p-6 shadow-sm">
        <h2 class="text-lg font-bold text-gray-900 mb-4 pb-3 border-b border-gray-100">Items</h2>

        <div class="border border-gray-200 rounded-lg overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                        <th class="px-3 py-3 w-10">No</th>
                        <th class="px-3 py-3">Description</th>
                        <th class="px-3 py-3 text-right">Qty</th>
                        <th class="px-3 py-3 text-right">Unit price</th>
                        <th class="px-3 py-3 text-right">Discount</th>
                        <th class="px-3 py-3">Tax</th>
                        <th class="px-3 py-3 text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($order->items as $item)
                    <tr class="align-top">
                        <td class="px-3 py-3 text-gray-400">{{ $item->line_no }}</td>
                        <td class="px-3 py-3">
                            <div class="text-gray-900">{{ $item->description }}</div>
                            <div class="text-xs text-gray-400">
                                @if($item->project_label){{ $item->project_label }} · @endif{{ $item->periodLabel() }}
                            </div>
                        </td>
                        <td class="px-3 py-3 text-right whitespace-nowrap">{{ rtrim(rtrim(number_format($item->qty, 2, ',', '.'), '0'), ',') }} {{ $item->unit }}</td>
                        <td class="px-3 py-3 text-right whitespace-nowrap">{{ $item->currency }} {{ number_format($item->unit_price, 2, ',', '.') }}</td>
                        <td class="px-3 py-3 text-right whitespace-nowrap">{{ $item->discount_amount > 0 ? number_format($item->discount_amount, 2, ',', '.') : '—' }}</td>
                        <td class="px-3 py-3 text-xs text-gray-600">
                            {{ collect($item->tax_keys ?? [])->map(fn ($k) => $taxLabels[$k] ?? $k)->implode(', ') ?: '—' }}
                        </td>
                        <td class="px-3 py-3 text-right font-medium whitespace-nowrap">{{ number_format($item->line_total, 2, ',', '.') }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-50 border-t-2 border-gray-200 text-sm">
                    <tr><td colspan="6" class="px-3 py-2 text-right text-gray-500">Subtotal</td><td class="px-3 py-2 text-right">{{ PurchaseOrder::money($order->subtotal) }}</td></tr>
                    <tr><td colspan="6" class="px-3 py-2 text-right text-gray-500">Discount</td><td class="px-3 py-2 text-right">− {{ PurchaseOrder::money($order->discount_total) }}</td></tr>
                    <tr><td colspan="6" class="px-3 py-2 text-right text-gray-500">VAT</td><td class="px-3 py-2 text-right">{{ PurchaseOrder::money($order->vat_total) }}</td></tr>
                    <tr><td colspan="6" class="px-3 py-2 text-right text-gray-500">Withholding</td><td class="px-3 py-2 text-right">− {{ PurchaseOrder::money($order->withholding_total) }}</td></tr>
                    <tr><td colspan="6" class="px-3 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Grand Total</td>
                        <td class="px-3 py-3 text-right text-lg font-bold text-red-800 whitespace-nowrap">{{ PurchaseOrder::money($order->grand_total) }}</td></tr>
                </tfoot>
            </table>
        </div>

        <div class="mt-5 grid grid-cols-2 md:grid-cols-4 gap-3">
            @foreach([
                'Requester'  => $order->requester,
                'Accounting' => $order->accounting,
                'Cashier'    => $order->cashier,
                'Approver'   => $order->approver,
            ] as $label => $person)
            <div class="border border-gray-200 rounded-lg px-3 py-2">
                <p class="text-[10px] font-semibold text-gray-500 uppercase tracking-wide">{{ $label }}</p>
                <p class="text-sm text-gray-800 mt-0.5">{{ $person?->fullNameLabel() ?? '—' }}</p>
            </div>
            @endforeach
        </div>

        <div class="mt-5 pt-4 border-t border-gray-100 flex flex-wrap gap-2 justify-end">
            <a href="{{ route('general.purchase-order.print', $order) }}" target="_blank"
               class="inline-flex items-center gap-2 px-4 py-2 bg-gray-800 text-white text-sm font-semibold rounded-lg hover:bg-gray-900">
                <i class="fas fa-print"></i> Print Form
            </a>
            @if($canDelete)
            <button type="button" id="poDeleteToggle"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-white text-red-700 text-sm font-semibold rounded-lg border border-red-300 hover:bg-red-50">
                <i class="fas fa-trash"></i> Delete
            </button>
            @endif
        </div>

        @if($canDelete)
        <form method="POST" action="{{ route('general.purchase-order.destroy', $order) }}" id="poDeleteForm"
              class="hidden mt-4 border border-red-200 bg-red-50 rounded-lg p-4 space-y-2">
            @csrf
            <p class="text-sm text-red-900"><span class="font-semibold">{{ $order->po_no }} will be removed from the list.</span> It stays on record with the reason below.</p>
            <textarea name="delete_reason" rows="2" required minlength="3" maxlength="255" placeholder="Why is this document being deleted?"
                      class="w-full px-3 py-2 border border-red-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800"></textarea>
            <button type="submit" class="px-4 py-2 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900">Confirm Deletion</button>
        </form>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
    const poReject = document.getElementById('poRejectToggle');
    if (poReject) poReject.addEventListener('click', () => document.getElementById('poRejectForm').classList.toggle('hidden'));

    const poDelete = document.getElementById('poDeleteToggle');
    if (poDelete) poDelete.addEventListener('click', () => document.getElementById('poDeleteForm').classList.toggle('hidden'));

    document.querySelectorAll('.js-po-approve').forEach(function (form) {
        form.addEventListener('submit', async function (event) {
            if (form.dataset.confirmed === 'yes') return;
            event.preventDefault();

            const ok = await showConfirm('Approve this purchase order?', 'Approve Purchase Order', 'warning',
                { okText: 'Approve', cancelText: 'Cancel' });
            if (!ok) return;

            form.dataset.confirmed = 'yes';
            form.submit();
        });
    });
</script>
@endpush

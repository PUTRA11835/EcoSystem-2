@extends('dashboard')

@section('title', 'Purchase Orders')
@section('page-title', 'Purchase Management')
@section('page-subtitle', 'Purchase orders created manually or from approved purchase requests')

@php
    use App\Models\PurchaseOrder\PurchaseOrder;

    $filterForm = 'purchaseOrderFilters';
    $filter     = 'hr-general.recruitment.components.header-filter';
    $action     = 'hr-general.recruitment.components.icon-action';

    $cards = [
        ['label' => 'Waiting for You', 'value' => $counts['mine'],      'class' => 'text-red-800',   'icon' => 'fa-hourglass-half'],
        ['label' => 'Submitted',       'value' => $counts['submitted'], 'class' => 'text-amber-600', 'icon' => 'fa-clock'],
        ['label' => 'Approved',        'value' => $counts['approved'],  'class' => 'text-green-600', 'icon' => 'fa-circle-check'],
        ['label' => 'Rejected',        'value' => $counts['rejected'],  'class' => 'text-red-600',   'icon' => 'fa-circle-xmark'],
    ];

    $statusOptions = ['submitted' => 'Submitted', 'approved' => 'Approved', 'rejected' => 'Rejected'];
@endphp

@section('content')
@include('hr-general.purchase-request.components.hub-tabs')
@include('hr-general.reimbursement.components.date-picker')

<div class="space-y-5">
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach($cards as $card)
        <div class="bg-white rounded-xl p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ $card['label'] }}</p>
                <i class="fas {{ $card['icon'] }} text-sm {{ $card['class'] }} opacity-60"></i>
            </div>
            <p class="text-2xl font-bold mt-1 {{ $card['class'] }}">{{ $card['value'] }}</p>
        </div>
        @endforeach
    </div>

    <form id="{{ $filterForm }}" method="GET" action="{{ route('general.purchase-order.index') }}" data-filter-form class="hidden"></form>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-3.5 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h3 class="text-sm font-bold text-gray-800">Purchase Orders</h3>
                <p class="text-[10px] text-gray-400 mt-0.5">
                    Use the <i class="fas fa-filter text-[9px]"></i> icons in the table header to search &amp; filter.
                </p>
            </div>
            <div class="flex items-center gap-2" data-live-region="toolbar">
                @if($hasFilters)
                    <a href="{{ route('general.purchase-order.index') }}"
                       class="px-3 py-2 bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 text-xs font-semibold rounded-lg shadow-sm flex items-center gap-1.5 whitespace-nowrap">
                        <i class="fas fa-rotate-left text-[10px]"></i> Reset Filter
                    </a>
                @endif
                @if($canCreate)
                    <a href="{{ route('general.purchase-order.create') }}"
                       class="inline-flex items-center gap-1.5 px-4 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 shadow-sm whitespace-nowrap">
                        <i class="fas fa-plus text-xs"></i> New Purchase Order
                    </a>
                @endif
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left">
                <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider text-[10px] font-bold border-b border-gray-200 select-none">
                    <tr>
                        <th class="px-4 py-3 w-12">No.</th>
                        @include($filter, ['form' => $filterForm, 'name' => 'number', 'label' => 'Document No.', 'type' => 'search', 'value' => $filters['number'], 'placeholder' => 'e.g. 01/PO/10/2026', 'thClass' => 'min-w-40'])
                        <th class="px-4 py-3">PR Base</th>
                        @include($filter, ['form' => $filterForm, 'name' => 'date', 'label' => 'Date', 'type' => 'daterange', 'value' => ['from' => $filters['date_from'], 'to' => $filters['date_to']], 'thClass' => 'min-w-32'])
                        <th class="px-4 py-3">Requester</th>
                        @include($filter, ['form' => $filterForm, 'name' => 'supplier', 'label' => 'Supplier', 'type' => 'search', 'value' => $filters['supplier'], 'placeholder' => 'Supplier name…', 'thClass' => 'min-w-44'])
                        <th class="px-4 py-3">Invoice No.</th>
                        <th class="px-4 py-3 text-right">Total</th>
                        @include($filter, ['form' => $filterForm, 'name' => 'status', 'label' => 'Status', 'type' => 'options', 'value' => $filters['status'], 'options' => $statusOptions, 'default' => 'all', 'allLabel' => 'All', 'thClass' => 'min-w-28'])
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100 text-gray-700" data-live-region="rows">
                    @forelse($orders as $i => $order)
                    @php
                        $tone = ['approved' => 'text-green-700', 'rejected' => 'text-red-700'][$order->status] ?? 'text-blue-700';
                        $mine = $order->isSubmitted() && (int) $order->approver_id === (int) session('user.id');
                    @endphp
                    <tr class="hover:bg-gray-50 transition-colors {{ $order->trashed() ? 'opacity-60' : '' }}">
                        <td class="px-4 py-3 text-gray-400">{{ $orders->firstItem() + $i }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <a href="{{ route('general.purchase-order.show', $order) }}" class="font-mono text-xs text-red-800 font-semibold hover:underline">{{ $order->po_no }}</a>
                            @if($mine)
                                <span class="block mt-1 px-1.5 py-0.5 bg-red-800 text-white text-[10px] font-bold rounded uppercase tracking-wide text-center">Your turn</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-500">{{ $order->purchaseRequest?->request_no ?? '—' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $order->po_date->format('d M Y') }}</td>
                        <td class="px-4 py-3">{{ $order->requester?->fullNameLabel() ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $order->supplier_name }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $order->invoice_no ?: '—' }}</td>
                        <td class="px-4 py-3 text-right font-semibold text-gray-900 whitespace-nowrap">{{ PurchaseOrder::money($order->grand_total) }}</td>
                        <td class="px-4 py-3 font-semibold {{ $tone }}">{{ $order->statusLabel() }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1.5">
                                @include($action, ['icon' => 'eye', 'tone' => $mine ? 'green' : 'gray', 'label' => $mine ? 'Open to approve or reject' : 'View details', 'href' => route('general.purchase-order.show', $order)])
                                @if($order->isSubmitted() && !$order->trashed() && ($canManage || (int) $order->created_by === (int) session('user.id')))
                                    @include($action, ['icon' => 'pen', 'tone' => 'blue', 'label' => 'Edit', 'href' => route('general.purchase-order.edit', $order)])
                                @endif
                                @include($action, ['icon' => 'print', 'tone' => 'gray', 'label' => 'Print', 'newTab' => true, 'href' => route('general.purchase-order.print', $order)])
                                @if($canManage && !$order->trashed())
                                    @include($action, ['icon' => 'trash', 'tone' => 'red', 'label' => 'Delete — opens the document', 'href' => route('general.purchase-order.show', $order)])
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="px-4 py-12 text-center text-gray-400">
                            {{ $hasFilters ? 'No purchase order matches these filters.' : 'No purchase order yet.' }}
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div data-live-region="pagination">
            @include('hr-general.recruitment.components.pagination', ['paginator' => $orders, 'form' => $filterForm])
        </div>
    </div>
</div>
@endsection

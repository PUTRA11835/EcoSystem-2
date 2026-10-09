@extends('dashboard')
@section('title', 'Inventory')
@section('page-title', 'Inventory & Assets')
@section('page-subtitle', 'Office inventory and consumables, kept as stock quantities.')

@php
    // Capabilities of this tab, as ticked in Management → Roles.
    $canCreate = $canDo('general.inventory.items', 'create');
    $canEdit   = $canDo('general.inventory.items', 'edit');
    $canDelete = $canDo('general.inventory.items', 'delete');

    $filter = 'hr-general.recruitment.components.header-filter';
    $action = 'hr-general.recruitment.components.icon-action';
    $money = fn ($v) => \App\Models\InventoryItem::money($v);

    // Sort links (only on name, number and date columns) keep every other filter; clicking a sorted column again flips it, a third click clears the sort.
    $sortArgs = function (string $column) use ($filters) {
        $active = $filters['sort'] === $column;
        $next = !$active ? 'asc' : ($filters['dir'] === 'asc' ? 'desc' : null);
        $query = request()->except(['page', 'sort', 'dir']) + ($next ? ['sort' => $column, 'dir' => $next] : []);

        return ['sortUrl' => url()->current() . ($query ? '?' . http_build_query($query) : ''), 'sortMark' => $active ? ($filters['dir'] === 'asc' ? '↑' : '↓') : '⇅'];
    };
    $range = fn (string $key) => ['min' => $filters[$key . '_min'], 'max' => $filters[$key . '_max']];
@endphp

@section('content')
<div class="w-full space-y-6 px-1 lg:px-2">
    @include('hr-general.inventory.components.tabs')

    {{-- Every header filter and the rows-per-page choice belong to this form through their `form` attribute; the sort is kept here too. --}}
    <form id="{{ $filterForm }}" method="GET" action="{{ route('general.inventory.items.index') }}" data-filter-form class="hidden">
        <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
        <input type="hidden" name="dir" value="{{ $filters['dir'] }}">
    </form>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-3.5 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h3 class="text-sm font-bold text-gray-800">Inventory</h3>
                <p class="text-[10px] text-gray-400 mt-0.5">Use the <i class="fas fa-filter text-[9px]"></i> icon in a column header to search or filter; names, numbers and dates can also be sorted ↑ ↓.</p>
            </div>
            <div class="flex items-center gap-2" data-live-region="toolbar">
                @if($hasFilters)
                    <a href="{{ route('general.inventory.items.index') }}"
                        class="px-3 py-2 bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-gray-800 text-xs font-semibold rounded-lg shadow-sm transition-colors flex items-center gap-1.5 whitespace-nowrap">
                        <i class="fas fa-rotate-left text-[10px]"></i> Reset Filter
                    </a>
                @endif
                @if($canCreate)
                    <button type="button" onclick="invOpen('inventoryItemModal')"
                        class="inline-flex items-center gap-1.5 px-4 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-all shadow-sm whitespace-nowrap">
                        <i class="fas fa-plus text-xs"></i> New Inventory Item
                    </button>
                @endif
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left">
                <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider text-[10px] font-bold border-b border-gray-200 select-none">
                    <tr>
                        <th class="px-4 py-3 w-12">No.</th>
                        @include($filter, [
                            'form' => $filterForm, 'name' => 'search', 'label' => 'Item', 'type' => 'search',
                            'value' => $filters['search'], 'placeholder' => 'Name or code…', 'thClass' => 'min-w-52',
                        ] + $sortArgs('name'))
                        @include($filter, [
                            'form' => $filterForm, 'name' => 'category', 'label' => 'Category', 'type' => 'options',
                            'value' => $filters['category'], 'options' => $categories, 'allLabel' => 'All categories', 'thClass' => 'min-w-36',
                        ])
                        @include($filter, [
                            'form' => $filterForm, 'name' => 'qty', 'label' => 'Qty', 'type' => 'range', 'align' => 'right',
                            'value' => $range('qty'), 'note' => 'Quantity in stock.', 'thClass' => 'min-w-28',
                        ] + $sortArgs('quantity'))
                        @include($filter, [
                            'form' => $filterForm, 'name' => 'min_stock', 'label' => 'Min. stock', 'type' => 'range', 'align' => 'right',
                            'value' => $range('min_stock'), 'thClass' => 'min-w-32',
                        ] + $sortArgs('min_stock'))
                        @include($filter, [
                            'form' => $filterForm, 'name' => 'price', 'label' => 'Unit price', 'type' => 'range', 'align' => 'right',
                            'value' => $range('price'), 'note' => 'In rupiah.', 'thClass' => 'min-w-32',
                        ] + $sortArgs('unit_price'))
                        @include($filter, [
                            'form' => $filterForm, 'name' => 'value', 'label' => 'Stock value', 'type' => 'range', 'align' => 'right',
                            'value' => $range('value'), 'note' => 'Unit price × qty, in rupiah.', 'thClass' => 'min-w-36',
                        ] + $sortArgs('stock_value'))
                        @include($filter, [
                            'form' => $filterForm, 'name' => 'location', 'label' => 'Location', 'type' => 'options',
                            'value' => $filters['location'], 'options' => $locations, 'allLabel' => 'All locations', 'thClass' => 'min-w-36',
                        ])
                        @include($filter, [
                            'form' => $filterForm, 'name' => 'status', 'label' => 'Status', 'type' => 'options',
                            'value' => $filters['status'], 'options' => \App\Models\InventoryItem::STATUSES, 'allLabel' => 'All statuses', 'thClass' => 'min-w-28',
                        ])
                        {{-- Pinned, so the actions stay reachable while the wide table scrolls sideways. --}}
                        <th class="px-4 py-3 text-center sticky right-0 bg-gray-50 border-l border-gray-200">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700" data-live-region="rows">
                    @forelse($rows as $item)
                        @php
                            $status = $item->status();
                            $payload = [
                                '_id' => $item->id, 'code' => $item->code, 'name' => $item->name, 'category' => $item->category, 'unit' => $item->unit,
                                'quantity' => $item->quantity, 'min_stock' => $item->min_stock, 'unit_price' => (int) $item->unit_price,
                                'location' => $item->location, 'status_override' => $item->status_override, 'notes' => $item->notes,
                                'photo_url' => $item->photo_path ? route('general.inventory.items.photo', $item) : '',
                            ];
                        @endphp
                        <tr class="align-top">
                            <td class="px-4 py-3 text-gray-400">{{ $rows->firstItem() + $loop->index }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    @if($item->photo_path)
                                        <img src="{{ route('general.inventory.items.photo', $item) }}" alt="" loading="lazy" class="w-9 h-9 rounded-lg object-cover border border-gray-200 shrink-0">
                                    @endif
                                    <div>
                                        <span class="font-semibold text-gray-800">{{ $item->name }}</span>
                                        <span class="block text-[10px] text-gray-400 font-mono">{{ $item->code }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">{{ $item->category }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">{{ number_format($item->quantity) }} <span class="text-gray-400">{{ $item->unit }}</span></td>
                            <td class="px-4 py-3 text-right">{{ number_format($item->min_stock) }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">{{ $money($item->unit_price) }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">{{ $money($item->stockValue()) }}</td>
                            <td class="px-4 py-3">{{ $item->location ?: '-' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ \App\Models\InventoryItem::STATUS_BADGES[$status] }}">{{ \App\Models\InventoryItem::STATUSES[$status] }}</span>
                            </td>
                            <td class="px-4 py-3 sticky right-0 bg-white border-l border-gray-100">
                                <div class="flex items-center justify-center gap-1.5">
                                    @if($canEdit)
                                        <button type="button" title="Edit" aria-label="Edit" onclick="invEdit('inventoryItemModal', this)"
                                            data-payload="{{ json_encode($payload) }}"
                                            class="w-8 h-8 inline-flex items-center justify-center rounded-lg border bg-white transition-all shrink-0 border-blue-200 text-blue-600 hover:bg-blue-50"><i class="fas fa-pen text-xs"></i></button>
                                    @endif
                                    @if($canDelete)
                                        @include($action, [
                                            'icon' => 'trash', 'tone' => 'red', 'label' => 'Delete', 'post' => route('general.inventory.items.destroy', $item),
                                            'confirm' => 'Delete ' . $item->name . ' (' . $item->code . ')? This cannot be undone.',
                                            'confirmTitle' => 'Delete Inventory Item',
                                        ])
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-5 py-10 text-center text-gray-400">
                                {{ $hasFilters ? 'No inventory items match these filters.' : 'No inventory items yet.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Numbered pages + "Rows per page" (10 · 15 · 25 · 50), the same footer as the other HR tables. --}}
        <div data-live-region="pagination">
            @include('hr-general.recruitment.components.pagination', ['paginator' => $rows, 'form' => $filterForm])
        </div>
    </div>
</div>

@include('hr-general.recruitment.components.confirm-forms')
@if($canCreate || $canEdit)
    @include('hr-general.inventory.components.modal-helpers')
    @include('hr-general.inventory.components.item-modal', ['choices' => $choices])
@endif
@endsection

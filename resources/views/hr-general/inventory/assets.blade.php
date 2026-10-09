@extends('dashboard')
@section('title', 'Assets')
@section('page-title', 'Inventory & Assets')
@section('page-subtitle', 'Company assets, tracked one unit at a time.')

@php
    // Capabilities of this tab, as ticked in Management → Roles.
    $canCreate = $canDo('general.inventory.assets', 'create');
    $canEdit   = $canDo('general.inventory.assets', 'edit');
    $canDelete = $canDo('general.inventory.assets', 'delete');

    $filter = 'hr-general.recruitment.components.header-filter';
    $action = 'hr-general.recruitment.components.icon-action';

    // Sort links (only on name, number and date columns) keep every other filter; clicking a sorted column again flips it, a third click clears the sort.
    $sortArgs = function (string $column) use ($filters) {
        $active = $filters['sort'] === $column;
        $next = !$active ? 'asc' : ($filters['dir'] === 'asc' ? 'desc' : null);
        $query = request()->except(['page', 'sort', 'dir']) + ($next ? ['sort' => $column, 'dir' => $next] : []);

        return ['sortUrl' => url()->current() . ($query ? '?' . http_build_query($query) : ''), 'sortMark' => $active ? ($filters['dir'] === 'asc' ? '↑' : '↓') : '⇅'];
    };
@endphp

@section('content')
<div class="w-full space-y-6 px-1 lg:px-2">
    @include('hr-general.inventory.components.tabs')

    {{-- Every header filter and the rows-per-page choice belong to this form through their `form` attribute; the sort is kept here too. --}}
    <form id="{{ $filterForm }}" method="GET" action="{{ route('general.inventory.assets.index') }}" data-filter-form class="hidden">
        <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
        <input type="hidden" name="dir" value="{{ $filters['dir'] }}">
    </form>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-3.5 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h3 class="text-sm font-bold text-gray-800">Assets</h3>
                <p class="text-[10px] text-gray-400 mt-0.5">Use the <i class="fas fa-filter text-[9px]"></i> icon in a column header to search or filter; names, numbers and dates can also be sorted ↑ ↓.</p>
            </div>
            <div class="flex items-center gap-2" data-live-region="toolbar">
                @if($hasFilters)
                    <a href="{{ route('general.inventory.assets.index') }}"
                        class="px-3 py-2 bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-gray-800 text-xs font-semibold rounded-lg shadow-sm transition-colors flex items-center gap-1.5 whitespace-nowrap">
                        <i class="fas fa-rotate-left text-[10px]"></i> Reset Filter
                    </a>
                @endif
                @if($canCreate)
                    <button type="button" onclick="invOpen('inventoryAssetModal')"
                        class="inline-flex items-center gap-1.5 px-4 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-all shadow-sm whitespace-nowrap">
                        <i class="fas fa-plus text-xs"></i> New Asset
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
                            'form' => $filterForm, 'name' => 'search', 'label' => 'Asset', 'type' => 'search',
                            'value' => $filters['search'], 'placeholder' => 'Name, code, serial or brand…', 'thClass' => 'min-w-52',
                        ] + $sortArgs('name'))
                        @include($filter, [
                            'form' => $filterForm, 'name' => 'category', 'label' => 'Category', 'type' => 'options',
                            'value' => $filters['category'], 'options' => $categories, 'allLabel' => 'All categories', 'thClass' => 'min-w-36',
                        ])
                        @include($filter, [
                            'form' => $filterForm, 'name' => 'assignee', 'label' => 'Assignee', 'type' => 'search',
                            'value' => $filters['assignee'], 'placeholder' => 'Employee name…', 'thClass' => 'min-w-40',
                        ])
                        @include($filter, [
                            'form' => $filterForm, 'name' => 'date', 'label' => 'Purchase date', 'type' => 'daterange',
                            'value' => ['from' => $filters['date_from'], 'to' => $filters['date_to']], 'thClass' => 'min-w-36',
                        ] + $sortArgs('purchase_date'))
                        @include($filter, [
                            'form' => $filterForm, 'name' => 'price', 'label' => 'Purchase price', 'type' => 'range', 'align' => 'right',
                            'value' => ['min' => $filters['price_min'], 'max' => $filters['price_max']], 'note' => 'In rupiah.', 'thClass' => 'min-w-40',
                        ] + $sortArgs('purchase_price'))
                        @include($filter, [
                            'form' => $filterForm, 'name' => 'location', 'label' => 'Location', 'type' => 'options',
                            'value' => $filters['location'], 'options' => $locations, 'allLabel' => 'All locations', 'thClass' => 'min-w-36',
                        ])
                        @include($filter, [
                            'form' => $filterForm, 'name' => 'condition', 'label' => 'Condition', 'type' => 'options',
                            'value' => $filters['condition'], 'options' => $conditions, 'allLabel' => 'All conditions', 'thClass' => 'min-w-32',
                        ])
                        @include($filter, [
                            'form' => $filterForm, 'name' => 'status', 'label' => 'Status', 'type' => 'options',
                            'value' => $filters['status'], 'options' => $statuses, 'allLabel' => 'All statuses', 'thClass' => 'min-w-28',
                        ])
                        {{-- Pinned, so the actions stay reachable while the wide table scrolls sideways. --}}
                        <th class="px-4 py-3 text-center sticky right-0 bg-gray-50 border-l border-gray-200">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700" data-live-region="rows">
                    @forelse($rows as $asset)
                        @php
                            $payload = [
                                '_id' => $asset->id, 'code' => $asset->code, 'name' => $asset->name, 'category' => $asset->category, 'brand' => $asset->brand,
                                'serial_number' => $asset->serial_number, 'assignee_employee_id' => $asset->assignee_employee_id,
                                'purchase_date' => $asset->purchase_date?->format('Y-m-d'), 'purchase_price' => (int) $asset->purchase_price,
                                'condition' => $asset->condition, 'status' => $asset->status, 'location' => $asset->location, 'notes' => $asset->notes,
                                'photo_url' => $asset->photo_path ? route('general.inventory.assets.photo', $asset) : '',
                            ];
                        @endphp
                        <tr class="align-top">
                            <td class="px-4 py-3 text-gray-400">{{ $rows->firstItem() + $loop->index }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    @if($asset->photo_path)
                                        <img src="{{ route('general.inventory.assets.photo', $asset) }}" alt="" loading="lazy" class="w-9 h-9 rounded-lg object-cover border border-gray-200 shrink-0">
                                    @endif
                                    <div>
                                        <span class="font-semibold text-gray-800">{{ $asset->name }}</span>
                                        <span class="block text-[10px] text-gray-400"><span class="font-mono">{{ $asset->code }}</span>@if($asset->brand) • {{ $asset->brand }}@endif @if($asset->serial_number) • S/N {{ $asset->serial_number }}@endif</span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">{{ $asset->category }}</td>
                            <td class="px-4 py-3">{{ $asset->assignee_name ?: '-' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $asset->purchase_date?->format('d M Y') ?? '-' }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">{{ \App\Models\InventoryItem::money($asset->purchase_price) }}</td>
                            <td class="px-4 py-3">{{ $asset->location ?: '-' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ \App\Models\InventoryOption::badge('asset_condition', $asset->condition) }}">{{ $conditions[$asset->condition] ?? $asset->condition }}</span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ \App\Models\InventoryOption::badge('asset_status', $asset->status) }}">{{ $statuses[$asset->status] ?? $asset->status }}</span>
                            </td>
                            <td class="px-4 py-3 sticky right-0 bg-white border-l border-gray-100">
                                <div class="flex items-center justify-center gap-1.5">
                                    @if($canEdit)
                                        <button type="button" title="Edit" aria-label="Edit" onclick="invEdit('inventoryAssetModal', this)"
                                            data-payload="{{ json_encode($payload) }}"
                                            class="w-8 h-8 inline-flex items-center justify-center rounded-lg border bg-white transition-all shrink-0 border-blue-200 text-blue-600 hover:bg-blue-50"><i class="fas fa-pen text-xs"></i></button>
                                    @endif
                                    @if($canDelete)
                                        @include($action, [
                                            'icon' => 'trash', 'tone' => 'red', 'label' => 'Delete', 'post' => route('general.inventory.assets.destroy', $asset),
                                            'confirm' => 'Delete ' . $asset->name . ' (' . $asset->code . ')? This cannot be undone.',
                                            'confirmTitle' => 'Delete Asset',
                                        ])
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-5 py-10 text-center text-gray-400">
                                {{ $hasFilters ? 'No assets match these filters.' : 'No assets yet.' }}
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
{{-- With the calendar: the "Purchase date" filter needs it even where nothing can be edited. --}}
@include('hr-general.inventory.components.modal-helpers', ['dates' => true])
@if($canCreate || $canEdit)
    @include('hr-general.inventory.components.asset-modal', ['choices' => $choices, 'employees' => $employees])
@endif
@endsection

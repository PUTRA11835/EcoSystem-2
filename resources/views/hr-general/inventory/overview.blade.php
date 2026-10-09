@extends('dashboard')
@section('title', 'Inventory & Assets')
@section('page-title', 'Inventory & Assets')
@section('page-subtitle', 'A recap of what the company holds: office inventory in stock, and tracked assets.')

@php
    $money = fn ($v) => \App\Models\InventoryItem::money($v);
    $issueLabel = ['low_stock' => 'Low stock', 'out_of_stock' => 'Out of stock', 'damaged' => 'Damaged', 'maintenance' => 'Under maintenance'];
    $issueTone = ['low_stock' => 'bg-amber-100 text-amber-700', 'out_of_stock' => 'bg-red-100 text-red-700', 'damaged' => 'bg-red-100 text-red-700', 'maintenance' => 'bg-amber-100 text-amber-700'];
    $maxStock = max(1, (float) $stockByCategory->max('total_value'));
    $maxAsset = max(1, (float) $assetsByCategory->max('total_value'));
    $assetTotal = max(1, (int) $assetsByStatus->sum());
@endphp

@section('content')
<div class="w-full space-y-6 px-1 lg:px-2">
    @include('hr-general.inventory.components.tabs')

    {{-- Inactive inventory lines and disposed assets are left out of every figure on this page. --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        @foreach([
            ['Inventory lines', number_format($summary['lines']), 'boxes-stacked', 'primary-text'],
            ['Stock value', $money($summary['stock_value']), 'coins', 'text-green-600'],
            ['Assets', number_format($summary['assets']), 'laptop', 'primary-text'],
            ['Asset value (purchase)', $money($summary['asset_value']), 'coins', 'text-green-600'],
        ] as [$label, $figure, $icon, $tone])
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3 flex items-center gap-3">
                <span class="w-9 h-9 rounded-lg bg-gray-50 flex items-center justify-center {{ $tone }}"><i class="fas fa-{{ $icon }}"></i></span>
                <div>
                    <p class="text-lg font-bold text-gray-800 leading-tight">{{ $figure }}</p>
                    <p class="text-[11px] text-gray-500">{{ $label }}</p>
                </div>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5 items-start">
        <div class="xl:col-span-2 space-y-5">
            {{-- Inventory by category --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100">
                    <h3 class="text-sm font-bold text-gray-800">Inventory by category</h3>
                    <p class="text-[10px] text-gray-400 mt-0.5">Value = unit price × quantity in stock.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-gray-50 text-[10px] uppercase tracking-wider text-gray-500">
                            <tr><th class="px-4 py-3">Category</th><th class="px-4 py-3 text-right">Lines</th><th class="px-4 py-3 text-right">Units</th><th class="px-4 py-3 min-w-48">Value</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-gray-700">
                            @forelse($stockByCategory as $row)
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-gray-800">{{ $row->category }}</td>
                                    <td class="px-4 py-3 text-right">{{ number_format($row->line_count) }}</td>
                                    <td class="px-4 py-3 text-right">{{ number_format($row->units) }}</td>
                                    <td class="px-4 py-3">
                                        <span class="block">{{ $money($row->total_value) }}</span>
                                        <span class="block h-1.5 mt-1 rounded-full bg-gray-100"><span class="block h-1.5 rounded-full primary-gradient" style="width: {{ round($row->total_value / $maxStock * 100) }}%"></span></span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-10 text-center text-gray-400">No inventory recorded yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Assets by category --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100">
                    <h3 class="text-sm font-bold text-gray-800">Assets by category</h3>
                    <p class="text-[10px] text-gray-400 mt-0.5">Value = what was paid for the units.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-gray-50 text-[10px] uppercase tracking-wider text-gray-500">
                            <tr><th class="px-4 py-3">Category</th><th class="px-4 py-3 text-right">Units</th><th class="px-4 py-3 min-w-48">Purchase value</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-gray-700">
                            @forelse($assetsByCategory as $row)
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-gray-800">{{ $row->category }}</td>
                                    <td class="px-4 py-3 text-right">{{ number_format($row->unit_count) }}</td>
                                    <td class="px-4 py-3">
                                        <span class="block">{{ $money($row->total_value) }}</span>
                                        <span class="block h-1.5 mt-1 rounded-full bg-gray-100"><span class="block h-1.5 rounded-full primary-gradient" style="width: {{ round($row->total_value / $maxAsset * 100) }}%"></span></span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-4 py-10 text-center text-gray-400">No assets recorded yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="space-y-5">
            {{-- Needs attention --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100">
                    <h3 class="text-sm font-bold text-gray-800">Needs attention</h3>
                    <p class="text-[10px] text-gray-400 mt-0.5">Low or empty stock, damaged assets and assets under maintenance.</p>
                </div>
                <ul class="divide-y divide-gray-100 text-xs">
                    @forelse($attention as $row)
                        <li class="px-5 py-2.5 flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <span class="font-semibold text-gray-800 truncate block">{{ $row['name'] }}</span>
                                <span class="text-[11px] text-gray-400">{{ $row['code'] }}@if($row['detail']) • {{ $row['detail'] }}@endif</span>
                            </div>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold shrink-0 {{ $issueTone[$row['issue']] }}">{{ $issueLabel[$row['issue']] }}</span>
                        </li>
                    @empty
                        <li class="px-5 py-8 text-center text-gray-400"><i class="fas fa-circle-check text-lg mb-2 block text-green-500"></i>All good.</li>
                    @endforelse
                </ul>
            </div>

            {{-- Asset status --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100"><h3 class="text-sm font-bold text-gray-800">Assets by status</h3></div>
                <ul class="px-5 py-3 space-y-3 text-xs">
                    @foreach($statusLabels as $key => $text)
                        @php $count = (int) ($assetsByStatus[$key] ?? 0); @endphp
                        <li>
                            <div class="flex items-center justify-between"><span>{{ $text }}</span><span class="font-semibold text-gray-800">{{ number_format($count) }}</span></div>
                            <span class="block h-1.5 mt-1 rounded-full bg-gray-100"><span class="block h-1.5 rounded-full primary-gradient" style="width: {{ round($count / $assetTotal * 100) }}%"></span></span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection

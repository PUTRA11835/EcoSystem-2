@extends('dashboard')
@section('title', 'Employee Contract')
@section('page-title', 'Employee Contract')
@section('page-subtitle', 'Whose contract ends soon, and every contract in one filterable list')

@php
    $routeIndex = route('management.employee.contract.index');
    $canManage  = $can('general.contracts.list');
    $badge = [
        'active' => 'bg-green-100 text-green-700', 'draft' => 'bg-gray-200 text-gray-600', 'expired' => 'bg-red-100 text-red-700',
        'terminated' => 'bg-red-100 text-red-700', 'inactive' => 'bg-gray-100 text-gray-500', 'none' => 'bg-gray-100 text-gray-400',
    ];
    $typeBadge = ['PKWT' => 'bg-indigo-100 text-indigo-700', 'PKWTT' => 'bg-blue-100 text-blue-700', 'EXTERNAL' => 'bg-amber-100 text-amber-700'];
    $date = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->format('d M Y') : '—';
    $tabClass = fn (bool $on) => 'flex-1 sm:flex-none px-4 py-2.5 text-sm font-medium text-center rounded-lg transition-all '
        . ($on ? 'primary-gradient text-white shadow-sm' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100');
@endphp

@section('content')
<div class="w-full space-y-6 px-1 lg:px-2">

    {{-- ── Tabs: Dashboard | Contracts ─────────────────────────────────────── --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <nav class="flex flex-wrap gap-1 p-1" aria-label="Tabs">
            <a href="{{ $routeIndex }}" class="{{ $tabClass($tab === 'dashboard') }}"><i class="fas fa-chart-pie mr-2"></i>Dashboard</a>
            <a href="{{ $routeIndex }}?tab=list" class="{{ $tabClass($tab === 'list') }}"><i class="fas fa-file-signature mr-2"></i>Contracts</a>
        </nav>
    </div>

@if($tab === 'dashboard')
    {{-- ══════════════════════════ DASHBOARD ══════════════════════════ --}}
    @include('hr-general.contracts.components.dashboard', [
        'dash' => $dash, 'dashUrl' => $routeIndex, 'listUrl' => $routeIndex . '?tab=list',
    ])

@else
    {{-- ══════════════════════════ CONTRACT LIST ══════════════════════════ --}}
    @php
        $filterForm = 'contractFilters';
        $filter = 'hr-general.recruitment.components.header-filter';
        $hasFilters = collect($filters)->contains(fn ($v) => $v !== '');
    @endphp

    {{-- Every header filter and the rows-per-page choice belong to this form through their `form` attribute. --}}
    <form id="{{ $filterForm }}" method="GET" action="{{ $routeIndex }}" data-filter-form class="hidden">
        <input type="hidden" name="tab" value="list">
    </form>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-3.5 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h3 class="text-sm font-bold text-gray-800">All contracts</h3>
                <p class="text-[10px] text-gray-400 mt-0.5">Use the <i class="fas fa-filter text-[9px]"></i> icons in the table header to search &amp; filter each column.</p>
            </div>
            <div class="flex items-center gap-2" data-live-region="toolbar">
                @if($hasFilters)
                    <a href="{{ $routeIndex }}?tab=list"
                        class="px-3 py-2 bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-gray-800 text-xs font-semibold rounded-lg shadow-sm transition-colors flex items-center gap-1.5 whitespace-nowrap">
                        <i class="fas fa-rotate-left text-[10px]"></i> Reset Filter
                    </a>
                @endif
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left">
                <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider text-[10px] font-bold border-b border-gray-200 select-none">
                    <tr>
                        <th class="px-4 py-3 w-12">No.</th>
                        @include($filter, [
                            'form' => $filterForm, 'name' => 'employee', 'label' => 'Employee', 'type' => 'search',
                            'value' => $filters['employee'], 'placeholder' => 'Name or ECI…', 'thClass' => 'min-w-48',
                        ])
                        @include($filter, [
                            'form' => $filterForm, 'name' => 'number', 'label' => 'Contract No.', 'type' => 'search',
                            'value' => $filters['number'], 'placeholder' => 'Type a contract number…', 'thClass' => 'min-w-40',
                        ])
                        @include($filter, [
                            'form' => $filterForm, 'name' => 'type', 'label' => 'Type', 'type' => 'options',
                            'value' => $filters['type'], 'options' => $types, 'allLabel' => 'All types', 'thClass' => 'min-w-32',
                        ])
                        @include($filter, [
                            'form' => $filterForm, 'name' => 'department', 'label' => 'Department', 'type' => 'options',
                            'value' => $filters['department'], 'options' => $departments, 'allLabel' => 'All departments', 'thClass' => 'min-w-40',
                        ])
                        <th class="px-4 py-3 min-w-28">Start</th>
                        @include($filter, [
                            'form' => $filterForm, 'name' => 'end', 'label' => 'End', 'type' => 'daterange',
                            'value' => ['from' => $filters['end_from'], 'to' => $filters['end_to']], 'thClass' => 'min-w-32',
                            'note' => 'Contracts ending between these dates.',
                        ])
                        @include($filter, [
                            'form' => $filterForm, 'name' => 'status', 'label' => 'Status', 'type' => 'options',
                            'value' => $filters['status'], 'options' => $statuses, 'allLabel' => 'All statuses', 'thClass' => 'min-w-32',
                        ])
                        <th class="px-4 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700" data-live-region="rows">
                    @forelse($rows as $row)
                        <tr class="hover:bg-gray-50 align-top">
                            <td class="px-4 py-3 text-gray-400">{{ $rows->firstItem() + $loop->index }}</td>
                            <td class="px-4 py-3">
                                <a href="{{ route('master.employee.detail', $row->employee_id) }}" class="font-semibold text-gray-800 hover:underline">{{ $row->name }}</a>
                                <span class="block text-[11px] text-gray-400">{{ $row->eci }} • {{ $row->position ?: '—' }}</span>
                            </td>
                            <td class="px-4 py-3 font-medium text-gray-800">{{ $row->contract_number ?: '—' }}</td>
                            <td class="px-4 py-3">
                                @if($row->has_contract)
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $typeBadge[$row->type] ?? 'bg-gray-100 text-gray-600' }}">{{ $row->type ?? $row->contract_type }}</span>
                                @else <span class="text-gray-300">—</span> @endif
                            </td>
                            <td class="px-4 py-3">{{ $row->employee_department ?: '—' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $date($row->start_date) }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ $row->has_contract ? ($row->end_date ? $date($row->end_date) : 'Permanent') : '—' }}
                                @if($row->ending_soon)<span class="block text-[10px] text-amber-600"><i class="fas fa-hourglass-half"></i> Ending soon</span>@endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $badge[$row->effective_status] ?? 'bg-gray-100 text-gray-500' }}">{{ $statuses[$row->effective_status] ?? $row->effective_status }}</span>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <a href="{{ route('master.employee.detail', $row->employee_id) }}" class="text-xs font-semibold primary-text hover:underline">Profile</a>
                                @if($canManage)
                                    <span class="text-gray-300 mx-1">·</span>
                                    <a href="{{ route('general.contracts.show', $row->employee_id) }}" class="text-xs font-semibold primary-text hover:underline">Manage</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-4 py-12 text-center text-gray-400">
                            <i class="fas fa-magnifying-glass text-lg mb-2 block"></i>{{ $hasFilters ? 'No contract matches these filters.' : 'No contract recorded yet.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div data-live-region="pagination">
            @include('hr-general.recruitment.components.pagination', [
                'paginator' => $rows, 'form' => $filterForm, 'perPage' => $perPage, 'perPageOptions' => $perPageOptions,
            ])
        </div>
    </div>

    {{-- The calendar of the End-date filter. --}}
    @include('hr-general.inventory.components.modal-helpers', ['dates' => true])
@endif

</div>
@endsection

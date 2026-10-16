@extends('dashboard')
@section('title', 'Contracts')
@section('page-title', 'Contract')
@section('page-subtitle', 'Fixed-term, indefinite-term, internship and addendum contracts of internal employees. External consultants have their own External Contract menu.')

@php
    $filter = 'hr-general.recruitment.components.header-filter';
    $hasFilters = collect($filters)->contains(fn ($v) => $v !== '');
    $badge = [
        'active' => 'bg-green-100 text-green-700', 'draft' => 'bg-gray-200 text-gray-600', 'expired' => 'bg-red-100 text-red-700',
        'terminated' => 'bg-red-100 text-red-700', 'inactive' => 'bg-gray-100 text-gray-500', 'none' => 'bg-gray-100 text-gray-400',
    ];
    $typeBadge = ['PKWT' => 'tone-primary', 'PKWTT' => 'tone-primary-strong', 'INTERNSHIP' => 'bg-sky-100 text-sky-700', 'ADDENDUM' => 'bg-purple-100 text-purple-700', 'EXTERNAL' => 'bg-amber-100 text-amber-700'];
    $canSalary = $canDo('general.contracts.salary');
    $canEdit = $canDo('general.contracts.list', 'edit');
@endphp

@section('content')
<div class="w-full space-y-6 px-1 lg:px-2">
    @include('hr-general.contracts.components.tabs')
    @include('hr-general.recruitment.components.form-errors')

    {{-- Every header filter and the rows-per-page choice belong to this form through their `form` attribute. --}}
    <form id="{{ $filterForm }}" method="GET" action="{{ route('general.contracts.list') }}" data-filter-form class="hidden"></form>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-3.5 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h3 class="text-sm font-bold text-gray-800">All contracts</h3>
                <p class="text-[10px] text-gray-400 mt-0.5">Use the <i class="fas fa-filter text-[9px]"></i> icons in the table header to search &amp; filter each column.
                    @if($canExternal)Consultant contracts: <a href="{{ route('general.external.contracts.index') }}" class="underline font-semibold">External Contract</a>.@endif</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @include('hr-general.partials.data-transfer', ['dataset' => 'contracts.list', 'bare' => true])
                <div class="flex items-center gap-2" data-live-region="toolbar">
                    @if($hasFilters)
                        <a href="{{ route('general.contracts.list') }}"
                            class="px-3 py-2 bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-gray-800 text-xs font-semibold rounded-lg shadow-sm transition-colors flex items-center gap-1.5 whitespace-nowrap">
                            <i class="fas fa-rotate-left text-[10px]"></i> Reset Filter
                        </a>
                    @endif
                </div>
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
                                <a href="{{ route('general.contracts.show', $row->employee_id) }}" class="font-semibold text-gray-800 hover:underline">{{ $row->name }}</a>
                                <span class="block text-[11px] text-gray-400">{{ $row->eci }} • {{ $row->position ?: '-' }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="font-medium text-gray-800">{{ $row->contract_number ?: '-' }}</span>@if($row->has_signed)<i class="fas fa-stamp ml-1 primary-text" title="Stamped copy on file"></i>@endif
                            </td>
                            <td class="px-4 py-3">
                                @if($row->has_contract)
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $typeBadge[$row->type] ?? 'bg-gray-100 text-gray-600' }}">{{ $kindLabels[$row->contract_kind] ?? ($row->type ?? $row->contract_type) }}</span>
                                @else <span class="text-gray-300">-</span> @endif
                            </td>
                            <td class="px-4 py-3">{{ $row->employee_department ?: '-' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ $row->has_contract && $row->start_date ? \Illuminate\Support\Carbon::parse($row->start_date)->format('d M Y') : '-' }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if($row->has_contract)
                                    {{ $row->end_date ? \Illuminate\Support\Carbon::parse($row->end_date)->format('d M Y') : 'Permanent' }}
                                    @if($row->ending_soon)<span class="block text-[10px] text-amber-600"><i class="fas fa-hourglass-half"></i> Ending soon</span>@endif
                                @else <span class="text-gray-300">-</span> @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $badge[$row->effective_status] ?? 'bg-gray-100 text-gray-500' }}">{{ $statuses[$row->effective_status] ?? $row->effective_status }}</span>
                                @if($row->sign_stage)
                                    <span class="block mt-1"><span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ \App\Support\Contracts\SigningRules::BADGES[$row->sign_stage] ?? 'bg-gray-100 text-gray-600' }}"><i class="fas fa-signature mr-0.5"></i>{{ \App\Support\Contracts\SigningRules::LEGAL_LABELS[$row->sign_stage] ?? $row->sign_stage }}</span></span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end">
                                    <div class="seg">
                                        @if($row->has_contract)
                                            <a href="{{ route('general.contracts.document', $row->contract_id) }}" class="seg-view">View</a>
                                            @if($canSalary)<a href="{{ route('general.contracts.document', $row->contract_id) }}?print=1" class="seg-print">Print</a>@endif
                                            @if($canEdit)<a href="{{ route('general.contracts.show', $row->employee_id) }}?edit_contract_id={{ $row->contract_id }}" class="seg-edit">Edit</a>@endif
                                        @endif
                                        <a href="{{ route('general.contracts.show', $row->employee_id) }}" class="seg-manage">Manage</a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-4 py-12 text-center text-gray-400">
                            <i class="fas fa-magnifying-glass text-lg mb-2 block"></i>{{ $hasFilters ? 'No contract matches these filters.' : 'No employee found.' }}</td></tr>
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
</div>

{{-- The calendar of the End-date filter. --}}
@include('hr-general.inventory.components.modal-helpers', ['dates' => true])
@endsection

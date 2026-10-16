{{--
    The table and pagination of External → External Contract. Rendered with the page, and alone (without the layout) when the
    list is refreshed in the background by the filters (ExternalContractController::index answers an XMLHttpRequest with this).

    Parameters: $rows (paginator of ExternalContractService::listing) · $canRate · $statuses · $perPage · $perPageOptions · $filterForm
--}}
@php
    $badge = [
        'active' => 'bg-green-100 text-green-700', 'draft' => 'bg-gray-200 text-gray-600', 'expired' => 'bg-red-100 text-red-700',
        'terminated' => 'bg-red-100 text-red-700', 'inactive' => 'bg-gray-100 text-gray-500',
    ];
    $schemeTone = ['mandays' => 'tone-primary', 'monthly' => 'bg-amber-100 text-amber-700', 'fixed_price' => 'bg-sky-100 text-sky-700', 'hourly' => 'bg-purple-100 text-purple-700'];
    $canEdit = $canDo('general.external.contracts', 'edit');
@endphp
<div class="overflow-x-auto">
    <table class="w-full text-xs text-left">
        <thead class="bg-gray-50 text-[10px] uppercase tracking-wider text-gray-500">
            <tr>
                <th class="px-4 py-3 w-10">No</th>
                <th class="px-4 py-3">Consultant</th>
                <th class="px-4 py-3">Contract No.</th>
                <th class="px-4 py-3">Template</th>
                <th class="px-4 py-3">Type</th>
                <th class="px-4 py-3">Period</th>
                @if($canRate)<th class="px-4 py-3">Rate</th>@endif
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3 text-right">Action</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 text-gray-700">
            @forelse($rows as $row)
                <tr class="hover:bg-gray-50 align-top">
                    <td class="px-4 py-3 text-gray-400">{{ $rows->firstItem() + $loop->index }}</td>
                    <td class="px-4 py-3">
                        <a href="{{ route('general.external.contracts.manage', $row->employee_id) }}" class="font-semibold text-gray-800 hover:underline">{{ $row->name }}</a>
                        <span class="block text-[11px] text-gray-400">{{ $row->eci }} • {{ $row->position ?: '-' }}</span>
                    </td>
                    <td class="px-4 py-3 font-medium text-gray-800 whitespace-nowrap">{{ $row->contract_number }}@if($row->has_signed)<i class="fas fa-stamp ml-1 primary-text" title="Stamped copy on file"></i>@endif</td>
                    <td class="px-4 py-3">
                        @if($row->template_name)<span class="px-2 py-0.5 rounded-full text-[10px] font-semibold tone-primary"><i class="far fa-file-lines mr-1"></i>{{ $row->template_name }}</span>
                        @else <span class="text-gray-300">-</span> @endif
                    </td>
                    <td class="px-4 py-3">
                        @if($row->scheme_label)<span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $schemeTone[$row->scheme_key] ?? 'bg-gray-100 text-gray-600' }}">{{ $kindLabels[$row->contract_kind] ?? $row->scheme_label }}</span>
                        @else <span class="text-gray-300">-</span> @endif
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        {{ $row->start_date ? \Illuminate\Support\Carbon::parse($row->start_date)->format('d M Y') : '—' }}
                        <span class="block text-[11px] text-gray-400">until {{ $row->end_date ? \Illuminate\Support\Carbon::parse($row->end_date)->format('d M Y') : '—' }}</span>
                    </td>
                    @if($canRate)
                        <td class="px-4 py-3 whitespace-nowrap">
                            @if($row->rate_text)<span class="font-semibold text-gray-800">{{ $row->rate_text }}</span><span class="block text-[11px] text-gray-400">/ {{ $row->rate_unit }}</span>
                            @else <span class="text-gray-300">-</span> @endif
                        </td>
                    @endif
                    <td class="px-4 py-3 whitespace-nowrap">
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $badge[$row->effective_status] ?? '' }}">{{ $statuses[$row->effective_status] ?? $row->effective_status }}</span>
                        @if($row->ending_soon)<span class="block text-[10px] text-amber-600 mt-0.5"><i class="fas fa-hourglass-half"></i> Ending soon</span>@endif
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex justify-end">
                            <div class="seg">
                                <a href="{{ route('general.external.contracts.document', $row->contract_id) }}" class="seg-view">View</a>
                                @if($canRate)<a href="{{ route('general.external.contracts.document', $row->contract_id) }}?print=1" class="seg-print">Print</a>@endif
                                @if($canEdit)<a href="{{ route('general.external.contracts.manage', $row->employee_id) }}?edit={{ $row->contract_id }}#new-contract" class="seg-edit">Edit</a>@endif
                                <a href="{{ route('general.external.contracts.manage', $row->employee_id) }}" class="seg-manage">Manage</a>
                            </div>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ $canRate ? 9 : 8 }}" class="px-4 py-12 text-center text-gray-400">
                    <i class="fas fa-magnifying-glass text-lg mb-2 block"></i>No external contract matches the search and filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@include('hr-general.recruitment.components.pagination', ['paginator' => $rows, 'form' => $filterForm])

{{--
    The table and pagination of External → Consultants. Rendered with the page, and alone (without the layout) when the list is
    refreshed in the background by the filters (ExternalConsultantController::index answers an XMLHttpRequest with this).

    Parameters: $rows (paginator of ExternalConsultantService::listing) · $canRate · $periodLabels · $perPage · $perPageOptions · $filterForm
--}}
@php
    $periodTone = ['in_period' => 'text-green-600', 'upcoming' => 'text-blue-600', 'ended' => 'text-red-500', 'none' => 'text-gray-400'];
    $schemeTone = ['mandays' => 'tone-primary', 'monthly' => 'bg-amber-100 text-amber-700', 'fixed_price' => 'bg-sky-100 text-sky-700', 'hourly' => 'bg-purple-100 text-purple-700'];
@endphp
<div class="overflow-x-auto">
    <table class="w-full text-xs text-left">
        <thead class="bg-gray-50 text-[10px] uppercase tracking-wider text-gray-500">
            <tr>
                <th class="px-4 py-3 w-10">No</th>
                <th class="px-4 py-3">Consultant</th>
                <th class="px-4 py-3">Vendor / Assignment</th>
                <th class="px-4 py-3">Scheme</th>
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
                        <div class="flex items-start gap-3">
                            <span class="w-9 h-9 rounded-full tone-primary flex items-center justify-center text-[11px] font-bold shrink-0">{{ $row->initials }}</span>
                            <div class="min-w-0">
                                <a href="{{ route('general.external.consultants.show', $row->employee_id) }}" class="font-semibold text-gray-800 hover:underline">{{ $row->name }}</a>
                                <span class="block text-[11px] text-gray-400">{{ $row->eci }} • {{ $row->position ?: '-' }}</span>
                                @if($row->email || $row->phone)
                                    <span class="block text-[11px] text-gray-400">
                                        @if($row->email)<i class="far fa-envelope"></i> {{ $row->email }}@endif
                                        @if($row->phone)<span class="ml-2"><i class="fas fa-phone text-[9px]"></i> {{ $row->phone }}</span>@endif
                                    </span>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <span class="font-medium text-gray-800">{{ $row->assignment_role ?: '-' }}</span>
                        <span class="block text-[11px] text-gray-400"><i class="far fa-building"></i> {{ $row->vendor_partner ?: ($row->client_company ?: 'No vendor / client') }}</span>
                    </td>
                    <td class="px-4 py-3">
                        @if($row->scheme_label)
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $schemeTone[$row->scheme_key] ?? 'bg-gray-100 text-gray-600' }}">{{ $row->scheme_label }}</span>
                        @else <span class="text-gray-300">-</span> @endif
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        @if($row->engagement_start || $row->engagement_end)
                            {{ $row->engagement_start ? \Illuminate\Support\Carbon::parse($row->engagement_start)->format('d M Y') : '—' }}
                            <span class="block text-[11px] text-gray-400">until {{ $row->engagement_end ? \Illuminate\Support\Carbon::parse($row->engagement_end)->format('d M Y') : '—' }}</span>
                            <span class="block text-[10px] font-semibold {{ $periodTone[$row->period_state] }}">{{ $periodLabels[$row->period_state] }}</span>
                        @else <span class="text-gray-300">-</span> @endif
                    </td>
                    @if($canRate)
                        <td class="px-4 py-3 whitespace-nowrap">
                            @if($row->rate_text)
                                <span class="font-semibold text-gray-800">{{ $row->rate_text }}</span>
                                <span class="block text-[11px] text-gray-400">{{ $row->rate_unit }}</span>
                            @else <span class="text-gray-300">-</span> @endif
                        </td>
                    @endif
                    <td class="px-4 py-3 whitespace-nowrap">
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $row->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">{{ $row->is_active ? 'Active' : 'Inactive' }}</span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex justify-end">
                            <div class="seg">
                                <a href="{{ route('general.external.consultants.show', $row->employee_id) }}" class="seg-view">View</a>
                                @if($canDo('general.external.contracts'))<a href="{{ route('general.external.contracts.manage', $row->employee_id) }}" class="seg-print">Contracts</a>@endif
                                @if($canDo('external.section.basic_data.view'))<a href="{{ route('general.external.consultants.edit', $row->employee_id) }}" class="seg-edit">Edit</a>@endif
                            </div>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ $canRate ? 8 : 7 }}" class="px-4 py-12 text-center text-gray-400">
                    <i class="fas fa-magnifying-glass text-lg mb-2 block"></i>No consultant matches the search and filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@include('hr-general.recruitment.components.pagination', ['paginator' => $rows, 'form' => $filterForm])

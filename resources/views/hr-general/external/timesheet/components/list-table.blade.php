{{--
    The table and pagination of External → Timesheet & Payment (both tabs). Rendered with the page, and alone (without the layout)
    when the live filters refresh it.

    Parameters: $rows (paginator of ExternalTimesheetService::listing) · $amounts · $invoiceView · $perPage · $perPageOptions · $filterForm
--}}
@php
    use App\Support\External\ExternalTimesheetRules as R;
    $schemeTone = ['mandays' => 'tone-primary', 'monthly' => 'bg-amber-100 text-amber-700', 'fixed_price' => 'bg-sky-100 text-sky-700', 'hourly' => 'bg-purple-100 text-purple-700'];
    $unit = ['mandays' => 'mandays', 'monthly' => 'month', 'hourly' => 'hours', 'fixed_price' => 'milestone'];
    $cols = 7 + ($amounts ? 1 : 0) + ($invoiceView ? 2 : 0);
@endphp
<div class="overflow-x-auto">
    <table class="w-full text-xs text-left">
        <thead class="bg-gray-50 text-[10px] uppercase tracking-wider text-gray-500">
            <tr>
                <th class="px-4 py-3 w-10">No</th><th class="px-4 py-3">Consultant</th><th class="px-4 py-3">Period</th><th class="px-4 py-3">Contract</th>
                <th class="px-4 py-3">Hours / unit</th>
                @if($amounts)<th class="px-4 py-3">Gross</th>@endif
                <th class="px-4 py-3">Status</th>
                @if($invoiceView)<th class="px-4 py-3">Invoice</th><th class="px-4 py-3">Due</th>@endif
                <th class="px-4 py-3 text-right">Action</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 text-gray-700">
            @forelse($rows as $row)
                <tr class="hover:bg-gray-50 align-top">
                    <td class="px-4 py-3 text-gray-400">{{ $rows->firstItem() + $loop->index }}</td>
                    <td class="px-4 py-3"><a href="{{ route('general.external.timesheet.show', $row->id) }}" class="font-semibold text-gray-800 hover:underline">{{ $row->name }}</a><span class="block text-[11px] text-gray-400">{{ $row->eci }}</span></td>
                    <td class="px-4 py-3 whitespace-nowrap">{{ $row->period_label }}<span class="block text-[11px] text-gray-400">{{ $row->sheet_no }}</span></td>
                    <td class="px-4 py-3"><span class="font-medium text-gray-800 whitespace-nowrap">{{ $row->contract_number }}</span>
                        @if($row->scheme_key)<span class="block"><span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $schemeTone[$row->scheme_key] ?? 'bg-gray-100 text-gray-600' }}">{{ \App\Support\External\ExternalRules::schemeLabel($row->scheme_key) }}</span></span>@endif</td>
                    <td class="px-4 py-3 whitespace-nowrap">{{ number_format($row->total_hours, 2, ',', '.') }} h
                        @if($row->billable_qty > 0)<span class="block text-[11px] text-gray-400">{{ number_format($row->billable_qty, 2, ',', '.') }} {{ $unit[$row->scheme_key] ?? '' }}</span>@endif</td>
                    @if($amounts)<td class="px-4 py-3 whitespace-nowrap font-semibold text-gray-800">{{ $row->gross_amount !== null ? \App\Support\External\ExternalRules::money($row->gross_amount, $row->currency) : '—' }}</td>@endif
                    <td class="px-4 py-3 whitespace-nowrap"><span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ R::STATUS_BADGES[$row->status] }}">{{ R::STATUS_LABELS[$row->status] }}</span></td>
                    @if($invoiceView)
                        <td class="px-4 py-3 whitespace-nowrap">
                            @if($row->invoice_no)<span class="font-medium text-gray-800">{{ $row->invoice_no }}</span><span class="block"><span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ R::INVOICE_BADGES[$row->invoice_status] }}">{{ R::INVOICE_LABELS[$row->invoice_status] }}</span></span>
                            @else <span class="text-gray-400">Not uploaded</span> @endif
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $row->due_date ? \Illuminate\Support\Carbon::parse($row->due_date)->format('d M Y') : '—' }}</td>
                    @endif
                    <td class="px-4 py-3"><div class="flex justify-end"><a href="{{ route('general.external.timesheet.show', $row->id) }}" class="px-3 py-1.5 rounded-lg border border-gray-300 text-[11px] font-semibold primary-text hover:bg-gray-50">Review</a></div></td>
                </tr>
            @empty
                <tr><td colspan="{{ $cols }}" class="px-4 py-12 text-center text-gray-400"><i class="far fa-folder-open text-lg mb-2 block"></i>No timesheet matches the search and filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@include('hr-general.recruitment.components.pagination', ['paginator' => $rows, 'form' => $filterForm])

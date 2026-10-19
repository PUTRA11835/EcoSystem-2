{{--
    The table and pagination of HR & General → Offboarding. Rendered with the page, and alone (without the layout) when the live filters refresh it.
    Parameters: $rows (paginator of OffboardingService::listing) · $perPage · $perPageOptions · $filterForm
--}}
@php
    $statusBadge = ['in_progress' => 'bg-blue-100 text-blue-700', 'completed' => 'bg-green-100 text-green-700', 'cancelled' => 'bg-gray-200 text-gray-500'];
    $statusLabel = ['in_progress' => 'In progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];
    $typeTone = ['Internal' => 'bg-gray-100 text-gray-600', 'External' => 'bg-amber-100 text-amber-700'];
@endphp
<div class="overflow-x-auto">
    <table class="w-full text-xs text-left">
        <thead class="bg-gray-50 text-[10px] uppercase tracking-wider text-gray-500">
            <tr><th class="px-4 py-3 w-10">No</th><th class="px-4 py-3">Employee</th><th class="px-4 py-3">Type</th><th class="px-4 py-3">Effective date</th><th class="px-4 py-3 w-64">Progress</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Action</th></tr>
        </thead>
        <tbody class="divide-y divide-gray-100 text-gray-700">
            @forelse($rows as $r)
                <tr class="hover:bg-gray-50 align-top">
                    <td class="px-4 py-3 text-gray-400">{{ $rows->firstItem() + $loop->index }}</td>
                    <td class="px-4 py-3"><a href="{{ route('general.offboarding.show', $r->id) }}" class="font-semibold text-gray-800 hover:underline">{{ $r->name }}</a>
                        <span class="block text-[11px] text-gray-400">{{ $r->eci }} • {{ $r->position ?: '-' }} <span class="ml-1 px-1.5 py-0.5 rounded-full text-[10px] font-bold {{ $typeTone[$r->employee_type] ?? '' }}">{{ $r->employee_type }}</span></span></td>
                    <td class="px-4 py-3 whitespace-nowrap">{{ $r->type_label }}</td>
                    <td class="px-4 py-3 whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($r->effective_date)->format('d M Y') }}
                        @if($r->access_deactivated_at)<span class="block text-[10px] text-gray-400"><i class="fas fa-lock"></i> access deactivated</span>@endif</td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-between text-[11px] text-gray-500"><span>{{ $r->done }}/{{ $r->total }} completed</span><span class="font-semibold">{{ $r->percent }}%</span></div>
                        <div class="h-1.5 rounded-full bg-gray-100 mt-1 overflow-hidden"><div class="h-full rounded-full primary-gradient" style="width: {{ $r->percent }}%"></div></div>
                        @if($r->overdue > 0)<span class="block text-[10px] text-red-600 mt-1"><i class="fas fa-triangle-exclamation"></i> {{ $r->overdue }} overdue</span>@endif
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap"><span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $statusBadge[$r->status] }}">{{ $statusLabel[$r->status] }}</span></td>
                    <td class="px-4 py-3"><div class="flex justify-end"><a href="{{ route('general.offboarding.show', $r->id) }}" class="px-3 py-1.5 rounded-lg border border-gray-300 text-[11px] font-semibold primary-text hover:bg-gray-50">Open Checklist</a></div></td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-12 text-center text-gray-400"><i class="far fa-folder-open text-lg mb-2 block"></i>No offboarding matches the search and filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@include('hr-general.recruitment.components.pagination', ['paginator' => $rows, 'form' => $filterForm])

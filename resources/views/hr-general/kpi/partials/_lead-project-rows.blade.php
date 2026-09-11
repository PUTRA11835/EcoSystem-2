@php
    // Read-only. The leader is derived from master employee data:
    //   project manager (if on a project) → "report to" → none.
    $srcBadge = [
        'project' => ['Project',     'bg-amber-50 text-amber-700'],
        'master'  => ['Master data', 'bg-gray-100 text-gray-500'],
    ];
@endphp
@forelse($employees as $emp)
    @php
        $bd    = $emp->basicData;
        $projs = $emp->deliveryProjects ?? collect();
        $rt    = $reportsToMap[$emp->employee_id] ?? ['name' => null, 'source' => null];
    @endphp
    <tr class="hover:bg-gray-50/70 transition-colors">
        <td class="px-5 py-3 text-gray-400 text-xs font-medium align-top">{{ $employees->firstItem() + $loop->index }}</td>
        <td class="px-4 py-3 align-top">
            <p class="font-semibold text-gray-900 text-sm">{{ $bd?->full_name ?? $emp->eci }}</p>
            <p class="text-xs text-indigo-500 font-mono">{{ $emp->eci }}</p>
        </td>
        <td class="px-4 py-3 text-xs text-gray-600 align-top">{{ $bd?->position ?? '—' }}</td>

        {{-- Project --}}
        <td class="px-4 py-3 align-top">
            @if($projs->isEmpty())
                <span class="text-xs text-gray-300">—</span>
            @else
                <div class="flex flex-wrap gap-1">
                    @foreach($projs->take(3) as $p)
                    <span class="px-1.5 py-0.5 rounded bg-gray-100 text-[10px] text-gray-600">{{ \Illuminate\Support\Str::limit($p->name, 22) }}</span>
                    @endforeach
                    @if($projs->count() > 3)<span class="text-[10px] text-gray-400">+{{ $projs->count() - 3 }}</span>@endif
                </div>
            @endif
        </td>

        {{-- Leader — derived, changed only from master employee data --}}
        <td class="px-4 py-3 align-top">
            @if($rt['name'])
                <span class="text-xs font-medium text-gray-800">{{ $rt['name'] }}</span>
                @if($rt['source'] && isset($srcBadge[$rt['source']]))
                <span class="ml-1 inline-block px-1.5 py-0.5 rounded text-[9px] font-bold {{ $srcBadge[$rt['source']][1] }}">{{ $srcBadge[$rt['source']][0] }}</span>
                @endif
            @else
                <span class="text-xs text-gray-400">—</span>
            @endif
        </td>
    </tr>
@empty
    <tr><td colspan="5" class="py-12 text-center text-gray-400 text-sm">No employees match these filters.</td></tr>
@endforelse

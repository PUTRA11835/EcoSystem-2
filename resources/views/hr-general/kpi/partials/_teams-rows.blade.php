@php
    $srcBadge = [
        'manual'  => ['Manual',  'bg-gray-100 text-gray-600'],
        'team'    => ['Team',    'bg-indigo-50 text-indigo-600'],
        'project' => ['Project', 'bg-amber-50 text-amber-700'],
    ];
@endphp
@forelse($employees as $emp)
    @php
        $bd = $emp->basicData;
        $leadId = $bd?->direct_supervision;
        $lead = ($leadId && isset($leadMap[$leadId])) ? $leadMap[$leadId] : null;
        $leadName = $lead?->basicData?->full_name ?? ($leadId ? ('#' . $leadId) : null);
        $src = $bd?->lead_source;
        $team = $bd?->kpiTeam;
        $projs = $emp->deliveryProjects ?? collect();
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
                @if($canEdit)
                <div class="proj-cell relative mt-1" data-emp="{{ $emp->employee_id }}"
                     data-projids="{{ $projs->pluck('id')->implode(',') }}">
                    <button type="button" onclick="pOpen(this)" class="text-[11px] text-amber-700 font-semibold hover:underline">
                        <i class="fas fa-arrow-down-short-wide text-[9px]"></i> use project lead
                    </button>
                    <div class="proj-menu hidden absolute z-40 left-0 mt-1 w-[260px] bg-white border border-gray-200 rounded-xl shadow-xl py-1"></div>
                </div>
                @endif
            @endif
        </td>

        {{-- Team --}}
        <td class="px-4 py-3 align-top">
            @if($canEdit)
            <div class="team-cell relative" data-emp="{{ $emp->employee_id }}">
                <button type="button" onclick="tOpen(this)"
                    class="team-btn w-full flex items-center justify-between gap-2 px-3 py-2 text-xs border border-gray-200 rounded-xl bg-white hover:border-indigo-300 transition-all max-w-[240px]">
                    <span class="team-label truncate {{ $team ? 'font-medium text-gray-800' : 'text-gray-400 italic' }}">{{ $team?->name ?? 'No team' }}</span>
                    <i class="fas fa-chevron-down text-[9px] text-gray-400"></i>
                </button>
                <input type="hidden" class="team-id" value="{{ $bd?->kpi_team_id ?? '' }}">
                <div class="team-menu hidden absolute z-40 left-0 mt-1 w-[260px] bg-white border border-gray-200 rounded-xl shadow-xl">
                    <div class="p-2 border-b border-gray-100">
                        <input type="text" class="team-search w-full px-2.5 py-1.5 text-xs border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-400" placeholder="Search team…" oninput="tFilter(this)">
                    </div>
                    <div class="team-list max-h-52 overflow-y-auto py-1"></div>
                </div>
            </div>
            @else
            <span class="text-xs {{ $team ? 'font-medium text-gray-800' : 'text-gray-400 italic' }}">{{ $team?->name ?? 'No team' }}</span>
            @endif
            @if($team?->lead)
            <p class="text-[10px] text-gray-400 mt-0.5">lead: {{ $team->lead->basicData?->full_name ?? ('#' . $team->lead_employee_id) }}</p>
            @endif
        </td>

        {{-- Leader --}}
        <td class="px-4 py-3 align-top">
            @if($canEdit)
            <div class="lead-cell relative" data-emp="{{ $emp->employee_id }}">
                <button type="button" onclick="leadOpen(this)"
                    class="lead-btn w-full flex items-center justify-between gap-2 px-3 py-2 text-xs border border-gray-200 rounded-xl bg-white hover:border-indigo-300 transition-all max-w-[260px]">
                    <span class="lead-label truncate {{ $leadName ? 'font-medium text-gray-800' : 'text-gray-400 italic' }}">{{ $leadName ?? 'No leader — set one' }}</span>
                    <i class="fas fa-chevron-down text-[9px] text-gray-400"></i>
                </button>
                <input type="hidden" class="lead-id" value="{{ $leadId ?? '' }}">
                <div class="lead-menu hidden absolute z-40 left-0 mt-1 w-[280px] bg-white border border-gray-200 rounded-xl shadow-xl">
                    <div class="p-2 border-b border-gray-100">
                        <input type="text" class="lead-search w-full px-2.5 py-1.5 text-xs border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-400" placeholder="Search leader…" oninput="leadFilter(this)">
                    </div>
                    <div class="lead-list max-h-56 overflow-y-auto py-1"></div>
                </div>
            </div>
            @else
            <span class="text-xs {{ $leadName ? 'font-medium text-gray-800' : 'text-gray-400 italic' }}">{{ $leadName ?? 'No leader' }}</span>
            @endif
            <span class="lead-src inline-block mt-1 px-1.5 py-0.5 rounded text-[9px] font-bold {{ $src && isset($srcBadge[$src]) ? $srcBadge[$src][1] : 'hidden' }}">
                {{ $src && isset($srcBadge[$src]) ? $srcBadge[$src][0] : '' }}
            </span>
        </td>

        <td class="px-4 py-3"></td>
    </tr>
@empty
    <tr><td colspan="7" class="py-12 text-center text-gray-400 text-sm">No employees match these filters.</td></tr>
@endforelse

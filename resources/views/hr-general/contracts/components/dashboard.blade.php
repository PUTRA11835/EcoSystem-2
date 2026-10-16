{{--
    The Contract dashboard: figures, the contracts ending soon, and the lists that need attention.
    Shared by HR & General → Contract → Dashboard and Management → Employee → Contract → Dashboard.

    Parameters:
      $dash    — ContractService::dashboard()
      $dashUrl — URL of the page that shows this dashboard (the 30 / 60 / 90 day switch links back to it)
      $listUrl — URL of the contract list (the "See all" links add ?status=)
--}}
@php
    $canManage = $can('general.contracts.list');
    $typeBadge = ['PKWT' => 'bg-indigo-100 text-indigo-700', 'PKWTT' => 'bg-blue-100 text-blue-700', 'EXTERNAL' => 'bg-amber-100 text-amber-700'];
    $date = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->format('d M Y') : '—';
    $statusBadge = [
        'active' => 'bg-green-100 text-green-700', 'draft' => 'bg-gray-200 text-gray-600', 'expired' => 'bg-red-100 text-red-700',
        'terminated' => 'bg-red-100 text-red-700', 'inactive' => 'bg-gray-100 text-gray-500',
    ];
    $statusLabels = \App\Support\Contracts\ContractRules::STATUS_LABELS;
@endphp
@php
    $cards = $dash['cards'];
    $maxType = max(1, (int) $dash['byType']->max());
    $maxDept = max(1, (int) $dash['byDept']->max());
    $maxMonth = max(1, (int) $dash['months']->max('count'));
    $window = $dash['window'];
    $tone = fn (int $left) => $left <= 7 ? 'bg-red-100 text-red-700' : ($left <= 30 ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-600');
@endphp

<div class="grid grid-cols-2 lg:grid-cols-4 xl:grid-cols-6 gap-3">
    @foreach([
        ['Active contracts', $cards['active'], 'fa-circle-check', 'text-green-600', $cards['permanent'] . ' permanent'],
        ['Ending in 30 days', $cards['ending30'], 'fa-hourglass-half', 'text-amber-600', $cards['ending60'] . ' within 60 · ' . $cards['ending90'] . ' within 90'],
        ['Expired', $cards['expired'], 'fa-calendar-xmark', 'text-red-600', 'latest contract has ended'],
        ['No contract', $cards['none'], 'fa-file-circle-xmark', 'text-gray-500', 'active employees'],
        ['Draft', $cards['draft'], 'fa-file-pen', 'text-gray-500', 'not yet active'],
        ['Employees', $cards['employees'], 'fa-users', 'text-gray-900', 'active, with latest contract'],
    ] as [$label, $value, $icon, $color, $sub])
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
            <div class="flex items-center justify-between">
                <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide">{{ $label }}</p>
                <i class="fas {{ $icon }} text-sm {{ $color }} opacity-70"></i>
            </div>
            <p class="text-2xl font-bold mt-1 {{ $color }}">{{ number_format($value) }}</p>
            <p class="text-[11px] text-gray-400 mt-0.5">{{ $sub }}</p>
        </div>
    @endforeach
</div>

{{-- Ending soon: the table people open this page for. --}}
<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="px-5 py-3.5 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h3 class="text-sm font-bold text-gray-800">Contracts ending soon</h3>
            <p class="text-[11px] text-gray-400 mt-0.5">Running contracts that end within the next {{ $window }} days, soonest first.</p>
        </div>
        <div class="inline-flex rounded-lg border border-gray-200 bg-gray-50 p-0.5 text-xs font-semibold" role="group" aria-label="Window">
            @foreach([30 => '30 days', 60 => '60 days', 90 => '90 days'] as $days => $text)
                <a href="{{ $dashUrl }}?window={{ $days }}"
                    class="px-3 py-1.5 rounded-md transition-colors {{ $window === $days ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-800' }}">
                    {{ $text }} <span class="ml-0.5 text-[10px] text-gray-400">{{ $cards['ending' . $days] }}</span>
                </a>
            @endforeach
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-xs text-left">
            <thead class="bg-gray-50 text-[10px] uppercase tracking-wider text-gray-500">
                <tr>
                    <th class="px-4 py-3 w-10">No</th>
                    <th class="px-4 py-3">Employee</th>
                    <th class="px-4 py-3">Contract No.</th>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3">Start</th>
                    <th class="px-4 py-3">End</th>
                    <th class="px-4 py-3">Time left</th>
                    <th class="px-4 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 text-gray-700">
                @forelse($dash['ending'] as $row)
                    <tr class="hover:bg-gray-50 align-top">
                        <td class="px-4 py-3 text-gray-400">{{ $loop->iteration }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('master.employee.detail', $row->employee_id) }}" class="font-semibold text-gray-800 hover:underline">{{ $row->name }}</a>
                            <span class="block text-[11px] text-gray-400">{{ $row->eci }} • {{ $row->position ?: '—' }} • {{ $row->employee_department ?: '—' }}</span>
                        </td>
                        <td class="px-4 py-3 font-medium text-gray-800">{{ $row->contract_number ?: '—' }}</td>
                        <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $typeBadge[$row->type] ?? 'bg-gray-100 text-gray-600' }}">{{ $row->type ?? $row->contract_type ?? '—' }}</span></td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $date($row->start_date) }}</td>
                        <td class="px-4 py-3 whitespace-nowrap font-semibold">{{ $date($row->end_date) }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $tone($row->days_left) }}">
                                {{ $row->days_left === 0 ? 'Today' : $row->days_left . ' ' . \Illuminate\Support\Str::plural('day', $row->days_left) }}
                            </span>
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
                    <tr><td colspan="8" class="px-4 py-12 text-center text-gray-400">
                        <i class="fas fa-circle-check text-lg mb-2 block text-green-500"></i>No running contract ends within {{ $window }} days.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- The contract records themselves (latest first) — so the dashboard shows real contract data, not only counts. --}}
<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="px-5 py-3.5 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h3 class="text-sm font-bold text-gray-800">Contract records <span class="text-gray-400 font-semibold">· {{ number_format($dash['records']) }}</span></h3>
            <p class="text-[11px] text-gray-400 mt-0.5">The latest recorded contracts, whatever their status.</p>
        </div>
        <a href="{{ $listUrl }}" class="text-xs font-semibold primary-text whitespace-nowrap hover:underline">Open the full list →</a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-xs text-left">
            <thead class="bg-gray-50 text-[10px] uppercase tracking-wider text-gray-500">
                <tr>
                    <th class="px-4 py-3 w-10">No</th>
                    <th class="px-4 py-3">Employee</th>
                    <th class="px-4 py-3">Contract No.</th>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3">Period</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 text-gray-700">
                @forelse($dash['recent'] as $row)
                    <tr class="hover:bg-gray-50 align-top">
                        <td class="px-4 py-3 text-gray-400">{{ $loop->iteration }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('master.employee.detail', $row->employee_id) }}" class="font-semibold text-gray-800 hover:underline">{{ $row->name }}</a>
                            <span class="block text-[11px] text-gray-400">{{ $row->eci }} • {{ $row->position ?: '—' }} • {{ $row->employee_department ?: '—' }}</span>
                        </td>
                        <td class="px-4 py-3 font-medium text-gray-800">{{ $row->contract_number ?: '—' }}</td>
                        <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $typeBadge[$row->type] ?? 'bg-gray-100 text-gray-600' }}">{{ $row->type ?? $row->contract_type ?? '—' }}</span></td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $date($row->start_date) }}
                            <span class="block text-[11px] text-gray-400">until {{ $row->end_date ? $date($row->end_date) : 'permanent' }}</span></td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $statusBadge[$row->effective_status] ?? 'bg-gray-100 text-gray-500' }}">{{ $statusLabels[$row->effective_status] ?? $row->effective_status }}</span>
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
                    <tr><td colspan="7" class="px-4 py-12 text-center text-gray-400">
                        <i class="fas fa-file-circle-xmark text-lg mb-2 block"></i>No contract has been recorded yet — {{ number_format($dash['cards']['none']) }} active employees have none.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    {{-- Next six months --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <h3 class="text-sm font-bold text-gray-800">Ending by month</h3>
        <p class="text-[11px] text-gray-400 mb-4">Running contracts, by the month they end.</p>
        <div class="space-y-2.5">
            @foreach($dash['months'] as $month)
                <div class="flex items-center gap-3 text-xs">
                    <span class="w-16 shrink-0 text-gray-500">{{ $month['label'] }}</span>
                    <div class="flex-1 h-4 bg-gray-100 rounded">
                        <div class="h-4 rounded primary-gradient" style="width: {{ $month['count'] > 0 ? max(3, round($month['count'] / $maxMonth * 100)) : 0 }}%"></div>
                    </div>
                    <span class="w-8 shrink-0 text-right font-semibold text-gray-800">{{ $month['count'] }}</span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- By type --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <h3 class="text-sm font-bold text-gray-800">By contract type</h3>
        <p class="text-[11px] text-gray-400 mb-4">Latest contract of every employee that has one.</p>
        <div class="space-y-2.5">
            @forelse($dash['byType'] as $type => $n)
                <div class="flex items-center gap-3 text-xs">
                    <span class="w-24 shrink-0 text-gray-600 truncate">{{ $type }}</span>
                    <div class="flex-1 h-4 bg-gray-100 rounded">
                        <div class="h-4 rounded primary-gradient" style="width: {{ max(3, round($n / $maxType * 100)) }}%"></div>
                    </div>
                    <span class="w-8 shrink-0 text-right font-semibold text-gray-800">{{ $n }}</span>
                </div>
            @empty
                <p class="text-sm text-gray-400 py-6 text-center">No contract recorded yet.</p>
            @endforelse
        </div>
    </div>

    {{-- By department --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <h3 class="text-sm font-bold text-gray-800">Active contracts by department</h3>
        <p class="text-[11px] text-gray-400 mb-4">Top {{ $dash['byDept']->count() }} departments.</p>
        <div class="space-y-2.5">
            @forelse($dash['byDept'] as $dept => $n)
                <div class="flex items-center gap-3 text-xs">
                    <span class="w-28 shrink-0 text-gray-600 truncate" title="{{ $dept }}">{{ $dept }}</span>
                    <div class="flex-1 h-4 bg-gray-100 rounded">
                        <div class="h-4 rounded primary-gradient" style="width: {{ max(3, round($n / $maxDept * 100)) }}%"></div>
                    </div>
                    <span class="w-8 shrink-0 text-right font-semibold text-gray-800">{{ $n }}</span>
                </div>
            @empty
                <p class="text-sm text-gray-400 py-6 text-center">No active contract.</p>
            @endforelse
        </div>
    </div>
</div>

{{-- Needs attention --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
    @foreach([
        ['Expired contracts', 'The latest contract has ended and nothing replaced it.', $dash['expired'], $cards['expired'], 'expired', 'fa-calendar-xmark text-red-500'],
        ['Without a contract', 'Active employees with no contract on record.', $dash['none'], $cards['none'], 'none', 'fa-file-circle-xmark text-gray-400'],
    ] as [$title, $hint, $people, $total, $statusKey, $icon])
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <div class="flex items-start justify-between gap-3 mb-3">
                <div>
                    <h3 class="text-sm font-bold text-gray-800"><i class="fas {{ $icon }} mr-1.5"></i>{{ $title }} <span class="text-gray-400 font-semibold">· {{ $total }}</span></h3>
                    <p class="text-[11px] text-gray-400 mt-0.5">{{ $hint }}</p>
                </div>
                @if($total > 0)
                    <a href="{{ $listUrl }}{{ str_contains($listUrl, '?') ? '&' : '?' }}status={{ $statusKey }}" class="text-xs font-semibold primary-text whitespace-nowrap hover:underline">See all →</a>
                @endif
            </div>
            @forelse($people as $row)
                <a href="{{ route('master.employee.detail', $row->employee_id) }}"
                    class="flex items-center justify-between gap-3 py-2 border-b border-gray-100 last:border-0 hover:bg-gray-50 -mx-2 px-2 rounded">
                    <span class="min-w-0">
                        <span class="block text-sm text-gray-800 truncate">{{ $row->name }}</span>
                        <span class="block text-[11px] text-gray-400 truncate">{{ $row->eci }} • {{ $row->employee_department ?: '—' }}</span>
                    </span>
                    <span class="text-xs text-gray-500 whitespace-nowrap">{{ $row->end_date ? 'ended ' . $date($row->end_date) : '—' }}</span>
                </a>
            @empty
                <p class="text-sm text-gray-400 py-6 text-center">Nobody — all good.</p>
            @endforelse
        </div>
    @endforeach
</div>

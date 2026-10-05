@extends('dashboard')

@section('title', 'Onboarding — Data Completeness')
@section('page-title', 'Onboarding')
@section('page-subtitle', 'Standard start-of-work data checklist that HR, supervisors and payroll can rely on')

@section('content')
@php
    // Tautan pengurutan: klik kolom yang sama membalik arah.
    $sortUrl = function (string $asc, ?string $desc = null) use ($filters) {
        $next = $filters['sort'] === $asc && $desc ? $desc : $asc;
        return request()->fullUrlWithQuery(['sort' => $next, 'page' => null]);
    };
    $sortMark = fn (string ...$keys) => in_array($filters['sort'], $keys, true)
        ? ($filters['sort'] === 'progress_desc' ? '↓' : '↑') : '⇅';
@endphp

{{-- Tata letak mengikuti halaman Onboarding aplikasi acuan (ESH): ringkasan di kiri,
     tabel karyawan di kanan. Angka ringkasan dihitung dari SELURUH karyawan aktif,
     bukan dari hasil filter. --}}
<div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">

    {{-- ── Kiri: ringkasan ─────────────────────────────────────────────── --}}
    <div class="lg:col-span-4 xl:col-span-3 bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-base font-bold text-gray-900">Onboarding Summary</h2>
                    <p class="text-xs text-gray-500 mt-1">Live progress from the employee master data.</p>
                </div>
                <span class="inline-block px-2 py-0.5 rounded-full text-xs font-bold bg-blue-100 text-blue-800">{{ $summary['percent'] }}%</span>
            </div>
            <div class="mt-4 h-2 bg-gray-200 rounded-full overflow-hidden">
                <div class="h-2 rounded-full primary-solid" style="width: {{ $summary['percent'] }}%"></div>
            </div>
            <p class="text-xs text-gray-500 mt-2">{{ number_format($summary['items_done']) }} of {{ number_format($summary['items_total']) }} required items filled</p>
        </div>

        <div class="border-t border-gray-100 p-5 grid grid-cols-2 gap-3">
            @foreach([
                ['label' => 'Employees',   'value' => $summary['employees']],
                ['label' => 'In progress', 'value' => $summary['in_progress']],
                ['label' => 'Complete',    'value' => $summary['complete']],
                ['label' => 'Items left',  'value' => $summary['items_remaining']],
            ] as $tile)
                <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
                    <p class="text-2xl font-bold text-gray-900">{{ number_format($tile['value']) }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">{{ $tile['label'] }}</p>
                </div>
            @endforeach
        </div>

        <div class="border-t border-gray-100 px-5 py-4">
            <p class="text-xs text-gray-500 leading-relaxed">
                Nothing is typed in here. An item turns complete as soon as the data is saved in
                <span class="font-semibold text-gray-700">Master › Employee</span> or by the employee in
                <span class="font-semibold text-gray-700">My Profile</span>. External consultants are assessed on contact, identity, tax ID and bank account items only.
            </p>
        </div>
    </div>

    {{-- ── Kanan: daftar karyawan ──────────────────────────────────────── --}}
    <div class="lg:col-span-8 xl:col-span-9 bg-white rounded-xl shadow-sm">
        <div class="p-5 flex items-start justify-between gap-3">
            <div>
                <h2 class="text-base font-bold text-gray-900">Employee Onboarding</h2>
                <p class="text-xs text-gray-500 mt-1">Open a checklist to see exactly which items are still missing and where to fill them in.</p>
            </div>
            <span class="text-xs font-semibold text-gray-700 bg-gray-100 rounded-full px-3 py-1 whitespace-nowrap">
                {{ number_format($rows->total()) }} {{ $rows->total() === 1 ? 'employee' : 'employees' }}
            </span>
        </div>

        {{-- Filter ringkas --}}
        <form method="GET" action="{{ route('general.onboarding.index') }}" class="px-5 pb-4">
            <div class="grid grid-cols-2 md:grid-cols-12 gap-2 items-end">
                <div class="col-span-2 md:col-span-4">
                    <input type="text" name="search" value="{{ $filters['search'] }}" aria-label="Search"
                           placeholder="Search name, ECI, position or department..."
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>
                <div class="md:col-span-2">
                    <select name="status" aria-label="Status" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                        @foreach(['in_progress' => 'In progress', 'complete' => 'Complete', 'all' => 'All statuses'] as $value => $label)
                            <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-2">
                    <select name="type" aria-label="Type" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                        @foreach(['all' => 'All types', 'Internal' => 'Internal', 'External' => 'External'] as $value => $label)
                            <option value="{{ $value }}" @selected($filters['type'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-2">
                    <select name="missing" aria-label="Missing in" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                        <option value="all" @selected($filters['missing'] === 'all')>Missing: any</option>
                        @foreach($groups as $key => $label)
                            <option value="{{ $key }}" @selected($filters['missing'] === $key)>Missing: {{ $label }}</option>
                        @endforeach
                        <option value="join_date" @selected($filters['missing'] === 'join_date')>Missing: Join date</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <select name="lock" aria-label="Profile lock" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                        @foreach(['all' => 'Lock: any', 'ready' => 'Ready to lock', 'locked' => 'Locked'] as $value => $label)
                            <option value="{{ $value }}" @selected($filters['lock'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-span-2 md:col-span-2 flex gap-2">
                    <button type="submit" class="px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all">Apply</button>
                    <a href="{{ route('general.onboarding.index') }}"
                       class="px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">Reset</a>
                </div>
            </div>
            <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
        </form>

        {{-- HC-D64 — alat join date untuk HR (opsional): hanya saat filter "Missing: Join date" aktif & ada izinnya --}}
        @if(($filters['missing'] ?? '') === 'join_date' && $can('general.onboarding.join-date'))
            @include('hr-general.onboarding.partials.join-date-tools')
        @endif

        <div class="border-t border-gray-100 overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide border-b border-gray-100">
                        <th class="px-5 py-3 w-12">No</th>
                        <th class="px-3 py-3"><a href="{{ $sortUrl('name') }}" class="hover:text-gray-900">Employee <span class="text-gray-400">{{ $sortMark('name') }}</span></a></th>
                        <th class="px-3 py-3 whitespace-nowrap"><a href="{{ $sortUrl('join_date') }}" class="hover:text-gray-900">Join date <span class="text-gray-400">{{ $sortMark('join_date') }}</span></a></th>
                        <th class="px-3 py-3">Status</th>
                        <th class="px-3 py-3">Type</th>
                        <th class="px-3 py-3 w-64"><a href="{{ $sortUrl('progress_asc', 'progress_desc') }}" class="hover:text-gray-900">Progress <span class="text-gray-400">{{ $sortMark('progress_asc', 'progress_desc') }}</span></a></th>
                        <th class="px-5 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($rows as $index => $e)
                    <tr class="align-top hover:bg-gray-50 transition-colors">
                        <td class="px-5 py-3.5 text-gray-400">{{ $rows->firstItem() + $index }}</td>
                        <td class="px-3 py-3.5">
                            <a href="{{ route('general.onboarding.show', $e['employee_id']) }}" class="font-semibold text-gray-900 hover:underline">{{ $e['name'] }}</a>
                            <p class="text-xs text-gray-500 mt-0.5">
                                <span class="font-mono">{{ $e['eci'] }}</span> • {{ $e['position'] ?? '-' }}
                            </p>
                        </td>
                        <td class="px-3 py-3.5 whitespace-nowrap text-gray-700">
                            {{ $e['join_date'] ? \Carbon\Carbon::parse($e['join_date'])->format('d M Y') : '—' }}
                        </td>
                        <td class="px-3 py-3.5 whitespace-nowrap text-gray-700">
                            {{ \App\Services\HrProfile\EmploymentStatus::label($e['employment_status'] ?? null) }}
                            @if(!empty($e['locked_at']))
                                <p class="mt-1"><span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-gray-200 text-gray-700"><i class="fas fa-lock text-[9px]"></i> Locked</span></p>
                            @elseif($e['status'] === 'complete')
                                <p class="mt-1"><span class="inline-block px-2 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-800">Ready to lock</span></p>
                            @endif
                        </td>
                        <td class="px-3 py-3.5">
                            <span class="inline-block px-2 py-0.5 rounded-full text-xs font-bold
                                {{ $e['type'] === 'External' ? 'bg-sky-100 text-sky-800' : 'bg-green-100 text-green-800' }}">{{ $e['type'] }}</span>
                        </td>
                        <td class="px-3 py-3.5">
                            <div class="flex items-center justify-between text-xs text-gray-600 mb-1">
                                <span>{{ $e['done'] }}/{{ $e['total'] }} filled</span>
                                <span class="font-semibold text-gray-900">{{ $e['percent'] }}%</span>
                            </div>
                            <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                                <div class="h-2 rounded-full primary-solid" style="width: {{ $e['percent'] }}%"></div>
                            </div>
                            @if($e['status'] !== 'complete')
                                <div class="flex flex-wrap gap-1 mt-2">
                                    @foreach($e['groups'] as $g)
                                        @if($g['missing'])
                                            <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-800"
                                                  title="{{ implode(', ', $g['missing']) }}">{{ $g['label'] }} · {{ count($g['missing']) }}</span>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-right whitespace-nowrap">
                            <a href="{{ route('general.onboarding.show', $e['employee_id']) }}"
                               class="inline-block px-3 py-1.5 text-xs font-semibold rounded-full border border-blue-300 text-blue-700 hover:bg-blue-50">Open Checklist</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-5 py-12 text-center text-gray-500">No employees match the filters.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-5">{{ $rows->links() }}</div>
    </div>
</div>
@endsection

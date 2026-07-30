@extends('dashboard')
@section('title', 'Delivery Support')
@section('page-title', 'Delivery Support')
@section('page-subtitle', 'Manage all support delivery items')

@push('styles')
<style>
    .primary-focus:focus { border-color: var(--primary-color) !important; box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.15) !important; outline: none !important; }

    /* Summary tiles — clickable, men-drive filter tabel yang sama dengan header kolom */
    .stat-tile {
        display: block; width: 100%; text-align: left;
        padding: 0.875rem 1rem;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 0.75rem;
        box-shadow: 0 1px 2px rgba(0,0,0,0.04);
        transition: box-shadow 0.15s ease, border-color 0.15s ease, transform 0.15s ease;
        cursor: pointer;
    }
    .stat-tile:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); transform: translateY(-1px); }
    .stat-tile.is-active { border-color: var(--primary-color); box-shadow: 0 0 0 2px rgba(var(--primary-rgb), 0.18); }
    .stat-icon {
        display: inline-flex; align-items: center; justify-content: center;
        width: 2rem; height: 2rem; border-radius: 0.5rem; font-size: 0.875rem;
    }

    /* ── Per-column header sort + filter (sejajar Delivery Project list) ──────── */
    .sup-th { padding: 0; }
    .sup-th-btn { width: 100%; display: flex; align-items: center; gap: 0.375rem; padding: 0.75rem 1.5rem; cursor: pointer; background: transparent; text-align: left; font-size: 0.75rem; font-weight: 500; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; }
    .sup-th-btn:hover { background: #f3f4f6; }
    .sup-sort-icon { color: #d1d5db; font-weight: 400; text-transform: none; letter-spacing: normal; font-size: 0.8rem; }
    .sup-sort-icon.active { color: #ef4444; font-weight: 700; }
    .sup-funnel { width: 0.8rem; height: 0.8rem; color: #d1d5db; margin-left: auto; flex-shrink: 0; }
    .sup-funnel.active { color: #ef4444; }
    .sup-panel { background: #fff; border: 1px solid #f3f4f6; border-radius: 0.75rem; box-shadow: 0 10px 25px rgba(0,0,0,0.15); padding: 0.75rem; min-width: 220px; text-transform: none; letter-spacing: normal; }
    .sup-panel-label { display: block; font-size: 11px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.03em; margin-bottom: 0.25rem; }
    .sup-panel-input { width: 100%; padding: 0.375rem 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem; font-size: 0.875rem; font-weight: 400; text-transform: none; color: #374151; background: #fff; }
    .sup-panel-input:focus { outline: none; border-color: #f87171; box-shadow: 0 0 0 3px rgba(254,202,202,0.5); }
    .sup-sort-btn { flex: 1; padding: 0.375rem 0.5rem; font-size: 0.75rem; color: #4b5563; border: 1px solid #e5e7eb; border-radius: 0.375rem; background: #fff; cursor: pointer; }
    .sup-sort-btn:hover { background: #f9fafb; }
    .sup-clear-btn { margin-top: 0.75rem; width: 100%; padding: 0.375rem 0.5rem; font-size: 0.75rem; color: #4b5563; border: 1px solid #e5e7eb; border-radius: 0.375rem; background: #fff; cursor: pointer; }
    .sup-clear-btn:hover { background: #f9fafb; }
</style>
@endpush

@section('content')
<div class="min-h-screen bg-gray-50">
    {{-- Flash success/error toast sudah ditampilkan layout dashboard.blade.php.
         Jangan diulang di sini: toast jadi dobel. --}}

    @if($errors->any())
        <script>document.addEventListener('DOMContentLoaded',()=>showNotification(@json($errors->first()),'error'));</script>
    @endif

    {{-- Sub Navigation Tabs --}}
    <div class="mb-6 bg-white rounded-lg shadow-sm border border-gray-200">
        <nav class="flex space-x-1 p-1" aria-label="Tabs">
            <a href="{{ route('delivery.support.index') }}"
               class="flex-1 sm:flex-none px-4 py-2.5 text-sm font-medium text-center rounded-lg transition-all
                      {{ Request::routeIs('delivery.support.index') ? 'primary-gradient text-white shadow-sm' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100' }}">
                <i class="fas fa-headset mr-2"></i>
                <span>Support</span>
            </a>
            <a href="{{ route('delivery.support.planning-list') }}"
               class="flex-1 sm:flex-none px-4 py-2.5 text-sm font-medium text-center rounded-lg transition-all
                      {{ Request::routeIs('delivery.support.planning-list') ? 'primary-gradient text-white shadow-sm' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100' }}">
                <i class="fas fa-tasks mr-2"></i>
                <span>Planning</span>
            </a>
        </nav>
    </div>

    {{-- ── Data turunan untuk summary, opsi filter, dan penanda baris ───────────
         Semuanya dihitung dari koleksi $supports yang memang sudah dimuat penuh
         untuk tabel, jadi tidak ada query tambahan. --}}
    @php
        $today     = \Illuminate\Support\Carbon::today();
        $soonLimit = $today->copy()->addDays(30);

        $progressOf = fn($s) => (float) ($s->calculated_progress ?? 0);

        // Bucket progress — nilai ini juga dipakai sebagai data-bucket & value filter.
        $bucketOf = function ($s) use ($progressOf) {
            $p = $progressOf($s);
            if ($p >= 100) return 'completed';
            return $p > 0 ? 'in progress' : 'not started';
        };

        // Timeline flag — eksklusif (tepat satu label per baris) supaya angka tile
        // tidak saling tumpang tindih. Support yang sudah Completed tidak dihitung
        // sebagai overdue/ending soon karena pekerjaannya memang sudah selesai.
        $timelineOf = function ($s) use ($today, $soonLimit, $progressOf) {
            if (!$s->end_date)                 return 'none';
            if ($progressOf($s) >= 100)        return '';
            if ($s->end_date->lt($today))      return 'overdue';
            if ($s->end_date->lte($soonLimit)) return 'soon';
            if ($s->start_date && $s->start_date->gt($today)) return 'upcoming';
            return 'active';
        };

        $statTotal       = $supports->count();
        $statInProgress  = $supports->filter(fn($s) => $bucketOf($s) === 'in progress')->count();
        $statCompleted   = $supports->filter(fn($s) => $bucketOf($s) === 'completed')->count();
        $statNotStarted  = $supports->filter(fn($s) => $bucketOf($s) === 'not started')->count();
        $statSoon        = $supports->filter(fn($s) => $timelineOf($s) === 'soon')->count();
        $statOverdue     = $supports->filter(fn($s) => $timelineOf($s) === 'overdue')->count();
        $statUpcoming    = $supports->filter(fn($s) => $timelineOf($s) === 'upcoming')->count();
        $statNoTimeline  = $supports->filter(fn($s) => $timelineOf($s) === 'none')->count();
        $statClients     = $supports->map(fn($s) => $s->client->basicData->name_1 ?? null)->filter()->unique()->count();

        $statPct = fn($n) => $statTotal > 0 ? round($n / $statTotal * 100) : 0;

        // Opsi distinct untuk filter per-kolom (desktop)
        $clientOptions = $supports->map(fn($s) => $s->client->basicData->name_1 ?? null)->filter()->unique()->sort()->values();
        $typeOptions   = $supports->pluck('type')->filter()->unique()->sort()->values();
        $methodOptions = $supports->pluck('support_method')->filter()->unique()->sort()->values();
    @endphp

    {{-- ── Summary ─────────────────────────────────────────────────────────────
         Tiap tile mengklik filter yang sama dengan filter header kolom
         (supSetFilter), supaya angka tile dan isi tabel tidak pernah bercerita
         beda. --}}
    <div class="mb-6">
        <div class="flex items-baseline justify-between mb-3">
            <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Summary</h3>
            <span class="text-xs text-gray-400 hidden sm:inline">Click a card to filter the list</span>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            {{-- Total --}}
            <button type="button" class="stat-tile" data-stat-key="__all__" data-stat-value=""
                    title="Show all support items">
                <div class="flex items-center justify-between">
                    <span class="stat-icon bg-gray-100 text-gray-600"><i class="fas fa-headset"></i></span>
                    <span class="text-2xl font-bold text-gray-900">{{ $statTotal }}</span>
                </div>
                <p class="mt-2 text-sm font-medium text-gray-700">Total Delivery Support</p>
                <p class="text-xs text-gray-400">{{ $statClients }} clients</p>
            </button>
            {{-- Progress: In Progress --}}
            <button type="button" class="stat-tile" data-stat-key="bucket" data-stat-value="in progress">
                <div class="flex items-center justify-between">
                    <span class="stat-icon bg-blue-100 text-blue-700"><i class="fas fa-spinner"></i></span>
                    <span class="text-2xl font-bold text-gray-900">{{ $statInProgress }}</span>
                </div>
                <p class="mt-2 text-sm font-medium text-gray-700">In Progress</p>
                <p class="text-xs text-gray-400">{{ $statPct($statInProgress) }}% of total</p>
            </button>
            {{-- Progress: Completed --}}
            <button type="button" class="stat-tile" data-stat-key="bucket" data-stat-value="completed">
                <div class="flex items-center justify-between">
                    <span class="stat-icon bg-green-100 text-green-700"><i class="fas fa-check-circle"></i></span>
                    <span class="text-2xl font-bold text-gray-900">{{ $statCompleted }}</span>
                </div>
                <p class="mt-2 text-sm font-medium text-gray-700">Completed</p>
                <p class="text-xs text-gray-400">{{ $statPct($statCompleted) }}% of total</p>
            </button>
            {{-- Progress: Not Started --}}
            <button type="button" class="stat-tile" data-stat-key="bucket" data-stat-value="not started">
                <div class="flex items-center justify-between">
                    <span class="stat-icon bg-gray-100 text-gray-500"><i class="fas fa-hourglass-start"></i></span>
                    <span class="text-2xl font-bold text-gray-900">{{ $statNotStarted }}</span>
                </div>
                <p class="mt-2 text-sm font-medium text-gray-700">Not Started</p>
                <p class="text-xs text-gray-400">{{ $statPct($statNotStarted) }}% of total</p>
            </button>
            {{-- Timeline: ending soon --}}
            <button type="button" class="stat-tile" data-stat-key="timeline" data-stat-value="soon"
                    title="End date within 30 days (excluding completed)">
                <div class="flex items-center justify-between">
                    <span class="stat-icon bg-orange-100 text-orange-700"><i class="fas fa-hourglass-half"></i></span>
                    <span class="text-2xl font-bold text-gray-900">{{ $statSoon }}</span>
                </div>
                <p class="mt-2 text-sm font-medium text-gray-700">Ending &le; 30 days</p>
                <p class="text-xs text-gray-400">still open</p>
            </button>
            {{-- Timeline: overdue --}}
            <button type="button" class="stat-tile" data-stat-key="timeline" data-stat-value="overdue"
                    title="End date already passed and progress is below 100%">
                <div class="flex items-center justify-between">
                    <span class="stat-icon bg-red-100 text-red-700"><i class="fas fa-triangle-exclamation"></i></span>
                    <span class="text-2xl font-bold text-gray-900">{{ $statOverdue }}</span>
                </div>
                <p class="mt-2 text-sm font-medium text-gray-700">Past End Date</p>
                <p class="text-xs {{ $statOverdue > 0 ? 'text-red-600 font-medium' : 'text-gray-400' }}">not completed</p>
            </button>
            {{-- Timeline: upcoming --}}
            <button type="button" class="stat-tile" data-stat-key="timeline" data-stat-value="upcoming"
                    title="Start date is still in the future">
                <div class="flex items-center justify-between">
                    <span class="stat-icon bg-indigo-100 text-indigo-700"><i class="fas fa-calendar-plus"></i></span>
                    <span class="text-2xl font-bold text-gray-900">{{ $statUpcoming }}</span>
                </div>
                <p class="mt-2 text-sm font-medium text-gray-700">Upcoming</p>
                <p class="text-xs text-gray-400">not started yet</p>
            </button>
            {{-- Timeline: belum diisi --}}
            <button type="button" class="stat-tile" data-stat-key="timeline" data-stat-value="none"
                    title="End date is not filled in">
                <div class="flex items-center justify-between">
                    <span class="stat-icon bg-yellow-100 text-yellow-700"><i class="fas fa-calendar-xmark"></i></span>
                    <span class="text-2xl font-bold text-gray-900">{{ $statNoTimeline }}</span>
                </div>
                <p class="mt-2 text-sm font-medium text-gray-700">No Timeline</p>
                <p class="text-xs text-gray-400">end date empty</p>
            </button>
        </div>
    </div>

    <div class="bg-white shadow-sm rounded-lg overflow-hidden">
        {{-- Card Header --}}
        <div class="px-4 sm:px-6 py-4 bg-gray-50 border-b border-gray-200">
            <div class="flex justify-between items-center flex-wrap gap-4">
                <h3 class="text-lg font-medium text-gray-900">All Support Items</h3>
                @if($can('delivery-support.add-new'))
                <a href="{{ route('delivery.support.create') }}"
                   class="primary-gradient text-white font-bold py-2 px-4 rounded-lg hover:opacity-90 transition duration-300 text-sm">
                    <i class="fas fa-plus mr-2"></i>
                    Add Delivery Support
                </a>
                @endif
            </div>
        </div>

        {{-- Search Bar — selaras dengan style input pada Create form (rounded-lg + border eksplisit + padding seragam) --}}
        <div class="p-4">
            <input type="search" id="support-search"
                placeholder="Search by client, ticket ID, or description..."
                class="block w-full border border-gray-300 rounded-lg shadow-sm primary-focus transition text-sm px-4 py-2.5">
        </div>

        {{-- MOBILE VIEW: Card Layout --}}
        <div class="block lg:hidden px-4 pb-4">
            <div id="mobile-support-list" class="space-y-4">
                @forelse($supports as $support)
                    @php
                        $progress = $support->calculated_progress ?? 0;
                        $progressColor = $progress >= 100 ? '#10b981' : ($progress > 50 ? '#3b82f6' : ($progress > 0 ? '#f59e0b' : '#9ca3af'));
                    @endphp
                    <div class="support-card bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow cursor-pointer"
                         onclick="window.location.href='{{ route('delivery.support.show', $support->id) }}'"
                         data-client="{{ strtolower($support->client->basicData->name_1 ?? '') }}"
                         data-type="{{ strtolower($support->type ?? '') }}"
                         data-method="{{ strtolower($support->support_method ?? '') }}"
                         data-bucket="{{ $bucketOf($support) }}"
                         data-timeline="{{ $timelineOf($support) }}"
                         data-searchable="{{ strtolower(($support->client->basicData->name_1 ?? '') . ' ' . ($support->type ?? '') . ' ' . ($support->name ?? '')) }}">
                        <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <h4 class="text-base font-semibold text-gray-900">
                                        {{ $support->name ?? 'Support #' . $support->id }}
                                    </h4>
                                    <p class="text-sm text-gray-600 mt-1">{{ $support->client->basicData->name_1 ?? 'N/A' }}</p>
                                </div>
                                @if($support->type)
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                        {{ $support->type }}
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div class="px-4 py-3 space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-500">Progress</span>
                                <div class="flex items-center space-x-3">
                                    <div class="w-24 bg-gray-200 rounded-full h-2">
                                        <div class="h-2 rounded-full" style="width: {{ $progress }}%; background-color: {{ $progressColor }};"></div>
                                    </div>
                                    <span class="text-sm font-bold" style="color: {{ $progressColor }}">{{ number_format($progress, 1) }}%</span>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3 text-sm">
                                <div>
                                    <span class="text-gray-500">Start:</span>
                                    <p class="font-medium text-gray-900">{{ $support->start_date ? $support->start_date->format('d M Y') : 'N/A' }}</p>
                                </div>
                                <div>
                                    <span class="text-gray-500">End:</span>
                                    <p class="font-medium text-gray-900">{{ $support->end_date ? $support->end_date->format('d M Y') : 'N/A' }}</p>
                                </div>
                            </div>
                            <div class="pt-2 border-t border-gray-200">
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-gray-500">Support Method:</span>
                                    <span class="font-medium text-gray-900">{{ $support->support_method ?? 'N/A' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No support items</h3>
                        <p class="mt-1 text-sm text-gray-500">Get started by creating a new support item.</p>
                    </div>
                @endforelse
                <div id="mobile-no-results" class="hidden text-center py-8">
                    <p class="text-sm text-gray-500">No support items match your search.</p>
                </div>
            </div>
        </div>

        {{-- DESKTOP VIEW: Table Layout --}}
        <div class="hidden lg:block px-4 pb-4">
            <div class="table-container border border-gray-200 rounded-lg" style="max-height: 600px; overflow-y: auto;">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50 sticky top-0 z-10">
                        <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{-- Client: sort + searchable select filter --}}
                            <th class="sup-th relative bg-gray-50 border-b">
                                <button type="button" class="sup-th-btn" onclick="toggleSupPanel('sup-panel-client', this)">
                                    <span>Client</span>
                                    <span id="sup-sort-client" class="sup-sort-icon">⇅</span>
                                    @include('delivery.support.list.partials.sup-funnel', ['key' => 'client'])
                                </button>
                                <div id="sup-panel-client" class="sup-panel hidden">
                                    @include('delivery.support.list.partials.sup-sort-btns', ['key' => 'client'])
                                    <label class="sup-panel-label">Filter</label>
                                    <select onchange="supSetFilter('client', this.value)" class="sup-panel-input"
                                            data-searchable="true" data-search-placeholder="Search client...">
                                        <option value="">All Clients</option>
                                        @foreach($clientOptions as $c)<option value="{{ strtolower($c) }}">{{ $c }}</option>@endforeach
                                    </select>
                                    @include('delivery.support.list.partials.sup-clear', ['key' => 'client'])
                                </div>
                            </th>
                            {{-- Support Name: sort + keyword filter --}}
                            <th class="sup-th relative bg-gray-50 border-b">
                                <button type="button" class="sup-th-btn" onclick="toggleSupPanel('sup-panel-name', this)">
                                    <span>Support Name</span>
                                    <span id="sup-sort-name" class="sup-sort-icon">⇅</span>
                                    @include('delivery.support.list.partials.sup-funnel', ['key' => 'name'])
                                </button>
                                <div id="sup-panel-name" class="sup-panel hidden">
                                    @include('delivery.support.list.partials.sup-sort-btns', ['key' => 'name'])
                                    <label class="sup-panel-label">Search</label>
                                    <input type="text" oninput="supSetFilter('name', this.value)" placeholder="Type support name..." class="sup-panel-input">
                                    @include('delivery.support.list.partials.sup-clear', ['key' => 'name'])
                                </div>
                            </th>
                            {{-- Type: sort + select filter --}}
                            <th class="sup-th relative bg-gray-50 border-b">
                                <button type="button" class="sup-th-btn" onclick="toggleSupPanel('sup-panel-type', this)">
                                    <span>Type</span>
                                    <span id="sup-sort-type" class="sup-sort-icon">⇅</span>
                                    @include('delivery.support.list.partials.sup-funnel', ['key' => 'type'])
                                </button>
                                <div id="sup-panel-type" class="sup-panel hidden">
                                    @include('delivery.support.list.partials.sup-sort-btns', ['key' => 'type'])
                                    <label class="sup-panel-label">Filter</label>
                                    <select onchange="supSetFilter('type', this.value)" class="sup-panel-input">
                                        <option value="">All Types</option>
                                        @foreach($typeOptions as $t)<option value="{{ strtolower($t) }}">{{ $t }}</option>@endforeach
                                    </select>
                                    @include('delivery.support.list.partials.sup-clear', ['key' => 'type'])
                                </div>
                            </th>
                            {{-- Progress: sort (numerik) + filter bucket --}}
                            <th class="sup-th relative bg-gray-50 border-b">
                                <button type="button" class="sup-th-btn" onclick="toggleSupPanel('sup-panel-bucket', this)">
                                    <span>Progress</span>
                                    <span id="sup-sort-progress" class="sup-sort-icon">⇅</span>
                                    @include('delivery.support.list.partials.sup-funnel', ['key' => 'bucket'])
                                </button>
                                <div id="sup-panel-bucket" class="sup-panel hidden">
                                    @include('delivery.support.list.partials.sup-sort-btns', ['key' => 'progress'])
                                    <label class="sup-panel-label">Filter</label>
                                    <select onchange="supSetFilter('bucket', this.value)" class="sup-panel-input">
                                        <option value="">All Progress</option>
                                        <option value="not started">Not Started</option>
                                        <option value="in progress">In Progress</option>
                                        <option value="completed">Completed</option>
                                    </select>
                                    @include('delivery.support.list.partials.sup-clear', ['key' => 'bucket'])
                                </div>
                            </th>
                            {{-- Timeline: sort by end date + filter status timeline --}}
                            <th class="sup-th relative bg-gray-50 border-b">
                                <button type="button" class="sup-th-btn" onclick="toggleSupPanel('sup-panel-timeline', this)">
                                    <span>Timeline</span>
                                    <span id="sup-sort-timeline" class="sup-sort-icon">⇅</span>
                                    @include('delivery.support.list.partials.sup-funnel', ['key' => 'timeline'])
                                </button>
                                <div id="sup-panel-timeline" class="sup-panel hidden">
                                    @include('delivery.support.list.partials.sup-sort-btns', ['key' => 'timeline'])
                                    <label class="sup-panel-label">Filter</label>
                                    <select onchange="supSetFilter('timeline', this.value)" class="sup-panel-input">
                                        <option value="">All Timelines</option>
                                        <option value="active">Running</option>
                                        <option value="soon">Ending &le; 30 days</option>
                                        <option value="overdue">Past end date</option>
                                        <option value="upcoming">Upcoming</option>
                                        <option value="none">No timeline</option>
                                    </select>
                                    @include('delivery.support.list.partials.sup-clear', ['key' => 'timeline'])
                                </div>
                            </th>
                            {{-- Method: sort + select filter --}}
                            <th class="sup-th relative bg-gray-50 border-b">
                                <button type="button" class="sup-th-btn" onclick="toggleSupPanel('sup-panel-method', this)">
                                    <span>Method</span>
                                    <span id="sup-sort-method" class="sup-sort-icon">⇅</span>
                                    @include('delivery.support.list.partials.sup-funnel', ['key' => 'method'])
                                </button>
                                <div id="sup-panel-method" class="sup-panel hidden">
                                    @include('delivery.support.list.partials.sup-sort-btns', ['key' => 'method'])
                                    <label class="sup-panel-label">Filter</label>
                                    <select onchange="supSetFilter('method', this.value)" class="sup-panel-input">
                                        <option value="">All Methods</option>
                                        @foreach($methodOptions as $m)<option value="{{ strtolower($m) }}">{{ $m }}</option>@endforeach
                                    </select>
                                    @include('delivery.support.list.partials.sup-clear', ['key' => 'method'])
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200" id="desktop-support-table-body">
                        @forelse($supports as $support)
                            @php
                                $progress = $support->calculated_progress ?? 0;
                                $progressColor = $progress >= 100 ? '#10b981' : ($progress > 50 ? '#3b82f6' : ($progress > 0 ? '#f59e0b' : '#9ca3af'));
                            @endphp
                            <tr class="support-row hover:bg-gray-50 transition-colors cursor-pointer"
                                onclick="window.location.href='{{ route('delivery.support.show', $support->id) }}'"
                                data-client="{{ strtolower($support->client->basicData->name_1 ?? '') }}"
                                data-name="{{ strtolower($support->name ?? '') }}"
                                data-type="{{ strtolower($support->type ?? '') }}"
                                data-method="{{ strtolower($support->support_method ?? '') }}"
                                data-bucket="{{ $bucketOf($support) }}"
                                data-timeline="{{ $timelineOf($support) }}"
                                data-progress="{{ $progress }}"
                                data-end="{{ optional($support->end_date)->timestamp ?? 0 }}"
                                data-searchable="{{ strtolower(($support->client->basicData->name_1 ?? '') . ' ' . ($support->type ?? '') . ' ' . ($support->name ?? '')) }}">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ $support->client->basicData->name_1 ?? 'N/A' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    {{ $support->name ?? 'Support #' . $support->id }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($support->type)
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                            {{ $support->type }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-24 bg-gray-200 rounded-full h-2">
                                            <div class="h-2 rounded-full" style="width: {{ $progress }}%; background-color: {{ $progressColor }};"></div>
                                        </div>
                                        <span class="text-sm font-bold" style="color: {{ $progressColor }}">{{ number_format($progress, 1) }}%</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $support->start_date ? $support->start_date->format('d M') : '-' }} - {{ $support->end_date ? $support->end_date->format('d M Y') : '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $support->support_method ?? 'N/A' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    <h3 class="mt-2 text-sm font-medium text-gray-900">No support items</h3>
                                    <p class="mt-1 text-sm text-gray-500">Get started by creating a new support item.</p>
                                </td>
                            </tr>
                        @endforelse
                        <tr id="desktop-no-results-row" class="hidden">
                            <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                                No support items match your search.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    // ── Desktop header: per-column sort + filter, summary tiles, dan search bar
    // berbagi satu state. Fungsi-fungsi ini global karena markup header memakai
    // inline onclick/onchange (pola yang sama dengan Delivery Project list).
    const SUP_TEXT_FILTERS = ['name'];
    const SUP_SORT_KEYS    = ['client', 'name', 'type', 'progress', 'timeline', 'method'];
    const supState = { sortKey: null, sortDir: 'asc', filters: {} };

    function supSearchTerm() {
        const input = document.getElementById('support-search');
        return input ? input.value.toLowerCase().trim() : '';
    }

    // Satu baris/card lolos bila cocok search bar DAN semua filter aktif.
    function supMatches(el) {
        if (!(el.dataset.searchable || '').includes(supSearchTerm())) return false;
        const f = supState.filters;
        for (const key in f) {
            const val = f[key];
            // Card mobile tidak membawa semua data-* milik baris desktop (mis. name);
            // filter untuk key yang tidak ada diabaikan, bukan dianggap tidak cocok.
            if (!val || !(key in el.dataset)) continue;
            const data = el.dataset[key] || '';
            if (SUP_TEXT_FILTERS.includes(key)) { if (!data.includes(val)) return false; }
            else if (data !== val) return false;
        }
        return true;
    }

    function applySupportView() {
        const tbody = document.getElementById('desktop-support-table-body');
        if (tbody) {
            const rows = Array.from(tbody.querySelectorAll('tr.support-row'));
            const visible = [];
            rows.forEach(row => {
                const ok = supMatches(row);
                row.style.display = ok ? '' : 'none';
                if (ok) visible.push(row);
            });

            if (supState.sortKey) {
                const k = supState.sortKey;
                const dir = supState.sortDir === 'desc' ? -1 : 1;
                visible.sort((a, b) => {
                    if (k === 'progress') return ((Number(a.dataset.progress) || 0) - (Number(b.dataset.progress) || 0)) * dir;
                    // Timeline diurutkan berdasarkan end date, bukan teks labelnya.
                    if (k === 'timeline') return ((Number(a.dataset.end) || 0) - (Number(b.dataset.end) || 0)) * dir;
                    return (a.dataset[k] || '').localeCompare(b.dataset[k] || '', undefined, { numeric: true, sensitivity: 'base' }) * dir;
                });
                visible.forEach(r => tbody.appendChild(r));
            }

            const noRes = document.getElementById('desktop-no-results-row');
            if (noRes) { tbody.appendChild(noRes); noRes.classList.toggle('hidden', !(visible.length === 0 && rows.length > 0)); }
        }

        applySupportMobileView();
        updateSupIcons();
        updateStatTiles();
        const tc = document.querySelector('.table-container');
        if (tc) tc.scrollTop = 0;
    }

    function applySupportMobileView() {
        const cards = document.querySelectorAll('.support-card');
        if (!cards.length) return;
        let visible = 0;
        cards.forEach(card => {
            const ok = supMatches(card);
            card.style.display = ok ? '' : 'none';
            if (ok) visible++;
        });
        const noRes = document.getElementById('mobile-no-results');
        if (noRes) noRes.classList.toggle('hidden', !(visible === 0 && cards.length > 0));
    }

    function updateSupIcons() {
        SUP_SORT_KEYS.forEach(k => {
            const el = document.getElementById('sup-sort-' + k);
            if (!el) return;
            if (supState.sortKey === k) { el.textContent = supState.sortDir === 'asc' ? '↑' : '↓'; el.classList.add('active'); }
            else { el.textContent = '⇅'; el.classList.remove('active'); }
        });
        ['client', 'name', 'type', 'bucket', 'timeline', 'method'].forEach(k => {
            const fn = document.getElementById('sup-funnel-' + k);
            if (fn) fn.classList.toggle('active', !!supState.filters[k]);
        });
    }

    function supSort(key, dir) { supState.sortKey = key; supState.sortDir = dir; applySupportView(); closeAllSupPanels(); }
    function supSetFilter(key, value) { supState.filters[key] = (value || '').toLowerCase().trim(); applySupportView(); }
    function supClearFilter(key) {
        supState.filters[key] = '';
        const panel = document.getElementById('sup-panel-' + key);
        if (panel) panel.querySelectorAll('input, select').forEach(el => {
            el.value = '';
            // select-enhance.js merender label tombolnya dari event 'change';
            // tanpa dispatch ini label tetap memperlihatkan pilihan lama.
            if (el.tagName === 'SELECT') el.dispatchEvent(new Event('change', { bubbles: false }));
        });
        applySupportView();
    }

    // Fixed-position popovers so panels are never clipped by the scroll container.
    function closeAllSupPanels() { document.querySelectorAll('.sup-panel').forEach(p => p.classList.add('hidden')); }
    function toggleSupPanel(id, btn) {
        const panel = document.getElementById(id);
        if (!panel) return;
        const isOpen = !panel.classList.contains('hidden');
        closeAllSupPanels();
        if (isOpen) return;
        const r = btn.getBoundingClientRect();
        panel.style.position = 'fixed';
        panel.style.top = (r.bottom + 4) + 'px';
        panel.style.left = Math.min(r.left, window.innerWidth - 250) + 'px';
        panel.style.zIndex = 9999;
        panel.classList.remove('hidden');
    }
    document.addEventListener('click', function (e) {
        // .se-panel / .se-wrap: dropdown hasil select-enhance.js. Panelnya di-detach
        // ke <body> (mode fixed) sehingga bukan lagi turunan .sup-panel — tanpa
        // guard ini, klik di dalamnya menutup panel filter.
        if (e.target.closest('.sup-th-btn') || e.target.closest('.sup-panel')
            || e.target.closest('.se-panel') || e.target.closest('.se-wrap')) return;
        closeAllSupPanels();
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeAllSupPanels(); });

    // ── Summary tiles ────────────────────────────────────────────────────────
    function updateStatTiles() {
        document.querySelectorAll('.stat-tile').forEach(tile => {
            const key = tile.dataset.statKey;
            const val = tile.dataset.statValue || '';
            const active = key === '__all__'
                ? Object.values(supState.filters).every(v => !v)
                : supState.filters[key] === val;
            tile.classList.toggle('is-active', !!active);
        });
    }

    function supStatTileClick(tile) {
        const key = tile.dataset.statKey;
        const val = tile.dataset.statValue || '';
        if (key === '__all__') {
            Object.keys(supState.filters).forEach(k => supClearFilter(k));
            applySupportView();
            return;
        }
        // Klik ulang tile yang sedang aktif = lepas filter-nya.
        const next = supState.filters[key] === val ? '' : val;
        supState.filters[key] = next;
        // Samakan select di panel header supaya angka tile dan kontrol kolom
        // tidak menampilkan dua kebenaran berbeda.
        const panel = document.getElementById('sup-panel-' + key);
        if (panel) panel.querySelectorAll('select').forEach(sel => {
            sel.value = next;
            sel.dispatchEvent(new Event('change', { bubbles: false }));
        });
        applySupportView();
    }

    document.addEventListener('DOMContentLoaded', function () {
        const searchInput = document.getElementById('support-search');
        if (searchInput) {
            searchInput.addEventListener('input', applySupportView);
            searchInput.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') { this.value = ''; applySupportView(); }
            });
        }

        document.querySelectorAll('.stat-tile').forEach(tile => {
            tile.addEventListener('click', () => supStatTileClick(tile));
        });
        updateStatTiles();
    });
</script>
@endsection

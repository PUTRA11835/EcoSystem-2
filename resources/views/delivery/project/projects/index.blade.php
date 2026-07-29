@extends('dashboard')
@section('title', 'Project Delivery')
@section('page-title', 'Project Delivery')
@section('page-subtitle', 'Manage all delivery projects')
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
</style>
@endpush

@section('content')
    {{-- Sub Navigation Tabs --}}
    <div class="mb-6 bg-white rounded-lg shadow-sm border border-gray-200">
        <nav class="flex space-x-1 p-1" aria-label="Tabs">
            <a href="{{ route('projects.index') }}"
               class="flex-1 sm:flex-none px-4 py-2.5 text-sm font-medium text-center rounded-lg transition-all
                      {{ Request::routeIs('projects.index') ? 'primary-gradient text-white shadow-sm' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100' }}">
                <i class="fas fa-project-diagram mr-2"></i>
                <span>Project</span>
            </a>
            <a href="{{ route('issues.index') }}"
               class="flex-1 sm:flex-none px-4 py-2.5 text-sm font-medium text-center rounded-lg transition-all
                      {{ Request::routeIs('issues.*') ? 'primary-gradient text-white shadow-sm' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100' }}">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <span>Issues</span>
            </a>
            <a href="{{ route('planning.index') }}"
               class="flex-1 sm:flex-none px-4 py-2.5 text-sm font-medium text-center rounded-lg transition-all
                      {{ Request::routeIs('planning.*') ? 'primary-gradient text-white shadow-sm' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100' }}">
                <i class="fas fa-tasks mr-2"></i>
                <span>Planning</span>
            </a>
        </nav>
    </div>

    {{-- Flash Notifications --}}
    @if(session('success'))
        <script>document.addEventListener('DOMContentLoaded',()=>showNotification(@json(session('success')),'success'));</script>
    @endif
    @if(session('error'))
        <script>document.addEventListener('DOMContentLoaded',()=>showNotification(@json(session('error')),'error'));</script>
    @endif
    {{-- ── Summary ──────────────────────────────────────────────────────────────
         Dihitung dari koleksi $projects yang memang sudah dimuat penuh untuk
         tabel, jadi tidak ada query tambahan. Setiap tile mengklik filter yang
         sama dengan filter header kolom (projSetFilter), supaya angka dan isi
         tabel tidak pernah bercerita beda. --}}
    @php
        $today     = \Illuminate\Support\Carbon::today();
        $soonLimit = $today->copy()->addDays(30);

        $statTotal      = $projects->count();
        $statOpen       = $projects->where('category', 'Open')->count();
        $statInProcess  = $projects->where('category', 'In Process')->count();
        $statClosed     = $projects->where('category', 'Closed')->count();
        $statOnTrack    = $projects->where('status', 'On Track')->count();
        $statAtRisk     = $projects->where('status', 'At Risk')->count();
        $statAtCritical = $projects->where('status', 'At Critical')->count();
        $statCustomers  = $projects->map(fn($p) => $p->client->basicData->name_1 ?? null)->filter()->unique()->count();

        // Deadline kontrak — hanya untuk project yang belum Closed.
        $statEndingSoon = $projects->filter(fn($p) => ($p->category ?? '') !== 'Closed' && $p->contract_end_date
                            && $p->contract_end_date->gte($today) && $p->contract_end_date->lte($soonLimit))->count();
        $statOverdue    = $projects->filter(fn($p) => ($p->category ?? '') !== 'Closed' && $p->contract_end_date
                            && $p->contract_end_date->lt($today))->count();

        $statPct = fn($n) => $statTotal > 0 ? round($n / $statTotal * 100) : 0;

        // Dipakai baris tabel & card mobile sebagai data-deadline, supaya tile
        // "Ending <= 30 days" bisa memakai mesin filter generik yang sama.
        $projectDeadlineFlag = function ($p) use ($today, $soonLimit) {
            if (($p->category ?? '') === 'Closed' || !$p->contract_end_date) return '';
            if ($p->contract_end_date->lt($today)) return 'overdue';
            return $p->contract_end_date->lte($soonLimit) ? 'soon' : '';
        };
    @endphp
    <div class="mb-6">
        <div class="flex items-baseline justify-between mb-3">
            <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Summary</h3>
            <span class="text-xs text-gray-400 hidden sm:inline">Click a card to filter the list</span>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            {{-- Total --}}
            <button type="button" class="stat-tile" data-stat-key="__all__" data-stat-value=""
                    title="Show all projects">
                <div class="flex items-center justify-between">
                    <span class="stat-icon bg-gray-100 text-gray-600"><i class="fas fa-project-diagram"></i></span>
                    <span class="text-2xl font-bold text-gray-900">{{ $statTotal }}</span>
                </div>
                <p class="mt-2 text-sm font-medium text-gray-700">Total Projects</p>
                <p class="text-xs text-gray-400">{{ $statCustomers }} customers</p>
            </button>
            {{-- Category: Open --}}
            <button type="button" class="stat-tile" data-stat-key="category" data-stat-value="open">
                <div class="flex items-center justify-between">
                    <span class="stat-icon bg-yellow-100 text-yellow-700"><i class="fas fa-folder-open"></i></span>
                    <span class="text-2xl font-bold text-gray-900">{{ $statOpen }}</span>
                </div>
                <p class="mt-2 text-sm font-medium text-gray-700">Open</p>
                <p class="text-xs text-gray-400">{{ $statPct($statOpen) }}% of total</p>
            </button>
            {{-- Category: In Process --}}
            <button type="button" class="stat-tile" data-stat-key="category" data-stat-value="in process">
                <div class="flex items-center justify-between">
                    <span class="stat-icon bg-blue-100 text-blue-700"><i class="fas fa-spinner"></i></span>
                    <span class="text-2xl font-bold text-gray-900">{{ $statInProcess }}</span>
                </div>
                <p class="mt-2 text-sm font-medium text-gray-700">In Process</p>
                <p class="text-xs text-gray-400">{{ $statPct($statInProcess) }}% of total</p>
            </button>
            {{-- Category: Closed --}}
            <button type="button" class="stat-tile" data-stat-key="category" data-stat-value="closed">
                <div class="flex items-center justify-between">
                    <span class="stat-icon bg-green-100 text-green-700"><i class="fas fa-check-circle"></i></span>
                    <span class="text-2xl font-bold text-gray-900">{{ $statClosed }}</span>
                </div>
                <p class="mt-2 text-sm font-medium text-gray-700">Closed</p>
                <p class="text-xs text-gray-400">{{ $statTotal - $statClosed }} still active</p>
            </button>
            {{-- Status (SPI): On Track --}}
            <button type="button" class="stat-tile" data-stat-key="status" data-stat-value="on track">
                <div class="flex items-center justify-between">
                    <span class="stat-icon bg-green-100 text-green-700"><i class="fas fa-thumbs-up"></i></span>
                    <span class="text-2xl font-bold text-gray-900">{{ $statOnTrack }}</span>
                </div>
                <p class="mt-2 text-sm font-medium text-gray-700">On Track</p>
                <p class="text-xs text-gray-400">{{ $statPct($statOnTrack) }}% of total</p>
            </button>
            {{-- Status (SPI): At Risk --}}
            <button type="button" class="stat-tile" data-stat-key="status" data-stat-value="at risk">
                <div class="flex items-center justify-between">
                    <span class="stat-icon bg-yellow-100 text-yellow-700"><i class="fas fa-exclamation-triangle"></i></span>
                    <span class="text-2xl font-bold text-gray-900">{{ $statAtRisk }}</span>
                </div>
                <p class="mt-2 text-sm font-medium text-gray-700">At Risk</p>
                <p class="text-xs text-gray-400">{{ $statPct($statAtRisk) }}% of total</p>
            </button>
            {{-- Status (SPI): At Critical --}}
            <button type="button" class="stat-tile" data-stat-key="status" data-stat-value="at critical">
                <div class="flex items-center justify-between">
                    <span class="stat-icon bg-red-100 text-red-700"><i class="fas fa-fire"></i></span>
                    <span class="text-2xl font-bold text-gray-900">{{ $statAtCritical }}</span>
                </div>
                <p class="mt-2 text-sm font-medium text-gray-700">At Critical</p>
                <p class="text-xs text-gray-400">{{ $statPct($statAtCritical) }}% of total</p>
            </button>
            {{-- Deadline kontrak --}}
            <button type="button" class="stat-tile" data-stat-key="deadline" data-stat-value="soon"
                    title="Contract ends within 30 days (excluding Closed)">
                <div class="flex items-center justify-between">
                    <span class="stat-icon bg-orange-100 text-orange-700"><i class="fas fa-hourglass-half"></i></span>
                    <span class="text-2xl font-bold text-gray-900">{{ $statEndingSoon }}</span>
                </div>
                <p class="mt-2 text-sm font-medium text-gray-700">Ending &le; 30 days</p>
                <p class="text-xs {{ $statOverdue > 0 ? 'text-red-600 font-medium' : 'text-gray-400' }}">
                    {{ $statOverdue }} past contract end
                </p>
            </button>
        </div>
    </div>

    <div class="bg-white shadow-sm rounded-lg overflow-hidden">
        {{-- Card Header --}}
        <div class="px-4 sm:px-6 py-4 bg-gray-50 border-b border-gray-200">
            <div class="flex justify-between items-center flex-wrap gap-4">
                <h3 class="text-lg font-medium text-gray-900">
                    All Delivery Projects
                </h3>
                @if($can('delivery-project.add-new'))
                <a href="{{ route('projects.create') }}"
                   class="primary-gradient text-white font-bold py-2 px-4 rounded-lg hover:opacity-90 transition duration-300 text-sm sm:text-base">
                    <span class="hidden sm:inline">Add New</span>
                    <span class="sm:hidden">+ Add</span>
                </a>
                @endif
            </div>
        </div>
        {{-- Distinct option lists for the per-column header filters (desktop) --}}
        @php
            $typeOptions     = $projects->pluck('project_type')->filter()->unique()->sort()->values();
            $aeOptions       = $projects->pluck('ae_name')->filter()->unique()->sort()->values();
            $ownerOptions    = $projects->pluck('project_owner')->filter()->unique()->sort()->values();
            $customerOptions = $projects->map(fn($p) => $p->client->basicData->name_1 ?? null)->filter()->unique()->sort()->values();
        @endphp
        {{-- Mobile-only search. On desktop, filtering & sorting live in the table
             header (like the Support Tickets list). --}}
        <div class="p-4 lg:hidden">
            <input type="search" id="project-search"
                placeholder="Search by IO, customer, type, project name, AE, or owner..."
                class="block w-full border border-gray-300 rounded-lg shadow-sm primary-focus transition text-sm px-4 py-2.5">
        </div>
        {{-- MOBILE VIEW: Card Layout --}}
        <div class="block lg:hidden px-4 pb-4">
            <div id="mobile-project-list" class="space-y-4">
                @forelse($projects as $project)
                    @php $deadlineFlag = $projectDeadlineFlag($project); @endphp
                    <div class="project-card bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow cursor-pointer"
                         onclick="window.location.href='{{ route('projects.show', $project->id) }}'"
                         data-type="{{ strtolower($project->project_type ?? '') }}"
                         data-category="{{ strtolower($project->category ?? '') }}"
                         data-status="{{ strtolower($project->status ?? '') }}"
                         data-deadline="{{ $deadlineFlag }}"
                         data-ae="{{ strtolower($project->ae_name ?? '') }}"
                         data-customer="{{ strtolower($project->client->basicData->name_1 ?? '') }}"
                         data-searchable-content="{{ strtolower(($project->io_number ?? '') . ' ' . ($project->client->basicData->name_1 ?? '') . ' ' . $project->project_type . ' ' . $project->name . ' ' . ($project->ae_name ?? '') . ' ' . $project->project_owner . ' ' . $project->status . ' ' . $project->category) }}">
                        {{-- Card Header --}}
                        <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <h4 class="text-base font-semibold text-gray-900">
                                        {{ $project->client->basicData->name_1 ?? 'N/A' }}
                                    </h4>
                                    <p class="text-sm text-gray-600 mt-1">{{ $project->project_type }}</p>
                                </div>
                            </div>
                        </div>
                        {{-- Card Body --}}
                        <div class="px-4 py-3 space-y-3">
                            {{-- IO Number --}}
                            <div>
                                <p class="text-sm text-gray-500">IO Number:</p>
                                <p class="text-sm font-medium text-gray-900 mt-1">{{ $project->io_number ?? 'N/A' }}</p>
                            </div>
                            {{-- Project Name --}}
                            <div>
                                <p class="text-sm text-gray-500">Project Name:</p>
                                <p class="text-sm font-medium text-gray-900 mt-1">{{ $project->name }}</p>
                            </div>
                            {{-- Account Executive --}}
                            <div>
                                <p class="text-sm text-gray-500">AE:</p>
                                <p class="text-sm font-medium text-gray-900 mt-1">{{ $project->ae_name ?? 'N/A' }}</p>
                            </div>
                            {{-- Project Owner --}}
                            <div>
                                <p class="text-sm text-gray-500">Project Owner:</p>
                                <p class="text-sm font-medium text-gray-900 mt-1">{{ $project->project_owner ?? 'N/A' }}</p>
                            </div>
                            {{-- Status Badges --}}
                            <div class="flex flex-wrap gap-2">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                    {{ $project->category === 'Open' ? 'bg-yellow-100 text-yellow-800' :
                                       ($project->category === 'In Process' ? 'bg-blue-100 text-blue-800' : 
                                       ($project->category === 'Closed' ? 'bg-green-100 text-green-800' :'bg-blue-100 text-blue-800')) }}">
                                    {{ $project->category ?? 'N/A' }}
                                </span>
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                    {{ $project->status === 'On Track' ? 'bg-green-100 text-green-800' :
                                       ($project->status === 'At Risk' ? 'bg-yellow-100 text-yellow-800' :
                                       ($project->status === 'At Critical' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800')) }}">
                                    {{ $project->status ?? 'N/A' }}
                                </span>
                            </div>
                            {{-- Last Update --}}
                            <div class="pt-2 border-t border-gray-200">
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-gray-500">Last Update:</span>
                                    <span class="font-medium text-gray-900">{{ $project->updated_at->format('d M Y') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No project data</h3>
                        <p class="mt-1 text-sm text-gray-500">Start by adding a new project.</p>
                    </div>
                @endforelse
                {{-- No Results Message --}}
                <div id="mobile-no-results" class="hidden text-center py-8">
                    <p class="text-sm text-gray-500">No projects match your search.</p>
                </div>
            </div>
        </div>
        
        {{-- DESKTOP VIEW: Table Layout dengan Fixed Height dan Scroll --}}
        <div class="hidden lg:block px-4 pb-4">
            {{-- Table Container dengan scroll --}}
            <div class="table-container border border-gray-200 rounded-lg" 
                 style="height: 500px; max-height: 500px; overflow-y: scroll; overflow-x: auto;">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50 sticky top-0 z-10">
                        <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{-- IO Number: sort + keyword filter --}}
                            <th class="proj-th relative bg-gray-50 border-b">
                                <button type="button" class="proj-th-btn" onclick="toggleProjPanel('proj-panel-io', this)">
                                    <span>IO Number</span>
                                    <span id="proj-sort-io" class="proj-sort-icon">⇅</span>
                                    @include('delivery.project.projects.partials.proj-funnel', ['key' => 'io'])
                                </button>
                                <div id="proj-panel-io" class="proj-panel hidden">
                                    @include('delivery.project.projects.partials.proj-sort-btns', ['key' => 'io'])
                                    <label class="proj-panel-label">Search</label>
                                    <input type="text" oninput="projSetFilter('io', this.value)" placeholder="Type IO number…" class="proj-panel-input">
                                    @include('delivery.project.projects.partials.proj-clear', ['key' => 'io'])
                                </div>
                            </th>
                            {{-- Customer: sort + select filter --}}
                            <th class="proj-th relative bg-gray-50 border-b">
                                <button type="button" class="proj-th-btn" onclick="toggleProjPanel('proj-panel-customer', this)">
                                    <span>Customer</span>
                                    <span id="proj-sort-customer" class="proj-sort-icon">⇅</span>
                                    @include('delivery.project.projects.partials.proj-funnel', ['key' => 'customer'])
                                </button>
                                <div id="proj-panel-customer" class="proj-panel hidden">
                                    @include('delivery.project.projects.partials.proj-sort-btns', ['key' => 'customer'])
                                    <label class="proj-panel-label">Filter</label>
                                    <select onchange="projSetFilter('customer', this.value)" class="proj-panel-input"
                                            data-searchable="true" data-search-placeholder="Search customer...">
                                        <option value="">All Customers</option>
                                        @foreach($customerOptions as $c)<option value="{{ strtolower($c) }}">{{ $c }}</option>@endforeach
                                    </select>
                                    @include('delivery.project.projects.partials.proj-clear', ['key' => 'customer'])
                                </div>
                            </th>
                            {{-- Project Type: sort + select filter --}}
                            <th class="proj-th relative bg-gray-50 border-b">
                                <button type="button" class="proj-th-btn" onclick="toggleProjPanel('proj-panel-type', this)">
                                    <span>Project Type</span>
                                    <span id="proj-sort-type" class="proj-sort-icon">⇅</span>
                                    @include('delivery.project.projects.partials.proj-funnel', ['key' => 'type'])
                                </button>
                                <div id="proj-panel-type" class="proj-panel hidden">
                                    @include('delivery.project.projects.partials.proj-sort-btns', ['key' => 'type'])
                                    <label class="proj-panel-label">Filter</label>
                                    <select onchange="projSetFilter('type', this.value)" class="proj-panel-input">
                                        <option value="">All Types</option>
                                        @foreach($typeOptions as $t)<option value="{{ strtolower($t) }}">{{ $t }}</option>@endforeach
                                    </select>
                                    @include('delivery.project.projects.partials.proj-clear', ['key' => 'type'])
                                </div>
                            </th>
                            {{-- Project Name: sort + keyword filter --}}
                            <th class="proj-th relative bg-gray-50 border-b">
                                <button type="button" class="proj-th-btn" onclick="toggleProjPanel('proj-panel-name', this)">
                                    <span>Project Name</span>
                                    <span id="proj-sort-name" class="proj-sort-icon">⇅</span>
                                    @include('delivery.project.projects.partials.proj-funnel', ['key' => 'name'])
                                </button>
                                <div id="proj-panel-name" class="proj-panel hidden">
                                    @include('delivery.project.projects.partials.proj-sort-btns', ['key' => 'name'])
                                    <label class="proj-panel-label">Search</label>
                                    <input type="text" oninput="projSetFilter('name', this.value)" placeholder="Type project name…" class="proj-panel-input">
                                    @include('delivery.project.projects.partials.proj-clear', ['key' => 'name'])
                                </div>
                            </th>
                            {{-- AE: sort + select filter --}}
                            <th class="proj-th relative bg-gray-50 border-b">
                                <button type="button" class="proj-th-btn" onclick="toggleProjPanel('proj-panel-ae', this)">
                                    <span>AE</span>
                                    <span id="proj-sort-ae" class="proj-sort-icon">⇅</span>
                                    @include('delivery.project.projects.partials.proj-funnel', ['key' => 'ae'])
                                </button>
                                <div id="proj-panel-ae" class="proj-panel hidden">
                                    @include('delivery.project.projects.partials.proj-sort-btns', ['key' => 'ae'])
                                    <label class="proj-panel-label">Filter</label>
                                    <select onchange="projSetFilter('ae', this.value)" class="proj-panel-input">
                                        <option value="">All AE</option>
                                        @foreach($aeOptions as $a)<option value="{{ strtolower($a) }}">{{ $a }}</option>@endforeach
                                    </select>
                                    @include('delivery.project.projects.partials.proj-clear', ['key' => 'ae'])
                                </div>
                            </th>
                            {{-- Project Owner: sort + select filter --}}
                            <th class="proj-th relative bg-gray-50 border-b">
                                <button type="button" class="proj-th-btn" onclick="toggleProjPanel('proj-panel-owner', this)">
                                    <span>Project Owner</span>
                                    <span id="proj-sort-owner" class="proj-sort-icon">⇅</span>
                                    @include('delivery.project.projects.partials.proj-funnel', ['key' => 'owner'])
                                </button>
                                <div id="proj-panel-owner" class="proj-panel hidden">
                                    @include('delivery.project.projects.partials.proj-sort-btns', ['key' => 'owner'])
                                    <label class="proj-panel-label">Filter</label>
                                    <select onchange="projSetFilter('owner', this.value)" class="proj-panel-input">
                                        <option value="">All Owners</option>
                                        @foreach($ownerOptions as $o)<option value="{{ strtolower($o) }}">{{ $o }}</option>@endforeach
                                    </select>
                                    @include('delivery.project.projects.partials.proj-clear', ['key' => 'owner'])
                                </div>
                            </th>
                            {{-- Category: sort + select filter --}}
                            <th class="proj-th relative bg-gray-50 border-b">
                                <button type="button" class="proj-th-btn" onclick="toggleProjPanel('proj-panel-category', this)">
                                    <span>Category</span>
                                    <span id="proj-sort-category" class="proj-sort-icon">⇅</span>
                                    @include('delivery.project.projects.partials.proj-funnel', ['key' => 'category'])
                                </button>
                                <div id="proj-panel-category" class="proj-panel hidden">
                                    @include('delivery.project.projects.partials.proj-sort-btns', ['key' => 'category'])
                                    <label class="proj-panel-label">Filter</label>
                                    <select onchange="projSetFilter('category', this.value)" class="proj-panel-input">
                                        <option value="">All Categories</option>
                                        <option value="open">Open</option>
                                        <option value="in process">In Process</option>
                                        <option value="closed">Closed</option>
                                    </select>
                                    @include('delivery.project.projects.partials.proj-clear', ['key' => 'category'])
                                </div>
                            </th>
                            {{-- Project Status: sort + select filter --}}
                            <th class="proj-th relative bg-gray-50 border-b">
                                <button type="button" class="proj-th-btn" onclick="toggleProjPanel('proj-panel-status', this)">
                                    <span>Project Status</span>
                                    <span id="proj-sort-status" class="proj-sort-icon">⇅</span>
                                    @include('delivery.project.projects.partials.proj-funnel', ['key' => 'status'])
                                </button>
                                <div id="proj-panel-status" class="proj-panel hidden">
                                    @include('delivery.project.projects.partials.proj-sort-btns', ['key' => 'status'])
                                    <label class="proj-panel-label">Filter</label>
                                    <select onchange="projSetFilter('status', this.value)" class="proj-panel-input">
                                        <option value="">All Statuses</option>
                                        <option value="on track">On Track</option>
                                        <option value="at risk">At Risk</option>
                                        <option value="at critical">At Critical</option>
                                    </select>
                                    @include('delivery.project.projects.partials.proj-clear', ['key' => 'status'])
                                </div>
                            </th>
                            {{-- Last Update Date: direct sort toggle --}}
                            <th class="proj-th bg-gray-50 border-b">
                                <button type="button" class="proj-th-btn" onclick="projToggleSort('updated')" title="Sort by Last Update">
                                    <span>Last Update Date</span>
                                    <span id="proj-sort-updated" class="proj-sort-icon">⇅</span>
                                </button>
                            </th>
                        </tr>
                    </thead>
                        <tbody id="desktop-project-table-body" class="bg-white divide-y divide-gray-200">
                            @forelse($projects as $project)
                                @php $deadlineFlag = $projectDeadlineFlag($project); @endphp
                                <tr class="project-row hover:bg-gray-50 transition-colors cursor-pointer"
                                    onclick="window.location.href='{{ route('projects.show', $project->id) }}'"
                                    data-deadline="{{ $deadlineFlag }}"
                                    data-io="{{ strtolower($project->io_number ?? '') }}"
                                    data-name="{{ strtolower($project->name ?? '') }}"
                                    data-type="{{ strtolower($project->project_type ?? '') }}"
                                    data-category="{{ strtolower($project->category ?? '') }}"
                                    data-status="{{ strtolower($project->status ?? '') }}"
                                    data-ae="{{ strtolower($project->ae_name ?? '') }}"
                                    data-owner="{{ strtolower($project->project_owner ?? '') }}"
                                    data-customer="{{ strtolower($project->client->basicData->name_1 ?? '') }}"
                                    data-updated="{{ optional($project->updated_at)->timestamp ?? 0 }}"
                                    data-searchable-content="{{ strtolower(($project->io_number ?? '') . ' ' . ($project->client->basicData->name_1 ?? '') . ' ' . $project->project_type . ' ' . $project->name . ' ' . ($project->ae_name ?? '') . ' ' . $project->project_owner . ' ' . $project->status . ' ' . $project->category) }}">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $project->io_number ?? 'N/A' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            {{ $project->client->basicData->name_1 ?? 'N/A' }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $project->project_type }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        <div class="max-w-xs truncate" title="{{ $project->name }}">
                                            {{ $project->name }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $project->ae_name ?? 'N/A' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $project->project_owner ?? 'N/A' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                            {{ $project->category === 'Open' ? 'bg-yellow-100 text-yellow-800' :
                                               ($project->category === 'In Process' ? 'bg-blue-100 text-blue-800' :
                                               ($project->category === 'Closed' ? 'bg-green-100 text-green-800' :'bg-blue-100 text-blue-800')) }}">
                                            {{ $project->category ?? 'N/A' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                            {{ $project->status === 'On Track' ? 'bg-green-100 text-green-800' :
                                               ($project->status === 'At Risk' ? 'bg-yellow-100 text-yellow-800' :
                                               ($project->status === 'At Critical' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800')) }}">
                                            {{ $project->status ?? 'N/A' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $project->updated_at->format('d M Y') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-6 py-12 text-center">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                        <h3 class="mt-2 text-sm font-medium text-gray-900">No project data</h3>
                                        <p class="mt-1 text-sm text-gray-500">Start by adding a new project.</p>
                                    </td>
                                </tr>
                            @endforelse
                            {{-- No Results Row --}}
                            <tr id="desktop-no-results-row" class="hidden">
                                <td colspan="9" class="px-6 py-4 text-center text-sm text-gray-500">
                                    No projects match your search.
                                </td>
                            </tr>
                        </tbody>
                </table>
            </div>
        </div>
    </div>

    <style>
        /* Smooth scroll behavior */
        html {
            scroll-behavior: smooth;
        }

        /* Fixed Table Container Styles untuk Desktop */
        @media (min-width: 1024px) {
            /* Pastikan container table memiliki scrollbar */
            .table-container {
                position: relative;
                max-height: 600px !important;
                height: 600px !important;
                overflow-y: auto !important;
                overflow-x: auto !important;
            }

            /* Sticky header */
            .table-container thead th {
                position: sticky;
                top: 0;
                z-index: 20;
                background-color: #f9fafb;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }

            /* Custom scrollbar untuk table container */
            .table-container::-webkit-scrollbar {
                width: 12px;
                height: 12px;
            }

            .table-container::-webkit-scrollbar-track {
                background: #f3f4f6;
                border-radius: 6px;
            }

            .table-container::-webkit-scrollbar-thumb {
                background: #9ca3af;
                border-radius: 6px;
                border: 2px solid #f3f4f6;
            }

            .table-container::-webkit-scrollbar-thumb:hover {
                background: #6b7280;
            }

            .table-container::-webkit-scrollbar-corner {
                background: #f3f4f6;
            }

            /* Ensure table takes full width */
            .table-container table {
                width: 100%;
                min-width: 100%;
            }

            /* Firefox scrollbar */
            .table-container {
                scrollbar-width: thin;
                scrollbar-color: #9ca3af #f3f4f6;
            }
        }

        /* Card animation on mobile */
        @media (max-width: 1023px) {
            .project-card {
                animation: fadeIn 0.3s ease-in;
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Mobile view specific styles */
        @media (max-width: 1023px) {
            .project-card {
                touch-action: manipulation;
            }
        }

        /* Status badges styling */
        .inline-flex {
            width: fit-content;
        }

        /* Row hover effect */
        .table-container tbody tr:hover {
            background-color: #f9fafb;
        }

        /* Ensure the table container is visible */
        .table-container {
            display: block !important;
            visibility: visible !important;
        }

        /* ── Per-column header sort + filter (ticket-list style) ─────────────── */
        .proj-th { padding: 0; }
        .proj-th-btn { width: 100%; display: flex; align-items: center; gap: 0.375rem; padding: 0.75rem 1.5rem; cursor: pointer; background: transparent; text-align: left; }
        .proj-th-btn:hover { background: #f3f4f6; }
        .proj-sort-icon { color: #d1d5db; font-weight: 400; text-transform: none; letter-spacing: normal; font-size: 0.8rem; }
        .proj-sort-icon.active { color: #ef4444; font-weight: 700; }
        .proj-funnel { width: 0.8rem; height: 0.8rem; color: #d1d5db; margin-left: auto; flex-shrink: 0; }
        .proj-funnel.active { color: #ef4444; }
        .proj-panel { background: #fff; border: 1px solid #f3f4f6; border-radius: 0.75rem; box-shadow: 0 10px 25px rgba(0,0,0,0.15); padding: 0.75rem; min-width: 220px; text-transform: none; letter-spacing: normal; }
        .proj-panel-label { display: block; font-size: 11px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.03em; margin-bottom: 0.25rem; }
        .proj-panel-input { width: 100%; padding: 0.375rem 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem; font-size: 0.875rem; font-weight: 400; text-transform: none; color: #374151; background: #fff; }
        .proj-panel-input:focus { outline: none; border-color: #f87171; box-shadow: 0 0 0 3px rgba(254,202,202,0.5); }
        .proj-sort-btn { flex: 1; padding: 0.375rem 0.5rem; font-size: 0.75rem; color: #4b5563; border: 1px solid #e5e7eb; border-radius: 0.375rem; background: #fff; cursor: pointer; }
        .proj-sort-btn:hover { background: #f9fafb; }
        .proj-clear-btn { margin-top: 0.75rem; width: 100%; padding: 0.375rem 0.5rem; font-size: 0.75rem; color: #4b5563; border: 1px solid #e5e7eb; border-radius: 0.375rem; background: #fff; cursor: pointer; }
        .proj-clear-btn:hover { background: #f9fafb; }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('project-search');
            const tableContainer = document.querySelector('.table-container');
            
            // Adjust table container height based on viewport (optional)
            function adjustTableHeight() {
                if (window.innerWidth >= 1024 && tableContainer) {
                    // Calculate available height
                    const windowHeight = window.innerHeight;
                    const containerTop = tableContainer.getBoundingClientRect().top;
                    const footerSpace = 50; // Space for footer/padding
                    const calculatedHeight = windowHeight - containerTop - footerSpace;
                    
                    // Set height between 400px and 700px
                    const finalHeight = Math.min(700, Math.max(400, calculatedHeight));
                    tableContainer.style.height = finalHeight + 'px';
                    tableContainer.style.maxHeight = finalHeight + 'px';
                }
            }

            // Call on load and resize
            adjustTableHeight();
            window.addEventListener('resize', adjustTableHeight);

            // Mobile-only search (filters the cards). Desktop uses the per-column
            // header sort/filter controls (see global functions below).
            if (searchInput) {
                searchInput.addEventListener('input', applyMobileView);
                searchInput.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape') { searchInput.value = ''; applyMobileView(); }
                });
            }
            
            // Prevent double-tap zoom on mobile for buttons
            let lastTouchEnd = 0;
            document.addEventListener('touchend', function(event) {
                const now = Date.now();
                if (now - lastTouchEnd <= 300) {
                    event.preventDefault();
                }
                lastTouchEnd = now;
            }, false);

            // Add scroll to top on double-click header
            const tableHeader = document.querySelector('.table-container thead');
            if (tableHeader) {
                tableHeader.addEventListener('dblclick', function() {
                    if (tableContainer) {
                        tableContainer.scrollTo({
                            top: 0,
                            behavior: 'smooth'
                        });
                    }
                });
            }

            // Debug: Check if container has correct styles
            if (tableContainer) {
            }
        });

        // ── Desktop header: per-column sort + filter (Support-Tickets style) ──────
        // Operates on the already-rendered rows. Functions are global because the
        // header markup wires them via inline onclick/onchange handlers.
        const PROJ_TEXT_FILTERS = ['io', 'name'];
        const PROJ_SORT_KEYS    = ['io', 'customer', 'type', 'name', 'ae', 'owner', 'category', 'status', 'updated'];
        const projState = { sortKey: null, sortDir: 'asc', filters: {} };

        function applyProjectView() {
            const tbody = document.getElementById('desktop-project-table-body');
            if (!tbody) return;
            const rows = Array.from(tbody.querySelectorAll('tr.project-row'));
            const f = projState.filters;
            const visible = [];

            rows.forEach(row => {
                let ok = true;
                for (const key in f) {
                    const val = f[key];
                    if (!val) continue;
                    const data = row.dataset[key] || '';
                    if (PROJ_TEXT_FILTERS.includes(key)) { if (!data.includes(val)) { ok = false; break; } }
                    else if (data !== val) { ok = false; break; }
                }
                row.style.display = ok ? '' : 'none';
                if (ok) visible.push(row);
            });

            if (projState.sortKey) {
                const k = projState.sortKey;
                const dir = projState.sortDir === 'desc' ? -1 : 1;
                visible.sort((a, b) => {
                    if (k === 'updated') return ((Number(a.dataset.updated) || 0) - (Number(b.dataset.updated) || 0)) * dir;
                    return (a.dataset[k] || '').localeCompare(b.dataset[k] || '', undefined, { numeric: true, sensitivity: 'base' }) * dir;
                });
                visible.forEach(r => tbody.appendChild(r));
            }

            const noRes = document.getElementById('desktop-no-results-row');
            if (noRes) { tbody.appendChild(noRes); noRes.classList.toggle('hidden', !(visible.length === 0 && rows.length > 0)); }

            updateProjIcons();
            updateStatTiles();
            applyMobileView();
            const tc = document.querySelector('.table-container');
            if (tc) tc.scrollTop = 0;
        }

        // Card mobile memakai state filter yang sama + kotak search mobile, supaya
        // klik summary tile ikut berlaku di layar kecil.
        function applyMobileView() {
            const cards = document.querySelectorAll('.project-card');
            if (!cards.length) return;
            const input = document.getElementById('project-search');
            const term  = input ? input.value.toLowerCase().trim() : '';
            const f = projState.filters;
            let visible = 0;

            cards.forEach(card => {
                let ok = (card.dataset.searchableContent || '').includes(term);
                if (ok) {
                    for (const key in f) {
                        const val = f[key];
                        // Card mobile tidak membawa semua data-* milik baris desktop
                        // (mis. io/name/owner) — filter untuk key yang tidak ada
                        // diabaikan, bukan dianggap tidak cocok.
                        if (!val || !(key in card.dataset)) continue;
                        const data = card.dataset[key] || '';
                        if (PROJ_TEXT_FILTERS.includes(key)) { if (!data.includes(val)) { ok = false; break; } }
                        else if (data !== val) { ok = false; break; }
                    }
                }
                card.style.display = ok ? '' : 'none';
                if (ok) visible++;
            });

            const noRes = document.getElementById('mobile-no-results');
            if (noRes) noRes.classList.toggle('hidden', !(visible === 0 && cards.length > 0));
        }

        // ── Summary tiles ────────────────────────────────────────────────────────
        function updateStatTiles() {
            document.querySelectorAll('.stat-tile').forEach(tile => {
                const key = tile.dataset.statKey;
                const val = tile.dataset.statValue || '';
                const active = key === '__all__'
                    ? Object.values(projState.filters).every(v => !v)
                    : projState.filters[key] === val;
                tile.classList.toggle('is-active', !!active);
            });
        }

        function projStatTileClick(tile) {
            const key = tile.dataset.statKey;
            const val = tile.dataset.statValue || '';
            if (key === '__all__') {
                Object.keys(projState.filters).forEach(k => projClearFilter(k));
                applyProjectView();
                return;
            }
            // Klik ulang tile yang sedang aktif = lepas filter-nya.
            const next = projState.filters[key] === val ? '' : val;
            projState.filters[key] = next;
            // Samakan select di panel header supaya angka tile dan kontrol kolom
            // tidak menampilkan dua kebenaran berbeda.
            const panel = document.getElementById('proj-panel-' + key);
            if (panel) panel.querySelectorAll('select').forEach(sel => {
                sel.value = next;
                sel.dispatchEvent(new Event('change', { bubbles: false }));
            });
            applyProjectView();
        }

        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.stat-tile').forEach(tile => {
                tile.addEventListener('click', () => projStatTileClick(tile));
            });
            updateStatTiles();
        });

        function updateProjIcons() {
            PROJ_SORT_KEYS.forEach(k => {
                const el = document.getElementById('proj-sort-' + k);
                if (!el) return;
                if (projState.sortKey === k) { el.textContent = projState.sortDir === 'asc' ? '↑' : '↓'; el.classList.add('active'); }
                else { el.textContent = '⇅'; el.classList.remove('active'); }
            });
            ['io', 'customer', 'type', 'name', 'ae', 'owner', 'category', 'status'].forEach(k => {
                const fn = document.getElementById('proj-funnel-' + k);
                if (fn) fn.classList.toggle('active', !!projState.filters[k]);
            });
        }

        function projSort(key, dir) { projState.sortKey = key; projState.sortDir = dir; applyProjectView(); closeAllProjPanels(); }
        function projToggleSort(key) {
            if (projState.sortKey === key) projState.sortDir = (projState.sortDir === 'asc' ? 'desc' : 'asc');
            else { projState.sortKey = key; projState.sortDir = 'asc'; }
            applyProjectView();
        }
        function projSetFilter(key, value) { projState.filters[key] = (value || '').toLowerCase().trim(); applyProjectView(); }
        function projClearFilter(key) {
            projState.filters[key] = '';
            const panel = document.getElementById('proj-panel-' + key);
            if (panel) panel.querySelectorAll('input, select').forEach(el => {
                el.value = '';
                // select-enhance.js merender label tombolnya dari event 'change';
                // tanpa dispatch ini label tetap memperlihatkan pilihan lama.
                if (el.tagName === 'SELECT') el.dispatchEvent(new Event('change', { bubbles: false }));
            });
            applyProjectView();
        }
        // Fixed-position popovers so panels are never clipped by the scroll container.
        function closeAllProjPanels() { document.querySelectorAll('.proj-panel').forEach(p => p.classList.add('hidden')); }
        function toggleProjPanel(id, btn) {
            const panel = document.getElementById(id);
            if (!panel) return;
            const isOpen = !panel.classList.contains('hidden');
            closeAllProjPanels();
            if (isOpen) return;
            const r = btn.getBoundingClientRect();
            panel.style.position = 'fixed';
            panel.style.top = (r.bottom + 4) + 'px';
            panel.style.left = Math.min(r.left, window.innerWidth - 250) + 'px';
            panel.style.zIndex = 9999;
            panel.classList.remove('hidden');
        }
        document.addEventListener('click', function (e) {
            // .se-panel / .se-wrap: dropdown hasil select-enhance.js. Panel-nya
            // di-detach ke <body> (mode fixed) sehingga bukan lagi turunan
            // .proj-panel — tanpa guard ini, klik di dalamnya menutup panel filter.
            if (e.target.closest('.proj-th-btn') || e.target.closest('.proj-panel')
                || e.target.closest('.se-panel') || e.target.closest('.se-wrap')) return;
            closeAllProjPanels();
        });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeAllProjPanels(); });
    </script>
@endsection
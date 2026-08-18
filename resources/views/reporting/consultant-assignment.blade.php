@extends('dashboard')
@section('title', 'Consultant Assignment')
@section('page-title', 'Consultant Assignment')
@section('page-subtitle', 'Consultants assigned to delivery projects')

@section('content')

{{-- ── Header Controls ──────────────────────────────────────────────────────── --}}
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
    <div>
        <h2 class="text-xl font-bold text-gray-900">Consultant Assignment</h2>
        <p class="text-sm text-gray-500 mt-0.5">Sourced from the Team Members of every delivery project</p>
    </div>
    <div class="flex items-center gap-2.5 flex-wrap">
        <div class="relative">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/>
                </svg>
            </span>
            <input type="text" id="caSearch" placeholder="Search consultant, project, customer…"
                   oninput="caOnSearchInput()"
                   class="w-full sm:w-72 pl-9 pr-3 py-1.5 border border-gray-200 rounded-xl text-xs text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
        </div>
        <button onclick="caResetFilters()" id="caBtnReset"
                class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-white border border-gray-200 text-gray-600 text-xs font-semibold rounded-xl hover:bg-gray-50 hover:border-gray-300 transition-all">
            <i class="fas fa-rotate-left text-xs"></i>Reset Filters
        </button>
        <button onclick="caExport()"
                class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-white border border-gray-200 text-gray-600 text-xs font-semibold rounded-xl hover:bg-gray-50 hover:border-gray-300 transition-all">
            <i class="fas fa-file-excel text-green-600 text-xs"></i>Export
        </button>
    </div>
</div>

{{-- ── Summary Cards (also the Assignment Status filter) ───────────────────── --}}
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2 mb-3">
    <div id="caCardAll" onclick="caCardFilter('')"
         class="ca-stat-card active-filter bg-white rounded-xl border border-gray-200 px-4 py-3.5 cursor-pointer select-none transition-all duration-200 hover:shadow-md hover:border-gray-300">
        <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest mb-2">Assignments</p>
        <p class="text-2xl font-bold text-gray-700 leading-none" id="caStatAssignments">0</p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 px-4 py-3.5 select-none">
        <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest mb-2">Consultants</p>
        <p class="text-2xl font-bold text-gray-700 leading-none" id="caStatConsultants">0</p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 px-4 py-3.5 select-none">
        <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest mb-2">Projects</p>
        <p class="text-2xl font-bold text-gray-700 leading-none" id="caStatProjects">0</p>
    </div>
    <div id="caCardActive" onclick="caCardFilter('active')"
         class="ca-stat-card bg-white rounded-xl border border-gray-200 px-4 py-3.5 cursor-pointer select-none transition-all duration-200 hover:shadow-md hover:border-green-200">
        <div class="flex items-center gap-1.5 mb-2">
            <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>
            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest">Active</p>
        </div>
        <p class="text-2xl font-bold text-green-600 leading-none" id="caStatActive">0</p>
    </div>
    <div id="caCardUpcoming" onclick="caCardFilter('upcoming')"
         class="ca-stat-card bg-white rounded-xl border border-gray-200 px-4 py-3.5 cursor-pointer select-none transition-all duration-200 hover:shadow-md hover:border-blue-200">
        <div class="flex items-center gap-1.5 mb-2">
            <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest">Upcoming</p>
        </div>
        <p class="text-2xl font-bold text-blue-600 leading-none" id="caStatUpcoming">0</p>
    </div>
    <div id="caCardEnded" onclick="caCardFilter('ended')"
         class="ca-stat-card bg-white rounded-xl border border-gray-200 px-4 py-3.5 cursor-pointer select-none transition-all duration-200 hover:shadow-md hover:border-gray-300">
        <div class="flex items-center gap-1.5 mb-2">
            <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest">Ended</p>
        </div>
        <p class="text-2xl font-bold text-gray-500 leading-none" id="caStatEnded">0</p>
    </div>
</div>

{{-- ── Secondary summary strip ─────────────────────────────────────────────── --}}
<div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-4">
    <div class="bg-white rounded-xl border border-gray-200 px-4 py-2.5">
        <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest">Internal</p>
        <p class="text-base font-bold text-gray-700" id="caStatInternal">0</p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 px-4 py-2.5">
        <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest">External / Vendor</p>
        <p class="text-base font-bold text-gray-700" id="caStatExternal">0</p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 px-4 py-2.5">
        <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest" title="Total planned working days from activity assignments">Planned MD</p>
        <p class="text-base font-bold text-gray-700" id="caStatPlannedMd">0</p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 px-4 py-2.5">
        <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest" title="Total approved timesheet mandays charged to the project">Actual MD</p>
        <p class="text-base font-bold text-gray-700" id="caStatActualMd">0</p>
    </div>
</div>

{{-- ── Table ───────────────────────────────────────────────────────────────── --}}
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
    {{-- Toolbar --}}
    <div class="flex items-center justify-between px-4 py-2.5 border-b border-gray-100 bg-gray-50/60">
        <div class="flex items-center gap-3">
            <p class="text-xs text-gray-400">
                Showing <span class="font-semibold text-gray-600" id="caRangeStart">0</span>&ndash;<span class="font-semibold text-gray-600" id="caRangeEnd">0</span>
                <span class="text-gray-300 mx-1">of</span>
                <span class="font-semibold text-gray-700" id="caTotalItems">0</span> consultants
                <span class="text-gray-300 mx-1">&middot;</span>
                <span class="font-semibold text-gray-600" id="caTotalRows">0</span> assignments
            </p>
            <button onclick="caToggleExpandAll()" id="caBtnExpandAll"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-semibold text-gray-500 bg-white border border-gray-200 hover:bg-gray-50 hover:border-gray-300 transition-all">
                <i class="fas fa-expand-alt text-xs"></i>Expand All
            </button>
        </div>
        <div class="flex items-center gap-1">
            <button onclick="caPrevPage()" id="caBtnPrev" disabled
                    class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-medium text-gray-500 bg-white border border-gray-200 hover:bg-gray-50 hover:border-gray-300 disabled:opacity-30 disabled:cursor-not-allowed transition-all">
                <svg fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3 h-3">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
                </svg>
                Prev
            </button>
            <button onclick="caNextPage()" id="caBtnNext" disabled
                    class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-medium text-gray-500 bg-white border border-gray-200 hover:bg-gray-50 hover:border-gray-300 disabled:opacity-30 disabled:cursor-not-allowed transition-all">
                Next
                <svg fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3 h-3">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
                </svg>
            </button>
        </div>
    </div>

    <div class="overflow-auto" style="max-height: calc(100vh - 400px); min-height: 240px;">
        <table class="w-full text-sm border-collapse" style="min-width: 2400px;">
            <thead class="sticky top-0 z-10 bg-gray-50">
                <tr>
                    {{-- CONSULTANT: keyword filter + sort, sticky --}}
                    <th class="p-0 text-left whitespace-nowrap border-b border-gray-200 sticky left-0 bg-gray-50 z-20" style="min-width:190px;">
                        <button type="button" onclick="caTogglePanel(event,'Consultant')"
                                class="w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                            <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Consultant</span>
                            <span id="caSortIcon_consultant_name" class="text-[10px] text-red-500 font-bold shrink-0"></span>
                            <svg class="w-3.5 h-3.5 text-gray-500 shrink-0 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                            <svg id="caFilterIcon_Consultant" class="w-3.5 h-3.5 text-gray-300 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 011 1v1.586a1 1 0 01-.293.707l-4.121 4.121A1 1 0 0012 12.121V15.5l-4 1.5v-4.879a1 1 0 00-.293-.707L3.586 7.293A1 1 0 013.293 6.586L3 5z" clip-rule="evenodd"/></svg>
                        </button>
                        <div id="caPanel_Consultant" class="hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] p-3" style="min-width:240px;">
                            <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Search consultant</label>
                            <input type="text" id="caFilterConsultant" placeholder="Type a name…"
                                   oninput="caOnTextFilterInput()" onclick="event.stopPropagation()"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm font-normal text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
                            <div class="border-t border-gray-100 mt-3 pt-3">
                                <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-2">Sort</label>
                                <div class="flex gap-2">
                                    <button type="button" onclick="caSort('consultant_name','asc')" class="flex-1 px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50">&uarr; A&ndash;Z</button>
                                    <button type="button" onclick="caSort('consultant_name','desc')" class="flex-1 px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50">&darr; Z&ndash;A</button>
                                </div>
                            </div>
                            <div class="flex justify-end mt-3">
                                <button type="button" onclick="caClearText('caFilterConsultant')" class="px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50">Clear</button>
                            </div>
                        </div>
                    </th>

                    <th class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:90px;">ECI</th>

                    {{-- POSITION: single-select dropdown --}}
                    <th class="p-0 text-left whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:160px;">
                        <div class="custom-dd relative w-full" id="ddCaPosition" data-fixed="true" data-onchange="caApplyDropdownFilter" data-searchable="true">
                            <button type="button" class="custom-dd-btn w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                                <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Position</span>
                                <svg class="custom-dd-arrow w-3.5 h-3.5 text-gray-500 transition-all duration-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <input type="hidden" id="caPosition" value="">
                            <div class="custom-dd-panel hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] py-1.5 overflow-y-auto" style="max-height:260px;min-width:220px;">
                                <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="">All</button>
                            </div>
                        </div>
                    </th>

                    {{-- PROJECT: single-select dropdown + sort --}}
                    <th class="p-0 text-left whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:220px;">
                        <div class="custom-dd relative w-full" id="ddCaProject" data-fixed="true" data-onchange="caApplyDropdownFilter" data-searchable="true">
                            <button type="button" class="custom-dd-btn w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                                <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Project</span>
                                <span id="caSortIcon_project_name" class="text-[10px] text-red-500 font-bold shrink-0"></span>
                                <svg class="custom-dd-arrow w-3.5 h-3.5 text-gray-500 transition-all duration-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <input type="hidden" id="caProjectId" value="">
                            <div class="custom-dd-panel hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] py-1.5 overflow-y-auto" style="max-height:260px;min-width:260px;">
                                <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="">All</button>
                            </div>
                        </div>
                    </th>

                    <th class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:120px;">IO Number</th>

                    {{-- CUSTOMER: single-select dropdown --}}
                    <th class="p-0 text-left whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:190px;">
                        <div class="custom-dd relative w-full" id="ddCaCustomer" data-fixed="true" data-onchange="caApplyDropdownFilter" data-searchable="true">
                            <button type="button" class="custom-dd-btn w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                                <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Customer</span>
                                <svg class="custom-dd-arrow w-3.5 h-3.5 text-gray-500 transition-all duration-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <input type="hidden" id="caCustomer" value="">
                            <div class="custom-dd-panel hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] py-1.5 overflow-y-auto" style="max-height:260px;min-width:240px;">
                                <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="">All</button>
                            </div>
                        </div>
                    </th>

                    {{-- PROJECT CATEGORY: multi-select --}}
                    <th class="p-0 text-left whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:150px;">
                        <div class="custom-dd relative w-full" id="ddCaCategory" data-fixed="true" data-multi="true" data-onchange="caApplyDropdownFilter">
                            <button type="button" class="custom-dd-btn w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                                <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Project Category</span>
                                <svg class="custom-dd-arrow w-3.5 h-3.5 text-gray-500 transition-all duration-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <input type="hidden" id="caCategory" value="">
                            <div class="custom-dd-panel hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] py-1.5 overflow-y-auto" style="max-height:240px;min-width:170px;">
                                <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="">All</button>
                                @foreach(['Open', 'In Process', 'Closed'] as $cat)
                                <button type="button" class="custom-dd-item w-full flex items-center justify-between gap-2 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="{{ $cat }}">
                                    <span class="custom-dd-item-text">{{ $cat }}</span>
                                    <svg class="custom-dd-check w-4 h-4 text-red-500 opacity-0 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                </button>
                                @endforeach
                            </div>
                        </div>
                    </th>

                    {{-- MODULE: single-select dropdown --}}
                    <th class="p-0 text-left whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:150px;">
                        <div class="custom-dd relative w-full" id="ddCaModule" data-fixed="true" data-onchange="caApplyDropdownFilter" data-searchable="true">
                            <button type="button" class="custom-dd-btn w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                                <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Module</span>
                                <svg class="custom-dd-arrow w-3.5 h-3.5 text-gray-500 transition-all duration-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <input type="hidden" id="caModule" value="">
                            <div class="custom-dd-panel hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] py-1.5 overflow-y-auto" style="max-height:260px;min-width:200px;">
                                <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="">All</button>
                                <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="__none__">No Module</button>
                            </div>
                        </div>
                    </th>

                    {{-- PROJECT ROLE: multi-select --}}
                    <th class="p-0 text-left whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:170px;">
                        <div class="custom-dd relative w-full" id="ddCaRole" data-fixed="true" data-multi="true" data-onchange="caApplyDropdownFilter">
                            <button type="button" class="custom-dd-btn w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                                <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Project Role</span>
                                <svg class="custom-dd-arrow w-3.5 h-3.5 text-gray-500 transition-all duration-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <input type="hidden" id="caRole" value="">
                            <div class="custom-dd-panel hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] py-1.5 overflow-y-auto" style="max-height:240px;min-width:200px;">
                                <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="">All</button>
                                @foreach(['Project Manager', 'Co Project Manager', 'Project Admin', 'Lead', 'Member'] as $role)
                                <button type="button" class="custom-dd-item w-full flex items-center justify-between gap-2 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="{{ $role }}">
                                    <span class="custom-dd-item-text">{{ $role }}</span>
                                    <svg class="custom-dd-check w-4 h-4 text-red-500 opacity-0 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                </button>
                                @endforeach
                            </div>
                        </div>
                    </th>

                    {{-- EMPLOYEE TYPE: multi-select --}}
                    <th class="p-0 text-left whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:150px;">
                        <div class="custom-dd relative w-full" id="ddCaEmployeeType" data-fixed="true" data-multi="true" data-onchange="caApplyDropdownFilter">
                            <button type="button" class="custom-dd-btn w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                                <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Employee Type</span>
                                <svg class="custom-dd-arrow w-3.5 h-3.5 text-gray-500 transition-all duration-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <input type="hidden" id="caEmployeeType" value="">
                            <div class="custom-dd-panel hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] py-1.5 overflow-y-auto" style="max-height:240px;min-width:160px;">
                                <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="">All</button>
                                @foreach(['Internal', 'External', 'Vendor'] as $type)
                                <button type="button" class="custom-dd-item w-full flex items-center justify-between gap-2 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="{{ $type }}">
                                    <span class="custom-dd-item-text">{{ $type }}</span>
                                    <svg class="custom-dd-check w-4 h-4 text-red-500 opacity-0 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                </button>
                                @endforeach
                            </div>
                        </div>
                    </th>

                    <th class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:140px;">Vendor</th>

                    {{-- PERIOD: from/to range + sort by start date --}}
                    <th class="p-0 text-left whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:200px;">
                        <button type="button" onclick="caTogglePanel(event,'Period')"
                                class="w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                            <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Period</span>
                            <span id="caSortIcon_start_date" class="text-[10px] text-red-500 font-bold shrink-0"></span>
                            <svg class="w-3.5 h-3.5 text-gray-500 shrink-0 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                            <svg id="caFilterIcon_Period" class="w-3.5 h-3.5 text-gray-300 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 011 1v1.586a1 1 0 01-.293.707l-4.121 4.121A1 1 0 0012 12.121V15.5l-4 1.5v-4.879a1 1 0 00-.293-.707L3.586 7.293A1 1 0 013.293 6.586L3 5z" clip-rule="evenodd"/></svg>
                        </button>
                        <div id="caPanel_Period" class="hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] p-3" style="min-width:250px;">
                            <p class="text-[11px] text-gray-400 mb-2 normal-case tracking-normal font-normal">Assignments overlapping this range</p>
                            <div class="space-y-2">
                                <div>
                                    <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">From</label>
                                    <input type="date" id="caPeriodFrom" onclick="event.stopPropagation()"
                                           class="w-full px-3 py-1.5 border border-gray-300 rounded-md text-sm font-normal text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">To</label>
                                    <input type="date" id="caPeriodTo" onclick="event.stopPropagation()"
                                           class="w-full px-3 py-1.5 border border-gray-300 rounded-md text-sm font-normal text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
                                </div>
                                <p id="caPeriodError" class="hidden text-xs text-red-500 normal-case tracking-normal font-normal">"To" must be on/after "From".</p>
                            </div>
                            <div class="border-t border-gray-100 mt-3 pt-3">
                                <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-2">Sort by start date</label>
                                <div class="flex gap-2">
                                    <button type="button" onclick="caSort('start_date','asc')" class="flex-1 px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50">&uarr; Oldest</button>
                                    <button type="button" onclick="caSort('start_date','desc')" class="flex-1 px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50">&darr; Newest</button>
                                </div>
                            </div>
                            <div class="flex justify-end gap-2 mt-3">
                                <button type="button" onclick="caClearPeriod()" class="px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50">Clear</button>
                                <button type="button" onclick="caApplyPeriod()" class="px-3 py-1.5 text-xs text-white bg-red-700 hover:bg-red-800 rounded-md">Apply</button>
                            </div>
                        </div>
                    </th>

                    <th class="px-3 py-2.5 text-center text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:100px;">Duration</th>
                    <th class="px-3 py-2.5 text-center text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:130px;">Status</th>

                    {{-- PLANNED MD: sort --}}
                    <th class="p-0 text-center whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:120px;">
                        <button type="button" onclick="caToggleSort('planned_md')" title="Planned working days from activity assignments"
                                class="w-full flex items-center justify-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                            <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Planned MD</span>
                            <span id="caSortIcon_planned_md" class="text-[10px] text-red-500 font-bold shrink-0"></span>
                        </button>
                    </th>

                    {{-- ACTUAL MD: sort --}}
                    <th class="p-0 text-center whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:120px;">
                        <button type="button" onclick="caToggleSort('actual_md')" title="Approved timesheet mandays charged to this project"
                                class="w-full flex items-center justify-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                            <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Actual MD</span>
                            <span id="caSortIcon_actual_md" class="text-[10px] text-red-500 font-bold shrink-0"></span>
                        </button>
                    </th>

                    <th class="px-3 py-2.5 text-center text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:110px;">Utilization</th>
                    <th class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:200px;">Notes</th>
                </tr>
            </thead>
            <tbody id="caTableBody" class="bg-white">
                <tr>
                    <td colspan="18" class="px-4 py-14 text-center text-gray-400 text-sm">
                        <i class="fas fa-spinner fa-spin text-2xl mb-3 block primary-text opacity-50"></i>
                        <span>Loading data...</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- Empty state --}}
    <div id="caEmpty" class="hidden text-center py-16 px-4">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-50 mb-4">
            <svg class="w-8 h-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/>
            </svg>
        </div>
        <p class="text-gray-700 font-semibold">No consultant assignments found</p>
        <p class="text-gray-400 text-xs mt-1">Try widening or resetting the filters</p>
    </div>
</div>
@endsection

@push('styles')
<style>
.primary-text { color: var(--primary-color) !important; }
/* Baris induk yang terbuka ditandai supaya batas antar consultant tetap jelas
   saat beberapa consultant dibuka sekaligus. */
.ca-group-row.ca-group-open { background: rgba(254, 242, 242, 0.6) !important; }
.ca-group-row.ca-group-open td { border-bottom: 0; }
.ca-stat-card.active-filter {
    border-left: 3px solid #dc2626 !important;
    border-top-color: #fecaca !important;
    border-right-color: #fecaca !important;
    border-bottom-color: #fecaca !important;
    background: #fff8f8 !important;
    box-shadow: 0 2px 8px rgba(220, 38, 38, 0.08) !important;
}
/* Kolom filter yang sedang aktif ditandai supaya tidak ada filter "tersembunyi". */
.custom-dd.ca-dd-active .custom-dd-btn span { color: #dc2626 !important; }
</style>
@endpush

@push('scripts')
<script src="/js/custom-dropdown.js?v={{ filemtime(public_path('js/custom-dropdown.js')) }}"></script>
<script>
// ── State ────────────────────────────────────────────────────────────────────
// Filter dikerjakan SERVER-side (satu sumber kebenaran dengan tombol Export),
// jadi setiap perubahan filter memicu fetch ulang. Sort & pagination lokal.
let caRows        = [];
let caSortKey     = null;
let caSortDir     = null;
let caPage        = 1;
let caStatus      = '';     // '' | active | upcoming | ended
let caSearchTimer = null;
let caExpanded    = new Set();   // employee_id yang barisnya sedang dibuka
const CA_PER_PAGE = 25;          // per CONSULTANT (baris induk), bukan per penugasan

document.addEventListener('DOMContentLoaded', function () {
    if (typeof initCustomDropdowns === 'function') initCustomDropdowns();
    caLoadFilterOptions();
    caLoad();
});

// Panel filter kustom (Consultant / Period) menutup saat klik di luar.
document.addEventListener('click', function (e) {
    if (!e.target.closest('[id^="caPanel_"]') && !e.target.closest('[onclick*="caTogglePanel"]')) {
        caClosePanels();
    }
});
window.addEventListener('resize', caClosePanels);

// ── Filter state → query params ──────────────────────────────────────────────

function caFilterParams() {
    const val = id => (document.getElementById(id)?.value || '').trim();
    const params = new URLSearchParams();
    const add = (key, v) => { if (v !== '') params.set(key, v); };

    add('search',            val('caSearch'));
    add('consultant',        val('caFilterConsultant'));
    add('project_id',        val('caProjectId'));
    add('customer',          val('caCustomer'));
    add('module',            val('caModule'));
    add('position',          val('caPosition'));
    add('role',              val('caRole'));
    add('employee_type',     val('caEmployeeType'));
    add('project_category',  val('caCategory'));
    add('period_from',       val('caPeriodFrom'));
    add('period_to',         val('caPeriodTo'));
    add('assignment_status', caStatus);

    return params;
}

async function caLoad() {
    const tbody = document.getElementById('caTableBody');
    tbody.innerHTML = `<tr><td colspan="18" class="px-4 py-14 text-center text-gray-400 text-sm">
        <i class="fas fa-spinner fa-spin text-2xl mb-3 block primary-text opacity-50"></i>
        <span>Loading data...</span>
    </td></tr>`;
    document.getElementById('caEmpty').classList.add('hidden');

    try {
        const res  = await fetch('/api/reporting/consultant-assignment?' + caFilterParams().toString(), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'API error');

        caRows = json.data || [];
        caRenderStats(json.stats || {});
        caPage = 1;
        // Hasil filter baru = kumpulan consultant yang berbeda; biarkan semua
        // baris tertutup supaya tidak ada baris terbuka milik data lama.
        caExpanded = new Set();
        caRender();

    } catch (e) {
        console.error(e);
        tbody.innerHTML = `<tr><td colspan="18" class="px-4 py-10 text-center text-sm">
            <div class="inline-flex flex-col items-center gap-2 text-red-500">
                <i class="fas fa-exclamation-circle text-xl"></i>
                <span>${caEsc(e.message)}</span>
            </div>
        </td></tr>`;
    }
}

async function caLoadFilterOptions() {
    try {
        const res = await fetch('/api/reporting/consultant-assignment/filter-options', {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        });
        if (!res.ok) return;
        const json = await res.json();
        if (!json.success) return;

        caFillDropdown('ddCaProject',  (json.projects || []).map(p => ({ value: String(p.id), text: p.name })));
        caFillDropdown('ddCaCustomer', (json.customers || []).map(c => ({ value: c, text: c })));
        caFillDropdown('ddCaPosition', (json.positions || []).map(p => ({ value: p, text: p })));
        caFillDropdown('ddCaModule',   (json.modules   || []).map(m => ({ value: m, text: m })), [
            { value: '',         text: 'All' },
            { value: '__none__', text: 'No Module' },
        ]);
    } catch (e) {
        console.warn('[Consultant Assignment] filter options:', e.message);
    }
}

/**
 * Isi opsi dropdown dinamis. Panel bisa sudah dipindah ke <body> (mode fixed),
 * jadi ambil referensinya dari `_ddPanel` yang disimpan initCustomDropdowns().
 */
function caFillDropdown(ddId, items, leadingItems) {
    const dd = document.getElementById(ddId);
    if (!dd) return;
    const panel = dd._ddPanel || dd.querySelector('.custom-dd-panel');
    if (!panel) return;

    panel.querySelectorAll('.custom-dd-item').forEach(el => el.remove());

    const makeItem = (value, text) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50';
        btn.dataset.value = value;
        btn.textContent = text;
        return btn;
    };

    const frag = document.createDocumentFragment();
    (leadingItems || [{ value: '', text: 'All' }]).forEach(i => frag.appendChild(makeItem(i.value, i.text)));
    items.forEach(i => frag.appendChild(makeItem(i.value, i.text)));

    // Sisipkan sebelum empty-state milik search injector kalau ada.
    if (panel._ddEmpty) panel.insertBefore(frag, panel._ddEmpty);
    else panel.appendChild(frag);
}

// ── Filter controls ──────────────────────────────────────────────────────────

function caOnSearchInput() {
    clearTimeout(caSearchTimer);
    caSearchTimer = setTimeout(caLoad, 350);
}

function caOnTextFilterInput() {
    caUpdateFilterIcons();
    clearTimeout(caSearchTimer);
    caSearchTimer = setTimeout(caLoad, 350);
}

// Dipanggil oleh custom-dropdown.js lewat data-onchange.
function caApplyDropdownFilter() {
    [['ddCaProject','caProjectId'], ['ddCaCustomer','caCustomer'], ['ddCaModule','caModule'],
     ['ddCaPosition','caPosition'], ['ddCaRole','caRole'], ['ddCaEmployeeType','caEmployeeType'],
     ['ddCaCategory','caCategory']].forEach(([ddId, inputId]) => {
        const dd = document.getElementById(ddId);
        if (dd) dd.classList.toggle('ca-dd-active', (document.getElementById(inputId)?.value || '') !== '');
    });
    caLoad();
}

function caApplyPeriod() {
    const from = document.getElementById('caPeriodFrom').value;
    const to   = document.getElementById('caPeriodTo').value;
    const err  = document.getElementById('caPeriodError');

    if (from && to && to < from) {
        err.classList.remove('hidden');
        return;
    }
    err.classList.add('hidden');
    caUpdateFilterIcons();
    caClosePanels();
    caLoad();
}

function caClearPeriod() {
    document.getElementById('caPeriodFrom').value = '';
    document.getElementById('caPeriodTo').value   = '';
    document.getElementById('caPeriodError').classList.add('hidden');
    caUpdateFilterIcons();
    caLoad();
}

function caClearText(inputId) {
    const inp = document.getElementById(inputId);
    if (inp) inp.value = '';
    caUpdateFilterIcons();
    caLoad();
}

function caCardFilter(status) {
    caStatus = status;
    document.querySelectorAll('.ca-stat-card').forEach(el => el.classList.remove('active-filter'));
    const cardId = status === 'active'   ? 'caCardActive'
                 : status === 'upcoming' ? 'caCardUpcoming'
                 : status === 'ended'    ? 'caCardEnded'
                 : 'caCardAll';
    document.getElementById(cardId)?.classList.add('active-filter');
    caLoad();
}

function caResetFilters() {
    ['caSearch', 'caFilterConsultant', 'caPeriodFrom', 'caPeriodTo'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });

    if (typeof setCustomDropdownValue === 'function') {
        ['caProjectId', 'caCustomer', 'caModule', 'caPosition'].forEach(id => setCustomDropdownValue(id, ''));
    }
    if (typeof clearCustomDropdownMulti === 'function') {
        ['caRole', 'caEmployeeType', 'caCategory'].forEach(id => clearCustomDropdownMulti(id));
    }
    document.querySelectorAll('.custom-dd').forEach(dd => dd.classList.remove('ca-dd-active'));

    caSortKey = null;
    caSortDir = null;
    caUpdateSortIcons();
    caUpdateFilterIcons();
    caCardFilter('');   // memicu caLoad()
}

function caExport() {
    window.location.href = '{{ route('reporting.consultant-assignment.export') }}?' + caFilterParams().toString();
}

// ── Header panels (Consultant / Period) ──────────────────────────────────────

function caTogglePanel(event, key) {
    event.stopPropagation();
    const panel = document.getElementById('caPanel_' + key);
    if (!panel) return;
    const wasHidden = panel.classList.contains('hidden');
    caClosePanels();
    if (typeof _closeAllDropdowns === 'function') _closeAllDropdowns();
    if (wasHidden) {
        panel.classList.remove('hidden');
        const inp = panel.querySelector('input[type="text"]');
        if (inp) setTimeout(() => inp.focus(), 30);
    }
}

function caClosePanels() {
    document.querySelectorAll('[id^="caPanel_"]').forEach(p => p.classList.add('hidden'));
}

function caUpdateFilterIcons() {
    const mark = (iconId, active) => {
        const icon = document.getElementById(iconId);
        if (!icon) return;
        icon.classList.toggle('text-red-500', active);
        icon.classList.toggle('text-gray-300', !active);
    };
    mark('caFilterIcon_Consultant', !!document.getElementById('caFilterConsultant')?.value);
    mark('caFilterIcon_Period', !!(document.getElementById('caPeriodFrom')?.value || document.getElementById('caPeriodTo')?.value));
}

// ── Sorting ──────────────────────────────────────────────────────────────────

function caSort(key, dir) {
    caSortKey = key;
    caSortDir = dir;
    caUpdateSortIcons();
    caClosePanels();
    caRender();
}

function caToggleSort(key) {
    if (caSortKey === key) {
        caSortDir = caSortDir === 'asc' ? 'desc' : 'asc';
    } else {
        caSortKey = key;
        caSortDir = 'desc';
    }
    caUpdateSortIcons();
    caRender();
}

function caUpdateSortIcons() {
    ['consultant_name', 'project_name', 'start_date', 'planned_md', 'actual_md'].forEach(key => {
        const el = document.getElementById('caSortIcon_' + key);
        // Escape unicode, bukan karakter literal: response tidak selalu
        // ber-charset utf-8 dan panah literal bisa jadi mojibake.
        if (el) el.textContent = caSortKey === key ? (caSortDir === 'asc' ? '↑' : '↓') : '';
    });
}

// ── Grouping per consultant ──────────────────────────────────────────────────
// Satu baris induk = satu consultant; penugasannya jadi baris anak yang bisa
// dibuka. Yang dipaginasi adalah CONSULTANT, bukan penugasan — kalau baris anak
// ikut dihitung, satu consultant bisa terpotong di antara dua halaman.

const CA_NUMERIC_KEYS = ['planned_md', 'actual_md'];

function caBuildGroups() {
    const byEmployee = new Map();

    caRows.forEach(r => {
        if (!byEmployee.has(r.employee_id)) {
            byEmployee.set(r.employee_id, {
                employee_id:     r.employee_id,
                consultant_name: r.consultant_name,
                eci:             r.eci,
                position:        r.position,
                division:        r.division,
                home_base:       r.home_base,
                employee_active: r.employee_active,
                items:           [],
            });
        }
        byEmployee.get(r.employee_id).items.push(r);
    });

    const groups = [...byEmployee.values()];

    groups.forEach(g => {
        // Baris anak selalu urut project → tanggal mulai, tidak ikut sort kolom.
        // Sort kolom bekerja di level consultant (lihat caSortGroups) supaya
        // urutan di dalam satu consultant tidak berubah-ubah dan sulit dibaca.
        g.items.sort((a, b) =>
            String(a.project_name).localeCompare(String(b.project_name))
            || String(a.start_date || '').localeCompare(String(b.start_date || ''))
        );

        g.project_count  = new Set(g.items.map(i => i.project_id)).size;
        g.customer_count = new Set(g.items.map(i => i.customer_name).filter(Boolean)).size;
        g.roles          = [...new Set(g.items.map(i => i.role).filter(Boolean))];
        g.modules        = [...new Set(g.items.map(i => i.module).filter(Boolean))];
        g.types          = [...new Set(g.items.map(i => i.employee_type).filter(Boolean))];
        g.active_count   = g.items.filter(i => i.assignment_status === 'Active').length;
        g.ended_count    = g.items.filter(i => i.assignment_status === 'Ended').length;
        g.upcoming_count = g.items.filter(i => i.assignment_status === 'Upcoming').length;

        // Planned/Actual MD dijumlahkan per PROJECT unik, bukan per baris:
        // angkanya berasal dari agregat project×employee, jadi consultant yang
        // punya dua peran di project yang sama akan terhitung dobel.
        const seenProject = new Set();
        g.planned_md = 0;
        g.actual_md  = 0;
        g.items.forEach(i => {
            if (seenProject.has(i.project_id)) return;
            seenProject.add(i.project_id);
            g.planned_md += i.planned_md || 0;
            g.actual_md  += i.actual_md  || 0;
        });
        g.planned_md = Math.round(g.planned_md * 100) / 100;
        g.actual_md  = Math.round(g.actual_md * 100) / 100;
        g.utilization = g.planned_md > 0 ? Math.round(g.actual_md / g.planned_md * 1000) / 10 : null;

        const starts = g.items.map(i => i.start_date).filter(Boolean).sort();
        g.first_start = starts.length ? starts[0] : '';
        // Satu saja penugasan tanpa end_date berarti keterlibatannya masih
        // berjalan → jangan tampilkan tanggal akhir yang menyesatkan.
        g.has_open_end = g.items.some(i => i.start_date && !i.end_date);
        const ends = g.items.map(i => i.end_date).filter(Boolean).sort();
        g.last_end = g.has_open_end ? '' : (ends.length ? ends[ends.length - 1] : '');

        g.first_project = g.items.length ? g.items[0].project_name : '';
    });

    return caSortGroups(groups);
}

function caSortGroups(groups) {
    const factor = caSortDir === 'asc' ? 1 : -1;

    // Default: nama consultant A–Z.
    if (!caSortKey) {
        return groups.sort((a, b) => String(a.consultant_name).localeCompare(String(b.consultant_name)));
    }

    // Kunci sort dipetakan ke nilai LEVEL CONSULTANT: numerik jadi total milik
    // consultant itu, tanggal jadi penugasan paling awal, project jadi project
    // pertama secara alfabet.
    const valueOf = {
        consultant_name: g => g.consultant_name,
        project_name:    g => g.first_project,
        start_date:      g => g.first_start,
        planned_md:      g => g.planned_md,
        actual_md:       g => g.actual_md,
    }[caSortKey] || (g => g.consultant_name);

    return groups.sort((a, b) => {
        if (CA_NUMERIC_KEYS.includes(caSortKey)) {
            return ((valueOf(a) || 0) - (valueOf(b) || 0)) * factor;
        }
        // Consultant tanpa nilai (mis. semua penugasannya tanpa tanggal) selalu
        // di bawah, apa pun arah sortirnya — kalau tidak, baris kosong menutupi data.
        const va = valueOf(a) || '';
        const vb = valueOf(b) || '';
        if (va === '' && vb === '') return 0;
        if (va === '') return 1;
        if (vb === '') return -1;
        return String(va).localeCompare(String(vb)) * factor;
    });
}

// ── Expand / collapse ────────────────────────────────────────────────────────

function caToggleRow(employeeId) {
    if (caExpanded.has(employeeId)) caExpanded.delete(employeeId);
    else caExpanded.add(employeeId);
    caRender();
}

function caToggleExpandAll() {
    const groups = caBuildGroups();
    // Kalau ada yang masih tertutup → buka semua; kalau semua terbuka → tutup semua.
    const allOpen = groups.length > 0 && groups.every(g => caExpanded.has(g.employee_id));
    caExpanded = allOpen ? new Set() : new Set(groups.map(g => g.employee_id));
    caRender();
}

// ── Pagination ───────────────────────────────────────────────────────────────

function caPrevPage() {
    if (caPage > 1) { caPage--; caRender(); }
}

function caNextPage() {
    const totalGroups = new Set(caRows.map(r => r.employee_id)).size;
    if (caPage < Math.ceil(totalGroups / CA_PER_PAGE)) { caPage++; caRender(); }
}

// ── Render ───────────────────────────────────────────────────────────────────

function caRenderStats(stats) {
    const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v ?? 0; };
    set('caStatAssignments', stats.assignments);
    set('caStatConsultants', stats.consultants);
    set('caStatProjects',    stats.projects);
    set('caStatActive',      stats.active);
    set('caStatUpcoming',    stats.upcoming);
    set('caStatEnded',       stats.ended);
    set('caStatInternal',    stats.internal);
    set('caStatExternal',    stats.external);
    set('caStatPlannedMd',   caNum(stats.planned_md));
    set('caStatActualMd',    caNum(stats.actual_md));
}

function caRender() {
    const tbody  = document.getElementById('caTableBody');
    const empty  = document.getElementById('caEmpty');
    const groups = caBuildGroups();

    document.getElementById('caTotalItems').textContent = groups.length;
    document.getElementById('caTotalRows').textContent  = caRows.length;

    if (groups.length === 0) {
        tbody.innerHTML = '';
        empty.classList.remove('hidden');
        document.getElementById('caRangeStart').textContent = 0;
        document.getElementById('caRangeEnd').textContent   = 0;
        document.getElementById('caBtnPrev').disabled = true;
        document.getElementById('caBtnNext').disabled = true;
        caUpdateExpandAllLabel(groups);
        return;
    }
    empty.classList.add('hidden');

    const totalPages = Math.ceil(groups.length / CA_PER_PAGE);
    if (caPage > totalPages) caPage = totalPages;
    const start = (caPage - 1) * CA_PER_PAGE;
    const slice = groups.slice(start, start + CA_PER_PAGE);

    document.getElementById('caRangeStart').textContent = start + 1;
    document.getElementById('caRangeEnd').textContent   = start + slice.length;
    document.getElementById('caBtnPrev').disabled = caPage <= 1;
    document.getElementById('caBtnNext').disabled = caPage >= totalPages;

    tbody.innerHTML = slice.map(g => caGroupRow(g) + (caExpanded.has(g.employee_id) ? caChildRows(g) : '')).join('');
    caUpdateExpandAllLabel(groups);
}

function caUpdateExpandAllLabel(groups) {
    const btn = document.getElementById('caBtnExpandAll');
    if (!btn) return;
    const allOpen = groups.length > 0 && groups.every(g => caExpanded.has(g.employee_id));
    btn.innerHTML = allOpen
        ? '<i class="fas fa-compress-alt text-xs"></i>Collapse All'
        : '<i class="fas fa-expand-alt text-xs"></i>Expand All';
}

/** Baris induk: satu consultant + ringkasan seluruh penugasannya. */
function caGroupRow(g) {
    const open = caExpanded.has(g.employee_id);
    return `
        <tr class="border-t border-gray-200 bg-white hover:bg-red-50/40 cursor-pointer ca-group-row ${open ? 'ca-group-open' : ''}"
            onclick="caToggleRow(${g.employee_id})">
            <td class="px-3 py-3 sticky left-0 z-10 ${open ? 'bg-red-50/60' : 'bg-white'}">
                <div class="flex items-center gap-2">
                    <svg class="w-3.5 h-3.5 text-gray-400 shrink-0 transition-transform ${open ? 'rotate-90' : ''}"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M9 5l7 7-7 7"/>
                    </svg>
                    <div class="flex flex-col min-w-0">
                        <span class="text-sm font-bold text-gray-800 truncate">${caEsc(g.consultant_name)}</span>
                        ${g.employee_active === false ? '<span class="text-[10px] text-gray-400">Inactive employee</span>' : ''}
                    </div>
                    <span class="ml-auto shrink-0 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-700"
                          title="${g.project_count} project, ${g.items.length} assignment">
                        <i class="fas fa-diagram-project text-[9px]"></i>${g.project_count}
                    </span>
                </div>
            </td>
            <td class="px-3 py-3 text-xs text-gray-500">${caEsc(g.eci) || '&mdash;'}</td>
            <td class="px-3 py-3 text-xs text-gray-600">${caEsc(g.position) || '&mdash;'}</td>
            <td class="px-3 py-3 text-xs font-semibold text-gray-700">
                ${g.project_count} project${g.project_count > 1 ? 's' : ''}
                ${g.items.length !== g.project_count ? `<span class="font-normal text-gray-400"> &middot; ${g.items.length} assignments</span>` : ''}
            </td>
            <td class="px-3 py-3 text-xs text-gray-300">&mdash;</td>
            <td class="px-3 py-3 text-xs text-gray-600">${g.customer_count} customer${g.customer_count > 1 ? 's' : ''}</td>
            <td class="px-3 py-3 text-xs text-gray-300">&mdash;</td>
            <td class="px-3 py-3 text-xs text-gray-600 max-w-[150px] truncate" title="${caEsc(g.modules.join(', '))}">${caEsc(g.modules.join(', ')) || '&mdash;'}</td>
            <td class="px-3 py-3 text-xs text-gray-700 max-w-[170px] truncate" title="${caEsc(g.roles.join(', '))}">${caEsc(g.roles.join(', ')) || '&mdash;'}</td>
            <td class="px-3 py-3">${g.types.map(t => caTypeBadge(t)).join(' ')}</td>
            <td class="px-3 py-3 text-xs text-gray-300">&mdash;</td>
            <td class="px-3 py-3 text-xs text-gray-600 whitespace-nowrap">${caGroupPeriodText(g)}</td>
            <td class="px-3 py-3 text-xs text-gray-300 text-center">&mdash;</td>
            <td class="px-3 py-3 text-center">${caGroupStatusBadges(g)}</td>
            <td class="px-3 py-3 text-center text-sm text-gray-700">${caNum(g.planned_md)}</td>
            <td class="px-3 py-3 text-center text-sm font-bold text-gray-800">${caNum(g.actual_md)}</td>
            <td class="px-3 py-3 text-center text-xs">${caUtilization(g.utilization)}</td>
            <td class="px-3 py-3 text-xs text-gray-300">&mdash;</td>
        </tr>
    `;
}

/** Baris anak: detail satu penugasan. */
function caChildRows(g) {
    return g.items.map(r => `
        <tr class="border-t border-gray-100 bg-gray-50/40 hover:bg-gray-50">
            <td class="px-3 py-2 sticky left-0 bg-gray-50/95 z-10">
                <div class="flex items-center gap-2 pl-5">
                    <span class="w-1.5 h-1.5 rounded-full bg-gray-300 shrink-0"></span>
                    <span class="text-[11px] text-gray-400 truncate">${caEsc(r.role)}</span>
                </div>
            </td>
            <td class="px-3 py-2"></td>
            <td class="px-3 py-2"></td>
            <td class="px-3 py-2">
                <a href="/projects/${r.project_id}" onclick="event.stopPropagation()" class="text-sm font-medium primary-text hover:underline">${caEsc(r.project_name)}</a>
                ${r.project_closed ? '<span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-semibold bg-gray-100 text-gray-500">CLOSED</span>' : ''}
            </td>
            <td class="px-3 py-2 text-xs text-gray-600">${caEsc(r.io_number) || '&mdash;'}</td>
            <td class="px-3 py-2 text-sm text-gray-700">${caEsc(r.customer_name) || '&mdash;'}</td>
            <td class="px-3 py-2">${caCategoryBadge(r.project_category)}</td>
            <td class="px-3 py-2 text-xs text-gray-600">${caEsc(r.module) || '&mdash;'}</td>
            <td class="px-3 py-2 text-xs text-gray-700">${caEsc(r.role)}</td>
            <td class="px-3 py-2">${caTypeBadge(r.employee_type)}</td>
            <td class="px-3 py-2 text-xs text-gray-600">${caEsc(r.vendor_name) || '&mdash;'}</td>
            <td class="px-3 py-2 text-xs text-gray-600 whitespace-nowrap">${caPeriodText(r)}</td>
            <td class="px-3 py-2 text-center text-xs text-gray-600">${r.duration_days != null ? r.duration_days + 'd' : '&mdash;'}</td>
            <td class="px-3 py-2 text-center">${caStatusBadge(r.assignment_status)}</td>
            <td class="px-3 py-2 text-center text-sm text-gray-700">${caNum(r.planned_md)}</td>
            <td class="px-3 py-2 text-center text-sm text-gray-700">${caNum(r.actual_md)}</td>
            <td class="px-3 py-2 text-center text-xs">${caUtilization(r.utilization)}</td>
            <td class="px-3 py-2 text-xs text-gray-500 max-w-[240px] truncate" title="${caEsc(r.notes)}">${caEsc(r.notes) || '&mdash;'}</td>
        </tr>
    `).join('');
}

function caGroupPeriodText(g) {
    if (!g.first_start) return '&mdash;';
    return caEsc(g.first_start) + ' &rarr; ' + (g.last_end ? caEsc(g.last_end) : 'ongoing');
}

function caGroupStatusBadges(g) {
    const parts = [];
    if (g.active_count)   parts.push(`<span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-green-100 text-green-700">${g.active_count} active</span>`);
    if (g.upcoming_count) parts.push(`<span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-blue-100 text-blue-700">${g.upcoming_count} upcoming</span>`);
    if (g.ended_count)    parts.push(`<span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-gray-100 text-gray-600">${g.ended_count} ended</span>`);
    return parts.length ? `<div class="flex flex-wrap gap-1 justify-center">${parts.join('')}</div>` : '<span class="text-gray-300">&mdash;</span>';
}

// ── Cell formatters ──────────────────────────────────────────────────────────

function caPeriodText(r) {
    if (!r.start_date) return '&mdash;';
    return caEsc(r.start_date) + ' &rarr; ' + (r.end_date ? caEsc(r.end_date) : 'ongoing');
}

function caStatusBadge(status) {
    const map = {
        'Active':    'bg-green-100 text-green-700',
        'Upcoming':  'bg-blue-100 text-blue-700',
        'Ended':     'bg-gray-100 text-gray-600',
        'No Period': 'bg-amber-100 text-amber-700',
    };
    const cls = map[status] || 'bg-gray-100 text-gray-600';
    return `<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold ${cls}">${caEsc(status)}</span>`;
}

function caCategoryBadge(category) {
    if (!category) return '<span class="text-xs text-gray-400">&mdash;</span>';
    const map = {
        'Open':       'bg-blue-50 text-blue-600',
        'In Process': 'bg-yellow-50 text-yellow-700',
        'Closed':     'bg-gray-100 text-gray-500',
    };
    const cls = map[category] || 'bg-gray-50 text-gray-600';
    return `<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold ${cls}">${caEsc(category)}</span>`;
}

function caTypeBadge(type) {
    const cls = type === 'Internal' ? 'bg-gray-100 text-gray-600' : 'bg-purple-50 text-purple-600';
    return `<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold ${cls}">${caEsc(type || 'Internal')}</span>`;
}

function caUtilization(pct) {
    if (pct == null) return '<span class="text-gray-300">&mdash;</span>';
    const cls = pct > 110 ? 'text-red-600' : pct >= 90 ? 'text-green-600' : 'text-gray-600';
    return `<span class="font-semibold ${cls}">${pct}%</span>`;
}

function caNum(v) {
    const n = Number(v || 0);
    return Number.isInteger(n) ? String(n) : n.toFixed(2);
}

function caEsc(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}
</script>
@endpush

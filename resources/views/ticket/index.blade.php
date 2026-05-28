@extends('dashboard')
@section('title', 'Support Tickets')
@section('page-title', 'Support Tickets')
@section('page-subtitle', 'Manage and track all support requests')
@section('content')

<!-- Modern Helpdesk Header -->
<div class="mb-6">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 tracking-tight mb-1">Support Tickets</h1>
            <p class="text-gray-500 text-sm">Manage and track all support requests</p>
        </div>

        <div class="flex items-center gap-3">
            @if($user->role->role_id === \App\Enums\RoleId::DELIVERY_SUPPORT_USER->value)
            <div class="inline-flex bg-gray-100 rounded-xl p-1">
                <button onclick="toggleView('my')" id="btnViewMy" class="px-5 py-2 text-sm font-semibold rounded-lg transition-all duration-200">
                    My Tickets
                </button>
                <button onclick="toggleView('all')" id="btnViewAll" class="px-5 py-2 text-sm font-semibold rounded-lg transition-all duration-200">
                    All Tickets
                </button>
            </div>
            @endif

            @if($user->role->role_id === \App\Enums\RoleId::DELIVERY_HELPDESK->value)
            <div class="inline-flex bg-gray-100 rounded-xl p-1">
                <button onclick="toggleView('all')" id="btnViewAllHd" class="px-5 py-2 text-sm font-semibold rounded-lg transition-all duration-200">
                    <i class="fas fa-list-check text-xs mr-1"></i> All Tickets
                </button>
                <button onclick="toggleView('unassigned')" id="btnViewUnassigned" class="px-5 py-2 text-sm font-semibold rounded-lg transition-all duration-200">
                    <i class="fas fa-user-clock text-xs mr-1"></i> Unassigned
                </button>
            </div>
            @endif

            @if($user->role->role_id === \App\Enums\RoleId::DELIVERY_SUPPORT_MANAGER->value)
            <div class="inline-flex bg-gray-100 rounded-xl p-1">
                <button onclick="toggleView('all')" id="btnViewAllSm" class="px-5 py-2 text-sm font-semibold rounded-lg transition-all duration-200">
                    <i class="fas fa-list-check text-xs mr-1"></i> All Ticket
                </button>
                <button onclick="toggleView('my')" id="btnViewMySm" class="px-5 py-2 text-sm font-semibold rounded-lg transition-all duration-200">
                    <i class="fas fa-user text-xs mr-1"></i> My Ticket
                </button>
            </div>
            @endif

            @if($user->role->role_id === \App\Enums\RoleId::EC_ADMINISTRATOR->value)
            <button onclick="openCreateTicketModal()" class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                Create Ticket
            </button>
            @endif

            @if(in_array($user->role->role_id, [\App\Enums\RoleId::EC_ADMINISTRATOR->value, \App\Enums\RoleId::DELIVERY_SUPPORT_HEAD->value, \App\Enums\RoleId::DELIVERY_HELPDESK->value]))
            <a href="{{ route('ticket.export') }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-semibold rounded-lg hover:bg-gray-50 hover:border-gray-400 transition-all duration-200">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-green-600">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                </svg>
                Export Excel
            </a>
            @endif
        </div>
    </div>
</div>

<!-- Stats Cards (collapsible) -->
<div class="mb-4">
    <button onclick="toggleSection('statsSection', 'statsChevron')"
            class="flex items-center gap-2 text-sm font-semibold text-gray-600 hover:text-gray-900 transition-colors duration-150 select-none mb-2 group">
        <svg id="statsChevron" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
             class="w-4 h-4 text-gray-400 group-hover:text-gray-600 transition-transform duration-200">
            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
        </svg>
        <span class="uppercase tracking-wide">Status Info</span>
    </button>
    <div id="statsSection" class="overflow-hidden transition-all duration-300" style="max-height: 200px;">
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3 pb-2">
            <div id="filterAll" class="bg-white rounded-lg border-2 border-red-600 p-3 hover:shadow-md transition-all duration-200 cursor-pointer" onclick="filterTickets('all')">
                <p class="text-xs font-medium text-gray-500 mb-1">Total</p>
                <p class="text-2xl font-bold text-gray-900" id="totalCount">0</p>
            </div>
            <div id="filterOpen" class="bg-white rounded-lg border border-gray-200 p-3 hover:shadow-md hover:border-red-400 transition-all duration-200 cursor-pointer" onclick="filterTickets('open')">
                <p class="text-xs font-medium text-gray-500 mb-1">Open</p>
                <p class="text-2xl font-bold text-gray-900" id="openCount">0</p>
            </div>
            <div id="filterInprocess" class="bg-white rounded-lg border border-gray-200 p-3 hover:shadow-md hover:border-red-400 transition-all duration-200 cursor-pointer" onclick="filterTickets('inprocess')">
                <p class="text-xs font-medium text-gray-500 mb-1">Inprocess</p>
                <p class="text-2xl font-bold text-gray-900" id="inprocessCount">0</p>
            </div>
            <div id="filterWaitingCustomer" class="bg-white rounded-lg border border-gray-200 p-3 hover:shadow-md hover:border-red-400 transition-all duration-200 cursor-pointer" onclick="filterTickets('waiting_on_customer')">
                <p class="text-xs font-medium text-gray-500 mb-1">Waiting Customer</p>
                <p class="text-2xl font-bold text-gray-900" id="waitingCustomerCount">0</p>
            </div>
            <div id="filterWaiting3rd" class="bg-white rounded-lg border border-gray-200 p-3 hover:shadow-md hover:border-red-400 transition-all duration-200 cursor-pointer" onclick="filterTickets('waiting_on_3rd_party')">
                <p class="text-xs font-medium text-gray-500 mb-1">Waiting 3rd Party</p>
                <p class="text-2xl font-bold text-gray-900" id="waiting3rdCount">0</p>
            </div>
            <div id="filterWaitingConfirm" class="bg-white rounded-lg border border-gray-200 p-3 hover:shadow-md hover:border-red-400 transition-all duration-200 cursor-pointer" onclick="filterTickets('waiting_to_confirmation')">
                <p class="text-xs font-medium text-gray-500 mb-1">Waiting Confirm</p>
                <p class="text-2xl font-bold text-gray-900" id="waitingConfirmCount">0</p>
            </div>
            <div id="filterClosed" class="bg-white rounded-lg border border-gray-200 p-3 hover:shadow-md hover:border-red-400 transition-all duration-200 cursor-pointer" onclick="filterTickets('closed')">
                <p class="text-xs font-medium text-gray-500 mb-1">Closed</p>
                <p class="text-2xl font-bold text-gray-900" id="closedCount">0</p>
            </div>
        </div>
    </div>
</div>

<!-- Pagination -->
<div class="flex items-center justify-between mb-4">
    <span class="text-sm text-gray-500">
        <span id="currentRangeStart">1</span>-<span id="currentRangeEnd">20</span> of <span id="totalItems">0</span> tickets
    </span>
    <div class="flex items-center gap-1">
        <button onclick="previousPage()" id="btnPrevPage" disabled class="p-1.5 rounded-lg border border-gray-200 hover:bg-gray-100 disabled:opacity-30 disabled:cursor-not-allowed transition-all">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-gray-600">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
            </svg>
        </button>
        <button onclick="nextPage()" id="btnNextPage" class="p-1.5 rounded-lg border border-gray-200 hover:bg-gray-100 disabled:opacity-30 disabled:cursor-not-allowed transition-all">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-gray-600">
                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
            </svg>
        </button>
    </div>
</div>

<!-- Ticket Table -->
<div id="ticketsContainer" class="hidden">
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="overflow-auto" style="max-height: calc(100vh - 360px); min-height: 200px;">
            <table class="w-full text-sm border-collapse" style="min-width: 2200px;">
                <thead class="sticky top-0 z-10 bg-gray-50">
                    <tr>
                        {{-- LAST UPDATE: sortable --}}
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap border-b border-gray-200 sticky left-0 bg-gray-50 z-20 th-sortable cursor-pointer transition-colors"
                            style="min-width:110px;" onclick="sortTickets('last_update')" title="Sort by Last Update">
                            <div class="flex items-center gap-1">
                                <span>Last Update</span>
                                <span id="sort-icon-last_update" class="sort-icon text-gray-300 font-normal normal-case tracking-normal">⇅</span>
                            </div>
                        </th>
                        {{-- TIKET: sortable --}}
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap border-b border-gray-200 sticky bg-gray-50 z-20 th-sortable cursor-pointer transition-colors"
                            style="min-width:120px;left:110px;" onclick="sortTickets('ticket_number')" title="Sort by Ticket Number">
                            <div class="flex items-center gap-1">
                                <span>Tiket</span>
                                <span id="sort-icon-ticket_number" class="sort-icon text-gray-300 font-normal normal-case tracking-normal">⇅</span>
                            </div>
                        </th>
                        {{-- DESCRIPTION: keyword search filter --}}
                        <th class="p-0 text-left whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:260px;">
                            <button type="button" id="descFilterBtn" onclick="toggleDescFilter(event)"
                                    class="w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                                <span class="text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap">Description</span>
                                <svg id="descFilterCaret" class="w-3.5 h-3.5 text-gray-500 transition-all duration-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                                <svg id="descFilterIcon" class="w-3.5 h-3.5 text-gray-300 transition-colors" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 011 1v1.586a1 1 0 01-.293.707l-4.121 4.121A1 1 0 0012 12.121V15.5l-4 1.5v-4.879a1 1 0 00-.293-.707L3.586 7.293A1 1 0 013.293 6.586L3 5z" clip-rule="evenodd"/></svg>
                            </button>
                            <div id="descFilterPanel" class="hidden absolute mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] p-3" style="min-width:260px;">
                                <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Search description</label>
                                <input type="text" id="descFilterInput" placeholder="Type keyword (case-insensitive)…"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400"
                                       oninput="onDescFilterInput()">
                                <p class="text-[10px] text-gray-400 mt-1.5">Matches any ticket whose description contains this text.</p>
                                <div class="flex justify-end gap-2 mt-3">
                                    <button type="button" onclick="clearDescFilter()" class="px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50">Clear</button>
                                    <button type="button" onclick="closeDescFilter()" class="px-3 py-1.5 text-xs text-white bg-red-700 hover:bg-red-800 rounded-md">Done</button>
                                </div>
                            </div>
                        </th>
                        {{-- DATE: from-to range filter (also supports sort) --}}
                        <th class="p-0 text-left whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:120px;">
                            <button type="button" id="dateFilterBtn" onclick="toggleDateFilter(event)"
                                    class="w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                                <span class="text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap">Date</span>
                                <svg id="dateFilterCaret" class="w-3.5 h-3.5 text-gray-500 transition-all duration-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                                <svg id="dateFilterIcon" class="w-3.5 h-3.5 text-gray-300 transition-colors" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 011 1v1.586a1 1 0 01-.293.707l-4.121 4.121A1 1 0 0012 12.121V15.5l-4 1.5v-4.879a1 1 0 00-.293-.707L3.586 7.293A1 1 0 013.293 6.586L3 5z" clip-rule="evenodd"/></svg>
                            </button>
                            <div id="dateFilterPanel" class="hidden absolute mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] p-3" style="min-width:240px;">
                                <div class="space-y-2">
                                    <div>
                                        <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">From</label>
                                        <input type="date" id="dateFilterFrom"
                                               class="w-full px-3 py-1.5 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">To</label>
                                        <input type="date" id="dateFilterTo"
                                               class="w-full px-3 py-1.5 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
                                    </div>
                                    <p id="dateFilterError" class="hidden text-xs text-red-500">"To" must be on/after "From".</p>
                                </div>
                                <div class="border-t border-gray-100 mt-3 pt-2">
                                    <span class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Sort</span>
                                    <div class="flex gap-2">
                                        <button type="button" onclick="setDateSort('asc')"  id="dateSortAsc"  class="flex-1 px-2 py-1 text-xs border border-gray-200 rounded-md hover:bg-gray-50">↑ Oldest</button>
                                        <button type="button" onclick="setDateSort('desc')" id="dateSortDesc" class="flex-1 px-2 py-1 text-xs border border-gray-200 rounded-md hover:bg-gray-50">↓ Newest</button>
                                    </div>
                                </div>
                                <div class="flex justify-end gap-2 mt-3">
                                    <button type="button" onclick="clearDateFilter()" class="px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50">Clear</button>
                                    <button type="button" onclick="applyDateFilter()" class="px-3 py-1.5 text-xs text-white bg-red-700 hover:bg-red-800 rounded-md">Apply</button>
                                </div>
                            </div>
                        </th>
                        {{-- CUSTOMER: column filter dropdown --}}
                        <th class="p-0 text-left whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:160px;">
                            <div class="custom-dd relative w-full" id="ddColFilterCustomer" data-fixed="true" data-onchange="applyColFilter" data-searchable="true">
                                <button type="button" class="custom-dd-btn w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                                    <span class="text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap">Customer</span>
                                    <svg class="custom-dd-arrow w-3.5 h-3.5 text-gray-500 transition-all duration-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <input type="hidden" id="colFilterCustomer" value="">
                                <div class="custom-dd-panel hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] py-1.5 overflow-y-auto" style="max-height:240px;min-width:200px;">
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="">All</button>
                                </div>
                            </div>
                        </th>
                        {{-- Ticket Lead: column filter dropdown --}}
                        <th class="p-0 text-left whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:120px;">
                            <div class="custom-dd relative w-full" id="ddColFilterPic" data-fixed="true" data-onchange="applyColFilter" data-searchable="true">
                                <button type="button" class="custom-dd-btn w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                                    <span class="text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap">Ticket Lead</span>
                                    <svg class="custom-dd-arrow w-3.5 h-3.5 text-gray-500 transition-all duration-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <input type="hidden" id="colFilterPic" value="">
                                <div class="custom-dd-panel hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] py-1.5 overflow-y-auto" style="max-height:240px;min-width:200px;">
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="">All</button>
                                </div>
                            </div>
                        </th>
                        {{-- PRIORITY: column filter dropdown --}}
                        <th class="p-0 text-left whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:90px;">
                            <div class="custom-dd relative w-full" id="ddColFilterPriority" data-fixed="true" data-onchange="applyColFilter">
                                <button type="button" class="custom-dd-btn w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                                    <span class="text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap">Priority</span>
                                    <svg class="custom-dd-arrow w-3.5 h-3.5 text-gray-500 transition-all duration-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <input type="hidden" id="colFilterPriority" value="">
                                <div class="custom-dd-panel hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] py-1.5 overflow-y-auto" style="max-height:220px;min-width:140px;">
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="">All</button>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="Very High">Very High</button>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="High">High</button>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="Medium">Medium</button>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="Low">Low</button>
                                </div>
                            </div>
                        </th>
                        {{-- SCALE: column filter dropdown --}}
                        <th class="p-0 text-left whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:80px;">
                            <div class="custom-dd relative w-full" id="ddColFilterScale" data-fixed="true" data-onchange="applyColFilter">
                                <button type="button" class="custom-dd-btn w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                                    <span class="text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap">Scale</span>
                                    <svg class="custom-dd-arrow w-3.5 h-3.5 text-gray-500 transition-all duration-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <input type="hidden" id="colFilterScale" value="">
                                <div class="custom-dd-panel hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] py-1.5 overflow-y-auto" style="max-height:220px;min-width:120px;">
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="">All</button>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="Simple">Simple</button>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="Medium">Medium</button>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="Complex">Complex</button>
                                </div>
                            </div>
                        </th>
                        {{-- STATUS: column filter dropdown --}}
                        <th class="p-0 text-left whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:120px;">
                            <div class="custom-dd relative w-full" id="ddColFilterStatus" data-fixed="true" data-onchange="applyColFilter">
                                <button type="button" class="custom-dd-btn w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                                    <span class="text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap">Status</span>
                                    <svg class="custom-dd-arrow w-3.5 h-3.5 text-gray-500 transition-all duration-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <input type="hidden" id="colFilterStatus" value="">
                                <div class="custom-dd-panel hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] py-1.5 overflow-y-auto" style="max-height:240px;min-width:200px;">
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="">All</button>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="open">Open</button>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="inprocess">Inprocess</button>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="waiting_on_customer">Waiting on Customer</button>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="waiting_on_3rd_party">Waiting on 3rd Party</button>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="waiting_to_confirmation">Waiting to Confirmation</button>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="hold">Hold</button>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="cancelled">Cancelled</button>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="closed">Closed</button>
                                </div>
                            </div>
                        </th>
                        {{-- TYPE: column filter dropdown --}}
                        <th class="p-0 text-left whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:130px;">
                            <div class="custom-dd relative w-full" id="ddColFilterType" data-fixed="true" data-onchange="applyColFilter">
                                <button type="button" class="custom-dd-btn w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                                    <span class="text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap">Type</span>
                                    <svg class="custom-dd-arrow w-3.5 h-3.5 text-gray-500 transition-all duration-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <input type="hidden" id="colFilterType" value="">
                                <div class="custom-dd-panel hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] py-1.5 overflow-y-auto" style="max-height:220px;min-width:170px;">
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="">All</button>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="Incident">Incident</button>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="Service Request">Service Request</button>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="Change Request">Change Request</button>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="Consult">Consult</button>
                                </div>
                            </div>
                        </th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap border-b border-gray-200" style="min-width:130px;">Assign Delivery</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap border-b border-gray-200" style="min-width:140px;">Customer Mandays</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap border-b border-gray-200" style="min-width:160px;">Progress</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap border-b border-gray-200" style="min-width:170px;">Target Respon Time (Hour)</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap border-b border-gray-200" style="min-width:150px;">Respon Time (Hour)</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap border-b border-gray-200" style="min-width:150px;">Respon Time Status</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap border-b border-gray-200" style="min-width:170px;">Target Resolution Time</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap border-b border-gray-200" style="min-width:200px;">Due Date/Time Resolution Time</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap border-b border-gray-200" style="min-width:140px;">Resolution Time</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap border-b border-gray-200" style="min-width:160px;">Resolution Time Status</th>
                    </tr>
                </thead>
                <tbody id="ticketsListBody" class="divide-y divide-gray-100 bg-white"></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Loading State -->
<div id="loadingState" class="text-center py-16 bg-white rounded-xl border border-gray-200 shadow-sm">
    <svg class="animate-spin h-8 w-8 text-red-700 mx-auto mb-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
    </svg>
    <p class="text-gray-500 text-sm font-medium">Loading tickets...</p>
</div>

<!-- Empty State -->
<div id="emptyState" class="hidden text-center py-16 bg-white rounded-xl border border-gray-200 shadow-sm">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 text-gray-300 mx-auto mb-3">
        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
    </svg>
    <p class="text-gray-600 font-semibold mb-1">No tickets found</p>
    <p class="text-gray-400 text-xs mb-4">Try adjusting your filters</p>
    <button onclick="resetFilters()" class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">Clear Filters</button>
</div>

<!-- Create Ticket Modal (Admin) -->
@if($user->role->role_id === \App\Enums\RoleId::EC_ADMINISTRATOR->value)
<div id="createTicketModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 overflow-hidden">
    <div class="h-full flex items-center justify-center p-4">
        <div class="bg-white rounded-xl w-full max-w-lg shadow-2xl">
            <div class="flex items-center justify-between px-6 py-5 border-b border-gray-200">
                <h3 class="text-xl font-bold text-gray-900">Create New Ticket</h3>
                <button onclick="closeCreateTicketModal()" class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-600 hover:bg-red-800 hover:text-white transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <form id="createTicketForm" onsubmit="submitCreateTicket(event)" class="p-6 space-y-4">
                <div>
                    <label class="text-xs font-semibold text-gray-600 mb-2 block uppercase tracking-wide">Customer</label>
                    <div class="relative">
                        <input type="text" id="customerSearch"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent"
                            placeholder="Search customer..."
                            autocomplete="off"
                            onfocus="showCustomerDropdown()"
                            oninput="filterCustomers()">
                        <input type="hidden" id="newCustomerId" required>
                        <div id="customerDropdown" class="hidden absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                            @foreach($customers as $customer)
                                <div class="customer-option px-4 py-3 hover:bg-gray-100 cursor-pointer text-sm border-b border-gray-100 last:border-0"
                                     data-id="{{ $customer['customer_id'] }}"
                                     data-name="{{ $customer['name'] }}"
                                     data-code="{{ $customer['customer_code'] }}"
                                     onclick="selectCustomer(this)">
                                    <div class="font-medium text-gray-900">{{ $customer['name'] }}</div>
                                    <div class="text-xs text-gray-500">{{ $customer['customer_code'] }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600 mb-2 block uppercase tracking-wide">Description</label>
                    <textarea id="newDescription" required rows="3" class="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent" placeholder="Describe the issue..."></textarea>
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600 mb-2 block uppercase tracking-wide">Priority</label>
                    <select id="newPriority" required class="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
                        <option value="Very High">Very High</option>
                        <option value="High">High</option>
                        <option value="Medium" selected>Medium</option>
                        <option value="Low">Low</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600 mb-2 block uppercase tracking-wide">Ticket Type <span class="text-gray-400 font-normal normal-case">(optional)</span></label>
                    <select id="newTicketType" class="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
                        <option value="">-- Select Type --</option>
                        <option value="Incident">Incident</option>
                        <option value="Service Request">Service Request</option>
                        <option value="Change Request">Change Request</option>
                        <option value="Consult">Consult</option>
                    </select>
                </div>
                <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                    <button type="button" onclick="closeCreateTicketModal()" class="inline-flex items-center px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all duration-200">Cancel</button>
                    <button type="submit" id="btnCreateTicket" class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">Create Ticket</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

<style>
/* Collapsible sections */
#statsSection {
    transition: max-height 0.25s ease, opacity 0.2s ease;
}
#statsSection[style*="max-height: 0"] {
    opacity: 0;
}
#statsChevron {
    transition: transform 0.2s ease;
}

/* ── Sort column headers ── */
thead th.th-sortable { user-select: none; }
thead th.th-sortable:hover { background: #f3f4f6; }
.sort-icon { font-style: normal; transition: color 0.15s; }
.sort-icon.active { color: #111827; }

/* ── Column filter dropdown active state (value selected) ── */
.custom-dd.col-dd-active .custom-dd-arrow {
    color: #111827;
}
.custom-dd.col-dd-active .custom-dd-btn > span {
    color: #111827;
}

/* View Toggle */
#btnViewAll, #btnViewMy { background: transparent; color: #6b7280; }
#btnViewAll.active, #btnViewMy.active { background: white; color: #111827; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
#btnViewAllHd, #btnViewUnassigned { background: transparent; color: #6b7280; }
#btnViewAllHd.active, #btnViewUnassigned.active { background: #991b1b; color: white; box-shadow: 0 1px 3px rgba(153,27,27,0.3); }
#btnViewAllSm, #btnViewMySm { background: transparent; color: #6b7280; }
#btnViewAllSm.active, #btnViewMySm.active { background: #991b1b; color: white; box-shadow: 0 1px 3px rgba(153,27,27,0.3); }

/* Table rows */
#ticketsListBody tr { cursor: pointer; transition: background 0.15s; }
#ticketsListBody tr:hover { background: #fafafa; }

/* ── Unread ticket row — blue (customer email) ── */
#ticketsListBody tr.ticket-unread-customer {
    background: #f0f7ff;
}
#ticketsListBody tr.ticket-unread-customer:hover {
    background: #e6f0fd;
}
#ticketsListBody tr.ticket-unread-customer td:first-child {
    border-left: 3px solid #93c5fd;
    padding-left: 10px;
}
#ticketsListBody tr.ticket-unread-customer td:first-child,
#ticketsListBody tr.ticket-unread-customer td:nth-child(2) {
    background: #f0f7ff;
}
#ticketsListBody tr.ticket-unread-customer:hover td:first-child,
#ticketsListBody tr.ticket-unread-customer:hover td:nth-child(2) {
    background: #e6f0fd;
}

/* ── Unread ticket row — yellow (internal note) ── */
#ticketsListBody tr.ticket-unread-internal {
    background: #fffbeb;
}
#ticketsListBody tr.ticket-unread-internal:hover {
    background: #fef3c7;
}
#ticketsListBody tr.ticket-unread-internal td:first-child {
    border-left: 3px solid #fbbf24;
    padding-left: 10px;
}
#ticketsListBody tr.ticket-unread-internal td:first-child,
#ticketsListBody tr.ticket-unread-internal td:nth-child(2) {
    background: #fffbeb;
}
#ticketsListBody tr.ticket-unread-internal:hover td:first-child,
#ticketsListBody tr.ticket-unread-internal:hover td:nth-child(2) {
    background: #fef3c7;
}

/* ── Unread dots ── */
.unread-dot {
    display: inline-block;
    width: 7px; height: 7px;
    border-radius: 50%;
    vertical-align: middle;
    margin-right: 5px;
    flex-shrink: 0;
}
.unread-dot-blue {
    background: #3b82f6;
    box-shadow: 0 0 0 2px #dbeafe;
}
.unread-dot-yellow {
    background: #f59e0b;
    box-shadow: 0 0 0 2px #fde68a;
}

/* Sticky columns */
#ticketsListBody tr td:first-child,
#ticketsListBody tr td:nth-child(2) {
    z-index: 5;
    box-shadow: 2px 0 4px rgba(0,0,0,0.04);
}
#ticketsListBody tr:hover td:first-child,
#ticketsListBody tr:hover td:nth-child(2) { background: #fafafa; }
</style>

<script>
    let allTickets = [];
    let filteredTickets = [];
    let currentFilter = 'all';
    let currentTicketSort = { key: 'last_update', dir: 'desc' };
    let itemsPerPage = 20;
    let currentPage = 1;
    let totalItems = 0;
    let totalPages = 0;
    let userRole                      = {{ $user->role->role_id ?? 0 }};
    let currentEmployeeId             = {{ $currentEmployeeId ?? 'null' }};
    const EC_ADMINISTRATOR_ROLE       = {{ \App\Enums\RoleId::EC_ADMINISTRATOR->value }};
    const DELIVERY_SUPPORT_USER_ROLE  = {{ \App\Enums\RoleId::DELIVERY_SUPPORT_USER->value }};
    const EC_USER_ROLE                = {{ \App\Enums\RoleId::EC_USER->value }};
    const HELPDESK_ROLE               = {{ \App\Enums\RoleId::DELIVERY_HELPDESK->value }};
    const SUPPORT_MANAGER_ROLE        = {{ \App\Enums\RoleId::DELIVERY_SUPPORT_MANAGER->value }};
    // Roles that use the All/Unassigned toggle (Helpdesk only)
    const STAFF_TOGGLE_ROLES          = [HELPDESK_ROLE];
    let currentView = (userRole === DELIVERY_SUPPORT_USER_ROLE || userRole === SUPPORT_MANAGER_ROLE) ? 'my' : 'all';
    let sortField = null; // 'last_update' | 'ticket_number' | 'date'
    let sortDir   = null; // 'desc' | 'asc'

    function getViewBase() {
        if (STAFF_TOGGLE_ROLES.includes(userRole)) {
            if (currentView === 'unassigned') return allTickets.filter(t => t.ticket_lead_id === null);
            return allTickets; // 'all' = semua tiket tanpa filter assigned/unassigned
        }
        // Support Manager: server already returns the correct set per currentView
        return allTickets;
    }

    document.addEventListener('DOMContentLoaded', function() {
        if (typeof initCustomDropdowns === 'function') initCustomDropdowns();
        loadTickets();
        if (userRole === EC_ADMINISTRATOR_ROLE || userRole === DELIVERY_SUPPORT_USER_ROLE || STAFF_TOGGLE_ROLES.includes(userRole) || userRole === SUPPORT_MANAGER_ROLE) updateViewToggle();
        startEmailPolling();
    });

    // -------------------------------------------------------------------------
    // Ticket polling: cek update tiket setiap 30 detik dari DB lokal (bukan Graph API)
    // Email inbox diproses server-side oleh scheduler (email:process-inbox tiap menit)
    // -------------------------------------------------------------------------
    let _lastTicketUpdate = null;

    async function checkTicketUpdates() {
        try {
            const res = await fetch('/ticket/latest-update', {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            });
            if (!res.ok) return;
            const data = await res.json();
            const latest = data.latest_update ?? null;
            if (latest && latest !== _lastTicketUpdate) {
                if (_lastTicketUpdate !== null) loadTickets();
                _lastTicketUpdate = latest;
            }
        } catch (err) {
            console.warn('[Ticket Polling] error:', err.message);
        }
    }

    function startEmailPolling() {
        checkTicketUpdates();
        setInterval(checkTicketUpdates, 15000);
    }

    function toggleView(view) {
        currentView = view;
        updateViewToggle();
        if (STAFF_TOGGLE_ROLES.includes(userRole)) {
            // Helpdesk: all tickets already loaded — filter client-side for unassigned, no re-fetch
            currentFilter = 'all';
            currentPage   = 1;
            filteredTickets = getViewBase();
            updateStats();
            renderTickets();
        } else {
            // Support Manager and others: re-fetch from server
            loadTickets();
        }
    }

    function updateViewToggle() {
        if (userRole === EC_ADMINISTRATOR_ROLE || userRole === DELIVERY_SUPPORT_USER_ROLE) {
            const btnAll = document.getElementById('btnViewAll');
            const btnMy  = document.getElementById('btnViewMy');
            if (btnAll && btnMy) {
                btnAll.classList.toggle('active', currentView === 'all');
                btnMy.classList.toggle('active',  currentView !== 'all');
            }
        }
        if (STAFF_TOGGLE_ROLES.includes(userRole)) {
            const btnA = document.getElementById('btnViewAllHd');
            const btnU = document.getElementById('btnViewUnassigned');
            if (btnA && btnU) {
                btnA.classList.toggle('active', currentView === 'all');
                btnU.classList.toggle('active', currentView === 'unassigned');
            }
        }
        if (userRole === SUPPORT_MANAGER_ROLE) {
            const btnA = document.getElementById('btnViewAllSm');
            const btnM = document.getElementById('btnViewMySm');
            if (btnA && btnM) {
                btnA.classList.toggle('active', currentView === 'all');
                btnM.classList.toggle('active', currentView === 'my');
            }
        }
    }

    async function loadTickets() {
        try {
            document.getElementById('loadingState').classList.remove('hidden');
            document.getElementById('ticketsContainer').classList.add('hidden');
            document.getElementById('emptyState').classList.add('hidden');

            let endpoint = '/api/tickets';
            if (userRole === EC_USER_ROLE) endpoint = '/api/tickets/my';
            else if ((userRole === EC_ADMINISTRATOR_ROLE || userRole === DELIVERY_SUPPORT_USER_ROLE) && currentView === 'my') endpoint = '/api/tickets/my';
            else if (userRole === SUPPORT_MANAGER_ROLE && currentView === 'my') endpoint = '/api/tickets/my';

            const response = await fetch(endpoint, {
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            });

            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) throw new Error('Non-JSON response');

            const data = await response.json();

            if (data.success) {
                allTickets = data.data.sort((a, b) => new Date(b.last_message_at || b.created_at) - new Date(a.last_message_at || a.created_at));
                populateCustomerFilter();
                populatePicFilter();
                filteredTickets = getViewBase();
                populateCustomerFilter();
                populatePicFilter();
                updateStats();
                renderTickets();
            } else {
                showNotification('Failed to load tickets', 'error');
                document.getElementById('loadingState').classList.add('hidden');
                document.getElementById('emptyState').classList.remove('hidden');
            }
        } catch (error) {
            console.error('Error:', error);
            showNotification('Failed to load tickets: ' + error.message, 'error');
            document.getElementById('loadingState').classList.add('hidden');
            document.getElementById('emptyState').classList.remove('hidden');
        }
    }

    function updateStats() {
        const base = getViewBase();
        document.getElementById('totalCount').textContent          = base.length;
        document.getElementById('openCount').textContent           = base.filter(t => t.status === 'open').length;
        document.getElementById('inprocessCount').textContent      = base.filter(t => t.status === 'inprocess').length;
        document.getElementById('waitingCustomerCount').textContent = base.filter(t => t.status === 'waiting_on_customer').length;
        document.getElementById('waiting3rdCount').textContent     = base.filter(t => t.status === 'waiting_on_3rd_party').length;
        document.getElementById('waitingConfirmCount').textContent  = base.filter(t => t.status === 'waiting_to_confirmation').length;
        document.getElementById('closedCount').textContent         = base.filter(t => t.status === 'closed').length;
    }

    function renderTickets() {
        const listBody = document.getElementById('ticketsListBody');
        const container = document.getElementById('ticketsContainer');

        document.getElementById('loadingState').classList.add('hidden');
        totalItems = filteredTickets.length;
        totalPages = Math.ceil(totalItems / itemsPerPage);

        if (filteredTickets.length === 0) {
            container.classList.add('hidden');
            document.getElementById('emptyState').classList.remove('hidden');
            updatePaginationDisplay();
            return;
        }

        container.classList.remove('hidden');
        document.getElementById('emptyState').classList.add('hidden');

        const startIndex = (currentPage - 1) * itemsPerPage;
        const endIndex = Math.min(startIndex + itemsPerPage, totalItems);
        let displayTickets = applyTicketSort(filteredTickets);
        const paginatedTickets = displayTickets.slice(startIndex, endIndex);

        listBody.innerHTML = paginatedTickets.map(ticket => createTicketRow(ticket)).join('');
        updatePaginationDisplay();
    }

    function relativeTime(date) {
        const now     = new Date();
        const diffMs  = now - date;
        const diffSec = Math.floor(diffMs / 1000);
        const diffMin = Math.floor(diffSec / 60);
        const diffHr  = Math.floor(diffMin / 60);
        const diffDay = Math.floor(diffHr  / 24);
        const diffWk  = Math.floor(diffDay / 7);
        const diffMo  = Math.floor(diffDay / 30);
        const diffYr  = Math.floor(diffDay / 365);

        if (diffSec < 60)  return 'Just now';
        if (diffMin === 1) return '1 minute ago';
        if (diffMin < 60)  return `${diffMin} minutes ago`;
        if (diffHr  === 1) return '1 hour ago';
        if (diffHr  < 24)  return `${diffHr} hours ago`;
        if (diffDay === 1) return 'Yesterday';
        if (diffDay < 7)   return `${diffDay} days ago`;
        if (diffWk  === 1) return '1 week ago';
        if (diffWk  < 5)   return `${diffWk} weeks ago`;
        if (diffMo  === 1) return '1 month ago';
        if (diffMo  < 12)  return `${diffMo} months ago`;
        if (diffYr  === 1) return '1 year ago';
        return `${diffYr} years ago`;
    }

    function createTicketRow(ticket) {
        const customerName = ticket.customer?.customer_name || 'Unknown';
        const lastActivity = new Date(ticket.last_message_at || ticket.created_at);
        const createdAt    = new Date(ticket.created_at);
        const endDate      = ticket.end_date ? new Date(ticket.end_date) : null;

        const fmt    = d => d.toLocaleDateString('en-GB', { timeZone: 'Asia/Jakarta', day: '2-digit', month: 'short', year: 'numeric' });
        const fmtDT  = d => d.toLocaleString('en-GB',    { timeZone: 'Asia/Jakarta', day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false });

        const lastUpdateStr = relativeTime(lastActivity);
        const lastUpdateTitle = fmtDT(lastActivity);
        const dateStr       = fmt(createdAt);
        const endDateStr    = endDate ? fmt(endDate) : '—';

        const agentName = ticket.employee?.employee_name || '<span class="text-gray-400">Unassigned</span>';

        const priorityColors = {
            'Very High': 'bg-purple-100 text-purple-700',
            'High':      'bg-red-100 text-red-700',
            'Medium':    'bg-blue-100 text-blue-700',
            'Low':       'bg-green-100 text-green-700'
        };
        const priorityClass = priorityColors[ticket.ticket_priority] || 'bg-gray-100 text-gray-500';
        const priorityLabel = ticket.ticket_priority || '—';

        const statusMap = {
            'open':                    { label: 'Open',                    cls: 'bg-blue-50 text-blue-700' },
            'inprocess':               { label: 'Inprocess',               cls: 'bg-yellow-50 text-yellow-700' },
            'waiting_on_customer':     { label: 'Waiting on Customer',     cls: 'bg-amber-50 text-amber-700' },
            'waiting_on_3rd_party':    { label: 'Waiting on 3rd Party',    cls: 'bg-indigo-50 text-indigo-700' },
            'waiting_to_confirmation': { label: 'Waiting to Confirmation', cls: 'bg-teal-50 text-teal-700' },
            'hold':                    { label: 'Hold',                    cls: 'bg-orange-50 text-orange-700' },
            'cancelled':               { label: 'Cancelled',               cls: 'bg-gray-100 text-gray-500' },
            'closed':                  { label: 'Closed',                  cls: 'bg-green-50 text-green-700' },
        };
        const typeColors = {
            'Incident':       'bg-red-50 text-red-600',
            'Service Request':'bg-indigo-50 text-indigo-600',
            'Change Request': 'bg-amber-50 text-amber-600',
            'Consult':        'bg-teal-50 text-teal-600',
        };

        const scaleColors = {
            'Simple':  'bg-sky-50 text-sky-600',
            'Medium':  'bg-amber-50 text-amber-600',
            'Complex': 'bg-rose-50 text-rose-600',
        };

        const sInfo = statusMap[ticket.status] || { label: ticket.status || '—', cls: 'bg-gray-100 text-gray-500' };
        const typeLabel = ticket.ticket_type || '—';
        const typeCls   = typeColors[ticket.ticket_type] || 'bg-gray-100 text-gray-500';

        const mandays = ticket.customer_mandays != null ? parseFloat(ticket.customer_mandays).toFixed(1) : '—';

        // ── Unread detection ──
        // Blue   = customer email belum dibalas (last_customer_reply_at > last_agent_reply_at)
        // Yellow = ada internal note dari orang LAIN yang belum ada public reply setelahnya,
        //          DAN pengirim note terakhir bukan kamu sendiri
        // Priority: yellow > blue
        const lastCustomer   = ticket.last_customer_reply_at  ? new Date(ticket.last_customer_reply_at)  : null;
        const lastInternal   = ticket.last_internal_note_at   ? new Date(ticket.last_internal_note_at)   : null;
        const lastAgent      = ticket.last_agent_reply_at     ? new Date(ticket.last_agent_reply_at)     : null;
        const lastNoteSender = ticket.last_internal_note_sender_id;

        const hasUnreadCustomer = lastCustomer && (!lastAgent || lastCustomer > lastAgent);
        // Yellow menyala jika note terakhir dikirim orang LAIN (bukan saya)
        // Tidak bergantung pada last_agent_reply_at — email reply tidak menghapus yellow
        const hasUnreadInternal = lastInternal
            && (Number(lastNoteSender) !== currentEmployeeId);

        let unreadCls = '', dot = '', timeColor = 'text-gray-500', numColor = 'text-gray-800';
        if (hasUnreadInternal) {
            unreadCls  = 'ticket-unread-internal';
            dot        = '<span class="unread-dot unread-dot-yellow" title="Ada internal note belum dibalas"></span>';
            timeColor  = 'text-amber-600 font-semibold';
            numColor   = 'text-amber-700';
        } else if (hasUnreadCustomer) {
            unreadCls  = 'ticket-unread-customer';
            dot        = '<span class="unread-dot unread-dot-blue" title="Customer belum dibalas"></span>';
            timeColor  = 'text-blue-600 font-semibold';
            numColor   = 'text-blue-700';
        }

        const badge = (label, cls) => `<span class="inline-block px-2 py-0.5 rounded text-[11px] font-semibold ${cls}">${label}</span>`;
        const cell  = (content, extraCls = '') => `<td class="px-3 py-2.5 text-sm text-gray-700 whitespace-nowrap ${extraCls}">${content}</td>`;
        const dash  = () => `<td class="px-3 py-2.5 text-sm text-gray-300 whitespace-nowrap">—</td>`;

        return `<tr class="${unreadCls}" onclick="window.location='/ticket/${ticket.ticket_id}'">
            <td class="px-3 py-2.5 whitespace-nowrap sticky left-0 bg-white" title="${lastUpdateTitle}">
                ${dot}<span class="text-xs ${timeColor}">${lastUpdateStr}</span>
            </td>
            <td class="px-3 py-2.5 whitespace-nowrap sticky bg-white border-r border-gray-100" style="left:110px;">
                <span class="font-mono text-xs font-semibold ${numColor}">${ticket.ticket_number || '—'}</span>
            </td>
            <td class="px-3 py-2.5 text-sm text-gray-700" style="min-width:260px;max-width:320px;">
                <span class="block truncate" title="${(ticket.description||'').replace(/"/g,'&quot;')}">${ticket.description || '—'}</span>
            </td>
            ${cell(dateStr)}
            ${cell(`<span class="font-medium text-gray-900">${customerName}</span>${ticket.end_customer_name ? '<span class="block text-xs text-gray-400 mt-0.5">&#8627; ' + ticket.end_customer_name + '</span>' : ''}`)}
            ${cell(ticket.employee?.employee_name || '<span class="text-gray-400 text-xs">Unassigned</span>')}
            ${cell(badge(priorityLabel, priorityClass))}
            ${ticket.scale ? cell(badge(ticket.scale, scaleColors[ticket.scale] || 'bg-gray-100 text-gray-500')) : dash()}
            ${cell(badge(sInfo.label, sInfo.cls))}
            ${cell(ticket.ticket_type ? badge(typeLabel, typeCls) : '—')}
            ${dash()}
            ${cell(mandays !== '—' ? `<span class="font-medium">${mandays}</span>` : '—')}
            <td class="px-3 py-2.5 whitespace-nowrap">
                ${(function() {
                    const pct = parseFloat(ticket.all_consultant_progress) || 0;
                    const barCls = pct >= 75 ? 'bg-green-500' : pct >= 40 ? 'bg-yellow-400' : 'bg-red-400';
                    const txtCls = pct >= 75 ? 'text-green-700' : pct >= 40 ? 'text-yellow-700' : 'text-red-600';
                    if (pct === 0 && !ticket.man_days) return '<span class="text-gray-300 text-xs">—</span>';
                    return `<div class="flex items-center gap-1.5">
                        <div class="bg-gray-200 rounded-full h-1.5" style="width:80px">
                            <div class="${barCls} h-1.5 rounded-full" style="width:${pct}%"></div>
                        </div>
                        <span class="text-xs font-bold ${txtCls}">${pct}%</span>
                    </div>`;
                })()}
            </td>
            ${dash()}
            ${dash()}
            ${dash()}
            ${dash()}
            ${cell(endDateStr)}
            ${dash()}
            ${dash()}
        </tr>`;
    }

    function updatePaginationDisplay() {
        const startIndex = (currentPage - 1) * itemsPerPage + 1;
        const endIndex = Math.min(currentPage * itemsPerPage, totalItems);
        document.getElementById('currentRangeStart').textContent = totalItems > 0 ? startIndex : 0;
        document.getElementById('currentRangeEnd').textContent = endIndex;
        document.getElementById('totalItems').textContent = totalItems;
        document.getElementById('btnPrevPage').disabled = currentPage === 1;
        document.getElementById('btnNextPage').disabled = currentPage >= totalPages;
    }

    function previousPage() { if (currentPage > 1) { currentPage--; renderTickets(); } }
    function nextPage() { if (currentPage < totalPages) { currentPage++; renderTickets(); } }

    function filterTickets(status) {
        currentFilter = status;
        ['filterAll', 'filterOpen', 'filterInprocess', 'filterWaitingCustomer', 'filterWaiting3rd', 'filterWaitingConfirm', 'filterClosed'].forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            el.classList.remove('border-red-600', 'shadow-md', 'border-2');
            el.classList.add('border-gray-200', 'border');
        });

        const filterMap = {
            'all':                    'filterAll',
            'open':                   'filterOpen',
            'inprocess':              'filterInprocess',
            'waiting_on_customer':    'filterWaitingCustomer',
            'waiting_on_3rd_party':   'filterWaiting3rd',
            'waiting_to_confirmation':'filterWaitingConfirm',
            'closed':                 'filterClosed',
        };
        if (filterMap[status]) {
            const el = document.getElementById(filterMap[status]);
            if (el) {
                el.classList.remove('border-gray-200', 'border');
                el.classList.add('border-red-600', 'shadow-md', 'border-2');
            }
        }

        applyAdvancedFilters();
    }

    function populateCustomerFilter() {
        const ddEl = document.getElementById('ddColFilterCustomer');
        if (!ddEl) return;
        // Panel may be detached to document.body (fixed mode) — use stored ref
        const panel = ddEl._ddPanel || ddEl.querySelector('.custom-dd-panel');
        if (!panel) return;

        // Remove existing items only (preserve injected search wrap + empty state)
        panel.querySelectorAll('.custom-dd-item').forEach(el => el.remove());

        const seen = new Set();
        const names = [];
        allTickets.forEach(t => {
            const name = t.customer?.customer_name;
            if (name && !seen.has(name)) { seen.add(name); names.push(name); }
        });
        names.sort((a, b) => a.localeCompare(b));

        const makeItem = (val, text) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50';
            btn.dataset.value = val;
            btn.textContent = text;
            return btn;
        };

        const fragment = document.createDocumentFragment();
        fragment.appendChild(makeItem('', 'All'));
        names.forEach(name => fragment.appendChild(makeItem(name, name)));

        // Insert before empty-state div if present, otherwise append
        const emptyEl = panel._ddEmpty || null;
        if (emptyEl) panel.insertBefore(fragment, emptyEl);
        else panel.appendChild(fragment);
    }

    function populatePicFilter() {
        const ddEl = document.getElementById('ddColFilterPic');
        if (!ddEl) return;
        const panel = ddEl._ddPanel || ddEl.querySelector('.custom-dd-panel');
        if (!panel) return;

        panel.querySelectorAll('.custom-dd-item').forEach(el => el.remove());

        const seen = new Set();
        const names = [];
        allTickets.forEach(t => {
            const name = t.employee?.employee_name;
            if (name && !seen.has(name)) { seen.add(name); names.push(name); }
        });
        names.sort((a, b) => a.localeCompare(b));

        const makeItem = (val, text) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50';
            btn.dataset.value = val;
            btn.textContent = text;
            return btn;
        };

        const fragment = document.createDocumentFragment();
        fragment.appendChild(makeItem('', 'All'));
        names.forEach(name => fragment.appendChild(makeItem(name, name)));

        const emptyEl = panel._ddEmpty || null;
        if (emptyEl) panel.insertBefore(fragment, emptyEl);
        else panel.appendChild(fragment);
    }

    function applyColFilter() {
        const colDdMap = {
            'ddColFilterCustomer': 'colFilterCustomer',
            'ddColFilterPic':      'colFilterPic',
            'ddColFilterPriority': 'colFilterPriority',
            'ddColFilterScale':    'colFilterScale',
            'ddColFilterStatus':   'colFilterStatus',
            'ddColFilterType':     'colFilterType',
        };
        Object.entries(colDdMap).forEach(([ddId, inputId]) => {
            updateColFilterActive(ddId, document.getElementById(inputId)?.value || '');
        });
        currentPage = 1;
        applyAdvancedFilters();
    }

    function updateColFilterActive(ddId, value) {
        const dd = document.getElementById(ddId);
        if (dd) dd.classList.toggle('col-dd-active', value !== '');
    }

    function applyAdvancedFilters() {
        const colCustomer = (document.getElementById('colFilterCustomer')?.value || '').toLowerCase();
        const colPic      = (document.getElementById('colFilterPic')?.value      || '').toLowerCase();
        const colPriority = document.getElementById('colFilterPriority')?.value || '';
        const colScale    = document.getElementById('colFilterScale')?.value    || '';
        const colStatus   = document.getElementById('colFilterStatus')?.value   || '';
        const colType     = document.getElementById('colFilterType')?.value     || '';

        // Date range filter (from-to inclusive, based on ticket.created_at in Asia/Jakarta)
        const dateFrom = document.getElementById('dateFilterFrom')?.value || '';
        const dateTo   = document.getElementById('dateFilterTo')?.value   || '';
        const fromMs = dateFrom ? new Date(dateFrom + 'T00:00:00+07:00').getTime() : null;
        const toMs   = dateTo   ? new Date(dateTo   + 'T23:59:59+07:00').getTime() : null;

        // Description keyword (case-insensitive substring)
        const descKw = (document.getElementById('descFilterInput')?.value || '').trim().toLowerCase();

        filteredTickets = getViewBase().filter(ticket => {
            const matchesCard      = currentFilter === 'all' || ticket.status === currentFilter;
            const matchColCustomer = !colCustomer || (ticket.customer?.customer_name || '').toLowerCase() === colCustomer;
            const matchColPic      = !colPic      || (ticket.employee?.employee_name || '').toLowerCase() === colPic;
            const matchColPriority = !colPriority || ticket.ticket_priority === colPriority;
            const matchColScale    = !colScale    || String(ticket.scale ?? '') === colScale;
            const matchColStatus   = !colStatus   || ticket.status === colStatus;
            const matchColType     = !colType     || ticket.ticket_type === colType;

            let matchDate = true;
            if (fromMs !== null || toMs !== null) {
                const created = ticket.created_at ? new Date(ticket.created_at).getTime() : NaN;
                if (Number.isNaN(created)) {
                    matchDate = false;
                } else {
                    if (fromMs !== null && created < fromMs) matchDate = false;
                    if (toMs   !== null && created > toMs)   matchDate = false;
                }
            }

            const matchDesc = !descKw || (ticket.description || '').toLowerCase().includes(descKw);

            return matchesCard
                && matchColCustomer && matchColPic && matchColPriority && matchColScale
                && matchColStatus && matchColType
                && matchDate && matchDesc;
        });
        updateColFilterIndicators();
        updateDateFilterIndicator();
        updateDescFilterIndicator();
        currentPage = 1;
        renderTickets();
    }

    // ── Date Range Filter ─────────────────────────────────────────────
    function toggleDateFilter(ev) {
        ev?.stopPropagation();
        const panel = document.getElementById('dateFilterPanel');
        const btn   = document.getElementById('dateFilterBtn');
        const open  = !panel.classList.contains('hidden');
        // close other popovers
        closeDescFilter();
        if (open) { panel.classList.add('hidden'); return; }
        positionPanelUnder(btn, panel);
        panel.classList.remove('hidden');
        updateDateSortButtons();
    }

    function applyDateFilter() {
        const from = document.getElementById('dateFilterFrom').value;
        const to   = document.getElementById('dateFilterTo').value;
        const errEl = document.getElementById('dateFilterError');
        if (from && to && to < from) { errEl.classList.remove('hidden'); return; }
        errEl.classList.add('hidden');
        document.getElementById('dateFilterPanel').classList.add('hidden');
        applyAdvancedFilters();
    }

    function clearDateFilter() {
        document.getElementById('dateFilterFrom').value = '';
        document.getElementById('dateFilterTo').value   = '';
        document.getElementById('dateFilterError').classList.add('hidden');
        applyAdvancedFilters();
    }

    function setDateSort(dir) {
        currentTicketSort = { key: 'date', dir };
        updateTicketSortIcons();
        updateDateSortButtons();
        renderTickets();
    }

    function updateDateSortButtons() {
        const asc  = document.getElementById('dateSortAsc');
        const desc = document.getElementById('dateSortDesc');
        if (!asc || !desc) return;
        const isDateSort = currentTicketSort.key === 'date';
        const activeCls   = ['bg-red-700','text-white','border-red-700','hover:bg-red-800'];
        const inactiveCls = ['border-gray-200','hover:bg-gray-50'];
        [asc, desc].forEach(b => { activeCls.forEach(c => b.classList.remove(c)); inactiveCls.forEach(c => b.classList.add(c)); });
        if (isDateSort && currentTicketSort.dir === 'asc')  { inactiveCls.forEach(c => asc.classList.remove(c));  activeCls.forEach(c => asc.classList.add(c));  }
        if (isDateSort && currentTicketSort.dir === 'desc') { inactiveCls.forEach(c => desc.classList.remove(c)); activeCls.forEach(c => desc.classList.add(c)); }
    }

    function updateDateFilterIndicator() {
        const from = document.getElementById('dateFilterFrom')?.value || '';
        const to   = document.getElementById('dateFilterTo')?.value   || '';
        const icon = document.getElementById('dateFilterIcon');
        const active = !!(from || to);
        if (icon) icon.classList.toggle('text-red-500', active);
        if (icon) icon.classList.toggle('text-gray-300', !active);
    }

    // ── Description Keyword Filter (debounced) ─────────────────────────
    let _descFilterTimer = null;
    function toggleDescFilter(ev) {
        ev?.stopPropagation();
        const panel = document.getElementById('descFilterPanel');
        const btn   = document.getElementById('descFilterBtn');
        const open  = !panel.classList.contains('hidden');
        // close other popovers
        const dp = document.getElementById('dateFilterPanel');
        if (dp) dp.classList.add('hidden');
        if (open) { panel.classList.add('hidden'); return; }
        positionPanelUnder(btn, panel);
        panel.classList.remove('hidden');
        document.getElementById('descFilterInput')?.focus();
    }

    function closeDescFilter() {
        const panel = document.getElementById('descFilterPanel');
        if (panel) panel.classList.add('hidden');
    }

    function onDescFilterInput() {
        clearTimeout(_descFilterTimer);
        _descFilterTimer = setTimeout(applyAdvancedFilters, 250);
    }

    function clearDescFilter() {
        const input = document.getElementById('descFilterInput');
        if (input) input.value = '';
        applyAdvancedFilters();
    }

    function updateDescFilterIndicator() {
        const kw   = (document.getElementById('descFilterInput')?.value || '').trim();
        const icon = document.getElementById('descFilterIcon');
        if (icon) icon.classList.toggle('text-red-500', kw !== '');
        if (icon) icon.classList.toggle('text-gray-300', kw === '');
    }

    // Position floating panel right under the column header button (handles overflow:auto)
    function positionPanelUnder(btn, panel) {
        const rect = btn.getBoundingClientRect();
        panel.style.position = 'fixed';
        panel.style.top  = (rect.bottom + 4) + 'px';
        panel.style.left = rect.left + 'px';
    }

    // Close popovers on outside click / Escape
    document.addEventListener('click', (e) => {
        const dp = document.getElementById('dateFilterPanel');
        const db = document.getElementById('dateFilterBtn');
        if (dp && !dp.classList.contains('hidden') && !dp.contains(e.target) && !db.contains(e.target)) dp.classList.add('hidden');
        const xp = document.getElementById('descFilterPanel');
        const xb = document.getElementById('descFilterBtn');
        if (xp && !xp.classList.contains('hidden') && !xp.contains(e.target) && !xb.contains(e.target)) xp.classList.add('hidden');
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            document.getElementById('dateFilterPanel')?.classList.add('hidden');
            document.getElementById('descFilterPanel')?.classList.add('hidden');
        }
    });

    function resetFilters() {
        const colFilterIds = ['colFilterCustomer','colFilterPic','colFilterPriority','colFilterScale','colFilterStatus','colFilterType'];
        const colDdIds     = ['ddColFilterCustomer','ddColFilterPic','ddColFilterPriority','ddColFilterScale','ddColFilterStatus','ddColFilterType'];
        if (typeof setCustomDropdownValue === 'function') {
            colFilterIds.forEach(id => setCustomDropdownValue(id, ''));
        } else {
            colFilterIds.forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
        }
        colDdIds.forEach(id => updateColFilterActive(id, ''));

        // Clear date range + description keyword
        const dFrom = document.getElementById('dateFilterFrom');  if (dFrom) dFrom.value = '';
        const dTo   = document.getElementById('dateFilterTo');    if (dTo)   dTo.value   = '';
        const dErr  = document.getElementById('dateFilterError'); if (dErr)  dErr.classList.add('hidden');
        const desc  = document.getElementById('descFilterInput'); if (desc)  desc.value  = '';

        currentTicketSort = { key: 'last_update', dir: 'desc' };
        updateTicketSortIcons();
        updateDateSortButtons();

        currentFilter = 'all';
        filterTickets('all');
    }

    // ── Column Filter Indicators & Customer Populate ──────────────────
    const COL_FILTER_MAP = {
        customer: 'colFilterCustomer',
        pic:      'colFilterPic',
        priority: 'colFilterPriority',
        scale:    'colFilterScale',
        status:   'colFilterStatus',
        type:     'colFilterType',
    };

    function updateColFilterIndicators() {
        Object.entries(COL_FILTER_MAP).forEach(([col, inputId]) => {
            const val  = document.getElementById(inputId)?.value || '';
            const icon = document.getElementById(`col-filter-icon-${col}`);
            if (!icon) return;
            icon.classList.toggle('text-red-500', val !== '');
            icon.classList.toggle('text-gray-300', val === '');
        });
    }

    // ── Column Sort ────────────────────────────────────────────────────
    const TICKET_SORT_KEYS = ['last_update', 'ticket_number', 'date', 'customer', 'priority', 'scale', 'status', 'type'];
    const PRIORITY_RANK    = { 'Very High': 4, 'High': 3, 'Medium': 2, 'Low': 1 };
    const SCALE_RANK       = { 'Complex': 3, 'Medium': 2, 'Simple': 1 };

    function sortTickets(key) {
        if (currentTicketSort.key === key) {
            currentTicketSort.dir = currentTicketSort.dir === 'asc' ? 'desc' : 'asc';
        } else {
            currentTicketSort = { key, dir: key === 'last_update' ? 'desc' : 'asc' };
        }
        updateTicketSortIcons();
        renderTickets();
    }

    function applyTicketSort(list) {
        const { key, dir } = currentTicketSort;
        return [...list].sort((a, b) => {
            let va, vb;
            if (key === 'last_update') {
                va = new Date(a.last_message_at || a.created_at).getTime();
                vb = new Date(b.last_message_at || b.created_at).getTime();
            } else if (key === 'ticket_number') {
                va = a.ticket_id ?? 0;
                vb = b.ticket_id ?? 0;
            } else if (key === 'date') {
                va = new Date(a.created_at).getTime();
                vb = new Date(b.created_at).getTime();
            } else if (key === 'customer') {
                va = (a.customer?.customer_name || '').toLowerCase();
                vb = (b.customer?.customer_name || '').toLowerCase();
                return dir === 'asc' ? va.localeCompare(vb) : vb.localeCompare(va);
            } else if (key === 'priority') {
                va = PRIORITY_RANK[a.ticket_priority] ?? 0;
                vb = PRIORITY_RANK[b.ticket_priority] ?? 0;
            } else if (key === 'scale') {
                va = SCALE_RANK[a.scale] ?? 0;
                vb = SCALE_RANK[b.scale] ?? 0;
            } else if (key === 'status') {
                va = (a.status || '').toLowerCase();
                vb = (b.status || '').toLowerCase();
                return dir === 'asc' ? va.localeCompare(vb) : vb.localeCompare(va);
            } else if (key === 'type') {
                va = (a.ticket_type || '').toLowerCase();
                vb = (b.ticket_type || '').toLowerCase();
                return dir === 'asc' ? va.localeCompare(vb) : vb.localeCompare(va);
            } else {
                return 0;
            }
            return dir === 'asc' ? va - vb : vb - va;
        });
    }

    function updateTicketSortIcons() {
        TICKET_SORT_KEYS.forEach(k => {
            const el = document.getElementById(`sort-icon-${k}`);
            if (!el) return;
            if (k === currentTicketSort.key) {
                el.textContent = currentTicketSort.dir === 'asc' ? '↓' : '↑';
                el.className = 'text-red-500 font-bold';
            } else {
                el.textContent = '⇅';
                el.className = 'text-gray-300';
            }
        });
    }

    function formatTimeAgo(date) {
        const tz = 'Asia/Jakarta';
        const now = new Date();
        const toDay = (d) => new Date(d.toLocaleDateString('en-CA', { timeZone: tz }));
        const todayDate  = toDay(now);
        const targetDate = toDay(date);
        const diffDays = Math.round((todayDate - targetDate) / 86400000);

        if (diffDays === 0) {
            return date.toLocaleTimeString('id-ID', { timeZone: tz, hour: '2-digit', minute: '2-digit', hour12: false });
        }
        if (diffDays === 1) return 'Yesterday';
        if (diffDays < 7) {
            return date.toLocaleDateString('en-GB', { timeZone: tz, weekday: 'short' });
        }
        if (date.getFullYear() === now.getFullYear()) {
            return date.toLocaleDateString('en-GB', { timeZone: tz, day: '2-digit', month: 'short' });
        }
        return date.toLocaleDateString('en-GB', { timeZone: tz, day: '2-digit', month: 'short', year: 'numeric' });
    }


    // ==================== ADMIN: CREATE TICKET ====================
    function openCreateTicketModal() { document.getElementById('createTicketModal').classList.remove('hidden'); }
    function closeCreateTicketModal() {
        document.getElementById('createTicketModal').classList.add('hidden');
        document.getElementById('createTicketForm').reset();
        document.getElementById('customerSearch').value = '';
        document.getElementById('newCustomerId').value = '';
        const btn = document.getElementById('btnCreateTicket');
        if (btn) { btn.disabled = false; btn.textContent = 'Create Ticket'; }
        const options = document.querySelectorAll('.customer-option');
        options.forEach(opt => opt.classList.remove('hidden'));
    }

    async function submitCreateTicket(e) {
        e.preventDefault();
        const btn = document.getElementById('btnCreateTicket');
        btn.disabled = true; btn.textContent = 'Creating…';
        const form = document.getElementById('createTicketForm');
        const ticketTypeVal = form.querySelector('#newTicketType').value;
        const data = {
            description: form.querySelector('#newDescription').value,
            ticket_priority: form.querySelector('#newPriority').value,
            customer_id: form.querySelector('#newCustomerId').value,
            ticket_type: ticketTypeVal || null,
        };
        try {
            const response = await fetch('/api/tickets', {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '' },
                credentials: 'same-origin',
                body: JSON.stringify(data)
            });
            const result = await response.json();
            if (result.success) {
                showNotification('Ticket created successfully!', 'success');
                closeCreateTicketModal();
                loadTickets();
            } else {
                showNotification(result.message || 'Failed to create ticket', 'error');
                btn.disabled = false; btn.textContent = 'Create Ticket';
            }
        } catch (error) {
            showNotification('Failed to create ticket: ' + error.message, 'error');
            btn.disabled = false; btn.textContent = 'Create Ticket';
        }
    }

    // ==================== COLLAPSIBLE SECTIONS ====================
    const _sectionOpen = { statsSection: true };

    function toggleSection(sectionId, chevronId) {
        const section = document.getElementById(sectionId);
        const chevron = document.getElementById(chevronId);
        if (!section) return;

        _sectionOpen[sectionId] = !_sectionOpen[sectionId];
        if (_sectionOpen[sectionId]) {
            section.style.maxHeight = section.scrollHeight + 'px';
            // Expand: restore natural max-height after transition so content isn't clipped
            section.addEventListener('transitionend', function onEnd() {
                section.style.maxHeight = 'none';
                section.removeEventListener('transitionend', onEnd);
            }, { once: true });
            if (chevron) chevron.style.transform = 'rotate(0deg)';
        } else {
            // Collapse: pin to current scrollHeight first so transition has a from-value
            section.style.maxHeight = section.scrollHeight + 'px';
            requestAnimationFrame(() => {
                requestAnimationFrame(() => { section.style.maxHeight = '0px'; });
            });
            if (chevron) chevron.style.transform = 'rotate(-90deg)';
        }
    }

    // Event listeners
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && document.getElementById('createTicketModal') && !document.getElementById('createTicketModal').classList.contains('hidden')) {
            closeCreateTicketModal();
        }
    });


    // ==================== CUSTOMER SEARCHABLE DROPDOWN ====================
    function showCustomerDropdown() {
        const dropdown = document.getElementById('customerDropdown');
        if (dropdown) {
            dropdown.classList.remove('hidden');
            filterCustomers();
        }
    }

    function hideCustomerDropdown() {
        const dropdown = document.getElementById('customerDropdown');
        if (dropdown) {
            setTimeout(() => dropdown.classList.add('hidden'), 200);
        }
    }

    function filterCustomers() {
        const searchInput = document.getElementById('customerSearch');
        const dropdown = document.getElementById('customerDropdown');
        if (!searchInput || !dropdown) return;

        const searchTerm = searchInput.value.toLowerCase();
        const options = dropdown.querySelectorAll('.customer-option');
        let hasVisible = false;

        options.forEach(option => {
            const name = option.dataset.name.toLowerCase();
            const code = option.dataset.code.toLowerCase();
            if (name.includes(searchTerm) || code.includes(searchTerm)) {
                option.classList.remove('hidden');
                hasVisible = true;
            } else {
                option.classList.add('hidden');
            }
        });

        if (!hasVisible) {
            dropdown.innerHTML = '<div class="px-4 py-3 text-sm text-gray-500">No customers found</div>';
        }
    }

    function selectCustomer(element) {
        const customerId = element.dataset.id;
        const customerName = element.dataset.name;
        const customerCode = element.dataset.code;

        document.getElementById('newCustomerId').value = customerId;
        document.getElementById('customerSearch').value = `${customerName} (${customerCode})`;
        document.getElementById('customerDropdown').classList.add('hidden');
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        const searchInput = document.getElementById('customerSearch');
        const dropdown = document.getElementById('customerDropdown');
        if (searchInput && dropdown && !searchInput.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.add('hidden');
        }
    });
</script>

{{-- Load custom-dd component (sama dengan Employee/Customer Management).
     filemtime cache buster supaya production auto-invalidate setiap deploy. --}}
@php
    $customDdPath = public_path('js/custom-dropdown.js');
    $customDdVer  = file_exists($customDdPath) ? filemtime($customDdPath) : time();
@endphp
<script src="/js/custom-dropdown.js?v={{ $customDdVer }}"></script>

@endsection

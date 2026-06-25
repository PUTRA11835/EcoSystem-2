@extends('dashboard')
@section('title', 'Support Tickets')
@section('page-title', 'Support Tickets')
@section('page-subtitle', 'Manage and track all support requests')
@section('content')
{{-- Quill.js (untuk editor pesan di modal Create Ticket) --}}
@if($can('ui.ticket.btn-create'))
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
@endif


{{-- ── Header Controls ──────────────────────────────────────────────────────── --}}
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-end gap-4 mb-5">
    <div class="flex items-center gap-2.5 flex-wrap">
        {{-- View toggles --}}
        @if($user->role->role_id === \App\Enums\RoleId::DELIVERY_SUPPORT_USER->value && !($isExternalEmployee ?? false))
        <div class="inline-flex bg-gray-100 rounded-xl p-1">
            <button onclick="toggleView('my')" id="btnViewMy" class="px-4 py-1.5 text-xs font-semibold rounded-lg transition-all duration-200">
                <i class="fas fa-user text-[10px] mr-1"></i>My Tickets
            </button>
            <button onclick="toggleView('all')" id="btnViewAll" class="px-4 py-1.5 text-xs font-semibold rounded-lg transition-all duration-200">
                <i class="fas fa-list text-[10px] mr-1"></i>All Tickets
            </button>
        </div>
        @elseif($user->hasRole(\App\Enums\RoleId::DELIVERY_HELPDESK->value))
        <div class="inline-flex bg-gray-100 rounded-xl p-1">
            <button onclick="toggleView('all')" id="btnViewAllHd" class="px-4 py-1.5 text-xs font-semibold rounded-lg transition-all duration-200">
                <i class="fas fa-list text-[10px] mr-1"></i>All Tickets
            </button>
            <button onclick="toggleView('unassigned')" id="btnViewUnassigned" class="px-4 py-1.5 text-xs font-semibold rounded-lg transition-all duration-200">
                <i class="fas fa-user-clock text-[10px] mr-1"></i>Unassigned
            </button>
        </div>
        @elseif($user->hasRole(\App\Enums\RoleId::DELIVERY_SUPPORT_MANAGER->value))
        <div class="inline-flex bg-gray-100 rounded-xl p-1">
            <button onclick="toggleView('my')" id="btnViewMy" class="px-4 py-1.5 text-xs font-semibold rounded-lg transition-all duration-200 active">My Tickets</button>
            <button onclick="toggleView('all')" id="btnViewAll" class="px-4 py-1.5 text-xs font-semibold rounded-lg transition-all duration-200">All Tickets</button>
        </div>
        @endif

        @if($can('ui.ticket.btn-create'))
        <button onclick="openCreateTicketModal()"
            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-all">
            <i class="fas fa-plus text-xs"></i>Create Ticket
        </button>
        @endif

        @if($user->hasAnyRole([\App\Enums\RoleId::EC_ADMINISTRATOR->value, \App\Enums\RoleId::DELIVERY_SUPPORT_HEAD->value, \App\Enums\RoleId::DELIVERY_HELPDESK->value]))
        <a href="{{ route('ticket.export') }}"
           class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-white border border-gray-200 text-gray-600 text-xs font-semibold rounded-xl hover:bg-gray-50 hover:border-gray-300 transition-all">
            <i class="fas fa-file-excel text-green-600 text-xs"></i>Export
        </a>
        @endif

    </div>
</div>

{{-- ── Status Cards ────────────────────────────────────────────────────────── --}}
<div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-2 mb-4">
    <div id="filterAll" onclick="filterTickets('all')"
         class="stat-card active-filter bg-white rounded-xl border border-gray-200 px-4 py-3.5 cursor-pointer select-none transition-all duration-200 hover:shadow-md hover:border-gray-300">
        <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest mb-2">Total</p>
        <p class="text-2xl font-bold text-gray-700 leading-none" id="totalCount">0</p>
    </div>
    <div id="filterOpen" onclick="filterTickets('open')"
         class="stat-card bg-white rounded-xl border border-gray-200 px-4 py-3.5 cursor-pointer select-none transition-all duration-200 hover:shadow-md hover:border-blue-200">
        <div class="flex items-center gap-1.5 mb-2">
            <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest">Open</p>
        </div>
        <p class="text-2xl font-bold text-blue-600 leading-none" id="openCount">0</p>
    </div>
    <div id="filterInprocess" onclick="filterTickets('inprocess')"
         class="stat-card bg-white rounded-xl border border-gray-200 px-4 py-3.5 cursor-pointer select-none transition-all duration-200 hover:shadow-md hover:border-yellow-200">
        <div class="flex items-center gap-1.5 mb-2">
            <span class="w-1.5 h-1.5 rounded-full bg-yellow-500"></span>
            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest">In Progress</p>
        </div>
        <p class="text-2xl font-bold text-yellow-600 leading-none" id="inprocessCount">0</p>
    </div>
    <div id="filterWaitingCustomer" onclick="filterTickets('waiting_on_customer')"
         class="stat-card bg-white rounded-xl border border-gray-200 px-4 py-3.5 cursor-pointer select-none transition-all duration-200 hover:shadow-md hover:border-amber-200">
        <div class="flex items-center gap-1.5 mb-2">
            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest">Wait Customer</p>
        </div>
        <p class="text-2xl font-bold text-amber-600 leading-none" id="waitingCustomerCount">0</p>
    </div>
    <div id="filterWaiting3rd" onclick="filterTickets('waiting_on_3rd_party')"
         class="stat-card bg-white rounded-xl border border-gray-200 px-4 py-3.5 cursor-pointer select-none transition-all duration-200 hover:shadow-md hover:border-indigo-200">
        <div class="flex items-center gap-1.5 mb-2">
            <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest">Wait 3rd Party</p>
        </div>
        <p class="text-2xl font-bold text-indigo-600 leading-none" id="waiting3rdCount">0</p>
    </div>
    <div id="filterWaitingConfirm" onclick="filterTickets('waiting_to_confirmation')"
         class="stat-card bg-white rounded-xl border border-gray-200 px-4 py-3.5 cursor-pointer select-none transition-all duration-200 hover:shadow-md hover:border-teal-200">
        <div class="flex items-center gap-1.5 mb-2">
            <span class="w-1.5 h-1.5 rounded-full bg-teal-500"></span>
            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest">Wait Confirm</p>
        </div>
        <p class="text-2xl font-bold text-teal-600 leading-none" id="waitingConfirmCount">0</p>
    </div>
    <div id="filterClosed" onclick="filterTickets('closed')"
         class="stat-card bg-white rounded-xl border border-gray-200 px-4 py-3.5 cursor-pointer select-none transition-all duration-200 hover:shadow-md hover:border-green-200">
        <div class="flex items-center gap-1.5 mb-2">
            <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>
            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest">Closed</p>
        </div>
        <p class="text-2xl font-bold text-green-600 leading-none" id="closedCount">0</p>
    </div>
</div>

{{-- ── Ticket Table ─────────────────────────────────────────────────────────── --}}
<div id="ticketsContainer" class="hidden">
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        {{-- Table Toolbar --}}
        <div class="flex items-center justify-between px-4 py-2.5 border-b border-gray-100 bg-gray-50/60">
            <p class="text-xs text-gray-400">
                Showing <span class="font-semibold text-gray-600" id="currentRangeStart">1</span>&ndash;<span class="font-semibold text-gray-600" id="currentRangeEnd">20</span>
                <span class="text-gray-300 mx-1">of</span>
                <span class="font-semibold text-gray-700" id="totalItems">0</span> tickets
            </p>
            <div class="flex items-center gap-1">
                <button onclick="previousPage()" id="btnPrevPage" disabled
                    class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-medium text-gray-500 bg-white border border-gray-200 hover:bg-gray-50 hover:border-gray-300 disabled:opacity-30 disabled:cursor-not-allowed transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3 h-3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                    Prev
                </button>
                <button onclick="nextPage()" id="btnNextPage"
                    class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-medium text-gray-500 bg-white border border-gray-200 hover:bg-gray-50 hover:border-gray-300 disabled:opacity-30 disabled:cursor-not-allowed transition-all">
                    Next
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3 h-3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                    </svg>
                </button>
            </div>
        </div>
        <div class="overflow-auto" style="max-height: calc(100vh - 320px); min-height: 200px;">
            <table class="w-full text-sm border-collapse" style="min-width: 2200px;">
                <thead class="sticky top-0 z-10 bg-gray-50 border-b border-gray-200">
                    <tr>
                        {{-- LAST UPDATE: sortable --}}
                        <th class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap border-b border-gray-200 sticky left-0 bg-gray-50 z-20 th-sortable cursor-pointer transition-colors"
                            style="min-width:110px;" onclick="sortTickets('last_update')" title="Sort by Last Update">
                            <div class="flex items-center gap-1">
                                <span>Last Update</span>
                                <span id="sort-icon-last_update" class="sort-icon text-gray-300 font-normal normal-case tracking-normal">⇅</span>
                            </div>
                        </th>
                        {{-- TIKET: sortable + keyword filter --}}
                        <th class="p-0 text-left whitespace-nowrap border-b border-gray-200 sticky bg-gray-50 z-20" style="min-width:120px;left:110px;">
                            <button type="button" id="ticketFilterBtn" onclick="toggleTicketFilter(event)"
                                    class="w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                                <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Tiket</span>
                                <svg id="ticketFilterCaret" class="w-3.5 h-3.5 text-gray-500 transition-all duration-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                                <svg id="ticketFilterIcon" class="w-3.5 h-3.5 text-gray-300 transition-colors" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 011 1v1.586a1 1 0 01-.293.707l-4.121 4.121A1 1 0 0012 12.121V15.5l-4 1.5v-4.879a1 1 0 00-.293-.707L3.586 7.293A1 1 0 013.293 6.586L3 5z" clip-rule="evenodd"/></svg>
                                <span id="sort-icon-ticket_number" onclick="event.stopPropagation(); sortTickets('ticket_number')" title="Click to toggle sort (descending ↔ ascending)" class="sort-icon cursor-pointer text-gray-300 font-normal normal-case tracking-normal text-xs ml-auto hover:text-red-500 transition-colors">⇅</span>
                            </button>
                            <div id="ticketFilterPanel" class="hidden absolute mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] p-3" style="min-width:220px;">
                                <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Search ticket number</label>
                                <input type="text" id="ticketFilterInput" placeholder="e.g. TKT-2024-001…"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400"
                                       oninput="onTicketFilterInput()">
                                <p class="text-[10px] text-gray-400 mt-1.5">Matches tickets whose number contains this text. Use the ⇅ icon in the header to sort.</p>
                                <div class="flex justify-end gap-2 mt-3">
                                    <button type="button" onclick="clearTicketFilter()" class="px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50">Clear</button>
                                </div>
                            </div>
                        </th>
                        {{-- DESCRIPTION: keyword search filter --}}
                        <th class="p-0 text-left whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:260px;">
                            <button type="button" id="descFilterBtn" onclick="toggleDescFilter(event)"
                                    class="w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                                <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Description</span>
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
                                </div>
                            </div>
                        </th>
                        {{-- DATE: from-to range filter (also supports sort) --}}
                        <th class="p-0 text-left whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:120px;">
                            <button type="button" id="dateFilterBtn" onclick="toggleDateFilter(event)"
                                    class="w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                                <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Date</span>
                                <svg id="dateFilterCaret" class="w-3.5 h-3.5 text-gray-500 transition-all duration-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                                <svg id="dateFilterIcon" class="w-3.5 h-3.5 text-gray-300 transition-colors" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 011 1v1.586a1 1 0 01-.293.707l-4.121 4.121A1 1 0 0012 12.121V15.5l-4 1.5v-4.879a1 1 0 00-.293-.707L3.586 7.293A1 1 0 013.293 6.586L3 5z" clip-rule="evenodd"/></svg>
                                <span id="sort-icon-date" onclick="event.stopPropagation(); sortTickets('date')" title="Click to toggle sort (descending ↔ ascending)" class="sort-icon cursor-pointer text-gray-300 font-normal normal-case tracking-normal text-xs ml-auto hover:text-red-500 transition-colors">⇅</span>
                            </button>
                            <div id="dateFilterPanel" class="hidden absolute mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] p-3" style="min-width:240px;">
                                <div class="space-y-2">
                                    <div>
                                        <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">From</label>
                                        <input type="date" id="dateFilterFrom"
                                               class="w-full px-3 py-1.5 border border-gray-300 rounded-md text-sm font-normal text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">To</label>
                                        <input type="date" id="dateFilterTo"
                                               class="w-full px-3 py-1.5 border border-gray-300 rounded-md text-sm font-normal text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
                                    </div>
                                    <p id="dateFilterError" class="hidden text-xs text-red-500">"To" must be on/after "From".</p>
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
                                    <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Customer</span>
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
                                    <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Ticket Lead</span>
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
                                    <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Priority</span>
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
                                    <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Scale</span>
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
                                    <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Status</span>
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
                                    <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Type</span>
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
                        <th class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap border-b border-gray-200" style="min-width:130px;">Assign Delivery</th>
                        <th class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap border-b border-gray-200" style="min-width:140px;">Customer Mandays</th>
                        <th class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap border-b border-gray-200" style="min-width:160px;">Progress</th>
                        <th class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap border-b border-gray-200" style="min-width:170px;">Target Respon Time (Hour)</th>
                        <th class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap border-b border-gray-200" style="min-width:150px;">Respon Time (Hour)</th>
                        <th class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap border-b border-gray-200" style="min-width:150px;">Respon Time Status</th>
                        <th class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap border-b border-gray-200" style="min-width:170px;">Target Resolution Time</th>
                        <th class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap border-b border-gray-200" style="min-width:200px;">Due Date/Time Resolution Time</th>
                        <th class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap border-b border-gray-200" style="min-width:140px;">Resolution Time</th>
                        <th class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap border-b border-gray-200" style="min-width:160px;">Resolution Time Status</th>
                        @if($can('sla.report'))
                        <th class="px-3 py-2.5 text-center text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap border-b border-gray-200" style="min-width:110px;">SLA Report</th>
                        @endif
                    </tr>
                </thead>
                <tbody id="ticketsListBody" class="divide-y divide-gray-100 bg-white"></tbody>
            </table>
        </div>
    </div>
</div>

{{-- ── Loading State ───────────────────────────────────────────────────────── --}}
<div id="loadingState" class="flex flex-col items-center justify-center py-20 bg-white rounded-2xl border border-gray-200 shadow-sm">
    <div class="w-12 h-12 rounded-2xl primary-gradient flex items-center justify-center mb-4 shadow-md">
        <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
    </div>
    <p class="text-gray-600 text-sm font-semibold">Loading tickets...</p>
    <p class="text-gray-400 text-xs mt-1">Please wait a moment</p>
</div>

{{-- ── Empty State ─────────────────────────────────────────────────────────── --}}
<div id="emptyState" class="hidden flex flex-col items-center justify-center py-20 bg-white rounded-2xl border border-gray-200 shadow-sm text-center">
    <div class="w-16 h-16 rounded-2xl bg-gray-100 flex items-center justify-center mb-4">
        <i class="fas fa-search text-gray-300 text-2xl"></i>
    </div>
    <p class="text-gray-700 font-semibold mb-1">No tickets found</p>
    <p class="text-gray-400 text-xs mb-5">Try adjusting your filters or search terms</p>
    <button onclick="resetFilters()"
        class="inline-flex items-center gap-1.5 px-4 py-2 primary-gradient text-white text-xs font-semibold rounded-xl hover:opacity-90 transition-all shadow-sm">
        <i class="fas fa-times text-xs"></i>Clear Filters
    </button>
</div>

{{-- ── Access Denied State ────────────────────────────────────────────────── --}}
<div id="accessDeniedState" class="hidden flex flex-col items-center justify-center py-20 bg-white rounded-2xl border border-gray-200 shadow-sm text-center">
    <div class="w-16 h-16 rounded-2xl bg-amber-50 flex items-center justify-center mb-4">
        <i class="fas fa-lock text-amber-400 text-2xl"></i>
    </div>
    <p class="text-gray-700 font-semibold mb-1">You don't have access to Tickets</p>
    <p id="accessDeniedMessage" class="text-gray-400 text-xs max-w-sm">Your account role isn't permitted to view the Ticket module. Please sign in with an account that has ticket access (e.g. Admin, Helpdesk, or RPMO).</p>
</div>

<!-- Create Ticket Modal -->
@if($can('ui.ticket.btn-create'))
<div id="createTicketModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 overflow-y-auto">
    <div class="min-h-full flex items-center justify-center p-4">
        <div class="bg-white rounded-xl w-full max-w-2xl shadow-2xl">
            <div class="flex items-center justify-between px-6 py-5 border-b border-gray-200">
                <h3 class="text-xl font-bold text-gray-900">Create New Ticket</h3>
                <button onclick="closeCreateTicketModal()" class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-600 hover:bg-red-800 hover:text-white transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <form id="createTicketForm" onsubmit="submitCreateTicket(event)" class="p-6 space-y-4">
                {{-- Customer --}}
                <div>
                    <label class="text-xs font-semibold text-gray-600 mb-1.5 block uppercase tracking-wide">
                        Customer <span class="text-red-500">*</span>
                    </label>
                    <div class="custom-dd relative w-full" id="ddCreateCustomer" data-fixed="true" data-searchable="true" data-search-placeholder="Search customer...">
                        <button type="button" class="custom-dd-btn w-full flex items-center justify-between gap-2 px-4 py-2.5 border border-gray-300 rounded-lg text-sm hover:border-gray-400 transition-colors bg-white">
                            <span class="custom-dd-label text-gray-400">Select customer...</span>
                            <svg class="custom-dd-arrow w-4 h-4 text-gray-400 shrink-0 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <input type="hidden" id="newCustomerId">
                        <div class="custom-dd-panel hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] overflow-y-auto" style="max-height:240px;">
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-400 hover:bg-gray-50" data-value="">-- Select customer --</button>
                            @foreach($customers as $customer)
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50" data-value="{{ $customer['customer_id'] }}">{{ $customer['name'] }} ({{ $customer['customer_code'] }})</button>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- CC Emails --}}
                <div>
                    <label class="text-xs font-semibold text-gray-600 mb-1.5 block uppercase tracking-wide">
                        CC <span class="text-gray-400 font-normal normal-case">(optional, pisah koma)</span>
                    </label>
                    <input type="text" id="newCcEmails"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent"
                        placeholder="cc1@example.com, cc2@example.com">
                </div>

                {{-- Subject / Description --}}
                <div>
                    <label class="text-xs font-semibold text-gray-600 mb-1.5 block uppercase tracking-wide">
                        Subject / Description <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="newDescription" required maxlength="1000"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent"
                        placeholder="Describe the issue...">
                </div>

                {{-- Priority + Ticket Type (2 col) --}}
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-semibold text-gray-600 mb-1.5 block uppercase tracking-wide">Priority <span class="text-red-500">*</span></label>
                        <select id="newPriority" required
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
                            <option value="Very High">Very High</option>
                            <option value="High">High</option>
                            <option value="Medium" selected>Medium</option>
                            <option value="Low">Low</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-600 mb-1.5 block uppercase tracking-wide">
                            Ticket Type <span class="text-red-500">*</span>
                        </label>
                        <select id="newTicketType" required
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
                            <option value="">-- Select Type --</option>
                            <option value="Incident">Incident</option>
                            <option value="Service Request">Service Request</option>
                            <option value="Change Request">Change Request</option>
                            <option value="Consult">Consult</option>
                        </select>
                    </div>
                </div>

                {{-- Scale --}}
                <div>
                    <label class="text-xs font-semibold text-gray-600 mb-1.5 block uppercase tracking-wide">
                        Scale <span class="text-gray-400 font-normal normal-case">(optional, untuk SLA)</span>
                    </label>
                    <select id="newScale"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
                        <option value="">-- Select Scale --</option>
                        <option value="Simple" selected>Simple</option>
                        <option value="Medium">Medium</option>
                        <option value="Complex">Complex</option>
                    </select>
                </div>

                {{-- Additional Info --}}
                <div class="border-t border-gray-100 pt-3">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">Additional Info <span class="font-normal normal-case">(optional)</span></p>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-semibold text-gray-600 mb-1.5 block uppercase tracking-wide">Name</label>
                            <input type="text" id="newName" maxlength="255"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent"
                                placeholder="Contact person name">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 mb-1.5 block uppercase tracking-wide">No HP</label>
                            <input type="text" id="newNoHp" maxlength="255"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent"
                                placeholder="Phone number">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 mb-1.5 block uppercase tracking-wide">Module</label>
                            <input type="text" id="newModule" maxlength="255"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent"
                                placeholder="Related module">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 mb-1.5 block uppercase tracking-wide">Client</label>
                            <input type="text" id="newClient" maxlength="255"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent"
                                placeholder="Client name">
                        </div>
                    </div>
                </div>

                {{-- Body / Message (Quill rich-text) --}}
                <div>
                    <label class="text-xs font-semibold text-gray-600 mb-1.5 block uppercase tracking-wide">
                        Message <span class="text-gray-400 font-normal normal-case">(optional — supports paste image)</span>
                    </label>
                    <div id="adminBodyEditor" class="border border-gray-300 rounded-lg overflow-hidden" style="min-height:140px; background:#fff;"></div>
                </div>

                {{-- Attachments --}}
                <div>
                    <label class="text-xs font-semibold text-gray-600 mb-1.5 block uppercase tracking-wide">
                        Attachments <span class="text-gray-400 font-normal normal-case">(optional, max 20 MB/file)</span>
                    </label>
                    <input type="file" id="newAttachments" multiple
                        class="w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200">
                </div>

                <div id="adminCreateError" class="hidden text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-4 py-3"></div>

                <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                    <button type="button" onclick="closeCreateTicketModal()"
                        class="inline-flex items-center px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all duration-200">Cancel</button>
                    <button type="submit" id="btnCreateTicket"
                        class="inline-flex items-center gap-2 px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                        <i class="fas fa-plus text-xs"></i>Create Ticket
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

<style>
/* ── Sort column headers ── */
thead th.th-sortable { user-select: none; }
thead th.th-sortable:hover { background: #f1f5f9; }
.sort-icon { font-style: normal; transition: color 0.15s; }
.sort-icon.active { color: #111827; }

/* ── Column filter dropdown active state ── */
.custom-dd.col-dd-active .custom-dd-arrow { color: #dc2626; }
.custom-dd.col-dd-active .custom-dd-btn > span { color: #dc2626; font-weight: 700; }

/* View Toggle */
#btnViewAll, #btnViewMy,
#btnViewAllHd, #btnViewUnassigned { background: transparent; color: #9ca3af; font-size: 12px; }

#btnViewAll.active, #btnViewMy.active,
#btnViewAllHd.active, #btnViewUnassigned.active { background: white; color: #111827; font-weight: 700; box-shadow: 0 1px 4px rgba(0,0,0,0.12); }

/* Stat cards active state */
.stat-card.active-filter {
    border-left: 3px solid #dc2626 !important;
    border-top-color: #fecaca !important;
    border-right-color: #fecaca !important;
    border-bottom-color: #fecaca !important;
    background: #fff8f8 !important;
    box-shadow: 0 2px 8px rgba(220,38,38,0.08) !important;
}

/* Table rows */
#ticketsListBody tr { cursor: pointer; transition: background 0.1s; }
#ticketsListBody tr:hover { background: #f8fafc; }

/* ── Unread ticket row — blue (customer email) ── */
#ticketsListBody tr.ticket-unread-customer { background: #f0f7ff; }
#ticketsListBody tr.ticket-unread-customer:hover { background: #e6f0fd; }
#ticketsListBody tr.ticket-unread-customer td:first-child {
    border-left: 3px solid #93c5fd;
    padding-left: 10px;
}
#ticketsListBody tr.ticket-unread-customer td:first-child,
#ticketsListBody tr.ticket-unread-customer td:nth-child(2) { background: #f0f7ff; }
#ticketsListBody tr.ticket-unread-customer:hover td:first-child,
#ticketsListBody tr.ticket-unread-customer:hover td:nth-child(2) { background: #e6f0fd; }

/* ── Unread ticket row — yellow (internal note) ── */
#ticketsListBody tr.ticket-unread-internal { background: #fffbeb; }
#ticketsListBody tr.ticket-unread-internal:hover { background: #fef3c7; }
#ticketsListBody tr.ticket-unread-internal td:first-child {
    border-left: 3px solid #fbbf24;
    padding-left: 10px;
}
#ticketsListBody tr.ticket-unread-internal td:first-child,
#ticketsListBody tr.ticket-unread-internal td:nth-child(2) { background: #fffbeb; }
#ticketsListBody tr.ticket-unread-internal:hover td:first-child,
#ticketsListBody tr.ticket-unread-internal:hover td:nth-child(2) { background: #fef3c7; }

/* ── Unread dots ── */
.unread-dot {
    display: inline-block;
    width: 7px; height: 7px;
    border-radius: 50%;
    vertical-align: middle;
    margin-right: 5px;
    flex-shrink: 0;
}
.unread-dot-blue { background: #3b82f6; box-shadow: 0 0 0 2px #dbeafe; }
.unread-dot-yellow { background: #f59e0b; box-shadow: 0 0 0 2px #fde68a; }

/* Sticky columns */
#ticketsListBody tr td:first-child,
#ticketsListBody tr td:nth-child(2) {
    z-index: 5;
    box-shadow: 2px 0 4px rgba(0,0,0,0.04);
}
#ticketsListBody tr:hover td:first-child,
#ticketsListBody tr:hover td:nth-child(2) { background: #f8fafc; }
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
    let userRoleIds                   = {!! json_encode($user->role_ids) !!};
    let userRole                      = userRoleIds[0] ?? 0;
    let currentEmployeeId             = {{ $currentEmployeeId ?? 'null' }};
    const CAN_VIEW_SLA_REPORT         = {{ $can('sla.report') ? 'true' : 'false' }};
    const IS_EXTERNAL_EMPLOYEE        = {{ ($isExternalEmployee ?? false) ? 'true' : 'false' }};
    const EC_ADMINISTRATOR_ROLE       = {{ \App\Enums\RoleId::EC_ADMINISTRATOR->value }};
    const DELIVERY_SUPPORT_USER_ROLE  = {{ \App\Enums\RoleId::DELIVERY_SUPPORT_USER->value }};
    const EC_USER_ROLE                = {{ \App\Enums\RoleId::EC_USER->value }};
    const HELPDESK_ROLE               = {{ \App\Enums\RoleId::DELIVERY_HELPDESK->value }};
    const SUPPORT_MANAGER_ROLE        = {{ \App\Enums\RoleId::DELIVERY_SUPPORT_MANAGER->value }};
    const HEAD_ROLES                  = [{{ \App\Enums\RoleId::DELIVERY_SUPPORT_HEAD->value }}, {{ \App\Enums\RoleId::DELIVERY_PROJECT_HEAD->value }}];
    // Roles that use the All/Unassigned toggle (Helpdesk only)
    const STAFF_TOGGLE_ROLES          = [HELPDESK_ROLE];
    let currentView = (userRole === DELIVERY_SUPPORT_USER_ROLE || (IS_EXTERNAL_EMPLOYEE && userRole !== SUPPORT_MANAGER_ROLE && !HEAD_ROLES.includes(userRole))) ? 'my' : 'all';
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

    // Refresh list saat halaman dipulihkan dari bfcache (tombol Back browser)
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) loadTickets();
    });

    // -------------------------------------------------------------------------
    // Ticket polling: cek update tiket setiap 10 detik dari DB lokal (bukan Graph API)
    // Email inbox diproses server-side oleh scheduler (email:process-inbox tiap menit)
    // -------------------------------------------------------------------------
    let _lastTicketUpdate = null;
    let _isFirstPoll      = true;

    async function checkTicketUpdates() {
        try {
            const res = await fetch('/ticket/latest-update', {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            });
            if (!res.ok) return;
            const data = await res.json();
            const latest = data.latest_update ?? null;

            if (_isFirstPoll) {
                // Simpan baseline — jangan reload (loadTickets sudah dipanggil saat DOMContentLoaded)
                _lastTicketUpdate = latest;
                _isFirstPoll = false;
                return;
            }

            if (latest !== _lastTicketUpdate) {
                _lastTicketUpdate = latest;
                loadTickets();
            }
        } catch (err) {
            console.warn('[Ticket Polling] error:', err.message);
        }
    }

    function startEmailPolling() {
        checkTicketUpdates();
        setInterval(checkTicketUpdates, 10000);
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
        if (userRole === EC_ADMINISTRATOR_ROLE || userRole === DELIVERY_SUPPORT_USER_ROLE || userRole === SUPPORT_MANAGER_ROLE) {
            const btnAll = document.getElementById('btnViewAll');
            const btnMy  = document.getElementById('btnViewMy');
            if (btnAll && btnMy) {
                btnAll.classList.toggle('active', currentView === 'all');
                btnMy.classList.toggle('active',  currentView === 'my');
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
    }

    async function loadTickets() {
        try {
            document.getElementById('loadingState').classList.remove('hidden');
            document.getElementById('ticketsContainer').classList.add('hidden');
            document.getElementById('emptyState').classList.add('hidden');

            let endpoint = '/api/tickets';
            if (userRole === EC_USER_ROLE) endpoint = '/api/tickets/my';
            else if (IS_EXTERNAL_EMPLOYEE && userRole !== SUPPORT_MANAGER_ROLE && !HEAD_ROLES.includes(userRole)) endpoint = '/api/tickets/my';
            else if ((userRole === EC_ADMINISTRATOR_ROLE || userRole === DELIVERY_SUPPORT_USER_ROLE) && currentView === 'my') endpoint = '/api/tickets/my';
            else if (userRole === SUPPORT_MANAGER_ROLE && currentView === 'my') endpoint = '/api/tickets/my';

            const response = await fetch(endpoint, {
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            });

            // Akun tanpa hak akses ke modul ticket (mis. role Project Admin) → tampilkan
            // state khusus, bukan "Failed to load tickets" yang membingungkan.
            if (response.status === 403 || response.status === 401) {
                let msg = '';
                try { msg = (await response.json()).message || ''; } catch (e) {}
                showAccessDenied(response.status === 401
                    ? 'Your session has expired. Please sign in again to view tickets.'
                    : (msg && msg !== 'Access denied' ? msg
                        : "Your account role isn't permitted to view the Ticket module. Please sign in with an account that has ticket access (e.g. Admin, Helpdesk, or RPMO)."));
                return;
            }

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
                showNotification(data.message || 'Failed to load tickets', 'error');
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

    // Tampilkan state "tidak punya akses" dan sembunyikan loading/list/empty.
    function showAccessDenied(message) {
        document.getElementById('loadingState').classList.add('hidden');
        document.getElementById('ticketsContainer').classList.add('hidden');
        document.getElementById('emptyState').classList.add('hidden');
        const msgEl = document.getElementById('accessDeniedMessage');
        if (msgEl && message) msgEl.textContent = message;
        document.getElementById('accessDeniedState').classList.remove('hidden');
        showNotification(message || "You don't have access to Tickets", 'error');
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
        const createdAt    = new Date(ticket.start_date || ticket.created_at);
        const endDate      = ticket.end_date ? new Date(ticket.end_date) : null;

        const fmt    = d => d.toLocaleDateString('en-GB', { timeZone: 'Asia/Jakarta', day: '2-digit', month: 'short', year: 'numeric' });
        const fmtDT  = d => d.toLocaleString('en-GB',    { timeZone: 'Asia/Jakarta', day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false });

        const lastUpdateStr = relativeTime(lastActivity);
        const lastUpdateTitle = fmtDT(lastActivity);
        const dateStr       = fmt(createdAt);
        const endDateStr    = endDate ? fmt(endDate) : '—';

        const agentName = ticket.employee?.employee_name || '<span class="text-gray-400">Unassigned</span>';

        const priorityColors = {
            'Very High': 'bg-red-50 text-red-700',
            'High':      'bg-orange-50 text-orange-700',
            'Medium':    'bg-yellow-50 text-yellow-700',
            'Low':       'bg-blue-50 text-blue-700'
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

        // ── Priority dots ──
        const prioDots = { 'Very High':'bg-red-500', 'High':'bg-orange-500', 'Medium':'bg-yellow-500', 'Low':'bg-blue-400' };

        // ── Status dots ──
        const statusDots = {
            'open':'bg-blue-500','inprocess':'bg-yellow-500','waiting_on_customer':'bg-amber-500',
            'waiting_on_3rd_party':'bg-indigo-500','waiting_to_confirmation':'bg-teal-500',
            'hold':'bg-orange-500','cancelled':'bg-gray-400','closed':'bg-green-500',
        };

        // ── Unread detection ──
        const lastCustomer   = ticket.last_customer_reply_at  ? new Date(ticket.last_customer_reply_at)  : null;
        const lastInternal   = ticket.last_internal_note_at   ? new Date(ticket.last_internal_note_at)   : null;
        const lastAgent      = ticket.last_agent_reply_at     ? new Date(ticket.last_agent_reply_at)     : null;
        const lastNoteSender = ticket.last_internal_note_sender_id;

        const hasUnreadCustomer = lastCustomer && (!lastAgent || lastCustomer > lastAgent);
        const hasUnreadInternal = lastInternal && (Number(lastNoteSender) !== currentEmployeeId);

        let unreadCls = '', dot = '', timeColor = 'text-gray-400', numColor = 'text-gray-700';
        if (hasUnreadInternal) {
            unreadCls = 'ticket-unread-internal';
            dot       = '<span class="unread-dot unread-dot-yellow" title="Ada internal note belum dibalas"></span>';
            timeColor = 'text-amber-600 font-semibold';
            numColor  = 'text-amber-700';
        } else if (hasUnreadCustomer) {
            unreadCls = 'ticket-unread-customer';
            dot       = '<span class="unread-dot unread-dot-blue" title="Customer belum dibalas"></span>';
            timeColor = 'text-blue-600 font-semibold';
            numColor  = 'text-blue-700';
        }

        // ── Helpers ──
        const badge = (label, cls, dot = '') =>
            `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold ${cls}">
                ${dot ? `<span class="w-1.5 h-1.5 rounded-full ${dot} flex-shrink-0"></span>` : ''}${label}
             </span>`;

        const cell = (content, extraCls = '') =>
            `<td class="px-3 py-3 text-sm text-gray-700 whitespace-nowrap ${extraCls}">${content}</td>`;

        const dash = () =>
            `<td class="px-3 py-3 text-gray-300 whitespace-nowrap text-xs text-center">—</td>`;

        // ── Row bgColor (must match for sticky cells) ──
        const rowBg = unreadCls === 'ticket-unread-internal' ? '#fffbeb'
                    : unreadCls === 'ticket-unread-customer'  ? '#f0f7ff'
                    : '#ffffff';

        return `<tr class="${unreadCls} border-b border-gray-100" onclick="window.location='/ticket/${ticket.ticket_id}'">
            {{-- Last Update --}}
            <td class="px-3 py-3 whitespace-nowrap sticky left-0" style="background:${rowBg}" title="${lastUpdateTitle}">
                <div class="flex items-center gap-1.5">
                    ${dot}
                    <span class="text-xs ${timeColor}">${lastUpdateStr}</span>
                </div>
            </td>
            {{-- Ticket # --}}
            <td class="px-3 py-3 whitespace-nowrap sticky border-r border-gray-100" style="left:110px;background:${rowBg}">
                <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-gray-100 font-mono text-xs font-bold ${numColor}">${ticket.ticket_number || '—'}</span>
            </td>
            {{-- Description --}}
            <td class="px-3 py-3 text-sm" style="min-width:260px;max-width:320px;">
                <span class="block truncate text-gray-700 font-medium leading-snug"
                      title="${(ticket.description||'').replace(/"/g,'&quot;')}">${ticket.description || '—'}</span>
            </td>
            {{-- Date --}}
            <td class="px-3 py-3 whitespace-nowrap">
                <span class="text-xs text-gray-500">${dateStr}</span>
            </td>
            {{-- Customer --}}
            <td class="px-3 py-3 whitespace-nowrap">
                <span class="text-sm font-semibold text-gray-800">${customerName}</span>
                ${ticket.end_customer_name ? `<span class="block text-[11px] text-gray-400 mt-0.5">↳ ${ticket.end_customer_name}</span>` : ''}
            </td>
            {{-- Ticket Lead --}}
            <td class="px-3 py-3 whitespace-nowrap">
                ${ticket.employee?.employee_name
                    ? `<div class="flex items-center gap-2">
                         <div class="w-6 h-6 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
                             <span class="text-[9px] font-bold text-red-700">${ticket.employee.employee_name.charAt(0).toUpperCase()}</span>
                         </div>
                         <span class="text-sm text-gray-700">${ticket.employee.employee_name}</span>
                       </div>`
                    : `<span class="text-xs text-gray-300 italic">Unassigned</span>`
                }
            </td>
            {{-- Priority --}}
            <td class="px-3 py-3 whitespace-nowrap">
                ${ticket.ticket_priority
                    ? badge(priorityLabel, priorityClass, prioDots[ticket.ticket_priority] || 'bg-gray-400')
                    : `<span class="text-gray-300 text-xs">—</span>`}
            </td>
            {{-- Scale --}}
            <td class="px-3 py-3 whitespace-nowrap">
                ${ticket.scale
                    ? badge(ticket.scale, scaleColors[ticket.scale] || 'bg-gray-100 text-gray-500')
                    : `<span class="text-gray-300 text-xs">—</span>`}
            </td>
            {{-- Status --}}
            <td class="px-3 py-3 whitespace-nowrap">
                ${badge(sInfo.label, sInfo.cls, statusDots[ticket.status] || 'bg-gray-400')}
            </td>
            {{-- Type --}}
            ${cell(ticket.ticket_type ? badge(typeLabel, typeCls) : '<span class="text-gray-300 text-xs">—</span>')}
            {{-- Assign Delivery --}}
            ${dash()}
            {{-- Customer Mandays --}}
            ${cell(mandays !== '—' ? `<span class="font-semibold text-gray-700">${mandays}</span>` : '<span class="text-gray-300 text-xs">—</span>')}
            {{-- Progress --}}
            <td class="px-3 py-3 whitespace-nowrap">
                ${(function() {
                    const pct = parseFloat(ticket.all_consultant_progress) || 0;
                    if (pct === 0 && !ticket.man_days) return '<span class="text-gray-300 text-xs">—</span>';
                    const barCls = pct >= 75 ? 'bg-green-500' : pct >= 40 ? 'bg-yellow-400' : 'bg-red-400';
                    const txtCls = pct >= 75 ? 'text-green-700' : pct >= 40 ? 'text-yellow-700' : 'text-red-600';
                    return `<div class="flex items-center gap-2">
                        <div class="bg-gray-100 rounded-full h-2 flex-1" style="min-width:60px;max-width:80px">
                            <div class="${barCls} h-2 rounded-full transition-all" style="width:${pct}%"></div>
                        </div>
                        <span class="text-xs font-bold ${txtCls} min-w-[28px]">${pct}%</span>
                    </div>`;
                })()}
            </td>
            ${(function() {
                const sla = ticket.sla;
                if (!sla) {
                    return `${dash()}${dash()}${dash()}${dash()}${dash()}${dash()}${dash()}`;
                }
                const slaStatusBadge = (status) => {
                    const map = {
                        'met':                 { label: 'Met',      cls: 'bg-green-50 text-green-700',  dot: 'bg-green-500' },
                        'breached':            { label: 'Breached', cls: 'bg-red-50 text-red-700',    dot: 'bg-red-500' },
                        'pending':             { label: 'Pending',  cls: 'bg-gray-100 text-gray-500',  dot: 'bg-gray-400' },
                        'pending_validation':  { label: 'Validating', cls: 'bg-blue-50 text-blue-600', dot: 'bg-blue-400' },
                        'paused':              { label: 'Paused',   cls: 'bg-amber-50 text-amber-700', dot: 'bg-amber-400' },
                    };
                    const s = map[status] || { label: status || '—', cls: 'bg-gray-100 text-gray-500', dot: 'bg-gray-400' };
                    return badge(s.label, s.cls, s.dot);
                };
                const fmtDue = d => d ? fmtDT(new Date(d)) : '—';

                return `
                    <td class="px-3 py-3 whitespace-nowrap text-xs text-gray-600">${sla.target_response_hours != null ? parseFloat(sla.target_response_hours).toFixed(2) + ' h' : '<span class="text-gray-300">—</span>'}</td>
                    <td class="px-3 py-3 whitespace-nowrap text-xs text-gray-600">${sla.response_time_hours != null ? parseFloat(sla.response_time_hours).toFixed(2) + ' h' : '<span class="text-gray-300">—</span>'}</td>
                    <td class="px-3 py-3 whitespace-nowrap">${slaStatusBadge(sla.response_status)}</td>
                    <td class="px-3 py-3 whitespace-nowrap text-xs text-gray-600">${sla.target_resolution_hours != null ? parseFloat(sla.target_resolution_hours).toFixed(2) + ' h' : '<span class="text-gray-300">—</span>'}</td>
                    <td class="px-3 py-3 whitespace-nowrap text-xs text-gray-500">${fmtDue(sla.resolution_due_at)}</td>
                    <td class="px-3 py-3 whitespace-nowrap text-xs text-gray-600">${sla.resolution_time_hours != null ? parseFloat(sla.resolution_time_hours).toFixed(2) + ' h' : '<span class="text-gray-300">—</span>'}</td>
                    <td class="px-3 py-3 whitespace-nowrap">${slaStatusBadge(sla.resolution_status)}</td>
                `;
            })()}
            ${CAN_VIEW_SLA_REPORT ? `
            <td class="px-3 py-3 whitespace-nowrap" onclick="event.stopPropagation()">
                ${ticket.sla
                    ? `<div class="flex items-center gap-1">
                           <button onclick="openSlaDetail(${ticket.ticket_id}, '${(ticket.ticket_number||'').replace(/'/g,"\\'")}'); event.stopPropagation();"
                               class="inline-flex items-center gap-1 px-2 py-1 rounded-lg border border-gray-200 hover:border-indigo-300 hover:bg-indigo-50 text-gray-400 hover:text-indigo-600 transition text-[10px] font-medium">
                               <i class="fas fa-history text-xs"></i><span>Log</span>
                           </button>
                           <a href="/admin/sla/tickets/${ticket.ticket_id}/log-pdf" target="_blank" onclick="event.stopPropagation();"
                               class="inline-flex items-center gap-1 px-2 py-1 rounded-lg border border-gray-200 hover:border-red-300 hover:bg-red-50 text-gray-400 hover:text-red-600 transition text-[10px] font-medium">
                               <i class="fas fa-download text-xs"></i><span>Log PDF</span>
                           </a>
                           <a href="/admin/sla/tickets/${ticket.ticket_id}/pdf" target="_blank" onclick="event.stopPropagation();"
                               class="inline-flex items-center gap-1 px-2 py-1 rounded-lg border border-gray-200 hover:border-orange-300 hover:bg-orange-50 text-gray-400 hover:text-orange-600 transition text-[10px] font-medium">
                               <i class="fas fa-download text-xs"></i><span>Summary PDF</span>
                           </a>
                       </div>`
                    : `<span class="text-gray-300 text-xs">—</span>`
                }
            </td>` : ''}
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
            el.classList.remove('active-filter', 'border-red-600', 'shadow-md', 'border-2');
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
                el.classList.add('active-filter', 'border-red-600', 'shadow-md', 'border-2');
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

        // Date range filter (from-to inclusive, based on ticket.start_date ?? ticket.created_at in Asia/Jakarta)
        const dateFrom = document.getElementById('dateFilterFrom')?.value || '';
        const dateTo   = document.getElementById('dateFilterTo')?.value   || '';
        const fromMs = dateFrom ? new Date(dateFrom + 'T00:00:00+07:00').getTime() : null;
        const toMs   = dateTo   ? new Date(dateTo   + 'T23:59:59+07:00').getTime() : null;

        // Description keyword (case-insensitive substring)
        const descKw   = (document.getElementById('descFilterInput')?.value  || '').trim().toLowerCase();
        const ticketKw = (document.getElementById('ticketFilterInput')?.value || '').trim().toLowerCase();

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
                const created = (ticket.start_date || ticket.created_at) ? new Date(ticket.start_date || ticket.created_at).getTime() : NaN;
                if (Number.isNaN(created)) {
                    matchDate = false;
                } else {
                    if (fromMs !== null && created < fromMs) matchDate = false;
                    if (toMs   !== null && created > toMs)   matchDate = false;
                }
            }

            const matchDesc   = !descKw   || (ticket.description   || '').toLowerCase().includes(descKw);
            const matchTicket = !ticketKw || (ticket.ticket_number || '').toLowerCase().includes(ticketKw);

            return matchesCard
                && matchColCustomer && matchColPic && matchColPriority && matchColScale
                && matchColStatus && matchColType
                && matchDate && matchDesc && matchTicket;
        });
        updateColFilterIndicators();
        updateDateFilterIndicator();
        updateDescFilterIndicator();
        updateTicketFilterIndicator();
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
        closeTicketFilter();
        if (open) { panel.classList.add('hidden'); return; }
        positionPanelUnder(btn, panel);
        panel.classList.remove('hidden');
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
        document.getElementById('dateFilterPanel')?.classList.add('hidden');
        document.getElementById('ticketFilterPanel')?.classList.add('hidden');
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

    // ── Ticket Number Keyword Filter (debounced) ──────────────────────────
    let _ticketFilterTimer = null;
    function toggleTicketFilter(ev) {
        ev?.stopPropagation();
        const panel = document.getElementById('ticketFilterPanel');
        const btn   = document.getElementById('ticketFilterBtn');
        const open  = !panel.classList.contains('hidden');
        document.getElementById('dateFilterPanel')?.classList.add('hidden');
        document.getElementById('descFilterPanel')?.classList.add('hidden');
        if (open) { panel.classList.add('hidden'); return; }
        // Move to body to escape the sticky-th stacking context so clicks work
        if (panel.parentElement !== document.body) document.body.appendChild(panel);
        positionPanelUnder(btn, panel);
        panel.classList.remove('hidden');
        document.getElementById('ticketFilterInput')?.focus();
    }

    function closeTicketFilter() {
        document.getElementById('ticketFilterPanel')?.classList.add('hidden');
    }

    function onTicketFilterInput() {
        clearTimeout(_ticketFilterTimer);
        _ticketFilterTimer = setTimeout(applyAdvancedFilters, 250);
    }

    function clearTicketFilter() {
        const input = document.getElementById('ticketFilterInput');
        if (input) input.value = '';
        applyAdvancedFilters();
    }

    function updateTicketFilterIndicator() {
        const kw   = (document.getElementById('ticketFilterInput')?.value || '').trim();
        const icon = document.getElementById('ticketFilterIcon');
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
        const tp = document.getElementById('ticketFilterPanel');
        const tb = document.getElementById('ticketFilterBtn');
        if (tp && !tp.classList.contains('hidden') && !tp.contains(e.target) && !tb.contains(e.target)) tp.classList.add('hidden');
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            document.getElementById('dateFilterPanel')?.classList.add('hidden');
            document.getElementById('descFilterPanel')?.classList.add('hidden');
            document.getElementById('ticketFilterPanel')?.classList.add('hidden');
        }
    });

    // Close popovers on any scroll outside the panel (table scroll, page scroll,
    // window resize). These panels are position:fixed, so scrolling would leave
    // them floating detached from their header — close them instead of letting
    // them collide with the view.
    function _closeTicketHeaderPanels() {
        document.getElementById('dateFilterPanel')?.classList.add('hidden');
        document.getElementById('descFilterPanel')?.classList.add('hidden');
        document.getElementById('ticketFilterPanel')?.classList.add('hidden');
    }
    window.addEventListener('scroll', (e) => {
        // Ignore scrolling that happens inside one of the open panels themselves.
        const t = e.target;
        if (t && t.nodeType === 1 && t.closest && (
            t.closest('#dateFilterPanel') || t.closest('#descFilterPanel') || t.closest('#ticketFilterPanel'))) return;
        _closeTicketHeaderPanels();
    }, true);
    window.addEventListener('resize', _closeTicketHeaderPanels);

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
        const desc   = document.getElementById('descFilterInput');   if (desc)   desc.value   = '';
        const ticketF = document.getElementById('ticketFilterInput'); if (ticketF) ticketF.value = '';

        currentTicketSort = { key: 'last_update', dir: 'desc' };
        updateTicketSortIcons();

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
                const extractNum = t => parseInt((t.ticket_number || '').replace(/\D+/g, '') || t.ticket_id || 0, 10);
                va = extractNum(a);
                vb = extractNum(b);
            } else if (key === 'date') {
                va = new Date(a.start_date || a.created_at).getTime();
                vb = new Date(b.start_date || b.created_at).getTime();
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
                el.textContent = currentTicketSort.dir === 'asc' ? '↑' : '↓';
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


    // ==================== HELPDESK: CREATE TICKET ====================
    // ==================== CREATE TICKET ====================
    let adminQuillEditor = null;
    function initAdminQuill() {
        if (adminQuillEditor) return;
        adminQuillEditor = new Quill('#adminBodyEditor', {
            theme: 'snow',
            placeholder: 'Write an initial message... (optional)',
            modules: { toolbar: [
                [{ header: [1, 2, false] }],
                ['bold', 'italic', 'underline'],
                [{ list: 'ordered' }, { list: 'bullet' }],
                ['link', 'image'],
                ['clean'],
            ]},
        });
        adminQuillEditor.root.addEventListener('paste', function (e) {
            const items = e.clipboardData?.items || [];
            for (const item of items) {
                if (item.type.startsWith('image/')) {
                    e.preventDefault();
                    const reader = new FileReader();
                    reader.onload = evt => {
                        const range = adminQuillEditor.getSelection(true);
                        adminQuillEditor.insertEmbed(range.index, 'image', evt.target.result);
                        adminQuillEditor.setSelection(range.index + 1);
                    };
                    reader.readAsDataURL(item.getAsFile());
                }
            }
        });
    }
    function openCreateTicketModal() {
        document.getElementById('createTicketModal').classList.remove('hidden');
        if (typeof initCustomDropdowns === 'function') initCustomDropdowns(document.getElementById('createTicketModal'));
        setTimeout(initAdminQuill, 50);
    }
    function closeCreateTicketModal() {
        document.getElementById('createTicketModal').classList.add('hidden');
        document.getElementById('createTicketForm').reset();
        if (typeof setCustomDropdownValue === 'function') setCustomDropdownValue('newCustomerId', '');
        const ccEl = document.getElementById('newCcEmails');
        if (ccEl) ccEl.value = '';
        document.getElementById('adminCreateError').classList.add('hidden');
        const btn = document.getElementById('btnCreateTicket');
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-plus text-xs"></i>Create Ticket'; }
        if (adminQuillEditor) { adminQuillEditor.setContents([]); }
        const att = document.getElementById('newAttachments');
        if (att) att.value = '';
    }

    async function submitCreateTicket(e) {
        e.preventDefault();
        const btn    = document.getElementById('btnCreateTicket');
        const errEl  = document.getElementById('adminCreateError');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin text-xs"></i> Creating…';
        errEl.classList.add('hidden');

        const customerIdVal = document.getElementById('newCustomerId').value;
        if (!customerIdVal) {
            errEl.textContent = 'Customer is required.';
            errEl.classList.remove('hidden');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-plus text-xs"></i>Create Ticket';
            return;
        }

        const ticketTypeVal = document.getElementById('newTicketType').value;
        if (!ticketTypeVal) {
            errEl.textContent = 'Ticket Type is required.';
            errEl.classList.remove('hidden');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-plus text-xs"></i>Create Ticket';
            return;
        }

        const bodyHtml = adminQuillEditor ? adminQuillEditor.root.innerHTML : '';
        const form = new FormData();
        form.append('description',     document.getElementById('newDescription').value);
        form.append('ticket_priority', document.getElementById('newPriority').value);
        form.append('customer_id',     document.getElementById('newCustomerId').value);
        form.append('ticket_type',     ticketTypeVal);
        form.append('cc_emails',       document.getElementById('newCcEmails').value || '');
        const scaleVal = document.getElementById('newScale').value;
        if (scaleVal) form.append('scale', scaleVal);
        form.append('name',            document.getElementById('newName').value || '');
        form.append('no_hp',           document.getElementById('newNoHp').value || '');
        form.append('module',          document.getElementById('newModule').value || '');
        form.append('client',          document.getElementById('newClient').value || '');
        form.append('body',            bodyHtml || '');
        const files = document.getElementById('newAttachments').files;
        for (let i = 0; i < files.length; i++) form.append('attachments[]', files[i]);
        const isAdmin = userRole === EC_ADMINISTRATOR_ROLE;
        const endpoint = isAdmin ? '/api/tickets' : '/api/tickets/helpdesk-create';
        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '' },
                credentials: 'same-origin',
                body: form
            });
            const result = await response.json();
            if (result.success) {
                const msg = isAdmin
                    ? 'Ticket created successfully!'
                    : `Ticket ${result.ticket_number} created${result.email_sent ? ' & email sent!' : ''}`;
                showNotification(msg, result.email_sent === false ? 'warning' : 'success');
                closeCreateTicketModal();
                loadTickets();
            } else {
                errEl.textContent = result.message || 'Failed to create ticket.';
                errEl.classList.remove('hidden');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-plus text-xs"></i>Create Ticket';
            }
        } catch (error) {
            errEl.textContent = 'Network error: ' + error.message;
            errEl.classList.remove('hidden');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-plus text-xs"></i>Create Ticket';
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
        if (e.key === 'Escape') {
            if (document.getElementById('slaDetailModal') && !document.getElementById('slaDetailModal').classList.contains('hidden')) {
                closeSlaDetail();
                return;
            }
            if (document.getElementById('createTicketModal') && !document.getElementById('createTicketModal').classList.contains('hidden')) {
                closeCreateTicketModal();
            }
        }
    });

    // ── SLA Log Modal ─────────────────────────────────────────────────────────
    const STATUS_CFG_SLA = {
        'pending_validation': { bg:'bg-gray-100',  text:'text-gray-500',  dot:'bg-gray-400',  label:'Pending'  },
        'pending':            { bg:'bg-blue-50',   text:'text-blue-700',  dot:'bg-blue-500',  label:'Active'   },
        'paused':             { bg:'bg-amber-50',  text:'text-amber-700', dot:'bg-amber-500', label:'Paused'   },
        'met':                { bg:'bg-green-50',  text:'text-green-700', dot:'bg-green-500', label:'Met'      },
        'breached':           { bg:'bg-red-50',    text:'text-red-700',   dot:'bg-red-500',   label:'Breached' },
    };
    const EVENT_ROW_CFG_SLA = {
        email_received:       { dot: '#6366f1', rowBg: '#fafaff', label: 'Email / Request Received'  },
        ticket_validated:     { dot: '#16a34a', rowBg: '#f6fef7', label: 'Ticket Created'            },
        agent_replied:        { dot: '#2563eb', rowBg: '#f5f9ff', label: 'Helpdesk Reply'            },
        customer_replied:     { dot: '#ea580c', rowBg: '#fff8f4', label: 'Customer Reply'            },
        resolution_sent:      { dot: '#0d9488', rowBg: '#f4fefc', label: 'Resolution Sent'           },
        escalated_to_sap:     { dot: '#7c3aed', rowBg: '#faf7ff', label: 'Escalated to SAP'         },
        escalated_to_support: { dot: '#6b7280', rowBg: '#f9fafb', label: 'Returned to Helpdesk'     },
        sla_warning:          { dot: '#ca8a04', rowBg: '#fffdf0', label: 'SLA Warning'               },
        sla_breached:         { dot: '#dc2626', rowBg: '#fff8f8', label: 'SLA Breached'              },
        ticket_closed:        { dot: '#374151', rowBg: '#f9fafb', label: 'Ticket Closed'             },
        meeting_started:      { dot: '#7c3aed', rowBg: '#faf7ff', label: 'Meeting Started'           },
        meeting_ended:        { dot: '#7c3aed', rowBg: '#faf7ff', label: 'Meeting Ended'             },
    };
    const BALL_ICON_SLA = {
        helpdesk: { icon: '▶', label: 'Helpdesk' },
        customer: { icon: '⏸', label: 'Customer' },
        sap:      { icon: '⏸', label: 'SAP'      },
        meeting:  { icon: '⏸', label: 'Meeting'  },
    };

    function _slaToHMM(hours) {
        if (hours === null || hours === undefined) return null;
        const h = Math.floor(hours);
        const m = Math.round((hours - h) * 60);
        return `${h}:${String(m).padStart(2, '0')}`;
    }
    function _slaToHLabel(hours) {
        if (hours === null || hours === undefined) return null;
        const mins = Math.round(hours * 60);
        return `${hours.toFixed(2)} h(${mins} min)`;
    }

    let _currentSlaTicketId = null;

    async function openSlaDetail(ticketId, ticketNum) {
        _currentSlaTicketId = ticketId;
        document.getElementById('slaDetailTicketNum').textContent = '#' + ticketNum;
        document.getElementById('slaDetailBadges').classList.add('hidden');
        document.getElementById('slaDetailBadges').innerHTML = '';
        document.getElementById('slaDetailStatsBar').classList.add('hidden');
        document.getElementById('slaDetailPdfBtn').href = '/admin/sla/tickets/' + ticketId + '/log-pdf';
        document.getElementById('slaDetailPdfBtn').classList.remove('hidden');
        document.getElementById('slaDetailContent').innerHTML = `
            <div class="flex flex-col items-center justify-center gap-3 py-20 text-gray-300">
                <i class="fas fa-spinner fa-spin text-3xl"></i>
                <p class="text-sm text-gray-400">Loading SLA log…</p>
            </div>`;
        document.getElementById('slaDetailModal').classList.remove('hidden');
        await _fetchSlaDetail(ticketId);
    }

    async function refreshSlaDetail() {
        if (!_currentSlaTicketId) return;
        const icon = document.getElementById('slaDetailRefreshIcon');
        icon && icon.classList.add('fa-spin');
        await _fetchSlaDetail(_currentSlaTicketId);
        icon && icon.classList.remove('fa-spin');
    }

    async function _fetchSlaDetail(ticketId) {
        try {
            const res  = await fetch('/api/tickets/' + ticketId + '/sla', { credentials: 'include' });
            const json = await res.json();
            if (!json.success || !json.data) {
                document.getElementById('slaDetailContent').innerHTML =
                    `<div class="flex flex-col items-center gap-2 py-16 text-gray-300"><i class="fas fa-inbox text-3xl"></i><p class="text-sm text-gray-400">No SLA data available for this ticket.</p></div>`;
                return;
            }
            _renderSlaDetail(json.data);
        } catch {
            document.getElementById('slaDetailContent').innerHTML =
                `<div class="flex flex-col items-center gap-2 py-16 text-red-300"><i class="fas fa-exclamation-triangle text-3xl"></i><p class="text-sm text-red-400">Failed to load SLA log.</p></div>`;
        }
    }

    function _renderSlaDetail(data) {
        const respSc = STATUS_CFG_SLA[data.response && data.response.status] || STATUS_CFG_SLA['pending'];
        const resSc  = STATUS_CFG_SLA[data.resolution && data.resolution.status] || STATUS_CFG_SLA['pending'];

        document.getElementById('slaStatResponseVal').textContent   = data.response && data.response.actual_hours != null ? data.response.actual_hours + ' hrs' : '—';
        document.getElementById('slaStatResponseStatus').innerHTML  = `<span class="${respSc.text} font-semibold">${respSc.label}</span><span class="text-slate-400"> / target ${data.response ? data.response.target_hours : '—'} hrs</span>`;
        document.getElementById('slaStatResolutionVal').textContent = data.resolution && data.resolution.actual_hours != null ? data.resolution.actual_hours + ' hrs' : (data.sla_mode === 'response_only' ? 'N/A' : '—');
        document.getElementById('slaStatResolutionStatus').innerHTML = data.resolution
            ? `<span class="${resSc.text} font-semibold">${resSc.label}</span><span class="text-slate-400"> / target ${data.resolution.target_hours} hrs</span>`
            : `<span class="text-slate-400">Response-only mode</span>`;

        const totalWait = data.total_waiting_hours != null ? data.total_waiting_hours : 0;
        document.getElementById('slaStatWaitingVal').textContent   = totalWait > 0 ? totalWait.toFixed(2) + ' hrs' : '0 hrs';
        document.getElementById('slaStatBallHolder').textContent   = data.ball_holder ? (data.ball_holder.charAt(0).toUpperCase() + data.ball_holder.slice(1)) : '—';
        document.getElementById('slaDetailStatsBar').classList.remove('hidden');

        const badgesEl = document.getElementById('slaDetailBadges');
        badgesEl.innerHTML = `
            <span class="inline-flex items-center gap-1 text-[11px] font-semibold ${respSc.text} ${respSc.bg} px-2.5 py-1 rounded-full whitespace-nowrap">
                <span class="w-1.5 h-1.5 rounded-full ${respSc.dot} flex-shrink-0"></span>Response: ${respSc.label}
            </span>
            ${data.resolution ? `<span class="inline-flex items-center gap-1 text-[11px] font-semibold ${resSc.text} ${resSc.bg} px-2.5 py-1 rounded-full whitespace-nowrap">
                <span class="w-1.5 h-1.5 rounded-full ${resSc.dot} flex-shrink-0"></span>Resolution: ${resSc.label}
            </span>` : ''}`;
        badgesEl.classList.remove('hidden');
        badgesEl.classList.add('flex');

        if (!data.events || !data.events.length) {
            document.getElementById('slaDetailContent').innerHTML = `
                <div class="flex flex-col items-center gap-3 py-20 text-gray-300">
                    <i class="fas fa-table text-3xl"></i>
                    <p class="text-sm text-gray-400">No events recorded yet</p>
                </div>`;
            return;
        }

        let lastDate = null;
        const rows = data.events.map(function(e) {
            const dt      = e.event_at ? new Date(e.event_at) : null;
            const dateStr = dt ? dt.toLocaleDateString('id-ID', { day:'2-digit', month:'2-digit', year:'numeric' }) : '—';
            const timeStr = dt ? dt.toLocaleTimeString('id-ID', { hour:'2-digit', minute:'2-digit' }) : '—';
            const showDate = dateStr !== lastDate;
            lastDate = dateStr;
            const evCfg   = EVENT_ROW_CFG_SLA[e.event_type] || { dot: '#9ca3af', rowBg: '#fff', label: e.event_type };
            const ballCfg = e.ball_after ? (BALL_ICON_SLA[e.ball_after] || null) : null;
            const waitCell = e.waiting_hours !== null && e.waiting_hours !== undefined ? `<span class="text-[11px] font-semibold text-amber-600 whitespace-nowrap">${_slaToHLabel(e.waiting_hours)}</span>` : `<span class="text-gray-300 text-xs">—</span>`;
            const respCell = e.response_hours !== null && e.response_hours !== undefined ? `<span class="text-[11px] font-semibold text-gray-700 whitespace-nowrap">${_slaToHLabel(e.response_hours)}</span>` : `<span class="text-gray-300 text-xs">—</span>`;
            const resCell  = e.meeting_paused
                ? `<span class="text-[10px] font-semibold text-purple-600 whitespace-nowrap">Paused (Meeting)</span>`
                : (e.resolution_hours !== null && e.resolution_hours !== undefined ? `<span class="text-[11px] font-semibold text-gray-700 whitespace-nowrap">${_slaToHLabel(e.resolution_hours)}</span>` : `<span class="text-gray-300 text-xs">—</span>`);
            const statusCell = e.jarvis_status ? `<span class="text-[10px] text-gray-500 whitespace-nowrap">${e.jarvis_status.replace(/_/g,' ')}</span>` : `<span class="text-gray-300 text-xs">—</span>`;
            const ballCell = ballCfg ? `<span class="text-[11px] font-semibold text-gray-600 whitespace-nowrap">${ballCfg.icon} ${ballCfg.label}</span>` : `<span class="text-gray-300 text-xs">—</span>`;
            const senderPrefix = e.sender_name ? `<span class="font-semibold text-gray-700">${e.sender_name}:</span> ` : '';
            const bodyText = e.message_preview || e.notes || null;
            const msgText  = bodyText
                ? `<span title="${(e.message_preview || '').replace(/"/g,'&quot;')}" class="text-gray-500 text-xs">${senderPrefix}${bodyText.substring(0, 80)}${bodyText.length > 80 ? '…' : ''}</span>`
                : (e.sender_name ? `<span class="font-semibold text-gray-700 text-xs">${e.sender_name}</span>` : `<span class="text-gray-300 text-xs">—</span>`);
            const dateSep = showDate ? `<tr><td colspan="9" style="background:#f3f4f6;border-top:1px solid #e5e7eb;border-bottom:1px solid #e5e7eb;padding:4px 12px;"><span style="font-size:10px;font-weight:600;color:#6b7280;letter-spacing:0.04em;">${dt ? dt.toLocaleDateString('id-ID', { weekday:'long', day:'numeric', month:'long', year:'numeric' }) : dateStr}</span></td></tr>` : '';
            return `${dateSep}<tr style="background:${evCfg.rowBg};border-left:3px solid ${evCfg.dot};" class="border-b border-gray-100/80 hover:brightness-[0.97] transition-all">
                <td class="px-3 py-2.5 text-xs text-gray-400 whitespace-nowrap">${showDate ? dateStr : ''}</td>
                <td class="px-3 py-2.5 text-xs text-gray-600 font-mono whitespace-nowrap">${timeStr}</td>
                <td class="px-3 py-2.5 text-right whitespace-nowrap">${waitCell}</td>
                <td class="px-3 py-2.5 text-right whitespace-nowrap">${respCell}</td>
                <td class="px-3 py-2.5 text-right whitespace-nowrap">${resCell}</td>
                <td class="px-3 py-2.5 whitespace-nowrap"><div class="flex items-center gap-2"><span class="w-1.5 h-1.5 rounded-full flex-shrink-0" style="background:${evCfg.dot};"></span><span class="text-xs text-gray-700">${evCfg.label}</span></div></td>
                <td class="px-3 py-2.5">${statusCell}</td>
                <td class="px-3 py-2.5">${ballCell}</td>
                <td class="px-3 py-2.5 max-w-[220px] truncate">${msgText}</td>
            </tr>`;
        }).join('');

        const netHoursLabel = data.resolution && data.resolution.net_hours != null ? ` <span class="font-normal normal-case text-gray-400">(${_slaToHMM(data.resolution.net_hours)})</span>` : '';
        document.getElementById('slaDetailContent').innerHTML = `
            <table class="w-full text-sm border-collapse" style="min-width:820px">
                <thead>
                    <tr class="sticky top-0 z-10" style="background:#f8fafc;border-bottom:2px solid #e2e8f0;">
                        <th class="px-3 py-2.5 text-[10px] font-semibold tracking-wider text-gray-400 uppercase whitespace-nowrap text-left">Date</th>
                        <th class="px-3 py-2.5 text-[10px] font-semibold tracking-wider text-gray-400 uppercase whitespace-nowrap text-left">Time</th>
                        <th class="px-3 py-2.5 text-[10px] font-semibold tracking-wider text-gray-400 uppercase whitespace-nowrap text-right">Waiting</th>
                        <th class="px-3 py-2.5 text-[10px] font-semibold tracking-wider text-gray-400 uppercase whitespace-nowrap text-right">Response</th>
                        <th class="px-3 py-2.5 text-[10px] font-semibold tracking-wider text-gray-400 uppercase whitespace-nowrap text-right">Resolution${netHoursLabel}</th>
                        <th class="px-3 py-2.5 text-[10px] font-semibold tracking-wider text-gray-400 uppercase whitespace-nowrap text-left" style="padding-left:16px;">Event</th>
                        <th class="px-3 py-2.5 text-[10px] font-semibold tracking-wider text-gray-400 uppercase whitespace-nowrap text-left">Status</th>
                        <th class="px-3 py-2.5 text-[10px] font-semibold tracking-wider text-gray-400 uppercase whitespace-nowrap text-left">Ball</th>
                        <th class="px-3 py-2.5 text-[10px] font-semibold tracking-wider text-gray-400 uppercase text-left">Message</th>
                    </tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>`;
    }

    function closeSlaDetail() {
        document.getElementById('slaDetailModal').classList.add('hidden');
        document.getElementById('slaDetailStatsBar').classList.add('hidden');
        document.getElementById('slaDetailBadges').classList.add('hidden');
        _currentSlaTicketId = null;
    }

</script>

{{-- SLA Log Modal --}}
@if($can('sla.report'))
<div id="slaDetailModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" onclick="closeSlaDetail()"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-6xl max-h-[92vh] flex flex-col overflow-hidden">
            <div class="flex-shrink-0 bg-white border-b border-gray-100">
                <div class="flex items-center justify-between px-6 py-4">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center">
                            <i class="fas fa-table text-gray-500 text-sm"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-800">SLA Log</h3>
                            <p class="text-xs text-gray-400 mt-0.5">Ticket <span id="slaDetailTicketNum" class="font-mono font-semibold text-gray-600"></span></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <div id="slaDetailBadges" class="hidden items-center gap-2 flex-wrap"></div>
                        <button onclick="refreshSlaDetail()" title="Refresh SLA Log"
                            class="inline-flex items-center gap-1.5 text-xs text-gray-500 hover:text-gray-700 border border-gray-200 hover:border-gray-300 bg-white px-3 py-1.5 rounded-lg transition whitespace-nowrap">
                            <i class="fas fa-sync-alt text-xs" id="slaDetailRefreshIcon"></i> Refresh
                        </button>
                        <a id="slaDetailPdfBtn" href="#" target="_blank"
                            class="hidden inline-flex items-center gap-1.5 text-xs font-semibold text-red-600 border border-red-200 hover:bg-red-50 px-3 py-1.5 rounded-lg transition whitespace-nowrap">
                            <i class="fas fa-file-pdf text-xs"></i> Download PDF
                        </a>
                        <button onclick="closeSlaDetail()"
                            class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                            <i class="fas fa-times text-sm"></i>
                        </button>
                    </div>
                </div>
                <div id="slaDetailStatsBar" class="hidden px-6 pb-4">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <div class="bg-gray-50 border border-gray-100 rounded-xl px-4 py-2.5">
                            <p class="text-[10px] text-gray-400 uppercase tracking-wider font-semibold">Response</p>
                            <p class="text-sm font-bold text-gray-800 mt-0.5" id="slaStatResponseVal">—</p>
                            <p class="text-[10px] mt-0.5" id="slaStatResponseStatus"></p>
                        </div>
                        <div class="bg-gray-50 border border-gray-100 rounded-xl px-4 py-2.5">
                            <p class="text-[10px] text-gray-400 uppercase tracking-wider font-semibold">Resolution</p>
                            <p class="text-sm font-bold text-gray-800 mt-0.5" id="slaStatResolutionVal">—</p>
                            <p class="text-[10px] mt-0.5" id="slaStatResolutionStatus"></p>
                        </div>
                        <div class="bg-gray-50 border border-gray-100 rounded-xl px-4 py-2.5">
                            <p class="text-[10px] text-gray-400 uppercase tracking-wider font-semibold">Waiting</p>
                            <p class="text-sm font-bold text-gray-800 mt-0.5" id="slaStatWaitingVal">—</p>
                            <p class="text-[10px] text-gray-400 mt-0.5">total pause time</p>
                        </div>
                        <div class="bg-gray-50 border border-gray-100 rounded-xl px-4 py-2.5">
                            <p class="text-[10px] text-gray-400 uppercase tracking-wider font-semibold">Ball Holder</p>
                            <p class="text-sm font-bold text-gray-800 mt-0.5" id="slaStatBallHolder">—</p>
                        </div>
                    </div>
                </div>
            </div>
            <div id="slaDetailContent" class="overflow-auto flex-1 bg-gray-50/30">
                <div class="flex items-center justify-center h-32 text-gray-300">
                    <i class="fas fa-spinner fa-spin text-3xl"></i>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

{{-- Load custom-dd component (sama dengan Employee/Customer Management).
     filemtime cache buster supaya production auto-invalidate setiap deploy. --}}
@php
    $customDdPath = public_path('js/custom-dropdown.js');
    $customDdVer  = file_exists($customDdPath) ? filemtime($customDdPath) : time();
@endphp
<script src="/js/custom-dropdown.js?v={{ $customDdVer }}"></script>

@endsection

@extends('dashboard')
@section('title', 'Support Tickets')
@section('page-title', 'Support Tickets')
@section('page-subtitle', 'Manage and track all support requests')
@section('content')
@php
    // Kolom "AI Summarize" duduk paling kiri dan ikut sticky. Lebarnya dipakai
    // ulang untuk menggeser offset `left:` kolom Last Update dan Tiket di
    // bawah — kalau kolomnya tidak tampil (tak punya izin), offsetnya nol dan
    // tata letak kembali persis seperti sebelum fitur ini ada.
    $canAiSummarize = $can('ui.ticket.btn-ai-summarize');
    $aiColW = $canAiSummarize ? 44 : 0;
@endphp
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
            @if($can('ticket.my-tickets.ds-user'))
            <button onclick="toggleView('my')" id="btnViewMy" class="px-4 py-1.5 text-xs font-semibold rounded-lg transition-all duration-200">
                <i class="fas fa-user text-[10px] mr-1"></i>My Tickets
            </button>
            @endif
            @if($can('ticket.all-tickets'))
            <button onclick="toggleView('all')" id="btnViewAll" class="px-4 py-1.5 text-xs font-semibold rounded-lg transition-all duration-200">
                <i class="fas fa-list text-[10px] mr-1"></i>All Tickets
            </button>
            @endif
            @if($can('ticket.unassigned'))
            <button onclick="toggleView('unassigned-tab')" id="btnViewUnassignedTab" class="px-4 py-1.5 text-xs font-semibold rounded-lg transition-all duration-200">
                <i class="fas fa-user-clock text-[10px] mr-1"></i>Unassigned Ticket
            </button>
            @endif
        </div>
        @elseif($user->hasRole(\App\Enums\RoleId::DELIVERY_HELPDESK->value))
        <div class="inline-flex bg-gray-100 rounded-xl p-1">
            @if($can('ticket.all-tickets'))
            <button onclick="toggleView('all')" id="btnViewAllHd" class="px-4 py-1.5 text-xs font-semibold rounded-lg transition-all duration-200">
                <i class="fas fa-list text-[10px] mr-1"></i>All Tickets
            </button>
            @endif
            @if($can('ticket.unassigned'))
            <button onclick="toggleView('unassigned')" id="btnViewUnassigned" class="px-4 py-1.5 text-xs font-semibold rounded-lg transition-all duration-200">
                <i class="fas fa-user-clock text-[10px] mr-1"></i>Unassigned
            </button>
            @endif
        </div>
        @elseif($user->hasRole(\App\Enums\RoleId::DELIVERY_SUPPORT_MANAGER->value))
        <div class="inline-flex bg-gray-100 rounded-xl p-1">
            @if($can('ticket.my-tickets.ds-manager'))
            <button onclick="toggleView('my')" id="btnViewMy" class="px-4 py-1.5 text-xs font-semibold rounded-lg transition-all duration-200">My Tickets</button>
            @endif
            @if($can('ticket.all-tickets'))
            <button onclick="toggleView('all')" id="btnViewAll" class="px-4 py-1.5 text-xs font-semibold rounded-lg transition-all duration-200">All Tickets</button>
            @endif
            @if($can('ticket.unassigned'))
            <button onclick="toggleView('unassigned-tab')" id="btnViewUnassignedTab" class="px-4 py-1.5 text-xs font-semibold rounded-lg transition-all duration-200">
                <i class="fas fa-user-clock text-[10px] mr-1"></i>Unassigned Ticket
            </button>
            @endif
        </div>
        @elseif($can('ticket.unassigned'))
        <div class="inline-flex bg-gray-100 rounded-xl p-1">
            <button onclick="toggleView('unassigned-tab')" id="btnViewUnassignedTab" class="px-4 py-1.5 text-xs font-semibold rounded-lg transition-all duration-200">
                <i class="fas fa-user-clock text-[10px] mr-1"></i>Unassigned Ticket
            </button>
        </div>
        @endif

        {{-- Tab "Ticket Modul" — independen dari role di atas. Muncul hanya kalau role-nya
             di-grant menu 'ticket.module-lead' DAN employee ini memang lead di module manapun
             (module_leads) — dua-duanya harus true. --}}
        @if($can('ticket.module-lead') && ($isModuleLead ?? false))
        <div class="inline-flex bg-gray-100 rounded-xl p-1">
            <button onclick="toggleView('module-lead')" id="btnViewModuleLead" class="px-4 py-1.5 text-xs font-semibold rounded-lg transition-all duration-200">
                <i class="fas fa-people-group text-[10px] mr-1"></i>Ticket Modul
            </button>
        </div>
        @endif

        @if($can('ui.ticket.btn-create'))
        <button onclick="openCreateTicketModal()"
            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-all">
            <i class="fas fa-plus text-xs"></i>Create Ticket
        </button>
        @endif

        @if($user->hasAnyRole([\App\Enums\RoleId::EC_ADMINISTRATOR->value, \App\Enums\RoleId::DELIVERY_SUPPORT_HEAD->value, \App\Enums\RoleId::DELIVERY_HELPDESK->value]))
        <button onclick="exportWithFilters()"
            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-white border border-gray-200 text-gray-600 text-xs font-semibold rounded-xl hover:bg-gray-50 hover:border-gray-300 transition-all">
            <i class="fas fa-file-excel text-green-600 text-xs"></i>Export
        </button>
        @endif

    </div>
</div>

{{-- ── Status Cards ────────────────────────────────────────────────────────── --}}
<div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-9 gap-2 mb-4">
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
    <div id="filterHold" onclick="filterTickets('hold')"
        class="stat-card bg-white rounded-xl border border-gray-200 px-4 py-3.5 cursor-pointer select-none transition-all duration-200 hover:shadow-md hover:border-orange-200">
        <div class="flex items-center gap-1.5 mb-2">
            <span class="w-1.5 h-1.5 rounded-full bg-orange-500"></span>
            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest">Hold</p>
        </div>
        <p class="text-2xl font-bold text-orange-600 leading-none" id="holdCount">0</p>
    </div>
    <div id="filterCancelled" onclick="filterTickets('cancelled')"
        class="stat-card bg-white rounded-xl border border-gray-200 px-4 py-3.5 cursor-pointer select-none transition-all duration-200 hover:shadow-md hover:border-gray-300">
        <div class="flex items-center gap-1.5 mb-2">
            <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest">Cancelled</p>
        </div>
        <p class="text-2xl font-bold text-gray-500 leading-none" id="cancelledCount">0</p>
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
                        @if($canAiSummarize)
                        {{-- AI SUMMARIZE: kolom paling kiri, tanpa label supaya tetap ramping --}}
                        <th class="px-2 py-2.5 text-center text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap border-b border-gray-200 sticky left-0 bg-gray-50 z-20"
                            style="min-width:{{ $aiColW }}px;width:{{ $aiColW }}px;" title="AI Summarize">
                            <span class="sr-only">AI</span>
                            <svg class="w-3.5 h-3.5 mx-auto text-gray-400" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                <path d="M10 1.5l1.6 4.2 4.4 1.3-4.4 1.3L10 12.5 8.4 8.3 4 7l4.4-1.3L10 1.5zM15.5 12l.9 2.3 2.6.7-2.6.7-.9 2.3-.9-2.3-2.6-.7 2.6-.7.9-2.3zM4.5 11l.7 1.8 2 .5-2 .5-.7 1.8-.7-1.8-2-.5 2-.5.7-1.8z" />
                            </svg>
                        </th>
                        @endif
                        {{-- LAST UPDATE: sortable --}}
                        <th class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap border-b border-gray-200 sticky bg-gray-50 z-20 th-sortable cursor-pointer transition-colors"
                            style="min-width:110px;left:{{ $aiColW }}px;" onclick="sortTickets('last_update')" title="Sort by Last Update">
                            <div class="flex items-center gap-1">
                                <span>Last Update</span>
                                <span id="sort-icon-last_update" class="sort-icon text-gray-300 font-normal normal-case tracking-normal">⇅</span>
                            </div>
                        </th>
                        {{-- TIKET: sortable + keyword filter --}}
                        <th class="p-0 text-left whitespace-nowrap border-b border-gray-200 sticky bg-gray-50 z-20" style="min-width:120px;left:{{ 110 + $aiColW }}px;">
                            <button type="button" id="ticketFilterBtn" onclick="toggleTicketFilter(event)"
                                class="w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                                <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Tiket</span>
                                <span id="sort-icon-ticket_number" class="sort-icon text-gray-300 font-normal normal-case tracking-normal text-xs">⇅</span>
                                <svg id="ticketFilterCaret" class="w-3.5 h-3.5 text-gray-500 transition-all duration-200 shrink-0 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                                </svg>
                                <svg id="ticketFilterIcon" class="w-3.5 h-3.5 text-gray-300 transition-colors" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 011 1v1.586a1 1 0 01-.293.707l-4.121 4.121A1 1 0 0012 12.121V15.5l-4 1.5v-4.879a1 1 0 00-.293-.707L3.586 7.293A1 1 0 013.293 6.586L3 5z" clip-rule="evenodd" />
                                </svg>
                            </button>
                            <div id="ticketFilterPanel" class="hidden absolute mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] p-3" style="min-width:220px;">
                                <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Search ticket number</label>
                                <input type="text" id="ticketFilterInput" placeholder="e.g. TKT-2024-001…"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm font-normal text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400"
                                    oninput="onTicketFilterInput()">
                                <div class="border-t border-gray-100 mt-3 pt-3">
                                    <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-2">Sort</label>
                                    <div class="flex gap-2">
                                        <button type="button" id="sort-btn-ticket_number-asc" onclick="sortTickets('ticket_number','asc'); closeTicketFilter();"
                                            class="sort-dir-btn flex-1 flex items-center justify-center gap-1 px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50 transition-colors">
                                            ↑ Ascending
                                        </button>
                                        <button type="button" id="sort-btn-ticket_number-desc" onclick="sortTickets('ticket_number','desc'); closeTicketFilter();"
                                            class="sort-dir-btn flex-1 flex items-center justify-center gap-1 px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50 transition-colors">
                                            ↓ Descending
                                        </button>
                                    </div>
                                </div>
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
                                <span id="sort-icon-description" class="sort-icon text-gray-300 font-normal normal-case tracking-normal text-xs">⇅</span>
                                <svg id="descFilterCaret" class="w-3.5 h-3.5 text-gray-500 transition-all duration-200 shrink-0 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                                </svg>
                                <svg id="descFilterIcon" class="w-3.5 h-3.5 text-gray-300 transition-colors" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 011 1v1.586a1 1 0 01-.293.707l-4.121 4.121A1 1 0 0012 12.121V15.5l-4 1.5v-4.879a1 1 0 00-.293-.707L3.586 7.293A1 1 0 013.293 6.586L3 5z" clip-rule="evenodd" />
                                </svg>
                            </button>
                            <div id="descFilterPanel" class="hidden absolute mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] p-3" style="min-width:260px;">
                                <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Search description</label>
                                <input type="text" id="descFilterInput" placeholder="Type keyword (case-insensitive)…"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm font-normal text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400"
                                    oninput="onDescFilterInput()">
                                <div class="flex justify-end gap-2 mt-3">
                                    <button type="button" onclick="clearDescFilter()" class="px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50">Clear</button>
                                </div>
                            </div>
                        </th>
                        {{-- DATE: from-to range filter (also supports sort) --}}
                        <th class="p-0 text-left whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:120px;">
                            <button type="button" id="dateFilterBtn" onclick="toggleDateFilter(event)"
                                class="w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                                <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Start Date</span>
                                <span id="sort-icon-date" class="sort-icon text-gray-300 font-normal normal-case tracking-normal text-xs">⇅</span>
                                <svg id="dateFilterCaret" class="w-3.5 h-3.5 text-gray-500 transition-all duration-200 shrink-0 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                                </svg>
                                <svg id="dateFilterIcon" class="w-3.5 h-3.5 text-gray-300 transition-colors" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 011 1v1.586a1 1 0 01-.293.707l-4.121 4.121A1 1 0 0012 12.121V15.5l-4 1.5v-4.879a1 1 0 00-.293-.707L3.586 7.293A1 1 0 013.293 6.586L3 5z" clip-rule="evenodd" />
                                </svg>
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
                                <div class="border-t border-gray-100 mt-3 pt-3">
                                    <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-2">Sort</label>
                                    <div class="flex gap-2">
                                        <button type="button" id="sort-btn-date-asc" onclick="sortTickets('date','asc'); document.getElementById('dateFilterPanel').classList.add('hidden');"
                                            class="sort-dir-btn flex-1 flex items-center justify-center gap-1 px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50 transition-colors">
                                            ↑ Ascending
                                        </button>
                                        <button type="button" id="sort-btn-date-desc" onclick="sortTickets('date','desc'); document.getElementById('dateFilterPanel').classList.add('hidden');"
                                            class="sort-dir-btn flex-1 flex items-center justify-center gap-1 px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50 transition-colors">
                                            ↓ Descending
                                        </button>
                                    </div>
                                </div>
                                <div class="flex justify-end gap-2 mt-3">
                                    <button type="button" onclick="clearDateFilter()" class="px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50">Clear</button>
                                    <button type="button" onclick="applyDateFilter()" class="px-3 py-1.5 text-xs text-white bg-red-700 hover:bg-red-800 rounded-md">Apply</button>
                                </div>
                            </div>
                        </th>
                        {{-- CLOSE DATE --}}
                        <th class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:120px;">
                            Close Date
                        </th>
                        {{-- DAY ON CLOSE: sort filter (highest/lowest) --}}
                        <th class="p-0 text-left whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:110px;">
                            <button type="button" id="dayOnCloseFilterBtn" onclick="toggleDayOnCloseFilter(event)"
                                class="w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                                <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Day on Close</span>
                                <span id="sort-icon-day_on_close" class="sort-icon text-gray-300 font-normal normal-case tracking-normal text-xs">⇅</span>
                                <svg id="dayOnCloseFilterCaret" class="w-3.5 h-3.5 text-gray-500 transition-all duration-200 shrink-0 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <div id="dayOnCloseFilterPanel" class="hidden absolute mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] p-3" style="min-width:200px;">
                                <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-2">Sort</label>
                                <div class="flex gap-2">
                                    <button type="button" id="sort-btn-day_on_close-desc" onclick="sortTickets('day_on_close','desc'); closeDayOnCloseFilter();"
                                        class="sort-dir-btn flex-1 flex items-center justify-center gap-1 px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50 transition-colors">
                                        ↓ Highest
                                    </button>
                                    <button type="button" id="sort-btn-day_on_close-asc" onclick="sortTickets('day_on_close','asc'); closeDayOnCloseFilter();"
                                        class="sort-dir-btn flex-1 flex items-center justify-center gap-1 px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50 transition-colors">
                                        ↑ Lowest
                                    </button>
                                </div>
                                <div class="flex justify-end gap-2 mt-3">
                                    <button type="button" onclick="clearDayOnCloseFilter()" class="px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50">Clear</button>
                                </div>
                            </div>
                        </th>
                        {{-- CUSTOMER: column filter dropdown --}}
                        <th class="p-0 text-left whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:160px;">
                            <div class="custom-dd relative w-full" id="ddColFilterCustomer" data-fixed="true" data-onchange="applyColFilter" data-searchable="true">
                                <button type="button" class="custom-dd-btn w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                                    <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Customer</span>
                                    <svg class="custom-dd-arrow w-3.5 h-3.5 text-gray-500 transition-all duration-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                                    </svg>
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
                                    <svg class="custom-dd-arrow w-3.5 h-3.5 text-gray-500 transition-all duration-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                                <input type="hidden" id="colFilterPic" value="">
                                <div class="custom-dd-panel hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] py-1.5 overflow-y-auto" style="max-height:240px;min-width:200px;">
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="">All</button>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="__unassigned__">Unassigned</button>
                                </div>
                            </div>
                        </th>
                        {{-- PRIORITY: column filter dropdown --}}
                        <th class="p-0 text-left whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:90px;">
                            <div class="custom-dd relative w-full" id="ddColFilterPriority" data-fixed="true" data-multi="true" data-onchange="applyColFilter">
                                <button type="button" class="custom-dd-btn w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                                    <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Priority</span>
                                    <svg class="custom-dd-arrow w-3.5 h-3.5 text-gray-500 transition-all duration-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                                <input type="hidden" id="colFilterPriority" value="">
                                <div class="custom-dd-panel hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] py-1.5 overflow-y-auto" style="max-height:220px;min-width:140px;">
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="">All</button>
                                    <button type="button" class="custom-dd-item w-full flex items-center justify-between gap-2 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="Very High"><span class="custom-dd-item-text">Very High</span><svg class="custom-dd-check w-4 h-4 text-red-500 opacity-0 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                        </svg></button>
                                    <button type="button" class="custom-dd-item w-full flex items-center justify-between gap-2 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="High"><span class="custom-dd-item-text">High</span><svg class="custom-dd-check w-4 h-4 text-red-500 opacity-0 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                        </svg></button>
                                    <button type="button" class="custom-dd-item w-full flex items-center justify-between gap-2 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="Medium"><span class="custom-dd-item-text">Medium</span><svg class="custom-dd-check w-4 h-4 text-red-500 opacity-0 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                        </svg></button>
                                    <button type="button" class="custom-dd-item w-full flex items-center justify-between gap-2 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="Low"><span class="custom-dd-item-text">Low</span><svg class="custom-dd-check w-4 h-4 text-red-500 opacity-0 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                        </svg></button>
                                </div>
                            </div>
                        </th>
                        {{-- SCALE: column filter dropdown --}}
                        <th class="p-0 text-left whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:80px;">
                            <div class="custom-dd relative w-full" id="ddColFilterScale" data-fixed="true" data-multi="true" data-onchange="applyColFilter">
                                <button type="button" class="custom-dd-btn w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                                    <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Scale</span>
                                    <svg class="custom-dd-arrow w-3.5 h-3.5 text-gray-500 transition-all duration-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                                <input type="hidden" id="colFilterScale" value="">
                                <div class="custom-dd-panel hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] py-1.5 overflow-y-auto" style="max-height:220px;min-width:120px;">
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="">All</button>
                                    <button type="button" class="custom-dd-item w-full flex items-center justify-between gap-2 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="Simple"><span class="custom-dd-item-text">Simple</span><svg class="custom-dd-check w-4 h-4 text-red-500 opacity-0 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                        </svg></button>
                                    <button type="button" class="custom-dd-item w-full flex items-center justify-between gap-2 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="Medium"><span class="custom-dd-item-text">Medium</span><svg class="custom-dd-check w-4 h-4 text-red-500 opacity-0 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                        </svg></button>
                                    <button type="button" class="custom-dd-item w-full flex items-center justify-between gap-2 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="Complex"><span class="custom-dd-item-text">Complex</span><svg class="custom-dd-check w-4 h-4 text-red-500 opacity-0 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                        </svg></button>
                                </div>
                            </div>
                        </th>
                        {{-- STATUS: column filter dropdown --}}
                        <th class="p-0 text-left whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:120px;">
                            <div class="custom-dd relative w-full" id="ddColFilterStatus" data-fixed="true" data-multi="true" data-onchange="applyColFilter">
                                <button type="button" class="custom-dd-btn w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                                    <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Status</span>
                                    <svg class="custom-dd-arrow w-3.5 h-3.5 text-gray-500 transition-all duration-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                                <input type="hidden" id="colFilterStatus" value="">
                                <div class="custom-dd-panel hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] py-1.5 overflow-y-auto" style="max-height:240px;min-width:200px;">
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="">All</button>
                                    <button type="button" class="custom-dd-item w-full flex items-center justify-between gap-2 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="open"><span class="custom-dd-item-text">Open</span><svg class="custom-dd-check w-4 h-4 text-red-500 opacity-0 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                        </svg></button>
                                    <button type="button" class="custom-dd-item w-full flex items-center justify-between gap-2 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="inprocess"><span class="custom-dd-item-text">Inprocess</span><svg class="custom-dd-check w-4 h-4 text-red-500 opacity-0 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                        </svg></button>
                                    <button type="button" class="custom-dd-item w-full flex items-center justify-between gap-2 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="waiting_on_customer"><span class="custom-dd-item-text">Waiting on Customer</span><svg class="custom-dd-check w-4 h-4 text-red-500 opacity-0 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                        </svg></button>
                                    <button type="button" class="custom-dd-item w-full flex items-center justify-between gap-2 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="waiting_on_3rd_party"><span class="custom-dd-item-text">Waiting on 3rd Party</span><svg class="custom-dd-check w-4 h-4 text-red-500 opacity-0 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                        </svg></button>
                                    <button type="button" class="custom-dd-item w-full flex items-center justify-between gap-2 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="waiting_to_confirmation"><span class="custom-dd-item-text">Waiting to Confirmation</span><svg class="custom-dd-check w-4 h-4 text-red-500 opacity-0 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                        </svg></button>
                                    <button type="button" class="custom-dd-item w-full flex items-center justify-between gap-2 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="hold"><span class="custom-dd-item-text">Hold</span><svg class="custom-dd-check w-4 h-4 text-red-500 opacity-0 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                        </svg></button>
                                    <button type="button" class="custom-dd-item w-full flex items-center justify-between gap-2 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="cancelled"><span class="custom-dd-item-text">Cancelled</span><svg class="custom-dd-check w-4 h-4 text-red-500 opacity-0 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                        </svg></button>
                                    <button type="button" class="custom-dd-item w-full flex items-center justify-between gap-2 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="closed"><span class="custom-dd-item-text">Closed</span><svg class="custom-dd-check w-4 h-4 text-red-500 opacity-0 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                        </svg></button>
                                </div>
                            </div>
                        </th>
                        {{-- TYPE: column filter dropdown --}}
                        <th class="p-0 text-left whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:130px;">
                            <div class="custom-dd relative w-full" id="ddColFilterType" data-fixed="true" data-multi="true" data-onchange="applyColFilter">
                                <button type="button" class="custom-dd-btn w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                                    <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Type</span>
                                    <svg class="custom-dd-arrow w-3.5 h-3.5 text-gray-500 transition-all duration-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                                <input type="hidden" id="colFilterType" value="">
                                <div class="custom-dd-panel hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] py-1.5 overflow-y-auto" style="max-height:220px;min-width:170px;">
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="">All</button>
                                    <button type="button" class="custom-dd-item w-full flex items-center justify-between gap-2 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="Incident"><span class="custom-dd-item-text">Incident</span><svg class="custom-dd-check w-4 h-4 text-red-500 opacity-0 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                        </svg></button>
                                    <button type="button" class="custom-dd-item w-full flex items-center justify-between gap-2 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="Change Request"><span class="custom-dd-item-text">Change Request</span><svg class="custom-dd-check w-4 h-4 text-red-500 opacity-0 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                        </svg></button>
                                    <button type="button" class="custom-dd-item w-full flex items-center justify-between gap-2 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="Service Request"><span class="custom-dd-item-text">Service Request</span><svg class="custom-dd-check w-4 h-4 text-red-500 opacity-0 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                        </svg></button>
                                    <button type="button" class="custom-dd-item w-full flex items-center justify-between gap-2 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="EWA"><span class="custom-dd-item-text">EWA</span><svg class="custom-dd-check w-4 h-4 text-red-500 opacity-0 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                        </svg></button>
                                    <button type="button" class="custom-dd-item w-full flex items-center justify-between gap-2 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="RISE"><span class="custom-dd-item-text">RISE</span><svg class="custom-dd-check w-4 h-4 text-red-500 opacity-0 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                        </svg></button>
                                    <button type="button" class="custom-dd-item w-full flex items-center justify-between gap-2 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="Consult"><span class="custom-dd-item-text">Consult</span><svg class="custom-dd-check w-4 h-4 text-red-500 opacity-0 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                        </svg></button>
                                    <button type="button" class="custom-dd-item w-full flex items-center justify-between gap-2 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="Internal"><span class="custom-dd-item-text">Internal</span><svg class="custom-dd-check w-4 h-4 text-red-500 opacity-0 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                        </svg></button>
                                </div>
                            </div>
                        </th>
                        {{-- MODULE: column filter dropdown --}}
                        <th class="p-0 text-left whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:130px;">
                            <div class="custom-dd relative w-full" id="ddColFilterModule" data-fixed="true" data-multi="true" data-onchange="applyColFilter">
                                <button type="button" class="custom-dd-btn w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                                    <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Module</span>
                                    <svg class="custom-dd-arrow w-3.5 h-3.5 text-gray-500 transition-all duration-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                                <input type="hidden" id="colFilterModule" value="">
                                <div class="custom-dd-panel hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] py-1.5 overflow-y-auto" style="max-height:220px;min-width:170px;">
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="">All</button>
                                    @foreach ($modules as $moduleOption)
                                        <button type="button" class="custom-dd-item w-full flex items-center justify-between gap-2 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="{{ $moduleOption['id'] }}"><span class="custom-dd-item-text">{{ $moduleOption['name'] }}</span><svg class="custom-dd-check w-4 h-4 text-red-500 opacity-0 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                            </svg></button>
                                    @endforeach
                                </div>
                            </div>
                        </th>
                        <th class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap border-b border-gray-200" style="min-width:130px;">Assign Delivery</th>
                        <th class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap border-b border-gray-200" style="min-width:140px;">Customer Mandays</th>
                        <th class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap border-b border-gray-200" style="min-width:120px;">Activity Date</th>
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
                        @if($can('ticket.hide'))
                        <th class="px-3 py-2.5 text-center text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap border-b border-gray-200" style="min-width:80px;">Actions</th>
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
                            <svg class="custom-dd-arrow w-4 h-4 text-gray-400 shrink-0 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
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

                {{-- To (dikosongkan by default — TIDAK auto company email; diisi manual bila perlu) --}}
                <div>
                    <label class="text-xs font-semibold text-gray-600 mb-1.5 block uppercase tracking-wide">
                        To <span class="text-gray-400 font-normal normal-case">(optional — kosongkan untuk EWA; bisa lebih dari satu, pisah koma)</span>
                    </label>
                    <input type="text" id="newToEmail"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent"
                        placeholder="to1@example.com, to2@example.com">
                </div>

                {{-- CC Emails (diisi manual — TIDAK auto dari contact) --}}
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
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
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
                            <option value="Change Request">Change Request</option>
                            <option value="Service Request">Service Request</option>
                            <option value="EWA">EWA</option>
                            <option value="RISE">RISE</option>
                            <option value="Consult">Consult</option>
                            {{-- Internal: tiket ini TIDAK ditampilkan ke customer di Jarvies --}}
                            <option value="Internal">Internal</option>
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
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
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
                            <select id="newModuleId"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
                                <option value="">Select module</option>
                                @foreach ($modules as $moduleOption)
                                    <option value="{{ $moduleOption['id'] }}">{{ $moduleOption['name'] }}</option>
                                @endforeach
                            </select>
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
    thead th.th-sortable {
        user-select: none;
    }

    thead th.th-sortable:hover {
        background: #f1f5f9;
    }

    .sort-icon {
        font-style: normal;
        transition: color 0.15s;
    }

    .sort-icon.active {
        color: #111827;
    }

    /* Label tombol sort ("↓ Highest", "↑ Descending") adalah satu teks; tanpa ini
       ia membungkus dan panahnya jatuh ke baris sendiri di atas teks. Panel-nya
       absolute, jadi biarkan melebar mengikuti isi. Ditaruh di CSS (bukan class
       Tailwind) karena updateSortButtons() menimpa className tombol ini. */
    .sort-dir-btn {
        white-space: nowrap;
    }

    /* Item dropdown adalah <button>, yang default UA-nya text-align:center. Selama
       teksnya muat satu baris span-nya menyusut sehingga terlihat rata kiri, tapi
       label panjang ("Waiting on Customer", "Change Request") membungkus jadi dua
       baris dan langsung tampak ter-center. Paksa rata kiri. */
    .custom-dd-item {
        text-align: left;
    }

    /* ── Column filter dropdown active state ── */
    .custom-dd.col-dd-active .custom-dd-arrow {
        color: #dc2626;
    }

    .custom-dd.col-dd-active .custom-dd-btn>span {
        color: #dc2626;
        font-weight: 700;
    }

    /* View Toggle */
    #btnViewAll,
    #btnViewMy,
    #btnViewAllHd,
    #btnViewUnassigned,
    #btnViewUnassignedTab,
    #btnViewModuleLead {
        background: transparent;
        color: #9ca3af;
        font-size: 12px;
    }

    #btnViewAll.active,
    #btnViewMy.active,
    #btnViewAllHd.active,
    #btnViewUnassigned.active,
    #btnViewUnassignedTab.active,
    #btnViewModuleLead.active {
        background: white;
        color: #111827;
        font-weight: 700;
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.12);
    }

    /* Stat cards active state */
    .stat-card.active-filter {
        border-left: 3px solid #dc2626 !important;
        border-top-color: #fecaca !important;
        border-right-color: #fecaca !important;
        border-bottom-color: #fecaca !important;
        background: #fff8f8 !important;
        box-shadow: 0 2px 8px rgba(220, 38, 38, 0.08) !important;
    }

    /* Table rows */
    #ticketsListBody tr {
        cursor: pointer;
        transition: background 0.1s;
    }

    #ticketsListBody tr:hover {
        background: #f8fafc;
    }

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
        width: 7px;
        height: 7px;
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
        box-shadow: 2px 0 4px rgba(0, 0, 0, 0.04);
    }

    #ticketsListBody tr:hover td:first-child,
    #ticketsListBody tr:hover td:nth-child(2) {
        background: #f8fafc;
    }
</style>

<script>
    let allTickets = [];
    let filteredTickets = [];
    let currentStats = null;
    let currentFilter = 'all';
    let currentTicketSort = {
        key: 'last_update',
        dir: 'desc'
    };
    let itemsPerPage = 200;
    let currentPage = 1;
    let totalItems = 0;
    let totalPages = 0;
    let userRoleIds                   = {!! json_encode($user->role_ids) !!};
    let userRole                      = userRoleIds[0] ?? 0;
    let currentEmployeeId             = {{ $currentEmployeeId ?? 'null' }};
    const CAN_VIEW_SLA_REPORT         = {{ $can('sla.report') ? 'true' : 'false' }};
    const CAN_HIDE_TICKET             = {{ $can('ticket.hide') ? 'true' : 'false' }};
    const CAN_VIEW_UNASSIGNED_TICKET  = {{ $can('ticket.unassigned') ? 'true' : 'false' }};
    const CAN_VIEW_ALL_TICKETS        = {{ $can('ticket.all-tickets') ? 'true' : 'false' }};
    const CAN_VIEW_MY_TICKET_DS_USER    = {{ $can('ticket.my-tickets.ds-user') ? 'true' : 'false' }};
    const CAN_VIEW_MY_TICKET_DS_MANAGER = {{ $can('ticket.my-tickets.ds-manager') ? 'true' : 'false' }};
    const IS_EXTERNAL_EMPLOYEE        = {{ ($isExternalEmployee ?? false) ? 'true' : 'false' }};
    const EC_ADMINISTRATOR_ROLE       = {{ \App\Enums\RoleId::EC_ADMINISTRATOR->value }};
    const DELIVERY_SUPPORT_USER_ROLE  = {{ \App\Enums\RoleId::DELIVERY_SUPPORT_USER->value }};
    const EC_USER_ROLE                = {{ \App\Enums\RoleId::EC_USER->value }};
    const HELPDESK_ROLE               = {{ \App\Enums\RoleId::DELIVERY_HELPDESK->value }};
    const SUPPORT_MANAGER_ROLE        = {{ \App\Enums\RoleId::DELIVERY_SUPPORT_MANAGER->value }};
    const HEAD_ROLES                  = [{{ \App\Enums\RoleId::DELIVERY_SUPPORT_HEAD->value }}, {{ \App\Enums\RoleId::DELIVERY_PROJECT_HEAD->value }}];
    // Roles that use the All/Unassigned toggle (Helpdesk only)
    const STAFF_TOGGLE_ROLES          = [HELPDESK_ROLE];
    // Pilih tab default berdasarkan tombol yang benar-benar tampil (permission-aware),
    // supaya gak ada mismatch antara tombol yang di-highlight vs data yang di-fetch.
    function computeDefaultView() {
        if (IS_EXTERNAL_EMPLOYEE && userRole !== SUPPORT_MANAGER_ROLE && !HEAD_ROLES.includes(userRole)) return 'my';

        if (userRole === DELIVERY_SUPPORT_USER_ROLE) {
            if (CAN_VIEW_MY_TICKET_DS_USER) return 'my';
            if (CAN_VIEW_ALL_TICKETS) return 'all';
            if (CAN_VIEW_UNASSIGNED_TICKET) return 'unassigned-tab';
            return 'my';
        }
        if (userRole === SUPPORT_MANAGER_ROLE) {
            if (CAN_VIEW_ALL_TICKETS) return 'all';
            if (CAN_VIEW_MY_TICKET_DS_MANAGER) return 'my';
            if (CAN_VIEW_UNASSIGNED_TICKET) return 'unassigned-tab';
            return 'all';
        }
        if (STAFF_TOGGLE_ROLES.includes(userRole)) {
            return 'all';
        }
        if (userRole !== EC_ADMINISTRATOR_ROLE && CAN_VIEW_UNASSIGNED_TICKET) return 'unassigned-tab';
        return 'all';
    }
    let currentView = computeDefaultView();
    let sortField = null; // 'last_update' | 'ticket_number' | 'date'
    let sortDir = null; // 'desc' | 'asc'


    // ── Filter state persistence ──────────────────────────────────────────
    // Klik "Back to Tickets" atau row ticket adalah full page navigation
    // (bukan history traversal), jadi tidak selalu di-restore dari bfcache.
    // Simpan state filter ke sessionStorage supaya tetap aktif walau halaman
    // di-load ulang dari awal saat user kembali ke list ini.
    const TICKET_FILTER_STORAGE_KEY = 'ticketIndexFilterState';

    function persistTicketFilterState() {
        try {
            const state = {
                currentFilter: currentFilter,
                currentTicketSort: currentTicketSort,
                colFilterCustomer: document.getElementById('colFilterCustomer')?.value || '',
                colFilterPic: document.getElementById('colFilterPic')?.value || '',
                colFilterPriority: document.getElementById('colFilterPriority')?.value || '',
                colFilterScale: document.getElementById('colFilterScale')?.value || '',
                colFilterStatus: document.getElementById('colFilterStatus')?.value || '',
                colFilterType: document.getElementById('colFilterType')?.value || '',
                colFilterModule: document.getElementById('colFilterModule')?.value || '',
                dateFilterFrom: document.getElementById('dateFilterFrom')?.value || '',
                dateFilterTo: document.getElementById('dateFilterTo')?.value || '',
                ticketFilterInput: document.getElementById('ticketFilterInput')?.value || '',
                descFilterInput: document.getElementById('descFilterInput')?.value || '',
            };
            sessionStorage.setItem(TICKET_FILTER_STORAGE_KEY, JSON.stringify(state));
        } catch (e) { /* sessionStorage unavailable (private mode, dsb) — abaikan */ }
    }

    function restoreTicketFilterState() {
        let state = null;
        try {
            const raw = sessionStorage.getItem(TICKET_FILTER_STORAGE_KEY);
            if (raw) state = JSON.parse(raw);
        } catch (e) { /* corrupt/unavailable — abaikan */ }
        if (!state) return;

        if (state.currentTicketSort && state.currentTicketSort.key) currentTicketSort = state.currentTicketSort;

        const setVal = (id, val) => {
            const el = document.getElementById(id);
            if (el && val) el.value = val;
        };
        setVal('dateFilterFrom', state.dateFilterFrom);
        setVal('dateFilterTo', state.dateFilterTo);
        setVal('ticketFilterInput', state.ticketFilterInput);
        setVal('descFilterInput', state.descFilterInput);

        // Single-select custom dropdowns — restore value + label sekaligus. Values are now
        // customer_id/employee_id (numeric) or '__unassigned__', not the name string older
        // sessionStorage entries (persisted before this endpoint switched to ID-based
        // filtering) may still contain — ignore anything that doesn't look like one of those,
        // instead of sending a non-numeric value to the server and silently getting zero results.
        const looksLikeIdOrUnassigned = v => v === '__unassigned__' || /^\d+$/.test(v);
        if (state.colFilterCustomer && looksLikeIdOrUnassigned(state.colFilterCustomer) && typeof setCustomDropdownValue === 'function') {
            setCustomDropdownValue('colFilterCustomer', state.colFilterCustomer);
        }
        if (state.colFilterPic && looksLikeIdOrUnassigned(state.colFilterPic) && typeof setCustomDropdownValue === 'function') {
            setCustomDropdownValue('colFilterPic', state.colFilterPic);
        }

        // Multi-select custom dropdowns — set value mentah lalu sync checkmark + label
        ['colFilterPriority', 'colFilterScale', 'colFilterStatus', 'colFilterType', 'colFilterModule'].forEach(id => {
            if (!state[id]) return;
            const hidden = document.getElementById(id);
            if (!hidden) return;
            hidden.value = state[id];
            const dd = hidden.closest('.custom-dd');
            if (dd && typeof _syncMultiVisualState === 'function') _syncMultiVisualState(dd);
        });

        // Status card (currentFilter) — pakai filterTickets() supaya highlight border-nya
        // ikut ter-update, bukan cuma variabelnya.
        if (state.currentFilter && typeof filterTickets === 'function') {
            filterTickets(state.currentFilter);
        }

        // Sinkronkan indikator "active" di dropdown kolom + terapkan filter ke data
        // (aman dipanggil walau allTickets masih kosong — akan diulang lagi oleh loadTickets()).
        if (typeof applyColFilter === 'function') applyColFilter();
    }

    document.addEventListener('DOMContentLoaded', async function() {
        if (typeof initCustomDropdowns === 'function') initCustomDropdowns();
        // Filter options must be populated before restoring persisted state — the Customer/PIC
        // dropdown values are now IDs, and setCustomDropdownValue() needs a matching item
        // already in the panel to resolve the ID back to a display name.
        await loadFilterOptions();
        restoreTicketFilterState();
        loadTickets();
        if (userRole === EC_ADMINISTRATOR_ROLE || userRole === DELIVERY_SUPPORT_USER_ROLE || STAFF_TOGGLE_ROLES.includes(userRole) || userRole === SUPPORT_MANAGER_ROLE || CAN_VIEW_UNASSIGNED_TICKET) updateViewToggle();
        startEmailPolling();
    });

    // Refresh list saat halaman dipulihkan dari bfcache (tombol Back browser)
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) loadTickets();
    });

    // -------------------------------------------------------------------------
    // Ticket polling: cek update tiket setiap 20 detik dari DB lokal (bukan Graph API)
    // Email inbox diproses server-side oleh scheduler (email:process-inbox tiap menit)
    // Kalau ada perubahan, langsung auto-refresh (silent, tanpa spinner/flash) — search,
    // filter, sort, dan posisi halaman yang sedang aktif tetap dipertahankan.
    // -------------------------------------------------------------------------
    let _lastTicketUpdate = null;
    let _isFirstPoll = true;
    const TICKET_POLL_INTERVAL_MS = 20000;

    async function checkTicketUpdates() {
        try {
            const res = await fetch('/ticket/latest-update', {
                headers: {
                    'Accept': 'application/json'
                },
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
                loadTickets(true);
            }
        } catch (err) {
            console.warn('[Ticket Polling] error:', err.message);
        }
    }

    function startEmailPolling() {
        checkTicketUpdates();
        setInterval(checkTicketUpdates, TICKET_POLL_INTERVAL_MS);
    }

    function exportWithFilters() {
        const params = new URLSearchParams();

        // Status dari card filter (klik status card)
        if (currentFilter && currentFilter !== 'all') {
            params.set('card_status', currentFilter);
        }
        // Filter kolom
        const colStatus = document.getElementById('colFilterStatus')?.value || '';
        const colCustomer = document.getElementById('colFilterCustomer')?.value || '';
        const colPic = document.getElementById('colFilterPic')?.value || '';
        const colPriority = document.getElementById('colFilterPriority')?.value || '';
        const colScale = document.getElementById('colFilterScale')?.value || '';
        const colType = document.getElementById('colFilterType')?.value || '';
        const colModule = document.getElementById('colFilterModule')?.value || '';
        if (colStatus) params.set('status', colStatus);
        if (colCustomer) params.set('customer_id', colCustomer);
        if (colPic) params.set('pic_id', colPic === '__unassigned__' ? 'unassigned' : colPic);
        if (colPriority) params.set('priority', colPriority);
        if (colScale) params.set('scale', colScale);
        if (colType) params.set('type', colType);
        if (colModule) params.set('module', colModule);

        // Date range
        const dateFrom = document.getElementById('dateFilterFrom')?.value || '';
        const dateTo = document.getElementById('dateFilterTo')?.value || '';
        if (dateFrom) params.set('date_from', dateFrom);
        if (dateTo) params.set('date_to', dateTo);

        // Keyword filters
        const descKw = (document.getElementById('descFilterInput')?.value || '').trim();
        const ticketKw = (document.getElementById('ticketFilterInput')?.value || '').trim();
        if (descKw) params.set('description', descKw);
        if (ticketKw) params.set('ticket_number', ticketKw);

        const qs = params.toString();
        window.location.href = '{{ route("ticket.export") }}' + (qs ? '?' + qs : '');
    }

    function toggleView(view) {
        currentView = view;
        updateViewToggle();
        // Helpdesk toggling All/Unassigned used to filter an already-fully-loaded client
        // array instead of re-fetching; now that the list is paginated there's no full
        // array to filter, so every role re-fetches from the server (loadTickets() passes
        // ?unassigned=1 for this case — see its endpoint-selection logic).
        if (STAFF_TOGGLE_ROLES.includes(userRole)) {
            currentFilter = 'all';
        }
        currentPage = 1;
        loadTickets();
        persistTicketFilterState();
    }

    function updateViewToggle() {
        if (userRole === EC_ADMINISTRATOR_ROLE || userRole === DELIVERY_SUPPORT_USER_ROLE || userRole === SUPPORT_MANAGER_ROLE) {
            const btnAll = document.getElementById('btnViewAll');
            const btnMy  = document.getElementById('btnViewMy');
            if (btnAll) btnAll.classList.toggle('active', currentView === 'all');
            if (btnMy)  btnMy.classList.toggle('active',  currentView === 'my');
        }
        if (STAFF_TOGGLE_ROLES.includes(userRole)) {
            const btnA = document.getElementById('btnViewAllHd');
            const btnU = document.getElementById('btnViewUnassigned');
            if (btnA) btnA.classList.toggle('active', currentView === 'all');
            if (btnU) btnU.classList.toggle('active', currentView === 'unassigned');
        }
        // Unassigned Ticket tab — visibility driven by 'ticket.unassigned' menu permission
        // (Role & Menu Access), independent of the hardcoded role toggles above.
        const btnUt = document.getElementById('btnViewUnassignedTab');
        if (btnUt) {
            btnUt.classList.toggle('active', currentView === 'unassigned-tab');
        }
        // Ticket Modul tab — visibility driven by 'ticket.module-lead' menu permission +
        // isModuleLead (server-computed), independent of the role toggles above.
        const btnMl = document.getElementById('btnViewModuleLead');
        if (btnMl) {
            btnMl.classList.toggle('active', currentView === 'module-lead');
        }
    }

    // Server-side pagination/filter/sort — loadTickets() fetches exactly one page that
    // already matches every active filter and sort, instead of fetching the entire ticket
    // set and filtering/sorting/paginating it in the browser. `allTickets`/`filteredTickets`
    // therefore hold only the current page from here on (names kept to minimize churn
    // elsewhere in this file). Called directly by every filter/sort/pagination control —
    // there's no separate client-side "apply filters to already-fetched data" step anymore.
    async function loadTickets(silent = false, _isRetry = false) {
        try {
            if (!silent) {
                document.getElementById('loadingState').classList.remove('hidden');
                document.getElementById('ticketsContainer').classList.add('hidden');
                document.getElementById('emptyState').classList.add('hidden');
            }

            let endpoint = '/api/tickets';
            const params = new URLSearchParams();
            if (userRole === EC_USER_ROLE) endpoint = '/api/tickets/my';
            else if (IS_EXTERNAL_EMPLOYEE && userRole !== SUPPORT_MANAGER_ROLE && !HEAD_ROLES.includes(userRole)) endpoint = '/api/tickets/my';
            else if (currentView === 'unassigned-tab') endpoint = '/api/tickets/unassigned';
            else if ((userRole === EC_ADMINISTRATOR_ROLE || userRole === DELIVERY_SUPPORT_USER_ROLE) && currentView === 'my') endpoint = '/api/tickets/my';
            else if (userRole === SUPPORT_MANAGER_ROLE && currentView === 'my') endpoint = '/api/tickets/my';
            else if (STAFF_TOGGLE_ROLES.includes(userRole) && currentView === 'unassigned') params.set('unassigned', '1');
            if (currentView === 'module-lead') params.set('module_team', '1');

            params.set('page', currentPage);
            params.set('per_page', itemsPerPage);
            params.set('sort_key', currentTicketSort.key);
            params.set('sort_dir', currentTicketSort.dir);
            if (currentFilter && currentFilter !== 'all') params.set('card_status', currentFilter);

            const colCustomer = document.getElementById('colFilterCustomer')?.value || '';
            const colPic = document.getElementById('colFilterPic')?.value || '';
            const colPriority = document.getElementById('colFilterPriority')?.value || '';
            const colScale = document.getElementById('colFilterScale')?.value || '';
            const colStatus = document.getElementById('colFilterStatus')?.value || '';
            const colType = document.getElementById('colFilterType')?.value || '';
            if (colCustomer) params.set('customer_id', colCustomer);
            if (colPic) params.set('pic_id', colPic === '__unassigned__' ? 'unassigned' : colPic);
            if (colPriority) params.set('priority', colPriority);
            if (colScale) params.set('scale', colScale);
            if (colStatus) params.set('status', colStatus);
            if (colType) params.set('type', colType);

            const dateFrom = document.getElementById('dateFilterFrom')?.value || '';
            const dateTo = document.getElementById('dateFilterTo')?.value || '';
            if (dateFrom) params.set('date_from', dateFrom);
            if (dateTo) params.set('date_to', dateTo);

            const descKw = (document.getElementById('descFilterInput')?.value || '').trim();
            const ticketKw = (document.getElementById('ticketFilterInput')?.value || '').trim();
            if (descKw) params.set('description', descKw);
            if (ticketKw) params.set('ticket_number', ticketKw);

            const response = await fetch(endpoint + '?' + params.toString(), {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });

            // Akun tanpa hak akses ke modul ticket (mis. role Project Admin) → tampilkan
            // state khusus, bukan "Failed to load tickets" yang membingungkan.
            if (response.status === 403 || response.status === 401) {
                let msg = '';
                try {
                    msg = (await response.json()).message || '';
                } catch (e) {}
                showAccessDenied(response.status === 401 ?
                    'Your session has expired. Please sign in again to view tickets.' :
                    (msg && msg !== 'Access denied' ? msg :
                        "Your account role isn't permitted to view the Ticket module. Please sign in with an account that has ticket access (e.g. Admin, Helpdesk, or RPMO)."));
                return;
            }

            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) throw new Error('Non-JSON response');

            const data = await response.json();

            if (data.success) {
                // Filters shrank the result set below the page we were on (e.g. searched
                // while on page 3) — snap back to the last valid page instead of showing blank.
                if (!_isRetry && data.meta && data.meta.last_page >= 1 && currentPage > data.meta.last_page) {
                    currentPage = data.meta.last_page;
                    return loadTickets(silent, true);
                }

                allTickets = data.data;
                filteredTickets = allTickets;
                totalItems = data.meta ? data.meta.total : allTickets.length;
                totalPages = data.meta ? data.meta.last_page : 1;
                currentStats = data.stats || null;

                updateColFilterIndicators();
                updateDateFilterIndicator();
                updateDescFilterIndicator();
                updateTicketFilterIndicator();
                updateStats();
                renderTickets();
                persistTicketFilterState();
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

    // Angka di kartu status ("Open", "Closed", dst) mengikuti filter kolom yang sedang
    // aktif (customer, PIC, priority, scale, type, date, keyword) — TAPI sengaja tidak
    // ikut memfilter berdasarkan kartu status itu sendiri (currentFilter), supaya klik
    // kartu "Closed" tidak membuat kartu lain menghitung ulang dari subset "closed" saja
    // (yang akan membuat semuanya jadi 0 kecuali Closed). Base ini dipakai bareng oleh
    // applyAdvancedFilters() untuk membangun `filteredTickets` tabel.
    // Badge counts now come from the server (`stats` in loadTickets()'s response, honoring
    // the same column filters/search as the list itself but not the card_status filter —
    // same semantics as the old client-side version, just computed server-side over the
    // whole filtered set instead of only whatever page happened to be loaded).
    function updateStats() {
        const s = currentStats || {};
        document.getElementById('totalCount').textContent = s.total ?? 0;
        document.getElementById('openCount').textContent = s.open ?? 0;
        document.getElementById('inprocessCount').textContent = s.inprocess ?? 0;
        document.getElementById('waitingCustomerCount').textContent = s.waiting_on_customer ?? 0;
        document.getElementById('waiting3rdCount').textContent = s.waiting_on_3rd_party ?? 0;
        document.getElementById('waitingConfirmCount').textContent = s.waiting_to_confirmation ?? 0;
        document.getElementById('holdCount').textContent = s.hold ?? 0;
        document.getElementById('cancelledCount').textContent = s.cancelled ?? 0;
        document.getElementById('closedCount').textContent = s.closed ?? 0;
    }

    // filteredTickets is now exactly one page, already filtered+sorted by the server —
    // no client-side slicing or sorting needed here anymore. totalItems/totalPages come
    // from loadTickets()'s response meta (set before this is called), not recomputed from
    // filteredTickets.length (which would just be the current page's count).
    function renderTickets() {
        const listBody = document.getElementById('ticketsListBody');
        const container = document.getElementById('ticketsContainer');

        document.getElementById('loadingState').classList.add('hidden');
        document.getElementById('emptyState').classList.add('hidden');
        container.classList.remove('hidden');

        if (filteredTickets.length === 0) {
            // Kosongkan hanya baris data; header, toolbar, dan popup search/filter tetap tampil.
            const colCount = container.querySelectorAll('thead th').length;
            listBody.innerHTML = `
                <tr>
                    <td colspan="${colCount}" class="px-4 py-16 text-center">
                        <div class="w-16 h-16 rounded-2xl bg-gray-100 flex items-center justify-center mb-4 mx-auto">
                            <i class="fas fa-search text-gray-300 text-2xl"></i>
                        </div>
                        <p class="text-gray-700 font-semibold mb-1">No tickets found</p>
                        <p class="text-gray-400 text-xs mb-5">Try adjusting your filters or search terms</p>
                        <button onclick="resetFilters()"
                            class="inline-flex items-center gap-1.5 px-4 py-2 primary-gradient text-white text-xs font-semibold rounded-xl hover:opacity-90 transition-all shadow-sm">
                            <i class="fas fa-times text-xs"></i>Clear Filters
                        </button>
                    </td>
                </tr>`;
            updatePaginationDisplay();
            return;
        }

        listBody.innerHTML = filteredTickets.map(ticket => createTicketRow(ticket)).join('');
        updatePaginationDisplay();
    }

    function relativeTime(date) {
        const now = new Date();
        const diffMs = now - date;
        const diffSec = Math.floor(diffMs / 1000);
        const diffMin = Math.floor(diffSec / 60);
        const diffHr = Math.floor(diffMin / 60);
        const diffDay = Math.floor(diffHr / 24);
        const diffWk = Math.floor(diffDay / 7);
        const diffMo = Math.floor(diffDay / 30);
        const diffYr = Math.floor(diffDay / 365);

        if (diffSec < 60) return 'Just now';
        if (diffMin === 1) return '1 minute ago';
        if (diffMin < 60) return `${diffMin} minutes ago`;
        if (diffHr === 1) return '1 hour ago';
        if (diffHr < 24) return `${diffHr} hours ago`;
        if (diffDay === 1) return 'Yesterday';
        if (diffDay < 7) return `${diffDay} days ago`;
        if (diffWk === 1) return '1 week ago';
        if (diffWk < 5) return `${diffWk} weeks ago`;
        if (diffMo === 1) return '1 month ago';
        if (diffMo < 12) return `${diffMo} months ago`;
        if (diffYr === 1) return '1 year ago';
        return `${diffYr} years ago`;
    }

    // Ticket closed → freeze "Day on Close" at the close timestamp instead of
    // letting it keep counting up to today. Dihitung sejak created_at,
    // sama seperti kolom Date.
    function dayOnCloseValue(ticket) {
        const start = ticket.created_at;
        if (!start) return null;
        const closedAt = ticket.status === 'closed'
            ? (ticket.sla?.resolved_at || ticket.updated_at)
            : null;
        const end = closedAt ? new Date(closedAt) : new Date();
        return Math.max(0, Math.ceil((end.getTime() - new Date(start).getTime()) / 86400000));
    }

    function createTicketRow(ticket) {
        const customerName = ticket.customer?.customer_name || 'Unknown';
        const lastActivity = new Date(ticket.last_message_at || ticket.created_at);
        const createdDate = new Date(ticket.created_at);
        // ticket.end_date is now set when a ticket's status changes to closed (see
        // TicketController::updateTicketStatus). Tickets closed before that fix never got
        // it set, so fall back to the same source "Day on Close" uses (resolved_at, then
        // updated_at) for those older rows.
        const closedAtRaw = ticket.end_date || (ticket.status === 'closed' ? (ticket.sla?.resolved_at || ticket.updated_at) : null);
        const endDate = closedAtRaw ? new Date(closedAtRaw) : null;

        const fmt = d => d.toLocaleDateString('en-GB', {
            timeZone: 'Asia/Jakarta',
            day: '2-digit',
            month: 'short',
            year: 'numeric'
        });
        const fmtDT = d => d.toLocaleString('en-GB', {
            timeZone: 'Asia/Jakarta',
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
        });

        const lastUpdateStr = relativeTime(lastActivity);
        const lastUpdateTitle = fmtDT(lastActivity);
        const dateStr = fmt(createdDate);
        const endDateStr = endDate ? fmt(endDate) : '—';

        const agentName = ticket.employee?.employee_name || '<span class="text-gray-400">Unassigned</span>';

        const priorityColors = {
            'Very High': 'bg-red-50 text-red-700',
            'High': 'bg-orange-50 text-orange-700',
            'Medium': 'bg-yellow-50 text-yellow-700',
            'Low': 'bg-blue-50 text-blue-700'
        };
        const priorityClass = priorityColors[ticket.ticket_priority] || 'bg-gray-100 text-gray-500';
        const priorityLabel = ticket.ticket_priority || '—';

        const statusMap = {
            'open': {
                label: 'Open',
                cls: 'bg-blue-50 text-blue-700'
            },
            'inprocess': {
                label: 'Inprocess',
                cls: 'bg-yellow-50 text-yellow-700'
            },
            'waiting_on_customer': {
                label: 'Waiting on Customer',
                cls: 'bg-amber-50 text-amber-700'
            },
            'waiting_on_3rd_party': {
                label: 'Waiting on 3rd Party',
                cls: 'bg-indigo-50 text-indigo-700'
            },
            'waiting_to_confirmation': {
                label: 'Waiting to Confirmation',
                cls: 'bg-teal-50 text-teal-700'
            },
            'hold': {
                label: 'Hold',
                cls: 'bg-orange-50 text-orange-700'
            },
            'cancelled': {
                label: 'Cancelled',
                cls: 'bg-gray-100 text-gray-500'
            },
            'closed': {
                label: 'Closed',
                cls: 'bg-green-50 text-green-700'
            },
        };
        const typeColors = {
            'Incident': 'bg-red-50 text-red-600',
            'Change Request': 'bg-amber-50 text-amber-600',
            'Service Request': 'bg-indigo-50 text-indigo-600',
            'EWA': 'bg-orange-50 text-orange-600',
            'RISE': 'bg-violet-50 text-violet-600',
            'Consult': 'bg-teal-50 text-teal-600',
            'Internal': 'bg-slate-100 text-slate-600',
        };

        const scaleColors = {
            'Simple': 'bg-sky-50 text-sky-600',
            'Medium': 'bg-amber-50 text-amber-600',
            'Complex': 'bg-rose-50 text-rose-600',
        };

        const sInfo = statusMap[ticket.status] || {
            label: ticket.status || '—',
            cls: 'bg-gray-100 text-gray-500'
        };
        const typeLabel = ticket.ticket_type || '—';
        const typeCls = typeColors[ticket.ticket_type] || 'bg-gray-100 text-gray-500';

        const mandays = ticket.customer_mandays != null ? parseFloat(ticket.customer_mandays).toFixed(1) : '—';

        // ── Priority dots ──
        const prioDots = {
            'Very High': 'bg-red-500',
            'High': 'bg-orange-500',
            'Medium': 'bg-yellow-500',
            'Low': 'bg-blue-400'
        };

        // ── Status dots ──
        const statusDots = {
            'open': 'bg-blue-500',
            'inprocess': 'bg-yellow-500',
            'waiting_on_customer': 'bg-amber-500',
            'waiting_on_3rd_party': 'bg-indigo-500',
            'waiting_to_confirmation': 'bg-teal-500',
            'hold': 'bg-orange-500',
            'cancelled': 'bg-gray-400',
            'closed': 'bg-green-500',
        };

        // ── Unread detection ──
        const lastCustomer = ticket.last_customer_reply_at ? new Date(ticket.last_customer_reply_at) : null;
        const lastInternal = ticket.last_internal_note_at ? new Date(ticket.last_internal_note_at) : null;
        const lastAgent = ticket.last_agent_reply_at ? new Date(ticket.last_agent_reply_at) : null;
        const lastNoteSender = ticket.last_internal_note_sender_id;

        const hasUnreadCustomer = lastCustomer && (!lastAgent || lastCustomer > lastAgent);
        const hasUnreadInternal = lastInternal && (Number(lastNoteSender) !== currentEmployeeId);

        // Kalau dua-duanya aktif, menangkan yang waktunya paling baru — bukan selalu
        // internal note yang menang (perilaku lama), supaya warna mengikuti update terakhir.
        const customerTs = hasUnreadCustomer ? lastCustomer.getTime() : -Infinity;
        const internalTs = hasUnreadInternal ? lastInternal.getTime() : -Infinity;

        let unreadCls = '',
            dot = '',
            timeColor = 'text-gray-400',
            numColor = 'text-gray-700';
        if (internalTs >= customerTs && hasUnreadInternal) {
            unreadCls = 'ticket-unread-internal';
            dot = '<span class="unread-dot unread-dot-yellow" title="Ada internal note belum dibalas"></span>';
            timeColor = 'text-amber-600 font-semibold';
            numColor = 'text-amber-700';
        } else if (hasUnreadCustomer) {
            unreadCls = 'ticket-unread-customer';
            dot = '<span class="unread-dot unread-dot-blue" title="Customer belum dibalas"></span>';
            timeColor = 'text-blue-600 font-semibold';
            numColor = 'text-blue-700';
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
        const rowBg = unreadCls === 'ticket-unread-internal' ? '#fffbeb' :
            unreadCls === 'ticket-unread-customer' ? '#f0f7ff' :
            '#ffffff';

        return `<tr class="${unreadCls} border-b border-gray-100" data-ticket-num="${(ticket.ticket_number||'').replace(/"/g,'')}" onclick="window.location='/ticket/${ticket.ticket_id}'" oncontextmenu="openTicketContextMenu(event,${ticket.ticket_id},this)">
            @if($canAiSummarize)
            {{-- AI Summarize --}}
            <td class="px-2 py-3 whitespace-nowrap sticky left-0 text-center" style="background:${rowBg}">
                <button type="button" title="AI Summarize"
                    onclick="event.stopPropagation(); openTicketSummary(${ticket.ticket_id}, '${(ticket.ticket_number || '').replace(/'/g, '')}')"
                    class="p-1.5 rounded-lg text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                        <path d="M10 1.5l1.6 4.2 4.4 1.3-4.4 1.3L10 12.5 8.4 8.3 4 7l4.4-1.3L10 1.5zM15.5 12l.9 2.3 2.6.7-2.6.7-.9 2.3-.9-2.3-2.6-.7 2.6-.7.9-2.3zM4.5 11l.7 1.8 2 .5-2 .5-.7 1.8-.7-1.8-2-.5 2-.5.7-1.8z" />
                    </svg>
                </button>
            </td>
            @endif
            {{-- Last Update --}}
            <td class="px-3 py-3 whitespace-nowrap sticky" style="left:{{ $aiColW }}px;background:${rowBg}" title="${lastUpdateTitle}">
                <div class="flex items-center gap-1.5">
                    ${dot}
                    <span class="text-xs ${timeColor}">${lastUpdateStr}</span>
                </div>
            </td>
            {{-- Ticket # --}}
            <td class="px-3 py-3 whitespace-nowrap sticky border-r border-gray-100" style="left:{{ 110 + $aiColW }}px;background:${rowBg}">
                <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-gray-100 font-mono text-xs font-bold ${numColor}">${ticket.ticket_number || '—'}</span>
            </td>
            {{-- Description --}}
            <td class="px-3 py-3 text-sm" style="min-width:260px;max-width:320px;">
                <span class="block truncate text-gray-700 ${ticket.is_read ? 'font-normal' : 'font-bold'} leading-snug"
                      title="${(ticket.description||'').replace(/"/g,'&quot;')}">${ticket.description || '—'}</span>
            </td>
            {{-- Start Date --}}
            <td class="px-3 py-3 whitespace-nowrap">
                <span class="text-xs text-gray-500">${dateStr}</span>
            </td>
            {{-- Close Date --}}
            <td class="px-3 py-3 whitespace-nowrap">
                <span class="text-xs text-gray-500">${endDateStr}</span>
            </td>
            {{-- Day on Close --}}
            <td class="px-3 py-3 whitespace-nowrap">
                ${(function() {
                    if (ticket.status === 'closed') {
                        return '<span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold bg-green-50 text-green-700">Closed</span>';
                    }
                    const days = dayOnCloseValue(ticket);
                    if (days === null) return '<span class="text-gray-300 text-xs">—</span>';
                    return `<span class="text-sm font-semibold text-gray-700">${days}</span>`;
                })()}
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
            {{-- Module --}}
            ${cell(ticket.module ? `<span class="text-sm text-gray-700">${ticket.module}</span>` : '<span class="text-gray-300 text-xs">—</span>')}
            {{-- Assign Delivery --}}
            <td class="px-3 py-3 whitespace-nowrap">
                ${ticket.delivery?.delivery_name
                    ? `<span class="text-xs font-semibold text-gray-700" title="${(ticket.delivery.delivery_label||ticket.delivery.delivery_name).replace(/"/g,'&quot;')}">${ticket.delivery.delivery_name}</span>
                       ${ticket.delivery.client_name ? `<span class="block text-[11px] text-gray-400 mt-0.5">${ticket.delivery.client_name}</span>` : ''}`
                    : `<span class="text-xs text-gray-300 italic">Unassigned</span>`}
            </td>
            {{-- Customer Mandays --}}
            ${cell(mandays !== '—' ? `<span class="font-semibold text-gray-700">${mandays}</span>` : '<span class="text-gray-300 text-xs">—</span>')}
            {{-- Activity Date: latest activity date logged across this ticket's support timesheets --}}
            ${cell(ticket.activity_date ? fmt(new Date(ticket.activity_date + 'T00:00:00')) : '<span class="text-gray-300 text-xs">—</span>')}
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
            ${CAN_HIDE_TICKET ? `
            <td class="px-3 py-3 whitespace-nowrap text-center" onclick="event.stopPropagation()">
                <button onclick="hideTicketFromList(${ticket.ticket_id}, event)"
                    title="Sembunyikan tiket ini"
                    class="inline-flex items-center gap-1 px-2 py-1 rounded-lg border border-gray-200 hover:border-orange-300 hover:bg-orange-50 text-gray-400 hover:text-orange-600 transition text-[10px] font-medium">
                    <i class="fas fa-eye-slash text-xs"></i><span>Hide</span>
                </button>
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

    function previousPage() {
        if (currentPage > 1) {
            currentPage--;
            loadTickets();
        }
    }

    function nextPage() {
        if (currentPage < totalPages) {
            currentPage++;
            loadTickets();
        }
    }

    function filterTickets(status) {
        currentFilter = status;
        ['filterAll', 'filterOpen', 'filterInprocess', 'filterWaitingCustomer', 'filterWaiting3rd', 'filterWaitingConfirm', 'filterHold', 'filterCancelled', 'filterClosed'].forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            el.classList.remove('active-filter', 'border-red-600', 'shadow-md', 'border-2');
            el.classList.add('border-gray-200', 'border');
        });

        const filterMap = {
            'all': 'filterAll',
            'open': 'filterOpen',
            'inprocess': 'filterInprocess',
            'waiting_on_customer': 'filterWaitingCustomer',
            'waiting_on_3rd_party': 'filterWaiting3rd',
            'waiting_to_confirmation': 'filterWaitingConfirm',
            'hold': 'filterHold',
            'cancelled': 'filterCancelled',
            'closed': 'filterClosed',
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

    // Customer/PIC filter options — fetched once from /api/tickets/filter-options (distinct
    // values across every visible ticket) instead of scanned out of allTickets, which after
    // pagination only ever holds the current page. Values are now customer_id/employee_id
    // (sent to the server as customer_id/pic_id), not the name string the old client-side
    // filter used to compare against.
    async function loadFilterOptions() {
        try {
            const res = await fetch('/api/tickets/filter-options', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            });
            if (!res.ok) return;
            const data = await res.json();
            if (data.success) {
                populateCustomerFilter(data.customers || []);
                populatePicFilter(data.pics || []);
            }
        } catch (e) {
            console.warn('[Filter Options] error:', e.message);
        }
    }

    function populateCustomerFilter(customers) {
        const ddEl = document.getElementById('ddColFilterCustomer');
        if (!ddEl) return;
        // Panel may be detached to document.body (fixed mode) — use stored ref
        const panel = ddEl._ddPanel || ddEl.querySelector('.custom-dd-panel');
        if (!panel) return;

        // Remove existing items only (preserve injected search wrap + empty state)
        panel.querySelectorAll('.custom-dd-item').forEach(el => el.remove());

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
        customers.forEach(c => fragment.appendChild(makeItem(String(c.id), c.name)));

        // Insert before empty-state div if present, otherwise append
        const emptyEl = panel._ddEmpty || null;
        if (emptyEl) panel.insertBefore(fragment, emptyEl);
        else panel.appendChild(fragment);
    }

    function populatePicFilter(pics) {
        const ddEl = document.getElementById('ddColFilterPic');
        if (!ddEl) return;
        const panel = ddEl._ddPanel || ddEl.querySelector('.custom-dd-panel');
        if (!panel) return;

        panel.querySelectorAll('.custom-dd-item').forEach(el => el.remove());

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
        fragment.appendChild(makeItem('__unassigned__', 'Unassigned'));
        pics.forEach(p => fragment.appendChild(makeItem(String(p.id), p.name)));

        const emptyEl = panel._ddEmpty || null;
        if (emptyEl) panel.insertBefore(fragment, emptyEl);
        else panel.appendChild(fragment);
    }

    function applyColFilter() {
        const colDdMap = {
            'ddColFilterCustomer': 'colFilterCustomer',
            'ddColFilterPic': 'colFilterPic',
            'ddColFilterPriority': 'colFilterPriority',
            'ddColFilterScale': 'colFilterScale',
            'ddColFilterStatus': 'colFilterStatus',
            'ddColFilterType': 'colFilterType',
            'ddColFilterModule': 'colFilterModule',
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

    // Entry point for every filter control (column dropdowns, date range, keyword search,
    // status cards). Filtering now happens server-side, so this just resets to page 1 and
    // re-fetches — indicator/stats/render updates happen inside loadTickets()'s response
    // handler once the new data is back.
    function applyAdvancedFilters() {
        currentPage = 1;
        loadTickets();
    }

    // ── Date Range Filter ─────────────────────────────────────────────
    function toggleDateFilter(ev) {
        ev?.stopPropagation();
        const panel = document.getElementById('dateFilterPanel');
        const btn = document.getElementById('dateFilterBtn');
        const open = !panel.classList.contains('hidden');
        // close other popovers
        closeDescFilter();
        closeTicketFilter();
        if (open) {
            panel.classList.add('hidden');
            return;
        }
        positionPanelUnder(btn, panel);
        panel.classList.remove('hidden');
    }

    function applyDateFilter() {
        const from = document.getElementById('dateFilterFrom').value;
        const to = document.getElementById('dateFilterTo').value;
        const errEl = document.getElementById('dateFilterError');
        if (from && to && to < from) {
            errEl.classList.remove('hidden');
            return;
        }
        errEl.classList.add('hidden');
        document.getElementById('dateFilterPanel').classList.add('hidden');
        applyAdvancedFilters();
    }

    function clearDateFilter() {
        document.getElementById('dateFilterFrom').value = '';
        document.getElementById('dateFilterTo').value = '';
        document.getElementById('dateFilterError').classList.add('hidden');
        if (currentTicketSort.key === 'date') {
            currentTicketSort = {
                key: 'last_update',
                dir: 'desc'
            };
            updateTicketSortIcons();
        }
        applyAdvancedFilters();
    }

    function updateDateFilterIndicator() {
        const from = document.getElementById('dateFilterFrom')?.value || '';
        const to = document.getElementById('dateFilterTo')?.value || '';
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
        const btn = document.getElementById('descFilterBtn');
        const open = !panel.classList.contains('hidden');
        // close other popovers
        document.getElementById('dateFilterPanel')?.classList.add('hidden');
        document.getElementById('ticketFilterPanel')?.classList.add('hidden');
        if (open) {
            panel.classList.add('hidden');
            return;
        }
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
        const kw = (document.getElementById('descFilterInput')?.value || '').trim();
        const icon = document.getElementById('descFilterIcon');
        if (icon) icon.classList.toggle('text-red-500', kw !== '');
        if (icon) icon.classList.toggle('text-gray-300', kw === '');
    }

    // ── Ticket Number Keyword Filter (debounced) ──────────────────────────
    let _ticketFilterTimer = null;

    function toggleTicketFilter(ev) {
        ev?.stopPropagation();
        const panel = document.getElementById('ticketFilterPanel');
        const btn = document.getElementById('ticketFilterBtn');
        const open = !panel.classList.contains('hidden');
        document.getElementById('dateFilterPanel')?.classList.add('hidden');
        document.getElementById('descFilterPanel')?.classList.add('hidden');
        if (open) {
            panel.classList.add('hidden');
            return;
        }
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
        if (currentTicketSort.key === 'ticket_number') {
            currentTicketSort = {
                key: 'last_update',
                dir: 'desc'
            };
            updateTicketSortIcons();
        }
        applyAdvancedFilters();
    }

    function updateTicketFilterIndicator() {
        const kw = (document.getElementById('ticketFilterInput')?.value || '').trim();
        const icon = document.getElementById('ticketFilterIcon');
        if (icon) icon.classList.toggle('text-red-500', kw !== '');
        if (icon) icon.classList.toggle('text-gray-300', kw === '');
    }

    // ── Day on Close Sort Filter ───────────────────────────────────────
    function toggleDayOnCloseFilter(ev) {
        ev?.stopPropagation();
        const panel = document.getElementById('dayOnCloseFilterPanel');
        const btn = document.getElementById('dayOnCloseFilterBtn');
        const open = !panel.classList.contains('hidden');
        document.getElementById('dateFilterPanel')?.classList.add('hidden');
        closeDescFilter();
        closeTicketFilter();
        if (open) {
            panel.classList.add('hidden');
            return;
        }
        if (panel.parentElement !== document.body) document.body.appendChild(panel);
        positionPanelUnder(btn, panel);
        panel.classList.remove('hidden');
    }

    function closeDayOnCloseFilter() {
        document.getElementById('dayOnCloseFilterPanel')?.classList.add('hidden');
    }

    function clearDayOnCloseFilter() {
        if (currentTicketSort.key === 'day_on_close') {
            currentTicketSort = {
                key: 'last_update',
                dir: 'desc'
            };
            updateTicketSortIcons();
            renderTickets();
        }
        closeDayOnCloseFilter();
    }

    // Position floating panel right under the column header button (handles overflow:auto)
    function positionPanelUnder(btn, panel) {
        const rect = btn.getBoundingClientRect();
        panel.style.position = 'fixed';
        panel.style.top = (rect.bottom + 4) + 'px';
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
        const cp = document.getElementById('dayOnCloseFilterPanel');
        const cb = document.getElementById('dayOnCloseFilterBtn');
        if (cp && !cp.classList.contains('hidden') && !cp.contains(e.target) && !cb.contains(e.target)) cp.classList.add('hidden');
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            document.getElementById('dateFilterPanel')?.classList.add('hidden');
            document.getElementById('descFilterPanel')?.classList.add('hidden');
            document.getElementById('ticketFilterPanel')?.classList.add('hidden');
            document.getElementById('dayOnCloseFilterPanel')?.classList.add('hidden');
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
        document.getElementById('dayOnCloseFilterPanel')?.classList.add('hidden');
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
        const colFilterIds = ['colFilterCustomer', 'colFilterPic'];
        const colFilterMultiIds = ['colFilterPriority', 'colFilterScale', 'colFilterStatus', 'colFilterType', 'colFilterModule'];
        const colDdIds = ['ddColFilterCustomer', 'ddColFilterPic', 'ddColFilterPriority', 'ddColFilterScale', 'ddColFilterStatus', 'ddColFilterType', 'ddColFilterModule'];
        if (typeof setCustomDropdownValue === 'function') {
            colFilterIds.forEach(id => setCustomDropdownValue(id, ''));
        } else {
            colFilterIds.forEach(id => {
                const el = document.getElementById(id);
                if (el) el.value = '';
            });
        }
        if (typeof clearCustomDropdownMulti === 'function') {
            colFilterMultiIds.forEach(id => clearCustomDropdownMulti(id));
        } else {
            colFilterMultiIds.forEach(id => {
                const el = document.getElementById(id);
                if (el) el.value = '';
            });
        }
        colDdIds.forEach(id => updateColFilterActive(id, ''));

        // Clear date range + description keyword
        const dFrom = document.getElementById('dateFilterFrom');
        if (dFrom) dFrom.value = '';
        const dTo = document.getElementById('dateFilterTo');
        if (dTo) dTo.value = '';
        const dErr = document.getElementById('dateFilterError');
        if (dErr) dErr.classList.add('hidden');
        const desc = document.getElementById('descFilterInput');
        if (desc) desc.value = '';
        const ticketF = document.getElementById('ticketFilterInput');
        if (ticketF) ticketF.value = '';

        currentTicketSort = {
            key: 'last_update',
            dir: 'desc'
        };
        updateTicketSortIcons();

        currentFilter = 'all';
        filterTickets('all');
    }

    // ── Column Filter Indicators & Customer Populate ──────────────────
    const COL_FILTER_MAP = {
        customer: 'colFilterCustomer',
        pic: 'colFilterPic',
        priority: 'colFilterPriority',
        scale: 'colFilterScale',
        status: 'colFilterStatus',
        type: 'colFilterType',
        module: 'colFilterModule',
    };

    function updateColFilterIndicators() {
        Object.entries(COL_FILTER_MAP).forEach(([col, inputId]) => {
            const val = document.getElementById(inputId)?.value || '';
            const icon = document.getElementById(`col-filter-icon-${col}`);
            if (!icon) return;
            icon.classList.toggle('text-red-500', val !== '');
            icon.classList.toggle('text-gray-300', val === '');
        });
    }

    // ── Column Sort ────────────────────────────────────────────────────
    const TICKET_SORT_KEYS = ['last_update', 'ticket_number', 'description', 'date', 'day_on_close', 'customer', 'priority', 'scale', 'status', 'type'];
    const PRIORITY_RANK = {
        'Very High': 4,
        'High': 3,
        'Medium': 2,
        'Low': 1
    };
    const SCALE_RANK = {
        'Complex': 3,
        'Medium': 2,
        'Simple': 1
    };

    // Sorting is server-side now (TicketController::applyTicketListSort() — has to be, so
    // it applies across the whole filtered set instead of just whatever page is loaded).
    // This just updates the sort state and re-fetches; there's no client-side comparator
    // anymore (dayOnCloseValue() below is kept — it's still used to *display* the column,
    // just no longer to sort it).
    function sortTickets(key, forcedDir) {
        if (forcedDir) {
            currentTicketSort = {
                key,
                dir: forcedDir
            };
        } else if (currentTicketSort.key === key) {
            currentTicketSort.dir = currentTicketSort.dir === 'asc' ? 'desc' : 'asc';
        } else {
            currentTicketSort = {
                key,
                dir: key === 'last_update' ? 'desc' : 'asc'
            };
        }
        updateTicketSortIcons();
        loadTickets();
        persistTicketFilterState();
    }

    function updateTicketSortIcons() {
        // Update ⇅ indicator next to column label
        TICKET_SORT_KEYS.forEach(k => {
            const el = document.getElementById(`sort-icon-${k}`);
            if (!el) return;
            if (k === currentTicketSort.key) {
                el.textContent = currentTicketSort.dir === 'asc' ? '↑' : '↓';
                el.className = 'sort-icon text-red-500 font-bold';
            } else {
                el.textContent = '⇅';
                el.className = 'sort-icon text-gray-300';
            }
        });

        // Update Ascending/Descending button colors inside panels
        const SORT_BTN_KEYS = ['ticket_number', 'description', 'date', 'day_on_close'];
        const activeBase = 'sort-dir-btn flex-1 flex items-center justify-center gap-1 px-3 py-1.5 text-xs font-semibold border rounded-md transition-colors';
        const activeCls = 'bg-red-50 border-red-300 text-red-700';
        const inactiveCls = 'text-gray-600 border-gray-200 hover:bg-gray-50';

        SORT_BTN_KEYS.forEach(k => {
            ['asc', 'desc'].forEach(dir => {
                const btn = document.getElementById(`sort-btn-${k}-${dir}`);
                if (!btn) return;
                const isActive = currentTicketSort.key === k && currentTicketSort.dir === dir;
                btn.className = `${activeBase} ${isActive ? activeCls : inactiveCls}`;
            });
        });
    }

    function formatTimeAgo(date) {
        const tz = 'Asia/Jakarta';
        const now = new Date();
        const toDay = (d) => new Date(d.toLocaleDateString('en-CA', {
            timeZone: tz
        }));
        const todayDate = toDay(now);
        const targetDate = toDay(date);
        const diffDays = Math.round((todayDate - targetDate) / 86400000);

        if (diffDays === 0) {
            return date.toLocaleTimeString('id-ID', {
                timeZone: tz,
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            });
        }
        if (diffDays === 1) return 'Yesterday';
        if (diffDays < 7) {
            return date.toLocaleDateString('en-GB', {
                timeZone: tz,
                weekday: 'short'
            });
        }
        if (date.getFullYear() === now.getFullYear()) {
            return date.toLocaleDateString('en-GB', {
                timeZone: tz,
                day: '2-digit',
                month: 'short'
            });
        }
        return date.toLocaleDateString('en-GB', {
            timeZone: tz,
            day: '2-digit',
            month: 'short',
            year: 'numeric'
        });
    }


    // ==================== HELPDESK: CREATE TICKET ====================
    // ==================== CREATE TICKET ====================
    let adminQuillEditor = null;

    function initAdminQuill() {
        if (adminQuillEditor) return;
        adminQuillEditor = new Quill('#adminBodyEditor', {
            theme: 'snow',
            placeholder: 'Write an initial message... (optional)',
            modules: {
                toolbar: [
                    [{
                        header: [1, 2, false]
                    }],
                    ['bold', 'italic', 'underline'],
                    [{
                        list: 'ordered'
                    }, {
                        list: 'bullet'
                    }],
                    ['link', 'image'],
                    ['clean'],
                ]
            },
        });
        adminQuillEditor.root.addEventListener('paste', function(e) {
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
        const toEl = document.getElementById('newToEmail');
        if (toEl) toEl.value = '';
        const ccEl = document.getElementById('newCcEmails');
        if (ccEl) ccEl.value = '';
        document.getElementById('adminCreateError').classList.add('hidden');
        const btn = document.getElementById('btnCreateTicket');
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-plus text-xs"></i>Create Ticket';
        }
        if (adminQuillEditor) {
            adminQuillEditor.setContents([]);
        }
        const att = document.getElementById('newAttachments');
        if (att) att.value = '';
    }

    async function submitCreateTicket(e) {
        e.preventDefault();
        const btn = document.getElementById('btnCreateTicket');
        const errEl = document.getElementById('adminCreateError');
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
        form.append('description', document.getElementById('newDescription').value);
        form.append('ticket_priority', document.getElementById('newPriority').value);
        form.append('customer_id', document.getElementById('newCustomerId').value);
        form.append('ticket_type', ticketTypeVal);
        form.append('to_email', document.getElementById('newToEmail').value.trim() || '');
        form.append('cc_emails', document.getElementById('newCcEmails').value || '');
        const scaleVal = document.getElementById('newScale').value;
        if (scaleVal) form.append('scale', scaleVal);
        form.append('name', document.getElementById('newName').value || '');
        form.append('no_hp', document.getElementById('newNoHp').value || '');
        const moduleIdVal = document.getElementById('newModuleId').value;
        if (moduleIdVal) form.append('module_id', moduleIdVal);
        form.append('client', document.getElementById('newClient').value || '');
        form.append('body', bodyHtml || '');
        const files = document.getElementById('newAttachments').files;
        for (let i = 0; i < files.length; i++) form.append('attachments[]', files[i]);
        const isAdmin = userRole === EC_ADMINISTRATOR_ROLE;
        const endpoint = isAdmin ? '/api/tickets' : '/api/tickets/helpdesk-create';
        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                credentials: 'same-origin',
                body: form
            });
            const result = await response.json();
            if (result.success) {
                const msg = isAdmin ?
                    'Ticket created successfully!' :
                    `Ticket ${result.ticket_number} created${result.email_sent ? ' & email sent!' : ''}`;
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
    const _sectionOpen = {
        statsSection: true
    };

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
            }, {
                once: true
            });
            if (chevron) chevron.style.transform = 'rotate(0deg)';
        } else {
            // Collapse: pin to current scrollHeight first so transition has a from-value
            section.style.maxHeight = section.scrollHeight + 'px';
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    section.style.maxHeight = '0px';
                });
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
        'pending_validation': {
            bg: 'bg-gray-100',
            text: 'text-gray-500',
            dot: 'bg-gray-400',
            label: 'Pending'
        },
        'pending': {
            bg: 'bg-blue-50',
            text: 'text-blue-700',
            dot: 'bg-blue-500',
            label: 'Active'
        },
        'paused': {
            bg: 'bg-amber-50',
            text: 'text-amber-700',
            dot: 'bg-amber-500',
            label: 'Paused'
        },
        'met': {
            bg: 'bg-green-50',
            text: 'text-green-700',
            dot: 'bg-green-500',
            label: 'Met'
        },
        'breached': {
            bg: 'bg-red-50',
            text: 'text-red-700',
            dot: 'bg-red-500',
            label: 'Breached'
        },
    };
    const EVENT_ROW_CFG_SLA = {
        email_received: {
            dot: '#6366f1',
            rowBg: '#fafaff',
            label: 'Email / Request Received'
        },
        ticket_validated: {
            dot: '#16a34a',
            rowBg: '#f6fef7',
            label: 'Ticket Created'
        },
        agent_replied: {
            dot: '#2563eb',
            rowBg: '#f5f9ff',
            label: 'Helpdesk Reply'
        },
        customer_replied: {
            dot: '#ea580c',
            rowBg: '#fff8f4',
            label: 'Customer Reply'
        },
        resolution_sent: {
            dot: '#0d9488',
            rowBg: '#f4fefc',
            label: 'Resolution Sent'
        },
        escalated_to_sap: {
            dot: '#7c3aed',
            rowBg: '#faf7ff',
            label: 'Escalated to SAP'
        },
        escalated_to_support: {
            dot: '#6b7280',
            rowBg: '#f9fafb',
            label: 'Returned to Helpdesk'
        },
        sla_warning: {
            dot: '#ca8a04',
            rowBg: '#fffdf0',
            label: 'SLA Warning'
        },
        sla_breached: {
            dot: '#dc2626',
            rowBg: '#fff8f8',
            label: 'SLA Breached'
        },
        ticket_closed: {
            dot: '#374151',
            rowBg: '#f9fafb',
            label: 'Ticket Closed'
        },
        meeting_started: {
            dot: '#7c3aed',
            rowBg: '#faf7ff',
            label: 'Meeting Started'
        },
        meeting_ended: {
            dot: '#7c3aed',
            rowBg: '#faf7ff',
            label: 'Meeting Ended'
        },
    };
    const BALL_ICON_SLA = {
        helpdesk: {
            icon: '▶',
            label: 'Helpdesk'
        },
        customer: {
            icon: '⏸',
            label: 'Customer'
        },
        sap: {
            icon: '⏸',
            label: 'SAP'
        },
        meeting: {
            icon: '⏸',
            label: 'Meeting'
        },
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
            const res = await fetch('/api/tickets/' + ticketId + '/sla', {
                credentials: 'include'
            });
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
        const resSc = STATUS_CFG_SLA[data.resolution && data.resolution.status] || STATUS_CFG_SLA['pending'];

        document.getElementById('slaStatResponseVal').textContent = data.response && data.response.actual_hours != null ? data.response.actual_hours + ' hrs' : '—';
        document.getElementById('slaStatResponseStatus').innerHTML = `<span class="${respSc.text} font-semibold">${respSc.label}</span><span class="text-slate-400"> / target ${data.response ? data.response.target_hours : '—'} hrs</span>`;
        document.getElementById('slaStatResolutionVal').textContent = data.resolution && data.resolution.actual_hours != null ? data.resolution.actual_hours + ' hrs' : (data.sla_mode === 'response_only' ? 'N/A' : '—');
        document.getElementById('slaStatResolutionStatus').innerHTML = data.resolution ?
            `<span class="${resSc.text} font-semibold">${resSc.label}</span><span class="text-slate-400"> / target ${data.resolution.target_hours} hrs</span>` :
            `<span class="text-slate-400">Response-only mode</span>`;

        const totalWait = data.total_waiting_hours != null ? data.total_waiting_hours : 0;
        document.getElementById('slaStatWaitingVal').textContent = totalWait > 0 ? totalWait.toFixed(2) + ' hrs' : '0 hrs';
        document.getElementById('slaStatBallHolder').textContent = data.ball_holder ? (data.ball_holder.charAt(0).toUpperCase() + data.ball_holder.slice(1)) : '—';
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
            const dt = e.event_at ? new Date(e.event_at) : null;
            const dateStr = dt ? dt.toLocaleDateString('id-ID', {
                timeZone: 'Asia/Jakarta',
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            }) : '—';
            const timeStr = dt ? dt.toLocaleTimeString('id-ID', {
                timeZone: 'Asia/Jakarta',
                hour: '2-digit',
                minute: '2-digit'
            }) : '—';
            const showDate = dateStr !== lastDate;
            lastDate = dateStr;
            const evCfg = EVENT_ROW_CFG_SLA[e.event_type] || {
                dot: '#9ca3af',
                rowBg: '#fff',
                label: e.event_type
            };
            const ballCfg = e.ball_after ? (BALL_ICON_SLA[e.ball_after] || null) : null;
            const waitCell = e.waiting_hours !== null && e.waiting_hours !== undefined ? `<span class="text-[11px] font-semibold text-amber-600 whitespace-nowrap">${_slaToHLabel(e.waiting_hours)}</span>` : `<span class="text-gray-300 text-xs">—</span>`;
            const respCell = e.response_hours !== null && e.response_hours !== undefined ? `<span class="text-[11px] font-semibold text-gray-700 whitespace-nowrap">${_slaToHLabel(e.response_hours)}</span>` : `<span class="text-gray-300 text-xs">—</span>`;
            const resCell = e.meeting_paused ?
                `<span class="text-[10px] font-semibold text-purple-600 whitespace-nowrap">Paused (Meeting)</span>` :
                (e.resolution_hours !== null && e.resolution_hours !== undefined ? `<span class="text-[11px] font-semibold text-gray-700 whitespace-nowrap">${_slaToHLabel(e.resolution_hours)}</span>` : `<span class="text-gray-300 text-xs">—</span>`);
            const statusCell = e.jarvis_status ? `<span class="text-[10px] text-gray-500 whitespace-nowrap">${e.jarvis_status.replace(/_/g,' ')}</span>` : `<span class="text-gray-300 text-xs">—</span>`;
            const ballCell = ballCfg ? `<span class="text-[11px] font-semibold text-gray-600 whitespace-nowrap">${ballCfg.icon} ${ballCfg.label}</span>` : `<span class="text-gray-300 text-xs">—</span>`;
            const senderPrefix = e.sender_name ? `<span class="font-semibold text-gray-700">${e.sender_name}:</span> ` : '';
            const bodyText = e.message_preview || e.notes || null;
            const msgText = bodyText ?
                `<span title="${(e.message_preview || '').replace(/"/g,'&quot;')}" class="text-gray-500 text-xs">${senderPrefix}${bodyText.substring(0, 80)}${bodyText.length > 80 ? '…' : ''}</span>` :
                (e.sender_name ? `<span class="font-semibold text-gray-700 text-xs">${e.sender_name}</span>` : `<span class="text-gray-300 text-xs">—</span>`);
            const dateSep = showDate ? `<tr><td colspan="9" style="background:#f3f4f6;border-top:1px solid #e5e7eb;border-bottom:1px solid #e5e7eb;padding:4px 12px;"><span style="font-size:10px;font-weight:600;color:#6b7280;letter-spacing:0.04em;">${dt ? dt.toLocaleDateString('id-ID', { timeZone: 'Asia/Jakarta', weekday:'long', day:'numeric', month:'long', year:'numeric' }) : dateStr}</span></td></tr>` : '';
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

    async function hideTicketFromList(ticketId, event) {
        event.stopPropagation();
        if (!await showConfirm('Sembunyikan tiket ini? Tiket tidak akan muncul di daftar utama.', 'Hide Ticket', 'danger')) return;
        try {
            const res = await fetch(`/api/tickets/${ticketId}/hide`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
            });
            const data = await res.json();
            if (data.success) {
                showNotification('Tiket berhasil disembunyikan.', 'success');
                setTimeout(() => window.location.reload(), 800);
            } else {
                showNotification(data.message || 'Gagal menyembunyikan tiket.', 'error');
            }
        } catch (e) {
            showNotification('Terjadi kesalahan. Coba lagi.', 'error');
        }
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
$customDdVer = file_exists($customDdPath) ? filemtime($customDdPath) : time();
@endphp
<script src="/js/custom-dropdown.js?v={{ $customDdVer }}"></script>

{{-- Ticket row context menu --}}
<div id="ticketContextMenu"
    class="hidden fixed z-[9999] bg-white border border-gray-200 rounded-xl shadow-xl py-1 min-w-[190px]"
    style="pointer-events:auto;">
    <button type="button" id="ctxOpenNewTab"
        class="w-full flex items-center gap-2.5 px-4 py-2 text-xs text-gray-700 hover:bg-gray-50 transition-colors text-left">
        <i class="fas fa-external-link-alt text-gray-400 w-3.5"></i>
        Open in new tab
    </button>
    @if($can('ticket.activity-log'))
    <div class="border-t border-gray-100 my-1"></div>
    <button type="button" id="ctxActivityLog"
        class="w-full flex items-center gap-2.5 px-4 py-2 text-xs text-gray-700 hover:bg-gray-50 transition-colors text-left">
        <i class="fas fa-clipboard-list text-gray-400 w-3.5"></i>
        Log Ticket Activity
    </button>
    @endif
    @if($can('reporting.log-shifting'))
    <div class="border-t border-gray-100 my-1"></div>
    <button type="button" id="ctxLogShifting"
        class="w-full flex items-center gap-2.5 px-4 py-2 text-xs text-gray-700 hover:bg-gray-50 transition-colors text-left">
        <i class="fas fa-clock text-gray-400 w-3.5"></i>
        Log Shifting
    </button>
    @endif
</div>

<script>
    (function() {
        let _ctxTicketId = null;
        let _ctxTicketNum = null;
        const menu = document.getElementById('ticketContextMenu');

        window.openTicketContextMenu = function(e, ticketId, rowEl) {
            e.preventDefault();
            e.stopPropagation();
            _ctxTicketId = ticketId;
            _ctxTicketNum = rowEl ? (rowEl.dataset.ticketNum || null) : null;

            // Posisi tepat di kursor, jaga agar tidak keluar viewport
            const vw = window.innerWidth,
                vh = window.innerHeight;
            const mw = 180,
                mh = 44;
            let x = e.clientX,
                y = e.clientY;
            if (x + mw > vw) x = vw - mw - 8;
            if (y + mh > vh) y = vh - mh - 8;

            menu.style.left = x + 'px';
            menu.style.top = y + 'px';
            menu.classList.remove('hidden');
        };

        document.getElementById('ctxOpenNewTab').addEventListener('click', function() {
            if (_ctxTicketId) window.open('/ticket/' + _ctxTicketId, '_blank');
            menu.classList.add('hidden');
            _ctxTicketId = null;
        });

        const ctxLog = document.getElementById('ctxActivityLog');
        if (ctxLog) {
            ctxLog.addEventListener('click', function() {
                if (_ctxTicketId) openActivityLogModal(_ctxTicketId, _ctxTicketNum);
                menu.classList.add('hidden');
                _ctxTicketId = null;
                _ctxTicketNum = null;
            });
        }

        const ctxLogShifting = document.getElementById('ctxLogShifting');
        if (ctxLogShifting) {
            ctxLogShifting.addEventListener('click', function() {
                if (_ctxTicketId && typeof openLogShiftingTicketModal === 'function') openLogShiftingTicketModal(_ctxTicketId);
                menu.classList.add('hidden');
                _ctxTicketId = null;
                _ctxTicketNum = null;
            });
        }

        document.addEventListener('click', function() {
            menu.classList.add('hidden');
            _ctxTicketId = null;
        });
    })();
</script>

@if($can('ticket.activity-log'))
{{-- ══════════════════════════════════════════════════════════════════════════
     TICKET ACTIVITY LOG MODAL
══════════════════════════════════════════════════════════════════════════ --}}
<div id="activityLogModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-[9990] flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-5xl max-h-[90vh] flex flex-col overflow-hidden">

        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 flex-shrink-0">
            <div>
                <h3 class="text-sm font-bold text-gray-900">Ticket Activity Log</h3>
                <p id="alModalTicketNum" class="text-xs text-gray-400 mt-0.5">—</p>
            </div>
            <div class="flex items-center gap-2">
                <button onclick="openAlForm(null)"
                    class="flex items-center gap-1.5 px-3 py-1.5 bg-red-700 hover:bg-red-800 text-white text-xs font-semibold rounded-lg transition-all">
                    Add Activity
                </button>
                <button onclick="closeActivityLogModal()"
                    class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-500 hover:bg-gray-200 transition-all text-sm">✕</button>
            </div>
        </div>

        {{-- Table --}}
        <div class="flex-1 overflow-auto">
            <table class="w-full text-xs border-collapse">
                <thead class="sticky top-0 bg-gray-50 z-10">
                    <tr>
                        <th class="px-3 py-2.5 text-left font-semibold text-gray-500 uppercase tracking-wide border-b border-gray-200 w-8">#</th>
                        <th class="px-3 py-2.5 text-left font-semibold text-gray-500 uppercase tracking-wide border-b border-gray-200 whitespace-nowrap">Date</th>
                        <th class="px-3 py-2.5 text-left font-semibold text-gray-500 uppercase tracking-wide border-b border-gray-200 whitespace-nowrap">PIC</th>
                        <th class="px-3 py-2.5 text-left font-semibold text-gray-500 uppercase tracking-wide border-b border-gray-200">Activity</th>
                        <th class="px-3 py-2.5 text-left font-semibold text-gray-500 uppercase tracking-wide border-b border-gray-200 whitespace-nowrap">File Ref.</th>
                        <th class="px-3 py-2.5 text-left font-semibold text-gray-500 uppercase tracking-wide border-b border-gray-200 w-16">Action</th>
                    </tr>
                </thead>
                <tbody id="alTableBody">
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-400">Loading…</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Activity Log Form Modal --}}
<div id="alFormModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-[9995] flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg flex flex-col overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <h3 id="alFormTitle" class="text-sm font-bold text-gray-900">Add Activity</h3>
            <button onclick="closeAlForm()" class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-500 hover:bg-gray-200 transition-all text-sm">✕</button>
        </div>
        <form id="alForm" class="px-6 py-5 space-y-4" onsubmit="submitAlForm(event)">
            <input type="hidden" id="alFormLogId">

            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Date <span class="text-red-500">*</span></label>
                <input type="date" id="alFormDate" required
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-500">
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Activity <span class="text-red-500">*</span></label>
                <textarea id="alFormActivity" rows="4" required maxlength="5000"
                    placeholder="Describe the activity…"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-500 resize-y"></textarea>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">File Ref. — Link</label>
                <input type="url" id="alFormUrl" placeholder="https://…"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-500">
                <p class="text-[10px] text-gray-400 mt-1">Paste a Google Drive, OneDrive, or other link</p>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">File Ref. — Upload</label>
                <div id="alFileArea" class="border border-dashed border-gray-300 rounded-lg px-3 py-3 text-xs text-gray-500">
                    <div id="alCurrentFile" class="hidden flex items-center gap-2 mb-2 p-2 bg-gray-50 rounded-lg">
                        <i class="fas fa-paperclip text-gray-400"></i>
                        <span id="alCurrentFileName" class="flex-1 truncate text-gray-700"></span>
                        <button type="button" onclick="removeAlFile()" class="text-red-400 hover:text-red-600 text-xs">✕</button>
                    </div>
                    <label class="cursor-pointer flex items-center gap-2 hover:text-gray-700 transition-colors">
                        <i class="fas fa-upload text-gray-400"></i>
                        <span id="alUploadLabel">Choose file (max 10 MB)</span>
                        <input type="file" id="alFormFile" class="hidden" onchange="onAlFileChange(this)">
                    </label>
                </div>
                <input type="hidden" id="alRemoveFile" value="0">
            </div>

            <div class="flex gap-3 pt-2">
                <button type="button" onclick="closeAlForm()"
                    class="flex-1 px-4 py-2 bg-white text-gray-700 text-sm font-medium rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">Cancel</button>
                <button type="submit" id="alFormSubmitBtn"
                    class="flex-1 px-4 py-2 bg-red-700 text-white text-sm font-semibold rounded-lg hover:bg-red-800 transition-all">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
    (function() {
        const CSRF = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
        const ESC = s => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        const CURR_EMP_ID = {{ session('user.id', 0) }};

        let _alTicketId = null;
        let _alTicketNum = null;
        let _alLogs = [];
        let _alEditId = null;

        // ── Open / Close main modal ──────────────────────────────────────────────
        window.openActivityLogModal = async function(ticketId, ticketNum) {
            _alTicketId = ticketId;
            _alTicketNum = ticketNum || null;
            document.getElementById('alModalTicketNum').textContent = ticketNum || `#${ticketId}`;
            document.getElementById('activityLogModal').classList.remove('hidden');
            document.getElementById('activityLogModal').classList.add('flex');
            document.getElementById('alTableBody').innerHTML =
                '<tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">Loading…</td></tr>';
            await loadAlLogs();
        };

        window.closeActivityLogModal = function() {
            document.getElementById('activityLogModal').classList.add('hidden');
            document.getElementById('activityLogModal').classList.remove('flex');
            closeAlForm();
            _alTicketId = null;
        };

        // ── Load logs ────────────────────────────────────────────────────────────
        async function loadAlLogs() {
            try {
                const res = await fetch(`/api/tickets/${_alTicketId}/activity-logs`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': CSRF()
                    },
                    credentials: 'same-origin',
                });
                const data = await res.json();
                if (data.success) {
                    _alLogs = data.data;
                    renderAlTable();
                } else {
                    document.getElementById('alTableBody').innerHTML =
                        `<tr><td colspan="6" class="px-4 py-6 text-center text-red-500">Failed to load</td></tr>`;
                }
            } catch (e) {
                document.getElementById('alTableBody').innerHTML =
                    `<tr><td colspan="6" class="px-4 py-6 text-center text-red-500">Error: ${ESC(e.message)}</td></tr>`;
            }
        }

        function renderAlTable() {
            if (_alLogs.length === 0) {
                document.getElementById('alTableBody').innerHTML =
                    '<tr><td colspan="6" class="px-4 py-8 text-center text-gray-400 text-xs">No activity entries yet. Click "Add Activity" to start.</td></tr>';
                return;
            }
            document.getElementById('alTableBody').innerHTML = _alLogs.map((log, idx) => {
                const isOwner = log.employee_id == CURR_EMP_ID;
                const fileHtml = buildFileRefHtml(log);
                return `<tr class="border-b border-gray-100 hover:bg-gray-50 align-top">
                <td class="px-3 py-2.5 text-gray-400 font-mono">${idx + 1}</td>
                <td class="px-3 py-2.5 text-gray-700 whitespace-nowrap">${ESC(log.activity_date)}</td>
                <td class="px-3 py-2.5 text-gray-700 whitespace-nowrap">${ESC(log.pic)}</td>
                <td class="px-3 py-2.5 text-gray-700" style="max-width:400px;white-space:pre-wrap;">${ESC(log.activity)}</td>
                <td class="px-3 py-2.5">${fileHtml}</td>
                <td class="px-3 py-2.5 whitespace-nowrap">
                    ${isOwner ? `
                    <button onclick="openAlForm(${log.id})" class="text-blue-500 hover:text-blue-700 mr-2" title="Edit"><i class="fas fa-pen text-xs"></i></button>
                    <button onclick="deleteAlLog(${log.id})" class="text-red-400 hover:text-red-600" title="Delete"><i class="fas fa-trash text-xs"></i></button>
                    ` : '<span class="text-gray-300">—</span>'}
                </td>
            </tr>`;
            }).join('');
        }

        function buildFileRefHtml(log) {
            const parts = [];
            if (log.file_ref_url) {
                parts.push(`<a href="${ESC(log.file_ref_url)}" target="_blank" rel="noopener" class="flex items-center gap-1 text-blue-500 hover:underline"><i class="fas fa-link text-[10px]"></i><span class="truncate max-w-[120px]">Link</span></a>`);
            }
            if (log.file_ref_path) {
                parts.push(`<a href="${ESC(log.file_ref_path)}" target="_blank" rel="noopener" class="flex items-center gap-1 text-blue-500 hover:underline"><i class="fas fa-paperclip text-[10px]"></i><span class="truncate max-w-[120px]">${ESC(log.file_ref_name || 'File')}</span></a>`);
            }
            return parts.length ? `<div class="space-y-0.5">${parts.join('')}</div>` : '<span class="text-gray-300">—</span>';
        }

        // ── Form ─────────────────────────────────────────────────────────────────
        window.openAlForm = function(logId) {
            _alEditId = logId;
            document.getElementById('alFormTitle').textContent = logId ? 'Edit Activity' : 'Add Activity';
            document.getElementById('alFormLogId').value = logId || '';
            document.getElementById('alFormDate').value = '';
            document.getElementById('alFormActivity').value = '';
            document.getElementById('alFormUrl').value = '';
            document.getElementById('alFormFile').value = '';
            document.getElementById('alRemoveFile').value = '0';
            document.getElementById('alCurrentFile').classList.add('hidden');
            document.getElementById('alUploadLabel').textContent = 'Choose file (max 10 MB)';

            if (logId) {
                const log = _alLogs.find(l => l.id === logId);
                if (log) {
                    document.getElementById('alFormDate').value = log.activity_date || '';
                    document.getElementById('alFormActivity').value = log.activity || '';
                    document.getElementById('alFormUrl').value = log.file_ref_url || '';
                    if (log.file_ref_name) {
                        document.getElementById('alCurrentFileName').textContent = log.file_ref_name;
                        document.getElementById('alCurrentFile').classList.remove('hidden');
                        document.getElementById('alUploadLabel').textContent = 'Replace file';
                    }
                }
            } else {
                // default date = today
                document.getElementById('alFormDate').value = new Date().toISOString().slice(0, 10);
            }

            document.getElementById('alFormModal').classList.remove('hidden');
            document.getElementById('alFormModal').classList.add('flex');
            document.getElementById('alFormDate').focus();
        };

        window.closeAlForm = function() {
            document.getElementById('alFormModal').classList.add('hidden');
            document.getElementById('alFormModal').classList.remove('flex');
            _alEditId = null;
        };

        window.onAlFileChange = function(input) {
            if (input.files && input.files[0]) {
                document.getElementById('alCurrentFileName').textContent = input.files[0].name;
                document.getElementById('alCurrentFile').classList.remove('hidden');
                document.getElementById('alRemoveFile').value = '0';
                document.getElementById('alUploadLabel').textContent = 'Replace file';
            }
        };

        window.removeAlFile = function() {
            document.getElementById('alFormFile').value = '';
            document.getElementById('alCurrentFile').classList.add('hidden');
            document.getElementById('alRemoveFile').value = '1';
            document.getElementById('alUploadLabel').textContent = 'Choose file (max 10 MB)';
        };

        window.submitAlForm = async function(e) {
            e.preventDefault();
            const btn = document.getElementById('alFormSubmitBtn');
            btn.disabled = true;
            btn.textContent = 'Saving…';

            const logId = document.getElementById('alFormLogId').value;
            const formData = new FormData();
            formData.append('activity_date', document.getElementById('alFormDate').value);
            formData.append('activity', document.getElementById('alFormActivity').value);
            const url = document.getElementById('alFormUrl').value.trim();
            if (url) formData.append('file_ref_url', url);

            const fileInput = document.getElementById('alFormFile');
            if (fileInput.files && fileInput.files[0]) {
                formData.append('file_ref', fileInput.files[0]);
            } else if (document.getElementById('alRemoveFile').value === '1') {
                formData.append('remove_file', '1');
            }

            const endpoint = logId ?
                `/api/tickets/${_alTicketId}/activity-logs/${logId}/update` :
                `/api/tickets/${_alTicketId}/activity-logs`;

            try {
                const res = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': CSRF(),
                        'Accept': 'application/json'
                    },
                    credentials: 'same-origin',
                    body: formData,
                });
                const data = await res.json();
                if (data.success) {
                    closeAlForm();
                    await loadAlLogs();
                } else {
                    showNotification(data.message || 'Failed to save activity.', 'error');
                }
            } catch (err) {
                showNotification('Error: ' + err.message, 'error');
            } finally {
                btn.disabled = false;
                btn.textContent = 'Save';
            }
        };

        window.deleteAlLog = async function(logId) {
            if (!await showConfirm('Delete this activity entry? This cannot be undone.', 'Delete Activity', 'danger')) return;
            try {
                const res = await fetch(`/api/tickets/${_alTicketId}/activity-logs/${logId}/delete`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': CSRF(),
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    credentials: 'same-origin',
                });
                const data = await res.json();
                if (data.success) {
                    await loadAlLogs();
                } else {
                    showNotification(data.message || 'Failed to delete activity.', 'error');
                }
            } catch (err) {
                showNotification('Error: ' + err.message, 'error');
            }
        };

        // Close modals on backdrop click
        document.getElementById('activityLogModal').addEventListener('click', function(e) {
            if (e.target === this) closeActivityLogModal();
        });
        document.getElementById('alFormModal').addEventListener('click', function(e) {
            if (e.target === this) closeAlForm();
        });
    })();
</script>
@endif

@if($can('reporting.log-shifting'))
{{-- ══════════════════════════════════════════════════════════════════════════
     LOG SHIFTING MODAL (single ticket — same data as Reporting > Log Shifting)
══════════════════════════════════════════════════════════════════════════ --}}
<div id="logShiftingTicketModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-[9990] flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[85vh] flex flex-col overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 flex-shrink-0">
            <div>
                <h3 class="text-sm font-bold text-gray-900">Log Shifting</h3>
                <p id="lstModalTicketNum" class="text-xs text-gray-400 mt-0.5">—</p>
            </div>
            <button onclick="closeLogShiftingTicketModal()"
                class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-500 hover:bg-gray-200 transition-all text-sm">✕</button>
        </div>
        <div class="flex-1 overflow-auto">
            <table class="w-full text-xs border-collapse">
                <thead class="sticky top-0 bg-gray-50 z-10">
                    <tr>
                        <th class="px-3 py-2.5 text-left font-semibold text-gray-500 uppercase tracking-wide border-b border-gray-200 whitespace-nowrap">Date</th>
                        <th class="px-3 py-2.5 text-left font-semibold text-gray-500 uppercase tracking-wide border-b border-gray-200 whitespace-nowrap">Time</th>
                        <th class="px-3 py-2.5 text-left font-semibold text-gray-500 uppercase tracking-wide border-b border-gray-200">SLA Message</th>
                        <th class="px-3 py-2.5 text-left font-semibold text-gray-500 uppercase tracking-wide border-b border-gray-200 whitespace-nowrap">PIC</th>
                    </tr>
                </thead>
                <tbody id="lstModalBody">
                    <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400">Loading…</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    window.openLogShiftingTicketModal = async function(ticketId) {
        const modal = document.getElementById('logShiftingTicketModal');
        const body  = document.getElementById('lstModalBody');
        document.getElementById('lstModalTicketNum').textContent = 'Loading...';
        body.innerHTML = '<tr><td colspan="4" class="px-4 py-8 text-center text-gray-400">Loading…</td></tr>';
        modal.classList.remove('hidden');

        try {
            const res = await fetch(`/api/reporting/log-shifting/${ticketId}`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            });
            const json = await res.json();
            if (!json.success) throw new Error(json.message || 'Failed to load data');

            const { ticket, messages } = json.data;
            document.getElementById('lstModalTicketNum').textContent = ticket.ticket_number || '—';

            if (!messages.length) {
                body.innerHTML = '<tr><td colspan="4" class="px-4 py-8 text-center text-gray-400">No SLA messages found for this ticket.</td></tr>';
                return;
            }

            body.innerHTML = messages.map(m => {
                const bubble  = m.bubble_date ? new Date(m.bubble_date) : null;
                const dateStr = bubble ? bubble.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
                const timeStr = bubble ? bubble.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', hour12: false }) + ' WIB' : '—';
                return `
                <tr class="border-b border-gray-100">
                    <td class="px-3 py-2.5 text-gray-500 whitespace-nowrap">${dateStr}</td>
                    <td class="px-3 py-2.5 text-gray-500 whitespace-nowrap">${timeStr}</td>
                    <td class="px-3 py-2.5 text-gray-700 whitespace-pre-wrap">${lstEsc(m.sla_message || '—')}</td>
                    <td class="px-3 py-2.5 text-gray-700 whitespace-nowrap">${m.sla_message_by ? lstEsc(m.sla_message_by) : '<span class="text-gray-300 italic">Unknown</span>'}</td>
                </tr>`;
            }).join('');
        } catch (e) {
            body.innerHTML = `<tr><td colspan="4" class="px-4 py-8 text-center text-red-500">${lstEsc(e.message)}</td></tr>`;
        }
    };

    window.closeLogShiftingTicketModal = function() {
        document.getElementById('logShiftingTicketModal').classList.add('hidden');
    };

    document.getElementById('logShiftingTicketModal').addEventListener('click', function(e) {
        if (e.target === this) closeLogShiftingTicketModal();
    });

    function lstEsc(str) {
        return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }
</script>
@endif

{{-- ══════════════════ AI SUMMARIZE ══════════════════ --}}
@if($canAiSummarize)
<script src="https://cdn.jsdelivr.net/npm/marked@12/marked.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/dompurify@3/dist/purify.min.js"></script>

<style>
    /* Preflight Tailwind mematikan marker list dan ukuran heading. Markdown
       hasil AI butuh keduanya kembali — dibatasi ke dalam .ai-sum-body saja
       supaya tidak bocor ke tabel tiket. Sengaja tanpa warna: pewarnaan tetap
       lewat utility Tailwind di elemen induk, jadi dark mode global ikut. */
    .ai-sum-body ul { list-style: disc; padding-left: 1.15rem; margin: .25rem 0; }
    .ai-sum-body ol { list-style: decimal; padding-left: 1.35rem; margin: .25rem 0; }
    .ai-sum-body li { margin: .2rem 0; }
    .ai-sum-body p { margin: .35rem 0; }
    .ai-sum-body p:first-child { margin-top: 0; }
    .ai-sum-body strong { font-weight: 600; }
    .ai-sum-body code { font-family: ui-monospace, monospace; font-size: .85em; }
    /* Tautan rujukan dokumentasi luar; preflight Tailwind menanggalkan garis
       bawahnya, jadi dikembalikan di sini supaya terbaca sebagai tautan. */
    .ai-sum-body a { text-decoration: underline; text-underline-offset: 2px; word-break: break-word; }
</style>

<div id="ticketSummaryModal" class="hidden fixed inset-0 z-[10000] bg-black/50 flex items-center justify-center p-4">
    {{-- Lebar 5xl: isinya kini langkah teknis bernomor berikut TCODE, nama tabel,
         dan URL rujukan — kolom sempit membuat satu langkah pecah jadi 6-7 baris. --}}
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-5xl max-h-[90vh] flex flex-col">
        {{-- Header --}}
        <div class="flex items-start justify-between gap-3 px-5 py-4 border-b border-gray-100">
            <div class="flex items-center gap-2.5 min-w-0">
                <span class="shrink-0 w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                        <path d="M10 1.5l1.6 4.2 4.4 1.3-4.4 1.3L10 12.5 8.4 8.3 4 7l4.4-1.3L10 1.5zM15.5 12l.9 2.3 2.6.7-2.6.7-.9 2.3-.9-2.3-2.6-.7 2.6-.7.9-2.3zM4.5 11l.7 1.8 2 .5-2 .5-.7 1.8-.7-1.8-2-.5 2-.5.7-1.8z" />
                    </svg>
                </span>
                <div class="min-w-0">
                    <h3 class="text-sm font-bold text-gray-800">AI Summarize</h3>
                    <p id="ticketSummaryTicketNo" class="text-xs text-gray-500 truncate">—</p>
                </div>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <span id="ticketSummaryStatus" class="text-[11px] text-gray-400"></span>
                <button type="button" onclick="closeTicketSummary()" class="p-1.5 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        {{-- Body: tiga kartu tetap, diisi sambil teksnya mengalir --}}
        <div class="overflow-y-auto px-5 py-4 space-y-3">
            <div id="ticketSummaryError" class="hidden rounded-xl bg-red-50 border border-red-100 px-4 py-3 text-sm text-red-700"></div>

            @foreach ([
                ['key' => 'isu',        'label' => 'Isu',              'tone' => 'amber'],
                ['key' => 'penyelesaian','label' => 'Cara Penyelesaian','tone' => 'blue'],
                ['key' => 'kesimpulan', 'label' => 'Kesimpulan',       'tone' => 'emerald'],
            ] as $sec)
            <div class="rounded-xl border border-gray-100 bg-gray-50 overflow-hidden">
                <div class="px-4 py-2 border-b border-gray-100 bg-{{ $sec['tone'] }}-50">
                    <span class="text-[11px] font-bold uppercase tracking-widest text-{{ $sec['tone'] }}-700">{{ $sec['label'] }}</span>
                </div>
                <div id="ticketSummary-{{ $sec['key'] }}" class="ai-sum-body px-4 py-3 text-sm text-gray-700 leading-relaxed">
                    <span class="text-gray-300 italic">Menunggu…</span>
                </div>
                @if ('penyelesaian' === $sec['key'])
                {{-- Rujukan dokumentasi luar yang benar-benar dibuka model saat
                     menyusun langkah penyelesaian. Diisi dari event 'sources'. --}}
                <div id="ticketSummarySources" class="hidden px-4 pb-3 pt-0 border-t border-gray-100">
                    <div class="text-[10px] font-bold uppercase tracking-widest text-gray-400 mt-2.5 mb-1.5">Sumber dokumentasi</div>
                    <ul id="ticketSummarySourcesList" class="space-y-1"></ul>
                </div>
                @endif
            </div>
            @endforeach
        </div>
    </div>
</div>

<script>
(function () {
    // Judul heading di bawah adalah KONTRAK dengan system prompt di
    // App\Services\Ai\AiTicketSummaryService::systemPrompt(). Kalau di sana
    // berubah, ubah juga di sini — kalau tidak, teksnya mengalir masuk ke kartu
    // yang salah (atau tidak masuk sama sekali).
    const TICKET_SUMMARY_SECTIONS = {
        'isu': 'isu',
        'cara penyelesaian': 'penyelesaian',
        'kesimpulan': 'kesimpulan',
    };

    let summaryAbort = null;

    function el(id) { return document.getElementById(id); }

    function mdToHtml(text) {
        return DOMPurify.sanitize(marked.parse(String(text ?? '')));
    }

    /**
     * Pecah teks yang sedang mengalir pada heading "## ", lalu render tiap
     * bagian ke kartunya. Dipanggil ulang setiap delta: heading terakhir
     * mungkin masih setengah tertulis, dan itu tidak apa-apa — bagian yang
     * belum dikenali cukup diabaikan sampai barisnya utuh.
     */
    function renderSummary(full) {
        const buckets = { isu: '', penyelesaian: '', kesimpulan: '' };
        let current = null;
        let preamble = '';

        // Di sela pencarian, model kadang menulis satu kalimat kerja ("Ada hasil
        // bagus. Mari fetch halaman berikutnya.") lalu menyambung heading TANPA
        // baris baru — jadi "…langkah.## Isu". Tanpa dipisahkan, heading itu tak
        // pernah cocok dan seluruh jawaban menumpuk di satu kartu.
        const normalized = String(full).replace(/([^\n])(#{1,3}\s*(?:Isu|Cara Penyelesaian|Kesimpulan)\b)/gi, '$1\n$2');

        normalized.split('\n').forEach(line => {
            const heading = line.match(/^\s*#{1,3}\s*(.+?)\s*$/);
            if (heading) {
                const key = TICKET_SUMMARY_SECTIONS[heading[1].trim().toLowerCase()];
                if (key) { current = key; return; }
            }
            // Teks sebelum heading pertama ditahan dulu, JANGAN langsung
            // ditumpahkan ke kartu Isu: itu biasanya narasi kerja model di sela
            // pencarian, bukan isi ringkasan. Baru dipakai kalau sampai akhir
            // tidak ada satu pun heading yang dikenali (lihat di bawah).
            if (!current) { preamble += line + '\n'; return; }
            buckets[current] += line + '\n';
        });

        // Belum ada heading sama sekali — tampilkan apa adanya di kartu Isu
        // supaya streaming tetap terlihat bergerak, bukan diam "Menunggu…".
        if (!current && preamble.trim()) {
            buckets.isu = preamble;
        }

        Object.keys(buckets).forEach(key => {
            const target = el('ticketSummary-' + key);
            const body = buckets[key].trim();
            if (body) {
                target.innerHTML = mdToHtml(body);
                // Tautan rujukan di dalam langkah penyelesaian mengarah ke luar
                // sistem — jangan menimpa halaman daftar tiket yang sedang dibuka.
                target.querySelectorAll('a[href]').forEach(a => {
                    a.target = '_blank';
                    a.rel = 'noopener noreferrer';
                });
            }
        });
    }

    /**
     * Daftar rujukan dokumentasi luar. Judul & URL datang dari hasil web_search
     * di sisi server — dianggap teks asing, jadi judulnya di-set lewat
     * textContent dan hanya URL http(s) yang boleh menjadi href.
     */
    function renderSources(items) {
        const box = el('ticketSummarySources');
        const list = el('ticketSummarySourcesList');
        list.innerHTML = '';

        (items || []).forEach(item => {
            if (!/^https?:\/\//i.test(item.url || '')) return;

            const li = document.createElement('li');
            const a = document.createElement('a');
            a.href = item.url;
            a.target = '_blank';
            a.rel = 'noopener noreferrer';
            a.className = 'text-xs text-indigo-600 hover:underline break-all';
            a.textContent = item.title || item.url;
            li.appendChild(a);
            list.appendChild(li);
        });

        box.classList.toggle('hidden', list.children.length === 0);
    }

    function resetSummary() {
        el('ticketSummaryError').classList.add('hidden');
        el('ticketSummaryError').textContent = '';
        renderSources([]);
        ['isu', 'penyelesaian', 'kesimpulan'].forEach(key => {
            el('ticketSummary-' + key).innerHTML = '<span class="text-gray-300 italic">Menunggu…</span>';
        });
    }

    function showSummaryError(message) {
        const box = el('ticketSummaryError');
        box.textContent = message;
        box.classList.remove('hidden');
    }

    window.openTicketSummary = async function (ticketId, ticketNumber) {
        if (summaryAbort) summaryAbort.abort();

        el('ticketSummaryTicketNo').textContent = ticketNumber || ('#' + ticketId);
        el('ticketSummaryModal').classList.remove('hidden');
        el('ticketSummaryStatus').textContent = 'Menganalisis…';
        resetSummary();

        summaryAbort = new AbortController();
        const controller = summaryAbort;

        let full = '';

        try {
            const response = await fetch('/ticket/' + ticketId + '/ai-summary', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'text/event-stream',
                },
                signal: controller.signal,
            });

            if (!response.ok || !response.body) {
                throw new Error('Tidak bisa menghubungi AI (HTTP ' + response.status + ').');
            }

            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';
            let sawError = null;

            while (true) {
                const { value, done } = await reader.read();
                if (done) break;

                buffer += decoder.decode(value, { stream: true });

                let boundary;
                while ((boundary = buffer.indexOf('\n\n')) !== -1) {
                    const frame = buffer.slice(0, boundary);
                    buffer = buffer.slice(boundary + 2);

                    let eventName = 'message';
                    let dataLine = '';
                    frame.split('\n').forEach(line => {
                        if (line.startsWith('event:')) eventName = line.slice(6).trim();
                        if (line.startsWith('data:')) dataLine = line.slice(5).trim();
                    });
                    if (!dataLine) continue;

                    let payload;
                    try { payload = JSON.parse(dataLine); } catch { continue; }

                    if (eventName === 'meta') {
                        el('ticketSummaryStatus').textContent = payload.cached ? 'Ringkasan tersimpan' : 'Menganalisis…';
                    } else if (eventName === 'status') {
                        // Progres riset dokumentasi luar: "Mencari dokumentasi…",
                        // "Membuka dokumentasi…", "Menyusun ringkasan…".
                        if (payload.label) el('ticketSummaryStatus').textContent = payload.label;
                    } else if (eventName === 'sources') {
                        renderSources(payload.items);
                    } else if (eventName === 'delta' && payload.text) {
                        full += payload.text;
                        renderSummary(full);
                    } else if (eventName === 'error') {
                        sawError = payload.message || 'Terjadi kesalahan.';
                    } else if (eventName === 'done') {
                        el('ticketSummaryStatus').textContent = payload.cached ? 'Ringkasan tersimpan' : 'Selesai';
                    }
                }
            }

            if (sawError) throw new Error(sawError);

            // Model membalas tanpa satu pun heading yang dikenali: jangan biarkan
            // ketiga kartu diam bertuliskan "Menunggu…" seolah masih memuat.
            if (!full.trim()) {
                showSummaryError('AI tidak mengembalikan ringkasan apa pun. Coba lagi.');
                el('ticketSummaryStatus').textContent = '';
            }
        } catch (e) {
            if (e.name === 'AbortError') return;
            showSummaryError(e.message);
            el('ticketSummaryStatus').textContent = '';
        } finally {
            if (summaryAbort === controller) summaryAbort = null;
        }
    };

    window.closeTicketSummary = function () {
        // Batalkan stream yang masih jalan — tanpa ini koneksi SSE-nya menggantung
        // di server sampai model selesai bicara ke modal yang sudah tertutup.
        if (summaryAbort) { summaryAbort.abort(); summaryAbort = null; }
        el('ticketSummaryModal').classList.add('hidden');
    };

    document.getElementById('ticketSummaryModal').addEventListener('click', function (e) {
        if (e.target === this) closeTicketSummary();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !el('ticketSummaryModal').classList.contains('hidden')) {
            closeTicketSummary();
        }
    });
})();
</script>
@endif

@endsection
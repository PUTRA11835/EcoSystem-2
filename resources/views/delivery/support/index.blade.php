@extends('dashboard')
@section('title', 'Delivery Support')
@section('content')

<!-- Modern Helpdesk Header -->
<div class="mb-8">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
        <div>
            <h1 class="text-4xl font-bold text-gray-900 tracking-tight mb-2">Support Tickets</h1>
            <p class="text-gray-500 text-base">Manage and track all support requests</p>
        </div>
        
        <!-- View Toggle -->
        @php
            $viewRoleIds = array_map('intval', session('user')['role_ids'] ?? [session('user')['role']['id'] ?? 0]);
            $isAdminOrHead   = array_intersect($viewRoleIds, [\App\Enums\RoleId::EC_ADMINISTRATOR->value, \App\Enums\RoleId::DELIVERY_SUPPORT_HEAD->value]);
            $isManager       = in_array(\App\Enums\RoleId::DELIVERY_SUPPORT_MANAGER->value, $viewRoleIds, true);
            $isSupportUser   = in_array(\App\Enums\RoleId::DELIVERY_SUPPORT_USER->value, $viewRoleIds, true);
        @endphp
        @if($isAdminOrHead)
        <div class="inline-flex bg-gray-100 rounded-xl p-1">
            <button onclick="toggleView('all')" id="btnViewAll" class="px-4 py-1.5 text-xs font-semibold rounded-lg transition-all duration-200">
                <i class="fas fa-list text-[10px] mr-1"></i>All Tickets
            </button>
            <button onclick="toggleView('unassign')" id="btnViewUnassign" class="px-4 py-1.5 text-xs font-semibold rounded-lg transition-all duration-200">
                <i class="fas fa-user-clock text-[10px] mr-1"></i>Unassign
            </button>
        </div>
        @elseif($isManager)
        <div class="inline-flex bg-gray-100 rounded-xl p-1">
            <button onclick="toggleView('all')" id="btnViewAll" class="px-4 py-1.5 text-xs font-semibold rounded-lg transition-all duration-200">
                <i class="fas fa-list text-[10px] mr-1"></i>All Tickets
            </button>
            <button onclick="toggleView('my')" id="btnViewMy" class="px-4 py-1.5 text-xs font-semibold rounded-lg transition-all duration-200">
                <i class="fas fa-user text-[10px] mr-1"></i>My Tickets
            </button>
        </div>
        @elseif($isSupportUser)
        <div class="inline-flex bg-gray-100 rounded-xl p-1">
            <button onclick="toggleView('my')" id="btnViewMy" class="px-4 py-1.5 text-xs font-semibold rounded-lg transition-all duration-200">
                <i class="fas fa-user text-[10px] mr-1"></i>My Tickets
            </button>
            <button onclick="toggleView('unassign')" id="btnViewUnassign" class="px-4 py-1.5 text-xs font-semibold rounded-lg transition-all duration-200">
                <i class="fas fa-user-clock text-[10px] mr-1"></i>Unassign
            </button>
        </div>
        @endif
    </div>
</div>

<!-- Stats Cards - Simple Minimalist Design -->
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-8">
    <!-- Total Tickets -->
    <div id="filterAll" class="bg-white rounded-lg border-2 border-red-600 p-4 hover:shadow-md transition-all duration-200 cursor-pointer" onclick="filterTickets('all')">
        <p class="text-xs font-medium text-gray-500 mb-1">Total</p>
        <p class="text-2xl font-bold text-gray-900" id="totalCount">0</p>
    </div>

    <!-- Open -->
    <div id="filterOpen" class="bg-white rounded-lg border border-gray-200 p-4 hover:shadow-md hover:border-red-400 transition-all duration-200 cursor-pointer" onclick="filterTickets('open')">
        <p class="text-xs font-medium text-gray-500 mb-1">Open</p>
        <p class="text-2xl font-bold text-gray-900" id="openCount">0</p>
    </div>

    <!-- Inprocess -->
    <div id="filterInprocess" class="bg-white rounded-lg border border-gray-200 p-4 hover:shadow-md hover:border-red-400 transition-all duration-200 cursor-pointer" onclick="filterTickets('inprocess')">
        <p class="text-xs font-medium text-gray-500 mb-1">Inprocess</p>
        <p class="text-2xl font-bold text-gray-900" id="inprocessCount">0</p>
    </div>

    <!-- Waiting -->
    <div id="filterWaiting" class="bg-white rounded-lg border border-gray-200 p-4 hover:shadow-md hover:border-red-400 transition-all duration-200 cursor-pointer" onclick="filterTickets('waiting')">
        <p class="text-xs font-medium text-gray-500 mb-1">Waiting</p>
        <p class="text-2xl font-bold text-gray-900" id="waitingCount">0</p>
    </div>

    <!-- Hold -->
    <div id="filterHold" class="bg-white rounded-lg border border-gray-200 p-4 hover:shadow-md hover:border-red-400 transition-all duration-200 cursor-pointer" onclick="filterTickets('hold')">
        <p class="text-xs font-medium text-gray-500 mb-1">Hold</p>
        <p class="text-2xl font-bold text-gray-900" id="holdCount">0</p>
    </div>

    <!-- Closed -->
    <div id="filterClosed" class="bg-white rounded-lg border border-gray-200 p-4 hover:shadow-md hover:border-red-400 transition-all duration-200 cursor-pointer" onclick="filterTickets('closed')">
        <p class="text-xs font-medium text-gray-500 mb-1">Closed</p>
        <p class="text-2xl font-bold text-gray-900" id="closedCount">0</p>
    </div>
</div>

<!-- Advanced Filters - Clean Modern Design -->
<div class="bg-white rounded-2xl border border-gray-200 p-6 mb-8 shadow-sm">
    <h3 class="text-base font-bold text-gray-900 mb-5">Filters & Search</h3>
    
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="flex flex-col">
            <label class="text-xs font-semibold text-gray-600 mb-2 uppercase tracking-wide">Filter By</label>
            <div class="custom-dd relative w-full" id="ddFilterType" data-onchange="updateFilterOptions">
                <button type="button" class="custom-dd-btn w-full flex items-center justify-between gap-2 px-4 py-3 border border-gray-300 rounded-xl text-sm font-medium bg-white hover:border-gray-400 transition-all">
                    <span class="custom-dd-label text-gray-500">Select Type</span>
                    <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-all duration-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <input type="hidden" id="filterTypeSelect" value="">
                <div class="custom-dd-panel hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] py-1.5 overflow-y-auto" style="max-height:220px;min-width:160px;">
                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="">Select Type</button>
                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="status">Status</button>
                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="type">Type</button>
                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="priority">Priority</button>
                </div>
            </div>
        </div>

        <div class="flex flex-col">
            <label class="text-xs font-semibold text-gray-600 mb-2 uppercase tracking-wide">Filter Value</label>
            <div class="custom-dd relative w-full" id="ddFilterValue">
                <button type="button" id="ddFilterValueBtn" class="custom-dd-btn w-full flex items-center justify-between gap-2 px-4 py-3 border border-gray-300 rounded-xl text-sm font-medium bg-gray-50 text-gray-400 cursor-not-allowed transition-all" disabled>
                    <span class="custom-dd-label text-gray-400">Select Type First</span>
                    <svg class="custom-dd-arrow w-4 h-4 text-gray-300 transition-all duration-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <input type="hidden" id="filterValueSelect" value="">
                <div class="custom-dd-panel hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] py-1.5 overflow-y-auto" style="max-height:220px;min-width:160px;">
                </div>
            </div>
        </div>
        
        <div class="flex flex-col md:col-span-2">
            <label class="text-xs font-semibold text-gray-600 mb-2 uppercase tracking-wide">Search Tickets</label>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-gray-400">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                </div>
                <input type="text" id="searchInput" placeholder="Search by ID, description, customer, PIC..." 
                    autocomplete="off"
                    class="w-full pl-12 pr-4 py-3 border border-gray-300 rounded-xl text-sm font-medium focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent bg-white transition-all hover:border-gray-400"
                    onkeyup="searchTickets()">
            </div>
        </div>
    </div>
    
    <div class="flex gap-3 justify-end mt-5 pt-5 border-t border-gray-100">
        <button onclick="applyAdvancedFilters()" class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
            Apply Filters
        </button>
        <button onclick="resetFilters()" class="inline-flex items-center px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all duration-200">
            Reset
        </button>
    </div>
</div>

<!-- Pagination Controls - Centered -->
<div class="flex items-center justify-center mb-6">
    <div class="flex items-center gap-2 bg-white border border-gray-200 rounded-lg p-1.5 shadow-sm">
        <button onclick="previousPage()" id="btnPrevPage" disabled class="inline-flex items-center justify-center w-9 h-9 rounded-md text-gray-600 hover:bg-gray-100 disabled:opacity-30 disabled:cursor-not-allowed transition-all duration-200">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
            </svg>
        </button>
        
        <div class="px-4 py-1.5">
            <span class="text-sm font-medium text-gray-700">
                <span id="currentRangeStart">1</span>-<span id="currentRangeEnd">20</span>
            </span>
            <span class="text-sm text-gray-400 mx-1.5">of</span>
            <span class="text-sm font-medium text-gray-700" id="totalItems">0</span>
        </div>
        
        <button onclick="nextPage()" id="btnNextPage" class="inline-flex items-center justify-center w-9 h-9 rounded-md text-gray-600 hover:bg-gray-100 disabled:opacity-30 disabled:cursor-not-allowed transition-all duration-200">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
            </svg>
        </button>
    </div>
</div>

<!-- NEW: Tickets List - Compact Card Style (Similar to image) -->
<div id="ticketsContainer" class="space-y-3">
    <div id="ticketsListBody">
        <!-- Tickets will be loaded here via JavaScript -->
    </div>
</div>

<!-- Loading State -->
<div id="loadingState" class="text-center py-20 bg-white rounded-xl border border-gray-200 shadow-sm">
    <div class="inline-flex items-center justify-center w-16 h-16 bg-red-50 rounded-full mb-4">
        <svg class="animate-spin h-10 w-10 text-red-800" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
    </div>
    <p class="text-gray-600 text-lg font-semibold">Loading tickets...</p>
    <p class="text-gray-500 text-sm mt-2">Please wait while we fetch your data</p>
</div>

<!-- Empty State -->
<div id="emptyState" class="hidden text-center py-20 bg-white rounded-xl border border-gray-200 shadow-sm">
    <div class="inline-flex items-center justify-center w-20 h-20 bg-gray-100 rounded-full mb-4">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 text-gray-400">
            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
        </svg>
    </div>
    <p class="text-gray-700 text-xl font-bold mb-2">No tickets found</p>
    <p class="text-gray-500 text-sm mb-6">Try adjusting your filters or search criteria</p>
    <button onclick="resetFilters()" class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
        Clear All Filters
    </button>
</div>

<!-- Ticket Detail Modal - Chat Style -->
<div id="ticketDetailModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 overflow-hidden">
    <div class="h-full flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl w-full max-w-7xl h-[90vh] flex flex-col shadow-2xl">
            <!-- Modal Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-red-600 rounded-full flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-white">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900" id="modalTicketTitle">Ticket Details</h3>
                        <p class="text-sm text-gray-500">Reported via email</p>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <div id="modalSupportTeam" class="hidden items-center gap-2 px-3 py-1.5 bg-gray-100 rounded-lg">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-gray-500 flex-shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                        </svg>
                        <span class="text-sm font-medium text-gray-700" id="modalSupportTeamNames"></span>
                    </div>
                    <button onclick="closeTicketDetail()" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-gray-600">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Modal Body - Split Layout -->
            <div class="flex-1 flex overflow-hidden">
                <!-- Left: Chat/Messages Area -->
                <div class="flex-1 flex flex-col border-r border-gray-200">
                    <!-- Messages Container -->
                    <div class="flex-1 overflow-y-auto p-6 space-y-4" id="ticketMessagesContainer">
                        <!-- Messages will be loaded here -->
                    </div>
                </div>

                <!-- Right: Properties Panel -->
                <div class="w-80 bg-gray-50 p-6 overflow-y-auto">
                    <h4 class="text-sm font-bold text-gray-900 mb-4 uppercase tracking-wide">Properties</h4>
                    
                    <div class="space-y-4">
                        <!-- Status -->
                        <div>
                            <label class="text-xs font-semibold text-gray-600 mb-2 block">Status</label>
                            <select id="detailStatus" disabled class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white">
                                <option>Open</option>
                            </select>
                        </div>

                        <!-- Priority -->
                        <div>
                            <label class="text-xs font-semibold text-gray-600 mb-2 block">Priority</label>
                            <select id="detailPriority" disabled class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white">
                                <option>Low</option>
                            </select>
                        </div>

                        <!-- Group -->
                        <div>
                            <label class="text-xs font-semibold text-gray-600 mb-2 block">Group</label>
                            <select id="detailGroup" disabled class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white">
                                <option>Customer Support</option>
                            </select>
                        </div>

                        <!-- Agent -->
                        <div>
                            <label class="text-xs font-semibold text-gray-600 mb-2 block">Agent</label>
                            <select id="detailAgent" disabled class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white">
                                <option>Unassigned</option>
                            </select>
                        </div>

                        <!-- Customer -->
                        <div>
                            <label class="text-xs font-semibold text-gray-600 mb-2 block">Customer</label>
                            <input type="text" id="detailCustomer" disabled class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white text-gray-700">
                        </div>

                        <!-- Type -->
                        <div>
                            <label class="text-xs font-semibold text-gray-600 mb-2 block">Type</label>
                            <input type="text" id="detailType" disabled class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white text-gray-700">
                        </div>

                        <!-- Man Days -->
                        <div>
                            <label class="text-xs font-semibold text-gray-600 mb-2 block">Man Days</label>
                            <input type="text" id="detailManDays" disabled class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white text-gray-700">
                        </div>

                        <!-- Created Date -->
                        <div>
                            <label class="text-xs font-semibold text-gray-600 mb-2 block">Created</label>
                            <input type="text" id="detailCreated" disabled class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white text-gray-700">
                        </div>

                        <!-- Due Date -->
                        <div>
                            <label class="text-xs font-semibold text-gray-600 mb-2 block">Due Date</label>
                            <input type="text" id="detailDueDate" disabled class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white text-gray-700">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Clean Modern Card-Based Ticket Styles */
.ticket-card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 1rem;
    padding: 1.25rem 1.5rem;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
}

.ticket-card:hover {
    background: #fafafa;
    border-color: #1f2937;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
    transform: translateY(-2px);
}

.ticket-card-header {
    display: flex;
    align-items: flex-start;
    gap: 1.25rem;
}

.ticket-avatar {
    width: 2.75rem;
    height: 2.75rem;
    border-radius: 50%;
    background: linear-gradient(135deg, #1f2937, #111827);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.875rem;
    flex-shrink: 0;
    letter-spacing: -0.025em;
}

.ticket-content {
    flex: 1;
    min-width: 0;
}

.ticket-title-row {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    margin-bottom: 0.5rem;
    flex-wrap: wrap;
}

.ticket-title {
    font-weight: 600;
    color: #111827;
    font-size: 0.9375rem;
    line-height: 1.5;
    letter-spacing: -0.01em;
}

.ticket-id {
    color: #6b7280;
    font-size: 0.8125rem;
    font-weight: 500;
}

.ticket-meta {
    display: flex;
    align-items: center;
    gap: 0.875rem;
    color: #6b7280;
    font-size: 0.8125rem;
    margin-bottom: 0.75rem;
    flex-wrap: wrap;
}

.ticket-meta-item {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
}

.ticket-meta-separator::before {
    content: "•";
    margin: 0 0.25rem;
    color: #d1d5db;
}

.ticket-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.25rem;
    flex-wrap: wrap;
}

.ticket-footer-left {
    display: flex;
    align-items: center;
    gap: 0.875rem;
    flex-wrap: wrap;
}

.ticket-footer-right {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    flex-wrap: wrap;
}

/* Badge Styles - Clean & Modern */
.badge {
    display: inline-flex;
    align-items: center;
    padding: 0.375rem 0.75rem;
    border-radius: 0.625rem;
    font-size: 0.75rem;
    font-weight: 600;
    white-space: nowrap;
    letter-spacing: 0.01em;
}

.badge-new {
    background: #dcfce7;
    color: #166534;
}

.badge-low {
    background: #f3f4f6;
    color: #4b5563;
}

.badge-medium {
    background: #dbeafe;
    color: #1e40af;
}

.badge-high {
    background: #fee2e2;
    color: #991b1b;
}

.badge-pending {
    background: #fef3c7;
    color: #92400e;
}

.badge-open {
    background: #dbeafe;
    color: #1e40af;
}

.badge-customer-responded {
    background: #e0e7ff;
    color: #4338ca;
}

/* View Toggle Buttons */
#btnViewAll, #btnViewMy, #btnViewUnassign {
    background: transparent;
    color: #9ca3af;
    font-size: 12px;
}

#btnViewAll.active, #btnViewMy.active, #btnViewUnassign.active {
    background: white;
    color: #111827;
    font-weight: 700;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.12);
}

/* Responsive Design */
@media (max-width: 768px) {
    .ticket-card {
        padding: 1rem 1.25rem;
    }
    
    .ticket-card-header {
        gap: 1rem;
    }
    
    .ticket-avatar {
        width: 2.5rem;
        height: 2.5rem;
    }
    
    .ticket-footer {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
    }
}
</style>

@php $customDdVer = file_exists(public_path('js/custom-dropdown.js')) ? filemtime(public_path('js/custom-dropdown.js')) : 1; @endphp
<script src="/js/custom-dropdown.js?v={{ $customDdVer }}"></script>

<script>
    let allTickets = [];
    let filteredTickets = [];
    let currentFilter = 'all';
    let itemsPerPage = 20;
    let currentPage = 1;
    let totalItems = 0;
    let totalPages = 0;
    let userRoleIds = {!! json_encode(array_map('intval', session('user')['role_ids'] ?? [session('user')['role']['id'] ?? 0])) !!};
    let userId = {{ session('user')['id'] ?? 0 }};
    const EC_ADMINISTRATOR_ROLE         = {{ \App\Enums\RoleId::EC_ADMINISTRATOR->value }};
    const DELIVERY_SUPPORT_USER_ROLE    = {{ \App\Enums\RoleId::DELIVERY_SUPPORT_USER->value }};
    const EC_USER_ROLE                  = {{ \App\Enums\RoleId::EC_USER->value }};
    const DELIVERY_SUPPORT_HEAD_ROLE    = {{ \App\Enums\RoleId::DELIVERY_SUPPORT_HEAD->value }};
    const DELIVERY_SUPPORT_MANAGER_ROLE = {{ \App\Enums\RoleId::DELIVERY_SUPPORT_MANAGER->value }};
    const hasRole = (id) => userRoleIds.includes(id);
    // Admin/Head defaults to 'all'; Support User or Manager (without head/admin) defaults to 'my'
    let currentView = (hasRole(EC_ADMINISTRATOR_ROLE) || hasRole(DELIVERY_SUPPORT_HEAD_ROLE)) ? 'all'
        : (hasRole(DELIVERY_SUPPORT_USER_ROLE) || hasRole(DELIVERY_SUPPORT_MANAGER_ROLE)) ? 'my' : 'all';

    document.addEventListener('DOMContentLoaded', function() {
        initCustomDropdowns();
        loadTickets();

        if (hasRole(DELIVERY_SUPPORT_USER_ROLE) || hasRole(EC_ADMINISTRATOR_ROLE) || hasRole(DELIVERY_SUPPORT_HEAD_ROLE) || hasRole(DELIVERY_SUPPORT_MANAGER_ROLE)) {
            updateViewToggle();
        }
    });

    function toggleView(view) {
        currentView = view;
        updateViewToggle();
        loadTickets();
    }

    function updateViewToggle() {
        const btnAll      = document.getElementById('btnViewAll');
        const btnMy       = document.getElementById('btnViewMy');
        const btnUnassign = document.getElementById('btnViewUnassign');

        if (hasRole(EC_ADMINISTRATOR_ROLE) || hasRole(DELIVERY_SUPPORT_HEAD_ROLE)) {
            if (btnAll)      btnAll.classList.toggle('active',      currentView === 'all');
            if (btnUnassign) btnUnassign.classList.toggle('active', currentView === 'unassign');
        } else if (hasRole(DELIVERY_SUPPORT_MANAGER_ROLE)) {
            if (btnMy)  btnMy.classList.toggle('active',  currentView === 'my');
            if (btnAll) btnAll.classList.toggle('active', currentView === 'all');
        } else if (hasRole(DELIVERY_SUPPORT_USER_ROLE)) {
            if (btnMy)       btnMy.classList.toggle('active',       currentView === 'my');
            if (btnUnassign) btnUnassign.classList.toggle('active', currentView === 'unassign');
        }
    }

    async function loadTickets() {
        try {
            document.getElementById('loadingState').classList.remove('hidden');
            document.getElementById('ticketsContainer').classList.add('hidden');
            document.getElementById('emptyState').classList.add('hidden');

            // Determine endpoint based on role and current view
            let endpoint = '/api/tickets';

            if (hasRole(EC_ADMINISTRATOR_ROLE) || hasRole(DELIVERY_SUPPORT_HEAD_ROLE)) {
                // Admin / Support Head: all tickets or unassigned view
                endpoint = (currentView === 'unassign') ? '/api/tickets?unassigned=1' : '/api/tickets';
            } else if (hasRole(DELIVERY_SUPPORT_MANAGER_ROLE)) {
                // Support Manager: "My Tickets" or "All Tickets"
                endpoint = (currentView === 'my') ? '/api/tickets/my' : '/api/tickets';
            } else if (hasRole(DELIVERY_SUPPORT_USER_ROLE)) {
                // Support User: "My Tickets" or "Unassign"
                endpoint = (currentView === 'my') ? '/api/tickets/my' : '/api/tickets';
            } else if (hasRole(EC_USER_ROLE)) {
                // EC User — only their own tickets
                endpoint = '/api/tickets/my';
            }
            // Other views / roles use /api/tickets (all tickets)
            
            const response = await fetch(endpoint, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });
            
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                throw new Error('Server returned non-JSON response');
            }
            
            const data = await response.json();
            
            if (data.success) {
                allTickets = data.data.sort((a, b) => {
                    return new Date(b.created_at) - new Date(a.created_at);
                });
                filteredTickets = allTickets;
                updateStats();
                renderTickets();
            } else {
                showNotification('Failed to load tickets: ' + (data.message || 'Unknown error'), 'error');
                document.getElementById('loadingState').classList.add('hidden');
                document.getElementById('emptyState').classList.remove('hidden');
            }
        } catch (error) {
            console.error('Error loading tickets:', error);
            showNotification('Failed to load tickets: ' + error.message, 'error');
            document.getElementById('loadingState').classList.add('hidden');
            document.getElementById('emptyState').classList.remove('hidden');
        }
    }

    function updateStats() {
        const total     = allTickets.length;
        const open      = allTickets.filter(t => t.status === 'open').length;
        const inprocess = allTickets.filter(t => t.status === 'inprocess').length;
        const waiting   = allTickets.filter(t => ['waiting_on_customer','waiting_on_3rd_party','waiting_to_confirmation'].includes(t.status)).length;
        const hold      = allTickets.filter(t => t.status === 'hold').length;
        const closed    = allTickets.filter(t => t.status === 'closed').length;

        document.getElementById('totalCount').textContent    = total;
        document.getElementById('openCount').textContent     = open;
        document.getElementById('inprocessCount').textContent = inprocess;
        document.getElementById('waitingCount').textContent  = waiting;
        document.getElementById('holdCount').textContent     = hold;
        document.getElementById('closedCount').textContent   = closed;
    }

    function renderTickets() {
        const listBody = document.getElementById('ticketsListBody');
        const loadingState = document.getElementById('loadingState');
        const emptyState = document.getElementById('emptyState');
        const container = document.getElementById('ticketsContainer');

        loadingState.classList.add('hidden');
        totalItems = filteredTickets.length;
        totalPages = Math.ceil(totalItems / itemsPerPage);

        if (filteredTickets.length === 0) {
            container.classList.add('hidden');
            emptyState.classList.remove('hidden');
            updatePaginationDisplay();
            return;
        }

        container.classList.remove('hidden');
        emptyState.classList.add('hidden');

        const startIndex = (currentPage - 1) * itemsPerPage;
        const endIndex = Math.min(startIndex + itemsPerPage, totalItems);
        const paginatedTickets = filteredTickets.slice(startIndex, endIndex);

        listBody.innerHTML = paginatedTickets.map(ticket => createTicketCard(ticket)).join('');

        updatePaginationDisplay();
    }

    function updatePaginationDisplay() {
        const startIndex = (currentPage - 1) * itemsPerPage + 1;
        const endIndex = Math.min(currentPage * itemsPerPage, totalItems);
        
        const rangeStartEl = document.getElementById('currentRangeStart');
        const rangeEndEl = document.getElementById('currentRangeEnd');
        const totalItemsEl = document.getElementById('totalItems');
        
        if (rangeStartEl) rangeStartEl.textContent = totalItems > 0 ? startIndex : 0;
        if (rangeEndEl) rangeEndEl.textContent = endIndex;
        if (totalItemsEl) totalItemsEl.textContent = totalItems;
        
        const btnPrev = document.getElementById('btnPrevPage');
        const btnNext = document.getElementById('btnNextPage');

        if (btnPrev) btnPrev.disabled = currentPage === 1;
        if (btnNext) btnNext.disabled = currentPage >= totalPages;
    }

    function previousPage() {
        if (currentPage > 1) {
            currentPage--;
            renderTickets();
        }
    }

    function nextPage() {
        if (currentPage < totalPages) {
            currentPage++;
            renderTickets();
        }
    }

    function createTicketCard(ticket) {
        // Customer info
        const customerName = ticket.customer?.customer_name || 'Unknown';
        const companyName = ticket.customer?.company_name || '';
        
        // Format dates
        const createdDate = new Date(ticket.created_at);
        const timeAgo = formatTimeAgo(createdDate);
        
        // Calculate due date status
        const dueDate = ticket.end_date ? new Date(ticket.end_date) : null;
        let dueText = '';
        let isDueSoon = false;
        let isOverdue = false;
        
        if (dueDate) {
            const now = new Date();
            const diffMs = dueDate - now;
            const diffHours = Math.floor(diffMs / 3600000);
            const diffDays = Math.floor(diffMs / 86400000);
            
            if (diffMs < 0) {
                isOverdue = true;
                const overdueMins = Math.abs(Math.floor(diffMs / 60000));
                const overdueHours = Math.abs(diffHours);
                const overdueDays = Math.abs(diffDays);
                
                if (overdueDays > 0) {
                    dueText = `${overdueDays} day${overdueDays > 1 ? 's' : ''}`;
                } else if (overdueHours > 0) {
                    dueText = `${overdueHours} hour${overdueHours > 1 ? 's' : ''}`;
                } else {
                    dueText = `${overdueMins} minute${overdueMins > 1 ? 's' : ''}`;
                }
            } else {
                if (diffDays < 1) {
                    isDueSoon = true;
                    dueText = `${diffHours} hour${diffHours > 1 ? 's' : ''}`;
                } else {
                    dueText = `${diffDays} day${diffDays > 1 ? 's' : ''}`;
                }
            }
        }
        
        // Status badge
        let statusBadge = '';
        if (isOverdue) {
            statusBadge = '<span class="inline-block px-2.5 py-0.5 rounded-md text-xs font-medium bg-red-50 text-red-700 border border-red-200">Overdue</span>';
        } else if (ticket.status === 'inprocess') {
            statusBadge = '<span class="inline-block px-2.5 py-0.5 rounded-md text-xs font-medium bg-pink-50 text-pink-700 border border-pink-200">First response due</span>';
        } else if (ticket.status === 'waiting_on_customer') {
            statusBadge = '<span class="inline-block px-2.5 py-0.5 rounded-md text-xs font-medium bg-pink-50 text-pink-700 border border-pink-200">Waiting on customer</span>';
        }
        
        // Priority indicator
        const priorityDots = {
            'Low': 'bg-green-500',
            'Medium': 'bg-blue-500',
            'High': 'bg-red-500'
        };
        const priorityDot = priorityDots[ticket.ticket_priority] || 'bg-blue-500';
        
        // Agent info
        const agentName = ticket.employee?.employee_name || 'Unassigned';
        const agentSuffix = ticket.employee?.employee_id ? ' / --' : '';
        
        // Status
        const statusMap = {
            'open':                    'Open',
            'inprocess':               'Inprocess',
            'waiting_on_customer':     'Waiting on Customer',
            'waiting_on_3rd_party':    'Waiting on 3rd Party',
            'waiting_to_confirmation': 'Waiting to Confirmation',
            'hold':                    'Hold',
            'cancelled':               'Cancelled',
            'closed':                  'Closed',
        };
        const statusLabel = statusMap[ticket.status] || ucfirst(ticket.status || 'Open');
        
        return `
            <div class="group bg-white border border-gray-200 rounded-lg hover:border-gray-300 hover:shadow-sm transition-all duration-200 cursor-pointer" onclick="viewTicketDetail(${ticket.ticket_id})">
                <div class="flex items-start gap-3 p-4">
                    <!-- Checkbox -->
                    <div class="flex-shrink-0 pt-1">
                        <input type="checkbox" 
                            class="w-4 h-4 text-red-600 border-gray-300 rounded focus:ring-2 focus:ring-red-500 focus:ring-offset-0" 
                            onclick="event.stopPropagation()">
                    </div>
                    
                    <!-- Avatar -->
                    <div class="flex-shrink-0">
                        <img 
                            src="https://ui-avatars.com/api/?name=${encodeURIComponent(customerName)}&background=6b7280&color=fff&size=44&rounded=true" 
                            alt="${customerName}" 
                            class="w-11 h-11 rounded-lg">
                    </div>
                    
                    <!-- Content Area -->
                    <div class="flex-1 min-w-0">
                        <!-- Status Badge -->
                        ${statusBadge ? `<div class="mb-2">${statusBadge}</div>` : ''}
                        
                        <!-- Title -->
                        <h3 class="text-base font-semibold text-gray-900 mb-2 line-clamp-1 group-hover:text-gray-700 transition-colors">
                            ${ticket.ticket_number ? ticket.ticket_number + ' — ' : ''}${ticket.description || 'No description'}
                        </h3>
                        
                        <!-- Meta Information -->
                        <div class="flex items-center flex-wrap gap-x-2 gap-y-1 text-sm text-gray-500">
                            <!-- Email Icon + Customer Name -->
                            <div class="flex items-center gap-1.5">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 flex-shrink-0">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                                </svg>
                                <span class="font-medium">${customerName}</span>
                                ${companyName ? `<span class="text-gray-400">(${companyName})</span>` : ''}
                            </div>
                            
                            <span class="text-gray-300">•</span>
                            
                            <!-- Created -->
                            <span>Created: ${timeAgo}</span>
                            
                            ${dueText ? `
                                <span class="text-gray-300">•</span>
                                <span class="${isOverdue ? 'text-red-600 font-medium' : ''}">
                                    ${isOverdue ? 'Overdue by: ' : 'Due in: '}${dueText}
                                </span>
                            ` : ''}
                        </div>
                    </div>
                    
                    <!-- Right Info Column -->
                    <div class="flex flex-col items-end gap-2.5 flex-shrink-0 min-w-[160px]">
                        <!-- Priority -->
                        <div class="flex items-center gap-1.5">
                            <div class="w-2 h-2 rounded-full ${priorityDot}"></div>
                            <span class="text-sm font-medium text-gray-700">${ticket.ticket_priority || 'Medium'}</span>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5 text-gray-400">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                            </svg>
                        </div>
                        
                        <!-- Agent/PIC -->
                        <div class="flex items-center gap-1.5 text-sm text-gray-600">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 flex-shrink-0">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                            </svg>
                            <span class="truncate max-w-[110px]">${agentName}${agentSuffix}</span>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5 text-gray-400">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                            </svg>
                        </div>
                        
                        <!-- Status -->
                        <div class="flex items-center gap-1.5 text-sm text-gray-600">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 flex-shrink-0">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <span>${statusLabel}</span>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5 text-gray-400">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    function formatTimeAgo(date) {
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);
        
        if (diffMins < 1) return 'just now';
        if (diffMins < 60) return `${diffMins} minute${diffMins > 1 ? 's' : ''} ago`;
        if (diffHours < 24) return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
        if (diffDays < 7) return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
        
        return date.toLocaleDateString('en-GB', { timeZone: 'Asia/Jakarta', day: 'numeric', month: 'short', year: 'numeric' });
    }

    function formatDueDate(date) {
        const now = new Date();
        const diffMs = date - now;
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);
        
        if (diffHours < 0) return 'overdue';
        if (diffHours < 24) return `in ${diffHours} hours`;
        if (diffDays < 7) return `in ${diffDays} days`;
        if (diffDays < 30) return `in ${Math.floor(diffDays / 7)} weeks`;
        
        return `in ${Math.floor(diffDays / 30)} months`;
    }

    function filterTickets(status) {
        currentFilter = status;

        // Reset all filter styles
        ['filterAll', 'filterOpen', 'filterInprocess', 'filterWaiting', 'filterHold', 'filterClosed'].forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            el.classList.remove('border-red-600', 'shadow-md');
            el.classList.add('border-gray-200');
        });

        // Apply active filter style (red border)
        const filterMap = {
            'all':      'filterAll',
            'open':     'filterOpen',
            'inprocess':'filterInprocess',
            'waiting':  'filterWaiting',
            'hold':     'filterHold',
            'closed':   'filterClosed',
        };

        if (filterMap[status]) {
            const filterElement = document.getElementById(filterMap[status]);
            if (filterElement) {
                filterElement.classList.remove('border-gray-200');
                filterElement.classList.add('border-red-600', 'shadow-md');
            }
        }

        // Apply filter
        if (status === 'all') {
            filteredTickets = allTickets;
        } else if (status === 'waiting') {
            filteredTickets = allTickets.filter(t => ['waiting_on_customer','waiting_on_3rd_party','waiting_to_confirmation'].includes(t.status));
        } else {
            filteredTickets = allTickets.filter(ticket => ticket.status === status);
        }

        currentPage = 1;
        renderTickets();
    }

    function viewTicketDetail(ticketId) {
        const ticket = allTickets.find(t => t.ticket_id === ticketId);
        if (!ticket) return;
        
        // Set modal title
        document.getElementById('modalTicketTitle').textContent = ticket.description || 'Ticket Details';
        
        // Create initial message
        const customerName = ticket.customer?.customer_name || 'Customer';
        const employeeName = ticket.employee?.employee_name || 'Unassigned';
        const createdDate = new Date(ticket.created_at).toLocaleString('en-GB', {
            timeZone: 'Asia/Jakarta',
            weekday: 'short',
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
        });
        
        const messagesContainer = document.getElementById('ticketMessagesContainer');
        messagesContainer.innerHTML = `
            <!-- Initial Report -->
            <div class="flex gap-3">
                <div class="w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center flex-shrink-0">
                    <span class="text-xs font-bold text-white">${customerName.substring(0, 1)}</span>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="font-semibold text-sm text-gray-900">${customerName}</span>
                        <span class="text-xs text-gray-500">reported via email • ${createdDate}</span>
                    </div>
                    <div class="bg-white border border-gray-200 rounded-lg p-4">
                        <p class="text-sm text-gray-700 mb-2">To: "Eclectic Consulting" &lt;support@eclecticconsulting.com&gt;</p>
                        <p class="text-sm text-gray-900 leading-relaxed">${ticket.description || 'No description provided'}</p>
                    </div>
                </div>
            </div>

            <!-- Response (if employee assigned) -->
            ${ticket.ticket_lead_id ? `
                <div class="flex gap-3">
                    <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center flex-shrink-0">
                        <span class="text-xs font-bold text-white">${employeeName.substring(0, 1)}</span>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="font-semibold text-sm text-gray-900">${employeeName}</span>
                            <span class="text-xs text-gray-500">replied</span>
                        </div>
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <p class="text-sm text-gray-700 mb-2">Status: ${statusMap[ticket.status] || ticket.status}</p>
                            <p class="text-sm text-gray-900">This ticket has been assigned and is being processed.</p>
                        </div>
                    </div>
                </div>
            ` : ''}
        `;
        
        // Set properties
        const statusLabels = {
            'open':                    'Open',
            'inprocess':               'Inprocess',
            'waiting_on_customer':     'Waiting on Customer',
            'waiting_on_3rd_party':    'Waiting on 3rd Party',
            'waiting_to_confirmation': 'Waiting to Confirmation',
            'hold':                    'Hold',
            'cancelled':               'Cancelled',
            'closed':                  'Closed',
        };
        document.getElementById('detailStatus').innerHTML = `<option>${statusLabels[ticket.status] || ticket.status || 'Open'}</option>`;
        document.getElementById('detailPriority').innerHTML = `<option>${ticket.ticket_priority || 'Medium'}</option>`;
        document.getElementById('detailGroup').innerHTML = `<option>Customer Support</option>`;
        document.getElementById('detailAgent').innerHTML = `<option>${ticket.employee?.employee_name || 'Unassigned'}</option>`;
        document.getElementById('detailCustomer').value = ticket.customer?.customer_name || 'N/A';
        document.getElementById('detailType').value = ticket.type || 'N/A';
        document.getElementById('detailManDays').value = ticket.man_days ? parseFloat(ticket.man_days).toFixed(1) + ' days' : 'N/A';
        document.getElementById('detailCreated').value = new Date(ticket.created_at).toLocaleString('en-GB', {
            timeZone: 'Asia/Jakarta',
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
        });
        document.getElementById('detailDueDate').value = ticket.end_date ? new Date(ticket.end_date).toLocaleDateString('en-GB', {
            timeZone: 'Asia/Jakarta',
            day: 'numeric',
            month: 'short',
            year: 'numeric'
        }) : 'No due date';
        
        // Support team names in header
        const supportTeamEl    = document.getElementById('modalSupportTeam');
        const supportTeamNames = document.getElementById('modalSupportTeamNames');
        const managerName = ticket.support_manager;
        const adminName   = ticket.support_admin;
        if (managerName || adminName) {
            const parts = [managerName, adminName].filter(Boolean);
            supportTeamNames.textContent = parts.join(' / ');
            supportTeamEl.classList.remove('hidden');
            supportTeamEl.classList.add('flex');
        } else {
            supportTeamEl.classList.add('hidden');
            supportTeamEl.classList.remove('flex');
        }

        document.getElementById('ticketDetailModal').classList.remove('hidden');
    }

    function closeTicketDetail() {
        document.getElementById('ticketDetailModal').classList.add('hidden');
    }

    function searchTickets() {
        applyAdvancedFilters();
    }

    function updateFilterOptions() {
        const filterType = document.getElementById('filterTypeSelect').value;
        const btn        = document.getElementById('ddFilterValueBtn');
        const panel      = document.querySelector('#ddFilterValue .custom-dd-panel');

        const optionsMap = {
            'status':   ['open', 'inprocess', 'waiting_on_customer', 'waiting_on_3rd_party', 'waiting_to_confirmation', 'hold', 'cancelled', 'closed'],
            'type':     ['AMS', 'MO', 'ATS', 'Project', 'Internal'],
            'priority': ['Low', 'Medium', 'High'],
        };

        // Reset value
        setCustomDropdownValue('filterValueSelect', '');

        if (filterType && optionsMap[filterType]) {
            // Enable button
            btn.disabled = false;
            btn.className = 'custom-dd-btn w-full flex items-center justify-between gap-2 px-4 py-3 border border-gray-300 rounded-xl text-sm font-medium bg-white hover:border-gray-400 transition-all';

            // Rebuild panel items
            panel.innerHTML = '';
            const placeholder = document.createElement('button');
            placeholder.type = 'button';
            placeholder.className = 'custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50';
            placeholder.dataset.value = '';
            placeholder.textContent = 'Select value';
            panel.appendChild(placeholder);

            optionsMap[filterType].forEach(opt => {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50';
                item.dataset.value = opt;
                item.textContent = opt.charAt(0).toUpperCase() + opt.slice(1).replace(/_/g, ' ');
                panel.appendChild(item);
            });

            // Re-init the dd so new items are wired up
            const ddEl = document.getElementById('ddFilterValue');
            ddEl._ddInited = false;
            initCustomDropdowns(ddEl.parentElement);
        } else {
            // Disable button
            btn.disabled = true;
            btn.className = 'custom-dd-btn w-full flex items-center justify-between gap-2 px-4 py-3 border border-gray-300 rounded-xl text-sm font-medium bg-gray-50 text-gray-400 cursor-not-allowed transition-all';
            panel.innerHTML = '';
        }
    }

    function applyAdvancedFilters() {
        const filterType = document.getElementById('filterTypeSelect').value;
        const filterValue = document.getElementById('filterValueSelect').value;
        const searchTerm = document.getElementById('searchInput').value.toLowerCase();

        filteredTickets = allTickets.filter(ticket => {
            const matchesSearch = !searchTerm || 
                (ticket.ticket_id && ticket.ticket_id.toString().includes(searchTerm)) ||
                (ticket.description && ticket.description.toLowerCase().includes(searchTerm)) ||
                (ticket.customer?.customer_name && ticket.customer.customer_name.toLowerCase().includes(searchTerm)) ||
                (ticket.employee?.employee_name && ticket.employee.employee_name.toLowerCase().includes(searchTerm));

            let matchesFilter = true;
            if (filterType && filterValue) {
                matchesFilter = ticket[filterType] === filterValue;
            }

            // Apply status filter from stats cards
            let matchesStatusFilter = true;
            if (currentFilter !== 'all') {
                if (currentFilter === 'waiting') {
                    matchesStatusFilter = ['waiting_on_customer','waiting_on_3rd_party','waiting_to_confirmation'].includes(ticket.status);
                } else {
                    matchesStatusFilter = ticket.status === currentFilter;
                }
            }

            return matchesSearch && matchesFilter && matchesStatusFilter;
        });

        currentPage = 1;
        renderTickets();
    }

    function resetFilters() {
        document.getElementById('searchInput').value = '';
        setCustomDropdownValue('filterTypeSelect', '');
        // Reset & disable the filter value dd
        const btn   = document.getElementById('ddFilterValueBtn');
        const panel = document.querySelector('#ddFilterValue .custom-dd-panel');
        setCustomDropdownValue('filterValueSelect', '');
        btn.disabled = true;
        btn.className = 'custom-dd-btn w-full flex items-center justify-between gap-2 px-4 py-3 border border-gray-300 rounded-xl text-sm font-medium bg-gray-50 text-gray-400 cursor-not-allowed transition-all';
        panel.innerHTML = '';
        currentFilter = 'all';
        filterTickets('all');
    }


    // Event listeners
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeTicketDetail();
        }
    });

</script>

@endsection
@extends('dashboard')
@section('title', 'Delivery Support')
@section('content')

<!-- Modern Helpdesk Header -->
<div class="mb-6">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 tracking-tight mb-1">Support Tickets</h1>
            <p class="text-gray-500 text-sm">Manage and track all support requests</p>
        </div>

        <div class="flex items-center gap-3">
            @if($user->role->role_id == 1 || $user->role->role_id == 2)
            <div class="inline-flex bg-gray-100 rounded-xl p-1">
                <button onclick="toggleView('all')" id="btnViewAll" class="px-5 py-2 text-sm font-semibold rounded-lg transition-all duration-200">
                    All Tickets
                </button>
                <button onclick="toggleView('my')" id="btnViewMy" class="px-5 py-2 text-sm font-semibold rounded-lg transition-all duration-200">
                    My Tickets
                </button>
            </div>
            @endif

            @if($user->role->role_id == 1)
            <button onclick="openCreateTicketModal()" class="inline-flex items-center gap-2 px-4 py-2 bg-red-700 text-white text-sm font-semibold rounded-xl hover:bg-red-800 transition-all duration-200 shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Create Ticket
            </button>
            @endif
        </div>
    </div>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
    <div id="filterAll" class="bg-white rounded-lg border-2 border-red-600 p-3 hover:shadow-md transition-all duration-200 cursor-pointer" onclick="filterTickets('all')">
        <p class="text-xs font-medium text-gray-500 mb-1">Total</p>
        <p class="text-2xl font-bold text-gray-900" id="totalCount">0</p>
    </div>
    <div id="filterInProcess" class="bg-white rounded-lg border border-gray-200 p-3 hover:shadow-md hover:border-red-400 transition-all duration-200 cursor-pointer" onclick="filterTickets('in process')">
        <p class="text-xs font-medium text-gray-500 mb-1">In Process</p>
        <p class="text-2xl font-bold text-gray-900" id="processCount">0</p>
    </div>
    <div id="filterAuthorAction" class="bg-white rounded-lg border border-gray-200 p-3 hover:shadow-md hover:border-red-400 transition-all duration-200 cursor-pointer" onclick="filterTickets('author action')">
        <p class="text-xs font-medium text-gray-500 mb-1">Author Action</p>
        <p class="text-2xl font-bold text-gray-900" id="authorCount">0</p>
    </div>
    <div id="filterProposed" class="bg-white rounded-lg border border-gray-200 p-3 hover:shadow-md hover:border-red-400 transition-all duration-200 cursor-pointer" onclick="filterTickets('proposed solution')">
        <p class="text-xs font-medium text-gray-500 mb-1">Proposed</p>
        <p class="text-2xl font-bold text-gray-900" id="proposedCount">0</p>
    </div>
    <div id="filterSAP" class="bg-white rounded-lg border border-gray-200 p-3 hover:shadow-md hover:border-red-400 transition-all duration-200 cursor-pointer" onclick="filterTickets('sent in to SAP')">
        <p class="text-xs font-medium text-gray-500 mb-1">Sent to SAP</p>
        <p class="text-2xl font-bold text-gray-900" id="sapCount">0</p>
    </div>
    <div id="filterClosed" class="bg-white rounded-lg border border-gray-200 p-3 hover:shadow-md hover:border-red-400 transition-all duration-200 cursor-pointer" onclick="filterTickets('closed')">
        <p class="text-xs font-medium text-gray-500 mb-1">Closed</p>
        <p class="text-2xl font-bold text-gray-900" id="closedCount">0</p>
    </div>
</div>

<!-- Filters & Search -->
<div class="bg-white rounded-xl border border-gray-200 p-4 mb-6 shadow-sm">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
        <div class="flex flex-col">
            <label class="text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Filter By</label>
            <select id="filterTypeSelect" onchange="updateFilterOptions()" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent bg-white">
                <option value="">Select Type</option>
                <option value="jarvies_status">Jarvies Status</option>
                <option value="status">Status</option>
                <option value="ticket_type">Ticket Type</option>
                <option value="priority">Priority</option>
            </select>
        </div>
        <div class="flex flex-col">
            <label class="text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Filter Value</label>
            <select id="filterValueSelect" disabled class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent bg-white disabled:bg-gray-50 disabled:text-gray-400">
                <option value="">Select Type First</option>
            </select>
        </div>
        <div class="flex flex-col md:col-span-2">
            <label class="text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Search Tickets</label>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-gray-400">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                </div>
                <input type="text" id="searchInput" placeholder="Search by ticket number, description, customer, PIC..."
                    autocomplete="off"
                    class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent bg-white"
                    onkeyup="searchTickets()">
            </div>
        </div>
    </div>
    <div class="flex gap-2 justify-end mt-3 pt-3 border-t border-gray-100">
        <button onclick="applyAdvancedFilters()" class="inline-flex items-center gap-1.5 px-4 py-2 bg-gray-900 text-white text-sm font-semibold rounded-lg hover:bg-gray-800 transition-all duration-200">
            Apply
        </button>
        <button onclick="resetFilters()" class="inline-flex items-center gap-1.5 px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all duration-200">
            Reset
        </button>
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

<!-- Ticket List -->
<div id="ticketsContainer" class="space-y-2">
    <div id="ticketsListBody"></div>
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
    <button onclick="resetFilters()" class="px-4 py-2 bg-red-700 text-white text-sm font-semibold rounded-lg hover:bg-red-800">Clear Filters</button>
</div>

<!-- Create Ticket Modal (Admin) -->
@if($user->role->role_id == 1)
<div id="createTicketModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 overflow-hidden">
    <div class="h-full flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl w-full max-w-lg shadow-2xl">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Create New Ticket</h3>
                <button onclick="closeCreateTicketModal()" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-gray-600">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <form id="createTicketForm" onsubmit="submitCreateTicket(event)" class="p-6 space-y-4">
                <div>
                    <label class="text-xs font-semibold text-gray-600 mb-2 block uppercase tracking-wide">Customer</label>
                    <div class="relative">
                        <input type="text" id="customerSearch"
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-gray-900"
                            placeholder="Search customer..."
                            autocomplete="off"
                            onfocus="showCustomerDropdown()"
                            oninput="filterCustomers()">
                        <input type="hidden" id="newCustomerId" required>
                        <div id="customerDropdown" class="hidden absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-xl shadow-lg max-h-48 overflow-y-auto">
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
                    <textarea id="newDescription" required rows="3" class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-gray-900" placeholder="Describe the issue..."></textarea>
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600 mb-2 block uppercase tracking-wide">Priority</label>
                    <select id="newPriority" required class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-gray-900">
                        <option value="Low">Low</option>
                        <option value="Medium" selected>Medium</option>
                        <option value="High">High</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600 mb-2 block uppercase tracking-wide">Ticket Type <span class="text-gray-400 font-normal normal-case">(optional)</span></label>
                    <select id="newTicketType" class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-gray-900">
                        <option value="">-- Select Type --</option>
                        <option value="Incident">Incident</option>
                        <option value="Service Request">Service Request</option>
                        <option value="Change Request">Change Request</option>
                        <option value="Consult">Consult</option>
                    </select>
                </div>
                <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                    <button type="button" onclick="closeCreateTicketModal()" class="px-6 py-3 bg-white text-gray-700 text-sm font-semibold rounded-xl border border-gray-300 hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="px-6 py-3 bg-red-700 text-white text-sm font-semibold rounded-xl hover:bg-red-800 shadow-sm">Create Ticket</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

<style>
/* Ticket List Item */
.ticket-list-item {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 0.75rem;
    padding: 1rem 1.25rem;
    cursor: pointer;
    transition: all 0.2s;
    display: block;
    text-decoration: none;
    color: inherit;
}
.ticket-list-item:hover {
    background: #f9fafb;
    border-color: #9ca3af;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    transform: translateY(-1px);
}

/* View Toggle */
#btnViewAll, #btnViewMy { background: transparent; color: #6b7280; }
#btnViewAll.active, #btnViewMy.active { background: white; color: #111827; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
</style>

<script>
    let allTickets = [];
    let filteredTickets = [];
    let currentFilter = 'all';
    let itemsPerPage = 20;
    let currentPage = 1;
    let totalItems = 0;
    let totalPages = 0;
    let currentView = 'all';
    let userRole = {{ $user->role->role_id ?? 0 }};

    document.addEventListener('DOMContentLoaded', function() {
        loadTickets();
        if (userRole === 1 || userRole === 2) updateViewToggle();
        startEmailPolling();
    });

    // -------------------------------------------------------------------------
    // Email polling: cek email masuk setiap 30 detik, refresh tiket jika ada baru
    // -------------------------------------------------------------------------
    async function checkNewEmails() {
        try {
            const res = await fetch('/api/email/process-inbox', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? ''
                },
                credentials: 'same-origin'
            });

            if (!res.ok) {
                console.warn('[Email Polling] HTTP', res.status, await res.text());
                return;
            }

            const data = await res.json();
            console.log('[Email Polling]', data);

            if (data.processed > 0) {
                loadTickets();
            }
        } catch (err) {
            console.warn('[Email Polling] error:', err.message);
        }
    }

    function startEmailPolling() {
        // Langsung cek saat halaman dibuka
        checkNewEmails();
        // Lalu setiap 30 detik
        setInterval(checkNewEmails, 30000);
    }

    function toggleView(view) {
        currentView = view;
        updateViewToggle();
        loadTickets();
    }

    function updateViewToggle() {
        if (userRole !== 1 && userRole !== 2) return;
        const btnAll = document.getElementById('btnViewAll');
        const btnMy = document.getElementById('btnViewMy');
        if (currentView === 'all') {
            btnAll.classList.add('active');
            btnMy.classList.remove('active');
        } else {
            btnMy.classList.add('active');
            btnAll.classList.remove('active');
        }
    }

    async function loadTickets() {
        try {
            document.getElementById('loadingState').classList.remove('hidden');
            document.getElementById('ticketsContainer').classList.add('hidden');
            document.getElementById('emptyState').classList.add('hidden');

            let endpoint = '/api/tickets';
            if (userRole === 3) endpoint = '/api/tickets/my';
            else if ((userRole === 1 || userRole === 2) && currentView === 'my') endpoint = '/api/tickets/my';

            const response = await fetch(endpoint, {
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            });

            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) throw new Error('Non-JSON response');

            const data = await response.json();

            if (data.success) {
                allTickets = data.data.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
                filteredTickets = allTickets;
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
        document.getElementById('totalCount').textContent = allTickets.length;
        document.getElementById('processCount').textContent = allTickets.filter(t => t.jarvies_status === 'in process').length;
        document.getElementById('authorCount').textContent = allTickets.filter(t => t.jarvies_status === 'author action').length;
        document.getElementById('proposedCount').textContent = allTickets.filter(t => t.jarvies_status === 'proposed solution').length;
        document.getElementById('sapCount').textContent = allTickets.filter(t => t.jarvies_status === 'sent in to SAP').length;
        document.getElementById('closedCount').textContent = allTickets.filter(t => t.jarvies_status === 'closed').length;
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
        const paginatedTickets = filteredTickets.slice(startIndex, endIndex);

        listBody.innerHTML = paginatedTickets.map(ticket => createTicketCard(ticket)).join('');
        updatePaginationDisplay();
    }

    function createTicketCard(ticket) {
        const customerName = ticket.customer?.customer_name || 'Unknown';
        const timeAgo = formatTimeAgo(new Date(ticket.created_at));
        const description = ticket.description || 'No description';
        const shortDesc = description.length > 100 ? description.substring(0, 100) + '...' : description;
        const agentName = ticket.employee?.employee_name || 'Unassigned';

        const priorityColors = { 'Low': 'bg-green-100 text-green-700', 'Medium': 'bg-blue-100 text-blue-700', 'High': 'bg-red-100 text-red-700' };
        const priorityClass = priorityColors[ticket.ticket_priority] || 'bg-gray-100 text-gray-700';

        const statusMap = {
            'open': { label: 'Open', class: 'bg-blue-50 text-blue-700' },
            'in_progress': { label: 'In Progress', class: 'bg-yellow-50 text-yellow-700' },
            'hold': { label: 'Hold', class: 'bg-orange-50 text-orange-700' },
            'cancel': { label: 'Cancel', class: 'bg-gray-100 text-gray-500' },
            'closed': { label: 'Closed', class: 'bg-green-50 text-green-700' },
            'reply': { label: 'Reply', class: 'bg-purple-50 text-purple-700' }
        };
        const statusInfo = statusMap[ticket.status] || { label: 'Open', class: 'bg-gray-100 text-gray-600' };

        const typeColors = {
            'Incident': 'bg-red-50 text-red-600',
            'Service Request': 'bg-indigo-50 text-indigo-600',
            'Change Request': 'bg-amber-50 text-amber-600',
            'Consult': 'bg-teal-50 text-teal-600'
        };
        const typeBadge = ticket.ticket_type
            ? `<span class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold ${typeColors[ticket.ticket_type] || 'bg-gray-100 text-gray-600'}">${ticket.ticket_type}</span>`
            : '';

        return `
            <a href="/ticket/${ticket.ticket_id}" class="ticket-list-item">
                <div class="flex items-start gap-3">
                    <img src="https://ui-avatars.com/api/?name=${encodeURIComponent(customerName)}&background=6b7280&color=fff&size=40&rounded=true"
                         alt="${customerName}" class="w-10 h-10 rounded-full flex-shrink-0 mt-0.5">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2 mb-0.5">
                            <span class="text-sm font-semibold text-gray-900 truncate">${customerName}</span>
                            <span class="text-xs text-gray-400 flex-shrink-0">${timeAgo}</span>
                        </div>
                        <p class="text-sm text-gray-700 font-medium truncate mb-1.5">${ticket.ticket_number || '#' + ticket.ticket_id} - ${shortDesc}</p>
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold ${statusInfo.class}">${statusInfo.label}</span>
                            <span class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold ${priorityClass}">${ticket.ticket_priority || 'Medium'}</span>
                            ${typeBadge}
                            <span class="text-[10px] text-gray-400">${agentName}</span>
                        </div>
                    </div>
                    <div class="flex-shrink-0 mt-1">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-gray-300">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                        </svg>
                    </div>
                </div>
            </a>`;
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
        ['filterAll', 'filterInProcess', 'filterAuthorAction', 'filterProposed', 'filterSAP', 'filterClosed'].forEach(id => {
            const el = document.getElementById(id);
            el.classList.remove('border-red-600', 'shadow-md', 'border-2');
            el.classList.add('border-gray-200', 'border');
        });

        const filterMap = {
            'all': 'filterAll', 'in process': 'filterInProcess', 'author action': 'filterAuthorAction',
            'proposed solution': 'filterProposed', 'sent in to SAP': 'filterSAP', 'closed': 'filterClosed'
        };
        if (filterMap[status]) {
            const el = document.getElementById(filterMap[status]);
            el.classList.remove('border-gray-200', 'border');
            el.classList.add('border-red-600', 'shadow-md', 'border-2');
        }

        filteredTickets = status === 'all' ? allTickets : allTickets.filter(t => t.jarvies_status === status);
        currentPage = 1;
        renderTickets();
    }

    function searchTickets() { applyAdvancedFilters(); }

    function updateFilterOptions() {
        const filterType = document.getElementById('filterTypeSelect').value;
        const filterValue = document.getElementById('filterValueSelect');
        filterValue.disabled = false;
        filterValue.innerHTML = '<option value="">Select value</option>';

        const options = {
            'jarvies_status': ['in process', 'author action', 'proposed solution', 'closed', 'sent in to SAP', 'sent it to support'],
            'status': ['open', 'in_progress', 'hold', 'cancel', 'closed', 'reply'],
            'ticket_type': ['Incident', 'Service Request', 'Change Request', 'Consult'],
            'priority': ['Low', 'Medium', 'High']
        };

        if (filterType && options[filterType]) {
            options[filterType].forEach(opt => {
                const option = document.createElement('option');
                option.value = opt;
                option.textContent = opt.charAt(0).toUpperCase() + opt.slice(1).replace(/_/g, ' ');
                filterValue.appendChild(option);
            });
        } else { filterValue.disabled = true; }
    }

    function applyAdvancedFilters() {
        const filterType = document.getElementById('filterTypeSelect').value;
        const filterValue = document.getElementById('filterValueSelect').value;
        const searchTerm = document.getElementById('searchInput').value.toLowerCase();

        filteredTickets = allTickets.filter(ticket => {
            const matchesSearch = !searchTerm ||
                (ticket.ticket_number && ticket.ticket_number.toLowerCase().includes(searchTerm)) ||
                (ticket.ticket_id && ticket.ticket_id.toString().includes(searchTerm)) ||
                (ticket.description && ticket.description.toLowerCase().includes(searchTerm)) ||
                (ticket.customer?.customer_name && ticket.customer.customer_name.toLowerCase().includes(searchTerm)) ||
                (ticket.employee?.employee_name && ticket.employee.employee_name.toLowerCase().includes(searchTerm));

            let matchesFilter = true;
            if (filterType && filterValue) matchesFilter = ticket[filterType] === filterValue;

            let matchesStatusFilter = true;
            if (currentFilter !== 'all') matchesStatusFilter = ticket.jarvies_status === currentFilter;

            return matchesSearch && matchesFilter && matchesStatusFilter;
        });
        currentPage = 1;
        renderTickets();
    }

    function resetFilters() {
        document.getElementById('searchInput').value = '';
        document.getElementById('filterTypeSelect').value = '';
        document.getElementById('filterValueSelect').value = '';
        document.getElementById('filterValueSelect').disabled = true;
        currentFilter = 'all';
        filterTickets('all');
    }

    function formatTimeAgo(date) {
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);
        if (diffMins < 1) return 'just now';
        if (diffMins < 60) return `${diffMins}m ago`;
        if (diffHours < 24) return `${diffHours}h ago`;
        if (diffDays < 7) return `${diffDays}d ago`;
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    }

    function showNotification(message, type = 'info') {
        const bgColor = type === 'success' ? 'bg-green-500' : type === 'error' ? 'bg-red-500' : 'bg-blue-500';
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 ${bgColor} text-white px-6 py-3 rounded-xl shadow-xl z-[100] transition-opacity duration-300 font-medium text-sm`;
        notification.textContent = message;
        document.body.appendChild(notification);
        setTimeout(() => { notification.style.opacity = '0'; setTimeout(() => notification.remove(), 300); }, 3000);
    }

    // ==================== ADMIN: CREATE TICKET ====================
    function openCreateTicketModal() { document.getElementById('createTicketModal').classList.remove('hidden'); }
    function closeCreateTicketModal() {
        document.getElementById('createTicketModal').classList.add('hidden');
        document.getElementById('createTicketForm').reset();
        document.getElementById('customerSearch').value = '';
        document.getElementById('newCustomerId').value = '';
        // Reset dropdown options visibility
        const options = document.querySelectorAll('.customer-option');
        options.forEach(opt => opt.classList.remove('hidden'));
    }

    async function submitCreateTicket(e) {
        e.preventDefault();
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
            } else { showNotification(result.message || 'Failed to create ticket', 'error'); }
        } catch (error) { showNotification('Failed to create ticket: ' + error.message, 'error'); }
    }

    // Event listeners
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && document.getElementById('createTicketModal') && !document.getElementById('createTicketModal').classList.contains('hidden')) {
            closeCreateTicketModal();
        }
    });
    document.getElementById('createTicketModal')?.addEventListener('click', function(e) { if (e.target === this) closeCreateTicketModal(); });

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

@endsection

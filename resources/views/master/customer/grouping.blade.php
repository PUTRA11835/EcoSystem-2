@extends('dashboard')

@section('title', 'Customer Grouping')
@section('page-title', 'Customer Grouping')

@section('content')
<div class="bg-white rounded-xl p-6 shadow-sm">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 pb-4 border-b-2 border-gray-100">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Customer Grouping</h2>
            <p class="text-sm text-gray-500 mt-0.5">Parent customers and their end customers</p>
        </div>
        <a href="{{ route('master.customer.index') }}" class="inline-flex items-center px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all duration-200">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
            </svg>
            Back to Customer List
        </a>
    </div>

    <!-- ════════ SECTION 1: Parent–Child ════════ -->
    <h3 class="text-lg font-bold text-gray-900 mb-1">Parent &amp; End Customers</h3>
    <p class="text-sm text-gray-500 mb-4">Hierarchical relationship — end customers belong to a parent customer.</p>

    <!-- Summary bar -->
    <div id="groupingSummary" class="flex items-center gap-3 mb-4 text-sm text-gray-500">
        <span id="summaryText">Loading...</span>
    </div>

    <!-- Cards -->
    <div id="groupingCards" class="space-y-6">
        <!-- Rendered by JS -->
    </div>

    <!-- Empty state (parent–child) -->
    <div id="groupingEmpty" class="hidden py-12 text-center">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-14 h-14 mx-auto mb-4 text-gray-300">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
        </svg>
        <p class="text-base font-medium text-gray-900 mb-1">No parent–child relationships found</p>
        <p class="text-sm text-gray-500">Assign a parent to a customer via <a href="{{ route('master.customer.index') }}" class="text-red-800 underline">Customer List</a>.</p>
    </div>

    <!-- ════════ SECTION 2: Customer Groups ════════ -->
    <div class="mt-10 pt-8 border-t-2 border-gray-100">
        <div class="flex items-start justify-between gap-4 mb-4">
            <div>
                <h3 class="text-lg font-bold text-gray-900 mb-1">Customer Groups</h3>
                <p class="text-sm text-gray-500">Flat grouping — each member is an independent customer (own login, contacts &amp; tickets) shown together.</p>
            </div>
            <button type="button" onclick="openNewGroup()" class="inline-flex items-center gap-1.5 px-4 py-2 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900 transition-all whitespace-nowrap">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                New group
            </button>
        </div>

        <div id="cgSummary" class="flex items-center gap-3 mb-4 text-sm text-gray-500">
            <span id="cgSummaryText">Loading...</span>
        </div>

        <div id="cgCards" class="space-y-6">
            <!-- Rendered by JS -->
        </div>

        <div id="cgEmpty" class="hidden py-12 text-center">
            <p class="text-base font-medium text-gray-900 mb-1">No customer groups yet</p>
            <p class="text-sm text-gray-500">Assign customers to a group via the <a href="{{ route('master.customer.index') }}" class="text-red-800 underline">Customer List</a> or detail page.</p>
        </div>
    </div>


</div>

{{-- New customer group modal --}}
<div id="newGroupModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-[60] items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-md w-full shadow-2xl">
        <div class="p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-1">New Customer Group</h3>
            <p class="text-sm text-gray-600 mb-4">Enter a name for the new customer group. You can add member companies afterwards.</p>
            <label class="block text-xs font-semibold text-gray-500 mb-1">Group name</label>
            <input type="text" id="newGroupName" placeholder="Customer group name"
                   class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent"
                   onkeydown="if(event.key==='Enter'){event.preventDefault();saveNewGroup();}">
            <div class="flex gap-3 mt-6">
                <button type="button" onclick="closeNewGroup()" class="flex-1 px-4 py-2.5 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">Cancel</button>
                <button type="button" onclick="saveNewGroup()" class="flex-1 px-4 py-2.5 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900 transition-all">Create</button>
            </div>
        </div>
    </div>
</div>

{{-- Add member modal --}}
<div id="addMemberModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-[60] items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-lg w-full shadow-2xl">
        <div class="p-6">
            <h3 id="addMemberTitle" class="text-lg font-bold text-gray-900 mb-1">Add Member</h3>
            <p class="text-sm text-gray-600 mb-4">Only customers not yet in any group are listed (a customer can belong to one group at a time).</p>
            <input type="text" id="addMemberSearch" placeholder="Search by company name, code, or email…"
                   oninput="renderAvailableList(this.value)"
                   class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent mb-3">
            <div id="addMemberList" class="max-h-72 overflow-y-auto border border-gray-100 rounded-lg p-1 divide-y divide-gray-50"></div>
            <div class="flex justify-end mt-6">
                <button type="button" onclick="closeAddMember()" class="px-4 py-2.5 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">Done</button>
            </div>
        </div>
    </div>
</div>

{{-- Remove member modal --}}
<div id="removeMemberModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-[60] items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-md w-full shadow-2xl">
        <div class="p-6">
            <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 bg-red-100 rounded-full">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-red-600">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M22 10.5h-6m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM4 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 10.374 21c-2.331 0-4.512-.645-6.374-1.766Z" />
                </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-900 text-center mb-2">Remove Member</h3>
            <p id="removeMemberText" class="text-sm text-gray-600 text-center mb-6">Remove this customer from the group?</p>
            <div class="flex gap-3">
                <button type="button" onclick="closeRemoveMember()" class="flex-1 px-4 py-2.5 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">Cancel</button>
                <button type="button" onclick="confirmRemoveMember()" class="flex-1 px-4 py-2.5 bg-red-600 text-white text-sm font-semibold rounded-lg hover:bg-red-700 transition-all">Remove</button>
            </div>
        </div>
    </div>
</div>

{{-- Edit customer group modal --}}
<div id="editGroupModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-[60] items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-md w-full shadow-2xl">
        <div class="p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-1">Edit Customer Group</h3>
            <p class="text-sm text-gray-600 mb-4">Update the group name. Member customers will reflect the new name.</p>
            <input type="hidden" id="editGroupId">
            <label class="block text-xs font-semibold text-gray-500 mb-1">Group name</label>
            <input type="text" id="editGroupName" placeholder="Customer group name"
                   class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent"
                   onkeydown="if(event.key==='Enter'){event.preventDefault();saveEditGroup();}">
            <div class="flex gap-3 mt-6">
                <button type="button" onclick="closeEditGroup()" class="flex-1 px-4 py-2.5 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">Cancel</button>
                <button type="button" onclick="saveEditGroup()" class="flex-1 px-4 py-2.5 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900 transition-all">Save</button>
            </div>
        </div>
    </div>
</div>

{{-- Delete customer group modal --}}
<div id="deleteGroupModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-[60] items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-md w-full shadow-2xl">
        <div class="p-6">
            <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 bg-red-100 rounded-full">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-red-600">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
            </div>
            <input type="hidden" id="deleteGroupId">
            <h3 class="text-lg font-bold text-gray-900 text-center mb-2">Delete Customer Group</h3>
            <p id="deleteGroupText" class="text-sm text-gray-600 text-center mb-6">Are you sure you want to delete this group?</p>
            <div class="flex gap-3">
                <button type="button" onclick="closeDeleteGroup()" class="flex-1 px-4 py-2.5 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">Cancel</button>
                <button type="button" onclick="confirmDeleteGroup()" class="flex-1 px-4 py-2.5 bg-red-600 text-white text-sm font-semibold rounded-lg hover:bg-red-700 transition-all">Delete</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
async function loadGrouping() {
    try {
        const res = await fetch('/api/customers/grouping-data', {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.message);
        renderGrouping(data.data);
    } catch (e) {
        console.error(e);
        document.getElementById('summaryText').textContent = 'Failed to load grouping data.';
    }
}

function renderGrouping(groups) {
    const cards  = document.getElementById('groupingCards');
    const empty  = document.getElementById('groupingEmpty');
    const summary = document.getElementById('summaryText');

    if (!groups.length) {
        cards.classList.add('hidden');
        empty.classList.remove('hidden');
        summary.textContent = 'No groups found.';
        return;
    }

    const totalEnd = groups.reduce((s, g) => s + g.end_customers.length, 0);
    summary.textContent = `${groups.length} parent customer${groups.length > 1 ? 's' : ''} · ${totalEnd} end customer${totalEnd !== 1 ? 's' : ''}`;

    cards.innerHTML = groups.map(group => `
        <div class="border border-gray-200 rounded-xl overflow-hidden">

            <!-- Parent header -->
            <div class="flex items-center justify-between px-5 py-4 bg-gray-50 border-b border-gray-200">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full primary-gradient flex items-center justify-center flex-shrink-0">
                        <span class="text-white text-xs font-bold">${escHtml(group.code)}</span>
                    </div>
                    <div>
                        <a href="/master/customer/${group.id}" class="font-semibold text-gray-900 hover:text-red-800 transition-colors">
                            ${escHtml(group.name)}
                        </a>
                        <p class="text-xs text-gray-400">${escHtml(group.email || '-')}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs text-gray-500 font-medium">${group.end_customers.length} end customer${group.end_customers.length !== 1 ? 's' : ''}</span>
                    <span class="inline-block px-2.5 py-1 text-xs font-semibold rounded-full ${group.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500'}">
                        ${group.status === 'active' ? 'Active' : 'Inactive'}
                    </span>
                </div>
            </div>

            <!-- End customers table -->
            <table class="w-full">
                <thead>
                    <tr class="bg-white border-b border-gray-100">
                        <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wider w-8"></th>
                        <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Company Name</th>
                        <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Email</th>
                        <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Code</th>
                        <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    ${group.end_customers.map(ec => `
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-5 py-3 text-gray-300 text-sm">&#8627;</td>
                        <td class="px-4 py-3 text-sm font-medium text-gray-800">${escHtml(ec.name)}</td>
                        <td class="px-4 py-3 text-sm text-gray-500">${escHtml(ec.email || '-')}</td>
                        <td class="px-4 py-3 text-sm font-mono text-gray-500">${escHtml(ec.code)}</td>
                        <td class="px-4 py-3">
                            <span class="inline-block px-2.5 py-1 text-xs font-semibold rounded-full ${ec.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500'}">
                                ${ec.status === 'active' ? 'Active' : 'Inactive'}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="/master/customer/${ec.id}" class="text-xs text-gray-400 hover:text-red-800 transition-colors">Detail &rsaquo;</a>
                        </td>
                    </tr>
                    `).join('')}
                </tbody>
            </table>

        </div>
    `).join('');
}

// ════════ Customer Groups (struktural, flat) ════════
async function loadCustomerGroups() {
    try {
        const res = await fetch('/api/customer-groups/grouping-data', {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.message);
        renderCustomerGroups(data.data);
    } catch (e) {
        console.error(e);
        document.getElementById('cgSummaryText').textContent = 'Failed to load customer groups.';
    }
}

let cgGroupsById = {};

function renderCustomerGroups(groups) {
    const cards   = document.getElementById('cgCards');
    const empty   = document.getElementById('cgEmpty');
    const summary = document.getElementById('cgSummaryText');

    cgGroupsById = {};
    groups.forEach(g => { cgGroupsById[g.id] = g; });

    if (!groups.length) {
        cards.classList.add('hidden');
        empty.classList.remove('hidden');
        summary.textContent = 'No customer groups yet.';
        return;
    }
    cards.classList.remove('hidden');
    empty.classList.add('hidden');

    const totalMembers = groups.reduce((s, g) => s + g.members.length, 0);
    summary.textContent = `${groups.length} group${groups.length > 1 ? 's' : ''} · ${totalMembers} member${totalMembers !== 1 ? 's' : ''}`;

    cards.innerHTML = groups.map(group => `
        <div class="border border-gray-200 rounded-xl overflow-hidden">

            <!-- Group header -->
            <div class="flex items-center justify-between px-5 py-4 bg-gray-50 border-b border-gray-200">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full bg-indigo-600 flex items-center justify-center flex-shrink-0">
                        <span class="text-white text-xs font-bold">${escHtml((group.code || group.name || '?').slice(0,4).toUpperCase())}</span>
                    </div>
                    <div>
                        <span class="font-semibold text-gray-900">${escHtml(group.name)}</span>
                        <p class="text-xs text-gray-400">Customer Group</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs text-gray-500 font-medium">${group.members.length} member${group.members.length !== 1 ? 's' : ''}</span>
                    <button type="button" onclick="openAddMember(${group.id})" title="Add member"
                            class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-all">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Add member
                    </button>
                    <button type="button" onclick="openEditGroup(${group.id})" title="Edit group"
                            class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-semibold text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" />
                        </svg>
                        Edit
                    </button>
                    <button type="button" onclick="openDeleteGroup(${group.id})" title="Delete group"
                            class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-semibold text-red-600 bg-white border border-gray-300 rounded-lg hover:bg-red-50 transition-all">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                        </svg>
                        Delete
                    </button>
                </div>
            </div>

            <!-- Members table -->
            <table class="w-full">
                <thead>
                    <tr class="bg-white border-b border-gray-100">
                        <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wider w-8"></th>
                        <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Company Name</th>
                        <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Email</th>
                        <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Code</th>
                        <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    ${group.members.length === 0 ? `
                    <tr>
                        <td colspan="6" class="px-5 py-4 text-center text-sm text-gray-400">No members yet — assign customers to this group from the Customer List or detail page.</td>
                    </tr>
                    ` : ''}
                    ${group.members.map(m => `
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-5 py-3 text-indigo-300 text-sm">&#9679;</td>
                        <td class="px-4 py-3 text-sm font-medium text-gray-800">${escHtml(m.name)}</td>
                        <td class="px-4 py-3 text-sm text-gray-500">${escHtml(m.email || '-')}</td>
                        <td class="px-4 py-3 text-sm font-mono text-gray-500">${escHtml(m.code)}</td>
                        <td class="px-4 py-3">
                            <span class="inline-block px-2.5 py-1 text-xs font-semibold rounded-full ${m.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500'}">
                                ${m.status === 'active' ? 'Active' : 'Inactive'}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <button type="button" onclick="openRemoveMember(${group.id}, ${m.id})" class="text-xs text-gray-400 hover:text-red-700 transition-colors mr-3">Remove</button>
                            <a href="/master/customer/${m.id}" class="text-xs text-gray-400 hover:text-red-800 transition-colors">Detail &rsaquo;</a>
                        </td>
                    </tr>
                    `).join('')}
                </tbody>
            </table>

        </div>
    `).join('');
}

// ──── Edit group ────
function openEditGroup(id) {
    const g = cgGroupsById[id];
    if (!g) return;
    document.getElementById('editGroupId').value = id;
    document.getElementById('editGroupName').value = g.name || '';
    const modal = document.getElementById('editGroupModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    setTimeout(() => document.getElementById('editGroupName').focus(), 50);
}

function closeEditGroup() {
    const modal = document.getElementById('editGroupModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

async function saveEditGroup() {
    const id   = document.getElementById('editGroupId').value;
    const name = (document.getElementById('editGroupName').value || '').trim();
    if (!name) { showNotification('Group name cannot be empty', 'error'); return; }
    try {
        const res = await fetch(`/api/customer-groups/${id}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            credentials: 'same-origin',
            body: JSON.stringify({ name })
        });
        const data = await res.json();
        if (data.success) {
            closeEditGroup();
            showNotification('Customer group updated successfully', 'success');
            loadCustomerGroups();
        } else {
            showNotification(data.message || 'Failed to update group', 'error');
        }
    } catch (e) {
        console.error('saveEditGroup error', e);
        showNotification('An error occurred while updating group', 'error');
    }
}

// ──── Delete group ────
function openDeleteGroup(id) {
    const g = cgGroupsById[id];
    if (!g) return;
    document.getElementById('deleteGroupId').value = id;
    const memberNote = g.members.length
        ? ` ${g.members.length} member${g.members.length !== 1 ? 's' : ''} will be unassigned (the customers themselves are not deleted).`
        : '';
    document.getElementById('deleteGroupText').textContent =
        `Are you sure you want to delete "${g.name}"?${memberNote} This action cannot be undone.`;
    const modal = document.getElementById('deleteGroupModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeDeleteGroup() {
    const modal = document.getElementById('deleteGroupModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

async function confirmDeleteGroup() {
    const id = document.getElementById('deleteGroupId').value;
    try {
        const res = await fetch(`/api/customer-groups/${id}/delete`, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            credentials: 'same-origin'
        });
        const data = await res.json();
        if (data.success) {
            closeDeleteGroup();
            showNotification('Customer group deleted successfully', 'success');
            loadCustomerGroups();
        } else {
            showNotification(data.message || 'Failed to delete group', 'error');
        }
    } catch (e) {
        console.error('confirmDeleteGroup error', e);
        showNotification('An error occurred while deleting group', 'error');
    }
}

// ──── New group ────
function openNewGroup() {
    document.getElementById('newGroupName').value = '';
    const modal = document.getElementById('newGroupModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    setTimeout(() => document.getElementById('newGroupName').focus(), 50);
}

function closeNewGroup() {
    const modal = document.getElementById('newGroupModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

async function saveNewGroup() {
    const name = (document.getElementById('newGroupName').value || '').trim();
    if (!name) { showNotification('Group name cannot be empty', 'error'); return; }
    try {
        const res = await fetch('/api/customer-groups', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            credentials: 'same-origin',
            body: JSON.stringify({ name })
        });
        const data = await res.json();
        if (data.success) {
            closeNewGroup();
            showNotification('Customer group created successfully', 'success');
            loadCustomerGroups();
        } else {
            showNotification(data.message || 'Failed to create group', 'error');
        }
    } catch (e) {
        console.error('saveNewGroup error', e);
        showNotification('An error occurred while creating group', 'error');
    }
}

// ──── Add member ────
let addMemberGroupId = null;
let availableCustomers = [];

async function openAddMember(groupId) {
    const g = cgGroupsById[groupId];
    if (!g) return;
    addMemberGroupId = groupId;
    document.getElementById('addMemberTitle').textContent = `Add Member — ${g.name}`;
    document.getElementById('addMemberSearch').value = '';
    document.getElementById('addMemberList').innerHTML = '<p class="text-sm text-gray-400 text-center py-6">Loading…</p>';
    const modal = document.getElementById('addMemberModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');

    try {
        const res = await fetch('/api/customer-groups/available-customers', {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.message);
        availableCustomers = data.data;
        renderAvailableList('');
        setTimeout(() => document.getElementById('addMemberSearch').focus(), 50);
    } catch (e) {
        console.error('openAddMember error', e);
        document.getElementById('addMemberList').innerHTML = '<p class="text-sm text-red-500 text-center py-6">Failed to load customers.</p>';
    }
}

function renderAvailableList(filter) {
    const list = document.getElementById('addMemberList');
    const f = (filter || '').toLowerCase().trim();
    const rows = availableCustomers.filter(c =>
        !f || (c.name || '').toLowerCase().includes(f) || (c.code || '').toLowerCase().includes(f) || (c.email || '').toLowerCase().includes(f)
    );

    if (!availableCustomers.length) {
        list.innerHTML = '<p class="text-sm text-gray-400 text-center py-6">No available customers — every customer already belongs to a group.</p>';
        return;
    }
    if (!rows.length) {
        list.innerHTML = '<p class="text-sm text-gray-400 text-center py-6">No customers match your search.</p>';
        return;
    }

    list.innerHTML = rows.map(c => `
        <button type="button" onclick="addMemberToGroup(${c.id})"
                class="w-full flex items-center justify-between gap-3 px-3 py-2.5 text-left hover:bg-indigo-50 rounded-lg transition-all">
            <div class="min-w-0">
                <p class="text-sm font-medium text-gray-800 truncate">${escHtml(c.name)}</p>
                <p class="text-xs text-gray-400 truncate">${escHtml(c.code)}${c.email ? ' · ' + escHtml(c.email) : ''}</p>
            </div>
            <span class="text-xs font-semibold text-indigo-600 flex-shrink-0">+ Add</span>
        </button>
    `).join('');
}

async function addMemberToGroup(customerId) {
    if (!addMemberGroupId) return;
    try {
        const res = await fetch(`/api/customer-groups/${addMemberGroupId}/members`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            credentials: 'same-origin',
            body: JSON.stringify({ customer_id: customerId })
        });
        const data = await res.json();
        if (data.success) {
            showNotification('Customer added to group', 'success');
            availableCustomers = availableCustomers.filter(c => c.id !== customerId);
            renderAvailableList(document.getElementById('addMemberSearch').value);
            loadCustomerGroups();
        } else {
            showNotification(data.message || 'Failed to add customer', 'error');
        }
    } catch (e) {
        console.error('addMemberToGroup error', e);
        showNotification('An error occurred while adding customer', 'error');
    }
}

function closeAddMember() {
    const modal = document.getElementById('addMemberModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    addMemberGroupId = null;
}

// ──── Remove member ────
let removeMemberCtx = { groupId: null, customerId: null };

function openRemoveMember(groupId, customerId) {
    const g = cgGroupsById[groupId];
    const m = g ? g.members.find(x => x.id === customerId) : null;
    removeMemberCtx = { groupId, customerId };
    document.getElementById('removeMemberText').textContent =
        `Remove "${m ? m.name : 'this customer'}" from "${g ? g.name : 'this group'}"? The customer itself will not be deleted and can be assigned to another group afterwards.`;
    const modal = document.getElementById('removeMemberModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeRemoveMember() {
    const modal = document.getElementById('removeMemberModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    removeMemberCtx = { groupId: null, customerId: null };
}

async function confirmRemoveMember() {
    const { groupId, customerId } = removeMemberCtx;
    if (!groupId || !customerId) return;
    try {
        const res = await fetch(`/api/customer-groups/${groupId}/members/${customerId}/delete`, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            credentials: 'same-origin'
        });
        const data = await res.json();
        if (data.success) {
            closeRemoveMember();
            showNotification('Customer removed from group', 'success');
            loadCustomerGroups();
        } else {
            showNotification(data.message || 'Failed to remove customer', 'error');
        }
    } catch (e) {
        console.error('confirmRemoveMember error', e);
        showNotification('An error occurred while removing customer', 'error');
    }
}

function escHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

document.addEventListener('DOMContentLoaded', () => {
    loadGrouping();
    loadCustomerGroups();
});
</script>
@endpush

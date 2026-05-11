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

    <!-- Summary bar -->
    <div id="groupingSummary" class="flex items-center gap-3 mb-6 text-sm text-gray-500">
        <span id="summaryText">Loading...</span>
    </div>

    <!-- Cards -->
    <div id="groupingCards" class="space-y-6">
        <!-- Rendered by JS -->
    </div>

    <!-- Empty state -->
    <div id="groupingEmpty" class="hidden py-20 text-center">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 mx-auto mb-4 text-gray-300">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
        </svg>
        <p class="text-base font-medium text-gray-900 mb-1">No customer groups found</p>
        <p class="text-sm text-gray-500">Create a customer then assign end customers to it via <a href="{{ route('master.customer.index') }}" class="text-red-800 underline">Customer List</a>.</p>
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

function escHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

document.addEventListener('DOMContentLoaded', loadGrouping);
</script>
@endpush

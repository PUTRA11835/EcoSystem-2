@extends('dashboard')
@section('title', 'Ticketing Overview')
@section('page-title', 'Ticketing Overview')
@section('page-subtitle', 'Customer ticket counts by status')

@section('content')
<div class="bg-white rounded-xl p-6 shadow-sm">

    <h2 class="text-lg font-bold text-gray-900 mb-4">Customer Tickets</h2>

    {{-- ── Table ───────────────────────────────────────────────────────────── --}}
    <div class="rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-auto" style="max-height: calc(100vh - 320px); min-height: 220px;">
            <table class="text-sm border-collapse w-full" style="min-width: 900px;">
                <thead class="sticky top-0 z-10 bg-gray-50">
                    <tr>
                        <th class="to-th text-left">Customer Name</th>
                        <th class="to-th text-right">Total Mandays</th>
                        <th class="to-th text-right">Open Tickets</th>
                        <th class="to-th text-right">In Process Tickets</th>
                        <th class="to-th text-right">Close Tickets</th>
                        <th class="to-th text-right">Other Tickets</th>
                        <th class="to-th text-right">Wait Close Tickets</th>
                    </tr>
                </thead>
                <tbody id="toTableBody" class="bg-white">
                    <tr>
                        <td colspan="7" class="px-4 py-14 text-center text-gray-400 text-sm">
                            <i class="fas fa-spinner fa-spin text-2xl mb-3 block primary-text opacity-50"></i>
                            <span class="text-gray-400">Loading data...</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- Empty state --}}
        <div id="toEmpty" class="hidden text-center py-16 px-4">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4">
                <svg class="w-8 h-8 text-gray-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
                </svg>
            </div>
            <p class="text-gray-700 font-semibold">No customer tickets found</p>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.primary-text { color: var(--primary-color) !important; }
.to-th {
    padding: 0.7rem 0.9rem; font-size: 11px; font-weight: 600;
    color: #6b7280; text-transform: uppercase; letter-spacing: 0.06em;
    white-space: nowrap; border-bottom: 1px solid #cbd5e1;
    background: #f9fafb;
}
.to-td {
    padding: 0.65rem 0.9rem; white-space: nowrap; border-bottom: 1px solid #e5e7eb;
    vertical-align: middle;
}
.to-bar-track { width: 100%; height: 6px; border-radius: 999px; background: #eef0f2; overflow: hidden; }
.to-bar-fill  { height: 100%; border-radius: 999px; }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', loadTicketingOverview);

async function loadTicketingOverview() {
    const tbody = document.getElementById('toTableBody');
    const empty = document.getElementById('toEmpty');
    empty.classList.add('hidden');

    try {
        const res = await fetch('/api/reporting/ticketing-overview', {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '' },
            credentials: 'same-origin'
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load data');

        renderTicketingOverview(json.data || []);
    } catch (e) {
        console.error(e);
        tbody.innerHTML = `<tr><td colspan="7" class="px-4 py-10 text-center text-sm">
            <div class="inline-flex flex-col items-center gap-2 text-red-500">
                <i class="fas fa-exclamation-circle text-xl"></i>
                <span>${escHtml(e.message)}</span>
            </div>
        </td></tr>`;
    }
}

function renderTicketingOverview(rows) {
    const tbody = document.getElementById('toTableBody');
    const empty = document.getElementById('toEmpty');

    if (rows.length === 0) {
        tbody.innerHTML = '';
        empty.classList.remove('hidden');
        return;
    }
    empty.classList.add('hidden');

    tbody.innerHTML = rows.map(r => {
        const ticketCount = r.open_tickets + r.inprocess_tickets + r.close_tickets + r.other_tickets + r.wait_close_tickets;
        return `
        <tr>
            <td class="to-td font-semibold text-gray-800">${escHtml(r.customer_name)}</td>
            <td class="to-td text-right text-gray-800">${r.total_mandays}</td>
            ${countCell(r.open_tickets,       ticketCount, '#ef4444')}
            ${countCell(r.inprocess_tickets,  ticketCount, '#f59e0b')}
            ${countCell(r.close_tickets,      ticketCount, '#22c55e')}
            ${countCell(r.other_tickets,      ticketCount, '#0f766e')}
            ${countCell(r.wait_close_tickets, ticketCount, '#6366f1')}
        </tr>
    `;
    }).join('');
}

function countCell(count, total, color) {
    const pct = total > 0 ? Math.round((count / total) * 100) : 0;
    return `<td class="to-td text-right">
        <div class="to-bar-track mb-1"><div class="to-bar-fill" style="width:${pct}%; background:${color};"></div></div>
        <span class="text-gray-600 text-xs">${count} Tickets</span>
    </td>`;
}

function escHtml(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
</script>
@endpush

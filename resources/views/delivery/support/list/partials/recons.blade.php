{{-- ══════════════════════════════════════════════════════════════════════ --}}
{{-- RECONS — rekonsiliasi tiket                                            --}}
{{-- ══════════════════════════════════════════════════════════════════════ --}}
<div class="bg-white shadow-md rounded-lg" data-support-id="{{ $support->id }}">
    {{-- Header --}}
    <div class="p-6 border-b border-gray-200">
        <div class="flex justify-between items-center flex-wrap gap-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-700 flex items-center">
                    <svg class="w-5 h-5 mr-2 primary-text" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/>
                    </svg>
                    Recons
                </h2>
                <p class="text-xs text-gray-500 mt-1">Ticket reconciliation — closed tickets with Customer MD grouped into recons batches</p>
            </div>

            <div class="flex items-center gap-3 flex-wrap">
                {{-- Toggle tampilan: daftar tiket vs daftar batch Recons --}}
                <div class="inline-flex bg-gray-100 rounded-xl p-1">
                    <button type="button" onclick="SupportRecons.switchView('tickets')" id="reconsViewTicketsBtn"
                            class="recons-view-btn px-4 py-1.5 text-xs font-semibold rounded-lg transition-all duration-200">
                        Tickets
                    </button>
                    <button type="button" onclick="SupportRecons.switchView('batches')" id="reconsViewBatchesBtn"
                            class="recons-view-btn px-4 py-1.5 text-xs font-semibold rounded-lg transition-all duration-200">
                        Recons List
                    </button>
                </div>

                @if($can('delivery-support.recons.manage'))
                <a href="{{ route('delivery.support.recons.create', $support->id) }}"
                   class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    New Recons
                </a>
                @endif
            </div>
        </div>
    </div>

    {{-- ────────────────────────────────────────────────────────────────── --}}
    {{-- VIEW A — Daftar tiket support ini + info Recons                     --}}
    {{-- ────────────────────────────────────────────────────────────────── --}}
    <div id="reconsTicketsView" class="px-6 py-5">
        <div class="flex items-center justify-between gap-3 flex-wrap mb-4">
            <div class="relative flex-1 min-w-[240px] max-w-md">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/>
                    </svg>
                </div>
                <input type="text" id="reconsTicketSearch" placeholder="Search ticket number, description, recons number…"
                       autocomplete="off" oninput="SupportRecons.renderTickets()"
                       class="w-full pl-9 pr-3 py-2 border border-gray-300 rounded-lg text-sm primary-focus">
            </div>
            <p class="text-xs text-gray-500" id="reconsTicketsCount"></p>
        </div>

        <div class="overflow-x-auto rounded-lg border border-gray-200">
            <table class="min-w-full text-sm" style="border-collapse:separate;border-spacing:0;">
                <thead>
                    <tr class="bg-gray-700 text-white">
                        <th class="px-4 py-3 text-left font-semibold whitespace-nowrap">Ticket Number</th>
                        <th class="px-4 py-3 text-left font-semibold" style="min-width:240px;">Description</th>
                        <th class="px-4 py-3 text-center font-semibold whitespace-nowrap">Start Date</th>
                        <th class="px-4 py-3 text-center font-semibold whitespace-nowrap">Close Date</th>
                        <th class="px-4 py-3 text-center font-semibold whitespace-nowrap">Status</th>
                        <th class="px-4 py-3 text-center font-semibold whitespace-nowrap">Type</th>
                        <th class="px-4 py-3 text-right font-semibold whitespace-nowrap">Customer MD</th>
                        <th class="px-4 py-3 text-left font-semibold whitespace-nowrap bg-gray-800">Recons Number</th>
                        <th class="px-4 py-3 text-left font-semibold bg-gray-800" style="min-width:180px;">Recons Description</th>
                        <th class="px-4 py-3 text-center font-semibold whitespace-nowrap bg-gray-800">Recons Status</th>
                    </tr>
                </thead>
                <tbody id="reconsTicketsBody" class="divide-y divide-gray-100">
                    <tr>
                        <td colspan="10" class="text-center py-10">
                            <svg class="animate-spin h-7 w-7 primary-text mx-auto mb-2" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            <p class="text-gray-500 text-xs">Loading tickets…</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p class="text-xs text-gray-400 mt-2">
            * Close Date is filled automatically when a ticket status becomes Closed.
            Only closed tickets that have Customer MD and have never been reconciled can be added to a new recons.
        </p>
    </div>

    {{-- ────────────────────────────────────────────────────────────────── --}}
    {{-- VIEW B — Daftar batch Recons                                        --}}
    {{-- ────────────────────────────────────────────────────────────────── --}}
    <div id="reconsBatchesView" class="px-6 py-5 hidden">
        <div class="overflow-x-auto rounded-lg border border-gray-200">
            <table class="min-w-full text-sm" style="border-collapse:separate;border-spacing:0;">
                <thead>
                    <tr class="bg-gray-700 text-white">
                        <th class="px-4 py-3 text-left font-semibold whitespace-nowrap">Recons Number</th>
                        <th class="px-4 py-3 text-left font-semibold" style="min-width:220px;">Description</th>
                        <th class="px-4 py-3 text-center font-semibold whitespace-nowrap">Recons Date</th>
                        <th class="px-4 py-3 text-center font-semibold whitespace-nowrap">Status</th>
                        <th class="px-4 py-3 text-right font-semibold whitespace-nowrap">Tickets</th>
                        <th class="px-4 py-3 text-right font-semibold whitespace-nowrap">Total MD</th>
                        <th class="px-4 py-3 text-left font-semibold whitespace-nowrap">Created By</th>
                        <th class="px-4 py-3 text-center font-semibold whitespace-nowrap">Action</th>
                    </tr>
                </thead>
                <tbody id="reconsBatchesBody" class="divide-y divide-gray-100">
                    <tr>
                        <td colspan="8" class="text-center py-10">
                            <svg class="animate-spin h-7 w-7 primary-text mx-auto mb-2" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            <p class="text-gray-500 text-xs">Loading recons…</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Konfirmasi hapus draft memakai modal global (partials/confirm-modal),
     sama seperti aksi destruktif lain di halaman ini — tidak ada modal khusus. --}}

<style>
    .recons-view-btn { background: transparent; color: #9ca3af; }
    .recons-view-btn.active {
        background: #fff;
        color: #111827;
        font-weight: 700;
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.12);
    }
    .recons-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.125rem 0.5rem;
        border-radius: 0.5rem;
        font-size: 0.6875rem;
        font-weight: 600;
        white-space: nowrap;
    }
    .recons-badge-draft     { background: #fef3c7; color: #92400e; }
    .recons-badge-submitted { background: #dcfce7; color: #166534; }
    .recons-badge-none      { background: #f3f4f6; color: #6b7280; }
</style>

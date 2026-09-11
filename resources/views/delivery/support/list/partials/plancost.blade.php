{{-- ══════════════════════════════════════════════════════════════════════ --}}
{{-- PLAN COST                                                              --}}
{{-- ══════════════════════════════════════════════════════════════════════ --}}
<div class="bg-white shadow-md rounded-lg" data-support-id="{{ $support->id }}">
    {{-- Header --}}
    <div class="p-6 border-b border-gray-200">
        <div class="flex justify-between items-center flex-wrap gap-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-700 flex items-center">
                    <svg class="w-5 h-5 mr-2 primary-text" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Plan Cost
                </h2>
                <p class="text-xs text-gray-500 mt-1">Support cost recapitulation: Indirect Cost &amp; Direct Cost</p>
            </div>
            @if($can('delivery-support.plan-cost.manage'))
            <button type="button" onclick="SupportPlanCost.openAddParentModal()"
                    class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Add Cost Item
            </button>
            @endif
        </div>
    </div>

    {{-- Summary Cards --}}
    <div id="supPlanCostSummaryCards" class="px-6 pt-5 pb-2 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
        <div class="col-span-full text-center py-4">
            <svg class="animate-spin h-6 w-6 primary-text mx-auto" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
        </div>
    </div>

    {{-- Recapitulation Table --}}
    <div class="px-6 pb-6">
        <div class="overflow-x-auto rounded-lg border border-gray-200">
            <table class="min-w-full text-sm" id="supPlanCostTable">
                <thead>
                    <tr class="bg-gray-700 text-white">
                        <th class="px-4 py-3 text-left font-semibold w-16">Code</th>
                        <th class="px-4 py-3 text-left font-semibold">Item Name</th>
                        <th class="px-4 py-3 text-right font-semibold w-36">Budget</th>
                        <th class="px-4 py-3 text-right font-semibold w-36 bg-blue-700">Release</th>
                        <th class="px-4 py-3 text-right font-semibold w-36 bg-orange-600">Actual</th>
                        <th class="px-4 py-3 text-right font-semibold w-36 bg-green-700">Avail. Budget</th>
                        <th class="px-4 py-3 text-right font-semibold w-36 bg-teal-700">Avail. Release</th>
                        <th class="px-4 py-3 text-center font-semibold w-28">Action</th>
                    </tr>
                </thead>
                <tbody id="supPlanCostTableBody" class="divide-y divide-gray-100">
                    <tr>
                        <td colspan="8" class="text-center py-10">
                            <svg class="animate-spin h-7 w-7 primary-text mx-auto mb-2" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            <p class="text-gray-500 text-xs">Loading cost data…</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p class="text-xs text-gray-400 mt-2">
            * Avail. Budget = Budget &minus; Release &nbsp;|&nbsp; Avail. Release = Release &minus; Actual
        </p>
    </div>
</div>

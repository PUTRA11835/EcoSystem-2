{{-- ══════════════════════════════════════════════════════════════════════ --}}
{{-- FINANCIAL / SALES DATA + TERM OF PAYMENT (TOP) PLAN                     --}}
{{-- Mirror dari Delivery Project (Delivery Information → Sales Data + TOP).  --}}
{{-- Disesuaikan untuk Delivery Support: tambah IO Number, tanpa field AE.    --}}
{{-- ══════════════════════════════════════════════════════════════════════ --}}
<div class="bg-white shadow-md rounded-lg mt-6 mb-6">
    <div class="p-6 border-b border-gray-200">
        <h2 class="text-lg font-semibold text-gray-700">Financial Information</h2>
        <p class="mt-1 text-sm text-gray-600">Sales data and Term Of Payment plan</p>
    </div>
    <form id="supportFinancialForm" action="{{ route('delivery.support.financial-info.update', $support->id) }}" method="POST" class="p-6">
        @csrf @method('PATCH')

        {{-- Sales Data --}}
        <div class="mb-6">
            <h4 class="text-lg font-medium text-gray-900 mb-4">Sales Data</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                {{-- IO Number --}}
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">IO Number</label>
                    <input type="text" name="io_number" value="{{ $support->io_number }}" maxlength="255"
                           class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus"
                           placeholder="e.g. IO-2026-001">
                    <p class="mt-1 text-xs text-gray-500">Must be unique across all delivery supports.</p>
                </div>
                {{-- Revenue --}}
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Revenue</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-gray-500 pointer-events-none">Rp.</span>
                        <input type="text" id="sfin_rev_disp" inputmode="numeric" autocomplete="off"
                               class="block w-full pl-9 pr-3 py-2.5 border border-gray-300 rounded-md shadow-sm text-sm primary-focus text-right"
                               placeholder="0">
                        <input type="hidden" name="revenue" id="sfin_rev_val" value="{{ $support->revenue }}">
                    </div>
                </div>
                {{-- Plan Cost --}}
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Plan Cost</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-gray-500 pointer-events-none">Rp.</span>
                        <input type="text" id="sfin_pc_disp" inputmode="numeric" autocomplete="off"
                               class="block w-full pl-9 pr-3 py-2.5 border border-gray-300 rounded-md shadow-sm text-sm primary-focus text-right"
                               placeholder="0">
                        <input type="hidden" name="plan_cost" id="sfin_pc_val" value="{{ $support->plan_cost }}">
                    </div>
                </div>
                {{-- Gross Profit (auto-calc: Revenue - Plan Cost) --}}
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Gross Profit</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-gray-500 pointer-events-none">Rp.</span>
                        <input type="text" id="sfin_gp_disp" readonly tabindex="-1"
                               class="block w-full pl-9 pr-3 py-2.5 border border-gray-200 rounded-md bg-gray-50 cursor-not-allowed text-sm text-gray-500 text-right"
                               placeholder="0">
                        <input type="hidden" name="gross_profit" id="sfin_gp_val" value="{{ $support->gross_profit }}">
                    </div>
                </div>
                {{-- % Gross Profit (auto-calc: GP / Revenue × 100) --}}
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">% Gross Profit</label>
                    <div class="relative">
                        <input type="text" id="sfin_pct_disp" readonly tabindex="-1"
                               class="block w-full pr-9 pl-3 py-2.5 border border-gray-200 rounded-md bg-gray-50 cursor-not-allowed text-sm text-gray-500 text-right"
                               placeholder="0,00">
                        <span class="absolute inset-y-0 right-0 flex items-center pr-3 text-sm text-gray-500 pointer-events-none">%</span>
                        <input type="hidden" name="gross_profit_percentage" id="sfin_pct_val" value="{{ $support->gross_profit_percentage }}">
                    </div>
                </div>
                {{-- Actual Cost (auto: Total Actual dari expense detail Plan Cost) --}}
                <div class="lg:col-start-2">
                    <label class="block text-sm font-medium text-gray-900 mb-1">Actual Cost</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-gray-500 pointer-events-none">Rp.</span>
                        <input type="text" id="sfin_ac_disp" readonly tabindex="-1"
                               class="block w-full pl-9 pr-3 py-2.5 border border-gray-200 rounded-md bg-gray-50 cursor-not-allowed text-sm text-gray-500 text-right"
                               placeholder="0">
                        <input type="hidden" id="sfin_ac_val" value="{{ $actualCost ?? 0 }}">
                    </div>
                </div>
                {{-- Actual Gross Profit (auto-calc: Revenue − Actual Cost) --}}
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Actual Gross Profit</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-gray-500 pointer-events-none">Rp.</span>
                        <input type="text" id="sfin_agp_disp" readonly tabindex="-1"
                               class="block w-full pl-9 pr-3 py-2.5 border border-gray-200 rounded-md bg-gray-50 cursor-not-allowed text-sm text-gray-500 text-right"
                               placeholder="0">
                        <input type="hidden" id="sfin_agp_val" value="">
                    </div>
                </div>
                {{-- % Actual Gross Profit (auto-calc: Actual GP / Revenue × 100) --}}
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">% Actual Gross Profit</label>
                    <div class="relative">
                        <input type="text" id="sfin_apct_disp" readonly tabindex="-1"
                               class="block w-full pr-9 pl-3 py-2.5 border border-gray-200 rounded-md bg-gray-50 cursor-not-allowed text-sm text-gray-500 text-right"
                               placeholder="0,00">
                        <span class="absolute inset-y-0 right-0 flex items-center pr-3 text-sm text-gray-500 pointer-events-none">%</span>
                        <input type="hidden" id="sfin_apct_val" value="">
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Term Of Payment (TOP) Plan ─────────────────────────── --}}
        <div class="mt-8 pt-6 border-t border-gray-200" data-support-id="{{ $support->id }}">
            <div class="flex justify-between items-center flex-wrap gap-3 mb-4">
                <div>
                    <h4 class="text-lg font-medium text-gray-900">Term Of Payment Plan</h4>
                </div>
                <button type="button" onclick="SupportPaymentTermPlan.openAdd()"
                        class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add Payment Term
                </button>
            </div>

            <div class="overflow-x-auto rounded-lg border border-gray-200">
                <table class="min-w-full text-sm border-collapse" id="supPaymentTermTable">
                    <thead>
                        <tr class="bg-gray-700 text-white">
                            <th class="px-3 py-3 text-center font-semibold whitespace-nowrap w-[50px]">No</th>
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[160px]">Payment Term</th>
                            <th class="px-3 py-3 text-center font-semibold whitespace-nowrap w-[110px]">Payment %</th>
                            <th class="px-3 py-3 text-right font-semibold whitespace-nowrap min-w-[150px]">Amount</th>
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[220px]">Payment Requirements / Evidence</th>
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[130px]">Estimated Date</th>
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[150px]">Submit Invoice Date</th>
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[130px]">Invoice No</th>
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[130px]">Paid Date</th>
                            <th class="px-3 py-3 text-center font-semibold whitespace-nowrap w-[100px]">Status</th>
                            <th class="px-3 py-3 text-center font-semibold whitespace-nowrap w-[80px]">Action</th>
                        </tr>
                    </thead>
                    <tbody id="supPaymentTermBody" class="divide-y divide-gray-100 bg-white">
                        <tr>
                            <td colspan="11" class="text-center py-8">
                                <svg class="animate-spin h-5 w-5 primary-text mx-auto mb-2" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                                <p class="text-gray-500 text-xs">Loading payment terms…</p>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="bg-gray-50 border-t-2 border-gray-200">
                        <tr id="supPaymentTermFooter" class="font-semibold text-gray-700">
                            <td class="px-3 py-3 text-center" colspan="2">Total</td>
                            <td class="px-3 py-3 text-center" id="supPtTotalPct">0%</td>
                            <td class="px-3 py-3 text-right" id="supPtTotalAmount">Rp 0</td>
                            <td class="px-3 py-3" colspan="7"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        {{-- Tombol simpan Financial Information (Sales Data + TOP amounts derived) --}}
        <div class="mt-6 text-right">
            <button type="submit" class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                Update Information
            </button>
        </div>
    </form>
</div>

{{-- ══════════════════════════════════════════════════════════════════════ --}}
{{-- PLAN COST                                                              --}}
{{-- ══════════════════════════════════════════════════════════════════════ --}}
<div class="bg-white shadow-md rounded-lg mb-6" data-support-id="{{ $support->id }}">
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
            <button type="button" onclick="SupportPlanCost.openAddParentModal()"
                    class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Add Cost Item
            </button>
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

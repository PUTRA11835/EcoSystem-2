{{-- ══════════════════════════════════════════════════════════════════════ --}}
{{-- FINANCIAL / SALES DATA + TERM OF PAYMENT (TOP) PLAN                     --}}
{{-- Mirror dari Delivery Project (Delivery Information → Sales Data + TOP).  --}}
{{-- Disesuaikan untuk Delivery Support: tambah IO Number, tanpa field AE.    --}}
{{-- ══════════════════════════════════════════════════════════════════════ --}}
<div class="bg-white shadow-md rounded-lg">
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
                {{-- Vendor (opsional) — Business Partner bertipe Vendor di Master
                     Business Partner. Sengaja di kolom yang sama dengan IO Number. --}}
                <div class="lg:col-start-1">
                    <label class="block text-sm font-medium text-gray-900 mb-1">Vendor</label>
                    <select name="vendor_id" id="sfin_vendor" data-searchable="true"
                            class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus bg-white">
                        <option value="">— No vendor —</option>
                        @foreach($vendors ?? [] as $vendor)
                            <option value="{{ $vendor->customer_id }}" {{ (int) $support->vendor_id === (int) $vendor->customer_id ? 'selected' : '' }}>
                                {{ $vendor->basicData->name_1 ?? $vendor->customer_code }}
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-500">Optional. Business partners with type Vendor only.</p>
                </div>
            </div>
        </div>

        {{-- ── Term Of Payment (TOP) Plan ─────────────────────────── --}}
        <div class="mt-8 pt-6 border-t border-gray-200" data-support-id="{{ $support->id }}">
            {{-- Type TOP: % dari revenue Sales Data, atau amount dari Contract Line Item --}}
            <div class="flex items-center flex-wrap gap-x-4 gap-y-2 mb-5">
                <span class="text-sm font-semibold text-gray-900">TOP Type</span>
                <div class="inline-flex rounded-lg border border-gray-300 overflow-hidden" role="group" id="supPtModeToggle">
                    <button type="button" data-mode="percentage" data-perm-action="edit"
                            onclick="SupportPaymentTermPlan.switchMode('percentage')"
                            class="pt-mode-btn px-4 py-2 text-sm font-medium transition">% of Revenue</button>
                    <button type="button" data-mode="line_item" data-perm-action="edit"
                            onclick="SupportPaymentTermPlan.switchMode('line_item')"
                            class="pt-mode-btn px-4 py-2 text-sm font-medium border-l border-gray-300 transition">Contract Line Item</button>
                </div>
                <p id="supPtModeHint" class="text-xs text-gray-500 basis-full sm:basis-auto"></p>
            </div>

            {{-- Peringatan non-blocking (mis. total TOP melebihi revenue Sales Data) --}}
            <div id="supPtWarnings" class="hidden mb-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800"></div>

            {{-- Contract Line Items — hanya di mode Line Item --}}
            <div id="supPtLineItemSection" class="hidden mb-8">
                <div class="flex justify-between items-center flex-wrap gap-3 mb-4">
                    <div>
                        <h4 class="text-lg font-medium text-gray-900">Contract Line Items</h4>
                        <p class="text-xs text-gray-500 mt-0.5">Add the contract line items first — every payment term takes its amount from one of them.</p>
                    </div>
                    @if($can('delivery-support.financial.manage'))
                    <button type="button" onclick="SupportPaymentTermPlan.openAddLineItem()"
                            class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Add Line Item
                    </button>
                    @endif
                </div>
                <div class="overflow-x-auto rounded-lg border border-gray-200">
                    <table class="min-w-full text-sm border-collapse">
                        <thead>
                            <tr class="bg-gray-700 text-white">
                                <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[160px]">Line Item</th>
                                <th class="px-3 py-3 text-left font-semibold whitespace-nowrap">Type</th>
                                <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[180px]">Schedule</th>
                                <th class="px-3 py-3 text-right font-semibold whitespace-nowrap">Nominal</th>
                                <th class="px-3 py-3 text-right font-semibold whitespace-nowrap">Total</th>
                                <th class="px-3 py-3 text-right font-semibold whitespace-nowrap">Billed in TOP</th>
                                <th class="px-3 py-3 text-center font-semibold whitespace-nowrap min-w-[220px]">Action</th>
                            </tr>
                        </thead>
                        <tbody id="supPtLineItemBody" class="divide-y divide-gray-100 bg-white"></tbody>
                        {{-- Total nilai kontrak vs revenue Sales Data --}}
                        <tfoot id="supPtLineItemFoot" class="bg-gray-50 border-t-2 border-gray-200"></tfoot>
                    </table>
                </div>
            </div>

            <div class="flex justify-between items-center flex-wrap gap-3 mb-4">
                <div>
                    <h4 class="text-lg font-medium text-gray-900">Term Of Payment Plan</h4>
                </div>
                @if($can('delivery-support.financial.manage'))
                <button type="button" onclick="SupportPaymentTermPlan.openAdd()"
                        class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add Payment Term
                </button>
                @endif
            </div>

            <div class="overflow-x-auto rounded-lg border border-gray-200">
                <table class="min-w-full text-sm border-collapse" id="supPaymentTermTable">
                    {{-- Kolom dirender JS: mode Line Item menambah kolom "Period" --}}
                    <thead id="supPaymentTermHead"></thead>
                    <tbody id="supPaymentTermBody" class="divide-y divide-gray-100 bg-white">
                        <tr>
                            <td colspan="12" class="text-center py-8">
                                <svg class="animate-spin h-5 w-5 primary-text mx-auto mb-2" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                                <p class="text-gray-500 text-xs">Loading payment terms…</p>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot id="supPaymentTermFoot" class="bg-gray-50 border-t-2 border-gray-200"></tfoot>
                </table>
            </div>
        </div>

        {{-- Tombol simpan Financial Information (Sales Data + TOP amounts derived) --}}
        @if($can('delivery-support.financial.edit'))
        <div class="mt-6 text-right">
            <button type="submit" class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                Update Information
            </button>
        </div>
        @endif
    </form>
</div>

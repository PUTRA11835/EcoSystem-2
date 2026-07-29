{{-- ══════════════════════════════════════════════════════════════════════════
     MODAL — EDIT DELIVERY INFORMATION (Sales Data)
     Field-nya dipindah apa adanya dari section inline lama; id, name, dan
     data-* dipertahankan supaya seluruh JS terkait (custom-dd, flatpickr,
     kalkulasi finansial, attachSectionForm) tetap terikat tanpa perubahan.
     ══════════════════════════════════════════════════════════════════════ --}}
<div id="deliveryInfoModal" class="fixed inset-0 z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black bg-opacity-50" onclick="closeModal('deliveryInfoModal')"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-content bg-white rounded-lg shadow-xl max-w-5xl w-full max-h-[90vh] flex flex-col">
            <div class="p-6 border-b border-gray-200 flex-shrink-0">
                <h3 class="text-lg font-semibold text-gray-900">Edit Delivery Information</h3>
            </div>
            <form id="deliveryInfoForm" action="{{ route('projects.updateDeliveryInfo', $project->id) }}" method="POST" class="flex-1 flex flex-col min-h-0">
                @csrf @method('PATCH')
                <div class="modal-body p-6 overflow-y-auto flex-1">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Account Executive Type</label>
                    <div class="custom-dd relative" data-fixed="true" data-onchange="toggleAEFields">
                        <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-4 py-2.5 bg-white border border-gray-300 rounded-lg shadow-sm text-sm hover:border-gray-400 transition-all text-left">
                            <span class="custom-dd-label {{ $project->ae_type ? 'text-gray-700' : 'text-gray-500' }}">{{ $project->ae_type ?: '-- Select --' }}</span>
                            <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <input type="hidden" name="ae_type" id="ae_type" value="{{ $project->ae_type }}">
                        <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:200px;">
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">-- Select --</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Internal">Internal</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="External">External</button>
                        </div>
                    </div>
                </div>
                <div id="ae_name_container">
                    <label class="block text-sm font-medium text-gray-900 mb-1">Account Executive Name</label>
                    @php
                        $aeName       = $project->ae_name;
                        $aeIsInternal = ($project->ae_type === 'Internal');
                        $aeIsExternal = ($project->ae_type === 'External');
                        $aeHasType    = ($aeIsInternal || $aeIsExternal);
                    @endphp
                    {{-- Placeholder ter-disable: tampil sampai Account Executive Type dipilih --}}
                    <input type="text" id="ae_name_placeholder" disabled
                           placeholder="-- Select type first --"
                           style="{{ $aeHasType ? 'display:none;' : '' }}"
                           class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm bg-gray-100 text-gray-400 cursor-not-allowed">
                    {{-- Custom-dd dengan search untuk AE Internal (konsisten dengan halaman create) --}}
                    <div id="ae_employee_dd_wrapper" style="{{ $aeIsInternal ? '' : 'display:none;' }}">
                        <div class="custom-dd relative" data-fixed="true" data-onchange="fillAEContactInfo">
                            <button type="button" class="custom-dd-btn w-full flex items-center justify-between py-2.5 px-3 bg-white border border-gray-300 rounded-md shadow-sm text-sm hover:border-gray-400 transition-all text-left">
                                <span class="custom-dd-label {{ ($aeIsInternal && $aeName) ? 'text-gray-700' : 'text-gray-500' }}">{{ ($aeIsInternal && $aeName) ? $aeName : '-- Select Employee --' }}</span>
                                <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <input type="hidden" name="{{ $aeIsInternal ? 'ae_name' : '' }}" id="ae_employee_hidden" value="{{ $aeIsInternal ? $aeName : '' }}">
                            <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:400px;">
                                <div class="custom-dd-search-wrap sticky top-0 bg-white border-b border-gray-100 px-2 py-2" style="z-index:1">
                                    <input type="text" class="custom-dd-search w-full px-3 py-1.5 text-sm border border-gray-200 rounded-md focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400" placeholder="Search employee…" autocomplete="off" spellcheck="false">
                                </div>
                                <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">-- Select Employee --</button>
                                @foreach($aeEmployees as $employee)
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="{{ $employee->basicData->full_name ?? '-' }}">{{ $employee->basicData->full_name ?? '-' }}</button>
                                @endforeach
                                <div class="custom-dd-empty hidden px-4 py-3 text-sm text-gray-400 text-center">No results</div>
                            </div>
                        </div>
                    </div>
                    {{-- Text input untuk AE External --}}
                    <input type="text" name="{{ $aeIsExternal ? 'ae_name' : '' }}" id="ae_name_input"
                           value="{{ $aeIsExternal ? $aeName : '' }}"
                           style="{{ $aeIsExternal ? '' : 'display:none;' }}"
                           class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">AE Phone</label>
                    <input type="text" name="ae_phone" value="{{ $project->ae_phone }}"
                           class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus"
                           placeholder="e.g. +6281234567890">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">AE Email</label>
                    <input type="email" name="ae_email" value="{{ $project->ae_email }}"
                           class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus"
                           placeholder="e.g. ae@example.com">
                </div>
                {{-- Revenue --}}
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Revenue</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-gray-500 pointer-events-none">Rp.</span>
                        <input type="text" id="sfin_rev_disp" inputmode="numeric" autocomplete="off"
                               class="block w-full pl-9 pr-3 py-2.5 border border-gray-300 rounded-md shadow-sm text-sm primary-focus text-right"
                               placeholder="0">
                        <input type="hidden" name="revenue" id="sfin_rev_val" value="{{ $project->revenue }}">
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
                        <input type="hidden" name="plan_cost" id="sfin_pc_val" value="{{ $project->plan_cost }}">
                    </div>
                </div>
                {{-- Gross Profit (auto-calc: Revenue - Plan Cost) --}}
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">
                        Gross Profit
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-gray-500 pointer-events-none">Rp.</span>
                        <input type="text" id="sfin_gp_disp" readonly tabindex="-1"
                               class="block w-full pl-9 pr-3 py-2.5 border border-gray-200 rounded-md bg-gray-50 cursor-not-allowed text-sm text-gray-500 text-right"
                               placeholder="0">
                        <input type="hidden" name="gross_profit" id="sfin_gp_val" value="{{ $project->gross_profit }}">
                    </div>
                </div>
                {{-- % Gross Profit (auto-calc: GP / Revenue × 100) --}}
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">
                        % Gross Profit
                    </label>
                    <div class="relative">
                        <input type="text" id="sfin_pct_disp" readonly tabindex="-1"
                               class="block w-full pr-9 pl-3 py-2.5 border border-gray-200 rounded-md bg-gray-50 cursor-not-allowed text-sm text-gray-500 text-right"
                               placeholder="0,00">
                        <span class="absolute inset-y-0 right-0 flex items-center pr-3 text-sm text-gray-500 pointer-events-none">%</span>
                        <input type="hidden" name="gross_profit_percentage" id="sfin_pct_val" value="{{ $project->gross_profit_percentage }}">
                    </div>
                </div>
                {{-- Actual Cost (auto: Total Actual dari expense detail Plan Cost).
                     lg:col-start-2 → sejajar tepat di bawah Plan Cost, sehingga
                     baris Actual berpasangan dengan baris Plan di atasnya. --}}
                <div class="lg:col-start-2">
                    <label class="block text-sm font-medium text-gray-900 mb-1">
                        Actual Cost
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-gray-500 pointer-events-none">Rp.</span>
                        <input type="text" id="sfin_ac_disp" readonly tabindex="-1"
                               class="block w-full pl-9 pr-3 py-2.5 border border-gray-200 rounded-md bg-gray-50 cursor-not-allowed text-sm text-gray-500 text-right"
                               placeholder="0">
                        <input type="hidden" id="sfin_ac_val" value="{{ $actualCost }}">
                    </div>
                </div>
                {{-- Actual Gross Profit (auto-calc: Revenue − Actual Cost) --}}
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">
                        Actual Gross Profit
                    </label>
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
                    <label class="block text-sm font-medium text-gray-900 mb-1">
                        % Actual Gross Profit
                    </label>
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
                <div class="p-6 border-t border-gray-200 flex justify-end gap-3 flex-shrink-0">
                    <button type="button" onclick="closeModal('deliveryInfoModal')"
                            class="px-4 py-2 border border-gray-300 text-sm font-medium text-gray-700 rounded-lg hover:bg-gray-50 transition-all duration-200">
                        Cancel
                    </button>
                    <button type="submit" class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                        Update Information
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

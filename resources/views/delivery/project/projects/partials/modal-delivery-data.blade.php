{{-- ══════════════════════════════════════════════════════════════════════════
     MODAL — EDIT DELIVERY DATA
     Field-nya dipindah apa adanya dari section inline lama; id, name, dan
     data-* dipertahankan supaya seluruh JS terkait (custom-dd, flatpickr,
     kalkulasi finansial, attachSectionForm) tetap terikat tanpa perubahan.
     ══════════════════════════════════════════════════════════════════════ --}}
<div id="deliveryDataModal" class="fixed inset-0 z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black bg-opacity-50" onclick="closeModal('deliveryDataModal')"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-content bg-white rounded-lg shadow-xl max-w-3xl w-full max-h-[90vh] flex flex-col">
            <div class="p-6 border-b border-gray-200 flex-shrink-0">
                <h3 class="text-lg font-semibold text-gray-900">Edit Delivery Data</h3>
            </div>
            <form id="deliveryDataForm" action="{{ route('projects.updateDeliveryData', $project->id) }}" method="POST" class="flex-1 flex flex-col min-h-0">
                @csrf @method('PATCH')
                <div class="modal-body p-6 overflow-y-auto flex-1">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Warranty Period <span class="text-gray-400 font-normal">(months)</span></label>
                    <input type="number" name="warranty_period" value="{{ $project->warranty_period }}"
                           min="0"
                           class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus"
                           placeholder="e.g. 12">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Delivery Method</label>
                    <div class="custom-dd relative" data-fixed="true">
                        <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-4 py-2.5 bg-white border border-gray-300 rounded-lg shadow-sm text-sm hover:border-gray-400 transition-all text-left">
                            <span class="custom-dd-label {{ $project->delivery_method ? 'text-gray-700' : 'text-gray-500' }}">{{ $project->delivery_method ?: '-- Select --' }}</span>
                            <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <input type="hidden" name="delivery_method" value="{{ $project->delivery_method }}">
                        <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:200px;">
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">-- Select --</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Onsite">Onsite</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Hybrid">Hybrid</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="WFH">WFH</button>
                        </div>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Total Mandays</label>
                    <input type="number" name="total_mandays" value="{{ $project->total_mandays }}"
                           class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus">
                </div>
            </div>
                </div>
                <div class="p-6 border-t border-gray-200 flex justify-end gap-3 flex-shrink-0">
                    <button type="button" onclick="closeModal('deliveryDataModal')"
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

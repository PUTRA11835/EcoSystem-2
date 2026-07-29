{{-- ══════════════════════════════════════════════════════════════════════════
     MODAL — EDIT LOCATION INFORMATION
     Field-nya dipindah apa adanya dari section inline lama; id, name, dan
     data-* dipertahankan supaya seluruh JS terkait (custom-dd, flatpickr,
     kalkulasi finansial, attachSectionForm) tetap terikat tanpa perubahan.
     ══════════════════════════════════════════════════════════════════════ --}}
<div id="locationInfoModal" class="fixed inset-0 z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black bg-opacity-50" onclick="closeModal('locationInfoModal')"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-content bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] flex flex-col">
            <div class="p-6 border-b border-gray-200 flex-shrink-0">
                <h3 class="text-lg font-semibold text-gray-900">Edit Location Information</h3>
            </div>
            <form id="locationInfoForm" action="{{ route('projects.updateLocationInfo', $project->id) }}" method="POST" class="flex-1 flex flex-col min-h-0">
                @csrf @method('PATCH')
                <div class="modal-body p-6 overflow-y-auto flex-1">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Location Name</label>
                    <input type="text" name="location_name" value="{{ $project->location_name }}"
                           placeholder="Enter Location Name"
                           class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Type of Address</label>
                    <div class="custom-dd relative" data-fixed="true">
                        <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-4 py-2.5 bg-white border border-gray-300 rounded-lg shadow-sm text-sm hover:border-gray-400 transition-all text-left">
                            <span class="custom-dd-label {{ $project->location_type ? 'text-gray-700' : 'text-gray-500' }}">{{ $project->location_type ?: '-- Select --' }}</span>
                            <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <input type="hidden" name="location_type" value="{{ $project->location_type }}">
                        <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:200px;">
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">-- Select --</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Head Office">Head Office</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Plant">Plant</button>
                        </div>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Country</label>
                    <input type="text" value="Indonesia" readonly
                           class="block w-full py-2.5 px-3 border border-gray-200 rounded-md shadow-sm text-sm bg-gray-50 text-gray-500 cursor-not-allowed">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Geographical</label>
                    @php $locGeo = $project->location_geographical; @endphp
                    <div class="custom-dd relative" data-onchange="locUpdateRegions" data-fixed="true">
                        <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-4 py-2.5 bg-white border border-gray-300 rounded-lg shadow-sm text-sm hover:border-gray-400 transition-all text-left">
                            <span class="custom-dd-label {{ $locGeo ? 'text-gray-700' : 'text-gray-500' }}">{{ $locGeo ?: '-- Select Geographical --' }}</span>
                            <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <input type="hidden" name="location_geographical" id="loc_geographical" value="{{ $locGeo }}">
                        <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:240px;">
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">-- Select Geographical --</button>
                            @foreach(['Jawa','Sumatera','Bali & N.Tenggara','Kalimantan','Sulawesi','Maluku','Papua'] as $g)
                                <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="{{ $g }}">{{ $g }}</button>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Region / Province</label>
                    <select name="location_region" id="loc_region"
                            data-no-enhance
                            data-selected="{{ $project->location_region }}"
                            class="block w-full py-2.5 px-3 pr-10 border border-gray-300 rounded-md shadow-sm text-sm appearance-none bg-white hover:border-gray-400 transition-all"
                            style="background-image: url(&quot;data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%239ca3af' stroke-width='2'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E&quot;); background-repeat: no-repeat; background-position: right 0.625rem center; background-size: 1rem;"
                            onchange="locUpdateCities()">
                        <option value="">-- Select Region --</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">City</label>
                    <select name="location_city" id="loc_city"
                            data-no-enhance
                            data-selected="{{ $project->location_city }}"
                            class="block w-full py-2.5 px-3 pr-10 border border-gray-300 rounded-md shadow-sm text-sm appearance-none bg-white hover:border-gray-400 transition-all"
                            style="background-image: url(&quot;data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%239ca3af' stroke-width='2'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E&quot;); background-repeat: no-repeat; background-position: right 0.625rem center; background-size: 1rem;">
                        <option value="">-- Select City --</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Valid From</label>
                    <input type="text" name="location_valid_from" id="loc_valid_from" readonly
                           value="{{ $project->location_valid_from ? \Carbon\Carbon::parse($project->location_valid_from)->format('Y-m-d') : '' }}"
                           placeholder="Select Valid From Date"
                           class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus cursor-pointer">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Valid To</label>
                    <input type="text" name="location_valid_to" id="loc_valid_to" readonly
                           value="{{ $project->location_valid_to ? \Carbon\Carbon::parse($project->location_valid_to)->format('Y-m-d') : '' }}"
                           placeholder="Select Valid To Date"
                           class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus cursor-pointer">
                </div>
                <div class="md:col-span-2 lg:col-span-3">
                    <label class="block text-sm font-medium text-gray-900 mb-1">Street Address</label>
                    <textarea name="location_street" rows="2"
                              class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus">{{ $project->location_street }}</textarea>
                </div>
            </div>
                </div>
                <div class="p-6 border-t border-gray-200 flex justify-end gap-3 flex-shrink-0">
                    <button type="button" onclick="closeModal('locationInfoModal')"
                            class="px-4 py-2 border border-gray-300 text-sm font-medium text-gray-700 rounded-lg hover:bg-gray-50 transition-all duration-200">
                        Cancel
                    </button>
                    <button type="submit" class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                        Update Location
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

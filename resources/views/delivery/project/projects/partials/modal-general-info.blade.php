{{-- ══════════════════════════════════════════════════════════════════════════
     MODAL — EDIT GENERAL INFORMATION
     Field-nya dipindah apa adanya dari section inline lama; id, name, dan
     data-* dipertahankan supaya seluruh JS terkait (custom-dd, flatpickr,
     kalkulasi finansial, attachSectionForm) tetap terikat tanpa perubahan.
     ══════════════════════════════════════════════════════════════════════ --}}
<div id="generalInfoModal" class="fixed inset-0 z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black bg-opacity-50" onclick="closeModal('generalInfoModal')"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-content bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] flex flex-col">
            <div class="p-6 border-b border-gray-200 flex-shrink-0">
                <h3 class="text-lg font-semibold text-gray-900">Edit General Information</h3>
            </div>
            <form id="generalInfoForm" action="{{ route('projects.updateGeneralInfo', $project->id) }}" method="POST" class="flex-1 flex flex-col min-h-0">
                @csrf @method('PATCH')
                <div class="modal-body p-6 overflow-y-auto flex-1">
            @php $clientLabel = $project->client->basicData->name_1 ?? ''; @endphp
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Customer --}}
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Customer</label>
                    <div class="custom-dd relative" data-fixed="true">
                        <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-4 py-2.5 bg-white border border-gray-300 rounded-lg shadow-sm text-sm hover:border-gray-400 transition-all text-left">
                            <span class="custom-dd-label {{ $clientLabel ? 'text-gray-700' : 'text-gray-500' }}">{{ $clientLabel ?: '-- Select Client --' }}</span>
                            <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <input type="hidden" name="client_id" value="{{ $project->client_id }}">
                        <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:400px;">
                            <div class="custom-dd-search-wrap sticky top-0 bg-white border-b border-gray-100 px-2 py-2" style="z-index:1">
                                <input type="text" class="custom-dd-search w-full px-3 py-1.5 text-sm border border-gray-200 rounded-md focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400" placeholder="Search client…" autocomplete="off" spellcheck="false">
                            </div>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">-- Select Client --</button>
                            @foreach($clients as $client)
                                <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="{{ $client->customer_id }}">{{ $client->basicData->name_1 ?? $client->email ?? 'Unknown' }}</button>
                            @endforeach
                            <div class="custom-dd-empty hidden px-4 py-3 text-sm text-gray-400 text-center">No results</div>
                        </div>
                    </div>
                </div>
                {{-- Project Name --}}
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Project Name</label>
                    <input type="text" name="name" value="{{ $project->name }}" required
                           class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus"
                           placeholder="Enter project name">
                </div>
                {{-- Project Owner --}}
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Project Owner</label>
                    <div class="custom-dd relative" data-fixed="true">
                        <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-4 py-2.5 bg-white border border-gray-300 rounded-lg shadow-sm text-sm hover:border-gray-400 transition-all text-left">
                            <span class="custom-dd-label {{ $project->project_owner ? 'text-gray-700' : 'text-gray-500' }}">{{ $project->project_owner ?: '-- Select Project Owner --' }}</span>
                            <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <input type="hidden" name="project_owner" value="{{ $project->project_owner }}">
                        <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:400px;">
                            <div class="custom-dd-search-wrap sticky top-0 bg-white border-b border-gray-100 px-2 py-2" style="z-index:1">
                                <input type="text" class="custom-dd-search w-full px-3 py-1.5 text-sm border border-gray-200 rounded-md focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400" placeholder="Search employee…" autocomplete="off" spellcheck="false">
                            </div>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">-- Select Project Owner --</button>
                            @foreach($employees as $employee)
                                <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="{{ $employee->basicData->full_name ?? '-' }}">{{ $employee->basicData->full_name ?? '-' }}</button>
                            @endforeach
                            <div class="custom-dd-empty hidden px-4 py-3 text-sm text-gray-400 text-center">No results</div>
                        </div>
                    </div>
                </div>
                {{-- Project Type --}}
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Project Type</label>
                    <div class="custom-dd relative" data-fixed="true">
                        <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-4 py-2.5 bg-white border border-gray-300 rounded-lg shadow-sm text-sm hover:border-gray-400 transition-all text-left">
                            <span class="custom-dd-label {{ $project->project_type ? 'text-gray-700' : 'text-gray-500' }}">{{ $project->project_type ?: '-- Select Type --' }}</span>
                            <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <input type="hidden" name="project_type" value="{{ $project->project_type }}">
                        <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:240px;">
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">-- Select Type --</button>
                            @foreach(['Implementation','Roll Out','Migration','Upgrade','WRICEF','Body Hire'] as $pt)
                                <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="{{ $pt }}">{{ $pt }}</button>
                            @endforeach
                        </div>
                    </div>
                </div>
                {{-- High Level Risk --}}
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">High Level Risk</label>
                    <div class="custom-dd relative" data-fixed="true">
                        <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-4 py-2.5 bg-white border border-gray-300 rounded-lg shadow-sm text-sm hover:border-gray-400 transition-all text-left">
                            <span class="custom-dd-label {{ $project->high_level_risk ? 'text-gray-700' : 'text-gray-500' }}">{{ $project->high_level_risk ?: '-- Select Risk Level --' }}</span>
                            <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <input type="hidden" name="high_level_risk" value="{{ $project->high_level_risk }}">
                        <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:200px;">
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">-- Select Risk Level --</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Low">Low</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Moderate">Moderate</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="High">High</button>
                        </div>
                    </div>
                </div>
                {{-- IO/Number Order --}}
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">IO/Number Order</label>
                    <input type="text" name="io_number" value="{{ $project->io_number }}"
                           @if($project->project_type === 'Body Hire') list="io_number_options" autocomplete="off" @endif
                           class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus"
                           placeholder="e.g. IO-2026-001">
                    @if($project->project_type === 'Body Hire')
                        <datalist id="io_number_options">
                            @foreach($sameCompanyIos as $io)<option value="{{ $io }}"></option>@endforeach
                        </datalist>
                        <p class="mt-1 text-xs text-blue-600">
                            <i class="fas fa-info-circle mr-1"></i>Body Hire: pilih IO number yang sudah ada milik company ini, atau ketik IO number baru.
                        </p>
                    @endif
                </div>
                {{-- Contract Start Date --}}
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Contract Start Date <span class="text-red-500">*</span></label>
                    <input type="text" name="contract_start_date" id="contract_start_date" autocomplete="off" readonly required
                           value="{{ $project->contract_start_date ? \Carbon\Carbon::parse($project->contract_start_date)->format('Y-m-d') : '' }}"
                           class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus bg-white cursor-pointer"
                           placeholder="dd-mon-yyyy">
                </div>
                {{-- Contract End Date --}}
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Contract End Date <span class="text-red-500">*</span></label>
                    <input type="text" name="contract_end_date" id="contract_end_date" autocomplete="off" readonly required
                           value="{{ $project->contract_end_date ? \Carbon\Carbon::parse($project->contract_end_date)->format('Y-m-d') : '' }}"
                           class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus bg-white cursor-pointer"
                           placeholder="dd-mon-yyyy">
                </div>
                {{-- Description (full width) --}}
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-900 mb-1">Description</label>
                    <textarea name="description" rows="4"
                              class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus"
                              placeholder="Enter project description">{{ $project->description }}</textarea>
                </div>
            </div>
                </div>
                <div class="p-6 border-t border-gray-200 flex justify-end gap-3 flex-shrink-0">
                    <button type="button" onclick="closeModal('generalInfoModal')"
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

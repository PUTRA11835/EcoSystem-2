<div class="space-y-6">
    <!-- General Information -->
    <div>
        <div class="flex justify-between items-center mb-4 pb-2 border-b border-gray-200">
            <h3 class="text-base font-semibold text-gray-900">General Information</h3>
            <button onclick="saveCustomerBasicData(customerId)" class="inline-flex items-center gap-2 px-4 py-2 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900 transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0Z" />
                </svg>
                Save Changes
            </button>
        </div>
        <div class="grid grid-cols-6 gap-4">
            <!-- Customer Code (Editable, max 4 alphanumeric) -->
            <div class="col-span-1">
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Customer Code <span class="text-red-600">*</span></label>
                <input type="text" id="customerCode" value="{{ $customer->customer_code ?? '' }}"
                    maxlength="50" required
                    oninput="this.value=this.value.toUpperCase().replace(/[^A-Z0-9]/g,'')"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm font-mono tracking-widest focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent uppercase">
            </div>

            <!-- Email Domain (filter inbox emails for ticket validation by sender domain) -->
            <div class="col-span-2">
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Email Domain</label>
                <input type="text" id="customerDomain" value="{{ $customer->domain ?? '' }}"
                    placeholder="@company.com"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
                <span class="text-xs text-gray-400 mt-1 block">Inbox emails whose sender ends with this domain will be routed to this customer's staging tickets.</span>
            </div>

            <!-- Title -->
            <div class="col-span-1">
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Title</label>
                <div class="relative">
                    <input type="text" id="title" value="{{ $customer->basicData->title ?? '' }}" 
                        class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent pr-8">
                    <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Name 1 -->
            <div class="col-span-2">
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Name 1 <span class="text-red-600">*</span></label>
                <input type="text" id="name1" value="{{ $customer->basicData->name_1 ?? '' }}" 
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent" required>
            </div>

            <!-- Search Term 1 -->
            <div class="col-span-1">
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Search Term 1</label>
                <input type="text" id="searchTerm1" value="{{ $customer->basicData->search_term_1 ?? '' }}" 
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>

            <!-- External Number -->
            <div class="col-span-1">
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">External Number</label>
                <input type="text" id="externalNumber" value="{{ $customer->basicData->external_number ?? '' }}" 
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>

            <!-- Name 2 (di bawah Name 1) -->
            <div class="col-span-2 row-start-2 col-start-3">
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Name 2</label>
                <input type="text" id="name2" value="{{ $customer->basicData->name_2 ?? '' }}" 
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>

            <!-- Search Term 2 (di bawah Search Term 1) -->
            <div class="col-span-1 row-start-2 col-start-5">
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Search Term 2</label>
                <input type="text" id="searchTerm2" value="{{ $customer->basicData->search_term_2 ?? '' }}" 
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>

            <!-- Created/Changed Info -->
            <div class="col-span-6 mt-6 pt-4 border-t border-gray-200">
                <h4 class="text-sm font-semibold text-gray-700 mb-3">Audit Information</h4>
                <div class="grid grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <div class="flex gap-4">
                            <span class="text-sm font-semibold text-gray-700 w-32">Created By</span>
                            <span id="createdBy" class="text-sm text-gray-600">
                                {{ $customer->basicData->created_by ?? '-' }}
                            </span>
                        </div>
                        <div class="flex gap-4">
                            <span class="text-sm font-semibold text-gray-700 w-32">Created On</span>
                            <span id="createdOn" class="text-sm text-gray-600">
                                @if($customer->basicData && $customer->basicData->created_on)
                                    {{ \Carbon\Carbon::parse($customer->basicData->created_on)->format('d.m.Y H:i:s') }}
                                @else
                                    -
                                @endif
                            </span>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <div class="flex gap-4">
                            <span class="text-sm font-semibold text-gray-700 w-32">Last Changed By</span>
                            <span id="lastChangedBy" class="text-sm text-gray-600">
                                {{ $customer->basicData->last_changed_by ?? '-' }}
                            </span>
                        </div>
                        <div class="flex gap-4">
                            <span class="text-sm font-semibold text-gray-700 w-32">Last Changed On</span>
                            <span id="lastChangedOn" class="text-sm text-gray-600">
                                @if($customer->basicData && $customer->basicData->last_changed_on)
                                    {{ \Carbon\Carbon::parse($customer->basicData->last_changed_on)->format('d.m.Y H:i:s') }}
                                @else
                                    -
                                @endif
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Customer Information -->
    <div>
        <h3 class="text-base font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-200">Customer Information</h3>

        <div class="grid grid-cols-6 gap-4">
            <!-- Customer Group -->
            <div class="col-span-1">
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Customer Group</label>
                <div class="relative">
                    <input type="text" id="customerGroup" value="{{ $customer->basicData->customer_group ?? '' }}" 
                        class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent pr-8">
                    <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Credit Limit Type -->
            <div class="col-span-1">
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Credit Limit Type</label>
                <div class="relative">
                    <input type="text" id="creditLimitType" value="{{ $customer->basicData->credit_limit_type ?? '' }}" 
                        class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent pr-8">
                    <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Industry Sector -->
            <div class="col-span-1">
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Industry Sector</label>
                <div class="relative">
                    <input type="text" id="industrySector" value="{{ $customer->basicData->industry_sector ?? '' }}" 
                        class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent pr-8">
                    <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- EC Account Executive -->
            <div class="col-span-1">
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">EC Account Executive</label>
                @php
                    $ecCurrent = $customer->basicData->ec_account_executive ?? '';
                    $ecMatched = collect($employees ?? [])->contains(fn($e) => $e['eci'] === $ecCurrent);
                @endphp
                <select id="ecAccountExecutive" data-searchable="true"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent bg-white">
                    <option value="">— Select Employee —</option>
                    @foreach(($employees ?? []) as $emp)
                        <option value="{{ $emp['eci'] }}" {{ $ecCurrent === $emp['eci'] ? 'selected' : '' }}>
                            {{ $emp['name'] }} ({{ $emp['eci'] }})
                        </option>
                    @endforeach
                    @if($ecCurrent !== '' && !$ecMatched)
                        {{-- Legacy value not matching any employee ECI — keep it so it isn't lost --}}
                        <option value="{{ $ecCurrent }}" selected>{{ $ecCurrent }}</option>
                    @endif
                </select>
            </div>

            <!-- Authorization Group -->
            <div class="col-span-1">
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Authorization Group</label>
                <input type="text" id="authorizationGroup" value="{{ $customer->basicData->authorization_group ?? '' }}" 
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>

            <!-- Block Checkbox -->
            <div class="col-span-1 flex items-center pt-4">
                <div class="flex items-center gap-2">
                    <input type="checkbox" id="block" {{ (isset($customer->basicData->block) && $customer->basicData->block) ? 'checked' : '' }}
                        class="w-4 h-4 text-red-800 border-gray-300 rounded focus:ring-red-800">
                    <label for="block" class="text-sm font-semibold text-gray-700">Block</label>
                </div>
            </div>

            <!-- Customer Category -->
            <div class="col-span-2 row-start-2">
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Customer Category</label>
                <div class="relative">
                    <input type="text" id="customerCategory" value="{{ $customer->basicData->customer_category ?? '' }}" 
                        class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent pr-8">
                    <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- SAP Account Executive -->
            <div class="col-span-2 row-start-2">
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">SAP Account Executive</label>
                <input type="text" id="sapAccountExecutive" value="{{ $customer->basicData->sap_account_executive ?? '' }}" 
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>

            <!-- Deletion Flag Checkbox -->
            <div class="col-span-1 row-start-2 flex items-center pt-4">
                <div class="flex items-center gap-2">
                    <input type="checkbox" id="deletionFlag" {{ (isset($customer->basicData->deletion_flag) && $customer->basicData->deletion_flag) ? 'checked' : '' }}
                        class="w-4 h-4 text-red-800 border-gray-300 rounded focus:ring-red-800">
                    <label for="deletionFlag" class="text-sm font-semibold text-gray-700">Deletion Flag</label>
                </div>
            </div>
        </div>
    </div>
</div>
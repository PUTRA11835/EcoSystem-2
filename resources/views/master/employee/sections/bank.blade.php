<div class="space-y-6 {{ (isset($isReadonly) && $isReadonly) ? 'profile-readonly' : '' }}">
    <!-- BANK INFORMATION SECTION (Form untuk Create & Update) -->
    <div>
        <div class="flex justify-between items-center mb-4 pb-2 border-b border-gray-200">
            <div class="flex items-center gap-2">
                <h3 class="text-base font-semibold text-gray-900">Bank Information</h3>
                @if(isset($isReadonly) && $isReadonly)
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 text-xs font-medium"><i class="fas fa-lock text-[10px]"></i> View Only</span>
                @endif
            </div>
            <div class="flex gap-2 js-section-action">
                <button type="button" onclick="clearBankForm()" class="inline-flex items-center gap-1.5 px-3 py-2 bg-gray-500 text-white text-xs font-semibold rounded-lg hover:bg-gray-600 transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                    </svg>
                    New
                </button>
                <button type="button" onclick="saveBank()" class="inline-flex items-center gap-1.5 px-3 py-2 bg-red-800 text-white text-xs font-semibold rounded-lg hover:bg-red-900 transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0Z" />
                    </svg>
                    <span id="saveBankButtonText">Save</span>
                </button>
            </div>
        </div>
        
        <input type="hidden" id="editBankId">
        
        <!-- Bank Account Details Section -->
        <div class="mb-6">
            <h5 class="text-sm font-bold text-gray-900 mb-3 pb-2 border-b border-gray-200"> Bank Account Details</h5>
            <div class="grid grid-cols-6 gap-4">
                <!-- Bank Name -->
                <div class="col-span-2">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Bank Name <span class="text-red-600">*</span></label>
                    <div class="custom-dd relative">
                        <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-3 py-2.5 bg-white border border-gray-300 rounded-lg text-sm hover:border-gray-400 transition-all text-left">
                            <span class="custom-dd-label text-gray-500">Select Bank</span>
                            <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <input type="hidden" id="bankName" value="">
                        <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:220px;">
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">Select Bank</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="BCA">Bank Central Asia (BCA)</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Mandiri">Bank Mandiri</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="BNI">Bank Negara Indonesia (BNI)</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="BRI">Bank Rakyat Indonesia (BRI)</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="CIMB Niaga">CIMB Niaga</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Danamon">Bank Danamon</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Permata">Bank Permata</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="BTN">Bank Tabungan Negara (BTN)</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="OCBC NISP">Bank OCBC NISP</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Maybank">Maybank Indonesia</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Panin">Bank Panin</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="BTPN">Bank BTPN</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Jenius">Jenius (BTPN)</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Bank Jago">Bank Jago</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Seabank">Seabank</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Blu BCA">Blu by BCA Digital</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Allo Bank">Allo Bank</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Neobank">Neobank</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Other">Other</button>
                        </div>
                    </div>
                </div>

                <!-- Bank Key -->
                <div class="col-span-1">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Bank Key</label>
                    <input type="text" id="bankKey" placeholder="BCA, 014" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>

                <!-- Account Number -->
                <div class="col-span-2">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Account Number <span class="text-red-600">*</span></label>
                    <input type="text" id="bankAccountNumber" placeholder="1234567890" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>

                <!-- Account Holder -->
                <div class="col-span-1">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Account Holder</label>
                    <input type="text" id="bankAccountHolder" placeholder="John Doe" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>
            </div>
        </div>

        <!-- Validity Period Section -->
        <div class="mb-6">
            <h5 class="text-sm font-bold text-gray-900 mb-3 pb-2 border-b border-gray-200"> Validity Period</h5>
            <div class="grid grid-cols-6 gap-4">
                <!-- Valid From -->
                <div class="col-span-3">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Valid From</label>
                    <input type="date" id="bankValidFrom" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>

                <!-- Valid To -->
                <div class="col-span-3">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Valid To</label>
                    <input type="date" id="bankValidTo" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>
            </div>
        </div>

        <!-- Attachments Section -->
        <div class="bg-gray-50 rounded-lg p-4">
            <h5 class="text-sm font-bold text-gray-900 mb-3"> Attachments</h5>
            <div class="grid grid-cols-6 gap-4">
                <!-- Drive Link -->
                <div class="col-span-3">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Drive Link</label>
                    <input type="url" id="bankDriveLink" placeholder="https://drive.google.com/..." class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>

                <!-- Verify Link -->
                <div class="col-span-3">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Verify Link</label>
                    <input type="url" id="bankVerifyLink" placeholder="https://" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>
            </div>
        </div>
    </div>

    <!-- BANK DETAILS SECTION (Table) -->
    <div>
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-base font-semibold text-gray-900">Bank Details</h3>
            <div class="flex items-center gap-3">
                <!-- Search -->
                <div class="relative">
                    <input type="text" id="bankSearch" placeholder="Search" class="w-64 px-3 py-2 pr-10 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
                    <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                    </button>
                </div>

                <!-- Action Buttons -->
                <button onclick="copySelectedBank()" title="Copy" class="inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-gray-300 text-gray-700 text-xs font-semibold rounded-lg hover:bg-gray-50 transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" />
                    </svg>
                    Copy
                </button>

                <button onclick="deleteSelectedBank()" title="Delete" class="inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-red-600 text-red-600 text-xs font-semibold rounded-lg hover:bg-red-50 transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                    </svg>
                    Delete
                </button>

                <!-- Settings button -->
                <button title="Settings" class="w-8 h-8 flex items-center justify-center border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50 transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
                </button>

                <!-- Export button -->
                <button title="Export" class="w-8 h-8 flex items-center justify-center border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50 transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto border border-gray-200 rounded-lg">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="w-10 px-4 py-3 text-left">
                            <input type="radio" name="selectedBank" class="w-4 h-4 text-red-800 focus:ring-red-800" disabled>
                        </th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Bank Name</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Account Number</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Account Holder</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Bank Key</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Validity</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Status</th>
                        <th class="w-10 px-4 py-3 text-left">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-gray-500">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                            </svg>
                        </th>
                    </tr>
                </thead>
                <tbody id="bankTableBody" class="bg-white divide-y divide-gray-100">
                    <!-- Dynamic rows will be inserted here -->
                    <tr>
                        <td colspan="8" class="px-4 py-16 text-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 mx-auto mb-4 text-gray-300">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
                            </svg>
                            <p class="text-base font-medium text-gray-900 mb-2">No bank accounts found</p>
                            <small class="text-sm text-gray-500">Fill the form above and click "Save" to add a new bank account</small>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="confirmDeleteBankModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-md w-full shadow-2xl">
        <div class="p-6">
            <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 bg-red-100 rounded-full">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-red-600">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-900 text-center mb-2">Delete Bank Account</h3>
            <p class="text-sm text-gray-600 text-center mb-1">Are you sure you want to delete this bank account?</p>
            <p class="text-sm font-semibold text-gray-900 text-center mb-6" id="deleteBankInfo"></p>
            <div class="flex gap-3">
                <button onclick="closeConfirmDeleteBank()" class="flex-1 px-4 py-2.5 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">Cancel</button>
                <button onclick="confirmDeleteBank()" class="flex-1 px-4 py-2.5 bg-red-600 text-white text-sm font-semibold rounded-lg hover:bg-red-700 transition-all">Delete</button>
            </div>
        </div>
    </div>
</div>

<script>
    let banksData = [];
    let selectedBankId = null;
    let deleteBankId = null;

    /**
     * Load all banks for this employee
     */
    async function loadBanks() {
        try {
            
            const response = await fetch(`/api/employees/${employeeId}/bank`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin'
            });

            const data = await response.json();

            if (data.success && data.data && data.data.length > 0) {
                banksData = data.data;
                renderBankTable(data.data);
            } else {
                banksData = [];
                renderEmptyTable();
            }
        } catch (error) {
            console.error(' Error loading banks:', error);
            banksData = [];
            renderEmptyTable();
        }
    }

    /**
     * Render bank table with data
     */
    function renderBankTable(banks) {
        const tbody = document.getElementById('bankTableBody');
        
        tbody.innerHTML = banks.map(bank => {
            const validity = formatValidity(bank.valid_from, bank.valid_to);
            const statusBadge = getBankStatusBadge(bank);
            const maskedAccount = maskAccountNumber(bank.account_number);

            return `
                <tr class="hover:bg-gray-50 transition-colors cursor-pointer" onclick="selectBankRow(${bank.bank_id}, event)">
                    <td class="px-4 py-3">
                        <input type="radio" name="selectedBank" value="${bank.bank_id}" 
                            onclick="selectBank(${bank.bank_id})" 
                            class="w-4 h-4 text-red-800 focus:ring-red-800">
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <strong class="font-semibold text-gray-900">${bank.bank_name || '-'}</strong>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <span class="font-mono text-gray-600">${maskedAccount}</span>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">${bank.account_holder || '-'}</td>
                    <td class="px-4 py-3 text-sm">
                        ${bank.bank_key ? `<span class="inline-block px-2 py-1 text-xs font-semibold rounded bg-gray-100 text-gray-700">${bank.bank_key}</span>` : '-'}
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">${validity}</td>
                    <td class="px-4 py-3 text-sm">
                        <div class="flex flex-wrap gap-1">
                            ${statusBadge}
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <button onclick="loadBankToForm(${bank.bank_id}); event.stopPropagation();" class="text-gray-400 hover:text-gray-600">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                            </svg>
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
    }

    /**
     * Render empty table
     */
    function renderEmptyTable() {
        const tbody = document.getElementById('bankTableBody');
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="px-4 py-16 text-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 mx-auto mb-4 text-gray-300">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
                    </svg>
                    <p class="text-base font-medium text-gray-900 mb-2">No bank accounts found</p>
                    <small class="text-sm text-gray-500">Fill the form above and click "Save" to add a new bank account</small>
                </td>
            </tr>
        `;
    }

    /**
     * Mask account number for security
     */
    function maskAccountNumber(accountNumber) {
        if (!accountNumber) return '-';
        const length = accountNumber.length;
        if (length <= 4) return '*'.repeat(length);
        return '*'.repeat(length - 4) + accountNumber.slice(-4);
    }

    /**
     * Format validity period
     */
    function formatValidity(validFrom, validTo) {
        if (!validFrom && !validTo) return 'No expiration';
        const from = validFrom ? formatDate(validFrom) : '-';
        const to = validTo ? formatDate(validTo) : 'No expiration';
        return `${from} - ${to}`;
    }

    /**
     * Get bank status badge
     */
    function getBankStatusBadge(bank) {
        if (!bank.valid_to) {
            return '<span class="inline-block px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">Active</span>';
        }
        
        const validTo = new Date(bank.valid_to);
        const today = new Date();
        const daysRemaining = Math.ceil((validTo - today) / (1000 * 60 * 60 * 24));
        
        if (daysRemaining < 0) {
            return '<span class="inline-block px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Expired</span>';
        } else if (daysRemaining <= 30) {
            return '<span class="inline-block px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">Expiring Soon</span>';
        } else {
            return '<span class="inline-block px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Valid</span>';
        }
    }

    /**
     * Format date for display
     */
    function formatDate(dateString) {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return date.toLocaleDateString('id-ID', { year: 'numeric', month: 'short', day: 'numeric' });
    }

    /**
     * Select bank from radio button
     */
    function selectBank(bankId) {
        selectedBankId = bankId;
        loadBankToForm(bankId);
    }

    /**
     * Select bank row (when clicking on row)
     */
    function selectBankRow(bankId, event) {
        if (event.target.tagName === 'INPUT' || event.target.tagName === 'BUTTON' || event.target.closest('button')) {
            return;
        }
        
        const radio = document.querySelector(`input[name="selectedBank"][value="${bankId}"]`);
        if (radio) {
            radio.checked = true;
            selectBank(bankId);
        }
    }

    /**
     * Load bank data to form fields
     */
    async function loadBankToForm(bankId) {
        try {
            
            const response = await fetch(`/api/employees/${employeeId}/bank/${bankId}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin'
            });

            const data = await response.json();

            if (data.success && data.data) {
                const bank = data.data;
                
                // Set hidden ID untuk update
                document.getElementById('editBankId').value = bank.bank_id;
                
                // Set form values
                setCustomDropdownValue('bankName', bank.bank_name || '');
                document.getElementById('bankKey').value = bank.bank_key || '';
                document.getElementById('bankAccountNumber').value = bank.account_number || '';
                document.getElementById('bankAccountHolder').value = bank.account_holder || '';
                document.getElementById('bankValidFrom').value = bank.valid_from || '';
                document.getElementById('bankValidTo').value = bank.valid_to || '';
                document.getElementById('bankDriveLink').value = bank.drive_link || '';
                document.getElementById('bankVerifyLink').value = bank.verify_link || '';
                
                // Update button text
                document.getElementById('saveBankButtonText').textContent = 'Update';
                
            }
        } catch (error) {
            console.error(' Error loading bank:', error);
        }
    }

    /**
     * Clear form (for create new)
     */
    function clearBankForm() {
        document.getElementById('editBankId').value = '';
        setCustomDropdownValue('bankName', '');
        document.getElementById('bankKey').value = '';
        document.getElementById('bankAccountNumber').value = '';
        document.getElementById('bankAccountHolder').value = '';
        document.getElementById('bankValidFrom').value = '';
        document.getElementById('bankValidTo').value = '';
        document.getElementById('bankDriveLink').value = '';
        document.getElementById('bankVerifyLink').value = '';
        
        // Uncheck radio
        const radios = document.querySelectorAll('input[name="selectedBank"]');
        radios.forEach(radio => radio.checked = false);
        
        selectedBankId = null;
        
        // Update button text
        document.getElementById('saveBankButtonText').textContent = 'Save';
        
        showNotification('Form cleared. Ready to create new bank account.', 'info');
    }

    /**
     * Save bank (create or update)
     */
    async function saveBank() {
        // Validate required fields
        const bankName = document.getElementById('bankName').value;
        const accountNumber = document.getElementById('bankAccountNumber').value;
        
        if (!bankName || !accountNumber) {
            showNotification('Bank name and account number are required', 'error');
            return;
        }

        const bankId = document.getElementById('editBankId').value;
        const isUpdate = bankId !== '';

        const bankData = {
            bank_name: bankName,
            bank_key: document.getElementById('bankKey').value || null,
            account_number: accountNumber,
            account_holder: document.getElementById('bankAccountHolder').value || null,
            valid_from: document.getElementById('bankValidFrom').value || null,
            valid_to: document.getElementById('bankValidTo').value || null,
            drive_link: document.getElementById('bankDriveLink').value || null,
            verify_link: document.getElementById('bankVerifyLink').value || null
        };

        try {
            const url = isUpdate 
                ? `/api/employees/${employeeId}/bank/${bankId}`
                : `/api/employees/${employeeId}/bank`;
            
            const method = isUpdate ? 'PUT' : 'POST';
            
            
            const response = await fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                credentials: 'same-origin',
                body: JSON.stringify(bankData)
            });

            const data = await response.json();
            
            if (data.success) {
                showNotification(
                    isUpdate ? 'Bank account updated successfully!' : 'Bank account created successfully!', 
                    'success'
                );
                loadBanks();
                
                if (!isUpdate) {
                    clearBankForm();
                } else {
                    // Reload the same record
                    loadBankToForm(bankId);
                }
            } else {
                showNotification('Failed to save bank account: ' + (data.message || 'Unknown error'), 'error');
            }
        } catch (error) {
            console.error(' Error saving bank:', error);
            showNotification('An error occurred while saving bank account', 'error');
        }
    }

    /**
     * Copy selected bank
     */
    function copySelectedBank() {
        if (!selectedBankId) {
            showNotification('Please select a bank account first', 'warning');
            return;
        }
        showNotification('Copy feature coming soon', 'info');
    }

    /**
     * Delete selected bank
     */
    function deleteSelectedBank() {
        if (!selectedBankId) {
            showNotification('Please select a bank account first', 'warning');
            return;
        }
        
        deleteBankId = selectedBankId;
        
        const bank = banksData.find(b => b.bank_id === selectedBankId);
        let bankInfo = 'this bank account';
        
        if (bank) {
            bankInfo = `${bank.bank_name || ''} - ${maskAccountNumber(bank.account_number)}`;
        }
        
        document.getElementById('deleteBankInfo').textContent = bankInfo;
        document.getElementById('confirmDeleteBankModal').classList.remove('hidden');
        document.getElementById('confirmDeleteBankModal').classList.add('flex');
    }

    /**
     * Close delete confirmation
     */
    function closeConfirmDeleteBank() {
        document.getElementById('confirmDeleteBankModal').classList.add('hidden');
        document.getElementById('confirmDeleteBankModal').classList.remove('flex');
        deleteBankId = null;
    }

    /**
     * Confirm delete bank
     */
    async function confirmDeleteBank() {
        if (!deleteBankId) return;

        try {
            
            const response = await fetch(`/api/employees/${employeeId}/bank/${deleteBankId}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                credentials: 'same-origin'
            });

            const data = await response.json();
            
            if (data.success) {
                showNotification('Bank account deleted successfully!', 'success');
                closeConfirmDeleteBank();
                selectedBankId = null;
                loadBanks();
                clearBankForm();
            } else {
                showNotification('Failed to delete bank account: ' + (data.message || 'Unknown error'), 'error');
            }
        } catch (error) {
            console.error(' Error deleting bank:', error);
            showNotification('An error occurred while deleting bank account', 'error');
        }
    }

    /**
     * Show notification
     */

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        loadBanks();
    });


    // Close modals on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (!document.getElementById('confirmDeleteBankModal').classList.contains('hidden')) {
                closeConfirmDeleteBank();
            }
        }
    });
</script>
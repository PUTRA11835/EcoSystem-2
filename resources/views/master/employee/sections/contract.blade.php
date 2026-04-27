<div class="space-y-6">
    <!-- CONTRACT INFORMATION SECTION (Form untuk Create & Update) -->
    <div>
        <div class="flex justify-between items-center mb-4 pb-2 border-b border-gray-200">
            <h3 class="text-base font-semibold text-gray-900">Contract Information</h3>
            <div class="flex gap-2">
                <button type="button" onclick="clearContractForm()" class="inline-flex items-center gap-1.5 px-3 py-2 bg-gray-500 text-white text-xs font-semibold rounded-lg hover:bg-gray-600 transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                    </svg>
                    New
                </button>
                <button type="button" onclick="saveContract()" class="inline-flex items-center gap-1.5 px-3 py-2 bg-red-800 text-white text-xs font-semibold rounded-lg hover:bg-red-900 transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0Z" />
                    </svg>
                    <span id="saveContractButtonText">Save</span>
                </button>
            </div>
        </div>
        
        <input type="hidden" id="editContractId">
        
        <!-- Contract Details Section -->
        <div class="mb-6">
            <h5 class="text-sm font-bold text-gray-900 mb-3 pb-2 border-b border-gray-200"> Contract Details</h5>
            <div class="grid grid-cols-6 gap-4">
                <!-- Contract Number -->
                <div class="col-span-1">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Contract No <span class="text-red-600">*</span></label>
                    <input type="text" id="contractNumber" placeholder="CTR-2024-001" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>

                <!-- Contract Name -->
                <div class="col-span-2">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Contract Name <span class="text-red-600">*</span></label>
                    <input type="text" id="contractName" placeholder="Employment Contract 2024" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>

                <!-- Contract Type -->
                <div class="col-span-1">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Type <span class="text-red-600">*</span></label>
                    <div class="custom-dd relative">
                        <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-3 py-2.5 bg-white border border-gray-300 rounded-lg text-sm hover:border-gray-400 transition-all text-left">
                            <span class="custom-dd-label text-gray-500">Select Type</span>
                            <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <input type="hidden" id="contractType" value="">
                        <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5">
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">Select Type</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Permanent">Permanent</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Contract">Contract</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Probation">Probation</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Internship">Internship</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Freelance">Freelance</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Part-Time">Part-Time</button>
                        </div>
                    </div>
                </div>

                <!-- Position -->
                <div class="col-span-1">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Position <span class="text-red-600">*</span></label>
                    <input type="text" id="contractPosition" placeholder="Software Engineer" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>

                <!-- Contract Date -->
                <div class="col-span-1">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Contract Date</label>
                    <input type="date" id="contractDate" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>
            </div>
        </div>

        <!-- Employment Period & Compensation Section -->
        <div class="mb-6">
            <h5 class="text-sm font-bold text-gray-900 mb-3 pb-2 border-b border-gray-200"> Employment Period & Compensation</h5>
            <div class="grid grid-cols-6 gap-4">
                <!-- Start Date -->
                <div class="col-span-1">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Start Date <span class="text-red-600">*</span></label>
                    <input type="date" id="contractStartDate" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>

                <!-- End Date -->
                <div class="col-span-1">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">End Date</label>
                    <input type="date" id="contractEndDate" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>

                <!-- Salary -->
                <div class="col-span-2">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Salary (IDR)</label>
                    <input type="number" id="contractSalary" placeholder="5000000" step="0.01" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>

                <!-- Is Active Checkbox -->
                <div class="col-span-2 flex items-end">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" id="contractIsActive" class="w-4 h-4 text-red-800 border-gray-300 rounded focus:ring-red-800">
                        <span class="text-xs font-semibold text-gray-700">This contract is currently active</span>
                    </label>
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
                    <input type="url" id="contractDriveLink" placeholder="https://drive.google.com/..." class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>

                <!-- Verify Link -->
                <div class="col-span-3">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Verify Link</label>
                    <input type="url" id="contractVerifyLink" placeholder="https://" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>
            </div>
        </div>
    </div>

    <!-- CONTRACT DETAILS SECTION (Table) -->
    <div>
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-base font-semibold text-gray-900">Contract Details</h3>
            <div class="flex items-center gap-3">
                <!-- Search -->
                <div class="relative">
                    <input type="text" id="contractSearch" placeholder="Search" class="w-64 px-3 py-2 pr-10 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
                    <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                    </button>
                </div>

                <!-- Action Buttons -->
                <button onclick="copySelectedContract()" title="Copy" class="inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-gray-300 text-gray-700 text-xs font-semibold rounded-lg hover:bg-gray-50 transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" />
                    </svg>
                    Copy
                </button>

                <button onclick="deleteSelectedContract()" title="Delete" class="inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-red-600 text-red-600 text-xs font-semibold rounded-lg hover:bg-red-50 transition-all">
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
                            <input type="radio" name="selectedContract" class="w-4 h-4 text-red-800 focus:ring-red-800" disabled>
                        </th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Contract Name</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Type</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Position</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Period</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Salary</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Status</th>
                        <th class="w-10 px-4 py-3 text-left">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-gray-500">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                            </svg>
                        </th>
                    </tr>
                </thead>
                <tbody id="contractTableBody" class="bg-white divide-y divide-gray-100">
                    <!-- Dynamic rows will be inserted here -->
                    <tr>
                        <td colspan="8" class="px-4 py-16 text-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 mx-auto mb-4 text-gray-300">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                            </svg>
                            <p class="text-base font-medium text-gray-900 mb-2">No contracts found</p>
                            <small class="text-sm text-gray-500">Fill the form above and click "Save" to add a new contract</small>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="confirmDeleteContractModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-md w-full shadow-2xl">
        <div class="p-6">
            <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 bg-red-100 rounded-full">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-red-600">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-900 text-center mb-2">Delete Contract</h3>
            <p class="text-sm text-gray-600 text-center mb-1">Are you sure you want to delete this contract?</p>
            <p class="text-sm font-semibold text-gray-900 text-center mb-6" id="deleteContractInfo"></p>
            <div class="flex gap-3">
                <button onclick="closeConfirmDeleteContract()" class="flex-1 px-4 py-2.5 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">Cancel</button>
                <button onclick="confirmDeleteContract()" class="flex-1 px-4 py-2.5 bg-red-600 text-white text-sm font-semibold rounded-lg hover:bg-red-700 transition-all">Delete</button>
            </div>
        </div>
    </div>
</div>

<script>
    let contractsData = [];
    let selectedContractId = null;
    let deleteContractId = null;

    /**
     * Load all contracts for this employee
     */
    async function loadContracts() {
        try {
            console.log(' Loading contracts for employee:', employeeId);
            
            const response = await fetch(`/api/employees/${employeeId}/contract`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin'
            });

            const data = await response.json();
            console.log(' Contracts loaded:', data);

            if (data.success && data.data && data.data.length > 0) {
                contractsData = data.data;
                renderContractTable(data.data);
            } else {
                contractsData = [];
                renderEmptyTable();
            }
        } catch (error) {
            console.error(' Error loading contracts:', error);
            contractsData = [];
            renderEmptyTable();
        }
    }

    /**
     * Render contract table with data
     */
    function renderContractTable(contracts) {
        const tbody = document.getElementById('contractTableBody');
        
        tbody.innerHTML = contracts.map(contract => {
            const period = formatContractPeriod(contract.start_date, contract.end_date);
            const statusBadge = getContractStatusBadge(contract);
            const salary = formatSalary(contract.salary);

            return `
                <tr class="hover:bg-gray-50 transition-colors cursor-pointer" onclick="selectContractRow(${contract.contract_id}, event)">
                    <td class="px-4 py-3">
                        <input type="radio" name="selectedContract" value="${contract.contract_id}" 
                            onclick="selectContract(${contract.contract_id})" 
                            class="w-4 h-4 text-red-800 focus:ring-red-800">
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <strong class="font-semibold text-gray-900">${contract.contract_name || '-'}</strong>
                        ${contract.contract_number ? `<br><small class="text-xs text-gray-500">${contract.contract_number}</small>` : ''}
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <span class="inline-block px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                            ${contract.contract_type || '-'}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">${contract.position || '-'}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${period}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${salary}</td>
                    <td class="px-4 py-3 text-sm">
                        <div class="flex flex-wrap gap-1">
                            ${statusBadge}
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <button onclick="loadContractToForm(${contract.contract_id}); event.stopPropagation();" class="text-gray-400 hover:text-gray-600">
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
        const tbody = document.getElementById('contractTableBody');
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="px-4 py-16 text-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 mx-auto mb-4 text-gray-300">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                    <p class="text-base font-medium text-gray-900 mb-2">No contracts found</p>
                    <small class="text-sm text-gray-500">Fill the form above and click "Save" to add a new contract</small>
                </td>
            </tr>
        `;
    }

    /**
     * Format contract period
     */
    function formatContractPeriod(startDate, endDate) {
        if (!startDate) return '-';
        const start = formatDate(startDate);
        const end = endDate ? formatDate(endDate) : 'Permanent';
        return `${start} - ${end}`;
    }

    /**
     * Get contract status badge
     */
    function getContractStatusBadge(contract) {
        if (!contract.is_active) {
            return '<span class="inline-block px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">Inactive</span>';
        }
        
        if (!contract.end_date) {
            return '<span class="inline-block px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">Active (Permanent)</span>';
        }
        
        const endDate = new Date(contract.end_date);
        const today = new Date();
        const daysRemaining = Math.ceil((endDate - today) / (1000 * 60 * 60 * 24));
        
        if (daysRemaining < 0) {
            return '<span class="inline-block px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Expired</span>';
        } else if (daysRemaining <= 30) {
            return '<span class="inline-block px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">Expiring Soon</span>';
        } else {
            return '<span class="inline-block px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Active</span>';
        }
    }

    /**
     * Format salary
     */
    function formatSalary(salary) {
        if (!salary) return '-';
        return 'Rp ' + new Intl.NumberFormat('id-ID').format(salary);
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
     * Select contract from radio button
     */
    function selectContract(contractId) {
        selectedContractId = contractId;
        console.log(' Selected contract:', contractId);
        loadContractToForm(contractId);
    }

    /**
     * Select contract row (when clicking on row)
     */
    function selectContractRow(contractId, event) {
        if (event.target.tagName === 'INPUT' || event.target.tagName === 'BUTTON' || event.target.closest('button')) {
            return;
        }
        
        const radio = document.querySelector(`input[name="selectedContract"][value="${contractId}"]`);
        if (radio) {
            radio.checked = true;
            selectContract(contractId);
        }
    }

    /**
     * Load contract data to form fields
     */
    async function loadContractToForm(contractId) {
        try {
            console.log(' Loading contract to form:', contractId);
            
            const response = await fetch(`/api/employees/${employeeId}/contract/${contractId}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin'
            });

            const data = await response.json();
            console.log(' Contract data loaded:', data);

            if (data.success && data.data) {
                const contract = data.data;
                
                // Set hidden ID untuk update
                document.getElementById('editContractId').value = contract.contract_id;
                
                // Set form values
                document.getElementById('contractNumber').value = contract.contract_number || '';
                document.getElementById('contractName').value = contract.contract_name || '';
                setCustomDropdownValue('contractType', contract.contract_type || '');
                document.getElementById('contractPosition').value = contract.position || '';
                document.getElementById('contractDate').value = contract.contract_date ? contract.contract_date.split('T')[0] : '';
                document.getElementById('contractStartDate').value = contract.start_date || '';
                document.getElementById('contractEndDate').value = contract.end_date || '';
                document.getElementById('contractSalary').value = contract.salary || '';
                document.getElementById('contractIsActive').checked = contract.is_active || false;
                document.getElementById('contractDriveLink').value = contract.drive_link || '';
                document.getElementById('contractVerifyLink').value = contract.verify_link || '';
                
                // Update button text
                document.getElementById('saveContractButtonText').textContent = 'Update';
                
                console.log(' Contract loaded to form fields');
            }
        } catch (error) {
            console.error(' Error loading contract:', error);
        }
    }

    /**
     * Clear form (for create new)
     */
    function clearContractForm() {
        document.getElementById('editContractId').value = '';
        document.getElementById('contractNumber').value = '';
        document.getElementById('contractName').value = '';
        setCustomDropdownValue('contractType', '');
        document.getElementById('contractPosition').value = '';
        document.getElementById('contractDate').value = '';
        document.getElementById('contractStartDate').value = '';
        document.getElementById('contractEndDate').value = '';
        document.getElementById('contractSalary').value = '';
        document.getElementById('contractIsActive').checked = false;
        document.getElementById('contractDriveLink').value = '';
        document.getElementById('contractVerifyLink').value = '';
        
        // Uncheck radio
        const radios = document.querySelectorAll('input[name="selectedContract"]');
        radios.forEach(radio => radio.checked = false);
        
        selectedContractId = null;
        
        // Update button text
        document.getElementById('saveContractButtonText').textContent = 'Save';
        
        showNotification('Form cleared. Ready to create new contract.', 'info');
    }

    /**
     * Save contract (create or update)
     */
    async function saveContract() {
        // Validate required fields
        const contractNumber = document.getElementById('contractNumber').value;
        const contractName = document.getElementById('contractName').value;
        const contractType = document.getElementById('contractType').value;
        const position = document.getElementById('contractPosition').value;
        const startDate = document.getElementById('contractStartDate').value;
        
        if (!contractNumber || !contractName || !contractType || !position || !startDate) {
            showNotification('Contract number, name, type, position, and start date are required', 'error');
            return;
        }

        const contractId = document.getElementById('editContractId').value;
        const isUpdate = contractId !== '';

        const contractData = {
            contract_number: contractNumber,
            contract_name: contractName,
            contract_type: contractType,
            position: position,
            contract_date: document.getElementById('contractDate').value || null,
            start_date: startDate,
            end_date: document.getElementById('contractEndDate').value || null,
            salary: document.getElementById('contractSalary').value || null,
            is_active: document.getElementById('contractIsActive').checked,
            drive_link: document.getElementById('contractDriveLink').value || null,
            verify_link: document.getElementById('contractVerifyLink').value || null
        };

        try {
            const url = isUpdate 
                ? `/api/employees/${employeeId}/contract/${contractId}`
                : `/api/employees/${employeeId}/contract`;
            
            const method = isUpdate ? 'PUT' : 'POST';
            
            console.log(` ${isUpdate ? 'Updating' : 'Creating'} contract:`, url);
            console.log(' Contract data:', contractData);
            
            const response = await fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                credentials: 'same-origin',
                body: JSON.stringify(contractData)
            });

            const data = await response.json();
            console.log(' Save response:', data);
            
            if (data.success) {
                showNotification(
                    isUpdate ? 'Contract updated successfully!' : 'Contract created successfully!', 
                    'success'
                );
                loadContracts();
                
                if (!isUpdate) {
                    clearContractForm();
                } else {
                    // Reload the same record
                    loadContractToForm(contractId);
                }
            } else {
                showNotification('Failed to save contract: ' + (data.message || 'Unknown error'), 'error');
            }
        } catch (error) {
            console.error(' Error saving contract:', error);
            showNotification('An error occurred while saving contract', 'error');
        }
    }

    /**
     * Copy selected contract
     */
    function copySelectedContract() {
        if (!selectedContractId) {
            showNotification('Please select a contract first', 'warning');
            return;
        }
        showNotification('Copy feature coming soon', 'info');
    }

    /**
     * Delete selected contract
     */
    function deleteSelectedContract() {
        if (!selectedContractId) {
            showNotification('Please select a contract first', 'warning');
            return;
        }
        
        deleteContractId = selectedContractId;
        
        const contract = contractsData.find(c => c.contract_id === selectedContractId);
        let contractInfo = 'this contract';
        
        if (contract) {
            contractInfo = `${contract.contract_name || ''} (${contract.contract_number || ''})`;
        }
        
        document.getElementById('deleteContractInfo').textContent = contractInfo;
        document.getElementById('confirmDeleteContractModal').classList.remove('hidden');
        document.getElementById('confirmDeleteContractModal').classList.add('flex');
    }

    /**
     * Close delete confirmation
     */
    function closeConfirmDeleteContract() {
        document.getElementById('confirmDeleteContractModal').classList.add('hidden');
        document.getElementById('confirmDeleteContractModal').classList.remove('flex');
        deleteContractId = null;
    }

    /**
     * Confirm delete contract
     */
    async function confirmDeleteContract() {
        if (!deleteContractId) return;

        try {
            console.log(' Deleting contract:', deleteContractId);
            
            const response = await fetch(`/api/employees/${employeeId}/contract/${deleteContractId}`, {
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
                showNotification('Contract deleted successfully!', 'success');
                closeConfirmDeleteContract();
                selectedContractId = null;
                loadContracts();
                clearContractForm();
            } else {
                showNotification('Failed to delete contract: ' + (data.message || 'Unknown error'), 'error');
            }
        } catch (error) {
            console.error(' Error deleting contract:', error);
            showNotification('An error occurred while deleting contract', 'error');
        }
    }

    /**
     * Show notification
     */

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        console.log(' Contract section initialized');
        loadContracts();
    });


    // Close modals on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (!document.getElementById('confirmDeleteContractModal').classList.contains('hidden')) {
                closeConfirmDeleteContract();
            }
        }
    });
</script>
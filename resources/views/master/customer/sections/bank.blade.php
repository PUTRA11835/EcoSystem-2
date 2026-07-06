<div class="space-y-6">
    <!-- Form Header -->
    <div class="flex justify-between items-center pb-2 border-b border-gray-200">
        <h3 class="text-base font-semibold text-gray-900">Bank Account</h3>
        <div class="flex gap-2">
            <input type="hidden" id="editBankId">
            <button type="button" onclick="clearBankForm()" class="inline-flex items-center gap-1.5 px-3 py-2 bg-gray-500 text-white text-xs font-semibold rounded-lg hover:bg-gray-600 transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                </svg>
                New
            </button>
            <button type="button" onclick="saveBankInline()" class="inline-flex items-center gap-1.5 px-3 py-2 bg-red-800 text-white text-xs font-semibold rounded-lg hover:bg-red-900 transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0Z" />
                </svg>
                <span id="saveBankButtonText">Save</span>
            </button>
        </div>
    </div>

    <!-- Bank Form Fields -->
    <div>
        <h3 class="text-base font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-200">Bank Information</h3>
        <div class="grid grid-cols-6 gap-4 form-grid">
            <!-- Bank Name -->
            <div class="col-span-2">
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Bank Name <span class="text-red-600">*</span></label>
                <input type="text" id="bankName" placeholder="e.g., Bank Central Asia"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
            </div>

            <!-- Bank Key -->
            <div class="col-span-1">
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Bank Key</label>
                <input type="text" id="bankKey" placeholder="e.g., BCA"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
            </div>

            <!-- Account Number -->
            <div class="col-span-2">
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Account Number <span class="text-red-600">*</span></label>
                <input type="text" id="accountNumber" placeholder="e.g., 1234567890"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
            </div>

            <!-- Account Holder -->
            <div class="col-span-1">
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Account Holder</label>
                <input type="text" id="accountHolder" placeholder="e.g., John Doe"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
            </div>

            <!-- Valid From -->
            <div class="col-span-1">
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Valid From</label>
                <input type="date" id="bankValidFrom"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
            </div>

            <!-- Valid To -->
            <div class="col-span-1">
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Valid To</label>
                <input type="date" id="bankValidTo"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
            </div>

            <!-- Drive Link -->
            <div class="col-span-2">
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Drive Link</label>
                <input type="url" id="bankDriveLink" placeholder="https://drive.google.com/..."
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
            </div>

            <!-- Verify Link -->
            <div class="col-span-2">
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Verify Link</label>
                <input type="url" id="bankVerifyLink" placeholder="https://..."
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
            </div>
        </div>
    </div>

    <!-- Bank Accounts Table -->
    <div>
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 mb-4">
            <h3 class="text-base font-semibold text-gray-900">Bank Accounts</h3>
            <div class="flex flex-wrap items-center gap-3">
                <!-- Search -->
                <div class="relative">
                    <input type="text" id="bankSearch" placeholder="Search" oninput="filterBankTable(this.value)"
                        class="w-full sm:w-64 px-3 py-2 pr-10 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
                    <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                    </button>
                </div>

                <!-- Delete Button -->
                <button onclick="deleteSelectedBank()" title="Delete" class="inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-red-600 text-red-600 text-xs font-semibold rounded-lg hover:bg-red-50 transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                    </svg>
                    Delete
                </button>
            </div>
        </div>

        <div class="overflow-x-auto border border-gray-200 rounded-lg">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="w-10 px-4 py-3 text-left">
                            <input type="radio" name="selectedBank" class="w-4 h-4 text-red-800 focus:ring-red-800">
                        </th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Bank Name</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Bank Key</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Account Number</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Account Holder</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Valid From</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Valid To</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Links</th>
                        <th class="w-10 px-4 py-3 text-left">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-gray-500">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                            </svg>
                        </th>
                    </tr>
                </thead>
                <tbody id="bankAccountsTableBody" class="bg-white divide-y divide-gray-100">
                    <tr>
                        <td colspan="9" class="px-4 py-16 text-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 mx-auto mb-4 text-gray-300">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
                            </svg>
                            <p class="text-base font-medium text-gray-900 mb-2">No bank accounts found</p>
                            <small class="text-sm text-gray-500">Fill the form above and click &quot;Save&quot; to add a new bank account</small>
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
     * Load all bank accounts
     */
    async function loadBankAccounts() {
        try {
            const response = await fetch(`/api/customers/{{ $customer->customer_id }}/banks`, {
                method: 'GET',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            });
            const data = await response.json();
            if (data.success && data.data && data.data.length > 0) {
                banksData = data.data;
                renderBankTable(data.data);
            } else {
                banksData = [];
                renderEmptyBankTable();
            }
        } catch (error) {
            console.error('Error loading bank accounts:', error);
            renderEmptyBankTable();
        }
    }

    /**
     * Render bank table
     */
    function renderBankTable(banks) {
        const tbody = document.getElementById('bankAccountsTableBody');
        const fmt = d => d ? new Date(d).toLocaleDateString('en-GB') : '-';
        const linksHtml = (b) => [
            b.drive_link ? `<a href="${b.drive_link}" target="_blank" class="text-blue-600 hover:text-blue-800 text-xs mr-1" title="Drive">Drive</a>` : '',
            b.verify_link ? `<a href="${b.verify_link}" target="_blank" class="text-green-600 hover:text-green-800 text-xs" title="Verify">Verify</a>` : ''
        ].filter(Boolean).join('') || '-';

        tbody.innerHTML = banks.map(bank => `
            <tr class="hover:bg-gray-50 transition-colors cursor-pointer" onclick="selectBankRow(${bank.bank_id}, event)">
                <td class="px-4 py-3">
                    <input type="radio" name="selectedBank" value="${bank.bank_id}"
                        onclick="selectBank(${bank.bank_id})"
                        class="w-4 h-4 text-red-800 focus:ring-red-800">
                </td>
                <td class="px-4 py-3 text-sm font-medium text-gray-900">${bank.bank_name || '-'}</td>
                <td class="px-4 py-3 text-sm text-gray-700">
                    ${bank.bank_key ? `<span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs font-semibold rounded">${bank.bank_key}</span>` : '-'}
                </td>
                <td class="px-4 py-3 text-sm font-mono text-gray-900">${bank.account_number || '-'}</td>
                <td class="px-4 py-3 text-sm text-gray-700">${bank.account_holder || '-'}</td>
                <td class="px-4 py-3 text-sm text-gray-600">${fmt(bank.valid_from)}</td>
                <td class="px-4 py-3 text-sm text-gray-600">${fmt(bank.valid_to)}</td>
                <td class="px-4 py-3 text-sm">${linksHtml(bank)}</td>
                <td class="px-4 py-3">
                    <button onclick="loadBankToForm(${bank.bank_id}); event.stopPropagation();" class="text-gray-400 hover:text-gray-600">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                        </svg>
                    </button>
                </td>
            </tr>
        `).join('');
    }

    /**
     * Render empty table
     */
    function renderEmptyBankTable() {
        document.getElementById('bankAccountsTableBody').innerHTML = `
            <tr>
                <td colspan="9" class="px-4 py-16 text-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 mx-auto mb-4 text-gray-300">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
                    </svg>
                    <p class="text-base font-medium text-gray-900 mb-2">No bank accounts found</p>
                    <small class="text-sm text-gray-500">Fill the form above and click &quot;Save&quot; to add a new bank account</small>
                </td>
            </tr>
        `;
    }

    /**
     * Filter bank table
     */
    function filterBankTable(query) {
        const q = query.toLowerCase();
        const filtered = q
            ? banksData.filter(b =>
                (b.bank_name || '').toLowerCase().includes(q) ||
                (b.bank_key || '').toLowerCase().includes(q) ||
                (b.account_number || '').toLowerCase().includes(q) ||
                (b.account_holder || '').toLowerCase().includes(q))
            : banksData;
        if (filtered.length > 0) renderBankTable(filtered);
        else renderEmptyBankTable();
    }

    /**
     * Select bank row
     */
    function selectBank(bankId) {
        selectedBankId = bankId;
        loadBankToForm(bankId);
    }

    function selectBankRow(bankId, event) {
        if (event.target.tagName === 'INPUT' || event.target.tagName === 'BUTTON' || event.target.closest('button') || event.target.tagName === 'A') return;
        const radio = document.querySelector(`input[name="selectedBank"][value="${bankId}"]`);
        if (radio) { radio.checked = true; selectBank(bankId); }
    }

    /**
     * Load bank data to form
     */
    async function loadBankToForm(bankId) {
        try {
            const response = await fetch(`/api/customers/{{ $customer->customer_id }}/banks/${bankId}`, {
                method: 'GET',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            });
            const data = await response.json();
            if (data.success && data.data) {
                const b = data.data;
                const fmtDate = d => d ? d.split('T')[0] : '';
                document.getElementById('editBankId').value      = b.bank_id;
                document.getElementById('bankName').value         = b.bank_name || '';
                document.getElementById('bankKey').value          = b.bank_key || '';
                document.getElementById('accountNumber').value    = b.account_number || '';
                document.getElementById('accountHolder').value    = b.account_holder || '';
                document.getElementById('bankValidFrom').value    = fmtDate(b.valid_from);
                document.getElementById('bankValidTo').value      = fmtDate(b.valid_to);
                document.getElementById('bankDriveLink').value    = b.drive_link || '';
                document.getElementById('bankVerifyLink').value   = b.verify_link || '';
                document.getElementById('saveBankButtonText').textContent = 'Update';
                selectedBankId = bankId;
            }
        } catch (error) {
            console.error('Error loading bank:', error);
        }
    }

    /**
     * Save bank inline (create or update)
     */
    async function saveBankInline() {
        const bankName = document.getElementById('bankName').value;
        const accountNumber = document.getElementById('accountNumber').value;
        if (!bankName) { showNotification('Bank Name is required', 'error'); return; }
        if (!accountNumber) { showNotification('Account Number is required', 'error'); return; }

        const bankId = document.getElementById('editBankId').value;
        const isUpdate = bankId !== '';
        const bankData = {
            bank_name:      bankName,
            bank_key:       document.getElementById('bankKey').value || null,
            account_number: accountNumber,
            account_holder: document.getElementById('accountHolder').value || null,
            valid_from:     document.getElementById('bankValidFrom').value || null,
            valid_to:       document.getElementById('bankValidTo').value || null,
            drive_link:     document.getElementById('bankDriveLink').value || null,
            verify_link:    document.getElementById('bankVerifyLink').value || null
        };
        try {
            const url = isUpdate
                ? `/api/customers/{{ $customer->customer_id }}/banks/${bankId}`
                : `/api/customers/{{ $customer->customer_id }}/banks`;
            const response = await fetch(url, {
                method: isUpdate ? 'PUT' : 'POST',
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
                showNotification(isUpdate ? 'Bank account updated successfully!' : 'Bank account created successfully!', 'success');
                loadBankAccounts();
                if (isUpdate) { loadBankToForm(bankId); } else { clearBankForm(); }
            } else {
                showNotification('Failed to save: ' + (data.message || 'Unknown error'), 'error');
            }
        } catch (error) {
            showNotification('An error occurred while saving bank account', 'error');
        }
    }

    /**
     * Clear bank form (new mode)
     */
    function clearBankForm() {
        document.getElementById('editBankId').value = '';
        ['bankName','bankKey','accountNumber','accountHolder','bankValidFrom','bankValidTo','bankDriveLink','bankVerifyLink'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        document.querySelectorAll('input[name="selectedBank"]').forEach(r => r.checked = false);
        selectedBankId = null;
        document.getElementById('saveBankButtonText').textContent = 'Save';
        showNotification('Form cleared. Ready to create new bank account.', 'info');
    }

    /**
     * Delete selected bank
     */
    function deleteSelectedBank() {
        if (!selectedBankId) { showNotification('Please select a bank account first', 'warning'); return; }
        deleteBankId = selectedBankId;
        const bank = banksData.find(b => b.bank_id === selectedBankId);
        document.getElementById('deleteBankInfo').textContent = bank ? `${bank.bank_name} - ${bank.account_number}` : 'this bank account';
        document.getElementById('confirmDeleteBankModal').classList.remove('hidden');
        document.getElementById('confirmDeleteBankModal').classList.add('flex');
    }

    function closeConfirmDeleteBank() {
        document.getElementById('confirmDeleteBankModal').classList.add('hidden');
        document.getElementById('confirmDeleteBankModal').classList.remove('flex');
        deleteBankId = null;
    }

    async function confirmDeleteBank() {
        if (!deleteBankId) return;
        try {
            const response = await fetch(`/api/customers/{{ $customer->customer_id }}/banks/${deleteBankId}/delete`, {
                method: 'POST',
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
                loadBankAccounts();
                clearBankForm();
            } else {
                showNotification('Failed to delete: ' + (data.message || 'Unknown error'), 'error');
            }
        } catch (error) {
            showNotification('An error occurred while deleting', 'error');
        }
    }

    /**
     * Show notification
     */

    document.addEventListener('DOMContentLoaded', function() {
        loadBankAccounts();
    });


    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (!document.getElementById('confirmDeleteBankModal').classList.contains('hidden')) {
                closeConfirmDeleteBank();
            }
        }
    });
</script>

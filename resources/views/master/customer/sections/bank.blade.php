<div class="space-y-6">
    <!-- Header with Add Button -->
    <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-bold text-gray-900">Bank Accounts</h3>
        <button onclick="openAddBankModal()" class="inline-flex items-center gap-2 px-4 py-2 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900 transition-all">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            Add Bank Account
        </button>
    </div>

    <!-- Bank Accounts Table -->
    <div class="overflow-x-auto border border-gray-200 rounded-lg">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase">Bank Name</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase">Bank Key</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase">Account Number</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase">Account Holder</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase">Valid Period</th>
                    <th class="text-center px-4 py-3 text-xs font-semibold text-gray-700 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody id="bankAccountsTableBody" class="divide-y divide-gray-100">
                <!-- Data will be loaded here -->
                <tr id="noBankAccountsRow">
                    <td colspan="6" class="px-4 py-12 text-center text-sm text-gray-500">
                        <div class="flex flex-col items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 text-gray-400">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" />
                            </svg>
                            <p>No bank accounts added yet.</p>
                            <button onclick="openAddBankModal()" class="text-red-800 hover:text-red-900 font-semibold text-sm">
                                Click here to add your first bank account
                            </button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add/Edit Bank Account -->
<div id="bankAccountModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl max-w-3xl w-full max-h-[90vh] overflow-y-auto">
        <!-- Modal Header -->
        <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex justify-between items-center">
            <h3 id="bankModalTitle" class="text-lg font-bold text-gray-900">Add Bank Account</h3>
            <button onclick="closeBankModal()" class="text-gray-400 hover:text-gray-600">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="p-6">
            <form id="bankAccountForm" class="space-y-6">
                <input type="hidden" id="bankId" value="">
                
                <!-- Bank Information Section -->
                <div class="border border-gray-200 rounded-lg p-6">
                    <h4 class="text-base font-bold text-gray-900 mb-4 pb-2 border-b border-gray-200">Bank Information</h4>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Bank Name -->
                        <div class="flex flex-col">
                            <label class="text-xs font-semibold text-gray-600 mb-2">Bank Name <span class="text-red-600">*</span></label>
                            <input type="text" id="bankName" required
                                class="px-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-red-800"
                                placeholder="e.g., Bank Central Asia">
                        </div>

                        <!-- Bank Key -->
                        <div class="flex flex-col">
                            <label class="text-xs font-semibold text-gray-600 mb-2">Bank Key</label>
                            <input type="text" id="bankKey"
                                class="px-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-red-800"
                                placeholder="e.g., BCA">
                        </div>

                        <!-- Account Number -->
                        <div class="flex flex-col">
                            <label class="text-xs font-semibold text-gray-600 mb-2">Account Number <span class="text-red-600">*</span></label>
                            <input type="text" id="accountNumber" required
                                class="px-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-red-800"
                                placeholder="e.g., 1234567890">
                        </div>

                        <!-- Account Holder -->
                        <div class="flex flex-col">
                            <label class="text-xs font-semibold text-gray-600 mb-2">Account Holder</label>
                            <input type="text" id="accountHolder"
                                class="px-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-red-800"
                                placeholder="e.g., John Doe">
                        </div>

                        <!-- Valid From -->
                        <div class="flex flex-col">
                            <label class="text-xs font-semibold text-gray-600 mb-2">Valid From</label>
                            <input type="date" id="validFrom"
                                class="px-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                        </div>

                        <!-- Valid To -->
                        <div class="flex flex-col">
                            <label class="text-xs font-semibold text-gray-600 mb-2">Valid To</label>
                            <input type="date" id="validTo"
                                class="px-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                        </div>

                        <!-- Drive Link -->
                        <div class="flex flex-col md:col-span-2">
                            <label class="text-xs font-semibold text-gray-600 mb-2">Drive Link (Document)</label>
                            <input type="url" id="driveLink"
                                class="px-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-red-800"
                                placeholder="https://drive.google.com/...">
                        </div>

                        <!-- Verify Link -->
                        <div class="flex flex-col md:col-span-2">
                            <label class="text-xs font-semibold text-gray-600 mb-2">Verify Link</label>
                            <input type="url" id="verifyLink"
                                class="px-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-red-800"
                                placeholder="https://...">
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Modal Footer -->
        <div class="sticky bottom-0 bg-gray-50 border-t border-gray-200 px-6 py-4 flex justify-end gap-3">
            <button onclick="closeBankModal()" type="button"
                class="px-4 py-2 border border-gray-300 text-gray-700 text-sm font-semibold rounded-lg hover:bg-gray-100 transition-all">
                Cancel
            </button>
            <button onclick="saveBankAccount()" type="button"
                class="px-4 py-2 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900 transition-all">
                <span id="saveBankButtonText">Save Bank Account</span>
            </button>
        </div>
    </div>
</div>

<!-- Modal: View Bank Details -->
<div id="bankDetailModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <!-- Modal Header -->
        <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex justify-between items-center">
            <h3 class="text-lg font-bold text-gray-900">Bank Account Details</h3>
            <button onclick="closeBankDetailModal()" class="text-gray-400 hover:text-gray-600">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="p-6">
            <div id="bankDetailContent" class="space-y-4">
                <!-- Content will be loaded here -->
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="sticky bottom-0 bg-gray-50 border-t border-gray-200 px-6 py-4 flex justify-end gap-3">
            <button onclick="closeBankDetailModal()" type="button"
                class="px-4 py-2 border border-gray-300 text-gray-700 text-sm font-semibold rounded-lg hover:bg-gray-100 transition-all">
                Close
            </button>
            <button onclick="editFromDetail()" type="button"
                class="px-4 py-2 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900 transition-all">
                Edit
            </button>
        </div>
    </div>
</div>

<script>
// Global variable to store current bank ID for viewing
let currentViewBankId = null;

// Load all bank accounts
async function loadBankAccounts() {
    const customerId = {{ $customer->customer_id }};
    
    try {
        const response = await fetch(`/api/customers/${customerId}/banks`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin'
        });

        const data = await response.json();
        const tbody = document.getElementById('bankAccountsTableBody');
        const noDataRow = document.getElementById('noBankAccountsRow');

        if (data.success && data.data && data.data.length > 0) {
            noDataRow.classList.add('hidden');
            
            tbody.innerHTML = data.data.map(bank => {
                // Format valid period
                let validPeriod = '-';
                if (bank.valid_from || bank.valid_to) {
                    const from = bank.valid_from ? new Date(bank.valid_from).toLocaleDateString('id-ID') : '-';
                    const to = bank.valid_to ? new Date(bank.valid_to).toLocaleDateString('id-ID') : '-';
                    validPeriod = `${from} to ${to}`;
                }

                return `
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-4 py-3 text-sm font-medium text-gray-900">${bank.bank_name || '-'}</td>
                    <td class="px-4 py-3 text-sm text-gray-700">
                        ${bank.bank_key ? `<span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs font-semibold rounded">${bank.bank_key}</span>` : '-'}
                    </td>
                    <td class="px-4 py-3 text-sm font-mono text-gray-900">${bank.account_number || '-'}</td>
                    <td class="px-4 py-3 text-sm text-gray-700">${bank.account_holder || '-'}</td>
                    <td class="px-4 py-3 text-xs text-gray-600">${validPeriod}</td>
                    <td class="px-4 py-3 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <button onclick="viewBankDetails(${bank.bank_id})" class="text-blue-600 hover:text-blue-800" title="View Details">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </button>
                            <button onclick="editBankAccount(${bank.bank_id})" class="text-yellow-600 hover:text-yellow-800" title="Edit">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                </svg>
                            </button>
                            <button onclick="deleteBankAccount(${bank.bank_id})" class="text-red-600 hover:text-red-800" title="Delete">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                </svg>
                            </button>
                        </div>
                    </td>
                </tr>
            `}).join('');
        } else {
            noDataRow.classList.remove('hidden');
        }
    } catch (error) {
        console.error('Error loading bank accounts:', error);
        showNotification('Failed to load bank accounts', 'error');
    }
}

// Open Add Bank Modal
function openAddBankModal() {
    document.getElementById('bankModalTitle').textContent = 'Add Bank Account';
    document.getElementById('bankAccountForm').reset();
    document.getElementById('bankId').value = '';
    document.getElementById('saveBankButtonText').textContent = 'Save Bank Account';
    document.getElementById('bankAccountModal').classList.remove('hidden');
}

// Close Bank Modal
function closeBankModal() {
    document.getElementById('bankAccountModal').classList.add('hidden');
    document.getElementById('bankAccountForm').reset();
}

// View Bank Details
async function viewBankDetails(bankId) {
    const customerId = {{ $customer->customer_id }};
    currentViewBankId = bankId;
    
    try {
        const response = await fetch(`/api/customers/${customerId}/banks/${bankId}`, {
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
            const content = document.getElementById('bankDetailContent');
            
            const formatDate = (date) => date ? new Date(date).toLocaleDateString('id-ID', { year: 'numeric', month: 'long', day: 'numeric' }) : '-';
            
            content.innerHTML = `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs font-semibold text-gray-600 mb-1">Bank Name</p>
                        <p class="text-sm font-medium text-gray-900">${bank.bank_name || '-'}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-gray-600 mb-1">Bank Key</p>
                        <p class="text-sm text-gray-900">${bank.bank_key || '-'}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-gray-600 mb-1">Account Number</p>
                        <p class="text-sm font-mono font-medium text-gray-900">${bank.account_number || '-'}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-gray-600 mb-1">Account Holder</p>
                        <p class="text-sm text-gray-900">${bank.account_holder || '-'}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-gray-600 mb-1">Valid From</p>
                        <p class="text-sm text-gray-900">${formatDate(bank.valid_from)}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-gray-600 mb-1">Valid To</p>
                        <p class="text-sm text-gray-900">${formatDate(bank.valid_to)}</p>
                    </div>
                    ${bank.drive_link ? `
                    <div class="md:col-span-2">
                        <p class="text-xs font-semibold text-gray-600 mb-1">Drive Link</p>
                        <a href="${bank.drive_link}" target="_blank" class="text-sm text-blue-600 hover:text-blue-800 hover:underline break-all">${bank.drive_link}</a>
                    </div>
                    ` : ''}
                    ${bank.verify_link ? `
                    <div class="md:col-span-2">
                        <p class="text-xs font-semibold text-gray-600 mb-1">Verify Link</p>
                        <a href="${bank.verify_link}" target="_blank" class="text-sm text-blue-600 hover:text-blue-800 hover:underline break-all">${bank.verify_link}</a>
                    </div>
                    ` : ''}
                </div>
                
                <div class="mt-6 pt-4 border-t border-gray-200 grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs font-semibold text-gray-600 mb-1">Created At</p>
                        <p class="text-xs text-gray-700">${formatDate(bank.created_at)}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-gray-600 mb-1">Updated At</p>
                        <p class="text-xs text-gray-700">${formatDate(bank.updated_at)}</p>
                    </div>
                </div>
            `;
            
            document.getElementById('bankDetailModal').classList.remove('hidden');
        }
    } catch (error) {
        console.error('Error loading bank details:', error);
        showNotification('Failed to load bank details', 'error');
    }
}

// Close Bank Detail Modal
function closeBankDetailModal() {
    document.getElementById('bankDetailModal').classList.add('hidden');
    currentViewBankId = null;
}

// Edit from Detail View
function editFromDetail() {
    closeBankDetailModal();
    editBankAccount(currentViewBankId);
}

// Edit Bank Account
async function editBankAccount(bankId) {
    const customerId = {{ $customer->customer_id }};
    
    try {
        const response = await fetch(`/api/customers/${customerId}/banks/${bankId}`, {
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
            
            document.getElementById('bankModalTitle').textContent = 'Edit Bank Account';
            document.getElementById('bankId').value = bankId;
            document.getElementById('bankName').value = bank.bank_name || '';
            document.getElementById('bankKey').value = bank.bank_key || '';
            document.getElementById('accountNumber').value = bank.account_number || '';
            document.getElementById('accountHolder').value = bank.account_holder || '';
            document.getElementById('validFrom').value = bank.valid_from || '';
            document.getElementById('validTo').value = bank.valid_to || '';
            document.getElementById('driveLink').value = bank.drive_link || '';
            document.getElementById('verifyLink').value = bank.verify_link || '';
            document.getElementById('saveBankButtonText').textContent = 'Update Bank Account';
            
            document.getElementById('bankAccountModal').classList.remove('hidden');
        }
    } catch (error) {
        console.error('Error loading bank account:', error);
        showNotification('Failed to load bank account', 'error');
    }
}

// Save Bank Account
async function saveBankAccount() {
    const customerId = {{ $customer->customer_id }};
    const bankId = document.getElementById('bankId').value;
    
    const bankData = {
        bank_name: document.getElementById('bankName').value,
        bank_key: document.getElementById('bankKey').value,
        account_number: document.getElementById('accountNumber').value,
        account_holder: document.getElementById('accountHolder').value,
        valid_from: document.getElementById('validFrom').value || null,
        valid_to: document.getElementById('validTo').value || null,
        drive_link: document.getElementById('driveLink').value || null,
        verify_link: document.getElementById('verifyLink').value || null
    };

    // Validation
    if (!bankData.bank_name) {
        showNotification('Bank name is required', 'error');
        return;
    }
    if (!bankData.account_number) {
        showNotification('Account number is required', 'error');
        return;
    }

    try {
        const url = bankId 
            ? `/api/customers/${customerId}/banks/${bankId}`
            : `/api/customers/${customerId}/banks`;
        
        const method = bankId ? 'PUT' : 'POST';
        
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
            showNotification(bankId ? 'Bank account updated successfully!' : 'Bank account created successfully!', 'success');
            closeBankModal();
            loadBankAccounts();
        } else {
            showNotification('Failed to save: ' + (data.message || 'Unknown error'), 'error');
        }
    } catch (error) {
        console.error('Error saving bank account:', error);
        showNotification('An error occurred while saving', 'error');
    }
}

// Delete Bank Account
async function deleteBankAccount(bankId) {
    if (!confirm('Are you sure you want to delete this bank account? This action cannot be undone.')) {
        return;
    }
    
    const customerId = {{ $customer->customer_id }};
    
    try {
        const response = await fetch(`/api/customers/${customerId}/banks/${bankId}`, {
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
            loadBankAccounts();
        } else {
            showNotification('Failed to delete: ' + (data.message || 'Unknown error'), 'error');
        }
    } catch (error) {
        console.error('Error deleting bank account:', error);
        showNotification('An error occurred while deleting', 'error');
    }
}

// Load bank accounts on page load
document.addEventListener('DOMContentLoaded', function() {
    loadBankAccounts();
});
</script>
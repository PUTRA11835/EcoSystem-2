<div class="space-y-6">
    <!-- PAYMENT INFORMATION SECTION (Form untuk Create & Update) -->
    <div>
        <div class="flex justify-between items-center mb-4 pb-2 border-b border-gray-200">
            <h3 class="text-base font-semibold text-gray-900">Payment Information</h3>
            <div class="flex gap-2">
                <button type="button" onclick="clearPaymentForm()" class="inline-flex items-center gap-1.5 px-3 py-2 bg-gray-500 text-white text-xs font-semibold rounded-lg hover:bg-gray-600 transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                    </svg>
                    New
                </button>
                <button type="button" onclick="savePayment()" class="inline-flex items-center gap-1.5 px-3 py-2 bg-red-800 text-white text-xs font-semibold rounded-lg hover:bg-red-900 transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0Z" />
                    </svg>
                    <span id="savePaymentButtonText">Save</span>
                </button>
            </div>
        </div>
        
        <input type="hidden" id="editPaymentId">
        
        <!-- Payment Details Section -->
        <div class="mb-6">
            <h5 class="text-sm font-bold text-gray-900 mb-3 pb-2 border-b border-gray-200"> Payment Details</h5>
            <div class="grid grid-cols-6 gap-4">
                <!-- Amount -->
                <div class="col-span-1">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Amount <span class="text-red-600">*</span></label>
                    <input type="number" step="0.01" id="paymentAmount" placeholder="5000000.00" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>

                <!-- Paid At -->
                <div class="col-span-1">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Paid At <span class="text-red-600">*</span></label>
                    <input type="date" id="paymentPaidAt" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>

                <!-- Payment Method -->
                <div class="col-span-1">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Payment Method <span class="text-red-600">*</span></label>
                    <div class="relative">
                        <select id="paymentMethod" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white pr-10 appearance-none">
                            <option value="">Select Method</option>
                            <option value="Transfer">Transfer</option>
                            <option value="Cash">Cash</option>
                            <option value="E-Wallet">E-Wallet</option>
                            <option value="Cheque">Cheque</option>
                            <option value="Credit Card">Credit Card</option>
                        </select>
                        <div class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-gray-400">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Reference Number -->
                <div class="col-span-1">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Reference No</label>
                    <input type="text" id="paymentReferenceNumber" placeholder="TRX-123456" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>

                <!-- Payment Status -->
                <div class="col-span-1">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Status <span class="text-red-600">*</span></label>
                    <div class="relative">
                        <select id="paymentStatus" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white pr-10 appearance-none">
                            <option value="">Select Status</option>
                            <option value="Pending">Pending</option>
                            <option value="Processing">Processing</option>
                            <option value="Completed">Completed</option>
                            <option value="Failed">Failed</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                        <div class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-gray-400">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Valid To -->
                <div class="col-span-1">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Valid To</label>
                    <input type="date" id="paymentValidTo" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
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
                    <input type="url" id="paymentDriveLink" placeholder="https://drive.google.com/..." class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>

                <!-- Verify Link -->
                <div class="col-span-3">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Verify Link</label>
                    <input type="url" id="paymentVerifyLink" placeholder="https://" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>
            </div>
        </div>
    </div>

    <!-- PAYMENT DETAILS SECTION (Table) -->
    <div>
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-base font-semibold text-gray-900">Payment Details</h3>
            <div class="flex items-center gap-3">
                <!-- Search -->
                <div class="relative">
                    <input type="text" id="paymentSearch" placeholder="Search" class="w-64 px-3 py-2 pr-10 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
                    <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                    </button>
                </div>

                <!-- Action Buttons -->
                <button onclick="copySelectedPayment()" title="Copy" class="inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-gray-300 text-gray-700 text-xs font-semibold rounded-lg hover:bg-gray-50 transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" />
                    </svg>
                    Copy
                </button>

                <button onclick="deleteSelectedPayment()" title="Delete" class="inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-red-600 text-red-600 text-xs font-semibold rounded-lg hover:bg-red-50 transition-all">
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
                            <input type="radio" name="selectedPayment" class="w-4 h-4 text-red-800 focus:ring-red-800" disabled>
                        </th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Paid At</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Amount</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Method</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Reference No</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Validity</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Status</th>
                        <th class="w-10 px-4 py-3 text-left">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-gray-500">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                            </svg>
                        </th>
                    </tr>
                </thead>
                <tbody id="paymentTableBody" class="bg-white divide-y divide-gray-100">
                    <!-- Dynamic rows will be inserted here -->
                    <tr>
                        <td colspan="8" class="px-4 py-16 text-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 mx-auto mb-4 text-gray-300">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" />
                            </svg>
                            <p class="text-base font-medium text-gray-900 mb-2">No payment records found</p>
                            <small class="text-sm text-gray-500">Fill the form above and click "Save" to add a new payment</small>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="confirmDeletePaymentModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-md w-full shadow-2xl">
        <div class="p-6">
            <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 bg-red-100 rounded-full">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-red-600">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-900 text-center mb-2">Delete Payment</h3>
            <p class="text-sm text-gray-600 text-center mb-1">Are you sure you want to delete this payment record?</p>
            <p class="text-sm font-semibold text-gray-900 text-center mb-6" id="deletePaymentInfo"></p>
            <div class="flex gap-3">
                <button onclick="closeConfirmDeletePayment()" class="flex-1 px-4 py-2.5 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">Cancel</button>
                <button onclick="confirmDeletePayment()" class="flex-1 px-4 py-2.5 bg-red-600 text-white text-sm font-semibold rounded-lg hover:bg-red-700 transition-all">Delete</button>
            </div>
        </div>
    </div>
</div>

<script>
    let paymentsData = [];
    let selectedPaymentId = null;
    let deletePaymentId = null;

    /**
     * Load all payments for this employee
     */
    async function loadPayments() {
        try {
            console.log(' Loading payments for employee:', employeeId);
            
            const response = await fetch(`/api/employees/${employeeId}/payment`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin'
            });

            const data = await response.json();
            console.log(' Payments loaded:', data);

            if (data.success && data.data && data.data.length > 0) {
                paymentsData = data.data;
                renderPaymentTable(data.data);
            } else {
                paymentsData = [];
                renderEmptyTable();
            }
        } catch (error) {
            console.error(' Error loading payments:', error);
            paymentsData = [];
            renderEmptyTable();
        }
    }

    /**
     * Render payment table with data
     */
    function renderPaymentTable(payments) {
        const tbody = document.getElementById('paymentTableBody');
        
        tbody.innerHTML = payments.map(payment => {
            const validity = formatPaymentValidity(payment.valid_to);
            const statusBadge = getPaymentStatusBadge(payment);
            const formattedAmount = formatCurrency(payment.amount);

            return `
                <tr class="hover:bg-gray-50 transition-colors cursor-pointer" onclick="selectPaymentRow(${payment.payment_id}, event)">
                    <td class="px-4 py-3">
                        <input type="radio" name="selectedPayment" value="${payment.payment_id}" 
                            onclick="selectPayment(${payment.payment_id})" 
                            class="w-4 h-4 text-red-800 focus:ring-red-800">
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">${formatDate(payment.paid_at)}</td>
                    <td class="px-4 py-3 text-sm">
                        <strong class="font-semibold text-gray-900">${formattedAmount}</strong>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <span class="inline-block px-2 py-1 text-xs font-semibold rounded bg-blue-100 text-blue-700">${payment.payment_method || '-'}</span>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <span class="font-mono text-gray-600">${payment.reference_number || '-'}</span>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">${validity}</td>
                    <td class="px-4 py-3 text-sm">
                        <div class="flex flex-wrap gap-1">
                            ${statusBadge}
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <button onclick="loadPaymentToForm(${payment.payment_id}); event.stopPropagation();" class="text-gray-400 hover:text-gray-600">
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
        const tbody = document.getElementById('paymentTableBody');
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="px-4 py-16 text-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 mx-auto mb-4 text-gray-300">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" />
                    </svg>
                    <p class="text-base font-medium text-gray-900 mb-2">No payment records found</p>
                    <small class="text-sm text-gray-500">Fill the form above and click "Save" to add a new payment</small>
                </td>
            </tr>
        `;
    }

    /**
     * Format currency
     */
    function formatCurrency(amount) {
        if (!amount) return 'Rp 0';
        return 'Rp ' + parseFloat(amount).toLocaleString('id-ID', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    /**
     * Format validity period
     */
    function formatPaymentValidity(validTo) {
        if (!validTo) return 'No expiration';
        return formatDate(validTo);
    }

    /**
     * Get payment status badge
     */
    function getPaymentStatusBadge(payment) {
        const statusColors = {
            'Completed': 'bg-green-100 text-green-800',
            'Pending': 'bg-yellow-100 text-yellow-800',
            'Processing': 'bg-blue-100 text-blue-800',
            'Failed': 'bg-red-100 text-red-800',
            'Cancelled': 'bg-gray-100 text-gray-800'
        };
        
        const colorClass = statusColors[payment.payment_status] || 'bg-gray-100 text-gray-800';
        let badges = `<span class="inline-block px-2 py-1 text-xs font-semibold rounded-full ${colorClass}">${payment.payment_status || 'Unknown'}</span>`;
        
        // Check if expired
        if (payment.valid_to) {
            const validTo = new Date(payment.valid_to);
            const today = new Date();
            const daysRemaining = Math.ceil((validTo - today) / (1000 * 60 * 60 * 24));
            
            if (daysRemaining < 0) {
                badges += ' <span class="inline-block px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800 ml-1">Expired</span>';
            } else if (daysRemaining <= 30) {
                badges += ' <span class="inline-block px-2 py-1 text-xs font-semibold rounded-full bg-orange-100 text-orange-800 ml-1">Expiring Soon</span>';
            }
        }
        
        return badges;
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
     * Select payment from radio button
     */
    function selectPayment(paymentId) {
        selectedPaymentId = paymentId;
        console.log(' Selected payment:', paymentId);
        loadPaymentToForm(paymentId);
    }

    /**
     * Select payment row (when clicking on row)
     */
    function selectPaymentRow(paymentId, event) {
        if (event.target.tagName === 'INPUT' || event.target.tagName === 'BUTTON' || event.target.closest('button')) {
            return;
        }
        
        const radio = document.querySelector(`input[name="selectedPayment"][value="${paymentId}"]`);
        if (radio) {
            radio.checked = true;
            selectPayment(paymentId);
        }
    }

    /**
     * Load payment data to form fields
     */
    async function loadPaymentToForm(paymentId) {
        try {
            console.log(' Loading payment to form:', paymentId);
            
            const response = await fetch(`/api/employees/${employeeId}/payment/${paymentId}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin'
            });

            const data = await response.json();
            console.log(' Payment data loaded:', data);

            if (data.success && data.data) {
                const payment = data.data;
                
                // Set hidden ID untuk update
                document.getElementById('editPaymentId').value = payment.payment_id;
                
                // Set form values
                document.getElementById('paymentAmount').value = payment.amount || '';
                document.getElementById('paymentPaidAt').value = payment.paid_at || '';
                document.getElementById('paymentMethod').value = payment.payment_method || '';
                document.getElementById('paymentReferenceNumber').value = payment.reference_number || '';
                document.getElementById('paymentStatus').value = payment.payment_status || '';
                document.getElementById('paymentValidTo').value = payment.valid_to || '';
                document.getElementById('paymentDriveLink').value = payment.drive_link || '';
                document.getElementById('paymentVerifyLink').value = payment.verify_link || '';
                
                // Update button text
                document.getElementById('savePaymentButtonText').textContent = 'Update';
                
                console.log(' Payment loaded to form fields');
            }
        } catch (error) {
            console.error(' Error loading payment:', error);
        }
    }

    /**
     * Clear form (for create new)
     */
    function clearPaymentForm() {
        document.getElementById('editPaymentId').value = '';
        document.getElementById('paymentAmount').value = '';
        document.getElementById('paymentPaidAt').value = new Date().toISOString().split('T')[0];
        document.getElementById('paymentMethod').value = '';
        document.getElementById('paymentReferenceNumber').value = '';
        document.getElementById('paymentStatus').value = '';
        document.getElementById('paymentValidTo').value = '';
        document.getElementById('paymentDriveLink').value = '';
        document.getElementById('paymentVerifyLink').value = '';
        
        // Uncheck radio
        const radios = document.querySelectorAll('input[name="selectedPayment"]');
        radios.forEach(radio => radio.checked = false);
        
        selectedPaymentId = null;
        
        // Update button text
        document.getElementById('savePaymentButtonText').textContent = 'Save';
        
        showNotification('Form cleared. Ready to create new payment.', 'info');
    }

    /**
     * Save payment (create or update)
     */
    async function savePayment() {
        // Validate required fields
        const amount = document.getElementById('paymentAmount').value;
        const paidAt = document.getElementById('paymentPaidAt').value;
        const paymentMethod = document.getElementById('paymentMethod').value;
        const paymentStatus = document.getElementById('paymentStatus').value;
        
        if (!amount || !paidAt || !paymentMethod || !paymentStatus) {
            showNotification('Amount, paid at, payment method, and payment status are required', 'error');
            return;
        }

        const paymentId = document.getElementById('editPaymentId').value;
        const isUpdate = paymentId !== '';

        const paymentData = {
            amount: parseFloat(amount),
            paid_at: paidAt,
            payment_method: paymentMethod,
            reference_number: document.getElementById('paymentReferenceNumber').value || null,
            payment_status: paymentStatus,
            valid_to: document.getElementById('paymentValidTo').value || null,
            drive_link: document.getElementById('paymentDriveLink').value || null,
            verify_link: document.getElementById('paymentVerifyLink').value || null
        };

        try {
            const url = isUpdate 
                ? `/api/employees/${employeeId}/payment/${paymentId}`
                : `/api/employees/${employeeId}/payment`;
            
            const method = isUpdate ? 'PUT' : 'POST';
            
            console.log(` ${isUpdate ? 'Updating' : 'Creating'} payment:`, url);
            console.log(' Payment data:', paymentData);
            
            const response = await fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                credentials: 'same-origin',
                body: JSON.stringify(paymentData)
            });

            const data = await response.json();
            console.log(' Save response:', data);
            
            if (data.success) {
                showNotification(
                    isUpdate ? 'Payment updated successfully!' : 'Payment created successfully!', 
                    'success'
                );
                loadPayments();
                
                if (!isUpdate) {
                    clearPaymentForm();
                } else {
                    // Reload the same record
                    loadPaymentToForm(paymentId);
                }
            } else {
                showNotification('Failed to save payment: ' + (data.message || 'Unknown error'), 'error');
            }
        } catch (error) {
            console.error(' Error saving payment:', error);
            showNotification('An error occurred while saving payment', 'error');
        }
    }

    /**
     * Copy selected payment
     */
    function copySelectedPayment() {
        if (!selectedPaymentId) {
            showNotification('Please select a payment first', 'warning');
            return;
        }
        showNotification('Copy feature coming soon', 'info');
    }

    /**
     * Delete selected payment
     */
    function deleteSelectedPayment() {
        if (!selectedPaymentId) {
            showNotification('Please select a payment first', 'warning');
            return;
        }
        
        deletePaymentId = selectedPaymentId;
        
        const payment = paymentsData.find(p => p.payment_id === selectedPaymentId);
        let paymentInfo = 'this payment record';
        
        if (payment) {
            paymentInfo = `${formatCurrency(payment.amount)} - ${formatDate(payment.paid_at)}`;
        }
        
        document.getElementById('deletePaymentInfo').textContent = paymentInfo;
        document.getElementById('confirmDeletePaymentModal').classList.remove('hidden');
        document.getElementById('confirmDeletePaymentModal').classList.add('flex');
    }

    /**
     * Close delete confirmation
     */
    function closeConfirmDeletePayment() {
        document.getElementById('confirmDeletePaymentModal').classList.add('hidden');
        document.getElementById('confirmDeletePaymentModal').classList.remove('flex');
        deletePaymentId = null;
    }

    /**
     * Confirm delete payment
     */
    async function confirmDeletePayment() {
        if (!deletePaymentId) return;

        try {
            console.log(' Deleting payment:', deletePaymentId);
            
            const response = await fetch(`/api/employees/${employeeId}/payment/${deletePaymentId}`, {
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
                showNotification('Payment deleted successfully!', 'success');
                closeConfirmDeletePayment();
                selectedPaymentId = null;
                loadPayments();
                clearPaymentForm();
            } else {
                showNotification('Failed to delete payment: ' + (data.message || 'Unknown error'), 'error');
            }
        } catch (error) {
            console.error(' Error deleting payment:', error);
            showNotification('An error occurred while deleting payment', 'error');
        }
    }

    /**
     * Show notification
     */

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        console.log(' Payment section initialized');
        // Set default date to today
        document.getElementById('paymentPaidAt').value = new Date().toISOString().split('T')[0];
        loadPayments();
    });


    // Close modals on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (!document.getElementById('confirmDeletePaymentModal').classList.contains('hidden')) {
                closeConfirmDeletePayment();
            }
        }
    });
</script>
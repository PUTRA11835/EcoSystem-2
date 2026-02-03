<div class="space-y-6">
    <!-- History Section -->
    <div>
        <h3 class="text-base font-semibold text-gray-900 mb-4">History</h3>
        
        <!-- Business Development Section -->
        <div class="mb-6">
            <div class="flex justify-between items-center mb-4">
                <h4 class="text-sm font-semibold text-gray-900">Business Development</h4>
                <div class="flex items-center gap-3">
                    <!-- Search -->
                    <div class="relative">
                        <input type="text" id="businessSearch" placeholder="Search" class="w-64 px-3 py-2 pr-10 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
                        <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                            </svg>
                        </button>
                    </div>

                    <!-- Action Buttons -->
                    <button onclick="copySelectedBusiness()" title="Copy" class="inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-gray-300 text-gray-700 text-xs font-semibold rounded-lg hover:bg-gray-50 transition-all">
                        Copy
                    </button>

                    <button onclick="openCreateBusinessModal()" title="Create" class="inline-flex items-center gap-1.5 px-3 py-2 bg-red-800 text-white text-xs font-semibold rounded-lg hover:bg-red-900 transition-all">
                        Create
                    </button>

                    <button onclick="deleteSelectedBusiness()" title="Delete" class="inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-red-600 text-red-600 text-xs font-semibold rounded-lg hover:bg-red-50 transition-all">
                        Delete
                    </button>

                    <button title="Settings" class="w-8 h-8 flex items-center justify-center border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50 transition-all">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Business Development Table -->
            <div class="overflow-x-auto border border-gray-200 rounded-lg">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="w-10 px-4 py-3 text-left">
                                <input type="radio" name="selectedBusiness" class="w-4 h-4 text-red-800 focus:ring-red-800">
                            </th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Type</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Number</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Date</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Value</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Valid From</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Valid To</th>
                            <th class="w-10 px-4 py-3 text-left">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-gray-500">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                </svg>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="businessTableBody" class="bg-white divide-y divide-gray-100">
                        <tr>
                            <td colspan="8" class="px-4 py-12 text-center">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 mx-auto mb-3 text-gray-300">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5M9 11.25v1.5M12 9v3.75m3-6v6" />
                                </svg>
                                <p class="text-sm text-gray-500">No business development records found</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Project & Operational Section -->
        <div>
            <div class="flex justify-between items-center mb-4">
                <h4 class="text-sm font-semibold text-gray-900">Project & Operational</h4>
                <div class="flex items-center gap-3">
                    <!-- Search -->
                    <div class="relative">
                        <input type="text" id="projectSearch" placeholder="Search" class="w-64 px-3 py-2 pr-10 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
                        <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                            </svg>
                        </button>
                    </div>

                    <!-- Action Buttons -->
                    <button onclick="copySelectedProject()" title="Copy" class="inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-gray-300 text-gray-700 text-xs font-semibold rounded-lg hover:bg-gray-50 transition-all">
                        Copy
                    </button>

                    <button onclick="openCreateProjectModal()" title="Create" class="inline-flex items-center gap-1.5 px-3 py-2 bg-red-800 text-white text-xs font-semibold rounded-lg hover:bg-red-900 transition-all">
                        Create
                    </button>

                    <button onclick="deleteSelectedProject()" title="Delete" class="inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-red-600 text-red-600 text-xs font-semibold rounded-lg hover:bg-red-50 transition-all">
                        Delete
                    </button>

                    <button title="Settings" class="w-8 h-8 flex items-center justify-center border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50 transition-all">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Project & Operational Table -->
            <div class="overflow-x-auto border border-gray-200 rounded-lg">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="w-10 px-4 py-3 text-left">
                                <input type="radio" name="selectedProject" class="w-4 h-4 text-red-800 focus:ring-red-800">
                            </th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Type</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">IO Number</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Date</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Reference</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Ref. Date</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Value</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Valid From</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Valid To</th>
                            <th class="w-10 px-4 py-3 text-left">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-gray-500">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                </svg>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="projectTableBody" class="bg-white divide-y divide-gray-100">
                        <tr>
                            <td colspan="10" class="px-4 py-12 text-center">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 mx-auto mb-3 text-gray-300">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0M12 12.75h.008v.008H12v-.008Z" />
                                </svg>
                                <p class="text-sm text-gray-500">No project & operational records found</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    let businessData = [];
    let projectData = [];
    let selectedBusinessId = null;
    let selectedProjectId = null;

    /**
     * Load business development history
     */
    async function loadBusinessHistory() {
        try {
            // Sample data - replace with actual API call
            const sampleBusinessData = [
                {
                    id: 1,
                    type: 'Quotation (CQ)',
                    number: '01999/QUOT/ERP/XII/2025',
                    date: '01.12.2025',
                    value: '100.000.000',
                    valid_from: '01.01.2025',
                    valid_to: '31.12.9999'
                }
            ];

            businessData = sampleBusinessData;
            renderBusinessTable(sampleBusinessData);
        } catch (error) {
            console.error('❌ Error loading business history:', error);
            businessData = [];
            renderEmptyBusinessTable();
        }
    }

    /**
     * Load project & operational history
     */
    async function loadProjectHistory() {
        try {
            // Sample data - replace with actual API call
            const sampleProjectData = [
                {
                    id: 1,
                    type: 'Body Hire (IO01)',
                    io_number: '1000000000',
                    date: '01.12.2025',
                    reference: '01999/QUOT/ERP/XII/2025',
                    ref_date: '01.12.2025',
                    value: '100.000.000',
                    valid_from: '01.01.2025',
                    valid_to: '31.12.9999'
                }
            ];

            projectData = sampleProjectData;
            renderProjectTable(sampleProjectData);
        } catch (error) {
            console.error('❌ Error loading project history:', error);
            projectData = [];
            renderEmptyProjectTable();
        }
    }

    /**
     * Render business table
     */
    function renderBusinessTable(data) {
        const tbody = document.getElementById('businessTableBody');
        
        tbody.innerHTML = data.map(item => {
            return `
                <tr class="hover:bg-gray-50 transition-colors cursor-pointer">
                    <td class="px-4 py-3">
                        <input type="radio" name="selectedBusiness" value="${item.id}" 
                            onclick="selectedBusinessId = ${item.id}" 
                            class="w-4 h-4 text-red-800 focus:ring-red-800">
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-900">${item.type}</td>
                    <td class="px-4 py-3 text-sm text-gray-900">${item.number}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${item.date}</td>
                    <td class="px-4 py-3 text-sm text-gray-900">${item.value}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${item.valid_from}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${item.valid_to}</td>
                    <td class="px-4 py-3">
                        <button class="text-gray-400 hover:text-gray-600">
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
     * Render project table
     */
    function renderProjectTable(data) {
        const tbody = document.getElementById('projectTableBody');
        
        tbody.innerHTML = data.map(item => {
            return `
                <tr class="hover:bg-gray-50 transition-colors cursor-pointer">
                    <td class="px-4 py-3">
                        <input type="radio" name="selectedProject" value="${item.id}" 
                            onclick="selectedProjectId = ${item.id}" 
                            class="w-4 h-4 text-red-800 focus:ring-red-800">
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-900">${item.type}</td>
                    <td class="px-4 py-3 text-sm text-gray-900">${item.io_number}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${item.date}</td>
                    <td class="px-4 py-3 text-sm text-gray-900">${item.reference}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${item.ref_date}</td>
                    <td class="px-4 py-3 text-sm text-gray-900">${item.value}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${item.valid_from}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${item.valid_to}</td>
                    <td class="px-4 py-3">
                        <button class="text-gray-400 hover:text-gray-600">
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
     * Render empty business table
     */
    function renderEmptyBusinessTable() {
        const tbody = document.getElementById('businessTableBody');
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="px-4 py-12 text-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 mx-auto mb-3 text-gray-300">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5M9 11.25v1.5M12 9v3.75m3-6v6" />
                    </svg>
                    <p class="text-sm text-gray-500">No business development records found</p>
                </td>
            </tr>
        `;
    }

    /**
     * Render empty project table
     */
    function renderEmptyProjectTable() {
        const tbody = document.getElementById('projectTableBody');
        tbody.innerHTML = `
            <tr>
                <td colspan="10" class="px-4 py-12 text-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 mx-auto mb-3 text-gray-300">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0M12 12.75h.008v.008H12v-.008Z" />
                    </svg>
                    <p class="text-sm text-gray-500">No project & operational records found</p>
                </td>
            </tr>
        `;
    }

    // Placeholder functions for button actions
    function copySelectedBusiness() {
        if (!selectedBusinessId) {
            showNotification('Please select a business record first', 'warning');
            return;
        }
        showNotification('Copy feature coming soon', 'info');
    }

    function openCreateBusinessModal() {
        showNotification('Create business feature coming soon', 'info');
    }

    function deleteSelectedBusiness() {
        if (!selectedBusinessId) {
            showNotification('Please select a business record first', 'warning');
            return;
        }
        showNotification('Delete feature coming soon', 'info');
    }

    function copySelectedProject() {
        if (!selectedProjectId) {
            showNotification('Please select a project record first', 'warning');
            return;
        }
        showNotification('Copy feature coming soon', 'info');
    }

    function openCreateProjectModal() {
        showNotification('Create project feature coming soon', 'info');
    }

    function deleteSelectedProject() {
        if (!selectedProjectId) {
            showNotification('Please select a project record first', 'warning');
            return;
        }
        showNotification('Delete feature coming soon', 'info');
    }

    /**
     * Show notification
     */
    function showNotification(message, type = 'info') {
        const bgColor = type === 'success' ? 'bg-green-500' : 
                        type === 'error' ? 'bg-red-500' : 
                        type === 'warning' ? 'bg-yellow-500' : 'bg-blue-500';
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 ${bgColor} text-white px-6 py-3 rounded-lg shadow-lg z-50 transition-opacity duration-300`;
        notification.textContent = message;
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.opacity = '0';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        console.log('🚀 History section initialized');
        loadBusinessHistory();
        loadProjectHistory();
    });
</script>
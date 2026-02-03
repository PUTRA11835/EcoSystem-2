<div class="space-y-6">
    <!-- Identification Number Section (Main Form - for viewing selected) -->
    <div>
        <h3 class="text-base font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-200">Identification Number</h3>
        
        <div class="grid grid-cols-6 gap-4">
            <!-- ID Type -->
            <div class="col-span-1">
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">ID Type</label>
                <div class="relative">
                    <input type="text" id="idType" readonly class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm bg-gray-50 text-gray-700 pr-8">
                    <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Identification Number -->
            <div class="col-span-2">
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Identification Number</label>
                <input type="text" id="identificationNumber" readonly class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm bg-gray-50 text-gray-700">
            </div>

            <!-- Responsible Institution -->
            <div class="col-span-2">
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Responsible Institution</label>
                <input type="text" id="responsibleInstitution" readonly class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm bg-gray-50 text-gray-700">
            </div>

            <!-- Valid From -->
            <div class="col-span-1">
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Valid From</label>
                <input type="text" id="validFrom" readonly class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm bg-gray-50 text-gray-700">
            </div>
        </div>
    </div>

    <!-- Identification Details Table -->
    <div>
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-base font-semibold text-gray-900">Identification</h3>
            <div class="flex items-center gap-3">
                <!-- Search -->
                <div class="relative">
                    <input type="text" id="identificationSearch" placeholder="Search" class="w-64 px-3 py-2 pr-10 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
                    <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                    </button>
                </div>

                <!-- Action Buttons -->
                <button onclick="copySelectedIdentification()" title="Copy" class="inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-gray-300 text-gray-700 text-xs font-semibold rounded-lg hover:bg-gray-50 transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" />
                    </svg>
                    Copy
                </button>

                <button onclick="openCreateIdentificationModal()" title="Create" class="inline-flex items-center gap-1.5 px-3 py-2 bg-red-800 text-white text-xs font-semibold rounded-lg hover:bg-red-900 transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Create
                </button>

                <button onclick="openEditIdentificationModal()" title="Update" class="inline-flex items-center gap-1.5 px-3 py-2 bg-blue-600 text-white text-xs font-semibold rounded-lg hover:bg-blue-700 transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                    </svg>
                    Update
                </button>

                <button onclick="deleteSelectedIdentification()" title="Delete" class="inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-red-600 text-red-600 text-xs font-semibold rounded-lg hover:bg-red-50 transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                    </svg>
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

        <!-- Table -->
        <div class="overflow-x-auto border border-gray-200 rounded-lg">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="w-10 px-4 py-3 text-left">
                            <input type="radio" name="selectedIdentification" class="w-4 h-4 text-red-800 focus:ring-red-800">
                        </th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">ID Type</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Identification Number</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Responsible Institution</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Valid From</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Valid To</th>
                        <th class="w-10 px-4 py-3 text-left">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-gray-500">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                            </svg>
                        </th>
                    </tr>
                </thead>
                <tbody id="identificationTableBody" class="bg-white divide-y divide-gray-100">
                    <tr>
                        <td colspan="7" class="px-4 py-16 text-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 mx-auto mb-4 text-gray-300">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Zm6-10.125a1.875 1.875 0 1 1-3.75 0 1.875 1.875 0 0 1 3.75 0Zm1.294 6.336a6.721 6.721 0 0 1-3.17.789 6.721 6.721 0 0 1-3.168-.789 3.376 3.376 0 0 1 6.338 0Z" />
                            </svg>
                            <p class="text-base font-medium text-gray-900 mb-2">No identification documents found</p>
                            <small class="text-sm text-gray-500">Click "Create" to add a new identification</small>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create/Edit Identification Modal -->
<div id="identificationModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto shadow-2xl">
        <div class="flex justify-between items-center px-6 py-5 border-b border-gray-200">
            <h3 id="identificationModalTitle" class="text-xl font-bold text-gray-900">New Identification Number</h3>
            <button onclick="closeIdentificationModal()" class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-600 hover:bg-red-800 hover:text-white transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Tabs -->
        <div class="border-b border-gray-200">
            <nav class="flex -mb-px">
                <button onclick="switchIdentificationTab('general')" data-tab="general" class="identification-tab px-6 py-4 text-sm font-semibold border-b-2 border-red-800 text-red-800 whitespace-nowrap">
                    General Data
                </button>
                <button onclick="switchIdentificationTab('attachment')" data-tab="attachment" class="identification-tab px-6 py-4 text-sm font-semibold border-b-2 border-transparent text-gray-600 hover:text-red-800 hover:border-gray-300 whitespace-nowrap">
                    Attachment Link
                </button>
            </nav>
        </div>

        <div class="p-6">
            <form id="identificationForm">
                <input type="hidden" id="editIdentificationId">
                
                <!-- General Data Tab -->
                <div id="tab-general" class="identification-tab-content">
                    <h4 class="text-base font-semibold text-gray-900 mb-4">General Data</h4>
                    
                    <div class="grid grid-cols-6 gap-4">
                        <!-- ID Type -->
                        <div class="col-span-2">
                            <label class="text-xs font-semibold text-gray-600 mb-1.5 block">ID Type <span class="text-red-600">*</span></label>
                            <div class="relative">
                                <input type="text" id="modalIdType" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 pr-8">
                                <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Identification Number -->
                        <div class="col-span-2">
                            <label class="text-xs font-semibold text-gray-600 mb-1.5 block">Identification Number <span class="text-red-600">*</span></label>
                            <input type="text" id="modalIdentificationNumber" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                        </div>

                        <!-- Responsible Institution -->
                        <div class="col-span-2">
                            <label class="text-xs font-semibold text-gray-600 mb-1.5 block">Responsible Institution</label>
                            <input type="text" id="modalResponsibleInstitution" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                        </div>

                        <!-- Region -->
                        <div class="col-span-2">
                            <label class="text-xs font-semibold text-gray-600 mb-1.5 block">Region</label>
                            <div class="relative">
                                <input type="text" id="modalRegion" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 pr-8">
                                <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Valid From -->
                        <div class="col-span-2">
                            <label class="text-xs font-semibold text-gray-600 mb-1.5 block">Valid From</label>
                            <div class="relative">
                                <input type="date" id="modalValidFrom" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 pr-8">
                                <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 pointer-events-none">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Entry Date -->
                        <div class="col-span-2">
                            <label class="text-xs font-semibold text-gray-600 mb-1.5 block">Entry Date</label>
                            <div class="relative">
                                <input type="date" id="modalEntryDate" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 pr-8">
                                <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 pointer-events-none">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Country (Row 2) -->
                        <div class="col-span-2">
                            <label class="text-xs font-semibold text-gray-600 mb-1.5 block">Country</label>
                            <div class="relative">
                                <input type="text" id="modalCountry" value="Indonesia" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 pr-8">
                                <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Valid To -->
                        <div class="col-span-2 col-start-5">
                            <label class="text-xs font-semibold text-gray-600 mb-1.5 block">Valid To</label>
                            <div class="relative">
                                <input type="date" id="modalValidTo" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 pr-8">
                                <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 pointer-events-none">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Attachment Link Tab -->
                <div id="tab-attachment" class="identification-tab-content hidden">
                    <h4 class="text-base font-semibold text-gray-900 mb-4">Attachment Link</h4>
                    
                    <div class="space-y-4">
                        <div class="flex flex-col">
                            <label class="text-xs font-semibold text-gray-600 mb-1.5">Drive Link</label>
                            <input type="url" id="modalDriveLink" placeholder="https://drive.google.com/..." class="px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                        </div>

                        <div class="flex flex-col">
                            <label class="text-xs font-semibold text-gray-600 mb-1.5">Verify Link</label>
                            <input type="url" id="modalVerifyLink" placeholder="https://..." class="px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                        </div>
                    </div>
                </div>

                <div class="flex gap-3 justify-end mt-6 pt-6 border-t border-gray-200">
                    <button type="button" onclick="closeIdentificationModal()" class="px-5 py-2.5 bg-white border border-gray-300 text-gray-700 text-sm font-semibold rounded-lg hover:bg-gray-50 transition-all">
                        Cancel
                    </button>
                    <button type="button" onclick="saveIdentificationFromModal()" class="px-5 py-2.5 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900 transition-all">
                        Apply
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="confirmDeleteIdentificationModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-md w-full shadow-2xl">
        <div class="p-6">
            <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 bg-red-100 rounded-full">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-red-600">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-900 text-center mb-2">Delete Identification</h3>
            <p class="text-sm text-gray-600 text-center mb-1">Are you sure you want to delete this identification?</p>
            <p class="text-sm font-semibold text-gray-900 text-center mb-6" id="deleteIdentificationInfo"></p>
            <div class="flex gap-3">
                <button onclick="closeConfirmDeleteIdentification()" class="flex-1 px-4 py-2.5 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">Cancel</button>
                <button onclick="confirmDeleteIdentification()" class="flex-1 px-4 py-2.5 bg-red-600 text-white text-sm font-semibold rounded-lg hover:bg-red-700 transition-all">Delete</button>
            </div>
        </div>
    </div>
</div>

<script>
    let identificationsData = [];
    let selectedIdentificationId = null;
    let deleteIdentificationId = null;
    let isEditMode = false;

    /**
     * Load all identifications
     */
    async function loadIdentifications() {
        try {
            console.log('📡 Loading identifications for customer:', {{ $customerId }});
            
            const response = await fetch(`/api/customers/{{ $customerId }}/identifications`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin'
            });

            const data = await response.json();

            if (data.success && data.data && data.data.length > 0) {
                identificationsData = data.data;
                renderIdentificationTable(data.data);
            } else {
                identificationsData = [];
                renderEmptyIdentificationTable();
            }
        } catch (error) {
            console.error('❌ Error loading identifications:', error);
            identificationsData = [];
            renderEmptyIdentificationTable();
        }
    }

    /**
     * Render identification table
     */
    function renderIdentificationTable(identifications) {
        const tbody = document.getElementById('identificationTableBody');
        
        tbody.innerHTML = identifications.map(identification => {
            const validFrom = identification.valid_from ? new Date(identification.valid_from).toLocaleDateString('en-GB') : '';
            const validTo = identification.valid_to ? new Date(identification.valid_to).toLocaleDateString('en-GB') : '';

            return `
                <tr class="hover:bg-gray-50 transition-colors cursor-pointer" onclick="selectIdentificationRow(${identification.identification_id}, event)">
                    <td class="px-4 py-3">
                        <input type="radio" name="selectedIdentification" value="${identification.identification_id}" 
                            onclick="selectIdentification(${identification.identification_id})" 
                            class="w-4 h-4 text-red-800 focus:ring-red-800">
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-900">${identification.identification_type || '-'}</td>
                    <td class="px-4 py-3 text-sm text-gray-900">${identification.identification_number || '-'}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${identification.responsible_institution || '-'}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${validFrom}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${validTo}</td>
                    <td class="px-4 py-3">
                        <button onclick="loadIdentificationToForm(${identification.identification_id}); event.stopPropagation();" class="text-gray-400 hover:text-gray-600">
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
    function renderEmptyIdentificationTable() {
        const tbody = document.getElementById('identificationTableBody');
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="px-4 py-16 text-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 mx-auto mb-4 text-gray-300">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Zm6-10.125a1.875 1.875 0 1 1-3.75 0 1.875 1.875 0 0 1 3.75 0Zm1.294 6.336a6.721 6.721 0 0 1-3.17.789 6.721 6.721 0 0 1-3.168-.789 3.376 3.376 0 0 1 6.338 0Z" />
                    </svg>
                    <p class="text-base font-medium text-gray-900 mb-2">No identification documents found</p>
                    <small class="text-sm text-gray-500">Click "Create" to add a new identification</small>
                </td>
            </tr>
        `;
    }

    /**
     * Select identification
     */
    function selectIdentification(identificationId) {
        selectedIdentificationId = identificationId;
        loadIdentificationToForm(identificationId);
    }

    /**
     * Select identification row
     */
    function selectIdentificationRow(identificationId, event) {
        if (event.target.tagName === 'INPUT' || event.target.tagName === 'BUTTON' || event.target.closest('button')) {
            return;
        }
        
        const radio = document.querySelector(`input[name="selectedIdentification"][value="${identificationId}"]`);
        if (radio) {
            radio.checked = true;
            selectIdentification(identificationId);
        }
    }

    /**
     * Load identification to form (top form view only)
     */
    async function loadIdentificationToForm(identificationId) {
        try {
            const response = await fetch(`/api/customers/{{ $customerId }}/identifications/${identificationId}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin'
            });

            const data = await response.json();

            if (data.success && data.data) {
                const identification = data.data;
                
                // Update top form (view only)
                document.getElementById('idType').value = identification.identification_type || '';
                document.getElementById('identificationNumber').value = identification.identification_number || '';
                document.getElementById('responsibleInstitution').value = identification.responsible_institution || '';
                document.getElementById('validFrom').value = identification.valid_from ? new Date(identification.valid_from).toLocaleDateString('en-GB') : '';
            }
        } catch (error) {
            console.error('❌ Error loading identification:', error);
        }
    }

    /**
     * Switch identification tab
     */
    function switchIdentificationTab(tabName) {
        // Hide all tab contents
        document.querySelectorAll('.identification-tab-content').forEach(content => {
            content.classList.add('hidden');
        });

        // Remove active state from all tabs
        document.querySelectorAll('.identification-tab').forEach(tab => {
            tab.classList.remove('border-red-800', 'text-red-800');
            tab.classList.add('border-transparent', 'text-gray-600');
        });

        // Show selected tab content
        document.getElementById('tab-' + tabName).classList.remove('hidden');

        // Add active state to selected tab
        const activeTab = document.querySelector(`.identification-tab[data-tab="${tabName}"]`);
        activeTab.classList.remove('border-transparent', 'text-gray-600');
        activeTab.classList.add('border-red-800', 'text-red-800');
    }

    /**
     * Open create identification modal
     */
    function openCreateIdentificationModal() {
        isEditMode = false;
        document.getElementById('identificationModalTitle').textContent = 'New Identification Number';
        document.getElementById('identificationForm').reset();
        document.getElementById('editIdentificationId').value = '';
        document.getElementById('modalCountry').value = 'Indonesia';
        
        // Switch to first tab
        switchIdentificationTab('general');
        
        document.getElementById('identificationModal').classList.remove('hidden');
        document.getElementById('identificationModal').classList.add('flex');
    }

    /**
     * Open edit identification modal
     */
    async function openEditIdentificationModal() {
        if (!selectedIdentificationId) {
            showNotification('Please select an identification first', 'warning');
            return;
        }

        isEditMode = true;
        document.getElementById('identificationModalTitle').textContent = 'Edit Identification Number';
        
        try {
            const response = await fetch(`/api/customers/{{ $customerId }}/identifications/${selectedIdentificationId}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin'
            });

            const data = await response.json();

            if (data.success && data.data) {
                const identification = data.data;
                
                // Fill modal form with existing data
                document.getElementById('editIdentificationId').value = identification.identification_id;
                document.getElementById('modalIdType').value = identification.identification_type || '';
                document.getElementById('modalIdentificationNumber').value = identification.identification_number || '';
                document.getElementById('modalResponsibleInstitution').value = identification.responsible_institution || '';
                document.getElementById('modalRegion').value = identification.region || '';
                document.getElementById('modalCountry').value = identification.country || 'Indonesia';
                document.getElementById('modalEntryDate').value = identification.entry_date || '';
                document.getElementById('modalValidFrom').value = identification.valid_from || '';
                document.getElementById('modalValidTo').value = identification.valid_to || '';
                document.getElementById('modalDriveLink').value = identification.drive_link || '';
                document.getElementById('modalVerifyLink').value = identification.verify_link || '';
                
                // Switch to first tab
                switchIdentificationTab('general');
                
                document.getElementById('identificationModal').classList.remove('hidden');
                document.getElementById('identificationModal').classList.add('flex');
            }
        } catch (error) {
            console.error('❌ Error loading identification for edit:', error);
            showNotification('Failed to load identification data', 'error');
        }
    }

    /**
     * Close identification modal
     */
    function closeIdentificationModal() {
        document.getElementById('identificationModal').classList.add('hidden');
        document.getElementById('identificationModal').classList.remove('flex');
        isEditMode = false;
    }

    /**
     * Save identification from modal (CREATE or UPDATE)
     */
    async function saveIdentificationFromModal() {
        const form = document.getElementById('identificationForm');
        
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const identificationData = {
            identification_type: document.getElementById('modalIdType').value,
            identification_number: document.getElementById('modalIdentificationNumber').value,
            responsible_institution: document.getElementById('modalResponsibleInstitution').value,
            region: document.getElementById('modalRegion').value,
            country: document.getElementById('modalCountry').value,
            entry_date: document.getElementById('modalEntryDate').value,
            valid_from: document.getElementById('modalValidFrom').value,
            valid_to: document.getElementById('modalValidTo').value,
            drive_link: document.getElementById('modalDriveLink').value,
            verify_link: document.getElementById('modalVerifyLink').value,
        };

        try {
            let url, method, successMessage;
            
            if (isEditMode) {
                // UPDATE
                const identificationId = document.getElementById('editIdentificationId').value;
                url = `/api/customers/{{ $customerId }}/identifications/${identificationId}`;
                method = 'PUT';
                successMessage = 'Identification updated successfully!';
            } else {
                // CREATE
                url = `/api/customers/{{ $customerId }}/identifications`;
                method = 'POST';
                successMessage = 'Identification created successfully!';
            }

            const response = await fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                credentials: 'same-origin',
                body: JSON.stringify(identificationData)
            });

            const data = await response.json();
            
            if (data.success) {
                showNotification(successMessage, 'success');
                closeIdentificationModal();
                loadIdentifications();
                
                // If we just updated, refresh the top form
                if (isEditMode) {
                    loadIdentificationToForm(selectedIdentificationId);
                }
            } else {
                showNotification('Failed to save: ' + (data.message || 'Unknown error'), 'error');
            }
        } catch (error) {
            console.error('❌ Error saving identification:', error);
            showNotification('An error occurred while saving', 'error');
        }
    }

    /**
     * Copy selected identification
     */
    async function copySelectedIdentification() {
        if (!selectedIdentificationId) {
            showNotification('Please select an identification first', 'warning');
            return;
        }

        try {
            // Fetch the selected identification data
            const response = await fetch(`/api/customers/{{ $customerId }}/identifications/${selectedIdentificationId}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin'
            });

            const data = await response.json();

            if (data.success && data.data) {
                const identification = data.data;
                
                // Create a copy with modified number
                const copyData = {
                    identification_type: identification.identification_type,
                    identification_number: identification.identification_number + ' (Copy)',
                    responsible_institution: identification.responsible_institution,
                    region: identification.region,
                    country: identification.country,
                    entry_date: identification.entry_date,
                    valid_from: identification.valid_from,
                    valid_to: identification.valid_to,
                    drive_link: identification.drive_link,
                    verify_link: identification.verify_link,
                };

                // Create the copy
                const createResponse = await fetch(`/api/customers/{{ $customerId }}/identifications`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(copyData)
                });

                const createData = await createResponse.json();
                
                if (createData.success) {
                    showNotification('Identification copied successfully!', 'success');
                    loadIdentifications();
                } else {
                    showNotification('Failed to copy: ' + (createData.message || 'Unknown error'), 'error');
                }
            }
        } catch (error) {
            console.error('❌ Error copying identification:', error);
            showNotification('An error occurred while copying', 'error');
        }
    }

    /**
     * Delete selected identification
     */
    function deleteSelectedIdentification() {
        if (!selectedIdentificationId) {
            showNotification('Please select an identification first', 'warning');
            return;
        }
        
        deleteIdentificationId = selectedIdentificationId;
        
        const identification = identificationsData.find(i => i.identification_id === selectedIdentificationId);
        let identificationInfo = 'this identification';
        
        if (identification) {
            identificationInfo = `${identification.identification_type || 'Unknown'} - ${identification.identification_number || ''}`;
        }
        
        document.getElementById('deleteIdentificationInfo').textContent = identificationInfo;
        document.getElementById('confirmDeleteIdentificationModal').classList.remove('hidden');
        document.getElementById('confirmDeleteIdentificationModal').classList.add('flex');
    }

    /**
     * Close delete confirmation
     */
    function closeConfirmDeleteIdentification() {
        document.getElementById('confirmDeleteIdentificationModal').classList.add('hidden');
        document.getElementById('confirmDeleteIdentificationModal').classList.remove('flex');
        deleteIdentificationId = null;
    }

    /**
     * Confirm delete identification
     */
    async function confirmDeleteIdentification() {
        if (!deleteIdentificationId) return;

        try {
            const response = await fetch(`/api/customers/{{ $customerId }}/identifications/${deleteIdentificationId}`, {
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
                showNotification('Identification deleted successfully!', 'success');
                closeConfirmDeleteIdentification();
                selectedIdentificationId = null;
                loadIdentifications();
                
                // Clear form fields
                document.getElementById('idType').value = '';
                document.getElementById('identificationNumber').value = '';
                document.getElementById('responsibleInstitution').value = '';
                document.getElementById('validFrom').value = '';
            } else {
                showNotification('Failed to delete: ' + (data.message || 'Unknown error'), 'error');
            }
        } catch (error) {
            console.error('❌ Error deleting identification:', error);
            showNotification('An error occurred while deleting', 'error');
        }
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
        console.log('🚀 Identification section initialized');
        loadIdentifications();
    });

    // Close modals on outside click
    document.getElementById('identificationModal')?.addEventListener('click', function(e) {
        if (e.target === this) closeIdentificationModal();
    });

    document.getElementById('confirmDeleteIdentificationModal')?.addEventListener('click', function(e) {
        if (e.target === this) closeConfirmDeleteIdentification();
    });

    // Close modals on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (!document.getElementById('identificationModal').classList.contains('hidden')) {
                closeIdentificationModal();
            }
            if (!document.getElementById('confirmDeleteIdentificationModal').classList.contains('hidden')) {
                closeConfirmDeleteIdentification();
            }
        }
    });
</script>